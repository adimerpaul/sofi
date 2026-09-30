<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numero de pedido del dia por vendedor.
 *
 * Cada vendedor (CIfunc) numera sus pedidos desde 1 dentro de cada fecha de
 * pedido, para ubicarlos rapido en Mis pedidos. Todas las filas de una misma
 * comanda (NroPed) comparten el numero.
 */
class AddNumeroDiaToTbpedidos extends Migration
{
    public function up()
    {
        Schema::table('tbpedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('tbpedidos', 'numero_dia')) {
                $table->unsignedInteger('numero_dia')->nullable()->after('NroPed');
            }
        });

        // Numera los pedidos recientes (ultimos 7 dias y futuros) en el orden
        // en que se crearon, para que Mis pedidos ya muestre el numero hoy.
        $comandas = DB::table('tbpedidos')
            ->select('NroPed', 'CIfunc', DB::raw('DATE(MIN(fecha)) as dia'))
            ->whereNull('numero_dia')
            ->where('fecha', '>=', date('Y-m-d', strtotime('-7 days')))
            ->groupBy('NroPed', 'CIfunc')
            ->orderBy('NroPed')
            ->get();
        $contador = [];
        foreach ($comandas as $c) {
            $clave = $c->CIfunc . '|' . $c->dia;
            $contador[$clave] = ($contador[$clave] ?? 0) + 1;
            DB::table('tbpedidos')->where('NroPed', $c->NroPed)->update(['numero_dia' => $contador[$clave]]);
        }
    }

    public function down()
    {
        Schema::table('tbpedidos', function (Blueprint $table) {
            if (Schema::hasColumn('tbpedidos', 'numero_dia')) {
                $table->dropColumn('numero_dia');
            }
        });
    }
}
