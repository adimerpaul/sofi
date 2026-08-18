<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lineas de cada factura.
 *
 * El producto se referencia por cod_prod, que es la clave real del catalogo
 * legado (tbproductos.cod_prod, un varchar). No lleva foreign key porque
 * tbproductos esta en latin1 y una FK exigiria misma collation; el codigo se
 * valida en el controlador contra tbproductos antes de guardar.
 *
 * El nombre, la unidad y el precio se copian al guardar: la factura tiene que
 * poder reimprimirse igual aunque el producto cambie de precio o de nombre.
 */
class CreateFacturaDetallesTable extends Migration
{
    public function up()
    {
        Schema::create('factura_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();

            $table->string('cod_prod', 25);
            $table->string('nombre', 150)->nullable();
            $table->string('unidad', 10)->nullable();

            // Decimal, no entero: lo que va a granel se vende en kilos.
            $table->decimal('cantidad', 12, 3)->default(0);
            $table->decimal('precio', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->index('cod_prod');
        });
    }

    public function down()
    {
        Schema::dropIfExists('factura_detalles');
    }
}
