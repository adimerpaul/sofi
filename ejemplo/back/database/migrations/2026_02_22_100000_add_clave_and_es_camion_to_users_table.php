<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'clave')) {
                $table->text('clave')->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'es_camion')) {
                $table->boolean('es_camion')->default(false)->after('celular');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'clave')) {
                $table->dropColumn('clave');
            }
            if (Schema::hasColumn('users', 'es_camion')) {
                $table->dropColumn('es_camion');
            }
        });
    }
};
