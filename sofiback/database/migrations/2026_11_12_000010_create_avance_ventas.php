<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Avance de ventas (AvanceVentasController): cada mes se pone un objetivo por
 * linea de producto (en total y por vendedor) y se compara con lo facturado.
 *
 * avance_lineas: las filas del formato, agrupadas por seccion (BANDEJAS,
 * PROCESADOS, ...). Cada linea junta los grupos de tbproductos (cod_grup) y,
 * si hace falta, productos sueltos (cod_prod), que mandan sobre el grupo.
 *
 * avance_objetivos: lo que se espera vender en el mes por linea. vendedor_id
 * es el CodAut de personal; 0 es el objetivo general de la empresa.
 */
class CreateAvanceVentas extends Migration
{
    // [seccion, linea, grupos de tbproductos]. MAYONESA y LECHE no tienen grupo
    // todavia: se les asignan productos desde la pantalla.
    private const LINEAS = [
        ['BANDEJAS', 'BANDEJA DE POLLO', ['14']],
        ['BANDEJAS', 'BANDEJA DE CERDO', ['11']],
        ['BANDEJAS', 'BANDEJA DE PAVO', ['13']],
        ['PROCESADOS', 'HAMBURGUESA', ['72']],
        ['PROCESADOS', 'NUGGETS', ['73']],
        ['PROCESADOS', 'PREPARADOS', ['71']],
        ['PROCESADOS', 'PAPAS FRITAS', ['0121']],
        ['FIAMBRES TOTAL', 'AHUMADO', ['81']],
        ['FIAMBRES TOTAL', 'QUESO ENRROLLADO', ['82']],
        ['FIAMBRES TOTAL', 'SECOS', ['83']],
        ['EMBUTIDOS TOTAL', 'CHORIZO', ['51']],
        ['EMBUTIDOS TOTAL', 'JAMON', ['52']],
        ['EMBUTIDOS TOTAL', 'MORTADELA BONISSIMA', ['56']],
        ['EMBUTIDOS TOTAL', 'MORTADELA SOFIA', ['58']],
        ['EMBUTIDOS TOTAL', 'SALCHICHA BONISSIMA', ['57']],
        ['EMBUTIDOS TOTAL', 'SALCHICHA SOFIA', ['59']],
        ['EMBUTIDOS TOTAL', 'PATE', ['54']],
        ['CONSERVAS TOTAL', 'ATUN', ['41']],
        ['CONSERVAS TOTAL', 'DURAZNOS', ['47']],
        ['CONSERVAS TOTAL', 'PALMITO', ['44']],
        ['CONSERVAS TOTAL', 'CHOCLO', ['42']],
        ['CONSERVAS TOTAL', 'MERMELADA', ['45']],
        ['CONSERVAS TOTAL', 'PEPINILLOS', ['46']],
        ['CONSERVAS TOTAL', 'ACEITUNAS', ['43']],
        ['PRODUCTOS NOT', 'MAYONESA', []],
        ['PRODUCTOS NOT', 'LECHE', []],
        ['PET TOTAL', 'PODIUM', ['91']],
        ['PET TOTAL', 'DOGSTAR', ['93']],
        ['PET TOTAL', 'MICAN', ['92']],
        ['PET TOTAL', 'SNACK CANINO', ['95']],
        ['PET TOTAL', 'ALIMENTO FELINO', ['94']],
        ['ESPECIFICOS', 'CORTES DE CERDO', ['21']],
        ['ESPECIFICOS', 'CERDO', ['22']],
        ['ESPECIFICOS', 'POLLO', ['31', '32']],
        ['ESPECIFICOS', 'HUEVO', ['62']],
    ];

    public function up()
    {
        Schema::create('avance_lineas', function (Blueprint $table) {
            $table->id();
            $table->string('seccion', 60);
            $table->string('nombre', 80);
            $table->unsignedInteger('orden')->default(0);
            // Codigos separados por coma: "31,32".
            $table->string('grupos', 255)->nullable();
            $table->text('productos')->nullable();
            $table->timestamps();
        });

        Schema::create('avance_objetivos', function (Blueprint $table) {
            $table->id();
            $table->char('mes', 7); // YYYY-MM
            $table->unsignedBigInteger('linea_id');
            $table->unsignedInteger('vendedor_id')->default(0);
            $table->decimal('objetivo', 12, 2)->default(0);
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['mes', 'linea_id', 'vendedor_id']);
            $table->foreign('linea_id')->references('id')->on('avance_lineas')->onDelete('cascade');
        });

        $ahora = now();
        $filas = [];
        foreach (self::LINEAS as $i => [$seccion, $nombre, $grupos]) {
            $filas[] = [
                'seccion' => $seccion,
                'nombre' => $nombre,
                'orden' => ($i + 1) * 10,
                'grupos' => $grupos ? implode(',', $grupos) : null,
                'productos' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }
        DB::table('avance_lineas')->insert($filas);
    }

    public function down()
    {
        Schema::dropIfExists('avance_objetivos');
        Schema::dropIfExists('avance_lineas');
    }
}
