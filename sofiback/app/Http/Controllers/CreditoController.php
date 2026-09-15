<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditoController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:cobranzasrecojo');
    }

    public function clientes(Request $request)
    {
        $datos = $request->validate(['buscar' => 'required|string|min:2|max:100']);
        return DB::table('tbclientes')->where(function ($q) use ($datos) {
            $q->where('Nombres', 'like', '%'.$datos['buscar'].'%')
                ->orWhere('Id', 'like', '%'.$datos['buscar'].'%');
        })->orderBy('Nombres')->limit(30)->get(['Cod_Aut as id', 'Nombres as nombre', 'Id as nit']);
    }

    public function index(Request $request)
    {
        $datos = $request->validate(['cliente_id' => 'nullable|integer|exists:tbclientes,Cod_Aut']);
        $filas = $this->deudas($datos['cliente_id'] ?? null);
        return ['deudas' => $filas->sortByDesc('fecha')->values(), 'saldo' => round($filas->sum('saldo'), 2)];
    }

    /**
     * Todos los clientes con lo que deben, para la lista principal de
     * cobranzas: los datos del cliente y una columna con su deuda. Son unos
     * pocos miles, asi que va todo de una vez y la pantalla filtra y busca.
     */
    public function resumen()
    {
        $porCliente = $this->deudas(null)->groupBy('cliente_id');

        $vendedores = DB::table('personal')->whereRaw("TRIM(COALESCE(ci, '')) <> ''")
            ->get(['ci', 'Nombre1', 'App1'])
            ->mapWithKeys(function ($p) {
                return [trim($p->ci) => trim(trim((string) $p->Nombre1) . ' ' . trim((string) $p->App1))];
            });

        $clientes = DB::table('tbclientes')->orderBy('Nombres')
            ->get(['Cod_Aut', 'Id', 'Nombres', 'Telf', 'Direccion', 'zona', 'CiVend', 'Canal', 'venta'])
            ->map(function ($c) use ($porCliente, $vendedores) {
                $deudas = $porCliente->get($c->Cod_Aut) ?? collect();
                $pendientes = $deudas->where('saldo', '>', 0);
                $desde = $pendientes->min('fecha');

                return [
                    'id' => (int) $c->Cod_Aut,
                    'nombre' => trim((string) $c->Nombres),
                    'nit' => trim((string) $c->Id),
                    'telefono' => trim((string) $c->Telf),
                    'direccion' => trim((string) $c->Direccion),
                    'zona' => trim((string) $c->zona),
                    'canal' => trim((string) $c->Canal),
                    'vendedor' => $vendedores->get(trim((string) $c->CiVend), ''),
                    'activo' => strtoupper(trim((string) $c->venta)) !== 'INACTIVO',
                    'saldo' => round($pendientes->sum('saldo'), 2),
                    'deudas' => $pendientes->count(),
                    // Desde cuando debe: la deuda pendiente mas vieja.
                    'desde' => $desde ? substr((string) $desde, 0, 10) : null,
                    'dias' => $desde ? (int) floor((time() - strtotime(substr((string) $desde, 0, 10))) / 86400) : null,
                ];
            });

        $conDeuda = $clientes->where('saldo', '>', 0);

        return [
            'clientes' => $clientes->values(),
            'totales' => [
                'clientes' => $clientes->count(),
                'con_deuda' => $conDeuda->count(),
                'saldo' => round($conDeuda->sum('saldo'), 2),
                'deudas' => $conDeuda->sum('deudas'),
            ],
        ];
    }

    /**
     * El detalle de un cliente: sus datos, todas sus deudas (pendientes y
     * pagadas) y las ventas que se le hicieron a credito con sus productos.
     */
    public function detalle($id)
    {
        $c = DB::table('tbclientes')->where('Cod_Aut', $id)->first();
        abort_unless($c, 404, 'El cliente no existe');

        $vendedor = DB::table('personal')->whereRaw('TRIM(ci) = ?', [trim((string) $c->CiVend)])->first(['Nombre1', 'App1']);
        $deudas = $this->deudas((int) $id)->sortByDesc('fecha')->values();

        $abonos = DB::table('creditos_abonos')->where('origen', 'factura')
            ->select('deuda_id', DB::raw('SUM(monto) as pagado'))->groupBy('deuda_id')->pluck('pagado', 'deuda_id');

        $ventas = DB::table('facturas')->where('cliente_id', $id)
            ->whereIn('tipo_pago', ['CRÉDITO', 'CREDITO'])->whereNull('deleted_at')
            ->orderByDesc('fecha')->orderByDesc('id')
            ->get(['id', 'fecha', 'hora', 'tipo_comprobante', 'total', 'estado', 'pedido_nro', 'pedido_tipo', 'observacion']);
        $detalles = DB::table('factura_detalles')->whereIn('factura_id', $ventas->pluck('id'))->whereNull('deleted_at')
            ->orderBy('id')->get(['factura_id', 'cod_prod', 'nombre', 'unidad', 'cantidad', 'peso', 'precio', 'subtotal'])
            ->groupBy('factura_id');

        $ventas = $ventas->map(function ($v) use ($abonos, $detalles) {
            $total = (float) $v->total;
            $pagado = (float) ($abonos[$v->id] ?? 0);
            $activa = $v->estado === 'ACTIVO';
            return [
                'id' => $v->id,
                'fecha' => substr((string) $v->fecha, 0, 10),
                'hora' => substr((string) $v->hora, 0, 5),
                'comprobante' => $v->tipo_comprobante,
                'pedido' => $v->pedido_nro,
                'total' => $total,
                'pagado' => $pagado,
                'saldo' => $activa ? max(0, round($total - $pagado, 2)) : 0,
                'estado' => !$activa ? 'ANULADA' : ($total - $pagado > 0.009 ? 'PENDIENTE' : 'PAGADA'),
                'observacion' => $v->observacion,
                'productos' => ($detalles->get($v->id) ?? collect())->map(function ($d) {
                    return [
                        'cod_prod' => trim((string) $d->cod_prod),
                        'nombre' => trim((string) $d->nombre),
                        'unidad' => trim((string) $d->unidad),
                        'cantidad' => (float) $d->cantidad,
                        'peso' => (float) $d->peso,
                        'precio' => (float) $d->precio,
                        'subtotal' => (float) $d->subtotal,
                    ];
                })->values(),
            ];
        });

        return [
            'cliente' => [
                'id' => (int) $c->Cod_Aut,
                'nombre' => trim((string) $c->Nombres),
                'nit' => trim((string) $c->Id),
                'telefono' => trim((string) $c->Telf),
                'direccion' => trim((string) $c->Direccion),
                'zona' => trim((string) $c->zona),
                'territorio' => trim((string) ($c->territorio ?? '')),
                'canal' => trim((string) $c->Canal),
                'vendedor' => $vendedor ? trim(trim((string) $vendedor->Nombre1) . ' ' . trim((string) $vendedor->App1)) : '',
                'latitud' => $c->Latitud,
                'longitud' => $c->longitud,
            ],
            'deudas' => $deudas,
            'ventas' => $ventas->values(),
            'totales' => [
                'saldo' => round($deudas->sum('saldo'), 2),
                'deudas' => $deudas->where('saldo', '>', 0)->count(),
                'abonado' => round($deudas->sum('pagado'), 2),
                'ventas' => $ventas->count(),
                'vendido' => round($ventas->where('estado', '<>', 'ANULADA')->sum('total'), 2),
            ],
        ];
    }

    /**
     * Las deudas de un cliente, o de todos: los comprobantes de facturacion
     * emitidos a credito y las deudas agregadas a mano, con lo abonado a cada una.
     */
    private function deudas($cliente)
    {
        $abonos = DB::table('creditos_abonos')->where('origen', 'factura')
            ->select('deuda_id', DB::raw('SUM(monto) as pagado'))
            ->groupBy('deuda_id')->pluck('pagado', 'deuda_id');

        // Solo lo que sale de facturacion: los comprobantes emitidos a credito
        // y lo que se les fue abonando. Las notas de caja no entran aca.
        $facturas = DB::table('facturas as f')->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'f.cliente_id')
            ->whereIn('f.tipo_pago', ['CRÉDITO', 'CREDITO'])
            ->when($cliente, function ($q) use ($cliente) { $q->where('f.cliente_id', $cliente); })
            ->get(['f.id', 'f.cliente_id', 'f.fecha', 'f.total as monto', 'f.estado', 'f.deleted_at',
                'f.tipo_comprobante', 'f.pedido_nro',
                DB::raw("COALESCE(c.Nombres, f.nombre) as cliente")]);

        $filas = collect();
        foreach ($facturas as $deuda) {
            $deuda->origen = 'factura';
            $deuda->clave = 'factura:' . $deuda->id;
            $deuda->concepto = ($deuda->tipo_comprobante === 'FACTURA' ? 'Factura #' : 'Venta #') . $deuda->id
                . ($deuda->pedido_nro ? ' · Pedido #' . $deuda->pedido_nro : '');
            $deuda->pagado = (float) ($abonos[$deuda->id] ?? 0);
            $deuda->monto = (float) $deuda->monto;
            $activa = $deuda->estado === 'ACTIVO' && !$deuda->deleted_at;
            if (!$activa && !$deuda->pagado) { continue; }
            $deuda->saldo = $activa ? max(0, round($deuda->monto - $deuda->pagado, 2)) : 0;
            $deuda->estado = !$activa ? 'ANULADA CON ABONOS: REVISAR' : ($deuda->saldo > 0 ? 'PENDIENTE' : 'PAGADO');
            $filas->push($deuda);
        }

        // Las deudas que cobranzas agrega a mano a un cliente.
        $abonosManuales = DB::table('creditos_abonos')->where('origen', 'manual')
            ->select('deuda_id', DB::raw('SUM(monto) as pagado'))
            ->groupBy('deuda_id')->pluck('pagado', 'deuda_id');

        $manuales = DB::table('creditos_manuales as d')->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'd.cliente_id')
            ->when($cliente, function ($q) use ($cliente) { $q->where('d.cliente_id', $cliente); })
            ->get(['d.id', 'd.cliente_id', 'd.fecha', 'd.monto', 'd.concepto', 'c.Nombres as cliente']);

        foreach ($manuales as $deuda) {
            $deuda->origen = 'manual';
            $deuda->clave = 'manual:' . $deuda->id;
            $deuda->pagado = (float) ($abonosManuales[$deuda->id] ?? 0);
            $deuda->monto = (float) $deuda->monto;
            $deuda->saldo = max(0, round($deuda->monto - $deuda->pagado, 2));
            $deuda->estado = $deuda->saldo > 0 ? 'PENDIENTE' : 'PAGADO';
            $filas->push($deuda);
        }

        return $filas;
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'cliente_id' => 'required|integer|exists:tbclientes,Cod_Aut',
            'fecha' => 'required|date_format:Y-m-d|before_or_equal:today',
            'concepto' => 'required|string|max:255',
            'monto' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'solicitud_id' => 'required|uuid',
        ]);
        return DB::transaction(function () use ($datos, $request) {
            DB::table('tbclientes')->where('Cod_Aut', $datos['cliente_id'])->lockForUpdate()->first();
            $previo = DB::table('creditos_manuales')->where('solicitud_id', $datos['solicitud_id'])->first();
            if ($previo) {
                abort_unless((int) $previo->cliente_id === (int) $datos['cliente_id']
                    && (int) $previo->user_id === (int) $request->user()->CodAut
                    && (string) $previo->concepto === $datos['concepto']
                    && (float) $previo->monto === (float) $datos['monto'], 409,
                    'Esta solicitud ya fue registrada con otros datos. Cierre el formulario y actualice.');
                return response()->json(['id' => $previo->id]);
            }
            $id = DB::table('creditos_manuales')->insertGetId($datos + [
                'user_id' => $request->user()->CodAut, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return response()->json(['id' => $id], 201);
        });
    }

    public function historial($origen, $id)
    {
        abort_unless(in_array($origen, ['factura', 'manual'], true), 404);
        return DB::table('creditos_abonos as a')->leftJoin('personal as p', 'p.CodAut', '=', 'a.user_id')
            ->where('a.origen', $origen)->where('a.deuda_id', $id)->orderByDesc('a.id')
            ->get(['a.id', 'a.monto', 'a.forma_pago', 'a.referencia', 'a.created_at',
                DB::raw("TRIM(CONCAT(COALESCE(p.Nombre1, ''), ' ', COALESCE(p.App1, ''))) as cobrador")]);
    }

    public function abonar(Request $request, $origen, $id)
    {
        abort_unless(in_array($origen, ['factura', 'manual'], true), 404);
        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'forma_pago' => 'required|in:EFECTIVO,QR,TRANSFERENCIA',
            'referencia' => 'nullable|string|max:100', 'solicitud_id' => 'required|uuid',
        ]);
        return DB::transaction(function () use ($datos, $request, $origen, $id) {
            $deuda = DB::table($origen === 'factura' ? 'facturas' : 'creditos_manuales')->where('id', $id)->lockForUpdate()->first();
            abort_unless($deuda, 404);
            $previo = DB::table('creditos_abonos')->where('solicitud_id', $datos['solicitud_id'])->first();
            if ($previo) {
                abort_unless($previo->origen === $origen && (int) $previo->deuda_id === (int) $id
                    && (int) $previo->user_id === (int) $request->user()->CodAut
                    && (float) $previo->monto === (float) $datos['monto']
                    && $previo->forma_pago === $datos['forma_pago'], 409,
                    'Esta solicitud ya fue registrada con otros datos. Cierre el formulario y actualice.');
                return response()->json(['id' => $previo->id]);
            }
            if ($origen === 'factura' && ($deuda->estado !== 'ACTIVO' || $deuda->deleted_at || !in_array($deuda->tipo_pago, ['CRÉDITO', 'CREDITO'], true))) {
                throw ValidationException::withMessages(['deuda' => 'Esta venta no tiene un crédito activo.']);
            }
            $monto = $origen === 'factura' ? $deuda->total : $deuda->monto;
            $pagado = DB::table('creditos_abonos')->where('origen', $origen)->where('deuda_id', $id)->sum('monto');
            if ((int) round($datos['monto'] * 100) > (int) round($monto * 100) - (int) round($pagado * 100)) {
                throw ValidationException::withMessages(['monto' => 'El abono supera el saldo pendiente. Actualice la cuenta.']);
            }
            $abono = DB::table('creditos_abonos')->insertGetId($datos + [
                'origen' => $origen, 'deuda_id' => $id, 'cliente_id' => $deuda->cliente_id,
                'user_id' => $request->user()->CodAut, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return response()->json(['id' => $abono, 'saldo' => round($monto - $pagado - $datos['monto'], 2)], 201);
        });
    }
}
