<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lineas de cada compra.
 *
 * El producto se referencia por cod_prod (la clave real del catalogo legado).
 * Sin foreign key porque tbproductos esta en latin1 y una FK exigiria la misma
 * collation; el codigo se valida en el controlador antes de guardar.
 *
 * El nombre y la unidad se copian al guardar para que la compra se pueda
 * reimprimir igual aunque el producto cambie despues.
 */
class CreateCompraDetallesTable extends Migration
{
    public function up()
    {
        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();

            $table->string('cod_prod', 25);
            $table->string('nombre', 150)->nullable();
            $table->string('unidad', 10)->nullable();

            // Decimal: lo que va a granel se compra en kilos.
            $table->decimal('cantidad', 12, 3)->default(0);
            // Lo que se paga por unidad al proveedor.
            $table->decimal('precio', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);

            // Opcional: si viene, se actualiza el precio de venta del producto.
            $table->decimal('precio_venta', 12, 2)->nullable();

            $table->string('lote', 50)->nullable();
            $table->date('fecha_vencimiento')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('cod_prod');
        });
    }

    public function down()
    {
        Schema::dropIfExists('compra_detalles');
    }
}
