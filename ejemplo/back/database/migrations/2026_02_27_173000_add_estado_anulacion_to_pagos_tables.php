<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            if (!Schema::hasColumn('pagos', 'estado')) {
                $table->string('estado', 20)->default('ACTIVO')->after('monto');
            }
            if (!Schema::hasColumn('pagos', 'anulado_user_id')) {
                $table->foreignId('anulado_user_id')->nullable()->after('user_id')->constrained('users');
            }
            if (!Schema::hasColumn('pagos', 'anulado_at')) {
                $table->dateTime('anulado_at')->nullable()->after('fecha_hora');
            }
        });

        Schema::table('cobranzas_deuda_pagos', function (Blueprint $table) {
            if (!Schema::hasColumn('cobranzas_deuda_pagos', 'estado')) {
                $table->string('estado', 20)->default('ACTIVO')->after('monto');
            }
            if (!Schema::hasColumn('cobranzas_deuda_pagos', 'anulado_user_id')) {
                $table->foreignId('anulado_user_id')->nullable()->after('user_id')->constrained('users');
            }
            if (!Schema::hasColumn('cobranzas_deuda_pagos', 'anulado_at')) {
                $table->dateTime('anulado_at')->nullable()->after('fecha_hora');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            if (Schema::hasColumn('pagos', 'anulado_user_id')) {
                $table->dropConstrainedForeignId('anulado_user_id');
            }
            if (Schema::hasColumn('pagos', 'anulado_at')) {
                $table->dropColumn('anulado_at');
            }
            if (Schema::hasColumn('pagos', 'estado')) {
                $table->dropColumn('estado');
            }
        });

        Schema::table('cobranzas_deuda_pagos', function (Blueprint $table) {
            if (Schema::hasColumn('cobranzas_deuda_pagos', 'anulado_user_id')) {
                $table->dropConstrainedForeignId('anulado_user_id');
            }
            if (Schema::hasColumn('cobranzas_deuda_pagos', 'anulado_at')) {
                $table->dropColumn('anulado_at');
            }
            if (Schema::hasColumn('cobranzas_deuda_pagos', 'estado')) {
                $table->dropColumn('estado');
            }
        });
    }
};

