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

        $emisor = config('siat.emisor');
        $cliente = $factura->cliente;

        $vendedor = $factura->vendedor
            ? trim(implode(' ', array_filter([
                trim($factura->vendedor->Nombre1),
                trim($factura->vendedor->App1),
                trim($factura->vendedor->Apm),
            ])))
            : '';

        $filas = '';
        foreach ($factura->detalles as $d) {
            $porUnidad = trim((string) $d->unidad) !== 'KG';

            $filas .= '<tr>'
                . "<td class='r'>" . number_format($porUnidad ? $d->cantidad : 0, 2) . '</td>'
                . '<td>' . e($d->cod_prod) . '</td>'
                . '<td>' . e(mb_substr($d->nombre, 0, 45)) . '</td>'
                . "<td class='c'>" . e($d->unidad) . '</td>'
                . "<td class='r'>" . number_format($d->cantidad, 2) . '</td>'
                . "<td class='r'>" . number_format($d->precio, 3) . '</td>'
                . "<td class='r'>" . number_format($d->subtotal, 2) . '</td>'
                . '</tr>';
        }

        $anulado = $factura->estado === 'ANULADO'
            ? "<div class='anulado'>ANULADO: " . e($factura->motivo_anulacion) . '</div>'
            : '';

        $html = "<style>
            @page { margin: 10mm }
            * { font-family: sans-serif; font-size: 10px }
            .c { text-align: center } .r { text-align: right }
            .titulo { font-size: 15px; font-weight: bold; letter-spacing: 2px }
            .cab { width: 100%; border-collapse: collapse; margin-bottom: 4px }
            .cab td { border: 1px solid #333; padding: 2px 4px }
            table.d { width: 100%; border-collapse: collapse }
            table.d th { border: 1px solid #333; padding: 3px; background: #f0f0f0 }
            table.d td { border: 1px solid #333; padding: 2px 3px }
            .pie { width: 100%; margin-top: 6px }
            .tot td { padding: 2px 4px; border: 1px solid #333 }
            .firma { margin-top: 40px; width: 100% }
            .firma td { padding-top: 18px }
            .linea { border-bottom: 1px solid #333; width: 220px; display: inline-block }
            .anulado { border: 2px solid #c62828; color: #c62828; font-weight: bold;
                       text-align: center; padding: 4px; margin: 6px 0 }
            .legal { font-size: 8px; margin-top: 10px; text-align: center }
        </style>

        <table style='width:100%'><tr>
            <td class='titulo'>" . e($emisor['nombre']) . "</td>
            <td class='titulo c'>BOLETA DE ENTREGA</td>
            <td class='titulo r'>Nro " . $factura->id . "</td>
        </tr></table>

        <table class='cab'>
            <tr>
                <td><b>CI/NIT:</b> " . e($factura->nit ?: '-') . "</td>
                <td><b>TELF.:</b> " . e($cliente->Telf ?? '') . "</td>
                <td><b>F. Emision:</b> " . $factura->fecha->format('d/m/Y') . "</td>
            </tr>
            <tr>
                <td colspan='2'><b>Cliente:</b> " . e($factura->nombre ?: 'Sin cliente') . "</td>
                <td><b>Zona:</b> " . e($cliente->zona ?? '') . "</td>
            </tr>
            <tr>
                <td colspan='2'><b>Direccion:</b> " . e($cliente->Direccion ?? '') . "</td>
                <td><b>Hora:</b> " . e($factura->hora) . "</td>
            </tr>
            <tr>
                <td colspan='2'><b>Vendedor:</b> " . e($vendedor) . "</td>
                <td><b>Territorio:</b> " . e($cliente->territorio ?? '') . "</td>
            </tr>
        </table>

        $anulado

        <table class='d'>
            <tr>
                <th>CANT</th><th>CODIGO</th><th>CONCEPTO</th>
                <th>UNID</th><th>P. NETO</th><th>P. UNIT</th><th>TOTAL</th>
            </tr>
            $filas
        </table>

        <table class='pie'><tr>
            <td style='vertical-align:top'>
                <div><b>LITERAL:</b> " . e($this->enLetras($factura->total)) . "</div>
                <div><b>TIPO DE PAGO:</b> " . e($factura->tipo_pago) . "</div>
                <div><b>OBS.:</b> " . e($factura->observacion ?: '') . "</div>
            </td>
            <td style='width:38%'>
                <table class='tot' style='width:100%'>
                    <tr><td>SUB. TOT Bs.</td><td class='r'>" . number_format($factura->subtotal, 2) . "</td></tr>
                    <tr><td>DESCT. Bs.</td><td class='r'>" . number_format($factura->descuento, 2) . "</td></tr>
                    <tr><td><b>TOTAL Bs.</b></td><td class='r'><b>" . number_format($factura->total, 2) . "</b></td></tr>
                </table>
            </td>
        </tr></table>

        <table class='firma'>
            <tr><td>CI: <span class='linea'></span></td></tr>
            <tr><td>Nombre: <span class='linea'></span></td></tr>
            <tr><td>Firma: <span class='linea'></span></td></tr>
        </table>

        <div class='legal'>RESPALDE SU CANCELACION DEL PRESENTE CON LA BOLETA ORIGINAL</div>";

        $pdf = App::make('dompdf.wrapper');
        $pdf->setPaper('letter');
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

        $emisor = config('siat.emisor');
        $logo = is_file(public_path('img/sofia.png'))
            ? base64_encode(file_get_contents(public_path('img/sofia.png')))
            : '';

        $filas = '';
        foreach ($factura->detalles as $d) {
            $filas .= '<tr>'
                . "<td class='r'>" . e($d->cod_prod) . '</td>'
                . "<td class='r'>" . number_format($d->cantidad, 2) . '</td>'
                . '<td>' . e($d->unidad === 'KG' ? 'KILOGRAMO' : 'UNIDAD (SERVICIOS)') . '</td>'
                . '<td>' . e($d->nombre) . '</td>'
                . "<td class='r'>" . number_format($d->precio, 2) . '</td>'
                . "<td class='r'>0.00</td>"
                . "<td class='r'>" . number_format($d->subtotal, 2) . '</td>'
                . '</tr>';
        }

        $cuf = $factura->cuf ?: '';
        $sinCuf = $cuf === ''
            ? "<div class='aviso'>DOCUMENTO SIN VALOR FISCAL &middot; no fue emitido a Impuestos Nacionales</div>"
            : '';

        $anulado = $factura->estado === 'ANULADO'
            ? "<div class='aviso'>FACTURA ANULADA: " . e($factura->motivo_anulacion) . '</div>'
            : '';

        $html = "<style>
            @page { margin: 12mm }
            * { font-family: sans-serif; font-size: 11px }
            .c { text-align: center } .r { text-align: right }
            .imagen { width: 130px }
            .area td { padding: 1px 3px }
            .titulo1 { text-align: center; font-weight: bold }
            table.detalle { width: 100%; border-collapse: collapse; margin-top: 6px }
            table.detalle th { padding: 4px; border: 1px solid #333; background: #f0f0f0; font-size: 10px }
            .detalle2 { border: 1px solid #999; padding: 3px; font-size: 10px }
            .aviso { border: 2px solid #c62828; color: #c62828; font-weight: bold;
                     text-align: center; padding: 5px; margin: 6px 0 }
        </style>

        <table style='width:100%'>
        <tr>
            <td class='c' style='width:38%'>"
                . ($logo ? "<img class='imagen' src='data:image/png;base64,$logo'>" : '')
            . "</td>
            <td>
                <table class='area'>
                    <tr><td><b>NIT:</b></td><td>" . e(config('siat.nit')) . "</td></tr>
                    <tr><td><b>FACTURA No:</b></td><td>" . ($factura->nro_factura ?: $factura->id) . "</td></tr>
                    <tr><td style='vertical-align:top'><b>COD. AUTORIZACION:</b></td>
                        <td>" . ($cuf !== '' ? e(chunk_split($cuf, 23, '<br>')) : '—') . "</td></tr>
                </table>
            </td>
        </tr>
        <tr class='titulo1'>
            <td class='area'>" . e($emisor['nombre']) . '<br>' . e($emisor['sucursal']) . "<br>
                PUNTO DE VENTA " . (int) config('siat.codigo_punto_venta') . '<br>'
                . e($emisor['direccion']) . '<br>Telefono : ' . e($emisor['telefono']) . '<br>'
                . e($emisor['ciudad']) . "</td>
            <td>" . $factura->id . "</td>
        </tr>
        </table>

        <div class='titulo1' style='margin:6px 0'>
            <span style='color:blue; font-size:16px'>FACTURA</span><br>
            <span>(Con derecho a crédito fiscal)</span>
        </div>

        $sinCuf
        $anulado

        <table class='area' style='width:100%'>
            <tr>
                <td><b>FECHA:</b></td><td>" . $factura->fecha->format('Y-m-d') . " {$factura->hora}</td>
                <td><b>NIT/CI/CEX:</b></td><td>" . e($factura->nit ?: '-') . "</td>
                <td><b>Compl:</b></td><td>" . e($factura->cliente->complto ?? '') . "</td>
            </tr>
            <tr>
                <td><b>Nombres/Razon Social:</b></td><td>" . e($factura->nombre ?: 'Sin cliente') . "</td>
                <td><b>Cod Cliente:</b></td><td>" . ($factura->cliente_id ?: '') . "</td>
                <td></td><td></td>
            </tr>
        </table>

        <table class='detalle'>
            <tr>
                <th>Código Producto Servicio</th><th>Cantidad</th><th>Unidad de Medida</th>
                <th>Descripcion</th><th>Precio unitario</th><th>Descuento</th><th>Importe</th>
            </tr>
            $filas
        </table>

        <table style='width:100%; margin-top:6px'><tr>
            <td style='vertical-align:top'><b>Son:</b> "
                . e(mb_strtoupper($this->enLetras($factura->total))) . " Bolivianos</td>
            <td style='width:42%'>
                <table style='width:100%'>
                    <tr><td class='detalle2'>SUBTOTAL Bs.</td>
                        <td class='detalle2 r' style='color:blue; font-weight:bold'>"
                        . number_format($factura->subtotal, 2) . "</td></tr>
                    <tr><td class='detalle2'>DESCUENTO Bs.</td>
                        <td class='detalle2 r'>" . number_format($factura->descuento, 2) . "</td></tr>
                    <tr><td class='detalle2'>TOTAL Bs.</td>
                        <td class='detalle2 r'>" . number_format($factura->total, 2) . "</td></tr>
                    <tr><td class='detalle2'><b>MONTO A PAGAR Bs.</b></td>
                        <td class='detalle2 r'><b>" . number_format($factura->total, 2) . "</b></td></tr>
                </table>
            </td>
        </tr></table>

        <table style='width:100%; margin-top:10px'>
            <tr><td class='c'>&quot;ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS,
                EL USO ILÍCITO SERÁ SANCIONADO PENALMENTE DE ACUERDO A LEY&quot;.</td></tr>
            <tr><td class='c'>Ley N° 453: El proveedor debe brindar atención sin discriminación,
                con respeto, calidez y cordialidad a los usuarios y consumidores.</td></tr>
        </table>";

        $pdf = App::make('dompdf.wrapper');
        $pdf->setPaper('letter');
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
