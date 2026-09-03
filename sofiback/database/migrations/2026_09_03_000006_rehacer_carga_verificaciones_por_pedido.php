<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La carga no se revisa producto por producto sino canasta por canasta: cada
 * pedido sube en la suya, asi que el caminero da el visto bueno sobre el
 * pedido entero, no sobre los productos sumados de todo el camion.
 *
 * La tabla anterior guardaba una fila por producto y no llego a usarse, asi
 * que se rehace con la forma correcta en vez de arrastrar columnas que ya no
 * significan nada.
 *
 * productos_esperados y total_esperado guardan como estaba el pedido cuando se
 * lo reviso: si despues le agregan lineas, la canasta ya no es la que se miro y
 * el pedido vuelve a quedar pendiente.
 */
class RehacerCargaVerificacionesPorPedido extends Migration
{
    public function up()
    {
        Schema::dropIfExists('carga_verificaciones');

        Schema::create('carga_verificaciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->date('fecha');
            $tabla->string('placa', 100);
            $tabla->unsignedBigInteger('nro_pedido');
            $tabla->string('pedido_tipo', 20);
            $tabla->unsignedInteger('productos_esperados')->default(0);
            $tabla->decimal('total_esperado', 12, 2)->default(0);
            $tabla->boolean('verificado')->default(false);
            $tabla->string('observacion', 190)->nullable();
            $tabla->unsignedInteger('personal_id')->nullable();
            $tabla->string('verificado_por', 120)->nullable();
            $tabla->timestamp('verificado_en')->nullable();
            $tabla->timestamps();

            // Una canasta se revisa una sola vez por camion y dia; volver a
            // tocarla corrige la misma fila.
            $tabla->unique(['fecha', 'placa', 'nro_pedido', 'pedido_tipo'], 'carga_verificaciones_unica');
        });
    }

    public function down()
    {
        Schema::dropIfExists('carga_verificaciones');
    }
}
