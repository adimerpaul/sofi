<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

    /** Carpeta dentro de public/ donde viven las fotos de productos. */
    private const DIR_IMAGENES = 'uploads/productos';

    /** Campos editables que son numericos; el resto se trata como texto. */
    private const CAMPOS_NUMERICOS = [
        'Precio', 'Precio_Costo', 'Precio3', 'Precio4', 'Precio5', 'Precio6',
    ];

    /**
     * Sube (o reemplaza) la foto de un producto.
     *
     * Se guarda en WebP y limitada a 800px: las fotos vienen del celular y sin
     * reducirlas ocupan varios MB cada una. La anterior se borra para no dejar
     * huerfanos en disco.
     */
    public function subirImagen(Request $request, $codProd)
    {
        $request->validate([
            'imagen' => 'required|image|max:8192',
        ]);

        $producto = Producto::whereRaw('TRIM(cod_prod) = ?', [trim($codProd)])->first();
        if (!$producto) {
            return response()->json(['message' => 'El producto no existe'], 404);
        }

        $destino = public_path(self::DIR_IMAGENES);
        if (!is_dir($destino)) {
            @mkdir($destino, 0775, true);
        }

        $manager = new ImageManager(new Driver());
        $imagen = $manager->read($request->file('imagen')->getPathname());
        // scaleDown y no resizeDown: resizeDown fuerza el tamano exacto y
        // deforma la foto; scaleDown la encaja en la caja manteniendo la
        // proporcion y sin ampliar las que ya son pequenas.
        $imagen->scaleDown(width: 800, height: 800);

        $nombre = Str::uuid()->toString() . '.webp';
        $imagen->toWebp(quality: 80)->save($destino . DIRECTORY_SEPARATOR . $nombre);

        $anterior = $producto->imagen;
        $producto->imagen = self::DIR_IMAGENES . '/' . $nombre;
        $producto->save();

        $this->borrarImagen($anterior);

        return response()->json([
            'cod_prod' => trim($producto->cod_prod),
            'imagen'   => $producto->imagen,
            'message'  => 'Imagen actualizada',
        ]);
    }

    /**
     * Actualiza los datos editables de un producto.
     *
     * Se listan uno por uno a proposito: tbproductos tiene decenas de columnas
     * del sistema legado (codigos SIN, stock, banderas de caja) que no deben
     * poder tocarse desde la web. Lo que no este aca se ignora.
     */
    public function actualizar(Request $request, $codProd)
    {
        // Los max coinciden con el ancho real de cada columna de tbproductos.
        $datos = $request->validate([
            'Producto'     => 'required|string|max:105',
            'Nomcomer'     => 'nullable|string|max:190',
            'cod_grup'     => 'nullable|string|max:25',
            'codUnid'      => 'nullable|string|max:15',
            'tipo'         => 'nullable|string|max:255',
            'Precio'       => 'nullable|numeric|min:0',
            'Precio_Costo' => 'nullable|numeric|min:0',
            'Precio3'      => 'nullable|numeric|min:0',
            'Precio4'      => 'nullable|numeric|min:0',
            'Precio5'      => 'nullable|numeric|min:0',
            'Precio6'      => 'nullable|numeric|min:0',
        ]);

        $producto = Producto::whereRaw('TRIM(cod_prod) = ?', [trim($codProd)])->first();
        if (!$producto) {
            return response()->json(['message' => 'El producto no existe'], 404);
        }

        // El grupo tiene que existir: guardar un codigo suelto dejaria el
        // producto sin grupo en el catalogo y en los reportes.
        $grupo = trim((string) ($datos['cod_grup'] ?? ''));
        if ($grupo !== '') {
            $existe = DB::table('tbgrupos')->whereRaw('TRIM(Cod_grup) = ?', [$grupo])->exists();
            if (!$existe) {
                return response()->json(['message' => 'El grupo ' . $grupo . ' no existe'], 422);
            }
        }

        foreach ($datos as $campo => $valor) {
            if (in_array($campo, self::CAMPOS_NUMERICOS, true)) {
                $producto->$campo = $valor === null ? 0 : round((float) $valor, 3);
                continue;
            }

            // Ojo: las columnas de tbproductos son NOT NULL y sin default. Un
            // campo vacio del formulario llega aqui como null, porque Laravel
            // aplica ConvertEmptyStringsToNull, y guardarlo tal cual revienta
            // con "Column cannot be null". Va como cadena vacia.
            $producto->$campo = $valor === null ? '' : trim((string) $valor);
        }

        $producto->save();

        return response()->json([
            'message'  => 'Producto actualizado',
            'producto' => $producto->fresh(),
        ]);
    }

    /** Quita la foto del producto y la borra del disco. */
    public function quitarImagen($codProd)
    {
        $producto = Producto::whereRaw('TRIM(cod_prod) = ?', [trim($codProd)])->first();
        if (!$producto) {
            return response()->json(['message' => 'El producto no existe'], 404);
        }

        $anterior = $producto->imagen;
        $producto->imagen = null;
        $producto->save();

        $this->borrarImagen($anterior);

        return response()->json(['message' => 'Imagen quitada']);
    }

    /** Solo borra dentro de la carpeta de productos, nunca fuera. */
    private function borrarImagen($ruta)
    {
        if (!$ruta || strpos($ruta, self::DIR_IMAGENES . '/') !== 0) {
            return;
        }

        $archivo = public_path($ruta);
        if (is_file($archivo)) {
            @unlink($archivo);
        }
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
        // perPage=0 es el "Todos" de la tabla; se sirve con tope para que no
        // se pueda pedir el catalogo entero sin limite.
        $perPage = (int) $request->input('perPage', 15);
        if ($perPage === 0) {
            $perPage = 2000;
        } elseif ($perPage < 1 || $perPage > 2000) {
            $perPage = 15;
        }

        $sortBy = $request->input('sortBy', 'Producto');
        $columna = self::ORDENABLES[$sortBy] ?? 'p.Producto';
        $direccion = $request->boolean('descending') ? 'desc' : 'asc';

        return $this->queryCatalogo($request)
            ->orderByRaw($columna . ' ' . $direccion)
            ->orderBy('p.cod_prod')
            ->paginate($perPage);
    }

    /**
     * Catalogo con los filtros de la pantalla aplicados.
     * Lo comparten el listado y las exportaciones, para que el Excel y el PDF
     * salgan siempre con exactamente lo que se ve en la tabla.
     */
    private function queryCatalogo(Request $request)
    {
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
                'p.imagen',
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

        return $query;
    }

    /**
     * Da de baja un producto.
     *
     * No se borra la fila: tbventas guarda el cod_pro de cada linea vendida y
     * borrarlo dejaria huerfano todo el historico. Se sigue la convencion que
     * ya usa el negocio, que es marcar el nombre como INACTIVO; asi desaparece
     * del catalogo y de la pantalla de ventas salvo que se pidan los inactivos.
     */
    public function eliminar($codProd)
    {
        $producto = Producto::whereRaw('TRIM(cod_prod) = ?', [trim($codProd)])->first();
        if (!$producto) {
            return response()->json(['message' => 'El producto no existe'], 404);
        }

        if (stripos($producto->Producto, 'inactivo') !== false) {
            return response()->json(['message' => 'El producto ya estaba dado de baja'], 422);
        }

        $producto->Producto = trim($producto->Producto) . ' INACTIVO';
        $producto->save();

        return response()->json(['message' => 'Producto dado de baja']);
    }

    /** Catalogo filtrado en Excel. */
    public function exportarExcel(Request $request)
    {
        $filas = $this->queryCatalogo($request)->orderBy('p.Producto')->get();

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Productos');

        $cabecera = ['Código', 'Producto', 'Grupo', 'Unidad', 'Precio', 'P. Costo', 'Stock'];
        $hoja->fromArray($cabecera, null, 'A1');
        $hoja->getStyle('A1:G1')->getFont()->setBold(true);

        $fila = 2;
        foreach ($filas as $p) {
            // El cuarto argumento (strictNullComparison) es imprescindible: sin
            // el, fromArray compara con == y se salta los ceros, dejando en
            // blanco los precios y stocks en 0 en vez de escribir 0.
            $hoja->fromArray([
                $p->cod_prod,
                $p->Producto,
                $p->grupo,
                $p->codUnid,
                (float) $p->Precio,
                (float) $p->Precio_Costo,
                (float) $p->cantidad,
            ], null, 'A' . $fila, true);
            $fila++;
        }

        $hoja->getStyle('E2:G' . max($fila - 1, 2))->getNumberFormat()->setFormatCode('#,##0.00');

        foreach (range('A', 'G') as $col) {
            $hoja->getColumnDimension($col)->setAutoSize(true);
        }

        $nombre = 'productos_' . date('Y-m-d_His') . '.xlsx';
        $tmp = tempnam(sys_get_temp_dir(), 'xls');
        (new Xlsx($libro))->save($tmp);

        return response()->download($tmp, $nombre)->deleteFileAfterSend(true);
    }

    /** Catalogo filtrado en PDF. */
    public function exportarPdf(Request $request)
    {
        $filas = $this->queryCatalogo($request)->orderBy('p.Producto')->get();

        $html = "<style>
            *{font-family:sans-serif}
            h3{margin:0 0 2px}
            .sub{font-size:10px;color:#666;margin-bottom:8px}
            table{width:100%;border-collapse:collapse;font-size:9px}
            th{background:#eee;border:1px solid #bbb;padding:3px;text-align:left}
            td{border:1px solid #ddd;padding:3px}
            .n{text-align:right}
        </style>
        <h3>Catálogo de productos</h3>
        <div class='sub'>" . count($filas) . " productos &middot; " . date('d/m/Y H:i') . "</div>
        <table><tr>
            <th>Código</th><th>Producto</th><th>Grupo</th><th>Unid.</th>
            <th class='n'>Precio</th><th class='n'>P. Costo</th><th class='n'>Stock</th>
        </tr>";

        foreach ($filas as $p) {
            $html .= '<tr>'
                . '<td>' . e($p->cod_prod) . '</td>'
                . '<td>' . e($p->Producto) . '</td>'
                . '<td>' . e($p->grupo) . '</td>'
                . '<td>' . e($p->codUnid) . '</td>'
                . "<td class='n'>" . number_format((float) $p->Precio, 2) . '</td>'
                . "<td class='n'>" . number_format((float) $p->Precio_Costo, 2) . '</td>'
                . "<td class='n'>" . number_format((float) $p->cantidad, 2) . '</td>'
                . '</tr>';
        }

        $html .= '</table>';

        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($html);

        return $pdf->stream('productos_' . date('Y-m-d_His') . '.pdf', ['Attachment' => false]);
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
