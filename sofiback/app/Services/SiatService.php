<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\SiatConfiguracion;
use App\Models\SiatCufd;
use App\Models\SiatCui;
use App\Models\SiatMotivoAnulacion;
use Illuminate\Support\Facades\DB;

/**
 * Trato con el SIAT de Impuestos Nacionales.
 *
 * Es un port de los scripts sueltos de la carpeta `impuestos/` (CUF.php,
 * 01GeneracionCompraVenta.php, GenerateCui.php...) a algo que se pueda llamar
 * desde el sistema: mismos algoritmos y mismas llamadas SOAP, pero leyendo la
 * configuracion de la base y guardando lo que devuelve el SIAT.
 *
 * Los codigos van encadenados y en ese orden:
 *
 *   CUIS -> dura ~1 anio. Se pide una vez por sucursal y punto de venta.
 *   CUFD -> dura 24 horas. Se pide con el CUIS; trae el codigo de control.
 *   CUF  -> uno por factura. Se calcula aca (mod 11 + base 16) y termina con
 *           el codigo de control del CUFD del dia.
 *
 * Por eso `cufdDelDia()` genera solo lo que falte: al facturar a las 8 de la
 * manana nadie deberia tener que entrar antes a la pantalla de Impuestos a
 * apretar un boton.
 *
 * Modalidad 2 (COMPUTARIZADA EN LINEA): el XML va con raiz
 * <facturaComputarizadaCompraVenta> y el SIAT no exige firma XMLDSig, por eso
 * aca no se firma nada.
 */
class SiatService
{
    /** Documento sector 1 = COMPRA-VENTA. */
    const DOCUMENTO_SECTOR = 1;

    /** Emision 1 = EN LINEA. */
    const EMISION_EN_LINEA = 1;

    /** Tipo 1 = FACTURA CON DERECHO A CREDITO FISCAL. */
    const TIPO_FACTURA = 1;

    /** Moneda 1 = BOLIVIANO. */
    const MONEDA_BOLIVIANO = 1;

    /**
     * Leyenda de la actividad economica. El SIAT las publica en su catalogo;
     * esta es la que ya venia imprimiendo el sistema de caja.
     */
    const LEYENDA = 'Ley N° 453: El proveedor debe brindar atención sin discriminación, con respeto, calidez y cordialidad a los usuarios y consumidores.';

    /** @var SiatConfiguracion */
    private $config;

    public function __construct(SiatConfiguracion $config = null)
    {
        $this->config = $config ?: SiatConfiguracion::activa();
    }

    public function configuracion()
    {
        return $this->config;
    }

    /* ---------------------------------------------------------------- */
    /* Codigos                                                           */
    /* ---------------------------------------------------------------- */

    /**
     * CUIS vigente. Si no hay y $generar, lo pide al SIAT.
     *
     * @throws \RuntimeException si el SIAT no lo entrega.
     */
    public function cuisVigente($sucursal = null, $puntoVenta = null, $generar = true, $userId = null)
    {
        list($sucursal, $puntoVenta) = $this->serie($sucursal, $puntoVenta);

        $cuis = SiatCui::vigente($sucursal, $puntoVenta, $this->config->codigo_ambiente);
        if ($cuis || !$generar) {
            return $cuis;
        }

        return $this->pedirCuis($sucursal, $puntoVenta, $userId);
    }

    /** Pide un CUIS al SIAT sin mirar si ya hay uno vigente. */
    public function pedirCuis($sucursal = null, $puntoVenta = null, $userId = null)
    {
        list($sucursal, $puntoVenta) = $this->serie($sucursal, $puntoVenta);

        $respuesta = $this->llamar('FacturacionCodigos', 'cuis', [
            'SolicitudCuis' => [
                'codigoAmbiente'   => $this->config->codigo_ambiente,
                'codigoModalidad'  => $this->config->codigo_modalidad,
                'codigoPuntoVenta' => $puntoVenta,
                'codigoSistema'    => $this->config->codigo_sistema,
                'codigoSucursal'   => $sucursal,
                'nit'              => $this->config->nit,
            ],
        ], 'RespuestaCuis');

        if (empty($respuesta->codigo)) {
            throw new \RuntimeException($this->mensajes($respuesta, 'El SIAT no devolvió un CUIS'));
        }

        return SiatCui::create([
            'codigo'             => $respuesta->codigo,
            'fecha_vigencia'     => $this->fecha(isset($respuesta->fechaVigencia) ? $respuesta->fechaVigencia : null),
            'codigo_sucursal'    => $sucursal,
            'codigo_punto_venta' => $puntoVenta,
            'codigo_ambiente'    => $this->config->codigo_ambiente,
            'user_id'            => $userId,
            'respuesta'          => json_encode($respuesta),
        ]);
    }

    /**
     * CUFD del dia. Si no hay uno vigente lo pide solo, generando antes el
     * CUIS si tampoco existe: es lo que permite facturar sin pasos previos.
     *
     * @throws \RuntimeException si el SIAT no lo entrega.
     */
    public function cufdDelDia($sucursal = null, $puntoVenta = null, $generar = true, $userId = null)
    {
        list($sucursal, $puntoVenta) = $this->serie($sucursal, $puntoVenta);

        $cufd = SiatCufd::vigente($sucursal, $puntoVenta, $this->config->codigo_ambiente);
        if ($cufd || !$generar) {
            return $cufd;
        }

        return $this->pedirCufd($sucursal, $puntoVenta, $userId);
    }

    /** Pide un CUFD al SIAT sin mirar si ya hay uno vigente. */
    public function pedirCufd($sucursal = null, $puntoVenta = null, $userId = null)
    {
        list($sucursal, $puntoVenta) = $this->serie($sucursal, $puntoVenta);

        $cuis = $this->cuisVigente($sucursal, $puntoVenta, true, $userId);
        if (!$cuis) {
            throw new \RuntimeException('No hay un CUIS vigente y no se pudo generar');
        }

        $respuesta = $this->llamar('FacturacionCodigos', 'cufd', [
            'SolicitudCufd' => [
                'codigoAmbiente'   => $this->config->codigo_ambiente,
                'codigoModalidad'  => $this->config->codigo_modalidad,
                'codigoPuntoVenta' => $puntoVenta,
                'codigoSistema'    => $this->config->codigo_sistema,
                'codigoSucursal'   => $sucursal,
                'cuis'             => $cuis->codigo,
                'nit'              => $this->config->nit,
            ],
        ], 'RespuestaCufd');

        if (empty($respuesta->codigo)) {
            throw new \RuntimeException($this->mensajes($respuesta, 'El SIAT no devolvió un CUFD'));
        }

        return SiatCufd::create([
            'codigo'             => $respuesta->codigo,
            'codigo_control'     => isset($respuesta->codigoControl) ? $respuesta->codigoControl : null,
            'direccion'          => isset($respuesta->direccion) ? $respuesta->direccion : null,
            'fecha_vigencia'     => $this->vigenciaCufd(isset($respuesta->fechaVigencia) ? $respuesta->fechaVigencia : null),
            'codigo_sucursal'    => $sucursal,
            'codigo_punto_venta' => $puntoVenta,
            'codigo_ambiente'    => $this->config->codigo_ambiente,
            'siat_cui_id'        => $cuis->id,
            'user_id'            => $userId,
            'respuesta'          => json_encode($respuesta),
        ]);
    }

    /**
     * Prueba que el token y la URL sirvan, sin generar nada.
     *
     * verificarComunicacion es la operacion mas barata del SIAT: si responde,
     * el token es valido y la URL es la correcta.
     */
    public function probarConexion()
    {
        return $this->cliente('FacturacionSincronizacion')->verificarComunicacion();
    }

    /**
     * Calcula el CUF. Es el algoritmo publicado por Impuestos: se arma una
     * cadena con los campos rellenados con ceros, se le pega el digito
     * verificador modulo 11 y todo eso se pasa a base 16.
     *
     * @param string $fechaHora en formato YmdHis + 3 digitos de milisegundo.
     */
    public function calcularCuf($fechaHora, $sucursal, $puntoVenta, $numeroFactura, $codigoControl)
    {
        $cadena = str_pad($this->config->nit, 13, '0', STR_PAD_LEFT)
            . $fechaHora
            . str_pad($sucursal, 4, '0', STR_PAD_LEFT)
            . $this->config->codigo_modalidad
            . self::EMISION_EN_LINEA
            . self::TIPO_FACTURA
            . str_pad(self::DOCUMENTO_SECTOR, 2, '0', STR_PAD_LEFT)
            . str_pad($numeroFactura, 10, '0', STR_PAD_LEFT)
            . str_pad($puntoVenta, 4, '0', STR_PAD_LEFT);

        $cadena .= $this->digitoMod11($cadena);

        return $this->base16($cadena) . $codigoControl;
    }

    /* ---------------------------------------------------------------- */
    /* Emision                                                           */
    /* ---------------------------------------------------------------- */

    /**
     * Emite una factura ya registrada: pide el CUFD del dia si falta, calcula
     * el CUF, arma el XML, lo valida contra el XSD y lo manda al SIAT.
     *
     * Devuelve la misma factura actualizada. No lanza excepciones de negocio
     * hacia afuera: si algo falla, la factura queda con estado_siat = ERROR y
     * el motivo en mensaje_siat, para no perder la venta que ya se cobro.
     */
    public function emitirFactura(Factura $factura, $userId = null)
    {
        try {
            return $this->emitir($factura, $userId);
        } catch (\Throwable $e) {
            $factura->update([
                'estado_siat'  => 'ERROR',
                'mensaje_siat' => $this->mensajeError($e),
                'online'       => false,
            ]);

            return $factura;
        }
    }

    private function emitir(Factura $factura, $userId)
    {
        if ($faltan = $this->config->faltantes()) {
            throw new \RuntimeException('Falta ' . implode(', ', $faltan) . ' en los datos de Impuestos');
        }

        if ((float) $factura->total <= 0) {
            throw new \RuntimeException('El SIAT no acepta facturas con importe cero');
        }

        list($sucursal, $puntoVenta) = $this->serie();

        $cufd = $this->cufdDelDia($sucursal, $puntoVenta, true, $userId);
        if (!$cufd) {
            throw new \RuntimeException('No hay un CUFD vigente y no se pudo generar');
        }

        // El CUIS puede haber caducado aunque el CUFD del dia siga vivo, y en
        // ese caso cufdDelDia() ni lo mira. Se pide aparte para no reventar
        // despues al armar la solicitud.
        $cuis = $this->cuisVigente($sucursal, $puntoVenta, true, $userId);
        if (!$cuis) {
            throw new \RuntimeException('No hay un CUIS vigente y no se pudo generar');
        }

        // Milisegundos propios: el CUF los exige y dos facturas del mismo
        // segundo tienen que dar codigos distintos.
        $ahora = microtime(true);
        $mili = str_pad((string) ((int) (($ahora - floor($ahora)) * 1000)), 3, '0', STR_PAD_LEFT);
        $fechaEmision = date('Y-m-d\TH:i:s', (int) $ahora) . '.' . $mili;

        $numero = $this->siguienteNumero($sucursal, $puntoVenta);

        $cuf = $this->calcularCuf(
            date('YmdHis', (int) $ahora) . $mili,
            $sucursal,
            $puntoVenta,
            $numero,
            (string) $cufd->codigo_control
        );

        $factura->update([
            'nro_factura'        => $numero,
            'codigo_sucursal'    => $sucursal,
            'codigo_punto_venta' => $puntoVenta,
            'cuf'                => $cuf,
            'cufd'               => $cufd->codigo,
            'codigo_control'     => $cufd->codigo_control,
            'leyenda'            => self::LEYENDA,
            'fecha_emision'      => $fechaEmision,
            'estado_siat'        => 'PENDIENTE',
            'mensaje_siat'       => null,
        ]);

        $xml = $this->armarXml($factura, $cufd, $fechaEmision);
        $this->validarXsd($xml);

        // Se conserva antes de llamar al SIAT. Si el servicio se cae, este es
        // el documento que luego puede enviarse como paquete de contingencia.
        $factura->update(['xml' => $xml]);

        $comprimido = gzencode($xml, 9);

        $respuesta = $this->llamar('ServicioFacturacionCompraVenta', 'recepcionFactura', [
            'SolicitudServicioRecepcionFactura' => [
                'codigoAmbiente'        => $this->config->codigo_ambiente,
                'codigoDocumentoSector' => self::DOCUMENTO_SECTOR,
                'codigoEmision'         => self::EMISION_EN_LINEA,
                'codigoModalidad'       => $this->config->codigo_modalidad,
                'codigoPuntoVenta'      => $puntoVenta,
                'codigoSistema'         => $this->config->codigo_sistema,
                'codigoSucursal'        => $sucursal,
                'cufd'                  => $cufd->codigo,
                'cuis'                  => $cuis->codigo,
                'nit'                   => $this->config->nit,
                'tipoFacturaDocumento'  => self::TIPO_FACTURA,
                'archivo'               => $comprimido,
                'fechaEnvio'            => $fechaEmision,
                'hashArchivo'           => hash('sha256', $comprimido),
            ],
        ], 'RespuestaServicioFacturacion');

        $estado = isset($respuesta->codigoDescripcion) ? strtoupper($respuesta->codigoDescripcion) : null;

        $factura->update([
            'codigo_recepcion' => isset($respuesta->codigoRecepcion) ? $respuesta->codigoRecepcion : null,
            'estado_siat'      => $estado ?: 'DESCONOCIDO',
            'mensaje_siat'     => $this->mensajes($respuesta, ''),
            // Solo se da por emitida si el SIAT la acepto.
            'online'           => in_array($estado, ['VALIDADA', 'PENDIENTE', 'RECIBIDA'], true),
        ]);

        return $factura->fresh();
    }

    /**
     * Pregunta al SIAT en que quedo una factura ya enviada. Es lo que permite
     * comprobar desde la pantalla de Impuestos que se facturo bien.
     */
    public function verificarFactura(Factura $factura)
    {
        if (!$factura->cuf) {
            throw new \RuntimeException('La factura no fue enviada al SIAT: no tiene CUF');
        }

        $cufd = $this->cufdDelDia($factura->codigo_sucursal, $factura->codigo_punto_venta, true);
        $cuis = $this->cuisVigente($factura->codigo_sucursal, $factura->codigo_punto_venta, true);

        $respuesta = $this->llamar('ServicioFacturacionCompraVenta', 'verificacionEstadoFactura', [
            'SolicitudServicioVerificacionEstadoFactura' => [
                'codigoAmbiente'        => $this->config->codigo_ambiente,
                'codigoDocumentoSector' => self::DOCUMENTO_SECTOR,
                'codigoEmision'         => self::EMISION_EN_LINEA,
                'codigoModalidad'       => $this->config->codigo_modalidad,
                'codigoPuntoVenta'      => (int) $factura->codigo_punto_venta,
                'codigoSistema'         => $this->config->codigo_sistema,
                'codigoSucursal'        => (int) $factura->codigo_sucursal,
                'cufd'                  => $cufd->codigo,
                'cuis'                  => $cuis->codigo,
                'nit'                   => $this->config->nit,
                'tipoFacturaDocumento'  => self::TIPO_FACTURA,
                'cuf'                   => $factura->cuf,
            ],
        ], 'RespuestaServicioFacturacion');

        $estado = isset($respuesta->codigoDescripcion) ? strtoupper($respuesta->codigoDescripcion) : null;

        $factura->update([
            'estado_siat'  => $estado ?: $factura->estado_siat,
            'mensaje_siat' => $this->mensajes($respuesta, ''),
            'online'       => $estado === 'VALIDADA' ? true : $factura->online,
        ]);

        return [
            'estado'    => $estado,
            'mensaje'   => $this->mensajes($respuesta, 'Sin mensajes del SIAT'),
            'respuesta' => json_decode(json_encode($respuesta), true),
        ];
    }

    /** Anula en el SIAT una factura ya validada. */
    public function anularFactura(Factura $factura, $codigoMotivo = 1)
    {
        if (!$factura->cuf) {
            throw new \RuntimeException('La factura no fue enviada al SIAT: no tiene CUF');
        }

        $cufd = $this->cufdDelDia($factura->codigo_sucursal, $factura->codigo_punto_venta, true);
        $cuis = $this->cuisVigente($factura->codigo_sucursal, $factura->codigo_punto_venta, true);

        $respuesta = $this->llamar('ServicioFacturacionCompraVenta', 'anulacionFactura', [
            'SolicitudServicioAnulacionFactura' => [
                'codigoAmbiente'        => $this->config->codigo_ambiente,
                'codigoDocumentoSector' => self::DOCUMENTO_SECTOR,
                'codigoEmision'         => self::EMISION_EN_LINEA,
                'codigoModalidad'       => $this->config->codigo_modalidad,
                'codigoPuntoVenta'      => (int) $factura->codigo_punto_venta,
                'codigoSistema'         => $this->config->codigo_sistema,
                'codigoSucursal'        => (int) $factura->codigo_sucursal,
                'cufd'                  => $cufd->codigo,
                'cuis'                  => $cuis->codigo,
                'nit'                   => $this->config->nit,
                'tipoFacturaDocumento'  => self::TIPO_FACTURA,
                'codigoMotivo'          => $codigoMotivo,
                'cuf'                   => $factura->cuf,
            ],
        ], 'RespuestaServicioFacturacion');

        $estado = isset($respuesta->codigoDescripcion) ? strtoupper($respuesta->codigoDescripcion) : null;
        $transaccion = isset($respuesta->transaccion) ? (bool) $respuesta->transaccion : false;
        $mensaje = $this->mensajes($respuesta, $estado ?: 'El SIAT no confirmó la anulación');

        $factura->update([
            'estado_siat'  => $estado ?: $factura->estado_siat,
            'mensaje_siat' => $mensaje,
        ]);

        return [
            'transaccion' => $transaccion,
            'estado'      => $estado,
            'mensaje'     => $mensaje,
        ];
    }

    /* ---------------------------------------------------------------- */
    /* XML                                                               */
    /* ---------------------------------------------------------------- */

    /**
     * Arma el XML de la factura computarizada.
     *
     * Los datos del producto que exige Impuestos (actividad economica y
     * codigo de producto SIN) salen de tbproductos, que ya los tiene cargados
     * en codgruppasin y codProdSin; la unidad se traduce con tbunidmed, cuyo
     * codUnid es justamente el codigo del catalogo del SIAT.
     */
    private function armarXml(Factura $factura, SiatCufd $cufd, $fechaEmision)
    {
        $emisor = config('siat.emisor');

        // No usar map('trim'): Collection pasa tambien la clave como segundo
        // argumento y trim la interpreta como mascara de caracteres. Eso
        // convertia, por ejemplo, la segunda linea 100002 en 00002.
        $codigos = $factura->detalles->pluck('cod_prod')->map(function ($codigo) {
            return trim((string) $codigo);
        })->unique();

        $productos = DB::table('tbproductos as p')
            ->leftJoin('tbunidmed as u', DB::raw('TRIM(u.Codmed)'), '=', DB::raw('TRIM(p.codUnid)'))
            ->whereIn(DB::raw('TRIM(p.cod_prod)'), $codigos)
            ->get([
                DB::raw('TRIM(p.cod_prod) as cod_prod'),
                DB::raw('TRIM(p.codProdSin) as producto_sin'),
                DB::raw('TRIM(p.codgruppasin) as actividad'),
                'u.codUnid as unidad_sin',
            ])
            ->keyBy('cod_prod');

        // Producto fiscal comodin autorizado por el negocio. Solo presta los
        // campos exigidos por el SIAT cuando el producto vendido no los tiene;
        // la descripcion, cantidad y precio continúan siendo los de la venta.
        $respaldo = DB::table('tbproductos as p')
            ->leftJoin('tbunidmed as u', DB::raw('TRIM(u.Codmed)'), '=', DB::raw('TRIM(p.codUnid)'))
            ->whereRaw('TRIM(p.cod_prod) = ?', ['541623'])
            ->first([
                DB::raw('TRIM(p.cod_prod) as cod_prod'),
                DB::raw('TRIM(p.codProdSin) as producto_sin'),
                DB::raw('TRIM(p.codgruppasin) as actividad'),
                'u.codUnid as unidad_sin',
            ]);

        if (!$respaldo || !$respaldo->producto_sin || !$respaldo->actividad) {
            throw new \RuntimeException(
                'El producto de respaldo 541623 no tiene código SIN o actividad económica cargados'
            );
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $raiz = $doc->createElement('facturaComputarizadaCompraVenta');
        $raiz->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $raiz->setAttribute('xsi:noNamespaceSchemaLocation', 'facturaComputarizadaCompraVenta.xsd');
        $doc->appendChild($raiz);

        $cabecera = $doc->createElement('cabecera');
        $raiz->appendChild($cabecera);

        $documento = preg_replace('/\D/', '', (string) $factura->nit);

        $campos = [
            'nitEmisor'                   => $this->config->nit,
            'razonSocialEmisor'           => $this->config->razon_social ?: $emisor['nombre'],
            'municipio'                   => $emisor['ciudad'],
            'telefono'                    => $emisor['telefono'],
            'numeroFactura'               => $factura->nro_factura,
            'cuf'                         => $factura->cuf,
            'cufd'                        => $cufd->codigo,
            'codigoSucursal'              => $factura->codigo_sucursal,
            'direccion'                   => $cufd->direccion ?: $emisor['direccion'],
            'codigoPuntoVenta'            => $factura->codigo_punto_venta,
            'fechaEmision'                => $fechaEmision,
            'nombreRazonSocial'           => $factura->nombre ?: 'S/N',
            'codigoTipoDocumentoIdentidad' => $this->tipoDocumento($documento),
            'numeroDocumento'             => $documento,
            'complemento'                 => null,
            'codigoCliente'               => $factura->cliente_id ?: $documento,
            'codigoMetodoPago'            => $this->metodoPago($factura->tipo_pago),
            'numeroTarjeta'               => null,
            'montoTotal'                  => $this->monto($factura->total),
            'montoTotalSujetoIva'         => $this->monto($factura->total),
            'codigoMoneda'                => self::MONEDA_BOLIVIANO,
            'tipoCambio'                  => '1.00',
            'montoTotalMoneda'            => $this->monto($factura->total),
            'montoGiftCard'               => null,
            'descuentoAdicional'          => $this->monto($factura->descuento),
            'codigoExcepcion'             => null,
            'cafc'                        => null,
            'leyenda'                     => self::LEYENDA,
            // `personal` no tiene columna de usuario; el CI es lo unico corto
            // y unico que identifica a quien emitio.
            'usuario'                     => trim((string) ($factura->usuario->ci ?? '')) ?: 'sistema',
            'codigoDocumentoSector'       => self::DOCUMENTO_SECTOR,
        ];

        foreach ($campos as $nombre => $valor) {
            $cabecera->appendChild($this->nodo($doc, $nombre, $valor));
        }

        foreach ($factura->detalles as $linea) {
            $cod = trim($linea->cod_prod);
            $prod = $productos->get($cod);
            $usaRespaldo = !$prod || !$prod->producto_sin || !$prod->actividad;

            $fiscal = $usaRespaldo ? $respaldo : $prod;

            $detalle = $doc->createElement('detalle');
            $raiz->appendChild($detalle);

            $lineas = [
                'actividadEconomica' => $fiscal->actividad,
                'codigoProductoSin'  => $fiscal->producto_sin,
                'codigoProducto'     => $usaRespaldo ? $respaldo->cod_prod : $cod,
                'descripcion'        => $linea->nombre,
                'cantidad'           => $this->monto($linea->cantidad),
                // Sin unidad cargada se manda 57 (UNIDAD), que es el comodin
                // del catalogo del SIAT.
                'unidadMedida'       => ($prod->unidad_sin ?? null) ?: ($respaldo->unidad_sin ?: 57),
                'precioUnitario'     => $this->monto($linea->precio),
                'montoDescuento'     => '0.00',
                'subTotal'           => $this->monto($linea->subtotal),
                'numeroSerie'        => null,
                'numeroImei'         => null,
            ];

            foreach ($lineas as $nombre => $valor) {
                $detalle->appendChild($this->nodo($doc, $nombre, $valor));
            }
        }

        return $doc->saveXML();
    }

    /** Los campos vacios van como <campo xsi:nil="true"/>, no como <campo/>. */
    private function nodo(\DOMDocument $doc, $nombre, $valor)
    {
        if ($valor === null || $valor === '') {
            $nodo = $doc->createElement($nombre);
            $nodo->setAttribute('xsi:nil', 'true');

            return $nodo;
        }

        return $doc->createElement($nombre, htmlspecialchars((string) $valor, ENT_XML1));
    }

    /**
     * Valida contra el XSD antes de mandar. Sin esto el SIAT devuelve un
     * rechazo generico y no se sabe que campo estaba mal.
     */
    private function validarXsd($xml)
    {
        $xsd = resource_path('siat/facturaComputarizadaCompraVenta.xsd');
        if (!is_file($xsd)) {
            return;
        }

        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $valido = $doc->schemaValidate($xsd);

        $errores = array_map(function ($e) {
            return trim($e->message);
        }, libxml_get_errors());

        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        if (!$valido) {
            throw new \RuntimeException('El XML no cumple el formato del SIAT: ' . implode(' | ', $errores));
        }
    }

    /* ---------------------------------------------------------------- */
    /* Apoyo                                                             */
    /* ---------------------------------------------------------------- */

    /**
     * Siguiente numero de la serie.
     *
     * Se mira tambien tbfactura porque el sistema de caja de escritorio
     * factura contra el mismo NIT y punto de venta: si cada uno llevara su
     * propio contador, el SIAT recibiria dos facturas con el mismo numero.
     */
    private function siguienteNumero($sucursal, $puntoVenta)
    {
        $propio = (int) Factura::where('codigo_sucursal', $sucursal)
            ->where('codigo_punto_venta', $puntoVenta)
            ->max('nro_factura');

        $caja = (int) DB::table('tbfactura')->where('PuntVenta', $puntoVenta)->max('nrofac');

        return max($propio, $caja) + 1;
    }

    /** Sucursal y punto de venta a usar, con lo configurado por defecto. */
    private function serie($sucursal = null, $puntoVenta = null)
    {
        return [
            $sucursal === null ? (int) $this->config->codigo_sucursal : (int) $sucursal,
            $puntoVenta === null ? (int) $this->config->codigo_punto_venta : (int) $puntoVenta,
        ];
    }

    /**
     * 5 = NIT, 1 = CI. Se decide por el largo: en Bolivia un NIT tiene 9 o mas
     * digitos y una cedula no llega a eso.
     */
    private function tipoDocumento($documento)
    {
        return strlen($documento) >= 9 ? 5 : 1;
    }

    /** Catalogo de metodos de pago del SIAT, con lo que usa la pantalla. */
    private function metodoPago($tipoPago)
    {
        $mapa = [
            'EFECTIVO'      => 1,
            'TARJETA'       => 2,
            'CHEQUE'        => 3,
            'VALES'         => 4,
            'OTRO'          => 5,
            'CREDITO'       => 6,
            'TRANSFERENCIA' => 7,
            'DEPOSITO'      => 8,
        ];

        $clave = strtoupper(trim((string) $tipoPago));

        return isset($mapa[$clave]) ? $mapa[$clave] : 1;
    }

    /** El SIAT exige exactamente dos decimales con punto. */
    private function monto($valor)
    {
        return number_format((float) $valor, 2, '.', '');
    }

    /* ---------------------------------------------------------------- */
    /* SOAP                                                              */
    /* ---------------------------------------------------------------- */

    /** Llama una operacion del SIAT y devuelve el nodo de respuesta pedido. */
    private function llamar($servicio, $operacion, array $parametros, $nodo)
    {
        $resultado = $this->cliente($servicio)->$operacion($parametros);

        if (!isset($resultado->$nodo)) {
            throw new \RuntimeException('El SIAT respondió sin ' . $nodo);
        }

        return $resultado->$nodo;
    }

    private function cliente($servicio)
    {
        if (!class_exists('SoapClient')) {
            throw new \RuntimeException('la extensión SOAP de PHP no está habilitada en el servidor');
        }

        return new \SoapClient($this->config->wsdl($servicio), [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => 'apikey: TokenApi ' . trim((string) $this->config->token),
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
     * El SIAT no usa codigos HTTP para los errores de negocio: responde 200
     * con un mensajesList adentro.
     */
    public function mensajes($respuesta, $porDefecto)
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

    public function mensajeError(\Throwable $e)
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

    /* ---------------------------------------------------------------- */
    /* Algoritmo del CUF (modulo 11 + base 16)                           */
    /* ---------------------------------------------------------------- */

    /** Digito verificador modulo 11 tal como lo publica Impuestos. */
    private function digitoMod11($cadena, $limiteMultiplicador = 9)
    {
        $suma = 0;
        $multiplicador = 2;

        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $suma += $multiplicador * (int) substr($cadena, $i, 1);

            if (++$multiplicador > $limiteMultiplicador) {
                $multiplicador = 2;
            }
        }

        $digito = $suma % 11;

        if ($digito === 10) {
            return '1';
        }
        if ($digito === 11) {
            return '0';
        }

        return (string) $digito;
    }

    /**
     * Base 16 sobre el numero entero completo. Va con bcmath porque la cadena
     * tiene ~40 digitos y no entra en un int de PHP.
     */
    private function base16($numero, $mayusculas = true)
    {
        $hex = '0123456789abcdef';
        $salida = '';

        $numero = ltrim((string) $numero, '0');
        if ($numero === '') {
            return '0';
        }

        while (bccomp($numero, '0') > 0) {
            $salida = $hex[(int) bcmod($numero, '16')] . $salida;
            $numero = bcdiv($numero, '16', 0);
        }

        return $mayusculas ? strtoupper($salida) : $salida;
    }
}
