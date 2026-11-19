<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hora limite para mandar pedidos por tipo (Facturacion > Horarios de envio).
 *
 * El pedido se puede registrar igual despues de la hora, pero queda marcado
 * como fuera de horario (tbpedidos.fuera_horario) y oculto en Facturacion >
 * Pedidos. Con "habilitado" encendido se sigue mandando normal despues de la
 * hora (para un dia en que se acepta tarde).
 *
 * No es tbhorariosenvio: esa es la del envio automatico de pedidos CREADO.
 */
class CreateHorariosPedido extends Migration
{
    public function up()
    {
        Schema::create('horarios_pedido', function (Blueprint $table) {
            $table->id();
            // Tipo de tbpedidos.tipo: NORMAL (embutidos), POLLO, CERDO.
            $table->string('tipo', 20)->unique();
            $table->string('nombre', 40);
            $table->time('hora_limite');
            // Encendido: despues de la hora se sigue mandando sin marcar.
            $table->boolean('habilitado')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->unsignedInteger('actualizado_por')->nullable();
            $table->timestamps();
        });

        $ahora = now();
        foreach ([
            ['NORMAL', 'EMBUTIDOS', '19:00:00', 1],
            ['POLLO', 'POLLO', '23:00:00', 2],
            ['CERDO', 'CERDO', '23:00:00', 3],
        ] as [$tipo, $nombre, $hora, $orden]) {
            DB::table('horarios_pedido')->insert([
                'tipo' => $tipo, 'nombre' => $nombre, 'hora_limite' => $hora, 'habilitado' => false,
                'orden' => $orden, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        Schema::table('tbpedidos', function (Blueprint $table) {
            $table->boolean('fuera_horario')->default(false);
        });
    }

    public function down()
    {
        Schema::table('tbpedidos', function (Blueprint $table) {
            $table->dropColumn('fuera_horario');
        });
        Schema::dropIfExists('horarios_pedido');
    }
}
