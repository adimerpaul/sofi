<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (!Schema::hasColumn('ventas', 'verificado')) {
                $table->boolean('verificado')->default(false)->after('facturado');
            }
            if (!Schema::hasColumn('ventas', 'verificado_user_id')) {
                $table->foreignId('verificado_user_id')->nullable()->after('verificado')->constrained('users');
            }
            if (!Schema::hasColumn('ventas', 'verificado_at')) {
                $table->timestamp('verificado_at')->nullable()->after('verificado_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'verificado_at')) {
                $table->dropColumn('verificado_at');
            }
            if (Schema::hasColumn('ventas', 'verificado_user_id')) {
                $table->dropConstrainedForeignId('verificado_user_id');
            }
            if (Schema::hasColumn('ventas', 'verificado')) {
                $table->dropColumn('verificado');
            }
        });
    }
};
