<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El stock pasa a vivir en tbproductos en vez de calcularse sobre tbstock.
 *
 * - stock_actual: la existencia del producto. Arranca con el conteo del
 *   2026-10-05 (database/data/stock_inicial_2026_10_05.csv); lo que no esta en
 *   esa lista queda en 0. Desde ahi lo mueven las ventas y compras del sistema.
 * - precio_compra: por ahora vacio.
 *
 * Se llama stock_actual y no stock porque tbproductos ya tiene una columna
 * "stock" (varchar(1), la marca S del sistema anterior).
 *
 * tbstock no se toca: sigue siendo el historial de movimientos.
 */
class AddStockActualYPrecioCompraToTbproductos extends Migration
{
    private $archivo = 'database/data/stock_inicial_2026_10_05.csv';

    public function up()
    {
        Schema::table('tbproductos', function (Blueprint $table) {
            $table->decimal('stock_actual', 12, 3)->default(0);
            $table->decimal('precio_compra', 12, 2)->nullable();
        });

        $f = fopen(base_path($this->archivo), 'r');
        fgetcsv($f, 0, ';'); // encabezado

        $cargados = 0;
        $noExisten = [];
        DB::transaction(function () use ($f, &$cargados, &$noExisten) {
            // Todo en cero; despues solo lo del conteo toma su cantidad.
            DB::table('tbproductos')->update(['stock_actual' => 0]);

            while (($r = fgetcsv($f, 0, ';')) !== false) {
                if (count($r) < 2 || trim($r[0]) === '') {
                    continue;
                }
                $cod = trim($r[0]);
                $stock = round((float) str_replace(',', '.', trim($r[1])), 3);

                $n = DB::table('tbproductos')->whereRaw('TRIM(cod_prod) = ?', [$cod])
                    ->update(['stock_actual' => $stock]);
                $n ? $cargados++ : $noExisten[] = $cod;
            }
        });
        fclose($f);

        echo "  Stock inicial cargado en {$cargados} productos\n";
        foreach ($noExisten as $cod) {
            echo "  No existe en tbproductos: {$cod}\n";
        }
    }

    public function down()
    {
        Schema::table('tbproductos', function (Blueprint $table) {
            $table->dropColumn(['stock_actual', 'precio_compra']);
        });
    }
}
