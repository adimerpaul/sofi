<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pareto de clientes del preventista (permiso paretoclientes).
 *
 * El preventista elige uno o varios productos y ve que clientes suyos se lo
 * pidieron, de mayor a menor (por peso o por cantidad), con la ultima vez que
 * lo pidieron: para saber a quien ofrecerle ese producto.
 *
 * Sale de sus propios pedidos (tbpedidos.CIfunc = su CodAut), lineas por
 * producto, sin borrados, ocultos ni productos INACTIVO.
 */
class ParetoClienteController extends Controller
{
    /** Unidad de la linea: la del pedido (caja) o la del producto; el cerdo va por kilo. */
    private const UNIDAD_SQL = "CASE WHEN UPPER(TRIM(pr.tipo)) = 'CERDO' THEN 'KG'
        ELSE COALESCE(NULLIF(TRIM(p.caja), ''), NULLIF(TRIM(pr.codUnid), ''), 'U') END";

    /** Lo que se cuenta como kilos: lo que va por KG tal cual; lo demas, cantidad x peso por unidad. */
    private const PESO_SQL = "CASE WHEN (CASE WHEN UPPER(TRIM(pr.tipo)) = 'CERDO' THEN 'KG'
        ELSE COALESCE(NULLIF(TRIM(p.caja), ''), NULLIF(TRIM(pr.codUnid), ''), 'U') END) = 'KG'
        THEN p.Cant ELSE p.Cant * COALESCE(pr.CantPren, 0) END";

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
                DB::raw('COUNT(DISTINCT p.idCli) as clientes'),
            ]);
    }

    /** Los productos que el preventista ya vendio, para el buscador (si viene grupos[], solo de esos grupos). */
    public function productos(Request $request)
    {
        $buscar = trim((string) $request->query('buscar', ''));

        return $this->conGrupos($this->lineas($request), $request)
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($w) use ($buscar) {
                    $w->where('pr.Producto', 'like', '%' . $buscar . '%')
                        ->orWhere('p.cod_prod', 'like', $buscar . '%');
                });
            })
            ->groupBy(DB::raw('TRIM(p.cod_prod)'))
            ->orderByRaw('COUNT(DISTINCT p.idCli) DESC')
            ->limit(50)
            ->get([
                DB::raw('TRIM(p.cod_prod) as cod_prod'),
                DB::raw('MAX(TRIM(pr.Producto)) as producto'),
                DB::raw('MAX(TRIM(g.Descripcion)) as grupo'),
                DB::raw('COUNT(DISTINCT p.idCli) as clientes'),
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
        $datos = $request->validate([
            'productos'   => 'required_without:grupos|array',
            'productos.*' => 'string|max:30',
            'grupos'      => 'required_without:productos|array',
            'grupos.*'    => 'string|max:30',
            'medida'      => 'nullable|in:peso,cantidad',
            'desde'       => 'nullable|date_format:Y-m-d',
            'hasta'       => 'nullable|date_format:Y-m-d',
        ]);
        $medida = $datos['medida'] ?? 'peso';
        $productos = array_map('trim', $datos['productos'] ?? []);
        $grupos = array_map('trim', $datos['grupos'] ?? []);

        $lineas = $this->lineas($request)
            ->when($productos, function ($q) use ($productos) {
                $q->whereIn(DB::raw('TRIM(p.cod_prod)'), $productos);
            }, function ($q) use ($grupos) {
                $q->whereIn(DB::raw('TRIM(g.Cod_grup)'), $grupos);
            })
            ->orderBy('p.fecha')
            ->get([
                'p.idCli', 'p.NroPed', 'p.fecha', 'p.Cant', 'p.subtotal',
                DB::raw('TRIM(p.cod_prod) as cod_prod'),
                DB::raw(self::UNIDAD_SQL . ' as unidad'),
                DB::raw('(' . self::PESO_SQL . ') as peso'),
                DB::raw('TRIM(c.Nombres) as cliente'), DB::raw('TRIM(c.Id) as nit'),
                DB::raw('TRIM(c.zona) as zona'), DB::raw('TRIM(c.Direccion) as direccion'),
                DB::raw('TRIM(c.Telf) as telefono'),
            ]);

        $clientes = [];
        foreach ($lineas as $l) {
            $id = (int) $l->idCli;
            if (!isset($clientes[$id])) {
                $clientes[$id] = [
                    'cliente_id' => $id,
                    'cliente'    => (string) $l->cliente,
                    'nit'        => (string) $l->nit,
                    'zona'       => (string) $l->zona,
                    'direccion'  => (string) $l->direccion,
                    'telefono'   => (string) $l->telefono,
                    'pedidos'    => [],
                    'cantidad'   => 0.0,
                    'peso'       => 0.0,
                    'importe'    => 0.0,
                    'unidades'   => [],
                ];
            }
            $c = &$clientes[$id];
            $c['pedidos'][$l->NroPed] = true;
            $c['cantidad'] += (float) $l->Cant;
            $c['peso'] += (float) $l->peso;
            $c['importe'] += (float) $l->subtotal;
            $c['unidades'][$l->unidad] = true;
            // Vienen por fecha: la ultima linea que pasa es la ultima vez.
            $c['ultima_fecha'] = substr((string) $l->fecha, 0, 10);
            $c['ultima_cantidad'] = (float) $l->Cant;
            $c['ultima_unidad'] = (string) $l->unidad;
            unset($c);
        }

        $filas = array_map(function ($c) {
            $c['pedidos'] = count($c['pedidos']);
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
                'clientes' => count($filas),
                'pedidos'  => count(array_unique(array_column($lineas->all(), 'NroPed'))),
                'cantidad' => round(array_sum(array_column($filas, 'cantidad')), 3),
                'peso'     => round(array_sum(array_column($filas, 'peso')), 3),
                'importe'  => round(array_sum(array_column($filas, 'importe')), 2),
                'clase_a'  => count(array_filter($filas, function ($f) { return $f['clase'] === 'A'; })),
            ],
        ];
    }

    /** Deja solo los grupos de ?grupos[] (sin filtro si no viene ninguno). */
    private function conGrupos($consulta, Request $request)
    {
        $grupos = array_filter(array_map('trim', (array) $request->query('grupos', [])), 'strlen');

        return $consulta->when($grupos, function ($q) use ($grupos) {
            $q->whereIn(DB::raw('TRIM(g.Cod_grup)'), array_values($grupos));
        });
    }

    /** Las lineas de pedido del preventista que entra, en el rango (por defecto, los ultimos 90 dias). */
    private function lineas(Request $request)
    {
        abort_unless($request->user() && $request->user()->can('paretoclientes'), 403, 'No tiene permiso para el pareto de clientes');

        $hasta = $request->query('hasta') ?: date('Y-m-d');
        $desde = $request->query('desde') ?: date('Y-m-d', strtotime($hasta . ' -90 days'));

        return DB::table('tbpedidos as p')
            ->join('tbclientes as c', 'c.Cod_Aut', '=', 'p.idCli')
            ->leftJoin('tbproductos as pr', DB::raw('TRIM(pr.cod_prod)'), '=', DB::raw('TRIM(p.cod_prod)'))
            ->leftJoin('tbgrupos as g', DB::raw('TRIM(g.Cod_grup)'), '=', DB::raw('TRIM(pr.cod_grup)'))
            ->whereNull('p.deleted_at')
            ->where('p.oculto', 0)
            ->whereNotNull('p.Cant')
            ->whereRaw("TRIM(COALESCE(p.cod_prod, '')) <> ''")
            // Los INACTIVO ya no se venden (501607 es el relleno de los pedidos viejos de pollo).
            ->whereRaw("COALESCE(pr.Producto, '') NOT LIKE '%INACTIVO%'")
            ->whereRaw('TRIM(p.CIfunc) = ?', [(string) $request->user()->CodAut])
            ->whereRaw('DATE(p.fecha) BETWEEN ? AND ?', [min($desde, $hasta), max($desde, $hasta)]);
    }
}
