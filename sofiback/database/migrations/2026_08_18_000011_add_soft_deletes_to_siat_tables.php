<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrado logico en las tablas del modulo de Impuestos.
 *
 * Un CUIS o un CUFD que se dio de baja igual respalda facturas ya emitidas: si
 * manana Impuestos observa una, hay que poder mostrar con que codigo se firmo.
 * Por eso se marca y no se borra.
 */
class AddSoftDeletesToSiatTables extends Migration
{
    private const TABLAS = ['siat_configuraciones', 'siat_cuis', 'siat_cufds'];

    public function up()
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down()
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
}
