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

            $verif=DB::SELECT("SELECT * from entregas where comanda='".$value['comanda']."' and fechaEntreg='".$value['FechaEntreg']."' and estado='ENTREGADO'");
            $verif2=DB::SELECT("SELECT * from entregas where comanda='".$value['comanda']."' and fechaEntreg='".$value['FechaEntreg']."' and estado='NO ENTREGADO'");
            $verif3=DB::SELECT("SELECT * from entregas where comanda='".$value['comanda']."' and fechaEntreg='".$value['FechaEntreg']."' and estado='RECHAZADO'");


            if(sizeof($verif)>0 && $request->estado=='ENTREGADO'){

            }
            elseif (sizeof($verif2)>0 && $request->estado=='NO ENTREGADO'){
            
            }
            elseif(sizeof($verif3)>0 && $request->estado=='RECHAZADO'){
                
            }
            else{
            DB::table("entregas")->insert([
                "cliente_id"=>$request->cliente_id,
                "cinit"=>$request->cinit,
                "comanda"=>$value['comanda'],
                "monto"=>$value['Importe'],
                "tipago"=>$value['Tipago'],
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
                "pago"=>$value['pago']
            ]);}
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

    public function reportEntImp(Request $request){
        return DB::SELECT("SELECT c.comanda,e.Nombres,c.Importe,n.estado,c.Tipago,n.pago 
        from tbclientes e inner join tbctascobrar c on e.Id=c.CINIT LEFT JOIN entregas n on n.comanda=c.comanda 
        where date(c.FechaEntreg)='$request->fecha' and c.placa='$request->placa' 
        and (c.CodAuto, c.comanda) in 
                    (SELECT min(c2.CodAuto) ,c2.comanda
                          from tbctascobrar c2
                          WHERE date(c2.FechaEntreg)='$request->fecha' and c2.placa='$request->placa' group by c2.comanda)
        group by c.comanda,e.Nombres,c.Importe,n.estado,c.Tipago,n.pago  order by c.comanda,n.estado asc;");
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

    public function reporteGrupo(){
        "SELECT gs.Descripcion, count(DISTINCT(cb.CINIT))
FROM tbgrupos gs inner join tbgrupopadre gp on gs.Cod_pdr=gp.cod_grup
inner join tbproductos pr on gp.cod_grup=pr.cod_grup
inner join tbventas vt on vt.cod_pro=pr.cod_prod
inner join tbctascobrar cb on cb.comanda=vt.Comanda
WHERE cb.FechaEntreg='2024-07-06'
group by gs.Descripcion;";
    }

    public function boletaentrega($comanda){
        $pedido=DB::SELECT("SELECT * from tbctascobrar where comanda ='$comanda'")[0];
        $detalle=DB::SELECT("SELECT * from tbventas v inner join tbproductos p on v.cod_pro=p.cod_prod where v.Comanda='$comanda' ");
        $cliente= DB::SELECT("SELECT * from tbclientes where Id=".$pedido->CINIT)[0];
        $personal=DB::SELECT("SELECT * from personal where ci='$pedido->CIFunc'")[0];
        $contenido='';
        foreach ($detalle as $value) {
            $contenido="<tr><th>$value->cant</th><th>$value->cod_prod</th><th>$value->Producto</th><th>$value->codUnid</th><th>P.BRUTO</th><th>CJS</th><th>KG</th><th>P.NETO</th><th>P.UNIT</th><th>TOTAL</th></tr>";

            # code...
        }
        $cadena="<style>
        .titulo{
        text-align:center;
        font-weight: bold;
        }
        </style>
        <div class='titulo'>BOLETA DE ENTREGA</div>
        <hr>
        <table>
        <tr><th>NIT</th><td>".$cliente->Id."</td><th>Telefono</th><td>".$cliente->Telf."</td></tr>
        <tr><th>Cliente</th><td>".$cliente->Nombres."</td><th>FEmision</th><td>".$pedido->FechaCan."</td></tr>
        <tr><th>DIreccion</th><td>".$cliente->Direccion."</td><th></th><td></td></tr>
        <tr><th>Vendedor</th><td>".$personal->Nombre1." ".$personal->Nombre2 ." ".$personal->App1." ". $personal->Apm."</td><th>Nro Pedido</th><td>$comanda</td></tr>
        </table>
        <hr>
        <table>
        <tr><th>CANT</th><th>CODIGO</th><th>CONCEPTO</th><th>UNID</th><th>P.BRUTO</th><th>CJS</th><th>KG</th><th>P.NETO</th><th>P.UNIT</th><th>TOTAL</th></tr>
        </table>
        ";

        return $cadena;

    }
}
