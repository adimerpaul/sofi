<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Avance de ventas del mes (permiso avanceventas).
 *
 * Lo vendido sale de lo facturado: facturas ACTIVO del mes (una modificacion o
 * un retorno parcial dejan el anterior ANULADO, asi cada venta cuenta una vez)
 * con su detalle. El volumen va siempre en kg, como en el detalle de ventas:
 * lo pesado si la linea lo tiene (pollo, cerdo, caja); si va por KG, la
 * cantidad; si va por unidad, cantidad x peso de la unidad (tbproductos.CantPren),
 * ej. 2 sachets de hamburguesa 2X113 GRS = 2 x 0.226 kg. La unica excepcion es
 * la linea marcada por_cantidad (HUEVO), que suma la cantidad vendida.
 *
 * Cada producto cae en una linea del formato (avance_lineas) por su codigo o,
 * si no, por su grupo. Lo que no esta en ninguna linea no entra al avance.
 * Cobertura: cuantos clientes distintos compraron la linea, la seccion o algo.
 */
class AvanceVentasController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!$request->user() || !$request->user()->can('avanceventas')) {
                return response()->json(['message' => 'No tiene permiso de avance de ventas'], 403);
            }

            return $next($request);
        })->except(['miAvance', 'miExcel']);

        // Avance de ventas vendedores (permiso avanceventasvendedores): el vendedor ve solo lo suyo.
        $this->middleware(function ($request, $next) {
            if (!$request->user() || !$request->user()->can('avanceventasvendedores')) {
                return response()->json(['message' => 'No tiene permiso de ver su avance de ventas'], 403);
            }

            return $next($request);
        })->only(['miAvance', 'miExcel']);
    }

    /**
     * El avance del usuario logueado: sus ventas (facturas con su CI) contra
     * los objetivos que le pusieron. No manda nada de los demas vendedores.
     */
    public function miAvance(Request $request)
    {
        return $this->soloVendedor($this->datos($this->mes($request)), $request->user());
    }

    public function miExcel(Request $request)
    {
        $mes = $this->mes($request);
        $datos = $this->soloVendedor($this->datos($mes), $request->user());
        $v = $datos['vendedores'][0];
        $titulo = 'AVANCE DE VENTAS ' . mb_strtoupper(Carbon::createFromFormat('Y-m', $mes)->locale('es')->isoFormat('MMMM YYYY'))
            . ' - ' . mb_strtoupper($v['nombre']);

        $libro = new Spreadsheet();
        $libro->removeSheetByIndex(0);
        $this->hojaFormato($libro, $v['nombre'], $titulo, $datos, $v['clave'], $v['id']);
        $libro->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, 'mi_avance_' . $mes . '.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Deja en los datos solo al vendedor (aunque no haya vendido nada todavia). */
    private function soloVendedor(array $datos, $usuario)
    {
        $clave = 'v' . $usuario->CodAut;
        $vendedor = collect($datos['vendedores'])->firstWhere('clave', $clave) ?: [
            'clave' => $clave,
            'id' => (int) $usuario->CodAut,
            'ci' => trim((string) $usuario->ci),
            'nombre' => trim(trim((string) $usuario->Nombre1) . ' ' . trim((string) $usuario->App1)),
        ];
        $id = (int) $usuario->CodAut;

        $datos['vendedores'] = [$vendedor];
        $datos['objetivos'] = (object) (isset($datos['objetivos']->{$id}) ? [$id => $datos['objetivos']->{$id}] : []);
        $datos['resultados'] = (object) (isset($datos['resultados']->{$clave}) ? [$clave => $datos['resultados']->{$clave}] : []);

        return $datos;
    }

    public function index(Request $request)
    {
        return $this->datos($this->mes($request));
    }

    /** Guarda los objetivos de un mes para el general (vendedor_id 0) o un vendedor. */
    public function guardarObjetivos(Request $request)
    {
        $datos = $request->validate([
            'mes' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'vendedor_id' => 'required|integer|min:0',
            'objetivos' => 'required|array',
            'objetivos.*.linea_id' => 'required|integer|exists:avance_lineas,id',
            'objetivos.*.objetivo' => 'nullable|numeric|min:0',
        ]);

        $ahora = now();
        $usuario = $request->user()->CodAut;
        DB::transaction(function () use ($datos, $ahora, $usuario) {
            foreach ($datos['objetivos'] as $o) {
                $clave = ['mes' => $datos['mes'], 'linea_id' => $o['linea_id'], 'vendedor_id' => $datos['vendedor_id']];
                $valor = round((float) ($o['objetivo'] ?? 0), 2);
                if ($valor <= 0) {
                    DB::table('avance_objetivos')->where($clave)->delete();
                    continue;
                }
                $existe = DB::table('avance_objetivos')->where($clave)->exists();
                if ($existe) {
                    DB::table('avance_objetivos')->where($clave)
                        ->update(['objetivo' => $valor, 'user_id' => $usuario, 'updated_at' => $ahora]);
                } else {
                    DB::table('avance_objetivos')->insert($clave + [
                        'objetivo' => $valor, 'user_id' => $usuario, 'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                }
            }
        });

        return $this->datos($datos['mes']);
    }

    /** Grupos de tbproductos para armar las lineas. */
    public function grupos()
    {
        return DB::table('tbgrupos')
            ->orderBy('Descripcion')
            ->get([DB::raw('TRIM(Cod_grup) as cod_grup'), DB::raw('TRIM(Descripcion) as grupo')]);
    }

    /** Productos para asignar sueltos a una linea (ej. MAYONESA, LECHE). */
    public function productos(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        return DB::table('tbproductos')
            ->whereNull('deleted_at')
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('cod_prod', 'like', "%$q%")->orWhere('Producto', 'like', "%$q%");
                });
            })
            ->orderBy('Producto')
            ->limit(50)
            ->get([DB::raw('TRIM(cod_prod) as cod_prod'), DB::raw('TRIM(Producto) as producto')]);
    }

    /** Cambia los grupos y productos que junta una linea. */
    public function actualizarLinea(Request $request, $id)
    {
        $datos = $request->validate([
            'grupos' => 'nullable|array',
            'grupos.*' => 'string|max:20',
            'productos' => 'nullable|array',
            'productos.*' => 'string|max:30',
        ]);
        $limpiar = function ($lista) {
            $lista = array_values(array_unique(array_filter(array_map('trim', $lista ?? []), 'strlen')));
            return $lista ? implode(',', $lista) : null;
        };
        $n = DB::table('avance_lineas')->where('id', $id)->update([
            'grupos' => $limpiar($datos['grupos'] ?? []),
            'productos' => $limpiar($datos['productos'] ?? []),
            'updated_at' => now(),
        ]);
        if (!$n && !DB::table('avance_lineas')->where('id', $id)->exists()) {
            return response()->json(['message' => 'Linea no encontrada'], 404);
        }

        return ['ok' => true];
    }

    public function excel(Request $request)
    {
        $mes = $this->mes($request);
        $datos = $this->datos($mes);
        $titulo = 'AVANCE DE VENTAS ' . mb_strtoupper(Carbon::createFromFormat('Y-m', $mes)->locale('es')->isoFormat('MMMM YYYY'));

        $libro = new Spreadsheet();
        $libro->removeSheetByIndex(0);

        $this->hojaFormato($libro, 'General', $titulo . ' - GENERAL', $datos, 'g', 0);
        $this->hojaVendedores($libro, $titulo, $datos);
        foreach ($datos['vendedores'] as $v) {
            $this->hojaFormato($libro, $v['nombre'], $titulo . ' - ' . mb_strtoupper($v['nombre']), $datos, $v['clave'], $v['id']);
        }
        $libro->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, 'avance_ventas_' . $mes . '.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    // ------------------------------------------------------------------

    private function mes(Request $request)
    {
        $mes = (string) $request->get('mes', '');
        return preg_match('/^\d{4}-\d{2}$/', $mes) ? $mes : date('Y-m');
    }

    /**
     * Avance que se espera al dia, como en la planilla AVANCE (K11 = dia / dias
     * del mes): el mes en curso va por el dia de hoy, uno pasado al 100% y uno
     * futuro en 0.
     */
    private function ritmo($mes)
    {
        $inicio = Carbon::createFromFormat('Y-m-d', $mes . '-01')->startOfDay();
        $dias = $inicio->daysInMonth;
        $hoy = Carbon::today();
        if ($hoy->format('Y-m') === $mes) {
            $dia = $hoy->day;
        } else {
            $dia = $hoy->lt($inicio) ? 0 : $dias;
        }

        return ['dia' => $dia, 'dias' => $dias, 'objetivo' => $dias ? $dia / $dias : 0];
    }

    /**
     * Color del avance como en la planilla AVANCE: verde si llega al objetivo
     * del dia, amarillo si le falta hasta 3/28 (unos 10,7 puntos), rojo si le
     * falta mas. [fondo, letra] o null sin avance.
     */
    public static function colorAvance($avance, $objetivoDia)
    {
        if ($avance === null) return null;
        if ($avance >= $objetivoDia - 0.0005) return ['C6EFCE', '006100'];
        if ($avance >= $objetivoDia - 3 / 28) return ['FFEB9C', '9C5700'];
        return ['FFC7CE', '9C0006'];
    }

    private function pintarAvance($hoja, $celda, $avance, $objetivoDia)
    {
        $color = self::colorAvance($avance, $objetivoDia);
        if (!$color) return;
        $hoja->getStyle($celda)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $color[1]]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color[0]]],
        ]);
    }

    private function datos($mes)
    {
        $desde = $mes . '-01';
        $hasta = Carbon::createFromFormat('Y-m-d', $desde)->endOfMonth()->format('Y-m-d');

        $lineas = DB::table('avance_lineas')->orderBy('orden')->orderBy('id')->get();
        $porProducto = [];
        $porGrupo = [];
        foreach ($lineas as $l) {
            foreach (array_filter(explode(',', (string) $l->productos)) as $cod) $porProducto[trim($cod)] = $l->id;
            foreach (array_filter(explode(',', (string) $l->grupos)) as $grupo) $porGrupo[trim($grupo)] = $l->id;
        }
        $seccionDe = $lineas->pluck('seccion', 'id');
        // Lineas que suman la cantidad vendida en vez de los kg (HUEVO).
        $porCantidad = $lineas->filter(function ($l) { return !empty($l->por_cantidad); })->pluck('id')->flip();

        $filas = DB::select(
            "SELECT TRIM(f.vendedor_ci) AS ci, f.cliente_id, TRIM(d.cod_prod) AS cod_prod, TRIM(p.cod_grup) AS cod_grup,
                    SUM(CASE WHEN COALESCE(d.peso, 0) > 0 THEN d.peso
                             WHEN UPPER(TRIM(d.unidad)) = 'KG' THEN d.cantidad
                             ELSE d.cantidad * COALESCE(p.CantPren, 0) END) AS vol,
                    SUM(d.cantidad) AS cant,
                    SUM(d.subtotal) AS bs
             FROM facturas f
             JOIN factura_detalles d ON d.factura_id = f.id AND d.deleted_at IS NULL
             LEFT JOIN tbproductos p ON p.cod_prod = d.cod_prod
             WHERE f.estado = 'ACTIVO' AND f.deleted_at IS NULL AND f.fecha BETWEEN ? AND ?
             GROUP BY TRIM(f.vendedor_ci), f.cliente_id, TRIM(d.cod_prod), TRIM(p.cod_grup)",
            [$desde, $hasta]
        );

        // Vendedores (personal) por CI.
        $cis = array_values(array_unique(array_filter(array_map(function ($f) { return $f->ci; }, $filas))));
        $personal = $cis
            ? DB::table('personal')->whereIn(DB::raw('TRIM(ci)'), $cis)
                ->get(['CodAut', DB::raw('TRIM(ci) as ci'), DB::raw("TRIM(CONCAT(TRIM(Nombre1), ' ', TRIM(App1))) as nombre")])
                ->keyBy('ci')
            : collect();

        $objetivosFilas = DB::table('avance_objetivos')->where('mes', $mes)->get();
        $objetivos = [];
        foreach ($objetivosFilas as $o) {
            $objetivos[(int) $o->vendedor_id][$o->linea_id] = (float) $o->objetivo;
        }

        // Acumulado por alcance: 'g' general y uno por vendedor.
        $acum = [];
        $vendedores = [];
        $sumar = function ($clave, $lineaId, $seccion, $cliente, $vol, $bs) use (&$acum) {
            $a = &$acum[$clave];
            foreach ([['lineas', $lineaId], ['secciones', $seccion], ['total', 'total']] as [$nivel, $k]) {
                $n = &$a[$nivel][$k];
                $n['vol'] = ($n['vol'] ?? 0) + $vol;
                $n['bs'] = ($n['bs'] ?? 0) + $bs;
                $n['clientes'][$cliente] = true;
                unset($n);
            }
            unset($a);
        };
        foreach ($filas as $f) {
            $lineaId = $porProducto[$f->cod_prod] ?? ($porGrupo[$f->cod_grup] ?? null);
            if (!$lineaId) continue;
            $seccion = $seccionDe[$lineaId];
            $vol = isset($porCantidad[$lineaId]) ? (float) $f->cant : (float) $f->vol;
            $bs = (float) $f->bs;
            $sumar('g', $lineaId, $seccion, $f->cliente_id, $vol, $bs);

            $p = $personal[$f->ci] ?? null;
            $clave = $p ? 'v' . $p->CodAut : 'c' . ($f->ci ?: 'SIN');
            if (!isset($vendedores[$clave])) {
                $vendedores[$clave] = [
                    'clave' => $clave,
                    'id' => $p ? (int) $p->CodAut : 0,
                    'ci' => $f->ci,
                    'nombre' => $p ? $p->nombre : ('CI ' . ($f->ci ?: 'sin vendedor')),
                ];
            }
            $sumar($clave, $lineaId, $seccion, $f->cliente_id, $vol, $bs);
        }

        // Vendedores con objetivo pero todavia sin ventas.
        $sinVenta = array_diff(array_keys($objetivos), [0], array_column($vendedores, 'id'));
        if ($sinVenta) {
            foreach (DB::table('personal')->whereIn('CodAut', $sinVenta)
                         ->get(['CodAut', DB::raw('TRIM(ci) as ci'), DB::raw("TRIM(CONCAT(TRIM(Nombre1), ' ', TRIM(App1))) as nombre")]) as $p) {
                $vendedores['v' . $p->CodAut] = ['clave' => 'v' . $p->CodAut, 'id' => (int) $p->CodAut, 'ci' => $p->ci, 'nombre' => $p->nombre];
            }
        }

        // Los conjuntos de clientes se mandan como cantidad (cobertura).
        $resultados = [];
        foreach ($acum as $clave => $niveles) {
            foreach ($niveles as $nivel => $items) {
                foreach ($items as $k => $n) {
                    $resultados[$clave][$nivel][$k] = [
                        'vol' => round($n['vol'], 2),
                        'bs' => round($n['bs'], 2),
                        'cobertura' => count($n['clientes']),
                    ];
                }
            }
        }

        $vendedores = array_values($vendedores);
        usort($vendedores, function ($a, $b) use ($resultados) {
            return ($resultados[$b['clave']]['total']['total']['vol'] ?? 0) <=> ($resultados[$a['clave']]['total']['total']['vol'] ?? 0);
        });

        return [
            'mes' => $mes,
            'desde' => $desde,
            'hasta' => $hasta,
            'ritmo' => $this->ritmo($mes),
            'lineas' => $lineas->map(function ($l) {
                return [
                    'id' => $l->id,
                    'seccion' => $l->seccion,
                    'nombre' => $l->nombre,
                    'grupos' => array_values(array_filter(explode(',', (string) $l->grupos))),
                    'productos' => array_values(array_filter(explode(',', (string) $l->productos))),
                    'por_cantidad' => !empty($l->por_cantidad),
                ];
            })->values(),
            'vendedores' => $vendedores,
            'objetivos' => (object) $objetivos,
            'resultados' => (object) $resultados,
        ];
    }

    /**
     * Las filas del formato para un alcance: lineas, subtotal de cada seccion y
     * el avance total. En el general, la linea sin objetivo propio toma la suma
     * de los objetivos de los vendedores.
     */
    private function filas($datos, $clave, $vendedorId)
    {
        $res = $datos['resultados']->$clave ?? [];
        $obj = (array) ($datos['objetivos']->{$vendedorId} ?? []);
        $objVend = [];
        if ($clave === 'g') {
            foreach ((array) $datos['objetivos'] as $vid => $porLinea) {
                if ((int) $vid === 0) continue;
                foreach ($porLinea as $lid => $v) $objVend[$lid] = ($objVend[$lid] ?? 0) + $v;
            }
        }

        $filas = [];
        $secciones = [];
        foreach ($datos['lineas'] as $l) {
            $secciones[$l['seccion']][] = $l;
        }
        $totObj = 0;
        $totVol = 0;
        foreach ($secciones as $seccion => $lineas) {
            $secObj = 0;
            foreach ($lineas as $l) {
                $objetivo = $obj[$l['id']] ?? ($objVend[$l['id']] ?? 0);
                $r = $res['lineas'][$l['id']] ?? ['vol' => 0, 'bs' => 0, 'cobertura' => 0];
                $filas[] = ['tipo' => 'linea', 'nombre' => $l['nombre'], 'objetivo' => $objetivo, 'vol' => $r['vol'],
                    'bs' => $r['bs'], 'avance' => $objetivo > 0 ? $r['vol'] / $objetivo : null, 'cobertura' => $r['cobertura']];
                $secObj += $objetivo;
            }
            $r = $res['secciones'][$seccion] ?? ['vol' => 0, 'bs' => 0, 'cobertura' => 0];
            $filas[] = ['tipo' => 'seccion', 'nombre' => $seccion, 'objetivo' => $secObj, 'vol' => $r['vol'], 'bs' => $r['bs'],
                'avance' => $secObj > 0 ? $r['vol'] / $secObj : null, 'cobertura' => $r['cobertura']];
            $totObj += $secObj;
            $totVol += $r['vol'];
        }
        $r = $res['total']['total'] ?? ['vol' => 0, 'bs' => 0, 'cobertura' => 0];
        $filas[] = ['tipo' => 'total', 'nombre' => 'AVANCE TOTAL', 'objetivo' => $totObj, 'vol' => $r['vol'], 'bs' => $r['bs'],
            'avance' => $totObj > 0 ? $r['vol'] / $totObj : null, 'cobertura' => $r['cobertura']];

        return $filas;
    }

    private function hojaFormato(Spreadsheet $libro, $nombre, $titulo, $datos, $clave, $vendedorId)
    {
        $hoja = $libro->createSheet();
        $hoja->setTitle($this->nombreHoja($libro, $nombre));

        $hoja->setCellValue('A1', $titulo);
        $hoja->mergeCells('A1:F1');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $hoja->setCellValue('A2', 'Del ' . $datos['desde'] . ' al ' . $datos['hasta'] . ' · según lo facturado · volumen en kg (lo que va por unidad, por el peso de cada unidad)');
        $hoja->mergeCells('A2:F2');
        $hoja->getStyle('A2')->getFont()->setItalic(true)->setSize(9);
        $ritmo = $datos['ritmo'];
        $hoja->setCellValue('A3', 'OBJETIVO AL DIA (' . $ritmo['dia'] . '/' . $ritmo['dias'] . ')');
        $hoja->setCellValue('D3', $ritmo['objetivo']);
        $hoja->getStyle('A3:D3')->getFont()->setBold(true);
        $hoja->getStyle('D3')->getNumberFormat()->setFormatCode('0.0%');

        $enc = ['DESCRIPCION', 'OBJETIVO KG', 'VOLUMEN VENDIDO KG','AVANCE %', 'COBERTURA', 'VENDIDO Bs'];
        $hoja->fromArray($enc, null, 'A4');
        $hoja->getStyle('A4:F4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A4A2A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $hoja->getRowDimension(4)->setRowHeight(30);

        $fila = 5;
        foreach ($this->filas($datos, $clave, $vendedorId) as $f) {
            $hoja->setCellValue('A' . $fila, $f['nombre']);
            $hoja->setCellValue('B' . $fila, $f['objetivo'] > 0 ? $f['objetivo'] : null);
            $hoja->setCellValue('C' . $fila, $f['vol']);
            $hoja->setCellValue('D' . $fila, $f['avance']);
            $hoja->setCellValue('E' . $fila, $f['cobertura']);
            $hoja->setCellValue('F' . $fila, $f['bs']);
            if ($f['tipo'] === 'seccion') {
                $hoja->getStyle("A$fila")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC000']],
                ]);
                $hoja->getStyle("B$fila:D$fila")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
                ]);
                $hoja->getStyle("E$fila:F$fila")->getFont()->setBold(true);
            } elseif ($f['tipo'] === 'total') {
                $hoja->getStyle("A$fila:F$fila")->getFont()->setBold(true);
                $hoja->getStyle("A$fila")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $this->pintarAvance($hoja, "D$fila", $f['avance'], $ritmo['objetivo']);
            $fila++;
        }
        $ultima = $fila - 1;
        $hoja->getStyle("A4:F$ultima")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $hoja->getStyle("B5:C$ultima")->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle("D5:D$ultima")->getNumberFormat()->setFormatCode('0.0%');
        $hoja->getStyle("E5:E$ultima")->getNumberFormat()->setFormatCode('#,##0');
        $hoja->getStyle("F5:F$ultima")->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (['A' => 30, 'B' => 13, 'C' => 14, 'D' => 11, 'E' => 12, 'F' => 14] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
        $hoja->freezePane('B5');
    }

    private function hojaVendedores(Spreadsheet $libro, $titulo, $datos)
    {
        $hoja = $libro->createSheet();
        $hoja->setTitle('Vendedores');
        $hoja->setCellValue('A1', $titulo . ' - VENDEDORES');
        $hoja->mergeCells('A1:G1');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $hoja->fromArray(['#', 'VENDEDOR', 'OBJETIVO KG', 'VOLUMEN VENDIDO', 'AVANCE %', 'COBERTURA', 'VENDIDO Bs'], null, 'A3');
        $hoja->getStyle('A3:G3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A4A2A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);

        $fila = 4;
        foreach ($datos['vendedores'] as $i => $v) {
            $total = collect($this->filas($datos, $v['clave'], $v['id']))->last();
            $hoja->fromArray([
                $i + 1, $v['nombre'], $total['objetivo'] > 0 ? $total['objetivo'] : null, $total['vol'],
                $total['avance'], $total['cobertura'], $total['bs'],
            ], null, 'A' . $fila);
            $this->pintarAvance($hoja, "E$fila", $total['avance'], $datos['ritmo']['objetivo']);
            $fila++;
        }
        $general = collect($this->filas($datos, 'g', 0))->last();
        $hoja->fromArray(['', 'TOTAL', $general['objetivo'] > 0 ? $general['objetivo'] : null, $general['vol'],
            $general['avance'], $general['cobertura'], $general['bs']], null, 'A' . $fila);
        $hoja->getStyle("A$fila:G$fila")->getFont()->setBold(true);
        $hoja->getStyle("A$fila:G$fila")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
        $this->pintarAvance($hoja, "E$fila", $general['avance'], $datos['ritmo']['objetivo']);
        $hoja->setCellValue('B2', 'OBJETIVO AL DIA (' . $datos['ritmo']['dia'] . '/' . $datos['ritmo']['dias'] . ')');
        $hoja->setCellValue('E2', $datos['ritmo']['objetivo']);
        $hoja->getStyle('B2:E2')->getFont()->setBold(true);
        $hoja->getStyle('E2')->getNumberFormat()->setFormatCode('0.0%');

        $hoja->getStyle("A3:G$fila")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $hoja->getStyle("C4:D$fila")->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle("E4:E$fila")->getNumberFormat()->setFormatCode('0.0%');
        $hoja->getStyle("G4:G$fila")->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (['A' => 5, 'B' => 32, 'C' => 13, 'D' => 14, 'E' => 11, 'F' => 12, 'G' => 14] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
    }

    /** Excel limita el nombre de la hoja a 31 caracteres, sin : \ / ? * [ ] y sin repetir. */
    private function nombreHoja(Spreadsheet $libro, $nombre)
    {
        $base = mb_substr(trim(preg_replace('/[:\\\\\/\?\*\[\]]+/', ' ', $nombre)) ?: 'Hoja', 0, 28);
        $titulo = $base;
        $n = 2;
        while ($libro->sheetNameExists($titulo)) {
            $titulo = $base . ' ' . $n++;
        }
        return $titulo;
    }
}
