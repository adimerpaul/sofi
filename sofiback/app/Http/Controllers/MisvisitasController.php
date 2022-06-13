<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MisvisitasController extends Controller
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
//        return  $request;
        if ($request->id==0){
            return DB::select("SELECT estado,COUNT(*) cantidad FROM misvisitas WHERE date(fecha)='".$request->fecha."' GROUP BY estado ORDER BY estado");
        }else{
            return DB::select("SELECT estado,COUNT(*) cantidad FROM misvisitas WHERE date(fecha)='".$request->fecha."' AND personal_id='".$request->id."' GROUP BY estado ORDER BY estado");
        }
    }

    public function listvisita(Request $request){
        if ($request->id==0){
            return DB::SELECT("select * from misvisitas v inner join tbclientes c on v.cliente_id=c.Cod_Aut inner join personal on v.personal_id=CodAut where date(fecha)='".$request->fecha."' order by v.id");}
        else{
            return DB::SELECT("select * from misvisitas v inner join tbclientes c on v.cliente_id=c.Cod_Aut inner join personal on v.personal_id=CodAut where date(fecha)='".$request->fecha."' AND personal_id='".$request->id."' order by v.id");}
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
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
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
