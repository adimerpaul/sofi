<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'cliente_id')) {
                $table->foreignId('cliente_id')->nullable()->after('user_id')->constrained('clientes');
            }
            if (!Schema::hasColumn('pedidos', 'tipo_pago')) {
                $table->string('tipo_pago', 30)->nullable()->after('estado');
            }
            if (!Schema::hasColumn('pedidos', 'tipo_pedido')) {
                $table->string('tipo_pedido', 30)->default('REALIZAR_PEDIDO')->after('tipo_pago');
            }
            if (!Schema::hasColumn('pedidos', 'comentario_visita')) {
                $table->text('comentario_visita')->nullable()->after('observaciones');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'cliente_id')) {
                $table->dropConstrainedForeignId('cliente_id');
            }
            if (Schema::hasColumn('pedidos', 'tipo_pago')) {
                $table->dropColumn('tipo_pago');
            }
            if (Schema::hasColumn('pedidos', 'tipo_pedido')) {
                $table->dropColumn('tipo_pedido');
            }
            if (Schema::hasColumn('pedidos', 'comentario_visita')) {
                $table->dropColumn('comentario_visita');
            }
        });
    }
};
