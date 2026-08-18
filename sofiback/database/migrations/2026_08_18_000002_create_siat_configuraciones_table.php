<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de Impuestos (SIAT) con los que se piden CUIS, CUFD y CUF.
 *
 * Estaban repartidos entre el .env y los scripts sueltos de la carpeta
 * `impuestos/`. Aca viven en la base para que se puedan cambiar desde la
 * pantalla /impuestos sin tocar archivos ni reiniciar nada: el token delegado
 * caduca cada tanto y quien lo renueva no es quien despliega.
 *
 * Es una sola fila (id = 1). Si algun dia hay mas de una empresa emisora, se
 * agrega una columna `activa` y se filtra por ella.
 */
class CreateSiatConfiguracionesTable extends Migration
{
    public function up()
    {
        Schema::create('siat_configuraciones', function (Blueprint $table) {
            $table->id();

            $table->string('razon_social', 150)->nullable();
            $table->string('nit', 20);

            // Token delegado del SIAT: es un JWT largo, no entra en un varchar
            // corto. Se guarda tal cual; la pantalla lo muestra oculto.
            $table->text('token')->nullable();

            // 1 = PRODUCCION. Aca no se factura contra el piloto; la columna
            // existe porque siat_cuis y siat_cufds guardan con que ambiente se
            // pidio cada codigo.
            $table->unsignedTinyInteger('codigo_ambiente')->default(1);

            // 1 = ELECTRONICA EN LINEA, 2 = COMPUTARIZADA EN LINEA.
            $table->unsignedTinyInteger('codigo_modalidad')->default(2);

            $table->string('codigo_sistema', 60)->nullable();
            $table->unsignedInteger('codigo_sucursal')->default(0);
            $table->unsignedInteger('codigo_punto_venta')->default(0);

            // Servicios del SIAT (CUIS, CUFD, envio de facturas).
            $table->string('url_siat', 200)->default('https://siatrest.impuestos.gob.bo/v2/');
            // Portal publico: es la que se codifica en el QR de la factura.
            $table->string('url_siat2', 200)->default('https://siat.impuestos.gob.bo/');

            $table->timestamps();
        });

        // Primera fila con lo que ya estaba en config/siat.php (.env). El
        // token se siembra solo si esta en el .env; si no, se pega desde la
        // pantalla.
        DB::table('siat_configuraciones')->insert([
            'razon_social'       => config('siat.razon_social'),
            'nit'                => config('siat.nit'),
            'token'              => config('siat.token') ?: null,
            'codigo_ambiente'    => 1,
            'codigo_modalidad'   => config('siat.modalidad'),
            'codigo_sistema'     => config('siat.codigo_sistema'),
            'codigo_sucursal'    => config('siat.codigo_sucursal'),
            'codigo_punto_venta' => config('siat.codigo_punto_venta'),
            'url_siat'           => config('siat.url_siat'),
            'url_siat2'          => config('siat.url_siat2'),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('siat_configuraciones');
    }
}
