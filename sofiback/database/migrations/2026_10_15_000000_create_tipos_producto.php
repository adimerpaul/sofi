<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de producto administrables (POLLO, CERDO, RES, EMBUTIDO, HUEVO, PET y
 * los que se agreguen desde Productos).
 *
 * tbproductos.tipo no cambia de significado: sigue diciendo con que formulario
 * se pide el producto (NORMAL, POLLO, CERDO o RES), que es lo que copian
 * tbpedidos.tipo, la facturacion y los reportes. Cada tipo administrable lleva
 * ese "modo" y al elegirlo en un producto se escribe en tbproductos.tipo.
 *
 * Los productos existentes quedan con el tipo de su modo; los NORMAL pasan a
 * EMBUTIDO, salvo los codigos de huevo de TipoPedido, que pasan a HUEVO.
 */
class CreateTiposProducto extends Migration
{
    private const TIPOS = [
        ['nombre' => 'POLLO',    'modo' => 'POLLO',  'color' => 'orange-8'],
        ['nombre' => 'CERDO',    'modo' => 'CERDO',  'color' => 'pink-6'],
        ['nombre' => 'RES',      'modo' => 'RES',    'color' => 'red-9'],
        ['nombre' => 'EMBUTIDO', 'modo' => 'NORMAL', 'color' => 'brown-6'],
        ['nombre' => 'HUEVO',    'modo' => 'NORMAL', 'color' => 'amber-9'],
        ['nombre' => 'PET',      'modo' => 'NORMAL', 'color' => 'teal-6'],
    ];

    public function up()
    {
        Schema::create('tipos_producto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 30)->unique();
            // NORMAL, POLLO, CERDO o RES: el formulario del pedido.
            $table->string('modo', 10)->default('NORMAL');
            // Color de Quasar para el chip del pedido.
            $table->string('color', 20)->default('primary');
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });

        $ahora = date('Y-m-d H:i:s');
        foreach (self::TIPOS as $i => $tipo) {
            DB::table('tipos_producto')->insert($tipo + ['orden' => $i + 1, 'created_at' => $ahora, 'updated_at' => $ahora]);
        }

        Schema::table('tbproductos', function (Blueprint $table) {
            if (!Schema::hasColumn('tbproductos', 'tipo_producto_id')) {
                $table->unsignedBigInteger('tipo_producto_id')->nullable()->index();
            }
        });

        $id = DB::table('tipos_producto')->pluck('id', 'nombre');
        foreach (['POLLO', 'CERDO', 'RES'] as $modo) {
            DB::table('tbproductos')->whereRaw('UPPER(TRIM(tipo)) = ?', [$modo])
                ->update(['tipo_producto_id' => $id[$modo]]);
        }
        DB::table('tbproductos')->whereNull('tipo_producto_id')
            ->whereIn(DB::raw('TRIM(cod_prod)'), \App\Services\TipoPedido::HUEVO)
            ->update(['tipo_producto_id' => $id['HUEVO']]);
        DB::table('tbproductos')->whereNull('tipo_producto_id')
            ->update(['tipo_producto_id' => $id['EMBUTIDO']]);
    }

    public function down()
    {
        Schema::table('tbproductos', function (Blueprint $table) {
            if (Schema::hasColumn('tbproductos', 'tipo_producto_id')) {
                $table->dropColumn('tipo_producto_id');
            }
        });
        Schema::dropIfExists('tipos_producto');
    }
}
