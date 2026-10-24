<?php

namespace App\Services;

use App\Http\Controllers\Concerns\PapeleriaSofia;
use App\Http\Controllers\FacturacionController;
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

    /**
     * Estados de entrega que traen plata: el retorno parcial tambien se cobra,
     * solo que por lo que el cliente se quedo.
     */
    public const ESTADOS_COBRADOS = ['ENTREGADO', 'RETORNO PARCIAL'];

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
    public function filas($fecha, $placa = null, $soloComprobantes = false): Collection
    {
        $consulta = DB::table('entregas as e')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'e.cliente_id')
            ->leftJoin('facturas as fb', 'fb.id', '=', 'e.factura_id')
            ->leftJoin('personal as p', 'p.CodAut', '=', 'e.personal_id')
            ->where('e.fechaEntreg', $fecha);

        // Lo que el camion recogio con la app: entregas ligadas a un
        // comprobante. Las de la ruta del sistema anterior no tienen.
        if ($soloComprobantes) {
            $consulta->whereNotNull('e.factura_id');
        }

        if ($placa !== null && trim((string) $placa) !== '') {
            $consulta->where('e.placa', trim($placa));
        }

        // Lo de un comprobante anulado (o borrado) no sale: si se volvio a
        // emitir, la nota nueva trae su propia entrega y saldria repetida; si
        // se anulo el pedido, ya no hay nada que rendir.
        // Lo no entregado si sale aunque despues se anulara la venta (es lo
        // normal: caja anula lo que volvio). No trae plata, asi que no hay nada
        // que se pueda contar dos veces, y el camion tiene que verse con su 0.
        $consulta->where(function ($w) {
            $w->whereNotIn('e.estado', self::ESTADOS_COBRADOS)
                ->orWhereNotExists(function ($sub) {
                    $sub->from('facturas as fa')
                        ->whereColumn('fa.id', 'e.factura_id')
                        ->where(function ($w) {
                            $w->where('fa.estado', 'ANULADO')->orWhereNotNull('fa.deleted_at');
                        });
                });
        });

        // Un NO ENTREGADO se puede corregir despues entregando: de cada
        // comprobante vale solo su ultima entrega, si no la nota saldria a la
        // vez en anulados y en lo cobrado. Las de la ruta de siempre no tienen
        // comprobante y quedan todas.
        $consulta->whereNotExists(function ($sub) {
            $sub->from('entregas as e2')
                ->whereNotNull('e.factura_id')
                ->whereColumn('e2.factura_id', 'e.factura_id')
                ->whereColumn('e2.id', '>', 'e.id');
        });

        return $consulta
            ->orderBy('e.placa')
            ->orderBy('e.factura_id')
            ->orderBy('e.comanda')
            ->get([
                // El numero que se ve es el del comprobante (el Nro de la
                // boleta), no el del pedido. Las de la ruta del sistema
                // anterior no tienen comprobante y siguen con su comanda.
                'e.id', DB::raw('COALESCE(e.factura_id, e.comanda) as nota'), 'e.comanda', 'e.estado', 'e.tipago', 'e.hora',
                DB::raw("TRIM(COALESCE(e.placa, '')) as placa"),
                DB::raw('COALESCE(e.monto, 0) as monto'),
                DB::raw('COALESCE(e.monto_efectivo, 0) as monto_efectivo'),
                DB::raw('COALESCE(e.monto_qr, 0) as monto_qr'),
                DB::raw("TRIM(COALESCE(e.observacion, '')) as motivo"),
                // Una baja sale a nombre de su cuenta (BAJAS POR ...), igual
                // que en el comprobante impreso, no del cliente que la recibe.
                DB::raw("COALESCE((
                    SELECT NULLIF(TRIM(cb.Nombres), '')
                    FROM tbpedidos pb
                    JOIN tbclientes cb ON cb.Cod_Aut = pb.bonificacionId
                    WHERE pb.deleted_at IS NULL AND pb.NroPed = fb.pedido_nro
                      AND " . TipoPedido::sql('pb') . " = UPPER(TRIM(fb.pedido_tipo))
                      AND pb.bonificacionId IN (" . implode(',', FacturacionController::CUENTAS_BAJA) . ")
                    LIMIT 1
                ), TRIM(COALESCE(c.Nombres, ''))) as cliente"),
                DB::raw("TRIM(CONCAT(COALESCE(p.Nombre1, ''), ' ', COALESCE(p.App1, ''))) as caminero"),
            ])
            ->map(function ($fila) {
                $fila->monto = (float) $fila->monto;
                $fila->monto_efectivo = (float) $fila->monto_efectivo;
                $fila->monto_qr = (float) $fila->monto_qr;
                // Lo que no se entrego no trae nada: va con 0, no con el total
                // de la nota, en la tabla, en los totales y en la hoja.
                if (!in_array($fila->estado, self::ESTADOS_COBRADOS, true)) {
                    $fila->monto = 0.0;
                    $fila->monto_efectivo = 0.0;
                    $fila->monto_qr = 0.0;
                }
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
        $entregadas = $filas->whereIn('estado', self::ESTADOS_COBRADOS);

        return [
            'contados' => $entregadas->where('tipago', 'CONTADO')->values(),
            'qr' => $entregadas->where('tipago', 'PAGO QR')->values(),
            'mixtos' => $entregadas->where('tipago', 'MIXTO')->values(),
            'creditos' => $entregadas->where('tipago', 'CRÉDITO')->values(),
            'anulados' => $filas->whereNotIn('estado', self::ESTADOS_COBRADOS)->values(),
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

    /**
     * El recojo en una sola tabla: una fila por nota, con lo que entro por
     * cada via en su propia columna. Lo que no se cobro (credito o lo que
     * falto) tambien tiene columna, asi la suma de las columnas cuadra
     * siempre con el total de las notas entregadas.
     *
     * Las no entregadas van al final, sin plata, para que se vea el motivo.
     */
    public function tabla($filas): array
    {
        $cobradas = $filas->whereIn('estado', self::ESTADOS_COBRADOS);
        $sinCobrar = $filas->whereNotIn('estado', self::ESTADOS_COBRADOS);

        $lineas = $cobradas->map(function ($fila) {
            $credito = $fila->tipago === 'CRÉDITO' ? $fila->monto : 0.0;
            $falta = $credito > 0 ? 0.0 : max(round($fila->monto - $fila->monto_efectivo - $fila->monto_qr, 2), 0.0);

            return [
                'id' => $fila->id,
                'nota' => $fila->nota,
                'cliente' => $fila->cliente,
                'estado' => $fila->estado,
                'tipago' => $fila->tipago,
                'entregada' => true,
                'monto' => round($fila->monto, 2),
                'efectivo' => round($fila->monto_efectivo, 2),
                'qr' => round($fila->monto_qr, 2),
                'credito' => round($credito, 2),
                'falta' => $falta,
                'motivo' => $fila->estado === 'RETORNO PARCIAL' ? $fila->motivo : '',
            ];
        })->values();

        $noEntregadas = $sinCobrar->map(function ($fila) {
            return [
                'id' => $fila->id,
                'nota' => $fila->nota,
                'cliente' => $fila->cliente,
                'estado' => $fila->estado,
                'tipago' => $fila->tipago,
                'entregada' => false,
                'monto' => round($fila->monto, 2),
                'efectivo' => 0.0, 'qr' => 0.0, 'credito' => 0.0, 'falta' => 0.0,
                'motivo' => $fila->motivo,
            ];
        })->values();

        return [
            'filas' => $lineas->concat($noEntregadas)->values(),
            'totales' => [
                'notas' => $lineas->count(),
                'monto' => round($lineas->sum('monto'), 2),
                'efectivo' => round($lineas->sum('efectivo'), 2),
                'qr' => round($lineas->sum('qr'), 2),
                'credito' => round($lineas->sum('credito'), 2),
                'falta' => round($lineas->sum('falta'), 2),
                'no_entregadas' => $noEntregadas->count(),
                'monto_no_entregado' => round($noEntregadas->sum('monto'), 2),
            ],
        ];
    }

    /**
     * La tabla del recojo en una hoja, con la firma del caminero al pie.
     *
     * Con $pendientes (lo de pendientes() de ese camion), debajo van las notas
     * que siguen en el camion sin ninguna entrega: lo que todavia no se cobro.
     */
    public function tablaHtml($fecha, $caminero, $placa, $filas, $pendientes = null)
    {
        $pendientes = collect($pendientes ?? []);
        $tabla = $this->tabla($filas);
        $t = $tabla['totales'];
        $monto = function ($valor) {
            return $valor > 0 ? number_format($valor, 2) : '';
        };
        $cuerpo = '';

        foreach ($tabla['filas'] as $i => $fila) {
            $clase = $fila['entregada'] ? ($i % 2 ? " class='par'" : '') : " class='sin'";
            $nombre = e($fila['cliente'] ?: 'Sin cliente');
            if ($fila['motivo'] !== '') {
                $nombre .= "<br><span class='motivo'>" . e($fila['motivo']) . '</span>';
            }

            $cuerpo .= "<tr$clase>"
                . "<td class='c'>" . ($i + 1) . '</td>'
                . "<td class='c nota'>" . e($fila['nota']) . '</td>'
                . "<td>$nombre</td>";

            if ($fila['entregada']) {
                $cuerpo .= "<td class='r b'>" . number_format($fila['monto'], 2) . '</td>'
                    . "<td class='r'>" . $monto($fila['efectivo']) . '</td>'
                    . "<td class='r'>" . $monto($fila['qr']) . '</td>'
                    . "<td class='r'>" . $monto($fila['credito']) . '</td>'
                    . "<td class='r falta'>" . $monto($fila['falta']) . '</td>';
            } else {
                $cuerpo .= "<td class='r gris'>" . number_format($fila['monto'], 2) . '</td>'
                    . "<td colspan='4' class='c gris'>" . e($fila['estado']) . '</td>';
            }

            $cuerpo .= '</tr>';
        }

        if ($cuerpo === '') {
            $cuerpo = "<tr><td colspan='8' class='c gris' style='padding:16px'>Sin notas este día</td></tr>";
        }

        $caja = "<table class='caja-doc'>
            <tr><td colspan='2' class='tit'>RECOJO DEL DÍA</td></tr>
            <tr><td class='et'>Fecha</td><td class='r'>" . date('d/m/Y', strtotime($fecha)) . "</td></tr>
            <tr><td class='et'>Camión</td><td class='r'><b>" . e($placa) . "</b></td></tr>
            <tr><td class='et'>Notas</td><td class='r nro'>" . $t['notas'] . "</td></tr>
        </table>";

        // Tiene que entrar en una sola hoja carta: filas apretadas y letra
        // grande, que baja un poco solo en los dias con muchas notas.
        $notas = count($tabla['filas']) + ($pendientes->isNotEmpty() ? $pendientes->count() + 4 : 0);
        [$letra, $alto] = $notas <= 34 ? [10.5, 1.5] : ($notas <= 44 ? [9.5, 1] : [8.5, 0.5]);

        return '<style>' . $this->estilosImpresion() . "
            @page { margin: 8mm 10mm 14mm 10mm }
            .pie { bottom: -10mm }
            .logo { width: 80px }
            .datos { margin-top: 4px }
            .datos td { padding: 2px 6px; font-size: 10px }
            .detalle { margin-top: 5px }
            .detalle th { padding: 3px 4px; font-size: 8.5px }
            .detalle td { padding: {$alto}px 4px; font-size: {$letra}px; line-height: 1.15 }
            .firma { margin-top: 26px; text-align: center; font-size: 9px; color: #666 }
            .firma-linea { border-top: 1px solid #999; width: 62mm; margin: 0 auto 3px }
            .firma b { color: #222; font-size: 10px }
            .nota { color: #1a5fb4; font-weight: bold }
            .b { font-weight: bold }
            .falta { color: #c62828 }
            .motivo { color: #bf360c; font-size: 7.5px }
            .detalle tr.sin td { color: #999; background: #f4f4f4 }
            .detalle tfoot td { background: #37474f; color: #fff; font-weight: bold; padding: 3px 4px }
            .totales td { padding: 2px 8px; font-size: 10px }
            .totales .final td { font-size: 12px }
        </style>"
        . $this->cabeceraEmisor($caja)
        . "<table class='datos'>
            <tr>
                <td style='width:55%'><span class='et'>Caminero</span><br><b>" . e($caminero) . "</b></td>
                <td><span class='et'>Día del recojo</span><br>" . $this->fechaLarga($fecha) . "</td>
            </tr>
        </table>
        <table class='detalle'>
            <thead><tr>
                <th style='width:22px'>N°</th>
                <th style='width:52px'>Nro</th>
                <th style='text-align:left'>Cliente</th>
                <th style='width:62px' class='r'>Total</th>
                <th style='width:62px' class='r'>Efectivo</th>
                <th style='width:62px' class='r'>QR</th>
                <th style='width:62px' class='r'>Crédito</th>
                <th style='width:52px' class='r'>Falta</th>
            </tr></thead>
            <tbody>$cuerpo</tbody>
            <tfoot><tr>
                <td colspan='3' class='r'>TOTALES</td>
                <td class='r'>" . number_format($t['monto'], 2) . "</td>
                <td class='r'>" . number_format($t['efectivo'], 2) . "</td>
                <td class='r'>" . number_format($t['qr'], 2) . "</td>
                <td class='r'>" . number_format($t['credito'], 2) . "</td>
                <td class='r'>" . number_format($t['falta'], 2) . "</td>
            </tr></tfoot>
        </table>
        <table class='totales' style='margin-top:5px'>
            <tr><td>Efectivo + QR + Crédito + Falta</td><td class='r' style='width:120px'>Bs. "
                . number_format($t['efectivo'] + $t['qr'] + $t['credito'] + $t['falta'], 2) . "</td></tr>
            <tr class='final'><td>A RENDIR EN CAJA (EFECTIVO + QR)</td><td class='r'>Bs. "
                . number_format($t['efectivo'] + $t['qr'], 2) . "</td></tr>
        </table>
        " . $this->pendientesHtml($pendientes) . "
        <div class='firma'>
            <div class='firma-linea'></div>
            <b>" . e($caminero) . "</b><br>Firma del caminero
        </div>
        <div class='pie'><div class='legal'>"
            . e(config('siat.emisor')['nombre']) . ' &middot; Recojo del ' . date('d/m/Y', strtotime($fecha))
            . ' &middot; camión ' . e($placa) . ' &middot; generado el ' . date('d/m/Y H:i') . '</div></div>';
    }

    /** Debajo de la tabla: las notas que siguen en el camion, sin cobrar. */
    private function pendientesHtml($pendientes)
    {
        if ($pendientes->isEmpty()) {
            return '';
        }

        $cuerpo = '';
        foreach ($pendientes->values() as $i => $fila) {
            $cuerpo .= '<tr' . ($i % 2 ? " class='par'" : '') . '>'
                . "<td class='c'>" . ($i + 1) . '</td>'
                . "<td class='c nota'>" . e($fila->nota) . '</td>'
                . '<td>' . e($fila->cliente ?: 'Sin cliente') . '</td>'
                . "<td class='c'>" . e($fila->tipo_pago) . '</td>'
                . "<td class='r b'>" . number_format($fila->monto, 2) . '</td>'
                . '</tr>';
        }

        return "<div style='margin-top:8px;font-weight:bold;font-size:10px;color:#c62828'>"
            . 'PENDIENTES · SIGUEN EN EL CAMIÓN SIN ENTREGA NI COBRO (' . $pendientes->count() . ')</div>'
            . "<table class='detalle' style='margin-top:3px'>
            <thead><tr>
                <th style='width:22px'>N°</th>
                <th style='width:52px'>Nro</th>
                <th style='text-align:left'>Cliente</th>
                <th style='width:80px'>Tipo pago</th>
                <th style='width:62px' class='r'>Total</th>
            </tr></thead>
            <tbody>$cuerpo</tbody>
            <tfoot><tr>
                <td colspan='4' class='r'>TOTAL PENDIENTE</td>
                <td class='r'>" . number_format($pendientes->sum('monto'), 2) . '</td>
            </tr></tfoot>
        </table>';
    }

    /**
     * Las ventas del turno que van en un camion y todavia no tienen ninguna
     * entrega registrada (siguen "en camion"). No traen plata, pero sin esto
     * un camion que aun no marco nada no aparecia y parecia que no existia.
     *
     * El turno es el de facturacion: de las 18:00 del dia anterior a las 18:00
     * del dia. El camion es el del pedido, o el de la venta directa, igual que
     * en la grilla de facturacion.
     */
    public function pendientes($fecha, $placa = null): Collection
    {
        $desde = date('Y-m-d', strtotime($fecha . ' -1 day')) . ' 18:00:00';
        $hasta = $fecha . ' 18:00:00';
        $camion = "COALESCE(NULLIF(TRIM(f.placa), ''), (
                SELECT CONVERT(TRIM(COALESCE(pc.placa, '')) USING utf8mb4)
                FROM tbpedidos pc
                WHERE pc.deleted_at IS NULL AND pc.NroPed = f.pedido_nro
                  AND " . TipoPedido::sql('pc') . " = UPPER(TRIM(f.pedido_tipo))
                LIMIT 1
            ))";

        return DB::table('facturas as f')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'f.cliente_id')
            ->where('f.estado', '<>', 'ANULADO')
            ->whereNull('f.deleted_at')
            ->whereRaw("TIMESTAMP(f.fecha, COALESCE(f.hora, '00:00:00')) >= ?", [$desde])
            ->whereRaw("TIMESTAMP(f.fecha, COALESCE(f.hora, '00:00:00')) < ?", [$hasta])
            ->whereNotExists(function ($sub) {
                $sub->from('entregas as e')->whereColumn('e.factura_id', 'f.id');
            })
            ->orderBy('f.id')
            ->get([
                'f.id as nota', 'f.tipo_comprobante', 'f.tipo_pago', 'f.pedido_tipo',
                DB::raw('COALESCE(f.total, 0) as monto'),
                DB::raw("COALESCE(NULLIF(TRIM(c.Nombres), ''), f.nombre) as cliente"),
                DB::raw("$camion as placa"),
            ])
            ->map(function ($fila) {
                $fila->monto = (float) $fila->monto;
                $fila->placa = trim((string) $fila->placa);
                return $fila;
            })
            // El camion se filtra aca y no en el SQL: la placa del pedido viene
            // del legado con otra collation y MySQL no deja compararlas.
            ->filter(function ($fila) use ($placa) {
                return $fila->placa !== ''
                    && ($placa === null || trim((string) $placa) === '' || $fila->placa === trim($placa));
            })
            ->values();
    }

    /** Los camiones que trajeron algo ese dia, con su caminero. */
    public function camiones($fecha, $soloComprobantes = false): array
    {
        return DB::table('entregas as e')
            ->leftJoin('personal as p', 'p.CodAut', '=', 'e.personal_id')
            ->where('e.fechaEntreg', $fecha)
            ->when($soloComprobantes, function ($consulta) {
                $consulta->whereNotNull('e.factura_id');
            })
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
                <th style='width:64px'>Nro</th>
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

    /**
     * El resumen general: una fila por camion y por dia con lo que recogio
     * (efectivo y QR), sin el detalle de las notas. Es lo que cobranzas mira
     * para saber cuanto trajo cada camion.
     *
     * $dias es [fecha => [['placa', 'planilla', 'caminero', 'notas',
     * 'efectivo', 'qr'], ...]], ya en orden.
     */
    public function resumenHtml($desde, $hasta, array $dias)
    {
        $cuerpo = '';
        $general = ['notas' => 0, 'efectivo' => 0.0, 'qr' => 0.0];
        $variosDias = $desde !== $hasta;
        $columnas = $variosDias ? 8 : 7;
        $i = 0;

        foreach ($dias as $fecha => $camiones) {
            $delDia = ['notas' => 0, 'efectivo' => 0.0, 'qr' => 0.0];

            foreach ($camiones as $c) {
                $par = $i % 2 ? " class='par'" : '';
                $cuerpo .= "<tr$par>"
                    . "<td class='c'>" . (++$i) . '</td>'
                    . ($variosDias ? "<td class='c'>" . date('d/m/Y', strtotime($fecha)) . '</td>' : '')
                    . "<td class='c'>" . e($c['planilla']) . '</td>'
                    . '<td><b>' . e($c['placa']) . "</b><br><span class='gris'>" . e($c['caminero']) . '</span></td>'
                    . "<td class='c'>" . $c['notas'] . '</td>'
                    . "<td class='r'>" . number_format($c['efectivo'], 2) . '</td>'
                    . "<td class='r'>" . number_format($c['qr'], 2) . '</td>'
                    . "<td class='r b'>" . number_format($c['efectivo'] + $c['qr'], 2) . '</td>'
                    . '</tr>';

                foreach ($delDia as $k => $v) {
                    $delDia[$k] += $c[$k];
                }
            }

            // Con varios dias, cada dia cierra con su subtotal.
            if ($variosDias && $camiones) {
                $cuerpo .= "<tr class='sub'>"
                    . "<td colspan='4' class='r'>TOTAL " . $this->fechaLarga($fecha) . '</td>'
                    . "<td class='c'>" . $delDia['notas'] . '</td>'
                    . "<td class='r'>" . number_format($delDia['efectivo'], 2) . '</td>'
                    . "<td class='r'>" . number_format($delDia['qr'], 2) . '</td>'
                    . "<td class='r'>" . number_format($delDia['efectivo'] + $delDia['qr'], 2) . '</td>'
                    . '</tr>';
            }

            foreach ($general as $k => $v) {
                $general[$k] += $delDia[$k];
            }
        }

        if ($cuerpo === '') {
            $cuerpo = "<tr><td colspan='$columnas' class='c gris' style='padding:16px'>Ningún camión recogió en estas fechas</td></tr>";
        }

        $periodo = $variosDias
            ? date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta))
            : date('d/m/Y', strtotime($desde));

        $caja = "<table class='caja-doc'>
            <tr><td colspan='2' class='tit'>RESUMEN DEL RECOJO</td></tr>
            <tr><td class='et'>" . ($variosDias ? 'Del' : 'Fecha') . "</td><td class='r'>" . date('d/m/Y', strtotime($desde)) . '</td></tr>'
            . ($variosDias ? "<tr><td class='et'>Al</td><td class='r'>" . date('d/m/Y', strtotime($hasta)) . '</td></tr>' : '') . "
            <tr><td class='et'>Notas</td><td class='r nro'>" . $general['notas'] . "</td></tr>
        </table>";

        return '<style>' . $this->estilosImpresion() . "
            .b { font-weight: bold }
            .detalle td { padding: 3px 5px; font-size: 10px }
            .detalle tr.sub td { background: #eceff1; font-weight: bold; font-size: 9px }
            .detalle tfoot td { background: #37474f; color: #fff; font-weight: bold; padding: 4px 5px }
        </style>"
        . $this->cabeceraEmisor($caja)
        . "<table class='datos'>
            <tr>
                <td><span class='et'>" . ($variosDias ? 'Periodo' : 'Día del recojo') . '</span><br>'
                    . ($variosDias ? $periodo : $this->fechaLarga($desde)) . "</td>
            </tr>
        </table>
        <table class='detalle'>
            <thead><tr>
                <th style='width:24px'>N°</th>"
                . ($variosDias ? "<th style='width:62px'>Fecha</th>" : '') . "
                <th style='width:52px'>Planilla</th>
                <th style='text-align:left'>Camión / Caminero</th>
                <th style='width:40px'>Notas</th>
                <th style='width:74px' class='r'>Efectivo</th>
                <th style='width:74px' class='r'>QR</th>
                <th style='width:84px' class='r'>Total recogido</th>
            </tr></thead>
            <tbody>$cuerpo</tbody>
            <tfoot><tr>
                <td colspan='" . ($columnas - 4) . "' class='r'>TOTAL GENERAL</td>
                <td class='c'>" . $general['notas'] . "</td>
                <td class='r'>" . number_format($general['efectivo'], 2) . "</td>
                <td class='r'>" . number_format($general['qr'], 2) . "</td>
                <td class='r'>" . number_format($general['efectivo'] + $general['qr'], 2) . "</td>
            </tr></tfoot>
        </table>
        <table class='totales' style='margin-top:9px'>
            <tr class='final'><td>TOTAL RECOGIDO (EFECTIVO + QR)</td><td class='r' style='width:140px'>Bs. "
                . number_format($general['efectivo'] + $general['qr'], 2) . "</td></tr>
        </table>
        <div class='pie'><div class='legal'>"
            . e(config('siat.emisor')['nombre']) . ' &middot; Resumen del recojo ' . $periodo
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
