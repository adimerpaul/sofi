<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eventos significativos registrados en el SIAT (contingencia).
 *
 * Cuando una factura no pudo salir en linea (se cayo internet o el servicio
 * de Impuestos), se manda despues "fuera de linea" en un paquete asociado a
 * un evento: asi conserva su fecha y hora de emision original. Se guarda el
 * evento para poder reintentar el paquete con el mismo codigo si falla, en
 * vez de registrar otro evento encima del mismo periodo.
 */
class CreateSiatEventosSignificativos extends Migration
{
    public function up()
    {
        if (Schema::hasTable('siat_eventos_significativos')) {
            return;
        }

        Schema::create('siat_eventos_significativos', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('codigo_motivo');               // catalogo del SIAT, 1..7
            $table->string('descripcion', 255);
            $table->dateTime('inicio');                                 // fechaHoraInicioEvento
            $table->dateTime('fin');                                    // fechaHoraFinEvento
            $table->string('cufd', 200);                                // CUFD vigente al registrar
            $table->string('cufd_evento', 200)->index();                // CUFD del momento del corte
            $table->integer('codigo_sucursal')->default(0);
            $table->integer('codigo_punto_venta')->default(0);
            $table->string('codigo_evento', 50)->nullable();            // codigoRecepcionEventoSignificativo
            $table->string('codigo_recepcion', 100)->nullable();        // del paquete
            $table->string('estado', 30)->default('PENDIENTE');         // PENDIENTE / REGISTRADO / VALIDADO / OBSERVADO / ERROR
            $table->text('mensaje')->nullable();
            $table->json('factura_ids')->nullable();
            $table->integer('user_id')->nullable();                     // personal.CodAut
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('siat_eventos_significativos');
    }
}
