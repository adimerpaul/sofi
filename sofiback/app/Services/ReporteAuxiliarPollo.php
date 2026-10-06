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
    /** Las columnas de la planilla, en su orden, con su rotulo corto. */
    public const COLUMNAS = [
        '500104' => '', '500105' => '', '500106' => '', '500107' => '',
        '500108' => '', '500109' => '', '501005' => '', '501006' => '',
        '501600' => 'ALA', '501601' => 'CAD.', '501604' => 'PECH.', '501606' => 'P/M',
        '501704' => 'FILETE', '502101' => 'COG.', '502108' => 'HUE.', '502106' => 'MEN.',
    ];

    /** Pollo entero (frial y brasa): la columna final los suma. */
    public const ENTEROS = ['500104', '500105', '500106', '500107', '500108', '500109', '501005', '501006'];

    /**
     * Cuantos dias antes de la salida se busca lo vendido para ese dia: caja
     * factura el pedido la vispera o el mismo dia, el margen es por si acaso.
     */
    private const DIAS_ANTES = 7;

    public function datos($fecha): array
    {
        // PHP guarda las claves numericas como enteros: el codigo va como texto.
        $codigos = array_map('strval', array_keys(self::COLUMNAS));
        $cero = array_fill_keys($codigos, 0.0);

        $movimientos = $this->movimientos($codigos, date('Y-m-d', strtotime($fecha . ' -' . self::DIAS_ANTES . ' days')));
        $facturas = $this->facturas($movimientos);

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

            if ($factura['preventista_id'] === null) {
                $directas[$m->cod] -= $delta;
            } else {
                $id = $factura['preventista_id'];
                $vendedores[$id] = $vendedores[$id] ?? ['nombre' => $factura['preventista'], 'valores' => $cero];
                $vendedores[$id]['valores'][$m->cod] -= $delta;
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

        $stockFinal = $cero;
        $stockInicial = $cero;
        foreach ($codigos as $cod) {
            $stockFinal[$cod] = (float) $actual->get($cod, 0) - $posterior[$cod];
            $stockInicial[$cod] = $stockFinal[$cod] + $totalVentas[$cod];
        }

        $nombres = DB::table('tbproductos')
            ->whereIn(DB::raw('TRIM(cod_prod)'), $codigos)
            ->pluck('Producto', DB::raw('TRIM(cod_prod) as cod'));

        $redondear = function (array $valores) {
            return array_map(function ($v) { return round($v, 3); }, $valores);
        };

        return [
            'fecha' => $fecha,
            'columnas' => array_map(function ($cod) use ($nombres) {
                return [
                    'cod_prod' => $cod,
                    'corto' => self::COLUMNAS[$cod],
                    'nombre' => trim((string) $nombres->get($cod, '')),
                    'entero' => in_array($cod, self::ENTEROS, true),
                ];
            }, $codigos),
            'stock_inicial' => $redondear($stockInicial),
            'vendedores' => array_values(array_map(function ($v) use ($redondear) {
                return ['nombre' => $v['nombre'], 'valores' => $redondear($v['valores'])];
            }, $vendedores)),
            'ventas_directas' => $redondear($directas),
            'total_ventas' => $redondear($totalVentas),
            'stock_final' => $redondear($stockFinal),
        ];
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
