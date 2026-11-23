<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ProductoRiesgo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Almacen > Productos en riesgo: lo contado en un inventario que vence en N dias
 * o menos (20 por defecto). Lista independiente: no modifica almacenes ni
 * registro_almacenes. Quitar nunca borra: pasa a QUITADO con quien y cuando.
 * Todo pide el permiso almacenRiesgo.
 */
class ProductoRiesgoController extends Controller
{
    /** "dias" guardado cuando se lleno con TODO el inventario (sin limite de vencimiento). */
    const TODOS = 9999;

    public function index(Request $request)
    {
        $this->autorizar($request);
        $estado = $request->input('estado', 'ACTIVO');   // ACTIVO | QUITADO | TODOS
        $q = trim((string) $request->input('q', ''));

        // Solo la ultima lista generada; las anteriores quedan en la tabla pero no se ven.
        $generacion = ProductoRiesgo::generacionActual();
        $base = function () use ($generacion) {
            return ProductoRiesgo::query()->where('generacion', $generacion ?: 0);
        };

        $query = $base();
        if (in_array($estado, ['ACTIVO', 'QUITADO'], true)) {
            $query->where('estado', $estado);
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('producto', 'like', "%$q%")->orWhere('codigo_producto', 'like', "%$q%")
                    ->orWhere('lote', 'like', "%$q%")->orWhere('grupo', 'like', "%$q%")
                    ->orWhere('observacion', 'like', "%$q%")->orWhere('comentario', 'like', "%$q%")
                    ->orWhere('agregado_por_nombre', 'like', "%$q%")->orWhere('quitado_por_nombre', 'like', "%$q%");
            });
        }
        // Lo que vence antes primero (vencidos arriba).
        $query->orderByRaw('fecha_vencimiento IS NULL')->orderBy('fecha_vencimiento')->orderBy('producto')->orderByDesc('id');

        $perPage = min(max((int) $request->input('per_page', 25), 1), 200);
        $page = $query->paginate($perPage);

        // Foto actual del producto (tbproductos.imagen); MySQL ignora los espacios finales de cod_prod.
        $codigos = $page->getCollection()->pluck('codigo_producto')->filter()->unique()->values()->all();
        $imagenes = [];
        if ($codigos) {
            foreach (Producto::withTrashed()->whereIn('cod_prod', $codigos)->get(['cod_prod', 'imagen']) as $p) {
                if ($p->imagen) $imagenes[$p->cod_prod] = $p->imagen;
            }
        }
        $page->getCollection()->transform(function ($row) use ($imagenes) {
            $row->imagen = isset($imagenes[trim((string) $row->codigo_producto)]) ? $imagenes[trim((string) $row->codigo_producto)] : null;
            return $row;
        });

        $primera = $generacion ? $base()->orderBy('id')->first(['fecha_inventario', 'dias', 'agregado_por_nombre', 'agregado_at']) : null;

        return response()->json($page->toArray() + [
            'totales' => [
                'ACTIVO' => $base()->where('estado', 'ACTIVO')->count(),
                'QUITADO' => $base()->where('estado', 'QUITADO')->count(),
            ],
            'ultimo_inventario' => $primera ? $primera->fecha_inventario : null,
            'generacion' => $primera ? [
                'numero' => $generacion,
                'fecha_inventario' => $primera->fecha_inventario,
                'dias' => $primera->dias,
                'generado_por' => $primera->agregado_por_nombre,
                'generado_at' => $primera->agregado_at,
            ] : null,
        ]);
    }

    /**
     * Conteos del inventario de $fecha que vencen en $dias dias o menos.
     * Con $dias = TODOS entra todo lo contado, tambien lo que no tiene vencimiento.
     */
    private function conteos($fecha, $dias)
    {
        $query = DB::table('registro_almacenes as r')
            ->join('almacenes as a', 'a.id', '=', 'r.almacen_id')
            ->leftJoin('personal as p', 'p.CodAut', '=', 'r.user_id')
            ->whereDate('a.fecha_registro', $fecha);
        if ($dias < self::TODOS) {
            $query->whereNotNull('r.fecha_vencimiento')
                ->whereDate('r.fecha_vencimiento', '<=', date('Y-m-d', strtotime("+$dias days")));
        }
        return $query
            ->where('r.cantidad', '>', 0)
            ->select('r.id', 'r.almacen_id', 'r.cantidad', 'r.fecha_vencimiento', 'r.lote', 'r.comentario',
                'a.codigo', 'a.codigo_producto', 'a.producto', 'a.unidad', 'a.grupo', 'a.fecha_registro',
                'p.Nombre1', 'p.App1')
            ->get();
    }

    /** Datos del conteo tal como se guardan en la lista. */
    private function datosConteo($c)
    {
        return [
            'almacen_id' => $c->almacen_id, 'registro_almacen_id' => $c->id,
            'fecha_inventario' => substr((string) $c->fecha_registro, 0, 10),
            'codigo' => $c->codigo, 'codigo_producto' => trim((string) $c->codigo_producto),
            'producto' => $c->producto, 'unidad' => $c->unidad, 'grupo' => $c->grupo,
            'lote' => $c->lote === 'null' ? null : $c->lote,
            'fecha_vencimiento' => $c->fecha_vencimiento, 'cantidad' => $c->cantidad,
            'comentario' => $c->comentario === 'null' ? null : $c->comentario,
            'contado_por' => trim(trim((string) $c->Nombre1) . ' ' . trim((string) $c->App1)),
        ];
    }

    /**
     * Vuelve a leer el inventario de la lista actual (misma fecha y dias) y la pone al dia:
     * entran los conteos nuevos, los activos toman los datos del conteo (salvo cantidad o
     * vencimiento corregidos a mano) y los que ya no estan en el inventario pasan a QUITADO.
     * Los quitados a mano siguen quitados y los comentarios no se tocan.
     */
    public function actualizar(Request $request)
    {
        $this->autorizar($request);
        $generacion = ProductoRiesgo::generacionActual();
        abort_unless($generacion, 422, 'Primero llene la lista con un inventario');
        $primera = ProductoRiesgo::where('generacion', $generacion)->orderBy('id')->first();
        $fecha = substr((string) $primera->fecha_inventario, 0, 10);
        $dias = $primera->dias !== null ? (int) $primera->dias : 20;

        $conteos = $this->conteos($fecha, $dias)->keyBy('id');
        $user = $request->user();
        $nombre = ProductoRiesgo::nombreUsuario($user);
        $ahora = now();
        $res = ['agregados' => 0, 'actualizados' => 0, 'quitados' => 0];

        DB::transaction(function () use ($generacion, $conteos, $dias, $user, $nombre, $ahora, &$res) {
            $existentes = ProductoRiesgo::where('generacion', $generacion)->get()->keyBy('registro_almacen_id');

            foreach ($existentes as $regId => $row) {
                if ($row->estado !== 'ACTIVO') continue;
                if (!$regId || !isset($conteos[$regId])) {
                    $row->update([
                        'estado' => 'QUITADO',
                        'quitado_por' => $user->CodAut, 'quitado_por_nombre' => $nombre, 'quitado_at' => $ahora,
                        'motivo_quitado' => 'YA NO ESTA EN EL INVENTARIO (ACTUALIZAR DATOS)',
                    ]);
                    $res['quitados']++;
                    continue;
                }
                $datos = $this->datosConteo($conteos[$regId]);
                // Lo corregido a mano en la lista manda sobre el conteo.
                if ($row->cantidad_original !== null) unset($datos['cantidad']);
                if ($row->fecha_vencimiento_original !== null) unset($datos['fecha_vencimiento']);
                $row->fill($datos);
                if ($row->isDirty()) {
                    $row->save();
                    $res['actualizados']++;
                }
            }

            $filas = [];
            foreach ($conteos as $regId => $c) {
                if (isset($existentes[$regId])) continue;
                $filas[] = $this->datosConteo($c) + [
                    'generacion' => $generacion, 'dias' => $dias, 'estado' => 'ACTIVO',
                    'agregado_por' => $user->CodAut, 'agregado_por_nombre' => $nombre, 'agregado_at' => $ahora,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ];
            }
            foreach (array_chunk($filas, 500) as $lote) {
                ProductoRiesgo::insert($lote);
            }
            $res['agregados'] = count($filas);
        });

        return response()->json($res);
    }

    /**
     * Llena la lista con el inventario de la fecha: todos los ACTIVO pasan a QUITADO
     * (quedan en el historial) y entra cada conteo con vencimiento en "dias" o menos.
     */
    public function generar(Request $request)
    {
        $this->autorizar($request);
        $request->validate(['fecha' => 'required|date', 'dias' => 'nullable|integer|min:0|max:365', 'todo' => 'nullable|boolean']);
        $fecha = $request->fecha;
        // "todo" (boton de Almacen): todo lo contado en el inventario, venza cuando venza.
        $dias = $request->boolean('todo') ? self::TODOS : ($request->filled('dias') ? (int) $request->dias : 20);
        $user = $request->user();
        $nombre = ProductoRiesgo::nombreUsuario($user);
        $ahora = now();

        $conteos = $this->conteos($fecha, $dias);

        if ($conteos->isEmpty()) {
            return response()->json(['message' => $dias >= self::TODOS
                ? "El inventario del $fecha no tiene conteos"
                : "El inventario del $fecha no tiene productos que venzan en $dias días o menos"], 422);
        }

        $creados = 0;
        $quitados = 0;
        DB::transaction(function () use ($conteos, $fecha, $dias, $user, $nombre, $ahora, &$creados, &$quitados) {
            // Cada llenado es una lista nueva; la pantalla solo muestra la ultima.
            $generacion = ((int) ProductoRiesgo::lockForUpdate()->max('generacion')) + 1;
            $quitados = ProductoRiesgo::where('estado', 'ACTIVO')->update([
                'estado' => 'QUITADO',
                'quitado_por' => $user->CodAut, 'quitado_por_nombre' => $nombre, 'quitado_at' => $ahora,
                'motivo_quitado' => "REEMPLAZADO POR EL INVENTARIO DEL $fecha",
                'updated_at' => $ahora,
            ]);
            $filas = [];
            foreach ($conteos as $c) {
                $filas[] = $this->datosConteo($c) + [
                    'generacion' => $generacion, 'dias' => $dias,
                    'estado' => 'ACTIVO',
                    'agregado_por' => $user->CodAut, 'agregado_por_nombre' => $nombre, 'agregado_at' => $ahora,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ];
            }
            foreach (array_chunk($filas, 500) as $lote) {
                ProductoRiesgo::insert($lote);
            }
            $creados = count($filas);
        });

        return response()->json(['agregados' => $creados, 'quitados' => $quitados]);
    }

    /**
     * Corrige cantidad y/o vencimiento de un producto activo (solo en esta lista,
     * no en el inventario). La primera vez guarda el valor que venia del conteo.
     */
    public function update(Request $request, $id)
    {
        $this->autorizar($request);
        $request->validate([
            'cantidad' => 'sometimes|required|numeric|min:0',
            'fecha_vencimiento' => 'sometimes|required|date',
            'observacion' => 'sometimes|nullable|string|max:500',
        ]);
        $row = ProductoRiesgo::findOrFail($id);
        $soloComentario = !$request->has('cantidad') && !$request->has('fecha_vencimiento');
        abort_if($row->estado !== 'ACTIVO' && !$soloComentario, 422, 'Solo se puede editar un producto activo');

        $cambios = [];
        if ($request->has('observacion')) {
            $obs = trim((string) $request->observacion);
            $obs = $obs === '' ? null : $obs;
            if ($obs !== $row->observacion) $cambios['observacion'] = $obs;
        }
        if ($request->has('cantidad') && (float) $request->cantidad !== (float) $row->cantidad) {
            if ($row->cantidad_original === null) $cambios['cantidad_original'] = $row->cantidad;
            $cambios['cantidad'] = round((float) $request->cantidad, 2);
        }
        $fecha = $request->has('fecha_vencimiento') ? substr($request->fecha_vencimiento, 0, 10) : null;
        if ($fecha && $fecha !== substr((string) $row->fecha_vencimiento, 0, 10)) {
            if ($row->fecha_vencimiento_original === null) $cambios['fecha_vencimiento_original'] = $row->fecha_vencimiento;
            $cambios['fecha_vencimiento'] = $fecha;
        }
        if ($cambios) {
            $cambios['editado_por'] = $request->user()->CodAut;
            $cambios['editado_por_nombre'] = ProductoRiesgo::nombreUsuario($request->user());
            $cambios['editado_at'] = now();
            $row->update($cambios);
        }
        return response()->json($row);
    }

    public function quitar(Request $request, $id)
    {
        $this->autorizar($request);
        $row = ProductoRiesgo::findOrFail($id);
        abort_if($row->estado !== 'ACTIVO', 422, 'Este producto ya fue quitado');
        $motivo = trim((string) $request->input('motivo', ''));
        $row->update([
            'estado' => 'QUITADO',
            'quitado_por' => $request->user()->CodAut,
            'quitado_por_nombre' => ProductoRiesgo::nombreUsuario($request->user()),
            'quitado_at' => now(),
            'motivo_quitado' => $motivo !== '' ? mb_strtoupper($motivo) : null,
        ]);
        return response()->json($row);
    }

    /** Vuelve a la lista un quitado: crea una fila nueva y la vieja queda en el historial. */
    public function reagregar(Request $request, $id)
    {
        $this->autorizar($request);
        $row = ProductoRiesgo::findOrFail($id);
        abort_if($row->estado !== 'QUITADO', 422, 'Este producto ya está activo');
        abort_if($row->generacion != ProductoRiesgo::generacionActual(), 422, 'Ese producto es de una lista anterior');
        $yaActivo = ProductoRiesgo::where('generacion', $row->generacion)->where('estado', 'ACTIVO')->where('codigo_producto', $row->codigo_producto)
            ->where('lote', $row->lote)->whereDate('fecha_vencimiento', $row->fecha_vencimiento)->exists();
        abort_if($yaActivo, 409, "{$row->producto} ya está en la lista de riesgo");
        $nueva = $row->replicate(['quitado_por', 'quitado_por_nombre', 'quitado_at', 'motivo_quitado']);
        $nueva->fill([
            'estado' => 'ACTIVO',
            'quitado_por' => null, 'quitado_por_nombre' => null, 'quitado_at' => null, 'motivo_quitado' => null,
            'agregado_por' => $request->user()->CodAut,
            'agregado_por_nombre' => ProductoRiesgo::nombreUsuario($request->user()),
            'agregado_at' => now(),
        ]);
        $nueva->save();
        return response()->json($nueva, 201);
    }

    /**
     * Excel de trabajo con el formato de la hoja de riesgo que usa almacen:
     * Deposito / Fecha conteo / Semana, leyenda de colores y una fila por producto
     * activo de la lista actual, ordenado por vencimiento.
     * CHECK: VENCIDO (sin fecha o ya vencido), RIESGO CRITICO (0 a 10 dias),
     * RIESGO (11 a 30), OK (mas de 30). Los dias se cuentan desde hoy.
     */
    public function excel(Request $request)
    {
        $this->autorizar($request);
        $generacion = ProductoRiesgo::generacionActual();
        abort_unless($generacion, 422, 'Primero llene la lista con un inventario');
        $deposito = mb_strtoupper(trim((string) $request->input('deposito', 'ORURO'))) ?: 'ORURO';

        $filas = ProductoRiesgo::where('generacion', $generacion)->where('estado', 'ACTIVO')
            ->orderByRaw('fecha_vencimiento IS NOT NULL')->orderBy('fecha_vencimiento')->orderBy('producto')->get();
        $fechaConteo = substr((string) optional($filas->first())->fecha_inventario, 0, 10)
            ?: substr((string) ProductoRiesgo::where('generacion', $generacion)->value('fecha_inventario'), 0, 10);
        $hoy = strtotime(date('Y-m-d'));

        $rojo = 'E53935'; $amarillo = 'FFEB3B'; $verde = '8BC34A'; $negro = '212121';
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Riesgo');
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $borde = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]];
        $encabezado = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rojo]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ];

        // Cabecera: Deposito, Fecha conteo, Semana.
        $cab = [['Depósito:', $deposito], ['Fecha conteo:', $fechaConteo ? date('d/m/Y', strtotime($fechaConteo)) : ''],
            ['Semana:', $fechaConteo ? (int) date('W', strtotime($fechaConteo)) : '']];
        foreach ($cab as $i => [$etiqueta, $valor]) {
            $f = $i + 1;
            $hoja->setCellValue("A$f", $etiqueta);
            $hoja->mergeCells("B$f:C$f");
            $hoja->setCellValue("B$f", $valor);
            $hoja->getStyle("A$f")->applyFromArray($encabezado)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $hoja->getStyle("B$f:C$f")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $hoja->getStyle('A1:C3')->applyFromArray($borde);

        // Leyenda de colores.
        foreach ([[$rojo, 'DE 1 A 10 DIAS', '10%'], [$amarillo, 'DE 11 A 30 DIAS', '45%'], [$verde, 'MAS DE 30 DIAS', '100%']] as $i => [$color, $texto, $pct]) {
            $f = $i + 1;
            $hoja->getStyle("E$f")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
            $hoja->setCellValue("F$f", $texto);
            $hoja->setCellValue("G$f", $pct);
            $hoja->getStyle("G$f")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $hoja->getStyle('E1:G3')->applyFromArray($borde);

        // Tabla.
        $titulos = ['CÓDIGO DE PRODUCTO', 'SKU', 'CHECK', 'FECHA DE VENCIMIENTO', 'PAQ /KG', 'DIAS HABILES DE VENTA', 'OBSERVACIONES'];
        $hoja->fromArray($titulos, null, 'A5');
        $hoja->getStyle('A5:G5')->applyFromArray($encabezado);
        $hoja->getRowDimension(5)->setRowHeight(30);

        $f = 6;
        foreach ($filas as $r) {
            $venc = $r->fecha_vencimiento ? substr((string) $r->fecha_vencimiento, 0, 10) : null;
            $dias = $venc ? (int) round((strtotime($venc) - $hoy) / 86400) : null;
            if ($dias === null || $dias < 0) {
                [$check, $fondo, $letra] = ['VENCIDO', $negro, 'FFFFFF'];
            } elseif ($dias <= 10) {
                [$check, $fondo, $letra] = ['RIESGO CRITICO', $rojo, 'FFFFFF'];
            } elseif ($dias <= 30) {
                [$check, $fondo, $letra] = ['RIESGO', $amarillo, '000000'];
            } else {
                [$check, $fondo, $letra] = ['OK', $verde, '000000'];
            }
            $obs = implode(' · ', array_filter([trim((string) $r->observacion), trim((string) $r->comentario), $r->lote ? 'LOTE ' . $r->lote : '']));

            $hoja->setCellValueExplicit("A$f", trim((string) $r->codigo_producto), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $hoja->setCellValue("B$f", $r->producto);
            $hoja->setCellValue("C$f", $check);
            $hoja->setCellValue("D$f", $venc ? date('d/m/Y', strtotime($venc)) : '');
            $hoja->setCellValue("E$f", (float) $r->cantidad);
            $hoja->setCellValue("F$f", $dias === null ? '' : $dias);
            $hoja->setCellValue("G$f", $obs);
            $hoja->getStyle("C$f")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fondo);
            $hoja->getStyle("C$f")->getFont()->getColor()->setRGB($letra);
            $hoja->getStyle("D$f")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($check === 'VENCIDO' ? 'FFFFFF' : $fondo);
            $hoja->getStyle("D$f")->getFont()->getColor()->setRGB($check === 'RIESGO CRITICO' ? 'FFFFFF' : '000000');
            $f++;
        }
        $ultima = max($f - 1, 5);
        $hoja->getStyle("A5:G$ultima")->applyFromArray($borde);
        $hoja->getStyle("A6:A$ultima")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle("D6:F$ultima")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle("E6:E$ultima")->getNumberFormat()->setFormatCode('#,##0.##');

        foreach (['A' => 14, 'B' => 50, 'C' => 16, 'D' => 14, 'E' => 10, 'F' => 12, 'G' => 40] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
        $hoja->freezePane('A6');
        $hoja->setAutoFilter("A5:G$ultima");
        $hoja->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);

        $nombre = 'productos_riesgo_' . ($fechaConteo ?: date('Y-m-d')) . '.xlsx';
        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function autorizar(Request $request)
    {
        abort_unless($request->user() && $request->user()->can('almacenRiesgo'), 403,
            'No tiene permiso para productos en riesgo');
    }
}
