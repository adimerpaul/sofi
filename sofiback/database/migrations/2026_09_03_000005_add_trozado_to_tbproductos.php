<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de producto trozado.
 *
 * Lo trozado (el pollo que se entrega en piezas) no se cuenta en la boleta: en
 * el papel la cantidad no dice nada util, asi que la columna Cant sale con un
 * guion en vez del numero.
 *
 * Va como varchar y no como boolean porque tbproductos es una tabla legada que
 * ya usa texto para sus banderas (stock, Imprime): se guarda 'SI' o 'NO'.
 */
class AddTrozadoToTbproductos extends Migration
{
    public function up()
    {
        Schema::table('tbproductos', function (Blueprint $table) {
            $table->string('trozado', 2)->nullable()->default('NO')->after('imagen');
        });
    }

    public function down()
    {
        Schema::table('tbproductos', function (Blueprint $table) {
            $table->dropColumn('trozado');
        });
    }
}
