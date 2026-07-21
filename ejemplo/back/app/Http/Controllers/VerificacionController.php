<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class VerificacionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeVerificacion($request);

        $data = $request->validate([
            'fecha' => 'nullable|date',
            'search' => 'nullable|string|max:120',
            'verificado' => 'nullable|string|in:TODOS,SI,NO',
        ]);

        $fecha = $data['fecha'] ?? now()->toDateString();
        $search = mb_strtolower(trim((string) ($data['search'] ?? '')));
        $verificado = strtoupper((string) ($data['verificado'] ?? 'TODOS'));

        $ventas = $this->queryVentas($fecha)
            ->when($verificado === 'SI', fn (Builder $q) => $q->where('verificado', true))
            ->when($verificado === 'NO', fn (Builder $q) => $q->where('verificado', false))
            ->get();

        if ($search !== '') {
            $ventas = $ventas->filter(function (Venta $venta) use ($search) {
                $productos = $venta->ventaDetalles->map(function (VentaDetalle $detalle) {
                    return trim(implode(' ', [
                        (string) ($detalle->producto?->codigo ?? ''),
                        (string) ($detalle->nombre ?: ($detalle->producto?->nombre ?? '')),
                    ]));
                })->implode(' ');

                $haystack = mb_strtolower(implode(' ', [
                    (string) $venta->id,
                    (string) ($venta->pedido_id ?? ''),
                    (string) ($venta->pedido?->cliente?->nombre ?? ''),
                    (string) ($venta->cliente?->nombre ?? ''),
                    (string) ($venta->pedido?->user?->name ?? ''),
                    (string) ($venta->pedido?->usuarioCamion?->name ?? ''),
                    (string) ($venta->verificador?->name ?? ''),
                    $productos,
                ]));

                return str_contains($haystack, $search);
            })->values();
        }

        $rows = $ventas->map(fn (Venta $venta) => $this->mapVentaRow($venta))->values();

        return response()->json([
            'data' => $rows,
            'stats' => [
                'total' => $rows->count(),
                'verificados' => $rows->where('verificado', true)->count(),
                'no_verificados' => $rows->where('verificado', false)->count(),
                'facturados' => $rows->where('facturado', true)->count(),
                'no_facturados' => $rows->where('facturado', false)->count(),
            ],
            'filtros' => [
                'fecha' => $fecha,
                'verificado' => $verificado,
            ],
        ]);
    }

    public function updateVenta(Request $request, Venta $venta)
    {
        $this->authorizeVerificacion($request);

        $data = $request->validate([
            'verificado' => 'required|boolean',
        ]);

        $venta->verificado = (bool) $data['verificado'];
        $venta->verificado_user_id = $venta->verificado ? $request->user()->id : null;
        $venta->verificado_at = $venta->verificado ? now() : null;
        $venta->save();

        return response()->json([
            'message' => $venta->verificado ? 'Venta verificada' : 'Verificacion retirada',
            'venta' => $this->mapVentaRow($venta->fresh($this->relations())),
        ]);
    }

    public function imprimir(Request $request)
    {
        $this->authorizeVerificacion($request);

        $data = $request->validate([
            'fecha' => 'nullable|date',
            'verificado' => 'nullable|string|in:TODOS,SI,NO',
        ]);

        $fecha = $data['fecha'] ?? now()->toDateString();
        $verificado = strtoupper((string) ($data['verificado'] ?? 'TODOS'));

        $ventas = $this->queryVentas($fecha)
            ->when($verificado === 'SI', fn (Builder $q) => $q->where('verificado', true))
            ->when($verificado === 'NO', fn (Builder $q) => $q->where('verificado', false))
            ->get();

        if ($ventas->isEmpty()) {
            return response()->json(['message' => 'No hay ventas para imprimir en la fecha seleccionada'], 422);
        }

        $pdf = Pdf::loadView('pdf.verificacion_ventas', [
            'fecha' => $fecha,
            'ventas' => $ventas,
            'filtroVerificado' => $verificado,
        ])->setPaper('letter');

        return $pdf->download("verificacion_ventas_{$fecha}.pdf");
    }

    public function imprimirVenta(Request $request, Venta $venta)
    {
        $this->authorizeVerificacion($request);

        if (!$venta->pedido_id) {
            return response()->json(['message' => 'La venta no esta asociada a pedido'], 422);
        }

        $venta->loadMissing($this->relations());

        $pdf = Pdf::loadView('pdf.verificacion_ventas', [
            'fecha' => (string) ($venta->fecha ?? now()->toDateString()),
            'ventas' => collect([$venta]),
            'filtroVerificado' => $venta->verificado ? 'SI' : 'NO',
        ])->setPaper('letter');

        return $pdf->download("verificacion_venta_{$venta->id}.pdf");
    }

    private function queryVentas(string $fecha): Builder
    {
        return Venta::query()
            ->with($this->relations())
            ->whereNotNull('pedido_id')
            ->whereDate('fecha', $fecha)
            ->whereHas('pedido', function (Builder $q) {
                $q->where('tipo_pedido', 'REALIZAR_PEDIDO');
            })
            ->orderBy('hora')
            ->orderBy('id');
    }

    private function relations(): array
    {
        return [
            'pedido:id,user_id,cliente_id,usuario_camion_id,fecha,hora,estado,tipo_pago,facturado,tipo_pedido,observaciones',
            'pedido.user:id,name',
            'pedido.cliente:id,nombre,codcli,ci,telefono,direccion',
            'pedido.usuarioCamion:id,name,placa',
            'user:id,name',
            'cliente:id,nombre,codcli,ci,telefono,direccion',
            'verificador:id,name',
            'ventaDetalles:id,venta_id,producto_id,cantidad,precio,nombre',
            'ventaDetalles.producto:id,codigo,nombre,tipo',
        ];
    }

    private function mapVentaRow(Venta $venta): array
    {
        $productos = $venta->ventaDetalles->map(function (VentaDetalle $detalle) {
            return [
                'id' => $detalle->id,
                'codigo' => $detalle->producto?->codigo ?: ('#' . $detalle->producto_id),
                'nombre' => $detalle->nombre ?: ($detalle->producto?->nombre ?: ('Producto #' . $detalle->producto_id)),
                'tipo' => strtoupper((string) ($detalle->producto?->tipo ?? 'NORMAL')),
                'cantidad' => (float) $detalle->cantidad,
                'precio' => (float) $detalle->precio,
                'subtotal' => (float) $detalle->cantidad * (float) $detalle->precio,
            ];
        })->values();

        return [
            'venta_id' => $venta->id,
            'pedido_id' => $venta->pedido_id,
            'comanda' => $venta->pedido_id,
            'fecha' => (string) ($venta->fecha ?? ''),
            'hora' => (string) ($venta->hora ?? ''),
            'estado' => (string) ($venta->estado ?? ''),
            'cliente' => $venta->pedido?->cliente?->nombre ?: $venta->cliente?->nombre,
            'vendedor' => (string) ($venta->pedido?->user?->name ?? $venta->user?->name ?? ''),
            'camion' => (string) ($venta->pedido?->usuarioCamion?->name ?? ''),
            'camion_placa' => (string) ($venta->pedido?->usuarioCamion?->placa ?? ''),
            'facturado' => (bool) ($venta->facturado ?? false),
            'factura_estado' => (string) ($venta->factura_estado ?? 'SIN_GESTION'),
            'tipo_pago' => (string) ($venta->tipo_pago ?? ''),
            'total' => (float) ($venta->total ?? 0),
            'verificado' => (bool) ($venta->verificado ?? false),
            'verificado_at' => optional($venta->verificado_at)->format('Y-m-d H:i:s'),
            'verificado_por' => (string) ($venta->verificador?->name ?? ''),
            'observaciones' => (string) ($venta->pedido?->observaciones ?? ''),
            'productos' => $productos,
        ];
    }

    private function authorizeVerificacion(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 401, 'No autenticado');

        $isAdmin = strtoupper((string) ($user->role ?? '')) === 'ADMIN';
        $canVerificacion = method_exists($user, 'can') && $user->can('Verificacion');
        abort_unless($isAdmin || $canVerificacion, 403, 'No autorizado');
    }
}
