<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            if (!Schema::hasColumn('pagos', 'considerar_en_cobranza')) {
                $table->boolean('considerar_en_cobranza')->default(true)->after('observacion');
            }
            if (!Schema::hasColumn('pagos', 'nro_pago')) {
                $table->string('nro_pago', 120)->nullable()->after('considerar_en_cobranza');
            }
            if (!Schema::hasColumn('pagos', 'comprobante_path')) {
                $table->string('comprobante_path', 500)->nullable()->after('nro_pago');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            if (Schema::hasColumn('pagos', 'comprobante_path')) {
                $table->dropColumn('comprobante_path');
            }
            if (Schema::hasColumn('pagos', 'nro_pago')) {
                $table->dropColumn('nro_pago');
            }
            if (Schema::hasColumn('pagos', 'considerar_en_cobranza')) {
                $table->dropColumn('considerar_en_cobranza');
            }
        });
    }
};

