<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aca solo se factura contra el SIAT de produccion, asi que la URL del
 * ambiente piloto sobraba: quedaba en pantalla invitando a confundirse de
 * ambiente. El codigo_ambiente se conserva porque siat_cuis y siat_cufds
 * guardan con cual se pidio cada codigo.
 */
class DropUrlPilotoFromSiatConfiguraciones extends Migration
{
    public function up()
    {
        // En una instalacion nueva la columna ya no se crea: esta migracion
        // solo limpia las bases que alcanzaron a tenerla.
        if (!Schema::hasColumn('siat_configuraciones', 'url_piloto')) {
            return;
        }

        Schema::table('siat_configuraciones', function (Blueprint $table) {
            $table->dropColumn('url_piloto');
        });
    }

    public function down()
    {
        Schema::table('siat_configuraciones', function (Blueprint $table) {
            $table->string('url_piloto', 200)->default('https://pilotosiatservicios.impuestos.gob.bo/v2/');
        });
    }
}
