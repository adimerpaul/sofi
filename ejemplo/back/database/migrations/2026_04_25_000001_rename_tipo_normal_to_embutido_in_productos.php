<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('productos')->where('tipo', 'NORMAL')->update(['tipo' => 'EMBUTIDO']);
    }

    public function down(): void
    {
        DB::table('productos')->where('tipo', 'EMBUTIDO')->update(['tipo' => 'NORMAL']);
    }
};
