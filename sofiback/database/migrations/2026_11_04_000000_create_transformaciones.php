<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transformacion de productos: se sacan kilos de uno o varios productos de
 * origen (pechuga, cadera, alas a granel...) y se ingresan kilos de los
 * productos que salen de ellos (frial mediano, frial grande...), o al reves.
 *
 * transformaciones es la cabecera (quien, cuando, estado, anulacion) y
 * transformacion_detalles cada producto con su tipo: ORIGEN (sale del stock)
 * o DESTINO (entra al stock). El movimiento real va a tbstock y
 * tbproductos.stock_actual, igual que una compra.
 */
class CreateTransformaciones extends Migration
{
    public function up()
    {
        Schema::create('transformaciones', function (Blueprint $table) {
            $table->id();

            // personal.CodAut es int SIGNED.
            $table->integer('user_id');
            $table->foreign('user_id')->references('CodAut')->on('personal');

            $table->date('fecha');
            $table->time('hora');
            $table->string('estado', 20)->default('ACTIVO');
            $table->string('observacion', 255)->nullable();

            $table->decimal('total_origen_kg', 12, 3)->default(0);
            $table->decimal('total_destino_kg', 12, 3)->default(0);

            $table->string('motivo_anulacion', 255)->nullable();
            $table->integer('anulado_por')->nullable();
            $table->foreign('anulado_por')->references('CodAut')->on('personal');
            $table->timestamp('anulado_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['fecha', 'estado']);
        });

        Schema::create('transformacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transformacion_id')->constrained('transformaciones');
            $table->string('tipo', 10); // ORIGEN | DESTINO
            $table->string('cod_prod', 25);
            $table->string('producto', 150);
            $table->string('unidad', 20)->nullable();
            $table->decimal('kg', 12, 3);
            $table->decimal('cajas', 10, 2)->nullable();
            $table->timestamps();

            $table->index('cod_prod');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transformacion_detalles');
        Schema::dropIfExists('transformaciones');
    }
}
