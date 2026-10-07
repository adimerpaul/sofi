<?php

namespace App\Http\Controllers;

use App\Services\CargaCamion;
use App\Services\TipoPedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Entregas del dia sobre facturacion: el reemplazo del reporte de entregas
 * del sistema anterior (que leia tbctascobrar).
 *
 * Los comprobantes de cada camion son los de la jornada de reparto -de la
 * vispera a las 18:00 a ese dia a las 18:00-, el mismo criterio con el que
 * el caminero carga y entrega, asi que lo que se ve aca es exactamente lo que
 * salio en el camion. Lo que paso con cada uno sale de la ultima entrega que
 * registro el caminero.
 */
class EntregaFacturaController extends Controller
{
    /** Estados de entrega, en el orden en que se muestran. */
    private const ESTADOS = ['ENTREGADO', 'RETORNO PARCIAL', 'NO ENTREGADO', 'RECHAZADO', 'PENDIENTE'];

    /**
     * Todo lo del dia en una llamada: el resumen por camion (avance, zonas y
     * plata) y cada comprobante con su estado, para el mapa y las tablas.
     */
    public function index(Request $request)
    {
        $datos = $request->validate([
            'fecha' => 'nullable|date_format:Y-m-d',
            'tipo' => 'nullable|string|max:20',
        ]);
        $fecha = $datos['fecha'] ?? date('Y-m-d');
        $tipo = strtoupper(trim((string) ($datos['tipo'] ?? '')));
        $tipo = in_array($tipo, TipoPedido::TIPOS, true) ? $tipo : null;

        $comprobantes = $this->comprobantes($fecha, $tipo);

        return [
            'fecha' => $fecha,
            'jornada' => CargaCamion::textoJornada($fecha),
            'tipo' => $tipo,
            'tipos' => TipoPedido::TIPOS,
            'totales' => $this->resumir($comprobantes),
            'camiones' => $comprobantes->groupBy('placa')->map(function ($delCamion, $placa) {
                return ['placa' => $placa] + $this->resumir($delCamion) + [
                    // Las zonas que lleva el camion, con cuantas notas de cada una.
                    'zonas' => $delCamion->groupBy('zona_hex')->map(function ($deZona, $hex) {
                        return [
                            'hex' => $hex ?: null,
                            'zona' => $deZona->first()['zona'] ?: 'SIN ZONA',
                            'cantidad' => $deZona->count(),
                        ];
                    })->sortByDesc('cantidad')->values(),
                ];
            })->sortBy('placa')->values(),
            'comprobantes' => $comprobantes->values(),
        ];
    }

    /**
     * Lo mismo pero solo de las ventas del vendedor que entra (pantalla
     * /avance): sus comprobantes de la jornada, en que camion van y si ya se
     * entregaron, para seguirlos en el mapa y bajar la boleta.
     */
    public function vendedor(Request $request)
    {
        $datos = $request->validate([
            'fecha' => 'nullable|date_format:Y-m-d',
        ]);
        $fecha = $datos['fecha'] ?? date('Y-m-d');
        $ci = trim((string) $request->user()->ci);

        $comprobantes = $ci === '' ? collect() : $this->comprobantes($fecha, null, $ci);

        return [
            'fecha' => $fecha,
            'jornada' => CargaCamion::textoJornada($fecha),
            'totales' => $this->resumir($comprobantes),
            'camiones' => $comprobantes->groupBy('placa')->map(function ($delCamion, $placa) {
                return ['placa' => $placa] + $this->resumir($delCamion);
            })->sortBy('placa')->values(),
            'comprobantes' => $comprobantes->values(),
        ];
    }

    /** Conteos por estado, avance y plata de un grupo de comprobantes. */
    private function resumir($comprobantes): array
    {
        $porEstado = [];
        foreach (self::ESTADOS as $estado) {
            $porEstado[$estado] = $comprobantes->where('estado', $estado)->count();
        }
        $total = $comprobantes->count();
        $cerrados = $total - $porEstado['PENDIENTE'];

        return [
            'total' => $total,
            'entregados' => $porEstado['ENTREGADO'],
            'retorno' => $porEstado['RETORNO PARCIAL'],
            'no_entregados' => $porEstado['NO ENTREGADO'],
            'rechazados' => $porEstado['RECHAZADO'],
            'pendientes' => $porEstado['PENDIENTE'],
            'cerrados' => $cerrados,
            'porcentaje' => $total ? (int) round($cerrados * 100 / $total) : 0,
            'monto' => round($comprobantes->sum('total'), 2),
            'efectivo' => round($comprobantes->sum('efectivo'), 2),
            'qr' => round($comprobantes->sum('qr'), 2),
            'credito' => round($comprobantes->where('credito', true)->whereIn('estado', ['ENTREGADO', 'RETORNO PARCIAL'])->sum('cobrable'), 2),
        ];
    }

    /**
     * Los comprobantes de la jornada que salieron en algun camion; con
     * $vendedorCi, solo los de ese vendedor.
     */
    private function comprobantes($fecha, $tipo, $vendedorCi = null)
    {
        $facturas = DB::table('facturas as f')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'f.cliente_id')
            ->whereNull('f.deleted_at')
            ->where('f.estado', '<>', 'ANULADO')
            ->tap(function ($consulta) use ($fecha) {
                CargaCamion::enJornada($consulta, $fecha);
            })
            // Con un tipo elegido la venta directa (sin tipo) no entra.
            ->when($tipo, function ($q) use ($tipo) {
                $q->whereRaw('UPPER(TRIM(f.pedido_tipo)) = ?', [$tipo]);
            })
            ->when($vendedorCi, function ($q) use ($vendedorCi) {
                $q->where('f.vendedor_ci', $vendedorCi);
            })
            ->orderBy('f.id')
            ->get([
                'f.id', 'f.tipo_comprobante', 'f.nro_factura', 'f.pedido_nro', 'f.pedido_tipo',
                'f.cliente_id', 'f.total', 'f.tipo_pago', 'f.fecha', 'f.hora',
                DB::raw("TRIM(COALESCE(f.placa, '')) as placa_directa"),
                DB::raw("TRIM(COALESCE(f.nombre, '')) as nombre"),
                DB::raw("TRIM(COALESCE(f.nit, '')) as nit"),
                DB::raw("TRIM(COALESCE(c.Nombres, '')) as cliente"),
                DB::raw("TRIM(COALESCE(c.Direccion, '')) as direccion"),
                DB::raw("TRIM(COALESCE(c.Telf, '')) as telefono"),
                'c.Latitud as latitud', 'c.longitud as longitud',
            ]);

        if ($facturas->isEmpty()) {
            return collect();
        }

        // Camion y color de zona: los del pedido; la venta directa trae su
        // camion y toma el color de la ultima asignacion de ese camion.
        $pedidos = collect();
        $nros = $facturas->pluck('pedido_nro')->filter()->unique()->values()->all();
        if ($nros) {
            $pedidos = DB::table('tbpedidos')
                ->whereNull('deleted_at')
                ->whereIn('NroPed', $nros)
                ->where('bonificacion', 0)
                ->groupBy('NroPed', DB::raw(TipoPedido::sql('')))
                ->get([
                    'NroPed', DB::raw(TipoPedido::sqlAgrupado('') . ' as tipo'),
                    DB::raw("TRIM(COALESCE(MIN(placa), '')) as placa"),
                    DB::raw("TRIM(COALESCE(MIN(colorStyle), '')) as colorStyle"),
                ])
                ->keyBy(function ($p) { return $p->NroPed . '-' . $p->tipo; });
        }
        $placasDirectas = $facturas->whereNull('pedido_nro')->pluck('placa_directa')->filter()->unique()->values()->all();
        $colorDirecta = $placasDirectas ? CargaCamion::coloresDeZona($placasDirectas, $fecha) : [];
        $zonas = DB::table('colores')->whereNull('deleted_at')->get(['zona', 'colorStyle'])
            ->mapWithKeys(function ($z) {
                return preg_match('/#[0-9a-f]{6}/i', (string) $z->colorStyle, $m)
                    ? [strtoupper($m[0]) => trim((string) $z->zona)] : [];
            });

        $ids = $facturas->pluck('id')->all();
        // La ultima entrega de cada comprobante es la que vale.
        $entregas = DB::table('entregas')->whereIn('factura_id', $ids)->orderBy('id')
            ->get(['factura_id', 'estado', 'tipago', 'monto', 'monto_efectivo', 'monto_qr',
                'observacion', 'hora', 'despachador'])
            ->keyBy('factura_id');
        $cargas = DB::table('carga_verificaciones')->whereIn('factura_id', $ids)
            ->get(['factura_id', 'verificado', 'observado', 'nro_canasta'])->keyBy('factura_id');

        return $facturas->map(function ($f) use ($pedidos, $colorDirecta, $zonas, $entregas, $cargas) {
            if ($f->pedido_nro) {
                $pedido = $pedidos->get($f->pedido_nro . '-' . strtoupper(trim((string) $f->pedido_tipo)));
                $placa = $pedido->placa ?? '';
                $estilo = $pedido->colorStyle ?? '';
            } else {
                $placa = $f->placa_directa;
                $estilo = $colorDirecta[$placa]['colorStyle'] ?? '';
            }
            // Sin camion es venta de mostrador: no se reparte.
            if ($placa === '') {
                return null;
            }
            $hex = preg_match('/#[0-9a-f]{6}/i', $estilo, $m) ? strtoupper($m[0]) : null;

            $entrega = $entregas->get($f->id);
            $estado = $entrega ? (trim((string) $entrega->estado) ?: 'PENDIENTE') : 'PENDIENTE';
            $cobrada = in_array($estado, ['ENTREGADO', 'RETORNO PARCIAL'], true);
            $carga = $cargas->get($f->id);
            $credito = in_array(mb_strtoupper(trim((string) $f->tipo_pago), 'UTF-8'), ['CRÉDITO', 'CREDITO'], true);
            $total = round((float) $f->total, 2);

            return [
                'factura_id' => (int) $f->id,
                'tipo_comprobante' => $f->tipo_comprobante,
                'nro_factura' => $f->nro_factura,
                'pedido_nro' => $f->pedido_nro ? (int) $f->pedido_nro : null,
                'pedido_tipo' => strtoupper(trim((string) $f->pedido_tipo)) ?: null,
                'hora' => substr((string) $f->hora, 0, 5),
                'cliente_id' => $f->cliente_id ? (int) $f->cliente_id : null,
                'cliente' => $f->cliente ?: ($f->nombre ?: 'Sin cliente'),
                'nit' => $f->nit,
                'direccion' => $f->direccion,
                'telefono' => $f->telefono,
                'lat' => self::coordenada($f->latitud),
                'lng' => self::coordenada($f->longitud),
                'placa' => $placa,
                'zona_hex' => $hex,
                'zona' => $hex ? ($zonas[$hex] ?? '') : '',
                'total' => $total,
                'tipo_pago' => $f->tipo_pago,
                'credito' => $credito,
                'estado' => $estado,
                'tipago' => $entrega->tipago ?? null,
                // Lo que se cobro con retorno parcial es menos que la nota.
                'cobrable' => $entrega && $cobrada ? round((float) $entrega->monto, 2) : $total,
                'efectivo' => $cobrada ? round((float) $entrega->monto_efectivo, 2) : 0.0,
                'qr' => $cobrada ? round((float) $entrega->monto_qr, 2) : 0.0,
                'observacion' => trim((string) ($entrega->observacion ?? '')) ?: null,
                'entrega_hora' => $entrega ? substr((string) $entrega->hora, 0, 5) : null,
                'caminero' => $entrega ? trim((string) $entrega->despachador) : null,
                'carga' => $carga ? ($carga->observado ? 'OBSERVADA' : ($carga->verificado ? 'VERIFICADA' : 'PENDIENTE')) : 'PENDIENTE',
                'canasta' => $carga->nro_canasta ?? null,
            ];
        })->filter()->values();
    }

    /** Coordenada cargada a mano como numero, o null si no se puede leer. */
    private static function coordenada($valor)
    {
        $texto = preg_replace('/[^0-9.\-]/', '', str_replace(',', '.', trim((string) $valor)));

        return is_numeric($texto) && (float) $texto != 0.0 ? (float) $texto : null;
    }
}
