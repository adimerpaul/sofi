<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el cajero va avanzando en un pedido antes de finalizarlo.
 *
 * Guardar no crea la venta ni toca el stock: solo deja anotados los pesos,
 * cantidades, precios y datos del comprobante para seguir despues. Recien al
 * finalizar se registra la venta, se descuenta el stock y el borrador se borra.
 */
class CreatePedidoBorradoresTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('pedido_borradores')) {
            return;
        }
        Schema::create('pedido_borradores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pedido_nro');
            $table->string('pedido_tipo', 10);
            // items, tipo_comprobante, tipo_pago, nit y observacion tal como
            // los manda la pantalla.
            $table->longText('datos');
            $table->decimal('total', 12, 2)->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('usuario', 150)->nullable();
            $table->timestamps();
            $table->unique(['pedido_nro', 'pedido_tipo']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('pedido_borradores');
    }
}
