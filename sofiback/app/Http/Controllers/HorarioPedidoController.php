<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Hora limite para mandar pedidos por tipo (tabla horarios_pedido; reglas en
 * App\Services\HorarioPedido). Verlos basta con estar logueado (Visita y Mis
 * pedidos avisan antes de mandar); cambiarlos pide el permiso horariosPedido.
 */
class HorarioPedidoController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless($request->user() && $request->user()->can('horariosPedido'), 403,
                'No tiene permiso para cambiar los horarios de envío');
            return $next($request);
        })->only(['update']);
    }

    public function index()
    {
        return DB::table('horarios_pedido as h')
            ->leftJoin('personal as p', 'p.CodAut', '=', 'h.actualizado_por')
            ->orderBy('h.orden')
            ->get([
                'h.id', 'h.tipo', 'h.nombre', 'h.hora_limite', 'h.habilitado', 'h.updated_at',
                DB::raw("TRIM(CONCAT(TRIM(COALESCE(p.Nombre1, '')), ' ', TRIM(COALESCE(p.App1, '')))) as actualizado_por"),
            ]);
    }

    public function update(Request $request, $id)
    {
        $datos = $request->validate([
            'hora_limite' => 'required|date_format:H:i',
            'habilitado'  => 'required|boolean',
        ]);
        $n = DB::table('horarios_pedido')->where('id', $id)->update([
            'hora_limite' => $datos['hora_limite'] . ':00',
            'habilitado' => (bool) $datos['habilitado'],
            'actualizado_por' => $request->user()->CodAut,
            'updated_at' => now(),
        ]);
        abort_unless($n || DB::table('horarios_pedido')->where('id', $id)->exists(), 404, 'El horario no existe');

        return DB::table('horarios_pedido')->find($id);
    }
}
