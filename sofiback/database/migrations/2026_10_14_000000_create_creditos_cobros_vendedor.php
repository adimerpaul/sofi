<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el vendedor cobra en la calle a una deuda. No descuenta nada: queda
 * PENDIENTE hasta que cobranzas lo confirma (recien ahi se crea el abono en
 * creditos_abonos) o lo rechaza con un motivo.
 */
class CreateCreditosCobrosVendedor extends Migration
{
    public function up()
    {
        Schema::create('creditos_cobros_vendedor', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('origen', 15);
            $table->unsignedBigInteger('deuda_id');
            $table->integer('cliente_id')->index();
            $table->decimal('monto', 12, 2);
            $table->string('referencia', 100)->nullable();
            $table->integer('user_id');
            $table->uuid('solicitud_id')->unique();
            // PENDIENTE, CONFIRMADO o RECHAZADO.
            $table->string('estado', 15)->default('PENDIENTE')->index();
            $table->unsignedBigInteger('abono_id')->nullable();
            $table->integer('revisado_por')->nullable();
            $table->timestamp('revisado_at')->nullable();
            $table->string('motivo_rechazo', 150)->nullable();
            $table->timestamps();
            $table->index(['origen', 'deuda_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('creditos_cobros_vendedor');
    }
}
