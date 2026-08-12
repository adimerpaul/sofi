<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MapaClienteReporteController extends Controller
{
    public function pedidos(Request $request)
    {
        $filters = $this->validateCommonFilters($request);
        $query = Pedido::query()
            ->with([
                'cliente:id,nombre,direccion,telefono,territorio,codcli,ci',
                'user:id,name',
                'usuarioCamion:id,name,placa',
                'zona:id,nombre,color',
                'detalles.producto:id,codigo,nombre,imagen,tipo',
            ]);
        $this->applyFilters($query, $filters);

        $tiposSeleccionados = $this->resolveTiposReportePedidos($filters);

        $pedidos = $query
            ->orderBy('hora')
            ->orderBy('id')
            ->get()
            ->map(function (Pedido $pedido) use ($tiposSeleccionados) {
                $pedido->setRelation('detalles', $pedido->detalles
                    ->filter(function ($detalle) use ($tiposSeleccionados) {
                        $tipo = $this->normalizeTipoProductoReporte($detalle->producto?->tipo);
                        return in_array($tipo, $tiposSeleccionados, true);
                    })
                    ->values());
                return $pedido;
            })
            ->filter(fn (Pedido $pedido) => $pedido->detalles->isNotEmpty())
            ->values();

        if ($pedidos->isEmpty()) {
            return response()->json(['message' => 'No hay pedidos para los filtros seleccionados'], 404);
        }

        $filename = 'reporte_pedidos_' . str_replace('-', '', (string) $filters['fecha']) . '.pdf';
        $pdf = Pdf::loadView('pdf.mapa_cliente_pedidos', [
            'pedidos' => $pedidos,
            'fecha' => $filters['fecha'],
        ])->setPaper('letter');

        return $pdf->download($filename);
    }

    public function zonaVehiculo(Request $request)
    {
        $filters = $this->validateCommonFilters($request, true);
        $camion = User::query()->find((int) $filters['usuario_camion_id']);
        if (!$camion || !$camion->es_camion) {
            return response()->json(['message' => 'Debe seleccionar un usuario camion valido'], 422);
        }

        $query = Pedido::query()
            ->with([
                'cliente:id,nombre,direccion,telefono,territorio,codcli,ci',
                'user:id,name',
                'usuarioCamion:id,name,placa',
                'zona:id,nombre,color',
                'detalles.producto:id,codigo,nombre',
            ]);
        $this->applyFilters($query, $filters);
        $query->where('usuario_camion_id', (int) $filters['usuario_camion_id']);

        $pedidos = $query
            ->orderBy('hora')
            ->orderBy('id')
            ->get();

        if ($pedidos->isEmpty()) {
            return response()->json(['message' => 'No hay pedidos para el camion seleccionado'], 404);
        }

        $total = (float) $pedidos->sum('total');
        $filename = 'reporte_zona_vehiculo_' . str_replace('-', '', (string) $filters['fecha']) . '_' . $camion->id . '.pdf';
        $pdf = Pdf::loadView('pdf.mapa_cliente_zona_vehiculo', [
            'pedidos' => $pedidos,
            'fecha' => $filters['fecha'],
            'camion' => $camion,
            'total' => $total,
        ])->setPaper('letter', 'landscape');

        return $pdf->download($filename);
    }

    public function productosTotales(Request $request)
    {
        $filters = $this->validateCommonFilters($request);
        $query = Pedido::query()
            ->with(['detalles.producto:id,codigo,nombre,imagen']);
        $this->applyFilters($query, $filters);

        $pedidos = $query->get();
        if ($pedidos->isEmpty()) {
            return response()->json(['message' => 'No hay pedidos para los filtros seleccionados'], 404);
        }

        $productos = $pedidos
            ->flatMap(fn (Pedido $pedido) => $pedido->detalles)
            ->filter(fn ($detalle) => $detalle->producto !== null)
            ->groupBy('producto_id')
            ->map(function (Collection $group) {
                $producto = $group->first()->producto;
                $imagen = trim((string) ($producto->imagen ?? ''));
                $imagePath = public_path($imagen);
                $hasImage = $imagen !== '' && file_exists($imagePath);

                return [
                    'codigo' => $producto->codigo ?: ('#' . $producto->id),
                    'nombre' => $producto->nombre,
                    'cantidad_total' => (float) $group->sum('cantidad'),
                    'importe_total' => (float) $group->sum('total'),
                    'imagen_path' => $hasImage ? $imagePath : null,
                ];
            })
            ->sortBy('nombre')
            ->values();

        if ($productos->isEmpty()) {
            return response()->json(['message' => 'No hay productos para los filtros seleccionados'], 404);
        }

        $filename = 'reporte_productos_totales_' . str_replace('-', '', (string) $filters['fecha']) . '.pdf';
        $pdf = Pdf::loadView('pdf.mapa_cliente_productos_totales', [
            'fecha' => $filters['fecha'],
            'productos' => $productos,
            'cantidadTotal' => (float) $productos->sum('cantidad_total'),
            'importeTotal' => (float) $productos->sum('importe_total'),
        ])->setPaper('letter');

        return $pdf->download($filename);
    }

    private function validateCommonFilters(Request $request, bool $requireCamion = false): array
    {
        $this->authorizeMapaAccess($request);
        $rules = [
            'fecha' => 'nullable|date',
            'vendedor_id' => 'nullable|integer|exists:users,id',
            'usuario_camion_id' => ($requireCamion ? 'required' : 'nullable') . '|integer|exists:users,id',
            'pedido_zona_id' => 'nullable|integer|exists:pedido_zonas,id',
            'tipo' => 'nullable|string|in:TODOS,NORMAL,EMBUTIDO,HUEVO,PET,POLLO,RES,CERDO',
            'tipos' => 'nullable|array',
            'tipos.*' => 'string|in:NORMAL,EMBUTIDO,HUEVO,PET,POLLO,RES,CERDO',
        ];

        $data = $request->validate($rules);
        $data['fecha'] = $data['fecha'] ?? now()->toDateString();
        $data['tipo'] = strtoupper((string) ($data['tipo'] ?? 'TODOS'));
        $data['tipos'] = $data['tipos'] ?? [];
        return $data;
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->where('estado', 'Enviado')
            ->whereDate('fecha', $filters['fecha'])
            ->when(!empty($filters['vendedor_id']), function (Builder $q) use ($filters) {
                $q->where('user_id', (int) $filters['vendedor_id']);
            })
            ->when(!empty($filters['pedido_zona_id']), function (Builder $q) use ($filters) {
                $q->where('pedido_zona_id', (int) $filters['pedido_zona_id']);
            })
            ->when(!empty($filters['usuario_camion_id']), function (Builder $q) use ($filters) {
                $q->where('usuario_camion_id', (int) $filters['usuario_camion_id']);
            })
            ->when(($filters['tipo'] ?? 'TODOS') !== 'TODOS', function (Builder $q) use ($filters) {
                if (in_array($filters['tipo'], ['NORMAL', 'EMBUTIDO'], true)) {
                    $q->where('contiene_normal', true);
                }
                if ($filters['tipo'] === 'POLLO') {
                    $q->where('contiene_pollo', true);
                }
                if ($filters['tipo'] === 'RES') {
                    $q->where('contiene_res', true);
                }
                if ($filters['tipo'] === 'CERDO') {
                    $q->where('contiene_cerdo', true);
                }
            });
    }

    private function resolveTiposReportePedidos(array $filters): array
    {
        $requested = collect($filters['tipos'] ?? [])
            ->map(fn ($tipo) => $this->normalizeTipoProductoReporte($tipo))
            ->filter()
            ->unique()
            ->values();

        if ($requested->isEmpty()) {
            return ['EMBUTIDO', 'HUEVO', 'PET'];
        }

        return $requested->all();
    }

    private function normalizeTipoProductoReporte(?string $tipo): string
    {
        $value = strtoupper(trim((string) ($tipo ?? 'EMBUTIDO')));
        if ($value === '' || $value === 'NORMAL') return 'EMBUTIDO';
        if (in_array($value, ['EMBUTIDO', 'HUEVO', 'PET', 'POLLO', 'CERDO', 'RES'], true)) {
            return $value;
        }
        return 'EMBUTIDO';
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
