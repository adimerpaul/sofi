<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canastillos con los que se pesa el pollo, el cerdo y la res.
 *
 * Esa carne se pesa dentro de los canastillos, asi que la balanza marca el
 * peso bruto. Cada canastillo pesa 2 kg: lo que se cobra es el peso neto,
 * bruto menos los canastillos. La columna peso sigue siendo lo cobrado (el
 * neto), para que stock, SIAT y reportes no cambien; aca solo queda de donde
 * salio ese neto, para imprimirlo en la boleta como en el papel de siempre.
 *
 * Nullable: lo que no se pesa con canastillos, y todo lo ya emitido, queda
 * en null.
 */
class AddCanastillosToFacturaDetalles extends Migration
{
    public function up()
    {
        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->decimal('peso_bruto', 12, 3)->nullable()->after('peso');
            $table->unsignedInteger('canastillos')->nullable()->after('peso_bruto');
        });
    }

    public function down()
    {
        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->dropColumn(['peso_bruto', 'canastillos']);
        });
    }
}
