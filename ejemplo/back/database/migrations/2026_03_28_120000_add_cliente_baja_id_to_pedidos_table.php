<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'cliente_baja_id')) {
                $table->foreignId('cliente_baja_id')
                    ->nullable()
                    ->after('cliente_id')
                    ->constrained('clientes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'cliente_baja_id')) {
                $table->dropConstrainedForeignId('cliente_baja_id');
            }
        });
    }
};
