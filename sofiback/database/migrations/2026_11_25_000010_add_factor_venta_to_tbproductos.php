<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Factor de venta aproximado por unidad pedida.
 *
 * El preventista pide en unidades (1 tocino de 150 g, 1 frial) pero el
 * producto se cobra por kilo: el subtotal del pedido sale de
 * cantidad x factorVenta x precio, para que el monto de la visita, Mis pedidos
 * y Pedidos totales se acerque a lo que realmente se va a vender.
 *
 * Arranca en 0 en todos los productos y en 0 el pedido sigue multiplicando
 * cantidad x precio como siempre. Para probar se cargan dos productos:
 * 521802 TOCINO DE CERDO (1 = 0.150 kg) y 500107 FRIAL MEDIANO (1 = 2.51 kg).
 *
 * La columna se agrega solo si falta, igual que precioAprox.
 */
class AddFactorVentaToTbproductos extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('tbproductos', 'factorVenta')) {
            Schema::table('tbproductos', function (Blueprint $table) {
                $table->decimal('factorVenta', 10, 3)->default(0)->after('precioAprox');
            });
        }

        // tbproductos guarda codigos con espacios al final: se compara con TRIM.
        DB::table('tbproductos')->whereRaw('TRIM(cod_prod) = ?', ['521802'])->update(['factorVenta' => 0.150]);
        DB::table('tbproductos')->whereRaw('TRIM(cod_prod) = ?', ['500107'])->update(['factorVenta' => 2.51]);
    }

    public function down()
    {
        if (!Schema::hasColumn('tbproductos', 'factorVenta')) {
            return;
        }

        Schema::table('tbproductos', function (Blueprint $table) {
            $table->dropColumn('factorVenta');
        });
    }
}
