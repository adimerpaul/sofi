<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productos en riesgo:
 * - generacion: numero de cada "Llenar con inventario". La pantalla solo muestra la
 *   ultima; las anteriores quedan en la tabla como historial.
 * - dias: los dias de vencimiento con que se lleno, para "Actualizar datos".
 * - observacion: comentario que escribe el usuario en la lista (el "comentario"
 *   sigue siendo el del conteo).
 */
class AddGeneracionObservacionToProductosRiesgo extends Migration
{
    public function up()
    {
        Schema::table('productos_riesgo', function (Blueprint $table) {
            $table->unsignedInteger('generacion')->nullable()->index()->after('id');
            $table->unsignedSmallInteger('dias')->nullable()->after('generacion');
            $table->string('observacion', 500)->nullable()->after('comentario');
        });
    }

    public function down()
    {
        Schema::table('productos_riesgo', function (Blueprint $table) {
            $table->dropColumn(['generacion', 'dias', 'observacion']);
        });
    }
}
