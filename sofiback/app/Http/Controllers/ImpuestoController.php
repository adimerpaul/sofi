<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use App\Models\SiatConfiguracion;
use App\Models\SiatCufd;
use App\Models\SiatCui;
use App\Models\SiatMotivoAnulacion;
use App\Services\SiatService;
use Illuminate\Http\Request;

/**
 * Modulo de Impuestos: datos del emisor ante el SIAT, codigos CUIS/CUFD y
 * control de lo que se envio a facturar.
 *
 * Todo sale de la base (tabla siat_configuraciones), no del .env: el token
 * delegado caduca cada tanto y quien lo renueva en la oficina de Impuestos no
 * es quien despliega el servidor.
 *
 * El protocolo con el SIAT vive en SiatService; aca solo queda lo de HTTP.
 * Los tres codigos van encadenados:
 *
 *   CUIS -> dura ~1 anio, uno por sucursal y punto de venta.
 *   CUFD -> dura 24 horas, se pide con el CUIS.
 *   CUF  -> por factura, se calcula con el codigo de control del CUFD.
 */
class ImpuestoController extends Controller
{
    /** Estado completo para la pantalla: datos, faltantes y codigos vigentes. */
    public function configuracion()
    {
        return response()->json($this->estado(SiatConfiguracion::activa()));
    }

    /** Guarda los datos de Impuestos, el token incluido. */
    public function guardarConfiguracion(Request $request)
    {
        $datos = $request->validate([
            'razon_social'       => 'nullable|string|max:150',
            'nit'                => 'required|string|max:20',
            // El token es un JWT largo. La pantalla lo muestra entero y lo
            // manda tal cual, asi que aca llega completo o no llega.
            'token'              => 'nullable|string',
            // Se puede corregir a mano, pero normalmente sale del propio JWT.
            'token_expira'       => 'nullable|date',
            'codigo_modalidad'   => 'required|integer|in:1,2',
            'codigo_sistema'     => 'required|string|max:60',
            'codigo_sucursal'    => 'required|integer|min:0',
            'codigo_punto_venta' => 'required|integer|min:0',
            'url_siat'           => 'required|string|max:200',
            'url_siat2'          => 'required|string|max:200',
        ]);

        $config = SiatConfiguracion::activa();

        $datos['token'] = trim((string) $request->input('token')) ?: null;

        // Si no mandaron vencimiento se lee del claim `exp` del token, que es
        // la fuente real: nadie tiene que copiar la fecha a mano.
        if (empty($datos['token_expira'])) {
            $datos['token_expira'] = SiatConfiguracion::expiracionDelToken($datos['token']);
        }

        $config->update($datos);

        return response()->json([
            'message' => 'Datos de Impuestos guardados',
        ] + $this->estado($config->fresh()));
    }

    /** Prueba que el token y la URL sirvan, sin generar nada. */
    public function probar()
    {
        $siat = new SiatService();

        if ($faltan = $siat->configuracion()->faltantes()) {
            return response()->json([
                'message' => 'Falta ' . implode(', ', $faltan) . ' para poder conectarse',
            ], 422);
        }

        try {
            $respuesta = $siat->probarConexion();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo conectar al SIAT: ' . $siat->mensajeError($e),
            ], 422);
        }

        return response()->json([
            'message'   => 'Conexión correcta con el SIAT de producción',
            'respuesta' => json_decode(json_encode($respuesta), true),
        ]);
    }

    /** Historial de CUIS pedidos en el ambiente configurado. */
    public function cuis(Request $request)
    {
        $config = SiatConfiguracion::activa();

        return response()->json(
            SiatCui::where('codigo_ambiente', $config->codigo_ambiente)
                ->orderByDesc('id')
                ->limit($this->limite($request))
                ->get()
        );
    }

    /**
     * Pide un CUIS al SIAT y lo guarda.
     *
     * Si ya hay uno vigente no se pide otro salvo que manden forzar: el SIAT
     * devuelve siempre el mismo mientras dure, y cada llamada queda registrada
     * del lado de ellos.
     */
    public function generarCuis(Request $request)
    {
        $siat = new SiatService();
        $config = $siat->configuracion();

        if ($faltan = $config->faltantes()) {
            return response()->json([
                'message' => 'Falta ' . implode(', ', $faltan) . ' en los datos de Impuestos',
            ], 422);
        }

        $sucursal = (int) $request->input('codigo_sucursal', $config->codigo_sucursal);
        $puntoVenta = (int) $request->input('codigo_punto_venta', $config->codigo_punto_venta);

        $vigente = SiatCui::vigente($sucursal, $puntoVenta, $config->codigo_ambiente);
        if ($vigente && !$request->boolean('forzar')) {
            return response()->json([
                'message' => 'Ya hay un CUIS vigente hasta ' . $vigente->fecha_vigencia->format('d/m/Y H:i'),
                'cuis'    => $vigente,
            ], 422);
        }

        try {
            $cuis = $siat->pedirCuis($sucursal, $puntoVenta, optional($request->user())->CodAut);
        } catch (\Throwable $e) {
            return response()->json(['message' => $siat->mensajeError($e)], 422);
        }

        return response()->json([
            'message' => 'CUIS ' . $cuis->codigo . ' generado',
            'cuis'    => $cuis,
        ], 201);
    }

    /** Historial de CUFD pedidos en el ambiente configurado. */
    public function cufds(Request $request)
    {
        $config = SiatConfiguracion::activa();

        return response()->json(
            SiatCufd::where('codigo_ambiente', $config->codigo_ambiente)
                ->orderByDesc('id')
                ->limit($this->limite($request))
                ->get()
        );
    }

    /** Pide el CUFD del dia. Necesita un CUIS vigente del mismo punto de venta. */
    public function generarCufd(Request $request)
    {
        $siat = new SiatService();
        $config = $siat->configuracion();

        if ($faltan = $config->faltantes()) {
            return response()->json([
                'message' => 'Falta ' . implode(', ', $faltan) . ' en los datos de Impuestos',
            ], 422);
        }

        $sucursal = (int) $request->input('codigo_sucursal', $config->codigo_sucursal);
        $puntoVenta = (int) $request->input('codigo_punto_venta', $config->codigo_punto_venta);

        $vigente = SiatCufd::vigente($sucursal, $puntoVenta, $config->codigo_ambiente);
        if ($vigente && !$request->boolean('forzar')) {
            return response()->json([
                'message' => 'Ya hay un CUFD vigente hasta ' . $vigente->fecha_vigencia->format('d/m/Y H:i'),
                'cufd'    => $vigente,
            ], 422);
        }

        try {
            $cufd = $siat->pedirCufd($sucursal, $puntoVenta, optional($request->user())->CodAut);
        } catch (\Throwable $e) {
            return response()->json(['message' => $siat->mensajeError($e)], 422);
        }

        return response()->json([
            'message' => 'CUFD generado, vigente hasta ' . $cufd->fecha_vigencia->format('d/m/Y H:i'),
            'cufd'    => $cufd,
        ], 201);
    }

    /**
     * Da de baja un CUIS. Es borrado logico: las facturas ya emitidas se
     * firmaron con el, y si manana Impuestos observa una hay que poder
     * mostrar con que codigo se hizo.
     */
    public function eliminarCuis($id)
    {
        $cuis = SiatCui::find($id);

        if (!$cuis) {
            return response()->json(['message' => 'El CUIS no existe'], 404);
        }

        $cuis->delete();

        return response()->json(['message' => 'CUIS ' . $cuis->codigo . ' dado de baja']);
    }

    /** Igual que el CUIS: se marca, no se borra. */
    public function eliminarCufd($id)
    {
        $cufd = SiatCufd::find($id);

        if (!$cufd) {
            return response()->json(['message' => 'El CUFD no existe'], 404);
        }

        $cufd->delete();

        return response()->json(['message' => 'CUFD dado de baja']);
    }

    /**
     * Motivos de anulacion del catalogo del SIAT.
     *
     * Lo consume el dialogo de anulacion de la pantalla de facturacion: el
     * codigo que se elija ahi es el `codigoMotivo` que se le manda a Impuestos.
     */
    public function motivosAnulacion()
    {
        return response()->json(SiatMotivoAnulacion::vigentes());
    }

    /** Vuelve a traer el catalogo del SIAT, por si publicaron uno nuevo. */
    public function sincronizarMotivosAnulacion()
    {
        $siat = new SiatService();

        try {
            $motivos = $siat->sincronizarMotivosAnulacion();
        } catch (\Throwable $e) {
            return response()->json(['message' => $siat->mensajeError($e)], 422);
        }

        return response()->json([
            'message' => 'Se sincronizaron ' . count($motivos) . ' motivos de anulación',
            'motivos' => $motivos,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Facturas enviadas al SIAT                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Lo que se mando a facturar, para revisar que haya salido bien.
     *
     * Solo las que llegaron a tener CUF o que fallaron en el intento: una
     * venta con voucher no tiene nada que ver con Impuestos.
     */
    public function facturas(Request $request)
    {
        $query = Factura::query()
            ->where('tipo_comprobante', 'FACTURA')
            ->where(function ($w) {
                $w->whereNotNull('cuf')->orWhereNotNull('estado_siat');
            })
            ->orderByDesc('id');

        if ($desde = $request->input('desde')) {
            $query->whereDate('fecha', '>=', $desde);
        }
        if ($hasta = $request->input('hasta')) {
            $query->whereDate('fecha', '<=', $hasta);
        }
        if ($estado = $request->input('estado_siat')) {
            $query->where('estado_siat', $estado);
        }

        return response()->json(
            $query->limit($this->limite($request))->get([
                'id', 'fecha', 'hora', 'nro_factura', 'nit', 'nombre', 'total',
                'estado', 'estado_siat', 'mensaje_siat', 'codigo_recepcion',
                'cuf', 'codigo_sucursal', 'codigo_punto_venta', 'online',
            ])
        );
    }

    /**
     * Le pregunta al SIAT en que quedo una factura. Es la comprobacion de que
     * se facturo bien: el estado lo dice Impuestos, no nosotros.
     */
    public function verificarFactura($id)
    {
        $factura = Factura::find($id);
        if (!$factura) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }

        $siat = new SiatService();

        try {
            $resultado = $siat->verificarFactura($factura);
        } catch (\Throwable $e) {
            return response()->json(['message' => $siat->mensajeError($e)], 422);
        }

        return response()->json([
            'message' => 'El SIAT la reporta como ' . ($resultado['estado'] ?: 'sin estado')
                . ($resultado['mensaje'] ? ': ' . $resultado['mensaje'] : ''),
            'estado'  => $resultado['estado'],
            'factura' => $factura->fresh(),
        ]);
    }

    /**
     * Reintenta el envio de una factura que quedo en ERROR.
     *
     * Se vuelve a calcular todo (numero, CUF y CUFD del dia) porque el CUF
     * lleva dentro la fecha y hora: reusar el anterior seria mandar un codigo
     * que ya no corresponde.
     */
    public function reenviarFactura(Request $request, $id)
    {
        $factura = Factura::with('detalles')->find($id);
        if (!$factura) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }

        if ($factura->tipo_comprobante !== 'FACTURA') {
            return response()->json(['message' => 'Esa venta se entregó como voucher'], 422);
        }

        if ($factura->online && $factura->estado_siat === 'VALIDADA') {
            return response()->json([
                'message' => 'La factura ya está validada en el SIAT; no hace falta reenviarla',
            ], 422);
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

    /* ------------------------------------------------------------------ */

    /** Lo que consume la pantalla: datos + que codigos estan vigentes hoy. */
    private function estado(SiatConfiguracion $config)
    {
        $cuis = SiatCui::vigente($config->codigo_sucursal, $config->codigo_punto_venta, $config->codigo_ambiente);
        $cufd = SiatCufd::vigente($config->codigo_sucursal, $config->codigo_punto_venta, $config->codigo_ambiente);

        return [
            'configuracion' => [
                'razon_social'       => $config->razon_social,
                'nit'                => $config->nit,
                'codigo_modalidad'   => $config->codigo_modalidad,
                'codigo_sistema'     => $config->codigo_sistema,
                'codigo_sucursal'    => $config->codigo_sucursal,
                'codigo_punto_venta' => $config->codigo_punto_venta,
                'url_siat'           => $config->url_siat,
                'url_siat2'          => $config->url_siat2,
                // El token va entero: la pantalla lo muestra para poder
                // revisarlo y volver a mandarlo al guardar.
                'token'              => $config->token,
                'token_expira'       => optional($config->token_expira)->format('Y-m-d H:i:s'),
            ],
            'ambiente'      => 'PRODUCCIÓN',
            'modalidad'     => $config->modalidadNombre(),
            'url_base'      => $config->urlBase(),
            // La que termina impresa en el QR de la factura.
            'url_qr'        => rtrim((string) $config->url_siat2, '/') . '/consulta/QR',
            'faltantes'     => $config->faltantes(),
            'token_vencido' => $config->tokenVencido(),
            'soap'          => class_exists('SoapClient'),
            'cuis'          => $cuis,
            'cufd'          => $cufd,
        ];
    }

    private function limite(Request $request)
    {
        return min(max((int) $request->input('limite', 50), 1), 200);
    }
}
