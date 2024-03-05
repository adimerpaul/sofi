<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntregaController extends Controller
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
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
//        return $request;
         /*DB::table('tbpedidos')
             ->where('idCli',$request->cliente_id)
             ->whereDate('fecha',$request->fecha)
             ->update([
                "estados"=>$request->estado
             ]);*/

        $cliente=DB::select("SELECT * FROM tbclientes WHERE Cod_Aut='".$request->cliente_id."'");
        //return $cliente;
            $verif=DB::SELECT("SELECT * from entregas where comanda='$request->comanda'
            and fechaEntreg='$request->fechaEntreg' and estado='ENTREGADO'");
            if(sizeof($verif)>0){
                return false;
        }
        $distancia=$this->distance( floatval( $request->lat),floatval($request->lng),floatval($cliente[0]->Latitud),floatval($cliente[0]->longitud));
        DB::table("entregas")->insert([
            "cliente_id"=>$request->cliente_id,
            "cinit"=>$request->cinit,
            "comanda"=>$request->comanda,
            "monto"=>$request->monto,
            "despachador"=>$request->user()->Nombre1.' '.$request->user()->App1,
            "personal_id"=>$request->user()->CodAut,
            "placa"=>$request->user()->placa,
            "lat"=>$request->lat,
            "lng"=>$request->lng,
            "estado"=>$request->estado,
            "observacion"=>$request->observacion,
            "fechaEntreg"=>$request->fechaEntreg,
            "fecha"=>date('Y-m-d'),
            "hora"=>date('H:i:s'),
            "distancia"=>$distancia,
        ]);
    }

    public function regTodo(Request $request)
    {
        $cliente=DB::select("SELECT * FROM tbclientes WHERE Cod_Aut='".$request->cliente_id."'");
        //return $cliente;

        $distancia=$this->distance( floatval( $request->lat),floatval($request->lng),floatval($cliente[0]->Latitud),floatval($cliente[0]->longitud));

        foreach ($request->listado as $value) {
            # code...
            //return $value['comanda'];
            $verif=DB::SELECT("SELECT * from entregas where comanda='".$value['comanda']."'
            and fechaEntreg='".$value['FechaEntreg']."' and estado='ENTREGADO'");
            if(sizeof($verif)>0){
                return false;
            }
            DB::table("entregas")->insert([
                "cliente_id"=>$request->cliente_id,
                "cinit"=>$request->cinit,
                "comanda"=>$value['comanda'],
                "monto"=>$value['Importe'],
                "despachador"=>$request->user()->Nombre1.' '.$request->user()->App1,
                "personal_id"=>$request->user()->CodAut,
                "placa"=>$request->user()->placa,
                "lat"=>$request->lat,
                "lng"=>$request->lng,
                "estado"=>$request->estado,
                "observacion"=>$request->observacion,
                "fechaEntreg"=>$value['FechaEntreg'],
                "fecha"=>date('Y-m-d'),
                "hora"=>date('H:i:s'),
                "distancia"=>$distancia,
            ]);
        }

    }

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

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($fecha)
    {
        //
        return DB::SELECT("SELECT * from tbclientes c inner join entregas e on c.Cod_Aut=e.cliente_id
         where e.fecha='$fecha'");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
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
        //
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
