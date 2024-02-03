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
        $list= DB::SELECT("SELECT c.comanda,c.FechaEntreg,c.Importe,c.Tipago,c.Observacion FROM tbctascobrar c WHERE c.CINIT='$request->id' and c.FechaEntreg='$request->fecha'");
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
    public function show($fecha)
    {
       /* return DB::select("
        SELECT p.idCli,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud,p.estados
FROM tbpedidos p
INNER JOIN tbclientes c ON c.Cod_Aut=p.idCli
WHERE date(p.fecha)='".$fecha."'
GROUP BY p.idCli,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud,p.estados;
");*/
    return DB::select(" SELECT p.CINIT,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud
    FROM tbctascobrar p
    INNER JOIN tbclientes c ON c.Id=p.CINIT
    WHERE date(p.FechaEntreg)='".$fecha."'
    GROUP BY p.CINIT,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud
    ");  
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
