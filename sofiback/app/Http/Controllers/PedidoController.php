<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedidoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
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


    public function store(Request $request)
    {
//        return $request->productos;
        $max=DB::select("SELECT max(NroPed) as max FROM tbpedidos");
        $cliente=DB::select("SELECT * FROM tbclientes WHERE Cod_Aut='".$request->idCli."'");
//        echo ($cliente[0]->Latitud);
//        return floatval( $request->lat)."   -   ".floatval($request->lng)."   -   ".$cliente[0]->Latitud."   -   ".$cliente[0]->longitud;
        $distancia=$this->distance( floatval( $request->lat),floatval($request->lng),floatval($cliente[0]->Latitud),floatval($cliente[0]->longitud));

        $numpedido=$max[0]->max+1;
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
//            echo $p['idCli'].'--';

//            DB::table('tbpedidos')->insert([
//                'NroPed' => $numpedido,
//                'cod_prod'=>$p['cod_prod'],
//                'CIfunc'=>$request->user()->CodAut,
//                'idCli'=>$request->idCli,
//                'Cant'=>$p['cantidad'],
//                'precio'=>$p['precio'],
//                'fecha'=>date('Y-m-d H:i:s'),
//                'subtotal'=>$p['subtotal'],
////                'cod_prod'=>$p['cod_prod'],
//                "cbrasa5"=>$p['cbrasa5'],
//                "ubrasa5"=>$p['ubrasa5'],
//                "bsbrasa5"=>$p['bsbrasa5'],
//                "obsbrasa5"=>$p['obsbrasa5'],
//                "cbrasa6"=>$p['cbrasa6'],
//                "cubrasa6"=>$p['cubrasa6'],
//                "bsbrasa6"=>$p['bsbrasa6'],
//                "obsbrasa6"=>$p['obsbrasa6'],
//                "Observaciones"=>$p['observacion'],
//                "Canttxt"=>$p['observacion']!=null?$p['observacion']:'',
//                "impreso"=>0,
//                "pagado"=>0,
//                "Tipo1"=>0,
//                "Tipo2"=>0,
//                "c104"=>$p['c104'],
//                "u104"=>$p['u104'],
//                "bs104"=>$p['bs104'],
//                "obs104"=>$p['obs104'],
//                "c105"=>$p['c105'],
//                "u105"=>$p['u105'],
//                "bs105"=>$p['bs105'],
//                "obs105"=>$p['obs105'],
//                "c106"=>$p['c106'],
//                "u106"=>$p['u106'],
//                "bs106"=>$p['bs106'],
//                "obs106"=>$p['obs106'],
//                "c107"=>$p['c107'],
//                "u107"=>$p['u107'],
//                "bs107"=>$p['bs107'],
//                "obs107"=>$p['obs107'],
//                "c108"=>$p['c108'],
//                "u108"=>$p['u108'],
//                "bs108"=>$p['bs108'],
//                "obs108"=>$p['obs108'],
//                "c109"=>$p['c109'],
//                "u109"=>$p['u109'],
//                "bs109"=>$p['bs109'],
//                "obs109"=>$p['obs109'],
//                "ala"=>$p['ala'],
//                "cadera"=>$p['cadera'],
//                "pecho"=>$p['pecho'],
//                "pie"=>$p['pie'],
//                "filete"=>$p['filete'],
//                "cuello"=>$p['cuello'],
//                "hueso"=>$p['hueso'],
//                "menu"=>$p['menu'],
//                "unidala"=>$p['unidala'],
//                "bsala"=>$p['bsala'],
//                "obsala"=>$p['obsala'],
//                "unidcadera"=>$p['unidcadera'],
//                "bscadera"=>$p['bscadera'],
//                "obscadera"=>$p['obscadera'],
//                "unidpecho"=>$p['unidpecho'],
//                "bspecho"=>$p['bspecho'],
//                "obspecho"=>$p['obspecho'],
//                "unidpie"=>$p['unidpie'],
//                "bspie"=>$p['bspie'],
//                "obspie"=>$p['obspie'],
//                "unidfilete"=>$p['unidfilete'],
//                "bsfilete"=>$p['bsfilete'],
//                "obsfilete"=>$p['obsfilete'],
//                "unidcuello"=>$p['unidcuello'],
//                "bscuello"=>$p['bscuello'],
//                "obscuello"=>$p['obscuello'],
//                "unidhueso"=>$p['unidhueso'],
//                "bshueso"=>$p['bshueso'],
//                "obshueso"=>$p['obshueso'],
//                "unidmenu"=>$p['unidmenu'],
//                "bsmenu"=>$p['bsmenu'],
//                "obsmenu"=>$p['obsmenu'],
//                "bs"=>$p['bs'],
//                "bs2"=>$p['bs2'],
//                "contado"=>$p['contado'],
//                "tipo"=>$p['tipo'],
//                "total"=>$p['total'],
//                "entero"=>$p['entero'],
//                "desmembre"=>$p['desmembre'],
//                "corte"=>$p['corte'],
//                "kilo"=>$p['kilo'],
//                "trozado"=>$p['trozado'],
//                "pierna"=>$p['pierna'],
//                "brazo"=>$p['brazo'],
//                "pfrial"=>$p['pfrial'],
//                "hora"=>date('H:i:s'),
//                "pago"=>$request->pago
//            ]);
// //            var_dump($p);
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
                "fact"=>$request->fact
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
            return DB::SELECT("SELECT p.NroPed,p.pago,p.fact,Cod_Aut,Id,Cod_ciudad,Cod_Nacio,cod_car,Nombres,Telf,Direccion,EstCiv,edad,Empresa,Categoria,Imp_pieza,CiVend,ListBlanck,MotivoListBlack,ListBlack,TipoPaciente,SupraCanal,Canal,subcanal,zona,Latitud,longitud,transporte,territorio,codcli,clinew,p.estado FROM tbpedidos p inner join tbclientes c on c.Cod_Aut=p.idCli  where date(p.fecha)='$request->fecha1' GROUP by  p.NroPed,p.pago,p.fact,cod_Aut,Id,Cod_ciudad,Cod_Nacio,cod_car,Nombres,Telf,Direccion,EstCiv,edad,Empresa,Categoria,Imp_pieza,CiVend,ListBlanck,MotivoListBlack,ListBlack,TipoPaciente,SupraCanal,Canal,subcanal,zona,Latitud,longitud,transporte,territorio,codcli,clinew,p.estado");
        else
            return DB::SELECT("SELECT p.NroPed,p.pago,p.fact,Cod_Aut,Id,Cod_ciudad,Cod_Nacio,cod_car,Nombres,Telf,Direccion,EstCiv,edad,Empresa,Categoria,Imp_pieza,CiVend,ListBlanck,MotivoListBlack,ListBlack,TipoPaciente,SupraCanal,Canal,subcanal,zona,Latitud,longitud,transporte,territorio,codcli,clinew,p.estado FROM tbpedidos p inner join tbclientes c on c.Cod_Aut=p.idCli  where date(p.fecha)='$request->fecha1' and c.CiVend='".$request->user()->ci."' GROUP by p.NroPed,p.pago,p.fact,Cod_Aut,Id,Cod_ciudad,Cod_Nacio,cod_car,Nombres,Telf,Direccion,EstCiv,edad,Empresa,Categoria,Imp_pieza,CiVend,ListBlanck,MotivoListBlack,ListBlack,TipoPaciente,SupraCanal,Canal,subcanal,zona,Latitud,longitud,transporte,territorio,codcli,clinew,p.estado");
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
            DB::select("UPDATE tbpedidos p set p.estado='ENVIADO' where p.NroPed='".$p['NroPed']."' and (SELECT c.venta from tbclientes c where c.Cod_Aut=p.idCli)='ACTIVO'");
        }
    }

    public function envpedido(Request $request)
    {
            //DB::select("UPDATE tbpedidos SET  estado='ENVIADO' WHERE NroPed='".$request->NroPed."'");
            DB::select("UPDATE tbpedidos p set p.estado='ENVIADO' where p.NroPed='".$request->NroPed."' and (SELECT c.venta from tbclientes c where c.Cod_Aut=p.idCli)='ACTIVO'");
    }

    public function envped(Request $request)
    {
            //DB::select("UPDATE tbpedidos SET  estado='ENVIADO' WHERE NroPed='".$request->NroPed."'");
            DB::select("UPDATE tbpedidos p set p.estado='ENVIADO' where p.NroPed='".$request->NroPed."'");
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
                "fact"=>$request->fact
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
        $pedido= DB::SELECT("SELECT NroPed,CIfunc,idCli,fecha,estado,pago,fact from tbpedidos where NroPed='$request->NroPed'  group by NroPed,CIfunc,idCli,fecha,estado,pago,fact ");
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
}
