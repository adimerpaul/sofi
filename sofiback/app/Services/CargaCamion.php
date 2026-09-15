<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La carga de un camion: los comprobantes que salen en el ese dia y el visto
 * bueno del caminero sobre cada uno.
 *
 * Caja factura y arma la canasta de cada venta; el caminero revisa canasta por
 * canasta —una por comprobante, con el pedido que la origino— y mientras quede
 * una sin revisar caja no imprime los papeles de ese camion.
 *
 * El camion de un comprobante sale del pedido que lo origino (tbpedidos.placa),
 * que es la unica relacion que existe entre una venta y un camion; la venta
 * directa de mostrador no viaja en ninguno y por eso no aparece aca.
 *
 * Nota de rendimiento: los filtros por fecha van como rango (>= dia y < dia
 * siguiente) y no con whereDate: envolver la columna en DATE() deja sin usar el
 * indice y esta pantalla la abre el caminero desde el celular.
 */
class CargaCamion
{
    /** Pollo entero: nombre, columna de cajas, columna de unidades. */
    public const PRODUCTOS_POLLO = [
        ['Brasa 5', 'cbrasa5', 'ubrasa5', 'bsbrasa5', 'obsbrasa5'],
        ['Brasa 6', 'cbrasa6', 'cubrasa6', 'bsbrasa6', 'obsbrasa6'],
        ['Pollo 104', 'c104', 'u104', 'bs104', 'obs104'],
        ['Pollo 105', 'c105', 'u105', 'bs105', 'obs105'],
        ['Pollo 106', 'c106', 'u106', 'bs106', 'obs106'],
        ['Pollo 107', 'c107', 'u107', 'bs107', 'obs107'],
        ['Pollo 108', 'c108', 'u108', 'bs108', 'obs108'],
        ['Pollo 109', 'c109', 'u109', 'bs109', 'obs109'],
    ];

    /** Cortes: nombre, columna de cantidad, columna con su unidad. */
    public const CORTES_POLLO = [
        ['Ala', 'ala', 'unidala', 'bsala', 'obsala'],
        ['Cadera', 'cadera', 'unidcadera', 'bscadera', 'obscadera'],
        ['Pecho', 'pecho', 'unidpecho', 'bspecho', 'obspecho'],
        ['Pie', 'pie', 'unidpie', 'bspie', 'obspie'],
        ['Filete', 'filete', 'unidfilete', 'bsfilete', 'obsfilete'],
        ['Cuello', 'cuello', 'unidcuello', 'bscuello', 'obscuello'],
        ['Hueso', 'hueso', 'unidhueso', 'bshueso', 'obshueso'],
        ['Menudencia', 'menu', 'unidmenu', 'bsmenu', 'obsmenu'],
    ];

    /**
     * Los comprobantes de ese dia que salen en ese camion, con su detalle y
     * con lo que el caminero ya reviso.
     */
    public function comprobantes($fecha, $placa, $facturaId = null, $conItems = true): Collection
    {
        $facturas = $this->facturas($fecha, $placa, $facturaId);

        if ($facturas->isEmpty()) {
            return collect();
        }

        $ids = $facturas->pluck('factura_id');
        // Para el resumen solo se compara cuantos productos tiene la canasta:
        // ahi no hace falta traer linea por linea de todos los comprobantes.
        $detalles = $conItems ? $this->detalles($ids) : null;
        $conteos = $conItems ? null : $this->conteos($ids);
        $marcas = $this->marcas($fecha, $placa);

        return $facturas->map(function ($factura) use ($detalles, $conteos, $marcas) {
            $items = $detalles
                ? ($detalles->get($factura->factura_id) ?? collect())->values()->all()
                : [];
            $productos = $detalles
                ? count($items)
                : (int) $conteos->get($factura->factura_id, 0);
            $marca = $marcas->get($factura->factura_id);

            $comprobante = [
                'factura_id' => (int) $factura->factura_id,
                'nro_factura' => $factura->nro_factura,
                'tipo_comprobante' => $factura->tipo_comprobante,
                'estado' => $factura->estado,
                'hora' => $factura->hora,
                'tipo_pago' => $factura->tipo_pago,
                'cliente' => $factura->nombre ?: ($factura->cliente ?: 'Sin cliente'),
                'nit' => $factura->nit,
                'zona' => $factura->zona,
                'nro_pedido' => $factura->pedido_nro ? (int) $factura->pedido_nro : null,
                'pedido_tipo' => strtoupper(trim((string) $factura->pedido_tipo)),
                'placa' => $factura->placa,
                'items' => $items,
                'productos' => $productos,
                'total' => round((float) $factura->total, 2),
            ];

            // Si despues del visto bueno cambian la venta, la canasta ya no es
            // la que se reviso y el comprobante vuelve a quedar pendiente.
            $cambio = $marca && (
                (int) $marca->items_esperados !== $comprobante['productos']
                || abs((float) $marca->total_esperado - $comprobante['total']) > 0.01
            );

            $comprobante['verificado'] = $marca ? ((bool) $marca->verificado && !$cambio) : false;
            // Si la venta cambio despues, la observacion tampoco sigue valiendo:
            // se reviso otra canasta.
            $comprobante['observado'] = $marca ? ((bool) $marca->observado && !$cambio) : false;
            $comprobante['cambio'] = (bool) $cambio;
            $comprobante['observacion'] = $marca->observacion ?? null;
            $comprobante['verificado_por'] = $marca->verificado_por ?? null;
            $comprobante['verificado_en'] = $marca->verificado_en ?? null;

            return $comprobante;
        });
    }

    /**
     * Como viene la revision de un camion: lo que mira el caminero y lo que
     * caja consulta antes de dejar imprimir.
     */
    public function estado($fecha, $placa, Collection $comprobantes = null): array
    {
        // Sin la lista ya armada se rearma, pero sin el detalle de cada
        // canasta: el resumen es solo conteos y el total del camion.
        $comprobantes = $comprobantes ?? $this->comprobantes($fecha, $placa, null, false);
        $verificados = $comprobantes->where('verificado', true);
        $marcados = $comprobantes->filter(function ($comprobante) {
            return !empty($comprobante['verificado_en']);
        });

        $ultimo = $marcados->sortByDesc('verificado_en')->first();

        return [
            'fecha' => $fecha,
            'placa' => $placa,
            'comprobantes' => $comprobantes->count(),
            'verificados' => $verificados->count(),
            'pendientes' => $comprobantes->count() - $verificados->count(),
            'con_observacion' => $comprobantes->filter(function ($comprobante) {
                return !empty($comprobante['observacion']);
            })->count(),
            // Observadas: cerradas por el caminero pero con algo que reclamar.
            // Cuentan como revisadas, asi que no frenan la impresion.
            'observados' => $comprobantes->where('observado', true)->count(),
            // Un camion sin comprobantes no esta verificado: no hay nada que
            // revisar, pero tampoco un caminero que se haya hecho responsable.
            'completo' => $comprobantes->isNotEmpty()
                && $verificados->count() === $comprobantes->count(),
            'porcentaje' => $comprobantes->isNotEmpty()
                ? (int) round(($verificados->count() / $comprobantes->count()) * 100)
                : 0,
            'verificado_en' => $ultimo['verificado_en'] ?? null,
            'verificado_por' => $ultimo['verificado_por'] ?? null,
            'total' => round($comprobantes->sum('total'), 2),
        ];
    }

    /** Un comprobante concreto de la carga, para validar lo que llega a marcar. */
    public function comprobante($fecha, $placa, $facturaId)
    {
        // Se filtra por id en la consulta: armar la carga entera para sacar una
        // sola canasta era lo que hacia lenta cada tilde del caminero.
        return $this->comprobantes($fecha, $placa, (int) $facturaId)->first();
    }

    /**
     * Da por buenas de golpe todas las canastas que todavia no se tocaron y
     * devuelve cuantas marco.
     *
     * Lo que ya tiene una observacion escrita no se pisa, para no borrar sin
     * querer un faltante que el caminero anoto.
     */
    public function verificarTodo($fecha, $placa, $personalId, $nombre): int
    {
        // Sin el detalle de cada canasta: para marcar alcanza con cuantos
        // productos tenia y su total, que es lo que se guarda.
        $filas = [];
        foreach ($this->comprobantes($fecha, $placa, null, false) as $comprobante) {
            if ($comprobante['verificado'] || !empty($comprobante['observacion'])) {
                continue;
            }

            $filas[] = $this->fila($fecha, $placa, $comprobante, true, null, false, $personalId, $nombre);
        }

        // Un solo viaje a la base en vez de un update por canasta: con un
        // camion lleno eran decenas de consultas seguidas.
        if ($filas) {
            DB::table('carga_verificaciones')->upsert($filas, ['factura_id']);
        }

        return count($filas);
    }

    /** Lo que se graba de una canasta revisada. */
    public function fila($fecha, $placa, array $comprobante, $verificado, $observacion, $observado, $personalId, $nombre): array
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
            // Las dos cierran la revision; observado dice con cual de las dos.
            'observado' => $observado,
            'observacion' => $observacion !== '' ? $observacion : null,
            'personal_id' => $personalId,
            'verificado_por' => $nombre,
            'verificado_en' => $verificado ? $ahora : null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
    }

    /**
     * Pedidos de la carga en curso que todavia no pasaron por caja.
     *
     * No son parte de lo que se revisa —no hay comprobante que imprimir— pero
     * al caminero le sirve saber que su camion todavia espera papeles, y
     * explica por que la lista esta corta.
     *
     * Se cuentan solo los del ultimo dia de pedidos asignados a esa placa: el
     * preventista toma hoy lo que sale manana, asi que la carga que se esta
     * armando es siempre la del dia mas reciente, no todo lo viejo sin cobrar.
     */
    public function pedidosSinFacturar($fecha, $placa): int
    {
        $dia = DB::table('tbpedidos')
            ->where('fecha', '>=', date('Y-m-d', strtotime($fecha . ' -7 days')) . ' 00:00:00')
            ->where('fecha', '<', $this->diaSiguiente($fecha))
            ->where('bonificacion', 0)
            ->whereRaw("TRIM(COALESCE(placa, '')) = ?", [$placa])
            ->max(DB::raw('DATE(fecha)'));

        if (!$dia) {
            return 0;
        }

        return (int) DB::table('tbpedidos as p')
            ->leftJoin('facturas as f', function ($join) {
                $join->on('f.pedido_nro', '=', 'p.NroPed')
                    ->on(DB::raw('UPPER(TRIM(f.pedido_tipo))'), '=', DB::raw('UPPER(TRIM(p.tipo))'))
                    ->whereNull('f.deleted_at')
                    ->where('f.estado', '<>', 'ANULADO');
            })
            ->where('p.fecha', '>=', $dia . ' 00:00:00')
            ->where('p.fecha', '<', $this->diaSiguiente($dia))
            ->where('p.bonificacion', 0)
            ->whereRaw("TRIM(COALESCE(p.placa, '')) = ?", [$placa])
            ->whereNull('f.id')
            ->distinct()
            ->count(DB::raw("CONCAT(p.NroPed, '|', UPPER(TRIM(p.tipo)))"));
    }

    /** Los comprobantes vigentes del dia que salen en ese camion. */
    private function facturas($fecha, $placa, $facturaId = null): Collection
    {
        return DB::table('facturas as f')
            ->when($facturaId, function ($consulta) use ($facturaId) {
                return $consulta->where('f.id', $facturaId);
            })
            // El camion vive en el pedido: sin pedido (venta de mostrador) el
            // comprobante no viaja en ningun camion.
            ->join('tbpedidos as p', function ($join) {
                $join->on('p.NroPed', '=', 'f.pedido_nro')
                    ->on(DB::raw('UPPER(TRIM(p.tipo))'), '=', DB::raw('UPPER(TRIM(f.pedido_tipo))'))
                    ->where('p.bonificacion', 0);
            })
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'f.cliente_id')
            ->where('f.fecha', '>=', $fecha . ' 00:00:00')
            ->where('f.fecha', '<', $this->diaSiguiente($fecha))
            ->whereNull('f.deleted_at')
            ->where('f.estado', '<>', 'ANULADO')
            ->whereRaw("TRIM(COALESCE(p.placa, '')) = ?", [$placa])
            // Un pedido tiene muchas lineas: sin agrupar, el comprobante
            // saldria repetido una vez por cada una.
            // MariaDB con ONLY_FULL_GROUP_BY no deduce la dependencia
            // funcional de la PK: hay que nombrar cada columna de f que sale
            // sin agregar.
            ->groupBy([
                'f.id', 'f.nro_factura', 'f.tipo_comprobante', 'f.estado',
                'f.hora', 'f.tipo_pago', 'f.total', 'f.pedido_nro',
                'f.pedido_tipo', 'f.nit', 'f.nombre',
            ])
            ->orderBy('f.id')
            ->get([
                'f.id as factura_id', 'f.nro_factura', 'f.tipo_comprobante', 'f.estado',
                'f.hora', 'f.tipo_pago', 'f.total', 'f.pedido_nro', 'f.pedido_tipo',
                DB::raw("TRIM(COALESCE(f.nit, '')) as nit"),
                DB::raw("TRIM(COALESCE(f.nombre, '')) as nombre"),
                DB::raw("TRIM(COALESCE(MIN(c.Nombres), '')) as cliente"),
                DB::raw("TRIM(COALESCE(MIN(c.zona), '')) as zona"),
                DB::raw("TRIM(COALESCE(MIN(p.placa), '')) as placa"),
            ]);
    }

    /** El detalle de cada comprobante: lo que de verdad va en la canasta. */
    private function detalles(Collection $facturaIds): Collection
    {
        return DB::table('factura_detalles')
            ->whereIn('factura_id', $facturaIds->all())
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['factura_id', 'cod_prod', 'nombre', 'unidad', 'cantidad', 'peso', 'precio', 'subtotal'])
            ->map(function ($detalle) {
                return [
                    'factura_id' => $detalle->factura_id,
                    'cod_prod' => $detalle->cod_prod,
                    'nombre' => $detalle->nombre,
                    'unidad' => $detalle->unidad ?: 'UND',
                    'cantidad' => (float) $detalle->cantidad,
                    // Lo que va a granel se pesa: es lo que el caminero mira
                    // para saber si la canasta esta completa.
                    'peso' => $detalle->peso !== null ? (float) $detalle->peso : null,
                    'total' => round((float) $detalle->subtotal, 2),
                ];
            })
            ->groupBy('factura_id');
    }

    /** Cuantos productos tiene cada canasta, sin traer el detalle. */
    private function conteos(Collection $facturaIds): Collection
    {
        return DB::table('factura_detalles')
            ->whereIn('factura_id', $facturaIds->all())
            ->whereNull('deleted_at')
            ->groupBy('factura_id')
            ->select('factura_id', DB::raw('COUNT(*) as productos'))
            ->pluck('productos', 'factura_id');
    }

    /** Lo que el caminero ya marco ese dia en ese camion. */
    private function marcas($fecha, $placa): Collection
    {
        return DB::table('carga_verificaciones')
            ->where('fecha', $fecha)
            ->where('placa', $placa)
            ->get()
            ->keyBy('factura_id');
    }

    private function diaSiguiente($fecha)
    {
        return date('Y-m-d', strtotime($fecha . ' +1 day')) . ' 00:00:00';
    }
}
