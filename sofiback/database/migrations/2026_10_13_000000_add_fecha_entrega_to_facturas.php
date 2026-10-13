<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dia en que el camion entrega el comprobante.
 *
 * Antes se deducia de la fecha y hora de la factura con el corte de las 18:00
 * (lo facturado de noche salia al dia siguiente), y cada pantalla lo calculaba
 * a su manera. Ahora queda guardado: el del pedido o, en venta directa, el que
 * elige caja. Facturacion, la carga, el caminero y cobranzas filtran por este.
 *
 * Lo que ya existe se completa con el dia que le daba el sistema: el de su
 * ultima entrega registrada y, si no tiene, la regla del corte.
 */
class AddFechaEntregaToFacturas extends Migration
{
    private const CORTE = '18:00:00';

    public function up()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->date('fecha_entrega')->nullable()->after('hora')->index();
        });

        // 1) Lo que ya tiene entrega: el dia en que el camion la registro.
        DB::statement("
            UPDATE facturas f
            JOIN (
                SELECT e.factura_id, e.fechaEntreg
                FROM entregas e
                JOIN (SELECT factura_id, MAX(id) AS id FROM entregas WHERE factura_id IS NOT NULL GROUP BY factura_id) u ON u.id = e.id
            ) e ON e.factura_id = f.id
            SET f.fecha_entrega = DATE(e.fechaEntreg)
            WHERE e.fechaEntreg IS NOT NULL
        ");

        // 2) Lo demas: facturado despues del corte va al dia siguiente, salvo
        // que su pedido se entregue ese mismo dia.
        $entregaPedido = DB::table('tbpedidos')
            ->whereNotNull('fecha_entrega')
            ->groupBy('NroPed')
            ->pluck(DB::raw('MIN(fecha_entrega)'), 'NroPed');

        DB::table('facturas')->whereNull('fecha_entrega')->orderBy('id')
            ->select(['id', 'fecha', 'hora', 'pedido_nro'])
            ->chunkById(1000, function ($facturas) use ($entregaPedido) {
                foreach ($facturas as $f) {
                    $dia = substr((string) $f->fecha, 0, 10);
                    if (!$dia) {
                        continue;
                    }
                    $delPedido = $f->pedido_nro ? substr((string) $entregaPedido->get($f->pedido_nro), 0, 10) : null;
                    if ((string) $f->hora >= self::CORTE && $delPedido !== $dia) {
                        $dia = date('Y-m-d', strtotime($dia . ' +1 day'));
                    }
                    DB::table('facturas')->where('id', $f->id)->update(['fecha_entrega' => $dia]);
                }
            });
    }

    public function down()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropIndex(['fecha_entrega']);
            $table->dropColumn('fecha_entrega');
        });
    }
}
