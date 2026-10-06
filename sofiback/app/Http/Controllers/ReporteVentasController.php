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

        $logo = public_path('logo-sofia.png');
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

    private function fecha(Request $request)
    {
        $datos = $request->validate(['fecha' => 'nullable|date']);

        return $datos['fecha'] ?? date('Y-m-d');
    }
}
