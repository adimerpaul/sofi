<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peso pesado en balanza de las lineas que se venden a granel.
 *
 * En los productos por kilo lo que se cobra es el peso, no la cantidad: el
 * pedido pide 3 chuletas y en el mostrador esas 3 chuletas dan 1.700 kg, que
 * es lo que multiplica al precio. Por eso hacen falta las dos columnas:
 * cantidad guarda cuantas piezas se entregan y peso los kilos cobrados.
 *
 * Queda nullable: en los productos por unidad no aplica y las lineas ya
 * emitidas se siguen cobrando por cantidad, como hasta ahora.
 */
class AddPesoToFacturaDetalles extends Migration
{
    public function up()
    {
        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->decimal('peso', 12, 3)->nullable()->after('cantidad');
        });
    }

    public function down()
    {
        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->dropColumn('peso');
        });
    }
}
