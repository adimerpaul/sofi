<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Deuda de los clientes segun cobranzas/creditos (CreditoController): los comprobantes de
 * facturacion emitidos a credito y las deudas manuales, menos los abonos que no se anularon.
 * Reemplaza a tbctascobrar para la visita, la lista de clientes y el bloqueo por deuda.
 */
class DeudaCliente
{
    /** Una fila por deuda pendiente: cliente_id, fecha, saldo. */
    public static function sqlPendientes(): string
    {
        return "SELECT x.cliente_id, x.fecha, x.saldo FROM (
            SELECT f.cliente_id, f.fecha,
                   f.total - COALESCE((SELECT SUM(a.monto) FROM creditos_abonos a
                        WHERE a.origen = 'factura' AND a.deuda_id = f.id AND a.anulado_at IS NULL), 0) AS saldo
            FROM facturas f
            WHERE f.tipo_pago IN ('CRÉDITO', 'CREDITO') AND f.estado = 'ACTIVO' AND f.deleted_at IS NULL
            UNION ALL
            SELECT d.cliente_id, d.fecha,
                   d.monto - COALESCE((SELECT SUM(a.monto) FROM creditos_abonos a
                        WHERE a.origen = 'manual' AND a.deuda_id = d.id AND a.anulado_at IS NULL), 0) AS saldo
            FROM creditos_manuales d
        ) x WHERE x.saldo > 0.009";
    }

    /** Totales por cliente: cliente_id, totdeuda, cantdeuda, fechaminima. Para usar como subconsulta en un JOIN. */
    public static function sqlPorCliente(): string
    {
        return "SELECT p.cliente_id, ROUND(SUM(p.saldo), 2) AS totdeuda, COUNT(*) AS cantdeuda, MIN(p.fecha) AS fechaminima
            FROM (" . self::sqlPendientes() . ") p GROUP BY p.cliente_id";
    }

    /** Totales de los clientes indicados (Cod_Aut), indexados por Cod_Aut. */
    public static function porCliente(array $codAuts)
    {
        $codAuts = array_values(array_filter($codAuts, fn ($c) => $c !== null && $c !== ''));
        if (empty($codAuts)) return collect();
        return collect(DB::select(
            "SELECT * FROM (" . self::sqlPorCliente() . ") t WHERE t.cliente_id IN (" . implode(',', array_fill(0, count($codAuts), '?')) . ")",
            $codAuts
        ))->keyBy('cliente_id');
    }

    /** Pone totdeuda, cantdeuda y fechaminima a cada cliente (objetos con Cod_Aut). */
    public static function adjuntar($clientes)
    {
        $deudas = self::porCliente(collect($clientes)->pluck('Cod_Aut')->all());
        foreach ($clientes as $cliente) {
            $d = $deudas->get($cliente->Cod_Aut);
            $cliente->totdeuda = $d ? (float) $d->totdeuda : 0;
            $cliente->cantdeuda = $d ? (int) $d->cantdeuda : 0;
            $cliente->fechaminima = $d ? $d->fechaminima : null;
        }
        return $clientes;
    }

    /**
     * Bloqueo por deuda: activa a todos y pone venta='INACTIVO' a quien cumpla cualquiera de:
     *  - deuda pendiente (contando solo deudas con saldo > 5 Bs) mayor a 12000 Bs
     *  - la deuda pendiente mas antigua (saldo >= 5 Bs) es de hace mas de 9 dias
     * Los clientes con tbclientes.excepcion_deuda = 1 nunca se bloquean (se marca en la pantalla Clientes).
     * Un cliente INACTIVO puede registrar pedido, pero no se
     * envia a despacho (envpedido / enviarpedidos) hasta que vuelva a ACTIVO.
     */
    public static function bloquear()
    {
        DB::update("UPDATE tbclientes SET venta = 'ACTIVO'");
        DB::update("UPDATE tbclientes t
            INNER JOIN (
                SELECT p.cliente_id,
                       SUM(CASE WHEN p.saldo > 5 THEN p.saldo ELSE 0 END) AS deuda,
                       MIN(CASE WHEN p.saldo >= 5 THEN p.fecha END) AS desde
                FROM (" . self::sqlPendientes() . ") p GROUP BY p.cliente_id
            ) d ON d.cliente_id = t.Cod_Aut
            SET t.venta = 'INACTIVO'
            WHERE t.excepcion_deuda = 0
              AND (d.deuda > 12000 OR DATEDIFF(CURDATE(), d.desde) > 9)");
    }

    /** Habilita a quien debe menos de 12000 Bs (o no debe nada). */
    public static function desbloquearMenores()
    {
        DB::update("UPDATE tbclientes t
            LEFT JOIN (" . self::sqlPorCliente() . ") d ON d.cliente_id = t.Cod_Aut
            SET t.venta = 'ACTIVO'
            WHERE d.cliente_id IS NULL OR d.totdeuda < 12000");
    }
}
