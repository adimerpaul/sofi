<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CUIS: Codigo Unico de Inicio de Sistema.
 *
 * Se pide una vez por sucursal y punto de venta y dura cerca de un anio. Es
 * el requisito previo para pedir un CUFD, asi que hay que tenerlo guardado y
 * no solo verlo en pantalla.
 */
class CreateSiatCuisTable extends Migration
{
    public function up()
    {
        Schema::create('siat_cuis', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 100);
            $table->dateTime('fecha_vigencia')->nullable();

            $table->unsignedInteger('codigo_sucursal')->default(0);
            $table->unsignedInteger('codigo_punto_venta')->default(0);

            // Ambiente en el que se pidio: un CUIS de piloto no sirve en
            // produccion, y conviene saber de cual es cada fila.
            $table->unsignedTinyInteger('codigo_ambiente')->default(2);

            // Quien lo genero (personal.CodAut). Sin foreign key a proposito:
            // es solo trazabilidad y no debe impedir dar de baja a nadie.
            $table->integer('user_id')->nullable();

            // Respuesta cruda del SIAT, por si hay que reclamar algo.
            $table->text('respuesta')->nullable();

            $table->timestamps();

            $table->index(['codigo_sucursal', 'codigo_punto_venta', 'fecha_vigencia'], 'siat_cuis_vigencia_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('siat_cuis');
    }
}
