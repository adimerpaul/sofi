<?php

namespace App\Console\Commands;

use App\Models\ImpuestoFalla;
use App\Services\Impuestos\CufdService;
use Illuminate\Console\Command;

class GenerarCufdDiarioCommand extends Command
{
    protected $signature = 'impuestos:generar-cufd-diario';
    protected $description = 'Genera el CUFD diario (automatico/manual) y registra fallas cuando corresponda';

    public function handle(CufdService $service): int
    {
        try {
            $result = $service->generateCufdDaily();
            $status = $result['status'] ?? null;
            $message = $result['message'] ?? 'Proceso ejecutado';

            if ($status === 'created') {
                ImpuestoFalla::query()
                    ->where('tipo', 'CUFD')
                    ->where('estado', 'pendiente')
                    ->update([
                        'estado' => 'resuelto',
                        'resolved_at' => now(),
                    ]);

                $this->info($message);
                return self::SUCCESS;
            }

            if ($status === 'skipped') {
                ImpuestoFalla::query()->create([
                    'tipo' => 'CUFD',
                    'mensaje' => $message,
                    'detalle' => [
                        'reason' => 'already_exists_today',
                    ],
                    'estado' => 'pendiente',
                    'fecha_evento' => now(),
                ]);

                $this->warn($message);
                return self::FAILURE;
            }

            $this->info($message);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            ImpuestoFalla::query()->create([
                'tipo' => 'CUFD',
                'mensaje' => 'Fallo la generacion automatica de CUFD',
                'detalle' => [
                    'error' => $e->getMessage(),
                ],
                'estado' => 'pendiente',
                'fecha_evento' => now(),
            ]);

            $this->error('Error CUFD: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
