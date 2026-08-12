<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\CobranzasDeuda;
use App\Models\CobranzasDeudaPago;
use App\Models\Pago;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CobranzasController extends Controller
{
    public function deudores(Request $request)
    {
        $this->authorizeCobranzas($request);
        $data = $request->validate([
            'search' => 'nullable|string|max:120',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
        ]);

        $search = mb_strtolower(trim((string) ($data['search'] ?? '')));
        $fechaInicio = $data['fecha_inicio'] ?? null;
        $fechaFin = $data['fecha_fin'] ?? null;

        $ventasCredito = Venta::query()
            ->with([
                'cliente:id,nombre,ci,telefono,direccion',
                'pagos:id,venta_id,user_id,monto,estado,fecha_hora,anulado_at,anulado_user_id,metodo_pago,considerar_en_cobranza,nro_pago,observacion,comprobante_path',
                'pagos.user:id,name',
                'pagos.anuladoPor:id,name',
            ])
            ->where('estado', 'Activo')
            ->when($fechaInicio && $fechaFin, fn (Builder $q) => $q->whereBetween('fecha', [$fechaInicio, $fechaFin]))
            ->when($fechaInicio && !$fechaFin, fn (Builder $q) => $q->whereDate('fecha', '>=', $fechaInicio))
            ->when(!$fechaInicio && $fechaFin, fn (Builder $q) => $q->whereDate('fecha', '<=', $fechaFin))
            ->where(function (Builder $q) {
                $q->whereRaw('UPPER(tipo_pago) LIKE ?', ['%CRED%']);
            })
            ->where('considerar_en_cobranza', true)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $deudasManuales = CobranzasDeuda::query()
            ->with([
                'cliente:id,nombre,ci,telefono,direccion',
                'pagos:id,deuda_id,user_id,monto,estado,fecha_hora,anulado_at,anulado_user_id,metodo_pago,considerar_en_cobranza,nro_pago,observacion,comprobante_path',
                'pagos.user:id,name',
                'pagos.anuladoPor:id,name',
            ])
            ->whereIn('estado', ['ACTIVA', 'PENDIENTE'])
            ->where('considerar_en_cobranza', true)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $map = collect();

        foreach ($ventasCredito as $venta) {
            $total = round((float) ($venta->total ?? 0), 2);
            $pagado = round((float) $this->sumPagosActivos($venta->pagos), 2);
            $saldo = $this->saldoConTolerancia($total, $pagado, 0.99);
            if ($saldo <= 0) {
                continue;
            }

            $key = 'cli-' . ($venta->cliente_id ?? ('venta-' . $venta->id));
            $row = $map->get($key, $this->emptyClienteRow(
                $key,
                $venta->cliente_id,
                (string) ($venta->cliente?->nombre ?: ($venta->nombre ?: 'Cliente sin nombre')),
                (string) ($venta->cliente?->ci ?: $venta->ci),
                (string) ($venta->cliente?->telefono ?? ''),
                (string) ($venta->cliente?->direccion ?? '')
            ));

            $row['monto_total'] += $total;
            $row['cobrado_total'] += $pagado;
            $row['saldo_total'] += $saldo;
            $row['items'][] = [
                'tipo' => 'VENTA',
                'id' => $venta->id,
                'referencia' => 'Venta #' . $venta->id,
                'fecha' => (string) ($venta->fecha ?? ''),
                'tipo_pago' => (string) ($venta->tipo_pago ?? ''),
                'monto_total' => $total,
                'cobrado' => $pagado,
                'saldo' => $saldo,
                'estado' => (string) ($venta->estado ?? ''),
                'considerar_en_cobranza' => (bool) ($venta->considerar_en_cobranza ?? true),
                'tolerancia_centavos' => 0.99,
                'pagos' => $venta->pagos->map(fn (Pago $p) => [
                    'id' => $p->id,
                    'monto' => round((float) $p->monto, 2),
                    'fecha_hora' => optional($p->fecha_hora)->format('Y-m-d H:i:s'),
                    'metodo_pago' => $p->metodo_pago,
                    'considerar_en_cobranza' => (bool) $p->considerar_en_cobranza,
                    'nro_pago' => $p->nro_pago,
                    'observacion' => $p->observacion,
                    'comprobante_path' => $p->comprobante_path,
                    'comprobante_url' => $this->comprobanteUrl($p->comprobante_path),
                    'registrado_por' => $p->user?->name,
                ])->values(),
            ];
            $map->put($key, $row);
        }

        foreach ($deudasManuales as $deuda) {
            $total = round((float) ($deuda->monto_total ?? 0), 2);
            $pagado = round((float) $this->sumPagosActivos($deuda->pagos), 2);
            $tol = round((float) ($deuda->tolerancia_centavos ?? 0.99), 2);
            $saldo = $this->saldoConTolerancia($total, $pagado, $tol);
            if ($saldo <= 0) {
                continue;
            }

            $nombre = (string) ($deuda->cliente?->nombre ?: $deuda->nombre_cliente ?: 'Cliente manual');
            $ci = (string) ($deuda->cliente?->ci ?: $deuda->ci_nit ?: '');
            $tel = (string) ($deuda->cliente?->telefono ?: $deuda->telefono ?: '');
            $dir = (string) ($deuda->cliente?->direccion ?: $deuda->direccion ?: '');
            $key = $deuda->cliente_id ? ('cli-' . $deuda->cliente_id) : ('man-' . $deuda->id);
            $row = $map->get($key, $this->emptyClienteRow($key, $deuda->cliente_id, $nombre, $ci, $tel, $dir));

            $row['monto_total'] += $total;
            $row['cobrado_total'] += $pagado;
            $row['saldo_total'] += $saldo;
            $row['items'][] = [
                'tipo' => 'DEUDA_MANUAL',
                'id' => $deuda->id,
                'referencia' => 'Deuda manual #' . $deuda->id,
                'fecha' => (string) ($deuda->fecha ?? ''),
                'tipo_pago' => 'CREDITO',
                'monto_total' => $total,
                'cobrado' => $pagado,
                'saldo' => $saldo,
                'estado' => (string) ($deuda->estado ?? 'ACTIVA'),
                'considerar_en_cobranza' => (bool) ($deuda->considerar_en_cobranza ?? true),
                'tolerancia_centavos' => $tol,
                'pagos' => $deuda->pagos->map(fn (CobranzasDeudaPago $p) => [
                    'id' => $p->id,
                    'monto' => round((float) $p->monto, 2),
                    'fecha_hora' => optional($p->fecha_hora)->format('Y-m-d H:i:s'),
                    'metodo_pago' => $p->metodo_pago,
                    'considerar_en_cobranza' => (bool) $p->considerar_en_cobranza,
                    'nro_pago' => $p->nro_pago,
                    'observacion' => $p->observacion,
                    'comprobante_path' => $p->comprobante_path,
                    'comprobante_url' => $this->comprobanteUrl($p->comprobante_path),
                    'registrado_por' => $p->user?->name,
                ])->values(),
            ];
            $map->put($key, $row);
        }

        $rows = $map->values()
            ->map(function (array $row) {
                $row['monto_total'] = round((float) $row['monto_total'], 2);
                $row['cobrado_total'] = round((float) $row['cobrado_total'], 2);
                $row['saldo_total'] = round((float) $row['saldo_total'], 2);
                $row['documentos'] = count($row['items']);
                return $row;
            })
            ->filter(function (array $row) use ($search) {
                if ($search === '') {
                    return true;
                }
                $stack = mb_strtolower(implode(' ', [
                    $row['cliente_nombre'] ?? '',
                    $row['ci_nit'] ?? '',
                    $row['telefono'] ?? '',
                    $row['direccion'] ?? '',
                ]));
                return str_contains($stack, $search);
            })
            ->sortByDesc('saldo_total')
            ->values();

        return response()->json([
            'data' => $rows,
            'stats' => [
                'deudores' => $rows->count(),
                'monto_total' => round((float) $rows->sum('monto_total'), 2),
                'cobrado_total' => round((float) $rows->sum('cobrado_total'), 2),
                'saldo_total' => round((float) $rows->sum('saldo_total'), 2),
            ],
        ]);
    }

    public function historialClientes(Request $request)
    {
        $this->authorizeCobranzas($request);
        $data = $request->validate([
            'search' => 'nullable|string|max:120',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:10|max:200',
        ]);

        $search = mb_strtolower(trim((string) ($data['search'] ?? '')));
        $perPage = (int) ($data['per_page'] ?? 50);

        $clientes = Cliente::query()
            ->select('id', 'nombre', 'ci', 'telefono', 'direccion')
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%' . $search . '%'])
                    ->orWhereRaw('LOWER(ci) LIKE ?', ['%' . $search . '%'])
                    ->orWhereRaw('LOWER(telefono) LIKE ?', ['%' . $search . '%'])
                    ->orWhereRaw('LOWER(direccion) LIKE ?', ['%' . $search . '%']);
            })
            ->orderBy('nombre')
            ->paginate($perPage);

        $clienteIds = collect($clientes->items())->pluck('id')->values();

        $ventas = Venta::query()
            ->with(['pagos:id,venta_id,monto,estado'])
            ->whereIn('cliente_id', $clienteIds)
            ->where(function (Builder $q) {
                $q->whereRaw('UPPER(tipo_pago) LIKE ?', ['%CRED%']);
            })
            ->whereRaw('UPPER(COALESCE(estado, ?)) <> ?', ['ACTIVO', 'ANULADO'])
            ->get();

        $deudas = CobranzasDeuda::query()
            ->with(['pagos:id,deuda_id,monto,estado'])
            ->whereIn('cliente_id', $clienteIds)
            ->get();

        $ventasByCliente = $ventas->groupBy('cliente_id');
        $deudasByCliente = $deudas->groupBy('cliente_id');

        $rows = collect($clientes->items())->map(function (Cliente $c) use ($ventasByCliente, $deudasByCliente) {
            $ventasCliente = collect($ventasByCliente->get($c->id, collect()));
            $deudasCliente = collect($deudasByCliente->get($c->id, collect()));
            $ventasConsideradas = $ventasCliente->filter(fn (Venta $v) => (bool) ($v->considerar_en_cobranza ?? true));
            $deudasConsideradas = $deudasCliente->filter(fn (CobranzasDeuda $d) => (bool) ($d->considerar_en_cobranza ?? true));

            $totalVentas = round((float) $ventasCliente->sum(fn (Venta $v) => (float) ($v->total ?? 0)), 2);
            $pagadoVentas = round((float) $ventasCliente->sum(fn (Venta $v) => (float) $this->sumPagosActivos($v->pagos)), 2);
            $saldoVentas = round((float) $ventasConsideradas->sum(function (Venta $v) {
                $total = round((float) ($v->total ?? 0), 2);
                $pagado = round((float) $this->sumPagosActivos($v->pagos), 2);
                return $this->saldoConTolerancia($total, $pagado, 0.99);
            }), 2);

            $totalDeudas = round((float) $deudasCliente->sum(fn (CobranzasDeuda $d) => (float) ($d->monto_total ?? 0)), 2);
            $pagadoDeudas = round((float) $deudasCliente->sum(fn (CobranzasDeuda $d) => (float) $this->sumPagosActivos($d->pagos)), 2);
            $saldoDeudas = round((float) $deudasConsideradas->sum(function (CobranzasDeuda $d) {
                $total = round((float) ($d->monto_total ?? 0), 2);
                $pagado = round((float) $this->sumPagosActivos($d->pagos), 2);
                $tol = round((float) ($d->tolerancia_centavos ?? 0.99), 2);
                return $this->saldoConTolerancia($total, $pagado, $tol);
            }), 2);

            $documentos = $ventasCliente->count() + $deudasCliente->count();
            $pagos = (int) $ventasCliente->sum(fn (Venta $v) => collect($v->pagos)->count())
                + (int) $deudasCliente->sum(fn (CobranzasDeuda $d) => collect($d->pagos)->count());

            $saldoTotal = round($saldoVentas + $saldoDeudas, 2);
            $cobradoTotal = round($pagadoVentas + $pagadoDeudas, 2);
            $montoTotal = round($totalVentas + $totalDeudas, 2);
            $documentosConsiderados = $ventasConsideradas->count() + $deudasConsideradas->count();
            $estado = $saldoTotal > 0 ? 'DEBE' : ($documentosConsiderados > 0 ? 'SALDADO' : 'NO_CONSIDERAR');

            return [
                'cliente_id' => $c->id,
                'cliente_nombre' => (string) ($c->nombre ?? ''),
                'ci_nit' => (string) ($c->ci ?? ''),
                'telefono' => (string) ($c->telefono ?? ''),
                'direccion' => (string) ($c->direccion ?? ''),
                'documentos' => $documentos,
                'pagos_total' => $pagos,
                'monto_total' => $montoTotal,
                'cobrado_total' => $cobradoTotal,
                'saldo_total' => $saldoTotal,
                'estado' => $estado,
            ];
        })->values();

        return response()->json([
            'data' => $rows,
            'pagination' => [
                'page' => $clientes->currentPage(),
                'per_page' => $clientes->perPage(),
                'total' => $clientes->total(),
                'last_page' => $clientes->lastPage(),
            ],
            'stats' => [
                'clientes' => $clientes->total(),
                'documentos' => (int) $rows->sum('documentos'),
                'pagos' => (int) $rows->sum('pagos_total'),
                'monto_total' => round((float) $rows->sum('monto_total'), 2),
                'cobrado_total' => round((float) $rows->sum('cobrado_total'), 2),
                'saldo_total' => round((float) $rows->sum('saldo_total'), 2),
            ],
        ]);
    }

    public function crearDeudaManual(Request $request)
    {
        $this->authorizeCobranzas($request);
        $data = $request->validate([
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'nombre_cliente' => 'nullable|string|max:255',
            'ci_nit' => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:100',
            'direccion' => 'nullable|string|max:500',
            'monto_total' => 'required|numeric|min:0.01',
            'tolerancia_centavos' => 'nullable|numeric|min:0|max:2',
            'fecha' => 'nullable|date',
            'observacion' => 'nullable|string|max:600',
        ]);

        if (empty($data['cliente_id']) && empty(trim((string) ($data['nombre_cliente'] ?? '')))) {
            return response()->json(['message' => 'Debe seleccionar cliente o ingresar nombre manual'], 422);
        }

        $deuda = CobranzasDeuda::create([
            'cliente_id' => $data['cliente_id'] ?? null,
            'user_id' => $request->user()->id,
            'nombre_cliente' => $data['nombre_cliente'] ?? null,
            'ci_nit' => $data['ci_nit'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'monto_total' => round((float) $data['monto_total'], 2),
            'tolerancia_centavos' => isset($data['tolerancia_centavos']) ? round((float) $data['tolerancia_centavos'], 2) : 0.99,
            'considerar_en_cobranza' => true,
            'fecha' => $data['fecha'] ?? now()->toDateString(),
            'estado' => 'ACTIVA',
            'observacion' => $data['observacion'] ?? null,
        ]);

        return response()->json([
            'message' => 'Deuda manual creada',
            'deuda' => $deuda->fresh(['cliente:id,nombre,ci,telefono,direccion']),
        ]);
    }

    public function registrarPagoVenta(Request $request)
    {
        $this->authorizeCobranzas($request);
        $data = $request->validate([
            'venta_id' => 'required|integer|exists:ventas,id',
            'monto' => 'required|numeric|min:0.01',
            'metodo_pago' => 'nullable|string|in:EFECTIVO,QR,TRANSFERENCIA,OTRO',
            'nro_pago' => 'nullable|string|max:120',
            'observacion' => 'nullable|string|max:600',
            'comprobante' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        return DB::transaction(function () use ($request, $data) {
            $venta = Venta::query()->with('pagos')->lockForUpdate()->findOrFail((int) $data['venta_id']);
            if (!(bool) ($venta->considerar_en_cobranza ?? true)) {
                return response()->json(['message' => 'Esta venta esta marcada como no considerar en cobranza'], 422);
            }
            $total = round((float) ($venta->total ?? 0), 2);
            $pagado = round((float) $this->sumPagosActivos($venta->pagos), 2);
            $saldo = $this->saldoConTolerancia($total, $pagado, 0.99);
            $monto = round((float) $data['monto'], 2);
            if ($monto - $saldo > 0.0001) {
                return response()->json(['message' => 'El monto supera el saldo pendiente'], 422);
            }

            $path = $this->storeComprobante($request, 'comprobante');

            $pago = Pago::create([
                'venta_id' => $venta->id,
                'pedido_id' => $venta->pedido_id,
                'cliente_id' => $venta->cliente_id,
                'user_id' => $request->user()->id,
                'tipo_pago' => 'CREDITO',
                'metodo_pago' => strtoupper((string) ($data['metodo_pago'] ?? 'EFECTIVO')),
                'monto' => $monto,
                'estado' => 'ACTIVO',
                'fecha_hora' => now(),
                'observacion' => $data['observacion'] ?? null,
                'considerar_en_cobranza' => true,
                'nro_pago' => $data['nro_pago'] ?? null,
                'comprobante_path' => $path,
            ]);

            return response()->json([
                'message' => 'Pago registrado',
                'pago' => $this->mapPagoVenta($pago->fresh('user:id,name')),
            ]);
        });
    }

    public function actualizarPagoVenta(Request $request, Pago $pago)
    {
        $this->authorizeCobranzas($request);
        $data = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'metodo_pago' => 'nullable|string|in:EFECTIVO,QR,TRANSFERENCIA,OTRO',
            'nro_pago' => 'nullable|string|max:120',
            'observacion' => 'nullable|string|max:600',
            'comprobante' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        return DB::transaction(function () use ($request, $pago, $data) {
            $pago = Pago::query()->lockForUpdate()->findOrFail($pago->id);
            if (strtoupper((string) ($pago->estado ?? 'ACTIVO')) === 'ANULADO') {
                return response()->json(['message' => 'El pago esta anulado y no se puede modificar'], 422);
            }
            $venta = Venta::query()->with('pagos')->lockForUpdate()->findOrFail((int) $pago->venta_id);
            if (!(bool) ($venta->considerar_en_cobranza ?? true)) {
                return response()->json(['message' => 'Esta venta esta marcada como no considerar en cobranza'], 422);
            }
            $montoNuevo = round((float) $data['monto'], 2);
            $total = round((float) ($venta->total ?? 0), 2);
            $otros = round((float) $venta->pagos
                ->where('id', '!=', $pago->id)
                ->where('estado', '!=', 'ANULADO')
                ->sum('monto'), 2);
            if (($otros + $montoNuevo) - $total > 0.0001) {
                return response()->json(['message' => 'El monto supera el total pendiente'], 422);
            }

            $path = $this->storeComprobante($request, 'comprobante');
            $pago->monto = $montoNuevo;
            if (isset($data['metodo_pago'])) $pago->metodo_pago = strtoupper((string) $data['metodo_pago']);
            $pago->considerar_en_cobranza = true;
            if (array_key_exists('nro_pago', $data)) $pago->nro_pago = $data['nro_pago'];
            if (array_key_exists('observacion', $data)) $pago->observacion = $data['observacion'];
            if ($path) $pago->comprobante_path = $path;
            $pago->save();

            return response()->json([
                'message' => 'Pago actualizado',
                'pago' => $this->mapPagoVenta($pago->fresh('user:id,name')),
            ]);
        });
    }

    public function registrarPagoDeudaManual(Request $request, CobranzasDeuda $deuda)
    {
        $this->authorizeCobranzas($request);
        $data = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'metodo_pago' => 'nullable|string|in:EFECTIVO,QR,TRANSFERENCIA,OTRO',
            'nro_pago' => 'nullable|string|max:120',
            'observacion' => 'nullable|string|max:600',
            'comprobante' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        return DB::transaction(function () use ($request, $deuda, $data) {
            $deuda = CobranzasDeuda::query()->with('pagos')->lockForUpdate()->findOrFail($deuda->id);
            if (!(bool) ($deuda->considerar_en_cobranza ?? true)) {
                return response()->json(['message' => 'Esta deuda esta marcada como no considerar en cobranza'], 422);
            }
            $total = round((float) $deuda->monto_total, 2);
            $pagado = round((float) $this->sumPagosActivos($deuda->pagos), 2);
            $tol = round((float) ($deuda->tolerancia_centavos ?? 0.99), 2);
            $saldo = $this->saldoConTolerancia($total, $pagado, $tol);
            $monto = round((float) $data['monto'], 2);
            if ($monto - $saldo > 0.0001) {
                return response()->json(['message' => 'El monto supera el saldo de la deuda'], 422);
            }

            $path = $this->storeComprobante($request, 'comprobante');
            $pago = CobranzasDeudaPago::create([
                'deuda_id' => $deuda->id,
                'user_id' => $request->user()->id,
                'monto' => $monto,
                'estado' => 'ACTIVO',
                'fecha_hora' => now(),
                'metodo_pago' => strtoupper((string) ($data['metodo_pago'] ?? 'EFECTIVO')),
                'considerar_en_cobranza' => true,
                'nro_pago' => $data['nro_pago'] ?? null,
                'observacion' => $data['observacion'] ?? null,
                'comprobante_path' => $path,
            ]);

            return response()->json([
                'message' => 'Pago de deuda registrado',
                'pago' => $this->mapPagoDeuda($pago->fresh('user:id,name')),
            ]);
        });
    }

    public function actualizarPagoDeudaManual(Request $request, CobranzasDeudaPago $pago)
    {
        $this->authorizeCobranzas($request);
        $data = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'metodo_pago' => 'nullable|string|in:EFECTIVO,QR,TRANSFERENCIA,OTRO',
            'nro_pago' => 'nullable|string|max:120',
            'observacion' => 'nullable|string|max:600',
            'comprobante' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        return DB::transaction(function () use ($request, $pago, $data) {
            $pago = CobranzasDeudaPago::query()->lockForUpdate()->findOrFail($pago->id);
            if (strtoupper((string) ($pago->estado ?? 'ACTIVO')) === 'ANULADO') {
                return response()->json(['message' => 'El pago esta anulado y no se puede modificar'], 422);
            }
            $deuda = CobranzasDeuda::query()->with('pagos')->lockForUpdate()->findOrFail((int) $pago->deuda_id);
            if (!(bool) ($deuda->considerar_en_cobranza ?? true)) {
                return response()->json(['message' => 'Esta deuda esta marcada como no considerar en cobranza'], 422);
            }
            $montoNuevo = round((float) $data['monto'], 2);
            $total = round((float) $deuda->monto_total, 2);
            $otros = round((float) $deuda->pagos
                ->where('id', '!=', $pago->id)
                ->where('estado', '!=', 'ANULADO')
                ->sum('monto'), 2);
            if (($otros + $montoNuevo) - $total > 0.0001) {
                return response()->json(['message' => 'El monto supera el saldo de la deuda'], 422);
            }

            $path = $this->storeComprobante($request, 'comprobante');
            $pago->monto = $montoNuevo;
            if (isset($data['metodo_pago'])) $pago->metodo_pago = strtoupper((string) $data['metodo_pago']);
            $pago->considerar_en_cobranza = true;
            if (array_key_exists('nro_pago', $data)) $pago->nro_pago = $data['nro_pago'];
            if (array_key_exists('observacion', $data)) $pago->observacion = $data['observacion'];
            if ($path) $pago->comprobante_path = $path;
            $pago->save();

            return response()->json([
                'message' => 'Pago de deuda actualizado',
                'pago' => $this->mapPagoDeuda($pago->fresh('user:id,name')),
            ]);
        });
    }

    public function historialClienteDetalle(Request $request, Cliente $cliente)
    {
        $this->authorizeCobranzas($request);

        $ventas = Venta::query()
            ->with([
                'pagos:id,venta_id,user_id,monto,estado,fecha_hora,anulado_at,anulado_user_id,metodo_pago,considerar_en_cobranza,nro_pago,observacion,comprobante_path',
                'pagos.user:id,name',
                'pagos.anuladoPor:id,name',
            ])
            ->where('cliente_id', $cliente->id)
            ->where(function (Builder $q) {
                $q->whereRaw('UPPER(tipo_pago) LIKE ?', ['%CRED%']);
            })
            ->whereRaw('UPPER(COALESCE(estado, ?)) <> ?', ['ACTIVO', 'ANULADO'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $deudas = CobranzasDeuda::query()
            ->with([
                'pagos:id,deuda_id,user_id,monto,estado,fecha_hora,anulado_at,anulado_user_id,metodo_pago,considerar_en_cobranza,nro_pago,observacion,comprobante_path',
                'pagos.user:id,name',
                'pagos.anuladoPor:id,name',
            ])
            ->where('cliente_id', $cliente->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $items = collect();
        foreach ($ventas as $venta) {
            $total = round((float) ($venta->total ?? 0), 2);
            $pagado = round((float) $this->sumPagosActivos($venta->pagos), 2);
            $considerar = (bool) ($venta->considerar_en_cobranza ?? true);
            $saldo = $considerar ? $this->saldoConTolerancia($total, $pagado, 0.99) : 0.0;
            $items->push([
                'tipo' => 'VENTA',
                'id' => $venta->id,
                'referencia' => 'Venta #' . $venta->id,
                'fecha' => (string) ($venta->fecha ?? ''),
                'tipo_pago' => (string) ($venta->tipo_pago ?? ''),
                'monto_total' => $total,
                'cobrado' => $pagado,
                'saldo' => $saldo,
                'estado' => $considerar ? (string) ($venta->estado ?? '') : 'NO_CONSIDERAR',
                'considerar_en_cobranza' => $considerar,
                'tolerancia_centavos' => 0.99,
                'pagos' => $venta->pagos->map(fn (Pago $p) => $this->mapPagoVenta($p))->values(),
            ]);
        }

        foreach ($deudas as $deuda) {
            $total = round((float) ($deuda->monto_total ?? 0), 2);
            $pagado = round((float) $this->sumPagosActivos($deuda->pagos), 2);
            $tol = round((float) ($deuda->tolerancia_centavos ?? 0.99), 2);
            $considerar = (bool) ($deuda->considerar_en_cobranza ?? true);
            $saldo = $considerar ? $this->saldoConTolerancia($total, $pagado, $tol) : 0.0;
            $items->push([
                'tipo' => 'DEUDA_MANUAL',
                'id' => $deuda->id,
                'referencia' => 'Deuda manual #' . $deuda->id,
                'fecha' => (string) ($deuda->fecha ?? ''),
                'tipo_pago' => 'CREDITO',
                'monto_total' => $total,
                'cobrado' => $pagado,
                'saldo' => $saldo,
                'estado' => $considerar ? (string) ($deuda->estado ?? 'ACTIVA') : 'NO_CONSIDERAR',
                'considerar_en_cobranza' => $considerar,
                'tolerancia_centavos' => $tol,
                'pagos' => $deuda->pagos->map(fn (CobranzasDeudaPago $p) => $this->mapPagoDeuda($p))->values(),
            ]);
        }

        return response()->json([
            'cliente' => [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'ci' => $cliente->ci,
                'telefono' => $cliente->telefono,
                'direccion' => $cliente->direccion,
            ],
            'items' => $items->sortByDesc('fecha')->values(),
        ]);
    }

    public function cambiarConsiderarVenta(Request $request, Venta $venta)
    {
        $this->authorizeCobranzas($request);
        $data = $request->validate([
            'considerar_en_cobranza' => 'required|boolean',
        ]);
        $venta->considerar_en_cobranza = (bool) $data['considerar_en_cobranza'];
        $venta->save();
        return response()->json(['message' => 'Venta actualizada', 'venta' => $venta->only(['id', 'considerar_en_cobranza'])]);
    }

    public function cambiarConsiderarDeuda(Request $request, CobranzasDeuda $deuda)
    {
        $this->authorizeCobranzas($request);
        $data = $request->validate([
            'considerar_en_cobranza' => 'required|boolean',
        ]);
        $deuda->considerar_en_cobranza = (bool) $data['considerar_en_cobranza'];
        $deuda->save();
        return response()->json(['message' => 'Deuda actualizada', 'deuda' => $deuda->only(['id', 'considerar_en_cobranza'])]);
    }

    public function anularPagoVenta(Request $request, Pago $pago)
    {
        $this->authorizeCobranzas($request);
        $data = $request->validate([
            'motivo' => 'nullable|string|max:600',
        ]);

        return DB::transaction(function () use ($request, $pago, $data) {
            $pago = Pago::query()->lockForUpdate()->findOrFail($pago->id);
            if (strtoupper((string) ($pago->estado ?? 'ACTIVO')) === 'ANULADO') {
                return response()->json(['message' => 'El pago ya esta anulado'], 422);
            }

            $pago->estado = 'ANULADO';
            $pago->anulado_user_id = $request->user()->id;
            $pago->anulado_at = now();
            if (!empty($data['motivo'])) {
                $pago->observacion = trim(((string) ($pago->observacion ?? '')) . ' | ANULADO: ' . $data['motivo']);
            }
            $pago->save();

            return response()->json([
                'message' => 'Pago anulado',
                'pago' => $this->mapPagoVenta($pago->fresh(['user:id,name', 'anuladoPor:id,name'])),
            ]);
        });
    }

    public function anularPagoDeuda(Request $request, CobranzasDeudaPago $pago)
    {
        $this->authorizeCobranzas($request);
        $data = $request->validate([
            'motivo' => 'nullable|string|max:600',
        ]);

        return DB::transaction(function () use ($request, $pago, $data) {
            $pago = CobranzasDeudaPago::query()->lockForUpdate()->findOrFail($pago->id);
            if (strtoupper((string) ($pago->estado ?? 'ACTIVO')) === 'ANULADO') {
                return response()->json(['message' => 'El pago ya esta anulado'], 422);
            }

            $pago->estado = 'ANULADO';
            $pago->anulado_user_id = $request->user()->id;
            $pago->anulado_at = now();
            if (!empty($data['motivo'])) {
                $pago->observacion = trim(((string) ($pago->observacion ?? '')) . ' | ANULADO: ' . $data['motivo']);
            }
            $pago->save();

            return response()->json([
                'message' => 'Pago anulado',
                'pago' => $this->mapPagoDeuda($pago->fresh(['user:id,name', 'anuladoPor:id,name'])),
            ]);
        });
    }

    public function clientes(Request $request)
    {
        $this->authorizeCobranzas($request);
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $rows = Cliente::query()
            ->select('id', 'nombre', 'ci', 'telefono', 'direccion')
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%' . $search . '%'])
                    ->orWhereRaw('LOWER(ci) LIKE ?', ['%' . $search . '%'])
                    ->orWhereRaw('LOWER(telefono) LIKE ?', ['%' . $search . '%']);
            })
            ->orderBy('nombre')
            ->limit(50)
            ->get();
        return response()->json($rows);
    }

    private function emptyClienteRow(string $key, ?int $clienteId, string $nombre, string $ci, string $telefono, string $direccion): array
    {
        return [
            'key' => $key,
            'cliente_id' => $clienteId,
            'cliente_nombre' => $nombre,
            'ci_nit' => $ci,
            'telefono' => $telefono,
            'direccion' => $direccion,
            'monto_total' => 0.0,
            'cobrado_total' => 0.0,
            'saldo_total' => 0.0,
            'items' => [],
        ];
    }

    private function saldoConTolerancia(float $total, float $pagado, float $tol = 0.99): float
    {
        $saldo = round(max(0, $total - $pagado), 2);
        return $saldo <= $tol ? 0.0 : $saldo;
    }

    private function storeComprobante(Request $request, string $key): ?string
    {
        if (!$request->hasFile($key)) {
            return null;
        }
        $file = $request->file($key);
        $name = 'comp_' . now()->format('YmdHis') . '_' . uniqid('', true) . '.' . $file->getClientOriginalExtension();
        $dir = public_path('uploads/cobranzas');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file->move($dir, $name);
        return 'uploads/cobranzas/' . $name;
    }

    private function comprobanteUrl(?string $path): ?string
    {
        if (!$path) return null;
        return url($path);
    }

    private function mapPagoVenta(Pago $p): array
    {
        return [
            'id' => $p->id,
            'monto' => round((float) $p->monto, 2),
            'estado' => (string) ($p->estado ?? 'ACTIVO'),
            'fecha_hora' => optional($p->fecha_hora)->format('Y-m-d H:i:s'),
            'anulado_at' => optional($p->anulado_at)->format('Y-m-d H:i:s'),
            'metodo_pago' => $p->metodo_pago,
            'considerar_en_cobranza' => (bool) $p->considerar_en_cobranza,
            'nro_pago' => $p->nro_pago,
            'observacion' => $p->observacion,
            'comprobante_path' => $p->comprobante_path,
            'comprobante_url' => $this->comprobanteUrl($p->comprobante_path),
            'registrado_por' => $p->user?->name,
            'anulado_por' => $p->anuladoPor?->name,
        ];
    }

    private function mapPagoDeuda(CobranzasDeudaPago $p): array
    {
        return [
            'id' => $p->id,
            'monto' => round((float) $p->monto, 2),
            'estado' => (string) ($p->estado ?? 'ACTIVO'),
            'fecha_hora' => optional($p->fecha_hora)->format('Y-m-d H:i:s'),
            'anulado_at' => optional($p->anulado_at)->format('Y-m-d H:i:s'),
            'metodo_pago' => $p->metodo_pago,
            'considerar_en_cobranza' => (bool) $p->considerar_en_cobranza,
            'nro_pago' => $p->nro_pago,
            'observacion' => $p->observacion,
            'comprobante_path' => $p->comprobante_path,
            'comprobante_url' => $this->comprobanteUrl($p->comprobante_path),
            'registrado_por' => $p->user?->name,
            'anulado_por' => $p->anuladoPor?->name,
        ];
    }

    private function sumPagosActivos($pagos): float
    {
        return (float) collect($pagos)->filter(function ($p) {
            return strtoupper((string) ($p->estado ?? 'ACTIVO')) !== 'ANULADO';
        })->sum('monto');
    }

    private function authorizeCobranzas(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 401, 'No autenticado');
        $isAdmin = strtoupper((string) ($user->role ?? '')) === 'ADMIN';
        $isCobradorRole = strtoupper((string) ($user->role ?? '')) === 'COBRADOR';
        $can = method_exists($user, 'can') && $user->can('Cobranzas');
        abort_unless($isAdmin || $isCobradorRole || $can, 403, 'No autorizado');
    }
}
