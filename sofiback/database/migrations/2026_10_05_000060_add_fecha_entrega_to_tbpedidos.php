<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dia en que se entrega el pedido.
 *
 * El preventista toma el pedido un dia y el camion lo entrega al siguiente:
 * la fecha de entrega es la del pedido mas un dia. Es la fecha con la que
 * trabajan el caminero (sus entregas) y facturacion (los pedidos a cobrar).
 * La llena el modelo Pedido al guardar; aca se completa lo que ya existe.
 */
class AddFechaEntregaToTbpedidos extends Migration
{
    public function up()
    {
        Schema::table('tbpedidos', function (Blueprint $table) {
            $table->date('fecha_entrega')->nullable()->after('fecha')->index();
        });

        DB::statement(
            'UPDATE tbpedidos SET fecha_entrega = DATE_ADD(DATE(fecha), INTERVAL 1 DAY) WHERE fecha IS NOT NULL'
        );
    }

    public function down()
    {
        Schema::table('tbpedidos', function (Blueprint $table) {
            $table->dropIndex(['fecha_entrega']);
            $table->dropColumn('fecha_entrega');
        });
    }
}
