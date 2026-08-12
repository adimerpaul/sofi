<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'despacho_estado')) {
                $table->string('despacho_estado', 20)->default('PENDIENTE')->after('auxiliar_estado');
            }
            if (!Schema::hasColumn('pedidos', 'entrega_at')) {
                $table->dateTime('entrega_at')->nullable()->after('despacho_estado');
            }
            if (!Schema::hasColumn('pedidos', 'despachador_user_id')) {
                $table->foreignId('despachador_user_id')->nullable()->after('entrega_at')->constrained('users');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'despachador_user_id')) {
                $table->dropConstrainedForeignId('despachador_user_id');
            }
            if (Schema::hasColumn('pedidos', 'entrega_at')) {
                $table->dropColumn('entrega_at');
            }
            if (Schema::hasColumn('pedidos', 'despacho_estado')) {
                $table->dropColumn('despacho_estado');
            }
        });
    }
};

