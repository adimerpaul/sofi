<?php

namespace App\Http\Controllers;

use App\Mail\TestMail;
use App\Models\Cliente;
use App\Models\CompraDetalle;
use App\Models\Cufd;
use App\Models\Cui;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;
use DOMDocument;
class VentaController extends Controller{
    public function anular(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $venta = Venta::with('ventaDetalles')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($venta->estado === 'Anulada') {
                return response()->json(['message' => 'La venta ya ha sido anulada.'], 400);
            }

            foreach ($venta->ventaDetalles as $detalle) {
                $productoId    = (int) $detalle->producto_id;
                $porRestituir  = (float) $detalle->cantidad;
                $cdDetalleId   = $detalle->compra_detalle_id ?? null;

                // 1) Devolver al mismo lote si existe
                if (!empty($cdDetalleId)) {
                    $cd = CompraDetalle::where('id', $cdDetalleId)
                        ->lockForUpdate()
                        ->first();

                    if ($cd && (int)$cd->producto_id === $productoId && $cd->estado === 'Activo') {
                        // No superar la cantidad comprada original
                        $capacidad = max(0.0, (float)$cd->cantidad - (float)$cd->cantidad_venta);
                        $sumar     = min($porRestituir, $capacidad);

                        if ($sumar > 0) {
                            $cd->cantidad_venta = (float)$cd->cantidad_venta + $sumar;
                            $cd->save();
                            $porRestituir -= $sumar;
                        }
                    }
                }

                // 2) Si queda saldo por restituir (detalle viejo sin lote o capacidad agotada) -> FIFO
                if ($porRestituir > 0) {
                    $lotes = CompraDetalle::where('producto_id', $productoId)
                        ->where('estado', 'Activo')
                        ->whereNull('deleted_at')
                        ->orderByRaw("CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END, fecha_vencimiento ASC")
                        ->lockForUpdate()
                        ->get(['id','cantidad','cantidad_venta']);

                    foreach ($lotes as $l) {
                        if ($porRestituir <= 0) break;

                        $capacidad = (float)$l->cantidad - (float)$l->cantidad_venta;
                        if ($capacidad <= 0) continue;

                        $sumar = min($capacidad, $porRestituir);
                        $l->cantidad_venta = (float)$l->cantidad_venta + $sumar;
                        $l->save();

                        $porRestituir -= $sumar;
                    }

                    if ($porRestituir > 1e-9) {
                        abort(422, 'No fue posible restaurar completamente el stock por lotes (capacidad insuficiente).');
                    }
                }
                #5 anular en impuesto
                $Impuestos = new ImpuestoController();
                $Impuestos->anularImpuestos($venta->cuf);
            }

            $venta->estado = 'Anulada';
            $venta->save();

            $client = Cliente::find($venta->cliente_id);
            if ($client->email != '') {
                $details = [
                    "title" => "Factura",
                    "body" => "Factura anulada",
                    "online" => true,
                    "anulado" => true,
                    "cuf" => $venta->cuf,
                    "numeroFactura" => $venta->id,
                    "sale_id" => $venta->id,
                    "carpeta" => "archivos",
                ];
                Mail::to($client->email)->send(new TestMail($details));
            }


            return response()->json([
                'message' => 'Venta anulada y stock restituido correctamente.',
                'venta'   => $venta,
            ]);
        });
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'ci'        => 'nullable|string',
            'nombre'    => 'nullable|string',
            'tipo_pago' => 'required|string|in:Efectivo,QR',
            'facturado' => 'nullable|boolean',
            'agencia'   => 'nullable|string',
            'productos' => 'required|array|min:1',

            'productos.*.producto_id'       => 'required|integer|exists:productos,id',
            'productos.*.cantidad'          => 'required|numeric|min:0.01',
            'productos.*.precio'            => 'required|numeric|min:0',
            'productos.*.compra_detalle_id' => 'nullable|integer|exists:compra_detalles,id',
        ]);
//        if (isset($data['ci']) && ($data['ci'] === '0' || $data['ci'] === 0)) {
//            return response()->json(['message' => 'El campo CI/NIT no puede ser cero.'], 422);
//        }

        $user    = $request->user();
        $cliente = $this->clienteUpdateOrCreate($request);

        $codigoPuntoVenta   = 0;
        $codigoSucursal     = 0;

        $cui = Cui::where('codigoPuntoVenta', $codigoPuntoVenta)
            ->where('codigoSucursal', $codigoSucursal)
            ->where('fechaVigencia', '>=', now())
            ->first();

        if (!$cui) {
            return response()->json(['message' => 'No existe CUI para la venta!!'], 400);
        }

        $cufd = Cufd::where('codigoPuntoVenta', $codigoPuntoVenta)
            ->where('codigoSucursal', $codigoSucursal)
            ->where('fechaVigencia', '>=', now())
            ->first();
        if (!$cufd) {
            return response()->json(['message' => 'No existe CUFD para la venta!!'], 400);
        }

        return DB::transaction(function () use ($data, $user, $cliente, $cufd, $cui) {
            error_log('Cliente: ' . json_encode($cliente));

            // 1) Venta
            $venta = Venta::create([
                'user_id'          => $user?->id,
                'cliente_id'       => $cliente?->id,
                'ci'               => $data['ci'] ?? null,
                'nombre'           => $data['nombre'] ?? null,
                'fecha'            => now()->toDateString(),
                'hora'             => now()->format('H:i:s'),
                'estado'           => 'Activo',
                'tipo_comprobante' => 'NOTA',
                'tipo_pago'        => $data['tipo_pago'],
                'facturado'        => (bool) ($data['facturado'] ?? false),
                'factura_estado'   => !empty($data['facturado']) ? 'PENDIENTE' : 'SIN_GESTION',
                'agencia'          => $data['agencia'] ?? ($user->agencia ?? null),
                'total'            => 0,
            ]);

            $total = 0.0;

            foreach ($data['productos'] as $item) {
                $productoId = (int) $item['producto_id'];
                $cantidad   = (float) $item['cantidad'];
                $precio     = (float) $item['precio'];

                // Snapshot de nombre del producto (para guardar en el detalle)
                $producto = Producto::select('id','nombre')->findOrFail($productoId);
                $nombreProducto = $producto->nombre;

                // 2) Si viene lote seleccionado, crear UN detalle amarrado a ese lote
                if (!empty($item['compra_detalle_id'])) {
                    $loteId = (int) $item['compra_detalle_id'];

                    $cd = CompraDetalle::where('id', $loteId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ((int)$cd->producto_id !== $productoId) {
                        abort(422, 'El lote seleccionado no corresponde al producto.');
                    }
                    if ($cd->estado !== 'Activo') {
                        abort(422, 'El lote seleccionado no está activo.');
                    }
                    if ((float)$cd->cantidad_venta < $cantidad) {
                        abort(422, 'Stock insuficiente en el lote seleccionado.');
                    }

                    // Crear detalle con snapshot completo
                    VentaDetalle::create([
                        'venta_id'          => $venta->id,
                        'producto_id'       => $productoId,
                        'cantidad'          => $cantidad,
                        'precio'            => $precio,
                        'nombre'            => $nombreProducto,
                        'lote'              => $cd->lote,
                        'fecha_vencimiento' => $cd->fecha_vencimiento,
                        'compra_detalle_id' => $cd->id,
                    ]);

                    // Descontar del lote
                    $cd->cantidad_venta = (float)$cd->cantidad_venta - $cantidad;
                    $cd->save();

                    $total += $cantidad * $precio;
                    continue; // Siguiente item
                }

                // 3) Si NO enviaron lote: FIFO por vencimiento -> puede PARTIRSE en varios detalles
                $restante = $cantidad;

                $lotes = CompraDetalle::where('producto_id', $productoId)
                    ->where('estado', 'Activo')
                    ->whereNull('deleted_at')
                    ->where('cantidad_venta', '>', 0)
                    ->orderByRaw("CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END, fecha_vencimiento ASC")
                    ->lockForUpdate()
                    ->get(['id','cantidad_venta','lote','fecha_vencimiento']);

                foreach ($lotes as $l) {
                    if ($restante <= 0) break;

                    $take = min((float)$l->cantidad_venta, $restante);
                    if ($take <= 0) continue;

                    // Crear un detalle POR CADA LOTE consumido
                    VentaDetalle::create([
                        'venta_id'          => $venta->id,
                        'producto_id'       => $productoId,
                        'cantidad'          => $take,
                        'precio'            => $precio,
                        'nombre'            => $nombreProducto,
                        'lote'              => $l->lote,
                        'fecha_vencimiento' => $l->fecha_vencimiento,
                        'compra_detalle_id' => $l->id,
                        'online'            => false,
                    ]);

                    // Descontar del lote
                    $l->cantidad_venta = (float)$l->cantidad_venta - $take;
                    $l->save();

                    $total    += $take * $precio;
                    $restante -= $take;
                }

                if ($restante > 1e-9) {
                    abort(422, 'Stock insuficiente por lotes.');
                }
            }

            // 4) Total final
            $venta->update(['total' => $total]);
            if ($venta->ci === '0' || $venta->ci === 0 || $venta->ci === null || $venta->ci === '') {
                return response()->json(
                    $venta->load('cliente','ventaDetalles.producto')
                );
            }


            //5) mandar a impuestos
            $leyendas = [
                "Ley N° 453: Puedes acceder a la reclamación cuando tus derechos han sido vulnerados.",
                "Ley N° 453: Puedes acceder a la reclamación cuando tus derechos han sido vulnerados.",
                "Ley N° 453: El proveedor debe brindar atención sin discriminación, con respeto, calidez y cordialidad a los usuarios y consumidores.",
                "Ley N° 453: El proveedor debe brindar atención sin discriminación, con respeto, calidez y cordialidad a los usuarios y consumidores.",
                "Ley N° 453: Está prohibido importar, distribuir o comercializar productos expirados o prontos a expirar.",
                "Ley N° 453: Los alimentos declarados de primera necesidad deben ser suministrados de manera adecuada, oportuna, continua y a precio justo.",
                "Ley N° 453: Tienes derecho a recibir información sobre las características y contenidos de los productos que consumes.",
                "Ley N° 453: Tienes derecho a un trato equitativo sin discriminación en la oferta de productos.",
                "Ley N° 453: Está prohibido importar, distribuir o comercializar productos prohibidos o retirados en el país de origen por atentar a la integridad física y a la salud.",
                "Ley N° 453: El proveedor deberá entregar el producto en las modalidades y términos ofertados o convenidos.",
                "Ley N° 453: En caso de incumplimiento a lo ofertado o convenido, el proveedor debe reparar o sustituir el producto..",
                "Ley N° 453: Los servicios deben suministrarse en condiciones de inocuidad, calidad y seguridad."
            ];
            $leyendaRandom = $leyendas[array_rand($leyendas)];

            $detalles = $venta->ventaDetalles;

            $detalleFactura = '';
//            <descripcion>" . utf8_encode(str_replace("&", "&amp;", $detalle->nombre)) . "</descripcion> trim
            foreach ($detalles as $detalle) {
                $detalleFactura .= "<detalle>
                <actividadEconomica>463000</actividadEconomica>
                <codigoProductoSin>62121</codigoProductoSin>
                <codigoProducto>" . $detalle->producto_id . "</codigoProducto>
                <descripcion>" . trim(utf8_encode(str_replace("&", "&amp;", $detalle->nombre))) . "</descripcion>
                <cantidad>" . $detalle->cantidad . "</cantidad>
                <unidadMedida>62</unidadMedida>
                <precioUnitario>" . $detalle->precio . "</precioUnitario>
                <montoDescuento>0</montoDescuento>
                <subTotal>" . $detalle->precio * $detalle->cantidad . "</subTotal>
                <numeroSerie xsi:nil='true'/>
                <numeroImei xsi:nil='true'/>
            </detalle>";
            }
            $token = env('TOKEN');
            $nit = env('NIT');
            $ambiente = env('AMBIENTE');
            $codigoSucursal = 0;
            $codigoModalidad = env('MODALIDAD');
            $codigoEmision = 1;
            $tipoFacturaDocumento = 1; // 1 con credito fiscal 2 sin credito fiscal 3 nota credito debito
            $codigoDocumentoSector = 1; //1 compra venta, 13 servicios basicos, 24 nota credito debito, 29 nota conciliacion
            $numeroFactura = $venta->id;
            $codigoPuntoVenta = 0;
            $codigoSistema = env('CODIGO_SISTEMA');

            $fechaEnvio = date("Y-m-d\TH:i:s.000");
            $cuf = new CUF();
            $cuf = $cuf->obtenerCUF(
                $nit,
                date("YmdHis000"),
                $codigoSucursal,
                $codigoModalidad,
                $codigoEmision,
                $tipoFacturaDocumento,
                $codigoDocumentoSector,
                $numeroFactura,
                $codigoPuntoVenta
            );
            $cuf = $cuf . $cufd->codigoControl;
//            <nombreRazonSocial>".utf8_encode(str_replace("&","&amp;",$cliente->nombre))."</nombreRazonSocial>
            $text = "<?xml version='1.0' encoding='UTF-8' standalone='yes'?>
        <facturaComputarizadaCompraVenta xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:noNamespaceSchemaLocation='facturaComputarizadaCompraVenta.xsd'>
        <cabecera>
        <nitEmisor>" . env('NIT') . "</nitEmisor>
        <razonSocialEmisor>" . env('RAZON') . "</razonSocialEmisor>
        <municipio>Oruro</municipio>
        <telefono>" . env('TELEFONO') . "</telefono>
        <numeroFactura>$numeroFactura</numeroFactura>
        <cuf>$cuf</cuf>
        <cufd>" . $cufd->codigo . "</cufd>
        <codigoSucursal>$codigoSucursal</codigoSucursal>
        <direccion>" . env('DIRECCION') . "</direccion>
        <codigoPuntoVenta>$codigoPuntoVenta</codigoPuntoVenta>
        <fechaEmision>$fechaEnvio</fechaEmision>
        <nombreRazonSocial>".$this->xmlSafe($cliente->nombre)."</nombreRazonSocial>
        <codigoTipoDocumentoIdentidad>" . $cliente->codigoTipoDocumentoIdentidad . "</codigoTipoDocumentoIdentidad>
        <numeroDocumento>" . $cliente->ci . "</numeroDocumento>
        <complemento>" . $cliente->complemento . "</complemento>
        <codigoCliente>" . $cliente->id . "</codigoCliente>
        <codigoMetodoPago>1</codigoMetodoPago>
        <numeroTarjeta xsi:nil='true'/>
        <montoTotal>" . $venta->total . "</montoTotal>
        <montoTotalSujetoIva>" . $venta->total . "</montoTotalSujetoIva>
        <codigoMoneda>1</codigoMoneda>
        <tipoCambio>1</tipoCambio>
        <montoTotalMoneda>" . $venta->total . "</montoTotalMoneda>
        <montoGiftCard xsi:nil='true'/>
        <descuentoAdicional>0</descuentoAdicional>
        <codigoExcepcion>" . ($cliente->codigoTipoDocumentoIdentidad == 5 ? 1 : 0) . "</codigoExcepcion>
        <cafc xsi:nil='true'/>
        <leyenda>$leyendaRandom</leyenda>
        <usuario>" . explode(" ", $user->name)[0] . "</usuario>
        <codigoDocumentoSector>" . $codigoDocumentoSector . "</codigoDocumentoSector>
        </cabecera>";
            $text .= $detalleFactura;
            $text .= "</facturaComputarizadaCompraVenta>";
//            error_log('Generando XML: ' . $text);

            $xml = new SimpleXMLElement($text);
            $dom = new DOMDocument('1.0');
            error_log('Formateando XML...');

            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xml->asXML());
            $nameFile = $venta->id;
            $dom->save("archivos/" . $nameFile . '.xml');

//            $firmar = new Firmar();
//            $firmar->firmar("archivos/" . $nameFile . '.xml');


            $xml = new DOMDocument();
            $xml->load("archivos/" . $nameFile . '.xml');
            error_log('Validando XML contra XSD...');
            if (!$xml->schemaValidate('facturaComputarizadaCompraVenta.xsd')) {
                error_log('Error: El XML no es válido contra el XSD.');
                echo "invalid";
            }
            error_log('XML válido contra XSD.');

            $file = "archivos/" . $nameFile . '.xml';
            $gzfile = "archivos/" . $nameFile . '.xml' . '.gz';
            $fp = gzopen($gzfile, 'w9');
            gzwrite($fp, file_get_contents($file));
            gzclose($fp);
            error_log('Archivo comprimido: ' . $gzfile);

//            $archivo = $firmar->getFileGzip("archivos/" . $nameFile . '.xml' . '.gz');
            $archivo=$this->getFileGzip("archivos/".$nameFile.'.xml'.'.gz');
            $hashArchivo = hash('sha256', $archivo);
            try {
                $client = new \SoapClient("https://pilotosiatservicios.impuestos.gob.bo/v2/ServicioFacturacionCompraVenta?WSDL", [
                    'stream_context' => stream_context_create([
                        'http' => [
                            'header'  => "apikey: TokenApi " . $token,
                            'timeout' => 5, // ⏱ máximo 5 segundos
                        ]
                    ]),
                    'cache_wsdl'   => WSDL_CACHE_NONE,
                    'compression'  => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
                    'trace'        => 1,
                    'use'          => SOAP_LITERAL,
                    'style'        => SOAP_DOCUMENT,
                    'connection_timeout' => 5, // también para handshake inicial
                ]);


                $result = $client->recepcionFactura([
                    "SolicitudServicioRecepcionFactura" => [
                        "codigoAmbiente" => $ambiente,
                        "codigoDocumentoSector" => $codigoDocumentoSector,
                        "codigoEmision" => $codigoEmision,
                        "codigoModalidad" => $codigoModalidad,
                        "codigoPuntoVenta" => $codigoPuntoVenta,
                        "codigoSistema" => $codigoSistema,
                        "codigoSucursal" => $codigoSucursal,
                        "cufd" => $cufd,
                        "cuis" => $cui->codigo,
                        "nit" => $nit,
                        "tipoFacturaDocumento" => $tipoFacturaDocumento,
                        "archivo" => $archivo,
                        "fechaEnvio" => $fechaEnvio,
                        "hashArchivo" => $hashArchivo,
                    ]
                ]);
                error_log('result: ' . json_encode($result));

//            result: {"RespuestaServicioFacturacion":{"codigoDescripcion":"RECHAZADA","codigoEstado":902,"mensajesList":{"codigo":1011,"descripcion":"EL COMPLEMENTO SOLO PUEDE SER ENVIADO CUANDO EL TIPO DE DOCUMENTO ES CARNET DE IDENTIDAD O CEDULA DE IDENTIDAD DE EXTRANJERO Complemento A1 tipoDocumento 5"},"transaccion":false}}
                if( isset($result->RespuestaServicioFacturacion) &&
                    isset($result->RespuestaServicioFacturacion->transaccion) &&
                    !$result->RespuestaServicioFacturacion->transaccion ) {
                    $venta->delete();
                    return response()->json(['message' => 'Error al enviar a impuestos: ' .
                        (isset($result->RespuestaServicioFacturacion->mensajesList->descripcion) ?
                            $result->RespuestaServicioFacturacion->mensajesList->descripcion : 'Error desconocido')
                    ], 400);
                }

                if( isset($result->RespuestaServicioFacturacion) &&
                    isset($result->RespuestaServicioFacturacion->transaccion) &&
                    $result->RespuestaServicioFacturacion->transaccion ) {
                    $venta->cuf = $cuf;
                    $venta->cufd = $cufd->codigo;
                    $venta->online = true;
                    $venta->leyenda = $leyendaRandom;
                    $venta->save();

                    // enviar correo
                    if ($cliente->email == null || $cliente->email == '') {
                        return response()->json(
                            $venta->load('cliente','ventaDetalles.producto')
                        );
                    }
                    $details=[
                        "title"=>"Factura",
                        "body"=>"Gracias por su compra",
                        "online"=>true,
                        "anulado"=>false,
                        "cuf"=>"",
                        "numeroFactura"=>"",
                        "sale_id"=>$venta->id,
                        "carpeta"=>"archivos",
                    ];
                    Mail::to($cliente->email)->send(new TestMail($details));
                }
            }catch (\Exception $e) {
                error_log('Error: ' . $e->getMessage());
                $venta->cuf = $cuf;
                $venta->cufd = $cufd->codigo;
                $venta->online = false;
                $venta->leyenda = $leyendaRandom;
                $venta->save();
                $details = [
                    "title" => "Factura",
                    "body" => "Gracias por su compra",
                    "online" => false,
                    "anulado" => false,
                    "cuf" => "",
                    "numeroFactura" => "",
                    "sale_id" => $venta->id,
                    "carpeta" => "archivos",
                ];
                Mail::to($cliente->email)->send(new TestMail($details));
            }
            return response()->json(
                $venta->load('cliente','ventaDetalles.producto')
            );
        });
    }
    function getFileGzip($fileName)
    {
        $fileName = $fileName;

        $handle = fopen($fileName, "rb");
        $contents = fread($handle, filesize($fileName));
        fclose($handle);

        return $contents;
    }
    function clienteUpdateOrCreate($request){
        $nit = trim((string) ($request->nit ?? $request->ci ?? ''));
        $ci = trim((string) ($request->ci ?? $request->nit ?? ''));

        $payload = [
            'nit' => $nit !== '' ? $nit : null,
            'ci' => $ci !== '' ? $ci : null,
            'nombre' => $request->nombre,
            'email' => $request->email,
            'codigoTipoDocumentoIdentidad' => $request->codigoTipoDocumentoIdentidad,
            'complemento' => $request->complemento,
        ];

        $findCliente = Cliente::query()
            ->where('nit', $nit)
            ->orWhere('ci', $ci)
            ->first();
        if ($findCliente) {
            $findCliente->update($payload);
            return $findCliente;
        } else {
            return Cliente::create($payload);
        }
    }
    private function xmlSafe(?string $s): string
    {
        $s = $s ?? '';
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }
        return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
    function index(Request $request){
        $fechaInicio = $request->fechaInicio;
        $fechaFin = $request->fechaFin;
        $user_id = $request->user;
        $user = $request->user();

        if ($user->role == 'Admin') {
            $ventas = Venta::with('user', 'cliente', 'ventaDetalles.producto')
                ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->orderBy('created_at', 'desc')
                ->get();
        }else{
            $ventas = Venta::with('user', 'cliente', 'ventaDetalles.producto')
                ->where('user_id', $user->id)
                ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->where('agencia', $user->agencia)
                ->orderBy('created_at', 'desc')
                ->get();
        }
        if ($user_id != '') {
            $ventas = $ventas->where('user_id', $user_id);
        }
        return $ventas;
    }
    function show($id){
        return Venta::with('user', 'cliente')->findOrFail($id);
    }
    function update(Request $request, $id){
        $venta = Venta::findOrFail($id);
        $venta->update($request->all());
        return $venta;
    }
    function destroy($id){
        $venta = Venta::findOrFail($id);
        $venta->delete();
        return $venta;
    }
}
