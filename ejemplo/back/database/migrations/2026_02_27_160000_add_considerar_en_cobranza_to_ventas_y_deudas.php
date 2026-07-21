<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (!Schema::hasColumn('ventas', 'considerar_en_cobranza')) {
                $table->boolean('considerar_en_cobranza')->default(true)->after('tipo_pago');
            }
        });

        Schema::table('cobranzas_deudas', function (Blueprint $table) {
            if (!Schema::hasColumn('cobranzas_deudas', 'considerar_en_cobranza')) {
                $table->boolean('considerar_en_cobranza')->default(true)->after('tolerancia_centavos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'considerar_en_cobranza')) {
                $table->dropColumn('considerar_en_cobranza');
            }
        });

        Schema::table('cobranzas_deudas', function (Blueprint $table) {
            if (Schema::hasColumn('cobranzas_deudas', 'considerar_en_cobranza')) {
                $table->dropColumn('considerar_en_cobranza');
            }
        });
    }
};

