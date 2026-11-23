<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productos en riesgo (Almacen > Productos en riesgo): lista de seguimiento de lo
 * contado en un inventario que vence pronto (por defecto en 20 dias o menos).
 *
 * No toca el inventario: copia los datos del conteo (registro_almacenes) al generar.
 * Sin FK a almacenes a proposito: "Eliminar todos" del inventario no debe borrar el
 * historial. Quitar no borra la fila: pasa a QUITADO con quien y cuando. Al generar
 * otra vez (nuevo inventario) todos los ACTIVO pasan a QUITADO y entra la lista nueva.
 */
class CreateProductosRiesgo extends Migration
{
    public function up()
    {
        Schema::create('productos_riesgo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('almacen_id')->nullable()->index();
            $table->unsignedBigInteger('registro_almacen_id')->nullable();
            $table->date('fecha_inventario')->nullable();
            $table->string('codigo', 20)->nullable();          // A, B, C... del inventario
            $table->string('codigo_producto', 50)->nullable()->index();
            $table->string('producto')->nullable();
            $table->string('unidad', 20)->nullable();
            $table->string('grupo')->nullable();
            $table->string('lote')->nullable();
            $table->date('fecha_vencimiento')->nullable()->index();
            $table->double('cantidad', 12, 2)->nullable();
            $table->string('comentario')->nullable();
            $table->string('contado_por')->nullable();
            $table->string('estado', 20)->default('ACTIVO')->index();   // ACTIVO | QUITADO
            $table->unsignedInteger('agregado_por')->nullable();
            $table->string('agregado_por_nombre')->nullable();
            $table->dateTime('agregado_at')->nullable();
            $table->unsignedInteger('quitado_por')->nullable();
            $table->string('quitado_por_nombre')->nullable();
            $table->dateTime('quitado_at')->nullable();
            $table->string('motivo_quitado')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('productos_riesgo');
    }
}
