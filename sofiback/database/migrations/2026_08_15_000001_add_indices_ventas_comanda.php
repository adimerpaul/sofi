<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indices para la pantalla de Ventas, que ahora lista tbventas agrupada por
 * comanda.
 *
 * Sin ellos, filtrar un dia de ventas escanea las 388.000 filas de tbventas
 * (2 s por consulta) y buscar la entrega de cada comanda escanea las 120.000
 * de entregas. Con los indices la misma pantalla baja a decimas de segundo.
 */
class AddIndicesVentasComanda extends Migration
{
    /** Indices a crear: nombre => [tabla, columnas]. */
    private $indices = [
        'idx_tbventas_fech_venta' => ['tbventas', 'Fech_Venta'],
        'idx_entregas_comanda'    => ['entregas', 'comanda'],
        'idx_entregas_fecha'      => ['entregas', 'fecha'],
    ];

    public function up()
    {
        foreach ($this->indices as $nombre => [$tabla, $columnas]) {
            if (!$this->existe($tabla, $nombre)) {
                DB::statement("CREATE INDEX {$nombre} ON {$tabla} ({$columnas})");
            }
        }
    }

    public function down()
    {
        foreach ($this->indices as $nombre => [$tabla, $columnas]) {
            if ($this->existe($tabla, $nombre)) {
                DB::statement("DROP INDEX {$nombre} ON {$tabla}");
            }
        }
    }

    private function existe($tabla, $nombre)
    {
        return count(DB::select("SHOW INDEX FROM {$tabla} WHERE Key_name = ?", [$nombre])) > 0;
    }
}
