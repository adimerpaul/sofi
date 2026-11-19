<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Hora limite para mandar pedidos por tipo (tabla horarios_pedido).
 *
 * Un pedido del dia que se registra despues de la hora de su tipo se guarda
 * igual, pero sus filas quedan con fuera_horario = 1 y oculto = 1: no sale en
 * Facturacion > Pedidos hasta que alguien lo restablezca. Si el tipo tiene
 * "habilitado" encendido se sigue mandando normal. Cada fila se mira por su
 * propio tbpedidos.tipo (un pedido puede llevar embutidos y pollo).
 */
class HorarioPedido
{
    /** tipo => fila de horarios_pedido, leida una vez por request. */
    private static $horarios = null;

    public static function horarios()
    {
        if (self::$horarios === null) {
            self::$horarios = DB::table('horarios_pedido')->get()->keyBy(function ($h) {
                return strtoupper(trim($h->tipo));
            })->all();
        }

        return self::$horarios;
    }

    /** El horario del tipo si ahora ya paso su hora limite y no esta habilitado. */
    public static function vencido($tipo)
    {
        $h = self::horarios()[strtoupper(trim((string) $tipo))] ?? null;
        if (!$h || $h->habilitado) {
            return null;
        }

        return date('H:i:s') >= $h->hora_limite ? $h : null;
    }

    /**
     * Marca las filas a insertar. Solo cuenta un pedido con fecha de hoy (uno
     * para otro dia se esta adelantando). $tiposPrevios: tipos que ya estaban
     * fuera de horario (al modificar se conserva la marca).
     * Devuelve los nombres de los tipos que quedaron fuera de horario.
     */
    public static function marcar(array &$filas, array $tiposPrevios = [])
    {
        $fuera = [];
        $hoy = date('Y-m-d');
        foreach ($filas as &$fila) {
            $tipo = strtoupper(trim((string) ($fila['tipo'] ?? '')));
            $h = substr((string) ($fila['fecha'] ?? ''), 0, 10) === $hoy ? self::vencido($tipo) : null;
            if ($h || in_array($tipo, $tiposPrevios, true)) {
                $fila['fuera_horario'] = 1;
                $fila['oculto'] = 1;
                $nombre = $h ? $h->nombre : (self::horarios()[$tipo]->nombre ?? $tipo);
                $fuera[$nombre] = $nombre . ($h ? ' (después de las ' . substr($h->hora_limite, 0, 5) . ')' : '');
            }
        }
        unset($fila);

        return array_values($fuera);
    }

    /** Aviso para la pantalla, o null si todo entro en horario. */
    public static function aviso(array $fuera)
    {
        return $fuera
            ? 'Pedido mandado FUERA DE HORARIO: ' . implode(', ', $fuera) . '. No saldrá en Facturación > Pedidos hasta que lo restablezcan.'
            : null;
    }
}
