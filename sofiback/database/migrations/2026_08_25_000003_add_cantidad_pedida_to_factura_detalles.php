<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuanto pidio el cliente, al lado de cuanto se le entrego.
 *
 * Hasta ahora, si el pedido decia 6 y en el mostrador salian 4, los 2 que no
 * se entregaron no quedaban en ningun lado: la factura guardaba 4 y el pedido
 * seguia diciendo 6, sin rastro de que la diferencia fue una decision del
 * cajero. Con esta columna la linea guarda las dos cifras y se puede revisar
 * despues que se cambio y por cuanto.
 *
 * Nullable a proposito: en la venta directa, y en las lineas que el cajero
 * agrega del catalogo, no hay pedido con que comparar. Las facturas ya
 * emitidas quedan en null, que es justamente "no se sabe".
 */
class AddCantidadPedidaToFacturaDetalles extends Migration
{
    public function up()
    {
        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->decimal('cantidad_pedida', 12, 3)->nullable()->after('cantidad');
        });
    }

    public function down()
    {
        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->dropColumn('cantidad_pedida');
        });
    }
}
