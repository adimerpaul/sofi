<?php

use App\Models\Factura;
use App\Services\ModificacionFactura;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de ediciones de un comprobante (editar = anular y reemitir).
 *
 * - modificacion_padre_id: el comprobante original de la cadena.
 * - modificacion_nro: numero de modificacion desde el padre (1, 2, 3...).
 * - modificacion_campos: JSON con lo que cambio respecto del anterior.
 *
 * Las cadenas que ya existian (pedido con ventas anuladas y reemitidas) se
 * completan aca mismo.
 */
class AddModificacionToFacturas extends Migration
{
    public function up()
    {
        Schema::table('facturas', function (Blueprint $table) {
            if (!Schema::hasColumn('facturas', 'modificacion_padre_id')) {
                $table->unsignedBigInteger('modificacion_padre_id')->nullable()->index();
            }
            if (!Schema::hasColumn('facturas', 'modificacion_nro')) {
                $table->unsignedInteger('modificacion_nro')->nullable();
            }
            if (!Schema::hasColumn('facturas', 'modificacion_campos')) {
                $table->longText('modificacion_campos')->nullable();
            }
        });

        // En orden de id: cada una toma el padre y el numero de su anterior,
        // que ya quedo completado en la vuelta previa.
        $servicio = new ModificacionFactura();
        Factura::whereNotNull('pedido_nro')
            ->whereNull('modificacion_nro')
            ->orderBy('id')
            ->get()
            ->each(function ($factura) use ($servicio) {
                $servicio->registrar($factura);
            });
    }

    public function down()
    {
        Schema::table('facturas', function (Blueprint $table) {
            foreach (['modificacion_padre_id', 'modificacion_nro', 'modificacion_campos'] as $columna) {
                if (Schema::hasColumn('facturas', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
}
