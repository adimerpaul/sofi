<?php

namespace App\Http\Controllers;

use App\Services\RecojoDelDia;
use Illuminate\Http\Request;

/**
 * El recojo del dia visto desde cobranzas.
 *
 * Es el mismo reporte que el caminero mira de su camion, pero de todos: quien
 * recibe la plata al final del dia necesita cuadrar camion por camion sin
 * tener que entrar con el usuario de cada uno.
 *
 * Todo el calculo y las hojas salen de RecojoDelDia, el mismo servicio que usa
 * el caminero, para que lo que aca se reclama sea exactamente lo que alla se
 * firmo.
 */
class CobranzaRecojoController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:cobranzasrecojo');
    }

    /**
     * El recojo del dia. Sin camion vienen todos, cada uno con sus grupos y
     * totales, mas un total general del dia.
     */
    public function reporte(Request $request, RecojoDelDia $recojo)
    {
        $datos = $request->validate([
            'fecha' => 'nullable|date',
            'camion' => 'nullable|string|max:100',
        ]);

        $fecha = $datos['fecha'] ?? date('Y-m-d');
        $camion = trim((string) ($datos['camion'] ?? ''));

        $filas = $recojo->filas($fecha, $camion ?: null);

        // Un bloque por camion: es como se cuenta la plata, un caminero a la
        // vez, y es tambien como se imprime.
        $camiones = [];
        foreach ($filas->groupBy('placa') as $placa => $delCamion) {
            $grupos = $recojo->agrupar($delCamion);

            $camiones[] = [
                'placa' => $placa,
                'caminero' => $recojo->caminero($delCamion, $placa),
                'grupos' => $grupos,
                'totales' => $recojo->totales($grupos, $delCamion->where('estado', 'ENTREGADO')),
                'notas' => $delCamion->count(),
            ];
        }

        $todos = $recojo->agrupar($filas);

        return [
            'fecha' => $fecha,
            'camion' => $camion ?: null,
            'camiones' => $camiones,
            // El total del dia, sumando todos los camiones que se muestran.
            'totales' => $recojo->totales($todos, $filas->where('estado', 'ENTREGADO')),
            'notas' => $filas->count(),
        ];
    }

    /** Los camiones que trajeron algo ese dia, para el filtro de la pantalla. */
    public function camiones(Request $request, RecojoDelDia $recojo)
    {
        $datos = $request->validate(['fecha' => 'nullable|date']);

        return $recojo->camiones($datos['fecha'] ?? date('Y-m-d'));
    }

    /**
     * Las mismas hojas que imprime el caminero, pero de cualquier camion.
     *
     * Sin camion salen las de todos, una detras de otra y agrupadas por
     * camion: cada hoja se firma por separado, asi que no se mezclan notas de
     * dos camiones en la misma.
     */
    public function reportePdf(Request $request, RecojoDelDia $recojo)
    {
        $datos = $request->validate([
            'fecha' => 'nullable|date',
            'camion' => 'nullable|string|max:100',
            'grupo' => 'nullable|string',
        ]);

        $fecha = $datos['fecha'] ?? date('Y-m-d');
        $camion = trim((string) ($datos['camion'] ?? ''));
        $grupo = $datos['grupo'] ?? 'todos';

        if ($grupo !== 'todos' && !isset(RecojoDelDia::HOJAS[$grupo])) {
            return response()->json(['message' => 'Esa hoja no existe'], 422);
        }

        $filas = $recojo->filas($fecha, $camion ?: null);
        $claves = $grupo === 'todos' ? array_keys(RecojoDelDia::HOJAS) : [$grupo];
        $hojas = [];

        foreach ($filas->groupBy('placa') as $placa => $delCamion) {
            $grupos = $recojo->agrupar($delCamion);
            $caminero = $recojo->caminero($delCamion, $placa);

            foreach ($claves as $clave) {
                // Las hojas que quedaron sin ninguna nota no se imprimen: en
                // papel esa hoja simplemente no se entrega.
                if ($grupos[$clave]->isEmpty()) {
                    continue;
                }

                $hojas[] = $recojo->hojaHtml(
                    RecojoDelDia::HOJAS[$clave], $clave, $fecha, $caminero, $placa, $grupos[$clave]
                );
            }
        }

        if (!$hojas) {
            return response()->json(['message' => 'No hay nada que imprimir en esta fecha'], 422);
        }

        $nombre = 'recojo_' . ($camion !== '' ? preg_replace('/\s+/', '_', $camion) . '_' : '')
            . ($grupo === 'todos' ? '' : $grupo . '_') . $fecha;

        return $recojo->pdf(
            implode("<div style='page-break-after: always'></div>", $hojas),
            $nombre
        );
    }
}
