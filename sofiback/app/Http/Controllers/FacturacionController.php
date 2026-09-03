<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use App\Services\CargaCamion;
use App\Services\SiatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Luecano\NumeroALetras\NumeroALetras;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ventas y facturas hechas desde el sistema web.
 *
 * Modelo propio (facturas / factura_detalles): no escribe en tbventas, que es
 * la tabla del sistema de caja de escritorio. Por eso lo que se registre aca
 * NO aparece en la pantalla de Ventas, que sigue leyendo tbventas.
 *
 * Lo que si se reusa son los maestros legados, para no duplicar datos:
 * tbclientes (cliente y su vendedor), personal (usuario) y tbproductos con su
 * stock, que se calcula como SUM(cant - saldo) sobre tbstock igual que en
 * ProductoController.
 */
class FacturacionController extends Controller
{
    /** Fecha centinela del legado para "sin valor". */
    private const FECHA_NULA = '1899-11-30 04:32:36';

    /** Tope de comprobantes por PDF; mas que esto no se imprime de una vez. */
    private const MAX_LOTE = 150;

    /** Listado de lo emitido, con filtros de la pantalla. */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('perPage', 20), 1), 200);

        return $this->filtrar($request)->paginate($perPage);
    }

    /**
     * Los filtros de la pantalla, en un solo sitio.
     *
     * Lo usan el listado y los reportes: lo que se exporta o se imprime en lote
     * tiene que ser exactamente lo que el usuario esta viendo, y con la consulta
     * duplicada eso se desincroniza al primer cambio.
     */
    private function filtrar(Request $request)
    {
        $query = Factura::query()
            ->with([
                'detalles',
                'cliente:Cod_Aut,Id,Nombres,zona',
                'vendedor:CodAut,ci,Nombre1,Nombre2,App1,Apm',
            ])
            ->select('facturas.*')
            // El camion no es de la factura sino del pedido que la origino, y
            // por eso se trae de tbpedidos en vez de guardarse repetido.
            ->selectSub(function ($sub) {
                $sub->from('tbpedidos as pc')
                    ->whereColumn('pc.NroPed', 'facturas.pedido_nro')
                    ->whereRaw('UPPER(TRIM(pc.tipo)) = UPPER(TRIM(facturas.pedido_tipo))')
                    ->limit(1)
                    ->select(DB::raw("TRIM(COALESCE(pc.placa, ''))"));
            }, 'placa')
            ->orderByDesc('id');

        if ($desde = $request->input('desde')) {
            $query->whereDate('fecha', '>=', $desde);
        }
        if ($hasta = $request->input('hasta')) {
            $query->whereDate('fecha', '<=', $hasta);
        }
        if ($tipo = $request->input('tipo')) {
            $query->where('tipo_comprobante', $tipo);
        }
        if ($estado = $request->input('estado')) {
            $query->where('estado', $estado);
        }

        // El camion se filtra por los pedidos que salieron en esa placa. La
        // subconsulta se queda dentro de tbpedidos a proposito: cruzar textos
        // entre facturas (utf8mb4) y el legado (latin1) mezcla collations.
        if ($camion = trim((string) $request->input('camion', ''))) {
            if ($camion === 'SIN') {
                $query->where(function ($w) {
                    $w->whereNull('pedido_nro')->orWhereNotIn('pedido_nro', function ($sub) {
                        $sub->from('tbpedidos')->select('NroPed')
                            ->whereRaw("TRIM(COALESCE(placa, '')) <> ''");
                    });
                });
            } else {
                $query->whereIn('pedido_nro', function ($sub) use ($camion) {
                    $sub->from('tbpedidos')->select('NroPed')
                        ->whereRaw('TRIM(placa) = ?', [$camion]);
                });
            }
        }

        if ($buscar = trim((string) $request->input('buscar', ''))) {
            $like = '%' . $buscar . '%';
            $query->where(function ($w) use ($like, $buscar) {
                // El carnet se busca desde el principio y no en cualquier parte
                // del numero: con LIKE '%2%' un comprobante se perdia entre
                // todos los NIT que llevaran un 2. El nombre si va parcial.
                $w->where('nombre', 'like', $like)
                    ->orWhere('nit', 'like', $buscar . '%');
                if (ctype_digit($buscar)) {
                    $w->orWhere('id', $buscar)
                        ->orWhere('nro_factura', $buscar)
                        // La comanda del pedido de origen: es el numero con el
                        // que se conoce la venta en el mostrador y en la ruta.
                        ->orWhere('pedido_nro', $buscar);
                }
            });
        }

        return $query;
    }

    /**
     * Camiones que tienen comprobantes en lo que se esta filtrando.
     *
     * Se ignora el filtro de camion para armar las opciones: si no, al elegir
     * uno el desplegable se quedaria con ese solo y no habria como cambiarlo.
     */
    public function camiones(Request $request)
    {
        $sinCamion = Request::create('', 'GET', $request->except('camion'));

        $numeros = $this->filtrar($sinCamion)->reorder()
            ->pluck('pedido_nro')->filter()->unique()->values();

        if ($numeros->isEmpty()) {
            return response()->json([]);
        }

        return DB::table('tbpedidos')
            ->whereIn('NroPed', $numeros)
            ->whereRaw("TRIM(COALESCE(placa, '')) <> ''")
            ->groupBy('placa')
            ->orderBy('placa')
            ->get([
                DB::raw('TRIM(placa) as placa'),
                DB::raw('COUNT(DISTINCT NroPed) as pedidos'),
            ]);
    }

    /**
     * Como viene la verificacion de la carga de un camion. La pantalla de
     * facturacion la consulta al filtrar por camion para avisar antes de que
     * el cajero intente imprimir y se lleve el rechazo.
     */
    public function carga(Request $request)
    {
        $datos = $request->validate([
            'fecha' => 'required|date',
            'camion' => 'required|string|max:100',
        ]);

        return (new CargaCamion())->estado($datos['fecha'], trim($datos['camion']));
    }

    /**
     * El motivo por el que un comprobante no se puede imprimir todavia, o null
     * si se puede.
     *
     * El caminero revisa las canastas del camion antes de salir y recien ahi
     * caja imprime: si el papel sale antes, nadie se hizo responsable de que
     * la mercaderia este arriba. La venta directa de mostrador no viaja en
     * ningun camion y nunca se frena.
     */
    private function bloqueoCarga(Factura $factura)
    {
        $placa = $this->camionDeFactura($factura);
        if ($placa === '') {
            return null;
        }

        $fecha = $factura->fecha instanceof \DateTimeInterface
            ? $factura->fecha->format('Y-m-d')
            : substr((string) $factura->fecha, 0, 10);

        $estado = (new CargaCamion())->estado($fecha, $placa);
        if ($estado['completo']) {
            return null;
        }

        return 'El camión ' . $placa . ' todavía no verificó su carga ('
            . $estado['verificados'] . '/' . $estado['comprobantes']
            . ' canastas revisadas). El caminero tiene que revisarla antes de imprimir.';
    }

    /** Placas del lote cuya carga sigue sin revisar, sin repetirse. */
    private function camionesSinVerificar($facturas)
    {
        $carga = new CargaCamion();
        $estados = [];

        foreach ($facturas as $factura) {
            $placa = $this->camionDeFactura($factura);
            if ($placa === '') {
                continue;
            }

            $fecha = $factura->fecha instanceof \DateTimeInterface
                ? $factura->fecha->format('Y-m-d')
                : substr((string) $factura->fecha, 0, 10);

            $clave = $fecha . '|' . $placa;
            if (!isset($estados[$clave])) {
                $estados[$clave] = $carga->estado($fecha, $placa);
            }
        }

        $pendientes = [];
        foreach ($estados as $estado) {
            if (!$estado['completo']) {
                $pendientes[$estado['placa']] = true;
            }
        }

        return array_keys($pendientes);
    }

    /** Camion en el que sale la factura; vacio si no salio en ninguno. */
    private function camionDeFactura(Factura $factura)
    {
        if (!$factura->pedido_nro) {
            return '';
        }

        $pedido = DB::table('tbpedidos')
            ->where('NroPed', $factura->pedido_nro)
            ->whereRaw('UPPER(TRIM(tipo)) = ?', [strtoupper(trim((string) $factura->pedido_tipo))])
            ->where('bonificacion', 0)
            ->first([DB::raw("TRIM(COALESCE(placa, '')) as placa")]);

        return $pedido->placa ?? '';
    }

    /** Una factura con su detalle, para ver o reimprimir. */
    public function show($id)
    {
        $factura = Factura::with(['detalles', 'cliente', 'usuario', 'vendedor'])->find($id);

        if (!$factura) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }

        return response()->json($factura);
    }

    /** Enlace publico del SIAT que tambien se codifica en el QR de la factura. */
    public function urlImpuestos($id)
    {
        $factura = Factura::find($id);

        if (!$factura) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }

        if ($factura->tipo_comprobante !== 'FACTURA' || !$factura->cuf) {
            return response()->json([
                'message' => 'Esta venta no tiene una factura fiscal disponible en Impuestos',
            ], 422);
        }

        return response()->json([
            'url' => FacturaFiscalController::urlSiat(
                $factura->cuf,
                $factura->nro_factura ?: $factura->id
            ),
        ]);
    }

    /** Catalogo de productos con su stock, para la grilla del carrito. */
    public function catalogo(Request $request)
    {
        $perPage = min(max((int) $request->input('perPage', 20), 1), 100);

        $query = DB::table('tbproductos as p')
            ->leftJoin('tbgrupos as g', 'g.Cod_grup', '=', 'p.cod_grup')
            ->where('p.Producto', 'not like', '%inactivo%')
            ->select([
                DB::raw('TRIM(p.cod_prod) as cod_prod'),
                DB::raw('TRIM(p.Producto) as producto'),
                DB::raw('TRIM(p.codUnid) as unidad'),
                DB::raw('TRIM(g.Descripcion) as grupo'),
                'p.imagen',
                'p.Precio as precio',
                // Lo usa la pantalla de compras para proponer el costo.
                'p.Precio_Costo as costo',
                'p.Precio3', 'p.Precio4', 'p.Precio5', 'p.Precio6',
                // El alias no puede llamarse "stock": tbproductos ya tiene una
                // columna asi y el ORDER BY resolveria a esa, no a esta.
                DB::raw('COALESCE((
                    SELECT SUM(s.cant - s.saldo) FROM tbstock s WHERE s.cod_prod = p.cod_prod
                ), 0) as existencia'),
            ]);

        if ($grupo = trim((string) $request->input('grupo', ''))) {
            $query->where('p.cod_grup', $grupo);
        }

        if ($buscar = trim((string) $request->input('buscar', ''))) {
            $like = '%' . $buscar . '%';
            $query->where(function ($w) use ($like) {
                $w->where('p.Producto', 'like', $like)
                    ->orWhere('p.cod_prod', 'like', $like);
            });
        }

        // Lo vendible primero: sin esto la grilla abre con bonificaciones y
        // productos de precio 0, que no sirven para cobrar.
        $productos = $query
            ->orderByRaw('(existencia > 0) DESC')
            ->orderByRaw('(p.Precio > 0) DESC')
            ->orderBy('p.Producto')
            ->paginate($perPage);

        $productos->getCollection()->transform(function ($p) {
            $p->precios = collect([$p->precio, $p->Precio3, $p->Precio4, $p->Precio5, $p->Precio6])
                ->map(function ($v) {
                    return round((float) $v, 2);
                })
                ->filter(function ($v) {
                    return $v > 0;
                })
                ->unique()
                ->values();

            unset($p->Precio3, $p->Precio4, $p->Precio5, $p->Precio6);

            $p->precio = round((float) $p->precio, 2);
            $p->costo = round((float) $p->costo, 2);
            $p->stock = round((float) $p->existencia, 3);
            unset($p->existencia);

            return $p;
        });

        return $productos;
    }

    /** Grupos con productos, para el filtro de categoria. */
    public function categorias()
    {
        return DB::table('tbgrupos as g')
            ->join('tbproductos as p', 'p.cod_grup', '=', 'g.Cod_grup')
            ->where('p.Producto', 'not like', '%inactivo%')
            ->groupBy('g.Cod_grup', 'g.Descripcion')
            ->orderBy('label')
            ->get([
                DB::raw('TRIM(g.Cod_grup) as value'),
                DB::raw('TRIM(g.Descripcion) as label'),
                DB::raw('COUNT(*) as productos'),
            ]);
    }

    /** Busqueda de clientes por NIT o nombre, para la cabecera. */
    public function clientes(Request $request)
    {
        $buscar = trim((string) $request->input('buscar', ''));
        if (mb_strlen($buscar) < 2) {
            return response()->json([]);
        }

        $like = '%' . $buscar . '%';

        return DB::table('tbclientes as c')
            ->leftJoin('personal as pv', function ($j) {
                $j->on(DB::raw('TRIM(pv.ci)'), '=', DB::raw('TRIM(c.CiVend)'));
            })
            ->where(function ($w) use ($like) {
                $w->where('c.Id', 'like', $like)->orWhere('c.Nombres', 'like', $like);
            })
            ->orderBy('c.Nombres')
            ->limit(20)
            ->get([
                'c.Cod_Aut as id',
                DB::raw('TRIM(c.Id) as nit'),
                DB::raw('TRIM(c.Nombres) as nombre'),
                DB::raw('TRIM(c.Direccion) as direccion'),
                DB::raw('TRIM(c.zona) as zona'),
                DB::raw('TRIM(c.CiVend) as vendedor_ci'),
                DB::raw("TRIM(CONCAT_WS(' ', NULLIF(TRIM(pv.Nombre1), ''), NULLIF(TRIM(pv.App1), ''))) as vendedor"),
            ]);
    }

    /** Pedidos de preventistas agrupados por numero y tipo para facturarlos. */
    public function pedidos(Request $request)
    {
        $datos = $request->validate([
            'fecha' => 'required|date',
            'tipo' => 'required|in:NORMAL,POLLO,CERDO,RES',
            'buscar' => 'nullable|string|max:100',
        ]);

        $query = DB::table('tbpedidos as p')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'p.idCli')
            ->leftJoin('personal as v', 'v.CodAut', '=', 'p.CIfunc')
            // Una venta anulada no cuenta como emitida: el pedido vuelve a la
            // cola para cobrarlo de nuevo, y por eso solo puede engancharse un
            // comprobante vigente por pedido.
            ->leftJoin('facturas as f', function ($join) {
                $join->on('f.pedido_nro', '=', 'p.NroPed')
                    ->on(DB::raw('UPPER(TRIM(f.pedido_tipo))'), '=', DB::raw('UPPER(TRIM(p.tipo))'))
                    ->whereNull('f.deleted_at')
                    ->where('f.estado', '<>', 'ANULADO');
            })
            ->whereDate('p.fecha', $datos['fecha'])
            ->whereRaw('UPPER(TRIM(p.tipo)) = ?', [$datos['tipo']])
            // Un pedido en CREADO todavia lo esta armando el preventista: solo
            // se factura lo que ya fue enviado.
            ->whereRaw("UPPER(TRIM(p.estado)) = 'ENVIADO'")
            ->where('p.bonificacion', 0);

        if ($buscar = trim((string) ($datos['buscar'] ?? ''))) {
            $like = '%' . $buscar . '%';
            $query->where(function ($w) use ($like, $buscar) {
                $w->where('c.Nombres', 'like', $like)
                    ->orWhere('c.Id', 'like', $like)
                    ->orWhere('v.Nombre1', 'like', $like)
                    ->orWhere('v.App1', 'like', $like);
                if (ctype_digit($buscar)) {
                    $w->orWhere('p.NroPed', $buscar);
                }
            });
        }

        $pedidos = $query
            // Un pedido es una sola tarjeta: las lineas de un mismo NroPed
            // pueden tener distinta hora (se van agregando de a poco), asi que
            // la cabecera se resume con MIN y solo se agrupa por el pedido.
            ->groupBy([
                'p.NroPed', DB::raw('UPPER(TRIM(p.tipo))'), 'f.id', 'f.tipo_comprobante',
                'f.fecha', 'f.nit',
            ])
            ->orderByRaw('CASE WHEN f.id IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc('p.NroPed')
            ->get([
                'p.NroPed as nro_pedido',
                DB::raw('UPPER(TRIM(p.tipo)) as tipo'),
                DB::raw('MIN(p.fecha) as fecha'),
                DB::raw('MIN(p.estado) as estado'),
                DB::raw('MIN(p.fact) as fact'),
                DB::raw('MIN(p.pago) as pago'),
                DB::raw('MIN(p.comentario) as comentario'),
                // El camion del pedido es tbpedidos.placa; colorStyle es el color
                // con el que esa placa ya se pinta en el mapa y en el reporte.
                DB::raw("TRIM(COALESCE(MIN(p.placa), '')) as placa"),
                DB::raw("TRIM(COALESCE(MIN(p.colorStyle), '')) as placa_color"),
                DB::raw('MIN(p.idCli) as cliente_id'),
                DB::raw('TRIM(MIN(c.Id)) as nit'),
                DB::raw('TRIM(MIN(c.Nombres)) as cliente'),
                DB::raw("TRIM(CONCAT_WS(' ', NULLIF(TRIM(MIN(v.Nombre1)), ''), NULLIF(TRIM(MIN(v.Nombre2)), ''), NULLIF(TRIM(MIN(v.App1)), ''), NULLIF(TRIM(MIN(v.Apm)), ''))) as vendedor"),
                DB::raw('COUNT(*) as productos'),
                DB::raw('ROUND(SUM(COALESCE(p.Cant, 0) * COALESCE(p.precio, 0)), 2) as total_pedido'),
                'f.id as factura_id', 'f.tipo_comprobante as comprobante_emitido',
                // El comprobante se emite el dia que se cobra, no el del pedido:
                // con su fecha y su carnet la pantalla de facturacion lo
                // encuentra sin buscarlo a mano.
                'f.fecha as factura_fecha', DB::raw('TRIM(f.nit) as factura_nit'),
            ]);

        $numeros = $pedidos->pluck('nro_pedido')->all();
        if (empty($numeros)) {
            return $pedidos;
        }

        // Se cargan todos los detalles en una sola consulta para que en el
        // celular se vea que el pedido ya viene armado por el preventista.
        $items = DB::table('tbpedidos as p')
            ->leftJoin('tbproductos as pr', function ($join) {
                $join->on(DB::raw('TRIM(pr.cod_prod)'), '=', DB::raw('TRIM(p.cod_prod)'));
            })
            ->whereIn('p.NroPed', $numeros)
            ->whereRaw('UPPER(TRIM(p.tipo)) = ?', [$datos['tipo']])
            ->whereRaw("UPPER(TRIM(p.estado)) = 'ENVIADO'")
            ->where('p.bonificacion', 0)
            ->orderBy('p.codAut')
            ->get([
                'p.NroPed as nro_pedido',
                DB::raw('TRIM(p.cod_prod) as cod_prod'),
                DB::raw("COALESCE(NULLIF(TRIM(pr.Producto), ''), CONCAT('Producto ', TRIM(p.cod_prod))) as nombre"),
                DB::raw('COALESCE(p.Cant, 0) as cantidad'),
                DB::raw('COALESCE(p.precio, 0) as precio'),
            ])
            ->groupBy('nro_pedido');

        $filasPedido = DB::table('tbpedidos')->whereIn('NroPed', $numeros)
            ->whereRaw('UPPER(TRIM(tipo)) = ?', [$datos['tipo']])
            ->whereRaw("UPPER(TRIM(estado)) = 'ENVIADO'")
            ->where('bonificacion', 0)
            ->orderBy('codAut')->get()->groupBy('NroPed');

        // Los que ya se habian cobrado y se anularon vuelven a salir como
        // pendientes: la tarjeta lo avisa para que el cajero sepa que al
        // entrar va a encontrar recuperado lo de la venta dada de baja.
        $anuladas = Factura::where('pedido_tipo', $datos['tipo'])
            ->where('estado', 'ANULADO')
            ->whereIn('pedido_nro', $numeros)
            ->orderBy('id')
            ->get(['id', 'pedido_nro', 'total'])
            ->keyBy('pedido_nro');

        return $pedidos->map(function ($pedido) use ($items, $filasPedido, $anuladas) {
            $pedido->items = ($items->get($pedido->nro_pedido) ?? collect())
                ->map(function ($item) {
                    $item->cantidad = (float) $item->cantidad;
                    $item->precio = (float) $item->precio;
                    $item->total = round($item->cantidad * $item->precio, 2);
                    return $item;
                })->values();
            $pedido->detalle_pollo = $this->detallePollo($filasPedido->get($pedido->nro_pedido) ?? collect());
            // keyBy se queda con la ultima, que es la anulacion mas reciente.
            $anulada = $pedido->factura_id ? null : $anuladas->get($pedido->nro_pedido);
            $pedido->anulada_id = $anulada->id ?? null;
            $pedido->anulada_total = $anulada ? (float) $anulada->total : null;
            return $pedido;
        });
    }

    /** Cabecera y productos editables de un pedido concreto. */
    public function pedido(Request $request, $nroPedido)
    {
        $datos = $request->validate([
            'tipo' => 'required|in:NORMAL,POLLO,CERDO,RES',
        ]);

        $cabecera = DB::table('tbpedidos as p')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'p.idCli')
            ->leftJoin('personal as v', 'v.CodAut', '=', 'p.CIfunc')
            ->where('p.NroPed', $nroPedido)
            ->whereRaw('UPPER(TRIM(p.tipo)) = ?', [$datos['tipo']])
            ->where('p.bonificacion', 0)
            ->first([
                'p.NroPed as nro_pedido', DB::raw('UPPER(TRIM(p.tipo)) as tipo'),
                'p.fecha', 'p.estado', 'p.fact', 'p.pago', 'p.comentario',
                // Mismo camion que se ve en el listado de pedidos por facturar.
                DB::raw("TRIM(COALESCE(p.placa, '')) as placa"),
                DB::raw("TRIM(COALESCE(p.colorStyle, '')) as placa_color"),
                DB::raw("TRIM(COALESCE(p.horario, '')) as horario"),
                'p.idCli as cliente_id', DB::raw('TRIM(c.Id) as nit'),
                DB::raw('TRIM(c.Nombres) as cliente'), DB::raw('TRIM(c.Direccion) as direccion'),
                DB::raw('TRIM(c.zona) as zona'), DB::raw('TRIM(c.CiVend) as vendedor_ci'),
                DB::raw("TRIM(CONCAT_WS(' ', NULLIF(TRIM(v.Nombre1), ''), NULLIF(TRIM(v.Nombre2), ''), NULLIF(TRIM(v.App1), ''), NULLIF(TRIM(v.Apm), ''))) as vendedor"),
            ]);

        if (!$cabecera) {
            return response()->json(['message' => 'El pedido no existe para el tipo seleccionado'], 404);
        }

        // Lo mismo que filtra el listado: mientras el pedido siga en CREADO el
        // preventista lo puede seguir cambiando, asi que no se cobra.
        if (strtoupper(trim((string) $cabecera->estado)) !== 'ENVIADO') {
            return response()->json(['message' => 'El pedido todavía no fue enviado por el preventista'], 422);
        }

        // Lo anulado ya no bloquea: si la venta se dio de baja, el pedido se
        // puede volver a cobrar.
        $yaFacturado = Factura::where('pedido_nro', $nroPedido)
            ->where('pedido_tipo', $datos['tipo'])
            ->where('estado', '<>', 'ANULADO')
            ->first(['id', 'tipo_comprobante', 'estado']);

        if ($yaFacturado) {
            return response()->json([
                'message' => 'El pedido ya fue procesado en la facturación #' . $yaFacturado->id,
                'factura' => $yaFacturado,
            ], 422);
        }

        $items = DB::table('tbpedidos as p')
            ->leftJoin('tbproductos as pr', function ($join) {
                $join->on(DB::raw('TRIM(pr.cod_prod)'), '=', DB::raw('TRIM(p.cod_prod)'));
            })
            ->where('p.NroPed', $nroPedido)
            ->whereRaw('UPPER(TRIM(p.tipo)) = ?', [$datos['tipo']])
            ->where('p.bonificacion', 0)
            ->orderBy('p.codAut')
            ->get([
                DB::raw('TRIM(p.cod_prod) as cod_prod'),
                DB::raw("COALESCE(NULLIF(TRIM(pr.Producto), ''), CONCAT('Producto ', TRIM(p.cod_prod))) as nombre"),
                DB::raw("COALESCE(NULLIF(TRIM(pr.codUnid), ''), 'UNIDAD') as unidad"),
                'pr.imagen', DB::raw('COALESCE(p.Cant, 0) as cantidad'),
                DB::raw('COALESCE(p.precio, 0) as precio'),
            ])
            ->map(function ($item) {
                $item->cantidad = (float) $item->cantidad;
                // Lo que pidio el cliente queda aparte de lo que se entrega:
                // sirve para avisarle al cajero que la linea cambio. No se
                // guarda ni sale impreso, es solo la referencia del pedido.
                $item->cantidad_pedida = $item->cantidad;
                $item->precio = (float) $item->precio;
                // Lo que va por kilo se pesa recien al cobrar: el peso sale en
                // blanco para que el cajero escriba lo de la balanza.
                $item->peso = null;
                $item->total = round($item->cantidad * $item->precio, 2);
                return $item;
            });

        $filasPedido = DB::table('tbpedidos')->where('NroPed', $nroPedido)
            ->whereRaw('UPPER(TRIM(tipo)) = ?', [$datos['tipo']])->where('bonificacion', 0)
            ->orderBy('codAut')->get();
        $cabecera->detalle_pollo = $this->detallePollo($filasPedido);

        // Si la venta anterior se anulo el pedido vuelve a la cola, pero lo que
        // ya se habia trabajado en el mostrador no se pierde: se recupera la
        // ultima anulada para no pesar y corregir todo otra vez desde cero.
        $anulada = Factura::with('detalles')
            ->where('pedido_nro', $nroPedido)
            ->where('pedido_tipo', $datos['tipo'])
            ->where('estado', 'ANULADO')
            ->orderByDesc('id')
            ->first();

        $cabecera->anulada = null;
        if ($anulada) {
            $items = $this->recuperarAnulada($items, $anulada);
            $cabecera->anulada = [
                'id'               => $anulada->id,
                'nro_factura'      => $anulada->nro_factura,
                'tipo_comprobante' => $anulada->tipo_comprobante,
                'tipo_pago'        => $anulada->tipo_pago,
                'nit'              => $anulada->nit,
                'observacion'      => $anulada->observacion,
                'total'            => (float) $anulada->total,
                'fecha'            => optional($anulada->fecha)->format('Y-m-d'),
                'hora'             => $anulada->hora,
                'motivo'           => $anulada->motivo_anulacion,
                'anulado_at'       => optional($anulada->anulado_at)->format('Y-m-d H:i'),
                'lineas'           => $anulada->detalles->count(),
            ];
        }

        return response()->json(['pedido' => $cabecera, 'items' => $items]);
    }

    /**
     * Devuelve a las lineas del pedido lo que se habia cobrado en una venta
     * que despues se anulo.
     *
     * Lo que pidio el cliente sigue siendo la referencia (cantidad_pedida),
     * pero la cantidad, el peso de balanza y el precio arrancan con lo que ya
     * se habia corregido al cobrar, y los productos que el cajero habia
     * agregado a mano vuelven a la lista.
     */
    private function recuperarAnulada($items, Factura $anulada)
    {
        $porCodigo = $anulada->detalles->keyBy(function ($detalle) {
            return trim((string) $detalle->cod_prod);
        });

        $items = $items->map(function ($item) use ($porCodigo) {
            $detalle = $porCodigo->get(trim((string) $item->cod_prod));
            if (!$detalle) {
                return $item;
            }
            $item->cantidad = (float) $detalle->cantidad;
            // El peso solo se recupera si de verdad se peso algo.
            $item->peso = (float) $detalle->peso > 0 ? (float) $detalle->peso : null;
            $item->precio = (float) $detalle->precio;
            $item->total = round(($item->peso ?: $item->cantidad) * $item->precio, 2);
            $item->recuperado = true;
            return $item;
        });

        $enPedido = $items->map(function ($item) {
            return trim((string) $item->cod_prod);
        })->all();

        foreach ($anulada->detalles as $detalle) {
            $codigo = trim((string) $detalle->cod_prod);
            if (in_array($codigo, $enPedido, true)) {
                continue;
            }
            $peso = (float) $detalle->peso > 0 ? (float) $detalle->peso : null;
            $items->push((object) [
                'cod_prod' => $codigo,
                'nombre'   => $detalle->nombre,
                'unidad'   => $detalle->unidad ?: 'UNIDAD',
                'imagen'   => null,
                'cantidad' => (float) $detalle->cantidad,
                // No venia en el pedido: no hay cantidad pedida con que compararlo.
                'cantidad_pedida' => null,
                'peso'     => $peso,
                'precio'   => (float) $detalle->precio,
                'total'    => round(($peso ?: (float) $detalle->cantidad) * (float) $detalle->precio, 2),
                'recuperado' => true,
            ]);
        }

        return $items->values();
    }

    private function detallePollo($filas)
    {
        $detalles = collect();
        $observaciones = collect();
        // El mapa de columnas es el mismo con el que el caminero revisa su
        // carga, asi que vive en un solo sitio.
        $productos = CargaCamion::PRODUCTOS_POLLO;
        $cortes = CargaCamion::CORTES_POLLO;
        foreach ($filas as $fila) {
            foreach (['Observaciones', 'Canttxt', 'comentario'] as $campo) {
                $texto = trim((string) ($fila->{$campo} ?? ''));
                if ($texto !== '') $observaciones->push($texto);
            }
            foreach ($productos as [$nombre, $caja, $unidad, $precio, $obs]) {
                $this->agregarDetallePollo($detalles, $fila, $nombre, $caja, 'CJA', $precio, $obs);
                $this->agregarDetallePollo($detalles, $fila, $nombre, $unidad, 'UND', $precio, $obs);
            }
            foreach ($cortes as [$nombre, $cantidad, $unidad, $precio, $obs]) {
                $this->agregarDetallePollo($detalles, $fila, $nombre, $cantidad, strtoupper(trim((string) ($fila->{$unidad} ?? 'KG'))), $precio, $obs);
            }
            $this->agregarDetallePollo($detalles, $fila, 'Rango', 'rango', 'KG', 'bs', null);
        }
        return [
            'observaciones' => $observaciones->unique()->values(),
            'productos' => $detalles->unique(fn ($d) => implode('|', $d))->values(),
        ];
    }

    private function agregarDetallePollo($detalles, $fila, $nombre, $campo, $unidad, $campoPrecio, $campoObservacion)
    {
        $cantidad = $fila->{$campo} ?? null;
        if ($cantidad === null || $cantidad === '' || (float) $cantidad == 0) return;
        $detalles->push([
            'nombre' => $nombre, 'cantidad' => (float) $cantidad, 'unidad' => $unidad ?: 'KG',
            'precio' => (float) ($fila->{$campoPrecio} ?? $fila->bs ?? $fila->bs2 ?? 0),
            'observacion' => $campoObservacion ? trim((string) ($fila->{$campoObservacion} ?? '')) : '',
        ]);
    }

    /** Registra la venta o factura con su detalle. */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.cod_prod' => 'required|string|max:25',
            'items.*.cantidad' => 'required|numeric|min:0.001',
            // Lo que decia el pedido, para dejar constancia de lo que cambio.
            'items.*.cantidad_pedida' => 'nullable|numeric|min:0',
            // Solo lo que va a granel lo trae; es lo que se cobra en esas lineas.
            'items.*.peso'     => 'nullable|numeric|min:0',
            'items.*.precio'   => 'required|numeric|min:0',
            'tipo_comprobante' => 'nullable|in:VENTA,FACTURA',
            'tipo_pago'        => 'nullable|string|max:20',
            'cliente_id'       => 'nullable|integer',
            'nit'              => 'nullable|string|max:20',
            'nombre'           => 'nullable|string|max:150',
            'descuento'        => 'nullable|numeric|min:0',
            'observacion'      => 'nullable|string|max:255',
            'pedido_nro'       => 'nullable|required_with:pedido_tipo|integer',
            'pedido_tipo'      => 'nullable|required_with:pedido_nro|in:NORMAL,POLLO,CERDO,RES',
        ]);

        $tipo = $datos['tipo_comprobante'] ?? 'VENTA';
        $nit = trim((string) ($datos['nit'] ?? ''));

        // Sin NIT no hay factura posible.
        if ($tipo === 'FACTURA' && $nit === '') {
            return response()->json([
                'message' => 'Para factura hace falta el NIT o CI del cliente',
            ], 422);
        }

        $codigos = collect($datos['items'])->pluck('cod_prod')
            ->map(function ($c) {
                return trim($c);
            })->unique();

        $productos = DB::table('tbproductos')
            ->whereIn(DB::raw('TRIM(cod_prod)'), $codigos)
            ->get()
            ->keyBy(function ($p) {
                return trim($p->cod_prod);
            });

        $faltantes = $codigos->diff($productos->keys());
        if ($faltantes->isNotEmpty()) {
            return response()->json([
                'message' => 'No existen los productos: ' . $faltantes->implode(', '),
            ], 422);
        }

        // En lo que va a granel el importe sale del peso, asi que una linea que
        // manda el peso vacio no se puede cobrar. Las pantallas que no mandan
        // peso (venta directa) siguen cobrando por cantidad.
        $sinPeso = collect($datos['items'])
            ->filter(function ($item) use ($productos) {
                return $this->esGranel($productos[trim($item['cod_prod'])])
                    && array_key_exists('peso', $item)
                    && (float) $item['peso'] <= 0;
            })
            ->map(function ($item) use ($productos) {
                return trim($productos[trim($item['cod_prod'])]->Producto);
            });

        if ($sinPeso->isNotEmpty()) {
            return response()->json([
                'message' => 'Falta el peso de: ' . $sinPeso->implode(', '),
            ], 422);
        }

        $usuario = $request->user();
        $ci = trim((string) ($usuario->ci ?? ''));
        if ($ci === '') {
            return response()->json(['message' => 'El usuario no tiene CI en personal'], 422);
        }

        // Si mandan cliente, se copian sus datos y su vendedor: la factura no
        // debe cambiar si manana editan la ficha del cliente.
        $cliente = null;
        if (!empty($datos['cliente_id'])) {
            $cliente = DB::table('tbclientes')->where('Cod_Aut', $datos['cliente_id'])->first();
            if (!$cliente) {
                return response()->json(['message' => 'El cliente no existe'], 422);
            }
        }

        if (!empty($datos['pedido_nro'])) {
            $existePedido = DB::table('tbpedidos')
                ->where('NroPed', $datos['pedido_nro'])
                ->whereRaw('UPPER(TRIM(tipo)) = ?', [$datos['pedido_tipo']])
                ->whereRaw("UPPER(TRIM(estado)) = 'ENVIADO'")
                ->exists();
            if (!$existePedido) {
                return response()->json(['message' => 'El pedido de origen no existe o todavía no fue enviado'], 422);
            }
            // Solo un comprobante vigente por pedido; los anulados no cuentan.
            $vigente = Factura::where('pedido_nro', $datos['pedido_nro'])
                ->where('pedido_tipo', $datos['pedido_tipo'])
                ->where('estado', '<>', 'ANULADO')
                ->exists();
            if ($vigente) {
                return response()->json(['message' => 'Este pedido ya fue facturado o convertido en voucher'], 422);
            }
        }

        $factura = DB::transaction(function () use ($datos, $productos, $usuario, $ci, $cliente, $tipo, $nit) {
            $subtotal = 0;
            $lineas = [];

            foreach ($datos['items'] as $item) {
                $cod = trim($item['cod_prod']);
                $prod = $productos[$cod];

                $cantidad = round((float) $item['cantidad'], 3);
                $precio = round((float) $item['precio'], 2);

                // El peso solo tiene sentido en lo que se vende por kilo: ahi
                // es lo que se cobra, y la cantidad queda como las piezas que
                // se entregan.
                $peso = $this->esGranel($prod) && isset($item['peso']) && (float) $item['peso'] > 0
                    ? round((float) $item['peso'], 3)
                    : null;

                $importe = round(($peso ?? $cantidad) * $precio, 2);
                $subtotal += $importe;

                $lineas[] = [
                    'cod_prod' => $cod,
                    'nombre'   => trim($prod->Producto),
                    'unidad'   => trim((string) $prod->codUnid),
                    'cantidad' => $cantidad,
                    // Queda guardado lo que pidio el cliente aunque se le haya
                    // entregado otra cosa: sin esto, los que no salieron no
                    // figuraban en ningun lado.
                    'cantidad_pedida' => isset($item['cantidad_pedida'])
                        ? round((float) $item['cantidad_pedida'], 3)
                        : null,
                    'peso'     => $peso,
                    'precio'   => $precio,
                    'subtotal' => $importe,
                ];
            }

            $subtotal = round($subtotal, 2);
            $descuento = min(round((float) ($datos['descuento'] ?? 0), 2), $subtotal);

            $factura = Factura::create([
                'user_id'          => $usuario->CodAut,
                'cliente_id'       => $cliente->Cod_Aut ?? null,
                'vendedor_ci'      => $cliente ? trim((string) $cliente->CiVend) : null,
                'fecha'            => date('Y-m-d'),
                'hora'             => date('H:i:s'),
                'nit'              => $nit !== '' ? $nit : ($cliente ? trim($cliente->Id) : null),
                'nombre'           => $datos['nombre'] ?? ($cliente ? trim($cliente->Nombres) : null),
                'tipo_comprobante' => $tipo,
                'tipo_pago'        => $datos['tipo_pago'] ?? 'EFECTIVO',
                'estado'           => 'ACTIVO',
                'subtotal'         => $subtotal,
                'descuento'        => $descuento,
                'total'            => round($subtotal - $descuento, 2),
                'observacion'      => $datos['observacion'] ?? null,
                'pedido_nro'       => $datos['pedido_nro'] ?? null,
                'pedido_tipo'      => $datos['pedido_tipo'] ?? null,
            ]);

            $factura->detalles()->createMany($lineas);

            // Lo vendido sale del inventario.
            $this->moverStock($lineas, $factura->id, $ci, date('Y-m-d H:i:s'), 'SALIDA');

            return $factura;
        });

        if ($tipo !== 'FACTURA') {
            return response()->json([
                'factura' => $factura->load('detalles'),
                'message' => 'Venta registrada con el número ' . $factura->id . '; el stock ya fue descontado',
            ], 201);
        }

        $siat = new SiatService();

        if (config('siat.simulado')) {
            // ---------------------------------------------------------------
            // ENVIO A IMPUESTOS DESACTIVADO (SIAT_SIMULADO=true en el .env).
            //
            // Mientras las credenciales del SIAT las use el otro sistema no se
            // puede generar el CUFD, asi que la factura se rellena entera en
            // local para poder revisar como sale impresa, pero NO se envia:
            // queda marcada como SIMULADO y no tiene valor fiscal. Para volver
            // a emitir de verdad basta con quitar SIAT_SIMULADO del .env; las
            // que quedaron simuladas se reenvian desde la pantalla Impuestos.
            // ---------------------------------------------------------------
            $factura = $siat->simularEmision($factura);
        } else {
            // Emision al SIAT. Va fuera de la transaccion a proposito: la venta
            // ya se cobro y el stock ya salio, asi que un problema con
            // Impuestos no debe deshacer nada. Si falla queda con
            // estado_siat = ERROR y se puede reintentar desde Impuestos.
            $factura = $siat->emitirFactura($factura, $usuario->CodAut);
        }

        return response()->json([
            'factura' => $factura->load('detalles'),
            'siat'    => [
                'estado'  => $factura->estado_siat,
                'mensaje' => $factura->mensaje_siat,
                'cuf'     => $factura->cuf,
            ],
            'message' => $this->mensajeEmision($factura),
        ], 201);
    }

    /** Los productos por kilo se cobran por peso, no por cantidad. */
    private function esGranel($producto)
    {
        return strtoupper(trim((string) $producto->codUnid)) === 'KG';
    }

    /** Que decirle al cajero segun como haya salido la emision. */
    private function mensajeEmision(Factura $factura)
    {
        $base = 'Factura ' . ($factura->nro_factura ?: $factura->id) . ' registrada; el stock ya fue descontado';

        if ($factura->estado_siat === 'ERROR') {
            return $base . '. NO se pudo enviar a Impuestos: ' . $factura->mensaje_siat
                . '. Queda sin valor fiscal hasta reenviarla desde Impuestos';
        }

        // Al cajero hay que decirle la verdad aunque el papel salga completo.
        if ($factura->estado_siat === SiatService::ESTADO_SIMULADO) {
            return $base . '. Modo simulación: NO se envió a Impuestos, así que'
                . ' se imprime pero todavía no tiene valor fiscal';
        }

        if (!$factura->cuf) {
            return $base . ', pero sin CUF: revisá los datos de Impuestos';
        }

        return $base . ' y enviada a Impuestos (' . $factura->estado_siat . ')';
    }

    /**
     * Anula sin borrar: la factura sigue existiendo pero deja de sumar.
     * Se guarda el motivo porque una anulacion sin razon no sirve de nada.
     */
    public function anular(Request $request, $id)
    {
        $datos = $request->validate([
            'codigo_motivo' => 'required|integer|between:1,4',
        ]);

        $motivos = [
            1 => 'FACTURA MAL EMITIDA',
            2 => 'DATOS DE EMISION INCORRECTOS',
            3 => 'FACTURA O NOTA DEVUELTA',
            4 => 'SUSTITUCION DE FACTURA EMITIDA EN CONTINGENCIA',
        ];

        $factura = Factura::with('detalles')->find($id);
        if (!$factura) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }

        $yaAnuladaLocalmente = $factura->estado === 'ANULADO';

        // Una factura con CUF existe en Impuestos y debe anularse primero ahi.
        // Si el SIAT rechaza o no responde, no se toca el estado ni el stock
        // local. Tambien permite reparar facturas que una version anterior
        // dejo anuladas solamente en Sofia.
        // La simulada tiene CUF pero nunca llego a Impuestos: no hay nada que
        // anular alli, se da de baja solo en Sofia.
        $respuestaSiat = null;
        if ($factura->tipo_comprobante === 'FACTURA' && $factura->cuf
            && $factura->estado_siat !== SiatService::ESTADO_SIMULADO) {
            $siat = new SiatService();

            try {
                $respuestaSiat = $siat->anularFactura($factura, $datos['codigo_motivo']);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'No se pudo anular en Impuestos: ' . $siat->mensajeError($e),
                ], 422);
            }

            if (empty($respuestaSiat['transaccion'])) {
                return response()->json([
                    'message' => 'Impuestos rechazó la anulación: ' . $respuestaSiat['mensaje'],
                    'siat' => $respuestaSiat,
                ], 422);
            }
        }

        if ($yaAnuladaLocalmente) {
            return response()->json([
                'message' => 'Factura anulada correctamente en Impuestos; el stock local ya había sido devuelto',
                'factura' => $factura->fresh(),
                'siat' => $respuestaSiat,
            ]);
        }

        $ci = trim((string) ($request->user()->ci ?? ''));

        DB::transaction(function () use ($factura, $datos, $motivos, $ci) {
            $lineas = $factura->detalles->map(function ($d) {
                return [
                    'cod_prod' => $d->cod_prod,
                    'cantidad' => (float) $d->cantidad,
                    'peso'     => (float) $d->peso,
                    'precio'   => (float) $d->precio,
                ];
            })->all();

            // Lo que no se vendio vuelve al inventario.
            $this->moverStock($lineas, $factura->id, $ci, date('Y-m-d H:i:s'), 'ANULACION');

            $factura->update([
                'estado'           => 'ANULADO',
                'motivo_anulacion' => $motivos[$datos['codigo_motivo']],
                'anulado_at'       => now(),
            ]);
        });

        return response()->json([
            'message' => $respuestaSiat
                ? 'Anulada en Impuestos y en Sofia; el stock descontado fue devuelto'
                : 'Anulada en Sofia; el stock descontado fue devuelto',
            'factura' => $factura->fresh(),
            'siat' => $respuestaSiat,
        ]);
    }

    /**
     * Hoja de estilos comun de los impresos.
     *
     * Va en un solo sitio para que el voucher y la factura se vean como
     * documentos de la misma casa. Ojo: dompdf no soporta flexbox ni grid, asi
     * que la maquetacion se hace con tablas y anchos en porcentaje.
     */
    private function estilosImpresion()
    {
        return "
            @page { margin: 12mm 11mm 20mm 11mm }
            * { font-family: 'DejaVu Sans', sans-serif }
            body { font-size: 9.5px; color: #222 }
            .c { text-align: center } .r { text-align: right }
            .gris { color: #777 }

            /* Cabecera: logo, datos del emisor y caja del documento. */
            .cabecera { width: 100%; border-collapse: collapse }
            .cabecera td { vertical-align: top; padding: 0 }
            .logo { width: 118px }
            .empresa { font-size: 14px; font-weight: bold; color: #c1272d; letter-spacing: .5px }
            .empresa-dato { font-size: 8.5px; color: #555; line-height: 1.45 }

            .caja-doc { border: 1.5px solid #c1272d; border-radius: 3px; width: 100% }
            .caja-doc .tit { background: #c1272d; color: #fff; font-size: 10px;
                             font-weight: bold; text-align: center; padding: 3px; letter-spacing: 1px }
            .caja-doc td { padding: 2px 6px; font-size: 9px }
            .caja-doc .et { color: #666 }
            .caja-doc .nro { font-size: 15px; font-weight: bold; color: #c1272d }

            /* Datos del cliente. */
            .datos { width: 100%; border-collapse: collapse; margin-top: 8px;
                     border: 1px solid #ccc; border-radius: 3px }
            .datos td { padding: 3.5px 6px; border-bottom: 1px solid #eee; font-size: 9px }
            .datos .et { color: #777; font-size: 8px; text-transform: uppercase; letter-spacing: .3px }

            /* Detalle. */
            .detalle { width: 100%; border-collapse: collapse; margin-top: 9px }
            .detalle th { background: #37474f; color: #fff; font-size: 8px; font-weight: bold;
                          padding: 5px 4px; text-transform: uppercase; letter-spacing: .4px }
            .detalle td { padding: 4px; border-bottom: 1px solid #e4e4e4; font-size: 9px }
            .detalle tr.par td { background: #fafafa }
            .detalle .cod { color: #666; font-size: 8.5px }

            /* Totales. */
            .totales { width: 100%; border-collapse: collapse }
            .totales td { padding: 3.5px 8px; font-size: 9.5px; border-bottom: 1px solid #eee }
            .totales .final td { background: #37474f; color: #fff; font-size: 12px;
                                 font-weight: bold; border: 0 }
            .literal { border: 1px solid #ddd; padding: 6px 8px; font-size: 9px; line-height: 1.5 }
            .literal b { color: #555 }

            .aviso { border: 1.5px solid #c62828; background: #ffebee; color: #c62828;
                     font-weight: bold; text-align: center; padding: 5px; margin: 7px 0; font-size: 9.5px }

            .copia { text-align: center; font-size: 10px; font-weight: bold;
                     letter-spacing: 4px; color: #999; margin-top: 6px }
            .pie { position: fixed; bottom: -14mm; left: 0; right: 0 }
            .legal { font-size: 7.5px; color: #888; text-align: center; line-height: 1.5 }
        ";
    }

    /**
     * Numero del codigo de barras del producto, para la columna del detalle.
     *
     * Va en numero y no como imagen: es lo que se pidio para la impresion.
     * Unos pocos productos tienen una letra al final (500104D), asi que se
     * dejan solo los digitos; la columna Codigo sigue con el codigo completo.
     */
    private function celdaBarras($codigo)
    {
        $numero = preg_replace('/\D/', '', (string) $codigo);

        return $numero !== '' ? e($numero) : '&mdash;';
    }
    /**
     * Camion con el que sale la venta.
     *
     * En Sofia el camion es tbpedidos.placa, asi que solo lo tiene lo que nace
     * de un pedido: una venta de mostrador sale sin placa.
     */
    private function camion($factura)
    {
        if (!$factura->pedido_nro || !$factura->pedido_tipo) {
            return '';
        }

        $placa = DB::table('tbpedidos')
            ->where('NroPed', $factura->pedido_nro)
            ->whereRaw('UPPER(TRIM(tipo)) = ?', [strtoupper(trim($factura->pedido_tipo))])
            ->value('placa');

        return trim((string) $placa);
    }

    /** Bloque de cabecera con el logo y los datos del emisor. */
    private function cabeceraEmisor($cajaDerecha)
    {
        $emisor = config('siat.emisor');
        $logo = is_file(public_path('img/sofia.png'))
            ? base64_encode(file_get_contents(public_path('img/sofia.png')))
            : '';

        return "<table class='cabecera'>
            <tr>
                <td style='width:130px'>"
                    . ($logo ? "<img class='logo' src='data:image/png;base64,$logo'>" : '')
                . "</td>
                <td style='padding-left:6px'>
                    <div class='empresa'>" . e($emisor['nombre']) . "</div>
                    <div class='empresa-dato'>
                        " . e($emisor['sucursal']) . " &middot; NIT " . e(config('siat.nit')) . "<br>
                        " . e($emisor['direccion']) . "<br>
                        Telf. " . e($emisor['telefono']) . " &middot; " . e($emisor['ciudad']) . "
                    </div>
                </td>
                <td style='width:210px'>$cajaDerecha</td>
            </tr>
        </table>";
    }

    /**
     * Voucher: la boleta de entrega, en tamano carta.
     *
     * Replica la boleta que se imprime en papel: cabecera con los datos del
     * cliente, la grilla de productos y el pie con literal, placa y totales.
     */
    public function voucher($id)
    {
        $factura = Factura::with(['detalles', 'cliente', 'vendedor'])->find($id);
        if (!$factura) {
            return response()->json(['message' => 'La venta no existe'], 404);
        }

        if ($bloqueo = $this->bloqueoCarga($factura)) {
            return response()->json(['message' => $bloqueo], 422);
        }

        return $this->pdf($this->voucherHtml($factura), 'voucher_' . $factura->id);
    }

    /** Cache de tbproductos.trozado por codigo, para no repetir la consulta
     *  en cada voucher cuando se imprime un lote entero. */
    private $trozados = [];

    /**
     * Codigos del detalle que son producto trozado.
     *
     * Lo trozado se entrega en piezas y la cantidad no dice nada util en el
     * papel: la columna Cant de la boleta sale con un guion. La bandera es
     * texto en tbproductos, asi que se acepta cualquiera de las formas con las
     * que se puede haber marcado a mano.
     */
    private function codigosTrozados($detalles)
    {
        $faltan = collect($detalles)
            ->map(function ($d) { return trim((string) $d->cod_prod); })
            ->filter()
            ->unique()
            ->reject(function ($cod) { return array_key_exists($cod, $this->trozados); })
            ->values();

        if ($faltan->isNotEmpty()) {
            $marcados = DB::table('tbproductos')
                ->whereIn(DB::raw('TRIM(cod_prod)'), $faltan->all())
                ->whereIn(DB::raw("UPPER(TRIM(COALESCE(trozado, '')))"), ['SI', '1', 'X', 'TRUE'])
                ->get([DB::raw('TRIM(cod_prod) as cod')])
                ->pluck('cod')
                ->all();

            foreach ($faltan as $cod) {
                $this->trozados[$cod] = in_array($cod, $marcados, true);
            }
        }

        return $this->trozados;
    }

    /** El voucher como HTML: aparte, para poder juntar varios en un PDF. */
    private function voucherHtml(Factura $factura)
    {
        $cliente = $factura->cliente;

        $vendedor = $factura->vendedor
            ? trim(implode(' ', array_filter([
                trim($factura->vendedor->Nombre1),
                trim($factura->vendedor->App1),
                trim($factura->vendedor->Apm),
            ])))
            : '';

        $placa = $this->camion($factura);

        $trozados = $this->codigosTrozados($factura->detalles);

        $filas = '';
        foreach ($factura->detalles as $i => $d) {
            // Como en la boleta de papel: CANT son las piezas que se entregan y
            // el peso de la balanza va en KG / P. NETO, que es lo que se cobra
            // en lo que va a granel.
            $peso = (float) $d->peso;
            $par = $i % 2 ? " class='par'" : '';
            // Lo trozado no se cuenta: en su lugar va un guion.
            $trozado = !empty($trozados[trim((string) $d->cod_prod)]);

            $filas .= "<tr$par>"
                . "<td class='r'>" . ($trozado ? '—' : number_format($d->cantidad, 2)) . '</td>'
                . "<td class='cod'>" . e($d->cod_prod) . '</td>'
                . "<td class='c cod'>" . $this->celdaBarras($d->cod_prod) . '</td>'
                . '<td>' . e($d->nombre) . '</td>'
                . "<td class='c'>" . e($d->unidad) . '</td>'
                . "<td class='r'>" . ($peso > 0 ? number_format($peso, 3) : '—') . '</td>'
                . "<td class='r'>" . number_format($d->cantidad_facturada, 2) . '</td>'
                . "<td class='r'>" . number_format($d->precio, 2) . '</td>'
                . "<td class='r'><b>" . number_format($d->subtotal, 2) . '</b></td>'
                . '</tr>';
        }

        $caja = "<table class='caja-doc'>
            <tr><td colspan='2' class='tit'>BOLETA DE ENTREGA</td></tr>
            <tr><td class='et'>Nro</td><td class='r nro'>" . $factura->id . "</td></tr>
            <tr><td class='et'>Fecha</td><td class='r'>" . $factura->fecha->format('d/m/Y') . "</td></tr>
            <tr><td class='et'>Hora</td><td class='r'>" . e($factura->hora) . "</td></tr>
        </table>";

        $anulado = $factura->estado === 'ANULADO'
            ? "<div class='aviso'>ANULADO &middot; " . e($factura->motivo_anulacion) . '</div>'
            : '';

        $descuento = (float) $factura->descuento > 0
            ? "<tr><td>Descuento Bs.</td><td class='r'>-" . number_format($factura->descuento, 2) . '</td></tr>'
            : '';

        $html = '<style>' . $this->estilosImpresion() . "
            .firmas { width: 100%; margin-top: 26px }
            .firmas td { padding: 0 14px; font-size: 8.5px; color: #666; text-align: center }
            .firma-linea { border-top: 1px solid #999; padding-top: 3px; margin-top: 34px }
        </style>"
        . $this->cabeceraEmisor($caja)
        . $anulado
        . "<table class='datos'>
            <tr>
                <td style='width:52%'><span class='et'>Cliente</span><br><b>"
                    . e($factura->nombre ?: 'Sin cliente') . "</b></td>
                <td style='width:24%'><span class='et'>CI / NIT</span><br>" . e($factura->nit ?: '—') . "</td>
                <td><span class='et'>Teléfono</span><br>" . e($cliente->Telf ?? '—') . "</td>
            </tr>
            <tr>
                <td><span class='et'>Dirección</span><br>" . e($cliente->Direccion ?? '—') . "</td>
                <td><span class='et'>Zona</span><br>" . e($cliente->zona ?? '—') . "</td>
                <td><span class='et'>Territorio</span><br>" . e($cliente->territorio ?? '—') . "</td>
            </tr>
            <tr>
                <td><span class='et'>Vendedor</span><br>" . e($vendedor ?: '—') . "</td>
                <td><span class='et'>Tipo de pago</span><br><b>" . e($factura->tipo_pago) . "</b></td>
                <td><span class='et'>Camión</span><br><b>" . e($placa ?: '—') . "</b></td>
            </tr>
            <tr>
                <td colspan='3'><span class='et'>Observación</span><br>"
                    . e($factura->observacion ?: '—') . "</td>
            </tr>
        </table>

        <table class='detalle'>
            <tr>
                <th style='width:7%'>Cant</th>
                <th style='width:8%'>Código</th>
                <th style='width:10%'>Cód. barras</th>
                <th>Concepto</th>
                <th style='width:6%'>Unid</th>
                <th style='width:9%'>Peso Kg</th>
                <th style='width:9%'>P. Neto</th>
                <th style='width:10%'>P. Unit</th>
                <th style='width:11%'>Total</th>
            </tr>
            $filas
        </table>

        <table style='width:100%; margin-top:10px; border-collapse:collapse'><tr>
            <td style='vertical-align:top; padding-right:10px'>
                <div class='literal'>
                    <b>SON:</b> " . e($this->enLetras($factura->total)) . " Bolivianos
                </div>
            </td>
            <td style='width:38%; vertical-align:top'>
                <table class='totales'>
                    <tr><td>Subtotal Bs.</td><td class='r'>" . number_format($factura->subtotal, 2) . "</td></tr>
                    $descuento
                    <tr class='final'><td>TOTAL Bs.</td><td class='r'>"
                        . number_format($factura->total, 2) . "</td></tr>
                </table>
            </td>
        </tr></table>

        <table class='firmas'>
            <tr>
                <td><div class='firma-linea'>C.I.</div></td>
                <td><div class='firma-linea'>Nombre</div></td>
                <td><div class='firma-linea'>Firma de conformidad</div></td>
            </tr>
        </table>

        <div class='pie'>
            <div class='copia'>COPIA</div>
            <div class='legal'>
                Respalde su cancelación del presente con la boleta original.<br>
                " . e(config('siat.emisor')['nombre']) . " &middot; documento generado el "
                . date('d/m/Y H:i') . "
            </div>
        </div>";

        return $html;
    }

    /**
     * Factura en tamano carta, con el mismo formato que la del sistema legado.
     *
     * Mientras no se emita al SIAT desde aca la venta no tiene CUF ni numero
     * de autorizacion, asi que el documento sale rotulado como sin valor
     * fiscal: hacerlo pasar por una factura fiscal sin serlo dejaria al cliente
     * con un papel que no le sirve para credito fiscal.
     */
    public function factura($id)
    {
        $factura = Factura::with(['detalles', 'cliente'])->find($id);
        if (!$factura) {
            return response()->json(['message' => 'La venta no existe'], 404);
        }

        if ($factura->tipo_comprobante !== 'FACTURA') {
            return response()->json([
                'message' => 'Esta venta se entregó como voucher, no tiene factura',
            ], 422);
        }

        if ($bloqueo = $this->bloqueoCarga($factura)) {
            return response()->json(['message' => $bloqueo], 422);
        }

        return $this->pdf($this->facturaHtml($factura), 'factura_' . $factura->id);
    }

    /** La factura como HTML: aparte, para poder juntar varias en un PDF. */
    private function facturaHtml(Factura $factura)
    {
        $placa = $this->camion($factura);

        $filas = '';
        foreach ($factura->detalles as $i => $d) {
            $par = $i % 2 ? " class='par'" : '';

            $filas .= "<tr$par>"
                . "<td class='cod'>" . e($d->cod_prod) . '</td>'
                . "<td class='c cod'>" . $this->celdaBarras($d->cod_prod) . '</td>'
                // Lo declarado a Impuestos es lo que se cobra: en lo que va por
                // kilo, el peso. Tiene que coincidir con lo que manda el SIAT.
                . "<td class='r'>" . number_format($d->cantidad_facturada, 2) . '</td>'
                . "<td class='c'>" . e($d->unidad === 'KG' ? 'KILOGRAMO' : 'UNIDAD (SERVICIOS)') . '</td>'
                . '<td>' . e($d->nombre) . '</td>'
                . "<td class='r'>" . number_format($d->precio, 2) . '</td>'
                . "<td class='r'>0.00</td>"
                . "<td class='r'><b>" . number_format($d->subtotal, 2) . '</b></td>'
                . '</tr>';
        }

        $cuf = (string) $factura->cuf;

        $caja = "<table class='caja-doc'>
            <tr><td colspan='2' class='tit'>FACTURA</td></tr>
            <tr><td class='et'>Nro</td><td class='r nro'>"
                . ($factura->nro_factura ?: $factura->id) . "</td></tr>
            <tr><td class='et'>NIT emisor</td><td class='r'>" . e(config('siat.nit')) . "</td></tr>
            <tr><td class='et'>Cód. autorización</td><td class='r' style='font-size:7px; word-wrap:break-word'>"
                . ($cuf !== '' ? implode('<br>', array_map('e', str_split($cuf, 24))) : '—') . "</td></tr>
        </table>";

        $sinCuf = $cuf === ''
            ? "<div class='aviso'>DOCUMENTO SIN VALOR FISCAL &middot; no fue emitido a Impuestos Nacionales</div>"
            : '';

        // El QR solo tiene sentido si la factura llego al SIAT: es el enlace
        // con el que el cliente la verifica en el portal de Impuestos.
        $qr = '';
        if ($cuf !== '') {
            $png = base64_encode(FacturaFiscalController::qrPng(
                FacturaFiscalController::urlSiat($cuf, $factura->nro_factura ?: $factura->id)
            ));
            $qr = "<td style='width:120px; text-align:center; vertical-align:top'>"
                . "<img src='data:image/png;base64,$png' style='width:110px; height:110px'></td>";
        }

        $anulado = $factura->estado === 'ANULADO'
            ? "<div class='aviso'>FACTURA ANULADA &middot; " . e($factura->motivo_anulacion) . '</div>'
            : '';

        $html = '<style>' . $this->estilosImpresion() . "
            .subtitulo { text-align: center; font-size: 8.5px; color: #666; margin: 6px 0 2px }
        </style>"
        . $this->cabeceraEmisor($caja)
        . "<div class='subtitulo'>(Con derecho a crédito fiscal)</div>"
        . $sinCuf
        . $anulado
        . "<table class='datos'>
            <tr>
                <td style='width:52%'><span class='et'>Nombre / Razón social</span><br><b>"
                    . e($factura->nombre ?: 'Sin cliente') . "</b></td>
                <td style='width:24%'><span class='et'>NIT / CI / CEX</span><br>"
                    . e($factura->nit ?: '—') . "</td>
                <td><span class='et'>Fecha</span><br>"
                    . $factura->fecha->format('d/m/Y') . ' ' . e($factura->hora) . "</td>
            </tr>
            <tr>
                <td><span class='et'>Cod. cliente</span><br>" . ($factura->cliente_id ?: '—') . "</td>
                <td><span class='et'>Complemento</span><br>"
                    . e(trim((string) ($factura->cliente->complto ?? '')) ?: '—') . "</td>
                <td><span class='et'>Forma de pago</span><br>" . e($factura->tipo_pago) . "</td>
            </tr>
            <tr>
                <td><span class='et'>Camión</span><br><b>" . e($placa ?: '—') . "</b></td>
                <td><span class='et'>Pedido</span><br>" . ($factura->pedido_nro ?: '—') . "</td>
                <td><span class='et'>Observación</span><br>" . e($factura->observacion ?: '—') . "</td>
            </tr>
        </table>

        <table class='detalle'>
            <tr>
                <th style='width:9%'>Código</th>
                <th style='width:10%'>Cód. barras</th>
                <th style='width:8%'>Cantidad</th>
                <th style='width:12%'>Unidad</th>
                <th>Descripción</th>
                <th style='width:10%'>P. Unitario</th>
                <th style='width:8%'>Descuento</th>
                <th style='width:11%'>Importe</th>
            </tr>
            $filas
        </table>

        <table style='width:100%; margin-top:10px; border-collapse:collapse'><tr>
            <td style='vertical-align:top; padding-right:10px'>
                <div class='literal'>
                    <b>SON:</b> " . e(mb_strtoupper($this->enLetras($factura->total))) . " BOLIVIANOS
                </div>
            </td>
            <td style='width:40%; vertical-align:top'>
                <table class='totales'>
                    <tr><td>Subtotal Bs.</td><td class='r'>" . number_format($factura->subtotal, 2) . "</td></tr>
                    <tr><td>Descuento Bs.</td><td class='r'>" . number_format($factura->descuento, 2) . "</td></tr>
                    <tr class='final'><td>MONTO A PAGAR Bs.</td><td class='r'>"
                        . number_format($factura->total, 2) . "</td></tr>
                </table>
            </td>
        </tr></table>

        <table style='width:100%; margin-top:6px; border-collapse:collapse'><tr>
            <td class='legal' style='vertical-align:top'>
                &quot;ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS,
                EL USO ILÍCITO SERÁ SANCIONADO PENALMENTE DE ACUERDO A LEY&quot;.<br>
                " . e($factura->leyenda ?: SiatService::LEYENDA) . "<br>
                Este documento es la Representación Gráfica de un Documento Fiscal Digital
                emitido en una modalidad de facturación en línea.
            </td>
            $qr
        </tr></table>

        <div class='copia'>COPIA</div>";

        return $html;
    }

    /**
     * Todos los comprobantes del filtro en un solo PDF, uno por hoja.
     *
     * Cada lote lleva solo lo suyo: el de facturas, las ventas entregadas como
     * factura; el de vouchers, las que salieron como voucher. Asi lo del dia se
     * imprime de una vez sin que una misma venta salga en los dos lotes.
     * El tope existe para no armar un PDF de cientos de hojas por un filtro
     * demasiado abierto.
     */
    public function lote(Request $request, $documento)
    {
        $documento = $documento === 'factura' ? 'factura' : 'voucher';

        $facturas = $this->filtrar($request)
            ->when($documento === 'factura', function ($q) {
                // La factura solo existe si la venta se entrego como factura.
                $q->where('tipo_comprobante', 'FACTURA');
            }, function ($q) {
                // Las que se entregaron como factura no van en el lote de
                // vouchers: cada venta se imprime en uno solo.
                $q->where('tipo_comprobante', '<>', 'FACTURA');
            })
            ->reorder('id')
            ->limit(self::MAX_LOTE)
            ->get();

        if ($facturas->isEmpty()) {
            return response()->json([
                'message' => $documento === 'factura'
                    ? 'No hay facturas en lo que estás viendo'
                    : 'No hay vouchers en lo que estás viendo',
            ], 422);
        }

        // Un lote sale entero o no sale: si alguno de los camiones todavia no
        // reviso su carga se frena todo, porque el papel se reparte junto.
        $camiones = $this->camionesSinVerificar($facturas);
        if (!empty($camiones)) {
            return response()->json([
                'message' => count($camiones) === 1
                    ? 'El camión ' . $camiones[0] . ' todavía no verificó su carga; no se puede imprimir el lote'
                    : 'Estos camiones todavía no verificaron su carga: ' . implode(', ', $camiones),
            ], 422);
        }

        $paginas = $facturas->map(function ($factura) use ($documento) {
            return $documento === 'factura'
                ? $this->facturaHtml($factura)
                : $this->voucherHtml($factura);
        })->implode("<div style='page-break-after: always'></div>");

        return $this->pdf($paginas, $documento . 's_' . date('Y-m-d'));
    }

    /**
     * Reporte del listado: lo mismo que se ve en pantalla, para llevar.
     *
     * contenido = ventas -> una fila por comprobante, con sus totales.
     * contenido = cambios -> una fila por producto que salio con otra cantidad
     * de la que pedia el pedido, que es lo que no se puede reconstruir mirando
     * la factura sola.
     */
    public function reporte(Request $request)
    {
        $datos = $request->validate([
            'formato'    => 'nullable|in:pdf,excel',
            'contenido'  => 'nullable|in:ventas,cambios',
        ]);

        $contenido = $datos['contenido'] ?? 'ventas';
        $facturas = $this->filtrar($request)->reorder('id')->get();

        $filas = $contenido === 'cambios'
            ? $this->filasCambios($facturas)
            : $this->filasVentas($facturas);

        if (empty($filas)) {
            return response()->json([
                'message' => $contenido === 'cambios'
                    ? 'Ningún pedido salió con cantidades distintas en este filtro'
                    : 'No hay ventas en este filtro',
            ], 422);
        }

        return ($datos['formato'] ?? 'pdf') === 'excel'
            ? $this->reporteExcel($contenido, $filas, $request)
            : $this->reportePdf($contenido, $filas, $request);
    }

    /** Una fila por comprobante. */
    private function filasVentas($facturas)
    {
        return $facturas->map(function ($f) {
            return [
                'nro'       => (string) ($f->nro_factura ?: $f->id),
                'tipo'      => $f->tipo_comprobante,
                // La comanda que origino la venta; vacia en la venta directa.
                'pedido'    => (string) ($f->pedido_nro ?: '—'),
                'fecha'     => $f->fecha->format('d/m/Y') . ' ' . $f->hora,
                'cliente'   => $f->nombre ?: 'Sin cliente',
                'nit'       => $f->nit ?: '—',
                'pago'      => $f->tipo_pago,
                'estado'    => $f->estado . ($f->estado_siat ? ' / ' . $f->estado_siat : ''),
                'subtotal'  => (float) $f->subtotal,
                'descuento' => (float) $f->descuento,
                'total'     => (float) $f->total,
            ];
        })->all();
    }

    /** Una fila por producto que no salio como lo pedia el pedido. */
    private function filasCambios($facturas)
    {
        $filas = [];

        foreach ($facturas as $f) {
            foreach ($f->detalles as $d) {
                if ($d->cantidad_pedida === null
                    || (float) $d->cantidad === (float) $d->cantidad_pedida) {
                    continue;
                }

                $filas[] = [
                    'nro'        => (string) ($f->nro_factura ?: $f->id),
                    'fecha'      => $f->fecha->format('d/m/Y'),
                    'pedido'     => (string) ($f->pedido_nro ?: '—'),
                    'cliente'    => $f->nombre ?: 'Sin cliente',
                    'producto'   => $d->nombre,
                    'unidad'     => $d->unidad,
                    'pedida'     => (float) $d->cantidad_pedida,
                    'entregada'  => (float) $d->cantidad,
                    'diferencia' => round((float) $d->cantidad - (float) $d->cantidad_pedida, 3),
                    'precio'     => (float) $d->precio,
                    // Lo que se dejo de cobrar (o se cobro de mas) por el cambio.
                    'importe'    => round(
                        ((float) $d->cantidad - (float) $d->cantidad_pedida) * (float) $d->precio,
                        2
                    ),
                ];
            }
        }

        return $filas;
    }

    /** Columnas de cada reporte: etiqueta, clave, ancho y si es importe. */
    private function columnasReporte($contenido)
    {
        if ($contenido === 'cambios') {
            return [
                ['Nº', 'nro', 7, false], ['Fecha', 'fecha', 11, false],
                ['Pedido', 'pedido', 9, false], ['Cliente', 'cliente', 26, false],
                ['Producto', 'producto', 32, false], ['Unid', 'unidad', 6, false],
                ['Pedida', 'pedida', 9, true], ['Entregada', 'entregada', 10, true],
                ['Diferencia', 'diferencia', 10, true], ['Precio Bs', 'precio', 10, true],
                ['Importe Bs', 'importe', 11, true],
            ];
        }

        return [
            ['Nº', 'nro', 8, false], ['Tipo', 'tipo', 10, false],
            ['Pedido', 'pedido', 9, false],
            ['Fecha', 'fecha', 16, false], ['Cliente', 'cliente', 30, false],
            ['NIT / CI', 'nit', 13, false], ['Pago', 'pago', 11, false],
            ['Estado', 'estado', 16, false], ['Subtotal Bs', 'subtotal', 12, true],
            ['Descuento Bs', 'descuento', 12, true], ['Total Bs', 'total', 12, true],
        ];
    }

    /** Titulo y rango, para que el papel diga que se esta mirando. */
    private function tituloReporte($contenido, Request $request)
    {
        $desde = $request->input('desde');
        $hasta = $request->input('hasta');

        $rango = $desde && $hasta && $desde === $hasta
            ? date('d/m/Y', strtotime($desde))
            : trim(($desde ? 'del ' . date('d/m/Y', strtotime($desde)) : '')
                . ($hasta ? ' al ' . date('d/m/Y', strtotime($hasta)) : ''));

        return [
            $contenido === 'cambios' ? 'CAMBIOS EN LOS PEDIDOS' : 'REPORTE DE VENTAS',
            $rango ?: 'Todas las fechas',
        ];
    }

    private function reportePdf($contenido, array $filas, Request $request)
    {
        list($titulo, $rango) = $this->tituloReporte($contenido, $request);
        $columnas = $this->columnasReporte($contenido);

        $encabezado = '';
        foreach ($columnas as list($etiqueta, $clave, $ancho, $esImporte)) {
            $encabezado .= "<th style='width:{$ancho}%" . ($esImporte ? '; text-align:right' : '') . "'>"
                . e($etiqueta) . '</th>';
        }

        $cuerpo = '';
        $totales = [];
        foreach ($filas as $i => $fila) {
            $par = $i % 2 ? " class='par'" : '';
            $cuerpo .= "<tr$par>";
            foreach ($columnas as list($etiqueta, $clave, $ancho, $esImporte)) {
                $valor = $fila[$clave];
                if ($esImporte) {
                    $totales[$clave] = ($totales[$clave] ?? 0) + (float) $valor;
                }
                $cuerpo .= $esImporte
                    ? "<td class='r'>" . number_format((float) $valor, 2) . '</td>'
                    : '<td>' . e($valor) . '</td>';
            }
            $cuerpo .= '</tr>';
        }

        // Solo se suman los importes; sumar cantidades de unidades distintas no
        // significa nada.
        $sumables = $contenido === 'cambios' ? ['importe'] : ['subtotal', 'descuento', 'total'];
        $pie = "<tr class='total'>";
        $primera = true;
        foreach ($columnas as list($etiqueta, $clave, $ancho, $esImporte)) {
            if ($primera) {
                $pie .= "<td colspan='1'><b>TOTAL (" . count($filas) . ")</b></td>";
                $primera = false;
                continue;
            }
            $pie .= in_array($clave, $sumables, true)
                ? "<td class='r'><b>" . number_format($totales[$clave] ?? 0, 2) . '</b></td>'
                : '<td></td>';
        }
        $pie .= '</tr>';

        $html = '<style>' . $this->estilosImpresion() . "
            .rep { width: 100%; border-collapse: collapse; margin-top: 8px }
            .rep th { background: #37474F; color: #fff; font-size: 8px; padding: 5px 4px;
                      text-align: left; text-transform: uppercase }
            .rep td { font-size: 8px; padding: 4px; border-bottom: 1px solid #E0E0E0 }
            .rep td.r { text-align: right }
            .rep tr.par td { background: #F5F7F8 }
            .rep tr.total td { background: #ECEFF1; border-top: 2px solid #37474F; font-size: 8.5px }
            .tit-rep { text-align: center; margin-bottom: 2px }
            .tit-rep h1 { font-size: 13px; margin: 0; letter-spacing: 1px }
            .tit-rep div { font-size: 8.5px; color: #666 }
        </style>
        <div class='tit-rep'>
            <h1>" . e($titulo) . "</h1>
            <div>" . e(config('siat.emisor')['nombre']) . ' &middot; ' . e($rango)
            . ' &middot; generado el ' . date('d/m/Y H:i') . "</div>
        </div>
        <table class='rep'>
            <thead><tr>$encabezado</tr></thead>
            <tbody>$cuerpo$pie</tbody>
        </table>";

        return $this->pdf($html, $contenido . '_' . date('Y-m-d'));
    }

    private function reporteExcel($contenido, array $filas, Request $request)
    {
        list($titulo, $rango) = $this->tituloReporte($contenido, $request);
        $columnas = $this->columnasReporte($contenido);
        $ultima = Coordinate::stringFromColumnIndex(count($columnas));

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle($contenido === 'cambios' ? 'Cambios' : 'Ventas');

        // Cabecera del reporte: titulo y rango, como en el PDF.
        $hoja->mergeCells('A1:' . $ultima . '1')->setCellValue('A1', $titulo);
        $hoja->mergeCells('A2:' . $ultima . '2')->setCellValue(
            'A2',
            config('siat.emisor')['nombre'] . ' · ' . $rango . ' · generado el ' . date('d/m/Y H:i')
        );
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $hoja->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('666666');
        $hoja->getStyle('A1:' . $ultima . '2')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($columnas as $i => list($etiqueta, $clave, $ancho, $esImporte)) {
            $hoja->setCellValueByColumnAndRow($i + 1, 4, $etiqueta);
        }

        $fila = 5;
        foreach ($filas as $registro) {
            foreach ($columnas as $i => list($etiqueta, $clave, $ancho, $esImporte)) {
                $hoja->setCellValueByColumnAndRow($i + 1, $fila, $registro[$clave]);
            }
            $fila++;
        }

        $sumables = $contenido === 'cambios' ? ['importe'] : ['subtotal', 'descuento', 'total'];
        $hoja->setCellValue('A' . $fila, 'TOTAL (' . count($filas) . ')');
        foreach ($columnas as $i => list($etiqueta, $clave, $ancho, $esImporte)) {
            if (!in_array($clave, $sumables, true)) {
                continue;
            }
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $hoja->setCellValue(
                $letra . $fila,
                '=SUM(' . $letra . '5:' . $letra . ($fila - 1) . ')'
            );
        }

        // Encabezado de la tabla en blanco sobre azul oscuro, como el PDF.
        $hoja->getStyle('A4:' . $ultima . '4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '37474F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECEFF1']],
        ]);
        $hoja->getStyle('A4:' . $ultima . $fila)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BDBDBD');

        foreach ($columnas as $i => list($etiqueta, $clave, $ancho, $esImporte)) {
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $hoja->getColumnDimension($letra)->setAutoSize(true);
            if ($esImporte) {
                $hoja->getStyle($letra . '5:' . $letra . $fila)
                    ->getNumberFormat()->setFormatCode('#,##0.000');
            }
        }

        // Los encabezados quedan fijos al desplazarse.
        $hoja->freezePane('A5');
        $hoja->setAutoFilter('A4:' . $ultima . ($fila - 1));

        $writer = new Xlsx($libro);
        $nombre = $contenido . '_' . date('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Arma el PDF de un HTML ya listo; todos salen con los mismos ajustes. */
    private function pdf($html, $nombre)
    {
        $pdf = App::make('dompdf.wrapper');
        // Sin subsetting la fuente se embebe entera y cada PDF pesa ~900 KB.
        $pdf->getDomPDF()->getOptions()->setIsFontSubsettingEnabled(true);
        $pdf->setPaper('letter');
        $pdf->loadHTML($html);

        return $pdf->stream($nombre . '.pdf', ['Attachment' => false]);
    }

    /** Importe en letras, con el mismo formato que usa la boleta de entrega. */
    private function enLetras($monto)
    {
        $monto = round((float) $monto, 2);
        $entero = (int) $monto;
        $decimal = (int) round(($monto - $entero) * 100);

        $formatter = new NumeroALetras();

        return ucfirst(strtolower(trim($formatter->toString($entero))))
            . ' ' . sprintf('%02d', $decimal) . '/100';
    }

    /**
     * Escribe el movimiento de inventario en tbstock.
     *
     * El stock de Sofia es SUM(cant - saldo) sobre esa tabla, asi que:
     *   SALIDA    -> cant = 0, saldo = cantidad   (baja el stock)
     *   ANULACION -> cant = cantidad, saldo = 0   (lo devuelve)
     *
     * Anular no borra el movimiento original: escribe el contrario, porque
     * tbstock es un libro de movimientos y borrar filas descuadraria el
     * historico. Se replica el patron con el que graba el sistema de caja.
     */
    private function moverStock(array $lineas, $facturaId, $ci, $ahora, $tipo)
    {
        $esSalida = $tipo === 'SALIDA';

        $posic = (int) DB::selectOne('SELECT COALESCE(MAX(posic), 0) AS n FROM tbstock FOR UPDATE')->n;

        $filas = [];
        foreach ($lineas as $linea) {
            // Lo que se vendio por peso sale del inventario en kilos.
            $movido = isset($linea['peso']) && (float) $linea['peso'] > 0
                ? (float) $linea['peso']
                : (float) $linea['cantidad'];

            $filas[] = [
                'cod_prod'     => $linea['cod_prod'],
                'Cod_Prodm'    => '',
                'Unidcant'     => 0,
                'UnidSaldo'    => 0,
                'cant'         => $esSalida ? 0 : $movido,
                'saldo'        => $esSalida ? $movido : 0,
                'PBruto'       => 0,
                'PreUnit'      => $linea['precio'],
                'CantCja'      => 0,
                'fecha'        => $ahora,
                'fecha_venc'   => self::FECHA_NULA,
                'Nro'          => 0,
                'AlmaOrig'     => 0,
                'CodStock'     => 0,
                'CodStockS'    => 0,
                'CodStockReg'  => 0,
                'ci'           => $ci,
                'MotivoEgreso' => $esSalida ? '' : 'ANULACION VENTA WEB ' . $facturaId,
                'NroLOte'      => '',
                // No es una comanda de caja: el origen va en motivstock.
                'comandast'    => 0,
                'Nrocierre'    => 0,
                'sw'           => 0,
                'codtrans'     => 0,
                'posic'        => ++$posic,
                'motivstock'   => ($esSalida ? 'VENTA WEB ' : 'ANULA VENTA WEB ') . $facturaId,
                'docum'        => '',
                'esfac'        => 0,
                'proveedor'    => '',
            ];
        }

        DB::table('tbstock')->insert($filas);
    }
}
