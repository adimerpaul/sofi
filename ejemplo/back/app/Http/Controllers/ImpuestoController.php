<?php

namespace App\Http\Controllers;

use App\Models\Cufd;
use App\Models\Cui;
use App\Models\EventoSignificativo;
use App\Models\ImpuestoFalla;
use App\Models\Venta;
use App\Services\Impuestos\CufdService;
use Illuminate\Http\Request;
use Phar;
use PharData;
//use SoapClient;

class ImpuestoController extends Controller{
    function facturacionOperaciones(){
//        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:siat="https://siat.impuestos.gob.bo/">
//   <soapenv:Header/>
//   <soapenv:Body>
//      <siat:consultaEventoSignificativo>
//         <SolicitudConsultaEvento>
//            <codigoAmbiente>?</codigoAmbiente>
//            <!--Optional:-->
//            <codigoPuntoVenta>?</codigoPuntoVenta>
//            <codigoSistema>?</codigoSistema>
//            <codigoSucursal>?</codigoSucursal>
//            <cufd>?</cufd>
//            <cuis>?</cuis>
//            <fechaEvento>?</fechaEvento>
//            <nit>?</nit>
//         </SolicitudConsultaEvento>
//      </siat:consultaEventoSignificativo>
//   </soapenv:Body>
//</soapenv:Envelope>
        $client = new \SoapClient(env('URL_SIAT')."FacturacionOperaciones?WSDL",  [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => "apikey: TokenApi ".env('TOKEN'),
                ]
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => 1,
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
        ]);
        $result= $client->consultaEventoSignificativo([
            "SolicitudConsultaEvento"=>[
                "codigoAmbiente"=>env('AMBIENTE'),
                "codigoPuntoVenta"=>0,
                "codigoSistema"=>env('CODIGO_SISTEMA'),
                "codigoSucursal"=>0,
                "cufd"=>Cufd::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now())->orderBy('id','desc')->first()->codigo,
                "cuis"=>Cui::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now())->orderBy('id','desc')->first()->codigo,
                "fechaEvento"=>date('Y-m-d'),
                "nit"=>env('NIT'),
            ]
        ]);
        return response()->json($result, 200);
    }
    function eventoSignificativo(Request $request){
        $cui=Cui::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now());
        if ($cui->count()==0){
            return response()->json(['message' => 'El CUI no existe'], 400);
        }
        $cufd=Cufd::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now());
        if ($cufd->count()==0){
            return response()->json(['message' => 'El CUFD no existe'], 400);
        }
        $codigoPuntoVenta =0;
        $codigoSucursal =0;

        $cui=Cui::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->first();
        $cufd=Cufd::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->first();
        $venta = Venta::find($request->venta_id);
        $fecha = $venta->fecha;
        $hora = $venta->hora;

//        verificar si existe en evento_significativo
        $eventoSignificativo = EventoSignificativo::where('codigoPuntoVenta', $codigoPuntoVenta)
            ->where('codigoSucursal', $codigoSucursal)
            ->where('fechaHoraInicioEvento', '<=', date('Y-m-d H:i:s', strtotime($fecha.' '.$hora)))
            ->where('fechaHoraFinEvento', '>=', date('Y-m-d H:i:s', strtotime($fecha.' '.$hora)))
//            ->where('cufdEvento', $venta->cuf)
            ->first();
        if (!$eventoSignificativo){
            $fechaInicio = date('Y-m-d\TH:i:s', strtotime($fecha.' '.$hora.' -1 second'));
            $fechaFin = date('Y-m-d\TH:i:s', strtotime($fecha.' '.$hora.' +1 second'));
            $fechaInicio = date('Y-m-d\TH:i:s.000', strtotime($fechaInicio));
            $fechaFin = date('Y-m-d\TH:i:s.000', strtotime($fechaFin));

            error_log("fechaInicio: ".$fechaInicio);
            error_log("fechaFin: ".$fechaFin);

            $client = new \SoapClient(env('URL_SIAT')."FacturacionOperaciones?WSDL",  [
                'stream_context' => stream_context_create([
                    'http' => [
                        'header' => "apikey: TokenApi ".env('TOKEN'),
                    ]
                ]),
                'cache_wsdl' => WSDL_CACHE_NONE,
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
                'trace' => 1,
                'use' => SOAP_LITERAL,
                'style' => SOAP_DOCUMENT,
            ]);
            try {
                $result= $client->registroEventoSignificativo([
                    "SolicitudEventoSignificativo"=>[
                        "codigoAmbiente"=>env('AMBIENTE'),
                        "codigoMotivoEvento"=>$request->codigoMotivoEvento,
                        "codigoPuntoVenta"=>0,
                        "codigoSistema"=>env('CODIGO_SISTEMA'),
                        "codigoSucursal"=>0,
                        "cufd"=>$cufd->codigo,
                        "cufdEvento"=>$venta->cufd,
                        "cuis"=>$cui->codigo,
                        "descripcion"=>$request->descripcion,
                        "fechaHoraFinEvento"=>$fechaFin,
                        "fechaHoraInicioEvento"=>$fechaInicio,
                        "nit"=>env('NIT'),
                    ]
                ]);
                error_log("result: ".json_encode($result));

                if ($result->RespuestaListaEventos->transaccion){
                    $eventoSignificativo = new EventoSignificativo();
                    $eventoSignificativo->codigoPuntoVenta=$codigoPuntoVenta;
                    $eventoSignificativo->codigoSucursal=$codigoSucursal;
                    $eventoSignificativo->fechaHoraFinEvento=date('Y-m-d H:i:s', strtotime($fecha .' '.$hora.' +1 second'));
                    $eventoSignificativo->fechaHoraInicioEvento=date('Y-m-d H:i:s', strtotime($fecha .' '.$hora.' -1 second'));
                    $eventoSignificativo->codigoMotivoEvento=$request->codigoMotivoEvento;
                    $eventoSignificativo->descripcion=$request->descripcion;
                    $eventoSignificativo->cufd=$cufd->codigo;
                    $eventoSignificativo->cufdEvento=$venta->cuf;
                    $eventoSignificativo->cufd_id=$cufd->id;
                    $eventoSignificativo->codigoRecepcionEventoSignificativo=$result->RespuestaListaEventos->codigoRecepcionEventoSignificativo;
                    $eventoSignificativo->save();
                    return response()->json(['message' => 'Evento Significativo registrado correctamente!!'], 200);
                }else{
                    return response()->json(['message' => json_encode($result->RespuestaListaEventos->mensajesList) ], 500);
                }
            }catch (\Exception $e){
                return response()->json(['message' => $e->getMessage()], 500);
            }
        }
        $a = new PharData('archivos/archive.tar');
        $ruta = "archivos/".$venta->id.".xml";
        $a->addFile($ruta);
        $a->compress(Phar::GZ);
        unlink('archivos/archive.tar');
        $firmar = new Firmar();
        $archivo=$firmar->getFileGzip("archivos/archive.tar.gz");
        $hashArchivo=hash('sha256', $archivo);
        unlink('archivos/archive.tar.gz');

        $client = new \SoapClient(env('URL_SIAT')."ServicioFacturacionCompraVenta?WSDL",  [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => "apikey: TokenApi ".env('TOKEN'),
                ]
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => 1,
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
        ]);
        $codigoAmbiente=env('AMBIENTE');
        $codigoDocumentoSector=1; // 1 compraventa 2 alquiler 23 prevaloradas
        $codigoEmision=2; // 1 online 2 offline 3 masivo
        $codigoModalidad=env('MODALIDAD'); //1 electronica 2 computarizada
        $codigoPuntoVenta=0;//
        $codigoSistema=env('CODIGO_SISTEMA');
        $tipoFacturaDocumento=1; // 1 con credito fiscal 2 sin creditofical 3 nota debito credito
        $result= $client->recepcionPaqueteFactura([
            "SolicitudServicioRecepcionPaquete"=>[
                "codigoAmbiente"=>$codigoAmbiente,
                "codigoDocumentoSector"=>$codigoDocumentoSector,
                "codigoEmision"=>$codigoEmision,
                "codigoModalidad"=>$codigoModalidad,
                "codigoPuntoVenta"=>$codigoPuntoVenta,
                "codigoSistema"=>$codigoSistema,
                "codigoSucursal"=>$codigoSucursal,
                "cufd"=>$cufd->codigo,
                "cuis"=>$cui->codigo,
                "nit"=>env('NIT'),
                "tipoFacturaDocumento"=>$tipoFacturaDocumento,
                "archivo"=>$archivo,
                "fechaEnvio"=>date('Y-m-d\TH:i:s.000'),
                "hashArchivo"=>$hashArchivo,
                "cafc"=>"XXX",
                "cantidadFacturas"=>1,
                "codigoEvento"=>$eventoSignificativo->codigoRecepcionEventoSignificativo,
            ]
        ]);
        error_log("result: ".json_encode($result));
        $eventoSignificativo->codigoRecepcion=$result->RespuestaServicioFacturacion->codigoRecepcion;
        $eventoSignificativo->save();

    }
    public function validarPaquete(Request $request){
        try {
            $codigoPuntoVenta=0;
            $codigoSucursal=0;
            if (Cui::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->count()==0){
                return response()->json(['message' => 'No existe CUI para la venta!!'], 400);
            }
            if (Cufd::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->count()==0){
                return response()->json(['message' => 'No exite CUFD para la venta!!'], 400);
            }
            $cui=Cui::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->first();
            $cufd=Cufd::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->first();

            $client = new \SoapClient(env('URL_SIAT')."ServicioFacturacionCompraVenta?WSDL",  [
                'stream_context' => stream_context_create([
                    'http' => [
                        'header' => "apikey: TokenApi ".env('TOKEN'),
                    ]
                ]),
                'cache_wsdl' => WSDL_CACHE_NONE,
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
                'trace' => 1,
                'use' => SOAP_LITERAL,
                'style' => SOAP_DOCUMENT,
            ]);

            $venta = Venta::find($request->venta_id);

            $eventoSignificativo = EventoSignificativo::
                where('fechaHoraInicioEvento', '<=', date('Y-m-d H:i:s', strtotime($venta->fecha.' '.$venta->hora)))
                ->where('fechaHoraFinEvento', '>=', date('Y-m-d H:i:s', strtotime($venta->fecha.' '.$venta->hora)))
                ->first();


            $result= $client->validacionRecepcionPaqueteFactura([
                "SolicitudServicioValidacionRecepcionPaquete"=>[
                    "codigoAmbiente"=>env('AMBIENTE'),
                    "codigoDocumentoSector"=>"1",
                    "codigoEmision"=>2,
                    "codigoModalidad"=>1,
                    "codigoPuntoVenta"=>0,
                    "codigoSistema"=>env('CODIGO_SISTEMA'),
                    "codigoSucursal"=>0,
                    "cufd"=>$cufd->codigo,
                    "cuis"=>$cui->codigo,
                    "nit"=>env('NIT'),
                    "tipoFacturaDocumento"=>1,
//                    "codigoRecepcion"=>$request->codigoRecepcion,
                    "codigoRecepcion"=>$eventoSignificativo->codigoRecepcion
                ]
            ]);
//            "RespuestaServicioFacturacion": {
//                "codigoDescripcion": "VALIDADA",
            if ($result->RespuestaServicioFacturacion->codigoDescripcion=="VALIDADA"){
                $venta->online=true;
                $venta->save();
            }
            return  $result;
        }catch (\Exception $e) {
            return response()->json(["success"=>false,'message' => $e->getMessage()], 500);
        }
    }
    function anularImpuestos($cuf){
        $cui=Cui::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now());
        if ($cui->count()==0){
            return response()->json(['message' => 'El CUI no existe'], 400);
        }
        $cufd=Cufd::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now());
        if ($cufd->count()==0){
            return response()->json(['message' => 'El CUFD no existe'], 400);
        }
        $client = new \SoapClient(env("URL_SIAT")."ServicioFacturacionCompraVenta?WSDL",  [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => "apikey: TokenApi ".env('TOKEN'),
                ]
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => 1,
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
        ]);
        $result= $client->anulacionFactura([
            "SolicitudServicioAnulacionFactura"=>[
                "codigoAmbiente"=>env('AMBIENTE'),
                "codigoDocumentoSector"=>1,
                "codigoEmision"=>1,
                "codigoModalidad"=>env('MODALIDAD'),
                "codigoPuntoVenta"=>0,
                "codigoSistema"=>env('CODIGO_SISTEMA'),
                "codigoSucursal"=>0,
                "cufd"=>Cufd::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now())->orderBy('id','desc')->first()->codigo,
                "cuis"=>Cui::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now())->orderBy('id','desc')->first()->codigo,
                "nit"=>env('NIT'),
                "tipoFacturaDocumento"=>1,
                "codigoMotivo"=>1,
                "cuf"=>$cuf,
            ]
        ]);
        return response()->json($result, 200);
    }
    function verificarImpuestos($cuf){
        $cui=Cui::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now());
        if ($cui->count()==0){
            return response()->json(['message' => 'El CUI no existe'], 400);
        }
        $cufd=Cufd::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now());
        if ($cufd->count()==0){
            return response()->json(['message' => 'El CUFD no existe'], 400);
        }
        $client = new \SoapClient(env("URL_SIAT")."ServicioFacturacionCompraVenta?WSDL",  [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => "apikey: TokenApi ".env('TOKEN'),
                ]
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => 1,
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
        ]);
        $result= $client->verificacionEstadoFactura([
            "SolicitudServicioVerificacionEstadoFactura"=>[
                "codigoAmbiente"=>env('AMBIENTE'),
                "codigoDocumentoSector"=>1,
                "codigoEmision"=>1,
                "codigoModalidad"=>env('MODALIDAD'),
                "codigoPuntoVenta"=>0,
                "codigoSistema"=>env('CODIGO_SISTEMA'),
                "codigoSucursal"=>0,
                "cufd"=>Cufd::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now())->orderBy('id','desc')->first()->codigo,
                "cuis"=>Cui::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now())->orderBy('id','desc')->first()->codigo,
                "nit"=>env('NIT'),
                "tipoFacturaDocumento"=>1,
                "cuf"=>$cuf,
            ]
        ]);
        return response()->json($result, 200);
    }
    public function generarCUI(){
        $codigoPuntoVenta = 0;
        $codigoSucursal = 0;
        $token=env('TOKEN');
//        error_log('generarCUI: '.$token);
        if (Cui::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->count()>=1){
            return response()->json(['message' => 'El CUI ya existe'], 400);
        }else{
            $client = new \SoapClient(env("URL_SIAT")."FacturacionCodigos?WSDL",  [
                'stream_context' => stream_context_create([
                    'http' => [
                        'header' => "apikey: TokenApi ".$token,
                    ]
                ]),
                'cache_wsdl' => WSDL_CACHE_NONE,
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
                'trace' => 1,
                'use' => SOAP_LITERAL,
                'style' => SOAP_DOCUMENT,
            ]);
            $result= $client->cuis([
                "SolicitudCuis"=>[
                    "codigoAmbiente"=>env('AMBIENTE'),
                    "codigoModalidad"=>env('MODALIDAD'),
                    "codigoPuntoVenta"=>$codigoPuntoVenta,
                    "codigoSistema"=>env('CODIGO_SISTEMA'),
                    "codigoSucursal"=>$codigoSucursal,
                    "nit"=>env('NIT'),
                ]
            ]);
            error_log("result: ".json_encode($result));
            $cui = new Cui();
            $cui->codigo = $result->RespuestaCuis->codigo;
            $cui->fechaVigencia =  date('Y-m-d H:i:s', strtotime($result->RespuestaCuis->fechaVigencia));
            $cui->codigoPuntoVenta = $codigoPuntoVenta;
            $cui->codigoSucursal = $codigoSucursal;
            $cui->fechaCreacion= date('Y-m-d H:i:s');
            $cui->save();
            return response()->json(['success' => 'CUI creado correctamente'], 200);
        }
    }
    function listCUFD(){
        $cufd = Cufd::orderBy('id', 'desc')->get();
        return response()->json($cufd, 200);
    }
    function generarCUFD(){
        try {
            error_log("generarCUFD");
            $result = app(CufdService::class)->generateCufdDaily();
            error_log("result: ".json_encode($result));
            if (($result['status'] ?? null) === 'skipped') {
                ImpuestoFalla::query()->create([
                    'tipo' => 'CUFD',
                    'mensaje' => $result['message'] ?? 'El CUFD vigente ya existe para hoy',
                    'detalle' => ['reason' => 'already_exists_today'],
                    'estado' => 'pendiente',
                    'fecha_evento' => now(),
                ]);
                return response()->json(['message' => $result['message']], 200);
            }
            return response()->json(['success' => $result['message']], 200);
        } catch (\Throwable $e) {
            ImpuestoFalla::query()->create([
                'tipo' => 'CUFD',
                'mensaje' => 'Fallo la generacion manual de CUFD',
                'detalle' => ['error' => $e->getMessage()],
                'estado' => 'pendiente',
                'fecha_evento' => now(),
            ]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function fallas()
    {
        $items = ImpuestoFalla::query()
            ->orderByDesc('fecha_evento')
            ->limit(100)
            ->get();

        return response()->json([
            'pending' => $items->where('estado', 'pendiente')->count(),
            'data' => $items->values(),
        ], 200);
    }

    public function ocultarFalla(ImpuestoFalla $falla)
    {
        $falla->update([
            'estado' => 'oculto',
            'resolved_at' => now(),
        ]);

        return response()->json(['message' => 'Falla ocultada'], 200);
    }

    public function resolverFalla(ImpuestoFalla $falla)
    {
        $falla->update([
            'estado' => 'resuelto',
            'resolved_at' => now(),
        ]);

        return response()->json(['message' => 'Falla resuelta'], 200);
    }

    public function eliminarFalla(ImpuestoFalla $falla)
    {
        $falla->delete();
        return response()->json(['message' => 'Falla eliminada'], 200);
    }

    public function reintentarCufd()
    {
        try {
            $result = app(CufdService::class)->generateCufdDaily();

            if (($result['status'] ?? '') === 'created') {
                ImpuestoFalla::query()
                    ->where('tipo', 'CUFD')
                    ->where('estado', 'pendiente')
                    ->update([
                        'estado' => 'resuelto',
                        'resolved_at' => now(),
                    ]);
            }
            if (($result['status'] ?? '') === 'skipped') {
                ImpuestoFalla::query()->create([
                    'tipo' => 'CUFD',
                    'mensaje' => $result['message'] ?? 'El CUFD vigente ya existe para hoy',
                    'detalle' => ['reason' => 'already_exists_today'],
                    'estado' => 'pendiente',
                    'fecha_evento' => now(),
                ]);
            }

            return response()->json($result, 200);
        } catch (\Throwable $e) {
            ImpuestoFalla::query()->create([
                'tipo' => 'CUFD',
                'mensaje' => 'Fallo al reintentar generar CUFD',
                'detalle' => ['error' => $e->getMessage()],
                'estado' => 'pendiente',
                'fecha_evento' => now(),
            ]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function estadoAutoCufd()
    {
        $actual = Cufd::query()
            ->where('fechaVigencia', '>=', now())
            ->latest('id')
            ->first();

        return response()->json([
            'hora_programada' => '00:15',
            'zona_horaria' => config('app.timezone'),
            'cufd_actual' => $actual,
            'fallas_pendientes' => ImpuestoFalla::query()->where('tipo', 'CUFD')->where('estado', 'pendiente')->count(),
        ], 200);
    }
}
