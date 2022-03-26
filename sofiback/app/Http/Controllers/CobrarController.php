<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class CobrarController extends Controller
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
    public function listdeudores(){
        return DB::SELECT("
        select t1.CINIT Id,(SELECT Nombres from tbclientes c where c.Id=t1.CINIT) as Nombres,sum(t1.Importe - (select sum(Acuenta)
         from tbctascobrar t2 where t2.comanda=t1.comanda)) deuda from tbctascobrar t1 where Nrocierre=0  
        group by t1.CINIT");
    }

    public function deudas($ci){
        return DB::SELECT("
            SELECT * FROM tbctascobrar c
            INNER JOIN tbclientes cli ON cli.Id=c.CINIT
            INNER JOIN tbventas v ON c.comanda=v.Comanda
            INNER JOIN tbproductos p ON p.cod_prod=v.cod_pro
            WHERE c.CINIT='$ci' AND c.Nrocierre=0
            ORDER BY c.comanda");

    }

    public function deudastodo(){
        return DB::SELECT("
            SELECT * FROM tbctascobrar c
            INNER JOIN tbclientes cli ON cli.Id=c.CINIT
            INNER JOIN tbventas v ON c.comanda=v.Comanda
            INNER JOIN tbproductos p ON p.cod_prod=v.cod_pro
            WHERE c.Nrocierre=0
            group by c.comanda
            ORDER BY c.comanda");

    }

    public function cxcobrar($ci){
        return DB::SELECT("
            SELECT t1.FechaEntreg,t1.comanda,t1.CINIT,t1.CIFunc, sum(t1.Importe - (select sum(Acuenta) from tbctascobrar t2 where t2.comanda=t1.comanda)) saldo
            FROM tbctascobrar t1
            WHERE t1.CINIT='$ci'
            group by t1.comanda	,t1.CINIT,t1.CIFunc,	t1.FechaEntreg
            ORDER BY comanda");

    }

    public function pagado(Request $request){

        //$CIfunc=$_SESSION['CodAut'];

        DB::table('tbctascow')->insert([
        'idCli'=>$request->ci,
        'pago'=>$request->monto,
        'CIfunc'=>$request->CIfunc,
        'fecha'=>date("Y-m-d H:i:s")
        ]);

        $query=DB::SELECT(" SELECT * FROM tbctascobrar c
        INNER JOIN tbclientes cli ON cli.Id=c.CINIT
        WHERE c.CINIT='$request->ci' AND  c.Nrocierre=0
        ORDER BY c.comanda");

        foreach ($query as $row){
            $saldo=floatval($row->Importe) - floatval($row->Acuenta);

            if($monto>0 && $saldo!=0){
                if ($monto>=$saldo){
                    DB::table('tbctascobrar')->where('codAuto',$row->CodAuto)->update(['Acuenta'=>'Importe']);
                    $monto=$monto-$saldo;
                }else{
                    DB::table('tbctascobrar')->where('codAuto',$row->CodAuto)->update(['Acuenta'=>Acuenta + $monto]);
                    $monto=0;
                }
            }
        }
        return true;
    }

    public function miscobros(Request $request ){
        //return $request;
        //return "SELECT *,(select Nombres from tbclientes where id=idcli) as Nombres from tbctascow where trim(CIFunc)='".$request->user()->ci."' and date(fecha)>='$request->fecha1' and date(fecha)<='$request->fecha2'";
        return DB::SELECT("SELECT *,(select Nombres from tbclientes where id=idcli) as Nombres from tbctascow where trim(CIFunc)='".$request->user()->ci."' and date(fecha)>='$request->fecha1' and date(fecha)<='$request->fecha2'");
    }

    public function impcobros(Request $request ){
        $datos= DB::SELECT("SELECT *,(select Nombres from tbclientes where id=idcli) as Nombres from tbctascow where trim(CIFunc)='".$request->user()->ci."' and date(fecha)>='$request->fecha1' and date(fecha)<='$request->fecha2'");
        $total=0;
        $cadena="
        <style>
        table, th, td {
            border: 1px solid;
          }
        table {
            border-collapse: collapse;
            width:100%;
          }
          </style>
        <div>NOMBRE:". $request->user()->Nombre1 ." ".$request->user()->Nombre2 ." ".$request->user()->App1 ." ".$request->user()->Apm ."</div>
        <div>FECHA: $request->fecha1 al $request->fecha2</div>
        <table>
        <tr>
        <th>FECHA</th>
        <th>No NOTA</th>
        <th>NOMBRE</th>
        <th>MONTO</th>
        <th>N BOLETA</th>
        </tr>";
        foreach ($datos as $r) {
            $total+=$r->pago;
            $cadena.="<tr>
            <td>$r->fecomanda</td>
            <td>$r->comanda</td>
            <td>$r->Nombres</td>
            <td>$r->pago</td>
            
            <td>$r->nboleta</td>
            </tr>";
        }
        $cadena.="
        </table>
        <div>TOTAL: $total</div>
        ";
        return $cadena;

    }


    public function insertcobro(Request $request){
//        return $request;
        foreach ($request->pagos as $row){
                //return $row['pago'];
            DB::table('tbctascow')->insert([
             'comanda'=>$row['comanda'],
             'pago'=>floatval($row['pago']),
            'idCli'=>$row['CINIT'],
            'CiFunc'=>$request->user()->ci,
             'fecha'=>date("Y-m-d H:i:s"),
             'estado'=>'CREADO',
             'procesado'=>0,
             'nboleta'=>$row['boleta'],
             'fecomanda'=>$row['FechaEntreg']
            ]);
        }
    }

    public function store(Request $request)
    {
        //
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

    public function verificar(Request $request){
        $cont="(SELECT w.comanda,w.pago as Acuenta,0 as Importe,99999 as Nrocierre,idCli as CINIT  FROM tbctascow w WHERE date(w.fecha)>='$request->fecha1' and date(w.fecha)<='$request->fecha2' union
        select t.comanda,t.Importe,t.Acuenta,Nrocierre,CINIT from tbctascobrar t )";

        DB::SELECT("UPDATE tbclientes set venta='ACTIVO' 
        where (SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from $cont c2 where c2.comanda=c.comanda)) FROM $cont c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0)<7000
        or (SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from $cont c2 where c2.comanda=c.comanda)) FROM $cont c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0) is null
         ");
        
    }

  //  codAut	comanda	 CiFunc idCli                          pago	nboleta		fecha	     estado	    procesado		fecomanda	
    //CodAuto   comanda	 CIFunc	CINIT	CiCajero	Importe	Acuenta	Nrocierre	FechaCan	Nroficha	FechaEntreg	codcli	
}
