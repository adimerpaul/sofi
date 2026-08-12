<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class InitController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'fechaInicio' => 'nullable|date',
            'fechaFin' => 'nullable|date',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $authUser = $request->user();
        $isAdmin = in_array(strtoupper((string) ($authUser->role ?? '')), ['ADMIN', 'ADMINISTRADOR'], true);

        $fechaInicio = Carbon::parse($data['fechaInicio'] ?? now()->toDateString())->toDateString();
        $fechaFin = Carbon::parse($data['fechaFin'] ?? now()->toDateString())->toDateString();

        if ($fechaInicio > $fechaFin) {
            [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
        }

        $userId = $isAdmin ? ($data['user_id'] ?? null) : (int) $authUser->id;

        $ventas = Venta::query()
            ->with([
                'user:id,name',
                'cliente:id,nombre',
                'ventaDetalles:id,venta_id,producto_id,cantidad,precio,nombre',
                'ventaDetalles.producto:id,nombre,tipo,codigo_unidad,presentacion',
            ])
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$isAdmin && !empty($authUser->agencia), fn ($q) => $q->where('agencia', $authUser->agencia))
            ->orderByDesc('fecha')
            ->orderByDesc('hora')
            ->orderByDesc('id')
            ->get();

        $pedidos = Pedido::query()
            ->with([
                'user:id,name',
                'cliente:id,nombre',
                'venta:id,pedido_id,total,estado,facturado,factura_estado',
                'detalles:id,pedido_id,producto_id,cantidad,precio,total',
                'detalles.producto:id,nombre,tipo,codigo_unidad,presentacion',
            ])
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('fecha')
            ->orderByDesc('hora')
            ->orderByDesc('id')
            ->get();

        $ventasActivas = $ventas->filter(fn (Venta $venta) => strtoupper((string) $venta->estado) === 'ACTIVO')->values();
        $ventasAnuladas = $ventas->filter(fn (Venta $venta) => strtoupper((string) $venta->estado) === 'ANULADA')->values();

        $mix = [
            'unidades' => 0.0,
            'kilogramos' => 0.0,
            'pollos' => 0.0,
        ];

        $lineas = [
            'pollo' => ['key' => 'pollo', 'label' => 'Pollo', 'cantidad' => 0.0, 'total' => 0.0],
            'cerdo' => ['key' => 'cerdo', 'label' => 'Cerdo', 'cantidad' => 0.0, 'total' => 0.0],
            'res' => ['key' => 'res', 'label' => 'Carnes', 'cantidad' => 0.0, 'total' => 0.0],
            'embutidos' => ['key' => 'embutidos', 'label' => 'Embutidos', 'cantidad' => 0.0, 'total' => 0.0],
            'comida_fria' => ['key' => 'comida_fria', 'label' => 'Comida fria', 'cantidad' => 0.0, 'total' => 0.0],
            'otros' => ['key' => 'otros', 'label' => 'Otros', 'cantidad' => 0.0, 'total' => 0.0],
        ];

        $topProductos = [];

        foreach ($ventasActivas as $venta) {
            foreach ($venta->ventaDetalles as $detalle) {
                $producto = $detalle->producto;
                $nombre = (string) ($detalle->nombre ?: $producto?->nombre ?: 'Producto sin nombre');
                $cantidad = (float) ($detalle->cantidad ?? 0);
                $precio = (float) ($detalle->precio ?? 0);
                $subtotal = round($cantidad * $precio, 2);

                $unidadKey = $this->resolveUnidad($producto?->codigo_unidad, $nombre, $producto?->presentacion);
                $lineaKey = $this->resolveLinea($nombre, $producto?->tipo, $producto?->presentacion);

                $mix[$unidadKey] += $cantidad;
                $lineas[$lineaKey]['cantidad'] += $cantidad;
                $lineas[$lineaKey]['total'] += $subtotal;

                if (!isset($topProductos[$nombre])) {
                    $topProductos[$nombre] = [
                        'nombre' => $nombre,
                        'cantidad' => 0.0,
                        'total' => 0.0,
                    ];
                }

                $topProductos[$nombre]['cantidad'] += $cantidad;
                $topProductos[$nombre]['total'] += $subtotal;
            }
        }

        $ventasPago = $ventasActivas
            ->groupBy(fn (Venta $venta) => strtoupper(trim((string) ($venta->tipo_pago ?? 'SIN DEFINIR'))))
            ->map(fn (Collection $group, string $tipo) => [
                'tipo' => $tipo,
                'cantidad' => $group->count(),
                'total' => round((float) $group->sum('total'), 2),
            ])
            ->sortByDesc('total')
            ->values();

        $pedidosPorEstado = collect(['Creado', 'Pendiente', 'Enviado', 'Aceptado', 'Anulado'])
            ->map(fn (string $estado) => [
                'estado' => $estado,
                'cantidad' => $pedidos->filter(fn (Pedido $pedido) => strtoupper((string) $pedido->estado) === strtoupper($estado))->count(),
            ])
            ->values();

        $facturadas = $ventasActivas->filter(fn (Venta $venta) => strtoupper((string) $venta->factura_estado) === 'FACTURADO')->values();
        $facturaPendiente = $ventasActivas->filter(fn (Venta $venta) => strtoupper((string) $venta->factura_estado) === 'PENDIENTE')->values();
        $facturaError = $ventasActivas->filter(function (Venta $venta) {
            return strtoupper((string) $venta->factura_estado) === 'ERROR'
                || trim((string) ($venta->factura_error ?? '')) !== '';
        })->values();
        $sinGestion = $ventasActivas->filter(function (Venta $venta) {
            $estado = strtoupper((string) ($venta->factura_estado ?? 'SIN_GESTION'));
            return in_array($estado, ['SIN_GESTION', ''], true) || !$venta->facturado;
        })->values();

        $actividad = collect()
            ->merge($pedidos->take(6)->map(function (Pedido $pedido) {
                return [
                    'tipo' => 'Pedido',
                    'codigo' => '#' . $pedido->id,
                    'cliente' => (string) ($pedido->cliente?->nombre ?? 'Sin cliente'),
                    'responsable' => (string) ($pedido->user?->name ?? 'Sin vendedor'),
                    'estado' => (string) ($pedido->estado ?? 'Sin estado'),
                    'total' => (float) ($pedido->total ?? 0),
                    'fecha' => (string) ($pedido->fecha ?? ''),
                    'hora' => (string) ($pedido->hora ?? ''),
                ];
            }))
            ->merge($ventas->take(6)->map(function (Venta $venta) {
                return [
                    'tipo' => 'Venta',
                    'codigo' => '#' . $venta->id,
                    'cliente' => (string) ($venta->cliente?->nombre ?? $venta->nombre ?? 'Sin cliente'),
                    'responsable' => (string) ($venta->user?->name ?? 'Sin vendedor'),
                    'estado' => (string) ($venta->factura_estado ?: $venta->estado ?: 'Sin estado'),
                    'total' => (float) ($venta->total ?? 0),
                    'fecha' => (string) ($venta->fecha ?? ''),
                    'hora' => (string) ($venta->hora ?? ''),
                ];
            }))
            ->sortByDesc(fn (array $item) => ($item['fecha'] ?? '') . ' ' . ($item['hora'] ?? '00:00:00'))
            ->take(10)
            ->values();

        return response()->json([
            'negocio' => [
                'nombre' => 'Sofia',
                'descripcion' => 'Ventas de pollo, unidades, comida fria, embutidos, carnes y cerdo.',
                'enfoque' => ['Pedidos', 'Ventas', 'Facturacion'],
            ],
            'periodo' => [
                'fechaInicio' => $fechaInicio,
                'fechaFin' => $fechaFin,
            ],
            'resumen' => [
                'pedidos' => [
                    'total' => $pedidos->count(),
                    'pendientes' => $pedidos->filter(fn (Pedido $pedido) => in_array(strtoupper((string) $pedido->estado), ['CREADO', 'PENDIENTE', 'ENVIADO'], true))->count(),
                    'aceptados' => $pedidos->filter(fn (Pedido $pedido) => strtoupper((string) $pedido->estado) === 'ACEPTADO')->count(),
                    'anulados' => $pedidos->filter(fn (Pedido $pedido) => strtoupper((string) $pedido->estado) === 'ANULADO')->count(),
                    'sin_venta' => $pedidos->filter(fn (Pedido $pedido) => empty($pedido->venta_id))->count(),
                ],
                'ventas' => [
                    'activas' => $ventasActivas->count(),
                    'anuladas' => $ventasAnuladas->count(),
                    'monto_total' => round((float) $ventasActivas->sum('total'), 2),
                    'ticket_promedio' => $ventasActivas->isNotEmpty() ? round((float) $ventasActivas->avg('total'), 2) : 0.0,
                    'items' => round((float) $ventasActivas->sum(fn (Venta $venta) => $venta->ventaDetalles->sum('cantidad')), 2),
                ],
                'facturacion' => [
                    'facturadas' => $facturadas->count(),
                    'pendientes' => $facturaPendiente->count(),
                    'errores' => $facturaError->count(),
                    'sin_gestion' => $sinGestion->count(),
                    'monto_facturado' => round((float) $facturadas->sum('total'), 2),
                ],
                'mix' => [
                    'unidades' => round($mix['unidades'], 2),
                    'kilogramos' => round($mix['kilogramos'], 2),
                    'pollos' => round($mix['pollos'], 2),
                ],
            ],
            'lineas_comerciales' => collect($lineas)
                ->sortByDesc('total')
                ->values(),
            'ventas_por_pago' => $ventasPago,
            'pedidos_por_estado' => $pedidosPorEstado,
            'top_productos' => collect($topProductos)
                ->sortByDesc('total')
                ->take(8)
                ->values(),
            'actividad_reciente' => $actividad,
            'alertas' => [
                [
                    'label' => 'Pedidos sin venta',
                    'value' => $pedidos->filter(fn (Pedido $pedido) => empty($pedido->venta_id))->count(),
                ],
                [
                    'label' => 'Ventas con error de factura',
                    'value' => $facturaError->count(),
                ],
                [
                    'label' => 'Facturas pendientes',
                    'value' => $facturaPendiente->count(),
                ],
                [
                    'label' => 'Ventas anuladas',
                    'value' => $ventasAnuladas->count(),
                ],
            ],
        ]);
    }

    private function resolveUnidad(?string $codigoUnidad, ?string $nombre, ?string $presentacion): string
    {
        $codigo = strtoupper(trim((string) $codigoUnidad));
        $texto = strtoupper(trim((string) $nombre . ' ' . $presentacion));

        if ($codigo === 'KG' || str_contains($texto, ' KG') || str_contains($texto, 'KILO')) {
            return 'kilogramos';
        }

        if (
            str_contains($texto, 'POLLO ENTERO')
            || str_contains($texto, 'POLLO PARRILLERO')
            || (str_contains($texto, 'POLLO') && in_array($codigo, ['U', 'UN', 'UNI'], true))
        ) {
            return 'pollos';
        }

        return 'unidades';
    }

    private function resolveLinea(?string $nombre, ?string $tipo, ?string $presentacion): string
    {
        $texto = strtoupper(trim((string) $nombre . ' ' . $tipo . ' ' . $presentacion));

        if (str_contains($texto, 'POLLO')) {
            return 'pollo';
        }
        if (str_contains($texto, 'CERDO') || str_contains($texto, 'CHANCHO') || str_contains($texto, 'PANCETA')) {
            return 'cerdo';
        }
        if (str_contains($texto, 'RES') || str_contains($texto, 'CARNE') || str_contains($texto, 'LOMO')) {
            return 'res';
        }
        if (
            str_contains($texto, 'SALCHICHA')
            || str_contains($texto, 'CHORIZO')
            || str_contains($texto, 'JAMON')
            || str_contains($texto, 'MORTADELA')
            || str_contains($texto, 'EMBUT')
            || str_contains($texto, 'TOCINO')
        ) {
            return 'embutidos';
        }
        if (
            str_contains($texto, 'FRIO')
            || str_contains($texto, 'FRIA')
            || str_contains($texto, 'CONGELADO')
            || str_contains($texto, 'MILANESA')
        ) {
            return 'comida_fria';
        }

        return 'otros';
    }
}
