<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminController extends Controller
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
    public function product(Request $request){

            $query=DB::SELECT("
         SELECT t.CodAut,t.Producto,t.TipPro,t.codUnid,t.Peso,s.Saldo Cant,t.Precio,t.Precio_Costo,
         t.Precio3,t.Precio4,t.Precio5,t.Precio6,t.Precio7,t.Precio8,t.Precio9,Precio10,Precio11,t.Precio12,
         gp.Descripcion
         FROM tbproductos t INNER JOIN tbstockm s ON s.cod_prod=t.cod_prod
         INNER JOIN tbgrupos g ON t.cod_grup=g.Cod_grup
         INNER JOIN tbgrupopadre gp ON g.Cod_pdr=gp.cod_grup
         WHERE t.cod_prod='$request->id'");
//            if ($query->num_rows()==0){
//                echo "-1";
//            }
        return $query;
    }

    public function pedido(Request $request ){
        //        echo $_POST['idcliente'];
        //        exit;
        //        $query=$this->db->query();
                $query=DB::SELECT("SELECT max(NroPed) as maximo FROM tbpedidos");
                $row=$query[0];
                $numpedido=intval($row->maximo) + 1;
                //var_dump($_POST);
                //exit;
                $CIfunc=$_SESSION['CodAut'];

                $producto=DB::SELECT("SELECT * FROM tbproductos");
                foreach ($producto as $row) {

                        DB::table('tbpedidos')->insert([
                        ['NroPed'=>$numpedido],
                        ['cod_prod'=>trim($row->cod_prod)],
                        ['CIfunc'=>$CIfunc],
                        [ 'idCli'=>$request->idcliente],
                        [ 'impreso'=>'0'],
                        [ 'pagado'=>'0'],
                        [ 'Cant'=>$row->cantidad],
                        [ 'Tipo1'=>$row->t1],
                        [ 'Tipo2'=>$row->t2],
                        [ 'precio'=>$row->precio],
                        [ 'subtotal'=>$row->subtotal],
                        [ 'Canttxt'=>$row->extra],
                        [ 'fecha'=>date("Y-m-d H:i:s")]
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
