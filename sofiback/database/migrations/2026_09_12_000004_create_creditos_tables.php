<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCreditosTables extends Migration
{
    public function up()
    {
        Schema::create('creditos_manuales', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('cliente_id')->index();
            $table->date('fecha');
            $table->string('concepto', 255);
            $table->decimal('monto', 12, 2);
            $table->integer('user_id');
            $table->uuid('solicitud_id')->unique();
            $table->timestamps();
        });
        Schema::create('creditos_abonos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('origen', 15);
            $table->unsignedBigInteger('deuda_id');
            $table->integer('cliente_id')->nullable()->index();
            $table->decimal('monto', 12, 2);
            $table->string('forma_pago', 20);
            $table->string('referencia', 100)->nullable();
            $table->integer('user_id');
            $table->uuid('solicitud_id')->unique();
            $table->timestamps();
            $table->index(['origen', 'deuda_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('creditos_abonos');
        Schema::dropIfExists('creditos_manuales');
    }
}
