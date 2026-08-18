<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El SIAT tiene dos direcciones distintas y se usaban de sitios distintos:
 *
 *   url_siat  -> servicios (CUIS, CUFD, envio de facturas). siatrest...
 *   url_siat2 -> portal publico; es la que va en el QR de la factura impresa.
 *
 * La segunda vivia en config/siat.php y la primera en la tabla. Se juntan aca
 * para que las dos se editen desde la pantalla de Impuestos.
 *
 * El rename va en SQL crudo a proposito: renombrar con Blueprint necesita
 * doctrine/dbal y este proyecto no lo tiene instalado.
 */
class SplitSiatUrls extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('siat_configuraciones', 'url_produccion')) {
            DB::statement('ALTER TABLE siat_configuraciones CHANGE url_produccion url_siat VARCHAR(200) NOT NULL DEFAULT "https://siatrest.impuestos.gob.bo/v2/"');
        }

        if (!Schema::hasColumn('siat_configuraciones', 'url_siat2')) {
            Schema::table('siat_configuraciones', function (Blueprint $table) {
                $table->string('url_siat2', 200)->default('https://siat.impuestos.gob.bo/')->after('url_siat');
            });
        }
    }

    public function down()
    {
        Schema::table('siat_configuraciones', function (Blueprint $table) {
            $table->dropColumn('url_siat2');
        });

        DB::statement('ALTER TABLE siat_configuraciones CHANGE url_siat url_produccion VARCHAR(200) NOT NULL DEFAULT "https://siatrest.impuestos.gob.bo/v2/"');
    }
}
