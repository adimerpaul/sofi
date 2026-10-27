<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturacion ficticia: facturas que se emiten al SIAT sin pasar por la
 * facturacion del sistema.
 *
 * Viven aparte de facturas/factura_detalles a proposito: no son ventas, no
 * descuentan inventario (tbstock / stock_actual), no entran a camiones,
 * entregas ni cobranzas. Lo unico que comparten con la facturacion real es la
 * serie del SIAT (mismo NIT, sucursal y punto de venta), por eso
 * SiatService::siguienteNumero() mira tambien esta tabla.
 *
 * Las columnas son las mismas que usa SiatService al emitir y anular, para
 * poder reutilizarlo tal cual (ver App\Models\FacturaFicticia).
 */
class CreateFacturasFicticias extends Migration
{
    public function up()
    {
        Schema::create('facturas_ficticias', function (Blueprint $table) {
            $table->id();

            // Mismo cuidado que en facturas: personal.CodAut y
            // tbclientes.Cod_Aut son int SIGNED.
            $table->integer('user_id');
            $table->foreign('user_id')->references('CodAut')->on('personal');

            $table->integer('cliente_id')->nullable();
            $table->foreign('cliente_id')->references('Cod_Aut')->on('tbclientes');

            $table->date('fecha');
            $table->time('hora');

            $table->string('nit', 20)->nullable();
            $table->string('nombre', 150)->nullable();

            $table->string('tipo_pago', 20)->default('EFECTIVO');
            $table->string('estado', 20)->default('ACTIVO');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('observacion', 255)->nullable();

            // Datos fiscales: los llena SiatService.
            $table->integer('nro_factura')->nullable();
            $table->integer('codigo_sucursal')->nullable();
            $table->integer('codigo_punto_venta')->nullable();
            $table->string('cuf', 200)->nullable();
            $table->string('cufd', 200)->nullable();
            $table->string('codigo_control', 50)->nullable();
            $table->string('codigo_recepcion', 100)->nullable();
            $table->string('estado_siat', 30)->nullable();
            $table->text('mensaje_siat')->nullable();
            $table->string('fecha_emision', 30)->nullable();
            $table->longText('xml')->nullable();
            $table->string('leyenda', 255)->nullable();
            $table->boolean('online')->default(false);

            $table->string('motivo_anulacion', 255)->nullable();
            $table->string('anulacion_observacion', 255)->nullable();
            $table->timestamp('anulado_at')->nullable();
            $table->integer('anulado_por')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['fecha', 'estado']);
            $table->index(['codigo_sucursal', 'codigo_punto_venta', 'nro_factura'], 'ff_serie_index');
            $table->index('nit');
        });

        Schema::create('factura_ficticia_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('factura_ficticia_id')->constrained('facturas_ficticias');

            // Copia del producto al guardar, para reimprimir igual aunque el
            // producto cambie. cod_prod solo sirve para sacar los codigos del
            // SIAT de tbproductos; no se toca su stock.
            $table->string('cod_prod', 30);
            $table->string('nombre', 200);
            $table->string('unidad', 30)->nullable();

            $table->decimal('cantidad', 12, 3)->default(0);
            $table->decimal('precio', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('factura_ficticia_detalles');
        Schema::dropIfExists('facturas_ficticias');
    }
}
