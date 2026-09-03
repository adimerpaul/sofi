<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El visto bueno del caminero sobre lo que sube a su camion.
 *
 * Antes de salir revisa la carga producto por producto: la lista sale de sumar
 * todos los pedidos del dia que van en su placa, y aca queda quien la reviso,
 * cuando, cuanto recibio de verdad y que anoto si algo no cuadraba. Mientras
 * no este todo verificado, caja no puede imprimir los comprobantes de ese
 * camion.
 *
 * Una fila por producto de la carga: la clave es el cod_prod del legado, o
 * POLLO|<nombre>|<unidad> para lo que en tbpedidos vive en columnas y no
 * tiene codigo de producto.
 *
 * cantidad_esperada guarda cuanto pedia la carga en el momento del visto
 * bueno: si despues entra otro pedido el total cambia y la fila deja de valer,
 * que es justo lo que hay que volver a mirar antes de imprimir.
 */
class CreateCargaVerificacionesTable extends Migration
{
    public function up()
    {
        Schema::create('carga_verificaciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->date('fecha');
            $tabla->string('placa', 100);
            $tabla->string('clave', 190);
            $tabla->string('producto', 190);
            $tabla->string('unidad', 20)->nullable();
            $tabla->decimal('cantidad_esperada', 14, 3)->default(0);
            $tabla->decimal('cantidad_recibida', 14, 3)->nullable();
            $tabla->boolean('verificado')->default(false);
            $tabla->string('observacion', 190)->nullable();
            $tabla->unsignedInteger('personal_id')->nullable();
            $tabla->string('verificado_por', 120)->nullable();
            $tabla->timestamp('verificado_en')->nullable();
            $tabla->timestamps();

            // Un producto se verifica una sola vez por camion y dia; volver a
            // tocarlo corrige la misma fila.
            $tabla->unique(['fecha', 'placa', 'clave'], 'carga_verificaciones_unica');
        });
    }

    public function down()
    {
        Schema::dropIfExists('carga_verificaciones');
    }
}
