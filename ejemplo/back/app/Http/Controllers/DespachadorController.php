<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DespachadorController extends Controller
{
    public function rutas(Request $request)
    {
        $this->authorizeRutas($request);
        $user = $request->user();

        $data = $request->validate([
            'fecha' => 'nullable|date',
            'usuario_camion_id' => 'nullable|integer|exists:users,id',
            'search' => 'nullable|string|max:120',
        ]);

        $fecha = $data['fecha'] ?? now()->toDateString();
        $camionId = $this->resolveCamionId($user, $data['usuario_camion_id'] ?? null);
        $search = mb_strtolower(trim((string) ($data['search'] ?? '')));

        $items = Pedido::query()
            ->with([
                'cliente:id,nombre,direccion,telefono,latitud,longitud,territorio,codcli,ci',
                'user:id,name',
                'usuarioCamion:id,name,placa',
                'zona:id,nombre,color,orden',
                'venta:id,pedido_id,total,tipo_pago,facturado,factura_estado,estado',
                'venta.pagos:id,venta_id,monto,correcciones,estado',
                'detalles:id,pedido_id,producto_id,cantidad',
                'detalles.producto:id,codigo,nombre,tipo',
            ])
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->whereDate('fecha', $fecha)
            ->where('estado', 'Enviado')
            ->where('usuario_camion_id', $camionId)
            ->orderBy('hora')
            ->orderBy('id')
            ->get();

        if ($search !== '') {
            $items = $items->filter(function (Pedido $p) use ($search) {
                $productos = $p->detalles->map(fn ($d) => $d->producto?->nombre ?: '')->implode(' ');
                $stack = mb_strtolower(implode(' ', [
                    (string) $p->id,
                    (string) ($p->cliente?->nombre ?? ''),
                    (string) ($p->cliente?->direccion ?? ''),
                    (string) ($p->cliente?->codcli ?? ''),
                    (string) ($p->cliente?->ci ?? ''),
                    $productos,
                ]));
                return str_contains($stack, $search);
            })->values();
        }

        $rows = $items->map(function (Pedido $pedido) {
            $venta = $pedido->venta ?: Venta::query()->with('pagos:id,venta_id,monto,correcciones,estado')->where('pedido_id', $pedido->id)->first();
            $pagos = $this->pagosActivos($venta?->pagos);
            $pagoInicial = $this->pagoInicialActivo($pagos);
            $cobrado = round((float) ($pagoInicial?->monto ?? 0), 2);
            $total = round((float) ($venta?->total ?? $pedido->total ?? 0), 2);
            $saldo = $this->saldoConTolerancia($total, $cobrado);

            return [
                'pedido_id' => $pedido->id,
                'venta_id' => $venta?->id,
                'comanda' => $pedido->id,
                'cliente_id' => $pedido->cliente?->id,
                'cliente' => $pedido->cliente?->nombre,
                'telefono' => $pedido->cliente?->telefono,
                'direccion' => $pedido->cliente?->direccion,
                'territorio' => $pedido->cliente?->territorio,
                'latitud' => is_numeric($pedido->cliente?->latitud) ? (float) $pedido->cliente->latitud : null,
                'longitud' => is_numeric($pedido->cliente?->longitud) ? (float) $pedido->cliente->longitud : null,
                'vendedor' => $pedido->user?->name,
                'camion' => $pedido->usuarioCamion?->name,
                'zona' => $pedido->zona?->nombre,
                'tipo_pago_venta' => $venta?->tipo_pago ?: $pedido->tipo_pago,
                'facturado' => (bool) ($venta?->facturado ?? $pedido->facturado),
                'factura_estado' => (string) ($venta?->factura_estado ?? 'SIN_GESTION'),
                'total' => $total,
                'cobrado' => $cobrado,
                'pagado' => $cobrado,
                'saldo' => $saldo,
                'ultimo_pago' => round((float) ($pagoInicial?->monto ?? 0), 2),
                'despacho_estado' => $pedido->despacho_estado ?: 'PENDIENTE',
                'pagos' => collect($pagoInicial ? [$pagoInicial] : [])->map(function ($pg) {
                    return [
                        'id' => $pg->id,
                        'monto' => round((float) $pg->monto, 2),
                        'correcciones' => (int) ($pg->correcciones ?? 0),
                    ];
                })->values(),
                'productos' => $pedido->detalles->map(function ($d) {
                    return [
                        'codigo' => $d->producto?->codigo,
                        'nombre' => $d->producto?->nombre,
                        'cantidad' => (float) $d->cantidad,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'data' => $rows,
            'stats' => [
                'total_entregas' => $rows->count(),
                'monto_total' => round((float) $rows->sum('total'), 2),
                'monto_cobrado' => round((float) $rows->sum('cobrado'), 2),
                'saldo_total' => round((float) $rows->sum('saldo'), 2),
            ],
        ]);
    }

    public function actualizarEstadoPedido(Request $request, Pedido $pedido)
    {
        $this->authorizeDespacho($request);
        $user = $request->user();

        $data = $request->validate([
            'estado' => 'required|string|in:ENTREGADO,NO ENTREGADO,RECHAZADO,PARCIAL,PENDIENTE',
        ]);

        $camionId = $this->resolveCamionId($user, $request->integer('usuario_camion_id'));
        if ((int) $pedido->usuario_camion_id !== (int) $camionId) {
            return response()->json(['message' => 'El pedido no pertenece al camion seleccionado'], 422);
        }

        $pedido->despacho_estado = strtoupper((string) $data['estado']);
        if (in_array($pedido->despacho_estado, ['ENTREGADO', 'PARCIAL', 'RECHAZADO', 'NO ENTREGADO'], true)) {
            $pedido->entrega_at = now();
            $pedido->despachador_user_id = $user->id;
        }
        $pedido->save();

        return response()->json([
            'message' => 'Estado de despacho actualizado',
            'pedido_id' => $pedido->id,
            'despacho_estado' => $pedido->despacho_estado,
        ]);
    }

    public function registrarPago(Request $request)
    {
        $this->authorizeDespacho($request);
        $user = $request->user();

        $data = $request->validate([
            'venta_id' => 'required|integer|exists:ventas,id',
            'monto' => 'required|numeric|min:0.01',
            'tipo_pago' => 'required|string|in:CONTADO,CREDITO',
            'metodo_pago' => 'required|string|in:EFECTIVO,QR,TRANSFERENCIA,OTRO',
            'observacion' => 'nullable|string|max:600',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
        ]);

        return DB::transaction(function () use ($data, $user) {
            $result = $this->registrarCobroVenta($data, $user, false);
            return response()->json([
                'message' => 'Pago registrado',
                'pago' => $result['pago'],
                'resumen' => $result['resumen'],
            ]);
        });
    }

    public function registrarPagosLote(Request $request)
    {
        $this->authorizeDespacho($request);
        $user = $request->user();

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.venta_id' => 'required|integer|exists:ventas,id',
            'items.*.pedido_id' => 'nullable|integer|exists:pedidos,id',
            'items.*.monto' => 'required|numeric|min:0',
            'items.*.tipo_pago' => 'required|string|in:CONTADO,CREDITO',
            'items.*.metodo_pago' => 'required|string|in:EFECTIVO,QR,TRANSFERENCIA,OTRO',
            'items.*.observacion' => 'nullable|string|max:600',
            'items.*.latitud' => 'nullable|numeric',
            'items.*.longitud' => 'nullable|numeric',
        ]);

        return DB::transaction(function () use ($data, $user) {
            $rows = [];
            foreach ($data['items'] as $item) {
                $rows[] = $this->registrarCobroVenta($item, $user, true);
            }

            return response()->json([
                'message' => 'Pagos registrados',
                'data' => collect($rows)->map(fn ($row) => [
                    'pedido_id' => $row['pedido_id'],
                    'venta_id' => $row['venta_id'],
                    'pago' => $row['pago'],
                    'resumen' => $row['resumen'],
                ])->values(),
            ]);
        });
    }

    public function despacho(Request $request)
    {
        $this->authorizeDespacho($request);
        $user = $request->user();

        $data = $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'usuario_camion_id' => 'nullable|integer|exists:users,id',
        ]);

        $fechaInicio = $data['fecha_inicio'] ?? now()->toDateString();
        $fechaFin = $data['fecha_fin'] ?? now()->toDateString();
        $camionId = $this->resolveCamionId($user, $data['usuario_camion_id'] ?? null);

        $pedidos = Pedido::query()
            ->with([
                'cliente:id,nombre,ci,codcli,direccion,telefono',
                'user:id,name',
                'usuarioCamion:id,name,placa',
                'zona:id,nombre,color,orden',
                'venta:id,pedido_id,total,tipo_pago,facturado,factura_estado,estado',
                'venta.pagos:id,venta_id,monto,tipo_pago,metodo_pago,fecha_hora,observacion,user_id',
                'venta.pagos.user:id,name',
                'detalles:id,pedido_id,producto_id,cantidad',
                'detalles.producto:id,codigo,nombre,tipo',
            ])
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->where('estado', 'Enviado')
            ->where('usuario_camion_id', $camionId)
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $rows = $pedidos->map(function (Pedido $pedido) {
            $venta = $pedido->venta ?: Venta::query()->with(['pagos.user:id,name'])->where('pedido_id', $pedido->id)->first();
            $pagos = $this->pagosActivos($venta?->pagos);
            $pagoInicial = $this->pagoInicialActivo($pagos);
            $cobrado = round((float) ($pagoInicial?->monto ?? 0), 2);
            $total = round((float) ($venta?->total ?? $pedido->total ?? 0), 2);
            $saldo = $this->saldoConTolerancia($total, $cobrado);

            return [
                'pedido_id' => $pedido->id,
                'venta_id' => $venta?->id,
                'comanda' => $pedido->id,
                'fecha' => (string) ($pedido->fecha ?? ''),
                'hora' => (string) ($pedido->hora ?? ''),
                'cliente' => $pedido->cliente?->nombre,
                'ci' => (string) ($pedido->cliente?->ci ?? ''),
                'vendedor' => $pedido->user?->name,
                'camion' => $pedido->usuarioCamion?->name,
                'placa' => $pedido->usuarioCamion?->placa,
                'zona' => $pedido->zona?->nombre,
                'tipo_pago' => (string) ($venta?->tipo_pago ?: $pedido->tipo_pago),
                'despacho_estado' => (string) ($pedido->despacho_estado ?: 'PENDIENTE'),
                'facturado' => (bool) ($venta?->facturado ?? false),
                'factura_estado' => (string) ($venta?->factura_estado ?? 'SIN_GESTION'),
                'total' => $total,
                'cobrado' => $cobrado,
                'pagado' => $cobrado,
                'saldo' => $saldo,
                'productos' => $pedido->detalles->map(function ($d) {
                    return [
                        'codigo' => $d->producto?->codigo,
                        'nombre' => $d->producto?->nombre,
                        'tipo' => strtoupper((string) ($d->producto?->tipo ?? 'NORMAL')),
                        'cantidad' => (float) $d->cantidad,
                    ];
                })->values(),
                'pagos' => collect($pagoInicial ? [$pagoInicial] : [])->map(function (Pago $p) {
                    return [
                        'id' => $p->id,
                        'fecha_hora' => optional($p->fecha_hora)->format('Y-m-d H:i:s'),
                        'monto' => round((float) $p->monto, 2),
                        'correcciones' => (int) ($p->correcciones ?? 0),
                        'tipo_pago' => $p->tipo_pago,
                        'metodo_pago' => $p->metodo_pago,
                        'observacion' => $p->observacion,
                        'registrado_por' => $p->user?->name,
                    ];
                })->values(),
            ];
        })->values();

        $pagos = Pago::query()
            ->with([
                'pedido:id,usuario_camion_id',
            ])
            ->whereBetween('fecha_hora', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59'])
            ->whereHas('pedido', function (Builder $q) use ($camionId) {
                $q->where('usuario_camion_id', $camionId);
            })
            ->orderBy('fecha_hora', 'desc')
            ->get();

        $resumen = [
            'total_pagos' => $pagos->count(),
            'total_entregas' => $rows->count(),
            'monto_total' => round((float) $rows->sum('total'), 2),
            'monto_cobrado' => round((float) $rows->sum('cobrado'), 2),
            'saldo_total' => round((float) $rows->sum('saldo'), 2),
            'contado' => round((float) $pagos->where('tipo_pago', 'CONTADO')->sum('monto'), 2),
            'credito' => round((float) $pagos->where('tipo_pago', 'CREDITO')->sum('monto'), 2),
        ];

        return response()->json([
            'data' => $rows,
            'stats' => $resumen,
        ]);
    }

    public function imprimirVouchers(Request $request)
    {
        $this->authorizeDespacho($request);
        [$fechaInicio, $fechaFin] = $this->extractRangoFechas($request);
        $camionId = $this->resolveCamionId($request->user(), $request->integer('usuario_camion_id'));

        $ventas = Venta::query()
            ->with([
                'pedido:id,user_id,cliente_id,usuario_camion_id,fecha,hora,tipo_pago,observaciones',
                'pedido.user:id,name',
                'pedido.cliente:id,nombre,codcli,ci,telefono',
                'user:id,name',
                'cliente:id,nombre,codcli,ci,telefono',
                'ventaDetalles:id,venta_id,producto_id,cantidad,precio,nombre',
                'ventaDetalles.producto:id,codigo,nombre,tipo',
            ])
            ->whereNotNull('pedido_id')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->whereHas('pedido', function (Builder $q) use ($camionId) {
                $q->where('tipo_pedido', 'REALIZAR_PEDIDO')
                    ->where('usuario_camion_id', $camionId);
            })
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        if ($ventas->isEmpty()) {
            return response()->json(['message' => 'No hay ventas para imprimir vouchers en el rango seleccionado'], 422);
        }

        $pdf = Pdf::loadView('pdf.digitador_vouchers_masivo', [
            'ventas' => $ventas,
            'razon' => (string) env('RAZON'),
        ])->setPaper('letter');

        return $pdf->download("vouchers_despacho_{$fechaInicio}_{$fechaFin}.pdf");
    }

    public function imprimirVoucherVenta(Request $request, Venta $venta)
    {
        $this->authorizeDespacho($request);
        $camionId = $this->resolveCamionId($request->user(), $request->integer('usuario_camion_id'));

        $venta->loadMissing([
            'pedido:id,user_id,cliente_id,usuario_camion_id,fecha,hora,tipo_pago,observaciones',
            'pedido.user:id,name',
            'pedido.cliente:id,nombre,codcli,ci,telefono',
            'user:id,name',
            'cliente:id,nombre,codcli,ci,telefono',
            'ventaDetalles:id,venta_id,producto_id,cantidad,precio,nombre',
            'ventaDetalles.producto:id,codigo,nombre,tipo',
        ]);

        if (!$venta->pedido_id || (int) ($venta->pedido?->usuario_camion_id ?? 0) !== (int) $camionId) {
            return response()->json(['message' => 'La venta no pertenece al camion seleccionado'], 422);
        }

        $pdf = Pdf::loadView('pdf.digitador_vouchers_masivo', [
            'ventas' => collect([$venta]),
            'razon' => (string) env('RAZON'),
        ])->setPaper('letter');

        return $pdf->download("voucher_despacho_venta_{$venta->id}.pdf");
    }

    private function extractRangoFechas(Request $request): array
    {
        $data = $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
        ]);

        return [
            $data['fecha_inicio'] ?? now()->toDateString(),
            $data['fecha_fin'] ?? now()->toDateString(),
        ];
    }

    private function resolveCamionId($user, ?int $camionId): int
    {
        $isAdmin = strtoupper((string) ($user->role ?? '')) === 'ADMIN';
        if ($isAdmin && $camionId) {
            return $camionId;
        }
        if (!$isAdmin && !$user->es_camion) {
            abort(403, 'El usuario no es camion');
        }
        return (int) $user->id;
    }

    private function authorizeRutas(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 401, 'No autenticado');
        $isAdmin = strtoupper((string) ($user->role ?? '')) === 'ADMIN';
        $can = method_exists($user, 'can') && $user->can('Rutas');
        abort_unless($isAdmin || $can || $user->es_camion, 403, 'No autorizado');
    }

    private function authorizeDespacho(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 401, 'No autenticado');
        $isAdmin = strtoupper((string) ($user->role ?? '')) === 'ADMIN';
        $can = method_exists($user, 'can') && $user->can('Despacho');
        abort_unless($isAdmin || $can || $user->es_camion, 403, 'No autorizado');
    }

    public function actualizarPago(Request $request, Pago $pago)
    {
        $this->authorizeDespacho($request);
        $user = $request->user();
        $data = $request->validate([
            'monto' => 'required|numeric|min:0.01',
        ]);

        return DB::transaction(function () use ($request, $user, $pago, $data) {
            $pago = Pago::query()->lockForUpdate()->findOrFail($pago->id);
            $venta = Venta::query()->with(['pagos', 'pedido'])->lockForUpdate()->findOrFail((int) $pago->venta_id);
            $pedido = $venta->pedido ?: Pedido::query()->where('venta_id', $venta->id)->first();
            $camionId = $this->resolveCamionId($user, $request->integer('usuario_camion_id'));
            $pagoInicial = $this->pagoInicialActivo($this->pagosActivos($venta->pagos));

            if ((int) ($pedido?->usuario_camion_id ?? 0) !== (int) $camionId) {
                return response()->json(['message' => 'No autorizado para modificar este pago'], 422);
            }
            if ((int) ($pagoInicial?->id ?? 0) !== (int) $pago->id) {
                return response()->json(['message' => 'Solo puede corregirse el cobro inicial desde rutas camion'], 422);
            }
            if ((int) ($pago->correcciones ?? 0) >= 1) {
                return response()->json(['message' => 'Este cobro solo puede corregirse una vez'], 422);
            }

            $montoNuevo = round((float) $data['monto'], 2);
            $total = round((float) ($venta->total ?? 0), 2);
            if (($montoNuevo - $total) > 0.0001) {
                return response()->json(['message' => 'El monto supera el total de la venta'], 422);
            }

            $pago->monto = $montoNuevo;
            $pago->correcciones = (int) ($pago->correcciones ?? 0) + 1;
            $pago->save();

            $pagadoNuevo = round($montoNuevo, 2);
            $saldoNuevo = $this->saldoConTolerancia($total, $pagadoNuevo);

            if ($pedido) {
                $pedido->despacho_estado = $saldoNuevo <= 0 ? 'ENTREGADO' : 'PARCIAL';
                $pedido->entrega_at = now();
                $pedido->despachador_user_id = $user->id;
                $pedido->save();
            }

            return response()->json([
                'message' => 'Pago modificado',
                'pago' => $pago->fresh(),
                'resumen' => [
                    'total' => $total,
                    'cobrado' => $pagadoNuevo,
                    'pagado' => $pagadoNuevo,
                    'saldo' => $saldoNuevo,
                    'cancelado' => $saldoNuevo <= 0,
                ],
            ]);
        });
    }

    private function pagosActivos($pagos)
    {
        return collect($pagos ?: [])->filter(function ($pago) {
            return strtoupper((string) ($pago->estado ?? 'ACTIVO')) === 'ACTIVO';
        })->values();
    }

    private function registrarCobroVenta(array $data, $user, bool $allowZero): array
    {
        $venta = Venta::query()->with(['pedido', 'pagos'])->lockForUpdate()->findOrFail((int) $data['venta_id']);
        $pedido = $venta->pedido ?: Pedido::query()->where('venta_id', $venta->id)->first();

        $total = round((float) ($venta->total ?? 0), 2);
        $pagosActivos = $this->pagosActivos($venta->pagos);
        $pagoInicial = $this->pagoInicialActivo($pagosActivos);
        $pagadoActual = round((float) ($pagoInicial?->monto ?? 0), 2);
        $saldo = round(max(0, $total - $pagadoActual), 2);
        $monto = round((float) $data['monto'], 2);
        $tipoPago = strtoupper((string) $data['tipo_pago']);
        $metodoPago = strtoupper((string) $data['metodo_pago']);

        if ($pagoInicial) {
            abort(422, 'El cobro inicial ya fue registrado');
        }
        if (!$allowZero && $monto <= 0) {
            abort(422, 'El monto debe ser mayor a 0');
        }
        if ($allowZero && $monto < 0) {
            abort(422, 'El monto no puede ser negativo');
        }
        if ($monto - $saldo > 0.0001) {
            abort(422, 'El monto supera el saldo pendiente');
        }
        if ($tipoPago === 'CONTADO' && ($saldo - $monto) > 0.99) {
            abort(422, 'Para contado se permite diferencia maxima de 0.99');
        }

        $pago = null;
        if ($monto > 0) {
            $pago = Pago::create([
                'venta_id' => $venta->id,
                'pedido_id' => $pedido?->id,
                'cliente_id' => $venta->cliente_id,
                'user_id' => $user->id,
                'tipo_pago' => $tipoPago,
                'metodo_pago' => $metodoPago,
                'monto' => $monto,
                'correcciones' => 0,
                'fecha_hora' => now(),
                'observacion' => $data['observacion'] ?? null,
                'latitud' => $data['latitud'] ?? null,
                'longitud' => $data['longitud'] ?? null,
            ]);
        }

        $pagadoNuevo = round($pagadoActual + $monto, 2);
        $saldoNuevo = $this->saldoConTolerancia($total, $pagadoNuevo);

        if ($pedido) {
            $pedido->despacho_estado = $saldoNuevo <= 0 ? 'ENTREGADO' : 'PARCIAL';
            $pedido->entrega_at = now();
            $pedido->despachador_user_id = $user->id;
            $pedido->save();
        }

        return [
            'pedido_id' => $pedido?->id,
            'venta_id' => $venta->id,
            'pago' => $pago,
            'resumen' => [
                'total' => round($total, 2),
                'cobrado' => round($monto, 2),
                'pagado' => round($monto, 2),
                'saldo' => round($saldoNuevo, 2),
                'cancelado' => $saldoNuevo <= 0,
            ],
        ];
    }

    private function pagoInicialActivo($pagos): ?Pago
    {
        $items = collect($pagos ?: []);
        return $items->sortBy('id')->first();
    }

    private function saldoConTolerancia(float $total, float $pagado): float
    {
        $saldo = round(max(0, $total - $pagado), 2);
        return $saldo <= 0.99 ? 0.0 : $saldo;
    }
}
