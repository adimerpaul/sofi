<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Avance de ventas segun el formato nuevo:
 * - Sale la seccion PRODUCTOS NOT (MAYONESA y LECHE): no va en el formato.
 * - avance_lineas.por_cantidad: la linea suma la cantidad vendida y no los kg.
 *   Solo HUEVO; todo lo demas va en kg.
 */
class AvanceLineasPorCantidad extends Migration
{
    public function up()
    {
        Schema::table('avance_lineas', function (Blueprint $table) {
            $table->boolean('por_cantidad')->default(false)->after('productos');
        });

        DB::table('avance_lineas')->where('nombre', 'HUEVO')->update(['por_cantidad' => true]);
        // Sus objetivos se van por la foreign key (onDelete cascade).
        DB::table('avance_lineas')->where('seccion', 'PRODUCTOS NOT')->delete();
    }

    public function down()
    {
        Schema::table('avance_lineas', function (Blueprint $table) {
            $table->dropColumn('por_cantidad');
        });

        $ahora = now();
        foreach ([[250, 'MAYONESA'], [260, 'LECHE']] as [$orden, $nombre]) {
            if (!DB::table('avance_lineas')->where('seccion', 'PRODUCTOS NOT')->where('nombre', $nombre)->exists()) {
                DB::table('avance_lineas')->insert([
                    'orden' => $orden, 'seccion' => 'PRODUCTOS NOT', 'nombre' => $nombre,
                    'grupos' => null, 'productos' => null, 'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }
    }
}
