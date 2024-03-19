<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\RegistroAlmacen;
use App\Models\User;
use Illuminate\Http\Request;

class AlmacenController extends Controller{
    public function porcentaje(Request $request){
        $porcentajeAvancadoCodigo = $this->porcentajeAvanceCodigo($request);
        return response()->json($porcentajeAvancadoCodigo);

    }
    public function porcentajeAvanceCodigo(Request $request){
        $fecha = $request->fecha;
        $almacenes = Almacen::selectRaw('codigo, count(*) as total')
            ->whereDate('fecha_registro', $fecha)
            ->groupBy('codigo')
            ->get();
        foreach ($almacenes as $almacen) {
            $realizado = Almacen::whereDate('fecha_registro', $fecha)
                ->where('codigo', $almacen->codigo)
                ->where('se_descargo', 'EXPORTADO')
                ->count();
            $almacen->realizado = $realizado;
            $almacen->porcentaje = round(($realizado / $almacen->total) * 100, 2);
        }
        return $almacenes;
    }
    public function registros(Request $request){
        $almacenes = RegistroAlmacen::where('almacen_id', $request->almacen_id)
            ->with('user')
            ->get();
        return response()->json($almacenes);
    }
    public function index(Request $request){
        $fecha = $request->fecha;
//        $porcentaje = $this->porcentajeImportado($request);
        $porcentaje = 0;
        $almacenes = Almacen::whereDate('fecha_registro', $fecha)->with('registros')->get();
//        $almacenes = $almacenes->map(function ($almacen) {
//            $almacen->cantidad1 = count($almacen->registros) > 0 ? $almacen->registros[0]->cantidad : 0;
//            $almacen->cantidad2 = count($almacen->registros) > 1 ? $almacen->registros[1]->cantidad : 0;
//            $almacen->cantidad3 = count($almacen->registros) > 2 ? $almacen->registros[2]->cantidad : 0;
//            $almacen->cantidad4 = count($almacen->registros) > 3 ? $almacen->registros[3]->cantidad : 0;
//            $almacen->cantidad5 = count($almacen->registros) > 4 ? $almacen->registros[4]->cantidad : 0;
//            $almacen->cantidad6 = count($almacen->registros) > 5 ? $almacen->registros[5]->cantidad : 0;
//            $almacen->cantidad7 = count($almacen->registros) > 6 ? $almacen->registros[6]->cantidad : 0;
//            $almacen->cantidad8 = count($almacen->registros) > 7 ? $almacen->registros[7]->cantidad : 0;
//            $almacen->cantidad9 = count($almacen->registros) > 8 ? $almacen->registros[8]->cantidad : 0;
//            $almacen->cantidad10 = count($almacen->registros) > 9 ? $almacen->registros[9]->cantidad : 0;
//        });
        foreach ($almacenes as $almacen) {
            $almacen->cantidad1 = count($almacen->registros) > 0 ? $almacen->registros[0]->cantidad : 0;
            $almacen->cantidad2 = count($almacen->registros) > 1 ? $almacen->registros[1]->cantidad : 0;
            $almacen->cantidad3 = count($almacen->registros) > 2 ? $almacen->registros[2]->cantidad : 0;
            $almacen->cantidad4 = count($almacen->registros) > 3 ? $almacen->registros[3]->cantidad : 0;
            $almacen->cantidad5 = count($almacen->registros) > 4 ? $almacen->registros[4]->cantidad : 0;
            $almacen->cantidad6 = count($almacen->registros) > 5 ? $almacen->registros[5]->cantidad : 0;
            $almacen->cantidad7 = count($almacen->registros) > 6 ? $almacen->registros[6]->cantidad : 0;
            $almacen->cantidad8 = count($almacen->registros) > 7 ? $almacen->registros[7]->cantidad : 0;
            $almacen->cantidad9 = count($almacen->registros) > 8 ? $almacen->registros[8]->cantidad : 0;
            $almacen->cantidad10 = count($almacen->registros) > 9 ? $almacen->registros[9]->cantidad : 0;
            $almacen->fechaVencimiento1 = count($almacen->registros) > 0 ? $almacen->registros[0]->fecha_vencimiento : null;
            $almacen->fechaVencimiento2 = count($almacen->registros) > 1 ? $almacen->registros[1]->fecha_vencimiento : null;
            $almacen->fechaVencimiento3 = count($almacen->registros) > 2 ? $almacen->registros[2]->fecha_vencimiento : null;
            $almacen->fechaVencimiento4 = count($almacen->registros) > 3 ? $almacen->registros[3]->fecha_vencimiento : null;
            $almacen->fechaVencimiento5 = count($almacen->registros) > 4 ? $almacen->registros[4]->fecha_vencimiento : null;
            $almacen->fechaVencimiento6 = count($almacen->registros) > 5 ? $almacen->registros[5]->fecha_vencimiento : null;
            $almacen->fechaVencimiento7 = count($almacen->registros) > 6 ? $almacen->registros[6]->fecha_vencimiento : null;
            $almacen->fechaVencimiento8 = count($almacen->registros) > 7 ? $almacen->registros[7]->fecha_vencimiento : null;
            $almacen->fechaVencimiento9 = count($almacen->registros) > 8 ? $almacen->registros[8]->fecha_vencimiento : null;
            $almacen->fechaVencimiento10 = count($almacen->registros) > 9 ? $almacen->registros[9]->fecha_vencimiento : null;
        }
        return response()->json(['almacenes' => $almacenes, 'porcentaje' => $porcentaje]);
    }
    public function porcentajeImportado(Request $request){
        $fecha = $request->fecha;
        $almacenes = Almacen::whereDate('fecha_registro', $fecha)->get();
        $total = count($almacenes);
        $importado = 0;
        foreach ($almacenes as $almacen) {
            if ($almacen->se_descargo == 'IMPORTADO') {
                $importado++;
            }
        }
        $porcentaje = $total == 0 ? 0 : ($importado / $total) * 100;
        return round($porcentaje, 2);
    }
    public function porcentajeAvance(Request $request){
        $fecha = $request->fecha;
        $almacenes = Almacen::whereDate('fecha_registro', $fecha)->get();
        $total = count($almacenes);
        $realizado = 0;
        foreach ($almacenes as $almacen) {
            if ($almacen->se_descargo == 'REALIZADO') {
                $realizado++;
            }
        }
        $porcentaje = $total == 0 ? 0 : ($realizado / $total) * 100;
        return round($porcentaje, 2);
    }
    public function cargarExcel(Request $request){
        //delete records date
        Almacen::whereDate('fecha_registro', date('Y-m-d'))->delete();

        $path = $this->uploadFile($request);
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $data = $spreadsheet->getActiveSheet()->toArray();
//        $cantidad = count($data);
//        $promedio = $cantidad/ $request->codigo;
        $insertAlmacen = [];
        foreach ($data as $key => $value) {
            if ($key > 0) {
//                $codigo=$this->obtencionCodigo($promedio,$key);
                $insertAlmacen[] = [
                    'codigo' =>  $value[7],
                    'codigo_producto' => $value[0],
                    'producto' => $value[1],
                    'unidad' => $value[2],
                    'saldo' => $value[3],
                    'registro' => $this->convertfecha($value[4]),
                    'vencimiento' => $this->convertfecha($value[5]),
                    'grupo' => $value[6],
                    'fecha_registro' => $request->fecha
                ];
//                $codigo=$this->obtencionCodigo($promedio,$key);
//                $almacen = new Almacen();
//                $almacen->codigo = $codigo;
//                $almacen->codigo_producto = $value[0];
//                $almacen->producto = $value[1];
//                $almacen->unidad = $value[2];
//                $almacen->saldo = $value[3];
//                $almacen->registro = $this->convertfecha($value[4]);
//                $almacen->vencimiento = $this->convertfecha($value[5]);
//                $almacen->grupo = $value[6];
//                $almacen->fecha_registro = $request->fecha
//                $almacen->save();
            }
        }
        Almacen::insert($insertAlmacen);
    }
    public function importData(Request $request)
    {
        $fecha = $this->convertDMY($request->fecha);
        $almacenes = Almacen::whereDate('fecha_registro', $fecha)
            ->where('codigo', $request->codigo)
            ->get();
        foreach ($almacenes as $almacen) {
            $almacen->se_descargo = 'IMPORTADO';
            $almacen->save();
        }
        return response()->json($almacenes);
    }
    public function exportData(Request $request)
    {
        $data = json_decode($request->input('almacen'));
        $user_id = json_decode($request->input('user'));
        error_log('data: ' . json_encode($data));
        $user_id = json_encode($user_id);
        $insertRegistroAlmacen = [];
        $updateAlmacen = [];
        foreach ($data as $almacenData) {
            $detalle = $almacenData->detalle;
            RegistroAlmacen::where('almacen_id', $almacenData->id)->delete();
            $cantidad = 0;
            foreach ($detalle as $item) {
//                error_log('almacenData: ' . json_encode($almacenData));
                if ($almacenData->estado == 'REALIZADO') {
//                    error_log('item: ' . json_encode($item));
                    $insertRegistroAlmacen[] = [
                        'cantidad' => $item->cantidad==''?0:$item->cantidad,
                        'fecha_vencimiento' => $item->vencimiento==''?null:substr($item->vencimiento,0,10),
                        'lote' => $item->lote=='null'?'':$item->lote, //cambiar 'null' por '
                        'comentario' => $item->comentario=='null'?'':$item->comentario, //cambiar 'null' por '
                        'almacen_id' => $almacenData->id,
                        'user_id' => $user_id,
                        'fecha_registro' => date('Y-m-d H:i:s')
                    ];
//                    $cantidad += intval($item->cantidad==''?0:$item->cantidad); //para float
                    $cantidad += $item->cantidad==''?0:$item->cantidad;
                    error_log('cantidad: ' . json_encode($cantidad));
                }

            }
            if ($almacenData->estado == 'REALIZADO') {
                $updateAlmacen[] = [
                    'id' => $almacenData->id,
                    'se_descargo' => 'EXPORTADO',
                    'cantidad' => $cantidad
                ];
            }
        }
        error_log('updateAlmacen: ' . json_encode($updateAlmacen));
        RegistroAlmacen::insert($insertRegistroAlmacen);
        foreach ($updateAlmacen as $almacen) {
            Almacen::where('id', $almacen['id'])->update(['se_descargo' => $almacen['se_descargo'], 'cantidad' => $almacen['cantidad']]);
            $almacen = Almacen::find($almacen['id']);
            error_log('almacen: ' . json_encode($almacen));
        }

        // Devuelve una respuesta de éxito
        return response()->json("success");
    }
    public function convertDMY($fecha){
        $fecha = explode('/', $fecha);
        $dia = strlen($fecha[0]) == 1 ? '0' . $fecha[0] : $fecha[0];
        $mes = strlen($fecha[1]) == 1 ? '0' . $fecha[1] : $fecha[1];
        $fecha = $fecha[2] . '-' . $mes . '-' . $dia;
        return $fecha;
    }
    public function convertfecha($fecha){
        $fecha = explode(' ', $fecha);
        $fecha = $fecha[0];
//        error_log('fecha: ' . json_encode($fecha));
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
    public function update(Request $request, $id){
        $almacen = Almacen::find($id);
        $almacen->codigo = $request->codigo;
        $almacen->codigo_producto = $request->codigo_producto;
        $almacen->producto = $request->producto;
        $almacen->unidad = $request->unidad;
        $almacen->saldo = $request->saldo;
        $almacen->registro = $request->registro;
        $almacen->vencimiento = $request->vencimiento;
        $almacen->grupo = $request->grupo;
        $almacen->fecha_registro = $request->fecha_registro;
        $almacen->save();
        return response()->json($almacen);
    }
    public function destroy($id){
        $almacen = Almacen::find($id);
        $almacen->delete();
        return response()->json($almacen);
    }
}
