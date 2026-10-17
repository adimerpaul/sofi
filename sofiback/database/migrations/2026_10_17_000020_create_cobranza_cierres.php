<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre de cobranzas por planilla: un camion en un dia de entrega. Se
 * registra al imprimir el cierre con ese camion elegido; el reporte del dia
 * va creciendo con cada planilla que se cierra. Desde ahi lo ya verificado
 * de ese camion ese dia queda fijo (no se desmarca ni se le cambia el monto)
 * hasta que alguien con el permiso rectificarPlanilla lo reabra.
 */
class CreateCobranzaCierres extends Migration
{
    public function up()
    {
        Schema::create('cobranza_cierres', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Dia de entrega (facturas.fecha_entrega) y camion de la planilla.
            $table->date('fecha');
            $table->string('placa', 50);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('cerrado_por', 120)->nullable();
            $table->timestamps();

            $table->unique(['fecha', 'placa']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('cobranza_cierres');
    }
}
