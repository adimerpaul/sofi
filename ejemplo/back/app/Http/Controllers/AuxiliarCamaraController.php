<?php

namespace App\Http\Controllers;

use App\Models\CompraDetalle;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AuxiliarCamaraController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAuxiliarAccess($request);

        $data = $request->validate([
            'fecha' => 'nullable|date',
            'vendedor_id' => 'nullable|integer|exists:users,id',
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'usuario_camion_id' => 'nullable|integer|exists:users,id',
            'pedido_zona_id' => 'nullable|integer|exists:pedido_zonas,id',
            'tipo' => 'nullable|string|in:TODOS,NORMAL,EMBUTIDO,HUEVO,PET,POLLO,RES,CERDO',
            'auxiliar_estado' => 'nullable|string|in:TODOS,PENDIENTE,HECHO,MODIFICADO',
            'search' => 'nullable|string|max:120',
        ]);

        $fecha = $data['fecha'] ?? now()->toDateString();
        $tipo = $this->normalizeTipo((string) ($data['tipo'] ?? 'TODOS'));
        $auxEstado = strtoupper((string) ($data['auxiliar_estado'] ?? 'TODOS'));
        $search = trim((string) ($data['search'] ?? ''));

        $query = Pedido::query()
            ->with([
                'cliente:id,nombre,direccion,telefono,territorio,codcli,ci',
                'user:id,name',
                'usuarioCamion:id,name,placa',
                'auxiliarUser:id,name',
                'zona:id,nombre,color,orden',
                'venta:id,total,estado',
                'detalles:id,pedido_id,producto_id,cantidad,precio,total,observacion_detalle,detalle_extra',
                'detalles.producto:id,codigo,nombre,tipo,imagen,codigo_unidad,peso_estimado',
            ])
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->where('estado', 'Enviado')
            ->whereDate('fecha', $fecha)
            ->when(!empty($data['vendedor_id']), fn ($q) => $q->where('user_id', (int) $data['vendedor_id']))
            ->when(!empty($data['cliente_id']), fn ($q) => $q->where('cliente_id', (int) $data['cliente_id']))
            ->when(!empty($data['usuario_camion_id']), fn ($q) => $q->where('usuario_camion_id', (int) $data['usuario_camion_id']))
            ->when(!empty($data['pedido_zona_id']), fn ($q) => $q->where('pedido_zona_id', (int) $data['pedido_zona_id']))
            ->when($auxEstado !== 'TODOS', fn ($q) => $q->where('auxiliar_estado', $auxEstado))
            ->when($tipo !== 'TODOS', function ($q) use ($tipo) {
                if ($tipo === 'NORMAL') $q->where('contiene_normal', true);
                if ($tipo === 'POLLO') $q->where('contiene_pollo', true);
                if ($tipo === 'RES') $q->where('contiene_res', true);
                if ($tipo === 'CERDO') $q->where('contiene_cerdo', true);
            })
            ->orderBy('hora')
            ->orderBy('id');

        $items = $query->get();
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $items = $items->filter(function (Pedido $p) use ($needle) {
                $stack = mb_strtolower(implode(' ', [
                    (string) $p->id,
                    $p->cliente?->nombre ?? '',
                    $p->cliente?->codcli ?? '',
                    $p->cliente?->ci ?? '',
                    $p->cliente?->direccion ?? '',
                    $p->user?->name ?? '',
                    $p->usuarioCamion?->name ?? '',
                    $p->zona?->nombre ?? '',
                ]));
                return str_contains($stack, $needle);
            })->values();
        }

        if ($tipo !== 'TODOS') {
            $items = $items
                ->map(function (Pedido $pedido) use ($tipo) {
                    $pedido->setRelation('detalles', $pedido->detalles
                        ->filter(fn (PedidoDetalle $detalle) => $this->normalizeTipo($detalle->producto?->tipo) === $tipo)
                        ->values());
                    return $pedido;
                })
                ->filter(fn (Pedido $pedido) => $pedido->detalles->isNotEmpty())
                ->values();
        }

        $rows = $items->map(function (Pedido $pedido) {
            $tipos = $pedido->detalles
                ->map(fn (PedidoDetalle $detalle) => $this->normalizeTipo($detalle->producto?->tipo))
                ->values();
            $total = (float) $pedido->detalles->sum('total');

            return [
                'id' => $pedido->id,
                'fecha' => (string) $pedido->fecha,
                'hora' => (string) ($pedido->hora ?? ''),
                'estado' => $pedido->estado,
                'auxiliar_estado' => $pedido->auxiliar_estado ?? 'PENDIENTE',
                'auxiliar_observacion' => $pedido->auxiliar_observacion,
                'auxiliar_hecho_at' => optional($pedido->auxiliar_hecho_at)->toDateTimeString(),
                'venta_generada' => (bool) $pedido->venta_generada,
                'venta_id' => $pedido->venta_id,
                'total' => $total,
                'tipo_pago' => $pedido->tipo_pago,
                'contiene_normal' => $tipos->contains('NORMAL'),
                'contiene_pollo' => $tipos->contains('POLLO'),
                'contiene_res' => $tipos->contains('RES'),
                'contiene_cerdo' => $tipos->contains('CERDO'),
                'cliente' => [
                    'id' => $pedido->cliente?->id,
                    'nombre' => $pedido->cliente?->nombre,
                    'codcli' => $pedido->cliente?->codcli,
                    'ci' => $pedido->cliente?->ci,
                    'direccion' => $pedido->cliente?->direccion,
                    'telefono' => $pedido->cliente?->telefono,
                    'territorio' => $pedido->cliente?->territorio,
                ],
                'vendedor' => [
                    'id' => $pedido->user?->id,
                    'name' => $pedido->user?->name,
                ],
                'camion' => [
                    'id' => $pedido->usuarioCamion?->id,
                    'name' => $pedido->usuarioCamion?->name,
                    'placa' => $pedido->usuarioCamion?->placa,
                ],
                'zona' => [
                    'id' => $pedido->zona?->id,
                    'nombre' => $pedido->zona?->nombre,
                    'color' => $pedido->zona?->color ?? '#9e9e9e',
                ],
                'detalles' => $pedido->detalles->map(function (PedidoDetalle $detalle) {
                    $pesoEstimado = $this->extractPesoEstimado($detalle);
                    $observacion = trim((string) ($detalle->observacion_detalle ?? ''));
                    if ($observacion === '') {
                        $extraObs = $detalle->detalle_extra['observacion'] ?? null;
                        $observacion = is_string($extraObs) ? trim($extraObs) : '';
                    }
                    return [
                        'id' => $detalle->id,
                        'producto_id' => $detalle->producto_id,
                        'codigo' => $detalle->producto?->codigo ?: ('#' . $detalle->producto_id),
                        'producto' => $detalle->producto?->nombre,
                        'tipo' => strtoupper((string) ($detalle->producto?->tipo ?? 'NORMAL')),
                        'codigo_unidad' => strtoupper((string) ($detalle->producto?->codigo_unidad ?? '')),
                        'observacion_detalle' => $observacion !== '' ? $observacion : null,
                        'detalle_extra' => $detalle->detalle_extra,
                        'imagen' => $detalle->producto?->imagen,
                        'peso_estimado' => $pesoEstimado,
                        'cantidad' => (float) $detalle->cantidad,
                        'precio' => (float) $detalle->precio,
                        'total' => (float) $detalle->total,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'fecha' => $fecha,
                'sync_at' => now()->toDateTimeString(),
                'total_pedidos' => $rows->count(),
                'total_bs' => (float) $rows->sum('total'),
                'pendientes' => $rows->where('auxiliar_estado', 'PENDIENTE')->count(),
                'hechos' => $rows->where('auxiliar_estado', 'HECHO')->count(),
                'modificados' => $rows->where('auxiliar_estado', 'MODIFICADO')->count(),
            ],
        ]);
    }

    public function procesar(Request $request, Pedido $pedido)
    {
        $this->authorizeAuxiliarAccess($request);
        if ($pedido->tipo_pedido !== 'REALIZAR_PEDIDO' || $pedido->estado !== 'Enviado') {
            return response()->json(['message' => 'Solo se pueden procesar pedidos enviados'], 422);
        }

        $data = $request->validate([
            'generar_venta' => 'nullable|boolean',
            'auxiliar_observacion' => 'nullable|string|max:600',
            'detalles' => 'nullable|array',
            'detalles.*.id' => 'required_with:detalles|integer|exists:pedido_detalles,id',
            'detalles.*.cantidad' => 'required_with:detalles|numeric|min:0',
            'detalles.*.precio' => 'nullable|numeric|min:0',
            'detalles.*.peso_estimado' => 'nullable|numeric|min:0',
        ]);

        $generarVenta = (bool) ($data['generar_venta'] ?? true);
        if ($generarVenta && $pedido->venta_generada) {
            return response()->json(['message' => 'Este pedido ya genero una venta'], 422);
        }

        return DB::transaction(function () use ($pedido, $data, $generarVenta, $request) {
            $updated = false;
            if (!empty($data['detalles'])) {
                $map = collect($data['detalles'])->keyBy('id');
                $detalles = $pedido->detalles()->with('producto:id,tipo')->get();
                foreach ($detalles as $detalle) {
                    if (!$map->has($detalle->id)) {
                        continue;
                    }
                    $newCant = (float) $map[$detalle->id]['cantidad'];
                    $newPrecio = array_key_exists('precio', $map[$detalle->id]) && $map[$detalle->id]['precio'] !== null
                        ? (float) $map[$detalle->id]['precio']
                        : (float) $detalle->precio;
                    $oldPeso = $this->extractPesoEstimado($detalle);
                    $newPeso = array_key_exists('peso_estimado', $map[$detalle->id])
                        ? (float) ($map[$detalle->id]['peso_estimado'] ?? 0)
                        : $oldPeso;
                    if ($newPeso <= 0) {
                        $newPeso = null;
                    }
                    if ($newCant < 0) {
                        abort(422, 'Cantidad invalida');
                    }
                    $factorPeso = $newPeso !== null ? $newPeso : 1.0;
                    $newTotal = round($newCant * $newPrecio * $factorPeso, 3);
                    $hasPesoChange = ($newPeso === null && $oldPeso !== null) || ($newPeso !== null && abs($newPeso - $oldPeso) > 0.0001);
                    if (
                        abs((float) $detalle->cantidad - $newCant) > 0.0001 ||
                        abs((float) $detalle->precio - $newPrecio) > 0.0001 ||
                        $hasPesoChange ||
                        abs((float) $detalle->total - $newTotal) > 0.0001
                    ) {
                        $extra = is_array($detalle->detalle_extra) ? $detalle->detalle_extra : [];
                        if ($newPeso !== null) {
                            $extra['peso_estimado'] = $newPeso;
                        } else {
                            unset($extra['peso_estimado']);
                        }
                        $detalle->cantidad = $newCant;
                        $detalle->precio = $newPrecio;
                        $detalle->total = $newTotal;
                        $detalle->detalle_extra = $extra;
                        $detalle->save();
                        $updated = true;
                    }
                }

                $pedido->detalles()->where('cantidad', '<=', 0)->delete();
                [$total, $contiene] = $this->recalcularPedido($pedido);
                $pedido->total = $total;
                $pedido->contiene_normal = $contiene['normal'];
                $pedido->contiene_res = $contiene['res'];
                $pedido->contiene_cerdo = $contiene['cerdo'];
                $pedido->contiene_pollo = $contiene['pollo'];
            }

            $pedido->auxiliar_observacion = $data['auxiliar_observacion'] ?? $pedido->auxiliar_observacion;
            $pedido->auxiliar_user_id = $request->user()->id;

            if ($generarVenta) {
                $venta = $this->crearVentaDesdePedido($pedido, $request->user()->id);
                $pedido->venta_generada = true;
                $pedido->venta_id = $venta->id;
                $pedido->auxiliar_estado = $updated ? 'MODIFICADO' : 'HECHO';
                $pedido->auxiliar_hecho_at = now();
            } else {
                $pedido->auxiliar_estado = 'MODIFICADO';
            }

            $pedido->save();

            return response()->json([
                'message' => $generarVenta ? 'Pedido procesado y venta generada' : 'Pedido modificado',
                'pedido' => $pedido->load([
                    'venta:id,total,estado',
                    'detalles.producto:id,codigo,nombre,imagen,tipo',
                    'cliente:id,nombre,codcli,ci',
                    'user:id,name',
                    'usuarioCamion:id,name,placa',
                    'zona:id,nombre,color',
                ]),
            ]);
        });
    }

    public function reportePedidos(Request $request)
    {
        $pedidos = $this->queryForReports($request)->with([
            'cliente:id,nombre,direccion,telefono,codcli,ci,territorio',
            'user:id,name',
            'usuarioCamion:id,name,placa',
            'zona:id,nombre,color',
            'detalles:id,pedido_id,producto_id,cantidad,precio,total,observacion_detalle,detalle_extra',
            'detalles.producto:id,codigo,nombre,imagen,tipo',
        ])->orderBy('hora')->orderBy('id')->get();

        $pedidos = $pedidos
            ->map(function (Pedido $pedido) {
                $pedido->setRelation('detalles', $pedido->detalles->filter(function (PedidoDetalle $detalle) {
                    return $this->normalizeTipo($detalle->producto?->tipo) === 'NORMAL';
                })->values());
                return $pedido;
            })
            ->filter(fn (Pedido $pedido) => $pedido->detalles->isNotEmpty())
            ->values();

        if ($pedidos->isEmpty()) {
            return response()->json(['message' => 'No hay pedidos para los filtros seleccionados'], 404);
        }

        $pedidos = $pedidos->map(function (Pedido $pedido) {
            $pedido->detalles->each(function (PedidoDetalle $detalle) {
                $detalle->setAttribute('imagen_data_url', $this->toDataImage((string) ($detalle->producto?->imagen ?? '')));
            });
            return $pedido;
        });

        $fecha = (string) ($request->input('fecha') ?: now()->toDateString());
        $pdf = Pdf::loadView('pdf.auxiliar_camara_pedidos', [
            'pedidos' => $pedidos,
            'fecha' => $fecha,
        ])->setPaper('letter');

        return $pdf->download('auxiliar_pedidos_' . str_replace('-', '', $fecha) . '.pdf');
    }

    public function reportePedidosCamion(Request $request)
    {
        $data = $request->validate([
            'usuario_camion_id' => 'required|integer|exists:users,id',
        ]);

        $camion = User::query()->find((int) $data['usuario_camion_id']);
        if (!$camion || !$camion->es_camion) {
            return response()->json(['message' => 'Debe seleccionar un usuario camion valido'], 422);
        }

        $pedidos = $this->queryForReports($request)->with([
            'cliente:id,nombre,direccion,telefono,codcli,ci,territorio',
            'user:id,name',
            'usuarioCamion:id,name,placa',
            'zona:id,nombre,color',
            'detalles:id,pedido_id,producto_id,cantidad,precio,total,observacion_detalle,detalle_extra',
            'detalles.producto:id,codigo,nombre,imagen,tipo',
        ])
            ->where('usuario_camion_id', (int) $camion->id)
            ->orderBy('hora')
            ->orderBy('id')
            ->get();

        $pedidos = $pedidos
            ->map(function (Pedido $pedido) {
                $pedido->setRelation('detalles', $pedido->detalles->filter(function (PedidoDetalle $detalle) {
                    return $this->normalizeTipo($detalle->producto?->tipo) === 'NORMAL';
                })->values());
                return $pedido;
            })
            ->filter(fn (Pedido $pedido) => $pedido->detalles->isNotEmpty())
            ->values();

        if ($pedidos->isEmpty()) {
            return response()->json(['message' => 'No hay pedidos para el camion seleccionado'], 404);
        }

        $pedidos = $pedidos->map(function (Pedido $pedido) {
            $pedido->detalles->each(function (PedidoDetalle $detalle) {
                $detalle->setAttribute('imagen_data_url', $this->toDataImage((string) ($detalle->producto?->imagen ?? '')));
            });
            return $pedido;
        });

        $fecha = (string) ($request->input('fecha') ?: now()->toDateString());
        $pdf = Pdf::loadView('pdf.auxiliar_camara_pedidos_camion', [
            'pedidos' => $pedidos,
            'fecha' => $fecha,
            'camion' => $camion,
            'total' => (float) $pedidos->sum('total'),
        ])->setPaper('letter');

        return $pdf->download('auxiliar_pedidos_camion_' . $camion->id . '_' . str_replace('-', '', $fecha) . '.pdf');
    }

    public function reporteProductosTotales(Request $request)
    {
        $includeClientes = $request->boolean('incluir_clientes', true);
        $pedidos = $this->queryForReports($request)->with([
            'cliente:id,nombre',
            'detalles:id,pedido_id,producto_id,cantidad,total',
            'detalles.producto:id,codigo,nombre,imagen,tipo',
        ])->get();

        if ($pedidos->isEmpty()) {
            return response()->json(['message' => 'No hay pedidos para los filtros seleccionados'], 404);
        }

        $productos = $pedidos
            ->flatMap(fn (Pedido $pedido) => $pedido->detalles)
            ->filter(fn (PedidoDetalle $d) => $d->producto !== null && $this->normalizeTipo($d->producto?->tipo) === 'NORMAL')
            ->groupBy('producto_id')
            ->map(function (Collection $group) use ($pedidos) {
                $producto = $group->first()->producto;
                $clientes = $group
                    ->groupBy(fn (PedidoDetalle $d) => (int) ($pedidos->firstWhere('id', $d->pedido_id)?->cliente?->id ?? 0))
                    ->map(function (Collection $rows, int $clienteId) use ($pedidos) {
                        $pedido = $pedidos->firstWhere('id', $rows->first()->pedido_id);
                        $nombre = $pedido?->cliente?->nombre ?: ('Cliente #' . $clienteId);
                        $cantidad = (float) $rows->sum('cantidad');
                        return [
                            'nombre' => $nombre,
                            'cantidad' => $cantidad,
                        ];
                    })
                    ->sortByDesc('cantidad')
                    ->values()
                    ->map(fn ($row) => $row['nombre'] . ' (' . rtrim(rtrim(number_format((float) $row['cantidad'], 2, '.', ''), '0'), '.') . ')')
                    ->implode(', ');
                return [
                    'codigo' => $producto->codigo ?: ('#' . $producto->id),
                    'nombre' => $producto->nombre,
                    'tipo' => strtoupper((string) ($producto->tipo ?? 'NORMAL')),
                    'cantidad_total' => (float) $group->sum('cantidad'),
                    'importe_total' => (float) $group->sum('total'),
                    'clientes' => $clientes,
                    'imagen_data_url' => $this->toDataImage((string) ($producto->imagen ?? '')),
                ];
            })
            ->sortByDesc('cantidad_total')
            ->values();

        if ($productos->isEmpty()) {
            return response()->json(['message' => 'No hay productos tipo NORMAL para los filtros seleccionados'], 404);
        }

        $fecha = (string) ($request->input('fecha') ?: now()->toDateString());
        $pdf = Pdf::loadView('pdf.auxiliar_camara_productos_totales', [
            'fecha' => $fecha,
            'productos' => $productos,
            'cantidadTotal' => (float) $productos->sum('cantidad_total'),
            'importeTotal' => (float) $productos->sum('importe_total'),
            'includeClientes' => $includeClientes,
        ])->setPaper('letter');

        $suffix = $includeClientes ? 'con_clientes' : 'sin_clientes';
        return $pdf->download('auxiliar_productos_' . $suffix . '_' . str_replace('-', '', $fecha) . '.pdf');
    }

    public function reporteVentasGeneradas(Request $request)
    {
        $pedidos = $this->queryForReports($request)->with([
            'cliente:id,nombre,codcli,ci',
            'user:id,name',
            'usuarioCamion:id,name,placa',
            'zona:id,nombre,color',
            'venta:id,pedido_id,total,estado,fecha,hora,tipo_pago',
        ])
            ->where('venta_generada', true)
            ->whereNotNull('venta_id')
            ->orderBy('hora')
            ->orderBy('id')
            ->get();

        if ($pedidos->isEmpty()) {
            return response()->json(['message' => 'No hay ventas generadas para los filtros seleccionados'], 404);
        }

        $fecha = (string) ($request->input('fecha') ?: now()->toDateString());
        $pdf = Pdf::loadView('pdf.auxiliar_camara_ventas_generadas', [
            'pedidos' => $pedidos,
            'fecha' => $fecha,
            'totalVentas' => (float) $pedidos->sum(fn (Pedido $p) => (float) ($p->venta?->total ?? 0)),
        ])->setPaper('letter');

        return $pdf->download('auxiliar_ventas_generadas_' . str_replace('-', '', $fecha) . '.pdf');
    }

    private function queryForReports(Request $request)
    {
        $this->authorizeAuxiliarAccess($request);
        $data = $request->validate([
            'fecha' => 'nullable|date',
            'vendedor_id' => 'nullable|integer|exists:users,id',
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'usuario_camion_id' => 'nullable|integer|exists:users,id',
            'pedido_zona_id' => 'nullable|integer|exists:pedido_zonas,id',
            'incluir_clientes' => 'nullable|boolean',
        ]);

        $fecha = $data['fecha'] ?? now()->toDateString();

        return Pedido::query()
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->where('estado', 'Enviado')
            ->whereDate('fecha', $fecha)
            ->where('contiene_normal', true)
            ->when(!empty($data['vendedor_id']), fn ($q) => $q->where('user_id', (int) $data['vendedor_id']))
            ->when(!empty($data['cliente_id']), fn ($q) => $q->where('cliente_id', (int) $data['cliente_id']))
            ->when(!empty($data['usuario_camion_id']), fn ($q) => $q->where('usuario_camion_id', (int) $data['usuario_camion_id']))
            ->when(!empty($data['pedido_zona_id']), fn ($q) => $q->where('pedido_zona_id', (int) $data['pedido_zona_id']));
    }

    private function recalcularPedido(Pedido $pedido): array
    {
        $detalles = $pedido->detalles()->with('producto:id,tipo')->get();
        $contiene = ['normal' => false, 'res' => false, 'cerdo' => false, 'pollo' => false];
        $total = 0.0;
        foreach ($detalles as $d) {
            $total += (float) $d->total;
            $tipo = strtoupper((string) ($d->producto?->tipo ?? 'NORMAL'));
            if ($tipo === 'RES') $contiene['res'] = true;
            elseif ($tipo === 'CERDO') $contiene['cerdo'] = true;
            elseif ($tipo === 'POLLO') $contiene['pollo'] = true;
            else $contiene['normal'] = true;
        }
        return [$total, $contiene];
    }

    private function crearVentaDesdePedido(Pedido $pedido, int $auxiliarUserId): Venta
    {
        $pedido->loadMissing(['detalles.producto:id,nombre,tipo', 'user:id,agencia', 'cliente:id,nombre,ci']);
        $detalles = $pedido->detalles;
        if ($detalles->isEmpty()) {
            abort(422, 'Pedido sin productos para generar venta');
        }

        $venta = Venta::create([
            'user_id' => $pedido->user_id ?: $auxiliarUserId,
            'pedido_id' => $pedido->id,
            'cliente_id' => $pedido->cliente_id,
            'fecha' => now()->toDateString(),
            'hora' => now()->format('H:i:s'),
            'ci' => (string) ($pedido->cliente?->ci ?? ''),
            'nombre' => (string) ($pedido->cliente?->nombre ?? ''),
            'estado' => 'Activo',
            'tipo_comprobante' => 'NOTA',
            'tipo_pago' => $pedido->tipo_pago ?: 'Efectivo',
            'facturado' => (bool) $pedido->facturado,
            'factura_estado' => $pedido->facturado ? 'PENDIENTE' : 'SIN_GESTION',
            'agencia' => $pedido->user?->agencia,
            'total' => 0,
            'online' => false,
        ]);

        $total = 0.0;
        foreach ($detalles as $detalle) {
            $cantidad = (float) $detalle->cantidad;
            if ($cantidad <= 0) continue;
            $precio = (float) $detalle->precio;
            $precioConPeso = $precio * $this->extractPesoEstimado($detalle);
            $nombre = (string) ($detalle->producto?->nombre ?? 'Producto');
            $tipo = $this->normalizeTipo($detalle->producto?->tipo);

            if ($tipo === 'NORMAL') {
                $total += $this->consumirStockPorVencimiento(
                    ventaId: (int) $venta->id,
                    productoId: (int) $detalle->producto_id,
                    cantidad: $cantidad,
                    precio: $precioConPeso,
                    nombreProducto: $nombre
                );
                continue;
            }

            VentaDetalle::create([
                'venta_id' => $venta->id,
                'producto_id' => $detalle->producto_id,
                'cantidad' => $cantidad,
                'precio' => $precioConPeso,
                'nombre' => $nombre,
            ]);
            $total += ($cantidad * $precioConPeso);
        }

        $venta->total = round($total, 3);
        $venta->save();

        return $venta;
    }

    private function consumirStockPorVencimiento(int $ventaId, int $productoId, float $cantidad, float $precio, string $nombreProducto): float
    {
        $restante = $cantidad;
        $sum = 0.0;

        $lotes = CompraDetalle::query()
            ->where('producto_id', $productoId)
            ->where('estado', 'Activo')
            ->whereNull('deleted_at')
            ->where('cantidad_venta', '>', 0)
            ->orderByRaw("CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END, fecha_vencimiento ASC")
            ->lockForUpdate()
            ->get(['id', 'cantidad_venta', 'lote', 'fecha_vencimiento']);

        foreach ($lotes as $lote) {
            if ($restante <= 0) break;
            $disp = (float) $lote->cantidad_venta;
            if ($disp <= 0) continue;

            $take = min($disp, $restante);
            if ($take <= 0) continue;

            VentaDetalle::create([
                'venta_id' => $ventaId,
                'producto_id' => $productoId,
                'cantidad' => $take,
                'precio' => $precio,
                'nombre' => $nombreProducto,
                'lote' => $lote->lote,
                'fecha_vencimiento' => $lote->fecha_vencimiento,
                'compra_detalle_id' => $lote->id,
            ]);

            $lote->cantidad_venta = $disp - $take;
            $lote->save();
            $restante -= $take;
            $sum += ($take * $precio);
        }

        if ($restante > 0.0001) {
            abort(422, 'Stock insuficiente para productos tipo NORMAL');
        }

        return $sum;
    }

    private function authorizeAuxiliarAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 401, 'No autenticado');

        $isAdmin = strtoupper((string) ($user->role ?? '')) === 'ADMIN';
        $canAuxiliar = method_exists($user, 'can') && $user->can('Auxiliar de camara');
        abort_unless($isAdmin || $canAuxiliar, 403, 'No autorizado');
    }

    private function toDataImage(string $imagePath): ?string
    {
        $path = trim($imagePath);
        if ($path === '') {
            return null;
        }

        $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, trim($path, "\\/"));
        $fullPath = public_path($normalized);
        if (!is_file($fullPath)) {
            return null;
        }

        // Try to convert to JPEG first to maximize PDF/browser compatibility
        // (AVIF is not consistently supported by renderers like DOMPDF).
        $jpegData = $this->toJpegBinary($fullPath);
        if ($jpegData !== null) {
            return 'data:image/jpeg;base64,' . base64_encode($jpegData);
        }

        $content = @file_get_contents($fullPath);
        if ($content === false) {
            return null;
        }

        $mime = @mime_content_type($fullPath);
        if (!is_string($mime) || !Str::startsWith($mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        // Final guard: avoid returning AVIF when renderer cannot display it.
        if ($mime === 'image/avif') {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    private function normalizeTipo(?string $tipo): string
    {
        $value = strtoupper(trim((string) ($tipo ?? 'NORMAL')));
        if ($value === 'TODOS') return 'TODOS';
        if ($value === 'NORMAL' || $value === 'EMBUTIDO') return 'NORMAL';
        return $value !== '' ? $value : 'NORMAL';
    }

    private function extractPesoEstimado(PedidoDetalle $detalle): float
    {
        $fromExtra = is_array($detalle->detalle_extra) ? ($detalle->detalle_extra['peso_estimado'] ?? null) : null;
        $peso = is_numeric($fromExtra) ? (float) $fromExtra : 0.0;
        if ($peso > 0) return $peso;

        $fromProduct = $detalle->producto?->peso_estimado ?? null;
        $pesoProducto = is_numeric($fromProduct) ? (float) $fromProduct : 0.0;
        return $pesoProducto > 0 ? $pesoProducto : 1.0;
    }

    private function toJpegBinary(string $fullPath): ?string
    {
        // 1) Native Imagick conversion (best AVIF support when extension is installed).
        if (class_exists(\Imagick::class)) {
            try {
                $img = new \Imagick($fullPath);
                $img = $img->mergeImageLayers(\Imagick::LAYERMETHOD_MERGE);
                $img->setImageBackgroundColor('white');
                $img->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                $img->setImageFormat('jpeg');
                $img->setImageCompressionQuality(80);
                $blob = $img->getImagesBlob();
                $img->clear();
                $img->destroy();
                if (is_string($blob) && $blob !== '') {
                    return $blob;
                }
            } catch (Throwable) {
                // Continue with fallback.
            }
        }

        // 2) GD fallback for classic formats.
        try {
            $bytes = @file_get_contents($fullPath);
            if ($bytes === false) {
                return null;
            }
            $resource = @imagecreatefromstring($bytes);
            if ($resource === false) {
                return null;
            }
            ob_start();
            imagejpeg($resource, null, 80);
            $jpeg = ob_get_clean();
            imagedestroy($resource);
            return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
        } catch (Throwable) {
            return null;
        }
    }
}
