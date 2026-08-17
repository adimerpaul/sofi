<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Luecano\NumeroALetras\NumeroALetras;

/**
 * Consulta de ventas sobre tbventas, que es lo que realmente se cobro.
 *
 * tbventas no es maestro-detalle: es una tabla plana con una fila por producto
 * vendido. La venta es el grupo de filas que comparten Comanda, asi que aca
 * todo se agrupa por Comanda (un dia normal son ~300 comandas de ~800 filas).
 *
 * tbventas por si sola no dice a quien se le vendio (su IdCli viene vacio y el
 * ci es el del cajero, casi siempre el generico de VENTAS GENERALES). El
 * cliente sale de la tabla entregas, que es la que liga comanda con cliente y
 * camion; de ahi se llega al vendedor por tbclientes.CiVend. Las comandas sin
 * entrega (venta de mostrador) se listan igual, pero sin cliente ni vendedor.
 */
class VentaController extends Controller
{
    /**
     * Codigo del producto "ADELANTO DE COMANDA": no es mercaderia, es un
     * anticipo cobrado en caja. Esas comandas nunca tienen cliente en ninguna
     * tabla, asi que se etiquetan como tales en vez de quedar en blanco.
     */
    private const COD_ADELANTO = 'F113';

    /**
     * Tope de filas por consulta. Un dia normal son ~300 comandas y un mes
     * ~2.800, asi que 5.000 cubre "ver todo" sin dejar la puerta abierta a
     * pedir el historico entero de tbventas de una sentada.
     */
    private const MAX_POR_CONSULTA = 5000;

    /**
     * Nombre del vendedor (alias pv sobre personal).
     *
     * Va aparte porque se usa en el listado, en la boleta y en las opciones
     * del filtro: si cada sitio lo arma a su manera, el mismo vendedor sale
     * con dos nombres distintos y el que se busca en el select no coincide
     * con el que se ve en la tabla.
     */
    private const SQL_VENDEDOR = "TRIM(CONCAT_WS(' ',
        NULLIF(TRIM(pv.Nombre1), ''),
        NULLIF(TRIM(pv.Nombre2), ''),
        NULLIF(TRIM(pv.App1), ''),
        NULLIF(TRIM(pv.Apm), '')
    ))";

    /** Columnas por las que se puede ordenar (evita SQL armado con input del usuario). */
    private const ORDENABLES = [
        'comanda'  => 'x.comanda',
        'fecha'    => 'x.fecha',
        'cliente'  => 'cliente',
        'zona'     => 'zona',
        'vendedor' => 'vendedor',
        'items'    => 'x.items',
        'cantidad' => 'x.cantidad',
        'total'    => 'x.total',
        'estado'   => 'estado',
    ];

    public function index(Request $request)
    {
        // perPage=0 es el "Todos" de la tabla. Se sirve igual paginado pero con
        // el tope de golpe, para que un rango largo no tumbe la consulta.
        $perPage = (int) $request->input('perPage', 20);
        if ($perPage === 0) {
            $perPage = self::MAX_POR_CONSULTA;
        } elseif ($perPage < 1 || $perPage > self::MAX_POR_CONSULTA) {
            $perPage = 20;
        }

        $sortBy = $request->input('sortBy', 'fecha');
        $columna = self::ORDENABLES[$sortBy] ?? 'x.fecha';
        $direccion = $request->boolean('descending', true) ? 'desc' : 'asc';

        $ventas = $this->baseQuery($request)
            ->select($this->columnas())
            ->orderByRaw($columna . ' ' . $direccion)
            ->orderBy('x.comanda', 'desc')
            ->paginate($perPage);

        $this->adjuntarFactura($ventas->getCollection());
        $this->completarClientes($ventas->getCollection());

        return $ventas;
    }

    /**
     * Totales de TODO el filtro (no solo de la pagina visible), para las
     * tarjetas de resumen de la pantalla.
     */
    public function resumen(Request $request)
    {
        $sub = $this->baseQuery($request)
            ->selectRaw('x.items as items, x.total as total');

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

    /**
     * Marca que comandas quedaron facturadas.
     *
     * Aca no hay nada que adivinar: tbfactura guarda la comanda, asi que el
     * enlace es exacto. Lo que no aparezca en tbfactura se entrego con voucher.
     *
     * La factura es ademas la segunda fuente de cliente: su IdCli es el NIT al
     * que se emitio. Sirve para las comandas que no pasaron por entregas (venta
     * de mostrador facturada), que si no quedarian sin nombre en la pantalla.
     */
    private function adjuntarFactura($ventas)
    {
        foreach ($ventas as $v) {
            $v->factura = null;
        }

        $comandas = $ventas->pluck('comanda')->filter()->unique()->values()->all();
        if (count($comandas) === 0) {
            return;
        }

        $facturas = DB::table('tbfactura as f')
            ->leftJoin('tbclientes as c', DB::raw('TRIM(c.Id)'), '=', DB::raw('TRIM(f.IdCli)'))
            ->leftJoin('personal as pv', function ($j) {
                $j->on(DB::raw('TRIM(pv.ci)'), '=', DB::raw('TRIM(c.CiVend)'));
            })
            ->whereIn('f.comanda', $comandas)
            ->select([
                'f.CodAut', 'f.nrofac', 'f.comanda', 'f.FechaFac', 'f.IdCli', 'f.cuffac', 'f.ESTADO',
                DB::raw('TRIM(c.Nombres) as cliente'),
                DB::raw('TRIM(c.zona) as zona'),
                DB::raw('TRIM(c.Direccion) as direccion'),
                DB::raw(self::SQL_VENDEDOR . ' as vendedor'),
            ])
            ->orderBy('f.CodAut')
            ->get()
            ->keyBy('comanda');

        foreach ($ventas as $v) {
            $f = $facturas->get($v->comanda);
            if (!$f) {
                continue;
            }

            $v->factura = [
                'CodAut'   => $f->CodAut,
                'nrofac'   => $f->nrofac,
                'comanda'  => $f->comanda,
                'FechaFac' => $f->FechaFac,
                'nit'      => trim($f->IdCli),
                'cliente'  => $f->cliente,
                'estado'   => trim((string) $f->ESTADO),
                'siat'     => FacturaFiscalController::urlSiat($f->cuffac, $f->nrofac),
            ];
        }
    }

    /**
     * Rellena el cliente de las comandas que no pasaron por entregas.
     *
     * Hay tres sitios distintos donde puede quedar registrado a quien se le
     * vendio, y ninguno los tiene todos:
     *   1. entregas       - reparto en camion, es la via normal (~80%).
     *   2. tbfactura      - a quien se emitio la factura fiscal.
     *   3. tbctascobrar / tbctascow - a quien se le fio (venta a credito).
     * Se aplican en ese orden y se marca de donde salio el dato en
     * origenCliente, para no dar por entregado lo que no lo esta.
     *
     * Lo que no aparece en ninguna de las tres es venta de mostrador al contado
     * sin identificar: ahi no hay cliente que mostrar, y son ~1 de cada 7.
     */
    private function completarClientes($ventas)
    {
        $pendientes = $ventas->filter(function ($v) {
            return empty($v->cliente);
        });

        if ($pendientes->isEmpty()) {
            return;
        }

        // Primero la factura, que ya viene adjuntada y no cuesta otra consulta.
        foreach ($pendientes as $v) {
            if (!empty($v->factura['cliente'])) {
                $v->cliente = $v->factura['cliente'];
                $v->nit = $v->factura['nit'];
                $v->origenCliente = 'FACTURA';
            }
        }

        // Luego las cuentas por cobrar de la propia comanda.
        $comandas = $this->comandasPendientes($pendientes, 'comanda');
        if (count($comandas) > 0) {
            $porComanda = $this->clientesACredito('tbctascobrar', $comandas)
                + $this->clientesACredito('tbctascow', $comandas);

            foreach ($pendientes as $v) {
                if (empty($v->cliente) && isset($porComanda[$v->comanda])) {
                    $this->volcarCliente($v, $porComanda[$v->comanda], 'CREDITO');
                }
            }
        }

        // Y por ultimo la comanda relacionada. tbventas.Comandas apunta a otra
        // comanda: es como se enlaza un adelanto o pago a cuenta con la venta
        // original, que si tiene comprador.
        $refs = $this->comandasPendientes($pendientes, 'comandaRef');
        if (count($refs) === 0) {
            return;
        }

        $porRef = $this->clientesPorComanda($refs);

        foreach ($pendientes as $v) {
            $ref = (int) ($v->comandaRef ?? 0);
            if (empty($v->cliente) && $ref > 0 && isset($porRef[$ref])) {
                $this->volcarCliente($v, $porRef[$ref], 'REFERENCIA');
            }
        }
    }

    /** Comandas (o comandas referenciadas) que siguen sin cliente. */
    private function comandasPendientes($ventas, $campo)
    {
        return $ventas->filter(function ($v) {
            return empty($v->cliente);
        })->pluck($campo)->map(function ($c) {
            return (int) $c;
        })->filter()->unique()->values()->all();
    }

    /** Copia los datos del cliente encontrado sin pisar lo que ya venia. */
    private function volcarCliente($venta, $cliente, $origen)
    {
        $venta->cliente = $cliente->cliente;
        $venta->nit = $cliente->nit;
        $venta->zona = $venta->zona ?: $cliente->zona;
        $venta->direccion = $venta->direccion ?: $cliente->direccion;
        $venta->vendedor = $venta->vendedor ?: $cliente->vendedor;
        $venta->tipago = $venta->tipago ?: ($cliente->tipago ?? null);
        $venta->placa = $venta->placa ?: ($cliente->placa ?? null);
        $venta->origenCliente = $origen;
    }

    /**
     * Cliente de un grupo de comandas, probando las fuentes en orden y sin
     * volver a consultar las que ya se resolvieron.
     */
    private function clientesPorComanda(array $comandas)
    {
        $encontrados = [];
        $faltan = $comandas;

        foreach (['entregas', 'tbfactura', 'tbctascobrar', 'tbctascow'] as $fuente) {
            if (count($faltan) === 0) {
                break;
            }

            if ($fuente === 'entregas') {
                $nuevos = $this->clientesDeEntrega($faltan);
            } elseif ($fuente === 'tbfactura') {
                $nuevos = $this->clientesDeFactura($faltan);
            } else {
                $nuevos = $this->clientesACredito($fuente, $faltan);
            }

            $encontrados += $nuevos;
            $faltan = array_values(array_diff($faltan, array_keys($nuevos)));
        }

        return $encontrados;
    }

    /** Cliente segun la entrega, indexado por comanda. */
    private function clientesDeEntrega(array $comandas)
    {
        return DB::table('entregas as e')
            ->join('tbclientes as c', 'c.Cod_Aut', '=', 'e.cliente_id')
            ->leftJoin('personal as pv', function ($j) {
                $j->on(DB::raw('TRIM(pv.ci)'), '=', DB::raw('TRIM(c.CiVend)'));
            })
            ->whereIn('e.comanda', $comandas)
            ->select(array_merge([
                'e.comanda',
                DB::raw('TRIM(e.tipago) as tipago'),
                DB::raw('TRIM(e.placa) as placa'),
            ], $this->columnasCliente()))
            ->orderBy('e.id')
            ->get()
            ->keyBy('comanda')
            ->all();
    }

    /** Cliente segun la factura fiscal, indexado por comanda. */
    private function clientesDeFactura(array $comandas)
    {
        return DB::table('tbfactura as f')
            ->join('tbclientes as c', DB::raw('TRIM(c.Id)'), '=', DB::raw('TRIM(f.IdCli)'))
            ->leftJoin('personal as pv', function ($j) {
                $j->on(DB::raw('TRIM(pv.ci)'), '=', DB::raw('TRIM(c.CiVend)'));
            })
            ->whereIn('f.comanda', $comandas)
            ->select(array_merge(['f.comanda'], $this->columnasCliente()))
            ->orderBy('f.CodAut')
            ->get()
            ->keyBy('comanda')
            ->all();
    }

    /** Columnas de cliente comunes a todas las fuentes. */
    private function columnasCliente()
    {
        return [
            DB::raw('TRIM(c.Nombres) as cliente'),
            DB::raw('TRIM(c.Id) as nit'),
            DB::raw('TRIM(c.zona) as zona'),
            DB::raw('TRIM(c.territorio) as territorio'),
            DB::raw('TRIM(c.Telf) as telefono'),
            DB::raw('TRIM(c.Direccion) as direccion'),
            DB::raw(self::SQL_VENDEDOR . ' as vendedor'),
        ];
    }

    /**
     * Cliente de las cuentas por cobrar, indexado por comanda.
     *
     * Las dos tablas no tienen el mismo esquema: tbctascobrar guarda el NIT en
     * CINIT y ademas el pago y la placa; tbctascow solo guarda idCli.
     */
    private function clientesACredito($tabla, array $comandas)
    {
        $esquema = [
            'tbctascobrar' => ['nit' => 'CINIT', 'tipago' => 'TRIM(cc.Tipago)', 'placa' => 'TRIM(cc.placa)'],
            'tbctascow'    => ['nit' => 'idCli', 'tipago' => "'CRÉDITO'",       'placa' => 'NULL'],
        ][$tabla];

        return DB::table($tabla . ' as cc')
            ->join('tbclientes as c', DB::raw('TRIM(c.Id)'), '=', DB::raw('TRIM(cc.' . $esquema['nit'] . ')'))
            ->leftJoin('personal as pv', function ($j) {
                $j->on(DB::raw('TRIM(pv.ci)'), '=', DB::raw('TRIM(c.CiVend)'));
            })
            ->whereIn('cc.comanda', $comandas)
            ->select(array_merge([
                'cc.comanda',
                DB::raw($esquema['tipago'] . ' as tipago'),
                DB::raw($esquema['placa'] . ' as placa'),
            ], $this->columnasCliente()))
            ->get()
            ->keyBy('comanda')
            ->all();
    }

    /**
     * Venta completa de una comanda: cabecera, productos y factura.
     *
     * Es el detalle de la pantalla y tambien lo que se muestra al buscar una
     * comanda suelta.
     */
    public function comanda($comanda)
    {
        if (!ctype_digit((string) $comanda)) {
            return response()->json(['message' => 'La comanda debe ser un numero'], 422);
        }

        $venta = DB::table('tbventas')
            ->where('Comanda', $comanda)
            ->selectRaw("
                Comanda as comanda,
                MIN(Fech_Venta) as fecha,
                MAX(FechaFac) as fechaFac,
                COUNT(*) as items,
                ROUND(SUM(Cant), 2) as cantidad,
                ROUND(SUM(Monto), 2) as total,
                MAX(TRIM(AtCliente)) as atendido,
                MAX(Comandas) as comandaRef,
                MAX(TRIM(cod_pro) = '" . self::COD_ADELANTO . "') as adelanto,
                MAX(TRIM(ci)) as ci
            ")
            ->groupBy('Comanda')
            ->first();

        if (!$venta) {
            return response()->json(['message' => 'No existe la comanda ' . $comanda], 404);
        }

        // Las columnas replican la boleta de entrega que se imprime en papel.
        // Ojo con dos nombres que en tbventas van al reves de lo que sugieren:
        // la CANT de la boleta es UnidPeso y el P.NETO es Cant.
        $venta->detalle = DB::table('tbventas as v')
            ->leftJoin('tbproductos as pr', DB::raw('TRIM(pr.cod_prod)'), '=', DB::raw('TRIM(v.cod_pro)'))
            ->where('v.Comanda', $comanda)
            ->select([
                DB::raw('TRIM(v.cod_pro) as cod_prod'),
                DB::raw('TRIM(pr.Producto) as producto'),
                DB::raw('TRIM(pr.codUnid) as unidad'),
                'v.UnidPeso as cant',
                'v.CantCaja as cajas',
                'v.Cant as cantidad',
                'v.PVentUnit as precio',
                'v.Descuatot as descuento',
                'v.Monto as subtotal',
                'v.Fech_Venta as fecha',
            ])
            ->orderBy('v.Fech_Venta')
            ->get();

        $venta->entrega = DB::table('entregas as e')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'e.cliente_id')
            ->leftJoin('personal as pv', function ($j) {
                $j->on(DB::raw('TRIM(pv.ci)'), '=', DB::raw('TRIM(c.CiVend)'));
            })
            ->where('e.comanda', $comanda)
            ->select([
                'e.estado',
                'e.tipago',
                'e.despachador',
                'e.placa',
                'e.hora',
                'e.fecha as fechaEmision',
                'e.fechaEntreg',
                'e.monto',
                'e.lat',
                'e.lng',
                'e.observacion',
                DB::raw('TRIM(c.Nombres) as cliente'),
                DB::raw('TRIM(c.Id) as nit'),
                DB::raw('TRIM(c.zona) as zona'),
                DB::raw('TRIM(c.territorio) as territorio'),
                DB::raw('TRIM(c.Telf) as telefono'),
                DB::raw('TRIM(c.Direccion) as direccion'),
                DB::raw(self::SQL_VENDEDOR . ' as vendedor'),
            ])
            ->orderByDesc('e.id')
            ->first();

        $venta->literal = $this->enLetras($venta->total);

        $venta->factura = null;
        $fact = DB::table('tbfactura')->where('comanda', $comanda)->orderByDesc('CodAut')->first();
        if ($fact) {
            $venta->factura = [
                'CodAut'   => $fact->CodAut,
                'nrofac'   => $fact->nrofac,
                'FechaFac' => $fact->FechaFac,
                'nit'      => trim($fact->IdCli),
                'estado'   => trim((string) $fact->ESTADO),
                'siat'     => FacturaFiscalController::urlSiat($fact->cuffac, $fact->nrofac),
            ];
        }

        // Misma cadena de respaldo que el listado: factura, credito y por
        // ultimo la comanda con la que esta relacionada.
        if (!$venta->entrega || !$venta->entrega->cliente) {
            $venta->entrega = $this->clienteDeRespaldo(
                (int) $comanda,
                $fact,
                (int) $venta->comandaRef,
                $venta->entrega
            );
        }

        return response()->json($venta);
    }

    /**
     * Cabecera de cliente para las comandas sin entrega: primero la factura y
     * si no, las cuentas por cobrar. Mantiene lo que ya trajera la entrega
     * (placa, pago, hora): solo completa los datos del cliente.
     */
    private function clienteDeRespaldo($comanda, $fact, $ref, $entrega)
    {
        $cliente = null;
        $origen = null;

        if ($fact) {
            $cliente = $this->clientePorNit(trim($fact->IdCli));
            $origen = 'FACTURA';
        }

        if (!$cliente) {
            foreach (['tbctascobrar', 'tbctascow'] as $tabla) {
                $porComanda = $this->clientesACredito($tabla, [$comanda]);
                if (isset($porComanda[$comanda])) {
                    $cliente = $porComanda[$comanda];
                    $origen = 'CREDITO';
                    break;
                }
            }
        }

        // La comanda relacionada (adelanto o pago a cuenta de otra venta).
        if (!$cliente && $ref > 0) {
            $porRef = $this->clientesPorComanda([$ref]);
            if (isset($porRef[$ref])) {
                $cliente = $porRef[$ref];
                $origen = 'REFERENCIA';
            }
        }

        if (!$cliente) {
            return $entrega;
        }

        $cliente->origen = $origen;

        foreach ((array) $entrega as $campo => $valor) {
            if (!isset($cliente->$campo) || $cliente->$campo === null || $cliente->$campo === '') {
                $cliente->$campo = $valor;
            }
        }

        return $cliente;
    }

    /** Datos de cabecera de un cliente por su NIT/CI. */
    private function clientePorNit($nit)
    {
        if ($nit === '') {
            return null;
        }

        return DB::table('tbclientes as c')
            ->leftJoin('personal as pv', function ($j) {
                $j->on(DB::raw('TRIM(pv.ci)'), '=', DB::raw('TRIM(c.CiVend)'));
            })
            ->whereRaw('TRIM(c.Id) = ?', [$nit])
            ->select([
                DB::raw('TRIM(c.Nombres) as cliente'),
                DB::raw('TRIM(c.Id) as nit'),
                DB::raw('TRIM(c.zona) as zona'),
                DB::raw('TRIM(c.territorio) as territorio'),
                DB::raw('TRIM(c.Telf) as telefono'),
                DB::raw('TRIM(c.Direccion) as direccion'),
                DB::raw(self::SQL_VENDEDOR . ' as vendedor'),
            ])
            ->first();
    }

    /** El LITERAL de la boleta, con el mismo formato que usa la factura. */
    private function enLetras($monto)
    {
        $monto = round((float) $monto, 2);
        $entero = (int) $monto;
        $decimal = (int) round(($monto - $entero) * 100);

        $formatter = new NumeroALetras();

        // toString deja un espacio sobrante al final, de ahi el trim.
        return ucfirst(strtolower(trim($formatter->toString($entero)))) . ' ' . sprintf('%02d', $decimal) . '/100';
    }

    /** Opciones para los selects de filtro. */
    public function filtros()
    {
        $vendedores = DB::table('tbclientes as c')
            ->join('personal as pv', function ($j) {
                $j->on(DB::raw('TRIM(pv.ci)'), '=', DB::raw('TRIM(c.CiVend)'));
            })
            ->selectRaw('TRIM(pv.ci) as value, ' . self::SQL_VENDEDOR . ' as label')
            ->distinct()
            ->orderBy('label')
            ->get();

        return response()->json([
            'vendedores' => $vendedores,
            'tipos'      => $this->valoresDistintos('tipago'),
            'estados'    => $this->valoresDistintos('estado'),
        ]);
    }

    private function valoresDistintos($columna)
    {
        return DB::table('entregas')
            ->select($columna)
            ->whereNotNull($columna)
            ->where($columna, '<>', '')
            ->groupBy($columna)
            ->orderBy($columna)
            ->pluck($columna);
    }

    /** Columnas que devuelve el listado. */
    private function columnas()
    {
        return [
            'x.comanda',
            'x.fecha',
            'x.items',
            'x.cantidad',
            'x.total',
            'x.atendido',
            'x.adelanto',
            'x.comandaRef',
            'e.estado',
            'e.tipago',
            'e.despachador',
            'e.placa',
            'e.hora as horaEntrega',
            'e.cliente_id',
            DB::raw('TRIM(c.Nombres) as cliente'),
            DB::raw('TRIM(c.Id) as nit'),
            DB::raw('TRIM(c.zona) as zona'),
            DB::raw('TRIM(c.Direccion) as direccion'),
            DB::raw(self::SQL_VENDEDOR . ' as vendedor'),
        ];
    }

    /**
     * Consulta con todos los filtros aplicados; la comparten el listado y el
     * resumen para que los totales siempre cuadren con la tabla.
     *
     * Las entregas se traen con una subconsulta agrupada (la ultima entrega de
     * cada comanda) en vez de un subselect por fila: entregas no tiene indice
     * por comanda y correlacionarla hacia 90 segundos la consulta.
     */
    private function baseQuery(Request $request)
    {
        $desde = $request->input('desde') ?: date('Y-m-d');
        $hasta = $request->input('hasta') ?: $desde;

        $ventas = DB::table('tbventas as v')
            ->selectRaw('
                v.Comanda as comanda,
                MIN(v.Fech_Venta) as fecha,
                COUNT(*) as items,
                ROUND(SUM(v.Cant), 2) as cantidad,
                ROUND(SUM(v.Monto), 2) as total,
                MAX(TRIM(v.AtCliente)) as atendido,
                MAX(v.Comandas) as comandaRef,
                MAX(TRIM(v.cod_pro) = "' . self::COD_ADELANTO . '") as adelanto
            ')
            ->where('v.Fech_Venta', '>=', $desde . ' 00:00:00')
            ->where('v.Fech_Venta', '<=', $hasta . ' 23:59:59')
            ->groupBy('v.Comanda');

        // La entrega puede quedar registrada el dia antes o el dia despues de
        // la venta, por eso la ventana va un dia a cada lado.
        $ultimas = DB::table('entregas')
            ->selectRaw('comanda, MAX(id) as id')
            ->where('fecha', '>=', date('Y-m-d', strtotime($desde . ' -1 day')))
            ->where('fecha', '<=', date('Y-m-d', strtotime($hasta . ' +1 day')))
            ->groupBy('comanda');

        $query = DB::query()
            ->fromSub($ventas, 'x')
            ->leftJoinSub($ultimas, 'ult', 'ult.comanda', '=', 'x.comanda')
            ->leftJoin('entregas as e', 'e.id', '=', 'ult.id')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'e.cliente_id')
            ->leftJoin('personal as pv', function ($j) {
                $j->on(DB::raw('TRIM(pv.ci)'), '=', DB::raw('TRIM(c.CiVend)'));
            });

        if ($tipo = trim((string) $request->input('tipo', ''))) {
            $query->where('e.tipago', $tipo);
        }
        if ($estado = trim((string) $request->input('estado', ''))) {
            $query->where('e.estado', $estado);
        }
        if ($vendedor = trim((string) $request->input('vendedor', ''))) {
            $query->whereRaw('TRIM(c.CiVend) = ?', [$vendedor]);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($w) use ($like, $search) {
                $w->where('c.Nombres', 'like', $like)
                    ->orWhere('c.Direccion', 'like', $like)
                    ->orWhere('c.zona', 'like', $like)
                    ->orWhere('c.Id', 'like', $like)
                    ->orWhere('pv.Nombre1', 'like', $like)
                    ->orWhere('pv.App1', 'like', $like)
                    ->orWhere('e.despachador', 'like', $like);
                if (ctype_digit($search)) {
                    $w->orWhere('x.comanda', $search);
                }
            });
        }

        // El filtro por producto va como EXISTS contra las lineas de la misma
        // comanda; tbventas tiene indice por Comanda, asi que sale barato.
        $producto = trim((string) $request->input('producto', ''));
        if ($producto !== '') {
            $like = '%' . $producto . '%';
            $query->whereExists(function ($s) use ($like) {
                $s->select(DB::raw(1))
                    ->from('tbventas as v2')
                    ->leftJoin('tbproductos as pr', 'pr.cod_prod', '=', 'v2.cod_pro')
                    ->whereColumn('v2.Comanda', 'x.comanda')
                    ->where(function ($w) use ($like) {
                        $w->where('v2.cod_pro', 'like', $like)
                            ->orWhere('pr.Producto', 'like', $like);
                    });
            });
        }

        // Solo facturadas / solo vouchers.
        $documento = trim((string) $request->input('documento', ''));
        if ($documento === 'FACTURA' || $documento === 'VOUCHER') {
            $existe = function ($q) {
                $q->select(DB::raw(1))
                    ->from('tbfactura as f')
                    ->whereColumn('f.comanda', 'x.comanda');
            };

            $documento === 'FACTURA'
                ? $query->whereExists($existe)
                : $query->whereNotExists($existe);
        }

        return $query;
    }
}
