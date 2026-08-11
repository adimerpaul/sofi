<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Consulta de ventas sobre la tabla legada tbpedidos.
 *
 * En tbpedidos cada fila es una linea de producto; una venta es el conjunto
 * de filas que comparten NroPed. El NroPed se reutiliza en unos pocos casos
 * (10 de 131.740), por eso se agrupa por NroPed + idCli.
 *
 * Con ~347.000 filas el rango de fechas es lo que hace usable la consulta
 * (0,05 s con rango vs 1,35 s sin el), por eso el front siempre manda uno.
 */
class VentaController extends Controller
{
    /** Columnas por las que se puede ordenar (evita SQL armado con input del usuario). */
    private const ORDENABLES = [
        'fecha'    => 'fecha',
        'NroPed'   => 'p.NroPed',
        'cliente'  => 'cliente',
        'vendedor' => 'vendedor',
        'items'    => 'items',
        'cantidad' => 'cantidad',
        'total'    => 'total',
    ];

    public function index(Request $request)
    {
        $perPage = (int) $request->input('perPage', 20);
        if ($perPage < 1 || $perPage > 200) {
            $perPage = 20;
        }

        $sortBy = $request->input('sortBy', 'fecha');
        $columna = self::ORDENABLES[$sortBy] ?? 'fecha';
        $direccion = $request->boolean('descending', true) ? 'desc' : 'asc';

        return $this->baseQuery($request)
            ->selectRaw("
                p.NroPed,
                p.idCli,
                MIN(p.fecha) as fecha,
                MAX(TRIM(c.Nombres)) as cliente,
                MAX(TRIM(c.Direccion)) as direccion,
                MAX(TRIM(c.zona)) as zona,
                MIN(p.CIfunc) as CIfunc,
                MAX(TRIM(CONCAT_WS(' ', NULLIF(TRIM(l.Nombre1), ''), NULLIF(TRIM(l.App1), '')))) as vendedor,
                COUNT(*) as items,
                SUM(p.Cant) as cantidad,
                SUM(p.subtotal) as total,
                MAX(p.tipo) as tipo,
                MAX(p.estado) as estado,
                MAX(p.pagado) as pagado
            ")
            ->orderByRaw($columna . ' ' . $direccion)
            ->orderBy('p.NroPed', 'desc')
            ->paginate($perPage);
    }

    /**
     * Totales de TODO el filtro (no solo de la pagina visible), para las
     * tarjetas de resumen de la pantalla.
     */
    public function resumen(Request $request)
    {
        $sub = $this->baseQuery($request)
            ->selectRaw('SUM(p.subtotal) as total, COUNT(*) as items');

        $row = DB::query()
            ->fromSub($sub, 'v')
            ->selectRaw('
                COUNT(*) as ventas,
                COALESCE(SUM(v.total), 0) as total,
                COALESCE(SUM(v.items), 0) as items
            ')
            ->first();

        return response()->json([
            'ventas' => (int) $row->ventas,
            'items'  => (int) $row->items,
            'total'  => round((float) $row->total, 2),
        ]);
    }

    /** Lineas de producto de una venta. */
    public function detalle(Request $request, $nroped)
    {
        $query = DB::table('tbpedidos as p')
            ->leftJoin('tbproductos as pr', 'pr.cod_prod', '=', 'p.cod_prod')
            ->where('p.NroPed', $nroped)
            ->select([
                'p.codAut',
                'p.cod_prod',
                DB::raw('TRIM(pr.Producto) as producto'),
                DB::raw('TRIM(pr.codUnid) as unidad'),
                'p.Cant as cantidad',
                'p.precio',
                'p.subtotal',
                'p.tipo',
                'p.fecha',
                'p.estado',
                DB::raw('TRIM(p.Observaciones) as observaciones'),
            ])
            ->orderBy('p.codAut');

        $idCli = $request->input('idCli');
        if ($idCli !== null && $idCli !== '') {
            $query->where('p.idCli', $idCli);
        }

        return $query->get();
    }

    /** Opciones para los selects de filtro. */
    public function filtros()
    {
        $vendedores = DB::table('tbpedidos as p')
            ->join('personal as l', 'l.CodAut', '=', 'p.CIfunc')
            ->select([
                'p.CIfunc as value',
                DB::raw("TRIM(CONCAT_WS(' ', NULLIF(TRIM(l.Nombre1), ''), NULLIF(TRIM(l.App1), ''))) as label"),
            ])
            ->distinct()
            ->orderBy('label')
            ->get();

        return response()->json([
            'vendedores' => $vendedores,
            'tipos'      => $this->valoresDistintos('tipo'),
            'estados'    => $this->valoresDistintos('estado'),
        ]);
    }

    private function valoresDistintos($columna)
    {
        return DB::table('tbpedidos')
            ->select($columna)
            ->whereNotNull($columna)
            ->where($columna, '<>', '')
            ->groupBy($columna)
            ->orderBy($columna)
            ->pluck($columna);
    }

    /**
     * Consulta agrupada con todos los filtros aplicados; la comparten el
     * listado y el resumen para que los totales siempre cuadren con la tabla.
     */
    private function baseQuery(Request $request)
    {
        $query = DB::table('tbpedidos as p')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'p.idCli')
            ->leftJoin('personal as l', 'l.CodAut', '=', 'p.CIfunc')
            ->groupBy('p.NroPed', 'p.idCli');

        // Se comparan datetime completos en vez de DATE(p.fecha) para no
        // anular el indice idx_fecha_estado_tipo.
        if ($desde = $request->input('desde')) {
            $query->where('p.fecha', '>=', $desde . ' 00:00:00');
        }
        if ($hasta = $request->input('hasta')) {
            $query->where('p.fecha', '<=', $hasta . ' 23:59:59');
        }

        if ($tipo = trim((string) $request->input('tipo', ''))) {
            $query->where('p.tipo', $tipo);
        }
        if ($estado = trim((string) $request->input('estado', ''))) {
            $query->where('p.estado', $estado);
        }
        if ($vendedor = trim((string) $request->input('vendedor', ''))) {
            $query->where('p.CIfunc', $vendedor);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($w) use ($like, $search) {
                $w->where('c.Nombres', 'like', $like)
                    ->orWhere('c.Direccion', 'like', $like)
                    ->orWhere('c.zona', 'like', $like)
                    ->orWhere('l.Nombre1', 'like', $like)
                    ->orWhere('l.App1', 'like', $like);
                if (ctype_digit($search)) {
                    $w->orWhere('p.NroPed', $search);
                }
            });
        }

        // El filtro por producto va como EXISTS: si se filtrara directo sobre
        // p.cod_prod se perderian las demas lineas de la venta y los totales
        // saldrian mal.
        $producto = trim((string) $request->input('producto', ''));
        if ($producto !== '') {
            $like = '%' . $producto . '%';
            $query->whereExists(function ($s) use ($like) {
                $s->select(DB::raw(1))
                    ->from('tbpedidos as p2')
                    ->leftJoin('tbproductos as pr', 'pr.cod_prod', '=', 'p2.cod_prod')
                    ->whereColumn('p2.NroPed', 'p.NroPed')
                    ->whereColumn('p2.idCli', 'p.idCli')
                    ->where(function ($w) use ($like) {
                        $w->where('p2.cod_prod', 'like', $like)
                            ->orWhere('pr.Producto', 'like', $like);
                    });
            });
        }

        return $query;
    }
}
