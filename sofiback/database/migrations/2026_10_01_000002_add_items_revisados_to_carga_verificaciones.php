<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision de la carga producto por producto.
 *
 * items_revisados guarda los id de factura_detalles que el caminero ya tildo
 * en esa canasta (JSON). Cuando estan todos, la canasta queda verificada.
 */
class AddItemsRevisadosToCargaVerificaciones extends Migration
{
    public function up()
    {
        Schema::table('carga_verificaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('carga_verificaciones', 'items_revisados')) {
                $table->text('items_revisados')->nullable()->after('total_esperado');
            }
        });
    }

    public function down()
    {
        Schema::table('carga_verificaciones', function (Blueprint $table) {
            if (Schema::hasColumn('carga_verificaciones', 'items_revisados')) {
                $table->dropColumn('items_revisados');
            }
        });
    }
}
