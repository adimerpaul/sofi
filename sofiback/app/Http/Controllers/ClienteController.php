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
//        return DB::select("
//        SELECT *,(
//        SELECT estado from misvisitas where cliente_id=Cod_Aut AND fecha='".date('Y-m-d')."' LIMIT 1
//        ) as tipo,(SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0) as totdeuda
//        ,(SELECT count(*) FROM tbctascobrar WHERE CINIT=tbclientes.Id AND Nrocierre=0 ) as cantdeuda
//        FROM tbclientes
//        WHERE TRIM(CiVend)='".$request->user()->ci."'
//        ORDER BY tipo desc
//        ");
        return DB::select(
            "SELECT *,
            (SELECT estado from misvisitas where cliente_id=Cod_Aut AND fecha='".date('Y-m-d')."' LIMIT 1) as tipo,
            (SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0) as totdeuda ,
            (SELECT MIN(c.FechaEntreg) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0) as fechaminima ,
            (SELECT count(*) FROM tbctascobrar WHERE CINIT=tbclientes.Id AND Nrocierre=0 and Acuenta=0) as cantdeuda
             FROM tbclientes
             WHERE TRIM(CiVend)='".$request->user()->ci."'
             ORDER BY tipo desc;"
        );
    }

    public function todosclientes(Request $request)
    {
//        return DB::select("SELECT * FROM tbclientes WHERE TRIM(CiVend)='".$request->user()->ci."'");
        return DB::select("
        SELECT *,(SELECT estado from misvisitas where cliente_id=Cod_Aut AND fecha='".date('Y-m-d')."' LIMIT 1) as tipo,
        (SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) 
        FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0) as totdeuda
        ,(SELECT count(*) FROM tbctascobrar WHERE CINIT=tbclientes.Id AND Nrocierre=0  and Acuenta=0) as cantdeuda
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
        where Id not in ('3501059017','3544875019','387115028','2773242015','2762953013','4034692','8560810','3513987','168266022','341104028','5068381') and
          ((SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda) )
            FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0 and (c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda))>=5 )>7000
        or (SELECT DATEDIFF( curdate(), (select min(c.FechaEntreg) from tbctascobrar c where c.CINIT =tbclientes.Id and c.Nrocierre=0 and Acuenta=0 and (c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda))>=5)))>5 )");
    }

    public function desbloq2(){
        DB::SELECT("UPDATE tbclientes set venta='ACTIVO'
        where (SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0)<7000
        or (SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0) is null
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

    public function listapersonal(){
        return DB::SELECT("SELECT * from personal");
    }

    public function listaclientes(){
        return DB::SELECT("SELECT * from tbclientes c inner join personal p on c.CiVend=p.ci");
    }

    public function modprevent(Request $request){
        DB::SELECT("UPDATE tbclientes set CiVend='$request->vendedor' where Cod_Aut=$request->cliente_id");
        return $request;
    }
}
