<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indices para la consulta de facturas fiscales desde la pantalla de Ventas.
 *
 * Sin ellos cada factura hace un scan completo de tbventas (396.000 filas)
 * para armar el detalle, y listar las facturas de un cliente escanea
 * tbfactura entera.
 */
class AddIndicesFactura extends Migration
{
    /** Indices a crear: nombre => [tabla, columnas]. */
    private $indices = [
        'idx_tbventas_comanda'  => ['tbventas', 'Comanda'],
        'idx_tbfactura_idcli'   => ['tbfactura', 'IdCli'],
        'idx_tbfactura_comanda' => ['tbfactura', 'comanda'],
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
