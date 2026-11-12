<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Precio de compra de cada linea vendida, copiado de tbproductos.precio_compra
 * al emitir (pedido facturado o venta directa), igual que el precio de venta:
 * asi la ganancia de una venta no cambia cuando despues se actualiza el costo
 * del producto con una compra nueva.
 *
 * Es por unidad de cobro (kilo o pieza, lo mismo que multiplica al precio):
 * el costo de la linea es cantidad_facturada x precio_compra.
 *
 * Las lineas viejas se rellenan con el precio de compra que tiene hoy el
 * producto, que es lo que ya usaban los reportes de detalle y base de ventas.
 * Lo que no tiene precio de compra cargado queda en null (sin costo).
 * En PHP y no con UPDATE ... JOIN: factura_detalles es utf8mb4 y tbproductos
 * latin1, y cruzar cod_prod entre las dos revienta por collation.
 */
class AddPrecioCompraToFacturaDetalles extends Migration
{
    public function up()
    {
        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->decimal('precio_compra', 12, 2)->nullable()->after('precio');
        });

        $costos = DB::table('tbproductos')
            ->whereNotNull('precio_compra')
            ->get([DB::raw('TRIM(cod_prod) as cod'), 'precio_compra'])
            ->mapWithKeys(function ($p) {
                return [strtoupper((string) $p->cod) => round((float) $p->precio_compra, 2)];
            });

        foreach ($costos as $cod => $costo) {
            DB::table('factura_detalles')
                ->whereNull('precio_compra')
                ->where('cod_prod', $cod)
                ->update(['precio_compra' => $costo]);
        }
    }

    public function down()
    {
        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->dropColumn('precio_compra');
        });
    }
}
