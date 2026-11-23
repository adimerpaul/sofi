<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productos en riesgo: la cantidad y el vencimiento se pueden corregir en la lista
 * (no en el inventario). Se guarda quien hizo el ultimo cambio y cuando, y los
 * valores que venian del conteo para poder verlos.
 */
class AddEditadoToProductosRiesgo extends Migration
{
    public function up()
    {
        Schema::table('productos_riesgo', function (Blueprint $table) {
            $table->double('cantidad_original', 12, 2)->nullable()->after('cantidad');
            $table->date('fecha_vencimiento_original')->nullable()->after('fecha_vencimiento');
            $table->unsignedInteger('editado_por')->nullable()->after('agregado_at');
            $table->string('editado_por_nombre')->nullable()->after('editado_por');
            $table->dateTime('editado_at')->nullable()->after('editado_por_nombre');
        });
    }

    public function down()
    {
        Schema::table('productos_riesgo', function (Blueprint $table) {
            $table->dropColumn(['cantidad_original', 'fecha_vencimiento_original', 'editado_por', 'editado_por_nombre', 'editado_at']);
        });
    }
}
