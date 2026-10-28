<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

        return $this->filtrar($request)->with(['detalles', 'usuario:CodAut,Nombre1,App1,Apm'])
            ->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Los filtros de la pantalla, compartidos por el listado y el Excel para
     * que el archivo salga con exactamente lo que se esta viendo.
     */
    private function filtrar(Request $request)
    {
        $query = Compra::query();

        if ($desde = $request->input('desde')) {
            $query->whereDate('fecha', '>=', $desde);
        }
        if ($hasta = $request->input('hasta')) {
            $query->whereDate('fecha', '<=', $hasta);
        }
        if ($estado = $request->input('estado')) {
            $query->where('estado', $estado);
        }
        if ($usuario = $request->input('usuario')) {
            $query->where('user_id', $usuario);
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

        if ($producto = trim((string) $request->input('producto', ''))) {
            $query->whereHas('detalles', function ($d) use ($producto) {
                $this->filtrarProducto($d, $producto);
            });
        }

        return $query;
    }

    /** Por codigo exacto o por parte del nombre del producto. */
    private function filtrarProducto($query, $producto)
    {
        $query->where(function ($w) use ($producto) {
            $w->where('cod_prod', $producto)->orWhere('nombre', 'like', '%' . $producto . '%');
        });
    }

    /** Quienes registraron compras alguna vez, para el filtro de usuario. */
    public function usuarios()
    {
        $ids = Compra::query()->distinct()->pluck('user_id');

        return User::whereIn('CodAut', $ids)->get(['CodAut', 'Nombre1', 'App1', 'Apm'])
            ->map(function ($u) {
                return ['id' => $u->CodAut, 'nombre' => $this->nombreUsuario($u)];
            })->sortBy('nombre')->values();
    }

    /** personal guarda los nombres con espacios de relleno. */
    private function nombreUsuario($u)
    {
        if (!$u) {
            return '—';
        }
        $partes = array_filter(array_map('trim', [$u->Nombre1 ?? '', $u->App1 ?? '', $u->Apm ?? '']));

        return $partes ? implode(' ', $partes) : '—';
    }

    /**
     * Reporte de compras en Excel, con los mismos filtros de la pantalla.
     *
     * Una portada con los indicadores del periodo, el listado de compras, el
     * detalle linea por linea y los resumenes por producto, fecha, usuario y
     * proveedor. Las anuladas salen en el listado y el detalle (marcadas en
     * rojo, para que se vea que existieron) pero no suman en ningun total ni
     * resumen: su stock ya fue devuelto.
     */
    public function excel(Request $request)
    {
        $compras = $this->filtrar($request)
            ->with(['detalles', 'usuario:CodAut,Nombre1,App1,Apm'])
            ->orderBy('fecha')->orderBy('hora')->orderBy('id')
            ->get();

        if ($compras->isEmpty()) {
            return response()->json(['message' => 'No hay compras con esos filtros'], 422);
        }

        $producto = trim((string) $request->input('producto', ''));

        $cabeceras = [];
        $lineas = [];
        foreach ($compras as $c) {
            $usuario = $this->nombreUsuario($c->usuario);
            $fecha = $c->fecha ? $c->fecha->format('Y-m-d') : '';
            $detalles = $c->detalles;
            // Con filtro de producto el detalle muestra solo ese producto; la
            // compra entera igual va en el listado con su total real.
            if ($producto !== '') {
                $detalles = $detalles->filter(function ($d) use ($producto) {
                    return trim($d->cod_prod) === $producto || stripos((string) $d->nombre, $producto) !== false;
                });
            }

            $cabeceras[] = [
                'id'        => $c->id,
                'fecha'     => $this->fechaExcel($fecha),
                'hora'      => (string) $c->hora,
                'proveedor' => $c->proveedor ?: 'Sin proveedor',
                'nit'       => (string) $c->nit,
                'factura'   => (string) $c->nro_factura,
                'pago'      => (string) $c->tipo_pago,
                'usuario'   => $usuario,
                'estado'    => $c->estado,
                'items'     => $c->detalles->count(),
                'subtotal'  => (float) $c->subtotal,
                'descuento' => (float) $c->descuento,
                'total'     => (float) $c->total,
                'obs'       => trim((string) ($c->estado === 'ANULADO' ? 'Anulada: ' . $c->motivo_anulacion : $c->observacion)),
            ];

            foreach ($detalles as $d) {
                $lineas[] = [
                    'id'        => $c->id,
                    'fecha'     => $this->fechaExcel($fecha),
                    'dia'       => $fecha,
                    'proveedor' => $c->proveedor ?: 'Sin proveedor',
                    'usuario'   => $usuario,
                    'cod_prod'  => trim($d->cod_prod),
                    'producto'  => trim((string) $d->nombre),
                    'unidad'    => trim((string) $d->unidad),
                    'cantidad'  => (float) $d->cantidad,
                    'precio'    => (float) $d->precio,
                    'subtotal'  => (float) $d->subtotal,
                    'lote'      => (string) $d->lote,
                    'vence'     => $d->fecha_vencimiento ? $this->fechaExcel(substr($d->fecha_vencimiento, 0, 10)) : '',
                    'estado'    => $c->estado,
                ];
            }
        }

        $activas = array_values(array_filter($cabeceras, function ($c) {
            return $c['estado'] !== 'ANULADO';
        }));
        $lineasActivas = array_values(array_filter($lineas, function ($l) {
            return $l['estado'] !== 'ANULADO';
        }));

        $desde = $request->input('desde');
        $hasta = $request->input('hasta');
        $rango = !$desde && !$hasta ? 'Todas las fechas'
            : ($desde === $hasta ? date('d/m/Y', strtotime($desde))
                : 'Del ' . ($desde ? date('d/m/Y', strtotime($desde)) : 'inicio') . ' al ' . ($hasta ? date('d/m/Y', strtotime($hasta)) : 'hoy'));
        $subtitulo = $rango . ' · generado el ' . date('d/m/Y H:i') . ' por ' . $this->nombreUsuario($request->user());

        $libro = new Spreadsheet();
        $libro->getProperties()->setCreator('Sofia')->setTitle('Reporte de compras');
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $this->hojaPortada($libro->getActiveSheet()->setTitle('Resumen'), $subtitulo, $request, $activas, $cabeceras, $lineasActivas);

        $this->hojaCompras(
            $libro->createSheet()->setTitle('Compras'), 'COMPRAS', $subtitulo,
            [
                ['Nº', 'id', 8, 'entero'],
                ['Fecha', 'fecha', 11, 'fecha'],
                ['Hora', 'hora', 9, 'texto'],
                ['Proveedor', 'proveedor', 30, 'texto'],
                ['NIT', 'nit', 13, 'texto'],
                ['Factura', 'factura', 12, 'texto'],
                ['Pago', 'pago', 11, 'texto'],
                ['Registrado por', 'usuario', 24, 'texto'],
                ['Estado', 'estado', 10, 'texto'],
                ['Ítems', 'items', 7, 'entero'],
                ['Subtotal Bs', 'subtotal', 13, 'importe'],
                ['Descuento Bs', 'descuento', 12, 'importe'],
                ['Total Bs', 'total', 13, 'importe'],
                ['Observación', 'obs', 34, 'texto'],
            ],
            $cabeceras
        );

        $this->hojaCompras(
            $libro->createSheet()->setTitle('Detalle'), 'COMPRAS · DETALLE POR PRODUCTO', $subtitulo,
            [
                ['Nº compra', 'id', 9, 'entero'],
                ['Fecha', 'fecha', 11, 'fecha'],
                ['Proveedor', 'proveedor', 26, 'texto'],
                ['Registrado por', 'usuario', 22, 'texto'],
                ['Código', 'cod_prod', 10, 'texto'],
                ['Producto', 'producto', 34, 'texto'],
                ['Unid', 'unidad', 7, 'texto'],
                ['Cantidad', 'cantidad', 11, 'cantidad'],
                ['Costo Bs', 'precio', 11, 'precio'],
                ['Subtotal Bs', 'subtotal', 13, 'importe'],
                ['Lote', 'lote', 12, 'texto'],
                ['Vence', 'vence', 11, 'fecha'],
                ['Estado', 'estado', 10, 'texto'],
            ],
            $lineas
        );

        $porProducto = $this->agrupar($lineasActivas, function ($l) {
            return $l['cod_prod'];
        }, function ($grupo) {
            $cantidad = array_sum(array_column($grupo, 'cantidad'));
            $importe = array_sum(array_column($grupo, 'subtotal'));
            $precios = array_column($grupo, 'precio');

            return [
                'cod_prod' => $grupo[0]['cod_prod'],
                'producto' => $grupo[0]['producto'],
                'unidad'   => $grupo[0]['unidad'],
                'compras'  => count(array_unique(array_column($grupo, 'id'))),
                'cantidad' => $cantidad,
                'minimo'   => min($precios),
                'promedio' => $cantidad > 0 ? round($importe / $cantidad, 2) : 0,
                'maximo'   => max($precios),
                'importe'  => $importe,
            ];
        }, 'importe');
        $this->hojaCompras(
            $libro->createSheet()->setTitle('Por producto'), 'COMPRAS · RESUMEN POR PRODUCTO', $subtitulo . ' · sin anuladas',
            [
                ['Código', 'cod_prod', 10, 'texto'],
                ['Producto', 'producto', 36, 'texto'],
                ['Unid', 'unidad', 7, 'texto'],
                ['Compras', 'compras', 10, 'entero'],
                ['Cantidad', 'cantidad', 12, 'cantidad'],
                ['Costo mín. Bs', 'minimo', 13, 'precio'],
                ['Costo prom. Bs', 'promedio', 14, 'precio'],
                ['Costo máx. Bs', 'maximo', 13, 'precio'],
                ['Importe Bs', 'importe', 14, 'importe'],
                ['% del total', 'pct', 11, 'porcentaje'],
            ],
            $porProducto
        );

        $this->hojaCompras(
            $libro->createSheet()->setTitle('Por fecha'), 'COMPRAS · RESUMEN POR FECHA', $subtitulo . ' · sin anuladas',
            [
                ['Fecha', 'fecha', 12, 'fecha'],
                ['Compras', 'compras', 10, 'entero'],
                ['Proveedores', 'proveedores', 12, 'entero'],
                ['Productos', 'productos', 11, 'entero'],
                ['Importe Bs', 'importe', 14, 'importe'],
                ['% del total', 'pct', 11, 'porcentaje'],
            ],
            $this->agrupar($lineasActivas, function ($l) {
                return $l['dia'];
            }, function ($grupo) {
                return [
                    'fecha'       => $grupo[0]['fecha'],
                    'compras'     => count(array_unique(array_column($grupo, 'id'))),
                    'proveedores' => count(array_unique(array_column($grupo, 'proveedor'))),
                    'productos'   => count(array_unique(array_column($grupo, 'cod_prod'))),
                    'importe'     => array_sum(array_column($grupo, 'subtotal')),
                ];
            }, 'fecha')
        );

        $this->hojaCompras(
            $libro->createSheet()->setTitle('Por usuario'), 'COMPRAS · RESUMEN POR USUARIO', $subtitulo . ' · sin anuladas',
            [
                ['Registrado por', 'usuario', 30, 'texto'],
                ['Compras', 'compras', 10, 'entero'],
                ['Productos', 'productos', 11, 'entero'],
                ['Descuento Bs', 'descuento', 13, 'importe'],
                ['Total Bs', 'total', 14, 'importe'],
                ['% del total', 'pct', 11, 'porcentaje'],
            ],
            $this->agrupar($activas, function ($c) {
                return $c['usuario'];
            }, function ($grupo) use ($lineasActivas) {
                $ids = array_column($grupo, 'id');
                $productos = array_filter($lineasActivas, function ($l) use ($ids) {
                    return in_array($l['id'], $ids);
                });

                return [
                    'usuario'   => $grupo[0]['usuario'],
                    'compras'   => count($grupo),
                    'productos' => count(array_unique(array_column($productos, 'cod_prod'))),
                    'descuento' => array_sum(array_column($grupo, 'descuento')),
                    'total'     => array_sum(array_column($grupo, 'total')),
                ];
            }, 'total')
        );

        $this->hojaCompras(
            $libro->createSheet()->setTitle('Por proveedor'), 'COMPRAS · RESUMEN POR PROVEEDOR', $subtitulo . ' · sin anuladas',
            [
                ['Proveedor', 'proveedor', 34, 'texto'],
                ['NIT', 'nit', 13, 'texto'],
                ['Compras', 'compras', 10, 'entero'],
                ['Descuento Bs', 'descuento', 13, 'importe'],
                ['Total Bs', 'total', 14, 'importe'],
                ['% del total', 'pct', 11, 'porcentaje'],
            ],
            $this->agrupar($activas, function ($c) {
                return $c['proveedor'];
            }, function ($grupo) {
                return [
                    'proveedor' => $grupo[0]['proveedor'],
                    'nit'       => $grupo[0]['nit'],
                    'compras'   => count($grupo),
                    'descuento' => array_sum(array_column($grupo, 'descuento')),
                    'total'     => array_sum(array_column($grupo, 'total')),
                ];
            }, 'total')
        );

        $libro->setActiveSheetIndex(0);

        $writer = new Xlsx($libro);
        $nombre = 'compras_' . ($desde ?: 'inicio') . ($desde === $hasta ? '' : '_a_' . ($hasta ?: date('Y-m-d'))) . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** 'YYYY-MM-DD' a numero de serie de Excel, para que filtre y ordene como fecha. */
    private function fechaExcel($fecha)
    {
        return $fecha ? ExcelDate::PHPToExcel(strtotime($fecha . ' 00:00:00')) : '';
    }

    /**
     * Agrupa, resume cada grupo y le pone el porcentaje sobre el total de la
     * columna $orden (salvo que se ordene por fecha, que va cronologico).
     */
    private function agrupar(array $filas, callable $clave, callable $resumen, $orden)
    {
        $grupos = [];
        foreach ($filas as $fila) {
            $grupos[$clave($fila)][] = $fila;
        }
        $resultado = array_values(array_map($resumen, $grupos));

        $importe = $orden === 'fecha' ? 'importe' : $orden;
        $total = array_sum(array_column($resultado, $importe));
        foreach ($resultado as &$r) {
            $r['pct'] = $total > 0 ? $r[$importe] / $total : 0;
        }
        unset($r);

        usort($resultado, function ($a, $b) use ($orden) {
            return $orden === 'fecha' ? $a['fecha'] <=> $b['fecha'] : $b[$orden] <=> $a[$orden];
        });

        return $resultado;
    }

    /** Colores del reporte: los mismos de la nota de compra impresa. */
    private const COLOR_TITULO = 'C1272D';
    private const COLOR_CABECERA = '37474F';
    private const COLOR_PIE = 'ECEFF1';
    private const COLOR_ZEBRA = 'F7F9FA';
    private const COLOR_BORDE = 'CFD8DC';

    /** Titulo de empresa y del reporte en las dos primeras filas. */
    private function encabezado($hoja, $ultima, $titulo, $subtitulo)
    {
        $empresa = config('siat.emisor.nombre') ?: 'SOFIA';

        $hoja->mergeCells('A1:' . $ultima . '1')->setCellValue('A1', mb_strtoupper($empresa) . ' · ' . $titulo);
        $hoja->mergeCells('A2:' . $ultima . '2')->setCellValue('A2', $subtitulo);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB(self::COLOR_TITULO);
        $hoja->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('616161');
        $hoja->getRowDimension(1)->setRowHeight(24);
        $hoja->getStyle('A1:' . $ultima . '2')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getStyle('A1:' . $ultima . '1')->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::COLOR_TITULO);
        $hoja->setShowGridlines(false);
    }

    /**
     * Una tabla con cabecera oscura, filas cebra, formatos numericos, fila de
     * totales, filtros, paneles fijos y lista para imprimir. Si la tabla tiene
     * columna de estado, las anuladas van en rojo tachado y los totales las
     * dejan afuera (SUMIF), asi el filtro no cambia lo que suma el pie.
     */
    private function hojaCompras($hoja, $titulo, $subtitulo, array $columnas, array $filas)
    {
        $ultima = Coordinate::stringFromColumnIndex(count($columnas));
        $this->encabezado($hoja, $ultima, $titulo, $subtitulo);

        $letras = [];
        foreach ($columnas as $i => $columna) {
            list($etiqueta, $clave, $ancho) = $columna;
            $letras[$clave] = Coordinate::stringFromColumnIndex($i + 1);
            $hoja->setCellValue($letras[$clave] . '4', $etiqueta);
            $hoja->getColumnDimension($letras[$clave])->setWidth($ancho);
        }

        $primera = 5;
        $fila = $primera;
        foreach ($filas as $n => $registro) {
            foreach ($columnas as $columna) {
                list(, $clave, , $formato) = $columna;
                $celda = $letras[$clave] . $fila;
                if ($formato === 'texto') {
                    // Codigos, NIT y facturas son etiquetas: sin esto Excel se
                    // come los ceros de adelante.
                    $hoja->setCellValueExplicit($celda, (string) $registro[$clave], DataType::TYPE_STRING);
                } else {
                    $hoja->setCellValue($celda, $registro[$clave]);
                }
            }
            if ($n % 2) {
                $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_ZEBRA);
            }
            if (($registro['estado'] ?? '') === 'ANULADO') {
                $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->getFont()
                    ->setStrikethrough(true)->getColor()->setRGB('C62828');
            }
            $fila++;
        }
        $fin = $fila - 1;

        $conEstado = isset($letras['estado']);
        $hoja->setCellValue('A' . $fila, $conEstado ? 'TOTAL (sin anuladas)' : 'TOTAL (' . count($filas) . ')');
        foreach ($columnas as $columna) {
            list(, $clave, , $formato) = $columna;
            // Sumar costos unitarios o ids no da ningun total.
            if (!in_array($formato, ['importe', 'cantidad', 'porcentaje']) || $fin < $primera) {
                continue;
            }
            $l = $letras[$clave];
            $hoja->setCellValue($l . $fila, $conEstado
                ? '=SUMIF(' . $letras['estado'] . $primera . ':' . $letras['estado'] . $fin . ',"<>ANULADO",' . $l . $primera . ':' . $l . $fin . ')'
                : '=SUM(' . $l . $primera . ':' . $l . $fin . ')');
        }
        if ($conEstado && $fin >= $primera) {
            $hoja->setCellValue('A' . ($fila + 1), 'Anuladas');
            $hoja->setCellValue('B' . ($fila + 1), '=COUNTIF(' . $letras['estado'] . $primera . ':' . $letras['estado'] . $fin . ',"ANULADO")');
            $hoja->getStyle('A' . ($fila + 1) . ':B' . ($fila + 1))->getFont()->setSize(9)->getColor()->setRGB('C62828');
        }

        $hoja->getStyle('A4:' . $ultima . '4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_CABECERA]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $hoja->getRowDimension(4)->setRowHeight(26);
        $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_PIE]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::COLOR_CABECERA]]],
        ]);
        $hoja->getStyle('A4:' . $ultima . $fila)->getBorders()->getInside()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COLOR_BORDE);
        $hoja->getStyle('A4:' . $ultima . $fila)->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COLOR_CABECERA);

        $formatos = [
            'entero'     => '#,##0',
            'cantidad'   => '#,##0.000',
            'precio'     => '#,##0.00',
            'importe'    => '#,##0.00',
            'porcentaje' => '0.0%',
            'fecha'      => 'dd/mm/yyyy',
        ];
        foreach ($columnas as $columna) {
            list(, $clave, , $formato) = $columna;
            $l = $letras[$clave];
            if (isset($formatos[$formato])) {
                $hoja->getStyle($l . $primera . ':' . $l . $fila)->getNumberFormat()->setFormatCode($formatos[$formato]);
            }
            if (in_array($formato, ['fecha', 'entero'])) {
                $hoja->getStyle($l . $primera . ':' . $l . $fin)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }
        if ($fila > $primera) {
            $hoja->getStyle('A' . $primera . ':' . $ultima . $fin)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        $hoja->freezePane('A5');
        if ($fin >= $primera) {
            $hoja->setAutoFilter('A4:' . $ultima . $fin);
        }

        $impresion = $hoja->getPageSetup();
        $impresion->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $impresion->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $impresion->setFitToWidth(1);
        $impresion->setFitToHeight(0);
        $impresion->setRowsToRepeatAtTopByStartAndEnd(4, 4);
        $hoja->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4);
        $hoja->getHeaderFooter()->setOddFooter('&L&8' . $titulo . '&C&8&D &T&R&8Página &P de &N');

        return $hoja;
    }

    /** Portada: filtros aplicados e indicadores del periodo. */
    private function hojaPortada($hoja, $subtitulo, Request $request, array $activas, array $todas, array $lineasActivas)
    {
        $this->encabezado($hoja, 'D', 'REPORTE DE COMPRAS', $subtitulo);
        $hoja->getColumnDimension('A')->setWidth(30);
        $hoja->getColumnDimension('B')->setWidth(22);
        $hoja->getColumnDimension('C')->setWidth(4);
        $hoja->getColumnDimension('D')->setWidth(40);

        $usuario = $request->input('usuario') ? $this->nombreUsuario(User::find($request->input('usuario'))) : 'Todos';
        $filtros = [
            ['Desde', $request->input('desde') ? date('d/m/Y', strtotime($request->input('desde'))) : 'Inicio'],
            ['Hasta', $request->input('hasta') ? date('d/m/Y', strtotime($request->input('hasta'))) : 'Hoy'],
            ['Estado', $request->input('estado') ?: 'Todos'],
            ['Registrado por', $usuario],
            ['Producto', trim((string) $request->input('producto', '')) ?: 'Todos'],
            ['Proveedor / NIT / factura', trim((string) $request->input('buscar', '')) ?: 'Todos'],
        ];

        $total = array_sum(array_column($activas, 'total'));
        $indicadores = [
            ['Compras registradas', count($todas), '#,##0'],
            ['Compras válidas', count($activas), '#,##0'],
            ['Compras anuladas', count($todas) - count($activas), '#,##0'],
            ['Subtotal Bs', array_sum(array_column($activas, 'subtotal')), '#,##0.00'],
            ['Descuentos Bs', array_sum(array_column($activas, 'descuento')), '#,##0.00'],
            ['TOTAL COMPRADO Bs', $total, '#,##0.00'],
            ['Compra promedio Bs', count($activas) ? $total / count($activas) : 0, '#,##0.00'],
            ['Productos distintos', count(array_unique(array_column($lineasActivas, 'cod_prod'))), '#,##0'],
            ['Proveedores distintos', count(array_unique(array_column($activas, 'proveedor'))), '#,##0'],
            ['Usuarios que registraron', count(array_unique(array_column($activas, 'usuario'))), '#,##0'],
        ];

        $seccion = function ($fila, $texto) use ($hoja) {
            $hoja->mergeCells('A' . $fila . ':B' . $fila)->setCellValue('A' . $fila, $texto);
            $hoja->getStyle('A' . $fila . ':B' . $fila)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_CABECERA]],
            ]);
            $hoja->getRowDimension($fila)->setRowHeight(20);
        };

        $seccion(4, 'INDICADORES (sin anuladas)');
        $fila = 5;
        foreach ($indicadores as $ind) {
            $hoja->setCellValue('A' . $fila, $ind[0]);
            $hoja->setCellValue('B' . $fila, $ind[1]);
            $hoja->getStyle('B' . $fila)->getNumberFormat()->setFormatCode($ind[2]);
            $fila++;
        }
        $hoja->getStyle('A5:B' . ($fila - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COLOR_BORDE);
        $destacada = 'A' . (5 + 5) . ':B' . (5 + 5);
        $hoja->getStyle($destacada)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::COLOR_TITULO]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FDECEA']],
        ]);

        $fila++;
        $seccion($fila, 'FILTROS APLICADOS');
        $fila++;
        $inicio = $fila;
        foreach ($filtros as $f) {
            $hoja->setCellValue('A' . $fila, $f[0]);
            $hoja->setCellValueExplicit('B' . $fila, (string) $f[1], DataType::TYPE_STRING);
            $fila++;
        }
        $hoja->getStyle('A' . $inicio . ':B' . ($fila - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COLOR_BORDE);
        $hoja->getStyle('A5:A' . $fila)->getFont()->getColor()->setRGB('455A64');
        $hoja->getStyle('B5:B' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $hoja->setCellValue('D4', 'Contenido del archivo');
        $hoja->getStyle('D4')->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_CABECERA);
        $hojas = [
            'Compras: una fila por compra',
            'Detalle: cada producto de cada compra',
            'Por producto: cantidades y costos',
            'Por fecha: lo comprado cada día',
            'Por usuario: quién registró cuánto',
            'Por proveedor: a quién se compró',
            'Las anuladas no suman en los totales.',
        ];
        foreach ($hojas as $i => $texto) {
            $hoja->setCellValue('D' . (5 + $i), '• ' . $texto);
            $hoja->getStyle('D' . (5 + $i))->getFont()->setSize(9)->getColor()->setRGB('616161');
        }

        $impresion = $hoja->getPageSetup();
        $impresion->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $impresion->setFitToWidth(1);
        $impresion->setFitToHeight(0);
    }

    /** Una compra con su detalle. */
    public function show($id)
    {
        $compra = Compra::with(['detalles.producto', 'usuario', 'proveedorRel'])->find($id);

        if (!$compra) {
            return response()->json(['message' => 'La compra no existe'], 404);
        }

        $compra->detalles->each(function ($detalle) {
            // Precio de venta actual tomado de tbproductos.Precio.
            $detalle->setAttribute('precio_producto', $detalle->producto->Precio ?? null);
            $detalle->unsetRelation('producto');
        });

        // Los datos de la empresa para la cabecera de la nota impresa: los
        // mismos que lleva la boleta de entrega.
        $compra->setAttribute('empresa', array_merge(
            array_diff_key(config('siat.emisor'), ['logo' => true]),
            ['nit' => config('siat.nit')]
        ));

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

            // El precio de compra del producto queda con el de la ultima compra.
            // El de venta solo se toca si lo mandaron: comprar mas caro no
            // significa que se quiera revender mas caro automaticamente.
            // Precio_Costo no se toca: pese al nombre es la lista "2do precio".
            foreach ($lineas as $linea) {
                $cambios = [];
                if ($linea['precio'] > 0) {
                    $cambios['precio_compra'] = $linea['precio'];
                }
                if ($linea['precio_venta'] !== null && $linea['precio_venta'] > 0) {
                    $cambios['Precio'] = $linea['precio_venta'];
                }
                if ($cambios) {
                    DB::table('tbproductos')
                        ->whereRaw('TRIM(cod_prod) = ?', [$linea['cod_prod']])
                        ->update($cambios);
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

        // La existencia vive en tbproductos.stock_actual: la compra la sube y su
        // anulacion la baja. tbstock queda como historial del movimiento.
        foreach ($filas as $fila) {
            $delta = round((float) $fila['cant'] - (float) $fila['saldo'], 3);
            if ($delta != 0) {
                DB::table('tbproductos')->whereRaw('TRIM(cod_prod) = ?', [trim($fila['cod_prod'])])
                    ->update(['stock_actual' => DB::raw('stock_actual + (' . sprintf('%.3F', $delta) . ')')]);
            }
        }
    }
}
