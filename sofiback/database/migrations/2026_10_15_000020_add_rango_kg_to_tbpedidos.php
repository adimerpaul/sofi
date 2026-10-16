<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * rango_kg: peso aproximado que pide el cliente para una linea (2.8, 2.1...),
 * con tolerancia de +-100 g. Lo carga el preventista con el switch "Rango" de
 * la linea en Visita / Mis pedidos y se ve al facturar el pedido.
 *
 * NULL = la linea no lleva rango. No es tbpedidos.rango: ese es entero y
 * cuenta unidades del pedido de pollo.
 */
class AddRangoKgToTbpedidos extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('tbpedidos', 'rango_kg')) {
            Schema::table('tbpedidos', function (Blueprint $table) {
                $table->decimal('rango_kg', 8, 3)->nullable();
            });
        }
    }

    public function down()
    {
        Schema::table('tbpedidos', function (Blueprint $table) {
            $table->dropColumn('rango_kg');
        });
    }
}
