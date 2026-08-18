<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deja el NIT del proveedor opcional.
 *
 * En el negocio muchos proveedores chicos no dan NIT; lo obligatorio es el
 * nombre. El problema es que tbproveedor.NIT tiene indice UNICO y era NOT
 * NULL: dos proveedores sin NIT quedaban ambos con cadena vacia y el segundo
 * reventaba con "Duplicate entry ''". MySQL si admite varios NULL en un
 * indice unico, asi que la columna pasa a aceptar NULL y el controlador
 * guarda NULL (no '') cuando no hay NIT.
 *
 * Se usa SQL directo porque cambiar una columna con doctrine/dbal en una
 * tabla legada arrastraria el resto de sus definiciones.
 */
class MakeNitNullableInTbproveedor extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE tbproveedor MODIFY NIT VARCHAR(16) NULL');

        // Los que ya estaban sin NIT pasan a NULL para que dejen de ocupar el
        // unico valor vacio que permitia el indice.
        DB::table('tbproveedor')->where('NIT', '')->update(['NIT' => null]);
    }

    public function down()
    {
        DB::table('tbproveedor')->whereNull('NIT')->update(['NIT' => '']);

        DB::statement('ALTER TABLE tbproveedor MODIFY NIT VARCHAR(16) NOT NULL');
    }
}
