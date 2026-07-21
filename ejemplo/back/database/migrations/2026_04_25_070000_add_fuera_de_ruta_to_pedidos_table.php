<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'fuera_de_ruta')) {
                $table->boolean('fuera_de_ruta')->default(false)->after('contiene_pollo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'fuera_de_ruta')) {
                $table->dropColumn('fuera_de_ruta');
            }
        });
    }
};
