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
    public function store(Request $request)
    {
//        return $request;
        $max=DB::select("SELECT max(NroPed) as max FROM tbpedidos");
        $cliente=DB::select("SELECT * FROM tbclientes WHERE Cod_Aut='".$request->idCli."'");
//        echo ($cliente[0]->Latitud);

        $distancia=$this->distance($request->lat,$request->lng,$cliente[0]->Latitud,$cliente[0]->longitud);
//        return $distancia;
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
                'cod_prod'=>$p['cod_prod'],
                "cbrasa5"=>$p['cbrasa5'],
                "ubrasa5"=>$p['ubrasa5'],
                "cbrasa6"=>$p['cbrasa6'],
                "cubrasa6"=>$p['cubrasa6'],
                "c104"=>$p['c104'],
                "u104"=>$p['u104'],
                "c105"=>$p['c105'],
                "u105"=>$p['u105'],
                "c106"=>$p['c106'],
                "u106"=>$p['u106'],
                "c107"=>$p['c107'],
                "u107"=>$p['u107'],
                "c108"=>$p['c108'],
                "u108"=>$p['u108'],
                "c109"=>$p['c109'],
                "u109"=>$p['u109'],
                "ala"=>$p['ala'],
                "cadera"=>$p['cadera'],
                "pecho"=>$p['pecho'],
                "pie"=>$p['pie'],
                "filete"=>$p['filete'],
                "cuello"=>$p['cuello'],
                "hueso"=>$p['hueso'],
                "menu"=>$p['menu'],
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
}
