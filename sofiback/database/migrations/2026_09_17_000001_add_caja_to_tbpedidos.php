<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unidad en la que se pidio un producto que se vende por caja.
 *
 * Cuando el producto tiene codUnid CAJA, el vendedor elige en la visita si la
 * cantidad va en unidades (U), cajas (CAJA) o kilos (KG). Para el resto de
 * productos queda en null.
 */
class AddCajaToTbpedidos extends Migration
{
    public function up()
    {
        Schema::table('tbpedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('tbpedidos', 'caja')) {
                $table->string('caja', 10)->nullable()->after('Cant');
            }
        });
    }

    public function down()
    {
        Schema::table('tbpedidos', function (Blueprint $table) {
            if (Schema::hasColumn('tbpedidos', 'caja')) {
                $table->dropColumn('caja');
            }
        });
    }
}
