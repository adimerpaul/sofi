<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            if (!Schema::hasColumn('compras', 'fecha_hora')) {
                $table->dateTime('fecha_hora')->nullable()->after('hora');
                $table->index('fecha_hora', 'compras_fecha_hora_idx');
            }

            if (!Schema::hasColumn('compras', 'facturado')) {
                $table->boolean('facturado')->default(false)->after('agencia');
            }

            if (!Schema::hasColumn('compras', 'foto')) {
                $table->string('foto', 255)->nullable()->after('facturado');
            }
        });

        DB::statement("\n            UPDATE compras\n            SET fecha_hora = CASE\n                WHEN fecha IS NOT NULL AND hora IS NOT NULL THEN TIMESTAMP(fecha, hora)\n                WHEN fecha IS NOT NULL THEN TIMESTAMP(fecha, '00:00:00')\n                ELSE fecha_hora\n            END\n            WHERE fecha_hora IS NULL\n        ");
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            if (Schema::hasColumn('compras', 'foto')) {
                $table->dropColumn('foto');
            }

            if (Schema::hasColumn('compras', 'facturado')) {
                $table->dropColumn('facturado');
            }

            if (Schema::hasColumn('compras', 'fecha_hora')) {
                $table->dropIndex('compras_fecha_hora_idx');
                $table->dropColumn('fecha_hora');
            }
        });
    }
};
