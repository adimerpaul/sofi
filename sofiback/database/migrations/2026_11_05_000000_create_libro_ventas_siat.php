<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Libro de ventas del SIAT importado desde Excel, para verificar las facturas
 * de Sofia contra lo que Impuestos tiene registrado.
 *
 * Es un registro aparte de facturas/facturas_ficticias: aca entran las
 * facturas que Impuestos reconoce, vengan o no de este sistema. El CUF
 * (columna "CODIGO DE AUTORIZACION") es la llave: lleva indice unico para que
 * reimportar el mismo archivo, o uno con meses solapados, no duplique nada.
 *
 * Tambien indexa facturas.cuf y facturas_ficticias.cuf, que es por donde se
 * cruzan las dos cosas.
 *
 * El permiso "verificacionFacturas" va aparte, en
 * 2026_11_05_000010_add_permission_verificacion_facturas.
 */
class CreateLibroVentasSiat extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('libro_ventas_siat')) {
            Schema::create('libro_ventas_siat', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('nro')->nullable();             // "Nº" de la fila en el archivo
                $table->date('fecha_factura')->index();
                $table->string('numero_factura', 30)->index();
                $table->string('cuf', 100)->unique();                   // codigo de autorizacion
                $table->string('nit_ci_cliente', 30)->nullable()->index();
                $table->string('complemento', 10)->nullable();
                $table->string('razon_social', 255)->nullable();
                $table->decimal('importe_total', 14, 2)->default(0);
                $table->decimal('importe_ice', 14, 2)->default(0);
                $table->decimal('importe_iehd', 14, 2)->default(0);
                $table->decimal('importe_ipj', 14, 2)->default(0);
                $table->decimal('tasas', 14, 2)->default(0);
                $table->decimal('otros_no_sujetos_iva', 14, 2)->default(0);
                $table->decimal('exportaciones_exentas', 14, 2)->default(0);
                $table->decimal('ventas_tasa_cero', 14, 2)->default(0);
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('descuentos', 14, 2)->default(0);
                $table->decimal('importe_gift_card', 14, 2)->default(0);
                $table->decimal('importe_base_debito_fiscal', 14, 2)->default(0);
                $table->decimal('debito_fiscal', 14, 2)->default(0);
                $table->string('estado', 20)->default('VALIDA')->index(); // VALIDA / ANULADA
                $table->string('codigo_control', 30)->nullable();
                $table->string('tipo_venta', 30)->nullable();
                $table->string('credito_fiscal', 5)->nullable();        // SI / NO
                $table->string('estado_consolidacion', 30)->nullable();
                $table->string('archivo_origen')->nullable();           // nombre del archivo importado
                $table->integer('user_id')->nullable();                 // personal.CodAut
                $table->timestamps();
                $table->softDeletes();

                $table->index(['fecha_factura', 'estado']);
            });
        }

        Schema::table('facturas', function (Blueprint $table) {
            $table->index('cuf', 'facturas_cuf_index');
        });
        Schema::table('facturas_ficticias', function (Blueprint $table) {
            $table->index('cuf', 'facturas_ficticias_cuf_index');
        });
    }

    public function down()
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropIndex('facturas_cuf_index');
        });
        Schema::table('facturas_ficticias', function (Blueprint $table) {
            $table->dropIndex('facturas_ficticias_cuf_index');
        });
        Schema::dropIfExists('libro_ventas_siat');
    }
}
