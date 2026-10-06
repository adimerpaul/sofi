<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Numero de canasta y nota de cada comprobante en la carga del camion.
 *
 * - nro_canasta: en que canasta(s) fisica(s) va lo del cliente ("3", "3 y 4").
 *   Lo anota el caminero al cargar y lo ve al entregar, para bajar la canasta
 *   correcta sin revolver el camion.
 * - nota: observacion libre del caminero. Es aparte de "observacion", que es
 *   el motivo con el que se marca una canasta como OBSERVADA.
 *
 * Se guardan aparte del visto bueno: verificar, desmarcar u observar no las
 * tocan.
 */
class AddCanastaYNotaToCargaVerificaciones extends Migration
{
    public function up()
    {
        Schema::table('carga_verificaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('carga_verificaciones', 'nro_canasta')) {
                $table->string('nro_canasta', 30)->nullable()->after('observacion');
            }
            if (!Schema::hasColumn('carga_verificaciones', 'nota')) {
                $table->string('nota', 255)->nullable()->after('nro_canasta');
            }
        });
    }

    public function down()
    {
        Schema::table('carga_verificaciones', function (Blueprint $table) {
            foreach (['nota', 'nro_canasta'] as $columna) {
                if (Schema::hasColumn('carga_verificaciones', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
}
