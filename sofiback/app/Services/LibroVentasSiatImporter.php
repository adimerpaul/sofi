<?php

namespace App\Services;

use App\Models\LibroVentaSiat;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Importa el libro de ventas del SIAT (XLSX, o el ZIP tal como se descarga)
 * a la tabla libro_ventas_siat.
 *
 * El CUF manda: si ya existe una fila con ese codigo de autorizacion no se
 * inserta otra, pero se actualizan sus datos (estado, importes) con lo que
 * trae el archivo, porque el SIAT cambia el estado cuando se anula una
 * factura. Las filas eliminadas a proposito no se tocan ni se resucitan.
 *
 * El archivo se lee por bloques de filas: PhpSpreadsheet guarda cada celda
 * como objeto y un libro mensual completo agota la memoria de PHP.
 */
class LibroVentasSiatImporter
{
    /** Cabeceras exactas del reporte del SIAT, en orden. */
    const HEADERS = [
        'Nº', 'FECHA DE LA FACTURA', 'Nº DE LA FACTURA', 'CODIGO DE AUTORIZACIÓN', 'NIT / CI CLIENTE',
        'COMPLEMENTO', 'NOMBRE O RAZON SOCIAL', 'IMPORTE TOTAL DE LA VENTA', 'IMPORTE ICE', 'IMPORTE IEHD',
        'IMPORTE IPJ', 'TASAS', 'OTROS NO SUJETOS AL IVA', 'EXPORTACIONES Y OPERACIONES EXENTAS',
        'VENTAS GRAVADAS A TASA CERO', 'SUBTOTAL', 'DESCUENTOS BONIFICACIONES Y REBAJAS SUJETAS AL IVA',
        'IMPORTE GIFT CARD', 'IMPORTE BASE PARA DEBITO FISCAL', 'DEBITO FISCAL', 'ESTADO',
        'CODIGO DE CONTROL', 'TIPO DE VENTA', 'CON DERECHO A CREDITO FISCAL', 'ESTADO CONSOLIDACION',
    ];

    const READ_CHUNK = 1000;
    const INSERT_CHUNK = 500;
    const LAST_COLUMN = 'Y';

    /**
     * @return array{total:int, insertados:int, duplicados:int, actualizados:int, meses:array}
     */
    public function import($path, $fileName, $userId = null)
    {
        $extracted = $this->extractSpreadsheet($path);

        try {
            return $this->importSpreadsheet($extracted ?: $path, $fileName, $userId);
        } finally {
            if ($extracted !== null) {
                @unlink($extracted);
            }
        }
    }

    /**
     * Si es un ZIP con el reporte dentro, deja el XLSX en un temporal y
     * devuelve su ruta. Null cuando ya es un XLSX (que tambien es un ZIP,
     * pero trae [Content_Types].xml) o un XLS antiguo.
     */
    private function extractSpreadsheet($path)
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }

        if ($zip->locateName('[Content_Types].xml') !== false) {
            $zip->close();

            return null;
        }

        $entry = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('/\.(xlsx|xls)$/i', $name)) {
                $entry = $name;
                break;
            }
        }

        if ($entry === null) {
            $zip->close();
            throw new RuntimeException('El archivo comprimido no contiene ningún Excel del libro de ventas.');
        }

        $base = tempnam(sys_get_temp_dir(), 'libro');
        @unlink($base);
        $target = $base . (preg_match('/\.xls$/i', $entry) ? '.xls' : '.xlsx');

        $contents = $zip->getFromName($entry);
        $zip->close();
        if ($contents === false || file_put_contents($target, $contents) === false) {
            throw new RuntimeException("No se pudo extraer «{$entry}» del archivo comprimido.");
        }

        return $target;
    }

    private function importSpreadsheet($path, $fileName, $userId)
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $info = $reader->listWorksheetInfo($path);
        } catch (Throwable $e) {
            $info = [];
        }

        // La version de PhpSpreadsheet de Sofia a veces no informa las filas:
        // entonces se lee por bloques hasta que un bloque llegue vacio.
        $lastRow = (int) ($info[0]['totalRows'] ?? 0);
        $sinLimite = $lastRow <= 0;
        if ($sinLimite) {
            $lastRow = PHP_INT_MAX;
        }
        $filter = new class implements IReadFilter {
            public $from = 1;
            public $to = 1;

            public function readCell($column, $row, $worksheetName = '')
            {
                return $row >= $this->from && $row <= $this->to;
            }
        };
        $reader->setReadFilter($filter);

        $seen = [];
        $months = [];
        $total = 0;
        $inserted = 0;
        $updated = 0;
        $pending = [];
        $headersChecked = false;

        for ($from = 1; $from <= $lastRow; $from += self::READ_CHUNK) {
            $to = min($from + self::READ_CHUNK - 1, $lastRow);
            $filter->from = $from;
            $filter->to = $to;

            try {
                $spreadsheet = $reader->load($path);
                $sheet = $spreadsheet->getSheet(0);
                $highest = (int) $sheet->getHighestDataRow();
                if ($sinLimite && $highest < $from) {
                    $spreadsheet->disconnectWorksheets();
                    break;
                }
                $chunk = $sheet->rangeToArray('A' . $from . ':' . self::LAST_COLUMN . min($to, max($highest, $from)), null, true, false, false);
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $sheet);
            } catch (Throwable $e) {
                throw new RuntimeException('No se pudo leer el archivo Excel: ' . $e->getMessage());
            }

            foreach ($chunk as $offset => $row) {
                $number = $from + $offset;
                if ($number === 1) {
                    $this->assertHeaders($row);
                    $headersChecked = true;
                    continue;
                }

                $cuf = trim((string) ($row[3] ?? ''));
                if ($cuf === '' || isset($seen[$cuf])) {
                    continue;   // pie del reporte, fila vacia o repetida en el propio archivo
                }
                $seen[$cuf] = true;
                $total++;

                $date = $this->parseDate($row[1] ?? null, $number);
                $months[$date->format('Y-m')] = true;
                $pending[$cuf] = $this->mapRow($row, $cuf, $date, $fileName, $userId);

                if (count($pending) >= self::INSERT_CHUNK) {
                    list($new, $changed) = $this->insertNew($pending);
                    $inserted += $new;
                    $updated += $changed;
                    $pending = [];
                }
            }

            unset($chunk);
        }

        if (!$headersChecked) {
            throw new RuntimeException('El archivo no tiene las columnas del libro de ventas del SIAT.');
        }

        if ($pending) {
            list($new, $changed) = $this->insertNew($pending);
            $inserted += $new;
            $updated += $changed;
        }

        $months = array_keys($months);
        sort($months);

        return [
            'total' => $total,
            'insertados' => $inserted,
            'duplicados' => $total - $inserted,
            'actualizados' => $updated,
            'meses' => $months,
        ];
    }

    /**
     * Inserta los CUF nuevos y actualiza los existentes si el archivo trae
     * otros datos. withTrashed: un CUF borrado sigue ocupando el indice unico.
     */
    private function insertNew(array $rows)
    {
        $existing = LibroVentaSiat::withTrashed()->whereIn('cuf', array_keys($rows))->get()->keyBy('cuf');
        $new = array_values(array_diff_key($rows, $existing->all()));
        if ($new) {
            LibroVentaSiat::insert($new);
        }

        $updated = 0;
        foreach ($existing as $cuf => $fila) {
            if ($fila->trashed()) {
                continue;
            }
            // Se conserva quien la importo primero; el resto refleja el ultimo archivo.
            $fila->fill(Arr::except($rows[$cuf], ['user_id', 'archivo_origen', 'created_at', 'updated_at']));
            if ($fila->isDirty()) {
                $fila->archivo_origen = $rows[$cuf]['archivo_origen'];
                $fila->save();
                $updated++;
            }
        }

        return [count($new), $updated];
    }

    private function mapRow(array $row, $cuf, Carbon $date, $fileName, $userId)
    {
        $now = now();

        return [
            'nro' => is_numeric($row[0] ?? null) ? (int) $row[0] : null,
            'fecha_factura' => $date->toDateString(),
            'numero_factura' => $this->text($row[2] ?? null, 30) ?? '',
            'cuf' => $cuf,
            'nit_ci_cliente' => $this->text($row[4] ?? null, 30),
            'complemento' => $this->text($row[5] ?? null, 10),
            'razon_social' => $this->text($row[6] ?? null, 255),
            'importe_total' => $this->amount($row[7] ?? null),
            'importe_ice' => $this->amount($row[8] ?? null),
            'importe_iehd' => $this->amount($row[9] ?? null),
            'importe_ipj' => $this->amount($row[10] ?? null),
            'tasas' => $this->amount($row[11] ?? null),
            'otros_no_sujetos_iva' => $this->amount($row[12] ?? null),
            'exportaciones_exentas' => $this->amount($row[13] ?? null),
            'ventas_tasa_cero' => $this->amount($row[14] ?? null),
            'subtotal' => $this->amount($row[15] ?? null),
            'descuentos' => $this->amount($row[16] ?? null),
            'importe_gift_card' => $this->amount($row[17] ?? null),
            'importe_base_debito_fiscal' => $this->amount($row[18] ?? null),
            'debito_fiscal' => $this->amount($row[19] ?? null),
            'estado' => mb_strtoupper($this->text($row[20] ?? null, 20) ?? 'VALIDA'),
            'codigo_control' => $this->text($row[21] ?? null, 30),
            'tipo_venta' => $this->text($row[22] ?? null, 30),
            'credito_fiscal' => $this->text($row[23] ?? null, 5),
            'estado_consolidacion' => $this->text($row[24] ?? null, 30),
            'archivo_origen' => mb_substr($fileName, 0, 255),
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function assertHeaders(array $row)
    {
        $found = array_map([$this, 'normalizeHeader'], array_slice($row, 0, count(self::HEADERS)));
        $expected = array_map([$this, 'normalizeHeader'], self::HEADERS);

        if ($found !== $expected) {
            throw new RuntimeException('El archivo no tiene las columnas del libro de ventas del SIAT.');
        }
    }

    /** Mayusculas, sin tildes y con espacios simples: el SIAT no siempre es consistente. */
    private function normalizeHeader($value)
    {
        $text = mb_strtoupper(trim((string) $value));
        $text = strtr($text, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'º' => 'O', '°' => 'O']);

        return preg_replace('/\s+/u', ' ', $text);
    }

    private function text($value, $length)
    {
        $text = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $text === '' ? null : mb_substr($text, 0, $length);
    }

    private function amount($value)
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }
        // A veces los importes vienen como texto con separador de miles.
        $clean = str_replace([' ', ','], '', (string) $value);

        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }

    private function parseDate($value, $row)
    {
        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            }

            return Carbon::createFromFormat('d/m/Y', trim((string) $value))->startOfDay();
        } catch (Throwable $e) {
            throw new RuntimeException("Fila {$row}: fecha de la factura inválida ({$value}).");
        }
    }
}
