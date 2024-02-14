<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use Illuminate\Http\Request;

class AlmacenController extends Controller{
    public function index(Request $request){
        $fecha = $request->fecha;
        $almacenes = Almacen::whereDate('fecha_registro', $fecha)->get();
        return response()->json($almacenes);
    }
    public function cargarExcel(Request $request){
        //delete records date
        Almacen::whereDate('fecha_registro', date('Y-m-d'))->delete();

        $path = $this->uploadFile($request);
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $data = $spreadsheet->getActiveSheet()->toArray();
        $cantidad = count($data);
        $promedio = $cantidad/ $request->codigo;
        foreach ($data as $key => $value) {
            if ($key > 0) {
                $codigo=$this->obtencionCodigo($promedio,$key);
                $almacen = new Almacen();
                $almacen->codigo = $codigo;
                $almacen->codigo_producto = $value[0];
                $almacen->producto = $value[1];
                $almacen->unidad = $value[2];
                $almacen->saldo = $value[3];
                $almacen->registro = $this->convertfecha($value[4]);
                $almacen->vencimiento = $this->convertfecha($value[5]);
                $almacen->grupo = $value[6];
                $almacen->fecha_registro = date('Y-m-d');
                $almacen->save();
            }
        }
    }
    public function convertfecha($fecha){
        $fecha = explode(' ', $fecha);
        $fecha = $fecha[0];
        error_log('fecha: ' . json_encode($fecha));
        $fecha = explode('/', $fecha);
//        error_log('fecha: ' . json_encode($fecha));
        $mes = strlen($fecha[0]) == 1 ? '0' . $fecha[0] : $fecha[0];
        $dia = strlen($fecha[1]) == 1 ? '0' . $fecha[1] : $fecha[1];
        $fecha = $fecha[2] . '-' . $mes . '-' . $dia;
        return $fecha;
    }
    public function obtencionCodigo($promedio, $key) {
        $codigos = range('A', 'Z');
        for ($i = 0; $i < count($codigos); $i++) {
            if ($key < $promedio * ($i + 1)) {
                return $codigos[$i];
            }
        }
        return end($codigos);
    }

    public function uploadFile(Request $request): string
    {
        $file = $request->file('file');
        $file->move(public_path('uploads'), $file->getClientOriginalName());
        //read excel
        $path = public_path('uploads/' . $file->getClientOriginalName());
        return $path;
    }
}
