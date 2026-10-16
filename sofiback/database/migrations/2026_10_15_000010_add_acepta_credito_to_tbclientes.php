<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * acepta_credito: permiso para pedir a credito. Sin marcar, el pedido de
 * Visita solo ofrece CONTADO y BOLETA ANTERIOR, y el backend rechaza CREDITO.
 *
 * Para no cortarle el credito de golpe a quien ya lo usa, quedan marcados los
 * clientes con algun pedido a CREDITO en los ultimos 90 dias y los dos
 * clientes de cambio/bonificacion, que siempre piden a credito.
 */
class AddAceptaCreditoToTbclientes extends Migration
{
    private $siempreCredito = ['61839000', '0023456'];

    public function up()
    {
        if (!Schema::hasColumn('tbclientes', 'acepta_credito')) {
            Schema::table('tbclientes', function (Blueprint $table) {
                $table->tinyInteger('acepta_credito')->default(0);
            });
        }

        DB::statement("
            UPDATE tbclientes SET acepta_credito = 1
            WHERE Cod_Aut IN (
                SELECT idCli FROM (
                    SELECT DISTINCT idCli FROM tbpedidos
                    WHERE pago = 'CREDITO' AND fecha >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                ) t
            )
        ");
        DB::table('tbclientes')->whereIn(DB::raw('TRIM(Id)'), $this->siempreCredito)->update(['acepta_credito' => 1]);
    }

    public function down()
    {
        Schema::table('tbclientes', function (Blueprint $table) {
            $table->dropColumn('acepta_credito');
        });
    }
}
