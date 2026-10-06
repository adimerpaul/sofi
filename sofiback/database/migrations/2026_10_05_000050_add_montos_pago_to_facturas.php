<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuanto se cobro en efectivo y cuanto por QR.
 *
 * Con tipo_pago MIXTO el cliente paga una parte en efectivo y otra por QR, y
 * las dos tienen que sumar el total. En EFECTIVO o QR se guarda el total en
 * su columna; en TARJETA y CREDITO quedan vacias.
 */
class AddMontosPagoToFacturas extends Migration
{
    public function up()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->decimal('monto_efectivo', 12, 2)->nullable()->after('tipo_pago');
            $table->decimal('monto_qr', 12, 2)->nullable()->after('monto_efectivo');
        });
    }

    public function down()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['monto_efectivo', 'monto_qr']);
        });
    }
}
