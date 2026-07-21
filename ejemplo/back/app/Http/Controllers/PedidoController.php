<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Visita;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PedidoController extends Controller {
    private array $estadosEditables = ['Creado', 'Pendiente'];
    private array $clientesRequierenClienteBaja = [1520, 1179];

    function recuperarPedido(Request $request) {
        $pedido = Pedido::with('detalles.producto')
            ->where('id', $request->id)
            ->first();
        return response()->json($pedido);
    }

    public function misPedidos(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'fecha' => 'nullable|date',
            'cliente_id' => 'nullable|integer|exists:clientes,id',
        ]);
        $fecha = $data['fecha'] ?? now()->toDateString();

        $items = Pedido::query()
            ->with(['detalles.producto', 'cliente:id,nombre,codcli,ci', 'clienteBaja:id,nombre,codcli,ci', 'user:id,name,role'])
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->where('user_id', $user->id)
            ->whereDate('fecha', $fecha)
            ->when(!empty($data['cliente_id']), function ($q) use ($data) {
                $q->where('cliente_id', (int) $data['cliente_id']);
            })
            ->orderBy('hora')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $items,
            'stats' => [
                'total' => $items->count(),
                'creado' => $items->where('estado', 'Creado')->count(),
                'pendiente' => $items->where('estado', 'Pendiente')->count(),
                'enviado' => $items->where('estado', 'Enviado')->count(),
            ],
        ]);
    }

    public function enviar(Request $request, Pedido $pedido)
    {
        $this->authorizePedidoOwnerOrAdmin($request, $pedido);

        if (!$this->isEditable($pedido)) {
            return response()->json(['message' => 'El pedido ya fue enviado y no puede modificarse.'], 422);
        }

        $pedido->estado = 'Enviado';
        $pedido->save();

        return response()->json($pedido->load(['detalles.producto', 'cliente', 'clienteBaja', 'user']));
    }

    public function enviarMisPedidos(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'fecha' => 'nullable|date',
            'ids' => 'nullable|array',
            'ids.*' => 'integer|exists:pedidos,id',
        ]);

        $fecha = $data['fecha'] ?? now()->toDateString();
        $ids = $data['ids'] ?? [];

        $query = Pedido::query()
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->where('user_id', $user->id)
            ->whereIn('estado', $this->estadosEditables);

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->whereDate('fecha', $fecha);
        }

        $items = $query->get();
        if ($items->isEmpty()) {
            return response()->json(['message' => 'No hay pedidos pendientes para enviar', 'sent' => 0], 200);
        }

        $updated = Pedido::query()
            ->whereIn('id', $items->pluck('id')->all())
            ->update(['estado' => 'Enviado']);

        return response()->json([
            'message' => 'Pedidos enviados correctamente',
            'sent' => $updated,
        ], 200);
    }

    public function update(Request $request, Pedido $pedido) {
        $user = $request->user();
        $isAdmin = strtoupper((string) ($user->role ?? '')) === 'ADMIN';
        $this->authorizePedidoOwnerOrAdmin($request, $pedido);

        $data = $request->validate([
            'estado' => ['sometimes', Rule::in(['Creado', 'Pendiente', 'Enviado', 'Aceptado', 'Anulado'])],
            'tipo_pago' => 'sometimes|nullable|string|in:Contado,QR,Credito,Boleta anterior',
            'facturado' => 'sometimes|nullable|boolean',
            'fecha' => 'sometimes|nullable|date',
            'hora' => 'sometimes|nullable|string|max:50',
            'observaciones' => 'sometimes|nullable|string|max:600',
            'comentario_visita' => 'sometimes|nullable|string|max:600',
            'cliente_baja_id' => 'sometimes|nullable|integer|exists:clientes,id',
            'productos' => 'sometimes|array',
            'productos.*.producto_id' => 'required_with:productos|integer|exists:productos,id',
            'productos.*.cantidad' => 'required_with:productos|numeric|min:0.01',
            'productos.*.precio' => 'required_with:productos|numeric|min:0',
            'productos.*.observacion' => 'nullable|string|max:600',
            'productos.*.detalle_extra' => 'nullable|array',
        ]);

        $estadoDestino = $data['estado'] ?? null;
        $camposEdicion = ['tipo_pago', 'facturado', 'fecha', 'hora', 'observaciones', 'comentario_visita', 'cliente_baja_id', 'productos'];
        $intentandoEditar = collect($camposEdicion)->contains(fn ($k) => array_key_exists($k, $data));

        if ($estadoDestino !== null) {
            if (in_array($estadoDestino, ['Aceptado', 'Anulado'], true) && !$isAdmin) {
                return response()->json(['message' => 'No autorizado para cambiar a ese estado'], 403);
            }

            if ($estadoDestino === 'Enviado' && !$this->isEditable($pedido)) {
                return response()->json(['message' => 'El pedido ya fue enviado y no puede reenviarse'], 422);
            }
        }

        if ($intentandoEditar && !$this->isEditable($pedido)) {
            return response()->json(['message' => 'El pedido enviado no puede modificarse'], 422);
        }

        DB::beginTransaction();
        try {
            if ($intentandoEditar) {
                if (
                    isset($pedido->cliente_id) &&
                    in_array((int) $pedido->cliente_id, $this->clientesRequierenClienteBaja, true) &&
                    array_key_exists('cliente_baja_id', $data) &&
                    empty($data['cliente_baja_id'])
                ) {
                    return response()->json(['message' => 'Debe seleccionar el cliente asociado para bajas o bonificacion'], 422);
                }

                if (($data['tipo_pago'] ?? null) === 'Credito' && $pedido->cliente_id) {
                    $cliente = Cliente::query()->select('id', 'puede_credito')->find($pedido->cliente_id);
                    if ($cliente && $cliente->puede_credito === false) {
                        return response()->json(['message' => 'Este cliente no puede tener credito'], 422);
                    }
                }

                $updatePayload = array_intersect_key($data, array_flip(['tipo_pago', 'facturado', 'fecha', 'hora', 'observaciones', 'comentario_visita', 'cliente_baja_id']));
                if (!empty($updatePayload)) {
                    $pedido->update($updatePayload);
                }

                if (array_key_exists('productos', $data)) {
                    [$total, $contiene] = $this->syncDetalles($pedido, $data['productos'] ?? []);
                    $pedido->update([
                        'total' => $total,
                        'contiene_normal' => $contiene['normal'],
                        'contiene_res' => $contiene['res'],
                        'contiene_cerdo' => $contiene['cerdo'],
                        'contiene_pollo' => $contiene['pollo'],
                    ]);
                }
            }

            if ($estadoDestino !== null) {
                $pedido->estado = $estadoDestino;
                $pedido->save();
            }

            DB::commit();
            return response()->json($pedido->load(['detalles.producto', 'cliente', 'clienteBaja', 'user']));
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function index(Request $request) {
        $fechaInicio = $request->fechaInicio;
        $fechaFin = $request->fechaFin;
        $user = $request->user();

        return Pedido::with(['detalles.producto', 'user', 'cliente', 'clienteBaja'])
            ->when($fechaInicio && $fechaFin, function ($q) use ($fechaInicio, $fechaFin) {
                $q->where('fecha', '>=', $fechaInicio)
                    ->where('fecha', '<=', $fechaFin);
            })
            ->when($request->filled('tipo_pedido'), function ($q) use ($request) {
                $q->where('tipo_pedido', $request->tipo_pedido);
            })
            ->when($request->filled('cliente_id'), function ($q) use ($request) {
                $q->where('cliente_id', $request->cliente_id);
            })
            ->when(($request->solo_mios ?? false) && $user, function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->when($request->filled('user'), function ($q) use ($request) {
                $q->where('user_id', $request->user);
            })
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->get();
    }

    public function store(Request $request) {
        DB::beginTransaction();
        try {
            $user = $request->user();
            $tipoPedido = strtoupper((string)($request->tipo_pedido ?? 'REALIZAR_PEDIDO'));
            $isPedido = $tipoPedido === 'REALIZAR_PEDIDO';

            $rules = [
                'tipo_pedido' => 'nullable|string|in:REALIZAR_PEDIDO,RETORNAR,NO_PEDIDO,GENERAR_RUTA',
                'tipo_pago' => 'nullable|string|in:Contado,QR,Credito,Boleta anterior',
                'facturado' => 'nullable|boolean',
                'fecha' => 'nullable|date',
                'hora' => 'nullable|string|max:50',
                'cliente_id' => 'nullable|integer|exists:clientes,id',
                'cliente_baja_id' => 'nullable|integer|exists:clientes,id',
                'observaciones' => 'nullable|string|max:600',
                'comentario_visita' => 'nullable|string|max:600',
                'productos' => 'nullable|array',
                'productos.*.producto_id' => 'required_with:productos|integer|exists:productos,id',
                'productos.*.cantidad' => 'required_with:productos|numeric|min:0.01',
                'productos.*.precio' => 'required_with:productos|numeric|min:0',
                'productos.*.observacion' => 'nullable|string|max:600',
                'productos.*.detalle_extra' => 'nullable|array',
            ];
            $data = $request->validate($rules);

            if ($isPedido && empty($data['productos'])) {
                return response()->json(['message' => 'Debe agregar al menos un producto para realizar pedido'], 422);
            }

            $clienteId = isset($data['cliente_id']) ? (int) $data['cliente_id'] : null;
            $clienteBajaId = isset($data['cliente_baja_id']) ? (int) $data['cliente_baja_id'] : null;

            if ($isPedido && $clienteId && in_array($clienteId, $this->clientesRequierenClienteBaja, true) && !$clienteBajaId) {
                return response()->json(['message' => 'Debe seleccionar el cliente asociado para bajas o bonificacion'], 422);
            }

            $cliente = null;
            if ($isPedido && $clienteId) {
                $cliente = Cliente::query()->select([
                    'id',
                    'puede_credito',
                    'lu',
                    'ma',
                    'mi',
                    'ju',
                    'vi',
                    'sa',
                    'do',
                ])->find($clienteId);
            }

            if ($isPedido && $cliente && ($data['tipo_pago'] ?? null) === 'Credito') {
                if ($cliente && $cliente->puede_credito === false) {
                    return response()->json(['message' => 'Este cliente no puede tener credito'], 422);
                }
            }

            if (!$isPedido) {
                $visita = $this->registrarVisita(
                    userId: (int) $user->id,
                    clienteId: $clienteId,
                    tipoVisita: $tipoPedido,
                    comentario: $data['comentario_visita'] ?? ($data['observaciones'] ?? null)
                );

                DB::commit();
                return response()->json([
                    'message' => 'Accion registrada',
                    'visita' => $visita->load('cliente:id,nombre,codcli'),
                ], 201);
            }

            $productos = $data['productos'] ?? [];
            $productoTipos = Producto::query()
                ->whereIn('id', collect($productos)->pluck('producto_id')->values()->all())
                ->pluck('tipo', 'id');

            $tiposUnicos = $this->collectProductosTipos($productos, $productoTipos);
            $contiene = $this->buildContiene($tiposUnicos);

            foreach ($productos as &$item) {
                $tipoItem = $this->normalizeProductoTipo($productoTipos[$item['producto_id']] ?? 'EMBUTIDO');
                $item['detalle_extra'] = $this->sanitizeDetalleExtra($tipoItem, $item['detalle_extra'] ?? null);
            }
            unset($item);

            $fechaPedido = $data['fecha'] ?? now()->format('Y-m-d');
            $fueraDeRuta = $this->isFueraDeRuta($cliente, $fechaPedido);

            $pedido = Pedido::create([
                'user_id' => $user->id,
                'cliente_id' => $clienteId,
                'cliente_baja_id' => $clienteBajaId,
                'fecha' => $fechaPedido,
                'hora' => $data['hora'] ?? null,
                'estado' => $isPedido ? 'Creado' : 'Pendiente',
                'tipo_pago' => $data['tipo_pago'] ?? null,
                'facturado' => (bool) ($data['facturado'] ?? false),
                'tipo_pedido' => $tipoPedido,
                'contiene_normal' => $contiene['normal'],
                'contiene_res' => $contiene['res'],
                'contiene_cerdo' => $contiene['cerdo'],
                'contiene_pollo' => $contiene['pollo'],
                'fuera_de_ruta' => $fueraDeRuta,
                'total' => 0,
                'observaciones' => $data['observaciones'] ?? null,
                'comentario_visita' => $data['comentario_visita'] ?? null,
            ]);

            $total = 0;
            foreach ($productos as $item) {
                $cantidad = (float)$item['cantidad'];
                $precio = (float)$item['precio'];
                $subtotal = $precio * $cantidad;
                if ($cantidad > 0) {
                    $pedido->detalles()->create([
                        'producto_id' => $item['producto_id'],
                        'cantidad' => $cantidad,
                        'precio' => $precio,
                        'total' => $subtotal,
                        'observacion_detalle' => $item['observacion'] ?? null,
                        'detalle_extra' => $item['detalle_extra'] ?? null,
                    ]);
                    $total += $subtotal;
                }
            }

            $pedido->update(['total' => $total]);
            $this->registrarVisita(
                userId: (int) $user->id,
                clienteId: $clienteId,
                tipoVisita: $tipoPedido,
                comentario: $data['comentario_visita'] ?? ($data['observaciones'] ?? null)
            );

            DB::commit();
            return response()->json($pedido->load(['detalles.producto', 'cliente', 'clienteBaja', 'user']), 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function syncDetalles(Pedido $pedido, array $productos): array
    {
        $productoTipos = Producto::query()
            ->whereIn('id', collect($productos)->pluck('producto_id')->values()->all())
            ->pluck('tipo', 'id');

        $tiposUnicos = $this->collectProductosTipos($productos, $productoTipos);
        $contiene = $this->buildContiene($tiposUnicos);

        $pedido->detalles()->delete();
        $total = 0.0;

        foreach ($productos as $item) {
            $cantidad = (float) ($item['cantidad'] ?? 0);
            $precio = (float) ($item['precio'] ?? 0);
            if ($cantidad <= 0) continue;

            $tipoItem = $this->normalizeProductoTipo($productoTipos[$item['producto_id']] ?? 'EMBUTIDO');
            $subtotal = $precio * $cantidad;
            $pedido->detalles()->create([
                'producto_id' => $item['producto_id'],
                'cantidad' => $cantidad,
                'precio' => $precio,
                'total' => $subtotal,
                'observacion_detalle' => $item['observacion'] ?? null,
                'detalle_extra' => $this->sanitizeDetalleExtra($tipoItem, $item['detalle_extra'] ?? null),
            ]);
            $total += $subtotal;
        }

        return [$total, $contiene];
    }

    private function authorizePedidoOwnerOrAdmin(Request $request, Pedido $pedido): void
    {
        $user = $request->user();
        $isAdmin = strtoupper((string) ($user->role ?? '')) === 'ADMIN';
        $canTotales = method_exists($user, 'can') && $user->can('Mis pedidos totales');
        $isOwner = (int) ($pedido->user_id ?? 0) === (int) ($user->id ?? 0);

        abort_unless($isOwner || $isAdmin || $canTotales, 403, 'No autorizado');
    }

    private function isEditable(Pedido $pedido): bool
    {
        return in_array((string) $pedido->estado, $this->estadosEditables, true);
    }

    private function normalizeProductoTipo(?string $tipo): string
    {
        $tipo = strtoupper(trim((string) $tipo));
        if ($tipo === 'NORMAL') return 'EMBUTIDO';

        return in_array($tipo, ['EMBUTIDO', 'POLLO', 'RES', 'CERDO'], true)
            ? $tipo
            : 'EMBUTIDO';
    }

    private function collectProductosTipos(array $productos, $productoTipos = null): array
    {
        if (empty($productos)) return [];

        $productoTipos = $productoTipos ?? Producto::query()
            ->whereIn('id', collect($productos)->pluck('producto_id')->values()->all())
            ->pluck('tipo', 'id');

        return collect($productos)
            ->map(fn ($item) => $this->normalizeProductoTipo($productoTipos[$item['producto_id']] ?? 'EMBUTIDO'))
            ->unique()
            ->values()
            ->all();
    }

    private function buildContiene(array $tiposUnicos): array
    {
        return [
            'normal' => in_array('EMBUTIDO', $tiposUnicos),
            'res'    => in_array('RES', $tiposUnicos),
            'cerdo'  => in_array('CERDO', $tiposUnicos),
            'pollo'  => in_array('POLLO', $tiposUnicos),
        ];
    }

    private function detalleDefaultsByTipo(?string $tipo): array
    {
        return match ($this->normalizeProductoTipo($tipo ?? '')) {
            'RES' => [
                'precio_res' => '',
                'res_trozado' => '',
                'res_entero' => '',
                'res_pierna' => '',
                'res_brazo' => '',
                'observacion' => '',
            ],
            'CERDO' => [
                'cerdo_precio_total' => '',
                'cerdo_entero' => '',
                'cerdo_desmembrado' => '',
                'cerdo_corte' => '',
                'cerdo_kilo' => '',
                'observacion' => '',
            ],
            'POLLO' => [
                'pollo_cja_b5' => '',
                'pollo_uni_b5' => '',
                'pollo_cja_b6' => '',
                'pollo_uni_b6' => '',
                'pollo_cja_104' => '',
                'pollo_uni_104' => '',
                'pollo_cja_105' => '',
                'pollo_uni_105' => '',
                'pollo_cja_106' => '',
                'pollo_uni_106' => '',
                'pollo_cja_107' => '',
                'pollo_uni_107' => '',
                'pollo_cja_108' => '',
                'pollo_uni_108' => '',
                'pollo_cja_109' => '',
                'pollo_uni_109' => '',
                'pollo_rango_unidades' => '',
                'pollo_ala' => '',
                'pollo_ala_unidad' => 'KG',
                'pollo_cadera' => '',
                'pollo_cadera_unidad' => 'KG',
                'pollo_pecho' => '',
                'pollo_pecho_unidad' => 'KG',
                'pollo_pi_mu' => '',
                'pollo_pi_mu_unidad' => 'KG',
                'pollo_filete' => '',
                'pollo_filete_unidad' => 'KG',
                'pollo_cuello' => '',
                'pollo_cuello_unidad' => 'KG',
                'pollo_hueso' => '',
                'pollo_hueso_unidad' => 'KG',
                'pollo_menudencia' => '',
                'pollo_menudencia_unidad' => 'KG',
                'pollo_bs' => '',
                'pollo_bs2' => '',
                'observacion' => '',
            ],
            default => [
                'observacion' => '',
            ],
        };
    }

    private function sanitizeDetalleExtra(?string $tipo, $detalle): ?array
    {
        $defaults = $this->detalleDefaultsByTipo($tipo);
        $detalle = is_array($detalle) ? $detalle : [];
        $sanitized = [];

        foreach ($defaults as $key => $value) {
            $sanitized[$key] = array_key_exists($key, $detalle) ? $detalle[$key] : $value;
        }

        return $sanitized;
    }

    private function registrarVisita(int $userId, ?int $clienteId, string $tipoVisita, ?string $comentario): Visita
    {
        return Visita::create([
            'user_id' => $userId,
            'cliente_id' => $clienteId,
            'fecha' => now()->toDateString(),
            'hora' => now()->format('H:i:s'),
            'tipo_visita' => $tipoVisita,
            'comentario' => $comentario ? mb_substr(trim($comentario), 0, 600) : null,
        ]);
    }

    private function isFueraDeRuta(?Cliente $cliente, ?string $fecha): bool
    {
        if (!$cliente || !$fecha) {
            return false;
        }

        try {
            $dayField = match (Carbon::parse($fecha)->dayOfWeekIso) {
                1 => 'lu',
                2 => 'ma',
                3 => 'mi',
                4 => 'ju',
                5 => 'vi',
                6 => 'sa',
                7 => 'do',
                default => null,
            };
        } catch (\Throwable $e) {
            return false;
        }

        if (!$dayField) {
            return false;
        }

        return !(bool) ($cliente->{$dayField} ?? false);
    }
}
