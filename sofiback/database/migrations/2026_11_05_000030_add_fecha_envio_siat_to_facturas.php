<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * fecha_envio_siat: fecha y hora en que el SIAT recibio de verdad la factura.
 *
 * fecha_emision es la fecha de la factura (la que va en el XML y en el papel)
 * y no se toca. Casi siempre coinciden, pero una factura que no pudo salir en
 * linea y se mando despues por evento significativo conserva su fecha_emision
 * original y aca queda registrado cuando se envio.
 *
 * Lo ya validado se rellena con fecha_emision, que en linea es el momento del envio.
 */
class AddFechaEnvioSiatToFacturas extends Migration
{
    public function up()
    {
        foreach (['facturas', 'facturas_ficticias'] as $tabla) {
            if (!Schema::hasColumn($tabla, 'fecha_envio_siat')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dateTime('fecha_envio_siat')->nullable()->after('fecha_emision');
                });
            }

            DB::table($tabla)
                ->whereNull('fecha_envio_siat')
                ->where('online', 1)
                ->whereNotNull('fecha_emision')
                ->update(['fecha_envio_siat' => DB::raw("STR_TO_DATE(LEFT(fecha_emision, 19), '%Y-%m-%dT%H:%i:%s')")]);
        }
    }

    public function down()
    {
        foreach (['facturas', 'facturas_ficticias'] as $tabla) {
            if (Schema::hasColumn($tabla, 'fecha_envio_siat')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dropColumn('fecha_envio_siat');
                });
            }
        }
    }
}
