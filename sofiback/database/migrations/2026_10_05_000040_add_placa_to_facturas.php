<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Camion de una venta directa (sin pedido).
 *
 * En lo que sale de un pedido el camion sigue siendo tbpedidos.placa; esta
 * columna solo la llena la venta directa, para poder filtrarla por camion en
 * facturacion igual que si hubiera sido un pedido.
 */
class AddPlacaToFacturas extends Migration
{
    public function up()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('placa', 50)->nullable()->after('pedido_tipo')->index();
        });
    }

    public function down()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropIndex(['placa']);
            $table->dropColumn('placa');
        });
    }
}
