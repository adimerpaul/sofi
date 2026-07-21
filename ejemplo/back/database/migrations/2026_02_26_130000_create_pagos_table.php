<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas');
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->foreignId('user_id')->constrained('users');
            $table->string('tipo_pago', 20)->default('CONTADO'); // CONTADO | CREDITO
            $table->string('metodo_pago', 30)->default('EFECTIVO'); // EFECTIVO | QR | TRANSFERENCIA | OTRO
            $table->decimal('monto', 12, 2);
            $table->dateTime('fecha_hora');
            $table->string('observacion', 600)->nullable();
            $table->decimal('latitud', 11, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};

