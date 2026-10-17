<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Numero de salida de un camion en un dia de reparto.
 *
 * Un solo correlativo por año para todos los camiones, que vuelve a 1 cada
 * año: cada camion en cada dia toma el siguiente (A1 0001, A2 0002... y al
 * dia siguiente sigue donde quedo). Se define por placa + fecha de entrega,
 * asi que todos los pedidos y comprobantes del camion ese dia comparten el
 * numero. Una vez dado queda fijo (tabla camion_numeros).
 */
class NumeroCamion
{
    /**
     * Primer dia de reparto con numero (el 0001). Lo anterior queda sin
     * numero; desde aca el correlativo vuelve a 0001 cada 1 de enero.
     */
    const INICIO = '2026-10-07';

    /** Numero de la placa en el dia; lo crea si todavia no tiene. Null sin camion. */
    public static function de($placa, $fecha)
    {
        $placa = trim((string) $placa);
        $fecha = substr((string) $fecha, 0, 10);
        // 'SIN' es como algunas pantallas agrupan lo que no va en camion.
        if ($placa === '' || strtoupper($placa) === 'SIN' || !strtotime($fecha) || $fecha < self::INICIO) {
            return null;
        }

        $existente = self::buscar($placa, $fecha);
        if ($existente) {
            return $existente;
        }

        $anio = (int) substr($fecha, 0, 4);
        // Dos cajas pueden pedirlo a la vez: los indices unicos frenan al
        // segundo, que vuelve a probar o se queda con el que grabo el primero.
        for ($intento = 0; $intento < 5; $intento++) {
            try {
                $siguiente = (int) DB::table('camion_numeros')->where('anio', $anio)->max('numero') + 1;
                DB::table('camion_numeros')->insert([
                    'placa' => $placa,
                    'fecha' => $fecha,
                    'anio' => $anio,
                    'numero' => $siguiente,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return $siguiente;
            } catch (QueryException $e) {
                if ($existente = self::buscar($placa, $fecha)) {
                    return $existente;
                }
            }
        }

        return null;
    }

    /** Numero ya asignado, sin crear nada. */
    public static function buscar($placa, $fecha)
    {
        $numero = DB::table('camion_numeros')
            ->where('placa', trim((string) $placa))
            ->where('fecha', substr((string) $fecha, 0, 10))
            ->value('numero');

        return $numero ? (int) $numero : null;
    }

    /** Como se imprime: 0001 ... 9999, 10000. */
    public static function formato($numero)
    {
        return $numero ? str_pad((string) $numero, 4, '0', STR_PAD_LEFT) : '';
    }
}
