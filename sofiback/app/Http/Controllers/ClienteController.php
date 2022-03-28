<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
//        return DB::select("SELECT * FROM tbclientes WHERE TRIM(CiVend)='".$request->user()->ci."'");
        return DB::select("
        SELECT *,(
        SELECT estado from misvisitas where cliente_id=Cod_Aut AND fecha='".date('Y-m-d')."' LIMIT 1
        ) as tipo,(SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0) as totdeuda 
        ,(SELECT count(*) FROM tbctascobrar WHERE CINIT=tbclientes.Id AND Nrocierre=0 ) as cantdeuda 
        FROM tbclientes 
        WHERE TRIM(CiVend)='".$request->user()->ci."'
        ORDER BY tipo desc
        ");
    }

    public function todosclientes(Request $request)
    {
//        return DB::select("SELECT * FROM tbclientes WHERE TRIM(CiVend)='".$request->user()->ci."'");
        return DB::select("
        SELECT *,(
        SELECT estado from misvisitas where cliente_id=Cod_Aut AND fecha='".date('Y-m-d')."' LIMIT 1
        ) as tipo,(SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0) as totdeuda 
        ,(SELECT count(*) FROM tbctascobrar WHERE CINIT=tbclientes.Id AND Nrocierre=0 ) as cantdeuda 
        FROM tbclientes 
        ORDER BY tipo desc
        ");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }
    public function bloquear(){
        DB::SELECT("UPDATE tbclientes set venta='INACTIVO' 
        where (
            SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda) ) 
            FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and (c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda))>=5 )>7000
        or (SELECT DATEDIFF( curdate(), (select min(c.FechaEntreg) from tbctascobrar c where c.CINIT =tbclientes.Id and c.Nrocierre=0 and (c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda))>=5)))>7 ");
    }

    public function desbloq2(){
        DB::SELECT("UPDATE tbclientes set venta='ACTIVO' 
        where (SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0)<7000
        or (SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0) is null
         ");
    }

    public function desbloquear(Request $request){
        if($request->venta=='ACTIVO')
            $request->venta='INACTIVO';
        else 
            $request->venta='ACTIVO';
        
        DB::SELECT("UPDATE tbclientes set venta='$request->venta' where Cod_Aut=$request->Cod_Aut");
        
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
