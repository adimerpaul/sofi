<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('productos', 'producto_grupo_padre_id')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->unsignedBigInteger('producto_grupo_padre_id')->nullable()->after('producto_grupo_id');
                $table->index('producto_grupo_padre_id', 'productos_producto_grupo_padre_id_idx');
                $table->foreign('producto_grupo_padre_id', 'productos_producto_grupo_padre_id_foreign')
                    ->references('id')
                    ->on('producto_grupo_padres')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        DB::statement("\n            UPDATE productos p\n            INNER JOIN producto_grupos g ON g.id = p.producto_grupo_id\n            SET p.producto_grupo_padre_id = g.producto_grupo_padre_id\n            WHERE p.producto_grupo_id IS NOT NULL\n              AND (p.producto_grupo_padre_id IS NULL OR p.producto_grupo_padre_id <> g.producto_grupo_padre_id)\n        ");
    }

    public function down(): void
    {
        // Data backfill migration: no destructive rollback.
    }
};
