<?php

use App\Services\NumeroCamion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numero de salida de los camiones: un solo correlativo por año para todos,
 * que vuelve a 0001 cada 1 de enero. Arranca el 07/10/2026 (A1 0001, A2
 * 0002...) y cada dia sigue donde quedo el anterior; lo de antes queda sin
 * numero. Todos los pedidos
 * y comprobantes de un camion en un mismo dia llevan el mismo numero.
 *
 * Una vez dado no se recalcula: lo ya impreso no cambia. Lo que ya salio se
 * numera aca, por dia y dentro del dia por placa. Ver App\Services\NumeroCamion.
 */
class CreateCamionNumeros extends Migration
{
    public function up()
    {
        Schema::create('camion_numeros', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('placa', 50);
            // Dia de reparto (fecha_entrega).
            $table->date('fecha');
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('numero');
            $table->timestamps();

            $table->unique(['placa', 'fecha']);
            $table->unique(['anio', 'numero']);
        });

        // Los repartos que ya salieron: pedidos con camion y ventas directas
        // con camion, cada uno en su dia de entrega.
        $pedidos = DB::table('tbpedidos')
            ->whereNull('deleted_at')
            ->whereNotNull('fecha_entrega')
            ->whereRaw("UPPER(TRIM(estado)) = 'ENVIADO'")
            ->where('bonificacion', 0)
            ->whereRaw("TRIM(COALESCE(placa, '')) <> ''")
            ->selectRaw('DISTINCT CONVERT(TRIM(placa) USING utf8mb4) as placa, DATE(fecha_entrega) as fecha')
            ->get();

        $directas = DB::table('facturas')
            ->whereNull('deleted_at')
            ->whereNull('pedido_nro')
            ->whereNotNull('fecha_entrega')
            ->whereRaw("TRIM(COALESCE(placa, '')) <> ''")
            ->selectRaw('DISTINCT TRIM(placa) as placa, fecha_entrega as fecha')
            ->get();

        $salidas = $pedidos->merge($directas)
            ->map(function ($s) {
                return ['placa' => (string) $s->placa, 'fecha' => substr((string) $s->fecha, 0, 10)];
            })
            ->filter(function ($s) {
                // La numeracion arranca el 07/10/2026: lo anterior queda sin numero.
                return $s['placa'] !== '' && $s['fecha'] >= NumeroCamion::INICIO;
            })
            ->unique(function ($s) {
                return $s['placa'] . '|' . $s['fecha'];
            })
            ->sort(function ($a, $b) {
                return [$a['fecha'], $a['placa']] <=> [$b['fecha'], $b['placa']];
            });

        $ahora = date('Y-m-d H:i:s');
        $ultimo = [];
        $filas = [];
        foreach ($salidas as $s) {
            $anio = (int) substr($s['fecha'], 0, 4);
            $ultimo[$anio] = ($ultimo[$anio] ?? 0) + 1;
            $filas[] = [
                'placa' => $s['placa'],
                'fecha' => $s['fecha'],
                'anio' => $anio,
                'numero' => $ultimo[$anio],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        foreach (array_chunk($filas, 500) as $lote) {
            DB::table('camion_numeros')->insert($lote);
        }
    }

    public function down()
    {
        Schema::dropIfExists('camion_numeros');
    }
}
