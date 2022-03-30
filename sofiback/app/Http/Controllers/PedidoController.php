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
            'cliente_id'=>$request->idCli
        ]);
        foreach ($request->productos as $p){
//            echo $p['idCli'].'--';

            DB::table('tbpedidos')->insert([
                'NroPed' => $numpedido,
                'cod_prod'=>$p['cod_prod'],
                'CIfunc'=>$request->user()->CodAut,
                'idCli'=>$request->idCli,
                'Cant'=>$p['cantidad'],
                'precio'=>$p['precio'],
                'fecha'=>date('Y-m-d H:i:s'),
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
                "hora"=>date('H:i:s'),
                "pago"=>$request->pago
            ]);
//            var_dump($p);
        }
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
            'cliente_id'=>$request->idCli
        ]);
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

    public function clientepedido(Request $request){
        //$cl=DB::SELECT("SELECT * from tbclientes where ci='".$request->user()->CodAut."'")[0];
        if($request->user()->CodAut==1)
            return DB::SELECT("SELECT p.NroPed,Cod_Aut,Id,Cod_ciudad,Cod_Nacio,cod_car,Nombres,Telf,Direccion,EstCiv,edad,Empresa,Categoria,Imp_pieza,CiVend,ListBlanck,MotivoListBlack,ListBlack,TipoPaciente,SupraCanal,Canal,subcanal,zona,Latitud,longitud,transporte,territorio,codcli,clinew,p.estado FROM tbpedidos p inner join tbclientes c on c.Cod_Aut=p.idCli  where date(p.fecha)>='$request->fecha1' and date(p.fecha)<='$request->fecha2' GROUP by  p.NroPed,Cod_Aut,Id,Cod_ciudad,Cod_Nacio,cod_car,Nombres,Telf,Direccion,EstCiv,edad,Empresa,Categoria,Imp_pieza,CiVend,ListBlanck,MotivoListBlack,ListBlack,TipoPaciente,SupraCanal,Canal,subcanal,zona,Latitud,longitud,transporte,territorio,codcli,clinew,p.estado");
        else
            return DB::SELECT("SELECT p.NroPed,Cod_Aut,Id,Cod_ciudad,Cod_Nacio,cod_car,Nombres,Telf,Direccion,EstCiv,edad,Empresa,Categoria,Imp_pieza,CiVend,ListBlanck,MotivoListBlack,ListBlack,TipoPaciente,SupraCanal,Canal,subcanal,zona,Latitud,longitud,transporte,territorio,codcli,clinew,p.estado FROM tbpedidos p inner join tbclientes c on c.Cod_Aut=p.idCli  where date(p.fecha)>='$request->fecha1' and date(p.fecha)<='$request->fecha2' and c.CiVend='".$request->user()->ci."' GROUP by p.NroPed,Cod_Aut,Id,Cod_ciudad,Cod_Nacio,cod_car,Nombres,Telf,Direccion,EstCiv,edad,Empresa,Categoria,Imp_pieza,CiVend,ListBlanck,MotivoListBlack,ListBlack,TipoPaciente,SupraCanal,Canal,subcanal,zona,Latitud,longitud,transporte,territorio,codcli,clinew,p.estado");
    }
    public function enviarpedidos(Request $request)
    {
        foreach ($request->clientes as $p){
            DB::select("UPDATE tbpedidos SET  estado='ENVIADO' WHERE NroPed='".$p['NroPed']."'");
        }
    }

    public function envpedido(Request $request)
    {
            DB::select("UPDATE tbpedidos SET  estado='ENVIADO' WHERE NroPed='".$request->NroPed."'");
    }

    public function updatecomanda(Request $request){
//        return $request;
        $numpedido=$request->comanda;
        DB::select("DELETE FROM tbpedidos where NroPed='$numpedido'");
        foreach ($request->productos as $p){
            DB::table('tbpedidos')->insert([
                'NroPed' => $numpedido,
                'cod_prod'=>$p['cod_prod'],
                'CIfunc'=>$request->user()->CodAut,
                'idCli'=>$request->idCli,
                'Cant'=>$p['cantidad'],
                'precio'=>$p['precio'],
                'fecha'=>date('Y-m-d H:i:s'),
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
                "hora"=>date('H:i:s'),
                "pago"=>$request->pago
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
        $pedido= DB::SELECT("SELECT NroPed,CIfunc,idCli,fecha,estado from tbpedidos where NroPed='$request->NroPed' and date(fecha)>='$request->fecha1' and date(fecha)<='$request->fecha2' group by NroPed,CIfunc,idCli,fecha,estado ");
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
            hora	,
            tbproductos.Producto as nombre
            from tbpedidos,tbproductos where tbpedidos.cod_prod=tbproductos.cod_prod and  NroPed = '$row->NroPed'" );

            $row->pedidos=$lisrped;
        }

        return $pedido;
    }

    public function export(Request $request){
        $pedidos=DB::SELECT("SELECT NroPed from tbpedidos where date(fecha)>='$request->fecha1' and date(fecha)<='$request->fecha2' and estado='ENVIADO' GROUP BY NroPed");
//        return $pedidos;
        foreach ($pedidos as $p){
//            return  $p->NroPed;
            $comanda=DB::connection('aron-9')->table('tbpedidos')->where('NroPed',$p->NroPed)->get()->count();
//            return  $comanda.'  -   ';
            if($comanda==0){
                $pedi=DB::SELECT("SELECT * from tbpedidos where date(fecha)>='$request->fecha1' and date(fecha)<='$request->fecha2' AND NroPed='".$p['NroPed']."' and estado='ENVIADO' ");
                return $pedi;
                foreach ($pedi as $pe){
                    DB::connection('aron-9')->table('tbpedidos')->insert([
                        "NroPed"=>$pe["NroPed"],
                        "cod_prod"=>$pe["cod_prod"],
                        "CIfunc"=>$pe["CIfunc"],
                        "idCli"=>$pe["idCli"],
                        "Cant"=>$pe["Cant"],
                        "precio"=>$pe["precio"],
                        "fecha"=>$pe["fecha"],
                        "subtotal"=>$pe["subtotal"],
                        "cbrasa5"=>$pe["cbrasa5"],
                        "ubrasa5"=>$pe["ubrasa5"],
                        "bsbrasa5"=>$pe["bsbrasa5"],
                        "obsbrasa5"=>$pe["obsbrasa5"],
                        "cbrasa6"=>$pe["cbrasa6"],
                        "cubrasa6"=>$pe["cubrasa6"],
                        "bsbrasa6"=>$pe["bsbrasa6"],
                        "obsbrasa6"=>$pe["obsbrasa6"],
                        "Observaciones"=>$pe["Observaciones"],
                        "c104"=>$pe["c104"],
                        "u104"=>$pe["u104"],
                        "bs104"=>$pe["bs104"],
                        "obs104"=>$pe["obs104"],
                        "c105"=>$pe["c105"],
                        "u105"=>$pe["u105"],
                        "bs105"=>$pe["bs105"],
                        "obs105"=>$pe["obs105"],
                        "c106"=>$pe["c106"],
                        "u106"=>$pe["u106"],
                        "bs106"=>$pe["bs106"],
                        "obs106"=>$pe["obs106"],
                        "c107"=>$pe["c107"],
                        "u107"=>$pe["u107"],
                        "bs107"=>$pe["bs107"],
                        "obs107"=>$pe["obs107"],
                        "c108"=>$pe["c108"],
                        "u108"=>$pe["u108"],
                        "bs108"=>$pe["bs108"],
                        "obs108"=>$pe["obs108"],
                        "c109"=>$pe["c109"],
                        "u109"=>$pe["u109"],
                        "bs109"=>$pe["bs109"],
                        "obs109"=>$pe["obs109"],
                        "ala"=>$pe["ala"],
                        "cadera"=>$pe["cadera"],
                        "pecho"=>$pe["pecho"],
                        "pie"=>$pe["pie"],
                        "filete"=>$pe["filete"],
                        "cuello"=>$pe["cuello"],
                        "hueso"=>$pe["hueso"],
                        "menu"=>$pe["menu"],
                        "unidala"=>$pe["unidala"],
                        "bsala"=>$pe["bsala"],
                        "obsala"=>$pe["obsala"],
                        "unidcadera"=>$pe["unidcadera"],
                        "bscadera"=>$pe["bscadera"],
                        "obscadera"=>$pe["obscadera"],
                        "unidpecho"=>$pe["unidpecho"],
                        "bspecho"=>$pe["bspecho"],
                        "obspecho"=>$pe["obspecho"],
                        "unidpie"=>$pe["unidpie"],
                        "bspie"=>$pe["bspie"],
                        "obspie"=>$pe["obspie"],
                        "unidfilete"=>$pe["unidfilete"],
                        "bsfilete"=>$pe["bsfilete"],
                        "obsfilete"=>$pe["obsfilete"],
                        "unidcuello"=>$pe["unidcuello"],
                        "bscuello"=>$pe["bscuello"],
                        "obscuello"=>$pe["obscuello"],
                        "unidhueso"=>$pe["unidhueso"],
                        "bshueso"=>$pe["bshueso"],
                        "obshueso"=>$pe["obshueso"],
                        "unidmenu"=>$pe["unidmenu"],
                        "bsmenu"=>$pe["bsmenu"],
                        "obsmenu"=>$pe["obsmenu"],
                        "bs"=>$pe["bs"],
                        "bs2"=>$pe["bs2"],
                        "contado"=>$pe["contado"],
                        "tipo"=>$pe["tipo"],
                        "total"=>$pe["total"],
                        "entero"=>$pe["entero"],
                        "desmembre"=>$pe["desmembre"],
                        "corte"=>$pe["corte"],
                        "kilo"=>$pe["kilo"],
                        "trozado"=>$pe["trozado"],
                        "pierna"=>$pe["pierna"],
                        "brazo"=>$pe["brazo"],
                        "hora"=>$pe["hora"],
                        "pago"=>$pe["pago"],
                    ]);
                }
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
