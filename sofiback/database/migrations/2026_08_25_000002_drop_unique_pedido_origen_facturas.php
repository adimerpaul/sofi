<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un pedido puede tener mas de un comprobante a lo largo del tiempo.
 *
 * El unique sobre (pedido_nro, pedido_tipo) daba por sentado que un pedido se
 * cobra una sola vez para siempre, y eso deja dos casos sin salida: la venta
 * que se anula y hay que volver a emitir, y la que se borra (borrado logico,
 * asi que la fila sigue ocupando la clave). En los dos el insert moria con
 * "Duplicate entry '201609-NORMAL'".
 *
 * Queda un indice normal, que es lo que de verdad hacia falta: las consultas
 * buscan el comprobante de un pedido, no necesitan que la base lo garantice
 * unico. La regla de negocio real —un solo comprobante VIGENTE por pedido, los
 * anulados no cuentan— la aplica FacturacionController::store().
 */
class DropUniquePedidoOrigenFacturas extends Migration
{
    public function up()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropUnique('facturas_pedido_origen_unique');
            $table->index(['pedido_nro', 'pedido_tipo'], 'facturas_pedido_origen_index');
        });
    }

    public function down()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropIndex('facturas_pedido_origen_index');
            $table->unique(['pedido_nro', 'pedido_tipo'], 'facturas_pedido_origen_unique');
        });
    }
}
