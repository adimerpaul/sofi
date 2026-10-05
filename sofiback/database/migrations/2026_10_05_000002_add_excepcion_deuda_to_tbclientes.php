<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * excepcion_deuda: el cliente nunca se bloquea por deuda (puede pedir aunque deba mucho).
 * Reemplaza la lista de CI que estaba fija en el bloqueo; esos clientes quedan marcados aca.
 */
class AddExcepcionDeudaToTbclientes extends Migration
{
    private $sinBloqueo = ['7308976010','4041584010','5722359015','7903071014','7313393','2763010019','387115028',
        '7279536013','6656467','2773242015','3509547','5720977','7205489','3501059017','3544875019','2762953013',
        '4034692','8560810','3513987','168266022','341104028','5068381','4525672011','370194024','8025247'];

    public function up()
    {
        if (!Schema::hasColumn('tbclientes', 'excepcion_deuda')) {
            Schema::table('tbclientes', function (Blueprint $table) {
                $table->tinyInteger('excepcion_deuda')->default(0);
            });
        }
        DB::table('tbclientes')->whereIn(DB::raw('TRIM(Id)'), $this->sinBloqueo)->update(['excepcion_deuda' => 1]);
    }

    public function down()
    {
        Schema::table('tbclientes', function (Blueprint $table) {
            $table->dropColumn('excepcion_deuda');
        });
    }
}
