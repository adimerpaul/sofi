<?php

namespace App\Services;

use App\Models\Factura;
use Illuminate\Support\Facades\DB;

/**
 * Lo que cambio entre el pedido del preventista y el comprobante que se
 * emitio en caja.
 *
 * Al cobrar un pedido el cajero puede corregir cantidades, precios, agregar o
 * quitar productos, o cambiar comprobante, forma de pago y NIT. La factura
 * queda marcada (cambio_pedido) y con el detalle de que se cambio
 * (cambio_pedido_campos), con la misma forma que modificacion_campos para que
 * las pantallas lo muestren igual.
 */
class CambioPedido
{
    /** Compara y guarda en la factura. No hace nada si no viene de un pedido. */
    public function registrar(Factura $factura): void
    {
        if (!$factura->pedido_nro) {
            return;
        }

        $campos = $this->diferencias($factura);
        $factura->forceFill([
            'cambio_pedido'        => $campos !== null && count($campos['resumen']) > 0,
            'cambio_pedido_campos' => $campos,
        ])->save();
    }

    /** Null si el pedido ya no existe (no hay contra que comparar). */
    public function diferencias(Factura $factura): ?array
    {
        $tipo = strtoupper(trim((string) $factura->pedido_tipo));

        $lineasPedido = DB::table('tbpedidos as p')
            ->leftJoin('tbproductos as pr', function ($join) {
                $join->on(DB::raw('TRIM(pr.cod_prod)'), '=', DB::raw('TRIM(p.cod_prod)'));
            })
            ->whereNull('p.deleted_at')
            ->where('p.NroPed', $factura->pedido_nro)
            ->whereRaw('UPPER(TRIM(p.tipo)) = ?', [$tipo])
            ->where('p.bonificacion', 0)
            ->get([
                DB::raw('TRIM(p.cod_prod) as cod_prod'),
                DB::raw("COALESCE(NULLIF(TRIM(pr.Producto), ''), CONCAT('Producto ', TRIM(p.cod_prod))) as nombre"),
                DB::raw('COALESCE(p.Cant, 0) as cantidad'),
                DB::raw('COALESCE(p.precio, 0) as precio'),
                'p.fact', 'p.pago', 'p.idCli',
            ]);

        if ($lineasPedido->isEmpty()) {
            return null;
        }

        // Cabecera: lo que la pantalla de caja propone a partir del pedido.
        $primera = $lineasPedido->first();
        $nitPedido = trim((string) DB::table('tbclientes')->where('Cod_Aut', $primera->idCli)->value('Id'));
        $propuesto = [
            'tipo_comprobante' => ['Comprobante', strtoupper(trim((string) $primera->fact)) === 'SI' ? 'FACTURA' : 'VENTA'],
            'tipo_pago'        => ['Forma de pago', stripos((string) $primera->pago, 'CREDIT') !== false ? 'CRÉDITO' : 'EFECTIVO'],
            'nit'              => ['NIT/CI', $nitPedido],
        ];
        $cabecera = [];
        foreach ($propuesto as $campo => [$etiqueta, $antes]) {
            $antes = $this->texto($antes);
            $despues = $this->texto($factura->{$campo});
            if (mb_strtoupper((string) $antes) !== mb_strtoupper((string) $despues)) {
                $cabecera[] = ['campo' => $campo, 'etiqueta' => $etiqueta, 'antes' => $antes, 'despues' => $despues];
            }
        }

        // Productos: un mismo codigo puede venir en varias lineas (otro precio),
        // asi que se compara la cantidad total y los precios usados.
        $agrupar = function ($lineas) {
            return collect($lineas)->groupBy(function ($l) {
                return trim((string) $l->cod_prod);
            })->map(function ($grupo) {
                return [
                    'nombre'   => trim((string) $grupo->first()->nombre),
                    'cantidad' => round($grupo->sum(function ($l) { return (float) $l->cantidad; }), 3),
                    'precios'  => $grupo->map(function ($l) { return round((float) $l->precio, 2); })->unique()->sort()->values(),
                ];
            });
        };
        $pedido = $agrupar($lineasPedido);
        $vendido = $agrupar($factura->detalles()->get(['cod_prod', 'nombre', 'cantidad', 'precio']));

        $productos = [];
        foreach ($vendido as $codigo => $linea) {
            $previa = $pedido->get($codigo);
            if (!$previa) {
                $productos[] = [
                    'cod_prod' => (string) $codigo, 'nombre' => $linea['nombre'], 'cambio' => 'AGREGADO',
                    'campos' => [['campo' => 'cantidad', 'etiqueta' => 'Cantidad', 'antes' => null, 'despues' => $linea['cantidad']]],
                ];
                continue;
            }
            $campos = [];
            if (abs($previa['cantidad'] - $linea['cantidad']) > 0.0005) {
                $campos[] = ['campo' => 'cantidad', 'etiqueta' => 'Cantidad', 'antes' => $previa['cantidad'], 'despues' => $linea['cantidad']];
            }
            if ($linea['precios']->diff($previa['precios'])->isNotEmpty()) {
                $campos[] = ['campo' => 'precio', 'etiqueta' => 'Precio',
                    'antes' => $previa['precios']->implode(' / '), 'despues' => $linea['precios']->implode(' / ')];
            }
            if ($campos) {
                $productos[] = ['cod_prod' => (string) $codigo, 'nombre' => $linea['nombre'], 'cambio' => 'MODIFICADO', 'campos' => $campos];
            }
        }
        foreach ($pedido as $codigo => $previa) {
            if (!$vendido->has($codigo)) {
                $productos[] = [
                    'cod_prod' => (string) $codigo, 'nombre' => $previa['nombre'], 'cambio' => 'QUITADO',
                    'campos' => [['campo' => 'cantidad', 'etiqueta' => 'Cantidad', 'antes' => $previa['cantidad'], 'despues' => null]],
                ];
            }
        }

        $resumen = array_map(function ($c) { return $c['etiqueta']; }, $cabecera);
        foreach ($productos as $p) {
            $resumen[] = $p['nombre'] . ': ' . ($p['cambio'] === 'MODIFICADO'
                ? implode(', ', array_map(function ($c) { return mb_strtolower($c['etiqueta']); }, $p['campos']))
                : mb_strtolower($p['cambio']));
        }

        return [
            'pedido_nro' => (int) $factura->pedido_nro,
            'cabecera'   => $cabecera,
            'productos'  => $productos,
            'resumen'    => $resumen,
        ];
    }

    private function texto($valor)
    {
        $texto = trim((string) $valor);
        return $texto === '' ? null : $texto;
    }
}
