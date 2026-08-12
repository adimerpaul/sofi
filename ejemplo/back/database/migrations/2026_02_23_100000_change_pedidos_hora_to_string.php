<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
//        if (!Schema::hasTable('pedidos') || !Schema::hasColumn('pedidos', 'hora')) {
//            return;
//        }
//
//        $driver = DB::getDriverName();
//
//        if ($driver === 'mysql') {
//            DB::statement('ALTER TABLE pedidos MODIFY hora VARCHAR(50) NULL');
//            return;
//        }
//
//        if ($driver === 'pgsql') {
//            DB::statement('ALTER TABLE pedidos ALTER COLUMN hora TYPE VARCHAR(50) USING hora::varchar');
//            return;
//        }
    }

    public function down(): void
    {
//        if (!Schema::hasTable('pedidos') || !Schema::hasColumn('pedidos', 'hora')) {
//            return;
//        }
//
//        $driver = DB::getDriverName();
//
//        if ($driver === 'mysql') {
//            DB::statement('ALTER TABLE pedidos MODIFY hora TIME NULL');
//            return;
//        }
//
//        if ($driver === 'pgsql') {
//            DB::statement('ALTER TABLE pedidos ALTER COLUMN hora TYPE TIME USING NULLIF(hora, \'\')::time');
//            return;
//        }
    }
};
