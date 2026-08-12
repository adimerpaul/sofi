<?php

namespace App\Console\Commands;

use App\Models\HorarioEnvio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnviarPedidosAutomatico extends Command
{
    protected $signature = 'pedidos:enviar-automatico {--force : Ejecuta sin importar el horario configurado}';

    protected $description = 'Envia automaticamente los pedidos CREADO del dia segun los horarios de tbhorariosenvio';

    public function handle()
    {
        $ahora = now();
        $diaIso = (string) $ahora->dayOfWeekIso; // 1=lunes ... 7=domingo

        $horarios = HorarioEnvio::where('activo', 1)->get();

        foreach ($horarios as $horario) {
            $dias = array_map('trim', explode(',', $horario->dias));

            $debeEjecutar = $this->option('force')
                || (in_array($diaIso, $dias)
                    && $ahora->format('H:i:s') >= $horario->hora
                    && $horario->ultima_ejecucion != $ahora->toDateString());

            if (!$debeEjecutar) {
                continue;
            }

            $enviados = DB::table('tbpedidos as p')
                ->where('p.estado', 'CREADO')
                ->where('p.bonificacion', 0)
                ->whereDate('p.fecha', $ahora->toDateString())
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('tbclientes as c')
                        ->whereColumn('c.Cod_Aut', 'p.idCli')
                        ->where('c.venta', 'ACTIVO');
                })
                ->update([
                    'p.estado' => 'ENVIADO',
                    'p.envio' => now(),
                    'p.enviado_sistema' => 1,
                ]);

            $horario->ultima_ejecucion = $ahora->toDateString();
            $horario->pedidos_enviados = $enviados;
            $horario->save();

            $this->info("Horario {$horario->hora}: {$enviados} filas de pedidos enviadas.");
        }

        return 0;
    }
}
