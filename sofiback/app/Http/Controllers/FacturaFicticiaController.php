<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PapeleriaSofia;
use App\Models\FacturaFicticia;
use App\Models\SiatConfiguracion;
use App\Services\SiatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Luecano\NumeroALetras\NumeroALetras;

/**
 * Facturacion ficticia: facturas al SIAT que no pasan por la facturacion.
 *
 * Se elige cliente y productos, se guarda en facturas_ficticias y se emite a
 * Impuestos con el mismo SiatService de la facturacion real. Nada de aca toca
 * facturas, tbventas, tbstock ni stock_actual: anular tampoco devuelve stock,
 * porque nunca se desconto.
 */
class FacturaFicticiaController extends Controller
{
    use PapeleriaSofia;

    const MOTIVOS_ANULACION = [
        1 => 'FACTURA MAL EMITIDA',
        2 => 'DATOS DE EMISION INCORRECTOS',
        3 => 'FACTURA O NOTA DEVUELTA',
        4 => 'SUSTITUCION DE FACTURA EMITIDA EN CONTINGENCIA',
    ];

    const TIPOS_PAGO = ['EFECTIVO', 'TARJETA', 'CHEQUE', 'TRANSFERENCIA', 'DEPOSITO', 'CREDITO', 'OTRO'];

    /** Todo el modulo va con el permiso; el menu solo lo esconde. */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!$request->user() || !$request->user()->can('facturacionFicticia')) {
                return response()->json(['message' => 'No tiene permiso de facturación ficticia'], 403);
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('perPage', 20), 1), 100);

        $query = FacturaFicticia::with('detalles')
            ->when($request->input('desde'), function ($q, $desde) { $q->where('fecha', '>=', $desde); })
            ->when($request->input('hasta'), function ($q, $hasta) { $q->where('fecha', '<=', $hasta); })
            ->when($request->input('estado'), function ($q, $estado) { $q->where('estado', $estado); });

        if ($buscar = trim((string) $request->input('buscar', ''))) {
            $like = '%' . $buscar . '%';
            $query->where(function ($w) use ($like, $buscar) {
                $w->where('nombre', 'like', $like)
                    ->orWhere('nit', 'like', $like)
                    ->orWhere('nro_factura', $buscar)
                    ->orWhere('id', $buscar);
            });
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function show($id)
    {
        $factura = FacturaFicticia::with('detalles')->find($id);
        if (!$factura) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }

        return $factura;
    }

    /** Guarda la factura y la emite al SIAT. No mueve inventario. */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'cliente_id'         => 'nullable|integer',
            'nit'                => 'required|string|max:20',
            'nombre'             => 'required|string|max:150',
            'tipo_pago'          => 'required|in:' . implode(',', self::TIPOS_PAGO),
            'descuento'          => 'nullable|numeric|min:0',
            'observacion'        => 'nullable|string|max:255',
            'items'              => 'required|array|min:1',
            'items.*.cod_prod'   => 'required|string|max:30',
            'items.*.nombre'     => 'nullable|string|max:200',
            'items.*.unidad'     => 'nullable|string|max:30',
            'items.*.cantidad'   => 'required|numeric|gt:0',
            'items.*.precio'     => 'required|numeric|gt:0',
        ]);

        // El nombre sale de tbproductos: es el que tiene los codigos del SIAT.
        $codigos = collect($datos['items'])->map(function ($i) { return trim($i['cod_prod']); })->unique();
        $productos = DB::table('tbproductos')
            ->whereIn(DB::raw('TRIM(cod_prod)'), $codigos)
            ->get([DB::raw('TRIM(cod_prod) as cod_prod'), DB::raw('TRIM(Producto) as producto')])
            ->keyBy('cod_prod');

        $faltan = $codigos->reject(function ($c) use ($productos) { return $productos->has($c); });
        if ($faltan->isNotEmpty()) {
            return response()->json(['message' => 'No existe el producto ' . $faltan->implode(', ')], 422);
        }

        $lineas = collect($datos['items'])->map(function ($i) use ($productos) {
            $cod = trim($i['cod_prod']);
            $cantidad = round((float) $i['cantidad'], 3);
            $precio = round((float) $i['precio'], 2);
            $nombre = trim((string) ($i['nombre'] ?? ''));

            return [
                'cod_prod' => $cod,
                'nombre'   => $nombre !== '' ? $nombre : $productos[$cod]->producto,
                'unidad'   => $i['unidad'] ?? null,
                'cantidad' => $cantidad,
                'precio'   => $precio,
                'subtotal' => round($cantidad * $precio, 2),
            ];
        });

        $subtotal = round($lineas->sum('subtotal'), 2);
        $descuento = round((float) ($datos['descuento'] ?? 0), 2);
        if ($descuento >= $subtotal) {
            return response()->json(['message' => 'El descuento no puede ser mayor o igual al subtotal'], 422);
        }

        $usuario = $request->user();

        $factura = DB::transaction(function () use ($datos, $lineas, $subtotal, $descuento, $usuario) {
            $factura = FacturaFicticia::create([
                'user_id'     => $usuario->CodAut,
                'cliente_id'  => $datos['cliente_id'] ?? null,
                'fecha'       => date('Y-m-d'),
                'hora'        => date('H:i:s'),
                'nit'         => trim($datos['nit']),
                'nombre'      => trim($datos['nombre']),
                'tipo_pago'   => $datos['tipo_pago'],
                'estado'      => 'ACTIVO',
                'subtotal'    => $subtotal,
                'descuento'   => $descuento,
                'total'       => round($subtotal - $descuento, 2),
                'observacion' => $datos['observacion'] ?? null,
            ]);

            $factura->detalles()->createMany($lineas->all());

            return $factura;
        });

        // Fuera de la transaccion: si el SIAT falla la factura queda guardada
        // con estado_siat = ERROR y se reenvia desde la lista.
        $factura = (new SiatService())->emitirFactura($factura->load('detalles'), $usuario->CodAut);

        return response()->json([
            'message' => $factura->estado_siat === 'ERROR'
                ? 'Factura guardada pero no se pudo enviar al SIAT: ' . $factura->mensaje_siat
                : 'Factura ' . $factura->nro_factura . ' enviada al SIAT (' . $factura->estado_siat . ')',
            'factura' => $factura->load('detalles'),
        ]);
    }

    /** Vuelve a mandar al SIAT una que quedo con error. */
    public function reenviar(Request $request, $id)
    {
        $factura = FacturaFicticia::with('detalles')->find($id);
        if (!$factura) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }

        if ($factura->estado === 'ANULADO') {
            return response()->json(['message' => 'La factura está anulada; no se reenvía'], 422);
        }

        if ($factura->online && $factura->estado_siat === 'VALIDADA') {
            return response()->json(['message' => 'La factura ya está validada en el SIAT'], 422);
        }

        $factura = (new SiatService())->emitirFactura($factura, optional($request->user())->CodAut);

        if ($factura->estado_siat === 'ERROR') {
            return response()->json([
                'message' => 'No se pudo enviar: ' . $factura->mensaje_siat,
                'factura' => $factura,
            ], 422);
        }

        return response()->json([
            'message' => 'Factura ' . $factura->nro_factura . ' enviada al SIAT (' . $factura->estado_siat . ')',
            'factura' => $factura,
        ]);
    }

    public function verificar($id)
    {
        $factura = FacturaFicticia::with('detalles')->find($id);
        if (!$factura) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }

        $siat = new SiatService();
        try {
            $resultado = $siat->verificarFactura($factura);
        } catch (\Throwable $e) {
            return response()->json(['message' => $siat->mensajeError($e)], 422);
        }

        return response()->json($resultado + ['factura' => $factura->fresh()]);
    }

    /**
     * Anula en Impuestos y luego aca. Si el SIAT la rechaza no se toca nada.
     * Sin CUF nunca llego al SIAT y se anula solo localmente.
     */
    public function anular(Request $request, $id)
    {
        $datos = $request->validate([
            'codigo_motivo' => 'required|integer|between:1,4',
            'observacion'   => 'nullable|string|max:255',
        ]);

        $factura = FacturaFicticia::with('detalles')->find($id);
        if (!$factura) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }

        if ($factura->estado === 'ANULADO') {
            return response()->json(['message' => 'La factura ya está anulada'], 422);
        }

        $respuestaSiat = null;
        if ($factura->cuf && $factura->estado_siat !== SiatService::ESTADO_SIMULADO) {
            $siat = new SiatService();

            try {
                $respuestaSiat = $siat->anularFactura($factura, $datos['codigo_motivo']);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'No se pudo anular en Impuestos: ' . $siat->mensajeError($e),
                ], 422);
            }

            if (empty($respuestaSiat['transaccion']) && empty($respuestaSiat['no_existe'])) {
                return response()->json([
                    'message' => 'Impuestos rechazó la anulación: ' . $respuestaSiat['mensaje'],
                    'siat'    => $respuestaSiat,
                ], 422);
            }
        }

        $observacion = trim((string) ($datos['observacion'] ?? ''));

        $factura->update([
            'estado'                => 'ANULADO',
            'motivo_anulacion'      => self::MOTIVOS_ANULACION[$datos['codigo_motivo']],
            'anulacion_observacion' => $observacion !== '' ? $observacion : null,
            'anulado_at'            => now(),
            'anulado_por'           => optional($request->user())->CodAut,
        ]);

        return response()->json([
            'message' => $respuestaSiat && empty($respuestaSiat['no_existe'])
                ? 'Factura anulada en Impuestos'
                : 'Factura anulada (no existía en Impuestos)',
            'factura' => $factura->fresh(),
            'siat'    => $respuestaSiat,
        ]);
    }

    /** Representacion grafica de la factura, con el QR al portal de Impuestos. */
    public function pdf($id)
    {
        $factura = FacturaFicticia::with(['detalles', 'cliente'])->find($id);
        if (!$factura) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }

        $pdf = App::make('dompdf.wrapper');
        $pdf->getDomPDF()->getOptions()->setIsFontSubsettingEnabled(true);
        $pdf->setPaper('letter');
        $pdf->loadHTML($this->facturaHtml($factura));

        return $pdf->stream('factura_' . ($factura->nro_factura ?: $factura->id) . '.pdf', ['Attachment' => false]);
    }

    private function facturaHtml(FacturaFicticia $factura)
    {
        $filas = '';
        foreach ($factura->detalles as $i => $d) {
            $filas .= '<tr' . ($i % 2 ? " class='par'" : '') . '>'
                . "<td class='cod'>" . e($d->cod_prod) . '</td>'
                . "<td class='r'>" . number_format($d->cantidad, 2) . '</td>'
                . "<td class='c'>" . e($d->unidad === 'KG' ? 'KILOGRAMO' : 'UNIDAD (SERVICIOS)') . '</td>'
                . '<td>' . e($d->nombre) . '</td>'
                . "<td class='r'>" . number_format($d->precio, 2) . '</td>'
                . "<td class='r'>0.00</td>"
                . "<td class='r'><b>" . number_format($d->subtotal, 2) . '</b></td>'
                . '</tr>';
        }

        $cuf = (string) $factura->cuf;
        $enviada = $cuf !== '' && $factura->estado_siat !== SiatService::ESTADO_SIMULADO
            && $factura->estado_siat !== 'ERROR';

        $caja = "<table class='caja-doc'>
            <tr><td colspan='2' class='tit'>FACTURA</td></tr>
            <tr><td class='et'>N&ordm; factura</td><td class='r nro'>" . e($factura->nro_factura ?: '—') . "</td></tr>
            <tr><td class='et'>NIT emisor</td><td class='r'>" . e(config('siat.nit')) . "</td></tr>
            <tr><td class='et'>Cód. autorización</td><td class='r' style='font-size:7px'>"
                . ($cuf !== '' ? implode('<br>', array_map('e', str_split($cuf, 24))) : '—') . "</td></tr>
        </table>";

        $avisos = '';
        if (!$enviada) {
            $avisos .= "<div class='aviso'>DOCUMENTO SIN VALOR FISCAL &middot; no fue emitido a Impuestos Nacionales</div>";
        }
        if ($factura->estado === 'ANULADO') {
            $avisos .= "<div class='aviso'>FACTURA ANULADA &middot; " . e($factura->motivo_anulacion) . '</div>';
        }

        $qr = '';
        if ($enviada) {
            $png = base64_encode(FacturaFiscalController::qrPng(
                FacturaFiscalController::urlSiat($cuf, $factura->nro_factura)
            ));
            $qr = "<td style='width:100px; text-align:center; vertical-align:top'>"
                . "<img src='data:image/png;base64,$png' style='width:95px; height:95px'></td>";
        }

        $emision = $factura->fecha_emision ? strtotime(substr($factura->fecha_emision, 0, 19)) : false;
        $fecha = $emision ? date('d/m/Y H:i:s', $emision) : $factura->fecha->format('d/m/Y') . ' ' . e($factura->hora);

        return '<style>' . $this->estilosImpresion() . "
            .subtitulo { text-align: center; font-size: 8.5px; color: #666; margin: 6px 0 2px }
        </style>"
            . $this->cabeceraEmisor($caja, SiatConfiguracion::activa()->razon_social ?: config('siat.razon_social'))
            . "<div class='subtitulo'>(Con derecho a crédito fiscal)</div>"
            . $avisos
            . "<table class='datos'>
                <tr>
                    <td style='width:52%'><span class='et'>Nombre / Razón social</span><br><b>"
                        . e($factura->nombre ?: 'S/N') . "</b></td>
                    <td style='width:24%'><span class='et'>NIT / CI / CEX</span><br>" . e($factura->nit ?: '—') . "</td>
                    <td><span class='et'>Fecha</span><br>" . $fecha . "</td>
                </tr>
                <tr>
                    <td><span class='et'>Cod. cliente</span><br>" . e($factura->cliente_id ?: $factura->nit) . "</td>
                    <td><span class='et'>Complemento</span><br>"
                        . e(trim((string) ($factura->cliente->complto ?? '')) ?: '—') . "</td>
                    <td><span class='et'>Forma de pago</span><br>" . e($factura->tipo_pago) . "</td>
                </tr>
            </table>

            <table class='detalle'>
                <tr>
                    <th style='width:10%'>Código</th>
                    <th style='width:9%'>Cantidad</th>
                    <th style='width:13%'>Unidad</th>
                    <th>Descripción</th>
                    <th style='width:11%'>P. Unitario</th>
                    <th style='width:9%'>Descuento</th>
                    <th style='width:12%'>Importe</th>
                </tr>
                $filas
            </table>

            <table style='width:100%; margin-top:6px; border-collapse:collapse'><tr>
                <td style='vertical-align:top; padding-right:10px'>
                    <div class='literal'><b>SON:</b> " . e(mb_strtoupper($this->enLetras($factura->total))) . " BOLIVIANOS</div>
                </td>
                <td style='width:40%; vertical-align:top'>
                    <table class='totales'>
                        <tr><td>Subtotal Bs.</td><td class='r'>" . number_format($factura->subtotal, 2) . "</td></tr>
                        <tr><td>Descuento Bs.</td><td class='r'>" . number_format($factura->descuento, 2) . "</td></tr>
                        <tr class='final'><td>MONTO A PAGAR Bs.</td><td class='r'>" . number_format($factura->total, 2) . "</td></tr>
                    </table>
                </td>
            </tr></table>

            <table style='width:100%; margin-top:8px; border-collapse:collapse'><tr>
                <td class='legal' style='vertical-align:top'>
                    &quot;ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS,
                    EL USO ILÍCITO SERÁ SANCIONADO PENALMENTE DE ACUERDO A LEY&quot;.<br>
                    " . e($factura->leyenda ?: SiatService::LEYENDA) . "<br>
                    Este documento es la Representación Gráfica de un Documento Fiscal Digital
                    emitido en una modalidad de facturación en línea.
                </td>
                $qr
            </tr></table>";
    }

    private function enLetras($monto)
    {
        $monto = round((float) $monto, 2);
        $entero = (int) $monto;
        $decimal = (int) round(($monto - $entero) * 100);

        return ucfirst(strtolower(trim((new NumeroALetras())->toString($entero))))
            . ' ' . sprintf('%02d', $decimal) . '/100';
    }
}
