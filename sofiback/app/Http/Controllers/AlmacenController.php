<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use Illuminate\Http\Request;

class AlmacenController extends Controller{
    public function index(Request $request){
        $fecha = $request->fecha;
        $almacenes = Almacen::whereBetween('fecha_registro',[$fecha.' 00:00:00',$fecha.' 23:59:59'])->get();
        return response()->json($almacenes);
    }
}
