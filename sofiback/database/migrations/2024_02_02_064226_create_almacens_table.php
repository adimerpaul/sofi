<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlmacensTable extends Migration
{
    public function up()
    {
        Schema::create('almacenes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('codigo_producto')->nullable();
            $table->string('producto')->nullable();
            $table->string('unidad')->nullable();
            $table->integer('saldo')->nullable();
            $table->date('registro')->nullable();
            $table->date('vencimiento')->nullable();
            $table->string('grupo')->nullable();
            $table->string('se_descargo')->nullable();
            $table->date('fecha_registro');
            $table->integer('cantidad')->nullable();
            $table->timestamps();
        });
    }
   public function down()
    {
        Schema::dropIfExists('almacenes');
    }
}
