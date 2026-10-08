<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Arranque en limpio de la verificacion de cobranzas: ningun comprobante
 * verificado. Las pruebas dejaron tildes que confundian.
 *
 * Borra:
 *  - cobranza_verificaciones: los tildes de efectivo y QR, los montos
 *    escritos y quien/cuando verifico. Su id vuelve a 1. Todos los
 *    comprobantes quedan "por verificar" en /cobranzas/verificacion.
 *
 * NO toca: facturas, entregas (lo que cobro el caminero sigue igual y
 * /caminero/reporte no cambia), tbpedidos, creditos ni carga_verificaciones.
 *
 * No se puede deshacer: hacer un respaldo de la base antes de correrla.
 */
class ReiniciarVerificacionesCobranza extends Migration
{
    public function up()
    {
        $antes = DB::table('cobranza_verificaciones')->count();

        // Sin transaccion: TRUNCATE en MySQL confirma solo.
        DB::table('cobranza_verificaciones')->truncate();

        echo "  cobranza_verificaciones: {$antes} filas borradas\n";
    }

    public function down()
    {
        // Lo borrado no se recupera: hay que volver al respaldo de la base.
    }
}
