<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
class PedidoController extends Controller{
    function enviarPedidosEmergencia(Request $request){
        $user = User::where('CodAut', $request->user)->first();
        $fecha = $request->fecha;
        $pedidosCreados= Pedido::where('CIfunc', $user->CodAut)
            ->where('estado', 'CREADO')
            ->whereDate('fecha', $fecha)
            ->get();
        $pedidosCondeuda=[];
        foreach ($pedidosCreados as $p){
            $pedido = Pedido::where('NroPed', $p->NroPed)->first();
            $cliente = Cliente::where('Cod_Aut', $pedido->idCli)->first();
            if ($cliente->venta == 'INACTIVO') {
                $exists = false;
                foreach ($pedidosCondeuda as $item) {
                    if ($item['Cod_Aut'] == $cliente->Cod_Aut) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $pedidosCondeuda[] = [
                        'Cod_Aut' => $cliente->Cod_Aut,
                        'Nombres' => $cliente->Nombres,
                        'Telf' => $cliente->Telf,
                        'Direccion' => $cliente->Direccion,
                        'zona' => $cliente->zona,
                        'fecha' => $p->fecha,
                        'NroPed' => $p->NroPed,
                    ];
                }
            } else {
                $p->estado = 'ENVIADO';
                $p->envio = now();
                $p->save();
            }
        }
        return response()->json([
            'pedidosCondeuda' => $pedidosCondeuda,
        ]);
    }
    function reportePedidoOnly($id){
        $pedidos = Pedido::where('NroPed', $id)
            ->select('NroPed', 'fecha', 'idCli', 'CIfunc', 'estado','fact','comentario','pago','placa','horario','comentario')
            ->where('estado', 'ENVIADO')
            ->with(['cliente' => function ($query) {
                $query->select('Cod_Aut', 'Nombres', 'Direccion', 'Telf','zona');
            }])
            ->with(['user' => function ($query) {
                $query->select('CodAut', 'Nombre1', 'App1');
            }])
            ->groupBy('NroPed', 'fecha', 'idCli', 'CIfunc', 'estado','fact','comentario','pago','placa','horario','comentario')
            ->orderBy('NroPed')
            ->where('tipo','NORMAL')
            ->get();
        $pedidosAll = Pedido::where('NroPed', $id)
            ->select('NroPed','cod_prod','precio','Cant','Canttxt','subtotal')
            ->with(['producto' => function ($query) {
                $query->select(DB::raw("TRIM(cod_prod) as cod_prod"), 'Producto');
            }])
            ->where('tipo','NORMAL')
            ->get();
        $resPedido=[];

        $pedidosIds = $pedidos->pluck('NroPed');
        Pedido::whereIn('NroPed',$pedidosIds)
            ->where('impreso',0)
            ->update(['impreso'=>1]);

        foreach ($pedidos as $p){
            $productos=$pedidosAll->where('NroPed',$p->NroPed);
            $resProducto=[];
            foreach ($productos as $pro){
                $resProducto[]=[
                    'Nroped'=>$pro->NroPed,
                    'cod_prod'=>$pro->cod_prod,
                    'producto'=>isset($pro->producto->Producto)?$pro->producto->Producto:'',
                    'precio'=>$pro->precio,
                    'Cant'=>$pro->Cant,
                    'Canttxt'=>$pro->Canttxt,
                    'subtotal'=>$pro->subtotal
                ];
            }
            $resPedido[]=[
                'pedido'=>$p,
                'productos'=>$productos
            ];
        }
        $vehiculos = DB::table('vehiculo')->get();
        $data = [
            'pedidos' => $resPedido,
            'vehiculos' => $vehiculos
        ];
//        return $data;
        $pdf = PDF::loadView('pdf.reportePedido', $data);
        return $pdf->stream('document.pdf');
    }
    function reportePedido(Request $request,$fecha){
        $pedidos = Pedido::whereDate('fecha', $fecha)
            ->select('NroPed', 'fecha', 'idCli', 'CIfunc', 'estado','fact','comentario','pago','placa','horario','comentario')
            ->where('estado', 'ENVIADO')
            ->with(['cliente' => function ($query) {
                $query->select('Cod_Aut', 'Nombres', 'Direccion', 'Telf','zona');
            }])
            ->with(['user' => function ($query) {
                $query->select('CodAut', 'Nombre1', 'App1');
            }])
            ->groupBy('NroPed', 'fecha', 'idCli', 'CIfunc', 'estado','fact','comentario','pago','placa','horario','comentario')
            ->orderBy('NroPed')
            ->where('tipo','NORMAL')
            ->get();
        $pedidosAll = Pedido::whereDate('fecha', $fecha)
            ->select('NroPed','cod_prod','precio','Cant','Canttxt','subtotal')
            ->with(['producto' => function ($query) {
                $query->select('cod_prod', 'Producto');
            }])
            ->where('tipo','NORMAL')
            ->get();
        $resPedido=[];

        $pedidosIds = $pedidos->pluck('NroPed');
        Pedido::whereIn('NroPed',$pedidosIds)
            ->where('impreso',0)
            ->update(['impreso'=>1]);

        foreach ($pedidos as $p){
            $productos=$pedidosAll->where('NroPed',$p->NroPed);
            $resProducto=[];
            foreach ($productos as $pro){
                $resProducto[]=[
                    'Nroped'=>$pro->NroPed,
                    'cod_prod'=>$pro->cod_prod,
                    'producto'=>isset($pro->producto->Producto)?$pro->producto->Producto:'',
                    'precio'=>$pro->precio,
                    'Cant'=>$pro->Cant,
                    'Canttxt'=>$pro->Canttxt,
                    'subtotal'=>$pro->subtotal
                ];
            }
            $resPedido[]=[
                'pedido'=>$p,
                'productos'=>$productos
            ];
        }
        $vehiculos = DB::table('vehiculo')->get();
        $data = [
            'pedidos' => $resPedido,
            'vehiculos' => $vehiculos
//            'fecha' => $fecha
        ];
//        return $data;
        $pdf = PDF::loadView('pdf.reportePedido', $data);
        return $pdf->stream('document.pdf');
    }
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function distance($lat1, $lon1, $lat2, $lon2) {
        $pi80 = M_PI / 180;
        $lat1 *= $pi80;
        $lon1 *= $pi80;
        $lat2 *= $pi80;
        $lon2 *= $pi80;
        $r = 6372797; // mean radius of Earth in km
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlon / 2) * sin($dlon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $km = $r * $c;
//echo ' '.$km;
        return $km;
    }

    public function rpollo(Request $request){
        return DB::SELECT("SELECT * from tbpedidos p, tbclientes c where c.Cod_Aut=p.idCli and date(fecha)>='$request->fecha1' and date(fecha)<='$request->fecha2'  and tipo='POLLO' AND
        trim(CIfunc)='".$request->user()->CodAut."' and estado='ENVIADO' ");
    }

    public function rres(Request $request){
        return DB::SELECT("SELECT * from tbpedidos p, tbclientes c where c.Cod_Aut=p.idCli and date(fecha)>='$request->fecha1' and date(fecha)<='$request->fecha2'  and tipo='RES' AND
        trim(CIfunc)='".$request->user()->CodAut."' and estado='ENVIADO' ");
    }

    public function rcerdo(Request $request){
        return DB::SELECT("SELECT * from tbpedidos p, tbclientes c where c.Cod_Aut=p.idCli and date(fecha)>='$request->fecha1' and date(fecha)<='$request->fecha2'  and tipo='CERDO' AND
        trim(CIfunc)='".$request->user()->CodAut."' and estado='ENVIADO' ");
    }

    public function rnormal(Request $request){
        return DB::SELECT(" SELECT tbpedidos.*,tbproductos.Producto from tbpedidos,tbproductos where tbpedidos.cod_prod=tbproductos.cod_prod  and tbpedidos.tipo='NORMAL' and NroPed=$request->comanda");
    }

    public function lispreventista(){
        return DB::SELECT("SELECT DISTINCT(l.CodAut),l.ci,l.Nombre1,l.App1 FROM tbpedidos p inner JOIN personal l on p.CIfunc=l.CodAut WHERE p.tipo='NORMAL'");
    }

    public function informeProducto(Request $request ){
        return DB::SELECT("SELECT o.cod_prod,o.Producto,count(*) as cantidad
        FROM tbpedidos p inner join tbproductos o on p.cod_prod=o.cod_prod
        WHERE p.tipo='NORMAL' and date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin'
        and p.CIfunc=$request->cod
        group by o.cod_prod,o.Producto;");
    }


    public function store(Request $request){
/*
        $primerTipo = null;
        $tiposDiferentes = false;

        foreach ($request->productos as $p) {
            $tipoActual = $p['tipo'];
            if ($primerTipo === null) {
                $primerTipo = $tipoActual;
            } elseif ($tipoActual !== $primerTipo) {
                $tiposDiferentes = true;
                break;
            }
        }

        if ($tiposDiferentes) {
            return response()->json(['message' => 'Todos los productos deben tener el mismo tipo'], 400);
        }*/

        foreach ($request->productos as $p){
            if ($p['tipo'] == 'POLLO') {
                $cbrasa5 = isset($p['cbrasa5']) ? $p['cbrasa5'] : 0;
                $ubrasa5 = isset($p['ubrasa5']) ? $p['ubrasa5'] : 0;
                $cbrasa6 = isset($p['cbrasa6']) ? $p['cbrasa6'] : 0;
                $cubrasa6 = isset($p['cubrasa6']) ? $p['cubrasa6'] : 0;
                $c104 = isset($p['c104']) ? $p['c104'] : 0;
                $u104 = isset($p['u104']) ? $p['u104'] : 0;
                $c105 = isset($p['c105']) ? $p['c105'] : 0;
                $u105 = isset($p['u105']) ? $p['u105'] : 0;
                $c106 = isset($p['c106']) ? $p['c106'] : 0;
                $u106 = isset($p['u106']) ? $p['u106'] : 0;
                $c107 = isset($p['c107']) ? $p['c107'] : 0;
                $u107 = isset($p['u107']) ? $p['u107'] : 0;
                $c108 = isset($p['c108']) ? $p['c108'] : 0;
                $u108 = isset($p['u108']) ? $p['u108'] : 0;
                $c109 = isset($p['c109']) ? $p['c109'] : 0;
                $u109 = isset($p['u109']) ? $p['u109'] : 0;

                $ala = isset($p['ala']) ? $p['ala'] : 0;
                $cadera = isset($p['cadera']) ? $p['cadera'] : 0;
                $pecho = isset($p['pecho']) ? $p['pecho'] : 0;
                $pie = isset($p['pie']) ? $p['pie'] : 0;
                $filete = isset($p['filete']) ? $p['filete'] : 0;
                $cuello = isset($p['cuello']) ? $p['cuello'] : 0;
                $hueso = isset($p['hueso']) ? $p['hueso'] : 0;
                $menu = isset($p['menu']) ? $p['menu'] : 0;
                $rango = isset($p['rango']) ? $p['rango'] : 0;

                $sumTotal = $cbrasa5 + $ubrasa5 + $cbrasa6 + $cubrasa6 + $c104 + $u104 + $c105 + $u105 + $c106 + $u106 + $c107 + $u107 + $c108 + $u108 + $c109 + $u109 + $ala + $cadera + $pecho + $pie + $filete + $cuello + $hueso + $menu+$rango;
                if ($sumTotal == 0) {
                    return response()->json(['message' => 'Debes ingresar al menos un producto frial pollo'], 500);
                    exit();
                }
                $sum1 =  $cbrasa5 + $ubrasa5 + $cbrasa6 + $cubrasa6 + $c104 + $u104 + $c105 + $u105 + $c106 + $u106 + $c107 + $u107 + $c108 + $u108 + $c109 + $u109;
                error_log('sum1: '.$sum1);
                $bs = isset($p['bs']) ? $p['bs'] : 0;
                error_log('bs: '.$bs);
                if ($sum1 >0 && $bs == 0) {
                    return response()->json(['message' => 'Debes ingresar el total de bs en pollo'], 500);
                    exit();
                }
                $sum2 = $ala + $cadera + $pecho + $pie + $filete + $cuello + $hueso + $menu;
                error_log('sum2: '.$sum2);
                $bs2 = isset($p['bs2']) ? $p['bs2'] : 0;
                error_log('bs2: '.$bs2);
                if ($sum2 >0 && $bs2 == 0) {
                    return response()->json(['message' => 'Debes ingresar el total de bs2 en pollo'], 500);
                    exit();
                }


                $sum3 =  $cbrasa5 + $ubrasa5 + $cbrasa6 + $cubrasa6 + $rango;
                $observacion = isset($p['observacion']) ? $p['observacion'] : '';
                if ($sum3 >0 && $observacion == '') {
                    return response()->json(['message' => 'Debes ingresar la observacion frial pollo'], 500);
                    exit();
                }


            }
            if($p['tipo']=='CERDO'){
                $pfrial = isset($p['pfrial']) ? $p['pfrial'] : 0;
                $total = isset($p['total']) ? $p['total'] : 0;
                $entero = isset($p['entero']) ? $p['entero'] : 0;
                $desmembre = isset($p['desmembre']) ? $p['desmembre'] : 0;
                $corte = isset($p['corte']) ? $p['corte'] : 0;
                $kilo = isset($p['kilo']) ? $p['kilo'] : 0;
                $sumTotal = $total + $entero + $desmembre + $corte + $kilo;
                if ($sumTotal == 0) {
                    return response()->json(['message' => 'Debes ingresar al menos un producto frial cerdo'], 500);
                    exit();
                }
                if ($pfrial == 0) {
                    return response()->json(['message' => 'Debes ingresar el total de bs en cerdo'], 500);
                    exit();
                }
            }
        }
//        return response()->json(['message' => 'DEVERIA INSERTAR'], 500);
//        exit();
//        return $request->productos;
        //$max=DB::select("SELECT max(NroPed) as max FROM tbpedidos");
        $cmdnum=DB::select("SELECT *  FROM comandas limit 1")[0];
        $numpedido=$cmdnum->comanda+1;
        DB::select("UPDATE `comandas` SET `comanda`='$numpedido' WHERE id=$cmdnum->id");

        $cliente=DB::select("SELECT * FROM tbclientes WHERE Cod_Aut='".$request->idCli."'");
//        echo ($cliente[0]->Latitud);
//        return floatval( $request->lat)."   -   ".floatval($request->lng)."   -   ".$cliente[0]->Latitud."   -   ".$cliente[0]->longitud;
        $distancia=$this->distance( floatval( $request->lat),floatval($request->lng),floatval($cliente[0]->Latitud),floatval($cliente[0]->longitud));

//        return $numpedido;
//        exit;
        DB::table('misvisitas')->insert([
            'estado'=>'PEDIDO',
            'fecha'=>date('Y-m-d'),
            'hora'=>date('H:i:s'),
            'lat'=>$request->lat,
            'lng'=>$request->lng,
            'distancia'=>$distancia,
            'observacion'=>'',
            'cliente_id'=>$request->idCli,
            'personal_id'=>$request->user()->CodAut
        ]);
        $data=[];
        foreach ($request->productos as $p){
        if($p['tipo']=='POLLO' || $p['tipo']=='RES' || $p['tipo']=='CERDO')
            $imp=1;
        else
            $imp=0;
            $d=[
                'NroPed' => $numpedido,
                'cod_prod'=>$p['cod_prod'],
                'CIfunc'=>$request->user()->CodAut,
                'idCli'=>$request->idCli,
                'Cant'=>$p['cantidad'],
                'precio'=>$p['precio'],
//                'fecha'=>date('Y-m-d H:i:s'),
                'fecha'=>$request->fecha.' '.date('H:i:s'),
                'subtotal'=>$p['subtotal'],
//                'cod_prod'=>$p['cod_prod'],
                "cbrasa5"=>$p['cbrasa5'],
                "ubrasa5"=>$p['ubrasa5'],
                "bsbrasa5"=>$p['bsbrasa5'],
                "obsbrasa5"=>$p['obsbrasa5'],
                "cbrasa6"=>$p['cbrasa6'],
                "cubrasa6"=>$p['cubrasa6'],
                "bsbrasa6"=>$p['bsbrasa6'],
                "obsbrasa6"=>$p['obsbrasa6'],
                "Observaciones"=>$p['observacion'],
                "Canttxt"=>$p['observacion']!=null?$p['observacion']:'',
                "impreso"=>$imp,
                "pagado"=>0,
                "Tipo1"=>0,
                "Tipo2"=>0,
                "c104"=>$p['c104'],
                "u104"=>$p['u104'],
                "bs104"=>$p['bs104'],
                "obs104"=>$p['obs104'],
                "c105"=>$p['c105'],
                "u105"=>$p['u105'],
                "bs105"=>$p['bs105'],
                "obs105"=>$p['obs105'],
                "c106"=>$p['c106'],
                "u106"=>$p['u106'],
                "bs106"=>$p['bs106'],
                "obs106"=>$p['obs106'],
                "c107"=>$p['c107'],
                "u107"=>$p['u107'],
                "bs107"=>$p['bs107'],
                "obs107"=>$p['obs107'],
                "c108"=>$p['c108'],
                "u108"=>$p['u108'],
                "bs108"=>$p['bs108'],
                "obs108"=>$p['obs108'],
                "c109"=>$p['c109'],
                "u109"=>$p['u109'],
                "bs109"=>$p['bs109'],
                "obs109"=>$p['obs109'],
                "ala"=>$p['ala'],
                "cadera"=>$p['cadera'],
                "pecho"=>$p['pecho'],
                "pie"=>$p['pie'],
                "filete"=>$p['filete'],
                "cuello"=>$p['cuello'],
                "hueso"=>$p['hueso'],
                "menu"=>$p['menu'],
                "unidala"=>$p['unidala'],
                "bsala"=>$p['bsala'],
                "obsala"=>$p['obsala'],
                "unidcadera"=>$p['unidcadera'],
                "bscadera"=>$p['bscadera'],
                "obscadera"=>$p['obscadera'],
                "unidpecho"=>$p['unidpecho'],
                "bspecho"=>$p['bspecho'],
                "obspecho"=>$p['obspecho'],
                "unidpie"=>$p['unidpie'],
                "bspie"=>$p['bspie'],
                "obspie"=>$p['obspie'],
                "unidfilete"=>$p['unidfilete'],
                "bsfilete"=>$p['bsfilete'],
                "obsfilete"=>$p['obsfilete'],
                "unidcuello"=>$p['unidcuello'],
                "bscuello"=>$p['bscuello'],
                "obscuello"=>$p['obscuello'],
                "unidhueso"=>$p['unidhueso'],
                "bshueso"=>$p['bshueso'],
                "obshueso"=>$p['obshueso'],
                "unidmenu"=>$p['unidmenu'],
                "bsmenu"=>$p['bsmenu'],
                "obsmenu"=>$p['obsmenu'],
                "bs"=>$p['bs'],
                "bs2"=>$p['bs2'],
                "contado"=>$p['contado'],
                "tipo"=>$p['tipo'],
                "total"=>$p['total'],
                "entero"=>$p['entero'],
                "desmembre"=>$p['desmembre'],
                "corte"=>$p['corte'],
                "kilo"=>$p['kilo'],
                "trozado"=>$p['trozado'],
                "pierna"=>$p['pierna'],
                "brazo"=>$p['brazo'],
                "pfrial"=>$p['pfrial'],
                "hora"=>date('H:i:s'),
                "pago"=>$request->pago,
                "fact"=>$request->fact,
                "rango"=>$p['rango'],
                "horario"=>$request->horario,
                "comentario"=>$request->comentario,
            ];
            array_push($data, $d);
        }
        DB::table('tbpedidos')->insert($data);
//        return ($data);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $cliente=DB::select("SELECT * FROM tbclientes WHERE Cod_Aut='".$request->idCli."'");
        $distancia=$this->distance($request->lat,$request->lng,$cliente[0]->Latitud,$cliente[0]->longitud);
        DB::table('misvisitas')->insert([
            'estado'=>$request->estado,
            'fecha'=>date('Y-m-d'),
            'hora'=>date('H:i:s'),
            'lat'=>$request->lat,
            'lng'=>$request->lng,
            'distancia'=>$distancia,
            'observacion'=>$request->observacion,
            'cliente_id'=>$request->idCli,
            'personal_id'=>$request->user()->CodAut
        ]);
    }

    public function reporteVenta(Request $request){

        $fec= strtotime($request->fecha);
        $numdia= date('w',$fec);
        $filtro='';
        switch ($numdia) {
            case 0:
                $filtro=" AND do=1 ";
                break;
            case 1:
                $filtro=" AND lu=1 ";
                break;
            case 2:
                $filtro=" AND Ma=1 ";
                break;
            case 3:
                $filtro= " AND Mi=1 ";
                break;
            case 4:
                $filtro= " AND Ju=1 ";
                break;
            case 5:
                $filtro=" AND Vi=1 ";
                break;
            case 6:
                $filtro=" AND Sa=1 ";
                break;
            default:
                $filtro= '';
                break;
        }
        return DB::SELECT("
        select p.CIfunc,l.ci,l.CodAut, l.Nombre1,l.App1,
        (SELECT count(DISTINCT(p2.idCli)) from tbpedidos p2 where date(p2.fecha)='$request->fecha' and p.CIfunc=p2.CIfunc) as totclient,
        (SELECT count(DISTINCT(p2.idCli)) from tbpedidos p2 inner join tbclientes c on p2.idCli=c.Cod_Aut where  date(p2.fecha)='$request->fecha' and p.CIfunc=p2.CIfunc ".$filtro.") as totvisita,
        (SELECT count(*) from tbclientes tc where tc.CiVend=l.ci ".$filtro.") as numcli ,
        (SELECT count(*) from misvisitas v WHERE v  .fecha='$request->fecha' and v.personal_id=l.CodAut and v.estado='PEDIDO') as npedido,
        (SELECT count(*) from misvisitas v WHERE v.fecha='$request->fecha' and v.personal_id=l.CodAut and v.estado='NO PEDIDO') as nopedido,
        (SELECT count(*) from misvisitas v WHERE v.fecha='$request->fecha' and v.personal_id=l.CodAut and v.estado='PARADO') as nparado
        from tbpedidos p inner join personal l on p.CIfunc= l.CodAut where date(p.fecha)='$request->fecha' GROUP by p.CIfunc,l.ci, l.Nombre1,l.App1,l.CodAut
        ");
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function listcomanda(Request $request){
        return DB::SELECT("SELECT * from tbproductos t, tbpedidos p, tbclientes c
        where c.Cod_Aut=p.idCli and p.cod_prod=t.cod_prod and date(fecha)>='$request->fecha1' and date(fecha)<='$request->fecha2'
        and p.tipo='NORMAL' AND estado='ENVIADO' ");
    }

    public function clientepedido(Request $request){
        //$cl=DB::SELECT("SELECT * from tbclientes where ci='".$request->user()->CodAut."'")[0];
        if($request->user()->CodAut==1)
            return DB::SELECT("SELECT p.NroPed,p.pago,p.fecha,p.fact,Cod_Aut,Id,Cod_ciudad,Cod_Nacio,cod_car,Nombres,Telf,Direccion,EstCiv,edad,Empresa,Categoria,Imp_pieza,CiVend,ListBlanck,MotivoListBlack,ListBlack,TipoPaciente,SupraCanal,Canal,subcanal,zona,Latitud,longitud,transporte,territorio,codcli,clinew,p.estado FROM tbpedidos p inner join tbclientes c on c.Cod_Aut=p.idCli  where date(p.fecha)='$request->fecha1' GROUP by  p.NroPed,p.pago,p.fecha,p.fact,cod_Aut,Id,Cod_ciudad,Cod_Nacio,cod_car,Nombres,Telf,Direccion,EstCiv,edad,Empresa,Categoria,Imp_pieza,CiVend,ListBlanck,MotivoListBlack,ListBlack,TipoPaciente,SupraCanal,Canal,subcanal,zona,Latitud,longitud,transporte,territorio,codcli,clinew,p.estado");
        else
            return DB::SELECT("SELECT p.NroPed,p.pago,p.fecha,p.fact,Cod_Aut,Id,Cod_ciudad,Cod_Nacio,cod_car,Nombres,Telf,Direccion,EstCiv,edad,Empresa,Categoria,Imp_pieza,CiVend,ListBlanck,MotivoListBlack,ListBlack,TipoPaciente,SupraCanal,Canal,subcanal,zona,Latitud,longitud,transporte,territorio,codcli,clinew,p.estado FROM tbpedidos p inner join tbclientes c on c.Cod_Aut=p.idCli  where date(p.fecha)='$request->fecha1' and c.CiVend='".$request->user()->ci."' GROUP by p.NroPed,p.pago,p.fecha,p.fact,Cod_Aut,Id,Cod_ciudad,Cod_Nacio,cod_car,Nombres,Telf,Direccion,EstCiv,edad,Empresa,Categoria,Imp_pieza,CiVend,ListBlanck,MotivoListBlack,ListBlack,TipoPaciente,SupraCanal,Canal,subcanal,zona,Latitud,longitud,transporte,territorio,codcli,clinew,p.estado");
    }

    public function pedpendiente(Request $request){
        return DB::SELECT("SELECT p.NroPed,Cod_Aut,Id,Cod_ciudad,Cod_Nacio,cod_car,Nombres,Telf,Direccion,EstCiv,edad,Empresa,Categoria,Imp_pieza,CiVend,ListBlanck,MotivoListBlack,ListBlack,TipoPaciente,SupraCanal,Canal,subcanal,zona,Latitud,longitud,transporte,territorio,codcli,clinew,p.estado,
        (SELECT sum(co.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=co.comanda))
                FROM tbctascobrar co WHERE co.CINIT=c.Id and co.Nrocierre=0 and co.Acuenta=0) as totdeuda ,
        (SELECT MIN(co.FechaEntreg) FROM tbctascobrar co WHERE co.CINIT=c.Id and co.Nrocierre=0 and co.Acuenta=0) as fechaminima
        FROM tbpedidos p inner join tbclientes c on c.Cod_Aut=p.idCli
        where c.venta='INACTIVO' and p.estado='CREADO' and date(p.fecha)='$request->fecha'
        GROUP by p.NroPed,Cod_Aut,Id,Cod_ciudad,Cod_Nacio,cod_car,Nombres,Telf,Direccion,EstCiv,edad,Empresa,Categoria,Imp_pieza,CiVend,ListBlanck,MotivoListBlack,ListBlack,TipoPaciente,SupraCanal,Canal,subcanal,zona,Latitud,longitud,transporte,territorio,codcli,clinew,p.estado");

    }

    public function enviarpedidos(Request $request)
    {
        foreach ($request->clientes as $p){
            //DB::select("UPDATE tbpedidos SET  estado='ENVIADO' WHERE NroPed='".$p['NroPed']."'");
            DB::select("UPDATE tbpedidos p set p.estado='ENVIADO' , p.envio = NOW()  where p.NroPed='".$p['NroPed']."' and (SELECT c.venta from tbclientes c where c.Cod_Aut=p.idCli)='ACTIVO'");
        }
    }

    public function envpedido(Request $request)
    {
            //DB::select("UPDATE tbpedidos SET  estado='ENVIADO' WHERE NroPed='".$request->NroPed."'");
        $pedido = Pedido::where('NroPed', $request->NroPed)->first();
        $cliente = Cliente::where('Cod_Aut', $pedido->idCli)->first();
        if ($cliente->venta == 'INACTIVO') {
            return response()->json(['message' => 'El cliente tiene deuda'], 500);
            exit();
        }
            DB::select("UPDATE tbpedidos p set p.estado='ENVIADO', p.envio = NOW()  where p.NroPed='".$request->NroPed."' and (SELECT c.venta from tbclientes c where c.Cod_Aut=p.idCli)='ACTIVO'");
    }

    public function envped(Request $request)
    {
            //DB::select("UPDATE tbpedidos SET  estado='ENVIADO' WHERE NroPed='".$request->NroPed."'");
            DB::select("UPDATE tbpedidos p set p.estado='ENVIADO', p.envio = NOW()  where p.NroPed='".$request->NroPed."'");
    }

    public function updatecomanda(Request $request){
//        return $request;
        $numpedido=$request->comanda;
        DB::select("DELETE FROM tbpedidos where NroPed='$numpedido'");
        foreach ($request->productos as $p){
            if($p['tipo']=='POLLO' || $p['tipo']=='RES' || $p['tipo']=='CERDO')
            $imp=1;
        else
            $imp=0;
            DB::table('tbpedidos')->insert([
                'NroPed' => $numpedido,
                'cod_prod'=>$p['cod_prod'],
                'CIfunc'=>$request->user()->CodAut,
                'idCli'=>$request->idCli,
                'Cant'=>$p['cantidad'],
                'precio'=>$p['precio'],
                'fecha'=>$request->fecha.' '.date('H:i:s'),
                'subtotal'=>$p['subtotal'],
                "cbrasa5"=>$p['cbrasa5'],
                "ubrasa5"=>$p['ubrasa5'],
                "bsbrasa5"=>$p['bsbrasa5'],
                "obsbrasa5"=>$p['obsbrasa5'],
                "cbrasa6"=>$p['cbrasa6'],
                "cubrasa6"=>$p['cubrasa6'],
                "bsbrasa6"=>$p['bsbrasa6'],
                "obsbrasa6"=>$p['obsbrasa6'],
                "Observaciones"=>$p['observacion'],
                "Canttxt"=>$p['observacion']!=null?$p['observacion']:'',
                "impreso"=>$imp,
                "pagado"=>0,
                "Tipo1"=>0,
                "Tipo2"=>0,
                "c104"=>$p['c104'],
                "u104"=>$p['u104'],
                "bs104"=>$p['bs104'],
                "obs104"=>$p['obs104'],
                "c105"=>$p['c105'],
                "u105"=>$p['u105'],
                "bs105"=>$p['bs105'],
                "obs105"=>$p['obs105'],
                "c106"=>$p['c106'],
                "u106"=>$p['u106'],
                "bs106"=>$p['bs106'],
                "obs106"=>$p['obs106'],
                "c107"=>$p['c107'],
                "u107"=>$p['u107'],
                "bs107"=>$p['bs107'],
                "obs107"=>$p['obs107'],
                "c108"=>$p['c108'],
                "u108"=>$p['u108'],
                "bs108"=>$p['bs108'],
                "obs108"=>$p['obs108'],
                "c109"=>$p['c109'],
                "u109"=>$p['u109'],
                "bs109"=>$p['bs109'],
                "obs109"=>$p['obs109'],
                "ala"=>$p['ala'],
                "cadera"=>$p['cadera'],
                "pecho"=>$p['pecho'],
                "pie"=>$p['pie'],
                "filete"=>$p['filete'],
                "cuello"=>$p['cuello'],
                "hueso"=>$p['hueso'],
                "menu"=>$p['menu'],
                "unidala"=>$p['unidala'],
                "bsala"=>$p['bsala'],
                "obsala"=>$p['obsala'],
                "unidcadera"=>$p['unidcadera'],
                "bscadera"=>$p['bscadera'],
                "obscadera"=>$p['obscadera'],
                "unidpecho"=>$p['unidpecho'],
                "bspecho"=>$p['bspecho'],
                "obspecho"=>$p['obspecho'],
                "unidpie"=>$p['unidpie'],
                "bspie"=>$p['bspie'],
                "obspie"=>$p['obspie'],
                "unidfilete"=>$p['unidfilete'],
                "bsfilete"=>$p['bsfilete'],
                "obsfilete"=>$p['obsfilete'],
                "unidcuello"=>$p['unidcuello'],
                "bscuello"=>$p['bscuello'],
                "obscuello"=>$p['obscuello'],
                "unidhueso"=>$p['unidhueso'],
                "bshueso"=>$p['bshueso'],
                "obshueso"=>$p['obshueso'],
                "unidmenu"=>$p['unidmenu'],
                "bsmenu"=>$p['bsmenu'],
                "obsmenu"=>$p['obsmenu'],
                "bs"=>$p['bs'],
                "bs2"=>$p['bs2'],
                "contado"=>$p['contado'],
                "tipo"=>$p['tipo'],
                "total"=>$p['total'],
                "entero"=>$p['entero'],
                "desmembre"=>$p['desmembre'],
                "corte"=>$p['corte'],
                "kilo"=>$p['kilo'],
                "trozado"=>$p['trozado'],
                "pierna"=>$p['pierna'],
                "brazo"=>$p['brazo'],
                "pfrial"=>$p['pfrial'],
                "hora"=>date('H:i:s'),
                "pago"=>$request->pago,
                "fact"=>$request->fact,
                "rango"=>$p['rango'],
                "horario"=>$request->horario,
                "comentario"=>$request->comentario,
            ]);
//            var_dump($p);
        }
    }
    public function deletecomanda(Request $request){
        //        return $request;
                $numpedido=$request->comanda;
                DB::select("DELETE FROM tbpedidos where NroPed='$numpedido'");
    }
    public function listpedido(Request $request){
        $pedido= DB::SELECT("SELECT NroPed,CIfunc,idCli,fecha,estado,pago,fact,horario,comentario from tbpedidos where NroPed='$request->NroPed'  group by NroPed,CIfunc,idCli,fecha,estado,pago,fact,horario,comentario ");
//        return $pedido;
        foreach ($pedido as $row) {
            $lisrped=DB::SELECT("SELECT
            tbpedidos.codAut ,
            NroPed	,
            tbpedidos.cod_prod,
            CIfunc	,
            idCli	,
            Cant	as cantidad,
            Tipo1	,
            Tipo2	,
            Canttxt	,
            tbpedidos.precio	,
            fecha	,
            estado	,
            impreso	,
            Observaciones	as observacion,
            pagado	,
            subtotal,
            cbrasa5	,
            ubrasa5	,
            bsbrasa5	,
            obsbrasa5	,
            cbrasa6	,
            cubrasa6,
            bsbrasa6	,
            obsbrasa6	,
            c104	,
            u104	,
            bs104	,
            obs104	,
            c105	,
            u105	,
            bs105	,
            obs105	,
            c106	,
            u106	,
            bs106	,
            obs106	,
            c107	,
            u107	,
            bs107	,
            obs107	,
            c108	,
            u108	,
            bs108	,
            obs108	,
            c109	,
            u109	,
            bs109	,
            obs109	,
            ala	,
            unidala	,
            bsala	,
            obsala	,
            cadera	,
            unidcadera	,
            bscadera	,
            obscadera	,
            pecho	,
            unidpecho,
            bspecho	,
            obspecho	,
            pie	,
            unidpie	,
            bspie	,
            obspie	,
            filete	,
            unidfilete	,
            bsfilete	,
            obsfilete	,
            cuello	,
            unidcuello	,
            bscuello	,
            obscuello	,
            hueso	,
            unidhueso,
            bshueso	,
            obshueso	,
            menu	,
            unidmenu,
            bsmenu	,
            obsmenu	,
            bs	,
            bs2	,
            contado	,
            tbpedidos.tipo	,
            total	,
            entero	,
            desmembre,
            corte	,
            kilo	,
            trozado	,
            pierna	,
            brazo	,
            pfrial	,
            hora	,
            pago,
            fact,
            rango,
            horario,
            comentario,
            tbproductos.Producto as nombre
            from tbpedidos,tbproductos where tbpedidos.cod_prod=tbproductos.cod_prod and  NroPed = '$row->NroPed'" );

            $row->pedidos=$lisrped;
        }

        return $pedido;
    }

    public function export(Request $request){
        $pedidos=DB::SELECT("SELECT * from tbpedidos where tipo='NORMAL' AND  date(fecha)>='$request->fecha1' and date(fecha)<='$request->fecha2' and estado='ENVIADO' ");
//        return $pedidos;
        foreach ($pedidos as $p){
//            return  $p->NroPed;
            $comanda=DB::connection('aron-9')->table('tbpedidos')->where('codAut',$p->codAut)->get()->count();
//            return  $comanda.'  -   ';
            if($comanda==0){
                //$pedi=DB::select("SELECT * from tbpedidos where tipo='NORMAL' AND date(fecha)>='$request->fecha1' and date(fecha)<='$request->fecha2' AND NroPed='".$p->NroPed."' and estado='ENVIADO' ");
//                return $pedi;
                //foreach ($pedi as $pe){
//                    return $pe->NroPed;CREADO
//                    $validar=DB::connection('aron-9')->table('tbpedidos')->where('NroPed',$pe->NroPed)->get();
//                    if($validar->count()==0){
                    DB::connection('aron-9')->table('tbpedidos')->insert([
                        "codAut"=>$p->codAut,
                        "NroPed"=>$p->NroPed,
                        "cod_prod"=>$p->cod_prod,
                        "CIfunc"=>$p->CIfunc,
                        "idCli"=>$p->idCli,
                        "Cant"=>$p->Cant,
                        "precio"=>$p->precio,
                        "fecha"=>$p->fecha,
                        "estado"=>$p->estado,
                        "subtotal"=>$p->subtotal,
                        "cbrasa5"=>$p->cbrasa5,
                        "ubrasa5"=>$p->ubrasa5,
                        "bsbrasa5"=>$p->bsbrasa5,
                        "obsbrasa5"=>$p->obsbrasa5,
                        "cbrasa6"=>$p->cbrasa6,
                        "cubrasa6"=>$p->cubrasa6,
                        "bsbrasa6"=>$p->bsbrasa6,
                        "obsbrasa6"=>$p->obsbrasa6,
                        "Observaciones"=>$p->Observaciones,
                        "Canttxt"=>$p->Observaciones!=null?$p->Observaciones:'',
                        "impreso"=>0,
                        "pagado"=>0,
                        "Tipo1"=>0,
                        "Tipo2"=>0,
                        "c104"=>$p->c104,
                        "u104"=>$p->u104,
                        "bs104"=>$p->bs104,
                        "obs104"=>$p->obs104,
                        "c105"=>$p->c105,
                        "u105"=>$p->u105,
                        "bs105"=>$p->bs105,
                        "obs105"=>$p->obs105,
                        "c106"=>$p->c106,
                        "u106"=>$p->u106,
                        "bs106"=>$p->bs106,
                        "obs106"=>$p->obs106,
                        "c107"=>$p->c107,
                        "u107"=>$p->u107,
                        "bs107"=>$p->bs107,
                        "obs107"=>$p->obs107,
                        "c108"=>$p->c108,
                        "u108"=>$p->u108,
                        "bs108"=>$p->bs108,
                        "obs108"=>$p->obs108,
                        "c109"=>$p->c109,
                        "u109"=>$p->u109,
                        "bs109"=>$p->bs109,
                        "obs109"=>$p->obs109,
                        "ala"=>$p->ala,
                        "cadera"=>$p->cadera,
                        "pecho"=>$p->pecho,
                        "pie"=>$p->pie,
                        "filete"=>$p->filete,
                        "cuello"=>$p->cuello,
                        "hueso"=>$p->hueso,
                        "menu"=>$p->menu,
                        "unidala"=>$p->unidala,
                        "bsala"=>$p->bsala,
                        "obsala"=>$p->obsala,
                        "unidcadera"=>$p->unidcadera,
                        "bscadera"=>$p->bscadera,
                        "obscadera"=>$p->obscadera,
                        "unidpecho"=>$p->unidpecho,
                        "bspecho"=>$p->bspecho,
                        "obspecho"=>$p->obspecho,
                        "unidpie"=>$p->unidpie,
                        "bspie"=>$p->bspie,
                        "obspie"=>$p->obspie,
                        "unidfilete"=>$p->unidfilete,
                        "bsfilete"=>$p->bsfilete,
                        "obsfilete"=>$p->obsfilete,
                        "unidcuello"=>$p->unidcuello,
                        "bscuello"=>$p->bscuello,
                        "obscuello"=>$p->obscuello,
                        "unidhueso"=>$p->unidhueso,
                        "bshueso"=>$p->bshueso,
                        "obshueso"=>$p->obshueso,
                        "unidmenu"=>$p->unidmenu,
                        "bsmenu"=>$p->bsmenu,
                        "obsmenu"=>$p->obsmenu,
                        "bs"=>$p->bs,
                        "bs2"=>$p->bs2,
                        "contado"=>$p->contado,
                        "tipo"=>$p->tipo,
                        "total"=>$p->total,
                        "entero"=>$p->entero,
                        "desmembre"=>$p->desmembre,
                        "corte"=>$p->corte,
                        "kilo"=>$p->kilo,
                        "trozado"=>$p->trozado,
                        "pierna"=>$p->pierna,
                        "brazo"=>$p->brazo,
                        "pfrial"=>$p->pfrial,
                        "hora"=>$p->hora,
                        "pago"=>$p->pago,
                        "fact"=>$p->fact,
                        "rango"=>$p->rango,

                    ]);
//                }
                //}
            }

        }
//        $conn = mysqli_connect("6.tcp.ngrok.io", "root", "", "sofia","14839");
//                if ($conn->connect_error) {
//                    die("Connection failed: " . $conn->connect_error);
//                }
//                foreach ($pedido as $row) {
//                    # code...
//                $result = $conn->query("SELECT * from tbpedidos where codAut='".$row->codAut."'");
//                if ($result->num_rows == 0) {
//                        $conn->query("INSERT INTO tbpedidos SET
//                NroPed = '".$row['NroPed']."',
//                cod_prod='".$row['cod_prod']."',
//                CIfunc='".$row['CIfunc']."',
//                idCli='".$row['idCli']."',
//                Cant='".$row['Cant']."',
//                precio='".$row['precio']."',
//                fecha='".$row['fecha']."',
//                subtotal='".$row['subtotal']."',
//                cbrasa5='".$row['cbrasa5']."',
//                ubrasa5='".$row['ubrasa5']."',
//                bsbrasa5='".$row['bsbrasa5']."',
//                obsbrasa5='".$row['obsbrasa5']."',
//                cbrasa6='".$row['cbrasa6']."',
//                cubrasa6='".$row['cubrasa6']."',
//                bsbrasa6='".$row['bsbrasa6']."',
//                obsbrasa6='".$row['obsbrasa6']."',
//                Observaciones='".$row['Observaciones']."',
//                c104='".$row['c104']."',
//                u104='".$row['u104']."',
//                bs104='".$row['bs104']."',
//                obs104='".$row['obs104']."',
//                c105='".$row['c105']."',
//                u105='".$row['u105']."',
//                bs105='".$row['bs105']."',
//                obs105='".$row['obs105']."',
//                c106='".$row['c106']."',
//                u106='".$row['u106']."',
//                bs106='".$row['bs106']."',
//                obs106='".$row['obs106']."',
//                c107='".$row['c107']."',
//                u107='".$row['u107']."',
//                bs107='".$row['bs107']."',
//                obs107='".$row['obs107']."',
//                c108='".$row['c108']."',
//                u108='".$row['u108']."',
//                bs108='".$row['bs108']."',
//                obs108='".$row['obs108']."',
//                c109='".$row['c109']."',
//                u109='".$row['u109']."',
//                bs109='".$row['bs109']."',
//                obs109='".$row['obs109']."',
//                ala='".$row['ala']."',
//                cadera='".$row['cadera']."',
//                pecho='".$row['pecho']."',
//                pie='".$row['pie']."',
//                filete='".$row['filete']."',
//                cuello='".$row['cuello']."',
//                hueso='".$row['hueso']."',
//                menu='".$row['menu']."',
//                unidala='".$row['unidala']."',
//                bsala='".$row['bsala']."',
//                obsala='".$row['obsala']."',
//                unidcadera='".$row['unidcadera']."',
//                bscadera='".$row['bscadera']."',
//                obscadera='".$row['obscadera']."',
//                unidpecho='".$row['unidpecho']."',
//                bspecho='".$row['bspecho']."',
//                obspecho='".$row['obspecho']."',
//                unidpie='".$row['unidpie']."',
//                bspie='".$row['bspie']."',
//                obspie='".$row['obspie']."',
//                unidfilete='".$row['unidfilete']."',
//                bsfilete='".$row['bsfilete']."',
//                obsfilete='".$row['obsfilete']."',
//                unidcuello='".$row['unidcuello']."',
//                bscuello='".$row['bscuello']."',
//                obscuello='".$row['obscuello']."',
//                unidhueso='".$row['unidhueso']."',
//                bshueso='".$row['bshueso']."',
//                obshueso='".$row['obshueso']."',
//                unidmenu='".$row['unidmenu']."',
//                bsmenu='".$row['bsmenu']."',
//                obsmenu='".$row['obsmenu']."',
//                bs='".$row['bs']."',
//                bs2='".$row['bs2']."',
//                contado='".$row['contado']."',
//                tipo='".$row['tipo']."',
//                total='".$row['total']."',
//                entero='".$row['entero']."',
//                desmembre='".$row['desmembre']."',
//                corte='".$row['corte']."',
//                kilo='".$row['kilo']."',
//                trozado='".$row['trozado']."',
//                pierna='".$row['pierna']."',
//                brazo='".$row['brazo']."',
//                hora='".$row['hora']."',
//                pago='".$row['pago']."'
//                        ");
//
//                } else {
//        //            echo "0";
//                }
//            }
//                $conn->close();
    }

    public function  resumenPedidos($fecha){

        $sql="SELECT c.Id,c.Nombres,p.NroPed,p.pago,p.fact,CONCAT(e.Nombre1,' ',e.App1)  personal,p.fecha,p.envio,impreso
        from tbpedidos p inner join personal e on p.CIfunc=e.CodAut inner join tbclientes c on p.idCli=c.Cod_Aut
        where date(p.fecha)='$fecha' and p.tipo='NORMAL' and estado='ENVIADO'
        GROUP by c.Id,c.Nombres,p.NroPed,p.pago,p.fact,personal,p.fecha,impreso,p.envio
        order by c.Id, p.NroPed";
//        error_log($sql);
        return DB::SELECT($sql);
    }

    public function mapClient(Request $request){
        if($request->id==0)
            return DB::select("SELECT p.idCli,c.Id,c.Nombres,c.Latitud,c.longitud,concat(trim(e.Nombre1),' ',trim(e.App1)) as vendedor
            from tbpedidos p inner join tbclientes c on p.idCli=c.Cod_Aut inner join personal e on p.CIfunc=e.CodAut
            where date(p.fecha)='$request->fecha' group by p.idCli,c.Id,c.Nombres,c.Latitud,c.longitud,e.Nombre1,e.App1 ");
        else
            return DB::select("SELECT p.idCli,c.Id,c.Nombres,c.Latitud,c.longitud,concat(trim(e.Nombre1),' ',trim(e.App1)) as vendedor
            from tbpedidos p inner join tbclientes c on p.idCli=c.Cod_Aut inner join personal e on p.CIfunc=e.CodAut
            where date(p.fecha)='$request->fecha' and  trim(p.CIfunc)=$request->id group by p.idCli,c.Id,c.Nombres,c.Latitud,c.longitud,e.Nombre1,e.App1");
    }

    public function mapClientes(Request $request){
        $resultado = DB::select("
        SELECT
            p.idCli, c.Id, c.Nombres, c.Latitud, c.longitud, c.territorio,
            CONCAT(TRIM(e.Nombre1), ' ', TRIM(e.App1)) AS vendedor,
            p.placa,p.horario,
            (SELECT v.color FROM vehiculo v WHERE v.placa = p.placa) AS color,
            SUM(p.subtotal) AS importe
        FROM tbpedidos p
        INNER JOIN tbclientes c ON p.idCli = c.Cod_Aut
        INNER JOIN personal e ON p.CIfunc = e.CodAut
        WHERE DATE(p.fecha) = ? AND p.estado = 'ENVIADO' AND p.tipo = 'NORMAL'
        GROUP BY p.idCli, c.Id, c.Nombres, c.Latitud, c.longitud, c.territorio, e.Nombre1, e.App1, p.placa,p.horario
    ", [$request->fecha]);

    // Devuelve el resultado como JSON o úsalo en tu vista
    return response()->json($resultado);
    }

    public function detallePedMap(Request $request){
            $resultado = DB::select("
                SELECT p.NroPed, p.Cant, tpr.cod_prod, tpr.Producto
                FROM tbpedidos p
                INNER JOIN tbproductos tpr ON p.cod_prod = tpr.cod_prod
                WHERE DATE(p.fecha) = ? AND p.tipo = 'NORMAL' AND p.idCli = ?
            ", [$request->fecha, $request->idCli]);
    return response()->json($resultado);
}

    public function listVehiculo(){
        return DB::SELECT("SELECT * from vehiculo order by id asc");
    }

    public function updaVehiPed(Request $request)
    {
        // Validar los datos del request
        $placa = $request->placa;
        $fecha = $request->fecha;
        $ids = array_map(function ($item) {
            return trim($item['idCli']);
        }, $request->listado);

        // Convertir el array de IDs en un string para usar en la consulta
        $idsString = implode("','", $ids);

        // Realizar la consulta de una sola vez
        DB::statement("
        UPDATE tbpedidos p
        SET p.placa = ?
        WHERE DATE(p.fecha) = ?
        AND TRIM(p.idCli) IN ('$idsString')
    ", [$placa, $fecha]);
    }
}
