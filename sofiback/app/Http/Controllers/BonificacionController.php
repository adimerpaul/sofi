<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BonificacionController extends Controller{
    public function bonificaciones(Request $request){
        $fecha = $request->input('fecha', Carbon::now()->format('Y-m-d'));

        // Agrupamos por NroPed y cargamos relaciones
        $pedidosConBonificaciones = Pedido::with(['cliente', 'user', 'producto'])
            ->whereDate('fecha', $fecha)
            ->whereRaw("(bonificacion = true OR (bonificacionAprovacion IS NOT NULL && bonificacionAprovacion != ''))")
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
    public function bonificacioneAprobar(Request $request)
    {
        $nroPed = $request->input('NroPed');

        // Obtener todos los pedidos de ese NroPed con bonificación
        $pedidos = Pedido::where('NroPed', $nroPed)
            ->where('bonificacion', true)
            ->get();

        if ($pedidos->isEmpty()) {
            return response()->json(['message' => 'Bonificación no encontrada.'], 404);
        }

        // Verificar si ya fue aprobada
        if ($pedidos->first()->bonificacionAprovacion) {
            return response()->json(['message' => 'La bonificación ya fue aprobada.'], 422);
        }

        // Guardar la aprobación en todos los registros del mismo NroPed
        foreach ($pedidos as $pedido) {
            $pedido->bonificacionAprovacion = $request->user()->Nombre1;
            $pedido->bonificacion = false;
            $pedido->save();
        }

        return response()->json(['message' => 'Bonificación aprobada correctamente.']);
    }
}
