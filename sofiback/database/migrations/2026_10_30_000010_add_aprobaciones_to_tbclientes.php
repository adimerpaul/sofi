<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aprobacion de clientes nuevos. Un preventista da de alta al cliente desde
 * "Nuevo Cliente" y queda pendiente: lo aprueban por separado Ventas y
 * Logistica desde la pantalla Clientes (permiso clientes).
 *
 * Los clientes que ya existen quedan aprobados (default 1); solo los que crea
 * un preventista arrancan en 0. creado_por y *_por guardan personal.CodAut.
 */
class AddAprobacionesToTbclientes extends Migration
{
    public function up()
    {
        Schema::table('tbclientes', function (Blueprint $table) {
            if (!Schema::hasColumn('tbclientes', 'aprobado_ventas')) {
                $table->tinyInteger('aprobado_ventas')->default(1);
                $table->unsignedInteger('aprobado_ventas_por')->nullable();
                $table->dateTime('aprobado_ventas_en')->nullable();
            }
            if (!Schema::hasColumn('tbclientes', 'aprobado_logistica')) {
                $table->tinyInteger('aprobado_logistica')->default(1);
                $table->unsignedInteger('aprobado_logistica_por')->nullable();
                $table->dateTime('aprobado_logistica_en')->nullable();
            }
            if (!Schema::hasColumn('tbclientes', 'creado_por')) {
                $table->unsignedInteger('creado_por')->nullable();
                $table->dateTime('creado_en')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('tbclientes', function (Blueprint $table) {
            $table->dropColumn([
                'aprobado_ventas', 'aprobado_ventas_por', 'aprobado_ventas_en',
                'aprobado_logistica', 'aprobado_logistica_por', 'aprobado_logistica_en',
                'creado_por', 'creado_en',
            ]);
        });
    }
}
