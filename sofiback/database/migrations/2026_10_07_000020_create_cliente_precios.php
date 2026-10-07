<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Que precio (de los 13 del producto) se le cobra a cada cliente en cada
 * grupo de productos. Por defecto el precio 1; se cambia por cliente y grupo
 * en la pestaña Precios de Clientes, y queda quien lo creo y quien lo cambio.
 *
 * Se carga una fila por cada cliente y cada grupo que ya existen, con el
 * precio 1.
 */
class CreateClientePrecios extends Migration
{
    public function up()
    {
        Schema::create('cliente_precios', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('cliente_id');
            $table->string('cod_grup', 10);
            // 1 = Precio, 2 = Precio_Costo, 3..13 = Precio3..Precio13.
            $table->unsignedTinyInteger('precio')->default(1);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['cliente_id', 'cod_grup']);
            $table->index('cod_grup');
        });

        $ahora = date('Y-m-d H:i:s');
        DB::statement("
            INSERT INTO cliente_precios (cliente_id, cod_grup, precio, created_at, updated_at)
            SELECT c.Cod_Aut, TRIM(g.Cod_grup), 1, ?, ?
            FROM tbclientes c
            CROSS JOIN (SELECT DISTINCT TRIM(Cod_grup) AS Cod_grup FROM tbgrupos WHERE TRIM(Cod_grup) <> '') g
        ", [$ahora, $ahora]);
    }

    public function down()
    {
        Schema::dropIfExists('cliente_precios');
    }
}
