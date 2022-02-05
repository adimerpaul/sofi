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
        return DB::SELECT("SELECT * from tbclientes where id in(select CINIT from tbctascobrar where Importe!=Acuenta and Nrocierre=0)");
        
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

    public function cxcobrar($ci){
        return DB::SELECT("
            SELECT *,(Importe - Acuenta) as saldo FROM tbctascobrar 
            WHERE CINIT='$ci' AND Nrocierre=0
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
                if ($monto>$saldo){
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

    public function insertcobro(Request $request){

        foreach ($request->pagos as $row){
                //return $row['pago'];
            DB::table('tbctascow')->insert([
                         'comanda'=>$row['comanda'],
                         'pago'=>floatval($row['pago']),
                        'idCli'=>$row['CINIT'],
                        'CiFunc'=>$row['CIFunc'],
                         'fecha'=>date("Y-m-d H:i:s"),
                         'estado'=>'CREADO',
                         'procesado'=>0
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
}
