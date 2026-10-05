<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carga las cuentas por cobrar del sistema anterior como deudas (creditos_manuales).
 *
 * Despues del arranque en limpio (2026_10_05_000010) los creditos quedaron vacios;
 * lo que los clientes ya debian sale del reporte "Cuentas por cobrar debito sumado"
 * del sistema anterior, copiado en database/data/saldos_anteriores_2026_10_05.csv.
 * Se carga la DEUDA (lo que falta pagar) de cada comanda; el importe original, lo
 * pagado a cuenta, la empresa, el vendedor y la fecha del ultimo pago quedan como
 * referencia en columnas nuevas.
 *
 * El cliente se busca por el NIT con el que tbctascobrar registro esa comanda y, si
 * no aparece, por el nombre exacto. Las filas sin cliente no se cargan y se listan.
 *
 * Se puede volver a correr: cada comanda tiene un solicitud_id fijo y no se duplica.
 */
class ImportarSaldosAnteriores extends Migration
{
    private $archivo = 'database/data/saldos_anteriores_2026_10_05.csv';

    public function up()
    {
        Schema::table('creditos_manuales', function (Blueprint $table) {
            $table->unsignedInteger('comanda')->nullable()->after('concepto')->index();
            $table->decimal('importe', 12, 2)->nullable()->after('monto');
            $table->decimal('a_cuenta', 12, 2)->nullable()->after('importe');
            $table->string('empresa', 150)->nullable()->after('a_cuenta');
            $table->string('vendedor', 150)->nullable()->after('empresa');
            $table->dateTime('ultimo_pago')->nullable()->after('vendedor');
        });

        $norm = function ($s) {
            return preg_replace('/\s+/', ' ', mb_strtoupper(trim((string) $s)));
        };
        $numero = function ($s) {
            return round((float) str_replace(',', '.', trim($s)), 2);
        };

        $f = fopen(base_path($this->archivo), 'r');
        fgetcsv($f, 0, ';'); // encabezado

        $cargadas = 0;
        $total = 0;
        $sinCliente = [];
        $ahora = now();

        DB::transaction(function () use ($f, $norm, $numero, $ahora, &$cargadas, &$total, &$sinCliente) {
            while (($r = fgetcsv($f, 0, ';')) !== false) {
                if (count($r) < 8) {
                    continue;
                }
                [$comanda, $nombre, $empresa, $importe, $aCuenta, $deuda, $fechaPago, $vendedor] = $r;
                $deuda = $numero($deuda);
                if ($deuda <= 0) {
                    continue;
                }

                $solicitud = $this->uuid('saldo-anterior-' . $comanda);
                if (DB::table('creditos_manuales')->where('solicitud_id', $solicitud)->exists()) {
                    continue;
                }

                $cliente = $this->buscarCliente((int) $comanda, $nombre, $norm);
                if (!$cliente) {
                    $sinCliente[] = $comanda . ' ' . $nombre;
                    continue;
                }

                $ultimoPago = DateTime::createFromFormat('j/n/Y H:i', trim($fechaPago)) ?: null;
                // La deuda lleva la fecha de la venta; si no esta, la del ultimo pago.
                $fecha = DB::table('tbctascobrar')->where('comanda', $comanda)->min('FechaEntreg')
                    ?: ($ultimoPago ? $ultimoPago->format('Y-m-d') : $ahora->toDateString());

                DB::table('creditos_manuales')->insert([
                    'cliente_id'   => $cliente,
                    'fecha'        => substr($fecha, 0, 10),
                    'concepto'     => 'Saldo anterior · Comanda #' . $comanda,
                    'comanda'      => (int) $comanda,
                    'monto'        => $deuda,
                    'importe'      => $numero($importe),
                    'a_cuenta'     => $numero($aCuenta),
                    'empresa'      => trim($empresa) !== '' ? trim($empresa) : null,
                    'vendedor'     => trim($vendedor) !== '' ? $norm($vendedor) : null,
                    'ultimo_pago'  => $ultimoPago ? $ultimoPago->format('Y-m-d H:i:s') : null,
                    'user_id'      => 0, // carga del sistema, no de un usuario
                    'solicitud_id' => $solicitud,
                    'created_at'   => $ahora,
                    'updated_at'   => $ahora,
                ]);
                $cargadas++;
                $total += $deuda;
            }
        });
        fclose($f);

        echo "  Saldos anteriores cargados: {$cargadas} (Bs " . number_format($total, 2) . ")\n";
        foreach ($sinCliente as $fila) {
            echo "  SIN CLIENTE, no se cargo: {$fila}\n";
        }
    }

    public function down()
    {
        // Solo lo que cargo esta migracion: las deudas a mano no llevan comanda.
        DB::table('creditos_manuales')->whereNotNull('comanda')->delete();

        Schema::table('creditos_manuales', function (Blueprint $table) {
            $table->dropIndex(['comanda']);
            $table->dropColumn(['comanda', 'importe', 'a_cuenta', 'empresa', 'vendedor', 'ultimo_pago']);
        });
    }

    /** Por el NIT de la comanda en tbctascobrar; si no, por el nombre exacto. */
    private function buscarCliente($comanda, $nombre, $norm)
    {
        $nit = DB::table('tbctascobrar')->where('comanda', $comanda)->orderByDesc('CodAuto')->value('CINIT');
        if ($nit !== null && trim($nit) !== '') {
            $candidatos = DB::table('tbclientes')->whereRaw('TRIM(Id) = ?', [trim($nit)])->get(['Cod_Aut', 'Nombres']);
            if ($candidatos->count() === 1) {
                return $candidatos[0]->Cod_Aut;
            }
            $mismo = $candidatos->first(function ($c) use ($norm, $nombre) {
                return $norm($c->Nombres) === $norm($nombre);
            });
            if ($mismo) {
                return $mismo->Cod_Aut;
            }
        }

        $porNombre = DB::table('tbclientes')
            ->whereRaw("TRIM(REGEXP_REPLACE(Nombres, '[[:space:]]+', ' ')) = ?", [$norm($nombre)])
            ->pluck('Cod_Aut');
        return $porNombre->count() === 1 ? $porNombre[0] : null;
    }

    /** UUID fijo por comanda, para que volver a correrla no duplique. */
    private function uuid($texto)
    {
        $h = md5($texto);
        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-4' . substr($h, 13, 3) . '-a'
            . substr($h, 17, 3) . '-' . substr($h, 20, 12);
    }
}
