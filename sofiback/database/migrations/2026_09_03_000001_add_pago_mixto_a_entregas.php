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
 *
 * Cada columna se agrega solo si falta, porque en produccion a veces se las
 * crea a mano y ese dump termina bajando a local.
 */
class AddPagoMixtoAEntregas extends Migration
{
    public function up()
    {
        Schema::table('entregas', function (Blueprint $tabla) {
            if (!Schema::hasColumn('entregas', 'monto_efectivo')) {
                $tabla->decimal('monto_efectivo', 12, 2)->default(0)->after('monto');
            }

            if (!Schema::hasColumn('entregas', 'monto_qr')) {
                $tabla->decimal('monto_qr', 12, 2)->default(0)->after('monto_efectivo');
            }

            if (!Schema::hasColumn('entregas', 'factura_id')) {
                $tabla->unsignedBigInteger('factura_id')->nullable()->after('comanda');
                $tabla->index('factura_id', 'entregas_factura_id_index');
            }
        });
    }

    public function down()
    {
        Schema::table('entregas', function (Blueprint $tabla) {
            if (Schema::hasColumn('entregas', 'factura_id')) {
                $tabla->dropIndex('entregas_factura_id_index');
            }

            $columnas = array_values(array_filter(
                ['monto_efectivo', 'monto_qr', 'factura_id'],
                function ($columna) {
                    return Schema::hasColumn('entregas', $columna);
                }
            ));

            if ($columnas) {
                $tabla->dropColumn($columnas);
            }
        });
    }
}
