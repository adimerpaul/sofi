<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranzas_deuda_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deuda_id')->constrained('cobranzas_deudas');
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('monto', 12, 2);
            $table->dateTime('fecha_hora');
            $table->string('metodo_pago', 30)->default('EFECTIVO');
            $table->boolean('considerar_en_cobranza')->default(true);
            $table->string('nro_pago', 120)->nullable();
            $table->string('observacion', 600)->nullable();
            $table->string('comprobante_path', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranzas_deuda_pagos');
    }
};

