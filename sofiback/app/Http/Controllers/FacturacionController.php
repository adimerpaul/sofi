<?php

namespace App\Http\Controllers;

use App\Models\Factura;
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
            ]);

            $factura->detalles()->createMany($lineas);

            // Lo vendido sale del inventario.
            $this->moverStock($lineas, $factura->id, $ci, date('Y-m-d H:i:s'), 'SALIDA');

            return $factura;
        });

        return response()->json([
            'factura' => $factura->load('detalles'),
            'message' => ($tipo === 'FACTURA' ? 'Factura' : 'Venta')
                . ' registrada con el número ' . $factura->id . '; el stock ya fue descontado',
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

        $factura = Factura::with('detalles')->find($id);
        if (!$factura) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }

        if ($factura->estado === 'ANULADO') {
            return response()->json(['message' => 'La factura ya estaba anulada'], 422);
        }

        $ci = trim((string) ($request->user()->ci ?? ''));

        DB::transaction(function () use ($factura, $datos, $ci) {
            $lineas = $factura->detalles->map(function ($d) {
                return ['cod_prod' => $d->cod_prod, 'cantidad' => (float) $d->cantidad, 'precio' => (float) $d->precio];
            })->all();

            // Lo que no se vendio vuelve al inventario.
            $this->moverStock($lineas, $factura->id, $ci, date('Y-m-d H:i:s'), 'ANULACION');

            $factura->update([
                'estado'           => 'ANULADO',
                'motivo_anulacion' => $datos['motivo'],
                'anulado_at'       => now(),
            ]);
        });

        return response()->json([
            'message' => 'Anulada; el stock descontado fue devuelto',
            'factura' => $factura->fresh(),
        ]);
    }

    /**
     * Voucher: el comprobante que se entrega siempre, en formato ticket.
     */
    public function voucher($id)
    {
        $factura = Factura::with(['detalles', 'cliente'])->find($id);
        if (!$factura) {
            return response()->json(['message' => 'La venta no existe'], 404);
        }

        $emisor = config('siat.emisor');

        $filas = '';
        foreach ($factura->detalles as $d) {
            $cant = number_format($d->cantidad, $d->unidad === 'KG' ? 3 : 0);
            $filas .= "<tr>
                <td colspan='3' class='prod'>" . e($d->nombre) . "</td>
            </tr><tr>
                <td>$cant x " . number_format($d->precio, 2) . "</td>
                <td class='c'>" . e($d->unidad) . "</td>
                <td class='r'>" . number_format($d->subtotal, 2) . "</td>
            </tr>";
        }

        $descuento = (float) $factura->descuento > 0
            ? "<tr><td colspan='2'>Descuento</td><td class='r'>-" . number_format($factura->descuento, 2) . "</td></tr>"
            : '';

        $html = "<style>
            @page { margin: 4mm; }
            * { font-family: sans-serif; font-size: 9px; }
            .c { text-align: center } .r { text-align: right }
            .tit { font-size: 12px; font-weight: bold }
            .prod { font-weight: bold; padding-top: 3px }
            table { width: 100%; border-collapse: collapse }
            .linea { border-top: 1px dashed #000; margin: 4px 0 }
            .tot { font-size: 13px; font-weight: bold }
        </style>
        <div class='c'>
            <div class='tit'>" . e($emisor['nombre']) . "</div>
            <div>" . e($emisor['sucursal']) . "</div>
            <div>NIT " . e(config('siat.nit')) . "</div>
            <div>" . e($emisor['direccion']) . "</div>
            <div>Telf. " . e($emisor['telefono']) . " &middot; " . e($emisor['ciudad']) . "</div>
        </div>
        <div class='linea'></div>
        <div class='c tit'>COMPROBANTE DE VENTA</div>
        <div class='c'>Nro " . $factura->id . "</div>
        <div class='linea'></div>
        <div>Fecha: " . $factura->fecha->format('d/m/Y') . " {$factura->hora}</div>
        <div>Cliente: " . e($factura->nombre ?: 'Sin cliente') . "</div>
        <div>NIT/CI: " . e($factura->nit ?: '-') . "</div>
        <div>Pago: " . e($factura->tipo_pago) . "</div>
        <div class='linea'></div>
        <table>$filas</table>
        <div class='linea'></div>
        <table>
            <tr><td colspan='2'>Subtotal</td><td class='r'>" . number_format($factura->subtotal, 2) . "</td></tr>
            $descuento
            <tr class='tot'><td colspan='2'>TOTAL Bs</td><td class='r'>" . number_format($factura->total, 2) . "</td></tr>
        </table>
        <div class='linea'></div>
        <div>Son: " . e($this->enLetras($factura->total)) . " Bolivianos</div>
        <div class='linea'></div>
        <div class='c'>¡Gracias por su compra!</div>";

        if ($factura->estado === 'ANULADO') {
            $html .= "<div class='c tit' style='margin-top:6px'>*** ANULADO ***</div>";
        }

        $pdf = App::make('dompdf.wrapper');
        // Ticket de 80mm; el alto se estira segun la cantidad de lineas.
        $pdf->setPaper([0, 0, 226.77, 400 + count($factura->detalles) * 26]);
        $pdf->loadHTML($html);

        return $pdf->stream('voucher_' . $factura->id . '.pdf', ['Attachment' => false]);
    }

    /**
     * Factura en formato carta.
     *
     * Mientras no se emita al SIAT desde aca la venta no tiene CUF ni numero
     * de autorizacion, asi que el documento sale rotulado como sin valor
     * fiscal. Hacerlo pasar por una factura fiscal sin serlo seria un problema
     * para el cliente y para el negocio.
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

        $emisor = config('siat.emisor');

        $filas = '';
        foreach ($factura->detalles as $d) {
            $filas .= '<tr>'
                . '<td>' . e($d->cod_prod) . '</td>'
                . "<td class='r'>" . number_format($d->cantidad, $d->unidad === 'KG' ? 3 : 0) . '</td>'
                . "<td class='c'>" . e($d->unidad) . '</td>'
                . '<td>' . e($d->nombre) . '</td>'
                . "<td class='r'>" . number_format($d->precio, 2) . '</td>'
                . "<td class='r'>" . number_format($d->subtotal, 2) . '</td>'
                . '</tr>';
        }

        $sinCuf = $factura->cuf
            ? ''
            : "<div class='aviso'>DOCUMENTO SIN VALOR FISCAL &middot; no fue emitido a Impuestos Nacionales</div>";

        $anulado = $factura->estado === 'ANULADO'
            ? "<div class='aviso'>FACTURA ANULADA: " . e($factura->motivo_anulacion) . '</div>'
            : '';

        $html = "<style>
            * { font-family: sans-serif; font-size: 11px }
            .c { text-align: center } .r { text-align: right }
            .cab { font-size: 15px; font-weight: bold }
            .tipo { text-align: center; font-size: 15px; font-weight: bold; color: #1565c0; margin: 6px 0 }
            table.d { width: 100%; border-collapse: collapse; margin-top: 6px }
            table.d th { background: #eee; border: 1px solid #999; padding: 4px; text-align: left }
            table.d td { border: 1px solid #ddd; padding: 4px }
            .datos td { padding: 2px 4px }
            .aviso { border: 2px solid #c62828; color: #c62828; font-weight: bold;
                     text-align: center; padding: 5px; margin: 8px 0 }
            .tot { font-size: 14px; font-weight: bold }
        </style>
        <table style='width:100%'><tr>
            <td class='c' style='width:35%'>
                <div class='cab'>" . e($emisor['nombre']) . "</div>
                <div>" . e($emisor['sucursal']) . "</div>
                <div>" . e($emisor['direccion']) . "</div>
                <div>Telf. " . e($emisor['telefono']) . " &middot; " . e($emisor['ciudad']) . "</div>
            </td>
            <td class='c'>
                <table class='datos' style='width:100%'>
                    <tr><td><b>NIT</b></td><td>" . e(config('siat.nit')) . "</td></tr>
                    <tr><td><b>Nro</b></td><td>" . ($factura->nro_factura ?: $factura->id) . "</td></tr>
                    <tr><td><b>Cod. Autorizacion</b></td><td>" . e($factura->cuf ?: '—') . "</td></tr>
                </table>
            </td>
        </tr></table>

        <div class='tipo'>FACTURA</div>
        $sinCuf
        $anulado

        <table class='datos' style='width:100%'>
            <tr>
                <td><b>FECHA:</b></td><td>" . $factura->fecha->format('d/m/Y') . " {$factura->hora}</td>
                <td><b>NIT/CI:</b></td><td>" . e($factura->nit ?: '-') . "</td>
            </tr>
            <tr>
                <td><b>Nombre/Razon Social:</b></td><td colspan='3'>" . e($factura->nombre ?: 'Sin cliente') . "</td>
            </tr>
        </table>

        <table class='d'>
            <tr>
                <th>Codigo</th><th>Cantidad</th><th>Unidad</th>
                <th>Descripcion</th><th>P. Unitario</th><th>Subtotal</th>
            </tr>
            $filas
        </table>

        <table style='width:100%; margin-top:8px'><tr>
            <td style='vertical-align:top'><b>Son:</b> " . e($this->enLetras($factura->total)) . " Bolivianos</td>
            <td style='width:40%'>
                <table class='datos' style='width:100%'>
                    <tr><td>SUBTOTAL Bs.</td><td class='r'>" . number_format($factura->subtotal, 2) . "</td></tr>
                    <tr><td>DESCUENTO Bs.</td><td class='r'>" . number_format($factura->descuento, 2) . "</td></tr>
                    <tr class='tot'><td>TOTAL Bs.</td><td class='r'>" . number_format($factura->total, 2) . "</td></tr>
                </table>
            </td>
        </tr></table>";

        $pdf = App::make('dompdf.wrapper');
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
