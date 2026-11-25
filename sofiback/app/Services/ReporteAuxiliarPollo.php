<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Reporte auxiliar del dia de pollo: con que stock se arranco el dia de
 * salida, cuanto vendio cada preventista de cada codigo y con cuanto se
 * termino. Es la planilla que se llenaba a mano en Excel.
 *
 * Todo sale de los movimientos de inventario (tbstock), que es lo mismo que
 * mueve tbproductos.stock_actual, para que el stock final cuadre con el del
 * sistema:
 * - Cada venta web deja un movimiento con su factura ("VENTA WEB id", y
 *   "ANULA VENTA WEB id" al anularla). La venta cuenta en el dia de entrega
 *   de su pedido; la venta directa de mostrador, en el dia de la factura.
 *   Asi una anulacion o un retorno parcial ya vienen descontados.
 * - Las compras ("COMPRA WEB id") cuentan en el dia en que se registraron.
 * - Stock final del dia = stock actual - lo que se movio en dias posteriores.
 *   Stock inicial = stock final + lo vendido ese dia (incluye lo comprado ese
 *   dia: no hay fila aparte para eso).
 *
 * Las correcciones de stock hechas a mano desde Productos no dejan
 * movimiento, asi que no se pueden ubicar en el tiempo: cuentan como si
 * hubieran estado siempre.
 *
 * Las filas que el sistema no registra (transito, devolucion, trozado, bajas)
 * van vacias en el Excel para llenarlas a mano.
 */
class ReporteAuxiliarPollo
{
    /**
     * Las columnas de la planilla, en su orden: [rotulo corto, codigos].
     *
     * Las 7 de pollo entero juntan codigos (el frial con su pollo sin menudo
     * del mismo calibre, y todo el pollo a la brasa) y van en CAJAS: los
     * pollos vendidos de cada codigo divididos entre su tbproductos.cantidadCaja
     * (12 el frial y el sin menudo, 16 la brasa, 18 la brasa 1.8). El resto,
     * un codigo por columna y en kilos.
     */
    public const COLUMNAS = [
        '500104' => ['104', ['500104', '501114']],
        '500105' => ['105', ['500105', '501115']],
        '500106' => ['106', ['500106', '501116']],
        '500107' => ['107', ['500107', '501117']],
        '500108' => ['108', ['500108', '501118']],
        '500109' => ['109', ['500109', '501119']],
        '501005' => ['BRASA', ['501005', '501006', '501007', '501008', '501009']],
        '501600' => ['ALA', ['501600']], '501601' => ['CAD.', ['501601']], '501604' => ['PECH.', ['501604']],
        '501606' => ['P/M', ['501606']], '501704' => ['FILETE', ['501704']], '502101' => ['COG.', ['502101']],
        '502102' => ['COG.S/C', ['502102']], '502108' => ['HUE.', ['502108']], '502106' => ['MEN.', ['502106']],
        '502109' => ['HIG.', ['502109']],
    ];

    /** Columnas de pollo entero (en cajas): la columna final las suma. */
    public const ENTEROS = ['500104', '500105', '500106', '500107', '500108', '500109', '501005'];

    /** Dias de facturas para sacar el peso promedio de un pollo de cada codigo. */
    private const DIAS_PESO = 30;

    /**
     * Cuantos dias antes de la salida se busca lo vendido para ese dia: caja
     * factura el pedido la vispera o el mismo dia, el margen es por si acaso.
     */
    private const DIAS_ANTES = 7;

    public function datos($fecha): array
    {
        // PHP guarda las claves numericas como enteros: el codigo va como texto.
        $columnas = array_map('strval', array_keys(self::COLUMNAS));
        $codigos = [];
        $columnaDe = [];
        foreach (self::COLUMNAS as $columna => $def) {
            foreach ($def[1] as $cod) {
                $codigos[] = $cod;
                $columnaDe[$cod] = (string) $columna;
            }
        }
        $cero = array_fill_keys($codigos, 0.0);
        $enteros = array_fill_keys(self::ENTEROS, true);

        $movimientos = $this->movimientos($codigos, date('Y-m-d', strtotime($fecha . ' -' . self::DIAS_ANTES . ' days')));
        $facturas = $this->facturas($movimientos);
        // En el pollo entero el inventario se mueve en kilos pero se cuenta en
        // pollos: cuantos pollos por kilo trae cada factura de cada codigo.
        $pollosPorKilo = $this->pollosPorKilo($movimientos, $columnaDe, $enteros);

        $posterior = $cero;
        $vendedores = [];
        $directas = $cero;

        foreach ($movimientos as $m) {
            // Lo que el movimiento le hizo al stock: la venta lo baja.
            $delta = (float) $m->cant - (float) $m->saldo;
            $factura = $m->factura_id ? ($facturas[$m->factura_id] ?? null) : null;
            $dia = $factura ? $factura['dia'] : substr((string) $m->fecha, 0, 10);

            if ($dia > $fecha) {
                $posterior[$m->cod] += $delta;
                continue;
            }
            if ($dia !== $fecha || !$factura) {
                continue;
            }

            // Pollo entero: los pollos vendidos (los kilos x pollos por kilo de su factura).
            $vendido = isset($enteros[$columnaDe[$m->cod]])
                ? -$delta * ($pollosPorKilo[$m->factura_id . '|' . $m->cod] ?? 1)
                : -$delta;
            if ($factura['preventista_id'] === null) {
                $directas[$m->cod] += $vendido;
            } else {
                $id = $factura['preventista_id'];
                $vendedores[$id] = $vendedores[$id] ?? ['nombre' => $factura['preventista'], 'valores' => $cero];
                $vendedores[$id]['valores'][$m->cod] += $vendido;
            }
        }

        // Los preventistas con pedidos de pollo para ese dia salen aunque
        // todavia no se les haya facturado nada, como en la planilla.
        foreach ($this->preventistasDelDia($fecha) as $id => $nombre) {
            $vendedores[$id] = $vendedores[$id] ?? ['nombre' => $nombre, 'valores' => $cero];
        }
        uasort($vendedores, function ($a, $b) {
            return strcmp($a['nombre'], $b['nombre']);
        });

        $actual = DB::table('tbproductos')
            ->whereIn(DB::raw('TRIM(cod_prod)'), $codigos)
            ->pluck('stock_actual', DB::raw('TRIM(cod_prod) as cod'))
            ->map(function ($v) { return (float) $v; });

        $totalVentas = $directas;
        foreach ($vendedores as $v) {
            foreach ($codigos as $cod) {
                $totalVentas[$cod] += $v['valores'][$cod];
            }
        }

        $productos = DB::table('tbproductos')
            ->whereIn(DB::raw('TRIM(cod_prod)'), $codigos)
            ->get([DB::raw('TRIM(cod_prod) as cod'), 'Producto', 'cantidadCaja'])
            ->keyBy('cod');
        // El stock del pollo entero esta en kilos: se pasa a pollos con el peso
        // promedio de un pollo de ese codigo en lo facturado los ultimos dias.
        $kiloPorPollo = $this->kiloPorPollo(self::codigosEnteros(), $fecha);

        $stockFinal = $cero;
        $stockInicial = $cero;
        foreach ($codigos as $cod) {
            $stockFinal[$cod] = (float) $actual->get($cod, 0) - $posterior[$cod];
            if (isset($enteros[$columnaDe[$cod]])) {
                $peso = $kiloPorPollo[$cod] ?? 0;
                $stockFinal[$cod] = $peso > 0 ? $stockFinal[$cod] / $peso : 0;
            }
            $stockInicial[$cod] = $stockFinal[$cod] + $totalVentas[$cod];
        }

        // De codigos a columnas: el pollo entero en cajas (pollos / cantidadCaja
        // de cada codigo, sin dividir si el codigo no tiene cargada la caja).
        $porColumna = function (array $valores) use ($columnas, $columnaDe, $enteros, $productos) {
            $res = array_fill_keys($columnas, 0.0);
            foreach ($valores as $cod => $v) {
                $col = $columnaDe[$cod];
                $caja = isset($enteros[$col]) ? (float) optional($productos->get($cod))->cantidadCaja : 0;
                $res[$col] += $caja > 0 ? $v / $caja : $v;
            }
            return array_map(function ($v) { return round($v, 3); }, $res);
        };

        return [
            'fecha' => $fecha,
            'columnas' => array_map(function ($col) use ($productos, $enteros) {
                $cods = self::COLUMNAS[$col][1];
                return [
                    'cod_prod' => $col,
                    // Encabezado: los codigos que suma la columna.
                    'codigos' => implode('+', count($cods) > 2
                        ? [reset($cods) . '…' . substr(end($cods), -2)]
                        : $cods),
                    'corto' => self::COLUMNAS[$col][0],
                    'nombre' => implode(' + ', array_map(function ($cod) use ($productos) {
                        $p = $productos->get($cod);
                        return $cod . ' ' . trim((string) ($p->Producto ?? ''))
                            . ($p && (float) $p->cantidadCaja > 0 ? ' (' . (float) $p->cantidadCaja . ' x caja)' : '');
                    }, $cods)),
                    'entero' => isset($enteros[$col]),
                ];
            }, $columnas),
            'stock_inicial' => $porColumna($stockInicial),
            'vendedores' => array_values(array_map(function ($v) use ($porColumna) {
                return ['nombre' => $v['nombre'], 'valores' => $porColumna($v['valores'])];
            }, $vendedores)),
            'ventas_directas' => $porColumna($directas),
            'total_ventas' => $porColumna($totalVentas),
            'stock_final' => $porColumna($stockFinal),
        ];
    }

    private static function codigosEnteros(): array
    {
        $cods = [];
        foreach (self::ENTEROS as $col) {
            $cods = array_merge($cods, self::COLUMNAS[$col][1]);
        }
        return $cods;
    }

    /**
     * Pollos por kilo de cada factura y codigo de pollo entero (cantidad /
     * peso del detalle), para pasar el movimiento de stock, que va en kilos,
     * a pollos. Sin peso cargado el movimiento ya esta en pollos.
     */
    private function pollosPorKilo($movimientos, array $columnaDe, array $enteros): array
    {
        $ids = $movimientos->filter(function ($m) use ($columnaDe, $enteros) {
            return $m->factura_id && isset($enteros[$columnaDe[$m->cod]]);
        })->pluck('factura_id')->unique()->values()->all();
        if (!$ids) {
            return [];
        }

        $res = [];
        DB::table('factura_detalles')
            ->whereIn('factura_id', $ids)
            ->whereIn(DB::raw('TRIM(cod_prod)'), self::codigosEnteros())
            ->groupBy('factura_id', DB::raw('TRIM(cod_prod)'))
            ->get(['factura_id', DB::raw('TRIM(cod_prod) as cod'),
                DB::raw('SUM(cantidad) as cantidad'), DB::raw('SUM(COALESCE(peso, 0)) as peso')])
            ->each(function ($d) use (&$res) {
                $res[$d->factura_id . '|' . $d->cod] = (float) $d->peso > 0 ? (float) $d->cantidad / (float) $d->peso : 1;
            });
        return $res;
    }

    /** Peso promedio (kg) de un pollo de cada codigo en lo facturado antes de la fecha. */
    private function kiloPorPollo(array $codigos, $fecha): array
    {
        return DB::table('factura_detalles as d')
            ->join('facturas as f', 'f.id', '=', 'd.factura_id')
            ->whereNull('d.deleted_at')
            ->whereNull('f.deleted_at')
            ->where('f.estado', 'ACTIVO')
            ->whereBetween('f.fecha', [date('Y-m-d', strtotime($fecha . ' -' . self::DIAS_PESO . ' days')), $fecha])
            ->whereIn(DB::raw('TRIM(d.cod_prod)'), $codigos)
            ->where('d.peso', '>', 0)
            ->where('d.cantidad', '>', 0)
            ->groupBy(DB::raw('TRIM(d.cod_prod)'))
            ->pluck(DB::raw('SUM(d.peso) / SUM(d.cantidad) as kg'), DB::raw('TRIM(d.cod_prod) as cod'))
            ->map(function ($v) { return (float) $v; })
            ->all();
    }

    /** Los movimientos web de esos codigos desde una fecha, con su factura si es venta. */
    private function movimientos(array $codigos, $desde)
    {
        return DB::table('tbstock')
            ->whereIn(DB::raw('TRIM(cod_prod)'), $codigos)
            ->where('fecha', '>=', $desde)
            ->where(function ($w) {
                $w->where('motivstock', 'like', 'VENTA WEB %')
                    ->orWhere('motivstock', 'like', 'ANULA VENTA WEB %')
                    ->orWhere('motivstock', 'like', 'COMPRA WEB %')
                    ->orWhere('motivstock', 'like', 'ANULA COMPRA WEB %');
            })
            ->get([
                DB::raw('TRIM(cod_prod) as cod'), 'cant', 'saldo', 'fecha',
                DB::raw("CASE WHEN motivstock LIKE '%VENTA WEB %'
                    THEN CAST(SUBSTRING_INDEX(TRIM(motivstock), ' ', -1) AS UNSIGNED) END as factura_id"),
            ]);
    }

    /**
     * Por cada factura de esos movimientos: en que dia cuenta y de que
     * preventista es (null en la venta directa).
     */
    private function facturas($movimientos): array
    {
        $ids = $movimientos->pluck('factura_id')->filter()->unique()->values()->all();
        if (!$ids) {
            return [];
        }

        $facturas = DB::table('facturas')->whereIn('id', $ids)->get(['id', 'fecha', 'pedido_nro']);

        $nros = $facturas->pluck('pedido_nro')->filter()->unique()->values()->all();
        $pedidos = $nros ? DB::table('tbpedidos as p')
            ->leftJoin('personal as pe', 'pe.CodAut', '=', 'p.CIfunc')
            ->whereIn('p.NroPed', $nros)
            ->groupBy('p.NroPed')
            ->get([
                'p.NroPed',
                DB::raw('MIN(p.fecha_entrega) as fecha_entrega'),
                DB::raw('MIN(p.fecha) as fecha'),
                DB::raw('MIN(p.CIfunc) as preventista_id'),
                DB::raw("TRIM(CONCAT_WS(' ', NULLIF(TRIM(MIN(pe.Nombre1)), ''), NULLIF(TRIM(MIN(pe.App1)), ''))) as preventista"),
            ])
            ->keyBy('NroPed') : collect();

        $resultado = [];
        foreach ($facturas as $f) {
            $pedido = $f->pedido_nro ? $pedidos->get($f->pedido_nro) : null;
            if ($pedido) {
                $dia = $pedido->fecha_entrega
                    ?: date('Y-m-d', strtotime(substr((string) $pedido->fecha, 0, 10) . ' +1 day'));
                $resultado[$f->id] = [
                    'dia' => substr((string) $dia, 0, 10),
                    'preventista_id' => (int) $pedido->preventista_id,
                    'preventista' => $pedido->preventista !== '' ? $pedido->preventista : 'SIN PREVENTISTA',
                ];
            } else {
                $resultado[$f->id] = [
                    'dia' => substr((string) $f->fecha, 0, 10),
                    'preventista_id' => null,
                    'preventista' => null,
                ];
            }
        }

        return $resultado;
    }

    /** Preventistas con pedidos de pollo que salen ese dia. */
    private function preventistasDelDia($fecha)
    {
        return DB::table('tbpedidos as p')
            ->join('personal as pe', 'pe.CodAut', '=', 'p.CIfunc')
            ->whereNull('p.deleted_at')
            ->where('p.fecha_entrega', $fecha)
            ->where('p.bonificacion', 0)
            ->whereRaw(TipoPedido::sql('p') . " = 'POLLO'")
            ->groupBy('p.CIfunc')
            ->pluck(
                DB::raw("TRIM(CONCAT_WS(' ', NULLIF(TRIM(MIN(pe.Nombre1)), ''), NULLIF(TRIM(MIN(pe.App1)), ''))) as nombre"),
                'p.CIfunc'
            );
    }
}
