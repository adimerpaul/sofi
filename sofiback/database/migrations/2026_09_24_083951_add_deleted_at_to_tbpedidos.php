<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrado logico de pedidos.
 *
 * Antes deletecomanda y updatecomanda hacian DELETE fisico sobre tbpedidos y
 * no quedaba rastro de quien borro un pedido ni que tenia. Ahora las filas se
 * marcan con deleted_at y el cambio queda en la tabla audits. Toda consulta que
 * lea tbpedidos debe filtrar deleted_at IS NULL.
 */
class AddDeletedAtToTbpedidos extends Migration
{
    public function up()
    {
        Schema::table('tbpedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('tbpedidos', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down()
    {
        Schema::table('tbpedidos', function (Blueprint $table) {
            if (Schema::hasColumn('tbpedidos', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
}
