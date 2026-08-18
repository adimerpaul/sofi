<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cabecera de las compras hechas desde el sistema web.
 *
 * Mismo criterio que facturas: maestro-detalle propio, con las claves
 * apuntando a los maestros legados que ya existen en Sofia, para no duplicar
 * proveedores ni personal.
 *
 *   user_id      -> personal.CodAut  (el modelo User usa la tabla personal)
 *   proveedor_id -> tbproveedor.CodAut
 *
 * Los int son SIGNED a proposito: las columnas referenciadas lo son y MySQL
 * rechaza la foreign key si no coinciden (error 3780).
 */
class CreateComprasTable extends Migration
{
    public function up()
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();

            $table->integer('user_id');
            $table->foreign('user_id')->references('CodAut')->on('personal');

            $table->integer('proveedor_id')->nullable();
            $table->foreign('proveedor_id')->references('CodAut')->on('tbproveedor');

            $table->date('fecha');
            $table->time('hora');

            // Se copian del proveedor: la compra no debe cambiar si manana
            // editan su ficha.
            $table->string('nit', 20)->nullable();
            $table->string('proveedor', 100)->nullable();

            $table->string('nro_factura', 30)->nullable();
            $table->string('tipo_pago', 20)->default('EFECTIVO');
            $table->string('estado', 20)->default('ACTIVO');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('observacion', 255)->nullable();

            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamp('anulado_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['fecha', 'estado']);
            $table->index('nro_factura');
        });
    }

    public function down()
    {
        Schema::dropIfExists('compras');
    }
}
