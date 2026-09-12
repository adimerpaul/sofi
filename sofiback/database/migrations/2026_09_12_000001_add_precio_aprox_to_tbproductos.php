<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precio aproximado por unidad.
 *
 * Hay productos que se venden por peso pero se piden por bulto (una caja de
 * pollo, un cerdo entero): al tomar el pedido todavia no se sabe cuanto pesa,
 * asi que el precio por kilo no sirve para adelantar el monto. En esos casos se
 * carga aca lo que sale aproximadamente cada unidad y el pedido calcula el
 * subtotal como cantidad x precioAprox en vez de cantidad x precio.
 *
 * Arranca en 0 y en 0 se queda hasta que alguien le ponga valor a un producto:
 * mientras sea cero el pedido sigue multiplicando por Precio como siempre.
 *
 * La columna se agrega solo si falta: en produccion se la crea a mano antes de
 * correr la migracion, y traer ese dump a local no debe romper el migrate.
 */
class AddPrecioAproxToTbproductos extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('tbproductos', 'precioAprox')) {
            return;
        }

        Schema::table('tbproductos', function (Blueprint $table) {
            $table->decimal('precioAprox', 10, 3)->nullable()->default(0)->after('Precio13');
        });
    }

    public function down()
    {
        if (!Schema::hasColumn('tbproductos', 'precioAprox')) {
            return;
        }

        Schema::table('tbproductos', function (Blueprint $table) {
            $table->dropColumn('precioAprox');
        });
    }
}
