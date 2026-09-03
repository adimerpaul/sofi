<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el caminero revisa antes de salir son los comprobantes del dia, no
 * los pedidos sueltos: caja factura, arma la canasta de cada venta y el
 * caminero da el visto bueno sobre esa venta. Recien con todas revisadas caja
 * imprime los papeles de ese camion.
 *
 * Por eso la fila cuelga de la factura y no del pedido. La tabla anterior
 * apuntaba al pedido y no llego a usarse mas que en pruebas, asi que se rehace.
 *
 * items_esperados y total_esperado guardan como estaba la venta cuando se la
 * reviso: si despues la cambian, el comprobante vuelve a quedar pendiente.
 */
class CargaVerificacionesPorComprobante extends Migration
{
    public function up()
    {
        Schema::dropIfExists('carga_verificaciones');

        Schema::create('carga_verificaciones', function (Blueprint $tabla) {
            $tabla->id();
            // Dia en que sale el camion, que es el del comprobante.
            $tabla->date('fecha');
            $tabla->string('placa', 100);
            $tabla->unsignedBigInteger('factura_id')->unique();
            $tabla->unsignedBigInteger('pedido_nro')->nullable();
            $tabla->string('pedido_tipo', 20)->nullable();
            $tabla->unsignedInteger('items_esperados')->default(0);
            $tabla->decimal('total_esperado', 12, 2)->default(0);
            $tabla->boolean('verificado')->default(false);
            $tabla->string('observacion', 190)->nullable();
            $tabla->unsignedInteger('personal_id')->nullable();
            $tabla->string('verificado_por', 120)->nullable();
            $tabla->timestamp('verificado_en')->nullable();
            $tabla->timestamps();

            $tabla->index(['fecha', 'placa'], 'carga_verificaciones_dia_camion');
        });
    }

    public function down()
    {
        Schema::dropIfExists('carga_verificaciones');
    }
}
