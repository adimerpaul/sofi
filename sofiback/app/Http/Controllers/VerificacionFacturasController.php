<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use App\Models\FacturaFicticia;
use App\Models\LibroVentaSiat;
use App\Services\LibroVentasSiatImporter;
use App\Services\SiatService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

/**
 * Verificacion de facturas: cruza el libro de ventas del SIAT (Excel que se
 * baja de Impuestos y se sube aca) con las facturas de Sofia por el CUF.
 *
 * Sofia emite desde dos tablas: facturas (facturacion normal) y
 * facturas_ficticias. Una fila del libro "esta en el sistema" si su CUF
 * existe en cualquiera de las dos. Pestañas:
 *   - todas / en_sistema / sin_registrar: filas del libro.
 *   - diferencias: estan en ambos lados pero no coinciden el importe o el
 *     estado (anulada en un lado y valida en el otro).
 *   - no_impuestos: facturas de Sofia con CUF que el libro no tiene.
 */
class VerificacionFacturasController extends Controller
{
    /** Tolerancia de redondeo al comparar importes. */
    const TOLERANCIA = 0.01;

    /**
     * Catalogo de motivos de anulacion del SIAT (sincronizacion
     * sincronizarParametricaMotivoAnulacion), el mismo que usa Area Fresca.
     */
    const MOTIVOS_ANULACION = [
        1 => 'FACTURA MAL EMITIDA',
        2 => 'NOTA DE CREDITO-DEBITO MAL EMITIDA',
        3 => 'DATOS DE EMISION INCORRECTOS',
        4 => 'FACTURA O NOTA DE CREDITO-DEBITO DEVUELTA',
    ];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!$request->user() || !$request->user()->can('verificacionFacturas')) {
                return response()->json(['message' => 'No tiene permiso de verificación de facturas'], 403);
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 200);

        $page = $this->filteredQuery($request)
            ->with([
                'factura' => function ($q) { $q->select('id', 'nro_factura', 'cuf', 'fecha', 'nombre', 'total', 'estado', 'estado_siat'); },
                'ficticia' => function ($q) { $q->select('id', 'nro_factura', 'cuf', 'fecha', 'nombre', 'total', 'estado', 'estado_siat'); },
            ])
            ->orderByDesc('fecha_factura')
            // numero_factura es texto: ordenar por largo primero lo deja en orden numerico.
            ->orderByRaw('LENGTH(numero_factura) DESC')
            ->orderByDesc('numero_factura')
            ->paginate($perPage);

        $caja = $this->cajaPorCuf($page->getCollection()->pluck('cuf')->all());

        $page->getCollection()->transform(function ($fila) use ($caja) {
            $sistema = $fila->factura ?: ($fila->ficticia ?: $caja->get($fila->cuf));
            $fila->setAttribute('sistema', $sistema ? [
                'origen' => $fila->factura ? 'FACTURA' : ($fila->ficticia ? 'FICTICIA' : 'CAJA'),
                'id' => $sistema->id,
                'nro_factura' => $sistema->nro_factura,
                'fecha' => optional($sistema->fecha)->format('Y-m-d'),
                'nombre' => $sistema->nombre,
                'total' => $sistema->total,
                'estado' => $sistema->estado,
                'estado_siat' => $sistema->estado_siat,
                'diferencias' => $this->diferencias($fila, $sistema),
            ] : null);
            $fila->setAttribute('url_siat', $this->urlSiat($fila->cuf, $fila->numero_factura));
            $fila->unsetRelation('factura');
            $fila->unsetRelation('ficticia');

            return $fila;
        });

        return response()->json($page);
    }

    public function resumen(Request $request)
    {
        // Los totales ignoran el filtro de pestaña: si no, "sin registrar" se compararia contra si mismo.
        $query = $this->filteredQuery($request, false);
        $validas = (clone $query)->where('estado', 'VALIDA');

        return response()->json([
            'mes' => $this->month($request)->format('Y-m'),
            'cantidad' => (clone $query)->count(),
            'validas' => (clone $validas)->count(),
            'anuladas' => (clone $query)->where('estado', '!=', 'VALIDA')->count(),
            'importe_total' => round((float) (clone $validas)->sum('importe_total'), 2),
            'debito_fiscal' => round((float) (clone $validas)->sum('debito_fiscal'), 2),
            'en_sistema' => $this->whereEnSistema(clone $query)->count(),
            'sin_registrar' => $this->whereSinRegistrar(clone $query)->count(),
            'importe_sin_registrar' => round((float) $this->whereSinRegistrar(clone $validas)->sum('importe_total'), 2),
            'diferencias' => $this->whereDiferencias(clone $query)->count(),
            'no_en_impuestos' => DB::query()->fromSub($this->faltantesQuery($request), 'x')->count(),
            'meses' => LibroVentaSiat::selectRaw("DATE_FORMAT(fecha_factura, '%Y-%m') as mes, COUNT(*) as cantidad")
                ->groupBy('mes')->orderByDesc('mes')->get(),
        ]);
    }

    public function show($id)
    {
        $fila = LibroVentaSiat::with('factura', 'ficticia')->find($id);
        if (!$fila) {
            return response()->json(['message' => 'La fila no existe'], 404);
        }
        $fila->setAttribute('url_siat', $this->urlSiat($fila->cuf, $fila->numero_factura));
        $fila->setAttribute('caja', $this->cajaPorCuf([$fila->cuf])->get($fila->cuf));

        return response()->json($fila);
    }

    /**
     * Facturas de Sofia (normales y ficticias) con CUF que no aparecen en el
     * libro importado: Impuestos no las tiene, o el libro del mes no se subio.
     */
    public function faltantes(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 200);

        $page = DB::query()->fromSub($this->faltantesQuery($request), 'x')
            ->orderByDesc('fecha')
            ->orderByDesc('nro_factura')
            ->paginate($perPage);

        $page->getCollection()->transform(function ($fila) {
            $fila->url_siat = $this->urlSiat($fila->cuf, $fila->nro_factura ?: $fila->id);

            return $fila;
        });

        return response()->json($page);
    }

    /**
     * Excel del mes con dos hojas: el libro de ventas del SIAT (lo que tiene
     * Impuestos) y las ventas de Sofia que salieron como factura (facturas
     * tipo FACTURA + ficticias, con CUF y no simuladas). Cada hoja dice si la
     * fila esta del otro lado. Respeta el filtro de busqueda y de estado SIAT.
     */
    public function excel(Request $request)
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $month = $this->month($request);
        $desde = $month->copy()->startOfMonth()->toDateString();
        $hasta = $month->copy()->endOfMonth()->toDateString();
        $mesTexto = $month->format('Y-m');

        // --- Hoja 1: libro del SIAT ---------------------------------------
        $libroFilas = $this->filteredQuery($request, false)
            ->orderBy('fecha_factura')
            ->orderByRaw('LENGTH(numero_factura)')
            ->orderBy('numero_factura')
            ->get();

        $cufsLibro = $libroFilas->pluck('cuf')->filter()->unique()->values()->all();
        $enSofia = collect();
        foreach (array_chunk($cufsLibro, 1000) as $lote) {
            foreach (['facturas' => 'Factura', 'facturas_ficticias' => 'Ficticia'] as $tabla => $origen) {
                DB::table($tabla)->whereNull('deleted_at')->whereIn('cuf', $lote)
                    ->get(['cuf', 'nro_factura', 'total', 'estado'])
                    ->each(function ($f) use ($enSofia, $origen) {
                        if (!$enSofia->has($f->cuf)) {
                            $enSofia->put($f->cuf, (object) ['origen' => $origen, 'nro' => $f->nro_factura, 'total' => $f->total, 'estado' => $f->estado]);
                        }
                    });
            }
            $this->cajaPorCuf($lote)->each(function ($c, $cuf) use ($enSofia) {
                if (!$enSofia->has($cuf)) {
                    $enSofia->put($cuf, (object) ['origen' => 'Caja', 'nro' => $c->nro_factura, 'total' => $c->total, 'estado' => $c->estado]);
                }
            });
        }

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Libro SIAT ' . $mesTexto);
        $columnas = ['N°', 'Fecha', 'Nº factura', 'CUF', 'NIT / CI', 'Compl.', 'Razón social', 'Importe total',
            'Descuentos', 'Subtotal', 'Base débito fiscal', 'Débito fiscal', 'Estado SIAT', 'En Sofia', 'Total Sofia', 'Diferencia'];
        $this->encabezadoExcel($hoja, 'LIBRO DE VENTAS DEL SIAT (IMPUESTOS)', 'Mes: ' . $mesTexto, $columnas);

        $r = 5;
        foreach ($libroFilas as $i => $f) {
            $s = $enSofia->get($f->cuf);
            $dif = $s && $s->total !== null ? round((float) $s->total - (float) $f->importe_total, 2) : null;
            $valores = [
                $i + 1, optional($f->fecha_factura)->format('d/m/Y'), $f->numero_factura, $f->cuf,
                $f->nit_ci_cliente, $f->complemento, $f->razon_social, (float) $f->importe_total,
                (float) $f->descuentos, (float) $f->subtotal, (float) $f->importe_base_debito_fiscal, (float) $f->debito_fiscal,
                $f->estado, $s ? $s->origen . ' ' . $s->nro . ' (' . $s->estado . ')' : 'NO ESTÁ EN SOFIA',
                $s && $s->total !== null ? (float) $s->total : '', $dif !== null && abs($dif) > self::TOLERANCIA ? $dif : '',
            ];
            $this->filaExcel($hoja, $r, $valores, [3, 4, 5]);
            if ($f->estado !== 'VALIDA') {
                $this->pintarFila($hoja, $r, count($columnas), 'EEEEEE');
            } elseif (!$s) {
                $this->pintarFila($hoja, $r, count($columnas), 'FFEBEE');
            }
            $r++;
        }
        $validas = $libroFilas->where('estado', 'VALIDA');
        $hoja->setCellValue('G' . $r, 'TOTAL VÁLIDAS (' . $validas->count() . ')');
        $hoja->setCellValue('H' . $r, round((float) $validas->sum('importe_total'), 2));
        $hoja->setCellValue('I' . $r, round((float) $validas->sum('descuentos'), 2));
        $hoja->setCellValue('J' . $r, round((float) $validas->sum('subtotal'), 2));
        $hoja->setCellValue('K' . $r, round((float) $validas->sum('importe_base_debito_fiscal'), 2));
        $hoja->setCellValue('L' . $r, round((float) $validas->sum('debito_fiscal'), 2));
        $this->cerrarHojaExcel($hoja, $r, count($columnas), 'H5:L' . $r, 'O5:P' . $r);

        // --- Hoja 2: ventas facturadas en Sofia ----------------------------
        $buscar = trim((string) $request->input('q', ''));
        $ventas = function ($tabla, $origen) use ($desde, $hasta, $buscar) {
            $q = DB::table($tabla)
                ->selectRaw("'$origen' as origen, id, nro_factura, cuf, fecha, hora, nit, nombre, subtotal, descuento, total, estado, estado_siat, COALESCE(LEFT(fecha_emision, 10), fecha) as fecha_fiscal")
                ->whereNull('deleted_at')
                ->whereNotNull('cuf')->where('cuf', '!=', '')
                ->where(function ($w) { $w->whereNull('estado_siat')->orWhere('estado_siat', '!=', SiatService::ESTADO_SIMULADO); })
                ->whereRaw('COALESCE(LEFT(fecha_emision, 10), fecha) BETWEEN ? AND ?', [$desde, $hasta]);
            if ($tabla === 'facturas') {
                $q->where('tipo_comprobante', 'FACTURA');
            }
            if ($buscar !== '') {
                $like = '%' . $buscar . '%';
                $q->where(function ($w) use ($like, $buscar) {
                    $w->where('nro_factura', $buscar)->orWhere('cuf', 'like', $like)
                        ->orWhere('nit', 'like', $like)->orWhere('nombre', 'like', $like);
                });
            }

            return $q;
        };
        $ventasFilas = DB::query()->fromSub($ventas('facturas', 'FACTURA')->unionAll($ventas('facturas_ficticias', 'FICTICIA')), 'x')
            ->orderBy('fecha_fiscal')->orderBy('nro_factura')->get();

        $enLibro = collect();
        foreach (array_chunk($ventasFilas->pluck('cuf')->unique()->values()->all(), 1000) as $lote) {
            LibroVentaSiat::whereIn('cuf', $lote)->get(['cuf', 'importe_total', 'estado'])
                ->each(function ($l) use ($enLibro) { $enLibro->put($l->cuf, $l); });
        }

        $hoja = $libro->createSheet();
        $hoja->setTitle('Ventas facturadas ' . $mesTexto);
        $columnas = ['N°', 'Fecha', 'Origen', 'Nº factura', 'CUF', 'NIT / CI', 'Cliente', 'Subtotal', 'Descuento',
            'Total', 'Estado Sofia', 'Estado SIAT', 'En libro SIAT', 'Importe SIAT', 'Diferencia'];
        $this->encabezadoExcel($hoja, 'VENTAS FACTURADAS EN SOFIA', 'Mes: ' . $mesTexto . ' · solo comprobantes tipo factura con CUF', $columnas);

        $r = 5;
        foreach ($ventasFilas as $i => $v) {
            $l = $enLibro->get($v->cuf);
            $dif = $l ? round((float) $v->total - (float) $l->importe_total, 2) : null;
            $valores = [
                $i + 1, date('d/m/Y', strtotime($v->fecha_fiscal)), $v->origen === 'FICTICIA' ? 'Ficticia' : 'Factura',
                $v->nro_factura, $v->cuf, $v->nit, $v->nombre, (float) $v->subtotal, (float) $v->descuento, (float) $v->total,
                $v->estado, $v->estado_siat, $l ? 'SÍ (' . $l->estado . ')' : 'NO',
                $l ? (float) $l->importe_total : '', $dif !== null && abs($dif) > self::TOLERANCIA ? $dif : '',
            ];
            $this->filaExcel($hoja, $r, $valores, [4, 5, 6]);
            if ($v->estado !== 'ACTIVO') {
                $this->pintarFila($hoja, $r, count($columnas), 'EEEEEE');
            } elseif (!$l) {
                $this->pintarFila($hoja, $r, count($columnas), 'FFF3E0');
            }
            $r++;
        }
        $activas = $ventasFilas->where('estado', 'ACTIVO');
        $hoja->setCellValue('G' . $r, 'TOTAL ACTIVAS (' . $activas->count() . ')');
        $hoja->setCellValue('H' . $r, round((float) $activas->sum('subtotal'), 2));
        $hoja->setCellValue('I' . $r, round((float) $activas->sum('descuento'), 2));
        $hoja->setCellValue('J' . $r, round((float) $activas->sum('total'), 2));
        $this->cerrarHojaExcel($hoja, $r, count($columnas), 'H5:J' . $r, 'N5:O' . $r);

        $libro->setActiveSheetIndex(0);
        $writer = new Xlsx($libro);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'facturas_' . $mesTexto . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function encabezadoExcel($hoja, $titulo, $subtitulo, array $columnas)
    {
        $hoja->setCellValue('A1', $titulo);
        $hoja->setCellValue('A2', $subtitulo);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        foreach ($columnas as $i => $t) {
            $hoja->setCellValueByColumnAndRow($i + 1, 4, $t);
        }
        $rango = 'A4:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columnas)) . '4';
        $hoja->getStyle($rango)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('37474F');
    }

    /** $texto: columnas (1-based) que van como texto (CUF, NIT, numero) para que Excel no las redondee. */
    private function filaExcel($hoja, $r, array $valores, array $texto)
    {
        foreach ($valores as $c => $v) {
            if (in_array($c + 1, $texto, true)) {
                $hoja->setCellValueExplicitByColumnAndRow($c + 1, $r, (string) $v, DataType::TYPE_STRING);
            } else {
                $hoja->setCellValueByColumnAndRow($c + 1, $r, $v === null ? '' : $v);
            }
        }
    }

    private function pintarFila($hoja, $r, $n, $rgb)
    {
        $rango = 'A' . $r . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n) . $r;
        $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
    }

    private function cerrarHojaExcel($hoja, $r, $n, ...$montos)
    {
        $ultima = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);
        $hoja->getStyle('A' . $r . ':' . $ultima . $r)->getFont()->setBold(true);
        $hoja->getStyle('A4:' . $ultima . $r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BDBDBD');
        foreach ($montos as $rango) {
            $hoja->getStyle($rango)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        for ($c = 1; $c <= $n; $c++) {
            $hoja->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
        $hoja->freezePane('A5');
    }

    public function importar(Request $request, LibroVentasSiatImporter $importer)
    {
        $request->validate([
            // El SIAT entrega el libro dentro de un ZIP; tambien se acepta el XLSX suelto.
            'archivo' => ['required', 'file', 'max:51200'],
        ]);
        $archivo = $request->file('archivo');
        $ext = strtolower($archivo->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'xls', 'zip'])) {
            return response()->json(['message' => 'El archivo debe ser .zip, .xlsx o .xls'], 422);
        }

        // Un libro mensual completo pesa: se da aire solo a esta peticion.
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        try {
            $result = $importer->import(
                $archivo->getRealPath(),
                $archivo->getClientOriginalName(),
                $request->user()->CodAut ?? null
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'No se pudo importar el archivo: ' . $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    /**
     * Reenvia al SIAT una factura de la pestaña "En Sofia, no en SIAT".
     *
     * Usa el mismo SiatService::emitirFactura que el reenvio de Impuestos y de
     * Ficticias: sale EN LINEA con el CUFD de hoy, asi que lleva numero, CUF y
     * fecha de emision nuevos (entra al libro del mes actual, no al original).
     */
    public function reenviar(Request $request)
    {
        $data = $request->validate([
            'origen' => ['required', 'in:FACTURA,FICTICIA'],
            'id' => ['required', 'integer'],
        ]);

        $modelo = $data['origen'] === 'FICTICIA' ? FacturaFicticia::class : Factura::class;
        $factura = $modelo::with('detalles')->find($data['id']);
        if (!$factura) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }
        if ($data['origen'] === 'FACTURA' && $factura->tipo_comprobante !== 'FACTURA') {
            return response()->json(['message' => 'Esa venta se entregó como voucher'], 422);
        }
        // Anulada en Sofia: mandarla ahora crearia en Impuestos una venta que no existe.
        if ($factura->estado === 'ANULADO') {
            return response()->json(['message' => 'La factura está anulada en Sofia; no se reenvía'], 422);
        }
        if ($factura->online && $factura->estado_siat === 'VALIDADA') {
            return response()->json(['message' => 'La factura ya está validada en el SIAT; no hace falta reenviarla'], 422);
        }

        $siat = new SiatService();
        $numero = (int) $factura->nro_factura;

        // Se reenvia con el MISMO numero. Es seguro solo si ese numero nunca
        // quedo registrado en el SIAT y nadie mas de la serie lo usa.
        if ($numero > 0) {
            if ($otra = $this->numeroOcupado($factura, $numero)) {
                return response()->json(['message' => "El Nº {$numero} ya lo usa {$otra}; no se puede reenviar con ese número"], 422);
            }

            if ($factura->cuf) {
                try {
                    $estadoPrevio = $siat->verificarFactura($factura)['estado'];
                } catch (Throwable $e) {
                    return response()->json(['message' => 'No se pudo consultar al SIAT antes de reenviar: ' . $e->getMessage()], 422);
                }
                if ($estadoPrevio === 'VALIDADA') {
                    return response()->json([
                        'message' => "La factura {$numero} ya estaba validada en el SIAT: se marcó como VALIDADA, no hizo falta reenviarla",
                        'nro_factura' => $numero,
                        'estado_siat' => 'VALIDADA',
                    ]);
                }
                if ($estadoPrevio && strpos($estadoPrevio, 'ANULA') !== false) {
                    return response()->json(['message' => "La factura {$numero} figura anulada en el SIAT ({$estadoPrevio}); no se reenvía con ese número"], 422);
                }
            }
        }

        $factura = $factura->fresh(['detalles', 'usuario']);
        @set_time_limit(180);

        if ($data['origen'] === 'FACTURA') {
            // Conserva numero y fecha: si fallo otro dia va por evento significativo
            // con su fecha original. Si el SIAT lo rechaza NO se manda con la de hoy.
            try {
                $r = $siat->reenviarConservandoFecha($factura, optional($request->user())->CodAut);
            } catch (Throwable $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            $factura = $r['factura'];
            $porEvento = $r['por_evento'] ? ' con su fecha original, por evento significativo ' . $r['evento']->codigo_evento : '';
        } else {
            // Las ficticias siguen en linea.
            $factura = $siat->emitirFactura($factura, optional($request->user())->CodAut, $numero > 0);
            if ($factura->estado_siat === 'ERROR') {
                return response()->json(['message' => 'No se pudo enviar: ' . $factura->mensaje_siat], 422);
            }
            $porEvento = '';
        }

        return response()->json([
            'message' => "Factura {$factura->nro_factura} enviada al SIAT ({$factura->estado_siat}){$porEvento}",
            'nro_factura' => $factura->nro_factura,
            'estado_siat' => $factura->estado_siat,
        ]);
    }

    /**
     * Anula SOLO en Impuestos una factura del libro que no existe en Sofia
     * (pestaña "En SIAT, no en Sofia"). Va directo al SIN con el CUF: en
     * Sofia no hay venta, stock ni cobro que tocar. Las que si estan en Sofia
     * se anulan desde Facturacion, que ademas devuelve el stock.
     */
    public function anular(Request $request, $id)
    {
        $data = $request->validate([
            'codigo_motivo' => ['required', 'integer', 'in:' . implode(',', array_keys(self::MOTIVOS_ANULACION))],
        ]);

        $fila = LibroVentaSiat::find($id);
        if (!$fila) {
            return response()->json(['message' => 'La fila no existe'], 404);
        }
        if ($fila->estado !== 'VALIDA') {
            return response()->json(['message' => 'La factura ya está anulada en Impuestos'], 422);
        }
        if ($fila->factura()->exists() || $fila->ficticia()->exists()) {
            return response()->json(['message' => 'Esta factura está en Sofia: anúlela desde Facturación para devolver el stock'], 422);
        }
        if (DB::table('tbfactura')->where('cuffac', $fila->cuf)->exists()) {
            return response()->json(['message' => 'Esta factura es del sistema de caja: anúlela desde la caja'], 422);
        }

        try {
            $r = (new SiatService())->anularCuf($fila->cuf, (int) $data['codigo_motivo']);
        } catch (Throwable $e) {
            return response()->json(['message' => 'No se pudo comunicar con Impuestos: ' . $e->getMessage()], 422);
        }

        if ($r['no_existe']) {
            return response()->json(['message' => 'Impuestos dice que esa factura no existe (924): no hay nada que anular'], 422);
        }
        if (!$r['transaccion']) {
            return response()->json(['message' => 'Impuestos rechazó la anulación: ' . $r['mensaje']], 422);
        }

        $fila->update(['estado' => 'ANULADA']);
        Log::info('[VERIFICACION][ANULAR] Factura anulada en Impuestos', [
            'libro_id' => $fila->id, 'numero_factura' => $fila->numero_factura, 'cuf' => $fila->cuf,
            'codigo_motivo' => (int) $data['codigo_motivo'], 'usuario' => optional($request->user())->CodAut,
        ]);

        return response()->json([
            'message' => "Factura {$fila->numero_factura} anulada en Impuestos",
            'fila' => $fila->fresh(),
        ]);
    }

    public function motivosAnulacion()
    {
        return response()->json(collect(self::MOTIVOS_ANULACION)->map(function ($descripcion, $codigo) {
            return ['codigo' => $codigo, 'descripcion' => $descripcion];
        })->values());
    }

    public function destroy($id)
    {
        $fila = LibroVentaSiat::find($id);
        if (!$fila) {
            return response()->json(['message' => 'La fila no existe'], 404);
        }
        $fila->delete();

        return response()->noContent();
    }

    // ---------------------------------------------------------------------

    private function filteredQuery(Request $request, $conTab = true)
    {
        $month = $this->month($request);
        $tab = $conTab ? (string) $request->input('tab', '') : '';

        $query = LibroVentaSiat::query()
            ->whereBetween('fecha_factura', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->when($request->filled('estado'), function ($q) use ($request) { $q->where('estado', $request->input('estado')); });

        if ($buscar = trim((string) $request->input('q', ''))) {
            $like = '%' . $buscar . '%';
            $query->where(function ($w) use ($like) {
                $w->where('numero_factura', 'like', $like)
                    ->orWhere('cuf', 'like', $like)
                    ->orWhere('nit_ci_cliente', 'like', $like)
                    ->orWhere('razon_social', 'like', $like);
            });
        }

        if ($tab === 'en_sistema') {
            $this->whereEnSistema($query);
        } elseif ($tab === 'sin_registrar') {
            $this->whereSinRegistrar($query);
        } elseif ($tab === 'diferencias') {
            $this->whereDiferencias($query);
        }

        return $query;
    }

    /** Subconsulta "existe en la tabla $tabla una factura con el mismo CUF". */
    private function existeEn($tabla, $conDiferencia = false)
    {
        return function ($sub) use ($tabla, $conDiferencia) {
            $sub->selectRaw('1')->from($tabla)
                ->whereColumn("$tabla.cuf", 'libro_ventas_siat.cuf')
                ->whereNull("$tabla.deleted_at");
            if ($conDiferencia) {
                // Importe distinto, o anulada en un lado y valida en el otro.
                $sub->whereRaw(
                    "(ABS($tabla.total - libro_ventas_siat.importe_total) > ? OR (libro_ventas_siat.estado = 'VALIDA') <> ($tabla.estado = 'ACTIVO'))",
                    [self::TOLERANCIA]
                );
            }
        };
    }

    /**
     * Lo mismo contra tbfactura: el sistema de caja de escritorio factura con
     * el mismo NIT, asi que buena parte del libro del SIAT sale de ahi. Sus
     * columnas son otras (cuffac, Importe, ESTADO) y no tiene borrado logico.
     */
    private function existeEnCaja($conDiferencia = false)
    {
        return function ($sub) use ($conDiferencia) {
            $sub->selectRaw('1')->from('tbfactura')
                // CONVERT al latin1 de la caja: asi MySQL usa tbfactura_cuffac_index.
                ->whereRaw('tbfactura.cuffac = CONVERT(libro_ventas_siat.cuf USING latin1)');
            if ($conDiferencia) {
                $sub->whereRaw(
                    "((tbfactura.Importe > 0 AND ABS(tbfactura.Importe - libro_ventas_siat.importe_total) > ?) OR (libro_ventas_siat.estado = 'VALIDA') = (UPPER(COALESCE(tbfactura.ESTADO, '')) LIKE 'ANULA%'))",
                    [self::TOLERANCIA]
                );
            }
        };
    }

    private function whereEnSistema($query)
    {
        return $query->where(function ($w) {
            $w->whereExists($this->existeEn('facturas'))
                ->orWhereExists($this->existeEn('facturas_ficticias'))
                ->orWhereExists($this->existeEnCaja());
        });
    }

    private function whereSinRegistrar($query)
    {
        return $query->whereNotExists($this->existeEn('facturas'))
            ->whereNotExists($this->existeEn('facturas_ficticias'))
            ->whereNotExists($this->existeEnCaja());
    }

    private function whereDiferencias($query)
    {
        return $query->where(function ($w) {
            $w->whereExists($this->existeEn('facturas', true))
                ->orWhereExists($this->existeEn('facturas_ficticias', true))
                ->orWhereExists($this->existeEnCaja(true));
        });
    }

    /** Filas de caja (tbfactura) de esos CUF, adaptadas a la forma de "sistema". */
    private function cajaPorCuf(array $cufs)
    {
        if (!$cufs) {
            return collect();
        }

        return DB::table('tbfactura')
            ->whereIn('cuffac', $cufs)
            ->get(['CodAut', 'nrofac', 'cuffac', 'FechaFac', 'Importe', 'ESTADO'])
            ->keyBy('cuffac')
            ->map(function ($c) {
                $anulada = stripos((string) $c->ESTADO, 'ANULA') === 0;

                return (object) [
                    'id' => $c->CodAut,
                    'nro_factura' => $c->nrofac,
                    'fecha' => $c->FechaFac ? Carbon::parse($c->FechaFac) : null,
                    'nombre' => null,
                    'nit' => null,
                    'total' => (float) $c->Importe > 0 ? $c->Importe : null,
                    'descuento' => 0,
                    // Se traduce a como lo dice Sofia para comparar igual que las demas.
                    'estado' => $anulada ? 'ANULADO' : 'ACTIVO',
                    'estado_siat' => trim((string) $c->ESTADO) ?: null,
                ];
            });
    }

    /**
     * Otra factura de la misma serie (sucursal + punto de venta) con ese
     * numero: en facturas, facturas_ficticias o tbfactura (caja). Null si esta libre.
     */
    private function numeroOcupado($factura, $numero)
    {
        $esFicticia = $factura instanceof FacturaFicticia;

        $enFacturas = Factura::where('codigo_sucursal', $factura->codigo_sucursal)
            ->where('codigo_punto_venta', $factura->codigo_punto_venta)
            ->where('nro_factura', $numero)
            ->when(!$esFicticia, function ($q) use ($factura) { $q->where('id', '!=', $factura->id); })
            ->value('id');
        if ($enFacturas) {
            return "la factura #{$enFacturas}";
        }

        $enFicticias = FacturaFicticia::withTrashed()
            ->where('codigo_sucursal', $factura->codigo_sucursal)
            ->where('codigo_punto_venta', $factura->codigo_punto_venta)
            ->where('nro_factura', $numero)
            ->when($esFicticia, function ($q) use ($factura) { $q->where('id', '!=', $factura->id); })
            ->value('id');
        if ($enFicticias) {
            return "la factura ficticia #{$enFicticias}";
        }

        if (DB::table('tbfactura')->where('PuntVenta', $factura->codigo_punto_venta)->where('nrofac', $numero)->exists()) {
            return 'una factura del sistema de caja';
        }

        return null;
    }

    /** Lo que no coincide entre la fila del libro y la factura de Sofia. */
    private function diferencias(LibroVentaSiat $fila, $sistema)
    {
        $out = [];
        // La caja deja Importe en 0 (el monto vive en otra tabla): sin dato no se compara.
        if ($sistema->total !== null && abs((float) $sistema->total - (float) $fila->importe_total) > self::TOLERANCIA) {
            $out[] = 'importe';
        }
        if (($fila->estado === 'VALIDA') !== ($sistema->estado === 'ACTIVO')) {
            $out[] = 'estado';
        }

        return $out;
    }

    /** Union de facturas y ficticias con CUF del mes que el libro no tiene. */
    private function faltantesQuery(Request $request)
    {
        $month = $this->month($request);
        $desde = $month->copy()->startOfMonth()->toDateString();
        $hasta = $month->copy()->endOfMonth()->toDateString();
        $buscar = trim((string) $request->input('q', ''));

        $build = function ($tabla, $origen) use ($desde, $hasta, $buscar) {
            $q = DB::table($tabla)
                ->selectRaw("'$origen' as origen, id, nro_factura, cuf, fecha, hora, nit, nombre, total, estado, estado_siat, mensaje_siat")
                ->whereNull('deleted_at')
                ->whereNotNull('cuf')->where('cuf', '!=', '')
                // Las simuladas tienen CUF pero nunca llegaron a Impuestos.
                ->where(function ($w) { $w->whereNull('estado_siat')->orWhere('estado_siat', '!=', SiatService::ESTADO_SIMULADO); })
                // Cuenta en el mes en que se emitio al SIAT (una reenviada pasa al
                // mes del reenvio); si nunca tuvo fecha de emision, en el de la venta.
                ->whereRaw('COALESCE(LEFT(fecha_emision, 10), fecha) BETWEEN ? AND ?', [$desde, $hasta])
                ->whereNotExists(function ($sub) use ($tabla) {
                    $sub->selectRaw('1')->from('libro_ventas_siat')
                        ->whereColumn('libro_ventas_siat.cuf', "$tabla.cuf")
                        ->whereNull('libro_ventas_siat.deleted_at');
                });
            if ($buscar !== '') {
                $like = '%' . $buscar . '%';
                $q->where(function ($w) use ($like, $buscar) {
                    $w->where('nro_factura', $buscar)
                        ->orWhere('cuf', 'like', $like)
                        ->orWhere('nit', 'like', $like)
                        ->orWhere('nombre', 'like', $like);
                });
            }

            return $q;
        };

        return $build('facturas', 'FACTURA')->unionAll($build('facturas_ficticias', 'FICTICIA'));
    }

    private function urlSiat($cuf, $numero)
    {
        try {
            return FacturaFiscalController::urlSiat($cuf, $numero);
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Mes del filtro (YYYY-MM); por defecto el anterior, que es el ya cerrado en el SIAT. */
    private function month(Request $request)
    {
        $value = (string) $request->input('mes', '');

        try {
            return preg_match('/^\d{4}-\d{2}$/', $value)
                ? Carbon::createFromFormat('Y-m-d', $value . '-01')->startOfMonth()
                : now()->subMonthNoOverflow()->startOfMonth();
        } catch (Throwable $e) {
            return now()->subMonthNoOverflow()->startOfMonth();
        }
    }
}
