<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'auxiliar_estado')) {
                $table->string('auxiliar_estado', 25)->default('PENDIENTE')->after('estado');
            }
            if (!Schema::hasColumn('pedidos', 'auxiliar_observacion')) {
                $table->string('auxiliar_observacion', 600)->nullable()->after('auxiliar_estado');
            }
            if (!Schema::hasColumn('pedidos', 'auxiliar_user_id')) {
                $table->foreignId('auxiliar_user_id')->nullable()->after('usuario_camion_id')->constrained('users');
            }
            if (!Schema::hasColumn('pedidos', 'auxiliar_hecho_at')) {
                $table->timestamp('auxiliar_hecho_at')->nullable()->after('auxiliar_user_id');
            }
            if (!Schema::hasColumn('pedidos', 'venta_generada')) {
                $table->boolean('venta_generada')->default(false)->after('auxiliar_hecho_at');
            }
            if (!Schema::hasColumn('pedidos', 'venta_id')) {
                $table->foreignId('venta_id')->nullable()->after('venta_generada')->constrained('ventas');
            }
        });

        Schema::table('ventas', function (Blueprint $table) {
            if (!Schema::hasColumn('ventas', 'pedido_id')) {
                $table->foreignId('pedido_id')->nullable()->after('cliente_id')->constrained('pedidos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'pedido_id')) {
                $table->dropConstrainedForeignId('pedido_id');
            }
        });

        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'venta_id')) {
                $table->dropConstrainedForeignId('venta_id');
            }
            if (Schema::hasColumn('pedidos', 'venta_generada')) {
                $table->dropColumn('venta_generada');
            }
            if (Schema::hasColumn('pedidos', 'auxiliar_hecho_at')) {
                $table->dropColumn('auxiliar_hecho_at');
            }
            if (Schema::hasColumn('pedidos', 'auxiliar_user_id')) {
                $table->dropConstrainedForeignId('auxiliar_user_id');
            }
            if (Schema::hasColumn('pedidos', 'auxiliar_observacion')) {
                $table->dropColumn('auxiliar_observacion');
            }
            if (Schema::hasColumn('pedidos', 'auxiliar_estado')) {
                $table->dropColumn('auxiliar_estado');
            }
        });
    }
};

