<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditoController extends Controller
{
    public function __construct()
    {
        $vendedor = ['clientesVendedor', 'deudasVendedor', 'cobrarVendedor'];
        $this->middleware('permission:cobranzasrecojo')->except($vendedor);
        $this->middleware('permission:cobranza')->only($vendedor);
    }

    public function clientesVendedor(Request $request)
    {
        // Se cobra a cualquier cliente con deuda, incluso inactivo o de otro preventista.
        $clientes = DB::table('tbclientes')->orderBy('Nombres')
            ->get(['Cod_Aut as id', 'Nombres as nombre', 'Id as nit']);
        $deudas = $this->deudas(null)->whereIn('cliente_id', $clientes->pluck('id')->all())->groupBy('cliente_id');
        return $clientes->map(function ($cliente) use ($deudas) {
            $cliente->saldo = round(($deudas->get($cliente->id) ?? collect())->sum('saldo'), 2);
            return $cliente;
        })->where('saldo', '>', 0)->values();
    }

    public function deudasVendedor(Request $request, $cliente)
    {
        abort_unless(DB::table('tbclientes')->where('Cod_Aut', $cliente)->exists(), 404, 'El cliente no existe.');
        return $this->deudas((int) $cliente)->where('saldo', '>', 0)->sortBy('fecha')->values();
    }

    /** Lote atomico: un error no deja cobros guardados a medias. */
    public function cobrarVendedor(Request $request, $cliente)
    {
        $datos = $request->validate([
            'cobros' => 'required|array|min:1|max:100',
            'cobros.*.origen' => 'required|in:factura,manual',
            'cobros.*.id' => 'required|integer|min:1',
            'cobros.*.monto' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'cobros.*.referencia' => 'nullable|string|max:100',
            'cobros.*.solicitud_id' => 'required|uuid|distinct',
        ]);
        return DB::transaction(function () use ($request, $cliente, $datos) {
            abort_unless(DB::table('tbclientes')->where('Cod_Aut', $cliente)->lockForUpdate()->first(), 404, 'El cliente no existe.');
            $cobros = collect($datos['cobros'])->sortBy(function ($c) { return $c['origen'] . ':' . $c['id']; });
            $claves = $cobros->map(function ($c) { return $c['origen'] . ':' . $c['id']; });
            abort_if($claves->unique()->count() !== $claves->count(), 422, 'Hay deudas repetidas en el cobro.');
            foreach ($cobros as $cobro) {
                $deuda = DB::table($cobro['origen'] === 'factura' ? 'facturas' : 'creditos_manuales')
                    ->where('id', $cobro['id'])->lockForUpdate()->first();
                abort_unless($deuda && (int) $deuda->cliente_id === (int) $cliente, 403, 'La deuda no pertenece al cliente.');
                $abono = Request::create('/', 'POST', [
                    'monto' => $cobro['monto'], 'referencia' => $cobro['referencia'] ?? null,
                    'solicitud_id' => $cobro['solicitud_id'], 'forma_pago' => 'EFECTIVO',
                ]);
                $abono->setUserResolver(function () use ($request) { return $request->user(); });
                $this->abonar($abono, $cobro['origen'], $cobro['id']);
            }
            return response()->json(['message' => 'Cobros registrados. Cobranzas ya puede consultarlos.']);
        });
    }

    public function clientes(Request $request)
    {
        $datos = $request->validate(['buscar' => 'required|string|min:2|max:100']);
        return DB::table('tbclientes')->where(function ($q) use ($datos) {
            $q->where('Nombres', 'like', '%'.$datos['buscar'].'%')
                ->orWhere('Id', 'like', '%'.$datos['buscar'].'%');
        })->orderBy('Nombres')->limit(30)->get(['Cod_Aut as id', 'Nombres as nombre', 'Id as nit']);
    }

    public function index(Request $request)
    {
        $datos = $request->validate(['cliente_id' => 'nullable|integer|exists:tbclientes,Cod_Aut']);
        $filas = $this->deudas($datos['cliente_id'] ?? null);
        return ['deudas' => $filas->sortByDesc('fecha')->values(), 'saldo' => round($filas->sum('saldo'), 2)];
    }

    /**
     * Todos los clientes con lo que deben, para la lista principal de
     * cobranzas: los datos del cliente y una columna con su deuda. Son unos
     * pocos miles, asi que va todo de una vez y la pantalla filtra y busca.
     */
    public function resumen()
    {
        $porCliente = $this->deudas(null)->groupBy('cliente_id');

        $vendedores = DB::table('personal')->whereRaw("TRIM(COALESCE(ci, '')) <> ''")
            ->get(['ci', 'Nombre1', 'App1'])
            ->mapWithKeys(function ($p) {
                return [trim($p->ci) => trim(trim((string) $p->Nombre1) . ' ' . trim((string) $p->App1))];
            });

        $clientes = DB::table('tbclientes')->orderBy('Nombres')
            ->get(['Cod_Aut', 'Id', 'Nombres', 'Telf', 'Direccion', 'zona', 'CiVend', 'Canal', 'venta'])
            ->map(function ($c) use ($porCliente, $vendedores) {
                $deudas = $porCliente->get($c->Cod_Aut) ?? collect();
                $pendientes = $deudas->where('saldo', '>', 0);
                $desde = $pendientes->min('fecha');

                return [
                    'id' => (int) $c->Cod_Aut,
                    'nombre' => trim((string) $c->Nombres),
                    'nit' => trim((string) $c->Id),
                    'telefono' => trim((string) $c->Telf),
                    'direccion' => trim((string) $c->Direccion),
                    'zona' => trim((string) $c->zona),
                    'canal' => trim((string) $c->Canal),
                    'vendedor' => $vendedores->get(trim((string) $c->CiVend), ''),
                    'activo' => strtoupper(trim((string) $c->venta)) !== 'INACTIVO',
                    'saldo' => round($pendientes->sum('saldo'), 2),
                    'deudas' => $pendientes->count(),
                    // Desde cuando debe: la deuda pendiente mas vieja.
                    'desde' => $desde ? substr((string) $desde, 0, 10) : null,
                    'dias' => $desde ? (int) floor((time() - strtotime(substr((string) $desde, 0, 10))) / 86400) : null,
                ];
            });

        $conDeuda = $clientes->where('saldo', '>', 0);

        return [
            'clientes' => $clientes->values(),
            'totales' => [
                'clientes' => $clientes->count(),
                'con_deuda' => $conDeuda->count(),
                'saldo' => round($conDeuda->sum('saldo'), 2),
                'deudas' => $conDeuda->sum('deudas'),
            ],
        ];
    }

    /**
     * "Cuentas por cobrar debito sumado": el mismo formato del reporte del
     * sistema anterior (COMANDA ... DIAS), una fila por deuda pendiente. Solo
     * deudores. Lleva autofiltro y una fila de totales con SUBTOTAL, que suma
     * solo lo que queda visible al filtrar.
     */
    public function excelDeudores()
    {
        $vendedores = DB::table('personal')->whereRaw("TRIM(COALESCE(ci, '')) <> ''")
            ->get(['ci', 'Nombre1', 'App1'])
            ->mapWithKeys(function ($p) {
                return [trim($p->ci) => trim(trim((string) $p->Nombre1) . ' ' . trim((string) $p->App1))];
            });
        $clientes = DB::table('tbclientes')->get(['Cod_Aut', 'Nombres', 'CiVend'])->keyBy('Cod_Aut');

        // Fecha de pago = el ultimo abono que vale; si no hubo, la que traia el saldo.
        $ultimosAbonos = DB::table('creditos_abonos')->whereNull('anulado_at')
            ->select('origen', 'deuda_id', DB::raw('MAX(created_at) as ultimo'))
            ->groupBy('origen', 'deuda_id')->get()
            ->mapWithKeys(function ($a) { return [$a->origen . ':' . $a->deuda_id => $a->ultimo]; });

        $filas = $this->deudas(null)->where('saldo', '>', 0)->map(function ($d) use ($clientes, $vendedores, $ultimosAbonos) {
            $cliente = $clientes->get($d->cliente_id);
            $esSaldo = $d->origen === 'manual' && !empty($d->comanda);
            $fechaPago = $ultimosAbonos->get($d->clave)
                ?: ($esSaldo && $d->ultimo_pago ? $d->ultimo_pago : $d->fecha);

            return [
                'comanda' => $esSaldo ? (int) $d->comanda
                    : ($d->origen === 'factura' ? (int) ($d->pedido_nro ?: $d->id) : 'M' . $d->id),
                'cliente' => trim((string) ($cliente->Nombres ?? $d->cliente)),
                'empresa' => $esSaldo ? (string) $d->empresa : '',
                // Del saldo anterior se respeta su importe y lo que ya traia a cuenta.
                'importe' => $esSaldo && $d->importe !== null ? (float) $d->importe : (float) $d->monto,
                'a_cuenta' => $esSaldo && $d->importe !== null
                    ? round((float) $d->importe - $d->saldo, 2) : round((float) $d->pagado, 2),
                'deuda' => (float) $d->saldo,
                'fecha_pago' => $fechaPago,
                'vendedor' => $esSaldo && $d->vendedor
                    ? $d->vendedor
                    : ($cliente ? $vendedores->get(trim((string) $cliente->CiVend), '') : ''),
            ];
        })->sortBy('comanda')->values();

        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Hoja1');

        $hoja->setCellValue('B1', 'C U E N T A S  P O R   C O B R A R  D E B I T O  S U M A D O');
        $hoja->mergeCells('B1:J1');
        $hoja->getStyle('B1')->getFont()->setBold(true)->setSize(14);
        $hoja->getStyle('B1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $hoja->setCellValue('B2', 'Al ' . date('d/m/Y H:i'));

        $hoja->fromArray(['COMANDA', 'NOMBRE DE CLIENTE', 'EMPRESA', 'IMPORTE', 'A CUENTA', 'DEUDA',
            'FECHA DE PAGO', 'VENDEDOR', 'DIAS'], null, 'B3');

        $hoy = new \DateTime(date('Y-m-d'));
        $fila = 4;
        foreach ($filas as $f) {
            $fecha = $f['fecha_pago'] ? new \DateTime((string) $f['fecha_pago']) : null;
            $hoja->setCellValue('B' . $fila, $f['comanda']);
            $hoja->setCellValueExplicit('C' . $fila, $f['cliente'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $hoja->setCellValueExplicit('D' . $fila, $f['empresa'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $hoja->setCellValue('E' . $fila, $f['importe']);
            $hoja->setCellValue('F' . $fila, $f['a_cuenta']);
            $hoja->setCellValue('G' . $fila, $f['deuda']);
            if ($fecha) {
                $hoja->setCellValue('H' . $fila, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($fecha));
                $hoja->setCellValue('J' . $fila, (int) (new \DateTime($fecha->format('Y-m-d')))->diff($hoy)->format('%r%a'));
            }
            $hoja->setCellValueExplicit('I' . $fila, $f['vendedor'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $fila++;
        }
        $ultima = max($fila - 1, 3);

        // Totales: SUBTOTAL(9) suma solo las filas visibles con el filtro puesto.
        $total = $ultima + 1;
        $hoja->setCellValue('C' . $total, 'TOTAL');
        foreach (['E', 'F', 'G'] as $col) {
            $hoja->setCellValue($col . $total, $ultima >= 4 ? "=SUBTOTAL(9,{$col}4:{$col}{$ultima})" : 0);
        }
        $hoja->setCellValue('B' . $total, $ultima >= 4 ? "=SUBTOTAL(3,B4:B{$ultima})" : 0);

        $hoja->getStyle('B3:J3')->getFont()->setBold(true);
        $hoja->getStyle('B4:B' . $ultima)->getFont()->setBold(true);
        $hoja->getStyle("B{$total}:J{$total}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE4D6']],
            'borders' => ['top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE]],
        ]);
        $hoja->getStyle("E4:G{$total}")->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle("H4:H{$ultima}")->getNumberFormat()->setFormatCode('d/m/yyyy h:mm');
        $hoja->setAutoFilter("B3:J{$ultima}");
        $hoja->freezePane('B4');

        foreach (['A' => 8.71, 'B' => 9.71, 'C' => 40.71, 'D' => 25.71, 'E' => 12.71, 'F' => 12.71,
            'G' => 12.71, 'H' => 15.71, 'I' => 32.71, 'J' => 8.71] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }

        $nombre = 'CUENTAS POR COBRAR ' . date('Y-m-d') . '.xlsx';
        return response()->streamDownload(function () use ($libro) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save('php://output');
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Cobros del rango (formato de la hoja de deposito de cobranzas): por cada
     * dia un titulo "DEPOSITO dd/mm/yy" y un bloque por deposito -los cobros
     * con la misma forma de pago y boleta- con vendedor, cliente, pago,
     * comanda y si era factura, y el total del bloque. Al pie, quien lo saco.
     * Los cobros anulados no entran.
     */
    public function excelCobros(Request $request)
    {
        $datos = $request->validate([
            'desde' => 'nullable|date_format:Y-m-d',
            'hasta' => 'nullable|date_format:Y-m-d',
        ]);
        $desde = $datos['desde'] ?? date('Y-m-d');
        $hasta = $datos['hasta'] ?? $desde;

        $abonos = DB::table('creditos_abonos')->whereNull('anulado_at')
            ->whereDate('created_at', '>=', $desde)->whereDate('created_at', '<=', $hasta)
            ->orderBy('created_at')->orderBy('id')
            ->get(['id', 'origen', 'deuda_id', 'cliente_id', 'monto', 'monto_efectivo', 'monto_qr', 'forma_pago', 'referencia', 'created_at']);

        // De la deuda salen la comanda, el vendedor y si era factura, con el
        // mismo criterio que el Excel de deudores.
        $deudas = $this->deudas(null)->keyBy('clave');
        $vendedores = DB::table('personal')->whereRaw("TRIM(COALESCE(ci, '')) <> ''")
            ->get(['ci', 'Nombre1', 'App1'])
            ->mapWithKeys(function ($p) {
                return [trim($p->ci) => trim(trim((string) $p->Nombre1) . ' ' . trim((string) $p->App1))];
            });
        $clientes = DB::table('tbclientes')->whereIn('Cod_Aut', $abonos->pluck('cliente_id')->unique()->all())
            ->get(['Cod_Aut', 'Nombres', 'CiVend'])->keyBy('Cod_Aut');

        $filas = $abonos->map(function ($a) use ($deudas, $vendedores, $clientes) {
            $d = $deudas->get($a->origen . ':' . $a->deuda_id);
            $cliente = $clientes->get($a->cliente_id);
            $esSaldo = $d && $d->origen === 'manual' && !empty($d->comanda);
            if ($esSaldo) {
                $comanda = (int) $d->comanda;
            } elseif ($a->origen === 'factura') {
                $comanda = (int) (($d->pedido_nro ?? null) ?: $a->deuda_id);
            } else {
                $comanda = 'M' . $a->deuda_id;
            }
            return [
                'dia' => substr((string) $a->created_at, 0, 10),
                'deposito' => trim(strtoupper((string) $a->forma_pago) . ' ' . trim((string) $a->referencia)),
                'vendedor' => $esSaldo && $d->vendedor
                    ? $d->vendedor
                    : ($cliente ? $vendedores->get(trim((string) $cliente->CiVend), '') : ''),
                'cliente' => trim((string) ($cliente->Nombres ?? ($d->cliente ?? ''))),
                'pago' => (float) $a->monto,
                'efectivo' => (float) $a->monto_efectivo,
                'qr' => (float) $a->monto_qr,
                'comanda' => $comanda,
                'factura' => $a->origen === 'factura' && $d && ($d->tipo_comprobante ?? '') === 'FACTURA' ? 'SI' : 'NO',
            ];
        });

        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Cobros');
        $borde = ['borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]];

        $fila = 1;
        foreach ($filas->groupBy('dia') as $dia => $delDia) {
            $hoja->setCellValue('B' . $fila, 'DEPOSITO ' . date('d/m/y', strtotime($dia)));
            $hoja->getStyle('B' . $fila)->getFont()->setBold(true);
            $fila += 2;

            foreach ($delDia->groupBy('deposito') as $deposito => $grupo) {
                $hoja->fromArray(['vendedor', 'cliente', 'pago', 'efectivo', 'qr', 'comanda', 'factura'], null, 'A' . $fila);
                $hoja->getStyle("A{$fila}:G{$fila}")->applyFromArray($borde);
                $desdeFila = ++$fila;
                foreach ($grupo as $f) {
                    $hoja->setCellValueExplicit('A' . $fila, $f['vendedor'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $hoja->setCellValueExplicit('B' . $fila, $f['cliente'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $hoja->setCellValue('C' . $fila, $f['pago']);
                    $hoja->setCellValue('D' . $fila, $f['efectivo']);
                    $hoja->setCellValue('E' . $fila, $f['qr']);
                    $hoja->setCellValue('F' . $fila, $f['comanda']);
                    $hoja->setCellValue('G' . $fila, $f['factura']);
                    $fila++;
                }
                $hoja->getStyle('A' . $desdeFila . ':G' . ($fila - 1))->applyFromArray($borde);
                // Total del deposito bajo pago, efectivo y qr y, al lado, la boleta.
                foreach (['C', 'D', 'E'] as $col) {
                    $hoja->setCellValue($col . $fila, '=SUM(' . $col . $desdeFila . ':' . $col . ($fila - 1) . ')');
                    $hoja->getStyle($col . $fila)->applyFromArray($borde)->getFont()->setBold(true);
                }
                $hoja->setCellValue('G' . $fila, $deposito);
                $fila += 3;
            }
        }

        if ($filas->isEmpty()) {
            $hoja->setCellValue('B1', 'Sin cobros del ' . $desde . ' al ' . $hasta);
            $fila = 3;
        } else {
            // Total de todo el rango, por si se sacan varios dias juntos.
            $hoja->setCellValue('B' . $fila, 'TOTAL COBRADO');
            $hoja->setCellValue('C' . $fila, round($filas->sum('pago'), 2));
            $hoja->setCellValue('D' . $fila, round($filas->sum('efectivo'), 2));
            $hoja->setCellValue('E' . $fila, round($filas->sum('qr'), 2));
            $hoja->getStyle("B{$fila}:E{$fila}")->getFont()->setBold(true);
            $hoja->setCellValue('C' . ($fila - 1), 'pago');
            $hoja->setCellValue('D' . ($fila - 1), 'efectivo');
            $hoja->setCellValue('E' . ($fila - 1), 'qr');
            $fila += 2;
        }
        $usuario = $request->user();
        $hoja->setCellValue('A' . $fila, 'ELABORADO POR ' . strtoupper(trim(($usuario->Nombre1 ?? '') . ' ' . ($usuario->App1 ?? ''))));

        $hoja->getStyle('C1:E' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (['A' => 32, 'B' => 40, 'C' => 12, 'D' => 12, 'E' => 12, 'F' => 12, 'G' => 18] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
        $hoja->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)->setFitToHeight(0);

        $nombre = 'COBROS ' . $desde . ($hasta !== $desde ? ' AL ' . $hasta : '') . '.xlsx';
        return response()->streamDownload(function () use ($libro) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save('php://output');
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * El detalle de un cliente: sus datos, todas sus deudas (pendientes y
     * pagadas) y las ventas que se le hicieron a credito con sus productos.
     */
    public function detalle($id)
    {
        $c = DB::table('tbclientes')->where('Cod_Aut', $id)->first();
        abort_unless($c, 404, 'El cliente no existe');

        $vendedor = DB::table('personal')->whereRaw('TRIM(ci) = ?', [trim((string) $c->CiVend)])->first(['Nombre1', 'App1']);
        $deudas = $this->deudas((int) $id)->sortByDesc('fecha')->values();

        $abonos = DB::table('creditos_abonos')->where('origen', 'factura')->whereNull('anulado_at')
            ->select('deuda_id', DB::raw('SUM(monto) as pagado'))->groupBy('deuda_id')->pluck('pagado', 'deuda_id');

        $ventas = DB::table('facturas')->where('cliente_id', $id)
            ->whereIn('tipo_pago', ['CRÉDITO', 'CREDITO'])->whereNull('deleted_at')
            ->orderByDesc('fecha')->orderByDesc('id')
            ->get(['id', 'fecha', 'hora', 'tipo_comprobante', 'total', 'estado', 'pedido_nro', 'pedido_tipo', 'observacion']);
        $detalles = DB::table('factura_detalles')->whereIn('factura_id', $ventas->pluck('id'))->whereNull('deleted_at')
            ->orderBy('id')->get(['factura_id', 'cod_prod', 'nombre', 'unidad', 'cantidad', 'peso', 'precio', 'subtotal'])
            ->groupBy('factura_id');

        $ventas = $ventas->map(function ($v) use ($abonos, $detalles) {
            $total = (float) $v->total;
            $pagado = (float) ($abonos[$v->id] ?? 0);
            $activa = $v->estado === 'ACTIVO';
            return [
                'id' => $v->id,
                'fecha' => substr((string) $v->fecha, 0, 10),
                'hora' => substr((string) $v->hora, 0, 5),
                'comprobante' => $v->tipo_comprobante,
                'pedido' => $v->pedido_nro,
                'total' => $total,
                'pagado' => $pagado,
                'saldo' => $activa ? max(0, round($total - $pagado, 2)) : 0,
                'estado' => !$activa ? 'ANULADA' : ($total - $pagado > 0.009 ? 'PENDIENTE' : 'PAGADA'),
                'observacion' => $v->observacion,
                'productos' => ($detalles->get($v->id) ?? collect())->map(function ($d) {
                    return [
                        'cod_prod' => trim((string) $d->cod_prod),
                        'nombre' => trim((string) $d->nombre),
                        'unidad' => trim((string) $d->unidad),
                        'cantidad' => (float) $d->cantidad,
                        'peso' => (float) $d->peso,
                        'precio' => (float) $d->precio,
                        'subtotal' => (float) $d->subtotal,
                    ];
                })->values(),
            ];
        });

        return [
            'cliente' => [
                'id' => (int) $c->Cod_Aut,
                'nombre' => trim((string) $c->Nombres),
                'nit' => trim((string) $c->Id),
                'telefono' => trim((string) $c->Telf),
                'direccion' => trim((string) $c->Direccion),
                'zona' => trim((string) $c->zona),
                'territorio' => trim((string) ($c->territorio ?? '')),
                'canal' => trim((string) $c->Canal),
                'vendedor' => $vendedor ? trim(trim((string) $vendedor->Nombre1) . ' ' . trim((string) $vendedor->App1)) : '',
                'latitud' => $c->Latitud,
                'longitud' => $c->longitud,
            ],
            'deudas' => $deudas,
            // Los anulados siguen a la vista, con quien los dio de baja y por
            // que: es la unica forma de explicar despues una plata mal anotada.
            'abonos' => DB::table('creditos_abonos as a')
                ->leftJoin('personal as p', 'p.CodAut', '=', 'a.user_id')
                ->leftJoin('personal as anu', 'anu.CodAut', '=', 'a.anulado_por')
                ->where('a.cliente_id', $id)->orderByDesc('a.created_at')->orderByDesc('a.id')
                ->get(['a.id', 'a.origen', 'a.deuda_id', 'a.monto', 'a.monto_efectivo', 'a.monto_qr', 'a.forma_pago', 'a.referencia', 'a.created_at',
                    'a.anulado_at', 'a.motivo_anulacion',
                    DB::raw("TRIM(CONCAT(COALESCE(p.Nombre1, ''), ' ', COALESCE(p.App1, ''))) as cobrador"),
                    DB::raw("TRIM(CONCAT(COALESCE(anu.Nombre1, ''), ' ', COALESCE(anu.App1, ''))) as anulado_por")]),
            'ventas' => $ventas->values(),
            'totales' => [
                'saldo' => round($deudas->sum('saldo'), 2),
                'deudas' => $deudas->where('saldo', '>', 0)->count(),
                'abonado' => round($deudas->sum('pagado'), 2),
                'ventas' => $ventas->count(),
                'vendido' => round($ventas->where('estado', '<>', 'ANULADA')->sum('total'), 2),
            ],
        ];
    }

    /**
     * Las deudas de un cliente, o de todos: los comprobantes de facturacion
     * emitidos a credito y las deudas agregadas a mano, con lo abonado a cada una.
     */
    private function deudas($cliente)
    {
        // Lo anulado no cuenta: el abono queda anotado pero la deuda vuelve a
        // deber lo que se le habia cobrado por equivocacion.
        $abonos = DB::table('creditos_abonos')->where('origen', 'factura')
            ->whereNull('anulado_at')
            ->select('deuda_id', DB::raw('SUM(monto) as pagado'))
            ->groupBy('deuda_id')->pluck('pagado', 'deuda_id');

        // Solo lo que sale de facturacion: los comprobantes emitidos a credito
        // y lo que se les fue abonando. Las notas de caja no entran aca.
        $facturas = DB::table('facturas as f')->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'f.cliente_id')
            ->whereIn('f.tipo_pago', ['CRÉDITO', 'CREDITO'])
            ->when($cliente, function ($q) use ($cliente) { $q->where('f.cliente_id', $cliente); })
            ->get(['f.id', 'f.cliente_id', 'f.fecha', 'f.total as monto', 'f.estado', 'f.deleted_at',
                'f.tipo_comprobante', 'f.pedido_nro',
                DB::raw("COALESCE(c.Nombres, f.nombre) as cliente")]);

        $filas = collect();
        foreach ($facturas as $deuda) {
            $deuda->origen = 'factura';
            $deuda->clave = 'factura:' . $deuda->id;
            $deuda->concepto = ($deuda->tipo_comprobante === 'FACTURA' ? 'Factura #' : 'Venta #') . $deuda->id
                . ($deuda->pedido_nro ? ' · Pedido #' . $deuda->pedido_nro : '');
            $deuda->pagado = (float) ($abonos[$deuda->id] ?? 0);
            $deuda->monto = (float) $deuda->monto;
            $activa = $deuda->estado === 'ACTIVO' && !$deuda->deleted_at;
            if (!$activa && !$deuda->pagado) { continue; }
            $deuda->saldo = $activa ? max(0, round($deuda->monto - $deuda->pagado, 2)) : 0;
            $deuda->estado = !$activa ? 'ANULADA CON ABONOS: REVISAR' : ($deuda->saldo > 0 ? 'PENDIENTE' : 'PAGADO');
            $filas->push($deuda);
        }

        // Las deudas que cobranzas agrega a mano a un cliente.
        $abonosManuales = DB::table('creditos_abonos')->where('origen', 'manual')
            ->whereNull('anulado_at')
            ->select('deuda_id', DB::raw('SUM(monto) as pagado'))
            ->groupBy('deuda_id')->pluck('pagado', 'deuda_id');

        $manuales = DB::table('creditos_manuales as d')->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'd.cliente_id')
            ->when($cliente, function ($q) use ($cliente) { $q->where('d.cliente_id', $cliente); })
            ->get(['d.id', 'd.cliente_id', 'd.fecha', 'd.monto', 'd.concepto', 'c.Nombres as cliente',
                // Lo que trae el saldo del sistema anterior (vacio en las deudas a mano).
                'd.comanda', 'd.importe', 'd.a_cuenta', 'd.empresa', 'd.vendedor', 'd.ultimo_pago']);

        foreach ($manuales as $deuda) {
            $deuda->origen = 'manual';
            $deuda->clave = 'manual:' . $deuda->id;
            $deuda->pagado = (float) ($abonosManuales[$deuda->id] ?? 0);
            $deuda->monto = (float) $deuda->monto;
            $deuda->saldo = max(0, round($deuda->monto - $deuda->pagado, 2));
            $deuda->estado = $deuda->saldo > 0 ? 'PENDIENTE' : 'PAGADO';
            $filas->push($deuda);
        }

        return $filas;
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'cliente_id' => 'required|integer|exists:tbclientes,Cod_Aut',
            'fecha' => 'required|date_format:Y-m-d|before_or_equal:today',
            'concepto' => 'required|string|max:255',
            'monto' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'solicitud_id' => 'required|uuid',
        ]);
        return DB::transaction(function () use ($datos, $request) {
            DB::table('tbclientes')->where('Cod_Aut', $datos['cliente_id'])->lockForUpdate()->first();
            $previo = DB::table('creditos_manuales')->where('solicitud_id', $datos['solicitud_id'])->first();
            if ($previo) {
                abort_unless((int) $previo->cliente_id === (int) $datos['cliente_id']
                    && (int) $previo->user_id === (int) $request->user()->CodAut
                    && (string) $previo->concepto === $datos['concepto']
                    && (float) $previo->monto === (float) $datos['monto'], 409,
                    'Esta solicitud ya fue registrada con otros datos. Cierre el formulario y actualice.');
                return response()->json(['id' => $previo->id]);
            }
            $id = DB::table('creditos_manuales')->insertGetId($datos + [
                'user_id' => $request->user()->CodAut, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return response()->json(['id' => $id], 201);
        });
    }

    /** Los abonos que valen de una deuda: lo anulado no entra, suma mal el total. */
    public function historial($origen, $id)
    {
        abort_unless(in_array($origen, ['factura', 'manual'], true), 404);
        return DB::table('creditos_abonos as a')->leftJoin('personal as p', 'p.CodAut', '=', 'a.user_id')
            ->where('a.origen', $origen)->where('a.deuda_id', $id)->whereNull('a.anulado_at')->orderByDesc('a.id')
            ->get(['a.id', 'a.monto', 'a.monto_efectivo', 'a.monto_qr', 'a.forma_pago', 'a.referencia', 'a.created_at',
                DB::raw("TRIM(CONCAT(COALESCE(p.Nombre1, ''), ' ', COALESCE(p.App1, ''))) as cobrador")]);
    }

    public function abonar(Request $request, $origen, $id)
    {
        abort_unless(in_array($origen, ['factura', 'manual'], true), 404);
        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'forma_pago' => 'required|in:EFECTIVO,QR,MIXTO',
            'monto_efectivo' => 'nullable|numeric|min:0',
            'monto_qr' => 'nullable|numeric|min:0',
            'referencia' => 'nullable|string|max:100', 'solicitud_id' => 'required|uuid',
        ]);
        // Cuanto entro por cada lado: en efectivo o QR va todo a uno; en el
        // mixto tienen que venir los dos y sumar el abono.
        $centavos = function ($v) { return (int) round((float) $v * 100); };
        if ($datos['forma_pago'] === 'MIXTO') {
            if ($centavos($datos['monto_efectivo'] ?? 0) <= 0 || $centavos($datos['monto_qr'] ?? 0) <= 0) {
                throw ValidationException::withMessages(['monto_efectivo' => 'En un pago mixto indicá cuánto fue en efectivo y cuánto por QR.']);
            }
            if ($centavos($datos['monto_efectivo']) + $centavos($datos['monto_qr']) !== $centavos($datos['monto'])) {
                throw ValidationException::withMessages(['monto' => 'Efectivo + QR tiene que sumar el monto del abono.']);
            }
            $datos['monto_efectivo'] = round((float) $datos['monto_efectivo'], 2);
            $datos['monto_qr'] = round((float) $datos['monto_qr'], 2);
        } else {
            $datos['monto_efectivo'] = $datos['forma_pago'] === 'EFECTIVO' ? round((float) $datos['monto'], 2) : 0;
            $datos['monto_qr'] = $datos['forma_pago'] === 'QR' ? round((float) $datos['monto'], 2) : 0;
        }
        return DB::transaction(function () use ($datos, $request, $origen, $id) {
            $deuda = DB::table($origen === 'factura' ? 'facturas' : 'creditos_manuales')->where('id', $id)->lockForUpdate()->first();
            abort_unless($deuda, 404);
            $previo = DB::table('creditos_abonos')->where('solicitud_id', $datos['solicitud_id'])->first();
            if ($previo) {
                abort_unless($previo->origen === $origen && (int) $previo->deuda_id === (int) $id
                    && (int) $previo->user_id === (int) $request->user()->CodAut
                    && (float) $previo->monto === (float) $datos['monto']
                    && $previo->forma_pago === $datos['forma_pago']
                    && (float) $previo->monto_efectivo === (float) $datos['monto_efectivo']
                    && (float) $previo->monto_qr === (float) $datos['monto_qr']
                    && (string) $previo->referencia === (string) ($datos['referencia'] ?? ''), 409,
                    'Esta solicitud ya fue registrada con otros datos. Cierre el formulario y actualice.');
                return response()->json(['id' => $previo->id]);
            }
            if ($origen === 'factura' && ($deuda->estado !== 'ACTIVO' || $deuda->deleted_at || !in_array($deuda->tipo_pago, ['CRÉDITO', 'CREDITO'], true))) {
                throw ValidationException::withMessages(['deuda' => 'Esta venta no tiene un crédito activo.']);
            }
            $monto = $origen === 'factura' ? $deuda->total : $deuda->monto;
            $pagado = DB::table('creditos_abonos')->where('origen', $origen)->where('deuda_id', $id)
                ->whereNull('anulado_at')->sum('monto');
            if ((int) round($datos['monto'] * 100) > (int) round($monto * 100) - (int) round($pagado * 100)) {
                throw ValidationException::withMessages(['monto' => 'El abono supera el saldo pendiente. Actualice la cuenta.']);
            }
            $abono = DB::table('creditos_abonos')->insertGetId($datos + [
                'origen' => $origen, 'deuda_id' => $id, 'cliente_id' => $deuda->cliente_id,
                'user_id' => $request->user()->CodAut, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return response()->json(['id' => $abono, 'saldo' => round($monto - $pagado - $datos['monto'], 2)], 201);
        });
    }

    /**
     * Da de baja un abono cobrado por equivocacion.
     *
     * No se borra ni se corrige el monto: el cobro quedo anotado y con el
     * motivo se puede explicar despues. Al no contar mas para el saldo, la
     * deuda vuelve a deber lo que se le habia descontado.
     */
    public function anularAbono(Request $request, $abono)
    {
        $datos = $request->validate(['motivo' => 'required|string|max:150']);

        return DB::transaction(function () use ($datos, $request, $abono) {
            $fila = DB::table('creditos_abonos')->where('id', $abono)->lockForUpdate()->first();
            abort_unless($fila, 404, 'El abono no existe.');
            if ($fila->anulado_at) {
                throw ValidationException::withMessages(['abono' => 'Este abono ya estaba anulado.']);
            }

            DB::table('creditos_abonos')->where('id', $abono)->update([
                'anulado_at' => now(), 'anulado_por' => $request->user()->CodAut,
                'motivo_anulacion' => trim($datos['motivo']), 'updated_at' => now(),
            ]);

            // El saldo que queda, para avisarle al cobrador cuanto vuelve a deber.
            $deuda = DB::table($fila->origen === 'factura' ? 'facturas' : 'creditos_manuales')
                ->where('id', $fila->deuda_id)->first();
            $monto = $deuda ? (float) ($fila->origen === 'factura' ? $deuda->total : $deuda->monto) : 0;
            $pagado = DB::table('creditos_abonos')->where('origen', $fila->origen)
                ->where('deuda_id', $fila->deuda_id)->whereNull('anulado_at')->sum('monto');

            return response()->json([
                'message' => 'Abono anulado: la deuda vuelve a deber Bs ' . number_format($monto - $pagado, 2),
                'saldo' => round($monto - $pagado, 2),
            ]);
        });
    }
}
