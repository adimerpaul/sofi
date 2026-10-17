<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PapeleriaSofia;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Services\CambioPedido;
use App\Services\CargaCamion;
use App\Services\ModificacionFactura;
use App\Services\SiatService;
use App\Services\TipoPedido;
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
    // El formato de papel de la casa: los mismos estilos y cabecera que usan
    // las hojas del recojo del caminero.
    use PapeleriaSofia;

    /** Fecha centinela del legado para "sin valor". */
    private const FECHA_NULA = '1899-11-30 04:32:36';

    /** Tope de comprobantes por PDF; mas que esto no se imprime de una vez. */
    private const MAX_LOTE = 600;

    /** Lo anulado ya no vale: no se imprime por ninguna via. */
    private const NO_IMPRIME_ANULADO = 'El comprobante está anulado: no se puede imprimir';

    /** Listado de lo emitido, con filtros de la pantalla. */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('perPage', 20), 1), 200);

        $pagina = $this->filtrar($request)->paginate($perPage);

        $this->adjuntarCarga($pagina->getCollection());
        $this->adjuntarEntrega($pagina->getCollection());
        $this->adjuntarZonaDirecta($pagina->getCollection());

        // Los conteos van pegados a la pagina y no en otra ruta: la pantalla
        // los muestra junto a los filtros y pedirlos aparte era una segunda
        // vuelta con los mismos parametros.
        return response()->json($pagina->toArray() + ['conteos' => $this->conteos($request)]);
    }

    /**
     * Cuantos comprobantes hay de cada tipo con los filtros puestos.
     *
     * Se ignora el filtro de tipo a proposito: los numeros tienen que seguir
     * diciendo cuanto hay del otro lado aunque se este mirando uno solo, si no
     * el chip elegido seria el unico con cantidad.
     */
    private function conteos(Request $request)
    {
        $sinTipo = Request::create('', 'GET', $request->except('tipo'));

        $porTipo = $this->filtrar($sinTipo)->reorder()->setEagerLoads([])
            ->groupBy('tipo_comprobante')
            ->select('tipo_comprobante', DB::raw('COUNT(*) as total'))
            ->pluck('total', 'tipo_comprobante');

        return [
            'FACTURA' => (int) $porTipo->get('FACTURA', 0),
            'VENTA'   => (int) $porTipo->get('VENTA', 0),
            'TODOS'   => (int) $porTipo->sum(),
        ];
    }

    /**
     * Le pega a cada comprobante como viene su revision de carga, para que el
     * listado lo diga sin tener que abrir la pantalla del caminero.
     *
     * Se lee de carga_verificaciones en una sola consulta por pagina: pedirle
     * el estado a CargaCamion fila por fila reconstruiria la carga entera del
     * camion una vez por comprobante.
     */
    private function adjuntarZonaDirecta($facturas)
    {
        // La venta directa con camion no tiene pedido del que sacar el color:
        // lleva el ultimo que se le dio a ese camion en la asignacion.
        $cache = [];
        foreach ($facturas as $factura) {
            $placa = trim((string) $factura->placa);
            if ($factura->pedido_nro || $placa === '' || $factura->zona_color) {
                continue;
            }
            $dia = $factura->fecha_entrega ?: (optional($factura->fecha)->format('Y-m-d') ?? date('Y-m-d'));
            $clave = $placa . '|' . $dia;
            if (!array_key_exists($clave, $cache)) {
                $cache[$clave] = CargaCamion::colorDeZona($placa, $dia);
            }
            if ($cache[$clave]) {
                $factura->zona_color = $cache[$clave]['color'] . '|' . $cache[$clave]['colorStyle'];
            }
        }
    }

    /**
     * Los camiones con el color de zona que se les dio en la ultima
     * asignacion: es el que toma la venta directa al elegir camion.
     */
    public function coloresCamion()
    {
        $zonas = CargaCamion::coloresDeZona();

        return DB::table('vehiculo')->get(['placa', 'colorStyle'])
            ->map(function ($v) use ($zonas) {
                $placa = trim((string) $v->placa);
                $zona = $zonas[$placa] ?? null;
                return [
                    'placa' => $placa,
                    // Sin asignacion reciente queda el color propio del vehiculo.
                    'colorStyle' => $zona['colorStyle'] ?? trim((string) $v->colorStyle),
                    'color' => $zona['color'] ?? null,
                    'de_asignacion' => (bool) $zona,
                ];
            })
            ->filter(function ($v) { return $v['placa'] !== ''; })
            ->values();
    }

    private function adjuntarCarga($facturas)
    {
        $marcas = collect();

        if ($facturas->isNotEmpty()) {
            $marcas = DB::table('carga_verificaciones')
                ->whereIn('factura_id', $facturas->pluck('id')->all())
                ->get()
                ->keyBy('factura_id');
        }

        foreach ($facturas as $factura) {
            $marca = $marcas->get($factura->id);

            $factura->carga_observacion = $marca->observacion ?? null;
            $factura->carga_observado = (bool) ($marca->observado ?? false);
            $factura->carga_verificado_por = $marca->verificado_por ?? null;
            $factura->carga_verificado_en = $marca->verificado_en ?? null;
            $factura->carga_estado = $this->estadoCarga($factura, $marca);
        }
    }

    /**
     * Si el comprobante llego o no al cliente.
     *
     * La entrega la registra el caminero desde su celular y cuelga de la
     * factura, igual que el visto bueno de la carga. Se lee de una sola
     * consulta por pagina por el mismo motivo.
     *
     * De un comprobante puede haber mas de un intento -se fue, no estaba, se
     * volvio- asi que se toma el ultimo: es el que vale.
     */
    private function adjuntarEntrega($facturas)
    {
        $entregas = collect();

        if ($facturas->isNotEmpty()) {
            $entregas = DB::table('entregas')
                ->whereIn('factura_id', $facturas->pluck('id')->all())
                ->orderBy('id')
                ->get()
                ->keyBy('factura_id');
        }

        foreach ($facturas as $factura) {
            $entrega = $entregas->get($factura->id);

            $factura->entrega_estado = $this->estadoEntrega($factura, $entrega);
            $factura->entrega_hora = $entrega->hora ?? null;
            $factura->entrega_fecha = $entrega->fechaEntreg ?? null;
            $factura->entrega_tipago = $entrega->tipago ?? null;
            $factura->entrega_monto = $entrega ? round((float) $entrega->monto, 2) : null;
            // Viene con espacios cuando el caminero no escribio nada: sin
            // recortar, la pantalla pinta una linea de observacion vacia.
            $observacion = trim((string) ($entrega->observacion ?? ''));
            $factura->entrega_observacion = $observacion !== '' ? $observacion : null;
            // Producto por producto lo que el cliente se quedo, si el caminero
            // marco un retorno parcial: es lo que caja usa para editar.
            $factura->entrega_retorno = !empty($entrega->retorno_detalle)
                ? json_decode($entrega->retorno_detalle, true)
                : null;
        }
    }

    /**
     * En que anda la entrega de un comprobante:
     *
     * NO_APLICA  no viaja en camion (venta de mostrador) o esta anulado
     * PENDIENTE  todavia esta en el camion
     * ENTREGADO / NO ENTREGADO / RECHAZADO  lo que marco el caminero
     */
    private function estadoEntrega(Factura $factura, $entrega)
    {
        // Sin camion (venta de mostrador) no pasa por el caminero: no aplica.
        if (!trim((string) $factura->placa) || $factura->estado === 'ANULADO') {
            return 'NO_APLICA';
        }

        if (!$entrega) {
            return 'PENDIENTE';
        }

        return trim((string) $entrega->estado) ?: 'PENDIENTE';
    }

    /**
     * En que anda la canasta de un comprobante:
     *
     * NO_APLICA  no viaja en camion (venta de mostrador) o esta anulado
     * PENDIENTE  el caminero todavia no la reviso, o la desmarco
     * CAMBIO     la reviso, pero despues le cambiaron la venta: vuelve a contar
     *            como pendiente porque la canasta ya no es la que miro
     * VERIFICADA el caminero se hizo responsable de que este arriba
     */
    private function estadoCarga(Factura $factura, $marca)
    {
        if (!trim((string) $factura->placa) || $factura->estado === 'ANULADO') {
            return 'NO_APLICA';
        }

        if (!$marca || !$marca->verificado) {
            return 'PENDIENTE';
        }

        // Mismo criterio que CargaCamion: el visto bueno vale para la venta tal
        // como estaba al revisarla, no para lo que se le agregue despues.
        $cambio = (int) $marca->items_esperados !== $factura->detalles->count()
            || abs((float) $marca->total_esperado - (float) $factura->total) > 0.01;

        if ($cambio) {
            return 'CAMBIO';
        }

        // Observada tambien esta revisada -no frena la impresion-, pero se
        // distingue para que caja vea que hay algo pendiente de resolver.
        return $marca->observado ? 'OBSERVADA' : 'VERIFICADA';
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
            // El camion de lo que sale de un pedido es el del pedido, y por eso
            // se trae de tbpedidos en vez de guardarse repetido. La venta
            // directa trae el suyo en facturas.placa. El CONVERT evita mezclar
            // la collation del legado (latin1) con la de facturas (utf8mb4).
            ->selectRaw("COALESCE(NULLIF(TRIM(facturas.placa), ''), (
                SELECT CONVERT(TRIM(COALESCE(pc.placa, '')) USING utf8mb4)
                FROM tbpedidos pc
                WHERE pc.deleted_at IS NULL AND pc.NroPed = facturas.pedido_nro
                  AND " . TipoPedido::sql('pc') . " = UPPER(TRIM(facturas.pedido_tipo))
                LIMIT 1
            )) as placa")
            // El color de zona que se le dio al pedido al asignarle camion (el
            // mismo del mapa de clientes): en la grilla guia a que zona va.
            ->selectRaw("(
                SELECT CONVERT(CONCAT(TRIM(COALESCE(pz.color, '')), '|', TRIM(COALESCE(pz.colorStyle, ''))) USING utf8mb4)
                FROM tbpedidos pz
                WHERE pz.deleted_at IS NULL AND pz.NroPed = facturas.pedido_nro
                  AND " . TipoPedido::sql('pz') . " = UPPER(TRIM(facturas.pedido_tipo))
                LIMIT 1
            ) as zona_color")
            ->orderByDesc('id');

        // Los comprobantes marcados a mano en la grilla: van solos, sin los
        // demas filtros, que ya se aplicaron al elegirlos.
        $ids = $this->idsSeleccionados($request);
        if (!empty($ids)) {
            return $query->whereIn('id', $ids);
        }

        $this->enVentana($query, $request);
        if ($tipo = $request->input('tipo')) {
            $query->where('tipo_comprobante', $tipo);
        }
        if ($estado = $request->input('estado')) {
            $query->where('estado', $estado);
        }
        // Tipo del pedido (embutidos, pollo, cerdo, res, podium y huevo). La
        // venta directa no tiene pedido y no entra al filtrar por tipo.
        $pedidoTipo = strtoupper(trim((string) $request->input('pedido_tipo', '')));
        if (in_array($pedidoTipo, TipoPedido::TIPOS, true)) {
            $query->whereRaw('UPPER(TRIM(facturas.pedido_tipo)) = ?', [$pedidoTipo]);
        }

        // El camion se filtra por los pedidos que salieron en esa placa. La
        // subconsulta se queda dentro de tbpedidos a proposito: cruzar textos
        // entre facturas (utf8mb4) y el legado (latin1) mezcla collations.
        // La venta directa se filtra por su propia placa (facturas.placa).
        if ($camion = trim((string) $request->input('camion', ''))) {
            if ($camion === 'SIN') {
                $query->whereRaw("TRIM(COALESCE(facturas.placa, '')) = ''")
                    ->where(function ($w) {
                        $w->whereNull('pedido_nro')->orWhereNotIn('pedido_nro', function ($sub) {
                            $sub->from('tbpedidos')->whereNull('tbpedidos.deleted_at')->select('NroPed')
                                ->whereRaw("TRIM(COALESCE(placa, '')) <> ''");
                        });
                    });
            } else {
                $query->where(function ($w) use ($camion) {
                    $w->whereIn('pedido_nro', function ($sub) use ($camion) {
                        $sub->from('tbpedidos')->whereNull('tbpedidos.deleted_at')->select('NroPed')
                            ->whereRaw('TRIM(placa) = ?', [$camion]);
                    })->orWhere(function ($directa) use ($camion) {
                        $directa->whereNull('pedido_nro')->whereRaw('TRIM(facturas.placa) = ?', [$camion]);
                    });
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

    /** 'ids' llega como arreglo o como '1,2,3' (en un GET); solo numeros. */
    private function idsSeleccionados(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /**
     * Una fila por camion con lo que lleva facturado, igual que en la pantalla
     * de pedidos por facturar: de los pedidos enviados en el rango de fechas,
     * cuantos ya tienen comprobante vigente.
     *
     * Se ignora el filtro de camion: si no, al elegir uno la lista se quedaria
     * con ese solo y no habria como cambiarlo.
     */
    public function camiones(Request $request)
    {
        $desde = $request->input('desde') ?: date('Y-m-d');
        $hasta = $request->input('hasta') ?: $desde;
        // Con un tipo de pedido elegido, cada camion cuenta solo los pedidos de
        // ese tipo, igual que la lista de comprobantes.
        $pedidoTipo = strtoupper(trim((string) $request->input('pedido_tipo', '')));
        $pedidoTipo = in_array($pedidoTipo, TipoPedido::TIPOS, true) ? $pedidoTipo : null;

        // Los pedidos de cada camion son los que se cobraron en la misma
        // ventana que muestra la lista (dia, o turno de caja con hora), mas los
        // que salen esos dias y todavia no tienen comprobante. Solo esos
        // pedidos, no su dia entero: un pedido viejo cobrado hoy metia todos
        // los de su dia, que ya no estaban por facturar.
        // Por comprobante y no por numero de pedido: un pedido con embutidos y
        // podium tiene dos, y puede que solo uno se haya cobrado en la ventana.
        $cobrados = $this->enVentana(Factura::query(), $request)
            ->where('estado', '<>', 'ANULADO')
            ->whereNotNull('pedido_nro')->pluck('id');
        // Lo cobrado despues de la hora de cierre ya es del reparto de manana:
        // en un turno (ayer 18:00 a hoy 18:00) salen los pedidos de hoy.
        $entregaDesde = $this->hora($request->input('hora_desde'))
            ? date('Y-m-d', strtotime($desde . ' +1 day'))
            : $desde;
        // Con fecha de entrega: los pedidos de ese reparto, sin vueltas de hora.
        if ($rango = $this->rangoEntregaFiltro($request)) {
            list($entregaDesde, $hasta) = $rango;
        }

        // Una fila por pedido (numero y tipo), marcada si ya se cobro.
        $pedidos = DB::table('tbpedidos as p')
            ->whereNull('p.deleted_at')
            ->leftJoin('facturas as f', function ($join) {
                $join->on('f.pedido_nro', '=', 'p.NroPed')
                    ->on(DB::raw('UPPER(TRIM(f.pedido_tipo))'), '=', DB::raw(TipoPedido::sql('p')))
                    ->whereNull('f.deleted_at')
                    ->where('f.estado', '<>', 'ANULADO');
            })
            ->where(function ($w) use ($entregaDesde, $hasta, $cobrados) {
                // De los que salen esos dias, solo lo que falta facturar: lo que
                // ya se cobro antes de la ventana no esta en la lista y no cuenta.
                $w->where(function ($rango) use ($entregaDesde, $hasta) {
                    $rango->where('p.fecha_entrega', '>=', $entregaDesde)->where('p.fecha_entrega', '<=', $hasta)
                        ->whereNull('f.id');
                });
                if ($cobrados->isNotEmpty()) {
                    $w->orWhereIn('f.id', $cobrados->all());
                }
            })
            ->whereRaw("UPPER(TRIM(p.estado)) = 'ENVIADO'")
            ->where('p.bonificacion', 0)
            ->when($pedidoTipo, function ($q) use ($pedidoTipo) {
                $q->whereRaw(TipoPedido::sql('p') . ' = ?', [$pedidoTipo]);
            })
            ->groupBy('p.NroPed', DB::raw(TipoPedido::sql('p')))
            ->get([
                DB::raw("TRIM(COALESCE(MIN(p.placa), '')) as placa"),
                DB::raw("TRIM(COALESCE(MIN(p.colorStyle), '')) as color"),
                DB::raw('MAX(f.id) as factura_id'),
            ]);

        // Las ventas directas a las que se les eligio camion cuentan como un
        // pedido mas de ese camion, ya cobrado, con el color de zona de su
        // ultima asignacion.
        $colores = collect();
        $directas = $this->enVentana(Factura::whereNull('pedido_nro'), $request)
            ->where('estado', '<>', 'ANULADO')
            ->whereRaw("TRIM(COALESCE(placa, '')) <> ''")
            // La venta directa no tiene tipo de pedido: filtrando por tipo no entra.
            ->when($pedidoTipo, function ($q) {
                $q->whereRaw('1 = 0');
            })
            ->get(['id', 'placa'])
            ->map(function ($f) use ($colores, $hasta) {
                $placa = trim($f->placa);
                if (!$colores->has($placa)) {
                    $colores->put($placa, CargaCamion::colorDeZona($placa, $hasta)['colorStyle'] ?? '');
                }
                return (object) ['placa' => $placa, 'color' => (string) $colores->get($placa), 'factura_id' => $f->id];
            });
        // Que comprobantes ya tienen la canasta revisada por el caminero. La
        // venta directa con camion tambien la revisa el caminero.
        $pedidos = $pedidos->concat($directas);
        $revisados = $this->cargaRevisada($pedidos->pluck('factura_id')->filter()->all());

        return $pedidos->groupBy(function ($pedido) {
            return $pedido->placa !== '' ? $pedido->placa : 'SIN';
        })->map(function ($grupo, $placa) use ($revisados) {
            return [
                'placa'       => $placa,
                'color'       => (string) optional($grupo->firstWhere('color', '!=', ''))->color,
                'total'       => $grupo->count(),
                'facturados'  => $grupo->whereNotNull('factura_id')->count(),
                'verificados' => $grupo->filter(function ($pedido) use ($revisados) {
                    return $pedido->factura_id && isset($revisados[$pedido->factura_id]);
                })->count(),
            ];
        })->values();
    }

    /**
     * De esos comprobantes, los que tienen la canasta revisada (verificada u
     * observada) y sin cambios despues: el mismo criterio que la columna
     * Carga (estadoCarga). Devuelve [factura_id => true].
     */
    private function cargaRevisada(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $lineas = DB::table('factura_detalles')
            ->whereIn('factura_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('factura_id')
            ->pluck(DB::raw('COUNT(*) as n'), 'factura_id');

        return DB::table('carga_verificaciones as v')
            ->join('facturas as f', 'f.id', '=', 'v.factura_id')
            ->whereIn('v.factura_id', $ids)
            ->where('v.verificado', 1)
            ->get(['v.factura_id', 'v.items_esperados', 'v.total_esperado', 'f.total'])
            ->filter(function ($marca) use ($lineas) {
                // Si despues del visto bueno cambiaron la venta, la canasta ya
                // no es la que se reviso: vuelve a contar como pendiente.
                return (int) $marca->items_esperados === (int) $lineas->get($marca->factura_id, 0)
                    && abs((float) $marca->total_esperado - (float) $marca->total) <= 0.01;
            })
            ->mapWithKeys(function ($marca) {
                return [$marca->factura_id => true];
            })
            ->all();
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

        return (new CargaCamion())->estado($datos['fecha'], trim($datos['camion']))
            // La pantalla avisa distinto si la carga sin verificar frena o no.
            + ['bloquea_impresion' => (bool) config('facturacion.exigir_carga_verificada')];
    }

    /**
     * Caja aprueba de una vez la carga entera de un camion, sin esperar a que
     * el caminero la revise canasta por canasta. Queda a nombre de quien la
     * aprobo; lo observado por el caminero no se pisa.
     */
    public function aprobarCarga(Request $request)
    {
        if (!$request->user()->can('facturacionAprobarCarga')) {
            return response()->json(['message' => 'No tiene permiso para aprobar la carga del camión'], 403);
        }

        $datos = $request->validate([
            'fecha' => 'required|date',
            'camion' => 'required|string|max:100',
        ]);

        $fecha = date('Y-m-d', strtotime($datos['fecha']));
        $placa = trim($datos['camion']);
        $usuario = $request->user();

        $servicio = new CargaCamion();
        $marcadas = $servicio->verificarTodo(
            $fecha, $placa, $usuario->CodAut, trim($usuario->Nombre1 . ' ' . $usuario->App1)
        );

        return [
            'message' => $marcadas > 0
                ? 'Camión ' . $placa . ' aprobado: ' . $marcadas . ' canastas'
                : 'El camión ' . $placa . ' no tenía canastas por aprobar',
            'carga' => $servicio->estado($fecha, $placa),
        ];
    }

    /**
     * Caja marca la canasta de un solo comprobante como revisada (o la vuelve
     * a sin revisar), sin esperar al caminero ni aprobar el camion entero.
     */
    public function marcarCarga(Request $request, $id)
    {
        if (!$request->user()->can('facturacionAprobarCarga')) {
            return response()->json(['message' => 'No tiene permiso para aprobar la carga'], 403);
        }

        $request->validate(['verificado' => 'required|boolean']);

        $factura = Factura::find($id);
        if (!$factura) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }

        $verificado = $request->boolean('verificado');
        $error = $this->aplicarMarcaCarga($factura, $verificado, $request->user());
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        return [
            'message' => $verificado
                ? 'Canasta #' . $factura->id . ' marcada como revisada'
                : 'Canasta #' . $factura->id . ' vuelve a sin revisar',
        ];
    }

    /**
     * Lo mismo que marcarCarga pero sobre los comprobantes marcados en la
     * grilla. Lo que no se puede marcar (anulado, sin camion, observado) se
     * salta y se informa, sin frenar al resto.
     */
    public function marcarCargaVarias(Request $request)
    {
        if (!$request->user()->can('facturacionAprobarCarga')) {
            return response()->json(['message' => 'No tiene permiso para aprobar la carga'], 403);
        }

        $request->validate(['verificado' => 'required|boolean']);
        $ids = $this->idsSeleccionados($request);
        if (empty($ids)) {
            return response()->json(['message' => 'No hay comprobantes seleccionados'], 422);
        }
        if (count($ids) > self::MAX_LOTE) {
            return response()->json(['message' => 'Se pueden marcar hasta ' . self::MAX_LOTE . ' comprobantes a la vez'], 422);
        }

        $verificado = $request->boolean('verificado');
        $usuario = $request->user();
        $marcadas = 0;
        $saltadas = [];

        foreach (Factura::whereIn('id', $ids)->orderBy('id')->get() as $factura) {
            $error = $this->aplicarMarcaCarga($factura, $verificado, $usuario, true);
            if ($error) {
                $saltadas[] = '#' . $factura->id;
            } else {
                $marcadas++;
            }
        }

        $mensaje = $marcadas . ($marcadas === 1 ? ' canasta ' : ' canastas ')
            . ($verificado ? 'marcadas como revisadas' : 'vuelven a sin revisar');
        if (!empty($saltadas)) {
            $mensaje .= ' · no se tocaron ' . count($saltadas) . ' (anuladas, sin camión u observadas): '
                . implode(', ', array_slice($saltadas, 0, 10)) . (count($saltadas) > 10 ? '…' : '');
        }

        return ['message' => $mensaje, 'marcadas' => $marcadas, 'saltadas' => count($saltadas)];
    }

    /**
     * Graba la revision de la canasta de un comprobante. Devuelve el motivo si
     * no se pudo, o null. Con $respetarObservadas no se pisa lo que el caminero
     * dejo observado: en la marca masiva se perderia su nota sin que nadie la lea.
     */
    private function aplicarMarcaCarga(Factura $factura, $verificado, $usuario, $respetarObservadas = false)
    {
        if ($factura->estado === 'ANULADO') {
            return 'El comprobante está anulado';
        }

        $placa = $this->camionDeFactura($factura);
        if ($placa === '') {
            return 'El comprobante no sale en ningún camión';
        }

        $fecha = $factura->fecha instanceof \DateTimeInterface
            ? $factura->fecha->format('Y-m-d')
            : substr((string) $factura->fecha, 0, 10);

        $servicio = new CargaCamion();
        $comprobante = $servicio->comprobante($fecha, $placa, $factura->id);
        if (!$comprobante) {
            return 'El comprobante no aparece en la carga del camión ' . $placa;
        }

        if ($respetarObservadas) {
            $observada = DB::table('carga_verificaciones')
                ->where('factura_id', $factura->id)
                ->where('observado', 1)
                ->exists();
            if ($observada) {
                return 'La canasta está observada por el caminero';
            }
        }

        DB::table('carga_verificaciones')->updateOrInsert(
            ['factura_id' => $factura->id],
            $servicio->fila(
                $fecha, $placa, $comprobante, $verificado, null, false,
                $usuario->CodAut, trim($usuario->Nombre1 . ' ' . $usuario->App1)
            )
        );

        return null;
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
        // Mientras la verificacion esta a prueba no frena la impresion.
        if (!config('facturacion.exigir_carga_verificada')) {
            return null;
        }

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
        // Mientras la verificacion esta a prueba no frena la impresion.
        if (!config('facturacion.exigir_carga_verificada')) {
            return [];
        }

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
        // La venta directa sale en el camion que caja le eligio.
        if (!$factura->pedido_nro) {
            return trim((string) $factura->getRawOriginal('placa'));
        }

        $pedido = DB::table('tbpedidos')
            ->whereNull('tbpedidos.deleted_at')
            ->where('NroPed', $factura->pedido_nro)
            ->whereRaw(TipoPedido::sql('') . ' = ?', [strtoupper(trim((string) $factura->pedido_tipo))])
            ->where('bonificacion', 0)
            ->first([DB::raw("TRIM(COALESCE(placa, '')) as placa")]);

        return $pedido->placa ?? '';
    }

    /**
     * La zona de reparto del pedido con su color, la que despacho le asigno al
     * armarlo. Es la misma marca de color con la que se arma la canasta, asi
     * que en el papel se ve a que zona va sin leer nada. Null si la venta no
     * salio de un pedido o el pedido no tiene color.
     */
    private function zonaDeFactura(Factura $factura)
    {
        if (!$factura->pedido_nro) {
            // Venta directa con camion: el color de su ultima asignacion.
            $zona = CargaCamion::colorDeZona($factura->getRawOriginal('placa'), optional($factura->fecha)->format('Y-m-d'));
            $estilo = $zona['colorStyle'] ?? '';
        } else {
            $estilo = $this->estiloDelPedido($factura);
        }

        return $this->zonaDeEstilo($estilo);
    }

    private function estiloDelPedido(Factura $factura)
    {
        return DB::table('tbpedidos')
            ->whereNull('tbpedidos.deleted_at')
            ->where('NroPed', $factura->pedido_nro)
            ->whereRaw(TipoPedido::sql('') . ' = ?', [strtoupper(trim((string) $factura->pedido_tipo))])
            ->where('bonificacion', 0)
            ->value('colorStyle');
    }

    /** "background-color: #F5EE17" -> ['zona' => 'NORTE', 'hex' => '#F5EE17'], o null. */
    private function zonaDeEstilo($estilo)
    {
        if (!preg_match('/#[0-9a-f]{6}/i', (string) $estilo, $m)) {
            return null;
        }
        $hex = strtoupper($m[0]);

        // El pedido guarda solo el estilo; el nombre sale de la tabla de colores.
        $zona = DB::table('colores')
            ->whereNull('deleted_at')
            ->where('colorStyle', 'like', '%' . $hex . '%')
            ->value('zona');

        return ['zona' => trim((string) $zona), 'hex' => $hex];
    }

    /**
     * La celda Camion del papel: la placa y, al lado, un cuadro pintado con el
     * color de la zona. Sin color queda solo la placa, como antes.
     */
    private function celdaCamion(Factura $factura, $placa)
    {
        $camion = "<span class='et'>Camión</span><br><b>" . e($placa ?: '—') . '</b>';

        $zona = $this->zonaDeFactura($factura);
        if (!$zona) {
            return $camion;
        }

        // Sobre los colores claros (amarillo, rosado) el texto blanco no se lee.
        [$r, $g, $b] = sscanf($zona['hex'], '#%02x%02x%02x');
        $texto = ($r * 299 + $g * 587 + $b * 114) / 1000 > 150 ? '#222' : '#fff';

        // dompdf no acomoda bien bloques en linea: placa y cuadro van en una
        // tablita para que queden uno al lado del otro.
        return "<table style='width:100%; border-collapse:collapse'><tr>
            <td style='padding:0; border:0; vertical-align:top'>$camion</td>
            <td style='width:52%; padding:4px 3px; border:1px solid #444;
                       background-color:{$zona['hex']}; color:$texto; text-align:center;
                       font-size:9px; font-weight:bold'>" . e($zona['zona'] ?: '') . "</td>
        </tr></table>";
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

        if ($factura->estado === 'ANULADO') {
            return response()->json(['message' => self::NO_IMPRIME_ANULADO], 422);
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
                DB::raw(str_replace('{p}', 'p', self::UNIDAD_VENTA_SQL) . ' as unidad'),
                DB::raw('TRIM(g.Descripcion) as grupo'),
                DB::raw('UPPER(TRIM(p.tipo)) as tipo'),
                'p.imagen',
                'p.Precio as precio',
                // Lo usa la pantalla de compras para proponer el costo.
                'p.precio_compra as costo',
                // El Precio 2 de la venta. Va aparte de "costo": si se arma con
                // ese alias la venta ofrece el precio de compra como Precio 2.
                'p.Precio_Costo',
                'p.Precio3', 'p.Precio4', 'p.Precio5', 'p.Precio6', 'p.Precio7', 'p.Precio8',
                'p.Precio9', 'p.Precio10', 'p.Precio11', 'p.Precio12', 'p.Precio13',
                // El alias no puede llamarse "stock": tbproductos ya tiene una
                // columna asi y el ORDER BY resolveria a esa, no a esta.
                // La existencia vive en tbproductos.stock_actual (ver moverStock).
                DB::raw('COALESCE(p.stock_actual, 0) as existencia'),
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
            $p->precios = $this->listaPrecios($p, ['Precio 1' => $p->precio]);
            unset($p->Precio_Costo);
            foreach (self::PRECIOS_LISTA as $columna) {
                unset($p->$columna);
            }

            $p->precio = round((float) $p->precio, 2);
            $p->costo = round((float) $p->costo, 2);
            $p->stock = round((float) $p->existencia, 3);
            unset($p->existencia);

            // En venta directa el pollo, cerdo y res tambien se pesan en canastillos.
            $p->con_canastillos = in_array($p->tipo, FacturaDetalle::TIPOS_CON_CANASTILLOS, true);
            $p->kg_canastillo = FacturaDetalle::KG_CANASTILLO;

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
            // La pantalla filtra por tipo de producto (Productos > Tipo); el
            // tipo de pedido queda opcional para quien lo siga mandando.
            'tipo' => 'nullable|' . TipoPedido::regla(),
            'tipo_producto' => 'nullable|integer',
            'buscar' => 'nullable|string|max:100',
        ]);

        return $this->pedidosDelDia(
            $datos['fecha'], $datos['tipo'] ?? null, $datos['buscar'] ?? '', $datos['tipo_producto'] ?? null
        );
    }

    /**
     * Codigos de los productos de un tipo administrable (Productos > Tipo).
     * Se buscan aparte para no cruzar tbpedidos con tbproductos por TRIM
     * dentro de la consulta agrupada.
     */
    private function codigosTipoProducto($tipoProducto)
    {
        return DB::table('tbproductos')
            ->where('tipo_producto_id', $tipoProducto)
            ->pluck(DB::raw('TRIM(cod_prod) as cod'))
            ->filter(function ($c) { return $c !== ''; })
            ->unique()->values()->all();
    }

    /**
     * Lo que lista la pantalla de pedidos por facturar para un dia de entrega
     * y un tipo. Lo usan la pantalla y el Excel del dia, que deben coincidir.
     *
     * Sin $tipo salen todos los tipos de pedido. Con $tipoProducto solo los
     * pedidos que traen al menos un producto de ese tipo; la tarjeta sigue
     * siendo el pedido entero (NroPed + tipo de pedido), que es lo que se
     * factura.
     */
    private function pedidosDelDia($fecha, $tipo, $buscar = '', $tipoProducto = null)
    {
        $datos = ['fecha' => $fecha, 'tipo' => $tipo, 'buscar' => $buscar];
        $clave = function ($nro, $tipoPedido) {
            return $nro . '|' . strtoupper(trim((string) $tipoPedido));
        };

        $query = DB::table('tbpedidos as p')
            ->whereNull('p.deleted_at')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'p.idCli')
            ->leftJoin('personal as v', 'v.CodAut', '=', 'p.CIfunc')
            // Una venta anulada no cuenta como emitida: el pedido vuelve a la
            // cola para cobrarlo de nuevo, y por eso solo puede engancharse un
            // comprobante vigente por pedido.
            ->leftJoin('facturas as f', function ($join) {
                $join->on('f.pedido_nro', '=', 'p.NroPed')
                    ->on(DB::raw('UPPER(TRIM(f.pedido_tipo))'), '=', DB::raw(TipoPedido::sql('p')))
                    ->whereNull('f.deleted_at')
                    ->where('f.estado', '<>', 'ANULADO');
            })
            // La lista va por dia de entrega: es el dia en que se hace la venta.
            ->where('p.fecha_entrega', $datos['fecha'])
            ->when($tipo, function ($q) use ($tipo) {
                $q->whereRaw(TipoPedido::sql('p') . ' = ?', [$tipo]);
            })
            // Un pedido en CREADO todavia lo esta armando el preventista: solo
            // se factura lo que ya fue enviado.
            ->whereRaw("UPPER(TRIM(p.estado)) = 'ENVIADO'")
            ->where('p.bonificacion', 0);

        if ($tipoProducto) {
            $codigos = $this->codigosTipoProducto($tipoProducto);
            if (empty($codigos)) {
                return collect();
            }
            // Todas las filas del pedido pasan o ninguna: asi los totales y
            // el conteo de productos siguen siendo los del pedido completo.
            $query->whereExists(function ($sub) use ($codigos) {
                $sub->from('tbpedidos as p2')->selectRaw('1')
                    ->whereColumn('p2.NroPed', 'p.NroPed')
                    ->whereRaw(TipoPedido::sql('p2') . ' = ' . TipoPedido::sql('p'))
                    ->whereNull('p2.deleted_at')
                    ->whereRaw("UPPER(TRIM(p2.estado)) = 'ENVIADO'")
                    ->where('p2.bonificacion', 0)
                    ->whereIn(DB::raw('TRIM(p2.cod_prod)'), $codigos);
            });
        }

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
                'p.NroPed', DB::raw(TipoPedido::sql('p')), 'f.id', 'f.tipo_comprobante',
                'f.fecha', 'f.nit', 'f.tipo_pago',
            ])
            ->orderByRaw('CASE WHEN f.id IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc('p.NroPed')
            ->get([
                'p.NroPed as nro_pedido',
                DB::raw(TipoPedido::sqlAgrupado('p') . ' as tipo'),
                DB::raw('MIN(p.fecha) as fecha'),
                DB::raw('MIN(p.fecha_entrega) as fecha_entrega'),
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
                'f.id as factura_id', 'f.tipo_comprobante as comprobante_emitido', 'f.tipo_pago as pago_emitido',
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
            ->whereNull('p.deleted_at')
            ->leftJoin('tbproductos as pr', function ($join) {
                $join->on(DB::raw('TRIM(pr.cod_prod)'), '=', DB::raw('TRIM(p.cod_prod)'));
            })
            // El tipo administrable (Productos > Tipo) distingue en la tarjeta
            // embutido, huevo, PET... dentro de un mismo pedido.
            ->leftJoin('tipos_producto as tp', 'tp.id', '=', 'pr.tipo_producto_id')
            ->whereIn('p.NroPed', $numeros)
            ->when($tipo, function ($q) use ($tipo) {
                $q->whereRaw(TipoPedido::sql('p') . ' = ?', [$tipo]);
            })
            ->whereRaw("UPPER(TRIM(p.estado)) = 'ENVIADO'")
            ->where('p.bonificacion', 0)
            ->orderBy('p.codAut')
            ->get([
                'p.NroPed as nro_pedido',
                DB::raw(TipoPedido::sql('p') . ' as tipo_pedido'),
                DB::raw('TRIM(p.cod_prod) as cod_prod'),
                DB::raw("COALESCE(NULLIF(TRIM(pr.Producto), ''), CONCAT('Producto ', TRIM(p.cod_prod))) as nombre"),
                DB::raw('COALESCE(p.Cant, 0) as cantidad'),
                DB::raw('COALESCE(p.precio, 0) as precio'),
                'tp.nombre as tipo_nombre',
                'tp.color as tipo_color',
            ])
            ->groupBy(function ($item) use ($clave) {
                return $clave($item->nro_pedido, $item->tipo_pedido);
            });

        $filasPedido = DB::table('tbpedidos')->whereNull('tbpedidos.deleted_at')->whereIn('NroPed', $numeros)
            ->when($tipo, function ($q) use ($tipo) {
                $q->whereRaw(TipoPedido::sql('') . ' = ?', [$tipo]);
            })
            ->whereRaw("UPPER(TRIM(estado)) = 'ENVIADO'")
            ->where('bonificacion', 0)
            ->orderBy('codAut')
            ->get(['tbpedidos.*', DB::raw(TipoPedido::sql('') . ' as tipo_pedido')])
            ->groupBy(function ($fila) use ($clave) {
                return $clave($fila->NroPed, $fila->tipo_pedido);
            });

        // Los que ya se habian cobrado y se anularon vuelven a salir como
        // pendientes: la tarjeta lo avisa para que el cajero sepa que al
        // entrar va a encontrar recuperado lo de la venta dada de baja.
        $anuladas = Factura::when($tipo, function ($q) use ($tipo) { $q->where('pedido_tipo', $tipo); })
            ->where('estado', 'ANULADO')
            ->whereIn('pedido_nro', $numeros)
            ->orderBy('id')
            ->get(['id', 'pedido_nro', 'pedido_tipo', 'total'])
            ->keyBy(function ($f) use ($clave) { return $clave($f->pedido_nro, $f->pedido_tipo); });

        // Los guardados sin finalizar: todavia no son venta ni bajaron stock.
        $borradores = DB::table('pedido_borradores')
            ->when($tipo, function ($q) use ($tipo) { $q->where('pedido_tipo', $tipo); })
            ->whereIn('pedido_nro', $numeros)
            ->get(['pedido_nro', 'pedido_tipo', 'total', 'usuario', 'updated_at'])
            ->keyBy(function ($b) use ($clave) { return $clave($b->pedido_nro, $b->pedido_tipo); });

        return $pedidos->map(function ($pedido) use ($items, $filasPedido, $anuladas, $borradores, $clave) {
            $llave = $clave($pedido->nro_pedido, $pedido->tipo);
            $borrador = $pedido->factura_id ? null : $borradores->get($llave);
            $pedido->borrador = $borrador ? [
                'total'    => (float) $borrador->total,
                'usuario'  => $borrador->usuario,
                'guardado' => substr((string) $borrador->updated_at, 0, 16),
            ] : null;
            $pedido->items = ($items->get($llave) ?? collect())
                ->map(function ($item) {
                    unset($item->tipo_pedido);
                    $item->cantidad = (float) $item->cantidad;
                    $item->precio = (float) $item->precio;
                    $item->total = round($item->cantidad * $item->precio, 2);
                    return $item;
                })->values();
            $pedido->detalle_pollo = $this->detallePollo($filasPedido->get($llave) ?? collect());
            // keyBy se queda con la ultima, que es la anulacion mas reciente.
            $anulada = $pedido->factura_id ? null : $anuladas->get($llave);
            $pedido->anulada_id = $anulada->id ?? null;
            $pedido->anulada_total = $anulada ? (float) $anulada->total : null;
            return $pedido;
        });
    }

    /** Hojas del Excel de pedidos del dia, en orden: tipo => [nombre, color]. */
    const HOJAS_EXCEL_PEDIDOS = [
        'POLLO'  => ['Pollo', 'E65100'],
        'NORMAL' => ['Embutidos', 'C62828'],
        'CERDO'  => ['Cerdo', 'AD1457'],
        'RES'    => ['Res', '4E342E'],
        'PODIUM' => ['Podium', 'F9A825'],
        'HUEVO'  => ['Huevo', 'FF8F00'],
    ];

    /**
     * Todos los pedidos de un dia de entrega en un Excel: una hoja de resumen
     * y una por tipo. Cada pedido va en una fila con su cliente y debajo sus
     * productos, agrupados para poder plegarlos.
     */
    public function pedidosExcel(Request $request)
    {
        $datos = $request->validate(['fecha' => 'required|date']);
        $fecha = $datos['fecha'];
        $dia = date('d/m/Y', strtotime($fecha));
        $empresa = config('siat.emisor')['nombre'] ?? 'Sofia';

        $libro = new Spreadsheet();
        $libro->getProperties()->setCreator($empresa)->setTitle('Pedidos ' . $dia);
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $resumen = $libro->getActiveSheet();
        $resumen->setTitle('Resumen');

        $porTipo = [];
        foreach (self::HOJAS_EXCEL_PEDIDOS as $tipo => list($nombre, $color)) {
            $porTipo[$tipo] = $this->pedidosDelDia($fecha, $tipo);
            $hoja = $libro->createSheet();
            $hoja->setTitle($nombre);
            $hoja->getTabColor()->setRGB($color);
            $this->hojaPedidosExcel($hoja, $porTipo[$tipo], 'PEDIDOS DE ' . mb_strtoupper($nombre), $color, $dia, $empresa);
        }
        $this->hojaResumenPedidos($resumen, $porTipo, $dia, $empresa);
        $libro->setActiveSheetIndex(0);

        $writer = new Xlsx($libro);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'pedidos_' . $fecha . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Titulo, subtitulo y linea de cifras arriba de cada hoja. */
    private function cabeceraHojaExcel($hoja, $titulo, $subtitulo, $cifras, $ultima, $color)
    {
        $hoja->mergeCells('A1:' . $ultima . '1')->setCellValue('A1', $titulo);
        $hoja->mergeCells('A2:' . $ultima . '2')->setCellValue('A2', $subtitulo);
        $hoja->mergeCells('A3:' . $ultima . '3')->setCellValue('A3', $cifras);
        $hoja->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $hoja->getRowDimension(1)->setRowHeight(28);
        $hoja->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('666666');
        $hoja->getStyle('A3')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('37474F');
    }

    /** Encabezado de tabla: blanco sobre gris azulado, como los reportes PDF. */
    private function encabezadoTablaExcel($hoja, $rango)
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '37474F']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
    }

    private function hojaPedidosExcel($hoja, $pedidos, $titulo, $color, $dia, $empresa)
    {
        // [encabezado, ancho, formato numerico]
        $columnas = [
            ['Nro pedido', 10, null], ['Hora', 7, null], ['Cliente', 34, null], ['NIT / CI', 13, null],
            ['Preventista', 24, null], ['Camión', 11, null], ['Comprob.', 9, null], ['Pago', 10, null],
            ['Estado', 19, null], ['Código', 10, null], ['Producto', 34, null],
            ['Cantidad', 10, '#,##0.###'], ['Unidad', 8, null], ['Precio Bs', 10, '#,##0.00'],
            ['Subtotal Bs', 12, '#,##0.00'], ['Total pedido Bs', 14, '#,##0.00'], ['Observaciones', 50, null],
        ];
        $ultima = Coordinate::stringFromColumnIndex(count($columnas));

        $facturados = $pedidos->filter(function ($p) { return $p->factura_id; })->count();
        $total = $pedidos->sum(function ($p) { return (float) $p->total_pedido; });
        $this->cabeceraHojaExcel(
            $hoja, $titulo,
            $empresa . ' · Entrega del ' . $dia . ' · generado el ' . date('d/m/Y H:i'),
            'Pedidos: ' . $pedidos->count() . '   ·   Facturados: ' . $facturados
                . '   ·   Pendientes: ' . ($pedidos->count() - $facturados)
                . '   ·   Total: Bs ' . number_format($total, 2, '.', ','),
            $ultima, $color
        );

        foreach ($columnas as $i => list($etiqueta, $ancho)) {
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $hoja->setCellValue($letra . '5', $etiqueta);
            $hoja->getColumnDimension($letra)->setWidth($ancho);
        }
        $this->encabezadoTablaExcel($hoja, 'A5:' . $ultima . '5');
        $hoja->getRowDimension(5)->setRowHeight(22);

        $fila = 6;
        if ($pedidos->isEmpty()) {
            $hoja->mergeCells('A6:' . $ultima . '6')->setCellValue('A6', 'No hay pedidos para esta fecha');
            $hoja->getStyle('A6')->getFont()->setItalic(true)->getColor()->setRGB('888888');
            $hoja->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            return;
        }

        // Los productos van debajo de su pedido y el grupo se pliega desde arriba.
        $hoja->setShowSummaryBelow(false);
        $filasPedido = [];
        foreach ($pedidos as $pedido) {
            $detalle = $pedido->detalle_pollo ?? ['observaciones' => [], 'productos' => [], 'datos' => []];
            $notas = collect($detalle['observaciones'])->merge(
                collect($detalle['datos'])
                    // Hora, pago y factura ya tienen su columna.
                    ->filter(function ($d) {
                        return $d['valor'] !== '' && $d['valor'] !== '—'
                            && !in_array($d['etiqueta'], ['Hora pedido', 'Pago', 'Factura'], true);
                    })
                    ->map(function ($d) { return $d['etiqueta'] . ': ' . $d['valor']; })
            )->implode(' · ');
            $items = $pedido->items ?? collect();
            $productos = collect($detalle['productos']);

            $hoja->fromArray([
                $pedido->nro_pedido, substr((string) $pedido->fecha, 11, 5), $pedido->cliente ?: 'Sin cliente',
                (string) ($pedido->nit ?: ''), $pedido->vendedor ?: 'Sin preventista', $pedido->placa ?: 'Sin camión',
                $this->comprobanteExcel($pedido), $this->pagoExcel($pedido), $this->estadoExcel($pedido),
                null, $this->cuantosProductos($items->count() + $productos->count()), null, null, null, null,
                (float) $pedido->total_pedido, $notas,
            ], null, 'A' . $fila, true);
            $hoja->getCell('D' . $fila)->setValueExplicit((string) ($pedido->nit ?: ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECEFF1']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '90A4AE']]],
            ]);
            $this->pintarCeldaExcel($hoja, 'F' . $fila, $this->hexColor($pedido->placa_color));
            $this->pintarCeldaExcel($hoja, 'I' . $fila, $pedido->factura_id ? '2E7D32' : ($pedido->borrador ? 'FF8F00' : 'EF6C00'));
            $this->pintarCeldaExcel($hoja, 'H' . $fila, strpos($this->pagoExcel($pedido), 'CR') === 0 ? 'D84315' : null);
            $filasPedido[] = $fila;
            $fila++;

            foreach ($items as $item) {
                $hoja->fromArray([
                    $pedido->nro_pedido, null, $pedido->cliente, null, null, null, null, null, null,
                    $item->cod_prod, $item->nombre, $item->cantidad, null, $item->precio, $item->total,
                ], null, 'A' . $fila, true);
                $hoja->getCell('J' . $fila)->setValueExplicit((string) $item->cod_prod, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $this->filaProductoExcel($hoja, $fila, $ultima, null);
                $fila++;
            }
            // Lo que cargo el preventista de pollo/cerdo/res en sus columnas propias.
            foreach ($productos as $producto) {
                $hoja->fromArray([
                    $pedido->nro_pedido, null, $pedido->cliente, null, null, null, null, null, null,
                    'Preventista', $producto['nombre'], $producto['cantidad'], $producto['unidad'],
                    $producto['precio'] ?: null, null, null, $producto['observacion'] ?: null,
                ], null, 'A' . $fila, true);
                $this->filaProductoExcel($hoja, $fila, $ultima, 'FFF3E0');
                $fila++;
            }
        }
        $ultimaDato = $fila - 1;

        $hoja->setCellValue('A' . $fila, 'TOTAL');
        $hoja->setCellValue('C' . $fila, $pedidos->count() . ' pedidos');
        $hoja->setCellValue('P' . $fila, '=SUM(P6:P' . $ultimaDato . ')');
        $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '37474F']],
        ]);

        foreach ($columnas as $i => list($etiqueta, $ancho, $formato)) {
            if ($formato) {
                $letra = Coordinate::stringFromColumnIndex($i + 1);
                $hoja->getStyle($letra . '6:' . $letra . $fila)->getNumberFormat()->setFormatCode($formato);
            }
        }
        $hoja->getStyle('A5:' . $ultima . $fila)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('BDBDBD');
        $hoja->getStyle('A6:' . $ultima . $fila)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $hoja->getStyle('Q6:Q' . $fila)->getAlignment()->setWrapText(true);
        $hoja->getStyle('C6:C' . $fila)->getAlignment()->setWrapText(true);
        $hoja->getStyle('A6:B' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle('F6:I' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $hoja->freezePane('D6');
        $hoja->setAutoFilter('A5:' . $ultima . $ultimaDato);
        $this->impresionHojaExcel($hoja, 5);
    }

    private function cuantosProductos($cantidad)
    {
        return $cantidad . ($cantidad === 1 ? ' producto' : ' productos');
    }

    /** Fila de un producto: letra gris para nro/cliente, nivel 1 del grupo. */
    private function filaProductoExcel($hoja, $fila, $ultima, $fondo)
    {
        $hoja->getStyle('A' . $fila . ':C' . $fila)->getFont()->setSize(8)->getColor()->setRGB('9E9E9E');
        if ($fondo) {
            $hoja->getStyle('J' . $fila . ':' . $ultima . $fila)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fondo);
        }
        $hoja->getStyle('K' . $fila)->getAlignment()->setIndent(1);
        $hoja->getRowDimension($fila)->setOutlineLevel(1);
    }

    private function hojaResumenPedidos($hoja, array $porTipo, $dia, $empresa)
    {
        $todos = collect($porTipo)->flatten(1);
        $this->cabeceraHojaExcel(
            $hoja, 'PEDIDOS DEL DÍA ' . $dia,
            $empresa . ' · generado el ' . date('d/m/Y H:i'),
            'Pedidos: ' . $todos->count() . '   ·   Total: Bs '
                . number_format($todos->sum(function ($p) { return (float) $p->total_pedido; }), 2, '.', ','),
            'I', '37474F'
        );

        // Por tipo de pedido.
        $hoja->fromArray(['Tipo', 'Pedidos', 'Facturados', 'Pendientes', 'Contado', 'Crédito', 'QR', 'Total Bs', '% facturado'], null, 'A5', true);
        $this->encabezadoTablaExcel($hoja, 'A5:I5');
        $fila = 6;
        foreach (self::HOJAS_EXCEL_PEDIDOS as $tipo => list($nombre, $color)) {
            $pedidos = $porTipo[$tipo];
            $pago = function ($texto) use ($pedidos) {
                return $pedidos->filter(function ($p) use ($texto) { return $this->pagoExcel($p) === $texto; })->count();
            };
            $facturados = $pedidos->filter(function ($p) { return $p->factura_id; })->count();
            $hoja->fromArray([
                $nombre, $pedidos->count(), $facturados, $pedidos->count() - $facturados,
                $pago('CONTADO'), $pago('CRÉDITO'), $pago('QR'),
                round($pedidos->sum(function ($p) { return (float) $p->total_pedido; }), 2),
                '=IF(B' . $fila . '=0,0,C' . $fila . '/B' . $fila . ')',
            ], null, 'A' . $fila, true);
            $this->pintarCeldaExcel($hoja, 'A' . $fila, $color);
            $fila++;
        }
        $hoja->setCellValue('A' . $fila, 'TOTAL');
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H'] as $letra) {
            $hoja->setCellValue($letra . $fila, '=SUM(' . $letra . '6:' . $letra . ($fila - 1) . ')');
        }
        $hoja->setCellValue('I' . $fila, '=IF(B' . $fila . '=0,0,C' . $fila . '/B' . $fila . ')');
        $this->filaTotalExcel($hoja, 'A' . $fila . ':I' . $fila);
        $hoja->getStyle('H6:H' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle('I6:I' . $fila)->getNumberFormat()->setFormatCode('0%');
        $hoja->getStyle('A5:I' . $fila)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BDBDBD');
        $hoja->getStyle('B6:I' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle('H6:H' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Por camion: cuantos pedidos de cada tipo lleva y por cuanto.
        $inicio = $fila + 3;
        $hoja->setCellValue('A' . ($inicio - 1), 'PEDIDOS POR CAMIÓN');
        $hoja->getStyle('A' . ($inicio - 1))->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('37474F');
        $nombres = collect(self::HOJAS_EXCEL_PEDIDOS)->map(function ($h) { return $h[0]; })->values()->all();
        $hoja->fromArray(array_merge(['Camión'], $nombres, ['Total pedidos', 'Total Bs']), null, 'A' . $inicio, true);
        $this->encabezadoTablaExcel($hoja, 'A' . $inicio . ':H' . $inicio);

        $camiones = [];
        foreach ($porTipo as $tipo => $pedidos) {
            foreach ($pedidos as $p) {
                $placa = trim((string) $p->placa) ?: 'Sin camión';
                if (!isset($camiones[$placa])) {
                    $camiones[$placa] = ['color' => null, 'tipos' => array_fill_keys(array_keys(self::HOJAS_EXCEL_PEDIDOS), 0), 'total' => 0];
                }
                $camiones[$placa]['color'] = $camiones[$placa]['color'] ?: $this->hexColor($p->placa_color);
                $camiones[$placa]['tipos'][$tipo]++;
                $camiones[$placa]['total'] += (float) $p->total_pedido;
            }
        }
        uksort($camiones, function ($a, $b) {
            if ($a === 'Sin camión') return 1;
            if ($b === 'Sin camión') return -1;
            return strcmp($a, $b);
        });

        $fila = $inicio + 1;
        foreach ($camiones as $placa => $camion) {
            $hoja->fromArray(array_merge(
                [$placa], array_values($camion['tipos']),
                ['=SUM(B' . $fila . ':F' . $fila . ')', round($camion['total'], 2)]
            ), null, 'A' . $fila, true);
            $this->pintarCeldaExcel($hoja, 'A' . $fila, $camion['color']);
            $fila++;
        }
        $hoja->setCellValue('A' . $fila, 'TOTAL');
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H'] as $letra) {
            $hoja->setCellValue($letra . $fila, '=SUM(' . $letra . ($inicio + 1) . ':' . $letra . ($fila - 1) . ')');
        }
        $this->filaTotalExcel($hoja, 'A' . $fila . ':H' . $fila);
        $hoja->getStyle('H' . ($inicio + 1) . ':H' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle('A' . $inicio . ':H' . $fila)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BDBDBD');
        $hoja->getStyle('B' . ($inicio + 1) . ':G' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $hoja->getColumnDimension('A')->setWidth(18);
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'I'] as $letra) {
            $hoja->getColumnDimension($letra)->setWidth(13);
        }
        $hoja->getColumnDimension('H')->setWidth(15);
        $this->impresionHojaExcel($hoja, null);
    }

    private function filaTotalExcel($hoja, $rango)
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '37474F']],
        ]);
    }

    /** Carta horizontal, a lo ancho de una pagina, con el encabezado repetido. */
    private function impresionHojaExcel($hoja, $filaEncabezado)
    {
        $pagina = $hoja->getPageSetup();
        $pagina->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)->setFitToHeight(0);
        if ($filaEncabezado) {
            $pagina->setRowsToRepeatAtTopByStartAndEnd($filaEncabezado, $filaEncabezado);
        }
        $hoja->getPageMargins()->setTop(0.4)->setBottom(0.5)->setLeft(0.3)->setRight(0.3);
        $hoja->getHeaderFooter()->setOddFooter('&L&8&A&R&8Página &P de &N');
    }

    /** Fondo de color con letra blanca o negra segun lo claro que sea. */
    private function pintarCeldaExcel($hoja, $celda, $hex)
    {
        if (!$hex) return;
        $valor = hexdec($hex);
        $luz = (0.299 * (($valor >> 16) & 255) + 0.587 * (($valor >> 8) & 255) + 0.114 * ($valor & 255)) / 255;
        $hoja->getStyle($celda)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $luz > 0.6 ? '212121' : 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $hex]],
        ]);
    }

    /** colorStyle viene como 'background-color: #RRGGBB'. */
    private function hexColor($estilo)
    {
        return preg_match('/#([0-9a-f]{6})/i', (string) $estilo, $m) ? strtoupper($m[1]) : null;
    }

    // Mismas reglas que las tarjetas de PedidosFactura.vue: antes de cobrar
    // manda lo que pidio el preventista; ya emitido, lo que salio.
    private function comprobanteExcel($pedido)
    {
        if ($pedido->factura_id) return $pedido->comprobante_emitido === 'FACTURA' ? 'Factura' : 'Recibo';
        return strtoupper(trim((string) $pedido->fact)) === 'SI' ? 'Factura' : 'Recibo';
    }

    private function pagoExcel($pedido)
    {
        $valor = strtoupper((string) ($pedido->factura_id ? $pedido->pago_emitido : $pedido->pago));
        if (strpos($valor, 'CR') !== false) return 'CRÉDITO';
        if (strpos($valor, 'QR') !== false) return 'QR';
        return 'CONTADO';
    }

    private function estadoExcel($pedido)
    {
        if ($pedido->factura_id) return 'LISTO · ' . $pedido->comprobante_emitido . ' #' . $pedido->factura_id;
        if (!empty($pedido->borrador)) return 'GUARDADO';
        if (!empty($pedido->anulada_id)) return 'RECUPERABLE';
        return 'PENDIENTE';
    }

    /** Cabecera y productos editables de un pedido concreto. */
    public function pedido(Request $request, $nroPedido)
    {
        $datos = $request->validate([
            'tipo' => 'required|' . TipoPedido::regla(),
        ]);

        $cabecera = DB::table('tbpedidos as p')
            ->whereNull('p.deleted_at')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'p.idCli')
            ->leftJoin('personal as v', 'v.CodAut', '=', 'p.CIfunc')
            ->where('p.NroPed', $nroPedido)
            ->whereRaw(TipoPedido::sql('p') . ' = ?', [$datos['tipo']])
            ->where('p.bonificacion', 0)
            ->first([
                'p.NroPed as nro_pedido', DB::raw(TipoPedido::sql('p') . ' as tipo'),
                'p.fecha', 'p.fecha_entrega', 'p.estado', 'p.fact', 'p.pago', 'p.comentario',
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
            ->whereNull('p.deleted_at')
            ->leftJoin('tbproductos as pr', function ($join) {
                $join->on(DB::raw('TRIM(pr.cod_prod)'), '=', DB::raw('TRIM(p.cod_prod)'));
            })
            ->where('p.NroPed', $nroPedido)
            ->whereRaw(TipoPedido::sql('p') . ' = ?', [$datos['tipo']])
            ->where('p.bonificacion', 0)
            ->orderBy('p.codAut')
            ->get([
                DB::raw('TRIM(p.cod_prod) as cod_prod'),
                DB::raw("COALESCE(NULLIF(TRIM(pr.Producto), ''), CONCAT('Producto ', TRIM(p.cod_prod))) as nombre"),
                DB::raw("COALESCE(NULLIF(" . str_replace('{p}', 'pr', self::UNIDAD_VENTA_SQL) . ", ''), 'UNIDAD') as unidad"),
                'pr.imagen', DB::raw('COALESCE(p.Cant, 0) as cantidad'),
                // Unidad que eligio el preventista para lo que se vende por
                // caja (U, CAJA o KG); null en el resto.
                DB::raw("NULLIF(TRIM(p.caja), '') as caja"),
                // Peso aproximado que pidio el cliente (+-100 g); null sin rango.
                'p.rango_kg',
                DB::raw('COALESCE(p.precio, 0) as precio'),
                // La lista de precios del producto, para el select del carrito.
                DB::raw('pr.Precio as precio1'), 'pr.Precio_Costo',
                'pr.Precio3', 'pr.Precio4', 'pr.Precio5', 'pr.Precio6', 'pr.Precio7', 'pr.Precio8',
                'pr.Precio9', 'pr.Precio10', 'pr.Precio11', 'pr.Precio12', 'pr.Precio13',
            ])
            ->map(function ($item) {
                // El precio del pedido va primero aunque no este en la lista.
                $item->precios = $this->listaPrecios($item, ['Pedido' => $item->precio, 'Precio 1' => $item->precio1]);
                unset($item->precio1, $item->Precio_Costo);
                foreach (self::PRECIOS_LISTA as $columna) {
                    unset($item->$columna);
                }
                $item->cantidad = (float) $item->cantidad;
                // Lo que pidio el cliente queda aparte de lo que se entrega:
                // sirve para avisarle al cajero que la linea cambio. No se
                // guarda ni sale impreso, es solo la referencia del pedido.
                $item->cantidad_pedida = $item->cantidad;
                $item->precio = (float) $item->precio;
                // Lo que va por kilo se pesa recien al cobrar: el peso sale en
                // blanco para que el cajero escriba lo de la balanza.
                $item->peso = null;
                $item->peso_bruto = null;
                $item->canastillos = null;
                $item->total = round($item->cantidad * $item->precio, 2);
                return $item;
            });

        // Rango (kg) que pidio el cliente por producto. Va aparte de los items
        // porque el borrador o la venta anulada pueden reemplazar las lineas.
        $cabecera->rangos = $items->filter(function ($item) {
            return $item->rango_kg !== null;
        })->mapWithKeys(function ($item) {
            return [$item->cod_prod => (float) $item->rango_kg];
        });

        // Pollo, cerdo y res se pesan en canastillos: la pantalla pide bruto y
        // canastillos en vez del peso directo.
        // Si es una baja, el comprobante sale a nombre de esa cuenta.
        $cabecera->baja = $this->cuentaDeBaja($nroPedido, $datos['tipo']);

        $cabecera->con_canastillos = in_array($datos['tipo'], FacturaDetalle::TIPOS_CON_CANASTILLOS, true);
        $cabecera->kg_canastillo = FacturaDetalle::KG_CANASTILLO;

        $filasPedido = DB::table('tbpedidos')->whereNull('tbpedidos.deleted_at')->where('NroPed', $nroPedido)
            ->whereRaw(TipoPedido::sql('') . ' = ?', [$datos['tipo']])->where('bonificacion', 0)
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
        $cabecera->retorno = null;
        if ($anulada) {
            $items = $this->recuperarAnulada($items, $anulada);

            // Si el caminero habia marcado un retorno parcial sobre esa venta,
            // lo que se vuelve a cobrar es lo que el cliente se quedo.
            $retorno = $this->retornoDe($anulada->id);
            if ($retorno) {
                $items = $this->aplicarRetorno($items, $retorno);
                $cabecera->retorno = $retorno;
            }
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

        // Si el cajero ya lo habia guardado sin finalizar, se sigue desde ahi:
        // lo guardado manda sobre lo del pedido y lo de la venta anulada.
        $cabecera->borrador = null;
        $borrador = DB::table('pedido_borradores')
            ->where('pedido_nro', $nroPedido)
            ->where('pedido_tipo', $datos['tipo'])
            ->first();
        if ($borrador) {
            $guardado = json_decode($borrador->datos, true) ?: [];
            $items = collect($guardado['items'] ?? [])->map(function ($item) {
                return (object) $item;
            })->values();
            $cabecera->borrador = [
                'tipo_comprobante' => $guardado['tipo_comprobante'] ?? null,
                'tipo_pago'        => $guardado['tipo_pago'] ?? null,
                'nit'              => $guardado['nit'] ?? null,
                'observacion'      => $guardado['observacion'] ?? null,
                'usuario'          => $borrador->usuario,
                'guardado'         => substr((string) $borrador->updated_at, 0, 16),
            ];
        }

        // Cada linea con su lista de precios, para el select y para cambiar
        // todas de una vez a un mismo precio (por ejemplo, al Precio 8).
        $items = $this->completarPrecios($items, $filasPedido);

        // Existencia de cada producto: la pantalla marca lo que no alcanza y no
        // deja finalizar (store lo vuelve a validar al vender).
        $stock = DB::table('tbproductos')
            ->whereIn(DB::raw('TRIM(cod_prod)'), collect($items)->pluck('cod_prod')->map(function ($c) {
                return trim((string) $c);
            })->unique()->values())
            ->get([DB::raw('TRIM(cod_prod) as cod_prod'), DB::raw('COALESCE(stock_actual, 0) as stock')])
            ->mapWithKeys(function ($p) {
                return [$p->cod_prod => round((float) $p->stock, 3)];
            });

        return response()->json(['pedido' => $cabecera, 'items' => $items, 'stock' => $stock]);
    }

    /**
     * Guarda momentaneamente lo que se lleva cargado de un pedido.
     *
     * No crea la venta ni mueve stock, y no exige pesos completos: es para
     * seguir despues. La venta se hace recien con store (Finalizar), que
     * borra el borrador.
     */
    public function guardarBorrador(Request $request, $nroPedido)
    {
        $datos = $request->validate([
            'pedido_tipo'         => 'required|' . TipoPedido::regla(),
            'items'               => 'present|array',
            'items.*.cod_prod'    => 'required|string|max:25',
            'items.*.nombre'      => 'nullable|string|max:150',
            'items.*.unidad'      => 'nullable|string|max:20',
            'items.*.caja'        => 'nullable|string|max:20',
            'items.*.cantidad'    => 'nullable|numeric|min:0',
            'items.*.cantidad_pedida' => 'nullable|numeric|min:0',
            'items.*.peso'        => 'nullable|numeric',
            'items.*.peso_bruto'  => 'nullable|numeric|min:0',
            'items.*.canastillos' => 'nullable|integer|min:0',
            'items.*.precio'      => 'nullable|numeric|min:0',
            'items.*.recuperado'  => 'nullable|boolean',
            'items.*.retorno'     => 'nullable|boolean',
            'items.*.preparado'   => 'nullable|boolean',
            'tipo_comprobante'    => 'nullable|in:VENTA,FACTURA',
            'tipo_pago'           => 'nullable|string|max:20',
            'nit'                 => 'nullable|string|max:20',
            'observacion'         => 'nullable|string|max:255',
        ]);

        $tipo = $datos['pedido_tipo'];

        $vigente = Factura::where('pedido_nro', $nroPedido)
            ->where('pedido_tipo', $tipo)
            ->where('estado', '<>', 'ANULADO')
            ->exists();
        if ($vigente) {
            return response()->json(['message' => 'Este pedido ya fue facturado o convertido en voucher'], 422);
        }

        $items = collect($datos['items'])->map(function ($item) {
            $cantidad = (float) ($item['cantidad'] ?? 0);
            $peso = isset($item['peso']) && (float) $item['peso'] > 0 ? round((float) $item['peso'], 3) : null;
            $precio = round((float) ($item['precio'] ?? 0), 2);
            $esPeso = in_array(strtoupper(trim((string) ($item['unidad'] ?? ''))), ['KG', 'CAJA'], true);
            return [
                'cod_prod'        => trim($item['cod_prod']),
                'nombre'          => $item['nombre'] ?? '',
                'unidad'          => $item['unidad'] ?? 'UNIDAD',
                'caja'            => $item['caja'] ?? null,
                'imagen'          => null,
                'cantidad'        => $cantidad,
                'cantidad_pedida' => isset($item['cantidad_pedida']) ? (float) $item['cantidad_pedida'] : null,
                'peso'            => $peso,
                'peso_bruto'      => isset($item['peso_bruto']) ? (float) $item['peso_bruto'] : null,
                'canastillos'     => isset($item['canastillos']) ? (int) $item['canastillos'] : null,
                'precio'          => $precio,
                'total'           => round(($esPeso ? (float) $peso : $cantidad) * $precio, 2),
                'recuperado'      => !empty($item['recuperado']),
                'retorno'         => !empty($item['retorno']),
                // Ya armado en el mostrador: la pantalla lo deja bloqueado.
                'preparado'       => !empty($item['preparado']),
            ];
        })->values();

        $usuario = $request->user();
        $ahora = date('Y-m-d H:i:s');
        $existe = DB::table('pedido_borradores')
            ->where('pedido_nro', $nroPedido)->where('pedido_tipo', $tipo)->exists();

        DB::table('pedido_borradores')->updateOrInsert(
            ['pedido_nro' => $nroPedido, 'pedido_tipo' => $tipo],
            [
                'datos'      => json_encode([
                    'items'            => $items,
                    'tipo_comprobante' => $datos['tipo_comprobante'] ?? null,
                    'tipo_pago'        => $datos['tipo_pago'] ?? null,
                    'nit'              => $datos['nit'] ?? null,
                    'observacion'      => $datos['observacion'] ?? null,
                ]),
                'total'      => round($items->sum('total'), 2),
                'user_id'    => $usuario->CodAut ?? null,
                'usuario'    => trim(trim((string) ($usuario->Nombre1 ?? '')) . ' ' . trim((string) ($usuario->App1 ?? ''))),
                'updated_at' => $ahora,
            ] + ($existe ? [] : ['created_at' => $ahora])
        );

        return response()->json([
            'message' => 'Pedido guardado. Todavía no es venta: el stock se descuenta al finalizar',
        ]);
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
    /**
     * El retorno parcial que el caminero marco sobre un comprobante, con su
     * detalle y la entrega de donde sale; null si no hubo.
     */
    private function retornoDe($facturaId)
    {
        $entrega = DB::table('entregas')
            ->where('factura_id', $facturaId)
            ->orderByDesc('id')
            ->first(['id', 'estado', 'observacion', 'retorno_detalle', 'despachador', 'fecha', 'hora']);

        if (!$entrega || $entrega->estado !== 'RETORNO PARCIAL' || empty($entrega->retorno_detalle)) {
            return null;
        }

        return json_decode($entrega->retorno_detalle, true) + [
            'entrega_id'  => $entrega->id,
            'observacion' => trim((string) $entrega->observacion),
            'caminero'    => trim((string) $entrega->despachador),
            'registrado'  => $entrega->fecha . ' ' . substr((string) $entrega->hora, 0, 5),
        ];
    }

    /**
     * El recuadro del retorno parcial para el impreso Pedido vs Facturacion:
     * quien lo registro, el motivo y cada producto con lo que salio, lo que
     * el cliente se quedo y lo devuelto. Lo entregado entero no se lista.
     */
    private function bloqueRetorno(array $retorno, callable $cant)
    {
        $filas = '';
        $devueltoBs = 0;
        foreach ($retorno['items'] ?? [] as $item) {
            $original = (float) ($item['original'] ?? 0);
            $entregado = (float) ($item['entregado'] ?? 0);
            if (abs($original - $entregado) < 0.001) {
                continue;
            }
            $precio = (float) ($item['precio'] ?? 0);
            $bs = round(($original - $entregado) * $precio, 2);
            $devueltoBs += $bs;
            $unidad = !empty($item['por_peso']) ? ' kg' : '';
            $filas .= '<tr><td>' . e($item['cod_prod'] ?? '') . '</td><td>' . e($item['nombre'] ?? '') . '</td>'
                . "<td class='r'>" . $cant($original) . $unidad . '</td>'
                . "<td class='r'>" . $cant($entregado) . $unidad . '</td>'
                . "<td class='r'><b>" . $cant($original - $entregado) . $unidad . '</b></td>'
                . "<td class='r'>" . number_format($precio, 2) . '</td>'
                . "<td class='r'><b>" . number_format($bs, 2) . '</b></td></tr>';
        }

        $datos = array_filter([
            !empty($retorno['motivo']) ? '<b>Motivo:</b> ' . e($retorno['motivo']) : null,
            !empty($retorno['caminero']) ? '<b>Caminero:</b> ' . e($retorno['caminero']) : null,
            !empty($retorno['registrado']) ? '<b>Registrado:</b> ' . e(date('d/m/Y H:i', strtotime($retorno['registrado']))) : null,
            !empty($retorno['factura_original']) ? '<b>Venta original:</b> #' . (int) $retorno['factura_original'] : null,
            isset($retorno['total_original'], $retorno['total_entregado'])
                ? '<b>Total:</b> ' . number_format((float) $retorno['total_original'], 2) . ' &rarr; ' . number_format((float) $retorno['total_entregado'], 2)
                : null,
        ]);

        return "<div class='retorno'><div class='tit-retorno'>RETORNO PARCIAL: el cliente devolvió parte de lo entregado</div>"
            . "<div class='sub'>" . implode(' &middot; ', $datos) . '</div>'
            . (!empty($retorno['observacion']) ? "<div class='sub'><b>Observación:</b> " . e($retorno['observacion']) . '</div>' : '')
            . ($filas
                ? "<table><tr><th>Código</th><th>Producto</th><th class='r'>Salió</th><th class='r'>Se quedó</th>"
                    . "<th class='r'>Devuelto</th><th class='r'>P. Unit</th><th class='r'>Bs devuelto</th></tr>$filas"
                    . "<tr class='tot'><td colspan='6' class='r'>TOTAL DEVUELTO</td><td class='r'>" . number_format($devueltoBs, 2) . '</td></tr></table>'
                : '')
            . '</div>';
    }

    /**
     * Pone en cada linea lo que el cliente se quedo segun el retorno parcial.
     * Lo devuelto entero sale de la lista; el peso vuelve sin bruto ni
     * canastillos porque ya no es el que marco la balanza.
     */
    private function aplicarRetorno($items, array $retorno)
    {
        $porCodigo = collect($retorno['items'] ?? [])->keyBy('cod_prod');

        return $items->map(function ($item) use ($porCodigo) {
            $linea = $porCodigo->get(trim((string) $item->cod_prod));
            if (!$linea || abs((float) $linea['original'] - (float) $linea['entregado']) < 0.001) {
                return $item;
            }
            $entregado = (float) $linea['entregado'];
            if ($entregado <= 0) {
                return null;
            }
            if (!empty($linea['por_peso'])) {
                $item->peso = $entregado;
                $item->peso_bruto = null;
                $item->canastillos = null;
            } else {
                $item->cantidad = $entregado;
            }
            $item->total = round(($item->peso ?: $item->cantidad) * (float) $item->precio, 2);
            $item->retorno = true;
            return $item;
        })->filter()->values();
    }

    /**
     * Cuando se emite de nuevo un pedido que el camion ya habia entregado, la
     * entrega pasa al comprobante nuevo: el cobro ya se hizo en la puerta, la
     * nota no tiene que volver a salir como pendiente en la lista del caminero
     * y el recojo de ese dia tiene que seguir cuadrando (lo de un comprobante
     * anulado no sale en el recojo). Pasa con cualquier entrega, no solo con
     * un retorno parcial: tambien cuando solo se cambio el tipo de comprobante.
     *
     * Con retorno parcial ademas el monto pasa a ser el del comprobante nuevo
     * y la carga queda revisada, porque el camion ya salio con ella.
     */
    private function heredarRetorno(Factura $nueva, $usuario)
    {
        $anterior = Factura::where('pedido_nro', $nueva->pedido_nro)
            ->where('pedido_tipo', $nueva->pedido_tipo)
            ->where('estado', 'ANULADO')
            ->where('id', '<', $nueva->id)
            ->orderByDesc('id')
            ->first(['id']);

        if (!$anterior) {
            return;
        }

        // Se lee antes de mover: busca la entrega por el comprobante anterior.
        $retorno = $this->retornoDe($anterior->id);

        DB::table('entregas')->where('factura_id', $anterior->id)->update([
            'factura_id' => $nueva->id,
        ]);

        if (!$retorno) {
            return;
        }

        DB::table('entregas')->where('id', $retorno['entrega_id'])->update([
            'monto' => round((float) $nueva->total, 2),
        ]);

        $marca = DB::table('carga_verificaciones')->where('factura_id', $anterior->id)->first();
        if ($marca && $marca->verificado) {
            $ahora = date('Y-m-d H:i:s');
            DB::table('carga_verificaciones')->updateOrInsert(['factura_id' => $nueva->id], [
                'fecha'           => optional($nueva->fecha)->format('Y-m-d') ?? date('Y-m-d'),
                'placa'           => $marca->placa,
                'pedido_nro'      => $nueva->pedido_nro,
                'pedido_tipo'     => $nueva->pedido_tipo,
                'items_esperados' => $nueva->detalles()->count(),
                'total_esperado'  => round((float) $nueva->total, 2),
                'verificado'      => true,
                'observado'       => false,
                'observacion'     => 'Reemitida por retorno parcial de #' . $anterior->id,
                // La mercaderia sigue en la misma canasta.
                'nro_canasta'     => $marca->nro_canasta,
                'nota'            => $marca->nota,
                'personal_id'     => $usuario->CodAut,
                'verificado_por'  => trim($usuario->Nombre1 . ' ' . $usuario->App1),
                'verificado_en'   => $ahora,
                'created_at'      => $ahora,
                'updated_at'      => $ahora,
            ]);
        }
    }

    /**
     * Lo que se vuelve a cobrar es lo de la ultima venta emitida, no lo del
     * pedido original: si al editar se quito un producto, no tiene que volver
     * a aparecer. Del pedido solo queda lo que la venta tambien llevaba (para
     * conservar la cantidad pedida y la lista de precios).
     */
    private function recuperarAnulada($items, Factura $anulada)
    {
        $porCodigo = $anulada->detalles->keyBy(function ($detalle) {
            return trim((string) $detalle->cod_prod);
        });

        // Una venta sin detalle no dice nada de que se quito: queda el pedido.
        if ($porCodigo->isNotEmpty()) {
            $items = $items->filter(function ($item) use ($porCodigo) {
                return $porCodigo->has(trim((string) $item->cod_prod));
            });
        }

        $items = $items->map(function ($item) use ($porCodigo) {
            $detalle = $porCodigo->get(trim((string) $item->cod_prod));
            if (!$detalle) {
                return $item;
            }
            $item->cantidad = (float) $detalle->cantidad;
            // El peso solo se recupera si de verdad se peso algo.
            $item->peso = (float) $detalle->peso > 0 ? (float) $detalle->peso : null;
            $item->peso_bruto = (float) $detalle->peso_bruto > 0 ? (float) $detalle->peso_bruto : null;
            $item->canastillos = $item->peso_bruto !== null ? (int) $detalle->canastillos : null;
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
            $pesoBruto = (float) $detalle->peso_bruto > 0 ? (float) $detalle->peso_bruto : null;
            $items->push((object) [
                'cod_prod' => $codigo,
                'nombre'   => $detalle->nombre,
                'unidad'   => $detalle->unidad ?: 'UNIDAD',
                'imagen'   => null,
                'cantidad' => (float) $detalle->cantidad,
                // No venia en el pedido: no hay cantidad pedida con que compararlo.
                'cantidad_pedida' => null,
                'peso'     => $peso,
                'peso_bruto'  => $pesoBruto,
                'canastillos' => $pesoBruto !== null ? (int) $detalle->canastillos : null,
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
        $datos = collect();
        foreach ($filas as $fila) {
            foreach (['Observaciones', 'Canttxt', 'comentario'] as $campo) {
                $texto = trim((string) ($fila->{$campo} ?? ''));
                if ($texto !== '') $observaciones->push($texto);
            }
            // El preventista casi nunca llena el precio de cada producto (bs104,
            // bsala...): pone uno solo para el pollo entero (bs) y otro para el
            // trozado (bs2). Si la linea no trae el suyo, se usa ese.
            foreach ($productos as [$nombre, $caja, $unidad, $precio, $obs]) {
                $conCaja = $this->agregarDetallePollo($detalles, $fila, $nombre, $caja, 'CJA', $precio, $obs, 'bs');
                $conUnidad = $this->agregarDetallePollo($detalles, $fila, $nombre, $unidad, 'UND', $precio, $obs, 'bs');
                if (!$conCaja && !$conUnidad) {
                    $this->agregarSinCantidad($detalles, $fila, $nombre, $precio, $obs);
                }
            }
            foreach ($cortes as [$nombre, $cantidad, $unidad, $precio, $obs]) {
                if (!$this->agregarDetallePollo($detalles, $fila, $nombre, $cantidad, strtoupper(trim((string) ($fila->{$unidad} ?? 'KG'))), $precio, $obs, 'bs2')) {
                    $this->agregarSinCantidad($detalles, $fila, $nombre, $precio, $obs);
                }
            }
            // Rango va en unidades y sin precio propio, como en la hoja de pesos.
            $this->agregarDetallePollo($detalles, $fila, 'Rango', 'rango', 'U', null, null);

            $valor = function ($campo) use ($fila) {
                return trim((string) ($fila->{$campo} ?? ''));
            };
            $tipo = strtoupper(trim((string) ($fila->tipo ?? '')));

            // Las mismas columnas de la hoja de pesos pollo (generarXlsPollo).
            if ($tipo === 'POLLO') {
                foreach ([
                    ['P. Trozado', $valor('bs2')],
                    ['P. Pollo', $valor('bs')],
                ] as [$etiqueta, $texto]) {
                    $datos->push(['etiqueta' => $etiqueta, 'valor' => $texto === '' ? '—' : $texto]);
                }
            }

            // Lo que carga el preventista para cerdo y res (PedidoController::store).
            $extra = [];
            if ($tipo === 'CERDO') {
                $extra = [['Entero', 'entero'], ['Desmembre', 'desmembre'], ['Corte', 'corte'],
                    ['Kilo', 'kilo'], ['Total', 'total'], ['P. Frial', 'pfrial']];
            } elseif ($tipo === 'RES') {
                $extra = [['Trozado', 'trozado'], ['Pierna', 'pierna'], ['Brazo', 'brazo'],
                    ['Total', 'total'], ['P. Frial', 'pfrial']];
            }
            // Por si acaso, todo lo demas que ayude a armar la venta.
            $extra = array_merge($extra, [['Horario', 'horario'], ['Hora pedido', 'hora'], ['Pago', 'pago'], ['Factura', 'fact']]);
            foreach ($extra as [$etiqueta, $campo]) {
                $texto = $valor($campo);
                if ($texto !== '' && $texto !== '0' && $texto !== '0.00') {
                    $datos->push(['etiqueta' => $etiqueta, 'valor' => $texto]);
                }
            }
        }
        return [
            'observaciones' => $observaciones->unique()->values(),
            'productos' => $detalles->unique(fn ($d) => implode('|', $d))->values(),
            'datos' => $datos->unique(fn ($d) => implode('|', $d))->values(),
        ];
    }

    private function agregarDetallePollo($detalles, $fila, $nombre, $campo, $unidad, $campoPrecio, $campoObservacion, $campoPrecioGeneral = null)
    {
        $cantidad = $fila->{$campo} ?? null;
        if ($cantidad === null || $cantidad === '' || (float) $cantidad == 0) return false;
        $precio = $campoPrecio ? (float) ($fila->{$campoPrecio} ?? 0) : 0;
        if (!$precio && $campoPrecioGeneral) {
            $precio = (float) ($fila->{$campoPrecioGeneral} ?? 0);
        }
        $detalles->push([
            'nombre' => $nombre, 'cantidad' => (float) $cantidad, 'unidad' => $unidad ?: 'KG',
            'precio' => $precio,
            'observacion' => $campoObservacion ? trim((string) ($fila->{$campoObservacion} ?? '')) : '',
        ]);
        return true;
    }

    /** Un producto sin cantidad pero con precio u observacion igual se muestra. */
    private function agregarSinCantidad($detalles, $fila, $nombre, $campoPrecio, $campoObservacion)
    {
        $precio = (float) ($fila->{$campoPrecio} ?? 0);
        $observacion = trim((string) ($fila->{$campoObservacion} ?? ''));
        if ($precio == 0 && $observacion === '') return;
        $detalles->push([
            'nombre' => $nombre, 'cantidad' => null, 'unidad' => '',
            'precio' => $precio, 'observacion' => $observacion,
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
            // Pollo, cerdo y res se pesan en canastillos: la balanza marca el
            // bruto y el neto que se cobra sale de restarle los canastillos.
            'items.*.peso_bruto'  => 'nullable|numeric|min:0',
            'items.*.canastillos' => 'nullable|integer|min:0',
            // Si el preventista se confundio, en caja se puede cobrar por kilo
            // lo que el catalogo tiene por unidad, o al reves.
            'items.*.por_peso' => 'nullable|boolean',
            'items.*.precio'   => 'required|numeric|min:0',
            'tipo_comprobante' => 'nullable|in:VENTA,FACTURA',
            'tipo_pago'        => 'nullable|string|max:20',
            // Pago MIXTO: una parte en efectivo y otra por QR; deben sumar el total.
            'monto_efectivo'   => 'nullable|required_if:tipo_pago,MIXTO|numeric|gt:0',
            'monto_qr'         => 'nullable|required_if:tipo_pago,MIXTO|numeric|gt:0',
            'cliente_id'       => 'nullable|integer',
            'nit'              => 'nullable|string|max:20',
            'nombre'           => 'nullable|string|max:150',
            'descuento'        => 'nullable|numeric|min:0',
            'observacion'      => 'nullable|string|max:255',
            'pedido_nro'       => 'nullable|required_with:pedido_tipo|integer',
            'pedido_tipo'      => 'nullable|required_with:pedido_nro|' . TipoPedido::regla(),
            // Camion de la venta directa; con pedido manda el del pedido.
            'placa'            => 'nullable|string|max:50',
            // Dia de entrega de la venta directa; con pedido manda el del pedido.
            'fecha_entrega'    => 'nullable|date',
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

        // Con canastillos el neto lo calcula el servidor a partir del bruto:
        // asi lo impreso (bruto, canastillos, neto) siempre cuadra con lo
        // cobrado, aunque la pantalla mande otro peso.
        // Con pedido manda su tipo; en venta directa, el tipo de cada producto.
        $pedidoTipo = $datos['pedido_tipo'] ?? null;
        $negativos = collect();
        foreach ($datos['items'] as $i => $item) {
            $prod = $productos[trim($item['cod_prod'])];
            $conCanastillos = in_array(
                $pedidoTipo ?? strtoupper(trim((string) ($prod->tipo ?? ''))),
                FacturaDetalle::TIPOS_CON_CANASTILLOS,
                true
            );
            if (!$conCanastillos ||!$this->lineaPorPeso($prod, $item) ||!isset($item['peso_bruto'])
                || (float) $item['peso_bruto'] <= 0) {
                unset($datos['items'][$i]['peso_bruto'], $datos['items'][$i]['canastillos']);
                continue;
            }
            $canastillos = (int) ($item['canastillos'] ?? 0);
            $neto = round((float) $item['peso_bruto'] - $canastillos * FacturaDetalle::KG_CANASTILLO, 3);
            if ($neto <= 0) {
                $negativos->push(trim($prod->Producto));
            }
            $datos['items'][$i]['canastillos'] = $canastillos;
            $datos['items'][$i]['peso'] = $neto;
        }

        if ($negativos->isNotEmpty()) {
            return response()->json([
                'message' => 'Los canastillos pesan más que el peso bruto en: ' . $negativos->implode(', '),
            ], 422);
        }

        // En lo que va a granel el importe sale del peso, asi que una linea que
        // manda el peso vacio no se puede cobrar. Las pantallas que no mandan
        // peso (venta directa) siguen cobrando por cantidad.
        $sinPeso = collect($datos['items'])
            ->filter(function ($item) use ($productos) {
                return $this->lineaPorPeso($productos[trim($item['cod_prod'])], $item)
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
                ->whereNull('tbpedidos.deleted_at')
                ->where('NroPed', $datos['pedido_nro'])
                ->whereRaw(TipoPedido::sql('') . ' = ?', [$datos['pedido_tipo']])
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

            // Sin permiso no se escribe un precio libre: vale el del pedido o
            // uno de la lista del producto (lo que ofrece el select del carrito).
            if (!$usuario->can('facturacionPrecio')) {
                $delPedido = DB::table('tbpedidos')
                    ->whereNull('tbpedidos.deleted_at')
                    ->where('NroPed', $datos['pedido_nro'])
                    ->whereRaw(TipoPedido::sql('') . ' = ?', [$datos['pedido_tipo']])
                    ->where('bonificacion', 0)
                    ->get([DB::raw('TRIM(cod_prod) as cod_prod'), 'precio'])
                    ->groupBy('cod_prod');

                $cambiados = collect($datos['items'])
                    ->filter(function ($item) use ($productos, $delPedido) {
                        $cod = trim($item['cod_prod']);
                        $permitidos = $this->listaPrecios($productos[$cod], ['Precio 1' => $productos[$cod]->Precio])
                            ->pluck('value')
                            ->merge(collect($delPedido->get($cod, []))->pluck('precio')->map(function ($v) {
                                return round((float) $v, 2);
                            }));
                        return !$permitidos->contains(round((float) $item['precio'], 2));
                    })
                    ->map(function ($item) use ($productos) {
                        return trim($productos[trim($item['cod_prod'])]->Producto);
                    });

                if ($cambiados->isNotEmpty()) {
                    return response()->json([
                        'message' => 'No tiene permiso para cambiar el precio de: ' . $cambiados->implode(', '),
                    ], 403);
                }
            }
        }

        $factura = DB::transaction(function () use ($datos, $productos, $usuario, $ci, $cliente, $tipo, $nit) {
            // Sin stock no se vende: se valida con las filas bloqueadas para
            // que dos cajas no vendan a la vez lo mismo que queda.
            $this->exigirStock($datos['items'], $productos);

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
                $peso = $this->lineaPorPeso($prod, $item) && isset($item['peso']) && (float) $item['peso'] > 0
                    ? round((float) $item['peso'], 3)
                    : null;

                $importe = round(($peso ?? $cantidad) * $precio, 2);
                $subtotal += $importe;

                $lineas[] = [
                    'cod_prod' => $cod,
                    'nombre'   => trim($prod->Producto),
                    'unidad'   => $this->unidadLinea($prod, $item),
                    'cantidad' => $cantidad,
                    // Queda guardado lo que pidio el cliente aunque se le haya
                    // entregado otra cosa: sin esto, los que no salieron no
                    // figuraban en ningun lado.
                    'cantidad_pedida' => isset($item['cantidad_pedida'])
                        ? round((float) $item['cantidad_pedida'], 3)
                        : null,
                    'peso'     => $peso,
                    // De donde salio el neto, solo en lo pesado con canastillos.
                    'peso_bruto'  => $peso !== null && isset($item['peso_bruto'])
                        ? round((float) $item['peso_bruto'], 3)
                        : null,
                    'canastillos' => $peso !== null && isset($item['peso_bruto'])
                        ? (int) $item['canastillos']
                        : null,
                    'precio'   => $precio,
                    'subtotal' => $importe,
                ];
            }

            $subtotal = round($subtotal, 2);
            $descuento = min(round((float) ($datos['descuento'] ?? 0), 2), $subtotal);
            $tipoPago = $datos['tipo_pago'] ?? 'EFECTIVO';
            list($montoEfectivo, $montoQr) = $this->montosPago($tipoPago, round($subtotal - $descuento, 2), $datos);

            // Al refacturar (anular y volver a emitir el mismo pedido) la venta
            // sigue siendo del dia en que se hizo: si tomara la fecha de hoy
            // saldria de la venta y de la jornada del camion que la entrego y
            // caeria en otro dia. Cuando se emitio de verdad queda en
            // created_at y, si es factura, en fecha_emision (la de Impuestos).
            $refacturada = !empty($datos['pedido_nro'])
                ? Factura::where('pedido_nro', $datos['pedido_nro'])
                    ->where('pedido_tipo', $datos['pedido_tipo'])
                    ->where('estado', 'ANULADO')
                    ->orderByDesc('id')
                    ->first(['id', 'fecha', 'hora', 'fecha_entrega'])
                : null;

            $factura = Factura::create([
                'fecha_entrega'    => $this->fechaEntregaNueva($datos, $refacturada),
                'user_id'          => $usuario->CodAut,
                'cliente_id'       => $cliente->Cod_Aut ?? null,
                'vendedor_ci'      => $cliente ? trim((string) $cliente->CiVend) : null,
                'fecha'            => $refacturada ? $refacturada->fecha->format('Y-m-d') : date('Y-m-d'),
                'hora'             => $refacturada ? $refacturada->hora : date('H:i:s'),
                'nit'              => $nit !== '' ? $nit : ($cliente ? trim($cliente->Id) : null),
                'nombre'           => $datos['nombre'] ?? ($cliente ? trim($cliente->Nombres) : null),
                'tipo_comprobante' => $tipo,
                'tipo_pago'        => $tipoPago,
                'monto_efectivo'   => $montoEfectivo,
                'monto_qr'         => $montoQr,
                'estado'           => 'ACTIVO',
                'subtotal'         => $subtotal,
                'descuento'        => $descuento,
                'total'            => round($subtotal - $descuento, 2),
                'observacion'      => $datos['observacion'] ?? null,
                'pedido_nro'       => $datos['pedido_nro'] ?? null,
                'pedido_tipo'      => $datos['pedido_tipo'] ?? null,
                'placa'            => empty($datos['pedido_nro']) && trim((string) ($datos['placa'] ?? '')) !== ''
                    ? trim($datos['placa']) : null,
            ]);

            $factura->detalles()->createMany($lineas);

            // Lo vendido sale del inventario.
            $this->moverStock($lineas, $factura->id, $ci, date('Y-m-d H:i:s'), 'SALIDA');

            if ($factura->pedido_nro) {
                $this->heredarRetorno($factura, $usuario);
                // Si viene de editar otro comprobante del pedido: padre, numero
                // de modificacion y que campos cambiaron.
                (new ModificacionFactura())->registrar($factura);
                // Si se cobro distinto de lo que pidio el preventista, queda marcado.
                (new CambioPedido())->registrar($factura);
                // Ya es venta: lo guardado a medias deja de servir.
                DB::table('pedido_borradores')
                    ->where('pedido_nro', $factura->pedido_nro)
                    ->where('pedido_tipo', $factura->pedido_tipo)
                    ->delete();
            }

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

    /**
     * Cuanto del total se cobro en efectivo y cuanto por QR: [efectivo, qr].
     *
     * EFECTIVO y QR llevan el total en su columna; MIXTO trae las dos partes y
     * tienen que sumar justo el total, si no la venta no se guarda. El resto
     * (TARJETA, CREDITO) no lleva montos.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function montosPago($tipoPago, $total, array $datos)
    {
        switch (strtoupper(trim((string) $tipoPago))) {
            case 'EFECTIVO':
                return [$total, null];
            case 'QR':
                return [null, $total];
            case 'MIXTO':
                $efectivo = round((float) ($datos['monto_efectivo'] ?? 0), 2);
                $qr = round((float) ($datos['monto_qr'] ?? 0), 2);
                if (abs($efectivo + $qr - $total) > 0.005) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'monto_efectivo' => 'En pago mixto efectivo (' . number_format($efectivo, 2) . ') + QR ('
                            . number_format($qr, 2) . ') tiene que sumar el total: Bs ' . number_format($total, 2),
                    ]);
                }
                return [$efectivo, $qr];
            default:
                return [null, null];
        }
    }

    /**
     * Dia de entrega de un comprobante nuevo. Al refacturar, el mismo del
     * anulado: sigue siendo el mismo reparto. Con pedido, el del pedido, pero
     * nunca antes de hoy (un pedido atrasado sale en el proximo reparto). La
     * venta directa, el que eligio caja o hoy.
     */
    private function fechaEntregaNueva(array $datos, $refacturada)
    {
        if ($refacturada && $refacturada->fecha_entrega) {
            return substr((string) $refacturada->fecha_entrega, 0, 10);
        }
        $hoy = date('Y-m-d');
        if (!empty($datos['pedido_nro'])) {
            $delPedido = DB::table('tbpedidos')
                ->whereNull('tbpedidos.deleted_at')
                ->where('NroPed', $datos['pedido_nro'])
                ->whereRaw(TipoPedido::sql('') . ' = ?', [$datos['pedido_tipo']])
                ->min('fecha_entrega');
            $dia = $delPedido ? substr((string) $delPedido, 0, 10) : $hoy;
            return max($dia, $hoy);
        }

        return !empty($datos['fecha_entrega']) ? date('Y-m-d', strtotime($datos['fecha_entrega'])) : $hoy;
    }

    /**
     * Corta los comprobantes por el rango de la lista. Con hora el corte es
     * por fecha y hora juntas: el turno de caja va de las 18:00 de ayer a las
     * 18:00 de hoy, no de medianoche a medianoche. La lista, los conteos y el
     * resumen por camion usan este mismo corte.
     */
    private function enVentana($query, Request $request)
    {
        // Con fecha de entrega manda esa: es el dia del reparto, sin cortes por hora.
        if ($rango = $this->rangoEntregaFiltro($request)) {
            return $rango[0] === $rango[1]
                ? $query->where('facturas.fecha_entrega', $rango[0])
                : $query->whereBetween('facturas.fecha_entrega', $rango);
        }
        $horaDesde = $this->hora($request->input('hora_desde'));
        $horaHasta = $this->hora($request->input('hora_hasta'));
        if ($desde = $request->input('desde')) {
            if ($horaDesde) {
                $query->whereRaw('TIMESTAMP(DATE(fecha), hora) >= ?', [$desde . ' ' . $horaDesde . ':00']);
            } else {
                $query->whereDate('fecha', '>=', $desde);
            }
        }
        if ($hasta = $request->input('hasta')) {
            if ($horaHasta) {
                // Hasta las 18:00 es hasta las 17:59:59; lo de las 18:00 en
                // punto ya es del turno siguiente.
                $query->whereRaw('TIMESTAMP(DATE(fecha), hora) < ?', [$hasta . ' ' . $horaHasta . ':00']);
            } else {
                $query->whereDate('fecha', '<=', $hasta);
            }
        }

        return $query;
    }

    /** 'YYYY-MM-DD' valido del filtro fecha_entrega, o null. */
    private function fechaEntregaFiltro(Request $request)
    {
        return $this->diaValido($request->input('fecha_entrega'));
    }

    /**
     * Rango de entrega [desde, hasta] del filtro, o null sin fecha de entrega.
     * Sin fecha_entrega_hasta (o igual a la de inicio) es un solo dia; si
     * llegan al reves se dan vuelta.
     */
    private function rangoEntregaFiltro(Request $request)
    {
        $desde = $this->fechaEntregaFiltro($request);
        if (!$desde) {
            return null;
        }
        $hasta = $this->diaValido($request->input('fecha_entrega_hasta')) ?: $desde;

        return $hasta < $desde ? [$hasta, $desde] : [$desde, $hasta];
    }

    private function diaValido($valor)
    {
        $valor = trim((string) $valor);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) && strtotime($valor) ? $valor : null;
    }

    /** 'HH:MM' valida o null; asi lo que llega del filtro no entra crudo al SQL. */
    private function hora($valor)
    {
        $valor = trim((string) $valor);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $valor) ? $valor : null;
    }

    /** Forma de pago para el impreso; el mixto lleva cuanto fue de cada lado. */
    private function textoPago($factura)
    {
        if (strtoupper((string) $factura->tipo_pago) !== 'MIXTO') {
            return (string) $factura->tipo_pago;
        }
        return 'MIXTO (Efectivo ' . number_format((float) $factura->monto_efectivo, 2)
            . ' + QR ' . number_format((float) $factura->monto_qr, 2) . ')';
    }

    /**
     * Los productos a granel se cobran por peso, no por cantidad.
     *
     * CAJA no es un bulto cerrado: son los granel que el preventista puede
     * pedir en unidades, cajas o kilos (tbpedidos.caja), pero que en el
     * mostrador se pesan igual que los de KG.
     */
    private function esGranel($producto)
    {
        return in_array($this->unidadVenta($producto), ['KG', 'CAJA'], true);
    }

    /**
     * Si la linea se cobra por peso. Manda lo que eligio el cajero (por_peso)
     * cuando el preventista cargo mal la unidad; si no, la del catalogo.
     */
    /**
     * Corta la venta (422) si algun producto no alcanza en stock_actual.
     * Lo que se pide sale igual que en moverStock: el peso en lo que va por
     * kilo y la cantidad en el resto, sumando las lineas del mismo producto.
     * Va dentro de la transaccion de la venta: lockForUpdate retiene las
     * filas hasta que se descuente el stock.
     */
    private function exigirStock(array $items, $productos)
    {
        $necesita = [];
        foreach ($items as $item) {
            $cod = trim($item['cod_prod']);
            $porPeso = $this->lineaPorPeso($productos[$cod], $item) && isset($item['peso']) && (float) $item['peso'] > 0;
            $necesita[$cod] = ($necesita[$cod] ?? 0) + round((float) ($porPeso ? $item['peso'] : $item['cantidad']), 3);
        }

        $existencias = DB::table('tbproductos')
            ->whereIn(DB::raw('TRIM(cod_prod)'), array_keys($necesita))
            ->lockForUpdate()
            ->get([DB::raw('TRIM(cod_prod) as cod_prod'), DB::raw('COALESCE(stock_actual, 0) as stock')])
            ->pluck('stock', 'cod_prod');

        $faltan = [];
        foreach ($necesita as $cod => $cantidad) {
            $hay = round((float) ($existencias[$cod] ?? 0), 3);
            if ($cantidad > $hay + 0.0005) {
                $faltan[] = [
                    'cod_prod' => (string) $cod,
                    'nombre'   => trim($productos[$cod]->Producto),
                    'necesita' => $cantidad,
                    'stock'    => $hay,
                ];
            }
        }
        if ($faltan) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
                'message' => 'No hay stock suficiente de: ' . implode(', ', array_map(function ($f) {
                    return $f['nombre'] . ' (hay ' . rtrim(rtrim(number_format(max($f['stock'], 0), 3, '.', ''), '0'), '.')
                        . ', se pide ' . rtrim(rtrim(number_format($f['necesita'], 3, '.', ''), '0'), '.') . ')';
                }, $faltan)),
                'sin_stock' => $faltan,
            ], 422));
        }
    }

    private function lineaPorPeso($producto, array $item)
    {
        if (isset($item['por_peso'])) {
            return (bool) $item['por_peso'];
        }
        return $this->esGranel($producto);
    }

    /** Unidad que queda en el detalle: la del catalogo salvo que se haya cambiado. */
    private function unidadLinea($producto, array $item)
    {
        $porPeso = $this->lineaPorPeso($producto, $item);
        if ($porPeso === $this->esGranel($producto)) {
            return $this->unidadVenta($producto);
        }
        return $porPeso ? 'KG' : 'UNIDAD';
    }

    /**
     * Unidad en la que se cobra el producto. El cerdo (tbproductos.tipo = CERDO) se
     * vende siempre por kilo aunque en el catalogo figure en unidades.
     * La misma regla esta en SQL en catalogo() y pedido() (UNIDAD_VENTA_SQL).
     */
    private function unidadVenta($producto)
    {
        if (strtoupper(trim((string) ($producto->tipo ?? ''))) === 'CERDO') {
            return 'KG';
        }
        return strtoupper(trim((string) $producto->codUnid));
    }

    /** Los precios de lista de tbproductos, ademas de Precio. */
    const PRECIOS_LISTA = [
        'Precio3', 'Precio4', 'Precio5', 'Precio6', 'Precio7', 'Precio8',
        'Precio9', 'Precio10', 'Precio11', 'Precio12', 'Precio13',
    ];

    /**
     * Precios para elegir en la venta, con su nombre: [{label, value}].
     * $primeros va adelante como [nombre => importe] (el del pedido, el
     * Precio 1); despues Precio 3..13. Sin ceros ni importes repetidos: si dos
     * coinciden queda el primer nombre.
     */
    private function listaPrecios($fila, array $primeros)
    {
        $valores = $primeros;
        // Precio_Costo es el Precio 2 del preventista (nombre heredado, no es
        // el costo); el catalogo lo trae con el alias "costo".
        $valores['Precio 2'] = $fila->Precio_Costo ?? 0;
        foreach (self::PRECIOS_LISTA as $columna) {
            $valores['Precio ' . substr($columna, 6)] = $fila->$columna ?? 0;
        }
        // Los que valen lo mismo van en una sola opcion con todos sus nombres
        // ("Precio 3 / 8"): descartar el repetido hacia desaparecer el Precio
        // 8 de la lista y no se podia elegir por su nombre.
        $opciones = [];
        foreach ($valores as $nombre => $valor) {
            $valor = round((float) $valor, 2);
            if ($valor <= 0) {
                continue;
            }
            $clave = number_format($valor, 2, '.', '');
            $opciones[$clave] = $opciones[$clave] ?? ['value' => $valor, 'nombres' => []];
            $opciones[$clave]['nombres'][] = $nombre;
        }

        return collect($opciones)->map(function ($opcion) {
            return ['label' => self::etiquetaPrecios($opcion['nombres'])] + $opcion;
        })->values();
    }

    /**
     * Las cuentas a las que se cargan las bajas: el pedido las lleva en
     * bonificacionId (las mismas que usa el Excel de preparacion).
     */
    const CUENTAS_BAJA = [2728, 3070]; // BAJAS POR BONIFICACIONES, BAJAS POR CALIDAD

    /** El nombre de la cuenta de baja del pedido, o null si no es una baja. */
    private function cuentaDeBaja($nroPedido, $tipo)
    {
        $cuentaId = DB::table('tbpedidos')
            ->whereNull('tbpedidos.deleted_at')
            ->where('NroPed', $nroPedido)
            ->whereRaw(TipoPedido::sql('') . ' = ?', [strtoupper(trim((string) $tipo))])
            ->whereIn('bonificacionId', self::CUENTAS_BAJA)
            ->value('bonificacionId');

        if (!$cuentaId) {
            return null;
        }

        return trim((string) DB::table('tbclientes')->where('Cod_Aut', $cuentaId)->value('Nombres')) ?: null;
    }

    /**
     * La observacion que va en el papel: la del comprobante y la del pedido
     * (Observaciones y comentario, las de la hoja de pesos). Cada producto del
     * pedido repite su observacion; va una sola por pedido: la de la primera
     * linea que la tenga. Venta directa: solo la del comprobante.
     */
    private function observacionImpresa(Factura $factura, $observacion)
    {
        $textos = collect([trim((string) $observacion)]);
        if ($factura->pedido_nro) {
            $fila = DB::table('tbpedidos')
                ->whereNull('tbpedidos.deleted_at')
                ->where('NroPed', $factura->pedido_nro)
                ->whereRaw(TipoPedido::sql('') . ' = ?', [strtoupper(trim((string) $factura->pedido_tipo))])
                ->whereRaw("(TRIM(COALESCE(Observaciones, '')) <> '' OR TRIM(COALESCE(comentario, '')) <> '')")
                ->orderBy('codAut')
                ->first(['Observaciones', 'comentario']);
            if ($fila) {
                $textos->push(trim((string) $fila->Observaciones), trim((string) $fila->comentario));
            }
        }
        $textos = $textos->filter(fn ($t) => $t !== '')->unique(fn ($t) => mb_strtoupper($t))->values();

        return $textos->implode(' · ');
    }

    /** "- BONIFICACION" -> "GONZALO ... - BONIFICACION"; sin repetir el nombre. */
    private function observacionDeBaja($cliente, $observacion)
    {
        $observacion = trim((string) $observacion);
        if ($cliente === '' || ($observacion !== '' && mb_stripos($observacion, $cliente) !== false)) {
            return $observacion;
        }
        if ($observacion === '') {
            return $cliente;
        }

        return mb_substr($cliente . (strpos($observacion, '-') === 0 ? ' ' : ' - ') . $observacion, 0, 255);
    }

    /** ['Pedido', 'Precio 3', 'Precio 8'] -> "Pedido · Precio 3 / 8". */
    private static function etiquetaPrecios(array $nombres)
    {
        $numeros = [];
        $otros = [];
        foreach ($nombres as $nombre) {
            if (preg_match('/^Precio (\d+)$/', $nombre, $m)) {
                $numeros[] = $m[1];
            } else {
                $otros[] = $nombre;
            }
        }
        if ($numeros) {
            $otros[] = 'Precio ' . implode(' / ', $numeros);
        }

        return implode(' · ', $otros);
    }

    /**
     * Le pone su lista de precios a las lineas que llegan sin ella: lo que se
     * recupero de una venta anulada o lo que viene de un borrador guardado.
     */
    private function completarPrecios($items, $filasPedido)
    {
        $faltan = $items->filter(function ($item) { return empty($item->precios); })
            ->map(function ($item) { return trim((string) $item->cod_prod); })
            ->unique()->values()->all();
        if (!$faltan) {
            return $items;
        }

        $productos = DB::table('tbproductos')
            ->whereIn(DB::raw('TRIM(cod_prod)'), $faltan)
            ->get(array_merge([DB::raw('TRIM(cod_prod) as cod_prod'), 'Precio', 'Precio_Costo'], self::PRECIOS_LISTA))
            ->keyBy('cod_prod');
        $delPedido = collect($filasPedido)->mapWithKeys(function ($fila) {
            return [trim((string) $fila->cod_prod) => (float) $fila->precio];
        });

        return $items->map(function ($item) use ($productos, $delPedido) {
            $codigo = trim((string) $item->cod_prod);
            $producto = $productos->get($codigo);
            if (empty($item->precios) && $producto) {
                $primeros = $delPedido->has($codigo) ? ['Pedido' => $delPedido->get($codigo)] : [];
                $item->precios = $this->listaPrecios($producto, $primeros + ['Precio 1' => $producto->Precio]);
            }
            return $item;
        });
    }

    /** unidadVenta() en SQL; {p} es el alias de tbproductos. */
    const UNIDAD_VENTA_SQL = "CASE WHEN UPPER(TRIM({p}.tipo)) = 'CERDO' THEN 'KG' ELSE TRIM({p}.codUnid) END";

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
    /**
     * Las zonas (colores) que se le pueden dar a un pedido, las mismas del
     * mapa de clientes.
     */
    public function colores()
    {
        return DB::table('colores')->whereNull('deleted_at')->orderBy('id')
            ->get(['id', 'zona', 'color', 'colorStyle']);
    }

    /**
     * Cambia el camion y/o el color de zona de un comprobante.
     *
     * - De un pedido: camion y color viven en tbpedidos (placa, color,
     *   colorStyle), asi que se cambian las lineas de ese pedido y de ese
     *   tipo (un pedido con embutidos y podium tiene un comprobante por
     *   tipo), por el modelo para que quede en audits.
     * - Venta directa: el camion es facturas.placa y no tiene color.
     *
     * Se puede aunque ya este entregado o cobrado: la entrega pasa al camion
     * nuevo junto con el comprobante, asi el recojo del dia sale en ese camion.
     *
     * Si cambia el camion, la revision de carga era del otro: vuelve a
     * pendiente para que el caminero nuevo la revise, y el numero de canasta
     * se borra (esa canasta quedo en el otro camion).
     */
    /**
     * Pasa un comprobante de contado (efectivo o QR) a credito o al reves.
     *
     * Las deudas salen de facturas.tipo_pago, asi que con esto solo el
     * credito aparece o desaparece de cuentas por cobrar. No se pasa a contado
     * lo que ya tiene abonos vigentes: primero se anulan en cobranzas, para no
     * perder plata registrada. A credito tiene que haber un cliente que deba.
     */
    public function cambiarPago(Request $request, $id)
    {
        $datos = $request->validate([
            'tipo_pago' => 'required|in:EFECTIVO,QR,CRÉDITO',
        ]);
        $nuevo = $datos['tipo_pago'];

        $factura = Factura::findOrFail($id);
        if ($factura->estado === 'ANULADO') {
            return response()->json(['message' => 'El comprobante está anulado'], 422);
        }

        $actual = mb_strtoupper(trim((string) $factura->tipo_pago), 'UTF-8');
        $eraCredito = in_array($actual, ['CRÉDITO', 'CREDITO'], true);
        if ($actual === $nuevo || ($eraCredito && $nuevo === 'CRÉDITO')) {
            return response()->json(['message' => 'El comprobante ya es ' . $nuevo], 422);
        }

        if ($nuevo === 'CRÉDITO' && !$factura->cliente_id) {
            return response()->json(['message' => 'A crédito hace falta un cliente registrado: este comprobante no tiene'], 422);
        }

        if ($eraCredito) {
            $abonado = (float) DB::table('creditos_abonos')
                ->where('origen', 'factura')->where('deuda_id', $factura->id)
                ->whereNull('anulado_at')->sum('monto');
            if ($abonado > 0) {
                return response()->json([
                    'message' => 'Este crédito ya tiene abonos por Bs ' . number_format($abonado, 2)
                        . ': anulalos primero en cobranzas para pasarlo a contado',
                ], 422);
            }
        }

        $total = round((float) $factura->total, 2);
        $factura->tipo_pago = $nuevo;
        $factura->monto_efectivo = $nuevo === 'EFECTIVO' ? $total : null;
        $factura->monto_qr = $nuevo === 'QR' ? $total : null;
        $factura->save();

        return response()->json([
            'message' => 'Comprobante #' . $factura->id . ': pasado de ' . ($actual ?: 'sin forma de pago') . ' a ' . $nuevo
                . ($nuevo === 'CRÉDITO' ? ' (ya figura en cuentas por cobrar)' : ($eraCredito ? ' (salió de cuentas por cobrar)' : '')),
        ]);
    }

    public function cambiarCamion(Request $request, $id)
    {
        $datos = $request->validate([
            'placa' => 'nullable|string|max:50',
            'color_id' => 'nullable|integer',
        ]);
        $placa = trim((string) ($datos['placa'] ?? ''));
        $colorId = $datos['color_id'] ?? null;

        if ($placa === '' && !$colorId) {
            return response()->json(['message' => 'Elegí un camión o un color'], 422);
        }

        if ($placa !== '' && !DB::table('vehiculo')->whereRaw('TRIM(placa) = ?', [$placa])->exists()) {
            return response()->json(['message' => 'Ese camión no existe'], 422);
        }

        $zona = null;
        if ($colorId) {
            $zona = DB::table('colores')->whereNull('deleted_at')->where('id', $colorId)->first(['zona', 'color', 'colorStyle']);
            if (!$zona) {
                return response()->json(['message' => 'Ese color no existe'], 422);
            }
        }

        $factura = Factura::findOrFail($id);
        if ($factura->estado === 'ANULADO') {
            return response()->json(['message' => 'El comprobante está anulado'], 422);
        }
        if ($zona && !$factura->pedido_nro) {
            return response()->json(['message' => 'La venta directa no tiene color de zona'], 422);
        }

        $anterior = $factura->pedido_nro ? $this->camionDeFactura($factura) : trim((string) $factura->placa);
        $cambiaPlaca = $placa !== '' && $anterior !== $placa;
        if (!$cambiaPlaca && !$zona) {
            return response()->json(['message' => 'El comprobante ya va en ' . $placa], 422);
        }

        DB::transaction(function () use ($factura, $placa, $cambiaPlaca, $zona) {
            if ($factura->pedido_nro) {
                \App\Models\Pedido::where('NroPed', $factura->pedido_nro)
                    ->whereRaw(TipoPedido::sql('') . ' = ?', [strtoupper(trim((string) $factura->pedido_tipo))])
                    ->get()
                    ->each(function ($linea) use ($placa, $cambiaPlaca, $zona) {
                        if ($cambiaPlaca) {
                            $linea->placa = $placa;
                        }
                        if ($zona) {
                            $linea->color = trim((string) $zona->color);
                            $linea->colorStyle = trim((string) $zona->colorStyle);
                        }
                        $linea->save();
                    });
            } elseif ($cambiaPlaca) {
                $factura->placa = $placa;
                $factura->save();
            }

            if (!$cambiaPlaca) {
                return;
            }

            DB::table('entregas')->where('factura_id', $factura->id)->update(['placa' => $placa]);

            DB::table('carga_verificaciones')->where('factura_id', $factura->id)->update([
                'placa' => $placa,
                'verificado' => false,
                'observado' => false,
                'observacion' => null,
                'items_revisados' => null,
                'nro_canasta' => null,
                'verificado_en' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        });

        $partes = [];
        if ($cambiaPlaca) {
            $partes[] = 'pasado de ' . ($anterior ?: 'sin camión') . ' a ' . $placa;
        }
        if ($zona) {
            $partes[] = 'zona ' . trim((string) $zona->zona);
        }

        return response()->json([
            'message' => 'Comprobante #' . $factura->id . ': ' . implode(', ', $partes),
        ]);
    }

    /**
     * La razon de verdad de una anulacion, para la oficina. El motivo de
     * Impuestos es uno de cuatro fijos y no explica nada; este es opcional.
     */
    const MOTIVOS_ANULACION_INTERNOS = [
        'CALIDAD DE PRODUCTO',
        'CANCELO PEDIDO EL CLIENTE/VENDEDOR',
        'CERRADO - NO CONTESTA',
        'ERROR DE DIGITACION',
        'FECHA PRONTO A VENCER',
        'NO QUISO',
        'NO RECOGIO',
        'NO TIENE DINERO',
        'PEDIDO INCORRECTO VENTAS',
        'PEDIDO INCORRECTO DESPACHOS',
        'TARDE',
        'ERROR DE PRECIO',
        'MERMA',
        'LOCAL NO UBICADO',
        'NO HAY SALIDAS A PROVINCIA',
        'BLOQUEOS',
    ];

    public function anular(Request $request, $id)
    {
        $datos = $request->validate([
            'codigo_motivo' => 'required|integer|between:1,4',
            // Por que se anulo, en palabras de quien lo hizo. Puede ir vacio.
            'observacion'   => 'nullable|string|in:' . implode(',', self::MOTIVOS_ANULACION_INTERNOS),
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

            // Si el SIN dice que no la tiene, no hay nada que anular alli y
            // se sigue con la anulacion local (y la devolucion de stock).
            if (empty($respuestaSiat['transaccion']) && empty($respuestaSiat['no_existe'])) {
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
        $usuario = $request->user();

        DB::transaction(function () use ($factura, $datos, $motivos, $ci, $usuario) {
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

            $observacion = trim((string) ($datos['observacion'] ?? ''));

            $factura->update([
                'estado'                => 'ANULADO',
                'motivo_anulacion'      => $motivos[$datos['codigo_motivo']],
                'anulacion_observacion' => $observacion !== '' ? $observacion : null,
                'anulado_at'            => now(),
                'anulado_por'           => $usuario->CodAut ?? null,
            ]);
        });

        return response()->json([
            'message' => $respuestaSiat && !empty($respuestaSiat['no_existe'])
                ? 'No existía en Impuestos: anulada solo en Sofia; el stock descontado fue devuelto'
                : ($respuestaSiat
                ? 'Anulada en Impuestos y en Sofia; el stock descontado fue devuelto'
                : 'Anulada en Sofia; el stock descontado fue devuelto'),
            'factura' => $factura->fresh(),
            'siat' => $respuestaSiat,
        ]);
    }

    /**
     * La fecha que va impresa en la factura fiscal: la de su emision en
     * Impuestos, que es la que muestra el QR. Puede no ser la de la venta: al
     * refacturar, la venta conserva el dia original y la factura se emite hoy.
     */
    private function fechaImpresaFactura(Factura $factura)
    {
        $emision = $factura->fecha_emision ? strtotime(substr($factura->fecha_emision, 0, 19)) : false;

        return $emision
            ? date('d/m/Y H:i:s', $emision)
            : $factura->fecha->format('d/m/Y') . ' ' . e($factura->hora);
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
     * Retorno parcial: el cliente recibe el pedido pero devuelve algunos items.
     *
     * Un comprobante emitido no se corrige. La factura porque el SIAT no lo
     * permite, y el voucher porque se decidio tratarlo igual: si las dos vias
     * dejan el mismo rastro, el inventario y los reportes se leen de una sola
     * manera. Entonces lo que se hace es anular el original y emitir uno nuevo
     * con lo que de verdad quedo en la puerta.
     *
     * El inventario cierra solo: la anulacion devuelve TODO lo que habia
     * salido y el comprobante nuevo descuenta unicamente lo entregado, asi que
     * al almacen vuelve exactamente lo retornado, producto por producto.
     */
    public function retornoParcial(Request $request, $id)
    {
        $datos = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.cod_prod'   => 'required|string|max:25',
            'items.*.cantidad'   => 'required|numeric|min:0',
            'items.*.peso'       => 'nullable|numeric|min:0',
            'codigo_motivo'      => 'nullable|integer|between:1,4',
            'observacion'        => 'nullable|string|max:200',
        ]);

        $original = Factura::with('detalles')->find($id);
        if (!$original) {
            return response()->json(['message' => 'El comprobante no existe'], 404);
        }
        if ($original->estado === 'ANULADO') {
            return response()->json(['message' => 'El comprobante ya está anulado'], 422);
        }

        $ci = trim((string) ($request->user()->ci ?? ''));
        if ($ci === '') {
            return response()->json(['message' => 'El usuario no tiene CI en personal'], 422);
        }

        // Lo entregado se compara contra la linea original: no se puede
        // "devolver" algo que la nota nunca tuvo ni entregar de mas, que
        // siempre es un error de tipeo del cajero.
        $porCodigo = $original->detalles->keyBy(function ($d) {
            return trim((string) $d->cod_prod);
        });

        $lineas = [];
        $subtotal = 0;

        foreach ($datos['items'] as $item) {
            $cod = trim((string) $item['cod_prod']);
            $linea = $porCodigo->get($cod);

            if (!$linea) {
                return response()->json([
                    'message' => 'El producto ' . $cod . ' no está en este comprobante',
                ], 422);
            }

            $cantidad = round((float) $item['cantidad'], 3);
            // Solo lo que va por kilo lleva peso; en el resto la linea se cobra
            // por cantidad, igual que al emitir.
            $peso = (float) $linea->peso > 0 && isset($item['peso'])
                ? round((float) $item['peso'], 3)
                : null;

            // Una linea entregada entera se deja tal cual vino, sin recalcular:
            // el retorno no es el momento de corregir precios ni pesos.
            if ($cantidad <= 0 && ($peso === null || $peso <= 0)) {
                continue;
            }

            if ($cantidad - (float) $linea->cantidad > 0.001) {
                return response()->json([
                    'message' => 'De ' . trim($linea->nombre) . ' no se puede entregar más de '
                        . rtrim(rtrim(number_format((float) $linea->cantidad, 3, '.', ''), '0'), '.'),
                ], 422);
            }
            if ($peso !== null && $peso - (float) $linea->peso > 0.001) {
                return response()->json([
                    'message' => 'De ' . trim($linea->nombre) . ' no se puede entregar más de '
                        . rtrim(rtrim(number_format((float) $linea->peso, 3, '.', ''), '0'), '.') . ' kg',
                ], 422);
            }

            $precio = round((float) $linea->precio, 2);
            $importe = round(($peso ?? $cantidad) * $precio, 2);
            $subtotal += $importe;

            $lineas[] = [
                'cod_prod' => $cod,
                'nombre'   => $linea->nombre,
                'unidad'   => $linea->unidad,
                'cantidad' => $cantidad,
                // Lo que decia la nota original: es lo que permite ver despues
                // cuanto se devolvio sin tener que cruzar los dos documentos.
                'cantidad_pedida' => (float) $linea->cantidad,
                'peso'     => $peso,
                // El bruto y los canastillos solo siguen valiendo si el peso
                // no cambio; con otro neto ya no se sabe como se peso.
                'peso_bruto'  => $peso !== null && abs($peso - (float) $linea->peso) < 0.001 ? $linea->peso_bruto : null,
                'canastillos' => $peso !== null && abs($peso - (float) $linea->peso) < 0.001 ? $linea->canastillos : null,
                'precio'   => $precio,
                'subtotal' => $importe,
            ];
        }

        if (!$lineas) {
            return response()->json([
                'message' => 'Si no se entregó nada, anulá el comprobante en vez de hacer un retorno parcial',
            ], 422);
        }

        $subtotal = round($subtotal, 2);
        if ($subtotal >= round((float) $original->subtotal, 2) - 0.001) {
            return response()->json([
                'message' => 'Se entregó todo: no hay nada que devolver',
            ], 422);
        }

        // El descuento de la nota original se reparte en la misma proporcion:
        // si se entrego la mitad, el cliente conserva la mitad del descuento.
        $descuentoOriginal = round((float) $original->descuento, 2);
        $descuento = $descuentoOriginal > 0 && (float) $original->subtotal > 0
            ? round($descuentoOriginal * ($subtotal / (float) $original->subtotal), 2)
            : 0;

        $motivo = (int) ($datos['codigo_motivo'] ?? 3);

        // Impuestos primero: si el SIAT rechaza la anulacion no se toca nada
        // local, porque quedarian dos comprobantes vivos por la misma venta.
        $respuestaSiat = null;
        if ($original->tipo_comprobante === 'FACTURA' && $original->cuf
            && $original->estado_siat !== SiatService::ESTADO_SIMULADO) {
            $siat = new SiatService();

            try {
                $respuestaSiat = $siat->anularFactura($original, $motivo);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'No se pudo anular en Impuestos: ' . $siat->mensajeError($e),
                ], 422);
            }

            // Si el SIN dice que no la tiene, no hay nada que anular alli y
            // se sigue con la anulacion local (y la devolucion de stock).
            if (empty($respuestaSiat['transaccion']) && empty($respuestaSiat['no_existe'])) {
                return response()->json([
                    'message' => 'Impuestos rechazó la anulación: ' . $respuestaSiat['mensaje'],
                    'siat' => $respuestaSiat,
                ], 422);
            }
        }

        $motivos = [
            1 => 'FACTURA MAL EMITIDA',
            2 => 'DATOS DE EMISION INCORRECTOS',
            3 => 'FACTURA O NOTA DEVUELTA',
            4 => 'SUSTITUCION DE FACTURA EMITIDA EN CONTINGENCIA',
        ];

        $usuario = $request->user();
        $nota = trim((string) ($datos['observacion'] ?? ''));

        $nueva = DB::transaction(function () use (
            $original, $lineas, $subtotal, $descuento, $motivo, $motivos, $ci, $usuario, $nota
        ) {
            $devueltas = $original->detalles->map(function ($d) {
                return [
                    'cod_prod' => $d->cod_prod,
                    'cantidad' => (float) $d->cantidad,
                    'peso'     => (float) $d->peso,
                    'precio'   => (float) $d->precio,
                ];
            })->all();

            // Vuelve el pedido entero y despues sale lo entregado: la
            // diferencia es exactamente lo que el camion trajo de regreso.
            $this->moverStock($devueltas, $original->id, $ci, date('Y-m-d H:i:s'), 'ANULACION');

            $original->update([
                'estado'                => 'ANULADO',
                'motivo_anulacion'      => $motivos[$motivo],
                'anulado_at'            => now(),
            ]);

            $nueva = Factura::create([
                'user_id'          => $usuario->CodAut,
                'cliente_id'       => $original->cliente_id,
                'vendedor_ci'      => $original->vendedor_ci,
                'fecha'            => date('Y-m-d'),
                'hora'             => date('H:i:s'),
                // Sale del mismo reparto que el original.
                'fecha_entrega'    => $original->fecha_entrega,
                'nit'              => $original->nit,
                'nombre'           => $original->nombre,
                'tipo_comprobante' => $original->tipo_comprobante,
                'tipo_pago'        => $original->tipo_pago,
                'estado'           => 'ACTIVO',
                'subtotal'         => $subtotal,
                'descuento'        => $descuento,
                'total'            => round($subtotal - $descuento, 2),
                'observacion'      => trim('Retorno parcial de la nota ' . $original->id . '. ' . $nota),
                'pedido_nro'       => $original->pedido_nro,
                'pedido_tipo'      => $original->pedido_tipo,
                'factura_origen_id' => $original->id,
                // El reparto ya paso: el comprobante nuevo no vuelve a salir en
                // la lista del caminero ni se le pide cobrar de nuevo.
                'confirmado_camion' => true,
                'entregado_camion'  => true,
            ]);

            $nueva->detalles()->createMany($lineas);
            $this->moverStock($lineas, $nueva->id, $ci, date('Y-m-d H:i:s'), 'SALIDA');

            // La plata que el caminero cobro en la puerta era por esta venta,
            // no por la que se acaba de anular: si la entrega se quedara
            // apuntando al comprobante muerto, su reporte del dia no cerraria.
            DB::table('entregas')
                ->where('factura_id', $original->id)
                ->update([
                    'factura_id' => $nueva->id,
                    'monto'      => round($subtotal - $descuento, 2),
                ]);

            return $nueva;
        });

        if ($nueva->tipo_comprobante !== 'FACTURA') {
            return response()->json([
                'message'  => 'Nota ' . $original->id . ' anulada y reemplazada por la ' . $nueva->id
                    . '; al almacén volvió lo que el cliente devolvió',
                'anulada'  => $original->fresh(),
                'factura'  => $nueva->load('detalles'),
                'siat'     => $respuestaSiat,
            ], 201);
        }

        // Fuera de la transaccion, igual que al emitir: un problema con
        // Impuestos no debe deshacer el stock ni la anulacion, que ya paso.
        $siat = new SiatService();
        $nueva = config('siat.simulado')
            ? $siat->simularEmision($nueva)
            : $siat->emitirFactura($nueva, $usuario->CodAut);

        return response()->json([
            'message' => 'Factura ' . $original->id . ' anulada y reemplazada por la ' . $nueva->id
                . '. ' . $this->mensajeEmision($nueva),
            'anulada' => $original->fresh(),
            'factura' => $nueva->load('detalles'),
            'siat'    => [
                'estado'  => $nueva->estado_siat,
                'mensaje' => $nueva->mensaje_siat,
                'cuf'     => $nueva->cuf,
            ],
        ], 201);
    }

    /**
     * Voucher: la boleta de entrega, en tamano carta.
     *
     * Replica la boleta que se imprime en papel: cabecera con los datos del
     * cliente, la grilla de productos y el pie con literal, placa y totales.
     */
    public function voucher(Request $request, $id)
    {
        $factura = Factura::with(['detalles', 'cliente', 'vendedor'])->find($id);
        if (!$factura) {
            return response()->json(['message' => 'La venta no existe'], 404);
        }

        if ($factura->estado === 'ANULADO') {
            return response()->json(['message' => self::NO_IMPRIME_ANULADO], 422);
        }

        if ($bloqueo = $this->bloqueoCarga($factura)) {
            return response()->json(['message' => $bloqueo], 422);
        }

        $pdf = $this->pdf($this->conMarcas($request, function ($marca) use ($factura) {
            return $this->voucherHtml($factura, $marca);
        }), 'voucher_' . $factura->id);
        $this->marcarImpreso($request, [$factura->id]);

        return $pdf;
    }

    /**
     * Pedido contra facturacion, en una sola hoja: cada producto con lo que
     * pidio el preventista y lo que se cobro, y abajo el comentario de que
     * cambio (o que no hubo cambios). No marca el comprobante como impreso:
     * es un control, no el papel que va al cliente.
     */
    public function comparacion($id)
    {
        $factura = Factura::with(['detalles', 'cliente', 'vendedor'])->find($id);
        if (!$factura) {
            return response()->json(['message' => 'La venta no existe'], 404);
        }

        $servicio = new CambioPedido();
        $lineasPedido = $factura->pedido_nro ? $servicio->lineasPedido($factura) : collect();
        $diferencias = $lineasPedido->isNotEmpty() ? $servicio->diferencias($factura) : null;
        $cambiosPorCodigo = collect($diferencias['productos'] ?? [])->keyBy('cod_prod');

        // Un mismo codigo puede venir en varias lineas (otro precio, otro
        // canastillo): se junta por codigo, igual que al detectar el cambio.
        $pedido = $lineasPedido->groupBy(function ($l) { return trim((string) $l->cod_prod); });
        $vendido = $factura->detalles->groupBy(function ($d) { return trim((string) $d->cod_prod); });
        $codigos = $pedido->keys()->merge($vendido->keys())->unique()->values();

        $cant = function ($v) {
            return rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.');
        };
        $precios = function ($lineas) {
            return collect($lineas)->map(function ($l) { return number_format((float) $l->precio, 2); })
                ->unique()->implode(' / ');
        };

        // Lo que el cliente devolvio al caminero (retorno parcial): el de este
        // comprobante o, si se reemitio, el de la venta anulada de la que salio.
        $retorno = $this->retornoDe($factura->id);
        if (!$retorno && $factura->pedido_nro) {
            $anterior = Factura::where('pedido_nro', $factura->pedido_nro)->where('pedido_tipo', $factura->pedido_tipo)
                ->where('estado', 'ANULADO')->where('id', '<', $factura->id)->orderByDesc('id')->value('id');
            $retorno = $anterior ? $this->retornoDe($anterior) : null;
        }
        $devueltos = collect($retorno['items'] ?? [])->filter(function ($item) {
            return abs((float) ($item['original'] ?? 0) - (float) ($item['entregado'] ?? 0)) >= 0.001;
        })->map(function ($item) { return trim((string) ($item['cod_prod'] ?? '')); })->all();

        $filas = '';
        $totPedido = 0;
        $totVendido = 0;
        foreach ($codigos as $i => $codigo) {
            $p = $pedido->get($codigo);
            $v = $vendido->get($codigo);
            $cambio = $cambiosPorCodigo->get((string) $codigo);
            $tipo = $cambio['cambio'] ?? null;
            $camposCambiados = collect($cambio['campos'] ?? [])->pluck('campo')->all();

            $subPedido = $p ? (float) $p->sum('subtotal') : 0;
            $subVendido = $v ? (float) $v->sum('subtotal') : 0;
            $totPedido += $subPedido;
            $totVendido += $subVendido;
            $dif = $subVendido - $subPedido;

            $marca = function ($campo, $texto) use ($tipo, $camposCambiados) {
                return $tipo === 'MODIFICADO' && in_array($campo, $camposCambiados, true) ? "<b class='cambio'>$texto</b>" : $texto;
            };
            $nombre = $v ? $v->first()->nombre : $p->first()->nombre;
            $estado = $tipo === 'AGREGADO' ? 'AGREGADO' : ($tipo === 'QUITADO' ? 'QUITADO' : ($tipo ? 'CAMBIÓ' : 'IGUAL'));
            $clase = $tipo === 'AGREGADO' ? 'agregado' : ($tipo === 'QUITADO' ? 'quitado' : ($tipo ? 'modificado' : ($i % 2 ? 'par' : '')));
            // Sin otro cambio, lo devuelto en el retorno parcial se marca aparte.
            if (!$tipo && in_array((string) $codigo, $devueltos, true)) {
                $estado = 'RETORNO';
                $clase = 'devuelto';
            }
            $peso = $v ? (float) $v->sum('peso') : 0;

            $filas .= "<tr class='$clase'>"
                . "<td class='cod'>" . e($codigo) . '</td>'
                . '<td>' . e($nombre) . '</td>'
                . "<td class='r sep'>" . ($p ? $marca('cantidad', $cant($p->sum('cantidad'))) : '—') . '</td>'
                . "<td class='r'>" . ($p ? $marca('precio', $precios($p)) : '—') . '</td>'
                . "<td class='r'>" . ($p ? number_format($subPedido, 2) : '—') . '</td>'
                . "<td class='r sep'>" . ($v ? $marca('cantidad', $cant($v->sum('cantidad'))) : '—') . '</td>'
                . "<td class='r'>" . ($peso > 0 ? $cant($peso) : '—') . '</td>'
                . "<td class='r'>" . ($v ? $marca('precio', $precios($v)) : '—') . '</td>'
                . "<td class='r'>" . ($v ? number_format($subVendido, 2) : '—') . '</td>'
                // Sin cambio, la diferencia solo es el peso real de la balanza.
                . "<td class='r sep" . ($tipo ? '' : ' gris') . "'>" . (abs($dif) >= 0.005 ? ($dif > 0 ? '+' : '') . number_format($dif, 2) : '') . '</td>'
                . "<td class='c est'>" . $estado . '</td>'
                . '</tr>';
        }

        // El comentario de abajo: que cambio, o que se facturo tal cual.
        if (!$factura->pedido_nro) {
            $comentario = "<div class='nota'>Venta directa: no viene de un pedido, no hay contra qué comparar.</div>";
        } elseif ($diferencias === null) {
            $comentario = "<div class='nota'>El pedido #" . (int) $factura->pedido_nro
                . ' ya no existe o fue eliminado: no hay contra qué comparar.</div>';
        } elseif (!count($diferencias['resumen'])) {
            $comentario = "<div class='sin-cambios'>NO HUBO CAMBIOS: se facturó tal cual el pedido.</div>";
        } else {
            $items = '';
            foreach ($diferencias['cabecera'] as $c) {
                $items .= '<li><b>' . e($c['etiqueta']) . ':</b> ' . e($c['antes'] ?? '—') . ' &rarr; ' . e($c['despues'] ?? '—') . '</li>';
            }
            foreach ($diferencias['productos'] as $c) {
                $detalle = collect($c['campos'])->map(function ($campo) use ($cant) {
                    $f = function ($x) use ($campo, $cant) {
                        return $x === null ? '—' : ($campo['campo'] === 'cantidad' ? $cant($x) : $x);
                    };
                    return mb_strtolower($campo['etiqueta']) . ' ' . e($f($campo['antes'])) . ' &rarr; ' . e($f($campo['despues']));
                })->implode(', ');
                $titulo = $c['cambio'] === 'AGREGADO' ? 'Se agregó' : ($c['cambio'] === 'QUITADO' ? 'Se quitó' : 'Cambió');
                $items .= '<li><b>' . $titulo . ' ' . e($c['nombre']) . ':</b> ' . $detalle . '</li>';
            }
            $comentario = "<div class='con-cambios'><div class='tit-cambios'>HUBO CAMBIOS ("
                . count($diferencias['resumen']) . ")</div><ul>$items</ul></div>";
        }

        $comentario .= $retorno ? $this->bloqueRetorno($retorno, $cant) : '';

        $vendedor = $factura->vendedor
            ? trim(implode(' ', array_filter([trim($factura->vendedor->Nombre1), trim($factura->vendedor->App1), trim($factura->vendedor->Apm)])))
            : '';
        $anulado = $factura->estado === 'ANULADO'
            ? "<div class='aviso'>ANULADO &middot; " . e($factura->motivo_anulacion) . '</div>'
            : '';
        $difTotal = $totVendido - $totPedido;

        // Todo tiene que entrar en una hoja: mientras mas productos, mas chica
        // la letra y mas juntas las filas (un pedido grande pasa de 50).
        $n = $codigos->count();
        [$letra, $relleno] = $n > 45 ? ['6.6px', '0.6px 3px'] : ($n > 30 ? ['7.3px', '1.4px 3px'] : ['8.3px', '2.6px 3px']);
        $chica = $n > 30;

        $html = '<style>' . $this->estilosImpresion() . "
            @page { margin: 9mm 9mm 9mm 9mm }
            .titulo { font-size: 13px; font-weight: bold; letter-spacing: .5px }
            .sub { font-size: 8.5px; color: #555 }
            .comp td, .comp td.cod { font-size: $letra; padding: $relleno; line-height: 1.15 }
            .comp td.gris { color: #999 }
            .comp th { font-size: 7.5px; padding: 3px 3px }
            .comp th.grupo { background: #546e7a; font-size: 8px }
            .comp .sep { border-left: 1.5px solid #90a4ae }
            .comp tr.agregado td { background: #e8f5e9 }
            .comp tr.quitado td { background: #ffebee; color: #b71c1c }
            .comp tr.modificado td { background: #fff8e1 }
            .comp tr.devuelto td { background: #fbe9e7; color: #bf360c }
            .comp .cambio { color: #c62828 }
            .comp .est { font-size: 7px; font-weight: bold }
            .comp tr.total td { background: #37474f; color: #fff; font-weight: bold; font-size: 9px }
            .sin-cambios { border: 1.5px solid #2e7d32; background: #e8f5e9; color: #1b5e20; font-weight: bold;
                           text-align: center; padding: 7px; margin-top: 8px; font-size: 11px }
            .con-cambios { border: 1.5px solid #e65100; background: #fff3e0; padding: 5px 8px; margin-top: 8px }
            .tit-cambios { font-weight: bold; color: #e65100; font-size: 10px }
            .con-cambios ul { margin: 3px 0 0 14px; padding: 0 }
            .con-cambios li { font-size: " . ($chica ? '7.8px' : '8.5px') . "; margin-bottom: 1px }
            .nota { border: 1px solid #999; background: #f5f5f5; padding: 6px; margin-top: 8px; text-align: center; font-size: 9.5px }
            .retorno { border: 1.5px solid #d84315; background: #fbe9e7; padding: 5px 8px; margin-top: 8px }
            .tit-retorno { font-weight: bold; color: #bf360c; font-size: 10px }
            .retorno .sub { color: #5d4037 }
            .retorno table { width: 100%; border-collapse: collapse; margin-top: 4px }
            .retorno th { background: #d84315; color: #fff; font-size: 7.5px; padding: 3px; text-transform: uppercase }
            .retorno td { font-size: 8.3px; padding: 2px 3px; border-bottom: 1px solid #ffccbc; background: #fff }
            .retorno tr.tot td { font-weight: bold; background: #ffccbc }
        </style>
        <table style='width:100%; border-collapse:collapse'><tr>
            <td>
                <div class='titulo'>PEDIDO vs FACTURACIÓN</div>
                <div class='sub'>Lo que pidió el preventista contra lo que se cobró</div>
            </td>
            <td class='r'>
                <div class='titulo'>Venta #" . $factura->id
                    . ($factura->pedido_nro ? ' &middot; Pedido #' . (int) $factura->pedido_nro : '') . "</div>
                <div class='sub'>" . $factura->fecha->format('d/m/Y') . ' ' . e($factura->hora)
                    . ' &middot; ' . e($factura->tipo_comprobante) . ' &middot; ' . e($this->textoPago($factura)) . "</div>
            </td>
        </tr></table>
        $anulado
        <table class='datos' style='margin-top:5px'>
            <tr>
                <td style='width:50%'><span class='et'>Cliente</span><br><b>" . e($factura->nombre ?: ($factura->cliente->Nombres ?? 'Sin cliente')) . "</b></td>
                <td style='width:20%'><span class='et'>CI / NIT</span><br>" . e($factura->nit ?: '—') . "</td>
                <td><span class='et'>Vendedor</span><br>" . e($vendedor ?: '—') . "</td>
            </tr>
        </table>

        <table class='detalle comp' style='margin-top:6px'>
            <tr>
                <th colspan='2'></th>
                <th colspan='3' class='grupo sep'>PEDIDO</th>
                <th colspan='4' class='grupo sep'>FACTURADO</th>
                <th colspan='2' class='grupo sep'></th>
            </tr>
            <tr>
                <th style='width:7%'>Código</th>
                <th>Producto</th>
                <th class='sep' style='width:6%'>Cant</th>
                <th style='width:7%'>P. Unit</th>
                <th style='width:8%'>Subtotal</th>
                <th class='sep' style='width:6%'>Cant</th>
                <th style='width:6%'>Kg</th>
                <th style='width:7%'>P. Unit</th>
                <th style='width:8%'>Subtotal</th>
                <th class='sep' style='width:7%'>Dif. Bs</th>
                <th style='width:7%'>Estado</th>
            </tr>
            $filas
            <tr class='total'>
                <td colspan='2' class='r'>TOTAL (" . $codigos->count() . " productos)</td>
                <td colspan='3' class='r sep'>" . number_format($totPedido, 2) . "</td>
                <td colspan='4' class='r sep'>" . number_format($totVendido, 2) . "</td>
                <td class='r sep'>" . (abs($difTotal) >= 0.005 ? ($difTotal > 0 ? '+' : '') . number_format($difTotal, 2) : '') . "</td>
                <td></td>
            </tr>
        </table>
        $comentario"
        . ($factura->observacion ? "<div class='sub' style='margin-top:5px'><b>Observación:</b> " . e($factura->observacion) . '</div>' : '');

        return $this->pdf($html, 'pedido_vs_factura_' . $factura->id);
    }

    /**
     * Que hojas salen de un comprobante: copias=1 da ORIGINAL y COPIA
     * seguidas; marca=ORIGINAL o marca=COPIA, solo esa. Sin nada, COPIA como
     * siempre.
     */
    private function marcasPedidas(Request $request)
    {
        if ($request->boolean('copias')) {
            return ['ORIGINAL', 'COPIA'];
        }
        $marca = strtoupper(trim((string) $request->query('marca', '')));
        return [in_array($marca, ['ORIGINAL', 'COPIA'], true) ? $marca : 'COPIA'];
    }

    /** Arma las hojas pedidas de un comprobante, una por pagina. */
    private function conMarcas(Request $request, callable $hoja)
    {
        return collect($this->marcasPedidas($request))
            ->map($hoja)
            ->implode("<div style='page-break-after: always'></div>");
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
    private function voucherHtml(Factura $factura, $marca = 'COPIA')
    {
        $cliente = $factura->cliente;

        // Una baja (por bonificacion o por calidad) se imprime a nombre de la
        // cuenta de baja y con el cliente que la recibe en la observacion,
        // como en la hoja de preparacion. Solo en el papel: el comprobante
        // guardado no cambia.
        $nombre = $factura->nombre;
        $observacion = $factura->observacion;
        $cuentaBaja = $factura->pedido_nro ? $this->cuentaDeBaja($factura->pedido_nro, $factura->pedido_tipo) : null;
        if ($cuentaBaja) {
            $nombre = $cuentaBaja;
            $observacion = $this->observacionDeBaja(trim((string) ($factura->nombre ?: ($cliente->Nombres ?? ''))), $observacion);
        }

        $vendedor = $factura->vendedor
            ? trim(implode(' ', array_filter([
                trim($factura->vendedor->Nombre1),
                trim($factura->vendedor->App1),
                trim($factura->vendedor->Apm),
            ])))
            : '';

        $placa = $this->camionDeFactura($factura);

        $trozados = $this->codigosTrozados($factura->detalles);

        // Si algo se peso con canastillos la boleta lleva, como la de papel,
        // P. Bruto, canastillos y sus kg antes del P. Neto. Sin canastillos
        // queda la grilla de siempre.
        $conCanastillos = $factura->detalles->contains(function ($d) {
            return (float) $d->peso_bruto > 0;
        });

        $filas = '';
        foreach ($factura->detalles as $i => $d) {
            // Como en la boleta de papel: CANT son las piezas que se entregan y
            // el peso de la balanza va en KG / P. NETO, que es lo que se cobra
            // en lo que va a granel.
            $peso = (float) $d->peso;
            $par = $i % 2 ? " class='par'" : '';
            // Lo trozado no se cuenta: en su lugar va un guion.
            $trozado = !empty($trozados[trim((string) $d->cod_prod)]);

            if ($conCanastillos) {
                // Lo pesado sin canastillos tiene el bruto igual al neto.
                $bruto = (float) $d->peso_bruto > 0 ? (float) $d->peso_bruto : $peso;
                $canastillos = (float) $d->peso_bruto > 0 ? (int) $d->canastillos : 0;
                $columnasPeso = "<td class='r'>" . ($bruto > 0 ? number_format($bruto, 2) : '—') . '</td>'
                    . "<td class='c'>" . ($canastillos > 0 ? $canastillos : '—') . '</td>';
            } else {
                $columnasPeso = "<td class='r'>" . ($peso > 0 ? number_format($peso, 3) : '—') . '</td>';
            }

            $filas .= "<tr$par>"
                . "<td class='r'>" . ($trozado ? '—' : number_format($d->cantidad, 2)) . '</td>'
                . "<td class='cod'>" . e($d->cod_prod) . '</td>'
                . '<td>' . e($d->nombre) . '</td>'
                . "<td class='c'>" . e($d->unidad) . '</td>'
                . $columnasPeso
                . "<td class='r'>" . number_format($d->cantidad_facturada, 2) . '</td>'
                . "<td class='r'>" . number_format($d->precio, 2) . '</td>'
                . "<td class='r'><b>" . number_format($d->subtotal, 2) . '</b></td>'
                . '</tr>';
        }

        // El voucher va en negro y sin logo, para que el camionero no lo
        // confunda con la factura (que sigue en rojo con el logo).
        $caja = "<table class='caja-doc caja-negra'>
            <tr><td colspan='2' class='tit'>BOLETA DE ENTREGA</td></tr>
            <tr><td class='et'>Nro</td><td class='r nro'>" . $factura->id . "</td></tr>
            <tr><td class='et'>Fecha</td><td class='r'>" . $factura->fecha->format('d/m/Y') . "</td></tr>
            <tr><td class='et'>Hora</td><td class='r'>" . e($factura->hora) . "</td></tr>
        </table>";

        // Al centro, grande, el id de la venta: es el correlativo con el que
        // se controla el reparto.
        $emisor = config('siat.emisor');
        $cabecera = "<table class='cabecera'>
            <tr>
                <td style='width:250px'>
                    <div class='empresa-negra'>" . e($emisor['nombre']) . "</div>
                    <div class='empresa-dato'>
                        " . e($emisor['sucursal']) . " &middot; NIT " . e(config('siat.nit')) . "<br>
                        " . e($emisor['direccion']) . "<br>
                        Telf. " . e($emisor['telefono']) . " &middot; " . e($emisor['ciudad']) . "
                    </div>
                </td>
                <td class='id-venta'>
                    <div class='id-et'>N&ordm; DE VENTA</div>
                    <div class='id-nro'>" . $factura->id . "</div>
                </td>
                <td style='width:210px'>$caja</td>
            </tr>
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
            .empresa-negra { font-size: 14px; font-weight: bold; color: #000; letter-spacing: .5px }
            .id-venta { text-align: center; vertical-align: middle }
            .id-et { font-size: 9px; font-weight: bold; color: #444; letter-spacing: 2px }
            .id-nro { font-size: 34px; font-weight: bold; color: #000; line-height: 1.1 }
            .caja-negra { border-color: #000 }
            .caja-negra .tit { background: #000 }
            .caja-negra .nro { color: #000 }
        </style>"
        . $cabecera
        . $anulado
        . "<table class='datos'>
            <tr>
                <td style='width:52%'><span class='et'>Cliente</span><br><b>"
                    . e($nombre ?: 'Sin cliente') . "</b></td>
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
                <td><span class='et'>Tipo de pago</span><br><b>" . e($this->textoPago($factura)) . "</b></td>
                <td>" . $this->celdaCamion($factura, $placa) . "</td>
            </tr>
            <tr>
                <td colspan='3'><span class='et'>Observación</span><br>"
                    . e($this->observacionImpresa($factura, $observacion) ?: '—') . "</td>
            </tr>
        </table>

        <table class='detalle'>
            <tr>
                <th style='width:7%'>Cant</th>
                <th style='width:8%'>Código</th>
                <th>Concepto</th>
                <th style='width:6%'>Unid</th>
                " . ($conCanastillos
                    ? "<th style='width:9%'>P. Bruto</th><th style='width:7%'>Canast.</th>"
                    : "<th style='width:9%'>Peso Kg</th>") . "
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
            <div class='copia'>" . e($marca) . "</div>
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
    public function factura(Request $request, $id)
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

        if ($factura->estado === 'ANULADO') {
            return response()->json(['message' => self::NO_IMPRIME_ANULADO], 422);
        }

        if ($bloqueo = $this->bloqueoCarga($factura)) {
            return response()->json(['message' => $bloqueo], 422);
        }

        $pdf = $this->pdf($this->conMarcas($request, function ($marca) use ($factura) {
            return $this->facturaHtml($factura, $marca);
        }), 'factura_' . $factura->id);
        $this->marcarImpreso($request, [$factura->id]);

        return $pdf;
    }

    /** La factura como HTML: aparte, para poder juntar varias en un PDF. */
    private function facturaHtml(Factura $factura, $marca = 'COPIA')
    {
        $placa = $this->camionDeFactura($factura);

        // En la factura la cantidad declarada son los kilos que se cobran, asi
        // que de como se llego a ese peso no queda rastro: cuando algo se peso
        // en canastillos se agrega el bruto con los canastillos que se le
        // descontaron. Son columnas informativas y lo fiscal no cambia.
        $conCanastillos = $factura->detalles->contains(function ($d) {
            return (float) $d->peso_bruto > 0;
        });

        // Piezas va siempre: en lo que se vende por peso son las piezas que se
        // entregan (la Cantidad fiscal son los kilos). En lo que va por unidad
        // la Cantidad ya son las piezas, y lo trozado no se cuenta: en los dos
        // casos la celda queda vacia.
        $trozados = $this->codigosTrozados($factura->detalles);

        $filas = '';
        foreach ($factura->detalles as $i => $d) {
            $par = $i % 2 ? " class='par'" : '';
            $peso = (float) $d->peso;
            $conPiezas = $peso > 0 && empty($trozados[trim((string) $d->cod_prod)]);
            $piezas = "<td class='r'>" . ($conPiezas ? number_format($d->cantidad, 2) : '') . '</td>';

            $columnasPeso = '';
            if ($conCanastillos) {
                // Lo pesado sin canastillos tiene el bruto igual al neto.
                $bruto = (float) $d->peso_bruto > 0 ? (float) $d->peso_bruto : $peso;
                $canastillos = (float) $d->peso_bruto > 0 ? (int) $d->canastillos : 0;
                $columnasPeso = "<td class='r'>" . ($bruto > 0 ? number_format($bruto, 2) : '—') . '</td>'
                    . "<td class='c'>" . ($canastillos > 0 ? $canastillos : '—') . '</td>';
            }

            $filas .= "<tr$par>"
                . "<td class='cod'>" . e($d->cod_prod) . '</td>'
                . $piezas
                // Lo declarado a Impuestos es lo que se cobra: en lo que va por
                // kilo, el peso. Tiene que coincidir con lo que manda el SIAT.
                . "<td class='r'>" . number_format($d->cantidad_facturada, 2) . '</td>'
                . "<td class='c uni'>" . e($peso > 0 || $d->unidad === 'KG'
                    ? 'KILOGRAMO' : 'UNIDAD (SERVICIOS)') . '</td>'
                . '<td>' . e($d->nombre) . '</td>'
                . $columnasPeso
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
            $qr = "<td style='width:92px; text-align:center; vertical-align:top'>"
                . "<img src='data:image/png;base64,$png' style='width:85px; height:85px'></td>";
        }

        $anulado = $factura->estado === 'ANULADO'
            ? "<div class='aviso'>FACTURA ANULADA &middot; " . e($factura->motivo_anulacion) . '</div>'
            : '';

        $html = '<style>' . $this->estilosImpresion() . "
            .subtitulo { text-align: center; font-size: 8.5px; color: #666; margin: 6px 0 2px }
            .id-venta { text-align: center; vertical-align: middle }
            .id-et { font-size: 9px; font-weight: bold; color: #444; letter-spacing: 2px }
            .id-nro { font-size: 34px; font-weight: bold; color: #000; line-height: 1.1 }

            /* La factura va apretada para que unos 30 productos entren en una
               hoja. Todo cuelga de .fac: en un lote mezclado los vouchers
               conservan su formato. */
            .fac .logo { width: 86px }
            .fac .empresa { font-size: 12px }
            .fac .empresa-dato { font-size: 7.5px; line-height: 1.3 }
            .fac .caja-doc .tit { font-size: 9px; padding: 2px }
            .fac .caja-doc td { padding: 1px 6px; font-size: 8px }
            .fac .caja-doc .nro { font-size: 13px }
            .fac .id-et { font-size: 7.5px }
            .fac .id-nro { font-size: 26px }
            .fac .subtitulo { font-size: 7.5px; margin: 2px 0 1px }
            .fac .datos { margin-top: 3px }
            .fac .datos td { padding: 1.5px 5px; font-size: 8px }
            .fac .datos .et { font-size: 6.5px }
            .fac .detalle { margin-top: 4px }
            .fac .detalle th { padding: 3px; font-size: 7px; letter-spacing: .2px }
            .fac .detalle td { padding: 1px 3px; font-size: 8px; line-height: 1.15 }
            .fac .detalle .cod { font-size: 7.5px }
            .fac .detalle .uni { font-size: 6.5px; white-space: nowrap }
            .fac .literal { padding: 3px 6px; font-size: 8px; line-height: 1.3 }
            .fac .totales td { padding: 1.5px 7px; font-size: 8.5px }
            .fac .totales .final td { font-size: 10.5px }
            .fac .legal { font-size: 6.5px; line-height: 1.3 }
            .fac .copia { font-size: 8.5px; margin-top: 3px }
        </style>
        <div class='fac'>"
        // La factura fiscal va con la razon social registrada en Impuestos.
        // Al centro, como en el voucher, el id de la venta para el control
        // del reparto (el numero fiscal sigue en la caja de la derecha).
        . $this->cabeceraEmisor(
            $caja,
            \App\Models\SiatConfiguracion::activa()->razon_social ?: config('siat.razon_social'),
            "<div class='id-et'>N&ordm; DE VENTA</div><div class='id-nro'>" . $factura->id . '</div>'
        )
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
                    . $this->fechaImpresaFactura($factura) . "</td>
            </tr>
            <tr>
                <td><span class='et'>Cod. cliente</span><br>" . ($factura->cliente_id ?: '—') . "</td>
                <td><span class='et'>Complemento</span><br>"
                    . e(trim((string) ($factura->cliente->complto ?? '')) ?: '—') . "</td>
                <td><span class='et'>Forma de pago</span><br>" . e($this->textoPago($factura)) . "</td>
            </tr>
            <tr>
                <td>" . $this->celdaCamion($factura, $placa) . "</td>
                <td><span class='et'>Pedido</span><br>" . ($factura->pedido_nro ?: '—') . "</td>
                <td><span class='et'>Observación</span><br>" . e($this->observacionImpresa($factura, $factura->observacion) ?: '—') . "</td>
            </tr>
        </table>

        <table class='detalle'>
            <tr>
                <th style='width:8%'>Código</th>
                <th style='width:7%'>Piezas</th>
                <th style='width:8%'>Cantidad</th>
                <th style='width:12%'>Unidad</th>
                <th>Descripción</th>
                " . ($conCanastillos
                    ? "<th style='width:8%'>P. Bruto</th><th style='width:6%'>Canast.</th>"
                    : '') . "
                <th style='width:10%'>P. Unitario</th>
                <th style='width:8%'>Descuento</th>
                <th style='width:11%'>Importe</th>
            </tr>
            $filas
        </table>

        <table style='width:100%; margin-top:4px; border-collapse:collapse'><tr>
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

        <div class='copia'>" . e($marca) . "</div>
        </div>";

        return $html;
    }

    /** Ventas por pedido del lote: cada parte sale en un request corto. */
    private const POR_PARTE = 30;

    /**
     * Las ventas que entran en un lote, o la respuesta que explica por que no
     * se puede imprimir.
     *
     * Cada lote lleva solo lo suyo: el de facturas, las ventas entregadas como
     * factura; el de vouchers, las que salieron como voucher. Asi lo del dia se
     * imprime de una vez sin que una misma venta salga en los dos lotes.
     *
     * @return array [$facturas, $error]
     */
    private function facturasDelLote(Request $request, $documento)
    {
        $facturas = $this->filtrar($request)
            // Aunque el filtro muestre anulados, esos no salen en el papel.
            ->where('estado', '<>', 'ANULADO')
            ->when($documento === 'factura', function ($q) {
                // La factura solo existe si la venta se entrego como factura.
                $q->where('tipo_comprobante', 'FACTURA');
            })
            ->when($documento === 'voucher', function ($q) {
                // Las que se entregaron como factura no van en el lote de
                // vouchers: cada venta se imprime en uno solo.
                $q->where('tipo_comprobante', '<>', 'FACTURA');
            })
            // Sale en el orden en que se reparte: embutidos, pollo, cerdo y
            // podium/huevo; despues res y la venta directa (sin tipo). Dentro
            // de cada tipo, por numero de venta. Las partes usan el mismo
            // orden, asi que juntas quedan igual que un lote entero.
            ->reorder()
            ->orderByRaw("CASE UPPER(TRIM(COALESCE(facturas.pedido_tipo, '')))
                WHEN 'NORMAL' THEN 1 WHEN 'POLLO' THEN 2 WHEN 'CERDO' THEN 3
                WHEN 'PODIUM' THEN 4 WHEN 'HUEVO' THEN 5 WHEN 'RES' THEN 6 ELSE 7 END")
            ->orderBy('facturas.id')
            // Uno de mas para saber si el filtro se pasa del tope: antes se
            // cortaba en silencio y quedaban ventas sin imprimir.
            ->limit(self::MAX_LOTE + 1)
            ->get();

        if ($facturas->count() > self::MAX_LOTE) {
            return [null, response()->json([
                'message' => 'Son más de ' . self::MAX_LOTE . ' comprobantes; filtrá por camión o por hora para imprimirlos en partes',
            ], 422)];
        }

        if ($facturas->isEmpty()) {
            $vacio = [
                'factura' => 'No hay facturas vigentes en lo que estás viendo (las anuladas no se imprimen)',
                'voucher' => 'No hay vouchers vigentes en lo que estás viendo (los anulados no se imprimen)',
                'todos' => 'No hay comprobantes vigentes en lo que estás viendo (los anulados no se imprimen)',
            ];

            return [null, response()->json(['message' => $vacio[$documento]], 422)];
        }

        // Un lote sale entero o no sale: si alguno de los camiones todavia no
        // reviso su carga se frena todo, porque el papel se reparte junto.
        $camiones = $this->camionesSinVerificar($facturas);
        if (!empty($camiones)) {
            return [null, response()->json([
                'message' => count($camiones) === 1
                    ? 'El camión ' . $camiones[0] . ' todavía no verificó su carga; no se puede imprimir el lote'
                    : 'Estos camiones todavía no verificaron su carga: ' . implode(', ', $camiones),
            ], 422)];
        }

        return [$facturas, null];
    }

    /** 'todos' saca el paquete completo del filtro sin separar por tipo. */
    private function documentoDeLote($documento)
    {
        return in_array($documento, ['factura', 'voucher', 'todos'], true) ? $documento : 'voucher';
    }

    /**
     * Las ventas de un lote, ya revisadas y en orden, para que la pantalla
     * pida el PDF en partes: cientos de hojas en un solo request dejaban a
     * dompdf sin memoria o a la pantalla esperando sin fin.
     */
    public function loteIds(Request $request, $documento)
    {
        [$facturas, $error] = $this->facturasDelLote($request, $this->documentoDeLote($documento));
        if ($error) {
            return $error;
        }

        return response()->json([
            'ids'       => $facturas->pluck('id')->all(),
            'por_parte' => self::POR_PARTE,
        ]);
    }

    /**
     * Marca como impresas las ventas de un lote armado en partes: recien
     * cuando el PDF entero llego a la pantalla, no parte por parte.
     */
    public function marcarImpresos(Request $request)
    {
        $ids = $this->idsSeleccionados($request);
        if (empty($ids) || count($ids) > self::MAX_LOTE) {
            return response()->json(['message' => 'No hay comprobantes para marcar'], 422);
        }

        $request->merge(['imprimir' => 1]);
        $this->marcarImpreso($request, $ids);

        return response()->json(['marcadas' => count($ids)]);
    }

    /**
     * Los comprobantes del filtro (o de los ids pedidos) en un solo PDF, uno
     * por hoja. La pantalla lo pide de a partes con ids=...; sin ids sale el
     * filtro entero, hasta el tope.
     */
    public function lote(Request $request, $documento)
    {
        // Muchas hojas juntas: con 128 MB dompdf se queda sin memoria.
        ini_set('memory_limit', '2048M');
        set_time_limit(600);

        $documento = $this->documentoDeLote($documento);

        [$facturas, $error] = $this->facturasDelLote($request, $documento);
        if ($error) {
            return $error;
        }

        // El filtro trae el cliente con pocas columnas (las de la grilla); el
        // papel necesita el completo (direccion, telefono, territorio,
        // complemento), igual que al imprimir una sola venta.
        $facturas->load('cliente');

        // Con copias=1 cada comprobante sale dos veces seguidas: ORIGINAL para
        // el cliente y COPIA para la empresa (4 ventas = 8 hojas).
        $marcas = $this->marcasPedidas($request);

        $paginas = $facturas->flatMap(function ($factura) use ($documento, $marcas) {
            // En el lote mezclado manda como se entrego cada venta; en los
            // otros dos el filtro ya dejo solo las que corresponden.
            $comoFactura = $documento === 'factura'
                || ($documento === 'todos' && $factura->tipo_comprobante === 'FACTURA');

            return array_map(function ($marca) use ($factura, $comoFactura) {
                return $comoFactura
                    ? $this->facturaHtml($factura, $marca)
                    : $this->voucherHtml($factura, $marca);
            }, $marcas);
        })->implode("<div style='page-break-after: always'></div>");

        $nombre = $documento === 'todos' ? 'comprobantes' : $documento . 's';

        $pdf = $this->pdf($paginas, $nombre . '_' . date('Y-m-d'));
        $this->marcarImpreso($request, $facturas->pluck('id')->all());

        return $pdf;
    }

    /**
     * Deja el rastro de que el comprobante salio a la impresora. Solo cuando
     * la pantalla lo pide con imprimir=1: el mismo PDF tambien se baja como
     * archivo y eso no cuenta como impreso.
     */
    private function marcarImpreso(Request $request, array $ids)
    {
        if (!$request->boolean('imprimir') || empty($ids)) {
            return;
        }

        $usuario = $request->user();
        DB::table('facturas')->whereIn('id', $ids)->update([
            'impreso_veces' => DB::raw('impreso_veces + 1'),
            'impreso_at'    => now(),
            'impreso_por'   => mb_substr(trim($usuario->Nombre1 . ' ' . $usuario->App1), 0, 100),
        ]);
    }

    /**
     * Reporte del listado: lo mismo que se ve en pantalla, para llevar.
     *
     * contenido = ventas -> una fila por comprobante, con sus totales.
     * contenido = cambios -> una fila por producto que salio con otra cantidad
     * de la que pedia el pedido, que es lo que no se puede reconstruir mirando
     * la factura sola.
     */
    /**
     * Reporte general de los comprobantes del filtro en Excel plano: cada
     * comprobante (activos y anulados) con su pedido, camion, cliente, quien
     * lo cobro y quien lo anulo, de que comprobante salio (padre) y cual lo
     * reemplazo (hijo), y una hoja con una fila por producto para filtrar.
     *
     * El filtro de estado se ignora: lo anulado tiene que salir siempre para
     * poder seguir las cadenas de refacturacion.
     */
    public function reporteGeneral(Request $request)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        // solo_anulados: la pestaña Anulados de Reportes usa este mismo armado.
        $soloAnulados = $request->boolean('solo_anulados');
        $request->merge(['estado' => $soloAnulados ? 'ANULADO' : '']);
        $facturas = $this->filtrar($request)->reorder('id')->get();
        $comoJson = $request->input('formato') === 'json';
        if ($facturas->isEmpty()) {
            return $comoJson
                ? response()->json(['filas' => []])
                : response()->json(['message' => $soloAnulados ? 'No hay comprobantes anulados en este filtro' : 'No hay comprobantes en este filtro'], 422);
        }

        $cadenas = $this->cadenasFacturas($facturas);
        $comprobantes = $this->filasGeneralComprobantes($facturas, $cadenas);

        // Para la pantalla: las mismas filas, con las fechas como texto.
        if ($comoJson) {
            $porId = $facturas->keyBy('id');
            return response()->json(['filas' => collect($comprobantes)->map(function ($fila, $id) use ($porId) {
                $f = $porId[$id];
                $fila['anulado'] = $fila['_anulado'];
                unset($fila['_anulado']);
                $fila['fecha'] = optional($f->fecha)->format('d/m/Y');
                $fila['fecha_entrega'] = $f->fecha_entrega ? date('d/m/Y', strtotime((string) $f->fecha_entrega)) : '';
                return $fila;
            })->values()]);
        }
        $empresa = config('siat.emisor')['nombre'] ?? 'Sofia';
        list(, $rango) = $this->tituloReporte('ventas', $request);
        $subtitulo = $empresa . ' · ' . $rango . ' · generado el ' . date('d/m/Y H:i');

        $libro = new Spreadsheet();
        $libro->getProperties()->setCreator($empresa)->setTitle('Reporte general de comprobantes');
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $resumen = $libro->getActiveSheet();
        $resumen->setTitle('Resumen');
        $this->hojaGeneralResumen($resumen, $comprobantes, $subtitulo);

        $columnas = $this->columnasGeneralComprobante();
        $hoja = $libro->createSheet();
        $hoja->setTitle('Comprobantes');
        $hoja->getTabColor()->setRGB('1565C0');
        $this->tablaGeneralExcel($hoja, 'COMPROBANTES (UNA FILA POR BOLETA / FACTURA)', $subtitulo,
            $columnas, $comprobantes, '1565C0',
            ['efectivo', 'qr', 'entrega_cobrado', 'entrega_efectivo', 'entrega_qr', 'subtotal', 'descuento', 'total', 'total_vigente'],
            '"Total vigente" no suma lo anulado. El total de la fila final sigue a los filtros.');

        $hoja = $libro->createSheet();
        $hoja->setTitle('Productos (plano)');
        $hoja->getTabColor()->setRGB('2E7D32');
        $this->tablaGeneralExcel($hoja, 'DETALLE PLANO: UNA FILA POR PRODUCTO DE CADA COMPROBANTE', $subtitulo,
            array_merge($columnas, $this->columnasGeneralProducto()),
            $this->filasGeneralProductos($facturas, $comprobantes), '2E7D32',
            ['importe', 'importe_vigente'],
            'Los datos de la boleta se repiten en cada producto: para montos sume "Importe" o "Importe vigente", no "Total".');

        $hoja = $libro->createSheet();
        $hoja->setTitle('Padres e hijos');
        $hoja->getTabColor()->setRGB('6A1B9A');
        $this->hojaGeneralCadenas($hoja, $cadenas, $comprobantes, $subtitulo);

        $libro->setActiveSheetIndex(0);
        $writer = new Xlsx($libro);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'reporte_general_' . date('Y-m-d') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Padre e hijos de cada comprobante, mirando tambien los que quedan fuera
     * del filtro (el original de una cadena puede ser de otro dia).
     *
     * - Retorno parcial: el hijo guarda de cual salio en factura_origen_id.
     * - Refacturado (editar = anular y reemitir): el padre es la ultima venta
     *   anulada del mismo pedido emitida antes, como en ModificacionFactura.
     *
     * Devuelve [id => datos] con padre, hijos, raiz, version y motivo.
     */
    private function cadenasFacturas($facturas)
    {
        $nros = $facturas->pluck('pedido_nro')->filter()->unique()->values()->all();
        $ids = $facturas->pluck('id')->all();
        $origenes = $facturas->pluck('factura_origen_id')->filter()->unique()->values()->all();

        $todas = Factura::query()
            ->where(function ($w) use ($nros, $ids, $origenes) {
                $w->whereIn('pedido_nro', $nros)
                    ->orWhereIn('factura_origen_id', $ids)
                    ->orWhereIn('id', $origenes);
            })
            ->orderBy('id')
            ->get(['id', 'nro_factura', 'pedido_nro', 'pedido_tipo', 'estado', 'factura_origen_id',
                'tipo_comprobante', 'tipo_pago', 'fecha', 'hora', 'nombre', 'total', 'motivo_anulacion',
                'anulacion_observacion', 'modificacion_campos'])
            ->keyBy('id');
        // Las del filtro ya vienen completas; se quedan esas.
        foreach ($facturas as $f) {
            $todas[$f->id] = $f;
        }
        $todas = $todas->sortKeys();

        $nodos = [];
        foreach ($todas as $f) {
            $nodos[$f->id] = ['factura' => $f, 'padre' => null, 'motivo' => null, 'hijos' => []];
        }
        $anuladasPorPedido = [];
        foreach ($todas as $f) {
            $padre = null;
            $motivo = null;
            if ($f->factura_origen_id && isset($nodos[$f->factura_origen_id])) {
                $padre = $f->factura_origen_id;
                $motivo = 'RETORNO PARCIAL';
            } elseif ($f->pedido_nro) {
                $clave = $f->pedido_nro . '|' . strtoupper(trim((string) $f->pedido_tipo));
                // Recorre en orden de id: la ultima anulada vista es la anterior.
                if (!empty($anuladasPorPedido[$clave])) {
                    $padre = $anuladasPorPedido[$clave];
                    $motivo = 'REFACTURADO';
                }
            }
            if ($padre && $padre !== $f->id) {
                $nodos[$f->id]['padre'] = $padre;
                $nodos[$f->id]['motivo'] = $motivo;
                $nodos[$padre]['hijos'][] = $f->id;
            }
            if ($f->pedido_nro && $f->estado === 'ANULADO') {
                $anuladasPorPedido[$f->pedido_nro . '|' . strtoupper(trim((string) $f->pedido_tipo))] = $f->id;
            }
        }

        foreach ($nodos as $id => $nodo) {
            $raiz = $id;
            $version = 1;
            $vistos = [$id => true];
            while (($padre = $nodos[$raiz]['padre']) && !isset($vistos[$padre])) {
                $vistos[$padre] = true;
                $raiz = $padre;
                $version++;
            }
            $nodos[$id]['raiz'] = $raiz;
            $nodos[$id]['version'] = $version;
        }

        return $nodos;
    }

    /** Texto de la relacion de un comprobante dentro de su cadena. */
    private function relacionCadena(array $nodo)
    {
        if (!$nodo['padre']) {
            return $nodo['hijos'] ? 'PADRE (ORIGINAL)' : 'ÚNICO';
        }
        return ($nodo['hijos'] ? 'HIJO Y PADRE · ' : 'HIJO · ') . $nodo['motivo'];
    }

    private function columnasGeneralComprobante()
    {
        // [encabezado, clave, ancho, formato]
        return [
            ['ID venta', 'id', 9, '0'], ['Nro factura', 'nro_factura', 10, null],
            ['Comprobante', 'comprobante', 11, null], ['Estado', 'estado', 10, null],
            ['Vigente', 'vigente', 8, null], ['Estado SIAT', 'estado_siat', 12, null],
            ['Fecha', 'fecha', 11, 'dd/mm/yyyy'], ['Hora', 'hora', 7, null],
            ['Fecha entrega', 'fecha_entrega', 11, 'dd/mm/yyyy'],
            ['Nro pedido', 'pedido', 10, null], ['Tipo pedido', 'pedido_tipo', 14, null],
            ['Fecha/hora pedido', 'pedido_fecha', 16, null],
            ['Camión', 'camion', 12, null],
            // Lo que marco el camionero al entregar (su ultimo intento).
            ['Camionero', 'camionero', 18, null], ['Estado entrega', 'entrega_estado', 13, null],
            ['Fecha/hora entrega', 'entrega_fecha', 16, null], ['Cobro camionero', 'entrega_tipago', 12, null],
            ['Cobrado camionero Bs', 'entrega_cobrado', 12, '#,##0.00'],
            ['Efectivo camionero Bs', 'entrega_efectivo', 12, '#,##0.00'],
            ['QR camionero Bs', 'entrega_qr', 11, '#,##0.00'],
            ['Obs. entrega', 'entrega_obs', 28, null],
            ['Cliente', 'cliente', 32, null], ['NIT / CI', 'nit', 13, null],
            ['Cliente en sistema', 'cliente_sistema', 30, null], ['Zona', 'zona', 14, null],
            ['Canal', 'canal', 12, null], ['Preventista', 'preventista', 24, null],
            ['Cobrado por', 'usuario', 20, null], ['Pago', 'pago', 10, null],
            ['Efectivo Bs', 'efectivo', 11, '#,##0.00'], ['QR Bs', 'qr', 10, '#,##0.00'],
            ['Subtotal Bs', 'subtotal', 11, '#,##0.00'], ['Descuento Bs', 'descuento', 11, '#,##0.00'],
            ['Total Bs', 'total', 12, '#,##0.00'], ['Total vigente Bs', 'total_vigente', 13, '#,##0.00'],
            ['Nro productos', 'lineas', 9, '0'],
            ['Cadena (ID original)', 'cadena', 11, '0'], ['Versión', 'version', 8, '0'],
            ['Relación', 'relacion', 28, null], ['Padre (ID)', 'padre', 10, '0'],
            ['Hijo (ID)', 'hijo', 10, null], ['Qué cambió vs. el padre', 'cambio_padre', 40, null],
            ['¿Cambió vs. pedido?', 'cambio_pedido', 10, null], ['Cambios vs. pedido', 'cambios_pedido', 40, null],
            ['Anulado el', 'anulado_at', 16, null], ['Anulado por', 'anulado_por', 20, null],
            ['Motivo anulación', 'motivo', 30, null], ['Observación', 'observacion', 30, null],
            ['Entregado camión', 'entregado', 10, null], ['Impreso', 'impreso', 8, null],
        ];
    }

    private function columnasGeneralProducto()
    {
        return [
            ['Código', 'cod_prod', 10, null], ['Producto', 'producto', 34, null],
            ['Familia', 'familia', 18, null], ['Subfamilia', 'subfamilia', 18, null],
            ['Unidad', 'unidad', 8, null], ['Cant. pedida', 'cant_pedida', 10, '#,##0.###'],
            ['Cantidad', 'cantidad', 10, '#,##0.###'], ['Peso bruto', 'peso_bruto', 10, '#,##0.###'],
            ['Canastillos', 'canastillos', 9, '0'], ['Peso neto', 'peso', 10, '#,##0.###'],
            ['Cant. cobrada', 'cobrada', 10, '#,##0.###'], ['Precio Bs', 'precio', 10, '#,##0.00'],
            ['Importe Bs', 'importe', 12, '#,##0.00'], ['Importe vigente Bs', 'importe_vigente', 13, '#,##0.00'],
            ['¿Cambió vs. pedido?', 'linea_cambio', 10, null],
        ];
    }

    private function filasGeneralComprobantes($facturas, array $cadenas)
    {
        $clientes = DB::table('tbclientes')
            ->whereIn('Cod_Aut', $facturas->pluck('cliente_id')->filter()->unique()->values())
            ->get(['Cod_Aut', DB::raw('TRIM(Nombres) as nombres'), DB::raw('TRIM(zona) as zona'), DB::raw('TRIM(Canal) as canal')])
            ->keyBy('Cod_Aut');
        $personal = DB::table('personal')
            ->whereIn('CodAut', $facturas->pluck('user_id')->merge($facturas->pluck('anulado_por'))->filter()->unique()->values())
            ->get(['CodAut', DB::raw("TRIM(CONCAT_WS(' ', NULLIF(TRIM(Nombre1), ''), NULLIF(TRIM(App1), ''))) as nombre")])
            ->keyBy('CodAut');
        $pedidos = DB::table('tbpedidos')
            ->whereNull('deleted_at')
            ->whereIn('NroPed', $facturas->pluck('pedido_nro')->filter()->unique()->values())
            ->groupBy('NroPed')
            ->get(['NroPed', DB::raw('MIN(fecha) as fecha')])
            ->keyBy('NroPed');
        $tipos = collect(self::HOJAS_EXCEL_PEDIDOS)->map(function ($h) { return $h[0]; });

        // De cada comprobante puede haber varios intentos de entrega: vale el
        // ultimo (keyBy se queda con el de id mas alto).
        $entregas = DB::table('entregas as e')
            ->leftJoin('personal as pe', 'pe.CodAut', '=', 'e.personal_id')
            ->whereIn('e.factura_id', $facturas->pluck('id')->all())
            ->orderBy('e.id')
            ->get([
                'e.factura_id', 'e.estado', 'e.fechaEntreg', 'e.hora', 'e.tipago', 'e.pago',
                'e.monto_efectivo', 'e.monto_qr', 'e.observacion',
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', NULLIF(TRIM(pe.Nombre1), ''), NULLIF(TRIM(pe.App1), ''))), ''), TRIM(e.despachador)) as camionero"),
            ])
            ->keyBy('factura_id');

        $filas = [];
        foreach ($facturas as $f) {
            $nodo = $cadenas[$f->id];
            $entrega = $entregas[$f->id] ?? null;
            $entregaFecha = $entrega && $entrega->fechaEntreg
                ? date('d/m/Y', strtotime((string) $entrega->fechaEntreg)) . ($entrega->hora ? ' ' . substr((string) $entrega->hora, 0, 5) : '')
                : '';
            $cliente = $clientes[$f->cliente_id] ?? null;
            $activo = $f->estado !== 'ANULADO';
            $vendedor = $f->vendedor
                ? trim(preg_replace('/\s+/', ' ', implode(' ', [
                    $f->vendedor->Nombre1, $f->vendedor->Nombre2, $f->vendedor->App1, $f->vendedor->Apm,
                ])))
                : '';
            $tipoPedido = strtoupper(trim((string) $f->pedido_tipo));
            $cambioPadre = $nodo['padre'] ? implode(' · ', (array) (($f->modificacion_campos ?? [])['resumen'] ?? [])) : '';
            if ($nodo['padre'] && $nodo['motivo'] === 'RETORNO PARCIAL' && $cambioPadre === '') {
                $cambioPadre = 'Retorno parcial: el cliente devolvió parte del pedido';
            }

            $filas[$f->id] = [
                '_anulado'       => !$activo,
                'id'             => $f->id,
                'nro_factura'    => (string) ($f->nro_factura ?: ''),
                'comprobante'    => $f->tipo_comprobante === 'FACTURA' ? 'FACTURA' : 'VOUCHER',
                'estado'         => $f->estado,
                'vigente'        => $activo ? 'SI' : 'NO',
                'estado_siat'    => (string) $f->estado_siat,
                'fecha'          => $this->fechaExcel($f->fecha),
                'hora'           => substr((string) $f->hora, 0, 5),
                'fecha_entrega'  => $this->fechaExcel($f->fecha_entrega),
                'pedido'         => $f->pedido_nro ? (string) $f->pedido_nro : 'VENTA DIRECTA',
                'pedido_tipo'    => $f->pedido_nro ? ($tipos[$tipoPedido] ?? $tipoPedido) : 'Venta directa',
                'pedido_fecha'   => $f->pedido_nro && isset($pedidos[$f->pedido_nro])
                    ? substr((string) $pedidos[$f->pedido_nro]->fecha, 0, 16) : '',
                'camion'         => trim((string) $f->placa) ?: 'Sin camión',
                'camionero'      => $entrega ? trim((string) $entrega->camionero) : '',
                'entrega_estado' => $this->estadoEntrega($f, $entrega),
                'entrega_fecha'  => $entregaFecha,
                'entrega_tipago' => $entrega ? trim((string) $entrega->tipago) : '',
                'entrega_cobrado'=> $entrega ? (float) $entrega->pago : null,
                'entrega_efectivo' => $entrega ? (float) $entrega->monto_efectivo : null,
                'entrega_qr'     => $entrega ? (float) $entrega->monto_qr : null,
                'entrega_obs'    => $entrega ? trim((string) $entrega->observacion) : '',
                'cliente'        => $f->nombre ?: 'Sin cliente',
                'nit'            => (string) $f->nit,
                'cliente_sistema'=> $cliente->nombres ?? '',
                'zona'           => $cliente->zona ?? '',
                'canal'          => $cliente->canal ?? '',
                'preventista'    => $vendedor,
                'usuario'        => $personal[$f->user_id]->nombre ?? '',
                'pago'           => $f->tipo_pago,
                'efectivo'       => (float) $f->monto_efectivo,
                'qr'             => (float) $f->monto_qr,
                'subtotal'       => (float) $f->subtotal,
                'descuento'      => (float) $f->descuento,
                'total'          => (float) $f->total,
                'total_vigente'  => $activo ? (float) $f->total : 0,
                'lineas'         => $f->detalles->count(),
                'cadena'         => $nodo['raiz'],
                'version'        => $nodo['version'],
                'relacion'       => $this->relacionCadena($nodo),
                'padre'          => $nodo['padre'],
                'hijo'           => implode(', ', $nodo['hijos']),
                'cambio_padre'   => $cambioPadre,
                'cambio_pedido'  => $f->pedido_nro ? ($f->cambio_pedido ? 'SI' : 'NO') : '',
                'cambios_pedido' => implode(' · ', (array) (($f->cambio_pedido_campos ?? [])['resumen'] ?? [])),
                'anulado_at'     => $f->anulado_at ? $f->anulado_at->format('d/m/Y H:i') : '',
                'anulado_por'    => $f->anulado_por ? ($personal[$f->anulado_por]->nombre ?? (string) $f->anulado_por) : '',
                'motivo'         => trim((string) ($f->anulacion_observacion ?: $f->motivo_anulacion)),
                'observacion'    => (string) $f->observacion,
                'entregado'      => $f->entregado_camion ? 'SI' : 'NO',
                'impreso'        => (int) $f->impreso_veces > 0 ? 'SI (' . (int) $f->impreso_veces . ')' : 'NO',
            ];
        }

        return $filas;
    }

    private function filasGeneralProductos($facturas, array $comprobantes)
    {
        $codigos = $facturas->flatMap(function ($f) {
            return $f->detalles->pluck('cod_prod');
        })->map(function ($c) { return trim((string) $c); })->unique()->values();
        $familias = DB::table('tbproductos as p')
            ->leftJoin('tbgrupos as g', 'g.Cod_grup', '=', 'p.cod_grup')
            ->leftJoin('tbgrupopadre as gp', 'gp.cod_grup', '=', 'g.Cod_pdr')
            ->whereIn(DB::raw('TRIM(p.cod_prod)'), $codigos)
            ->get([
                DB::raw('TRIM(p.cod_prod) as cod_prod'),
                DB::raw('TRIM(g.Descripcion) as familia'),
                DB::raw('TRIM(gp.Descripcion) as subfamilia'),
            ])
            ->keyBy('cod_prod');

        $filas = [];
        foreach ($facturas as $f) {
            $cabecera = $comprobantes[$f->id];
            $detalles = $f->detalles->isEmpty() ? [null] : $f->detalles;
            foreach ($detalles as $d) {
                if (!$d) {
                    $filas[] = $cabecera;
                    continue;
                }
                $codigo = trim((string) $d->cod_prod);
                $prod = $familias[$codigo] ?? null;
                $pedida = $d->cantidad_pedida === null ? null : (float) $d->cantidad_pedida;
                $filas[] = $cabecera + [
                    'cod_prod'        => $codigo,
                    'producto'        => $d->nombre,
                    'familia'         => $prod->familia ?? '',
                    'subfamilia'      => $prod->subfamilia ?? '',
                    'unidad'          => $d->unidad,
                    'cant_pedida'     => $pedida,
                    'cantidad'        => (float) $d->cantidad,
                    'peso_bruto'      => $d->peso_bruto === null ? null : (float) $d->peso_bruto,
                    'canastillos'     => $d->canastillos,
                    'peso'            => $d->peso === null ? null : (float) $d->peso,
                    'cobrada'         => (float) $d->cantidad_facturada,
                    'precio'          => (float) $d->precio,
                    'importe'         => (float) $d->subtotal,
                    'importe_vigente' => $cabecera['_anulado'] ? 0 : (float) $d->subtotal,
                    'linea_cambio'    => $pedida === null ? '' : ($pedida == (float) $d->cantidad ? 'NO' : 'SI'),
                ];
            }
        }

        return $filas;
    }

    /** Fecha como numero de Excel, para que se pueda filtrar y ordenar. */
    private function fechaExcel($fecha)
    {
        if (!$fecha) return null;
        $ts = $fecha instanceof \DateTimeInterface ? $fecha->getTimestamp() : strtotime((string) $fecha);
        return $ts ? \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(date('Y-m-d', $ts)) : null;
    }

    /**
     * Una tabla plana con titulo, encabezado fijo, filtros, totales y los
     * anulados en rojo. $columnas: [encabezado, clave, ancho, formato].
     */
    private function tablaGeneralExcel($hoja, $titulo, $subtitulo, array $columnas, $filas, $color, array $sumables, $nota)
    {
        $ultima = Coordinate::stringFromColumnIndex(count($columnas));
        $anulados = 0;
        foreach ($filas as $fila) {
            if ($fila['_anulado']) $anulados++;
        }
        $this->cabeceraHojaExcel($hoja, $titulo, $subtitulo,
            'Filas: ' . count($filas) . '   ·   Anuladas (en rojo): ' . $anulados
                . '   ·   ' . $nota,
            $ultima, $color);

        $claves = [];
        foreach ($columnas as $i => list($etiqueta, $clave, $ancho)) {
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $hoja->setCellValue($letra . '5', $etiqueta);
            $hoja->getColumnDimension($letra)->setWidth($ancho);
            $claves[] = $clave;
        }
        $this->encabezadoTablaExcel($hoja, 'A5:' . $ultima . '5');
        $hoja->getRowDimension(5)->setRowHeight(30);

        // Textos que parecen numeros (NIT, nro de factura, codigo) van como texto.
        $comoTexto = array_flip(['nro_factura', 'nit', 'pedido', 'cod_prod']);
        $fila = 6;
        foreach ($filas as $registro) {
            $valores = [];
            foreach ($claves as $clave) {
                $valores[] = $registro[$clave] ?? null;
            }
            $hoja->fromArray($valores, null, 'A' . $fila, true);
            foreach ($claves as $i => $clave) {
                if (isset($comoTexto[$clave]) && ($registro[$clave] ?? '') !== '') {
                    $hoja->getCellByColumnAndRow($i + 1, $fila)->setValueExplicit(
                        (string) $registro[$clave], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                    );
                }
            }
            if ($registro['_anulado']) {
                $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->applyFromArray([
                    'font' => ['color' => ['rgb' => 'B71C1C']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEBEE']],
                ]);
            }
            $fila++;
        }
        $ultimaDato = $fila - 1;

        // Totales con SUBTOTAL: siguen lo que el usuario deja visible al filtrar.
        $hoja->setCellValue('A' . $fila, 'TOTAL (filas visibles)');
        foreach ($claves as $i => $clave) {
            if (in_array($clave, $sumables, true)) {
                $letra = Coordinate::stringFromColumnIndex($i + 1);
                $hoja->setCellValue($letra . $fila, '=SUBTOTAL(109,' . $letra . '6:' . $letra . $ultimaDato . ')');
            }
        }
        $this->filaTotalExcel($hoja, 'A' . $fila . ':' . $ultima . $fila);

        foreach ($columnas as $i => $columna) {
            if (!empty($columna[3])) {
                $letra = Coordinate::stringFromColumnIndex($i + 1);
                $hoja->getStyle($letra . '6:' . $letra . $fila)->getNumberFormat()->setFormatCode($columna[3]);
            }
        }
        $hoja->getStyle('A5:' . $ultima . $fila)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('BDBDBD');
        $hoja->getStyle('A6:' . $ultima . $fila)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        $hoja->freezePane('C6');
        $hoja->setAutoFilter('A5:' . $ultima . $ultimaDato);
        $this->impresionHojaExcel($hoja, 5);
    }

    private function hojaGeneralResumen($hoja, array $comprobantes, $subtitulo)
    {
        $filas = collect($comprobantes);
        $activas = $filas->where('_anulado', false);
        $this->cabeceraHojaExcel($hoja, 'REPORTE GENERAL DE COMPROBANTES', $subtitulo,
            'Comprobantes: ' . $filas->count() . '   ·   Vigentes: ' . $activas->count()
                . '   ·   Anulados: ' . ($filas->count() - $activas->count()),
            'F', '37474F');

        $cifras = [
            ['Comprobantes emitidos', $filas->count(), null],
            ['Vigentes (activos)', $activas->count(), null],
            ['Anulados', $filas->count() - $activas->count(), null],
            ['Facturas vigentes', $activas->where('comprobante', 'FACTURA')->count(), null],
            ['Vouchers vigentes', $activas->where('comprobante', 'VOUCHER')->count(), null],
            ['Refacturados (hijos por edición)', $filas->filter(function ($f) { return strpos($f['relacion'], 'REFACTURADO') !== false; })->count(), null],
            ['Retornos parciales (hijos)', $filas->filter(function ($f) { return strpos($f['relacion'], 'RETORNO PARCIAL') !== false; })->count(), null],
            ['Ventas directas (sin pedido)', $filas->where('pedido', 'VENTA DIRECTA')->count(), null],
            ['Total vigente Bs', round($activas->sum('total'), 2), '#,##0.00'],
            ['Total anulado Bs', round($filas->where('_anulado', true)->sum('total'), 2), '#,##0.00'],
            ['Vigente en efectivo Bs', round($activas->sum('efectivo'), 2), '#,##0.00'],
            ['Vigente en QR Bs', round($activas->sum('qr'), 2), '#,##0.00'],
        ];
        $hoja->fromArray(['Indicador', 'Valor'], null, 'A5', true);
        $this->encabezadoTablaExcel($hoja, 'A5:B5');
        $fila = 6;
        foreach ($cifras as list($etiqueta, $valor, $formato)) {
            $hoja->fromArray([$etiqueta, $valor], null, 'A' . $fila, true);
            if ($formato) $hoja->getStyle('B' . $fila)->getNumberFormat()->setFormatCode($formato);
            $fila++;
        }
        $hoja->getStyle('A5:B' . ($fila - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BDBDBD');
        $hoja->getStyle('A6:A' . ($fila - 1))->getFont()->setBold(true);

        // Por camion y por tipo de pedido.
        $fila += 2;
        foreach ([['camion', 'POR CAMIÓN', 'Camión'], ['pedido_tipo', 'POR TIPO DE PEDIDO', 'Tipo de pedido']] as list($campo, $titulo, $encabezado)) {
            $hoja->setCellValue('A' . $fila, $titulo);
            $hoja->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('37474F');
            $fila++;
            $inicio = $fila;
            $hoja->fromArray([$encabezado, 'Comprobantes', 'Vigentes', 'Anulados', 'Total vigente Bs', 'Total anulado Bs'], null, 'A' . $fila, true);
            $this->encabezadoTablaExcel($hoja, 'A' . $fila . ':F' . $fila);
            $fila++;
            foreach ($filas->groupBy($campo)->sortKeys() as $clave => $grupo) {
                $vigentes = $grupo->where('_anulado', false);
                $hoja->fromArray([
                    (string) $clave, $grupo->count(), $vigentes->count(), $grupo->count() - $vigentes->count(),
                    round($vigentes->sum('total'), 2), round($grupo->where('_anulado', true)->sum('total'), 2),
                ], null, 'A' . $fila, true);
                $fila++;
            }
            $hoja->setCellValue('A' . $fila, 'TOTAL');
            foreach (['B', 'C', 'D', 'E', 'F'] as $letra) {
                $hoja->setCellValue($letra . $fila, '=SUM(' . $letra . ($inicio + 1) . ':' . $letra . ($fila - 1) . ')');
            }
            $this->filaTotalExcel($hoja, 'A' . $fila . ':F' . $fila);
            $hoja->getStyle('E' . ($inicio + 1) . ':F' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
            $hoja->getStyle('A' . $inicio . ':F' . $fila)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BDBDBD');
            $hoja->getStyle('B' . ($inicio + 1) . ':D' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $fila += 3;
        }

        $hoja->getColumnDimension('A')->setWidth(34);
        foreach (['B', 'C', 'D', 'E', 'F'] as $letra) {
            $hoja->getColumnDimension($letra)->setWidth(16);
        }
        $this->impresionHojaExcel($hoja, null);
    }

    /**
     * Solo las cadenas con mas de un comprobante: el original arriba y debajo
     * cada hijo, con sangria segun la generacion. Incluye los que quedan
     * fuera del filtro para que la cadena se lea completa.
     */
    private function hojaGeneralCadenas($hoja, array $cadenas, array $comprobantes, $subtitulo)
    {
        $raices = [];
        foreach ($comprobantes as $fila) {
            $raiz = $fila['cadena'];
            if (!empty($cadenas[$raiz]['hijos'])) {
                $raices[$raiz] = true;
            }
        }
        ksort($raices);

        $columnas = [
            ['Cadena (ID original)', 11], ['Generación', 10], ['ID venta', 12], ['Relación', 28],
            ['Padre (ID)', 10], ['Hijos (ID)', 12], ['Nro factura', 10], ['Comprobante', 11], ['Estado', 10],
            ['Fecha', 11], ['Hora', 7], ['Nro pedido', 10], ['Cliente', 32], ['Pago', 10], ['Total Bs', 12],
            ['Motivo anulación', 30], ['Qué cambió vs. el padre', 45], ['¿En el filtro?', 9],
        ];
        $ultima = Coordinate::stringFromColumnIndex(count($columnas));
        $this->cabeceraHojaExcel($hoja, 'PADRES E HIJOS: REFACTURADOS Y RETORNOS PARCIALES', $subtitulo,
            'Cadenas: ' . count($raices) . '   ·   El original (padre) va resaltado y debajo cada comprobante que lo reemplazó.',
            $ultima, '6A1B9A');
        foreach ($columnas as $i => list($etiqueta, $ancho)) {
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $hoja->setCellValue($letra . '5', $etiqueta);
            $hoja->getColumnDimension($letra)->setWidth($ancho);
        }
        $this->encabezadoTablaExcel($hoja, 'A5:' . $ultima . '5');
        $hoja->getRowDimension(5)->setRowHeight(30);

        $fila = 6;
        if (!$raices) {
            $hoja->mergeCells('A6:' . $ultima . '6')->setCellValue('A6', 'Ningún comprobante del filtro fue refacturado ni tuvo retorno parcial');
            $hoja->getStyle('A6')->getFont()->setItalic(true)->getColor()->setRGB('888888');
            return;
        }

        foreach (array_keys($raices) as $raiz) {
            // Recorrido en profundidad: cada hijo debajo de su padre.
            $pila = [[$raiz, 0]];
            while ($pila) {
                list($id, $nivel) = array_shift($pila);
                $nodo = $cadenas[$id];
                $f = $nodo['factura'];
                $motivo = trim((string) ($f->anulacion_observacion ?: $f->motivo_anulacion));
                $cambio = $nodo['padre'] ? implode(' · ', (array) (($f->modificacion_campos ?? [])['resumen'] ?? [])) : '';
                $hoja->fromArray([
                    $raiz, $nivel + 1, str_repeat('    ', $nivel) . ($nivel ? '└ ' : '') . $id,
                    $this->relacionCadena($nodo), $nodo['padre'], implode(', ', $nodo['hijos']),
                    (string) ($f->nro_factura ?: ''), $f->tipo_comprobante === 'FACTURA' ? 'FACTURA' : 'VOUCHER',
                    $f->estado, $this->fechaExcel($f->fecha), substr((string) $f->hora, 0, 5),
                    $f->pedido_nro ? (string) $f->pedido_nro : 'VENTA DIRECTA', $f->nombre, $f->tipo_pago,
                    (float) $f->total, $motivo, $cambio, isset($comprobantes[$id]) ? 'SI' : 'NO',
                ], null, 'A' . $fila, true);
                if (!$nivel) {
                    $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EDE7F6']],
                        'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6A1B9A']]],
                    ]);
                }
                $hoja->getStyle('I' . $fila)->getFont()->getColor()
                    ->setRGB($f->estado === 'ANULADO' ? 'C62828' : '2E7D32');
                $hoja->getStyle('I' . $fila)->getFont()->setBold(true);
                $fila++;
                $hijos = array_map(function ($h) use ($nivel) { return [$h, $nivel + 1]; }, $nodo['hijos']);
                $pila = array_merge($hijos, $pila);
            }
        }

        $hoja->getStyle('J6:J' . $fila)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $hoja->getStyle('O6:O' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle('A5:' . $ultima . ($fila - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('BDBDBD');
        $hoja->getStyle('A6:B' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle('P6:Q' . $fila)->getAlignment()->setWrapText(true);
        $hoja->freezePane('D6');
        $hoja->setAutoFilter('A5:' . $ultima . ($fila - 1));
        $this->impresionHojaExcel($hoja, 5);
    }

    public function reporte(Request $request)
    {
        $datos = $request->validate([
            'formato'    => 'nullable|in:pdf,excel',
            'contenido'  => 'nullable|in:ventas,cambios,anulados',
        ]);

        $contenido = $datos['contenido'] ?? 'ventas';
        $query = $this->filtrar($request)->reorder('id');
        // Los anulados salen solos, aunque el filtro de estado diga otra cosa.
        if ($contenido === 'anulados') {
            $query->where('estado', 'ANULADO');
        }
        $facturas = $query->get();

        if ($contenido === 'cambios') {
            $filas = $this->filasCambios($facturas);
        } elseif ($contenido === 'anulados') {
            $filas = $this->filasAnulados($facturas);
        } else {
            $filas = $this->filasVentas($facturas);
        }

        if (empty($filas)) {
            $mensajes = [
                'cambios'  => 'Ningún pedido salió con cantidades distintas en este filtro',
                'anulados' => 'No hay comprobantes anulados en este filtro',
                'ventas'   => 'No hay ventas en este filtro',
            ];
            return response()->json(['message' => $mensajes[$contenido]], 422);
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

    /**
     * Una fila por producto de cada comprobante anulado, con las mismas
     * columnas que el reporte de anulados del sistema de caja: datos del
     * cliente, familia y subfamilia del producto, vendedor, motivo y placa.
     */
    private function filasAnulados($facturas)
    {
        $clientes = DB::table('tbclientes')
            ->whereIn('Cod_Aut', $facturas->pluck('cliente_id')->filter()->unique()->values())
            ->get(['Cod_Aut', 'Canal', 'SupraCanal', 'subcanal', 'zona', 'Latitud', 'longitud'])
            ->keyBy('Cod_Aut');

        // Familia = grupo del producto; subfamilia = el grupo padre de ese grupo.
        $codigos = $facturas->flatMap(function ($f) {
            return $f->detalles->pluck('cod_prod');
        })->map(function ($c) { return trim((string) $c); })->unique()->values();
        $familias = DB::table('tbproductos as p')
            ->leftJoin('tbgrupos as g', 'g.Cod_grup', '=', 'p.cod_grup')
            ->leftJoin('tbgrupopadre as gp', 'gp.cod_grup', '=', 'g.Cod_pdr')
            ->whereIn(DB::raw('TRIM(p.cod_prod)'), $codigos)
            ->get([
                DB::raw('TRIM(p.cod_prod) as cod_prod'),
                DB::raw('TRIM(g.Descripcion) as familia'),
                DB::raw('TRIM(gp.Descripcion) as subfamilia'),
            ])
            ->keyBy('cod_prod');

        $filas = [];
        foreach ($facturas as $f) {
            $c = $clientes[$f->cliente_id] ?? null;
            $vendedor = $f->vendedor
                ? trim(preg_replace('/\s+/', ' ', implode(' ', [
                    $f->vendedor->Nombre1, $f->vendedor->Nombre2, $f->vendedor->App1, $f->vendedor->Apm,
                ])))
                : '';
            // El motivo de la oficina si se eligio; si no, el de Impuestos.
            $motivo = trim((string) ($f->anulacion_observacion ?: $f->motivo_anulacion));

            foreach ($f->detalles as $d) {
                $prod = $familias[trim((string) $d->cod_prod)] ?? null;
                $filas[] = [
                    'boleta'     => (string) ($f->nro_factura ?: $f->id),
                    'cliente'    => $f->nombre ?: 'Sin cliente',
                    'canal'      => $c ? trim((string) $c->Canal) : '',
                    'supracanal' => $c ? trim((string) $c->SupraCanal) : '',
                    'subcanal'   => $c ? trim((string) $c->subcanal) : '',
                    'zona'       => $c ? trim((string) $c->zona) : '',
                    'latitud'    => $c && is_numeric(trim((string) $c->Latitud)) ? (float) $c->Latitud : '',
                    'longitud'   => $c && is_numeric(trim((string) $c->longitud)) ? (float) $c->longitud : '',
                    'fecha'      => $f->fecha->format('d/m/Y') . ' ' . substr((string) $f->hora, 0, 5),
                    'familia'    => $prod->familia ?? '',
                    'subfamilia' => $prod->subfamilia ?? '',
                    'vendedor'   => $vendedor,
                    'cod_prod'   => trim((string) $d->cod_prod),
                    'producto'   => $d->nombre,
                    // Lo vendido por peso va en kilos; lo demas, en su cantidad.
                    'peso'       => (float) $d->peso > 0 ? (float) $d->peso : (float) $d->cantidad,
                    'importe'    => (float) $d->subtotal,
                    // Toda anulacion de Sofia es del comprobante completo.
                    'motivo'     => $motivo !== '' ? $motivo . ' TOTAL' : '',
                    'placa'      => trim((string) $f->placa),
                ];
            }
        }

        return $filas;
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

        if ($contenido === 'anulados') {
            return [
                ['No BOLETA', 'boleta', 5, false], ['CLIENTE', 'cliente', 10, false],
                ['CANAL', 'canal', 6, false], ['SUPRACANAL', 'supracanal', 4, false],
                ['SUBCANAL', 'subcanal', 6, false], ['ZONA', 'zona', 5, false],
                ['LATITUD', 'latitud', 5, false], ['LONGITUD', 'longitud', 5, false],
                ['FECHA', 'fecha', 6, false], ['FAMILIA', 'familia', 6, false],
                ['SUBFAMILIA', 'subfamilia', 6, false], ['VENDEDOR', 'vendedor', 8, false],
                ['COD. PRODUCTO', 'cod_prod', 4, false], ['PRODUCTO', 'producto', 10, false],
                ['PESO', 'peso', 4, true], ['IMPORTE', 'importe', 5, true],
                ['MOTIVO ANUL', 'motivo', 8, false], ['PLACA', 'placa', 5, false],
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

    private function nombreReporte($contenido)
    {
        $nombres = [
            'cambios'  => 'CAMBIOS EN LOS PEDIDOS',
            'anulados' => 'COMPROBANTES ANULADOS',
        ];

        return $nombres[$contenido] ?? 'REPORTE DE VENTAS';
    }

    /** Solo se suman los importes: sumar cantidades de unidades distintas no dice nada. */
    private function sumablesReporte($contenido)
    {
        $sumables = [
            'cambios'  => ['importe'],
            'anulados' => ['importe'],
        ];

        return $sumables[$contenido] ?? ['subtotal', 'descuento', 'total'];
    }

    /** Titulo y rango, para que el papel diga que se esta mirando. */
    private function tituloReporte($contenido, Request $request)
    {
        if ($rango = $this->rangoEntregaFiltro($request)) {
            return [
                $this->nombreReporte($contenido),
                $rango[0] === $rango[1]
                    ? 'Entrega del ' . date('d/m/Y', strtotime($rango[0]))
                    : 'Entrega del ' . date('d/m/Y', strtotime($rango[0])) . ' al ' . date('d/m/Y', strtotime($rango[1])),
            ];
        }
        $desde = $request->input('desde');
        $hasta = $request->input('hasta');

        $horaDesde = $this->hora($request->input('hora_desde'));
        $horaHasta = $this->hora($request->input('hora_hasta'));

        $rango = $desde && $hasta && $desde === $hasta && !$horaDesde && !$horaHasta
            ? date('d/m/Y', strtotime($desde))
            : trim(($desde ? 'del ' . date('d/m/Y', strtotime($desde)) . ($horaDesde ? ' ' . $horaDesde : '') : '')
                . ($hasta ? ' al ' . date('d/m/Y', strtotime($hasta)) . ($horaHasta ? ' ' . $horaHasta : '') : ''));

        return [
            $this->nombreReporte($contenido),
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
        $sumables = $this->sumablesReporte($contenido);
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
        $hoja->setTitle(ucfirst($contenido));

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

        $sumables = $this->sumablesReporte($contenido);
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

        // La existencia vive en tbproductos.stock_actual: la venta la baja y la
        // anulacion la devuelve. tbstock queda como historial del movimiento.
        foreach ($filas as $fila) {
            $delta = round((float) $fila['cant'] - (float) $fila['saldo'], 3);
            if ($delta != 0) {
                DB::table('tbproductos')->whereRaw('TRIM(cod_prod) = ?', [trim($fila['cod_prod'])])
                    ->update(['stock_actual' => DB::raw('stock_actual + (' . sprintf('%.3F', $delta) . ')')]);
            }
        }
    }
}
