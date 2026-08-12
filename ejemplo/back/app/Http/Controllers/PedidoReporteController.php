<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PedidoReporteController extends Controller
{
    public function exportar(Request $request, string $tipo)
    {
        $tipo = strtoupper(trim($tipo));
        if (!in_array($tipo, ['POLLO', 'RES', 'CERDO', 'NORMAL'], true)) {
            return response()->json(['message' => 'Tipo de reporte no valido'], 422);
        }

        $fecha = (string) $request->input('fecha', now()->toDateString());
        $request->validate([
            'fecha' => 'nullable|date',
        ]);

        $rows = $this->rowsByTipo($request, $fecha, $tipo);
        if ($rows->isEmpty()) {
            return response()->json(['message' => "No hay pedidos para $tipo en la fecha seleccionada"], 404);
        }

        try {
            $spreadsheet = match ($tipo) {
                'POLLO' => $this->buildPolloTemplate($rows, $request, $fecha),
                'RES' => $this->buildResTemplate($rows, $request, $fecha),
                'CERDO' => $this->buildCerdoTemplate($rows, $request, $fecha),
                default => $this->buildNormalSheet($rows, $request, $fecha),
            };

            $safeUser = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($request->user()->name ?? 'vendedor'));
            $filename = strtolower($safeUser . '_' . $tipo . '_' . str_replace('-', '', $fecha) . '.xlsx');
            $tempPath = storage_path('app/' . uniqid('pedido_reporte_', true) . '.xlsx');

            $writer = new Xlsx($spreadsheet);
            $writer->save($tempPath);

            return response()->download(
                $tempPath,
                $filename,
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            )->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo generar el reporte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function rowsByTipo(Request $request, string $fecha, string $tipo): Collection
    {
        $items = Pedido::query()
            ->with(['detalles.producto', 'cliente:id,nombre'])
            ->where('tipo_pedido', 'REALIZAR_PEDIDO')
            ->where('user_id', $request->user()->id)
            ->whereDate('fecha', $fecha)
            ->orderBy('hora')
            ->orderBy('id')
            ->get();

        $rows = collect();
        foreach ($items as $pedido) {
            foreach ($pedido->detalles as $detalle) {
                $tipoDetalle = strtoupper((string) ($detalle->producto->tipo ?? 'NORMAL'));
                $tipoDetalle = in_array($tipoDetalle, ['POLLO', 'RES', 'CERDO'], true) ? $tipoDetalle : 'NORMAL';
                if ($tipoDetalle !== $tipo) {
                    continue;
                }
                $rows->push([
                    'pedido' => $pedido,
                    'detalle' => $detalle,
                    'extra' => is_array($detalle->detalle_extra) ? $detalle->detalle_extra : [],
                ]);
            }
        }

        return $rows;
    }

    private function buildPolloTemplate(Collection $rows, Request $request, string $fecha): Spreadsheet
    {
        $path = resource_path('excel/ppollo.xlsx');
        if (!file_exists($path)) {
            throw new \RuntimeException('No existe la plantilla ppollo.xlsx');
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('E2', (string) ($request->user()->name ?? ''));
        $sheet->setCellValue('AB2', $fecha);

        $rowNum = 4;
        foreach ($rows as $row) {
            $pedido = $row['pedido'];
            $detalle = $row['detalle'];
            $ex = $row['extra'];

            $sheet->setCellValue('B' . $rowNum, (string) ($pedido->cliente->nombre ?? ''));
            $sheet->setCellValue('C' . $rowNum, $ex['pollo_cja_b5'] ?? '');
            $sheet->setCellValue('D' . $rowNum, $ex['pollo_uni_b5'] ?? '');
            $sheet->setCellValue('E' . $rowNum, $ex['pollo_cja_b6'] ?? '');
            $sheet->setCellValue('F' . $rowNum, $ex['pollo_uni_b6'] ?? '');
            $sheet->setCellValue('G' . $rowNum, $ex['pollo_cja_104'] ?? '');
            $sheet->setCellValue('H' . $rowNum, $ex['pollo_uni_104'] ?? '');
            $sheet->setCellValue('I' . $rowNum, $ex['pollo_cja_105'] ?? '');
            $sheet->setCellValue('J' . $rowNum, $ex['pollo_uni_105'] ?? '');
            $sheet->setCellValue('K' . $rowNum, $ex['pollo_cja_106'] ?? '');
            $sheet->setCellValue('L' . $rowNum, $ex['pollo_uni_106'] ?? '');
            $sheet->setCellValue('M' . $rowNum, $ex['pollo_cja_107'] ?? '');
            $sheet->setCellValue('N' . $rowNum, $ex['pollo_uni_107'] ?? '');
            $sheet->setCellValue('O' . $rowNum, $ex['pollo_cja_108'] ?? '');
            $sheet->setCellValue('P' . $rowNum, $ex['pollo_uni_108'] ?? '');
            $sheet->setCellValue('Q' . $rowNum, $ex['pollo_cja_109'] ?? '');
            $sheet->setCellValue('R' . $rowNum, $ex['pollo_uni_109'] ?? '');
            $sheet->setCellValue('S' . $rowNum, $ex['pollo_rango_unidades'] ?? '');
            $sheet->setCellValue('T' . $rowNum, $this->joinValueUnit($ex['pollo_ala'] ?? '', $ex['pollo_ala_unidad'] ?? ''));
            $sheet->setCellValue('U' . $rowNum, $this->joinValueUnit($ex['pollo_cadera'] ?? '', $ex['pollo_cadera_unidad'] ?? ''));
            $sheet->setCellValue('V' . $rowNum, $this->joinValueUnit($ex['pollo_pecho'] ?? '', $ex['pollo_pecho_unidad'] ?? ''));
            $sheet->setCellValue('W' . $rowNum, $this->joinValueUnit($ex['pollo_pi_mu'] ?? '', $ex['pollo_pi_mu_unidad'] ?? ''));
            $sheet->setCellValue('X' . $rowNum, $this->joinValueUnit($ex['pollo_filete'] ?? '', $ex['pollo_filete_unidad'] ?? ''));
            $sheet->setCellValue('Y' . $rowNum, $this->joinValueUnit($ex['pollo_cuello'] ?? '', $ex['pollo_cuello_unidad'] ?? ''));
            $sheet->setCellValue('Z' . $rowNum, $this->joinValueUnit($ex['pollo_hueso'] ?? '', $ex['pollo_hueso_unidad'] ?? ''));
            $sheet->setCellValue('AA' . $rowNum, $this->joinValueUnit($ex['pollo_menudencia'] ?? '', $ex['pollo_menudencia_unidad'] ?? ''));
            $sheet->setCellValue('AB' . $rowNum, $ex['pollo_bs'] ?? '');
            $sheet->setCellValue('AC' . $rowNum, $ex['pollo_bs2'] ?? '');
            $sheet->setCellValue('AD' . $rowNum, strtoupper((string) $pedido->tipo_pago) === 'CONTADO' ? 'si' : 'no');
            $sheet->setCellValue('AE' . $rowNum, (string) ($detalle->observacion_detalle ?? ''));
            $sheet->setCellValue('AF' . $rowNum, $pedido->facturado ? 'SI' : 'NO');
            $sheet->setCellValue('AG' . $rowNum, (string) ($pedido->hora ?? ''));
            $sheet->setCellValue('AH' . $rowNum, (string) ($pedido->observaciones ?? ''));
            $rowNum++;
        }

        return $spreadsheet;
    }

    private function buildResTemplate(Collection $rows, Request $request, string $fecha): Spreadsheet
    {
        $path = resource_path('excel/pres.xlsx');
        if (!file_exists($path)) {
            throw new \RuntimeException('No existe la plantilla pres.xlsx');
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('C3', (string) ($request->user()->name ?? ''));
        $sheet->setCellValue('J3', $fecha);

        $rowNum = 6;
        foreach ($rows as $row) {
            $pedido = $row['pedido'];
            $detalle = $row['detalle'];
            $ex = $row['extra'];

            $sheet->setCellValue('B' . $rowNum, (string) ($pedido->cliente->nombre ?? ''));
            $sheet->setCellValue('C' . $rowNum, $ex['precio_res'] ?? $detalle->precio);
            $sheet->setCellValue('D' . $rowNum, $ex['res_trozado'] ?? '');
            $sheet->setCellValue('E' . $rowNum, $ex['res_entero'] ?? '');
            $sheet->setCellValue('F' . $rowNum, $ex['res_pierna'] ?? '');
            $sheet->setCellValue('G' . $rowNum, $ex['res_brazo'] ?? '');
            $sheet->setCellValue('J' . $rowNum, (string) ($detalle->observacion_detalle ?? ''));
            $sheet->setCellValue('K' . $rowNum, strtoupper((string) $pedido->tipo_pago) === 'CONTADO' ? 'si' : 'no');
            $sheet->setCellValue('L' . $rowNum, $pedido->facturado ? 'SI' : 'NO');
            $sheet->setCellValue('M' . $rowNum, (string) ($pedido->hora ?? ''));
            $sheet->setCellValue('N' . $rowNum, (string) ($pedido->observaciones ?? ''));
            $rowNum++;
        }

        return $spreadsheet;
    }

    private function buildCerdoTemplate(Collection $rows, Request $request, string $fecha): Spreadsheet
    {
        $path = resource_path('excel/pcerdo.xlsx');
        if (!file_exists($path)) {
            throw new \RuntimeException('No existe la plantilla pcerdo.xlsx');
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('C3', (string) ($request->user()->name ?? ''));
        $sheet->setCellValue('J3', $fecha);

        $rowNum = 6;
        foreach ($rows as $row) {
            $pedido = $row['pedido'];
            $detalle = $row['detalle'];
            $ex = $row['extra'];

            $sheet->setCellValue('B' . $rowNum, (string) ($pedido->cliente->nombre ?? ''));
            $sheet->setCellValue('C' . $rowNum, $ex['cerdo_precio_total'] ?? $detalle->precio);
            $sheet->setCellValue('E' . $rowNum, $ex['cerdo_entero'] ?? '');
            $sheet->setCellValue('F' . $rowNum, $ex['cerdo_desmembrado'] ?? '');
            $sheet->setCellValue('H' . $rowNum, $ex['cerdo_corte'] ?? '');
            $sheet->setCellValue('I' . $rowNum, $ex['cerdo_kilo'] ?? '');
            $sheet->setCellValue('J' . $rowNum, (string) ($detalle->observacion_detalle ?? ''));
            $sheet->setCellValue('U' . $rowNum, strtoupper((string) $pedido->tipo_pago) === 'CONTADO' ? 'si' : 'no');
            $sheet->setCellValue('V' . $rowNum, $pedido->facturado ? 'SI' : 'NO');
            $sheet->setCellValue('W' . $rowNum, (string) ($pedido->hora ?? ''));
            $sheet->setCellValue('X' . $rowNum, (string) ($pedido->observaciones ?? ''));
            $rowNum++;
        }

        return $spreadsheet;
    }

    private function buildNormalSheet(Collection $rows, Request $request, string $fecha): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('NORMAL');

        $sheet->setCellValue('A1', 'REPORTE NORMAL');
        $sheet->setCellValue('A2', 'Vendedor');
        $sheet->setCellValue('B2', (string) ($request->user()->name ?? ''));
        $sheet->setCellValue('C2', 'Fecha');
        $sheet->setCellValue('D2', $fecha);

        $headers = ['CLIENTE', 'PRODUCTO', 'CANTIDAD', 'PRECIO', 'SUBTOTAL', 'OBSERVACION', 'FACT', 'PAGO', 'HORARIO', 'COMENTARIO'];
        $rowHead = 4;
        foreach ($headers as $i => $header) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue($col . $rowHead, $header);
            $sheet->getStyle($col . $rowHead)->getFont()->setBold(true);
        }

        $rowNum = 5;
        foreach ($rows as $row) {
            $pedido = $row['pedido'];
            $detalle = $row['detalle'];
            $sheet->setCellValue('A' . $rowNum, (string) ($pedido->cliente->nombre ?? ''));
            $sheet->setCellValue('B' . $rowNum, (string) ($detalle->producto->nombre ?? ''));
            $sheet->setCellValue('C' . $rowNum, (float) $detalle->cantidad);
            $sheet->setCellValue('D' . $rowNum, (float) $detalle->precio);
            $sheet->setCellValue('E' . $rowNum, (float) $detalle->total);
            $sheet->setCellValue('F' . $rowNum, (string) ($detalle->observacion_detalle ?? ''));
            $sheet->setCellValue('G' . $rowNum, $pedido->facturado ? 'SI' : 'NO');
            $sheet->setCellValue('H' . $rowNum, strtoupper((string) $pedido->tipo_pago) === 'CONTADO' ? 'si' : 'no');
            $sheet->setCellValue('I' . $rowNum, (string) ($pedido->hora ?? ''));
            $sheet->setCellValue('J' . $rowNum, (string) ($pedido->observaciones ?? ''));
            $rowNum++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    private function joinValueUnit(mixed $value, mixed $unit): string
    {
        $value = trim((string) $value);
        $unit = trim((string) $unit);
        if ($value === '') {
            return '';
        }
        return trim($value . ' ' . $unit);
    }
}
