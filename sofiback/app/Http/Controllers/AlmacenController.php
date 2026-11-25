<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\RegistroAlmacen;
use App\Models\User;
use Illuminate\Http\Request;

class AlmacenController extends Controller{
    function almacenRegistroVerificar($id, Request $request){
        error_log('id: ' . json_encode($id));
        error_log('request: ' . json_encode($request->all()));
        $almacen = RegistroAlmacen::find($id);
        $almacen->verificado = $request->verificado;
        $almacen->save();
        return response()->json($almacen);
    }
    function almacenPendientes(Request $request){
        $fecha_registro = $request->fecha;

        $almacenes = Almacen::with('registros')
            ->where('fecha_registro', $fecha_registro) // Filtra almacenes por fecha_registro proporcionada
            ->whereHas('registros', function ($query) {
                $query->whereNull('fecha_vencimiento'); // Filtra los registros relacionados donde fecha_vencimiento es NULL
            })
            ->get();
        foreach ($almacenes as $almacen) {
            $now = date('Y-m-d');
            $almacen->registros->map(function ($registro) use ($now) {
                $registro->dias_vencimiento = $registro->fecha_vencimiento ? (strtotime($registro->fecha_vencimiento) - strtotime($now)) / (60 * 60 * 24) : null;
                return $registro;
            });
        }
        return response()->json($almacenes);
    }
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
            $almacen->comentario1 = count($almacen->registros) > 0 ? $almacen->registros[0]->comentario : '';
            $almacen->comentario2 = count($almacen->registros) > 1 ? $almacen->registros[1]->comentario : '';
            $almacen->comentario3 = count($almacen->registros) > 2 ? $almacen->registros[2]->comentario : '';
            $almacen->comentario4 = count($almacen->registros) > 3 ? $almacen->registros[3]->comentario : '';
            $almacen->comentario5 = count($almacen->registros) > 4 ? $almacen->registros[4]->comentario : '';
            $almacen->comentario6 = count($almacen->registros) > 5 ? $almacen->registros[5]->comentario : '';
            $almacen->comentario7 = count($almacen->registros) > 6 ? $almacen->registros[6]->comentario : '';
            $almacen->comentario8 = count($almacen->registros) > 7 ? $almacen->registros[7]->comentario : '';
            $almacen->comentario9 = count($almacen->registros) > 8 ? $almacen->registros[8]->comentario : '';
            $almacen->comentario10 = count($almacen->registros) > 9 ? $almacen->registros[9]->comentario : '';
            $almacen->appCantidad = $almacen->cantidad1 + $almacen->cantidad2 + $almacen->cantidad3 + $almacen->cantidad4 + $almacen->cantidad5 + $almacen->cantidad6 + $almacen->cantidad7 + $almacen->cantidad8 + $almacen->cantidad9 + $almacen->cantidad10;
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
        // Formato "Productos para almacén" de /productos:
        // Código, Producto, Grupo, Unidad, P. Compra, Precio, P. Costo, Stock, GRUPOS
        if (isset($data[0][8]) && strtoupper(trim($data[0][8])) == 'GRUPOS') {
            foreach ($data as $key => $value) {
                if ($key == 0 || trim((string) $value[0]) === '') continue;
                $insertAlmacen[] = [
                    'codigo' => trim((string) $value[8]) !== '' ? trim($value[8]) : 'A',
                    'codigo_producto' => trim((string) $value[0]),
                    'producto' => $value[1],
                    'unidad' => $value[3],
                    'saldo' => (float) str_replace(',', '', $value[7]),
                    'registro' => null,
                    'vencimiento' => null,
                    'grupo' => $value[2],
                    'fecha_registro' => $request->fecha
                ];
            }
            Almacen::insert($insertAlmacen);
            return;
        }
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
    /**
     * Recibe de la app de almacen el conteo (JSON en "almacen") y lo guarda.
     * Antes logueaba todo el payload con error_log() (varias veces por item):
     * eso iba al stderr de php-fpm, llenaba el header FastCGI de nginx
     * ("upstream sent too big header" -> 502) y la app reintentaba.
     * Ahora: sin logs por item, todo en una transaccion y en lote.
     */
    public function exportData(Request $request)
    {
        $data = json_decode($request->input('almacen'));
        if (!is_array($data)) {
            return response()->json(['message' => 'almacen invalido'], 422);
        }
        $userId = (string) json_decode($request->input('user'));
        $ahora = date('Y-m-d H:i:s');

        $ids = [];
        $insertRegistroAlmacen = [];
        $updateAlmacen = [];
        foreach ($data as $almacenData) {
            if (!isset($almacenData->id)) continue;
            $ids[] = $almacenData->id;
            if (($almacenData->estado ?? null) != 'REALIZADO') continue;

            $cantidad = 0;
            foreach (($almacenData->detalle ?? []) as $item) {
                $cant = ($item->cantidad ?? '') === '' ? 0 : $item->cantidad;
                $venc = $item->vencimiento ?? '';
                $insertRegistroAlmacen[] = [
                    'cantidad' => $cant,
                    'fecha_vencimiento' => $venc == '' ? null : substr($venc, 0, 10),
                    'lote' => ($item->lote ?? null) == 'null' ? '' : ($item->lote ?? null),
                    'comentario' => ($item->comentario ?? null) == 'null' ? '' : ($item->comentario ?? null),
                    'almacen_id' => $almacenData->id,
                    'user_id' => $userId,
                    'fecha_registro' => $ahora,
                ];
                $cantidad += (float) $cant;
            }
            $updateAlmacen[$almacenData->id] = $cantidad;
        }

        \DB::transaction(function () use ($ids, $insertRegistroAlmacen, $updateAlmacen) {
            foreach (array_chunk($ids, 500) as $chunk) {
                RegistroAlmacen::whereIn('almacen_id', $chunk)->delete();
            }
            foreach (array_chunk($insertRegistroAlmacen, 500) as $chunk) {
                RegistroAlmacen::insert($chunk);
            }
            foreach ($updateAlmacen as $id => $cantidad) {
                Almacen::where('id', $id)->update(['se_descargo' => 'EXPORTADO', 'cantidad' => $cantidad]);
            }
        });

        \Log::info('exportData', [
            'user' => $userId,
            'productos' => count($ids),
            'exportados' => count($updateAlmacen),
            'registros' => count($insertRegistroAlmacen),
        ]);

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
    /**
     * Guarda desde la web los conteos de un producto (hasta 10, como la app):
     * cantidad, vencimiento, lote y comentario. Los que ya existen se actualizan
     * (conservan quien los conto), los nuevos quedan a nombre de quien guarda y
     * los que se quitaron se borran. Igual que exportData, el producto queda
     * EXPORTADO con la suma como cantidad.
     */
    public function guardarRegistros(Request $request, $id){
        $request->validate([
            'registros' => 'present|array|max:10',
            'registros.*.id' => 'nullable|integer',
            'registros.*.cantidad' => 'required|numeric|min:0',
            'registros.*.fecha_vencimiento' => 'nullable|date_format:Y-m-d',
            'registros.*.lote' => 'nullable|string|max:100',
            'registros.*.comentario' => 'nullable|string|max:255',
        ], [
            'registros.*.cantidad.required' => 'Falta la cantidad en un conteo',
            'registros.*.fecha_vencimiento.date_format' => 'Una fecha de vencimiento no es válida',
            'registros.max' => 'Máximo 10 conteos por producto',
        ]);
        $almacen = Almacen::findOrFail($id);
        $userId = $request->user()->CodAut;

        \DB::transaction(function () use ($request, $almacen, $userId) {
            $ids = [];
            $total = 0;
            foreach ($request->registros as $r) {
                $datos = [
                    'cantidad' => round((float) $r['cantidad'], 2),
                    'fecha_vencimiento' => !empty($r['fecha_vencimiento']) ? substr($r['fecha_vencimiento'], 0, 10) : null,
                    'lote' => isset($r['lote']) ? trim((string) $r['lote']) : '',
                    'comentario' => isset($r['comentario']) ? trim((string) $r['comentario']) : '',
                ];
                $total += $datos['cantidad'];
                $registro = !empty($r['id'])
                    ? RegistroAlmacen::where('almacen_id', $almacen->id)->where('id', $r['id'])->first()
                    : null;
                if ($registro) {
                    $registro->update($datos);
                } else {
                    $registro = RegistroAlmacen::create($datos + [
                        'almacen_id' => $almacen->id,
                        'user_id' => $userId,
                        'fecha_registro' => date('Y-m-d H:i:s'),
                    ]);
                }
                $ids[] = $registro->id;
            }
            RegistroAlmacen::where('almacen_id', $almacen->id)->whereNotIn('id', $ids ?: [0])->delete();
            $almacen->cantidad = $total;
            if (count($ids) > 0) {
                $almacen->se_descargo = 'EXPORTADO';
            }
            $almacen->save();
        });

        return response()->json(RegistroAlmacen::where('almacen_id', $almacen->id)->with('user')->get());
    }
    public function update(Request $request, $id){
        $request->validate([
            'registro' => 'nullable|date_format:Y-m-d',
            'vencimiento' => 'nullable|date_format:Y-m-d',
            'fecha_registro' => 'required|date_format:Y-m-d',
            'saldo' => 'nullable|numeric',
        ], [
            'registro.date_format' => 'La fecha de registro no es válida',
            'vencimiento.date_format' => 'La fecha de vencimiento no es válida',
            'fecha_registro.required' => 'Falta la fecha de inventario',
            'saldo.numeric' => 'El saldo debe ser un número',
        ]);
        $almacen = Almacen::findOrFail($id);
        $almacen->codigo = $request->codigo;
        $almacen->codigo_producto = $request->codigo_producto;
        $almacen->producto = $request->producto;
        $almacen->unidad = $request->unidad;
        $almacen->saldo = $request->saldo;
        $almacen->registro = $request->registro ? substr($request->registro, 0, 10) : null;
        $almacen->vencimiento = $request->vencimiento ? substr($request->vencimiento, 0, 10) : null;
        $almacen->grupo = $request->grupo;
        $almacen->fecha_registro = substr($request->fecha_registro, 0, 10);
        $almacen->save();
        return response()->json($almacen);
    }
    public function destroy($id){
        $almacen = Almacen::find($id);
        $almacen->delete();
        return response()->json($almacen);
    }
    public function eliminarTodos(Request $request){
        $request->validate(['fecha' => 'required|date']);
        // registro_almacens se borra en cascada por la FK almacen_id
        $eliminados = Almacen::whereDate('fecha_registro', $request->fecha)->delete();
        return response()->json(['eliminados' => $eliminados]);
    }
}
