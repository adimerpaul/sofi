<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BonificacionController extends Controller{
    public function listar(Request $request)
    {
        $fecha = $request->input('fecha', Carbon::now()->format('Y-m-d'));

        // Agrupamos por NroPed y cargamos relaciones
        $pedidosConBonificaciones = Pedido::with(['cliente', 'user', 'producto'])
            ->whereDate('fecha', $fecha)
            ->where('bonificacion', true)
            ->get()
            ->groupBy('NroPed')
            ->map(function ($items, $nroPed) {
                return [
                    'NroPed' => $nroPed,
                    'cliente' => $items->first()->cliente,
                    'comentario' => $items->first()->comentario,
                    'aprobacion' => $items->first()->bonificacionAprovacion,
                    'usuario' => $items->first()->user->Nombre1 ?? 'Desconocido',
                    'productos' => $items->map(function ($item) {
                        return [
                            'producto' => $item->producto->Producto ?? 'Desconocido',
                            'cantidad' => $item->Cant,
                            'total' => $item->total,
                            'id' => $item->codAut,
                        ];
                    })->values()
                ];
            })
            ->values();

        return response()->json($pedidosConBonificaciones);
    }
    public function aprobar(Request $request, $id)
    {
        $pedido = Pedido::findOrFail($id);
        $pedido->bonificacionAprovacion = $request->user()->name; // o cualquier campo de nombre
        $pedido->save();

        return response()->json(['message' => 'Bonificación aprobada con éxito.']);
    }
}
