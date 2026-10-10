<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quien anulo un comprobante y por que.
 *
 * El motivo de Impuestos es uno de cuatro fijos y no explica nada ("factura
 * mal emitida"); en la oficina se necesita saber quien lo hizo y, si lo
 * dejo escrito, la razon de verdad. La observacion es opcional.
 */
class AddAnuladoPorToFacturas extends Migration
{
    public function up()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->integer('anulado_por')->nullable()->after('anulado_at');
            $table->string('anulacion_observacion', 255)->nullable()->after('motivo_anulacion');
        });
    }

    public function down()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['anulado_por', 'anulacion_observacion']);
        });
    }
}
