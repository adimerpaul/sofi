<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RutaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

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
       /* return DB::table('tbpedidos')
            ->where('idCli',$request->id)
            ->whereDate('fecha',$request->fecha)
            ->where('tipo','NORMAL')
            ->get();*/
        $resul=[];
        $list= DB::SELECT("SELECT c.CINIT,c.comanda,c.FechaEntreg,c.Importe,c.Tipago,c.Observacion,
        (SELECT e.estado from entregas e where e.cinit=c.CINIT and e.comanda=c.comanda ) estado
        FROM tbctascobrar c WHERE c.CINIT='$request->id' and c.FechaEntreg='$request->fecha'
        group by c.CINIT,c.comanda,c.FechaEntreg,c.Importe,c.Tipago,c.Observacion");

        //return $list;
        foreach ($list as $r) {
            # code...
            //return $rcomanda;
            $prod=DB::SELECT("SELECT p.cod_prod,p.Producto,v.PVentUnit,v.cant,v.Monto from tbventas v inner join tbproductos p on v.cod_pro=p.cod_prod
            where v.Comanda=".$r->comanda);
            $r->detalle=$prod;
            array_push($resul,$r);
        }

        return json_encode($resul);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($fecha, Request $request)
    {
       /* return DB::select("
        SELECT p.idCli,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud,p.estados
FROM tbpedidos p
INNER JOIN tbclientes c ON c.Cod_Aut=p.idCli
WHERE date(p.fecha)='".$fecha."'
GROUP BY p.idCli,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud,p.estados;
");*/
        $user= $request->user();
    return DB::select(" SELECT c.Cod_Aut,p.CINIT,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud,
    (select e.estado from entregas e where e.cliente_id=c.Cod_Aut and e.fechaEntreg='$fecha' order by e.estado asc limit 1  ) estado
    FROM tbctascobrar p
    INNER JOIN tbclientes c ON c.Id=p.CINIT
    WHERE date(p.FechaEntreg)='".$fecha."'
    and p.placa='".$user->placa."'
    GROUP BY c.Cod_Aut,p.CINIT,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud
    ");
    }


    public function listRuta(Request $request)
    {
        $user= $request->user();
    return DB::select(" SELECT c.CINIT,l.Nombres,c.comanda,c.placa,e.despachador,e.estado,e.observacion
    from tbctascobrar c inner join tbclientes l on c.CINIT=l.Id
    left join entregas e on e.comanda=c.comanda where c.FechaEntreg='$request->fecha'
     order by c.comanda;

    ");
    }

    public function listPedidos(Request $request){
        return DB::SELECT("SELECT c.CINIT,l.Nombres,c.comanda,c.Tipago,p.Nombre1,p.App1
          from tbctascobrar c inner join tbclientes l on c.CINIT=l.Id inner join personal p on p.ci=c.CIFunc
        where c.FechaEntreg='$request->fecha' order by c.comanda desc;");
    }

    public function resumenEntrega(Request $request){
        return DB::SELECT("SELECT c.placa,c.fechaEntreg, COUNT(*) total,
        (select count(*) from entregas e
            WHERE e.fechaEntreg=c.FechaEntreg and c.placa=e.placa and e.estado='ENTREGADO') entreg,
        (select count(*) from entregas e
            WHERE e.fechaEntreg=c.FechaEntreg and c.placa=e.placa and e.estado='NO ENTREGADO') noentreg
         from tbctascobrar c where c.FechaEntreg='$request->fecha' and c.placa!='' GROUP by c.placa,c.fechaEntreg ");
    }

    public function reporteDes(Request $request){
        return DB::SELECT("SELECT c.CINIT,l.Nombres,c.comanda,c.Importe,c.placa,e.despachador,c.Tipago,e.observacion
        from tbctascobrar c inner join tbclientes l on c.CINIT=l.Id inner join entregas e on e.comanda=c.comanda
         where c.FechaEntreg='$request->fecha' and e.estado='ENTREGADO' and e.placa='".$request->user()->placa."' order by c.CINIT");
    }
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {

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
