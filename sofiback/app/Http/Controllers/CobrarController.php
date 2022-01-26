<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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

    public function deudas($ci){
        return DB::SELECT("
            SELECT * FROM tbctascobrar c
            INNER JOIN tbclientes cli ON cli.Id=c.CINIT
            INNER JOIN tbventas v ON c.comanda=v.Comanda
            INNER JOIN tbproductos p ON p.cod_prod=v.cod_pro
            WHERE c.CINIT='$ci' AND c.Nrocierre=0
            ORDER BY c.comanda");
        
    }

    public function deudas2($ci){
        return DB::SELECT("
            SELECT * FROM tbctascobrar c
            INNER JOIN tbclientes cli ON cli.Id=c.CINIT
            WHERE c.CINIT='$ci' AND c.Nrocierre=0
            ORDER BY c.comanda");
        
    }

    public function pagado($ci,$monto){

        //$CIfunc=$_SESSION['CodAut'];

        DB::table('tbctascow')->insert([
        ['idCli'=>$ci],
        ['pago'=>$monto],
        ['CIfunc'=>$CIfunc],
        ['fecha'=>date("Y-m-d H:i:s")]
        ]);

        $query=DB::SELECT(" SELECT * FROM tbctascobrar c
        INNER JOIN tbclientes cli ON cli.Id=c.CINIT
        WHERE c.CINIT='$ci' AND  c.Nrocierre=0
        ORDER BY c.comanda");

        foreach ($query as $row){
            $saldo=floatval($row->Importe) - floatval($row->Acuenta);

            if($monto>0 && $saldo!=0){
                if ($monto>$saldo){
                    $this->db->query("UPDATE tbctascobrar SET Acuenta=Importe WHERE codAuto='$row->CodAuto'");
                    $monto=$monto-$saldo;
                }else{
                    $this->db->query("UPDATE tbctascobrar SET Acuenta=Acuenta+$monto WHERE codAuto='$row->CodAuto'");
                    $monto=0;
                }
            }
        }
        echo 1;
    }
    public function insertcobro(){
        $ci=$_POST['ci'];
        $monto=$_POST['monto'];
        $CIfunc=$_SESSION['CodAut'];

        $query=$this->db->query("
SELECT * FROM tbctascobrar c
WHERE c.CINIT='$ci' AND c.Nrocierre=0");
        foreach ($query->result() as $row){
            if (isset($_POST['id'.$row->CodAuto]) && $_POST['id'.$row->CodAuto]!='' && $_POST['id'.$row->CodAuto]!=null){
                $monto=$_POST['id'.$row->CodAuto];
                $comanda=$row->CodAuto;
                        $this->db->query("INSERT INTO tbctascow 
                        SET 
                        pago='$monto',
                        idCli='$ci',
                        CIfunc='$CIfunc',
                         fecha='".date("Y-m-d H:i:s")."',
                         comanda='$comanda'
                        ");
            }
        }
        header('Location: '.base_url().'Cobrar');
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
