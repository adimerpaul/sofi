<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            ['nombre' => 'APOYO', 'color' => '#66bb6a', 'orden' => 10],
            ['nombre' => 'APOYO 2', 'color' => '#2e7d32', 'orden' => 20],
            ['nombre' => 'BOLIVAR', 'color' => '#f06292', 'orden' => 30],
            ['nombre' => 'CARACOLLO', 'color' => '#0288d1', 'orden' => 40],
            ['nombre' => 'CENTRO', 'color' => '#fdd835', 'orden' => 50],
            ['nombre' => 'CHALLAPATA', 'color' => '#b71c1c', 'orden' => 60],
            ['nombre' => 'HUANUNI', 'color' => '#9575cd', 'orden' => 70],
        ];

        foreach ($defaults as $row) {
            DB::table('pedido_zonas')->updateOrInsert(
                ['nombre' => $row['nombre']],
                [
                    'color' => $row['color'],
                    'orden' => $row['orden'],
                    'activo' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('pedido_zonas')
            ->whereIn('nombre', ['APOYO', 'APOYO 2', 'BOLIVAR', 'CARACOLLO', 'CENTRO', 'CHALLAPATA', 'HUANUNI'])
            ->delete();
    }
};

