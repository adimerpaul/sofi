<?php

namespace App\Services;

use App\Http\Controllers\Concerns\PapeleriaSofia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/**
 * El recojo del dia: la plata que los camiones traen de vuelta.
 *
 * Lo miran dos pantallas distintas. El caminero ve solo su camion, desde la
 * ruta, para cuadrar lo que rinde en caja. Cobranzas ve todos los camiones y
 * saca las mismas hojas, porque es quien recibe el dinero al final del dia.
 *
 * Vive aparte de los controladores justamente por eso: las dos tienen que
 * mostrar e imprimir exactamente lo mismo, y si la hoja del caminero y la de
 * cobranzas se calcularan por separado, la que se firma podria no coincidir
 * con la que reclama caja.
 */
class RecojoDelDia
{
    use PapeleriaSofia;

    /** Las hojas del recojo, una por forma de pago, como se entregan en papel. */
    public const HOJAS = [
        'contados' => 'CONTADOS DEL DÍA',
        'qr' => 'PAGOS QR',
        'mixtos' => 'MIXTOS',
        'creditos' => 'CRÉDITOS',
        'anulados' => 'ANULADOS',
    ];

    /**
     * Las entregas de un dia. Sin placa vienen las de todos los camiones, que
     * es lo que mira cobranzas.
     */
    public function filas($fecha, $placa = null): Collection
    {
        $consulta = DB::table('entregas as e')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'e.cliente_id')
            ->leftJoin('personal as p', 'p.CodAut', '=', 'e.personal_id')
            ->where('e.fechaEntreg', $fecha);

        if ($placa !== null && trim((string) $placa) !== '') {
            $consulta->where('e.placa', trim($placa));
        }

        return $consulta
            ->orderBy('e.placa')
            ->orderBy('e.comanda')
            ->get([
                'e.id', 'e.comanda as nota', 'e.estado', 'e.tipago', 'e.hora',
                DB::raw("TRIM(COALESCE(e.placa, '')) as placa"),
                DB::raw('COALESCE(e.monto, 0) as monto'),
                DB::raw('COALESCE(e.monto_efectivo, 0) as monto_efectivo'),
                DB::raw('COALESCE(e.monto_qr, 0) as monto_qr'),
                DB::raw("TRIM(COALESCE(e.observacion, '')) as motivo"),
                DB::raw("TRIM(COALESCE(c.Nombres, '')) as cliente"),
                DB::raw("TRIM(CONCAT(COALESCE(p.Nombre1, ''), ' ', COALESCE(p.App1, ''))) as caminero"),
            ])
            ->map(function ($fila) {
                $fila->monto = (float) $fila->monto;
                $fila->monto_efectivo = (float) $fila->monto_efectivo;
                $fila->monto_qr = (float) $fila->monto_qr;
                // Las entregas de la ruta de siempre no traen desglose: se
                // deduce del tipago para que el dinero cuadre igual. Solo el
                // mixto necesita las columnas cargadas.
                if ($fila->estado === 'ENTREGADO' && $fila->monto_efectivo == 0 && $fila->monto_qr == 0) {
                    if ($fila->tipago === 'CONTADO') {
                        $fila->monto_efectivo = $fila->monto;
                    } elseif ($fila->tipago === 'PAGO QR') {
                        $fila->monto_qr = $fila->monto;
                    }
                }
                return $fila;
            });
    }

    /** Las mismas hojas que hoy se entregan en papel, en el mismo orden. */
    public function agrupar($filas): array
    {
        $entregadas = $filas->where('estado', 'ENTREGADO');

        return [
            'contados' => $entregadas->where('tipago', 'CONTADO')->values(),
            'qr' => $entregadas->where('tipago', 'PAGO QR')->values(),
            'mixtos' => $entregadas->where('tipago', 'MIXTO')->values(),
            'creditos' => $entregadas->where('tipago', 'CRÉDITO')->values(),
            'anulados' => $filas->where('estado', '<>', 'ENTREGADO')->values(),
        ];
    }

    public function totales(array $grupos, $entregadas): array
    {
        return [
            'contados' => round($grupos['contados']->sum('monto'), 2),
            'qr' => round($grupos['qr']->sum('monto'), 2),
            'mixtos' => round($grupos['mixtos']->sum('monto'), 2),
            'creditos' => round($grupos['creditos']->sum('monto'), 2),
            'anulados' => round($grupos['anulados']->sum('monto'), 2),
            // Lo que se rinde en caja, ya separado por via: el mixto aporta a
            // las dos.
            'efectivo' => round($entregadas->sum('monto_efectivo'), 2),
            'qr_cobrado' => round($entregadas->sum('monto_qr'), 2),
        ];
    }

    /** Los camiones que trajeron algo ese dia, con su caminero. */
    public function camiones($fecha): array
    {
        return DB::table('entregas as e')
            ->leftJoin('personal as p', 'p.CodAut', '=', 'e.personal_id')
            ->where('e.fechaEntreg', $fecha)
            ->whereRaw("TRIM(COALESCE(e.placa, '')) <> ''")
            ->groupBy('e.placa', 'caminero')
            ->orderBy('e.placa')
            ->get([
                DB::raw('TRIM(e.placa) as placa'),
                DB::raw("TRIM(CONCAT(COALESCE(p.Nombre1, ''), ' ', COALESCE(p.App1, ''))) as caminero"),
            ])
            ->map(function ($fila) {
                return ['placa' => $fila->placa, 'caminero' => $fila->caminero ?: $fila->placa];
            })
            ->all();
    }

    /**
     * El nombre que va en la hoja de un camion: el del caminero que registro
     * las entregas de ese dia. Si no hay ninguna se cae a la placa, para que
     * la hoja vacia igual diga de que camion es.
     */
    public function caminero($filas, $placa)
    {
        foreach ($filas as $fila) {
            $nombre = trim((string) ($fila->caminero ?? ''));
            if ($nombre !== '') {
                return $nombre;
            }
        }

        return $placa;
    }

    /**
     * Una hoja del recojo, con el mismo formato que la boleta de entrega: la
     * cabecera de la casa, la grilla oscura del detalle y el total en barra.
     *
     * Al pie va la firma, que es lo que hace que caja pueda reclamarle al
     * caminero por lo que declaro haber cobrado.
     */
    public function hojaHtml($titulo, $clave, $fecha, $caminero, $placa, $filas)
    {
        $anulados = $clave === 'anulados';
        $cuerpo = '';

        foreach ($filas as $i => $fila) {
            $par = $i % 2 ? " class='par'" : '';

            $cuerpo .= "<tr$par>"
                . "<td class='c'>" . ($i + 1) . '</td>'
                . "<td class='c nota'>" . e($fila->nota) . '</td>'
                . '<td>' . e($fila->cliente) . '</td>'
                . ($anulados ? '<td>' . e($fila->motivo) . '</td>' : '')
                . "<td class='r'>" . number_format($fila->monto, 2) . '</td>'
                . '</tr>';
        }

        if ($cuerpo === '') {
            $cuerpo = "<tr><td colspan='" . ($anulados ? 5 : 4) . "' class='c gris'"
                . " style='padding:16px'>Sin notas en esta hoja</td></tr>";
        }

        // La caja roja de la derecha, igual que el "BOLETA DE ENTREGA" del
        // voucher: dice que hoja es, de que dia y de que camion.
        $caja = "<table class='caja-doc'>
            <tr><td colspan='2' class='tit'>" . e($titulo) . "</td></tr>
            <tr><td class='et'>Fecha</td><td class='r'>" . date('d/m/Y', strtotime($fecha)) . "</td></tr>
            <tr><td class='et'>Camión</td><td class='r'><b>" . e($placa) . "</b></td></tr>
            <tr><td class='et'>Notas</td><td class='r nro'>" . $filas->count() . "</td></tr>
        </table>";

        return '<style>' . $this->estilosImpresion() . "
            .firma { margin-top: 52px; text-align: center; font-size: 9px; color: #666 }
            .firma-linea { border-top: 1px solid #999; width: 62mm; margin: 0 auto 3px }
            .firma b { color: #222; font-size: 10px }
            .nota { color: #1a5fb4; font-weight: bold; font-size: 9px }
        </style>"
        . $this->cabeceraEmisor($caja)
        . "<table class='datos'>
            <tr>
                <td style='width:55%'><span class='et'>Caminero</span><br><b>"
                    . e($caminero) . "</b></td>
                <td><span class='et'>Día del recojo</span><br>"
                    . $this->fechaLarga($fecha) . "</td>
            </tr>
        </table>
        <table class='detalle'>
            <thead><tr>
                <th style='width:28px'>N°</th>
                <th style='width:64px'>Nota</th>
                <th style='text-align:left'>Nombre del cliente</th>"
                . ($anulados ? "<th style='width:170px;text-align:left'>Motivo</th>" : '') . "
                <th style='width:78px' class='r'>Monto Bs.</th>
            </tr></thead>
            <tbody>$cuerpo</tbody>
        </table>
        <table class='totales' style='margin-top:9px'>
            <tr class='final'>
                <td>TOTAL " . e($titulo) . "</td>
                <td class='r' style='width:120px'>Bs. "
                    . number_format($filas->sum('monto'), 2) . "</td>
            </tr>
        </table>
        <div class='firma'>
            <div class='firma-linea'></div>
            <b>" . e($caminero) . "</b><br>Firma del caminero
        </div>
        <div class='pie'><div class='legal'>"
            . e(config('siat.emisor')['nombre']) . ' &middot; ' . e($titulo)
            . ' del ' . date('d/m/Y', strtotime($fecha)) . ' &middot; camión ' . e($placa)
            . ' &middot; generado el ' . date('d/m/Y H:i') . '</div></div>';
    }

    /** SÁBADO, 29 DE AGOSTO DE 2026: como sale en la hoja de papel. */
    public function fechaLarga($fecha)
    {
        $dias = ['DOMINGO', 'LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES', 'SÁBADO'];
        $meses = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO',
            'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];

        $tiempo = strtotime($fecha);

        return $dias[(int) date('w', $tiempo)] . ', ' . (int) date('j', $tiempo)
            . ' DE ' . $meses[(int) date('n', $tiempo) - 1] . ' DE ' . date('Y', $tiempo);
    }

    /** Las hojas ya armadas, en un solo PDF tamano carta. */
    public function pdf($html, $nombre)
    {
        $pdf = App::make('dompdf.wrapper');
        // Sin subsetting la fuente se embebe entera y cada PDF pesa ~900 KB.
        $pdf->getDomPDF()->getOptions()->setIsFontSubsettingEnabled(true);
        $pdf->setPaper('letter');
        $pdf->loadHTML($html);

        return $pdf->stream($nombre . '.pdf', ['Attachment' => false]);
    }
}
