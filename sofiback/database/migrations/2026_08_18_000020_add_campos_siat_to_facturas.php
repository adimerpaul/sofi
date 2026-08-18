<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de la emision al SIAT en cada factura.
 *
 * `cuf`, `cufd`, `nro_factura`, `leyenda` y `online` ya existian reservados
 * desde que se creo la tabla. Faltaba todo lo que devuelve o exige el SIAT
 * para poder rastrear una emision:
 *
 *   - la serie (sucursal y punto de venta) con la que salio el numero, porque
 *     el correlativo es por serie y sin eso no se sabe a cual pertenece;
 *   - el codigo de recepcion, que es el acuse con el que se reclama;
 *   - la fecha de emision con milisegundos, que entra en el calculo del CUF y
 *     no se puede reconstruir despues desde un datetime normal;
 *   - el XML enviado, que es la unica prueba de que se mando.
 */
class AddCamposSiatToFacturas extends Migration
{
    public function up()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->unsignedInteger('codigo_sucursal')->nullable()->after('nro_factura');
            $table->unsignedInteger('codigo_punto_venta')->nullable()->after('codigo_sucursal');

            // Codigo de control del CUFD usado; es lo que se le pega al CUF.
            $table->string('codigo_control', 100)->nullable()->after('cufd');

            // Acuse del SIAT y en que quedo la factura para ellos.
            $table->string('codigo_recepcion', 100)->nullable()->after('codigo_control');
            $table->string('estado_siat', 25)->nullable()->after('codigo_recepcion');
            $table->text('mensaje_siat')->nullable()->after('estado_siat');

            // Con milisegundos (Y-m-d\TH:i:s.SSS): asi se firmo el CUF.
            $table->string('fecha_emision', 30)->nullable()->after('mensaje_siat');

            $table->mediumText('xml')->nullable()->after('fecha_emision');

            $table->index(['codigo_sucursal', 'codigo_punto_venta', 'nro_factura'], 'facturas_serie_index');
            $table->index('estado_siat');
        });
    }

    public function down()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropIndex('facturas_serie_index');
            $table->dropIndex(['estado_siat']);
            $table->dropColumn([
                'codigo_sucursal',
                'codigo_punto_venta',
                'codigo_control',
                'codigo_recepcion',
                'estado_siat',
                'mensaje_siat',
                'fecha_emision',
                'xml',
            ]);
        });
    }
}
