<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsignarController extends Controller
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
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }
    public function store(Request $request)
    {
        //
        //return $request;
        foreach ($request->clientes as $row) {

            DB::table('asignar')->insert([
                    ['cliente_id'=>$row->cliente->Cod_Aut],
                    ['usuario_id'=>$request->user_id],
                    ['fecha'=>$request->fecha],
                    ['estado'=>'PENDIENTE'],
                    ['hora'=>'00:00:00'],
                    ['lat'=>$row->cliente['Latitud']],
                    ['lng'=>$row->cliente['longitud']],
            ]);
        }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($fecha)
    {
        return DB::select("SELECT * FROM asignar a
INNER JOIN tbclientes c ON a.cliente_id=c.Cod_Aut
INNER JOIN personal u ON a.usuario_id=u.CodAut
WHERE fecha='$fecha'");
    }
    public function misasignaciones(Request $request)
    {
        return DB::select("SELECT * FROM asignar a
INNER JOIN tbclientes c ON a.cliente_id=c.Cod_Aut
INNER JOIN personal u ON a.usuario_id=u.CodAut
WHERE fecha='$request->fecha' AND
u.CodAut='".$request->user()->CodAut."'");
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
        DB::select("DELETE FROM asignar WHERE id=$id");
    }
}
