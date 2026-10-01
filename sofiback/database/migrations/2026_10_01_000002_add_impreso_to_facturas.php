<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rastro de impresion del comprobante.
 *
 * Caja no tenia forma de saber si una venta ya salio en papel y se volvia a
 * imprimir (o se quedaba sin imprimir). Se marca cuando el PDF se pide para
 * la impresora, no cuando solo se descarga.
 * - impreso_veces: cuantas veces se mando a imprimir.
 * - impreso_at / impreso_por: la ultima vez y quien.
 */
class AddImpresoToFacturas extends Migration
{
    public function up()
    {
        Schema::table('facturas', function (Blueprint $table) {
            if (!Schema::hasColumn('facturas', 'impreso_veces')) {
                $table->unsignedInteger('impreso_veces')->default(0);
            }
            if (!Schema::hasColumn('facturas', 'impreso_at')) {
                $table->dateTime('impreso_at')->nullable();
            }
            if (!Schema::hasColumn('facturas', 'impreso_por')) {
                $table->string('impreso_por', 100)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('facturas', function (Blueprint $table) {
            foreach (['impreso_por', 'impreso_at', 'impreso_veces'] as $columna) {
                if (Schema::hasColumn('facturas', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
}
