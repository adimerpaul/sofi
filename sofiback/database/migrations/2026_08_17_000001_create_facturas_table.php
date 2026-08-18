<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cabecera de las ventas/facturas hechas desde el sistema web.
 *
 * Es un modelo maestro-detalle propio: no toca tbventas, que es la tabla plana
 * del sistema de caja de escritorio. Las claves si apuntan a las tablas
 * legadas que ya son el maestro de cada cosa en Sofia, para no duplicar
 * clientes ni personal:
 *
 *   cliente_id -> tbclientes.Cod_Aut
 *   user_id    -> personal.CodAut  (el modelo User usa la tabla personal)
 *
 * El vendedor se guarda por su CI y no por id porque tbclientes.CiVend, que es
 * de donde sale, tambien referencia al personal por CI.
 */
class CreateFacturasTable extends Migration
{
    public function up()
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();

            // Quien la registro. Ojo con el tipo: personal.CodAut y
            // tbclientes.Cod_Aut son int SIGNED, asi que estas columnas no
            // pueden ser unsigned o MySQL rechaza la foreign key (error 3780).
            $table->integer('user_id');
            $table->foreign('user_id')->references('CodAut')->on('personal');

            $table->integer('cliente_id')->nullable();
            $table->foreign('cliente_id')->references('Cod_Aut')->on('tbclientes');

            // CI del vendedor asignado al cliente (tbclientes.CiVend).
            $table->string('vendedor_ci', 15)->nullable();

            $table->date('fecha');
            $table->time('hora');

            // Se copian del cliente para que la factura no cambie si manana
            // editan la ficha del cliente.
            $table->string('nit', 20)->nullable();
            $table->string('nombre', 150)->nullable();

            // VENTA (voucher) o FACTURA.
            $table->string('tipo_comprobante', 20)->default('VENTA');
            $table->string('tipo_pago', 20)->default('EFECTIVO');
            $table->string('estado', 20)->default('ACTIVO');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('observacion', 255)->nullable();

            // Datos fiscales, para cuando se emita al SIAT desde aca.
            $table->integer('nro_factura')->nullable();
            $table->string('cuf', 200)->nullable();
            $table->string('cufd', 200)->nullable();
            $table->string('leyenda', 255)->nullable();
            $table->boolean('online')->default(false);

            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamp('anulado_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['fecha', 'estado']);
            $table->index('tipo_comprobante');
            $table->index('nit');
        });
    }

    public function down()
    {
        Schema::dropIfExists('facturas');
    }
}
