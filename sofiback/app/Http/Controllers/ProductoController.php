<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller{
    public function index(){
//        return DB::SELECT("SELECT p.cod_prod,p.Producto,p.Precio,p.codUnid,p.tipo, (select (SUM(s.cant)-SUM(s.saldo)) from tbstock s where s.cod_prod=p.cod_prod group by p.cod_prod) as cantidad from tbproductos p");
        $productos = Producto::select([
            'cod_prod',
            'Producto',
            'Precio',
            'codUnid',
            'tipo',
            DB::raw('(SELECT SUM(s.cant - s.saldo) FROM tbstock s WHERE s.cod_prod = tbproductos.cod_prod) as cantidad')
        ])
            ->orderByDesc('cantidad')
            ->get();

        return $productos;
    }

    public function listProducto(){
        return DB::select("SELECT p.cod_prod,p.Producto,p.Precio,p.Precio_Costo,p.Precio3,p.Precio4,p.Precio5,p.Precio6,p.Precio7,p.Precio8,p.Precio9,p.Precio10,p.Precio11,p.Precio12,p.Precio13,p.PreCosto,
        p.codUnid,p.tipo,SUM(s.cant) cantidad
        FROM tbproductos p
        INNER JOIN tbstock s ON s.cod_prod=p.cod_prod
        GROUP BY p.cod_prod,p.Producto,p.Precio,p.Precio_Costo,p.Precio3,p.Precio4,p.Precio5,p.Precio6,p.Precio7,p.Precio8,p.Precio9,p.Precio10,p.Precio11,p.Precio12,p.Precio13,p.PreCosto,p.codUnid,p.tipo");
    }

    public function verProducto(){
        return DB::select("SELECT p.cod_prod,p.Producto,p.Precio,p.Precio_Costo,p.Precio3,p.Precio4,p.Precio5,p.Precio6,p.Precio7,p.Precio8,p.Precio9,p.Precio10,p.Precio11,p.Precio12,p.Precio13,p.PreCosto,
        p.codUnid,p.tipo
        FROM tbproductos p
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
