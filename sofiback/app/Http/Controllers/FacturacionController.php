<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use App\Services\SiatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Luecano\NumeroALetras\NumeroALetras;

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

    /** Listado de lo emitido, con filtros de la pantalla. */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('perPage', 20), 1), 200);

        $query = Factura::query()
            ->with([
                'detalles',
                'cliente:Cod_Aut,Id,Nombres,zona',
                'vendedor:CodAut,ci,Nombre1,Nombre2,App1,Apm',
            ])
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

        if ($buscar = trim((string) $request->input('buscar', ''))) {
            $like = '%' . $buscar . '%';
            $query->where(function ($w) use ($like, $buscar) {
                $w->where('nombre', 'like', $like)
                    ->orWhere('nit', 'like', $like);
                if (ctype_digit($buscar)) {
                    $w->orWhere('id', $buscar)->orWhere('nro_factura', $buscar);
                }
            });
        }

        return $query->paginate($perPage);
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
            ->leftJoin('facturas as f', function ($join) {
                $join->on('f.pedido_nro', '=', 'p.NroPed')
                    ->on(DB::raw('UPPER(TRIM(f.pedido_tipo))'), '=', DB::raw('UPPER(TRIM(p.tipo))'))
                    ->whereNull('f.deleted_at');
            })
            ->whereDate('p.fecha', $datos['fecha'])
            ->whereRaw('UPPER(TRIM(p.tipo)) = ?', [$datos['tipo']])
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
            ->groupBy([
                'p.NroPed', 'p.tipo', 'p.fecha', 'p.idCli', 'p.CIfunc', 'p.estado',
                'p.fact', 'p.pago', 'p.comentario', 'c.Id', 'c.Nombres',
                'v.Nombre1', 'v.Nombre2', 'v.App1', 'v.Apm', 'f.id', 'f.tipo_comprobante',
            ])
            ->orderByRaw('CASE WHEN f.id IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc('p.NroPed')
            ->get([
                'p.NroPed as nro_pedido',
                DB::raw('UPPER(TRIM(p.tipo)) as tipo'),
                'p.fecha', 'p.estado', 'p.fact', 'p.pago', 'p.comentario',
                'p.idCli as cliente_id',
                DB::raw('TRIM(c.Id) as nit'),
                DB::raw('TRIM(c.Nombres) as cliente'),
                DB::raw("TRIM(CONCAT_WS(' ', NULLIF(TRIM(v.Nombre1), ''), NULLIF(TRIM(v.Nombre2), ''), NULLIF(TRIM(v.App1), ''), NULLIF(TRIM(v.Apm), ''))) as vendedor"),
                DB::raw('COUNT(*) as productos'),
                DB::raw('ROUND(SUM(COALESCE(p.Cant, 0) * COALESCE(p.precio, 0)), 2) as total_pedido'),
                'f.id as factura_id', 'f.tipo_comprobante as comprobante_emitido',
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
            ->whereRaw('UPPER(TRIM(tipo)) = ?', [$datos['tipo']])->where('bonificacion', 0)
            ->orderBy('codAut')->get()->groupBy('NroPed');

        return $pedidos->map(function ($pedido) use ($items, $filasPedido) {
            $pedido->items = ($items->get($pedido->nro_pedido) ?? collect())
                ->map(function ($item) {
                    $item->cantidad = (float) $item->cantidad;
                    $item->precio = (float) $item->precio;
                    $item->total = round($item->cantidad * $item->precio, 2);
                    return $item;
                })->values();
            $pedido->detalle_pollo = $this->detallePollo($filasPedido->get($pedido->nro_pedido) ?? collect());
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
                'p.idCli as cliente_id', DB::raw('TRIM(c.Id) as nit'),
                DB::raw('TRIM(c.Nombres) as cliente'), DB::raw('TRIM(c.Direccion) as direccion'),
                DB::raw('TRIM(c.zona) as zona'), DB::raw('TRIM(c.CiVend) as vendedor_ci'),
                DB::raw("TRIM(CONCAT_WS(' ', NULLIF(TRIM(v.Nombre1), ''), NULLIF(TRIM(v.Nombre2), ''), NULLIF(TRIM(v.App1), ''), NULLIF(TRIM(v.Apm), ''))) as vendedor"),
            ]);

        if (!$cabecera) {
            return response()->json(['message' => 'El pedido no existe para el tipo seleccionado'], 404);
        }

        $yaFacturado = Factura::where('pedido_nro', $nroPedido)
            ->where('pedido_tipo', $datos['tipo'])
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
                $item->precio = (float) $item->precio;
                $item->total = round($item->cantidad * $item->precio, 2);
                return $item;
            });

        $filasPedido = DB::table('tbpedidos')->where('NroPed', $nroPedido)
            ->whereRaw('UPPER(TRIM(tipo)) = ?', [$datos['tipo']])->where('bonificacion', 0)
            ->orderBy('codAut')->get();
        $cabecera->detalle_pollo = $this->detallePollo($filasPedido);

        return response()->json(['pedido' => $cabecera, 'items' => $items]);
    }

    private function detallePollo($filas)
    {
        $detalles = collect();
        $observaciones = collect();
        $productos = [
            ['Brasa 5', 'cbrasa5', 'ubrasa5', 'bsbrasa5', 'obsbrasa5'], ['Brasa 6', 'cbrasa6', 'cubrasa6', 'bsbrasa6', 'obsbrasa6'],
            ['Pollo 104', 'c104', 'u104', 'bs104', 'obs104'], ['Pollo 105', 'c105', 'u105', 'bs105', 'obs105'],
            ['Pollo 106', 'c106', 'u106', 'bs106', 'obs106'], ['Pollo 107', 'c107', 'u107', 'bs107', 'obs107'],
            ['Pollo 108', 'c108', 'u108', 'bs108', 'obs108'], ['Pollo 109', 'c109', 'u109', 'bs109', 'obs109'],
        ];
        $cortes = [
            ['Ala', 'ala', 'unidala', 'bsala', 'obsala'], ['Cadera', 'cadera', 'unidcadera', 'bscadera', 'obscadera'],
            ['Pecho', 'pecho', 'unidpecho', 'bspecho', 'obspecho'], ['Pie', 'pie', 'unidpie', 'bspie', 'obspie'],
            ['Filete', 'filete', 'unidfilete', 'bsfilete', 'obsfilete'], ['Cuello', 'cuello', 'unidcuello', 'bscuello', 'obscuello'],
            ['Hueso', 'hueso', 'unidhueso', 'bshueso', 'obshueso'], ['Menudencia', 'menu', 'unidmenu', 'bsmenu', 'obsmenu'],
        ];
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
                ->exists();
            if (!$existePedido) {
                return response()->json(['message' => 'El pedido de origen no existe'], 422);
            }
            if (Factura::where('pedido_nro', $datos['pedido_nro'])->where('pedido_tipo', $datos['pedido_tipo'])->exists()) {
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
                $importe = round($cantidad * $precio, 2);
                $subtotal += $importe;

                $lineas[] = [
                    'cod_prod' => $cod,
                    'nombre'   => trim($prod->Producto),
                    'unidad'   => trim((string) $prod->codUnid),
                    'cantidad' => $cantidad,
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

        // Emision al SIAT. Va fuera de la transaccion a proposito: la venta ya
        // se cobro y el stock ya salio, asi que un problema con Impuestos no
        // debe deshacer nada. Si falla queda con estado_siat = ERROR y se
        // puede reintentar desde la pantalla de Impuestos.
        $factura = (new SiatService())->emitirFactura($factura, $usuario->CodAut);

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

    /** Que decirle al cajero segun como haya salido la emision. */
    private function mensajeEmision(Factura $factura)
    {
        $base = 'Factura ' . ($factura->nro_factura ?: $factura->id) . ' registrada; el stock ya fue descontado';

        if ($factura->estado_siat === 'ERROR') {
            return $base . '. NO se pudo enviar a Impuestos: ' . $factura->mensaje_siat
                . '. Queda sin valor fiscal hasta reenviarla desde Impuestos';
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
        $respuestaSiat = null;
        if ($factura->tipo_comprobante === 'FACTURA' && $factura->cuf) {
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
                return ['cod_prod' => $d->cod_prod, 'cantidad' => (float) $d->cantidad, 'precio' => (float) $d->precio];
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
            .pie { position: fixed; bottom: -14mm; left: 0; right: 0 }
            .legal { font-size: 7.5px; color: #888; text-align: center; line-height: 1.5 }
        ";
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

        $cliente = $factura->cliente;

        $vendedor = $factura->vendedor
            ? trim(implode(' ', array_filter([
                trim($factura->vendedor->Nombre1),
                trim($factura->vendedor->App1),
                trim($factura->vendedor->Apm),
            ])))
            : '';

        $filas = '';
        foreach ($factura->detalles as $i => $d) {
            // Como en la boleta de papel: lo que va a granel lleva CANT en 0 y
            // la cantidad real aparece en P. NETO.
            $porUnidad = trim((string) $d->unidad) !== 'KG';
            $par = $i % 2 ? " class='par'" : '';

            $filas .= "<tr$par>"
                . "<td class='r'>" . number_format($porUnidad ? $d->cantidad : 0, 2) . '</td>'
                . "<td class='cod'>" . e($d->cod_prod) . '</td>'
                . '<td>' . e($d->nombre) . '</td>'
                . "<td class='c'>" . e($d->unidad) . '</td>'
                . "<td class='r'>" . number_format($d->cantidad, 2) . '</td>'
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
                <td><span class='et'>Observación</span><br>" . e($factura->observacion ?: '—') . "</td>
            </tr>
        </table>

        <table class='detalle'>
            <tr>
                <th style='width:8%'>Cant</th>
                <th style='width:11%'>Código</th>
                <th>Concepto</th>
                <th style='width:8%'>Unid</th>
                <th style='width:10%'>P. Neto</th>
                <th style='width:12%'>P. Unit</th>
                <th style='width:13%'>Total</th>
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
            <div class='legal'>
                Respalde su cancelación del presente con la boleta original.<br>
                " . e(config('siat.emisor')['nombre']) . " &middot; documento generado el "
                . date('d/m/Y H:i') . "
            </div>
        </div>";

        $pdf = App::make("dompdf.wrapper");
        // Sin subsetting la fuente se embebe entera y cada PDF pesa ~900 KB.
        $pdf->getDomPDF()->getOptions()->setIsFontSubsettingEnabled(true);
        $pdf->setPaper("letter");
        $pdf->loadHTML($html);

        return $pdf->stream('voucher_' . $factura->id . '.pdf', ['Attachment' => false]);
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

        $filas = '';
        foreach ($factura->detalles as $i => $d) {
            $par = $i % 2 ? " class='par'" : '';

            $filas .= "<tr$par>"
                . "<td class='cod'>" . e($d->cod_prod) . '</td>'
                . "<td class='r'>" . number_format($d->cantidad, 2) . '</td>'
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
        </table>

        <table class='detalle'>
            <tr>
                <th style='width:10%'>Código</th>
                <th style='width:8%'>Cantidad</th>
                <th style='width:13%'>Unidad</th>
                <th>Descripción</th>
                <th style='width:10%'>P. Unitario</th>
                <th style='width:9%'>Descuento</th>
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
        </tr></table>";

        $pdf = App::make("dompdf.wrapper");
        // Sin subsetting la fuente se embebe entera y cada PDF pesa ~900 KB.
        $pdf->getDomPDF()->getOptions()->setIsFontSubsettingEnabled(true);
        $pdf->setPaper("letter");
        $pdf->loadHTML($html);

        return $pdf->stream('factura_' . $factura->id . '.pdf', ['Attachment' => false]);
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
            $filas[] = [
                'cod_prod'     => $linea['cod_prod'],
                'Cod_Prodm'    => '',
                'Unidcant'     => 0,
                'UnidSaldo'    => 0,
                'cant'         => $esSalida ? 0 : $linea['cantidad'],
                'saldo'        => $esSalida ? $linea['cantidad'] : 0,
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
