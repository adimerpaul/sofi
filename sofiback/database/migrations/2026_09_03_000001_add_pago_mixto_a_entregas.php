<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El caminero puede cobrar una nota parte en efectivo y parte por QR. Hasta
 * ahora entregas.tipago era un solo texto, asi que un cobro mixto no se podia
 * representar ni cuadrar en el reporte del dia: se guarda cuanto entro por
 * cada via, y tipago pasa a decir MIXTO.
 *
 * Tambien se ancla la entrega al comprobante que la origino (factura_id), que
 * es de donde el caminero saca hoy sus pedidos.
 */
class AddPagoMixtoAEntregas extends Migration
{
    public function up()
    {
        Schema::table('entregas', function (Blueprint $tabla) {
            $tabla->decimal('monto_efectivo', 12, 2)->default(0)->after('monto');
            $tabla->decimal('monto_qr', 12, 2)->default(0)->after('monto_efectivo');
            $tabla->unsignedBigInteger('factura_id')->nullable()->after('comanda');
            $tabla->index('factura_id', 'entregas_factura_id_index');
        });
    }

    public function down()
    {
        Schema::table('entregas', function (Blueprint $tabla) {
            $tabla->dropIndex('entregas_factura_id_index');
            $tabla->dropColumn(['monto_efectivo', 'monto_qr', 'factura_id']);
        });
    }
}
