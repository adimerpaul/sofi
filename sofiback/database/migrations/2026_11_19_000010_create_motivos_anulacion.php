<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Motivos de anulacion de facturas (Facturacion > Motivos de anulacion).
 *
 * Antes eran una lista fija en FacturacionController::MOTIVOS_ANULACION_INTERNOS;
 * se cargan aqui los mismos, en el mismo orden. En la factura se guarda solo
 * el texto (facturas.anulacion_observacion), asi que editar o borrar un motivo
 * no cambia las facturas ya anuladas.
 */
class CreateMotivosAnulacion extends Migration
{
    private const MOTIVOS = [
        'CALIDAD DE PRODUCTO',
        'CANCELO PEDIDO EL CLIENTE/VENDEDOR',
        'CERRADO - NO CONTESTA',
        'ERROR DE DIGITACION',
        'FECHA PRONTO A VENCER',
        'NO QUISO',
        'NO RECOGIO',
        'NO TIENE DINERO',
        'PEDIDO INCORRECTO VENTAS',
        'PEDIDO INCORRECTO DESPACHOS',
        'TARDE',
        'ERROR DE PRECIO',
        'MERMA',
        'LOCAL NO UBICADO',
        'NO HAY SALIDAS A PROVINCIA',
        'BLOQUEOS',
        'POR DEVOLUCION PARCIAL',
    ];

    public function up()
    {
        Schema::create('motivos_anulacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150)->unique();
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });

        $ahora = now();
        foreach (self::MOTIVOS as $i => $nombre) {
            DB::table('motivos_anulacion')->insert([
                'nombre' => $nombre, 'activo' => true, 'orden' => $i + 1,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('motivos_anulacion');
    }
}
