<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retorno parcial: el cliente recibe el pedido pero devuelve algunos items.
 *
 * Un comprobante emitido no se corrige: la factura porque el SIAT no lo
 * permite, y el voucher porque se decidio tratarlo igual para que las dos
 * vias dejen el mismo rastro. Entonces se anula el original y se emite uno
 * nuevo con lo que de verdad quedo en la puerta.
 *
 * De ahi las tres columnas:
 * - factura_origen_id: de cual salio, para poder seguir la cadena.
 * - confirmado_camion / entregado_camion: el comprobante nuevo nace con el
 *   reparto ya hecho, no vuelve a salir en la lista del caminero.
 */
class AddRetornoParcialToFacturas extends Migration
{
    public function up()
    {
        Schema::table('facturas', function (Blueprint $table) {
            if (!Schema::hasColumn('facturas', 'factura_origen_id')) {
                $table->unsignedBigInteger('factura_origen_id')->nullable()->after('pedido_tipo');
            }
            if (!Schema::hasColumn('facturas', 'confirmado_camion')) {
                $table->boolean('confirmado_camion')->default(false)->after('factura_origen_id');
            }
            if (!Schema::hasColumn('facturas', 'entregado_camion')) {
                $table->boolean('entregado_camion')->default(false)->after('confirmado_camion');
            }
        });
    }

    public function down()
    {
        Schema::table('facturas', function (Blueprint $table) {
            foreach (['entregado_camion', 'confirmado_camion', 'factura_origen_id'] as $columna) {
                if (Schema::hasColumn('facturas', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
}
