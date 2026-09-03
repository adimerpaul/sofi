<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lo que ve el caminero desde el celular: los comprobantes del dia que salen
 * en SU camion, para cobrarlos en la puerta del cliente, y el reporte de lo
 * que lleva recogido.
 *
 * El camion sale de personal.placa (igual que la ruta de entregas) y la lista
 * se arma con los comprobantes ya emitidos en facturacion; tbpedidos solo se
 * usa para saber en que camion viaja cada pedido.
 */
class CamineroController extends Controller
{
    /** Formas de pago con las que el caminero puede cerrar una entrega. */
    private const FORMAS_PAGO = ['CONTADO', 'PAGO QR', 'MIXTO', 'CRÉDITO'];

    /** Comprobantes del dia que van en el camion del usuario. */
    public function entregas(Request $request)
    {
        $datos = $request->validate(['fecha' => 'nullable|date']);
        $fecha = $datos['fecha'] ?? date('Y-m-d');

        $placa = $this->placa($request);
        if ($placa === '') {
            return response()->json([
                'message' => 'Tu usuario no tiene un camión asignado; pídelo a administración',
            ], 422);
        }

        // Un pedido es una fila: sus lineas pueden tener distinta hora, asi que
        // la cabecera se resume y el camion se toma de la primera linea.
        $pedidos = DB::table('tbpedidos')
            ->whereDate('fecha', $fecha)
            ->where('bonificacion', 0)
            ->groupBy('NroPed', DB::raw('UPPER(TRIM(tipo))'))
            ->havingRaw("TRIM(COALESCE(MIN(placa), '')) = ?", [$placa])
            ->select([
                'NroPed as nro_pedido',
                DB::raw('UPPER(TRIM(tipo)) as tipo'),
                DB::raw("TRIM(COALESCE(MIN(placa), '')) as placa"),
                DB::raw("TRIM(COALESCE(MIN(colorStyle), '')) as placa_color"),
                DB::raw('MIN(fecha) as pedido_fecha'),
                DB::raw('COUNT(*) as productos'),
                DB::raw('ROUND(SUM(COALESCE(Cant, 0) * COALESCE(precio, 0)), 2) as total_pedido'),
            ]);

        $facturas = DB::table('facturas as f')
            ->joinSub($pedidos, 'p', function ($join) {
                $join->on('p.nro_pedido', '=', 'f.pedido_nro')
                    ->on(DB::raw('UPPER(TRIM(f.pedido_tipo))'), '=', 'p.tipo');
            })
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'f.cliente_id')
            ->whereNull('f.deleted_at')
            ->where('f.estado', '<>', 'ANULADO')
            ->orderBy('p.pedido_fecha')
            ->get([
                'f.id as factura_id', 'f.fecha as factura_fecha', 'f.hora',
                'f.tipo_comprobante', 'f.tipo_pago', 'f.total', 'f.estado',
                DB::raw('TRIM(COALESCE(f.nit, "")) as nit'),
                DB::raw('TRIM(COALESCE(f.nombre, "")) as nombre'),
                'f.cliente_id', 'p.nro_pedido', 'p.tipo', 'p.placa', 'p.placa_color',
                'p.pedido_fecha', 'p.productos', 'p.total_pedido',
                DB::raw('TRIM(COALESCE(c.Id, "")) as cliente_nit'),
                DB::raw('TRIM(COALESCE(c.Nombres, "")) as cliente'),
                DB::raw('TRIM(COALESCE(c.Direccion, "")) as direccion'),
                DB::raw('TRIM(COALESCE(c.Telf, "")) as telefono'),
                'c.Latitud as latitud', 'c.longitud as longitud',
            ]);

        // La entrega vigente de cada comprobante: la ultima registrada manda,
        // porque un NO ENTREGADO se puede corregir despues cobrando.
        $entregas = collect();
        if ($facturas->isNotEmpty()) {
            $entregas = DB::table('entregas')
                ->whereIn('factura_id', $facturas->pluck('factura_id')->all())
                ->orderBy('id')
                ->get(['id', 'factura_id', 'estado', 'tipago', 'monto', 'monto_efectivo',
                    'monto_qr', 'pago', 'observacion', 'hora'])
                ->keyBy('factura_id');
        }

        $lista = $facturas->map(function ($factura) use ($entregas) {
            $entrega = $entregas->get($factura->factura_id);
            $factura->total = (float) $factura->total;
            $factura->entrega_id = $entrega->id ?? null;
            $factura->entrega_estado = $entrega->estado ?? null;
            $factura->tipago = $entrega->tipago ?? null;
            $factura->monto_efectivo = $entrega ? (float) $entrega->monto_efectivo : 0;
            $factura->monto_qr = $entrega ? (float) $entrega->monto_qr : 0;
            $factura->observacion = $entrega->observacion ?? null;
            $factura->entrega_hora = $entrega->hora ?? null;
            $factura->cobrada = $entrega && $entrega->estado === 'ENTREGADO';
            return $factura;
        });

        // Lo que todavia no paso por caja no tiene comprobante y por eso no
        // aparece arriba: se avisa para que el caminero no lo de por perdido.
        $sinComprobante = DB::table('tbpedidos as p')
            ->leftJoin('facturas as f', function ($join) {
                $join->on('f.pedido_nro', '=', 'p.NroPed')
                    ->on(DB::raw('UPPER(TRIM(f.pedido_tipo))'), '=', DB::raw('UPPER(TRIM(p.tipo))'))
                    ->whereNull('f.deleted_at')
                    ->where('f.estado', '<>', 'ANULADO');
            })
            ->whereDate('p.fecha', $fecha)
            ->where('p.bonificacion', 0)
            ->whereRaw("TRIM(COALESCE(p.placa, '')) = ?", [$placa])
            ->whereNull('f.id')
            ->distinct()
            ->count('p.NroPed');

        return [
            'fecha' => $fecha,
            'placa' => $placa,
            'sin_comprobante' => $sinComprobante,
            'resumen' => $this->resumen($lista),
            'entregas' => $lista->values(),
        ];
    }

    /**
     * Cierra una entrega: el caminero marca si cobro y como. No emite
     * comprobante, ese ya se emitio en caja; aca solo se registra el dinero.
     */
    public function cobrar(Request $request)
    {
        $datos = $request->validate([
            'factura_id' => 'required|integer',
            'estado' => 'required|in:ENTREGADO,NO ENTREGADO,RECHAZADO',
            'tipago' => 'nullable|in:' . implode(',', self::FORMAS_PAGO),
            'monto_efectivo' => 'nullable|numeric|min:0',
            'monto_qr' => 'nullable|numeric|min:0',
            'observacion' => 'nullable|string|max:90',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $placa = $this->placa($request);
        if ($placa === '') {
            return response()->json(['message' => 'Tu usuario no tiene un camión asignado'], 422);
        }

        $factura = DB::table('facturas')
            ->whereNull('deleted_at')
            ->where('id', $datos['factura_id'])
            ->first(['id', 'cliente_id', 'nit', 'total', 'estado', 'pedido_nro', 'pedido_tipo', 'fecha']);

        if (!$factura) {
            return response()->json(['message' => 'El comprobante no existe'], 404);
        }
        if ($factura->estado === 'ANULADO') {
            return response()->json(['message' => 'El comprobante está anulado'], 422);
        }

        $pedido = DB::table('tbpedidos')
            ->where('NroPed', $factura->pedido_nro)
            ->whereRaw('UPPER(TRIM(tipo)) = ?', [strtoupper(trim((string) $factura->pedido_tipo))])
            ->where('bonificacion', 0)
            ->first([DB::raw("TRIM(COALESCE(placa, '')) as placa"), 'fecha']);

        if (!$pedido || $pedido->placa !== $placa) {
            return response()->json(['message' => 'Ese pedido no va en tu camión'], 403);
        }

        $yaCobrada = DB::table('entregas')
            ->where('factura_id', $factura->id)
            ->where('estado', 'ENTREGADO')
            ->exists();
        if ($yaCobrada) {
            return response()->json(['message' => 'Esa entrega ya fue cobrada'], 422);
        }

        $total = round((float) $factura->total, 2);
        $efectivo = 0.0;
        $qr = 0.0;
        $tipago = $datos['tipago'] ?? null;

        if ($datos['estado'] === 'ENTREGADO') {
            if (!$tipago) {
                return response()->json(['message' => 'Indica cómo se cobró la entrega'], 422);
            }
            // El desglose lo decide la forma de pago: solo el mixto lo carga el
            // caminero, y tiene que cuadrar con el total del comprobante.
            if ($tipago === 'CONTADO') {
                $efectivo = $total;
            } elseif ($tipago === 'PAGO QR') {
                $qr = $total;
            } elseif ($tipago === 'MIXTO') {
                $efectivo = round((float) ($datos['monto_efectivo'] ?? 0), 2);
                $qr = round((float) ($datos['monto_qr'] ?? 0), 2);
                if ($efectivo <= 0 || $qr <= 0) {
                    return response()->json([
                        'message' => 'En un cobro mixto tienen que entrar montos por efectivo y por QR',
                    ], 422);
                }
                if (abs(($efectivo + $qr) - $total) > 0.01) {
                    return response()->json([
                        'message' => 'Efectivo + QR debe sumar Bs ' . number_format($total, 2, '.', ''),
                    ], 422);
                }
            }
        } else {
            $tipago = null;
            if (empty($datos['observacion'])) {
                return response()->json(['message' => 'Escribe el motivo de la no entrega'], 422);
            }
        }

        $usuario = $request->user();
        $cliente = DB::table('tbclientes')->where('Cod_Aut', $factura->cliente_id)
            ->first(['Id', 'Latitud', 'longitud']);

        $id = DB::table('entregas')->insertGetId([
            'cliente_id' => $factura->cliente_id ?: 0,
            'cinit' => $cliente->Id ?? $factura->nit,
            'comanda' => $factura->pedido_nro,
            'factura_id' => $factura->id,
            'monto' => $total,
            'monto_efectivo' => $efectivo,
            'monto_qr' => $qr,
            'pago' => $efectivo + $qr,
            'tipago' => $tipago,
            'despachador' => trim($usuario->Nombre1 . ' ' . $usuario->App1),
            'personal_id' => $usuario->CodAut,
            'placa' => $placa,
            'lat' => (string) ($datos['lat'] ?? ''),
            'lng' => (string) ($datos['lng'] ?? ''),
            'estado' => $datos['estado'],
            'observacion' => $datos['observacion'] ?? ' ',
            'fecha' => date('Y-m-d'),
            'fechaEntreg' => date('Y-m-d', strtotime($pedido->fecha)),
            'hora' => date('H:i:s'),
            'distancia' => (string) $this->distancia(
                $datos['lat'] ?? null, $datos['lng'] ?? null,
                $cliente->Latitud ?? null, $cliente->longitud ?? null
            ),
        ]);

        return ['id' => $id, 'message' => 'Entrega registrada'];
    }

    /**
     * El recojo del dia tal como se entrega en papel: contados, creditos, QR,
     * mixtos y anulados, cada uno con sus notas y su total.
     */
    public function reporte(Request $request)
    {
        $datos = $request->validate(['fecha' => 'nullable|date']);
        $fecha = $datos['fecha'] ?? date('Y-m-d');

        $placa = $this->placa($request);
        if ($placa === '') {
            return response()->json(['message' => 'Tu usuario no tiene un camión asignado'], 422);
        }

        $filas = DB::table('entregas as e')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'e.cliente_id')
            ->where('e.fechaEntreg', $fecha)
            ->where('e.placa', $placa)
            ->orderBy('e.comanda')
            ->get([
                'e.id', 'e.comanda as nota', 'e.estado', 'e.tipago', 'e.hora',
                DB::raw('COALESCE(e.monto, 0) as monto'),
                DB::raw('COALESCE(e.monto_efectivo, 0) as monto_efectivo'),
                DB::raw('COALESCE(e.monto_qr, 0) as monto_qr'),
                DB::raw("TRIM(COALESCE(e.observacion, '')) as motivo"),
                DB::raw("TRIM(COALESCE(c.Nombres, '')) as cliente"),
            ])
            ->map(function ($fila) {
                $fila->monto = (float) $fila->monto;
                $fila->monto_efectivo = (float) $fila->monto_efectivo;
                $fila->monto_qr = (float) $fila->monto_qr;
                return $fila;
            });

        $entregadas = $filas->where('estado', 'ENTREGADO');

        $grupos = [
            'contados' => $entregadas->where('tipago', 'CONTADO')->values(),
            'qr' => $entregadas->where('tipago', 'PAGO QR')->values(),
            'mixtos' => $entregadas->where('tipago', 'MIXTO')->values(),
            'creditos' => $entregadas->where('tipago', 'CRÉDITO')->values(),
            'anulados' => $filas->where('estado', '<>', 'ENTREGADO')->values(),
        ];

        $usuario = $request->user();

        return [
            'fecha' => $fecha,
            'placa' => $placa,
            'despachador' => trim($usuario->Nombre1 . ' ' . $usuario->App1),
            'grupos' => $grupos,
            'totales' => [
                'contados' => round($grupos['contados']->sum('monto'), 2),
                'qr' => round($grupos['qr']->sum('monto'), 2),
                'mixtos' => round($grupos['mixtos']->sum('monto'), 2),
                'creditos' => round($grupos['creditos']->sum('monto'), 2),
                'anulados' => round($grupos['anulados']->sum('monto'), 2),
                // Lo que el caminero rinde en caja al volver, ya separado por
                // via: el mixto aporta a las dos.
                'efectivo' => round($entregadas->sum('monto_efectivo'), 2),
                'qr_cobrado' => round($entregadas->sum('monto_qr'), 2),
            ],
            'avance' => $this->avance($fecha, $placa, $filas),
        ];
    }

    /** Cuanto lleva cobrado y cuanto le falta, contra lo que salio en el camion. */
    private function avance($fecha, $placa, $filas)
    {
        $porFacturar = DB::table('tbpedidos')
            ->whereDate('fecha', $fecha)
            ->where('bonificacion', 0)
            ->whereRaw("TRIM(COALESCE(placa, '')) = ?", [$placa])
            ->distinct()
            ->count('NroPed');

        $cerradas = $filas->pluck('nota')->unique()->count();
        $cobradas = $filas->where('estado', 'ENTREGADO')->pluck('nota')->unique()->count();

        return [
            'pedidos' => $porFacturar,
            'cerradas' => $cerradas,
            'cobradas' => $cobradas,
            'pendientes' => max($porFacturar - $cerradas, 0),
            'porcentaje' => $porFacturar > 0 ? round(($cerradas / $porFacturar) * 100) : 0,
        ];
    }

    /** Totales de la jornada tal como los mira el caminero en la lista. */
    private function resumen($lista)
    {
        $cobradas = $lista->where('cobrada', true);

        return [
            'comprobantes' => $lista->count(),
            'cobradas' => $cobradas->count(),
            'pendientes' => $lista->count() - $cobradas->count(),
            'total' => round($lista->sum('total'), 2),
            'efectivo' => round($cobradas->sum('monto_efectivo'), 2),
            'qr' => round($cobradas->sum('monto_qr'), 2),
            'por_cobrar' => round($lista->where('cobrada', false)->sum('total'), 2),
            'porcentaje' => $lista->count() > 0
                ? round(($cobradas->count() / $lista->count()) * 100)
                : 0,
        ];
    }

    private function placa(Request $request)
    {
        return trim((string) $request->user()->placa);
    }

    /** Metros entre el celular del caminero y el punto del cliente. */
    private function distancia($lat1, $lon1, $lat2, $lon2)
    {
        if (!$lat1 || !$lon1 || !$lat2 || !$lon2) {
            return 0;
        }

        $rad = M_PI / 180;
        $lat1 *= $rad;
        $lon1 *= $rad;
        $lat2 *= $rad;
        $lon2 *= $rad;
        $a = sin(($lat2 - $lat1) / 2) ** 2
            + cos($lat1) * cos($lat2) * sin(($lon2 - $lon1) / 2) ** 2;

        return round(6372797 * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
