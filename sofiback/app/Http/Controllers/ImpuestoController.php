<?php

namespace App\Http\Controllers;

use App\Models\SiatConfiguracion;
use App\Models\SiatCufd;
use App\Models\SiatCui;
use Illuminate\Http\Request;

/**
 * Modulo de Impuestos: datos del emisor ante el SIAT y obtencion de CUIS/CUFD.
 *
 * Todo sale de la base (tabla siat_configuraciones), no del .env: el token
 * delegado caduca cada tanto y quien lo renueva en la oficina de Impuestos no
 * es quien despliega el servidor. La pantalla /impuestos permite pegarlo y
 * volver a pedir los codigos sin tocar archivos.
 *
 * Los tres codigos van encadenados:
 *   CUIS -> dura ~1 anio, uno por sucursal y punto de venta.
 *   CUFD -> dura 24 horas, se pide con el CUIS.
 *   CUF  -> por factura, se calcula con el codigo de control del CUFD.
 *
 * De ahi que generarCufd() exija un CUIS vigente: sin el, el SIAT rechaza.
 */
class ImpuestoController extends Controller
{
    /** Estado completo para la pantalla: datos, faltantes y codigos vigentes. */
    public function configuracion()
    {
        $config = SiatConfiguracion::activa();

        return response()->json($this->estado($config));
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

    /**
     * Prueba que el token y la URL sirvan, sin generar nada.
     *
     * Se usa verificarComunicacion porque es la operacion mas barata del SIAT:
     * si responde, el token es valido y la URL del ambiente es la correcta.
     */
    public function probar()
    {
        $config = SiatConfiguracion::activa();

        if ($faltan = $config->faltantes()) {
            return response()->json([
                'message' => 'Falta ' . implode(', ', $faltan) . ' para poder conectarse',
            ], 422);
        }

        try {
            $client = $this->soap($config, 'FacturacionSincronizacion');
            $respuesta = $client->verificarComunicacion();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo conectar al SIAT: ' . $this->mensajeError($e),
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
                ->limit(min(max((int) $request->input('limite', 50), 1), 200))
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
        $config = SiatConfiguracion::activa();

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
            $client = $this->soap($config, 'FacturacionCodigos');
            $resultado = $client->cuis([
                'SolicitudCuis' => [
                    'codigoAmbiente'   => $config->codigo_ambiente,
                    'codigoModalidad'  => $config->codigo_modalidad,
                    'codigoPuntoVenta' => $puntoVenta,
                    'codigoSistema'    => $config->codigo_sistema,
                    'codigoSucursal'   => $sucursal,
                    'nit'              => $config->nit,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'El SIAT no respondió: ' . $this->mensajeError($e),
            ], 422);
        }

        $respuesta = isset($resultado->RespuestaCuis) ? $resultado->RespuestaCuis : null;

        if (!$respuesta || empty($respuesta->codigo)) {
            return response()->json([
                'message' => $this->mensajesSiat($respuesta, 'El SIAT no devolvió un CUIS'),
            ], 422);
        }

        $cuis = SiatCui::create([
            'codigo'             => $respuesta->codigo,
            'fecha_vigencia'     => $this->fecha(isset($respuesta->fechaVigencia) ? $respuesta->fechaVigencia : null),
            'codigo_sucursal'    => $sucursal,
            'codigo_punto_venta' => $puntoVenta,
            'codigo_ambiente'    => $config->codigo_ambiente,
            'user_id'            => optional($request->user())->CodAut,
            'respuesta'          => json_encode($respuesta),
        ]);

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
                ->limit(min(max((int) $request->input('limite', 50), 1), 200))
                ->get()
        );
    }

    /** Pide el CUFD del dia. Necesita un CUIS vigente del mismo punto de venta. */
    public function generarCufd(Request $request)
    {
        $config = SiatConfiguracion::activa();

        if ($faltan = $config->faltantes()) {
            return response()->json([
                'message' => 'Falta ' . implode(', ', $faltan) . ' en los datos de Impuestos',
            ], 422);
        }

        $sucursal = (int) $request->input('codigo_sucursal', $config->codigo_sucursal);
        $puntoVenta = (int) $request->input('codigo_punto_venta', $config->codigo_punto_venta);

        $cuis = SiatCui::vigente($sucursal, $puntoVenta, $config->codigo_ambiente);
        if (!$cuis) {
            return response()->json([
                'message' => 'No hay un CUIS vigente para esa sucursal y punto de venta; generalo primero',
            ], 422);
        }

        $vigente = SiatCufd::vigente($sucursal, $puntoVenta, $config->codigo_ambiente);
        if ($vigente && !$request->boolean('forzar')) {
            return response()->json([
                'message' => 'Ya hay un CUFD vigente hasta ' . $vigente->fecha_vigencia->format('d/m/Y H:i'),
                'cufd'    => $vigente,
            ], 422);
        }

        try {
            $client = $this->soap($config, 'FacturacionCodigos');
            $resultado = $client->cufd([
                'SolicitudCufd' => [
                    'codigoAmbiente'   => $config->codigo_ambiente,
                    'codigoModalidad'  => $config->codigo_modalidad,
                    'codigoPuntoVenta' => $puntoVenta,
                    'codigoSistema'    => $config->codigo_sistema,
                    'codigoSucursal'   => $sucursal,
                    'cuis'             => $cuis->codigo,
                    'nit'              => $config->nit,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'El SIAT no respondió: ' . $this->mensajeError($e),
            ], 422);
        }

        $respuesta = isset($resultado->RespuestaCufd) ? $resultado->RespuestaCufd : null;

        if (!$respuesta || empty($respuesta->codigo)) {
            return response()->json([
                'message' => $this->mensajesSiat($respuesta, 'El SIAT no devolvió un CUFD'),
            ], 422);
        }

        $cufd = SiatCufd::create([
            'codigo'             => $respuesta->codigo,
            'codigo_control'     => isset($respuesta->codigoControl) ? $respuesta->codigoControl : null,
            'direccion'          => isset($respuesta->direccion) ? $respuesta->direccion : null,
            'fecha_vigencia'     => $this->vigenciaCufd(isset($respuesta->fechaVigencia) ? $respuesta->fechaVigencia : null),
            'codigo_sucursal'    => $sucursal,
            'codigo_punto_venta' => $puntoVenta,
            'codigo_ambiente'    => $config->codigo_ambiente,
            'siat_cui_id'        => $cuis->id,
            'user_id'            => optional($request->user())->CodAut,
            'respuesta'          => json_encode($respuesta),
        ]);

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
            'ambiente'       => 'PRODUCCIÓN',
            'modalidad'      => $config->modalidadNombre(),
            'url_base'       => $config->urlBase(),
            // La que termina impresa en el QR de la factura.
            'url_qr'         => rtrim((string) $config->url_siat2, '/') . '/consulta/QR',
            'faltantes'      => $config->faltantes(),
            'token_vencido'  => $config->tokenVencido(),
            'soap'           => class_exists('SoapClient'),
            'cuis'           => $cuis,
            'cufd'           => $cufd,
        ];
    }

    /** Cliente SOAP del SIAT, con el token delegado en la cabecera apikey. */
    private function soap(SiatConfiguracion $config, $servicio)
    {
        if (!class_exists('SoapClient')) {
            throw new \RuntimeException('la extensión SOAP de PHP no está habilitada en el servidor');
        }

        return new \SoapClient($config->wsdl($servicio), [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => 'apikey: TokenApi ' . trim((string) $config->token),
                ],
            ]),
            'cache_wsdl'  => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace'       => 1,
            'use'         => SOAP_LITERAL,
            'style'       => SOAP_DOCUMENT,
        ]);
    }

    /**
     * El SIAT no usa codigos HTTP para los errores de negocio: responde 200 con
     * un mensajesList adentro. Sin esto el usuario solo veria "sin CUIS".
     */
    private function mensajesSiat($respuesta, $porDefecto)
    {
        $mensajes = isset($respuesta->mensajesList) ? $respuesta->mensajesList : null;

        if (!$mensajes) {
            return $porDefecto;
        }

        // Con un solo mensaje SoapClient devuelve el objeto suelto, no un array.
        if (!is_array($mensajes)) {
            $mensajes = [$mensajes];
        }

        $textos = [];
        foreach ($mensajes as $mensaje) {
            $codigo = isset($mensaje->codigo) ? $mensaje->codigo : '';
            $descripcion = isset($mensaje->descripcion) ? $mensaje->descripcion : '';
            $textos[] = trim($codigo . ' ' . $descripcion);
        }

        $texto = implode(' | ', array_filter($textos));

        return $texto !== '' ? $texto : $porDefecto;
    }

    private function mensajeError(\Throwable $e)
    {
        $mensaje = trim($e->getMessage());

        // Un token vencido llega como 401 dentro del texto del SoapFault y no
        // se entiende; se traduce para no mandar al usuario a leer logs.
        if (strpos($mensaje, '401') !== false || stripos($mensaje, 'unauthorized') !== false) {
            return 'el token delegado no es válido o está vencido (' . $mensaje . ')';
        }

        return $mensaje !== '' ? $mensaje : get_class($e);
    }

    private function fecha($valor)
    {
        if (!$valor) {
            return null;
        }

        $tiempo = strtotime($valor);

        return $tiempo ? date('Y-m-d H:i:s', $tiempo) : null;
    }

    /**
     * El CUFD no pasa de la medianoche aunque el SIAT devuelva otra cosa: es
     * diario. Se toma la menor de las dos para no darlo por bueno de mas.
     */
    private function vigenciaCufd($valor)
    {
        $finDelDia = strtotime(date('Y-m-d 23:59:59'));
        $delSiat = $valor ? strtotime($valor) : false;

        return date('Y-m-d H:i:s', $delSiat ? min($delSiat, $finDelDia) : $finDelDia);
    }

}
