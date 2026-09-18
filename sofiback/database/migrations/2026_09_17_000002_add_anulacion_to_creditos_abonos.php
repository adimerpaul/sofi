<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anulacion de un abono cobrado por equivocacion.
 *
 * El abono no se borra: la plata que el cliente entrego quedo anotada y el
 * cobrador tiene que poder explicar por que se dio de baja. Se marca con
 * quien lo anulo, cuando y por que, y deja de contar para el saldo de la
 * deuda; en el historial sigue a la vista, tachado.
 */
class AddAnulacionToCreditosAbonos extends Migration
{
    public function up()
    {
        Schema::table('creditos_abonos', function (Blueprint $table) {
            $table->timestamp('anulado_at')->nullable()->after('updated_at');
            $table->integer('anulado_por')->nullable()->after('anulado_at');
            $table->string('motivo_anulacion', 150)->nullable()->after('anulado_por');
        });
    }

    public function down()
    {
        Schema::table('creditos_abonos', function (Blueprint $table) {
            $table->dropColumn(['anulado_at', 'anulado_por', 'motivo_anulacion']);
        });
    }
}
