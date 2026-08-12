<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos');
            $table->date('fecha');
            $table->time('hora');
            $table->string('tipo_visita', 30);
            $table->string('comentario', 600)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'fecha']);
            $table->index(['cliente_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas');
    }
};
