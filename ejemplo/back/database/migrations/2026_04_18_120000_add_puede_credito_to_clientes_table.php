<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'puede_credito')) {
                $table->boolean('puede_credito')->default(true)->after('tarjeta');
            }
        });

        DB::table('clientes')
            ->whereNull('puede_credito')
            ->update(['puede_credito' => true]);
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'puede_credito')) {
                $table->dropColumn('puede_credito');
            }
        });
    }
};
