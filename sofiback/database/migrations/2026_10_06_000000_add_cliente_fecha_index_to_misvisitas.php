<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddClienteFechaIndexToMisvisitas extends Migration
{
    public function up()
    {
        Schema::table('misvisitas', function (Blueprint $table) {
            $table->index(['cliente_id', 'fecha'], 'misvisitas_cliente_fecha_index');
        });
    }

    public function down()
    {
        Schema::table('misvisitas', function (Blueprint $table) {
            $table->dropIndex('misvisitas_cliente_fecha_index');
        });
    }
}
