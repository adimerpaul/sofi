<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retorno parcial marcado por el caminero en la puerta.
 *
 * El comprobante no se toca: la entrega queda en estado RETORNO PARCIAL y en
 * esta columna (JSON) va producto por producto lo que salio y lo que el
 * cliente se quedo. Caja lo lee despues para editar el comprobante (anular y
 * emitir otro con lo entregado).
 */
class AddRetornoDetalleToEntregas extends Migration
{
    public function up()
    {
        Schema::table('entregas', function (Blueprint $table) {
            if (!Schema::hasColumn('entregas', 'retorno_detalle')) {
                $table->text('retorno_detalle')->nullable()->after('observacion');
            }
        });
    }

    public function down()
    {
        Schema::table('entregas', function (Blueprint $table) {
            if (Schema::hasColumn('entregas', 'retorno_detalle')) {
                $table->dropColumn('retorno_detalle');
            }
        });
    }
}
