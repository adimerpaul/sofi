<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impuesto_fallas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 50)->default('CUFD');
            $table->text('mensaje');
            $table->json('detalle')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente|resuelto|oculto
            $table->dateTime('fecha_evento');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tipo', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impuesto_fallas');
    }
};
