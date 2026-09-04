<?php

namespace App\Http\Controllers;

use App\Services\CargaCamion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
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

        // La fecha es la del comprobante, no la del pedido: el pedido se toma
        // un dia y se cobra al siguiente, y el caminero sale con lo que caja
        // facturo hoy.
        $facturas = DB::table('facturas as f')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'f.cliente_id')
            ->whereNull('f.deleted_at')
            ->where('f.estado', '<>', 'ANULADO')
            ->where('f.fecha', $fecha)
            ->whereNotNull('f.pedido_nro')
            ->orderBy('f.hora')
            ->get([
                'f.id as factura_id', 'f.fecha as factura_fecha', 'f.hora',
                'f.tipo_comprobante', 'f.tipo_pago', 'f.total', 'f.estado',
                DB::raw('TRIM(COALESCE(f.nit, "")) as nit'),
                DB::raw('TRIM(COALESCE(f.nombre, "")) as nombre'),
                'f.cliente_id', 'f.pedido_nro as nro_pedido',
                DB::raw('UPPER(TRIM(f.pedido_tipo)) as tipo'),
                DB::raw('TRIM(COALESCE(c.Id, "")) as cliente_nit'),
                DB::raw('TRIM(COALESCE(c.Nombres, "")) as cliente'),
                DB::raw('TRIM(COALESCE(c.Direccion, "")) as direccion'),
                DB::raw('TRIM(COALESCE(c.Telf, "")) as telefono'),
                'c.Latitud as latitud', 'c.longitud as longitud',
            ]);

        // El camion se pregunta solo por esos pedidos: buscarlo por fecha
        // obligaba a agrupar tbpedidos entera y ademas dejaba fuera lo de ayer
        // que se factura hoy.
        $pedidos = collect();
        if ($facturas->isNotEmpty()) {
            $pedidos = DB::table('tbpedidos')
                ->whereIn('NroPed', $facturas->pluck('nro_pedido')->unique()->all())
                ->where('bonificacion', 0)
                ->groupBy('NroPed', DB::raw('UPPER(TRIM(tipo))'))
                ->get([
                    'NroPed as nro_pedido',
                    DB::raw('UPPER(TRIM(tipo)) as tipo'),
                    DB::raw("TRIM(COALESCE(MIN(placa), '')) as placa"),
                    DB::raw("TRIM(COALESCE(MIN(colorStyle), '')) as placa_color"),
                    DB::raw('MIN(fecha) as pedido_fecha'),
                    DB::raw('COUNT(*) as productos'),
                    DB::raw('ROUND(SUM(COALESCE(Cant, 0) * COALESCE(precio, 0)), 2) as total_pedido'),
                ])
                ->keyBy(function ($pedido) {
                    return $pedido->nro_pedido . '-' . $pedido->tipo;
                });
        }

        $facturas = $facturas->filter(function ($factura) use ($pedidos, $placa) {
            $pedido = $pedidos->get($factura->nro_pedido . '-' . $factura->tipo);
            if (!$pedido || $pedido->placa !== $placa) {
                return false;
            }
            $factura->placa = $pedido->placa;
            $factura->placa_color = $pedido->placa_color;
            $factura->pedido_fecha = $pedido->pedido_fecha;
            $factura->productos = $pedido->productos;
            $factura->total_pedido = $pedido->total_pedido;
            return true;
        });

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
            // El credito se entrega sin plata: queda debiendo la nota entera.
            if ($tipago !== 'CRÉDITO') {
                $efectivo = round((float) ($datos['monto_efectivo'] ?? 0), 2);
                $qr = round((float) ($datos['monto_qr'] ?? 0), 2);

                // Sin desglose se asume la nota entera por la via elegida, que
                // es como cobraba esta pantalla antes de poder escribir montos.
                if ($efectivo <= 0 && $qr <= 0) {
                    if ($tipago === 'CONTADO') {
                        $efectivo = $total;
                    } elseif ($tipago === 'PAGO QR') {
                        $qr = $total;
                    }
                }

                // Cada via cobra lo suyo: en contado no entra nada por QR.
                if ($tipago === 'CONTADO') {
                    $qr = 0.0;
                } elseif ($tipago === 'PAGO QR') {
                    $efectivo = 0.0;
                }

                if ($tipago === 'MIXTO' && ($efectivo <= 0 || $qr <= 0)) {
                    return response()->json([
                        'message' => 'En un cobro mixto tienen que entrar montos por efectivo y por QR',
                    ], 422);
                }

                // El cliente casi nunca paga justo: de una nota de 106.70
                // entrega 106. Se guarda lo que de verdad entro y la diferencia
                // queda a la vista contra el total del comprobante; lo que no
                // se acepta es cobrar de mas, que siempre es un error de tipeo.
                $pagado = round($efectivo + $qr, 2);

                if ($pagado <= 0) {
                    return response()->json([
                        'message' => 'Escribe cuánto pagó el cliente',
                    ], 422);
                }
                if ($pagado - $total > 0.01) {
                    return response()->json([
                        'message' => 'No se puede cobrar más de Bs ' . number_format($total, 2, '.', ''),
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
            // El dia de reparto es el del comprobante, no el del pedido: asi
            // lo cobrado sale en el reporte del mismo dia en que se ve la lista.
            'fechaEntreg' => date('Y-m-d', strtotime($factura->fecha)),
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
                // Las entregas de la ruta de siempre no traen desglose: se
                // deduce del tipago para que el dinero cuadre igual. Solo el
                // mixto necesita las columnas cargadas.
                if ($fila->estado === 'ENTREGADO' && $fila->monto_efectivo == 0 && $fila->monto_qr == 0) {
                    if ($fila->tipago === 'CONTADO') {
                        $fila->monto_efectivo = $fila->monto;
                    } elseif ($fila->tipago === 'PAGO QR') {
                        $fila->monto_qr = $fila->monto;
                    }
                }
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

    /**
     * Cuanto lleva cerrado y cuanto le falta. Lo que salio en el camion son
     * las notas del dia (tbctascobrar); si ese dia todavia no hay notas
     * despachadas se cuentan los pedidos, que es como se ve desde facturacion.
     */
    private function avance($fecha, $placa, $filas)
    {
        $salieron = DB::table('tbctascobrar')
            ->where('FechaEntreg', $fecha)
            ->whereRaw('TRIM(placa) = ?', [$placa])
            ->distinct()
            ->count('comanda');

        if ($salieron === 0) {
            $salieron = DB::table('tbpedidos')
                ->whereDate('fecha', $fecha)
                ->where('bonificacion', 0)
                ->whereRaw("TRIM(COALESCE(placa, '')) = ?", [$placa])
                ->distinct()
                ->count('NroPed');
        }

        $cerradas = $filas->pluck('nota')->unique()->count();
        $cobradas = $filas->where('estado', 'ENTREGADO')->pluck('nota')->unique()->count();
        // Nunca pasa del 100%: si se cerro mas de lo que figura despachado,
        // el reparto ya esta completo y el porcentaje deja de informar.
        $total = max($salieron, $cerradas);

        return [
            'pedidos' => $total,
            'cerradas' => $cerradas,
            'cobradas' => $cobradas,
            'pendientes' => max($total - $cerradas, 0),
            'porcentaje' => $total > 0 ? round(($cerradas / $total) * 100) : 0,
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


    /**
     * La carga del dia: los comprobantes que salen en el camion, uno por
     * canasta, con el pedido que los origino.
     *
     * Es lo que el caminero mira antes de salir, parado al lado del camion:
     * las mismas ventas que caja acaba de facturar, para ir tildandolas.
     */
    public function carga(Request $request)
    {
        $datos = $request->validate(['fecha' => 'nullable|date']);
        $fecha = $datos['fecha'] ?? date('Y-m-d');

        $placa = $this->placa($request);
        if ($placa === '') {
            return response()->json([
                'message' => 'Tu usuario no tiene un camión asignado; pídelo a administración',
            ], 422);
        }

        $servicio = new CargaCamion();
        $comprobantes = $servicio->comprobantes($fecha, $placa);

        return [
            'fecha' => $fecha,
            'placa' => $placa,
            'caminero' => $this->nombre($request),
            'resumen' => $servicio->estado($fecha, $placa, $comprobantes),
            // Pedidos ya asignados a su camion que caja todavia no facturo: no
            // hay nada que revisar ahi, pero explica por que la lista esta
            // corta o vacia.
            'sin_facturar' => $servicio->pedidosSinFacturar($fecha, $placa),
            'comprobantes' => $comprobantes->values(),
        ];
    }

    /**
     * El visto bueno sobre una canasta.
     *
     * La observacion es opcional y es donde queda escrito lo que no cuadraba,
     * que es lo que despues sale en el papel que el caminero firma.
     */
    public function verificarCarga(Request $request)
    {
        $datos = $request->validate([
            'fecha' => 'nullable|date',
            'factura_id' => 'required|integer',
            'verificado' => 'required|boolean',
            'observacion' => 'nullable|string|max:190',
        ]);

        $fecha = $datos['fecha'] ?? date('Y-m-d');
        $placa = $this->placa($request);
        if ($placa === '') {
            return response()->json(['message' => 'Tu usuario no tiene un camión asignado'], 422);
        }

        $servicio = new CargaCamion();
        $comprobante = $servicio->comprobante($fecha, $placa, $datos['factura_id']);
        if (!$comprobante) {
            return response()->json(['message' => 'Ese comprobante no sale en tu camión'], 404);
        }

        $comprobante = $this->marcar(
            $request, $fecha, $placa, $comprobante,
            (bool) $datos['verificado'], $datos['observacion'] ?? null
        );

        // Solo vuelve la canasta que se toco: mandar la carga entera en cada
        // tilde obligaba al celular a redibujar toda la lista.
        return [
            'message' => $datos['verificado'] ? 'Canasta verificada' : 'Canasta desmarcada',
            'resumen' => $servicio->estado($fecha, $placa),
            'comprobante' => $comprobante,
        ];
    }

    /**
     * Da por buenas de golpe todas las canastas que todavia no se tocaron.
     *
     * Lo que ya tiene una observacion escrita no se pisa, para no borrar sin
     * querer un faltante que el caminero anoto.
     */
    public function verificarCargaTodo(Request $request)
    {
        $datos = $request->validate(['fecha' => 'nullable|date']);
        $fecha = $datos['fecha'] ?? date('Y-m-d');

        $placa = $this->placa($request);
        if ($placa === '') {
            return response()->json(['message' => 'Tu usuario no tiene un camión asignado'], 422);
        }

        $servicio = new CargaCamion();
        // Sin el detalle de cada canasta: para marcar alcanza con cuantos
        // productos tenia y su total, que es lo que se guarda.
        $comprobantes = $servicio->comprobantes($fecha, $placa, null, false);

        $filas = [];
        foreach ($comprobantes as $comprobante) {
            if ($comprobante['verificado'] || !empty($comprobante['observacion'])) {
                continue;
            }

            $filas[] = $this->fila($request, $fecha, $placa, $comprobante, true, null);
        }

        // Un solo viaje a la base en vez de un update por canasta: con un
        // camion lleno eran decenas de consultas seguidas.
        if ($filas) {
            DB::table('carga_verificaciones')->upsert($filas, ['factura_id']);
        }

        $comprobantes = $servicio->comprobantes($fecha, $placa);

        return [
            'message' => count($filas) > 0
                ? 'Se verificaron ' . count($filas) . ' canastas'
                : 'No quedaba nada por verificar',
            'resumen' => $servicio->estado($fecha, $placa, $comprobantes),
            'comprobantes' => $comprobantes->values(),
        ];
    }

    /**
     * Guarda el visto bueno de una canasta y devuelve como quedo, para poder
     * responder solo esa sin volver a armar la carga entera.
     */
    private function marcar(Request $request, $fecha, $placa, array $comprobante, $verificado, $observacion)
    {
        $fila = $this->fila($request, $fecha, $placa, $comprobante, $verificado, $observacion);

        DB::table('carga_verificaciones')->updateOrInsert(
            ['factura_id' => $comprobante['factura_id']],
            $fila
        );

        $comprobante['verificado'] = (bool) $verificado;
        $comprobante['cambio'] = false;
        $comprobante['observacion'] = $fila['observacion'];
        $comprobante['verificado_por'] = $fila['verificado_por'];
        $comprobante['verificado_en'] = $fila['verificado_en'];

        return $comprobante;
    }

    /** Lo que se graba de una canasta revisada. */
    private function fila(Request $request, $fecha, $placa, array $comprobante, $verificado, $observacion)
    {
        $ahora = date('Y-m-d H:i:s');
        $observacion = trim((string) $observacion);

        return [
            'factura_id' => $comprobante['factura_id'],
            'fecha' => $fecha,
            'placa' => $placa,
            'pedido_nro' => $comprobante['nro_pedido'],
            'pedido_tipo' => $comprobante['pedido_tipo'],
            // Se guarda como estaba la venta al revisarla: si despues la
            // cambian, el comprobante vuelve a quedar pendiente.
            'items_esperados' => $comprobante['productos'],
            'total_esperado' => $comprobante['total'],
            'verificado' => $verificado,
            'observacion' => $observacion !== '' ? $observacion : null,
            'personal_id' => $request->user()->CodAut,
            'verificado_por' => $this->nombre($request),
            'verificado_en' => $verificado ? $ahora : null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
    }

    /**
     * El papel que el caminero firma antes de salir: que canastas suben, de
     * quien es cada una y que anoto donde algo no cuadraba.
     */
    public function cargaReporte(Request $request)
    {
        $datos = $request->validate(['fecha' => 'nullable|date']);
        $fecha = $datos['fecha'] ?? date('Y-m-d');

        $placa = $this->placa($request);
        if ($placa === '') {
            return response()->json(['message' => 'Tu usuario no tiene un camión asignado'], 422);
        }

        $servicio = new CargaCamion();
        $comprobantes = $servicio->comprobantes($fecha, $placa);

        if ($comprobantes->isEmpty()) {
            return response()->json(['message' => 'Tu camión no tiene carga ese día'], 422);
        }

        return $this->pdf(
            $this->cargaHtml(
                $fecha, $placa, $this->nombre($request), $comprobantes,
                $servicio->estado($fecha, $placa, $comprobantes)
            ),
            'carga_' . $placa . '_' . $fecha
        );
    }

    /** El reporte de carga como HTML, para pasarlo a PDF. */
    private function cargaHtml($fecha, $placa, $caminero, $comprobantes, array $resumen)
    {
        $emisor = config('siat.emisor');

        $cuerpo = '';
        foreach ($comprobantes as $i => $comprobante) {
            $par = $i % 2 ? " class='par'" : '';

            // El detalle va en la misma celda, en chico: el caminero firma que
            // subio eso, no solo que subio una canasta.
            $detalle = [];
            foreach ($comprobante['items'] as $item) {
                $detalle[] = e($item['nombre']) . ' '
                    . $this->cantidad($item['peso'] ?: $item['cantidad']) . ' ' . e($item['unidad']);
            }

            $cuerpo .= "<tr$par>"
                . "<td class='c'>" . ($comprobante['verificado'] ? 'SI' : 'NO') . '</td>'
                . "<td class='c'>" . ($comprobante['tipo_comprobante'] === 'FACTURA' ? 'F' : 'R')
                . ' ' . ($comprobante['nro_factura'] ?: $comprobante['factura_id']) . '</td>'
                . "<td class='c'>" . ($comprobante['nro_pedido'] ?: '—') . '</td>'
                . '<td>' . e($comprobante['cliente'])
                . ($comprobante['zona'] ? " <span class='gris'>" . e($comprobante['zona']) . '</span>' : '')
                . "<div class='detalle'>" . implode(' &middot; ', $detalle) . '</div></td>'
                . "<td class='c'>" . $comprobante['productos'] . '</td>'
                . "<td class='r'>" . number_format($comprobante['total'], 2) . '</td>'
                . '<td' . ($comprobante['observacion'] ? " class='dif'" : '') . '>'
                . e($comprobante['observacion'] ?? '') . '</td>'
                . '</tr>';
        }

        $aviso = $resumen['completo']
            ? 'Carga verificada completa: el caminero declara haber recibido las canastas de esta lista.'
            : 'ATENCIÓN: faltan ' . $resumen['pendientes'] . ' canastas por verificar.';

        return "<style>
            @page { margin: 12mm 11mm 16mm 11mm }
            * { font-family: 'DejaVu Sans', sans-serif }
            body { font-size: 9.5px; color: #222 }
            .c { text-align: center } .r { text-align: right }
            .gris { color: #777 }
            .tit { text-align: center; margin-bottom: 6px }
            .tit h1 { font-size: 14px; margin: 0; letter-spacing: 1px }
            .tit div { font-size: 8.5px; color: #666 }
            .datos { width: 100%; border-collapse: collapse; margin-bottom: 6px;
                     border: 1px solid #ccc }
            .datos td { font-size: 9px; padding: 4px 6px }
            .datos .et { color: #666; width: 74px }
            .rep { width: 100%; border-collapse: collapse }
            .rep th { background: #37474F; color: #fff; font-size: 8px; padding: 5px 4px;
                      text-align: left; text-transform: uppercase }
            .rep td { font-size: 8.5px; padding: 4px; border-bottom: 1px solid #E0E0E0;
                      vertical-align: top }
            .rep tr.par td { background: #F5F7F8 }
            .rep td.dif { color: #c1272d; font-weight: bold }
            .rep .detalle { font-size: 7.5px; color: #666; margin-top: 2px }
            .rep tr.total td { background: #ECEFF1; border-top: 2px solid #37474F }
            .aviso { margin-top: 8px; font-size: 9px; font-weight: bold }
            .firmas { width: 100%; margin-top: 44px; border-collapse: collapse }
            .firmas td { width: 45%; text-align: center; font-size: 9px;
                         border-top: 1px solid #444; padding-top: 4px }
            .firmas td.sep { width: 10%; border: 0 }
        </style>
        <div class='tit'>
            <h1>VERIFICACIÓN DE CARGA</h1>
            <div>" . e($emisor['nombre']) . ' &middot; ' . e($emisor['sucursal'])
            . ' &middot; impreso el ' . date('d/m/Y H:i') . "</div>
        </div>
        <table class='datos'>
            <tr>
                <td class='et'>Camión</td><td><b>" . e($placa) . "</b></td>
                <td class='et'>Sale el</td><td>" . date('d/m/Y', strtotime($fecha)) . "</td>
            </tr>
            <tr>
                <td class='et'>Caminero</td><td>" . e($caminero) . "</td>
                <td class='et'>Verificado</td>
                <td>" . $resumen['verificados'] . ' de ' . $resumen['comprobantes']
            . ' canastas (' . $resumen['porcentaje'] . '%)'
            . ($resumen['verificado_en']
                ? ' &middot; ' . date('d/m/Y H:i', strtotime($resumen['verificado_en']))
                : '') . "</td>
            </tr>
        </table>
        <table class='rep'>
            <thead><tr>
                <th style='width:5%' class='c'>OK</th>
                <th style='width:9%' class='c'>Comprob.</th>
                <th style='width:8%' class='c'>Pedido</th>
                <th style='width:45%'>Cliente y contenido de la canasta</th>
                <th style='width:6%' class='c'>Ítems</th>
                <th style='width:11%' class='r'>Bs</th>
                <th style='width:16%'>Observación</th>
            </tr></thead>
            <tbody>$cuerpo
                <tr class='total'>
                    <td colspan='5'><b>TOTAL " . count($comprobantes) . " canastas</b></td>
                    <td class='r'><b>" . number_format($resumen['total'], 2) . "</b></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        <div class='aviso'>" . e($aviso) . "</div>
        <table class='firmas'>
            <tr>
                <td>Firma del caminero<br>" . e($caminero) . "</td>
                <td class='sep'></td>
                <td>Firma de almacén</td>
            </tr>
        </table>";
    }

    /** Cantidades con hasta tres decimales, sin ceros de relleno. */
    private function cantidad($valor)
    {
        $texto = number_format((float) $valor, 3, '.', '');

        return strpos($texto, '.') === false ? $texto : rtrim(rtrim($texto, '0'), '.');
    }

    private function pdf($html, $nombre)
    {
        $pdf = App::make('dompdf.wrapper');
        // Sin subsetting la fuente se embebe entera y cada PDF pesa ~900 KB.
        $pdf->getDomPDF()->getOptions()->setIsFontSubsettingEnabled(true);
        $pdf->setPaper('letter');
        $pdf->loadHTML($html);

        return $pdf->stream($nombre . '.pdf', ['Attachment' => false]);
    }

    /** Nombre del caminero tal como sale en los papeles. */
    private function nombre(Request $request)
    {
        $usuario = $request->user();

        return trim($usuario->Nombre1 . ' ' . $usuario->App1);
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
