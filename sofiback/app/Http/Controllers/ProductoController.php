<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller{
    public function index(){
//        return DB::SELECT("SELECT p.cod_prod,p.Producto,p.Precio,p.codUnid,p.tipo, (select (SUM(s.cant)-SUM(s.saldo)) from tbstock s where s.cod_prod=p.cod_prod group by p.cod_prod) as cantidad from tbproductos p");
        $productos = Producto::select([
            'cod_prod',
            'Producto',
            'Precio',
            'codUnid',
            'tipo',
            DB::raw('(SELECT SUM(s.cant - s.saldo) FROM tbstock s WHERE s.cod_prod = tbproductos.cod_prod) as cantidad')
        ])
//            que no tena el texto inactivo
            ->where('Producto', 'not like', '%inactivo%')
            ->orderByDesc('cantidad')
            ->get();

        return $productos;
    }

    /** Columnas por las que se puede ordenar el catalogo paginado. */
    private const ORDENABLES = [
        'cod_prod' => 'p.cod_prod',
        'Producto' => 'p.Producto',
        'grupo'    => 'grupo',
        'codUnid'  => 'p.codUnid',
        'Precio'   => 'p.Precio',
        'PreCosto' => 'p.PreCosto',
        'cantidad' => 'cantidad',
    ];

    /** Stock disponible: mismo calculo que usa index(). */
    private const SQL_STOCK = '(SELECT COALESCE(SUM(s.cant - s.saldo), 0) FROM tbstock s WHERE s.cod_prod = p.cod_prod)';

    /**
     * Catalogo con paginacion, busqueda y filtros del lado del servidor.
     * Reemplaza al volcado completo de verProducto() en la pantalla Productos.
     */
    public function paginado(Request $request)
    {
        $perPage = (int) $request->input('perPage', 20);
        if ($perPage < 1 || $perPage > 200) {
            $perPage = 20;
        }

        $sortBy = $request->input('sortBy', 'Producto');
        $columna = self::ORDENABLES[$sortBy] ?? 'p.Producto';
        $direccion = $request->boolean('descending') ? 'desc' : 'asc';

        $query = DB::table('tbproductos as p')
            ->leftJoin('tbgrupos as g', function ($join) {
                $join->on(DB::raw('TRIM(g.Cod_grup)'), '=', DB::raw('TRIM(p.cod_grup)'));
            })
            ->select([
                'p.CodAut',
                DB::raw('TRIM(p.cod_prod) as cod_prod'),
                DB::raw('TRIM(p.Producto) as Producto'),
                DB::raw('TRIM(p.Nomcomer) as Nomcomer'),
                DB::raw('TRIM(p.cod_grup) as cod_grup'),
                DB::raw('TRIM(g.Descripcion) as grupo'),
                DB::raw('TRIM(p.codUnid) as codUnid'),
                'p.tipo',
                'p.Precio', 'p.Precio_Costo', 'p.Precio3', 'p.Precio4', 'p.Precio5',
                'p.Precio6', 'p.Precio7', 'p.Precio8', 'p.Precio9', 'p.Precio10',
                'p.Precio11', 'p.Precio12', 'p.Precio13', 'p.PreCosto',
                DB::raw(self::SQL_STOCK . ' as cantidad'),
            ]);

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($w) use ($like) {
                $w->where('p.cod_prod', 'like', $like)
                    ->orWhere('p.Producto', 'like', $like)
                    ->orWhere('p.Nomcomer', 'like', $like);
            });
        }

        if ($grupo = trim((string) $request->input('grupo', ''))) {
            $query->whereRaw('TRIM(p.cod_grup) = ?', [$grupo]);
        }
        if ($unidad = trim((string) $request->input('unidad', ''))) {
            $query->whereRaw('TRIM(p.codUnid) = ?', [$unidad]);
        }
        if ($request->boolean('conStock')) {
            $query->whereRaw(self::SQL_STOCK . ' > 0');
        }
        // Por convencion del negocio los productos dados de baja llevan
        // "inactivo" en el nombre; se ocultan salvo que se pidan.
        if (!$request->boolean('incluirInactivos')) {
            $query->where('p.Producto', 'not like', '%inactivo%');
        }

        return $query
            ->orderByRaw($columna . ' ' . $direccion)
            ->orderBy('p.cod_prod')
            ->paginate($perPage);
    }

    /** Opciones para los selects de filtro del catalogo. */
    public function filtrosProducto()
    {
        return response()->json([
            'grupos' => DB::table('tbproductos as p')
                ->join('tbgrupos as g', DB::raw('TRIM(g.Cod_grup)'), '=', DB::raw('TRIM(p.cod_grup)'))
                ->select([
                    DB::raw('TRIM(p.cod_grup) as value'),
                    DB::raw('TRIM(g.Descripcion) as label'),
                ])
                ->distinct()
                ->orderBy('label')
                ->get(),
            'unidades' => DB::table('tbproductos')
                ->select(DB::raw('TRIM(codUnid) as unidad'))
                ->whereNotNull('codUnid')
                ->where('codUnid', '<>', '')
                ->groupBy(DB::raw('TRIM(codUnid)'))
                ->orderBy('unidad')
                ->pluck('unidad'),
        ]);
    }

    public function listProducto(){
        return DB::select("SELECT p.cod_prod,p.Producto,p.Precio,p.Precio_Costo,p.Precio3,p.Precio4,p.Precio5,p.Precio6,p.Precio7,p.Precio8,p.Precio9,p.Precio10,p.Precio11,p.Precio12,p.Precio13,p.PreCosto,
        p.codUnid,p.tipo,SUM(s.cant) cantidad
        FROM tbproductos p
        INNER JOIN tbstock s ON s.cod_prod=p.cod_prod
        GROUP BY p.cod_prod,p.Producto,p.Precio,p.Precio_Costo,p.Precio3,p.Precio4,p.Precio5,p.Precio6,p.Precio7,p.Precio8,p.Precio9,p.Precio10,p.Precio11,p.Precio12,p.Precio13,p.PreCosto,p.codUnid,p.tipo");
    }

    public function verProducto(){
        return DB::select("SELECT p.cod_prod,p.Producto,p.Precio,p.Precio_Costo,p.Precio3,p.Precio4,p.Precio5,p.Precio6,p.Precio7,p.Precio8,p.Precio9,p.Precio10,p.Precio11,p.Precio12,p.Precio13,p.PreCosto,
        p.codUnid,p.tipo
        FROM tbproductos p
                ");
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
