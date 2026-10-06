<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuanto de cada abono entro en efectivo y cuanto por QR.
 *
 * Las formas de pago de un abono pasan a ser EFECTIVO, QR o MIXTO (como en
 * facturacion); en el mixto se anota cuanto fue de cada lado. Los reportes de
 * cobros suman estas dos columnas.
 *
 * Los abonos que ya existen se completan con su forma de pago: EFECTIVO va a
 * efectivo; QR y TRANSFERENCIA (que ya no se ofrece) van a QR, porque las dos
 * entran al banco.
 */
class AddMontosPagoToCreditosAbonos extends Migration
{
    public function up()
    {
        Schema::table('creditos_abonos', function (Blueprint $table) {
            $table->decimal('monto_efectivo', 12, 2)->default(0)->after('monto');
            $table->decimal('monto_qr', 12, 2)->default(0)->after('monto_efectivo');
        });

        DB::table('creditos_abonos')->where('forma_pago', 'EFECTIVO')->update(['monto_efectivo' => DB::raw('monto')]);
        DB::table('creditos_abonos')->whereIn('forma_pago', ['QR', 'TRANSFERENCIA'])->update(['monto_qr' => DB::raw('monto')]);
    }

    public function down()
    {
        Schema::table('creditos_abonos', function (Blueprint $table) {
            $table->dropColumn(['monto_efectivo', 'monto_qr']);
        });
    }
}
