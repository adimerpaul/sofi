<?php

namespace App\Http\Controllers;

use App\Services\ReporteAuxiliarPollo;
use App\Services\TipoPedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Reportes del menu Ventas y Facturacion.
 */
class ReporteVentasController extends Controller
{
    /** Lo que se compara ante un redondeo de cantidades. */
    private const TOLERANCIA = 0.0005;

    /** Quiebre de stock de embutidos en pantalla. */
    public function quiebreEmbutido(Request $request)
    {
        $fecha = $this->fecha($request);

        return [
            'fecha'  => $fecha,
            'semana' => (int) date('W', strtotime($fecha)),
            'ciudad' => config('siat.emisor.ciudad'),
        ] + $this->quiebre($fecha);
    }

    /**
     * Quiebre de stock: lo que el cliente pidio de embutidos y no se le pudo
     * vender. Por cada producto de cada pedido, pedido - vendido en caja.
     *
     * - Pedido: tbpedidos de la fecha, tipo NORMAL (embutidos; podium y huevo
     *   se facturan aparte y no entran), sin bonificaciones.
     * - Vendido: el comprobante que emitio caja para ese pedido. Si se edito,
     *   cuenta la ultima version; un retorno parcial no cuenta, porque eso lo
     *   devolvio el cliente despues de la venta y no es falta de stock.
     * - Solo entran los pedidos que ya pasaron por caja: uno sin comprobante
     *   todavia no se vendio, no se sabe si va a quebrar.
     *
     * QUIEBRE TOTAL: no se vendio nada de ese producto. QUIEBRE PARCIAL: se
     * vendio menos de lo pedido.
     */
    private function quiebre($fecha)
    {
        $pedidas = DB::table('tbpedidos as p')
            ->join('tbclientes as c', 'c.Cod_Aut', '=', 'p.idCli')
            ->leftJoin('tbproductos as pr', DB::raw('TRIM(pr.cod_prod)'), '=', DB::raw('TRIM(p.cod_prod)'))
            ->leftJoin('personal as pe', 'pe.CodAut', '=', 'p.CIfunc')
            ->whereNull('p.deleted_at')
            ->whereDate('p.fecha', $fecha)
            ->where('p.bonificacion', 0)
            ->whereRaw(TipoPedido::sql('p') . " = 'NORMAL'")
            ->get([
                'p.NroPed', DB::raw('TRIM(p.cod_prod) as cod_prod'), 'p.Cant',
                DB::raw('UPPER(TRIM(COALESCE(p.caja, ""))) as caja'),
                DB::raw('UPPER(TRIM(COALESCE(pr.codUnid, ""))) as unidad'),
                DB::raw("COALESCE(NULLIF(TRIM(pr.Producto), ''), TRIM(p.cod_prod)) as producto"),
                DB::raw('TRIM(c.Nombres) as cliente'),
                DB::raw("TRIM(CONCAT_WS(' ', NULLIF(TRIM(pe.Nombre1), ''), NULLIF(TRIM(pe.App1), ''), NULLIF(TRIM(pe.Apm), ''))) as vendedor"),
            ]);

        $vendidas = $this->vendido($pedidas->pluck('NroPed')->unique()->values()->all());

        // Pedido agrupado por pedido y producto: un mismo codigo puede venir en
        // varias lineas (con otro precio).
        $filas = [];
        foreach ($pedidas as $l) {
            if (!isset($vendidas[$l->NroPed])) {
                continue;
            }
            $clave = $l->NroPed . '|' . $l->cod_prod;
            if (!isset($filas[$clave])) {
                $filas[$clave] = [
                    'pedido_nro' => (int) $l->NroPed,
                    'vendedor'   => $l->vendedor !== '' ? $l->vendedor : 'SIN VENDEDOR',
                    'cliente'    => $l->cliente,
                    'cod_prod'   => $l->cod_prod,
                    'producto'   => $l->producto,
                    // Lo pedido por kilo se compara con el peso cobrado; lo
                    // demas con la cantidad de piezas.
                    'por_kilo'   => ($l->caja !== '' ? $l->caja : $l->unidad) === 'KG',
                    'pedido'     => 0.0,
                ];
            }
            $filas[$clave]['pedido'] += (float) $l->Cant;
        }

        $resultado = [];
        foreach ($filas as $f) {
            $venta = $vendidas[$f['pedido_nro']][$f['cod_prod']] ?? null;
            $vendido = $venta ? ($f['por_kilo'] && $venta['peso'] > 0 ? $venta['peso'] : $venta['cantidad']) : 0.0;
            $pedido = round($f['pedido'], 3);
            $vendido = round($vendido, 3);
            $quiebre = round($pedido - $vendido, 3);
            if ($quiebre <= self::TOLERANCIA) {
                continue;
            }
            unset($f['por_kilo']);
            $resultado[] = $f + [
                'pedido'  => $pedido,
                'vendido' => $vendido,
                'quiebre' => $quiebre,
                'estado'  => $vendido > self::TOLERANCIA ? 'QUIEBRE PARCIAL' : 'QUIEBRE TOTAL',
            ];
        }

        // Como la tabla dinamica de la planilla: estado, vendedor, cliente, producto.
        usort($resultado, function ($a, $b) {
            return [$a['estado'] === 'QUIEBRE TOTAL', $a['vendedor'], $a['cliente'], $a['producto']]
                <=> [$b['estado'] === 'QUIEBRE TOTAL', $b['vendedor'], $b['cliente'], $b['producto']];
        });

        $total = function ($campo) use ($resultado) {
            return round(array_sum(array_column($resultado, $campo)), 3);
        };

        return [
            'filas'   => $resultado,
            'totales' => [
                'pedido'   => $total('pedido'),
                'vendido'  => $total('vendido'),
                'quiebre'  => $total('quiebre'),
                'parcial'  => count(array_filter($resultado, function ($f) { return $f['estado'] === 'QUIEBRE PARCIAL'; })),
                'total'    => count(array_filter($resultado, function ($f) { return $f['estado'] === 'QUIEBRE TOTAL'; })),
                'clientes' => count(array_unique(array_column($resultado, 'pedido_nro'))),
            ],
        ];
    }

    /**
     * Lo vendido por pedido y producto: [NroPed => [cod_prod => [cantidad, peso]]].
     * Solo trae los pedidos que tienen comprobante de caja vigente.
     */
    private function vendido(array $pedidos)
    {
        if (!$pedidos) {
            return [];
        }

        // Comprobantes de caja (no los de retorno parcial) de esos pedidos.
        $comprobantes = DB::table('facturas')
            ->whereNull('deleted_at')
            ->whereNull('factura_origen_id')
            ->where('pedido_tipo', 'NORMAL')
            ->whereIn('pedido_nro', $pedidos)
            ->orderBy('id')
            ->get(['id', 'pedido_nro', 'estado']);

        // Los que tienen un retorno parcial colgando: quedan anulados, pero lo
        // que se vendio fue eso.
        $conRetorno = DB::table('facturas')
            ->whereNull('deleted_at')
            ->whereIn('factura_origen_id', $comprobantes->pluck('id')->all() ?: [0])
            ->pluck('factura_origen_id')
            ->flip();

        // La ultima version de cada pedido. Si quedo anulada sin retorno, la
        // venta se cayo entera y el pedido no entra.
        $vigente = [];
        foreach ($comprobantes as $c) {
            $vigente[$c->pedido_nro] = $c;
        }
        $vigente = array_filter($vigente, function ($c) use ($conRetorno) {
            return $c->estado !== 'ANULADO' || $conRetorno->has($c->id);
        });
        if (!$vigente) {
            return [];
        }

        $pedidoDe = [];
        foreach ($vigente as $nro => $c) {
            $pedidoDe[$c->id] = $nro;
        }

        $vendido = array_fill_keys(array_keys($vigente), []);
        $detalles = DB::table('factura_detalles')
            ->whereNull('deleted_at')
            ->whereIn('factura_id', array_keys($pedidoDe))
            ->get(['factura_id', 'cod_prod', 'cantidad', 'peso']);
        foreach ($detalles as $d) {
            $nro = $pedidoDe[$d->factura_id];
            $cod = trim((string) $d->cod_prod);
            $actual = $vendido[$nro][$cod] ?? ['cantidad' => 0.0, 'peso' => 0.0];
            $vendido[$nro][$cod] = [
                'cantidad' => $actual['cantidad'] + (float) $d->cantidad,
                'peso'     => $actual['peso'] + (float) $d->peso,
            ];
        }

        return $vendido;
    }

    /** El mismo reporte en Excel, con el formato de la planilla que se usaba. */
    public function quiebreEmbutidoExcel(Request $request)
    {
        $fecha = $this->fecha($request);
        $datos = $this->quiebre($fecha);

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Quiebre embutido');

        $hoja->setCellValue('A1', 'QUIEBRE DE STOCK EMBUTIDO');
        $hoja->mergeCells('A1:F1');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $hoja->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $cabecera = [
            ['CIUDAD', config('siat.emisor.ciudad')],
            ['FECHA', date('d/m/Y', strtotime($fecha))],
            ['N° DE SEMANA', (int) date('W', strtotime($fecha))],
        ];
        foreach ($cabecera as $i => [$etiqueta, $valor]) {
            $fila = $i + 2;
            $hoja->setCellValue('A' . $fila, $etiqueta);
            $hoja->setCellValue('B' . $fila, $valor);
            $hoja->getStyle('A' . $fila)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            ]);
            $hoja->getStyle('B' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $hoja->getStyle('A2:B4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $hoja->getStyle('B2:B4')->getFont()->setBold(true);

        $logo = public_path(config('siat.emisor.logo', 'logo-sofia.png'));
        if (is_file($logo)) {
            $dibujo = new Drawing();
            $dibujo->setPath($logo);
            $dibujo->setHeight(60);
            $dibujo->setCoordinates('G1');
            $dibujo->setWorksheet($hoja);
        }

        $hoja->fromArray(['', '', '', '', 'PEDIDO', 'VENDIDO', 'QUIEBRE'], null, 'A6');
        $hoja->fromArray(['ESTADO', 'VENDEDOR', 'CLIENTE', 'PRODUCTO', 'Cant. pedida', 'Cant. vendida', 'Cant. quiebre'], null, 'A7');
        $hoja->getStyle('E6:G6')->getFont()->setBold(true);
        $hoja->getStyle('E6:G6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle('A7:G7')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        // Como en la tabla dinamica: estado, vendedor y cliente solo cuando cambian.
        $fila = 8;
        $anterior = ['estado' => null, 'vendedor' => null, 'pedido_nro' => null];
        foreach ($datos['filas'] as $f) {
            $nuevoEstado = $f['estado'] !== $anterior['estado'];
            $nuevoVendedor = $nuevoEstado || $f['vendedor'] !== $anterior['vendedor'];
            $nuevoCliente = $nuevoVendedor || $f['pedido_nro'] !== $anterior['pedido_nro'];

            if ($nuevoEstado) {
                $hoja->setCellValue('A' . $fila, $f['estado']);
            }
            if ($nuevoVendedor) {
                $hoja->setCellValue('B' . $fila, $f['vendedor']);
            }
            if ($nuevoCliente) {
                $hoja->setCellValue('C' . $fila, $f['cliente']);
            }
            if ($nuevoEstado && $fila > 8) {
                $hoja->getStyle("A{$fila}:G{$fila}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
            }
            $hoja->setCellValue('D' . $fila, $f['producto']);
            $hoja->setCellValue('E' . $fila, $f['pedido']);
            $hoja->setCellValue('F' . $fila, $f['vendido']);
            $hoja->setCellValue('G' . $fila, $f['quiebre']);
            $anterior = $f;
            $fila++;
        }

        $hoja->setCellValue('A' . $fila, 'Total general');
        $hoja->setCellValue('E' . $fila, $datos['totales']['pedido']);
        $hoja->setCellValue('F' . $fila, $datos['totales']['vendido']);
        $hoja->setCellValue('G' . $fila, $datos['totales']['quiebre']);
        $hoja->getStyle("A{$fila}:G{$fila}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
        ]);

        $hoja->getStyle('A8:D' . $fila)->getFont()->setSize(9);
        $hoja->getStyle('A8:B' . $fila)->getFont()->setBold(true);
        $hoja->getStyle('E7:G' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle('A7:G' . $fila)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR);
        $hoja->getStyle('F8:F' . max($fila - 1, 8))->getFont()->getColor()->setRGB('1F3864');
        $hoja->getStyle('G8:G' . max($fila - 1, 8))->getFont()->setBold(true)->getColor()->setRGB('C00000');

        foreach (['A' => 16, 'B' => 28, 'C' => 36, 'D' => 46, 'E' => 11, 'F' => 11, 'G' => 11] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
        $hoja->getRowDimension(7)->setRowHeight(28);
        $hoja->freezePane('A8');
        $hoja->setAutoFilter('A7:G' . max($fila - 1, 7));

        $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(7, 7);

        $nombre = 'quiebre_embutido_' . $fecha . '.xlsx';

        return response()->streamDownload(function () use ($libro) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save('php://output');
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Reporte auxiliar del dia de pollo en pantalla. */
    public function auxiliarPollo(Request $request)
    {
        return (new ReporteAuxiliarPollo())->datos($this->fecha($request));
    }

    /**
     * El reporte auxiliar de pollo en Excel, con el formato de la planilla de
     * papel. TOTAL y STOCK final van como formulas: las filas que el sistema
     * no registra (transito, devolucion, trozado, bajas) salen vacias y al
     * llenarlas a mano el stock se recalcula solo.
     */
    public function auxiliarPolloExcel(Request $request)
    {
        $fecha = $this->fecha($request);
        $datos = (new ReporteAuxiliarPollo())->datos($fecha);
        $codigos = array_column($datos['columnas'], 'cod_prod');

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Pollo');

        // A rotulos, B.. un codigo por columna y al final el pollo entero.
        $col = function ($i) { return Coordinate::stringFromColumnIndex(2 + $i); };
        $ultimoCodigo = $col(count($codigos) - 1);
        $colEntero = $col(count($codigos));
        $enteros = array_keys(array_filter($datos['columnas'], function ($c) { return $c['entero']; }));
        $rangoEntero = $col(min($enteros)) . '%d:' . $col(max($enteros)) . '%d';

        $dias = ['DOMINGO', 'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
        $meses = ['', 'ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.'];
        $t = strtotime($fecha);
        $dia = $dias[(int) date('w', $t)];

        $hoja->setCellValue('A1', 'R E P O R T E     A U X I L I A R     D E L     D I A');
        $hoja->mergeCells('A1:' . $col(10) . '2');
        $hoja->setCellValue($col(11) . '1', 'POLLO');
        $hoja->mergeCells($col(11) . '1:' . $colEntero . '2');
        $hoja->getStyle('A1:' . $colEntero . '2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'C00000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $hoja->setCellValue($col(9) . '3', 'Fecha Salida:');
        $hoja->setCellValue($col(11) . '3', date('j', $t) . '-' . $meses[(int) date('n', $t)] . '-' . date('Y', $t));
        $hoja->getStyle($col(9) . '3')->getFont()->getColor()->setRGB('C00000');
        $hoja->getStyle($col(11) . '3')->getFont()->setBold(true)->getColor()->setRGB('1F4E79');

        // Encabezado: codigo arriba y rotulo corto abajo.
        foreach ($datos['columnas'] as $i => $c) {
            $hoja->setCellValueExplicit($col($i) . '5', $c['cod_prod'], DataType::TYPE_STRING);
            $hoja->setCellValue($col($i) . '6', $c['corto']);
        }
        $hoja->setCellValue($colEntero . '5', 'POLLO');
        $hoja->setCellValue($colEntero . '6', 'ENTERO');
        $hoja->setCellValue('A6', $dia . ' ' . date('d/m', $t));
        $hoja->getStyle('A5:' . $colEntero . '6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B4C6D9']],
        ]);

        $fila = 7;
        $escribir = function ($rotulo, $valores, $estilo) use ($hoja, $codigos, $col, $colEntero, $rangoEntero, &$fila) {
            $hoja->setCellValue('A' . $fila, $rotulo);
            if ($valores !== null) {
                foreach ($codigos as $i => $cod) {
                    $valor = $valores[$cod] ?? 0;
                    $hoja->setCellValue($col($i) . $fila, is_string($valor) ? $valor : round($valor, 3));
                }
            }
            $hoja->setCellValue($colEntero . $fila, '=SUM(' . sprintf($rangoEntero, $fila, $fila) . ')');
            $hoja->getStyle('A' . $fila . ':' . $colEntero . $fila)->applyFromArray($estilo);
            return $fila++;
        };
        $formula = function ($plantilla) use ($codigos, $col) {
            $valores = [];
            foreach ($codigos as $i => $cod) {
                $valores[$cod] = str_replace('{c}', $col($i), $plantilla);
            }
            return $valores;
        };

        $relleno = function ($rgb) { return ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]]; };
        $estiloStock = ['font' => ['bold' => true], 'fill' => $relleno('D9C28F')];
        $estiloManual = ['font' => ['bold' => true, 'italic' => true, 'color' => ['rgb' => '1F3864']], 'fill' => $relleno('FFF2CC')];
        $estiloTotal = ['font' => ['bold' => true], 'fill' => $relleno('E6B9A6')];
        $estiloVendedor = ['font' => ['bold' => true, 'italic' => true]];

        // Lo que hay para el dia.
        $desdeEntrada = $escribir('STOCK', $datos['stock_inicial'], $estiloStock);
        foreach (['TRANSITO', 'TRANSITO 2', 'DEVOLUCION', 'TROZADO', 'PECHO/FILETE/HUESO'] as $rotulo) {
            $escribir($rotulo, null, $estiloManual);
        }
        $filaEntrada = $escribir('TOTAL', $formula('=SUM({c}' . $desdeEntrada . ':{c}' . ($fila - 1) . ')'), $estiloTotal);

        // Lo que salio ese dia.
        $hoja->setCellValue('A' . $fila, $dia . ' ' . date('d/m', $t));
        $hoja->getStyle('A' . $fila)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'C00000']]]);
        $fila++;
        $desdeSalida = $fila;
        foreach (['DIFERENCIA DEL BRASA', 'TRANSF. DE 106 A 105', 'BAJA CON OLOR'] as $rotulo) {
            $escribir($rotulo, null, $estiloManual);
        }
        foreach ($datos['vendedores'] as $v) {
            $escribir($v['nombre'], $v['valores'], $estiloVendedor);
        }
        $escribir('TROZADO', null, $estiloManual);
        $escribir('VENTAS DEL DIA', $datos['ventas_directas'],
            ['font' => ['bold' => true, 'italic' => true, 'color' => ['rgb' => 'C00000']], 'fill' => $relleno('F4E3A1')]);
        $filaSalida = $escribir('TOTAL', $formula('=SUM({c}' . $desdeSalida . ':{c}' . ($fila - 1) . ')'), $estiloTotal);
        $filaStock = $escribir('STOCK', $formula('={c}' . $filaEntrada . '-{c}' . $filaSalida),
            ['font' => ['bold' => true, 'color' => ['rgb' => '1F4E79']], 'fill' => $relleno('D9C28F')]);

        $hoja->setCellValue('A' . ($fila + 1), 'VENTAS DEL DIA = ventas directas de mostrador. STOCK de arriba = stock del sistema al empezar el día;'
            . ' las filas en amarillo se llenan a mano y el TOTAL y el STOCK final se recalculan solos.');
        $hoja->getStyle('A' . ($fila + 1))->getFont()->setItalic(true)->setSize(8)->getColor()->setRGB('7F7F7F');

        $hoja->getStyle('A5:' . $colEntero . $filaStock)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $hoja->getStyle('B7:' . $colEntero . $filaStock)->applyFromArray([
            'numberFormat' => ['formatCode' => '#,##0.0;[Red]-#,##0.0'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $hoja->getStyle($colEntero . '5:' . $colEntero . $filaStock)->getFont()->setBold(true)->getColor()->setRGB('C00000');

        $hoja->getColumnDimension('A')->setWidth(26);
        for ($i = 0; $i <= count($codigos); $i++) {
            $hoja->getColumnDimension($col($i))->setWidth(9);
        }
        $hoja->freezePane('B7');
        $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)->setFitToHeight(0);

        return response()->streamDownload(function () use ($libro) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save('php://output');
        }, 'reporte_auxiliar_pollo_' . $fecha . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Productos para elegir en el kardex: por codigo o nombre. */
    public function kardexProductos(Request $request)
    {
        $buscar = trim((string) $request->input('buscar', ''));

        return DB::table('tbproductos')
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($w) use ($buscar) {
                    $w->where('cod_prod', 'like', $buscar . '%')
                        ->orWhere('Producto', 'like', '%' . $buscar . '%');
                });
            })
            ->orderBy('Producto')
            ->limit(30)
            ->get([
                DB::raw('TRIM(cod_prod) as cod_prod'),
                DB::raw('TRIM(Producto) as producto'),
                DB::raw('COALESCE(stock_actual, 0) as stock_actual'),
            ]);
    }

    /** Kardex de un producto en pantalla; sin producto, el resumen de todos. */
    public function kardex(Request $request)
    {
        if (trim((string) $request->input('cod_prod', '')) === '') {
            return $this->resumenKardex($request);
        }

        return $this->datosKardex($request);
    }

    /**
     * Kardex de todos los productos: una fila por producto con lo que tenia
     * antes del rango, lo que entro y salio en el rango y con cuanto quedo.
     * Mismo criterio que el kardex de uno (tbstock, stock = cant - saldo).
     * Solo los que tienen movimientos en el rango o saldo distinto de cero.
     */
    private function resumenKardex(Request $request): array
    {
        $datos = $request->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
        ]);
        $desde = ($datos['desde'] ?? date('Y') . '-01-01') . ' 00:00:00';
        $hasta = ($datos['hasta'] ?? date('Y') . '-12-31') . ' 23:59:59';

        // SQL directo: los ? de las columnas van antes que el del WHERE.
        $movidos = DB::select(
            'SELECT TRIM(cod_prod) as cod_prod,
                SUM(CASE WHEN fecha < ? THEN cant - saldo ELSE 0 END) as anterior,
                SUM(CASE WHEN fecha >= ? THEN cant ELSE 0 END) as entradas,
                SUM(CASE WHEN fecha >= ? THEN saldo ELSE 0 END) as salidas,
                SUM(CASE WHEN fecha >= ? THEN 1 ELSE 0 END) as movimientos
            FROM tbstock WHERE fecha <= ? GROUP BY TRIM(cod_prod)',
            [$desde, $desde, $desde, $desde, $hasta]
        );

        $productos = DB::table('tbproductos')
            ->get([DB::raw('TRIM(cod_prod) as cod_prod'), DB::raw('TRIM(Producto) as producto'),
                DB::raw('COALESCE(stock_actual, 0) as stock_actual')])
            ->keyBy('cod_prod');

        $filas = collect($movidos)->map(function ($m) use ($productos) {
            $anterior = round((float) $m->anterior, 3);
            $entradas = round((float) $m->entradas, 3);
            $salidas = round((float) $m->salidas, 3);
            $producto = $productos->get($m->cod_prod);
            return [
                'cod_prod' => $m->cod_prod,
                'producto' => $producto->producto ?? 'Producto ' . $m->cod_prod,
                'saldo_anterior' => $anterior,
                'entradas' => $entradas,
                'salidas' => $salidas,
                'saldo_final' => round($anterior + $entradas - $salidas, 3),
                'movimientos' => (int) $m->movimientos,
                'stock_sistema' => round((float) ($producto->stock_actual ?? 0), 3),
            ];
        })->filter(function ($f) {
            return $f['movimientos'] > 0 || abs($f['saldo_final']) > 0.0005;
        })->sortBy('producto')->values();

        return [
            'todos' => true,
            'desde' => substr($desde, 0, 10),
            'hasta' => substr($hasta, 0, 10),
            'productos' => $filas,
            'totales' => [
                'productos' => $filas->count(),
                'movimientos' => $filas->sum('movimientos'),
            ],
        ];
    }

    /**
     * Kardex de un producto: cada entrada y salida de inventario (tbstock) en
     * el rango, con la existencia que iba quedando.
     *
     * - Entrada = cant, salida = saldo: el stock es SUM(cant - saldo).
     * - La existencia arranca con todo lo movido antes del rango y va sumando.
     *   Es la de tbstock; el stock del sistema (stock_actual) va aparte porque
     *   se reinicio con el conteo del 05/10/2026 y desde ahi solo lo mueven las
     *   ventas y compras web.
     * - Nro comanda y nro factura: los del sistema anterior salen de la fila
     *   (comandast) y de tbfactura; los movimientos web ("VENTA WEB 123")
     *   apuntan a la factura del sistema y de ahi sale su pedido.
     * - Por defecto, el año en curso.
     */
    private function datosKardex(Request $request): array
    {
        $datos = $request->validate([
            'cod_prod' => 'required|string|max:25',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
        ]);
        $cod = trim($datos['cod_prod']);
        $desde = $datos['desde'] ?? date('Y') . '-01-01';
        $hasta = $datos['hasta'] ?? date('Y') . '-12-31';

        $producto = DB::table('tbproductos')->where('cod_prod', $cod)
            ->first([DB::raw('TRIM(Producto) as producto'), DB::raw('COALESCE(stock_actual, 0) as stock_actual')]);
        abort_unless($producto, 404, 'Ese producto no existe');

        // El indice de cod_prod sirve: en tbstock los codigos no tienen espacios.
        $anterior = (float) DB::table('tbstock')->where('cod_prod', $cod)
            ->where('fecha', '<', $desde . ' 00:00:00')
            ->sum(DB::raw('cant - saldo'));

        $filas = DB::table('tbstock')->where('cod_prod', $cod)
            ->whereBetween('fecha', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->orderBy('fecha')->orderBy('CodAut')
            ->get(['CodAut', 'fecha', 'cant', 'saldo', 'MotivoEgreso', 'comandast', 'motivstock']);

        // Facturas web de los movimientos "VENTA WEB id" / "ANULA VENTA WEB id".
        $idsWeb = $filas->map(function ($f) {
            return preg_match('/VENTA WEB (\d+)$/', trim((string) $f->motivstock), $m) ? (int) $m[1] : null;
        })->filter()->unique()->values()->all();
        $web = $idsWeb ? DB::table('facturas')->whereIn('id', $idsWeb)
            ->get(['id', 'nro_factura', 'tipo_comprobante', 'pedido_nro'])->keyBy('id') : collect();

        // Facturas del sistema anterior, por comanda.
        $comandas = $filas->pluck('comandast')->filter()->unique()->values()->all();
        $legado = $comandas ? DB::table('tbfactura')->whereIn('comanda', $comandas)
            ->where('nrofac', '>', 0)->pluck('nrofac', 'comanda') : collect();

        $existencia = $anterior;
        $movimientos = $filas->map(function ($f) use (&$existencia, $web, $legado) {
            $entrada = (float) $f->cant;
            $salida = (float) $f->saldo;
            $existencia += $entrada - $salida;

            $comanda = (int) $f->comandast;
            $factura = $comanda ? ($legado[$comanda] ?? null) : null;
            if (preg_match('/VENTA WEB (\d+)$/', trim((string) $f->motivstock), $m) && ($w = $web->get((int) $m[1]))) {
                $comanda = $comanda ?: (int) $w->pedido_nro;
                $factura = $w->tipo_comprobante === 'FACTURA' && $w->nro_factura
                    ? $w->nro_factura
                    : 'Voucher #' . $w->id;
            }

            return [
                'id' => $f->CodAut,
                'fecha' => substr((string) $f->fecha, 0, 16),
                'entrada' => round($entrada, 3),
                'salida' => round($salida, 3),
                'motivo' => trim((string) $f->MotivoEgreso),
                'existencia' => round($existencia, 3),
                'comanda' => $comanda ?: null,
                'factura' => $factura ?: null,
                'motivo_stock' => trim((string) $f->motivstock),
            ];
        })->values();

        return [
            'cod_prod' => $cod,
            'producto' => $producto->producto,
            'desde' => $desde,
            'hasta' => $hasta,
            'saldo_anterior' => round($anterior, 3),
            'entradas' => round($movimientos->sum('entrada'), 3),
            'salidas' => round($movimientos->sum('salida'), 3),
            'saldo_final' => round($existencia, 3),
            'stock_sistema' => round((float) $producto->stock_actual, 3),
            'movimientos' => $movimientos,
        ];
    }

    /** El kardex en Excel, con las columnas de la planilla de siempre. */
    public function kardexExcel(Request $request)
    {
        if (trim((string) $request->input('cod_prod', '')) === '') {
            return $this->resumenKardexExcel($this->resumenKardex($request));
        }

        $datos = $this->datosKardex($request);

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Kardex');

        $hoja->setCellValue('A1', 'KARDEX ' . $datos['cod_prod'] . ' - ' . $datos['producto']);
        $hoja->mergeCells('A1:J1');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $hoja->setCellValue('A2', 'Del ' . date('d/m/Y', strtotime($datos['desde'])) . ' al ' . date('d/m/Y', strtotime($datos['hasta']))
            . '   ·   Saldo anterior: ' . $datos['saldo_anterior']);

        $hoja->fromArray(['CODIGO', 'PRODUCTO', 'FECHA REGISTRO', 'ENTRADA', 'SALIDA', 'MOTIVO INGRE/EGRE',
            'EXISTENCIA', 'NRO COMANDA', 'NRO FACTURA', 'MOTIVO INGR. STOCK'], null, 'A4');
        $hoja->getStyle('A4:J4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
        ]);

        $fila = 5;
        foreach ($datos['movimientos'] as $m) {
            $hoja->setCellValueExplicit('A' . $fila, $datos['cod_prod'], DataType::TYPE_STRING);
            $hoja->setCellValue('B' . $fila, $datos['producto']);
            $hoja->setCellValue('C' . $fila, date('d/m/Y H:i', strtotime($m['fecha'])));
            $hoja->setCellValue('D' . $fila, $m['entrada']);
            $hoja->setCellValue('E' . $fila, $m['salida']);
            $hoja->setCellValue('F' . $fila, $m['motivo']);
            $hoja->setCellValue('G' . $fila, $m['existencia']);
            $hoja->setCellValue('H' . $fila, $m['comanda']);
            $hoja->setCellValue('I' . $fila, $m['factura']);
            $hoja->setCellValue('J' . $fila, $m['motivo_stock']);
            $fila++;
        }

        $hoja->setCellValue('C' . $fila, 'TOTAL');
        $hoja->setCellValue('D' . $fila, $datos['entradas']);
        $hoja->setCellValue('E' . $fila, $datos['salidas']);
        $hoja->setCellValue('G' . $fila, $datos['saldo_final']);
        $hoja->getStyle("A{$fila}:J{$fila}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
        ]);

        $hoja->getStyle('D5:E' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle('G5:G' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle('A4:J' . $fila)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        foreach (['A' => 10, 'B' => 36, 'C' => 16, 'D' => 10, 'E' => 10, 'F' => 34, 'G' => 11, 'H' => 12, 'I' => 14, 'J' => 26] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
        $hoja->freezePane('A5');
        $hoja->setAutoFilter('A4:J' . max($fila - 1, 4));
        $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(4, 4);

        return response()->streamDownload(function () use ($libro) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save('php://output');
        }, 'kardex_' . $datos['cod_prod'] . '_' . $datos['desde'] . '_' . $datos['hasta'] . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * El kardex de todos los productos en Excel: una fila por producto. El
     * detalle movimiento por movimiento sale eligiendo el producto (todos
     * juntos son decenas de miles de filas).
     */
    private function resumenKardexExcel(array $datos)
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Kardex');

        $hoja->setCellValue('A1', 'KARDEX - TODOS LOS PRODUCTOS');
        $hoja->mergeCells('A1:H1');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $hoja->setCellValue('A2', 'Del ' . date('d/m/Y', strtotime($datos['desde'])) . ' al ' . date('d/m/Y', strtotime($datos['hasta']))
            . '   ·   ' . $datos['totales']['productos'] . ' productos');

        $hoja->fromArray(['CODIGO', 'PRODUCTO', 'SALDO ANTERIOR', 'ENTRADAS', 'SALIDAS', 'EXISTENCIA FINAL',
            'MOVIMIENTOS', 'STOCK SISTEMA'], null, 'A4');
        $hoja->getStyle('A4:H4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
        ]);

        $fila = 5;
        foreach ($datos['productos'] as $p) {
            $hoja->setCellValueExplicit('A' . $fila, $p['cod_prod'], DataType::TYPE_STRING);
            $hoja->fromArray([$p['producto'], $p['saldo_anterior'], $p['entradas'], $p['salidas'],
                $p['saldo_final'], $p['movimientos'], $p['stock_sistema']], null, 'B' . $fila, true);
            $fila++;
        }
        $hoja->getStyle('C5:F' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle('H5:H' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle('A4:H' . max($fila - 1, 4))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        foreach (['A' => 10, 'B' => 42, 'C' => 14, 'D' => 12, 'E' => 12, 'F' => 15, 'G' => 12, 'H' => 14] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
        $hoja->freezePane('A5');
        $hoja->setAutoFilter('A4:H' . max($fila - 1, 4));

        return response()->streamDownload(function () use ($libro) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save('php://output');
        }, 'kardex_todos_' . $datos['desde'] . '_' . $datos['hasta'] . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Columnas del detalle de ventas, en el orden de la planilla que ya usaban. */
    private const COLUMNAS_DETALLE = [
        'vendedor'     => 'VENDEDOR',
        'cod_prod'     => 'COD. PRODUCTO',
        'producto'     => 'PRODUCTO',
        'peso'         => 'PESO',
        'importe'      => 'IMPORTE',
        'p_compra'     => 'P.COMPRA',
        'placa'        => 'PLACA',
        'tipo_pago'    => 'TIP. PAGO',
        'cantidad'     => 'CANTIDAD',
        'cajas'        => 'CAJAS',
        'direccion'    => 'DIRECCION',
        'peso_promedio' => 'PESO PROMED',
        'doc_cliente'  => 'DOC CLIENTE',
        'cliente'      => 'CLIENTE',
        'nro_factura'  => 'NRO FACT',
        // El numero del comprobante: el Nro que sale impreso en la boleta.
        'nro_boleta'   => 'NRO BOLETA',
    ];

    /** Detalle de ventas en pantalla. */
    public function detalleVentas(Request $request)
    {
        [$desde, $hasta] = $this->turno($request);
        $filas = $this->detalle($desde, $hasta);

        return [
            'desde'   => $desde,
            'hasta'   => $hasta,
            'filas'   => $filas,
            'totales' => [
                'lineas'       => count($filas),
                'comprobantes' => count(array_unique(array_column($filas, 'factura_id'))),
                'peso'         => round(array_sum(array_column($filas, 'peso')), 3),
                'importe'      => round(array_sum(array_column($filas, 'importe')), 2),
                'p_compra'     => round(array_sum(array_column($filas, 'p_compra')), 2),
            ],
        ];
    }

    /**
     * Todo lo facturado en el turno, una fila por producto de cada comprobante
     * (factura o voucher), como la planilla de ventas del sistema anterior.
     *
     * - Entra todo lo que no esta anulado, venga de un pedido o de mostrador;
     *   la venta de mostrador va como vendedor AGENCIA.
     * - PESO: lo cobrado por kilo; lo que va por unidad, cantidad x el peso de
     *   cada unidad (tbproductos.CantPren).
     * - P.COMPRA: tbproductos.precio_compra x lo cobrado (kilos o unidades).
     *   Vacio mientras el producto no tenga precio de compra cargado.
     * - CANTIDAD: las piezas; en lo que va a granel sin piezas contadas, 0.
     * - CAJAS: los canastillos con que se peso en caja.
     * - CLIENTE: el nombre del comprobante (el de la ficha si no tiene).
     * - NRO FACT: el numero de factura; 0 en un voucher.
     * - NRO BOLETA: el numero del comprobante, el Nro de la boleta impresa.
     */
    private function detalle($desde, $hasta)
    {
        $lineas = DB::table('facturas as f')
            ->join('factura_detalles as d', 'd.factura_id', '=', 'f.id')
            ->leftJoin('tbproductos as pr', DB::raw('TRIM(pr.cod_prod)'), '=', DB::raw('TRIM(d.cod_prod)'))
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'f.cliente_id')
            ->whereNull('f.deleted_at')
            ->whereNull('d.deleted_at')
            ->where('f.estado', '<>', 'ANULADO')
            ->whereRaw('TIMESTAMP(DATE(f.fecha), f.hora) >= ?', [$desde])
            ->whereRaw('TIMESTAMP(DATE(f.fecha), f.hora) < ?', [$hasta])
            ->orderBy('f.id')
            ->orderBy('d.id')
            ->get([
                'f.id as factura_id', 'f.tipo_comprobante', 'f.nro_factura', 'f.tipo_pago', 'f.nit',
                // El camion: el de la venta directa o el del pedido (igual que en facturacion).
                DB::raw("COALESCE(NULLIF(TRIM(f.placa), ''), (
                    SELECT CONVERT(TRIM(COALESCE(pc.placa, '')) USING utf8mb4)
                    FROM tbpedidos pc
                    WHERE pc.deleted_at IS NULL AND pc.NroPed = f.pedido_nro
                      AND " . TipoPedido::sql('pc') . " = UPPER(TRIM(f.pedido_tipo))
                    LIMIT 1
                )) as placa"),
                // Por subconsulta: personal.ci no es unico y un join duplicaria filas.
                DB::raw("(SELECT TRIM(CONCAT_WS(' ', NULLIF(TRIM(pe.Nombre1), ''), NULLIF(TRIM(pe.Nombre2), ''),
                    NULLIF(TRIM(pe.App1), ''), NULLIF(TRIM(pe.Apm), '')))
                    FROM personal pe WHERE pe.ci = f.vendedor_ci LIMIT 1) as vendedor"),
                DB::raw('TRIM(c.Direccion) as direccion'),
                DB::raw("COALESCE(NULLIF(TRIM(f.nombre), ''), TRIM(c.Nombres)) as cliente"),
                DB::raw('TRIM(d.cod_prod) as cod_prod'), 'd.nombre', 'd.unidad', 'd.cantidad', 'd.peso',
                'd.canastillos', 'd.subtotal',
                'pr.CantPren', 'pr.precio_compra',
            ]);

        $filas = [];
        foreach ($lineas as $l) {
            $porKilo = (float) $l->peso > 0 || strtoupper(trim((string) $l->unidad)) === 'KG';
            $cantidad = (float) $l->cantidad;
            $pesoUnidad = (float) $l->CantPren;

            if ((float) $l->peso > 0) {
                $peso = (float) $l->peso;
            } elseif ($porKilo) {
                // Granel sin pesar aparte: la cantidad ya son kilos.
                $peso = $cantidad;
                $cantidad = 0.0;
            } else {
                $peso = $cantidad * $pesoUnidad;
            }

            $base = $porKilo ? $peso : (float) $l->cantidad;
            $pesoPromedio = !$porKilo && $pesoUnidad > 0
                ? $pesoUnidad
                : ($cantidad > 0 ? $peso / $cantidad : null);

            $filas[] = [
                'factura_id'    => (int) $l->factura_id,
                'vendedor'      => $l->vendedor ?: 'AGENCIA',
                'cod_prod'      => $l->cod_prod,
                'producto'      => trim((string) $l->nombre),
                'peso'          => round($peso, 3),
                'importe'       => round((float) $l->subtotal, 2),
                'p_compra'      => $l->precio_compra !== null ? round($base * (float) $l->precio_compra, 2) : null,
                'placa'         => (string) $l->placa,
                'tipo_pago'     => (string) $l->tipo_pago,
                'cantidad'      => round($cantidad, 3),
                'cajas'         => (int) $l->canastillos,
                'direccion'     => (string) $l->direccion,
                'peso_promedio' => $pesoPromedio !== null ? round($pesoPromedio, 3) : null,
                'doc_cliente'   => trim((string) $l->nit),
                'cliente'       => trim((string) $l->cliente),
                'nro_factura'   => $l->tipo_comprobante === 'FACTURA' ? (int) $l->nro_factura : 0,
                'nro_boleta'    => (int) $l->factura_id,
            ];
        }

        // Como la planilla: por vendedor y, dentro, por cliente.
        usort($filas, function ($a, $b) {
            return [$a['vendedor'] !== 'AGENCIA', $a['vendedor'], $a['doc_cliente'], $a['factura_id']]
                <=> [$b['vendedor'] !== 'AGENCIA', $b['vendedor'], $b['doc_cliente'], $b['factura_id']];
        });

        return $filas;
    }

    /** El detalle de ventas en Excel, con las columnas de la planilla. */
    public function detalleVentasExcel(Request $request)
    {
        [$desde, $hasta] = $this->turno($request);
        $filas = $this->detalle($desde, $hasta);

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Ventas');

        $hoja->fromArray(array_values(self::COLUMNAS_DETALLE), null, 'A1');
        $ultimaCol = Coordinate::stringFromColumnIndex(count(self::COLUMNAS_DETALLE));
        $hoja->getStyle("A1:{$ultimaCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
        ]);

        $fila = 2;
        foreach ($filas as $f) {
            $col = 1;
            foreach (array_keys(self::COLUMNAS_DETALLE) as $campo) {
                $celda = Coordinate::stringFromColumnIndex($col++) . $fila;
                // Codigos y documentos como texto: si no, Excel les come los ceros.
                if (in_array($campo, ['cod_prod', 'doc_cliente'], true)) {
                    $hoja->setCellValueExplicit($celda, (string) $f[$campo], DataType::TYPE_STRING);
                } else {
                    $hoja->setCellValue($celda, $f[$campo]);
                }
            }
            $fila++;
        }

        $hoja->setCellValue('A' . $fila, 'TOTAL');
        $hoja->setCellValue('D' . $fila, round(array_sum(array_column($filas, 'peso')), 3));
        $hoja->setCellValue('E' . $fila, round(array_sum(array_column($filas, 'importe')), 2));
        $hoja->setCellValue('F' . $fila, round(array_sum(array_column($filas, 'p_compra')), 2));
        $hoja->getStyle("A{$fila}:{$ultimaCol}{$fila}")->getFont()->setBold(true);

        $hoja->getStyle('D2:D' . $fila)->getNumberFormat()->setFormatCode('#,##0.000');
        $hoja->getStyle('E2:F' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle('I2:I' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle('L2:L' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle("A1:{$ultimaCol}{$fila}")->getFont()->setSize(9);
        foreach (['A' => 34, 'B' => 10, 'C' => 46, 'D' => 9, 'E' => 11, 'F' => 11, 'G' => 18, 'H' => 11,
                     'I' => 9, 'J' => 7, 'K' => 40, 'L' => 10, 'M' => 13, 'N' => 34, 'O' => 9, 'P' => 10] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
        $hoja->freezePane('A2');
        $hoja->setAutoFilter("A1:{$ultimaCol}" . max($fila - 1, 1));

        $nombre = 'ventas_' . str_replace([' ', ':'], ['_', ''], substr($desde, 0, 16))
            . '_a_' . str_replace([' ', ':'], ['_', ''], substr($hasta, 0, 16)) . '.xlsx';

        return response()->streamDownload(function () use ($libro) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save('php://output');
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Desde y hasta del detalle de ventas como 'Y-m-d H:i:s'. Por defecto el
     * turno de ayer a las 18:00 a hoy a las 18:00; el hasta no se incluye.
     */
    private function turno(Request $request)
    {
        $datos = $request->validate([
            'desde'      => 'nullable|date_format:Y-m-d',
            'hora_desde' => 'nullable|date_format:H:i',
            'hasta'      => 'nullable|date_format:Y-m-d',
            'hora_hasta' => 'nullable|date_format:H:i',
        ]);

        $desde = ($datos['desde'] ?? date('Y-m-d', strtotime('-1 day'))) . ' ' . ($datos['hora_desde'] ?? '18:00') . ':00';
        $hasta = ($datos['hasta'] ?? date('Y-m-d')) . ' ' . ($datos['hora_hasta'] ?? '18:00') . ':00';

        return [$desde, $hasta];
    }

    private function fecha(Request $request)
    {
        $datos = $request->validate(['fecha' => 'nullable|date']);

        return $datos['fecha'] ?? date('Y-m-d');
    }
}
