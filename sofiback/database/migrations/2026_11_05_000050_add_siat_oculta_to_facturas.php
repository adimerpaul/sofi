<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca para sacar una factura del control de envio al SIAT de Facturacion.
 *
 * Hay facturas que ya no se van a poder mandar (por ejemplo, fuera del plazo
 * de contingencia): se ocultan del aviso y del filtro "Faltan enviar" para que
 * no tapen las que si hay que mandar hoy, hasta decidir que hacer con ellas.
 * No cambia nada mas de la factura: sigue en la grilla y en los reportes.
 */
class AddSiatOcultaToFacturas extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('facturas', 'siat_oculta')) {
            return;
        }

        Schema::table('facturas', function (Blueprint $table) {
            $table->boolean('siat_oculta')->default(false)->after('fecha_envio_siat');
            $table->string('siat_oculta_motivo', 255)->nullable()->after('siat_oculta');
            $table->integer('siat_oculta_por')->nullable()->after('siat_oculta_motivo');   // personal.CodAut
            $table->dateTime('siat_oculta_at')->nullable()->after('siat_oculta_por');
        });
    }

    public function down()
    {
        if (Schema::hasColumn('facturas', 'siat_oculta')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->dropColumn(['siat_oculta', 'siat_oculta_motivo', 'siat_oculta_por', 'siat_oculta_at']);
            });
        }
    }
}
