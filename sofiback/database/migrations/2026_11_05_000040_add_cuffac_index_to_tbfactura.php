<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indice en tbfactura.cuffac (CUF de las facturas del sistema de caja).
 *
 * La verificacion de facturas cruza el libro del SIAT con la caja por CUF y,
 * sin indice, cada pantalla tardaba ~30 s. Solo agrega un indice: no cambia
 * datos ni columnas, y el sistema de caja de escritorio no se entera.
 */
class AddCuffacIndexToTbfactura extends Migration
{
    public function up()
    {
        if (!$this->existe()) {
            DB::statement('ALTER TABLE tbfactura ADD INDEX tbfactura_cuffac_index (cuffac)');
        }
    }

    public function down()
    {
        if ($this->existe()) {
            DB::statement('ALTER TABLE tbfactura DROP INDEX tbfactura_cuffac_index');
        }
    }

    private function existe()
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'tbfactura')
            ->where('INDEX_NAME', 'tbfactura_cuffac_index')
            ->exists();
    }
}
