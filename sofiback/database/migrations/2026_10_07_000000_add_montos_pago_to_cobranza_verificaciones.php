<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuanto de lo verificado por cobranzas entro en efectivo y cuanto por QR.
 *
 * monto_verificado sigue siendo el total (efectivo + QR). Las verificaciones
 * que ya existen quedan en null: para esas el reparto se deduce de como se
 * pago el comprobante (ver CobranzaVerificacionController::filas).
 */
class AddMontosPagoToCobranzaVerificaciones extends Migration
{
    public function up()
    {
        Schema::table('cobranza_verificaciones', function (Blueprint $table) {
            $table->decimal('monto_efectivo', 12, 2)->nullable()->after('monto_verificado');
            $table->decimal('monto_qr', 12, 2)->nullable()->after('monto_efectivo');
        });
    }

    public function down()
    {
        Schema::table('cobranza_verificaciones', function (Blueprint $table) {
            $table->dropColumn(['monto_efectivo', 'monto_qr']);
        });
    }
}
