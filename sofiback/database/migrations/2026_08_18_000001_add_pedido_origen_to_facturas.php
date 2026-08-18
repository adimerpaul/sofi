<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPedidoOrigenToFacturas extends Migration
{
    public function up()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->integer('pedido_nro')->nullable()->after('observacion');
            $table->string('pedido_tipo', 20)->nullable()->after('pedido_nro');
            $table->unique(['pedido_nro', 'pedido_tipo'], 'facturas_pedido_origen_unique');
        });
    }

    public function down()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropUnique('facturas_pedido_origen_unique');
            $table->dropColumn(['pedido_nro', 'pedido_tipo']);
        });
    }
}
