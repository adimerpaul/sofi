<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cantidad por caja: cuantas unidades (pollos, friales) trae una caja.
 *
 * Arranca en 0 en todos los productos (0 = sin dato). Se cargan los valores
 * de la planilla de pollo: los friales y el pollo sin menudo van de a 12 por
 * caja, el pollo a la brasa de a 16 (el de 1.8 de a 18).
 *
 * La columna se agrega solo si falta, igual que precioAprox y factorVenta.
 */
class AddCantidadCajaToTbproductos extends Migration
{
    private const CANTIDADES = [
        '500104' => 12, // FRIAL CHICO 1.5 - 1.7
        '500105' => 12, // FRIAL CHICO 1.7 - 1.9
        '500106' => 12, // FRIAL MEDIANO 1.9 - 2.2
        '500107' => 12, // FRIAL MEDIANO 2.2 - 2.5
        '500108' => 12, // FRIAL GRANDE 2.6 - 2.7
        '500109' => 12, // FRIAL GRANDE 2.8 - 2.9
        '501005' => 18, // POLLO BRASA 1.8
        '501006' => 16, // POLLO BRASA 2
        '501007' => 16, // POLLO BRASA 2.3
        '501008' => 16, // POLLO BRASA 2.6
        '501009' => 16, // POLLO BRASA 2.8
        '501115' => 12, // POLLO CHICO S/MENUDO 1,7 - 1,9
        '501116' => 12, // POLLO MEDIANO S/MENUDO 1,9 - 2,2
        '501117' => 12, // POLLO MEDIANO S/MENUDO 2,2 - 2,5
        '501118' => 12, // POLLO GRANDE S/MENUDO 2,6 - 2,7
        '501119' => 12, // POLLO GRANDE S/MENUDO 2,8 - 2,9
        '511116' => 12, // POLLO BRASA CON COGOTE 1,8
        '501114' => 12, // POLLO CHICO S/MENUDO 1.5-1.7
    ];

    public function up()
    {
        if (!Schema::hasColumn('tbproductos', 'cantidadCaja')) {
            Schema::table('tbproductos', function (Blueprint $table) {
                $table->decimal('cantidadCaja', 10, 2)->default(0)->after('factorVenta');
            });
        }

        // tbproductos guarda codigos con espacios al final: se compara con TRIM.
        // PHP vuelve entero la clave '500104'; como numero MySQL compararia
        // mal contra codigos como '500106C', por eso va como texto.
        foreach (self::CANTIDADES as $cod => $cantidad) {
            DB::table('tbproductos')->whereRaw('TRIM(cod_prod) = ?', [(string) $cod])->update(['cantidadCaja' => $cantidad]);
        }
    }

    public function down()
    {
        if (!Schema::hasColumn('tbproductos', 'cantidadCaja')) {
            return;
        }

        Schema::table('tbproductos', function (Blueprint $table) {
            $table->dropColumn('cantidadCaja');
        });
    }
}
