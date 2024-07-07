<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrestamoController extends Controller
{
    //
    public function store(Request $request)
    {

        DB::table("prestamos")->insert([
            "cliente_id"=>$request->cliente_id,
            "cinit"=>$request->cinit,
            "despachador"=>$request->user()->Nombre1.' '.$request->user()->App1,
            "personal_id"=>$request->user()->CodAut,
            "placa"=>$request->user()->placa,
            "ingreso"=>$request->ingreso,
            "salida"=>$request->salida,
            "fecha"=>date('Y-m-d'),
            "hora"=>date('H:i:s')
        ]);
    }

    public function rePrestamo2(Request $request){
        return DB::SELECT("SELECT p.cinit,c.Nombres,p.placa,sum(p.ingreso) prestado,sum(p.salida) devuelto
        FROM prestamos p INNER join tbclientes c on p.cliente_id=c.Cod_Aut
        WHERE p.fecha<='$request->ini' and p.fecha>='$request->fin'
        GROUP by p.cinit,p.placa,c.Nombres;");
    }

    public function rePrestamo(Request $request){
        return DB::select("SELECT p.fecha,p.cinit,c.Nombres,p.ingreso prestado,p.salida devuelto
        FROM prestamos p INNER join tbclientes c on p.cliente_id=c.Cod_Aut
        WHERE p.fecha='$request->fecha' and p.placa='$request->placa'
        order by p.fecha,p.cinit;");
    }

}
