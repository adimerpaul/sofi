<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * oculto: pedido que no va a salir (no hay producto) y caja lo saca de la
 * cuenta en Pedido factura, para que el avance del dia pueda llegar al 100%.
 * Se marca y se restablece desde la tarjeta del pedido; va en todas las
 * lineas del pedido (NroPed + tipo).
 */
class AddOcultoToTbpedidos extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('tbpedidos', 'oculto')) {
            Schema::table('tbpedidos', function (Blueprint $table) {
                $table->boolean('oculto')->default(false);
            });
        }
    }

    public function down()
    {
        Schema::table('tbpedidos', function (Blueprint $table) {
            $table->dropColumn('oculto');
        });
    }
}
