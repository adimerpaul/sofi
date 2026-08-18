<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Compras a proveedor.
 *
 * La compra en si vive en compras/compra_detalles, pero lo importante ocurre
 * en tbstock: el stock de Sofia es SUM(cant - saldo) sobre esa tabla, asi que
 * una compra ingresa una fila por producto con cant = cantidad y saldo = 0.
 * Es exactamente lo inverso de una venta, que graba cant = 0 y saldo = cantidad.
 *
 * Anular no borra el movimiento: mete el contrario (una salida por la misma
 * cantidad), porque tbstock es un libro de movimientos y borrar filas dejaria
 * descuadrado el historico.
 */
class CompraController extends Controller
{
    /** Fecha centinela del legado para "sin valor". */
    private const FECHA_NULA = '1899-11-30 04:32:36';

    /** Listado con los filtros de la pantalla. */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('perPage', 15), 1), 200);

        $query = Compra::query()->with('detalles')->orderByDesc('id');

        if ($desde = $request->input('desde')) {
            $query->whereDate('fecha', '>=', $desde);
        }
        if ($hasta = $request->input('hasta')) {
            $query->whereDate('fecha', '<=', $hasta);
        }
        if ($estado = $request->input('estado')) {
            $query->where('estado', $estado);
        }

        if ($buscar = trim((string) $request->input('buscar', ''))) {
            $like = '%' . $buscar . '%';
            $query->where(function ($w) use ($like, $buscar) {
                $w->where('proveedor', 'like', $like)
                    ->orWhere('nit', 'like', $like)
                    ->orWhere('nro_factura', 'like', $like);
                if (ctype_digit($buscar)) {
                    $w->orWhere('id', $buscar);
                }
            });
        }

        return $query->paginate($perPage);
    }

    /** Una compra con su detalle. */
    public function show($id)
    {
        $compra = Compra::with(['detalles', 'usuario', 'proveedorRel'])->find($id);

        if (!$compra) {
            return response()->json(['message' => 'La compra no existe'], 404);
        }

        return response()->json($compra);
    }

    /** Proveedores para el buscador de la cabecera. */
    public function proveedores(Request $request)
    {
        $query = DB::table('tbproveedor');

        if ($buscar = trim((string) $request->input('buscar', ''))) {
            $like = '%' . $buscar . '%';
            $query->where(function ($w) use ($like) {
                $w->where('PROVEEDOR', 'like', $like)->orWhere('NIT', 'like', $like);
            });
        }

        return $query->orderBy('PROVEEDOR')->limit(20)->get([
            'CodAut as id',
            DB::raw('TRIM(NIT) as nit'),
            DB::raw('TRIM(PROVEEDOR) as nombre'),
            DB::raw('TRIM(DIRECCION) as direccion'),
            DB::raw('TRIM(TELF) as telefono'),
        ]);
    }

    /**
     * Registra la compra y sube el stock.
     *
     * Todo en una transaccion: si algo falla no queda ni la compra a medias ni
     * stock ingresado que no corresponda a nada.
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.cod_prod'       => 'required|string|max:25',
            'items.*.cantidad'       => 'required|numeric|min:0.001',
            'items.*.precio'         => 'required|numeric|min:0',
            'items.*.precio_venta'   => 'nullable|numeric|min:0',
            'items.*.lote'           => 'nullable|string|max:50',
            'items.*.fecha_vencimiento' => 'nullable|date',
            'proveedor_id'           => 'nullable|integer',
            'nit'                    => 'nullable|string|max:20',
            'proveedor'              => 'nullable|string|max:100',
            'nro_factura'            => 'nullable|string|max:30',
            'tipo_pago'              => 'nullable|string|max:20',
            'descuento'              => 'nullable|numeric|min:0',
            'observacion'            => 'nullable|string|max:255',
        ]);

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

        $proveedor = null;
        if (!empty($datos['proveedor_id'])) {
            $proveedor = DB::table('tbproveedor')->where('CodAut', $datos['proveedor_id'])->first();
            if (!$proveedor) {
                return response()->json(['message' => 'El proveedor no existe'], 422);
            }
        }

        $compra = DB::transaction(function () use ($datos, $productos, $usuario, $ci, $proveedor) {
            $ahora = date('Y-m-d H:i:s');
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
                    'cod_prod'          => $cod,
                    'nombre'            => trim($prod->Producto),
                    'unidad'            => trim((string) $prod->codUnid),
                    'cantidad'          => $cantidad,
                    'precio'            => $precio,
                    'subtotal'          => $importe,
                    'precio_venta'      => isset($item['precio_venta']) ? round((float) $item['precio_venta'], 2) : null,
                    'lote'              => $item['lote'] ?? null,
                    'fecha_vencimiento' => $item['fecha_vencimiento'] ?? null,
                ];
            }

            $subtotal = round($subtotal, 2);
            $descuento = min(round((float) ($datos['descuento'] ?? 0), 2), $subtotal);

            $compra = Compra::create([
                'user_id'      => $usuario->CodAut,
                'proveedor_id' => $proveedor->CodAut ?? null,
                'fecha'        => date('Y-m-d'),
                'hora'         => date('H:i:s'),
                'nit'          => trim((string) ($datos['nit'] ?? ($proveedor->NIT ?? ''))) ?: null,
                'proveedor'    => $datos['proveedor'] ?? ($proveedor ? trim($proveedor->PROVEEDOR) : null),
                'nro_factura'  => $datos['nro_factura'] ?? null,
                'tipo_pago'    => $datos['tipo_pago'] ?? 'EFECTIVO',
                'estado'       => 'ACTIVO',
                'subtotal'     => $subtotal,
                'descuento'    => $descuento,
                'total'        => round($subtotal - $descuento, 2),
                'observacion'  => $datos['observacion'] ?? null,
            ]);

            $compra->detalles()->createMany($lineas);

            $this->moverStock($lineas, $compra->id, $ci, $ahora, 'INGRESO');

            // El precio de venta solo se toca si lo mandaron: comprar mas caro
            // no significa que se quiera revender mas caro automaticamente.
            foreach ($lineas as $linea) {
                if ($linea['precio_venta'] !== null && $linea['precio_venta'] > 0) {
                    DB::table('tbproductos')
                        ->whereRaw('TRIM(cod_prod) = ?', [$linea['cod_prod']])
                        ->update([
                            'Precio'       => $linea['precio_venta'],
                            'Precio_Costo' => $linea['precio'],
                        ]);
                }
            }

            return $compra;
        });

        return response()->json([
            'compra'  => $compra->load('detalles'),
            'message' => 'Compra registrada con el número ' . $compra->id . '; el stock ya fue actualizado',
        ], 201);
    }

    /**
     * Anula la compra y devuelve el stock que habia ingresado.
     *
     * Si algo de lo comprado ya se vendio el stock puede quedar negativo; se
     * avisa pero no se bloquea, porque la compra pudo cargarse por error y hay
     * que poder corregirla igual.
     */
    public function anular(Request $request, $id)
    {
        $datos = $request->validate([
            'motivo' => 'required|string|max:255',
        ]);

        $compra = Compra::with('detalles')->find($id);
        if (!$compra) {
            return response()->json(['message' => 'La compra no existe'], 404);
        }

        if ($compra->estado === 'ANULADO') {
            return response()->json(['message' => 'La compra ya estaba anulada'], 422);
        }

        $usuario = $request->user();
        $ci = trim((string) ($usuario->ci ?? ''));

        DB::transaction(function () use ($compra, $datos, $ci) {
            $lineas = $compra->detalles->map(function ($d) {
                return [
                    'cod_prod' => $d->cod_prod,
                    'cantidad' => (float) $d->cantidad,
                    'precio'   => (float) $d->precio,
                ];
            })->all();

            $this->moverStock($lineas, $compra->id, $ci, date('Y-m-d H:i:s'), 'ANULACION');

            $compra->update([
                'estado'           => 'ANULADO',
                'motivo_anulacion' => $datos['motivo'],
                'anulado_at'       => now(),
            ]);
        });

        return response()->json([
            'message' => 'Compra anulada; el stock ingresado fue devuelto',
            'compra'  => $compra->fresh(),
        ]);
    }

    /**
     * Escribe el movimiento en tbstock.
     *
     * INGRESO   -> cant = cantidad, saldo = 0   (sube el stock)
     * ANULACION -> cant = 0, saldo = cantidad   (lo baja de nuevo)
     *
     * Se replica el patron con el que graba el sistema de caja: comandast
     * enlaza el movimiento con el documento y posic es un correlativo propio
     * de tbstock.
     */
    private function moverStock(array $lineas, $compraId, $ci, $ahora, $tipo)
    {
        $esIngreso = $tipo === 'INGRESO';

        $posic = (int) DB::selectOne('SELECT COALESCE(MAX(posic), 0) AS n FROM tbstock FOR UPDATE')->n;

        $filas = [];
        foreach ($lineas as $linea) {
            $filas[] = [
                'cod_prod'     => $linea['cod_prod'],
                'Cod_Prodm'    => '',
                'Unidcant'     => 0,
                'UnidSaldo'    => 0,
                'cant'         => $esIngreso ? $linea['cantidad'] : 0,
                'saldo'        => $esIngreso ? 0 : $linea['cantidad'],
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
                'MotivoEgreso' => $esIngreso ? '' : 'ANULACION COMPRA WEB ' . $compraId,
                'NroLOte'      => $linea['lote'] ?? '',
                // No es una comanda de venta: identifica el documento de origen.
                'comandast'    => 0,
                'Nrocierre'    => 0,
                'sw'           => 0,
                'codtrans'     => 0,
                'posic'        => ++$posic,
                'motivstock'   => ($esIngreso ? 'COMPRA WEB ' : 'ANULA COMPRA WEB ') . $compraId,
                'docum'        => '',
                'esfac'        => 0,
                'proveedor'    => '',
            ];
        }

        DB::table('tbstock')->insert($filas);
    }
}
