<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Arranque en limpio (2026-10-05): facturacion, recojo y creditos empiezan de cero.
 *
 * Borra TODO lo del sistema nuevo:
 *  - facturacion: facturas, factura_detalles, pedido_borradores
 *  - lo que cuelga de un comprobante: carga_verificaciones, cobranza_verificaciones,
 *    y las entregas del recojo hechas con la app (entregas.factura_id no nulo)
 *  - cobranzas/creditos: creditos_manuales, creditos_abonos
 * y los ids vuelven a empezar en 1.
 *
 * NO toca: tbpedidos (los pedidos siguen; los ya cobrados vuelven a quedar pendientes),
 * tbclientes, tbproductos, tbfactura, tbctascobrar ni las entregas del sistema anterior
 * (las que no tienen factura_id), ni la configuracion del SIAT (CUIS/CUFD).
 *
 * Las facturas ya emitidas al SIAT NO se anulan en Impuestos: solo se borran de esta base.
 * La numeracion de facturas no vuelve a 1: sigue desde el mayor nro entre facturas y
 * tbfactura (SiatService::siguienteNumero), asi que no se repiten numeros ante el SIAT.
 *
 * No se puede deshacer: hacer un respaldo de la base antes de correrla.
 */
class ReiniciarFacturacionYCreditos extends Migration
{
    /** Se vacian enteras y su id vuelve a 1. El orden respeta la FK factura_detalles -> facturas. */
    private $tablas = [
        'factura_detalles',
        'carga_verificaciones',
        'cobranza_verificaciones',
        'pedido_borradores',
        'creditos_abonos',
        'creditos_manuales',
        'facturas',
    ];

    public function up()
    {
        $antes = [];
        foreach ($this->tablas as $tabla) {
            $antes[$tabla] = DB::table($tabla)->count();
        }
        $entregas = DB::table('entregas')->whereNotNull('factura_id')->count();

        // Sin transaccion: TRUNCATE/ALTER en MySQL confirman solos. Sin chequeo de FK
        // para poder vaciar facturas aunque factura_detalles la referencie.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::table('entregas')->whereNotNull('factura_id')->delete();
            foreach ($this->tablas as $tabla) {
                DB::table($tabla)->truncate();
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        echo "  Entregas de la app borradas: {$entregas}\n";
        foreach ($antes as $tabla => $n) {
            echo "  {$tabla}: {$n} filas borradas\n";
        }
    }

    public function down()
    {
        // Lo borrado no se recupera: hay que volver al respaldo de la base.
    }
}
