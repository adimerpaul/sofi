<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranzas_deudas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->foreignId('user_id')->constrained('users');
            $table->string('nombre_cliente', 255)->nullable();
            $table->string('ci_nit', 100)->nullable();
            $table->string('telefono', 100)->nullable();
            $table->string('direccion', 500)->nullable();
            $table->decimal('monto_total', 12, 2);
            $table->decimal('tolerancia_centavos', 12, 2)->default(0.99);
            $table->date('fecha')->nullable();
            $table->string('estado', 30)->default('ACTIVA');
            $table->string('observacion', 600)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranzas_deudas');
    }
};

