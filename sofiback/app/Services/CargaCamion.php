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
 * El camion de un comprobante sale del pedido que lo origino (tbpedidos.placa);
 * la venta directa sale en el camion que caja le eligio (facturas.placa) y, si
 * no se le eligio ninguno, es de mostrador y no aparece aca.
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
            // En que canasta va y la nota del caminero: siguen valiendo aunque
            // cambie la venta, la canasta fisica es la misma.
            $comprobante['nro_canasta'] = $marca->nro_canasta ?? null;
            $comprobante['nota'] = $marca->nota ?? null;
            $comprobante['verificado_por'] = $marca->verificado_por ?? null;
            $comprobante['verificado_en'] = $marca->verificado_en ?? null;

            // Producto por producto: con la canasta verificada van todos
            // tildados; si no, los que el caminero ya fue marcando. Si la venta
            // cambio, lo tildado era de otra canasta y no vale.
            $revisados = $marca && !$cambio ? $this->revisadosDe($marca) : [];
            $comprobante['items'] = array_map(function ($item) use ($comprobante, $revisados) {
                $item['revisado'] = $comprobante['verificado'] || in_array($item['id'], $revisados, true);
                return $item;
            }, $comprobante['items']);
            $comprobante['revisados'] = $detalles
                ? count(array_filter($comprobante['items'], function ($item) {
                    return $item['revisado'];
                }))
                : ($comprobante['verificado'] ? $productos : count($revisados));

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

    /**
     * Guarda el numero de canasta y la nota de un comprobante. Si la canasta
     * todavia no se reviso se abre su fila sin visto bueno; si ya tenia, no se
     * toca nada mas que estos dos campos.
     */
    public function guardarCanasta($fecha, $placa, array $comprobante, $nroCanasta, $nota, $personalId, $nombre): array
    {
        $nroCanasta = trim((string) $nroCanasta);
        $nota = trim((string) $nota);
        $campos = [
            'nro_canasta' => $nroCanasta !== '' ? $nroCanasta : null,
            'nota' => $nota !== '' ? $nota : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $existe = DB::table('carga_verificaciones')->where('factura_id', $comprobante['factura_id'])->exists();
        if ($existe) {
            DB::table('carga_verificaciones')->where('factura_id', $comprobante['factura_id'])->update($campos);
        } else {
            $fila = $this->fila($fecha, $placa, $comprobante, false, null, false, $personalId, $nombre);
            DB::table('carga_verificaciones')->insert(array_merge($fila, $campos));
        }

        $comprobante['nro_canasta'] = $campos['nro_canasta'];
        $comprobante['nota'] = $campos['nota'];

        return $comprobante;
    }

    /** Los id de factura_detalles que ya se tildaron en esa canasta. */
    public function revisadosDe($marca): array
    {
        $lista = json_decode((string) ($marca->items_revisados ?? ''), true);
        return is_array($lista) ? array_values(array_map('intval', $lista)) : [];
    }

    /**
     * Guarda lo tildado producto por producto en una canasta.
     *
     * Con todos tildados la canasta queda verificada sola; si se destilda uno
     * de una canasta verificada, vuelve a pendiente. Una observada sigue
     * observada: ahi solo se guarda lo tildado.
     */
    public function marcarProductos($fecha, $placa, array $comprobante, array $revisados, $personalId, $nombre): array
    {
        $ids = array_column($comprobante['items'], 'id');
        $revisados = array_values(array_intersect($ids, array_map('intval', $revisados)));
        $completo = count($ids) > 0 && count($revisados) === count($ids);

        $marca = DB::table('carga_verificaciones')->where('factura_id', $comprobante['factura_id'])->first();
        $observado = $comprobante['observado'];
        $verificado = $observado ? true : $completo;
        $observacion = $observado ? $comprobante['observacion'] : null;

        $fila = $this->fila($fecha, $placa, $comprobante, $verificado, $observacion, $observado, $personalId, $nombre);
        $fila['items_revisados'] = json_encode($revisados);
        // No se pisa quien abrio la fila ni cuando.
        if ($marca) {
            unset($fila['created_at']);
            if ($verificado && $marca->verificado && $marca->verificado_en) {
                $fila['verificado_en'] = $marca->verificado_en;
            }
        }

        DB::table('carga_verificaciones')->updateOrInsert(['factura_id' => $comprobante['factura_id']], $fila);

        $comprobante['verificado'] = (bool) $verificado;
        $comprobante['cambio'] = false;
        $comprobante['verificado_por'] = $fila['verificado_por'];
        $comprobante['verificado_en'] = $fila['verificado_en'];
        $comprobante['items'] = array_map(function ($item) use ($revisados, $verificado) {
            $item['revisado'] = $verificado || in_array($item['id'], $revisados, true);
            return $item;
        }, $comprobante['items']);
        $comprobante['revisados'] = $verificado ? count($ids) : count($revisados);

        return $comprobante;
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
            // Verificar o desmarcar la canasta entera reemplaza lo tildado de
            // a uno: verificada vale por todos, desmarcada vuelve a cero.
            'items_revisados' => null,
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
            ->whereNull('tbpedidos.deleted_at')
            ->where('fecha', '>=', date('Y-m-d', strtotime($fecha . ' -7 days')) . ' 00:00:00')
            ->where('fecha', '<', $this->diaSiguiente($fecha))
            ->where('bonificacion', 0)
            ->whereRaw("TRIM(COALESCE(placa, '')) = ?", [$placa])
            ->max(DB::raw('DATE(fecha)'));

        if (!$dia) {
            return 0;
        }

        return (int) DB::table('tbpedidos as p')
            ->whereNull('p.deleted_at')
            ->leftJoin('facturas as f', function ($join) {
                $join->on('f.pedido_nro', '=', 'p.NroPed')
                    ->on(DB::raw('UPPER(TRIM(f.pedido_tipo))'), '=', DB::raw(TipoPedido::sql('p')))
                    ->whereNull('f.deleted_at')
                    ->where('f.estado', '<>', 'ANULADO');
            })
            ->where('p.fecha', '>=', $dia . ' 00:00:00')
            ->where('p.fecha', '<', $this->diaSiguiente($dia))
            ->where('p.bonificacion', 0)
            ->whereRaw("TRIM(COALESCE(p.placa, '')) = ?", [$placa])
            ->whereNull('f.id')
            ->distinct()
            ->count(DB::raw("CONCAT(p.NroPed, '|', " . TipoPedido::sql('p') . ")"));
    }

    /** Los comprobantes vigentes del dia que salen en ese camion. */
    private function facturas($fecha, $placa, $facturaId = null): Collection
    {
        return DB::table('facturas as f')
            ->when($facturaId, function ($consulta) use ($facturaId) {
                return $consulta->where('f.id', $facturaId);
            })
            // El camion de lo que sale de un pedido vive en el pedido; la venta
            // directa a la que caja le eligio camion lo trae en facturas.placa.
            ->leftJoin('tbpedidos as p', function ($join) {
                $join->on('p.NroPed', '=', 'f.pedido_nro')
                    ->on(DB::raw(TipoPedido::sql('p')), '=', DB::raw('UPPER(TRIM(f.pedido_tipo))'))
                    ->whereNull('p.deleted_at')
                    ->where('p.bonificacion', 0);
            })
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'f.cliente_id')
            ->tap(function ($consulta) use ($fecha) {
                self::enJornada($consulta, $fecha);
            })
            ->whereNull('f.deleted_at')
            ->where('f.estado', '<>', 'ANULADO')
            ->where(function ($camion) use ($placa) {
                $camion->where(function ($pedido) use ($placa) {
                    $pedido->whereNotNull('p.NroPed')->whereRaw("TRIM(COALESCE(p.placa, '')) = ?", [$placa]);
                })->orWhere(function ($directa) use ($placa) {
                    $directa->whereNull('f.pedido_nro')->whereRaw("TRIM(COALESCE(f.placa, '')) = ?", [$placa]);
                });
            })
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
            ])
            // Todos salen en ese camion, vengan de un pedido o sean venta directa.
            ->each(function ($factura) use ($placa) {
                $factura->placa = $placa;
            });
    }

    /** El detalle de cada comprobante: lo que de verdad va en la canasta. */
    private function detalles(Collection $facturaIds): Collection
    {
        return DB::table('factura_detalles')
            ->whereIn('factura_id', $facturaIds->all())
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'factura_id', 'cod_prod', 'nombre', 'unidad', 'cantidad', 'peso', 'precio', 'subtotal'])
            ->map(function ($detalle) {
                return [
                    // Con el id se tilda cada producto por separado.
                    'id' => (int) $detalle->id,
                    'factura_id' => $detalle->factura_id,
                    'cod_prod' => $detalle->cod_prod,
                    'nombre' => $detalle->nombre,
                    'unidad' => $detalle->unidad ?: 'UND',
                    'cantidad' => (float) $detalle->cantidad,
                    // Lo que va a granel se pesa: es lo que el caminero mira
                    // para saber si la canasta esta completa.
                    'peso' => $detalle->peso !== null ? (float) $detalle->peso : null,
                    'total' => round((float) $detalle->subtotal, 2),
                    // Podium y huevo salen en el mismo comprobante, pero se
                    // cargan por separado: el caminero los revisa aparte.
                    'huevo' => in_array(trim((string) $detalle->cod_prod), TipoPedido::HUEVO, true),
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

    /** Hora en que cierra la jornada de reparto. */
    public const CORTE_JORNADA = '18:00:00';

    /**
     * Limita los comprobantes (alias f) a la jornada que termina ese dia: desde
     * la vispera a las 18:00 hasta ese dia a las 18:00. Caja factura de tarde
     * y de noche lo que sale a la manana siguiente, asi que el dia calendario
     * partia la carga en dos.
     *
     * fecha y hora son columnas separadas: el rango sobre f.fecha deja usar
     * el indice y la hora solo corta las puntas.
     */
    public static function enJornada($consulta, $fecha, $alias = 'f')
    {
        $vispera = date('Y-m-d', strtotime($fecha . ' -1 day'));
        $corte = self::CORTE_JORNADA;

        return $consulta
            ->where("$alias.fecha", '>=', $vispera)
            ->where("$alias.fecha", '<=', $fecha)
            ->where(function ($q) use ($alias, $vispera, $fecha, $corte) {
                $q->where(function ($q) use ($alias, $vispera, $corte) {
                    $q->where("$alias.fecha", $vispera)->where("$alias.hora", '>=', $corte);
                })->orWhere(function ($q) use ($alias, $fecha, $corte) {
                    $q->where("$alias.fecha", $fecha)->where("$alias.hora", '<', $corte);
                });
            });
    }

    /** La jornada escrita para el papel: "05/10/2026 18:00 a 06/10/2026 18:00". */
    public static function textoJornada($fecha)
    {
        $hora = substr(self::CORTE_JORNADA, 0, 5);

        return date('d/m/Y', strtotime($fecha . ' -1 day')) . ' ' . $hora
            . ' a ' . date('d/m/Y', strtotime($fecha)) . ' ' . $hora;
    }

    /**
     * El color de zona que se le dio al camion en la asignacion del mapa de
     * clientes (tbpedidos.color / colorStyle), para la venta directa que no
     * tiene pedido propio: asi sale pareja con lo demas del camion.
     *
     * La asignacion no guarda la hora, asi que vale el ultimo dia en que se
     * asigno ese camion (hasta $hasta) y, de ese dia, el color que se le dio a
     * mas pedidos: un pedido suelto con otro color no cambia la zona. Null si
     * en el ultimo mes no se le asigno nada.
     */
    public static function colorDeZona($placa, $hasta = null)
    {
        $placa = trim((string) $placa);
        if ($placa === '') {
            return null;
        }

        return self::coloresDeZona([$placa], $hasta)[$placa] ?? null;
    }

    /**
     * Lo mismo que colorDeZona para varios camiones (o todos, con null) en una
     * sola consulta: [placa => ['color' => ..., 'colorStyle' => ...]].
     */
    public static function coloresDeZona(?array $placas = null, $hasta = null): array
    {
        $hasta = substr((string) ($hasta ?: date('Y-m-d')), 0, 10);

        $filas = DB::table('tbpedidos')
            ->whereNull('deleted_at')
            // Rango sobre fecha (indexada); el ultimo mes alcanza de sobra.
            ->where('fecha', '>=', date('Y-m-d', strtotime($hasta . ' -30 days')) . ' 00:00:00')
            ->where('fecha', '<', date('Y-m-d', strtotime($hasta . ' +1 day')) . ' 00:00:00')
            ->whereRaw("TRIM(COALESCE(placa, '')) <> ''")
            ->whereRaw("TRIM(COALESCE(colorStyle, '')) <> ''")
            ->when($placas !== null, function ($q) use ($placas) {
                $q->whereIn(DB::raw('TRIM(placa)'), array_map('trim', $placas));
            })
            ->groupBy(DB::raw('TRIM(placa)'), DB::raw('DATE(fecha)'), DB::raw('TRIM(color)'), DB::raw('TRIM(colorStyle)'))
            ->get([
                DB::raw('TRIM(placa) as placa'), DB::raw('DATE(fecha) as dia'),
                DB::raw('TRIM(color) as color'), DB::raw('TRIM(colorStyle) as colorStyle'),
                DB::raw('COUNT(*) as pedidos'),
            ]);

        // Por camion: el ultimo dia y, de ese dia, el color con mas pedidos.
        return $filas->groupBy('placa')->map(function ($delCamion) {
            $dia = $delCamion->max('dia');
            $fila = $delCamion->where('dia', $dia)->sortByDesc('pedidos')->first();
            return ['color' => (string) $fila->color, 'colorStyle' => (string) $fila->colorStyle];
        })->all();
    }

    private function diaSiguiente($fecha)
    {
        return date('Y-m-d', strtotime($fecha . ' +1 day')) . ' 00:00:00';
    }
}
