<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'facturado')) {
                $table->boolean('facturado')->default(false)->after('tipo_pago');
            }
            if (!Schema::hasColumn('pedidos', 'contiene_normal')) {
                $table->boolean('contiene_normal')->default(false)->after('tipo_pedido');
            }
            if (!Schema::hasColumn('pedidos', 'contiene_res')) {
                $table->boolean('contiene_res')->default(false)->after('contiene_normal');
            }
            if (!Schema::hasColumn('pedidos', 'contiene_cerdo')) {
                $table->boolean('contiene_cerdo')->default(false)->after('contiene_res');
            }
            if (!Schema::hasColumn('pedidos', 'contiene_pollo')) {
                $table->boolean('contiene_pollo')->default(false)->after('contiene_cerdo');
            }
        });

        Schema::table('pedido_detalles', function (Blueprint $table) {
            if (!Schema::hasColumn('pedido_detalles', 'observacion_detalle')) {
                $table->string('observacion_detalle', 600)->nullable()->after('total');
            }
            if (!Schema::hasColumn('pedido_detalles', 'detalle_extra')) {
                $table->json('detalle_extra')->nullable()->after('observacion_detalle');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedido_detalles', function (Blueprint $table) {
            if (Schema::hasColumn('pedido_detalles', 'detalle_extra')) {
                $table->dropColumn('detalle_extra');
            }
            if (Schema::hasColumn('pedido_detalles', 'observacion_detalle')) {
                $table->dropColumn('observacion_detalle');
            }
        });

        Schema::table('pedidos', function (Blueprint $table) {
            foreach (['facturado', 'contiene_normal', 'contiene_res', 'contiene_cerdo', 'contiene_pollo'] as $col) {
                if (Schema::hasColumn('pedidos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
