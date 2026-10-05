<?php

use App\Models\Factura;
use App\Services\CambioPedido;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca en la factura si al cobrar se cambio algo del pedido del preventista.
 *
 * - cambio_pedido: 1 si el comprobante no salio igual al pedido.
 * - cambio_pedido_campos: JSON con que cambio (ver App\Services\CambioPedido).
 *
 * Los comprobantes que ya existian se completan aca mismo, comparando con el
 * pedido tal como esta hoy.
 */
class AddCambioPedidoToFacturas extends Migration
{
    public function up()
    {
        Schema::table('facturas', function (Blueprint $table) {
            if (!Schema::hasColumn('facturas', 'cambio_pedido')) {
                $table->boolean('cambio_pedido')->default(false)->index();
            }
            if (!Schema::hasColumn('facturas', 'cambio_pedido_campos')) {
                $table->longText('cambio_pedido_campos')->nullable();
            }
        });

        $servicio = new CambioPedido();
        Factura::whereNotNull('pedido_nro')->orderBy('id')->chunkById(200, function ($facturas) use ($servicio) {
            $facturas->each(function ($factura) use ($servicio) {
                $servicio->registrar($factura);
            });
        });
    }

    public function down()
    {
        Schema::table('facturas', function (Blueprint $table) {
            foreach (['cambio_pedido', 'cambio_pedido_campos'] as $columna) {
                if (Schema::hasColumn('facturas', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
}
