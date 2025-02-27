<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRegistroAlmacensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('registro_almacenes', function (Blueprint $table) {
            $table->id();
            $table->double('cantidad',11,2)->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->unsignedBigInteger('almacen_id');
            $table->foreign('almacen_id')->references('id')->on('almacenes')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('fecha_registro');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('registro_almacenes');
    }
}
