<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pareto de clientes del preventista (permiso paretoclientes).
 *
 * El preventista elige uno o varios productos (o un grupo) y ve que clientes
 * suyos se lo compraron, de mayor a menor (por peso o por cantidad), con la
 * ultima vez que lo compraron: para saber a quien ofrecerle ese producto.
 *
 * Sale de lo facturado, no de los pedidos: facturas y vouchers no anulados
 * de sus ventas (facturas.vendedor_ci = su ci), por fecha de entrega, sin
 * productos INACTIVO. El peso es el de factura_detalles (lo pesado en caja),
 * con el mismo criterio que el detalle de ventas de Reportes.
 */
class ParetoClienteController extends Controller
{
    /** Los grupos de producto (tbgrupos) que el preventista ya vendio. */
    public function grupos(Request $request)
    {
        return $this->lineas($request)
            ->whereNotNull('g.Cod_grup')
            ->groupBy(DB::raw('TRIM(g.Cod_grup)'))
            ->orderByRaw('MAX(TRIM(g.Descripcion))')
            ->get([
                DB::raw('TRIM(g.Cod_grup) as cod_grup'),
                DB::raw('MAX(TRIM(g.Descripcion)) as grupo'),
                DB::raw('COUNT(DISTINCT f.cliente_id) as clientes'),
            ]);
    }

    /** Los productos que el preventista ya vendio, para el buscador (si viene grupos[], solo de esos grupos). */
    public function productos(Request $request)
    {
        $buscar = trim((string) $request->query('buscar', ''));

        return $this->conGrupos($this->lineas($request), $request)
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($w) use ($buscar) {
                    $w->where('d.nombre', 'like', '%' . $buscar . '%')
                        ->orWhere('d.cod_prod', 'like', $buscar . '%');
                });
            })
            ->groupBy(DB::raw('TRIM(d.cod_prod)'))
            ->orderByRaw('COUNT(DISTINCT f.cliente_id) DESC')
            ->limit(50)
            ->get([
                DB::raw('TRIM(d.cod_prod) as cod_prod'),
                DB::raw('MAX(TRIM(d.nombre)) as producto'),
                DB::raw('MAX(TRIM(g.Descripcion)) as grupo'),
                DB::raw('COUNT(DISTINCT f.cliente_id) as clientes'),
            ]);
    }

    /**
     * Un cliente por fila, de mayor a menor por la medida elegida, con su
     * porcentaje, el acumulado y la clase (A hasta el 80%, B hasta el 95%,
     * C el resto).
     *
     * Con productos elegidos entran solo esos (el grupo solo acota el
     * buscador); sin productos, todo lo de los grupos elegidos.
     */
    public function index(Request $request)
    {
        $medida = $this->validar($request)['medida'] ?? 'peso';

        $lineas = $this->filtradas($request)
            ->orderBy('f.fecha_entrega')
            ->orderBy('f.id')
            ->get([
                'f.id as factura_id', 'f.cliente_id', 'f.fecha_entrega',
                'd.cantidad', 'd.peso', 'd.unidad', 'd.subtotal', 'pr.CantPren',
                DB::raw('TRIM(c.Nombres) as cliente'), DB::raw('TRIM(c.Id) as nit'),
                DB::raw('TRIM(c.zona) as zona'), DB::raw('TRIM(c.Direccion) as direccion'),
                DB::raw('TRIM(c.Telf) as telefono'),
            ]);

        $clientes = [];
        foreach ($lineas as $l) {
            $id = (int) $l->cliente_id;
            if (!isset($clientes[$id])) {
                $clientes[$id] = [
                    'cliente_id'   => $id,
                    'cliente'      => (string) $l->cliente,
                    'nit'          => (string) $l->nit,
                    'zona'         => (string) $l->zona,
                    'direccion'    => (string) $l->direccion,
                    'telefono'     => (string) $l->telefono,
                    'comprobantes' => [],
                    'cantidad'     => 0.0,
                    'peso'         => 0.0,
                    'importe'      => 0.0,
                    'unidades'     => [],
                ];
            }
            $unidad = $this->unidad($l);
            $c = &$clientes[$id];
            $c['comprobantes'][$l->factura_id] = true;
            $c['cantidad'] += (float) $l->cantidad;
            $c['peso'] += $this->peso($l);
            $c['importe'] += (float) $l->subtotal;
            $c['unidades'][$unidad] = true;
            // Vienen por fecha: la ultima linea que pasa es la ultima compra.
            $c['ultima_fecha'] = (string) $l->fecha_entrega;
            $c['ultima_cantidad'] = (float) $l->cantidad;
            $c['ultima_unidad'] = $unidad;
            unset($c);
        }

        $filas = array_map(function ($c) {
            $c['comprobantes'] = count($c['comprobantes']);
            $c['unidades'] = implode('/', array_keys($c['unidades']));
            $c['cantidad'] = round($c['cantidad'], 3);
            $c['peso'] = round($c['peso'], 3);
            $c['importe'] = round($c['importe'], 2);
            $c['dias'] = (int) floor((strtotime(date('Y-m-d')) - strtotime($c['ultima_fecha'])) / 86400);
            return $c;
        }, array_values($clientes));

        usort($filas, function ($a, $b) use ($medida) {
            return [$b[$medida], $b['ultima_fecha']] <=> [$a[$medida], $a['ultima_fecha']];
        });

        $total = array_sum(array_column($filas, $medida));
        $acumulado = 0.0;
        foreach ($filas as $i => &$f) {
            $acumulado += $f[$medida];
            $f['puesto'] = $i + 1;
            $f['porcentaje'] = $total > 0 ? round($f[$medida] / $total * 100, 1) : 0;
            $f['acumulado'] = $total > 0 ? round($acumulado / $total * 100, 1) : 0;
            // La clase sale de donde arranca el cliente: el que cruza el 80% todavia es A.
            $antes = $total > 0 ? ($acumulado - $f[$medida]) / $total * 100 : 0;
            $f['clase'] = $antes < 80 ? 'A' : ($antes < 95 ? 'B' : 'C');
        }
        unset($f);

        return [
            'medida'  => $medida,
            'filas'   => $filas,
            'totales' => [
                'clientes'     => count($filas),
                'comprobantes' => count(array_unique(array_column($lineas->all(), 'factura_id'))),
                'cantidad'     => round(array_sum(array_column($filas, 'cantidad')), 3),
                'peso'         => round(array_sum(array_column($filas, 'peso')), 3),
                'importe'      => round(array_sum(array_column($filas, 'importe')), 2),
                'clase_a'      => count(array_filter($filas, function ($f) { return $f['clase'] === 'A'; })),
            ],
        ];
    }

    /**
     * Lo que se le facturo a un cliente en el rango: un comprobante por fila
     * con sus lineas, el mas reciente primero.
     *
     * Cada comprobante va completo, con todas sus lineas, y cada linea dice
     * si es del producto o grupo filtrado (coincide). Sin ?todo solo los
     * comprobantes donde estuvo lo filtrado; con ?todo=1 todas sus compras,
     * como en Facturacion.
     */
    public function detalle(Request $request, $cliente)
    {
        $this->validar($request);
        $todo = $request->boolean('todo');
        $productos = array_values(array_filter(array_map('trim', (array) $request->query('productos', [])), 'strlen'));
        $grupos = array_values(array_filter(array_map('trim', (array) $request->query('grupos', [])), 'strlen'));
        $coincide = function ($l) use ($productos, $grupos) {
            return $productos
                ? in_array($l->cod_prod, $productos, true)
                : in_array(trim((string) $l->cod_grup), $grupos, true);
        };

        $lineas = $this->lineas($request)
            ->where('f.cliente_id', (int) $cliente)
            ->orderByDesc('f.fecha_entrega')
            ->orderByDesc('f.id')
            ->orderBy('d.id')
            ->get([
                'f.id as factura_id', 'f.fecha_entrega', 'f.fecha', 'f.hora', 'f.tipo_comprobante',
                'f.nro_factura', 'f.tipo_pago', 'f.total', 'f.placa',
                DB::raw('TRIM(d.cod_prod) as cod_prod'), DB::raw('TRIM(d.nombre) as producto'),
                'd.cantidad', 'd.peso', 'd.unidad', 'd.precio', 'd.subtotal', 'pr.CantPren',
                DB::raw('TRIM(g.Cod_grup) as cod_grup'),
            ]);

        $comprobantes = [];
        foreach ($lineas as $l) {
            $id = (int) $l->factura_id;
            if (!isset($comprobantes[$id])) {
                $comprobantes[$id] = [
                    'factura_id'    => $id,
                    'fecha_entrega' => (string) $l->fecha_entrega,
                    'emitido'       => substr((string) $l->fecha, 0, 10) . ' ' . substr((string) $l->hora, 0, 5),
                    'comprobante'   => $l->tipo_comprobante === 'FACTURA' ? 'Factura ' . $l->nro_factura : 'Voucher ' . $id,
                    'tipo_pago'     => (string) $l->tipo_pago,
                    'placa'         => trim((string) $l->placa),
                    // El total del comprobante entero, no solo de lo filtrado.
                    'total'         => round((float) $l->total, 2),
                    // Lo del producto o grupo filtrado dentro del comprobante.
                    'importe'       => 0.0,
                    'coincide'      => false,
                    'lineas'        => [],
                ];
            }
            $esDelFiltro = $coincide($l);
            if ($esDelFiltro) {
                $comprobantes[$id]['importe'] = round($comprobantes[$id]['importe'] + (float) $l->subtotal, 2);
                $comprobantes[$id]['coincide'] = true;
            }
            $comprobantes[$id]['lineas'][] = [
                'cod_prod' => $l->cod_prod,
                'producto' => $l->producto,
                'cantidad' => round((float) $l->cantidad, 3),
                'unidad'   => $this->unidad($l),
                'peso'     => round($this->peso($l), 3),
                'precio'   => round((float) $l->precio, 2),
                'importe'  => round((float) $l->subtotal, 2),
                'coincide' => $esDelFiltro,
            ];
        }

        if (!$todo) {
            $comprobantes = array_filter($comprobantes, function ($c) { return $c['coincide']; });
        }

        return ['comprobantes' => array_values($comprobantes)];
    }

    private function validar(Request $request)
    {
        return $request->validate([
            'productos'   => 'required_without:grupos|array',
            'productos.*' => 'string|max:30',
            'grupos'      => 'required_without:productos|array',
            'grupos.*'    => 'string|max:30',
            'medida'      => 'nullable|in:peso,cantidad',
            'desde'       => 'nullable|date_format:Y-m-d',
            'hasta'       => 'nullable|date_format:Y-m-d',
        ]);
    }

    /** Las lineas de los productos elegidos o, sin productos, de los grupos elegidos. */
    private function filtradas(Request $request)
    {
        $productos = array_values(array_filter(array_map('trim', (array) $request->query('productos', [])), 'strlen'));

        return $productos
            ? $this->lineas($request)->whereIn(DB::raw('TRIM(d.cod_prod)'), $productos)
            : $this->conGrupos($this->lineas($request), $request);
    }

    /** Deja solo los grupos de ?grupos[] (sin filtro si no viene ninguno). */
    private function conGrupos($consulta, Request $request)
    {
        $grupos = array_filter(array_map('trim', (array) $request->query('grupos', [])), 'strlen');

        return $consulta->when($grupos, function ($q) use ($grupos) {
            $q->whereIn(DB::raw('TRIM(g.Cod_grup)'), array_values($grupos));
        });
    }

    private function unidad($linea)
    {
        return strtoupper(trim((string) $linea->unidad)) ?: 'U';
    }

    /**
     * Kilos de una linea, como en el detalle de ventas: lo pesado en caja; si
     * no se peso aparte y va por KG, la cantidad; si va por unidad, cantidad x
     * el peso de cada unidad (tbproductos.CantPren).
     */
    private function peso($linea)
    {
        if ((float) $linea->peso > 0) {
            return (float) $linea->peso;
        }
        if ($this->unidad($linea) === 'KG') {
            return (float) $linea->cantidad;
        }
        return (float) $linea->cantidad * (float) $linea->CantPren;
    }

    /** Las lineas facturadas del preventista que entra, en el rango (por defecto, el mes de hasta). */
    private function lineas(Request $request)
    {
        $usuario = $request->user();
        abort_unless($usuario && $usuario->can('paretoclientes'), 403, 'No tiene permiso para el pareto de clientes');

        $hasta = $request->query('hasta') ?: date('Y-m-d');
        $desde = $request->query('desde') ?: substr($hasta, 0, 7) . '-01';

        return DB::table('facturas as f')
            ->join('factura_detalles as d', 'd.factura_id', '=', 'f.id')
            ->join('tbclientes as c', 'c.Cod_Aut', '=', 'f.cliente_id')
            ->leftJoin('tbproductos as pr', DB::raw('TRIM(pr.cod_prod)'), '=', DB::raw('TRIM(d.cod_prod)'))
            ->leftJoin('tbgrupos as g', DB::raw('TRIM(g.Cod_grup)'), '=', DB::raw('TRIM(pr.cod_grup)'))
            ->whereNull('f.deleted_at')
            ->whereNull('d.deleted_at')
            ->where('f.estado', '<>', 'ANULADO')
            ->whereRaw("TRIM(COALESCE(d.cod_prod, '')) <> ''")
            // Los INACTIVO ya no se venden.
            ->whereRaw("COALESCE(pr.Producto, '') NOT LIKE '%INACTIVO%'")
            ->whereRaw('TRIM(f.vendedor_ci) = ?', [trim((string) $usuario->ci)])
            ->whereBetween('f.fecha_entrega', [min($desde, $hasta), max($desde, $hasta)]);
    }
}
