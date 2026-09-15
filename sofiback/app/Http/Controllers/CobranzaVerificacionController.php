<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PapeleriaSofia;
use App\Services\RecojoDelDia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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

    /** Tilda (o destilda) un comprobante con el monto que se recibio. */
    public function verificar(Request $request)
    {
        $datos = $request->validate([
            'factura_id' => 'required|integer',
            'verificado' => 'required|boolean',
            'monto' => 'nullable|numeric|min:0|max:9999999999.99',
            'observacion' => 'nullable|string|max:190',
        ]);

        $fila = $this->filas(null, null, (int) $datos['factura_id'])->first();
        if (!$fila) {
            return response()->json(['message' => 'El comprobante no existe o está anulado'], 404);
        }

        $usuario = $request->user();
        $verificado = $request->boolean('verificado');
        // Sin monto escrito se toma lo que trajo el camion; si no trajo nada, el importe.
        $monto = isset($datos['monto'])
            ? round((float) $datos['monto'], 2)
            : ($fila['recogido'] !== null ? $fila['recogido'] : $fila['facturado']);
        $ahora = date('Y-m-d H:i:s');

        DB::table('cobranza_verificaciones')->updateOrInsert(['factura_id' => $fila['factura_id']], [
            'fecha' => $fila['fecha'],
            'placa' => $fila['placa'] === 'SIN' ? null : $fila['placa'],
            'monto_facturado' => $fila['facturado'],
            'monto_recogido' => (float) $fila['recogido'],
            'monto_verificado' => $monto,
            'verificado' => $verificado,
            'observacion' => $datos['observacion'] ?? null,
            'user_id' => $usuario->CodAut,
            'verificado_por' => trim($usuario->Nombre1 . ' ' . $usuario->App1),
            'verificado_en' => $verificado ? $ahora : null,
            'updated_at' => $ahora,
            'created_at' => $ahora,
        ]);

        return [
            'message' => $verificado ? 'Comprobante #' . $fila['factura_id'] . ' verificado' : 'Verificación quitada',
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
     * caminero y la verificacion de cobranzas.
     */
    private function filas($fecha, $camion, $facturaId = null)
    {
        $facturas = DB::table('facturas as f')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'f.cliente_id')
            ->whereNull('f.deleted_at')
            ->where('f.estado', 'ACTIVO')
            ->when($fecha, function ($q) use ($fecha) { $q->where('f.fecha', $fecha); })
            ->when($facturaId, function ($q) use ($facturaId) { $q->where('f.id', $facturaId); })
            ->orderBy('f.hora')
            ->get([
                'f.id', 'f.cliente_id', 'f.fecha', 'f.hora', 'f.tipo_comprobante', 'f.tipo_pago', 'f.total', 'f.pedido_nro', 'f.pedido_tipo',
                DB::raw("TRIM(COALESCE(c.Nombres, f.nombre, '')) as cliente"),
                DB::raw("TRIM(COALESCE(f.nit, c.Id, '')) as nit"),
            ]);

        if ($facturas->isEmpty()) {
            return collect();
        }

        // El camion es el del pedido; se busca de una vez para todos.
        $pedidos = DB::table('tbpedidos')
            ->whereIn('NroPed', $facturas->pluck('pedido_nro')->filter()->unique()->all())
            ->where('bonificacion', 0)
            ->groupBy('NroPed', DB::raw('UPPER(TRIM(tipo))'))
            ->get([
                'NroPed', DB::raw('UPPER(TRIM(tipo)) as tipo'),
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
            if ($entrega) {
                $recogido = in_array($entrega->estado, RecojoDelDia::ESTADOS_COBRADOS, true)
                    ? round((float) $entrega->monto_efectivo + (float) $entrega->monto_qr, 2)
                    : 0.0;
            }
            $facturado = round((float) $f->total, 2);
            $verificado = $v && $v->verificado;
            $montoVerificado = $v ? (float) $v->monto_verificado : null;

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
                'verificado' => $verificado,
                'monto_verificado' => $montoVerificado,
                'diferencia' => $verificado ? round($montoVerificado - $facturado, 2) : null,
                'observacion' => $v->observacion ?? null,
                'verificado_por' => $verificado ? $v->verificado_por : null,
                'verificado_en' => $verificado ? substr((string) $v->verificado_en, 0, 16) : null,
            ];
        })->filter(function ($fila) use ($camion) {
            return !$camion || $fila['placa'] === $camion;
        })->values();
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
