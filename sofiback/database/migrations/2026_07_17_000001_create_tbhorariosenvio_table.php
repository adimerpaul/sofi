<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTbhorariosenvioTable extends Migration
{
    public function up()
    {
        Schema::create('tbhorariosenvio', function (Blueprint $table) {
            $table->id();
            $table->time('hora');
            // dias de la semana en formato ISO separados por coma: 1=lunes ... 7=domingo
            $table->string('dias', 20)->default('1,2,3,4,5,6,7');
            $table->boolean('activo')->default(1);
            $table->date('ultima_ejecucion')->nullable();
            $table->integer('pedidos_enviados')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tbhorariosenvio');
    }
}
