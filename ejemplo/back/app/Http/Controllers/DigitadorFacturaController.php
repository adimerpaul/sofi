<?php

namespace App\Http\Controllers;

use App\Models\Cufd;
use App\Models\Cui;
use App\Models\Pedido;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Barryvdh\DomPDF\Facade\Pdf;
use DOMDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Luecano\NumeroALetras\NumeroALetras;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use SimpleXMLElement;
use Throwable;

class DigitadorFacturaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeDigitador($request);

        $data = $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'search' => 'nullable|string|max:120',
            'solo_factura' => 'nullable',
            'usuario_camion_id' => 'nullable|integer|exists:users,id',
            'tipos' => 'nullable|array',
            'tipos.*' => 'string|in:HUEVO,PET,POLLO,CERDO,EMBUTIDO,RES,NORMAL',
        ]);

        $fechaInicio = $data['fecha_inicio'] ?? now()->toDateString();
        $fechaFin = $data['fecha_fin'] ?? now()->toDateString();
        $soloFactura = $this->parseBoolean($data['solo_factura'] ?? false);
        $search = mb_strtolower(trim((string) ($data['search'] ?? '')));
        $camionId = !empty($data['usuario_camion_id']) ? (int) $data['usuario_camion_id'] : null;
        $tipos = collect($data['tipos'] ?? [])
            ->map(fn ($tipo) => $this->normalizeTipoFiltro(is_string($tipo) ? $tipo : null))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($camionId) {
            $camion = User::query()->find($camionId);
            if (!$camion || !$camion->es_camion) {
                return response()->json(['message' => 'El usuario seleccionado no es tipo camion'], 422);
            }
        }

        $ventas = Venta::query()
            ->with([
                'pedido:id,user_id,cliente_id,usuario_camion_id,fecha,hora,estado,tipo_pago,facturado,tipo_pedido,observaciones',
                'pedido.user:id,name',
                'pedido.cliente:id,nombre,codcli,ci,telefono',
                'pedido.usuarioCamion:id,name,placa',
                'user:id,name',
                'cliente:id,nombre,codcli,ci,telefono',
                'ventaDetalles:id,venta_id,producto_id,cantidad,precio,nombre',
                'ventaDetalles.producto:id,codigo,nombre,tipo',
            ])
            ->whereNotNull('pedido_id')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->whereHas('pedido', function (Builder $q) use ($camionId) {
                $q->where('tipo_pedido', 'REALIZAR_PEDIDO');
                if ($camionId) {
                    $q->where('usuario_camion_id', $camionId);
                }
            })
            ->when($soloFactura, fn (Builder $q) => $q->where('facturado', true))
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        $ventas = $this->filterVentasDetallesByTipos($ventas, $tipos);

        if ($search !== '') {
            $ventas = $ventas->filter(function (Venta $venta) use ($search) {
                $productos = $venta->ventaDetalles->map(function (VentaDetalle $d) {
                    return $d->nombre ?: ($d->producto?->nombre ?: '');
                })->implode(' ');

                $haystack = mb_strtolower(implode(' ', [
                    (string) $venta->id,
                    (string) ($venta->pedido_id ?? ''),
                    (string) ($venta->pedido?->cliente?->nombre ?? ''),
                    (string) ($venta->cliente?->nombre ?? ''),
                    (string) ($venta->pedido?->user?->name ?? ''),
                    (string) ($venta->user?->name ?? ''),
                    $productos,
                ]));

                return str_contains($haystack, $search);
            })->values();
        }

        $rows = $ventas->map(function (Venta $venta) {
            $productosVenta = $venta->ventaDetalles->map(function (VentaDetalle $d) {
                return [
                    'id' => $d->id,
                    'producto_id' => $d->producto_id,
                    'codigo' => $d->producto?->codigo ?: ('#' . $d->producto_id),
                    'nombre' => $d->nombre ?: ($d->producto?->nombre ?: ('Producto #' . $d->producto_id)),
                    'tipo' => strtoupper((string) ($d->producto?->tipo ?? 'NORMAL')),
                    'cantidad' => (float) $d->cantidad,
                    'precio' => (float) $d->precio,
                    'subtotal' => (float) $d->cantidad * (float) $d->precio,
                ];
            })->values();

            $tipos = $productosVenta->pluck('tipo')->unique()->values();

            return [
                'venta_id' => $venta->id,
                'comanda' => $venta->pedido_id,
                'vendedor' => $venta->pedido?->user?->name,
                'cliente' => $venta->pedido?->cliente?->nombre ?: $venta->cliente?->nombre,
                'camion' => $venta->pedido?->usuarioCamion?->name,
                'camion_id' => (int) ($venta->pedido?->usuario_camion_id ?? 0),
                'camion_placa' => (string) ($venta->pedido?->usuarioCamion?->placa ?? ''),
                'telefono' => (string) ($venta->pedido?->cliente?->telefono ?: $venta->cliente?->telefono ?: ''),
                'tipo' => $tipos,
                'productos' => $productosVenta,
                'fecha' => (string) ($venta->fecha ?? ''),
                'hora' => (string) ($venta->hora ?? ''),
                'pago' => (string) ($venta->tipo_pago ?? ''),
                'facturado' => (bool) $venta->facturado,
                'factura_estado' => (string) ($venta->factura_estado ?? 'SIN_GESTION'),
                'factura_error' => (string) ($venta->factura_error ?? ''),
                'estado' => (string) ($venta->estado ?? ''),
                'total' => (float) ($venta->total ?? 0),
                'pedido_id' => $venta->pedido_id,
                'generado_por' => $venta->user?->name,
                'cuf' => (string) ($venta->cuf ?? ''),
                'online' => (bool) ($venta->online ?? false),
                'observaciones' => (string) ($venta->pedido?->observaciones ?? ''),
                'detalle_edit' => [
                    'tipo_pago' => (string) ($venta->tipo_pago ?? ''),
                    'facturado' => (bool) $venta->facturado,
                    'factura_estado' => (string) ($venta->factura_estado ?? 'SIN_GESTION'),
                    'factura_error' => (string) ($venta->factura_error ?? ''),
                    'observaciones' => (string) ($venta->pedido?->observaciones ?? ''),
                    'productos' => $productosVenta,
                ],
            ];
        })->values();

        $camiones = User::query()
            ->where('es_camion', true)
            ->orderBy('name')
            ->get(['id', 'name', 'placa'])
            ->map(fn (User $camion) => [
                'id' => $camion->id,
                'name' => $camion->name,
                'placa' => (string) ($camion->placa ?? ''),
            ])
            ->values();

        $pedidosSinVenta = Pedido::query()
            ->with(['user:id,name', 'cliente:id,nombre'])
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->whereNull('venta_id')
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->get()
            ->map(function (Pedido $pedido) {
                return [
                    'comanda' => $pedido->id,
                    'fecha' => (string) $pedido->fecha,
                    'hora' => (string) ($pedido->hora ?? ''),
                    'vendedor' => $pedido->user?->name,
                    'cliente' => $pedido->cliente?->nombre,
                    'facturado' => (bool) $pedido->facturado,
                    'estado' => (string) ($pedido->estado ?? ''),
                ];
            })
            ->values();

        $stats = [
            'total_ventas' => $rows->count(),
            'monto_total_ventas' => (float) $rows->sum('total'),
            'ventas_facturadas' => $rows->where('facturado', true)->count(),
            'ventas_no_facturadas' => $rows->where('facturado', false)->count(),
            'pendientes_factura' => $rows->where('factura_estado', 'PENDIENTE')->count(),
            'pedidos_sin_venta' => $pedidosSinVenta->count(),
        ];

        return response()->json([
            'data' => $rows->values(),
            'pedidos_sin_venta' => $pedidosSinVenta,
            'stats' => $stats,
            'camiones' => $camiones,
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'solo_factura' => $soloFactura,
                'usuario_camion_id' => $camionId,
                'tipos' => $tipos,
            ],
        ]);
    }

    public function updatePedido(Request $request, Pedido $pedido)
    {
        $this->authorizeDigitador($request);

        if ($pedido->venta && $this->isFacturaBloqueada($pedido->venta)) {
            return response()->json([
                'message' => 'La factura ya fue generada. Anule la venta para volver a editar.',
            ], 422);
        }

        $data = $request->validate([
            'tipo_pago' => 'sometimes|nullable|string|in:Contado,QR,Credito,Boleta anterior',
            'facturado' => 'sometimes|boolean',
            'fecha' => 'sometimes|nullable|date',
            'hora' => 'sometimes|nullable|string|max:50',
            'observaciones' => 'sometimes|nullable|string|max:600',
        ]);

        $pedido->update($data);

        if ($pedido->venta && array_key_exists('facturado', $data)) {
            $pedido->venta->facturado = (bool) $data['facturado'];
            $pedido->venta->factura_estado = $data['facturado'] ? 'PENDIENTE' : 'SIN_GESTION';
            $pedido->venta->save();
        }

        return response()->json([
            'message' => 'Pedido actualizado',
            'pedido' => $pedido->fresh(['cliente:id,nombre', 'user:id,name']),
        ]);
    }

    public function updateVenta(Request $request, Venta $venta)
    {
        $this->authorizeDigitador($request);

        if ($this->isFacturaBloqueada($venta)) {
            return response()->json([
                'message' => 'La factura ya fue generada. Anule la venta para volver a editar.',
            ], 422);
        }

        $data = $request->validate([
            'estado' => 'sometimes|nullable|string|in:Activo,Anulada',
            'tipo_pago' => 'sometimes|nullable|string|in:Efectivo,QR,Contado,Credito,Boleta anterior',
            'facturado' => 'sometimes|boolean',
            'factura_estado' => 'sometimes|nullable|string|in:SIN_GESTION,PENDIENTE,FACTURADO,ERROR',
            'factura_error' => 'sometimes|nullable|string|max:2000',
            'observaciones' => 'sometimes|nullable|string|max:600',
            'productos' => 'sometimes|array',
            'productos.*.id' => 'required_with:productos|integer',
            'productos.*.cantidad' => 'required_with:productos|numeric|min:0',
            'productos.*.precio' => 'required_with:productos|numeric|min:0',
        ]);

        return DB::transaction(function () use ($venta, $data) {
            $venta->fill(array_intersect_key($data, array_flip(['estado', 'tipo_pago', 'facturado', 'factura_estado', 'factura_error'])));
            if ($venta->isDirty('facturado') && !$venta->isDirty('factura_estado')) {
                $venta->factura_estado = $venta->facturado ? 'PENDIENTE' : 'SIN_GESTION';
            }
            if ($venta->isDirty('facturado') && !$venta->facturado) {
                $venta->factura_error = null;
            }
            $venta->save();

            if (!empty($data['productos'])) {
                $ids = collect($data['productos'])->pluck('id')->map(fn ($v) => (int) $v)->all();
                $detalles = VentaDetalle::query()
                    ->where('venta_id', $venta->id)
                    ->whereIn('id', $ids)
                    ->get()
                    ->keyBy('id');

                foreach ($data['productos'] as $item) {
                    $detalle = $detalles->get((int) $item['id']);
                    if (!$detalle) {
                        continue;
                    }

                    $detalle->cantidad = (float) $item['cantidad'];
                    $detalle->precio = (float) $item['precio'];
                    $detalle->save();
                }

                $total = (float) VentaDetalle::query()
                    ->where('venta_id', $venta->id)
                    ->selectRaw('COALESCE(SUM(cantidad * precio), 0) as total')
                    ->value('total');
                $venta->total = round($total, 2);
                $venta->save();
            }

            if ($venta->pedido) {
                $venta->pedido->facturado = (bool) $venta->facturado;
                if (array_key_exists('observaciones', $data)) {
                    $venta->pedido->observaciones = $data['observaciones'];
                }
                $venta->pedido->save();
            }

            return response()->json([
                'message' => 'Venta actualizada',
                'venta' => $venta->fresh(['user:id,name', 'cliente:id,nombre', 'ventaDetalles.producto:id,codigo,nombre,tipo']),
            ]);
        });
    }

    public function generarFacturaTodos(Request $request)
    {
        $this->authorizeDigitador($request);

        $data = $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
        ]);

        $fechaInicio = $data['fecha_inicio'] ?? now()->toDateString();
        $fechaFin = $data['fecha_fin'] ?? now()->toDateString();

        $codigoPuntoVenta = 0;
        $codigoSucursal = 0;

        $cui = Cui::query()
            ->where('codigoPuntoVenta', $codigoPuntoVenta)
            ->where('codigoSucursal', $codigoSucursal)
            ->where('fechaVigencia', '>=', now())
            ->first();

        if (!$cui) {
            return response()->json(['message' => 'No existe CUI vigente para facturar'], 422);
        }

        $cufd = Cufd::query()
            ->where('codigoPuntoVenta', $codigoPuntoVenta)
            ->where('codigoSucursal', $codigoSucursal)
            ->where('fechaVigencia', '>=', now())
            ->first();

        if (!$cufd) {
            return response()->json(['message' => 'No existe CUFD vigente para facturar'], 422);
        }

        $ventas = Venta::query()
            ->with(['cliente', 'pedido.cliente', 'ventaDetalles.producto', 'user'])
            ->whereNotNull('pedido_id')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->where('estado', 'Activo')
            ->where('facturado', true)
            ->where(function (Builder $q) {
                $q->whereNull('factura_estado')
                    ->orWhereIn('factura_estado', ['SIN_GESTION', 'PENDIENTE', 'ERROR']);
            })
            ->whereHas('pedido', function (Builder $q) {
                $q->where('tipo_pedido', 'REALIZAR_PEDIDO');
            })
            ->orderBy('id')
            ->get();

        if ($ventas->isEmpty()) {
            return response()->json([
                'message' => 'No hay ventas pendientes para facturar en el rango seleccionado',
                'facturadas' => 0,
                'errores' => 0,
            ]);
        }

        $ok = 0;
        $errores = [];

        foreach ($ventas as $venta) {
            try {
                $this->emitirFacturaVenta($venta, $cui, $cufd, $request->user());
                $ok++;
            } catch (Throwable $e) {
                $msg = (string) $e->getMessage();
                if (str_contains($msg, 'Sin detalles facturables')) {
                    // Caso esperado para ventas con lineas en 0: no enviar a SIAT por ahora.
                    continue;
                }
                $venta->factura_estado = 'ERROR';
                $venta->factura_error = mb_substr($msg, 0, 2000);
                $venta->save();
                $errores[] = [
                    'venta_id' => $venta->id,
                    'comanda' => $venta->pedido_id,
                    'error' => $msg,
                ];
            }
        }

        return response()->json([
            'message' => 'Proceso de facturacion finalizado',
            'facturadas' => $ok,
            'errores' => count($errores),
            'detalle_errores' => $errores,
        ]);
    }

    public function imprimirFacturas(Request $request)
    {
        $this->authorizeDigitador($request);
        [$fechaInicio, $fechaFin, $camionId, $tipos] = $this->extractRangoFechas($request);

        $ventas = $this->queryVentasDigitador($fechaInicio, $fechaFin, $camionId, $tipos)
            ->where('facturado', true)
            ->where('factura_estado', 'FACTURADO')
            ->whereNotNull('cuf')
            ->get();
        $ventas = $this->filterVentasDetallesByTipos($ventas, $tipos);

        if ($ventas->isEmpty()) {
            $message = $camionId
                ? 'No hay facturas emitidas para imprimir para el camion seleccionado en el rango'
                : 'No hay facturas emitidas para imprimir en el rango seleccionado';
            return response()->json(['message' => $message], 422);
        }

        $items = $this->buildFacturaItems($ventas);
        $suffix = $camionId ? "_camion_{$camionId}" : '';

        $pdf = Pdf::loadView('pdf.digitador_facturas_masivo', [
            'items' => $items,
            'nit' => (string) env('NIT'),
            'razon' => (string) env('RAZON'),
            'direccion' => (string) env('DIRECCION'),
            'telefono' => (string) env('TELEFONO'),
        ])->setPaper('letter');

        return $pdf->download("facturas_emitidas_{$fechaInicio}_{$fechaFin}{$suffix}.pdf");
    }

    public function imprimirVouchers(Request $request)
    {
        $this->authorizeDigitador($request);
        [$fechaInicio, $fechaFin, $camionId, $tipos] = $this->extractRangoFechas($request);

        $ventas = $this->queryVentasDigitador($fechaInicio, $fechaFin, $camionId, $tipos)->get();
        $ventas = $this->filterVentasDetallesByTipos($ventas, $tipos);
        if ($ventas->isEmpty()) {
            $message = $camionId
                ? 'No hay ventas para imprimir vouchers para el camion seleccionado en el rango'
                : 'No hay ventas para imprimir vouchers en el rango seleccionado';
            return response()->json(['message' => $message], 422);
        }

        $suffix = $camionId ? "_camion_{$camionId}" : '';

        $pdf = Pdf::loadView('pdf.digitador_vouchers_masivo', [
            'ventas' => $ventas,
            'razon' => (string) env('RAZON'),
        ])->setPaper('letter');

        return $pdf->download("vouchers_ventas_{$fechaInicio}_{$fechaFin}{$suffix}.pdf");
    }

    public function imprimirFacturaVenta(Request $request, Venta $venta)
    {
        $this->authorizeDigitador($request);
        if (!$venta->pedido_id) {
            return response()->json(['message' => 'La venta no esta asociada a comanda'], 422);
        }
        if (!$venta->facturado || strtoupper((string) $venta->factura_estado) !== 'FACTURADO' || !$venta->cuf) {
            return response()->json(['message' => 'La venta no tiene factura emitida para imprimir'], 422);
        }
        $venta->loadMissing([
            'pedido:id,user_id,cliente_id,fecha,hora,estado,tipo_pago,facturado,tipo_pedido,observaciones',
            'pedido.user:id,name',
            'pedido.cliente:id,nombre,codcli,ci,telefono',
            'user:id,name',
            'cliente:id,nombre,codcli,ci,telefono',
            'ventaDetalles:id,venta_id,producto_id,cantidad,precio,nombre',
            'ventaDetalles.producto:id,codigo,nombre,tipo',
        ]);
        $items = $this->buildFacturaItems(collect([$venta]));
        $pdf = Pdf::loadView('pdf.digitador_facturas_masivo', [
            'items' => $items,
            'nit' => (string) env('NIT'),
            'razon' => (string) env('RAZON'),
            'direccion' => (string) env('DIRECCION'),
            'telefono' => (string) env('TELEFONO'),
        ])->setPaper('letter');
        return $pdf->download("factura_venta_{$venta->id}.pdf");
    }

    public function imprimirVoucherVenta(Request $request, Venta $venta)
    {
        $this->authorizeDigitador($request);
        if (!$venta->pedido_id) {
            return response()->json(['message' => 'La venta no esta asociada a comanda'], 422);
        }
        $venta->loadMissing([
            'pedido:id,user_id,cliente_id,fecha,hora,estado,tipo_pago,facturado,tipo_pedido,observaciones',
            'pedido.user:id,name',
            'pedido.cliente:id,nombre,codcli,ci,telefono',
            'user:id,name',
            'cliente:id,nombre,codcli,ci,telefono',
            'ventaDetalles:id,venta_id,producto_id,cantidad,precio,nombre',
            'ventaDetalles.producto:id,codigo,nombre,tipo',
        ]);
        $pdf = Pdf::loadView('pdf.digitador_vouchers_masivo', [
            'ventas' => collect([$venta]),
            'razon' => (string) env('RAZON'),
        ])->setPaper('letter');
        return $pdf->download("voucher_venta_{$venta->id}.pdf");
    }

    private function emitirFacturaVenta(Venta $venta, Cui $cui, Cufd $cufd, $usuarioAuth): void
    {
        $venta->loadMissing(['cliente', 'pedido.cliente', 'ventaDetalles', 'user']);

        $cliente = $venta->cliente ?: $venta->pedido?->cliente;
        if (!$cliente) {
            throw new \RuntimeException('No existe cliente asociado para la venta');
        }

        if ($venta->ventaDetalles->isEmpty()) {
            throw new \RuntimeException('La venta no tiene productos para facturar');
        }

        $detallesFacturables = $venta->ventaDetalles->filter(function (VentaDetalle $d) {
            $cantidad = (float) ($d->cantidad ?? 0);
            $precio = (float) ($d->precio ?? 0);
            return ($cantidad > 0) && ($precio > 0) && (($cantidad * $precio) > 0);
        })->values();

        if ($detallesFacturables->isEmpty()) {
            $venta->factura_estado = 'SIN_GESTION';
            $venta->factura_error = 'Sin detalles facturables: todos los productos tienen monto 0.';
            $venta->save();
            throw new \RuntimeException('Sin detalles facturables para enviar a impuestos (monto 0).');
        }

        $numeroDocumento = trim((string) ($cliente->ci ?? ''));
        if ($numeroDocumento === '') {
            throw new \RuntimeException('El cliente no tiene CI/NIT para facturar');
        }

        $codigoTipoDocumentoIdentidad = (int) ($cliente->codigoTipoDocumentoIdentidad ?: 1);
        $complemento = (string) ($cliente->complemento ?? '');

        $leyendas = [
            'Ley N 453: Puedes acceder a la reclamacion cuando tus derechos han sido vulnerados.',
            'Ley N 453: El proveedor debe brindar atencion sin discriminacion, con respeto, calidez y cordialidad.',
            'Ley N 453: Esta prohibido importar, distribuir o comercializar productos expirados o prontos a expirar.',
            'Ley N 453: Tienes derecho a recibir informacion sobre las caracteristicas de los productos que consumes.',
            'Ley N 453: Los servicios deben suministrarse en condiciones de inocuidad, calidad y seguridad.',
        ];
        $leyendaRandom = $leyendas[array_rand($leyendas)];

        $detalleFactura = '';
        $montoFacturable = 0.0;
        foreach ($detallesFacturables as $detalle) {
            $descripcion = trim((string) ($detalle->nombre ?: ($detalle->producto?->nombre ?? 'Producto')));
            $subTotal = (float) $detalle->precio * (float) $detalle->cantidad;
            $montoFacturable += $subTotal;
            $detalleFactura .= "<detalle>
                <actividadEconomica>463000</actividadEconomica>
                <codigoProductoSin>62121</codigoProductoSin>
                <codigoProducto>{$detalle->producto_id}</codigoProducto>
                <descripcion>{$this->xmlSafe($descripcion)}</descripcion>
                <cantidad>{$detalle->cantidad}</cantidad>
                <unidadMedida>62</unidadMedida>
                <precioUnitario>{$detalle->precio}</precioUnitario>
                <montoDescuento>0</montoDescuento>
                <subTotal>{$subTotal}</subTotal>
                <numeroSerie xsi:nil='true'/>
                <numeroImei xsi:nil='true'/>
            </detalle>";
        }

        $token = (string) env('TOKEN');
        $nit = (string) env('NIT');
        $ambiente = (int) env('AMBIENTE');
        $codigoSucursal = 0;
        $codigoModalidad = (int) env('MODALIDAD');
        $codigoEmision = 1;
        $tipoFacturaDocumento = 1;
        $codigoDocumentoSector = 1;
        $codigoPuntoVenta = 0;
        $codigoSistema = (string) env('CODIGO_SISTEMA');
        $fechaEnvio = date('Y-m-d\TH:i:s.000');

        $cufGen = new CUF();
        $cuf = $cufGen->obtenerCUF(
            $nit,
            date('YmdHis000'),
            (string) $codigoSucursal,
            (string) $codigoModalidad,
            (string) $codigoEmision,
            (string) $tipoFacturaDocumento,
            (string) $codigoDocumentoSector,
            (string) $venta->id,
            (string) $codigoPuntoVenta
        ) . $cufd->codigoControl;

        $usuarioCabecera = (string) ($usuarioAuth?->name ?? $venta->user?->name ?? 'SISTEMA');
        $usuarioCabecera = explode(' ', trim($usuarioCabecera))[0] ?? 'SISTEMA';

        $xmlText = "<?xml version='1.0' encoding='UTF-8' standalone='yes'?>
        <facturaComputarizadaCompraVenta xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:noNamespaceSchemaLocation='facturaComputarizadaCompraVenta.xsd'>
        <cabecera>
        <nitEmisor>{$nit}</nitEmisor>
        <razonSocialEmisor>" . env('RAZON') . "</razonSocialEmisor>
        <municipio>Oruro</municipio>
        <telefono>" . env('TELEFONO') . "</telefono>
        <numeroFactura>{$venta->id}</numeroFactura>
        <cuf>{$cuf}</cuf>
        <cufd>{$cufd->codigo}</cufd>
        <codigoSucursal>{$codigoSucursal}</codigoSucursal>
        <direccion>" . env('DIRECCION') . "</direccion>
        <codigoPuntoVenta>{$codigoPuntoVenta}</codigoPuntoVenta>
        <fechaEmision>{$fechaEnvio}</fechaEmision>
        <nombreRazonSocial>{$this->xmlSafe((string) $cliente->nombre)}</nombreRazonSocial>
        <codigoTipoDocumentoIdentidad>{$codigoTipoDocumentoIdentidad}</codigoTipoDocumentoIdentidad>
        <numeroDocumento>{$this->xmlSafe($numeroDocumento)}</numeroDocumento>
        <complemento>{$this->xmlSafe($complemento)}</complemento>
        <codigoCliente>{$cliente->id}</codigoCliente>
        <codigoMetodoPago>1</codigoMetodoPago>
        <numeroTarjeta xsi:nil='true'/>
        <montoTotal>{$montoFacturable}</montoTotal>
        <montoTotalSujetoIva>{$montoFacturable}</montoTotalSujetoIva>
        <codigoMoneda>1</codigoMoneda>
        <tipoCambio>1</tipoCambio>
        <montoTotalMoneda>{$montoFacturable}</montoTotalMoneda>
        <montoGiftCard xsi:nil='true'/>
        <descuentoAdicional>0</descuentoAdicional>
        <codigoExcepcion>" . ($codigoTipoDocumentoIdentidad === 5 ? 1 : 0) . "</codigoExcepcion>
        <cafc xsi:nil='true'/>
        <leyenda>{$this->xmlSafe($leyendaRandom)}</leyenda>
        <usuario>{$this->xmlSafe($usuarioCabecera)}</usuario>
        <codigoDocumentoSector>{$codigoDocumentoSector}</codigoDocumentoSector>
        </cabecera>
        {$detalleFactura}
        </facturaComputarizadaCompraVenta>";

        $xml = new SimpleXMLElement($xmlText);
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        $outputDir = storage_path('app/archivos');
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0775, true);
        }
        $xmlPath = $outputDir . DIRECTORY_SEPARATOR . $venta->id . '.xml';
        $gzPath = $xmlPath . '.gz';
        $dom->save($xmlPath);

        $validator = new DOMDocument();
        $validator->load($xmlPath);
        $xsdPath = public_path('facturaComputarizadaCompraVenta.xsd');
        if (!is_file($xsdPath) || !$validator->schemaValidate($xsdPath)) {
            throw new \RuntimeException('XML de factura invalido contra XSD');
        }

        $fp = gzopen($gzPath, 'w9');
        gzwrite($fp, file_get_contents($xmlPath));
        gzclose($fp);

        $archivo = $this->getFileGzip($gzPath);
        $hashArchivo = hash('sha256', $archivo);

        $client = new \SoapClient('https://pilotosiatservicios.impuestos.gob.bo/v2/ServicioFacturacionCompraVenta?WSDL', [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => 'apikey: TokenApi ' . $token,
                    'timeout' => 8,
                ],
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => 1,
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
            'connection_timeout' => 8,
        ]);

        $result = $client->recepcionFactura([
            'SolicitudServicioRecepcionFactura' => [
                'codigoAmbiente' => $ambiente,
                'codigoDocumentoSector' => $codigoDocumentoSector,
                'codigoEmision' => $codigoEmision,
                'codigoModalidad' => $codigoModalidad,
                'codigoPuntoVenta' => $codigoPuntoVenta,
                'codigoSistema' => $codigoSistema,
                'codigoSucursal' => $codigoSucursal,
                'cufd' => $cufd->codigo,
                'cuis' => $cui->codigo,
                'nit' => $nit,
                'tipoFacturaDocumento' => $tipoFacturaDocumento,
                'archivo' => $archivo,
                'fechaEnvio' => $fechaEnvio,
                'hashArchivo' => $hashArchivo,
            ],
        ]);

        $transaccion = (bool) ($result->RespuestaServicioFacturacion->transaccion ?? false);
        if (!$transaccion) {
            $descripcion = (string) ($result->RespuestaServicioFacturacion->mensajesList->descripcion ?? 'Error desconocido');
            $venta->cuf = $cuf;
            $venta->cufd = $cufd->codigo;
            $venta->online = false;
            $venta->leyenda = $leyendaRandom;
            $venta->factura_estado = 'PENDIENTE';
            $venta->factura_error = $descripcion;
            $venta->save();
            throw new \RuntimeException('Error al enviar a impuestos: ' . $descripcion);
        }

        $venta->cuf = $cuf;
        $venta->cufd = $cufd->codigo;
        $venta->online = true;
        $venta->leyenda = $leyendaRandom;
        $venta->factura_estado = 'FACTURADO';
        $venta->factura_error = null;
        $venta->save();
    }

    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }
        $normalized = mb_strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'si', 'yes', 'on'], true);
    }

    private function getFileGzip(string $fileName): string
    {
        $handle = fopen($fileName, 'rb');
        $contents = fread($handle, filesize($fileName));
        fclose($handle);
        return $contents;
    }

    private function extractRangoFechas(Request $request): array
    {
        $data = $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'usuario_camion_id' => 'nullable|integer|exists:users,id',
            'tipos' => 'nullable|array',
            'tipos.*' => 'string|in:HUEVO,PET,POLLO,CERDO,EMBUTIDO,RES,NORMAL',
        ]);
        $camionId = !empty($data['usuario_camion_id']) ? (int) $data['usuario_camion_id'] : null;
        $tipos = collect($data['tipos'] ?? [])
            ->map(fn ($tipo) => $this->normalizeTipoFiltro(is_string($tipo) ? $tipo : null))
            ->filter()
            ->unique()
            ->values()
            ->all();
        if ($camionId) {
            $camion = User::query()->find($camionId);
            if (!$camion || !$camion->es_camion) {
                abort(422, 'El usuario seleccionado no es tipo camion');
            }
        }
        return [
            $data['fecha_inicio'] ?? now()->toDateString(),
            $data['fecha_fin'] ?? now()->toDateString(),
            $camionId,
            $tipos,
        ];
    }

    private function queryVentasDigitador(string $fechaInicio, string $fechaFin, ?int $camionId = null, array $tipos = []): Builder
    {
        return Venta::query()
            ->with([
                'pedido:id,user_id,cliente_id,usuario_camion_id,fecha,hora,estado,tipo_pago,facturado,tipo_pedido,observaciones',
                'pedido.user:id,name',
                'pedido.cliente:id,nombre,codcli,ci,telefono',
                'pedido.usuarioCamion:id,name,placa',
                'user:id,name',
                'cliente:id,nombre,codcli,ci,telefono',
                'ventaDetalles:id,venta_id,producto_id,cantidad,precio,nombre',
                'ventaDetalles.producto:id,codigo,nombre,tipo',
            ])
            ->whereNotNull('pedido_id')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->whereHas('pedido', function (Builder $q) use ($camionId) {
                $q->where('tipo_pedido', 'REALIZAR_PEDIDO');
                if ($camionId) {
                    $q->where('usuario_camion_id', $camionId);
                }
            })
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->orderBy('id', 'desc');
    }

    private function filterVentasDetallesByTipos($ventas, array $tipos)
    {
        if (empty($tipos)) {
            return $ventas;
        }

        $selected = collect($tipos)
            ->map(fn (string $tipo) => $this->normalizeTipoFiltro($tipo))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $ventas
            ->map(function (Venta $venta) use ($selected) {
                $detalles = $venta->ventaDetalles
                    ->filter(function (VentaDetalle $detalle) use ($selected) {
                        $tipo = $this->normalizeTipoFiltro((string) ($detalle->producto?->tipo ?? 'NORMAL'));
                        return in_array($tipo, $selected, true);
                    })
                    ->values();
                $venta->setRelation('ventaDetalles', $detalles);
                return $venta;
            })
            ->filter(fn (Venta $venta) => $venta->ventaDetalles->isNotEmpty())
            ->values();
    }

    private function normalizeTipoFiltro(?string $tipo): ?string
    {
        $value = strtoupper(trim((string) ($tipo ?? '')));
        if ($value === '' || $value === 'TODOS') {
            return null;
        }
        if (str_contains($value, 'HUEVO')) {
            return 'HUEVO';
        }
        if (str_contains($value, 'PET')) {
            return 'PET';
        }
        if (str_contains($value, 'POLLO')) {
            return 'POLLO';
        }
        if (str_contains($value, 'CERDO')) {
            return 'CERDO';
        }
        if (str_contains($value, 'RES')) {
            return 'RES';
        }
        if (str_contains($value, 'EMBUTIDO') || $value === 'NORMAL') {
            return 'EMBUTIDO';
        }
        return null;
    }

    private function buildFacturaItems($ventas)
    {
        $formatter = new NumeroALetras();
        $nit = (string) env('NIT');
        $baseQr = rtrim((string) env('URL_SIAT2'), '/');

        return $ventas->map(function (Venta $venta) use ($formatter, $nit, $baseQr) {
            $detalles = $venta->ventaDetalles->filter(function (VentaDetalle $d) {
                return ((float) $d->cantidad > 0) && ((float) $d->precio > 0);
            })->values();
            $monto = (float) $detalles->sum(fn (VentaDetalle $d) => (float) $d->cantidad * (float) $d->precio);
            $entero = (int) floor($monto);
            $decimal = (int) round(($monto - $entero) * 100);
            $literal = trim($formatter->toWords($entero)) . ' ' . str_pad((string) $decimal, 2, '0', STR_PAD_LEFT) . '/100 Bolivianos';
            $urlQr = "{$baseQr}/consulta/QR?nit={$nit}&cuf={$venta->cuf}&numero={$venta->id}&t=2";
            $qrSvg = base64_encode(QrCode::format('svg')->size(180)->errorCorrection('H')->generate($urlQr));

            return [
                'venta' => $venta,
                'detalles' => $detalles,
                'monto' => $monto,
                'literal' => $literal,
                'url_qr' => $urlQr,
                'qr_svg' => $qrSvg,
            ];
        })->values();
    }

    private function xmlSafe(?string $value): string
    {
        $value = $value ?? '';
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        }
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function authorizeDigitador(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 401, 'No autenticado');

        $isAdmin = strtoupper((string) ($user->role ?? '')) === 'ADMIN';
        $canDigitador = method_exists($user, 'can') && $user->can('Digitador factura');
        abort_unless($isAdmin || $canDigitador, 403, 'No autorizado');
    }

    private function isFacturaBloqueada(Venta $venta): bool
    {
        $facturado = (bool) ($venta->facturado ?? false);
        $emitida = strtoupper((string) ($venta->factura_estado ?? '')) === 'FACTURADO';
        $activa = strtoupper((string) ($venta->estado ?? '')) === 'ACTIVO';
        return $facturado && $emitida && $activa;
    }
}
