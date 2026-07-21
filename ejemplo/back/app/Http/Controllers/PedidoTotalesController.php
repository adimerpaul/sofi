<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Http\Request;

class PedidoTotalesController extends Controller
{
    private array $estadosEditables = ['Creado', 'Pendiente'];

    public function index(Request $request)
    {
        $this->authorizeTotales($request);

        $data = $request->validate([
            'fecha' => 'nullable|date',
            'user_id' => 'nullable|integer|exists:users,id',
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'estado' => 'nullable|string|in:TODOS,Creado,Pendiente,Enviado,Aceptado,Anulado',
        ]);

        $fecha = $data['fecha'] ?? now()->toDateString();
        $estado = $data['estado'] ?? 'TODOS';

        $query = Pedido::query()
            ->with(['detalles.producto', 'cliente:id,nombre,codcli,ci', 'user:id,name,role'])
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->whereDate('fecha', $fecha)
            ->when(!empty($data['user_id']), function ($q) use ($data) {
                $q->where('user_id', (int) $data['user_id']);
            })
            ->when(!empty($data['cliente_id']), function ($q) use ($data) {
                $q->where('cliente_id', (int) $data['cliente_id']);
            })
            ->when($estado !== 'TODOS', function ($q) use ($estado) {
                $q->where('estado', $estado);
            })
            ->orderBy('hora')
            ->orderBy('id');

        $items = $query->get();

        $vendedorIds = Pedido::query()
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->whereDate('fecha', $fecha)
            ->distinct()
            ->pluck('user_id')
            ->filter()
            ->values();

        $vendedores = User::query()
            ->whereIn('id', $vendedorIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $items,
            'vendedores' => $vendedores,
            'stats' => [
                'total' => $items->count(),
                'creado' => $items->where('estado', 'Creado')->count(),
                'pendiente' => $items->where('estado', 'Pendiente')->count(),
                'enviado' => $items->where('estado', 'Enviado')->count(),
                'no_enviado' => $items->whereIn('estado', $this->estadosEditables)->count(),
                'monto_total' => (float) $items->sum('total'),
                'tipo_normal' => $items->where('contiene_normal', true)->count(),
                'tipo_pollo' => $items->where('contiene_pollo', true)->count(),
                'tipo_res' => $items->where('contiene_res', true)->count(),
                'tipo_cerdo' => $items->where('contiene_cerdo', true)->count(),
            ],
        ]);
    }

    public function enviarEmergencia(Request $request)
    {
        $this->authorizeTotales($request);

        $data = $request->validate([
            'fecha' => 'nullable|date',
            'user_id' => 'nullable|integer|exists:users,id',
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'estado' => 'nullable|string|in:TODOS,Creado,Pendiente',
            'ids' => 'nullable|array',
            'ids.*' => 'integer|exists:pedidos,id',
        ]);

        $fecha = $data['fecha'] ?? now()->toDateString();
        $estado = $data['estado'] ?? 'TODOS';
        $ids = $data['ids'] ?? [];

        $query = Pedido::query()
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->whereDate('fecha', $fecha)
            ->whereIn('estado', $this->estadosEditables)
            ->when(!empty($data['user_id']), function ($q) use ($data) {
                $q->where('user_id', (int) $data['user_id']);
            })
            ->when(!empty($data['cliente_id']), function ($q) use ($data) {
                $q->where('cliente_id', (int) $data['cliente_id']);
            })
            ->when($estado !== 'TODOS', function ($q) use ($estado) {
                $q->where('estado', $estado);
            });

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        $toSendIds = $query->pluck('id');
        if ($toSendIds->isEmpty()) {
            return response()->json([
                'message' => 'No hay pedidos pendientes para envio de emergencia',
                'sent' => 0,
            ]);
        }

        $updated = Pedido::query()
            ->whereIn('id', $toSendIds->all())
            ->update(['estado' => 'Enviado']);

        return response()->json([
            'message' => 'Pedidos enviados en modo emergencia',
            'sent' => $updated,
        ]);
    }

    private function authorizeTotales(Request $request): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'No autenticado');
        }

        $isAdmin = strtoupper((string) ($user->role ?? '')) === 'ADMIN';
        $canTotales = method_exists($user, 'can') && $user->can('Mis pedidos totales');

        abort_unless($isAdmin || $canTotales, 403, 'No autorizado');
    }
}
