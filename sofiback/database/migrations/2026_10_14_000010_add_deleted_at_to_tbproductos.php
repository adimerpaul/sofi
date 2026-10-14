<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrado logico de productos.
 *
 * Eliminar un producto desde la pantalla ya no lo pierde: queda marcado con
 * deleted_at (y quien lo hizo en la tabla audits) y se puede restaurar. El
 * nombre sigue llevando el sufijo INACTIVO mientras esta eliminado, porque es
 * lo que respetan las consultas crudas sobre tbproductos (catalogos, app del
 * preventista, sistema de caja).
 *
 * La columna se agrega solo si falta, como las demas de tbproductos.
 */
class AddDeletedAtToTbproductos extends Migration
{
    public function up()
    {
        Schema::table('tbproductos', function (Blueprint $table) {
            if (!Schema::hasColumn('tbproductos', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down()
    {
        Schema::table('tbproductos', function (Blueprint $table) {
            if (Schema::hasColumn('tbproductos', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
}
