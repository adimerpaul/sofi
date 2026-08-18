<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
    /** Listado de lo emitido, con filtros de la pantalla. */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('perPage', 20), 1), 200);

        $query = Factura::query()
            ->with(['detalles', 'cliente:Cod_Aut,Id,Nombres,zona'])
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

        // Si mandan cliente, se copian sus datos y su vendedor: la factura no
        // debe cambiar si manana editan la ficha del cliente.
        $cliente = null;
        if (!empty($datos['cliente_id'])) {
            $cliente = DB::table('tbclientes')->where('Cod_Aut', $datos['cliente_id'])->first();
            if (!$cliente) {
                return response()->json(['message' => 'El cliente no existe'], 422);
            }
        }

        $factura = DB::transaction(function () use ($datos, $productos, $usuario, $cliente, $tipo, $nit) {
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
            ]);

            $factura->detalles()->createMany($lineas);

            return $factura;
        });

        return response()->json([
            'factura' => $factura->load('detalles'),
            'message' => ($tipo === 'FACTURA' ? 'Factura' : 'Venta') . ' registrada con el número ' . $factura->id,
        ], 201);
    }

    /**
     * Anula sin borrar: la factura sigue existiendo pero deja de sumar.
     * Se guarda el motivo porque una anulacion sin razon no sirve de nada.
     */
    public function anular(Request $request, $id)
    {
        $datos = $request->validate([
            'motivo' => 'required|string|max:255',
        ]);

        $factura = Factura::find($id);
        if (!$factura) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }

        if ($factura->estado === 'ANULADO') {
            return response()->json(['message' => 'La factura ya estaba anulada'], 422);
        }

        $factura->update([
            'estado'           => 'ANULADO',
            'motivo_anulacion' => $datos['motivo'],
            'anulado_at'       => now(),
        ]);

        return response()->json(['message' => 'Factura anulada', 'factura' => $factura]);
    }
}
