<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
//        return DB::select("SELECT p.cod_prod,p.Producto,p.Precio,p.codUnid,p.tipo,SUM(s.cant) cantidad FROM tbproductos p
//        INNER JOIN tbstock s ON s.cod_prod=p.cod_prod
//        GROUP BY p.cod_prod,p.Producto,p.Precio,p.codUnid,p.tipo");
        return DB::select("SELECT cod_prod,Producto,Precio,codUnid,tipo,0 cantidad FROM `tbproductos` ");
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
