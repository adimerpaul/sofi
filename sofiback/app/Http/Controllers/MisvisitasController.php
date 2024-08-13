<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MisvisitasController extends Controller
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
//        return  $request;
        if ($request->id==0){
            return DB::select("SELECT estado,COUNT(*) cantidad FROM misvisitas WHERE date(fecha)='".$request->fecha."' GROUP BY estado ORDER BY estado");
        }else{
            return DB::select("SELECT estado,COUNT(*) cantidad FROM misvisitas WHERE date(fecha)='".$request->fecha."' AND personal_id='".$request->id."' GROUP BY estado ORDER BY estado");
        }
    }

    public function listvisita(Request $request){
        if ($request->id==0){
            return DB::SELECT("select * from misvisitas v inner join tbclientes c on v.cliente_id=c.Cod_Aut inner join personal on v.personal_id=CodAut where date(fecha)='".$request->fecha."' order by v.id");}
        else{
            return DB::SELECT("select * from misvisitas v inner join tbclientes c on v.cliente_id=c.Cod_Aut inner join personal on v.personal_id=CodAut where date(fecha)='".$request->fecha."' AND personal_id='".$request->id."' order by v.id");}
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

    public function listClientePrev(Request $request){
        $user=
        $numdia=date('w',strtotime($request->fecha));

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
        return  DB::select(
            "SELECT *,
            (SELECT m.estado from misvisitas m where m.id=(SELECT max(m2.id) from misvisitas m2 where m2.cliente_id=c.Cod_Aut AND m2.fecha='".$request->fecha."' )) as tipo
             FROM tbclientes c
             WHERE TRIM(c.CiVend)='".$request->user()->ci."' " .$filtro." Order by tipo desc");
    }

    public function pedidoVenta(Request $request)
    {
        
        return DB::select("SELECT estado,COUNT(*) cantidad FROM misvisitas WHERE date(fecha)='".$request->fecha."' AND personal_id='".$request->user()->CodAut."' GROUP BY estado ORDER BY estado");
    }

    public function reportEntregVend(Request $request){
        $resul=[];
        $list= DB::SELECT("SELECT e.hora,e.placa,p.CINIT,c.Id,c.Nombres,p.comanda, 
            (select e.estado from entregas e where e.cliente_id=c.Cod_Aut and e.fechaEntreg='$request->fecha' order by e.estado asc limit 1 ) estado
             FROM tbctascobrar p INNER JOIN tbclientes c ON c.Id=p.CINIT 
             left join entregas e on e.comanda=p.comanda 
        WHERE date(p.FechaEntreg)='$request->fecha' and p.CIFunc='".$request->user()->ci."' 
        GROUP BY e.hora,e.placa,c.Cod_Aut,p.CINIT,c.Id,c.Nombres,p.comanda 
        ORDER BY 
    CASE 
        WHEN e.hora IS NULL OR e.hora = '' THEN 1
        ELSE 0
    END, 
    e.hora ASC;");

        foreach ($list as $r) {
            # code...
            $prod=DB::SELECT("SELECT p.cod_prod,p.Producto,v.PVentUnit,v.cant,v.Monto from tbventas v inner join tbproductos p on v.cod_pro=p.cod_prod
            where v.Comanda=".$r->comanda);
            $r->detalle=$prod;
            array_push($resul,$r);
        }

        return json_encode($resul);
    }
}
