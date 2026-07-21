<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mapa_zona_tipos')) {
            Schema::create('mapa_zona_tipos', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('nombre')->unique();
                $table->unsignedInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        DB::statement('ALTER TABLE mapa_zona_poligonos MODIFY tipo INT UNSIGNED NOT NULL');

        $tipos = DB::table('mapa_zona_poligonos')
            ->select('tipo')
            ->distinct()
            ->orderByDesc('tipo')
            ->pluck('tipo')
            ->map(fn ($tipo) => (int) $tipo)
            ->all();

        foreach ([5, 4, 3] as $baseTipo) {
            if (!in_array($baseTipo, $tipos, true)) {
                $tipos[] = $baseTipo;
            }
        }

        rsort($tipos);

        foreach (array_values($tipos) as $index => $tipo) {
            DB::table('mapa_zona_tipos')->updateOrInsert(
                ['nombre' => $tipo],
                [
                    'orden' => $index + 1,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mapa_zona_tipos');
        DB::statement('ALTER TABLE mapa_zona_poligonos MODIFY tipo TINYINT UNSIGNED NOT NULL');
    }
};
