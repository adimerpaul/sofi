<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\MapaZonaPoligono;
use App\Models\PedidoZona;
use App\Models\User;
use Illuminate\Http\Request;

class MapaClienteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeMapaAccess($request);

        $data = $request->validate([
            'fecha' => 'nullable|date',
            'vendedor_id' => 'nullable|integer|exists:users,id',
            'tipo' => 'nullable|string|in:TODOS,NORMAL,POLLO,RES,CERDO',
            'search' => 'nullable|string|max:120',
        ]);

        $fecha = $data['fecha'] ?? now()->toDateString();
        $tipo = strtoupper((string) ($data['tipo'] ?? 'TODOS'));
        $search = trim((string) ($data['search'] ?? ''));

        $query = Pedido::query()
            ->with([
                'cliente:id,nombre,direccion,telefono,latitud,longitud,territorio,codcli,ci',
                'user:id,name',
                'usuarioCamion:id,name,placa',
                'zona:id,nombre,color,orden',
            ])
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->where('estado', 'Enviado')
            ->whereDate('fecha', $fecha)
            ->when(!empty($data['vendedor_id']), function ($q) use ($data) {
                $q->where('user_id', (int) $data['vendedor_id']);
            })
            ->when($tipo !== 'TODOS', function ($q) use ($tipo) {
                if ($tipo === 'NORMAL') $q->where('contiene_normal', true);
                if ($tipo === 'POLLO') $q->where('contiene_pollo', true);
                if ($tipo === 'RES') $q->where('contiene_res', true);
                if ($tipo === 'CERDO') $q->where('contiene_cerdo', true);
            })
            ->orderBy('hora')
            ->orderBy('id');

        $items = $query->get();

        $grouped = $items->groupBy(function (Pedido $p) {
            return implode('|', [
                (int) ($p->cliente_id ?? 0),
                (int) ($p->user_id ?? 0),
                (int) ($p->pedido_zona_id ?? 0),
                (int) ($p->usuario_camion_id ?? 0),
            ]);
        })->values();

        $rows = $grouped->map(function ($group, $idx) {
            $first = $group->first();
            $cliente = $first?->cliente;
            $zona = $first?->zona;
            $camion = $first?->usuarioCamion;
            $vendedor = $first?->user;

            $hasCoords = is_numeric($cliente?->latitud) && is_numeric($cliente?->longitud);

            return [
                'id' => (int) $first->id,
                'num' => (int) $idx + 1,
                'pedido_ids' => $group->pluck('id')->values(),
                'cantidad_pedidos' => $group->count(),
                'importe' => (float) $group->sum('total'),
                'cliente_id' => $cliente?->id,
                'cliente_nombre' => $cliente?->nombre,
                'cliente_ci' => $cliente?->ci,
                'cliente_codcli' => $cliente?->codcli,
                'direccion' => $cliente?->direccion,
                'telefono' => $cliente?->telefono,
                'territorio' => $cliente?->territorio,
                'latitud' => $hasCoords ? (float) $cliente->latitud : null,
                'longitud' => $hasCoords ? (float) $cliente->longitud : null,
                'vendedor_id' => $vendedor?->id,
                'vendedor' => $vendedor?->name,
                'usuario_camion_id' => $camion?->id,
                'usuario_camion' => $camion?->name,
                'placa_camion' => $camion?->placa,
                'pedido_zona_id' => $zona?->id,
                'zona' => $zona?->nombre,
                'zona_color' => $zona?->color ?? '#9e9e9e',
                'contiene_normal' => $group->contains(fn ($p) => (bool) $p->contiene_normal),
                'contiene_pollo' => $group->contains(fn ($p) => (bool) $p->contiene_pollo),
                'contiene_res' => $group->contains(fn ($p) => (bool) $p->contiene_res),
                'contiene_cerdo' => $group->contains(fn ($p) => (bool) $p->contiene_cerdo),
                'fuera_de_ruta' => $group->contains(fn ($p) => (bool) $p->fuera_de_ruta),
            ];
        })->values();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(function ($row) use ($needle) {
                $stack = mb_strtolower(implode(' ', [
                    $row['cliente_nombre'] ?? '',
                    $row['cliente_ci'] ?? '',
                    (string) ($row['cliente_codcli'] ?? ''),
                    $row['direccion'] ?? '',
                    $row['vendedor'] ?? '',
                    $row['usuario_camion'] ?? '',
                    $row['zona'] ?? '',
                ]));
                return str_contains($stack, $needle);
            })->values();
        }

        $vendedores = User::query()
            ->whereIn('id', $items->pluck('user_id')->unique()->values())
            ->orderBy('name')
            ->get(['id', 'name']);

        $camiones = User::query()
            ->where('es_camion', true)
            ->orderBy('name')
            ->get(['id', 'name', 'placa']);

        $zonas = PedidoZona::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'color', 'activo']);

        $poligonos = MapaZonaPoligono::query()
            ->with(['pedidoZona:id,nombre,color,orden,activo'])
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(function (MapaZonaPoligono $poligono) {
                return [
                    'id' => $poligono->id,
                    'nombre' => $poligono->nombre,
                    'tipo' => (int) $poligono->tipo,
                    'coordenadas' => $poligono->coordenadas ?: [],
                    'color' => $poligono->color ?: $poligono->pedidoZona?->color ?? '#607d8b',
                    'pedido_zona' => $poligono->pedidoZona ? [
                        'id' => $poligono->pedidoZona->id,
                        'nombre' => $poligono->pedidoZona->nombre,
                    ] : null,
                ];
            })
            ->values();

        $statsCamiones = $rows->groupBy(fn ($r) => $r['usuario_camion'] ?: 'SIN ASIGNAR')
            ->map(fn ($g) => $g->count())
            ->sortKeys()
            ->map(fn ($count, $name) => ['nombre' => $name, 'cantidad' => $count])
            ->values();

        return response()->json([
            'data' => $rows,
            'vendedores' => $vendedores,
            'camiones' => $camiones,
            'zonas' => $zonas,
            'poligonos' => $poligonos,
            'stats' => [
                'total_clientes' => $rows->count(),
                'monto_total' => (float) $rows->sum('importe'),
                'con_coordenadas' => $rows->whereNotNull('latitud')->whereNotNull('longitud')->count(),
                'tipo_normal' => $rows->where('contiene_normal', true)->count(),
                'tipo_pollo' => $rows->where('contiene_pollo', true)->count(),
                'tipo_res' => $rows->where('contiene_res', true)->count(),
                'tipo_cerdo' => $rows->where('contiene_cerdo', true)->count(),
                'fuera_de_ruta' => $rows->where('fuera_de_ruta', true)->count(),
                'camiones' => $statsCamiones,
            ],
        ]);
    }

    public function asignar(Request $request)
    {
        $this->authorizeMapaAccess($request);

        $data = $request->validate([
            'pedido_ids' => 'required|array|min:1',
            'pedido_ids.*' => 'integer|exists:pedidos,id',
            'pedido_zona_id' => 'nullable|integer|exists:pedido_zonas,id',
            'usuario_camion_id' => 'nullable|integer|exists:users,id',
        ]);

        if (!empty($data['usuario_camion_id'])) {
            $camion = User::query()->find($data['usuario_camion_id']);
            if (!$camion || !$camion->es_camion) {
                return response()->json(['message' => 'El usuario seleccionado no es tipo camion'], 422);
            }
        }

        $updated = Pedido::query()
            ->whereIn('id', $data['pedido_ids'])
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->where('estado', 'Enviado')
            ->update([
                'pedido_zona_id' => $data['pedido_zona_id'] ?? null,
                'usuario_camion_id' => $data['usuario_camion_id'] ?? null,
            ]);

        return response()->json([
            'message' => 'Asignacion realizada',
            'updated' => $updated,
        ]);
    }

    private function authorizeMapaAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 401, 'No autenticado');

        $isAdmin = strtoupper((string) ($user->role ?? '')) === 'ADMIN';
        $canMapa = method_exists($user, 'can') && $user->can('Mapa cliente');
        abort_unless($isAdmin || $canMapa, 403, 'No autorizado');
    }
}
