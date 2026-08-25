<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rellena la cantidad pedida de las ventas que se emitieron antes de que
 * existiera la columna.
 *
 * El dato no se habia perdido del todo: el pedido original sigue en tbpedidos
 * con la cantidad que pidio el cliente, asi que se puede recuperar linea por
 * linea y dejarlo guardado junto a lo que se entrego.
 *
 * Se hace en PHP y no con un UPDATE ... JOIN a proposito: factura_detalles es
 * utf8mb4 y tbpedidos es latin1, y cruzar cod_prod entre las dos en SQL revienta
 * por mezcla de collations.
 *
 * Solo toca filas en null y solo de facturas que vienen de un pedido. Lo que el
 * cajero agrego del catalogo no estaba en el pedido y se queda en null, que es
 * lo correcto: no hay cantidad pedida con que compararlo.
 */
class BackfillCantidadPedidaFacturaDetalles extends Migration
{
    public function up()
    {
        $facturas = DB::table('facturas')
            ->whereNotNull('pedido_nro')
            ->whereNull('deleted_at')
            ->get(['id', 'pedido_nro', 'pedido_tipo']);

        foreach ($facturas as $factura) {
            $pedidas = DB::table('tbpedidos')
                ->where('NroPed', $factura->pedido_nro)
                ->whereRaw('UPPER(TRIM(tipo)) = ?', [strtoupper(trim((string) $factura->pedido_tipo))])
                ->where('bonificacion', 0)
                ->get(['cod_prod', 'Cant'])
                ->mapWithKeys(function ($fila) {
                    return [trim($fila->cod_prod) => (float) $fila->Cant];
                });

            if ($pedidas->isEmpty()) {
                continue;
            }

            $detalles = DB::table('factura_detalles')
                ->where('factura_id', $factura->id)
                ->whereNull('cantidad_pedida')
                ->get(['id', 'cod_prod']);

            foreach ($detalles as $detalle) {
                $codigo = trim((string) $detalle->cod_prod);
                if (!$pedidas->has($codigo)) {
                    continue;
                }

                DB::table('factura_detalles')
                    ->where('id', $detalle->id)
                    ->update(['cantidad_pedida' => $pedidas->get($codigo)]);
            }
        }
    }

    public function down()
    {
        // No se revierte: una vez rellenada, la cantidad pedida no se distingue
        // de la que grabo el cajero al cobrar. Para deshacerlo del todo esta el
        // down() de la migracion que crea la columna.
    }
}
