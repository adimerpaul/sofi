<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (!Schema::hasColumn('ventas', 'facturado')) {
                $table->boolean('facturado')->default(false)->after('tipo_pago');
            }
            if (!Schema::hasColumn('ventas', 'factura_estado')) {
                $table->string('factura_estado', 20)->default('SIN_GESTION')->after('facturado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'factura_estado')) {
                $table->dropColumn('factura_estado');
            }
            if (Schema::hasColumn('ventas', 'facturado')) {
                $table->dropColumn('facturado');
            }
        });
    }
};

