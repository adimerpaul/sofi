<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Arranque en limpio de los camiones: ningun camion verificado y ningun cobro
 * del caminero. Lo pidio administracion para empezar de cero.
 *
 * Borra:
 *  - carga_verificaciones: el visto bueno de cada canasta y los productos
 *    tildados. Todos los camiones quedan sin verificar.
 *  - entregas hechas con la app del caminero (entregas.factura_id no nulo):
 *    cobros, no entregados, rechazos y retornos parciales. El reporte del
 *    caminero (/caminero/reporte) queda vacio y todas las notas vuelven a
 *    estar pendientes de cobro.
 *  - cobranza_verificaciones: lo que cobranzas verifico contra lo que trajo
 *    cada camion. Se calculo sobre esos cobros, asi que sin ellos no vale.
 * y los ids de carga_verificaciones y cobranza_verificaciones vuelven a 1.
 *
 * NO toca: facturas ni su detalle (los comprobantes siguen emitidos), tbpedidos,
 * creditos, ni las entregas del sistema anterior (sin factura_id).
 *
 * No se puede deshacer: hacer un respaldo de la base antes de correrla.
 */
class ReiniciarCamiones extends Migration
{
    /** Se vacian enteras y su id vuelve a 1. */
    private $tablas = [
        'carga_verificaciones',
        'cobranza_verificaciones',
    ];

    public function up()
    {
        $antes = [];
        foreach ($this->tablas as $tabla) {
            $antes[$tabla] = DB::table($tabla)->count();
        }
        $entregas = DB::table('entregas')->whereNotNull('factura_id')->count();

        // Sin transaccion: TRUNCATE en MySQL confirma solo.
        DB::table('entregas')->whereNotNull('factura_id')->delete();
        foreach ($this->tablas as $tabla) {
            DB::table($tabla)->truncate();
        }

        echo "  Entregas de la app (cobros del caminero) borradas: {$entregas}\n";
        foreach ($antes as $tabla => $n) {
            echo "  {$tabla}: {$n} filas borradas\n";
        }
    }

    public function down()
    {
        // Lo borrado no se recupera: hay que volver al respaldo de la base.
    }
}
