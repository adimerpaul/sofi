<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pedido_zonas')) {
            Schema::create('pedido_zonas', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 80)->unique();
                $table->string('color', 20)->default('#607d8b');
                $table->unsignedInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'pedido_zona_id')) {
                $table->foreignId('pedido_zona_id')
                    ->nullable()
                    ->after('cliente_id')
                    ->constrained('pedido_zonas')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('pedidos', 'usuario_camion_id')) {
                $table->foreignId('usuario_camion_id')
                    ->nullable()
                    ->after('pedido_zona_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'usuario_camion_id')) {
                $table->dropConstrainedForeignId('usuario_camion_id');
            }
            if (Schema::hasColumn('pedidos', 'pedido_zona_id')) {
                $table->dropConstrainedForeignId('pedido_zona_id');
            }
        });

        Schema::dropIfExists('pedido_zonas');
    }
};

