<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CUFD: Codigo Unico de Facturacion Diaria.
 *
 * Caduca cada 24 horas, asi que esta tabla crece una fila por dia y punto de
 * venta. Se guarda el codigo (largo, de ahi el varchar 500) y el codigo de
 * control, que es lo que despues entra en el calculo del CUF de cada factura.
 */
class CreateSiatCufdsTable extends Migration
{
    public function up()
    {
        Schema::create('siat_cufds', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 500);
            $table->string('codigo_control', 100)->nullable();
            $table->string('direccion', 255)->nullable();

            $table->dateTime('fecha_vigencia')->nullable();

            $table->unsignedInteger('codigo_sucursal')->default(0);
            $table->unsignedInteger('codigo_punto_venta')->default(0);
            $table->unsignedTinyInteger('codigo_ambiente')->default(2);

            // CUIS con el que se pidio, para poder rastrear el encadenado.
            $table->unsignedBigInteger('siat_cui_id')->nullable();

            $table->integer('user_id')->nullable();
            $table->text('respuesta')->nullable();

            $table->timestamps();

            $table->index(['codigo_sucursal', 'codigo_punto_venta', 'fecha_vigencia'], 'siat_cufds_vigencia_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('siat_cufds');
    }
}
