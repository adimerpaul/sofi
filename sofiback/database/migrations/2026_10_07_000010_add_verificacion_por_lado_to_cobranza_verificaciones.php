<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El efectivo y el QR de un comprobante se verifican por separado, cada uno
 * con su tilde, quien lo verifico y cuando. 'verificado' (y su usuario y
 * hora) sigue siendo el del comprobante entero: un pago mixto queda
 * verificado cuando estan los dos lados; los demas, con cualquiera.
 *
 * Las verificaciones que ya existen pasan a sus lados: las que no tenian
 * reparto se reparten por el tipo de pago del comprobante (QR a QR, lo
 * demas a efectivo) y despues se tilda cada lado que tenga monto.
 */
class AddVerificacionPorLadoToCobranzaVerificaciones extends Migration
{
    public function up()
    {
        Schema::table('cobranza_verificaciones', function (Blueprint $table) {
            $table->boolean('efectivo_ok')->default(false)->after('monto_qr');
            $table->integer('efectivo_user_id')->nullable()->after('efectivo_ok');
            $table->string('efectivo_por', 150)->nullable()->after('efectivo_user_id');
            $table->dateTime('efectivo_en')->nullable()->after('efectivo_por');
            $table->boolean('qr_ok')->default(false)->after('efectivo_en');
            $table->integer('qr_user_id')->nullable()->after('qr_ok');
            $table->string('qr_por', 150)->nullable()->after('qr_user_id');
            $table->dateTime('qr_en')->nullable()->after('qr_por');
        });

        DB::table('cobranza_verificaciones as v')->join('facturas as f', 'f.id', '=', 'v.factura_id')
            ->whereNull('v.monto_qr')
            ->update([
                'v.monto_qr' => DB::raw("CASE WHEN f.tipo_pago = 'QR' THEN v.monto_verificado ELSE 0 END"),
                'v.monto_efectivo' => DB::raw("CASE WHEN f.tipo_pago = 'QR' THEN 0 ELSE v.monto_verificado END"),
            ]);
        DB::table('cobranza_verificaciones')->whereNull('monto_qr')
            ->update(['monto_qr' => 0, 'monto_efectivo' => DB::raw('monto_verificado')]);

        $ladoDe = function ($lado) {
            return [
                $lado . '_ok' => true,
                $lado . '_user_id' => DB::raw('user_id'),
                $lado . '_por' => DB::raw('verificado_por'),
                $lado . '_en' => DB::raw('verificado_en'),
            ];
        };
        DB::table('cobranza_verificaciones')->where('verificado', 1)->where('monto_qr', '>', 0)->update($ladoDe('qr'));
        DB::table('cobranza_verificaciones')->where('verificado', 1)
            ->where(function ($q) { $q->where('monto_efectivo', '>', 0)->orWhere('monto_qr', '<=', 0); })
            ->update($ladoDe('efectivo'));
    }

    public function down()
    {
        Schema::table('cobranza_verificaciones', function (Blueprint $table) {
            $table->dropColumn(['efectivo_ok', 'efectivo_user_id', 'efectivo_por', 'efectivo_en',
                'qr_ok', 'qr_user_id', 'qr_por', 'qr_en']);
        });
    }
}
