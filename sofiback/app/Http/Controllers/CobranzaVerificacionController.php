<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PapeleriaSofia;
use App\Services\RecojoDelDia;
use App\Services\TipoPedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Verificacion de la facturacion del dia por cobranzas.
 *
 * Lo mismo que hacia la pantalla "Cuentas por cobrar" del sistema de caja: una
 * fila por comprobante con su importe, lo que trajo el camion y un tilde para
 * darlo por cobrado. El cobrador puede corregir el monto antes de tildar.
 */
class CobranzaVerificacionController extends Controller
{
    use PapeleriaSofia;

    public function __construct()
    {
        $this->middleware('permission:cobranzasverificar');
    }

    /** Los comprobantes del dia (y del camion, si se elige) con su verificacion. */
    public function index(Request $request)
    {
        [$fecha, $camion] = $this->filtros($request);
        $filas = $this->filas($fecha, $camion);

        return [
            'fecha' => $fecha,
            'filas' => $filas,
            'totales' => $this->totales($filas),
            // Los camiones del dia salen sin filtrar por camion, para poder cambiarlo.
            'camiones' => $this->filas($fecha, null)->groupBy('placa')->map(function ($grupo, $placa) {
                return [
                    'placa' => $placa,
                    'total' => $grupo->count(),
                    'verificados' => $grupo->where('verificado', true)->count(),
                    'entregados' => $grupo->where('entregado', true)->count(),
                    'entregados_verificados' => $grupo->where('entregado', true)->where('verificado', true)->count(),
                    'color' => (string) data_get($grupo->firstWhere('placa_color', '!=', ''), 'placa_color', ''),
                ];
            })->sortBy('placa')->values(),
        ];
    }

    /** Historial de facturacion del cliente, independiente del dia y camion. */
    public function historialVentas(Request $request, $cliente)
    {
        $request->validate(['page' => 'nullable|integer|min:1']);
        abort_unless(DB::table('tbclientes')->where('Cod_Aut', $cliente)->exists(), 404, 'Cliente no encontrado');

        return DB::table('facturas')->where('cliente_id', $cliente)
            ->whereNull('deleted_at')
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(20, ['id', 'fecha', 'hora', 'tipo_comprobante', 'pedido_nro', 'tipo_pago', 'total', 'estado']);
    }

    /** Busca clientes por nombre o NIT, con cuantos comprobantes le faltan verificar. */
    public function buscarClientes(Request $request)
    {
        $datos = $request->validate(['buscar' => 'required|string|min:2|max:100']);
        $like = '%' . trim($datos['buscar']) . '%';

        $clientes = DB::table('tbclientes')->where(function ($q) use ($like) {
            $q->where('Nombres', 'like', $like)->orWhere('Id', 'like', $like);
        })->orderBy('Nombres')->limit(30)->get(['Cod_Aut as id', 'Nombres as nombre', 'Id as nit']);

        $resumen = $this->resumenClientes($clientes->pluck('id')->all())->keyBy('cliente_id');

        return $clientes->map(function ($c) use ($resumen) {
            $r = $resumen->get($c->id);
            return [
                'id' => (int) $c->id,
                'nombre' => trim((string) $c->nombre),
                'nit' => trim((string) $c->nit),
                'comprobantes' => $r ? (int) $r->comprobantes : 0,
                'pendientes' => $r ? (int) $r->comprobantes - (int) $r->verificados : 0,
            ];
        })->values();
    }

    /**
     * Las compras (comprobantes vigentes) de un cliente de todas las fechas,
     * con la misma fila y el mismo tilde que la verificacion del dia.
     */
    public function facturasCliente(Request $request, $cliente)
    {
        $datos = $request->validate([
            'page' => 'nullable|integer|min:1',
            'estado' => 'nullable|in:pendientes,verificados,todos',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
        ]);
        $datosCliente = DB::table('tbclientes')->where('Cod_Aut', $cliente)->first(['Cod_Aut', 'Nombres', 'Id']);
        abort_unless($datosCliente, 404, 'Cliente no encontrado');

        $estado = $datos['estado'] ?? 'todos';
        $pagina = DB::table('facturas as f')
            ->leftJoin('cobranza_verificaciones as v', 'v.factura_id', '=', 'f.id')
            ->where('f.cliente_id', $cliente)
            ->whereNull('f.deleted_at')
            ->where('f.estado', 'ACTIVO')
            ->when($datos['desde'] ?? null, function ($q, $desde) { $q->where('f.fecha', '>=', $desde); })
            ->when($datos['hasta'] ?? null, function ($q, $hasta) { $q->where('f.fecha', '<=', $hasta); })
            ->when($estado === 'pendientes', function ($q) {
                $q->where(function ($w) { $w->whereNull('v.id')->orWhere('v.verificado', 0); });
            })
            ->when($estado === 'verificados', function ($q) { $q->where('v.verificado', 1); })
            ->orderByDesc('f.fecha')->orderByDesc('f.hora')->orderByDesc('f.id')
            ->paginate(30, ['f.id']);

        $ids = collect($pagina->items())->pluck('id')->map(function ($id) { return (int) $id; })->all();
        $orden = array_flip($ids);
        $filas = $ids ? $this->filas(null, null, $ids)->sortBy(function ($f) use ($orden) {
            return $orden[$f['factura_id']];
        })->values() : collect();

        $resumen = $this->resumenClientes([(int) $cliente])->first();

        return [
            'cliente' => [
                'id' => (int) $datosCliente->Cod_Aut,
                'nombre' => trim((string) $datosCliente->Nombres),
                'nit' => trim((string) $datosCliente->Id),
            ],
            'filas' => $filas,
            'pagina' => $pagina->currentPage(),
            'paginas' => $pagina->lastPage(),
            'total' => $pagina->total(),
            // Resumen de todas las compras del cliente, sin filtros.
            'totales' => [
                'comprobantes' => $resumen ? (int) $resumen->comprobantes : 0,
                'verificados' => $resumen ? (int) $resumen->verificados : 0,
                'facturado' => $resumen ? round((float) $resumen->facturado, 2) : 0,
                'verificado' => $resumen ? round((float) $resumen->verificado, 2) : 0,
                'por_verificar' => $resumen ? round((float) $resumen->facturado - (float) $resumen->facturado_verificado, 2) : 0,
            ],
        ];
    }

    /** Conteos y montos de los comprobantes vigentes de varios clientes. */
    private function resumenClientes(array $clientes)
    {
        if (!$clientes) {
            return collect();
        }

        return DB::table('facturas as f')
            ->leftJoin('cobranza_verificaciones as v', 'v.factura_id', '=', 'f.id')
            ->whereIn('f.cliente_id', $clientes)
            ->whereNull('f.deleted_at')
            ->where('f.estado', 'ACTIVO')
            ->groupBy('f.cliente_id')
            ->get([
                'f.cliente_id',
                DB::raw('COUNT(*) as comprobantes'),
                DB::raw('SUM(CASE WHEN v.verificado = 1 THEN 1 ELSE 0 END) as verificados'),
                DB::raw('SUM(f.total) as facturado'),
                DB::raw('SUM(CASE WHEN v.verificado = 1 THEN f.total ELSE 0 END) as facturado_verificado'),
                DB::raw('SUM(CASE WHEN v.verificado = 1 THEN v.monto_verificado ELSE 0 END) as verificado'),
            ]);
    }

    /**
     * Tilda (o destilda) un lado del comprobante: 'efectivo' o 'qr', con el
     * monto recibido de ese lado. Un pago mixto queda verificado cuando estan
     * los dos lados; los demas, con su lado.
     *
     * Sin 'lado' (la verificacion del dia) se tilda el comprobante entero:
     * con 'monto_efectivo'/'monto_qr', o con 'monto' repartido segun como se
     * pago, y se marca cada lado que tenga monto.
     */
    public function verificar(Request $request)
    {
        $datos = $request->validate([
            'factura_id' => 'required|integer',
            'verificado' => 'required|boolean',
            'lado' => 'nullable|in:efectivo,qr',
            'monto' => 'nullable|numeric|min:0|max:9999999999.99',
            'monto_efectivo' => 'nullable|numeric|min:0|max:9999999999.99',
            'monto_qr' => 'nullable|numeric|min:0|max:9999999999.99',
            'observacion' => 'nullable|string|max:190',
        ]);

        $fila = $this->filas(null, null, (int) $datos['factura_id'])->first();
        if (!$fila) {
            return response()->json(['message' => 'El comprobante no existe o está anulado'], 404);
        }

        $usuario = $request->user();
        $nombre = trim($usuario->Nombre1 . ' ' . $usuario->App1);
        $verificado = $request->boolean('verificado');
        $lado = $datos['lado'] ?? null;
        $ahora = date('Y-m-d H:i:s');
        $actual = DB::table('cobranza_verificaciones')->where('factura_id', $fila['factura_id'])->first();
        $marcarLado = function ($lado, $ok) use ($usuario, $nombre, $ahora) {
            return [
                $lado . '_ok' => $ok,
                $lado . '_user_id' => $ok ? $usuario->CodAut : null,
                $lado . '_por' => $ok ? $nombre : null,
                $lado . '_en' => $ok ? $ahora : null,
            ];
        };

        if ($lado) {
            if (!in_array($lado, $fila['lados'], true)) {
                return response()->json(['message' => 'Este comprobante no se pagó por ' . ($lado === 'qr' ? 'QR' : 'efectivo')], 422);
            }
            // Sin monto escrito, lo que ya habia o lo esperado de ese lado.
            $monto = isset($datos['monto'])
                ? round((float) $datos['monto'], 2)
                : ($fila['verificado_' . $lado] ?? $fila['esperado_' . $lado]);
            $cambios = ['monto_' . $lado => $monto] + $marcarLado($lado, $verificado);
        } else {
            $conReparto = isset($datos['monto_efectivo']) || isset($datos['monto_qr']);
            if ($conReparto) {
                $efectivo = round((float) ($datos['monto_efectivo'] ?? 0), 2);
                $qr = round((float) ($datos['monto_qr'] ?? 0), 2);
            } elseif (isset($datos['monto'])) {
                [$efectivo, $qr] = $this->repartir(round((float) $datos['monto'], 2), $fila);
            } else {
                // Sin monto escrito, lo esperado: lo que trajo el camion o el importe.
                [$efectivo, $qr] = [$fila['esperado_efectivo'], $fila['esperado_qr']];
            }
            $cambios = ['monto_efectivo' => $efectivo, 'monto_qr' => $qr]
                + $marcarLado('efectivo', $verificado && ($efectivo > 0 || $qr <= 0 || $fila['forma_pago'] === 'MIXTO'))
                + $marcarLado('qr', $verificado && ($qr > 0 || $fila['forma_pago'] === 'MIXTO'));
        }

        // Como queda el comprobante con el cambio.
        $quedan = function ($campo) use ($cambios, $actual) {
            return array_key_exists($campo, $cambios) ? $cambios[$campo] : ($actual->$campo ?? null);
        };
        $efectivoOk = (bool) $quedan('efectivo_ok');
        $qrOk = (bool) $quedan('qr_ok');
        $completo = $fila['forma_pago'] === 'MIXTO' ? $efectivoOk && $qrOk : $efectivoOk || $qrOk;
        $yaEstaba = $actual && $actual->verificado;

        DB::table('cobranza_verificaciones')->updateOrInsert(['factura_id' => $fila['factura_id']], $cambios + [
            'fecha' => $fila['fecha'],
            'placa' => $fila['placa'] === 'SIN' ? null : $fila['placa'],
            'monto_facturado' => $fila['facturado'],
            'monto_recogido' => (float) $fila['recogido'],
            'monto_verificado' => round(($efectivoOk ? (float) $quedan('monto_efectivo') : 0) + ($qrOk ? (float) $quedan('monto_qr') : 0), 2),
            'verificado' => $completo,
            'observacion' => $datos['observacion'] ?? ($actual->observacion ?? null),
            // Quien y cuando queda verificado el comprobante entero.
            'user_id' => $completo && $yaEstaba ? $actual->user_id : $usuario->CodAut,
            'verificado_por' => $completo && $yaEstaba ? $actual->verificado_por : $nombre,
            'verificado_en' => $completo ? ($yaEstaba ? $actual->verificado_en : $ahora) : null,
            'updated_at' => $ahora,
            'created_at' => $actual->created_at ?? $ahora,
        ]);

        if ($lado) {
            $mensaje = ($lado === 'qr' ? 'QR' : 'Efectivo') . ' de #' . $fila['factura_id'] . ($verificado ? ' verificado' : ': verificación quitada');
            if ($verificado && !$completo) {
                $mensaje .= ' · falta verificar el ' . ($lado === 'qr' ? 'efectivo' : 'QR');
            }
        } else {
            $mensaje = $verificado ? 'Comprobante #' . $fila['factura_id'] . ' verificado' : 'Verificación quitada';
        }

        return [
            'message' => $mensaje,
            'fila' => $this->filas(null, null, (int) $fila['factura_id'])->first(),
        ];
    }

    public function excel(Request $request)
    {
        [$fecha, $camion] = $this->filtros($request);
        $filas = $this->filas($fecha, $camion);
        $t = $this->totales($filas);

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Verificacion ' . $fecha);
        $hoja->setCellValue('A1', 'VERIFICACIÓN DE FACTURACIÓN');
        $hoja->setCellValue('A2', 'Fecha: ' . date('d/m/Y', strtotime($fecha)) . ' · Camión: ' . ($camion ?: 'Todos'));
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $columnas = [
            ['N°', 'n'], ['Comprobante', 'comprobante'], ['Pedido', 'pedido'], ['Cliente', 'cliente'],
            ['NIT', 'nit'], ['Camión', 'placa'], ['Pago', 'tipo_pago'], ['Entrega', 'entrega'],
            ['Facturado', 'facturado'], ['Recogido', 'recogido'], ['Verificado', 'monto_verificado'],
            ['Diferencia', 'diferencia'], ['Estado', 'estado_texto'], ['Verificó', 'verificado_por'],
        ];
        foreach ($columnas as $i => [$titulo]) {
            $hoja->setCellValueByColumnAndRow($i + 1, 4, $titulo);
        }
        $hoja->getStyle('A4:N4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle('A4:N4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('37474F');

        $r = 5;
        foreach ($filas->values() as $i => $f) {
            $valores = $f + [
                'n' => $i + 1,
                'comprobante' => ($f['tipo_comprobante'] === 'FACTURA' ? 'Factura #' : 'Venta #') . $f['factura_id'],
                'estado_texto' => $f['verificado'] ? 'VERIFICADO' : 'PENDIENTE',
                'placa' => $f['placa'] === 'SIN' ? 'Sin camión' : $f['placa'],
            ];
            foreach ($columnas as $c => [, $clave]) {
                $valor = $valores[$clave] ?? '';
                $hoja->setCellValueByColumnAndRow($c + 1, $r, $valor === null ? '' : $valor);
            }
            if ($f['verificado']) {
                $hoja->getStyle('A' . $r . ':N' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F5E9');
            }
            $r++;
        }

        $hoja->setCellValue('H' . $r, 'TOTALES');
        $hoja->setCellValue('I' . $r, $t['facturado']);
        $hoja->setCellValue('J' . $r, $t['recogido']);
        $hoja->setCellValue('K' . $r, $t['verificado']);
        $hoja->setCellValue('L' . $r, $t['diferencia']);
        $hoja->setCellValue('M' . $r, $t['verificados'] . '/' . $t['comprobantes']);
        $hoja->getStyle('A' . $r . ':N' . $r)->getFont()->setBold(true);
        $hoja->getStyle('A4:N' . $r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BDBDBD');
        $hoja->getStyle('I5:L' . $r)->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', 'N') as $letra) {
            $hoja->getColumnDimension($letra)->setAutoSize(true);
        }
        $hoja->freezePane('A5');

        $writer = new Xlsx($libro);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'verificacion_' . $fecha . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function pdf(Request $request)
    {
        [$fecha, $camion] = $this->filtros($request);
        $filas = $this->filas($fecha, $camion);
        $t = $this->totales($filas);
        $m = function ($v) { return $v === null ? '—' : number_format((float) $v, 2); };

        $cuerpo = '';
        foreach ($filas->values() as $i => $f) {
            $clase = $f['verificado'] ? " class='ok'" : ($i % 2 ? " class='par'" : '');
            $cuerpo .= "<tr$clase>"
                . "<td class='c'>" . ($i + 1) . '</td>'
                . "<td class='c'>" . ($f['tipo_comprobante'] === 'FACTURA' ? 'F' : 'V') . ' #' . $f['factura_id'] . '</td>'
                . "<td class='c'>" . e($f['pedido'] ?: '—') . '</td>'
                . '<td>' . e($f['cliente']) . '</td>'
                . '<td>' . e($f['placa'] === 'SIN' ? 'Sin camión' : $f['placa']) . '</td>'
                . "<td class='c'>" . e($f['tipo_pago']) . '</td>'
                . "<td class='r'>" . $m($f['facturado']) . '</td>'
                . "<td class='r'>" . $m($f['recogido']) . '</td>'
                . "<td class='r b'>" . ($f['verificado'] ? $m($f['monto_verificado']) : '') . '</td>'
                . "<td class='r " . (abs((float) $f['diferencia']) > 0.009 ? 'rojo' : '') . "'>" . ($f['verificado'] ? $m($f['diferencia']) : '') . '</td>'
                . "<td class='c'>" . ($f['verificado'] ? '&#10004;' : '') . '</td>'
                . '</tr>';
        }
        if ($cuerpo === '') {
            $cuerpo = "<tr><td colspan='11' class='c gris' style='padding:16px'>Sin comprobantes este día</td></tr>";
        }

        $caja = "<table class='caja-doc'>
            <tr><td colspan='2' class='tit'>VERIFICACIÓN</td></tr>
            <tr><td class='et'>Fecha</td><td class='r'>" . date('d/m/Y', strtotime($fecha)) . "</td></tr>
            <tr><td class='et'>Camión</td><td class='r'><b>" . e($camion ?: 'Todos') . "</b></td></tr>
            <tr><td class='et'>Verificados</td><td class='r nro'>" . $t['verificados'] . '/' . $t['comprobantes'] . "</td></tr>
        </table>";

        $html = '<style>' . $this->estilosImpresion() . "
            .b { font-weight: bold } .rojo { color: #c62828 }
            .detalle tr.ok td { background: #e8f5e9 }
            .detalle tfoot td { background: #37474f; color: #fff; font-weight: bold; padding: 5px 4px }
            .firma { margin-top: 48px; text-align: center; font-size: 9px; color: #666 }
            .firma-linea { border-top: 1px solid #999; width: 62mm; margin: 0 auto 3px }
        </style>"
            . $this->cabeceraEmisor($caja)
            . "<table class='detalle'>
                <thead><tr>
                    <th style='width:20px'>N°</th><th style='width:52px'>Comp.</th><th style='width:46px'>Pedido</th>
                    <th style='text-align:left'>Cliente</th><th style='width:70px;text-align:left'>Camión</th>
                    <th style='width:48px'>Pago</th><th style='width:56px' class='r'>Facturado</th>
                    <th style='width:56px' class='r'>Recogido</th><th style='width:56px' class='r'>Verificado</th>
                    <th style='width:50px' class='r'>Dif.</th><th style='width:18px'>&#10004;</th>
                </tr></thead>
                <tbody>$cuerpo</tbody>
                <tfoot><tr>
                    <td colspan='6' class='r'>TOTALES</td>
                    <td class='r'>" . number_format($t['facturado'], 2) . "</td>
                    <td class='r'>" . number_format($t['recogido'], 2) . "</td>
                    <td class='r'>" . number_format($t['verificado'], 2) . "</td>
                    <td class='r'>" . number_format($t['diferencia'], 2) . "</td>
                    <td></td>
                </tr></tfoot>
            </table>
            <div class='firma'><div class='firma-linea'></div>Firma de cobranzas</div>
            <div class='pie'><div class='legal'>" . e(config('siat.emisor')['nombre'])
            . ' &middot; Verificación de facturación del ' . date('d/m/Y', strtotime($fecha))
            . ' &middot; generado el ' . date('d/m/Y H:i') . '</div></div>';

        $pdf = App::make('dompdf.wrapper');
        $pdf->getDomPDF()->getOptions()->setIsFontSubsettingEnabled(true);
        $pdf->setPaper('letter', 'landscape');
        $pdf->loadHTML($html);

        return $pdf->stream('verificacion_' . $fecha . '.pdf', ['Attachment' => false]);
    }

    /**
     * Rango de fecha y hora en que se verifico (por defecto hoy, de 00:00 a
     * 23:59) y opcionalmente quien verifico. Devuelve [inicio, fin, usuario].
     */
    private function rangoVerificados(Request $request): array
    {
        $datos = $request->validate([
            'desde' => 'nullable|date_format:Y-m-d',
            'hasta' => 'nullable|date_format:Y-m-d',
            'hora_desde' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'hora_hasta' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'user_id' => 'nullable|integer',
        ]);
        $desde = $datos['desde'] ?? date('Y-m-d');
        $hasta = $datos['hasta'] ?? $desde;

        return [
            $desde . ' ' . ($datos['hora_desde'] ?? '00:00') . ':00',
            // Hasta las 23:59 incluye ese minuto entero.
            $hasta . ' ' . ($datos['hora_hasta'] ?? '23:59') . ':59',
            isset($datos['user_id']) ? (int) $datos['user_id'] : null,
        ];
    }

    /**
     * Los lados (efectivo y QR) tildados en el rango, cada uno por la fecha y
     * hora en que se tildo y con quien lo tildo, en ese orden. Opcionalmente
     * de un solo usuario o de un solo lado.
     */
    private function ladosDelRango($inicio, $fin, $usuario, $soloLado = null)
    {
        return collect($soloLado ? [$soloLado] : ['efectivo', 'qr'])->flatMap(function ($lado) use ($inicio, $fin, $usuario) {
            return DB::table('cobranza_verificaciones')
                ->where($lado . '_ok', 1)
                ->whereBetween($lado . '_en', [$inicio, $fin])
                ->when($usuario, function ($q) use ($usuario, $lado) { $q->where($lado . '_user_id', $usuario); })
                ->get(['id', 'factura_id', $lado . '_user_id as user_id', $lado . '_por as por', $lado . '_en as en', 'monto_' . $lado . ' as monto'])
                ->map(function ($v) use ($lado) {
                    $v->lado = $lado;
                    $v->por = mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $v->por))) ?: 'SIN USUARIO';
                    return $v;
                });
        })->sortBy(function ($v) { return $v->en . '-' . str_pad($v->id, 10, '0', STR_PAD_LEFT); })->values();
    }

    /** Quienes verificaron en el rango, con cuanto: para elegir el usuario del reporte. */
    public function verificadores(Request $request)
    {
        [$inicio, $fin] = $this->rangoVerificados($request);

        return $this->ladosDelRango($inicio, $fin, null)->groupBy(function ($v) { return (int) $v->user_id; })
            ->map(function ($grupo) {
                return [
                    'user_id' => $grupo->first()->user_id === null ? null : (int) $grupo->first()->user_id,
                    'nombre' => $grupo->first()->por,
                    'verificados' => $grupo->pluck('factura_id')->unique()->count(),
                    'total' => round($grupo->sum('monto'), 2),
                ];
            })->sortBy('nombre')->values();
    }

    /**
     * Cada lado verificado en el rango (opcionalmente de un usuario o de un
     * solo lado) con los datos del comprobante, quien lo verifico, el
     * vendedor, la comanda y cuanto va a QR, efectivo o credito: el efectivo
     * de una venta a credito cuenta como credito.
     */
    private function verificadosDelRango($inicio, $fin, $usuario, $soloLado = null)
    {
        $lados = $this->ladosDelRango($inicio, $fin, $usuario, $soloLado);
        if ($lados->isEmpty()) {
            return collect();
        }

        $ids = $lados->pluck('factura_id')->unique()->values()->all();
        $filas = $this->filas(null, null, $ids)->keyBy('factura_id');
        $facturas = DB::table('facturas as f')->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'f.cliente_id')
            ->whereIn('f.id', $ids)
            ->get(['f.id', 'f.vendedor_ci', 'c.CiVend'])->keyBy('id');
        $vendedores = DB::table('personal')->whereRaw("TRIM(COALESCE(ci, '')) <> ''")
            ->get(['ci', 'Nombre1', 'App1'])
            ->mapWithKeys(function ($p) {
                return [trim($p->ci) => trim(trim((string) $p->Nombre1) . ' ' . trim((string) $p->App1))];
            });

        return $lados->map(function ($v) use ($filas, $facturas, $vendedores) {
            $fila = $filas->get((int) $v->factura_id);
            if (!$fila) {
                return null;
            }
            $factura = $facturas->get((int) $v->factura_id);
            $monto = round((float) $v->monto, 2);
            $credito = $v->lado === 'efectivo' && $fila['forma_pago'] === 'CRÉDITO';
            $ci = trim((string) (($factura->vendedor_ci ?? '') ?: ($factura->CiVend ?? '')));

            return [
                'lado' => $v->lado,
                'monto' => $monto,
                'qr' => $v->lado === 'qr' ? $monto : 0.0,
                'efectivo' => $v->lado === 'efectivo' && !$credito ? $monto : 0.0,
                'credito' => $credito ? $monto : 0.0,
                'verificador' => $v->por,
                'vendedor' => $vendedores->get($ci, ''),
                'comanda' => $fila['pedido'] ? (int) $fila['pedido'] : $fila['factura_id'],
                'factura' => $fila['tipo_comprobante'] === 'FACTURA' ? 'SI' : 'NO',
            ] + $fila;
        })->filter()->values();
    }

    /**
     * Lo verificado por QR en el rango, con el mismo formato del Excel de
     * cobros QR de creditos: un bloque por cliente con fecha, vendedor, monto
     * QR, comanda, si era factura y referencia, el total del cliente y al
     * final el total del rango. El efectivo y el credito no entran; de un
     * pago mixto solo cuenta la parte QR.
     */
    public function excelQr(Request $request)
    {
        [$inicio, $fin, $usuario] = $this->rangoVerificados($request);
        $filas = $this->verificadosDelRango($inicio, $fin, $usuario, 'qr')->filter(function ($f) { return $f['qr'] > 0; })->values();
        $desde = substr($inicio, 0, 10);
        $hasta = substr($fin, 0, 10);

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Verificados QR');
        $borde = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];

        $fila = 1;
        if ($filas->isNotEmpty()) {
            $hoja->setCellValue('B' . $fila, 'VERIFICADOS POR QR ' . date('d/m/y', strtotime($desde))
                . ($hasta !== $desde ? ' AL ' . date('d/m/y', strtotime($hasta)) : ''));
            $hoja->getStyle('B' . $fila)->getFont()->setBold(true);
            if ($usuario) {
                $fila++;
                $hoja->setCellValue('B' . $fila, 'VERIFICÓ ' . $filas->first()['verificador']);
            }
            $fila += 2;
        }
        // Un bloque por cliente, en orden alfabetico, con el total de cada uno.
        foreach ($filas->sortBy('cliente')->groupBy('cliente') as $grupo) {
            $hoja->fromArray(['fecha', 'vendedor', 'cliente', 'qr', 'comanda', 'factura', 'referencia'], null, 'A' . $fila);
            $hoja->getStyle("A{$fila}:G{$fila}")->applyFromArray($borde);
            $desdeFila = ++$fila;
            foreach ($grupo->sortBy('factura_id') as $f) {
                $hoja->setCellValue('A' . $fila, date('d/m/y', strtotime($f['fecha'])));
                $hoja->setCellValueExplicit('B' . $fila, $f['vendedor'], DataType::TYPE_STRING);
                $hoja->setCellValueExplicit('C' . $fila, $f['cliente'], DataType::TYPE_STRING);
                $hoja->setCellValue('D' . $fila, $f['qr']);
                $hoja->setCellValue('E' . $fila, $f['comanda']);
                $hoja->setCellValue('F' . $fila, $f['factura']);
                $hoja->setCellValueExplicit('G' . $fila, (string) ($f['observacion'] ?? ''), DataType::TYPE_STRING);
                $fila++;
            }
            $hoja->getStyle('A' . $desdeFila . ':G' . ($fila - 1))->applyFromArray($borde);
            // Total del cliente bajo qr.
            $hoja->setCellValue('C' . $fila, 'TOTAL');
            $hoja->getStyle('C' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $hoja->setCellValue('D' . $fila, '=SUM(D' . $desdeFila . ':D' . ($fila - 1) . ')');
            $hoja->getStyle('D' . $fila)->applyFromArray($borde);
            $hoja->getStyle("C{$fila}:D{$fila}")->getFont()->setBold(true);
            $fila += 3;
        }

        if ($filas->isEmpty()) {
            $hoja->setCellValue('B1', 'Sin verificados por QR del ' . $desde . ' al ' . $hasta);
            $fila = 3;
        } else {
            // Total de todo el rango.
            $hoja->setCellValue('C' . $fila, 'TOTAL VERIFICADO POR QR');
            $hoja->setCellValue('D' . $fila, round($filas->sum('qr'), 2));
            $hoja->getStyle("C{$fila}:D{$fila}")->getFont()->setBold(true);
            $fila += 2;
        }
        $yo = $request->user();
        $hoja->setCellValue('A' . $fila, 'ELABORADO POR ' . strtoupper(trim(($yo->Nombre1 ?? '') . ' ' . ($yo->App1 ?? ''))));

        $hoja->getStyle('D1:D' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (['A' => 10, 'B' => 28, 'C' => 40, 'D' => 12, 'E' => 12, 'F' => 9, 'G' => 18] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
        $hoja->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER)->setFitToWidth(1)->setFitToHeight(0);

        return $this->descargar($libro, 'VERIFICADOS QR ' . $desde . ($hasta !== $desde ? ' AL ' . $hasta : '') . '.xlsx');
    }

    /**
     * Total del efectivo verificado por cada usuario en el rango, con el
     * formato del cierre de caja de creditos: una hoja por usuario con cuantos
     * comprobantes verifico en efectivo, el total y su firma. El QR y el
     * credito no entran.
     */
    public function excelTotal(Request $request)
    {
        return $this->cierreEfectivo($request, false);
    }

    /**
     * Lo mismo que el total, pero con el detalle: cada comprobante verificado
     * en efectivo con su cliente, el importe, el efectivo verificado y la
     * diferencia, para cuadrar el total.
     */
    public function excelDetalle(Request $request)
    {
        return $this->cierreEfectivo($request, true);
    }

    private function cierreEfectivo(Request $request, $detalle)
    {
        [$inicio, $fin, $usuario] = $this->rangoVerificados($request);
        // Solo el lado efectivo; el efectivo de una venta a credito no es plata.
        $filas = $this->verificadosDelRango($inicio, $fin, $usuario, 'efectivo')
            ->filter(function ($f) { return $f['credito'] <= 0; })->values();

        $libro = new Spreadsheet();
        $libro->removeSheetByIndex(0);
        $borde = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
        $gris = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']];
        $rango = date('d/m/Y H:i', strtotime($inicio)) . ' a ' . date('d/m/Y H:i', strtotime($fin));
        $titulo1 = $detalle ? 'DETALLE DE EFECTIVO VERIFICADO' : 'CIERRE DE EFECTIVO VERIFICADO';

        $grupos = $filas->isEmpty() ? collect(['SIN VERIFICACIONES' => collect()]) : $filas->groupBy('verificador')->sortKeys();
        $titulos = [];
        foreach ($grupos as $verificador => $grupo) {
            // Sin caracteres prohibidos y hasta 31; sin repetir.
            $titulo = mb_substr(trim(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', (string) $verificador)), 0, 31) ?: 'Hoja';
            for ($n = 2; in_array(mb_strtoupper($titulo), $titulos, true); $n++) {
                $titulo = mb_substr($titulo, 0, 28) . ' ' . $n;
            }
            $titulos[] = mb_strtoupper($titulo);
            $hoja = $libro->createSheet();
            $hoja->setTitle($titulo);

            $ultima = $detalle ? 'H' : 'B';
            $hoja->setCellValue('A1', $titulo1);
            $hoja->mergeCells("A1:{$ultima}1");
            $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(15);
            $hoja->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $hoja->fromArray([['Fecha:', $rango], ['Verificó:', $verificador]], null, 'A3');
            $hoja->getStyle('A3:A4')->getFont()->setBold(true);

            $total = round($grupo->sum('efectivo'), 2);
            if ($detalle) {
                $hoja->fromArray(['N°', 'Verificado el', 'Comprobante', 'Comanda', 'Cliente', 'Importe', 'Efectivo', 'Diferencia'], null, 'A6');
                $hoja->getStyle('A6:H6')->applyFromArray($borde + ['font' => ['bold' => true], 'fill' => $gris]);
                $r = 7;
                foreach ($grupo as $i => $f) {
                    // En un pago mixto el efectivo es solo una parte del importe.
                    $importe = $f['forma_pago'] === 'MIXTO' ? (float) $f['esperado_efectivo'] : $f['facturado'];
                    $hoja->fromArray([
                        $i + 1,
                        $f['efectivo_en'] ? date('d/m/Y H:i', strtotime($f['efectivo_en'])) : '',
                        ($f['tipo_comprobante'] === 'FACTURA' ? 'Factura #' : 'Venta #') . $f['factura_id'] . ($f['forma_pago'] === 'MIXTO' ? ' (mixto)' : ''),
                        $f['comanda'],
                        $f['cliente'],
                        $importe,
                        $f['efectivo'],
                        round($f['efectivo'] - $importe, 2),
                    ], null, 'A' . $r, true);
                    if (abs($f['efectivo'] - $importe) > 0.009) {
                        $hoja->getStyle('H' . $r)->getFont()->setBold(true)->getColor()->setRGB('C62828');
                    }
                    $r++;
                }
                $hoja->setCellValue('E' . $r, 'TOTAL EFECTIVO');
                $hoja->getStyle('E' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                foreach (['F', 'G', 'H'] as $col) {
                    $hoja->setCellValue($col . $r, $r > 7 ? "=SUM({$col}7:{$col}" . ($r - 1) . ')' : 0);
                }
                $hoja->getStyle("A7:H{$r}")->applyFromArray($borde);
                $hoja->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['bold' => true], 'fill' => $gris]);
                $hoja->getStyle("F7:H{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                foreach (['A' => 5, 'B' => 16, 'C' => 20, 'D' => 10, 'E' => 40, 'F' => 12, 'G' => 12, 'H' => 12] as $col => $ancho) {
                    $hoja->getColumnDimension($col)->setWidth($ancho);
                }
                $hoja->freezePane('A7');
                $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_LETTER)->setFitToWidth(1)->setFitToHeight(0);
            } else {
                // Solo el total, como el cierre de caja.
                $hoja->fromArray([['Comprobantes:', $grupo->count()], ['TOTAL EFECTIVO:', $total]], null, 'A6', true);
                $hoja->getStyle('B6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $hoja->getStyle('A7:B7')->applyFromArray($borde + ['font' => ['bold' => true, 'size' => 13], 'fill' => $gris]);
                $hoja->getStyle('B7')->getNumberFormat()->setFormatCode('#,##0.00');
                $hoja->getStyle('B7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $r = 7;
                $hoja->getColumnDimension('A')->setWidth(22);
                $hoja->getColumnDimension('B')->setWidth(40);
                $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT)->setPaperSize(PageSetup::PAPERSIZE_LETTER);
            }

            $hoja->setCellValue('A' . ($r + 4), '________________________');
            $hoja->setCellValue('A' . ($r + 5), 'Firma ' . $verificador);
        }

        // Con varios usuarios, una hoja de resumen al principio.
        if ($grupos->count() > 1) {
            $resumen = $libro->createSheet(0);
            $resumen->setTitle('Resumen');
            $resumen->setCellValue('A1', $titulo1);
            $resumen->getStyle('A1')->getFont()->setBold(true)->setSize(15);
            $resumen->setCellValue('A2', $rango);
            $resumen->fromArray(['Verificó', 'Comprobantes', 'Efectivo'], null, 'A4');
            $resumen->getStyle('A4:C4')->applyFromArray($borde + ['font' => ['bold' => true], 'fill' => $gris]);
            $fila = 5;
            foreach ($grupos as $verificador => $grupo) {
                $resumen->fromArray([$verificador, $grupo->count(), round($grupo->sum('efectivo'), 2)], null, 'A' . $fila, true);
                $fila++;
            }
            $resumen->setCellValue('A' . $fila, 'TOTAL');
            foreach (['B', 'C'] as $col) {
                $resumen->setCellValue($col . $fila, "=SUM({$col}5:{$col}" . ($fila - 1) . ')');
            }
            $resumen->getStyle("A5:C{$fila}")->applyFromArray($borde);
            $resumen->getStyle("A{$fila}:C{$fila}")->applyFromArray(['font' => ['bold' => true], 'fill' => $gris]);
            $resumen->getStyle("C5:C{$fila}")->getNumberFormat()->setFormatCode('#,##0.00');
            foreach (['A' => 34, 'B' => 14, 'C' => 14] as $col => $ancho) {
                $resumen->getColumnDimension($col)->setWidth($ancho);
            }
        }
        $libro->setActiveSheetIndex(0);

        return $this->descargar($libro, ($detalle ? 'DETALLE EFECTIVO ' : 'CIERRE EFECTIVO ') . substr($inicio, 0, 10) . '.xlsx');
    }

    private function descargar(Spreadsheet $libro, $nombre)
    {
        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function filtros(Request $request)
    {
        $datos = $request->validate([
            'fecha' => 'nullable|date',
            'camion' => 'nullable|string|max:100',
        ]);

        return [$datos['fecha'] ?? date('Y-m-d'), trim((string) ($datos['camion'] ?? ''))];
    }

    /**
     * Los comprobantes vigentes de un dia, con su camion, lo que marco el
     * caminero y la verificacion de cobranzas. $facturaId puede ser un id o
     * una lista de ids (las compras de un cliente).
     */
    private function filas($fecha, $camion, $facturaId = null)
    {
        $facturas = DB::table('facturas as f')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'f.cliente_id')
            ->whereNull('f.deleted_at')
            ->where('f.estado', 'ACTIVO')
            ->when($fecha, function ($q) use ($fecha) { $q->where('f.fecha', $fecha); })
            ->when($facturaId, function ($q) use ($facturaId) { $q->whereIn('f.id', (array) $facturaId); })
            ->orderBy('f.hora')
            ->get([
                'f.id', 'f.cliente_id', 'f.fecha', 'f.hora', 'f.tipo_comprobante', 'f.tipo_pago', 'f.total', 'f.pedido_nro', 'f.pedido_tipo',
                'f.monto_efectivo', 'f.monto_qr',
                DB::raw("TRIM(COALESCE(c.Nombres, f.nombre, '')) as cliente"),
                DB::raw("TRIM(COALESCE(f.nit, c.Id, '')) as nit"),
            ]);

        if ($facturas->isEmpty()) {
            return collect();
        }

        // El camion es el del pedido; se busca de una vez para todos.
        $pedidos = DB::table('tbpedidos')
            ->whereNull('tbpedidos.deleted_at')
            ->whereIn('NroPed', $facturas->pluck('pedido_nro')->filter()->unique()->all())
            ->where('bonificacion', 0)
            ->groupBy('NroPed', DB::raw(TipoPedido::sql('')))
            ->get([
                'NroPed', DB::raw(TipoPedido::sqlAgrupado('') . ' as tipo'),
                DB::raw("TRIM(COALESCE(MIN(placa), '')) as placa"),
                DB::raw("TRIM(COALESCE(MIN(colorStyle), '')) as color"),
            ])
            ->keyBy(function ($p) { return $p->NroPed . '-' . $p->tipo; });

        $ids = $facturas->pluck('id')->all();
        // La ultima entrega registrada es la que vale.
        $entregas = DB::table('entregas')->whereIn('factura_id', $ids)->orderBy('id')
            ->get(['factura_id', 'estado', 'tipago', 'monto_efectivo', 'monto_qr'])->keyBy('factura_id');
        $verificaciones = DB::table('cobranza_verificaciones')->whereIn('factura_id', $ids)->get()->keyBy('factura_id');

        return $facturas->map(function ($f) use ($pedidos, $entregas, $verificaciones) {
            $pedido = $pedidos->get($f->pedido_nro . '-' . strtoupper(trim((string) $f->pedido_tipo)));
            $entrega = $entregas->get($f->id);
            $v = $verificaciones->get($f->id);

            // Lo que trajo el camion: efectivo y QR de la entrega cobrada. A
            // credito o sin entregar no trajo plata; sin entrega todavia, no se sabe.
            $recogido = null;
            $cobrada = $entrega && in_array($entrega->estado, RecojoDelDia::ESTADOS_COBRADOS, true);
            if ($entrega) {
                $recogido = $cobrada ? round((float) $entrega->monto_efectivo + (float) $entrega->monto_qr, 2) : 0.0;
            }
            $facturado = round((float) $f->total, 2);
            $verificado = $v && $v->verificado;
            $montoVerificado = $v ? (float) $v->monto_verificado : null;

            // Como se pago: lo que marco el camion si ya cobro; si no, lo que
            // dice el comprobante.
            $origen = $cobrada ? $entrega : $f;
            $forma = $this->formaPago($cobrada ? $entrega->tipago : $f->tipo_pago,
                (float) $origen->monto_efectivo, (float) $origen->monto_qr);
            // Lo que se espera recibir de cada lado: lo que trajo el camion; sin
            // entrega todavia, el reparto del comprobante o el importe entero.
            if ($entrega) {
                $esperado = $cobrada
                    ? [round((float) $entrega->monto_efectivo, 2), round((float) $entrega->monto_qr, 2)]
                    : [0.0, 0.0];
            } elseif ((float) $f->monto_efectivo + (float) $f->monto_qr > 0) {
                $esperado = [round((float) $f->monto_efectivo, 2), round((float) $f->monto_qr, 2)];
            } else {
                $esperado = $forma === 'QR' ? [0.0, $facturado] : [$facturado, 0.0];
            }
            // Lo escrito por cobranzas de cada lado (null si todavia nada).
            $reparto = [
                $v && $v->monto_efectivo !== null ? (float) $v->monto_efectivo : null,
                $v && $v->monto_qr !== null ? (float) $v->monto_qr : null,
            ];
            // Que lado se verifica: el mixto los dos, el QR solo QR y lo demas
            // (efectivo y credito) solo efectivo.
            $lados = $forma === 'MIXTO' ? ['efectivo', 'qr'] : ($forma === 'QR' ? ['qr'] : ['efectivo']);

            return [
                'factura_id' => (int) $f->id,
                'fecha' => substr((string) $f->fecha, 0, 10),
                'hora' => substr((string) $f->hora, 0, 5),
                'tipo_comprobante' => $f->tipo_comprobante,
                'tipo_pago' => $f->tipo_pago,
                'pedido' => $f->pedido_nro,
                'cliente' => $f->cliente,
                'cliente_id' => $f->cliente_id,
                'nit' => $f->nit,
                'placa' => $pedido && $pedido->placa !== '' ? $pedido->placa : 'SIN',
                'placa_color' => $pedido->color ?? '',
                'entregado' => $entrega && in_array($entrega->estado, RecojoDelDia::ESTADOS_COBRADOS, true),
                'entrega' => $entrega ? ($entrega->estado . ($entrega->tipago ? ' · ' . $entrega->tipago : '')) : 'PENDIENTE',
                'facturado' => $facturado,
                'recogido' => $recogido,
                'recogido_efectivo' => $cobrada ? round((float) $entrega->monto_efectivo, 2) : null,
                'recogido_qr' => $cobrada ? round((float) $entrega->monto_qr, 2) : null,
                'forma_pago' => $forma,
                'esperado_efectivo' => $esperado[0],
                'esperado_qr' => $esperado[1],
                'verificado' => $verificado,
                'monto_verificado' => $montoVerificado,
                'verificado_efectivo' => $reparto[0],
                'verificado_qr' => $reparto[1],
                'lados' => $lados,
                'efectivo_ok' => $v ? (bool) $v->efectivo_ok : false,
                'efectivo_por' => $v && $v->efectivo_ok ? $v->efectivo_por : null,
                'efectivo_en' => $v && $v->efectivo_ok ? substr((string) $v->efectivo_en, 0, 16) : null,
                'qr_ok' => $v ? (bool) $v->qr_ok : false,
                'qr_por' => $v && $v->qr_ok ? $v->qr_por : null,
                'qr_en' => $v && $v->qr_ok ? substr((string) $v->qr_en, 0, 16) : null,
                'diferencia' => $verificado ? round($montoVerificado - $facturado, 2) : null,
                'observacion' => $v->observacion ?? null,
                'verificado_por' => $verificado ? $v->verificado_por : null,
                'verificado_en' => $verificado ? substr((string) $v->verificado_en, 0, 16) : null,
            ];
        })->filter(function ($fila) use ($camion) {
            return !$camion || $fila['placa'] === $camion;
        })->values();
    }

    /** EFECTIVO, QR, MIXTO o CRÉDITO segun el tipo de pago y los montos de cada lado. */
    private function formaPago($tipo, $efectivo, $qr)
    {
        $tipo = strtoupper((string) $tipo);
        if (strpos($tipo, 'DITO') !== false) {
            return 'CRÉDITO';
        }
        if (($efectivo > 0 && $qr > 0) || strpos($tipo, 'MIXTO') !== false) {
            return 'MIXTO';
        }
        if ($qr > 0 || strpos($tipo, 'QR') !== false) {
            return 'QR';
        }
        return 'EFECTIVO';
    }

    /**
     * Reparte un monto unico entre [efectivo, qr] segun la forma de pago: el
     * QR va entero a QR, el mixto lleva a QR hasta lo esperado y el resto es
     * efectivo, y lo demas es efectivo.
     */
    private function repartir($monto, array $fila)
    {
        if ($fila['forma_pago'] === 'QR') {
            return [0.0, $monto];
        }
        if ($fila['forma_pago'] === 'MIXTO') {
            $qr = min(round((float) $fila['esperado_qr'], 2), $monto);
            return [round($monto - $qr, 2), $qr];
        }
        return [$monto, 0.0];
    }

    private function totales($filas)
    {
        $verificadas = $filas->where('verificado', true);

        return [
            'comprobantes' => $filas->count(),
            'verificados' => $verificadas->count(),
            'facturado' => round($filas->sum('facturado'), 2),
            'recogido' => round($filas->sum('recogido'), 2),
            'verificado' => round($verificadas->sum('monto_verificado'), 2),
            // Lo verificado contra el importe de esos mismos comprobantes.
            'diferencia' => round($verificadas->sum('monto_verificado') - $verificadas->sum('facturado'), 2),
            'por_verificar' => round($filas->where('verificado', false)->sum('facturado'), 2),
        ];
    }
}
