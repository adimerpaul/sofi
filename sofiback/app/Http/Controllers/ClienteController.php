<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\MisVisita;
use App\Models\User;
use App\Services\DeudaCliente;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ClienteController extends Controller{
    function personalCliente(Request $request){
        // ?todos=1: todo el personal, aunque todavia no tenga clientes (pantalla Clientes, para asignar vendedor).
        if ($request->boolean('todos')) {
            return User::orderBy('Nombre1')->get()->map(function ($usuario) {
                return [
                    'CodAut' => $usuario->CodAut,
                    'ci' => trim($usuario->ci),
                    'nombre' => trim($usuario->Nombre1). ' ' . trim($usuario->Nombre2) . ' ' . trim($usuario->App1) . ' ' . trim($usuario->Apm),
                ];
            });
        }
        $usuariosConClientes = User::whereHas('clientes')
            ->with('clientes')
            ->get();
        return $usuariosConClientes->map(function ($usuario) {
            return [
                'CodAut' => $usuario->CodAut,
                'ci' => $usuario->ci,
                'nombre' => trim($usuario->Nombre1). ' ' . trim($usuario->Nombre2) . ' ' . trim($usuario->App1) . ' ' . trim($usuario->Apm),
            ];
        });
    }
    public function index(Request $request){
//        return DB::select(
//            "SELECT *,
//            (SELECT estado from misvisitas where id=(SELECT max(id) from misvisitas where cliente_id=Cod_Aut AND fecha='".date('Y-m-d')."' )) as tipo,
//            (SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0) as totdeuda ,
//            (SELECT MIN(c.FechaEntreg) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0) as fechaminima ,
//            (SELECT count(*) FROM tbctascobrar WHERE CINIT=tbclientes.Id AND Nrocierre=0 and Acuenta=0) as cantdeuda
//
//             FROM tbclientes
//             WHERE TRIM(CiVend)='".$request->user()->ci."'
//             ORDER BY tipo desc;"
//        );

        $misClientes = Cliente::whereRaw("TRIM(CiVend)='".$request->user()->ci."'")->get();

        $codAuts = $misClientes->pluck('Cod_Aut')->toArray();
        $Ids = $misClientes->pluck('Id')->toArray();

        // Si el vendedor no tiene clientes, un IN () vacio revienta el SQL
        $visitas = [];
        if (!empty($codAuts)) {
            $visitas = DB::select(
                "SELECT * FROM misvisitas WHERE cliente_id IN (".implode(',', array_fill(0, count($codAuts), '?')).") AND fecha = ?",
                array_merge($codAuts, [date('Y-m-d')])
            );
        }

        // Deuda desde cobranzas/creditos (ya no de tbctascobrar).
        DeudaCliente::adjuntar($misClientes);

        $misClientes->map(function ($cliente) use ($visitas) {
            $cliente->tipo = null;
            if (isset($visitas)) {
                foreach ($visitas as $visita) {
                    if ($cliente->Cod_Aut == $visita->cliente_id) {
                        $cliente->tipo = $visita->estado;
                        break;
                    }
                }
            }
        });

// ───── FOTOS: traer todas en 1 query y agrupar por cliente_id ─────
        $fotosRows = [];
        if (!empty($codAuts)) {
            $fotosRows = DB::table('cliente_photos')
                ->select('cliente_id', 'photo_path')
                ->whereIn('cliente_id', $codAuts)
                ->orderByDesc('id')
                ->get();
        }

        $byCliente = [];
        $base = url('/'); // porque guardas en /public/cliente_photos/...
        foreach ($fotosRows as $r) {
            $byCliente[$r->cliente_id][] = $base . '/' . ltrim($r->photo_path, '/');
        }

// Adjuntar SIN usar []= (una sola asignación)
        foreach ($misClientes as $cliente) {
            $lista = $byCliente[$cliente->Cod_Aut] ?? [];
            // si quieres limitar a las 3 más recientes: $lista = array_slice($lista, 0, 3);
            $cliente->fotografias = $lista;           // ✅ asignación directa
            // opcional:
            // $cliente->fotos_count = count($lista);
        }


        return $misClientes;
    }

    public function filtrarlista2(Request $request){

        if ($request->filtradia==9){
            //si es para todos los dias
            return DB::select(
                "SELECT *,
            (SELECT estado from misvisitas where id=(SELECT max(id) from misvisitas where cliente_id=Cod_Aut AND fecha='".date('Y-m-d')."' )) as tipo,
            (SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0) as totdeuda ,
            (SELECT MIN(c.FechaEntreg) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0) as fechaminima ,
            (SELECT count(*) FROM tbctascobrar WHERE CINIT=tbclientes.Id AND Nrocierre=0 and Acuenta=0) as cantdeuda

             FROM tbclientes
             WHERE TRIM(CiVend)='".$request->user()->ci."' ORDER BY tipo desc;");
        }

        if($request->filtradia==8) $numdia=date('w');
        else $numdia=$request->filtradia;

        $filtro='';
        switch ($numdia) {
            case 0:
                $filtro=" AND do=1 ";
                break;
            case 1:
                $filtro=" AND lu=1 ";
                break;
            case 2:
                $filtro=" AND Ma=1 ";
                break;
            case 3:
                $filtro= " AND Mi=1 ";
                break;
            case 4:
                $filtro= " AND Ju=1 ";
                break;
            case 5:
                $filtro=" AND Vi=1 ";
                break;
            case 6:
                $filtro=" AND Sa=1 ";
                break;
            default:
                $filtro= '';
                break;
        }
        return DB::select(
            "SELECT *,
            (SELECT estado from misvisitas where id=(SELECT max(id) from misvisitas where cliente_id=Cod_Aut AND fecha='".date('Y-m-d')."' )) as tipo,
            (SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0) as totdeuda ,
            (SELECT MIN(c.FechaEntreg) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0) as fechaminima ,
            (SELECT count(*) FROM tbctascobrar WHERE CINIT=tbclientes.Id AND Nrocierre=0 and Acuenta=0) as cantdeuda

             FROM tbclientes
             WHERE TRIM(CiVend)='".$request->user()->ci."' " .$filtro." ORDER BY tipo desc;");

             //SELECT t.idCli,COUNT(DISTINCT(date(t.fecha))) FROM tbpedidos t where YEAR(t.fecha)=YEAR('2022-10-14') and MONTH(t.fecha)=MONTH('2022-10-14') and t.idCli=1;
             //(SELECT COUNT(DISTINCT(date(t.fecha))) FROM tbpedidos t where YEAR(t.fecha)=YEAR('".date('Y-m-d')."') and MONTH(t.fecha)=MONTH('".date('Y-m-d')."') and t.idCli=tbclientes.Cod_Aut) as totalpedido
    }
    public function filtrarlista3(Request $request) {
        $user_ci = trim($request->user()->ci);
        $fecha_hoy = date('Y-m-d');
        if ($request->filtradia == 9) {
            // Si es para todos los días
            return DB::select("
            SELECT t.*,
                v.tipo,
                d.totdeuda,
                d.fechaminima,
                d.cantdeuda
            FROM tbclientes t
            LEFT JOIN (
                SELECT cliente_id, estado as tipo
                FROM misvisitas
                WHERE fecha = ? AND id IN (
                    SELECT MAX(id) FROM misvisitas GROUP BY cliente_id
                )
            ) v ON v.cliente_id = t.Cod_Aut
            LEFT JOIN (
                SELECT CINIT,
                    SUM(Importe - IFNULL((SELECT SUM(Acuenta) FROM tbctascobrar WHERE comanda=c.comanda), 0)) AS totdeuda,
                    MIN(FechaEntreg) AS fechaminima,
                    COUNT(*) AS cantdeuda
                FROM tbctascobrar c
                WHERE Nrocierre = 0 AND Acuenta = 0
                GROUP BY CINIT
            ) d ON d.CINIT = t.Id
            WHERE TRIM(t.CiVend) = ?
            ORDER BY v.tipo DESC
        ", [$fecha_hoy, $user_ci]);
        }

        // Determinar el día de la semana
        $numdia = ($request->filtradia == 8) ? date('w') : $request->filtradia;

        $dias = ['do', 'lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'];
        $filtro = isset($dias[$numdia]) ? " AND t.{$dias[$numdia]} = 1 " : "";

        $sql = "
        SELECT t.*,
            v.tipo,
            d.totdeuda,
            d.fechaminima,
            d.cantdeuda
        FROM tbclientes t
        LEFT JOIN (
            SELECT cliente_id, estado as tipo
            FROM misvisitas
            WHERE fecha = ? AND id IN (
                SELECT MAX(id) FROM misvisitas GROUP BY cliente_id
            )
        ) v ON v.cliente_id = t.Cod_Aut
        LEFT JOIN (
            SELECT CINIT,
                SUM(Importe - IFNULL((SELECT SUM(Acuenta) FROM tbctascobrar WHERE comanda=c.comanda), 0)) AS totdeuda,
                MIN(FechaEntreg) AS fechaminima,
                COUNT(*) AS cantdeuda
            FROM tbctascobrar c
            WHERE Nrocierre = 0 AND Acuenta = 0
            GROUP BY CINIT
        ) d ON d.CINIT = t.Id
        WHERE TRIM(t.CiVend) = ? $filtro
        ORDER BY v.tipo DESC
    ";
        error_log($sql);
        error_log("[$fecha_hoy, $user_ci]");
        return DB::select($sql, [$fecha_hoy, $user_ci]);
    }
    public function filtrarlista(Request $request)
    {
        $user_ci = trim($request->user()->ci);
        $fecha_hoy = Carbon::now()->format('Y-m-d');

        $idsExtra = ['61839000', '0023456'];

        // Subconsulta base (usada para ambos)
        $baseSelect = [
            'tbclientes.*',
            // Última visita
            'tipo' => MisVisita::select('estado')
                ->whereColumn('cliente_id', 'tbclientes.Cod_Aut')
                ->where('fecha', $fecha_hoy)
                ->orderByDesc('id')
                ->limit(1),
            // totdeuda / fechaminima / cantdeuda se agregan despues desde cobranzas/creditos
        ];

        // Primera consulta: clientes normales
        $clientesQuery = Cliente::query()
            ->whereRaw('TRIM(CiVend) = ?', [$user_ci])
            ->select($baseSelect);

        // Día de la semana
        if ($request->filtradia != 9) {
            $numdia = $request->filtradia == 8 ? Carbon::now()->dayOfWeek : $request->filtradia;
            $dias = ['do', 'lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'];
            if (isset($dias[$numdia])) {
                $clientesQuery->where($dias[$numdia], 1);
            }
        }

        // Segunda consulta: clientes forzados por ID
        $clientesExtraQuery = Cliente::query()
            ->whereIn('Id', $idsExtra)
            ->select($baseSelect);

        // Unión de ambas
        $clientes = $clientesQuery
            ->union($clientesExtraQuery)
            ->orderByDesc('tipo')
            ->get();
        DeudaCliente::adjuntar($clientes);
        $codAuts = $clientes->pluck('Cod_Aut')->filter()->values()->all();

        $mapFotos = [];
        if (!empty($codAuts)) {
            $rows = DB::table('cliente_photos')
                ->select('cliente_id', 'photo_path')
                ->whereIn('cliente_id', $codAuts)
                ->orderByDesc('id')
                ->get();

            $base = url('/'); // porque guardas bajo /public
            foreach ($rows as $r) {
                $mapFotos[$r->cliente_id][] = $base . '/' . ltrim($r->photo_path, '/');
            }
        }

        // Adjuntar SIN usar []= (evita "Indirect modification ...")
        $clientes = $clientes->map(function ($cli) use ($mapFotos) {
            $lista = $mapFotos[$cli->Cod_Aut] ?? [];
            // si quieres limitar, por ejemplo, a 3 más recientes:
            // $lista = array_slice($lista, 0, 3);
            $cli->fotografias = $lista;                 // asignación directa ✅
            $cli->fotos_count = count($lista);          // opcional
            return $cli;
        });

        return $clientes;
    }


    public function listsinpedido(Request $request)
    {
        $ci = $request->user()->ci;
        $codAut = $request->user()->CodAut;
        $ini = $request->ini . ' 00:00:00';
        $fin = $request->fin . ' 23:59:59';

        return DB::select("
        SELECT
            c.*,
            (SELECT MAX(p2.fecha)
             FROM tbpedidos p2
             WHERE p2.idCli = c.Cod_Aut AND p2.CIfunc = ? AND p2.deleted_at IS NULL) as ultima_compra
        FROM tbclientes c
        LEFT JOIN tbpedidos p
            ON p.idCli = c.Cod_Aut
            AND p.CIfunc = ?
            AND p.fecha BETWEEN ? AND ?
            AND p.deleted_at IS NULL
        WHERE c.CiVend = ?
          AND p.codAut IS NULL
    ", [$codAut, $codAut, $ini, $fin, $ci]);
    }
    public function exportarSinPedido(Request $request)
    {
        $ci = $request->user()->ci;
        $codAut = $request->user()->CodAut;
        $ini = $request->ini . ' 00:00:00';
        $fin = $request->fin . ' 23:59:59';

        $clientes = DB::select("
        SELECT
            c.*,
            (SELECT MAX(p2.fecha)
             FROM tbpedidos p2
             WHERE p2.idCli = c.Cod_Aut AND p2.CIfunc = ? AND p2.deleted_at IS NULL) as ultima_compra
        FROM tbclientes c
        LEFT JOIN tbpedidos p
            ON p.idCli = c.Cod_Aut
            AND p.CIfunc = ?
            AND p.fecha BETWEEN ? AND ?
            AND p.deleted_at IS NULL
        WHERE c.CiVend = ?
          AND p.codAut IS NULL
    ", [$codAut, $codAut, $ini, $fin, $ci]);

        $fechaActual = now()->format('d/m/Y H:i');
        $usuario = $request->user();

        $pdf = Pdf::loadView('pdf.sinpedido', [
            'clientes' => $clientes,
            'fecha' => $fechaActual,
            'usuario' => $usuario,
            'ini' => $request->ini,
            'fin' => $request->fin,
        ])->setPaper('A4', 'landscape');

        return $pdf->download('clientes_sin_pedido.pdf');
    }

    public function todosclientes(Request $request)
    {
//        return DB::select("SELECT * FROM tbclientes WHERE TRIM(CiVend)='".$request->user()->ci."'");
        return $this->consultaClientes();
    }

    /**
     * El listado de la pantalla Clientes en Excel, con los mismos filtros que
     * tenga puestos (buscar, vendedor, zona, estado). Hoja Clientes: todos los
     * datos del cliente agrupados (contacto, vendedor y zona, canal, dias de
     * visita, deuda, estado, ubicacion con link al mapa). Hoja Resumen: totales
     * por vendedor, zona, canal y dia de visita.
     */
    public function exportarExcel(Request $request)
    {
        // Son ~3000 clientes y 34 columnas: con 128M PhpSpreadsheet queda al limite.
        @ini_set('memory_limit', '512M');
        $texto = mb_strtolower(trim((string) $request->buscar));
        $vendedor = trim((string) $request->vendedor);
        $zona = trim((string) $request->zona);
        $estado = trim((string) $request->estado);

        $clientes = collect($this->consultaClientes())->filter(function ($c) use ($texto, $vendedor, $zona, $estado) {
            if ($vendedor !== '' && trim((string) $c->CiVend) !== $vendedor) return false;
            if ($zona !== '' && trim((string) $c->zona) !== $zona) return false;
            if ($estado !== '' && $c->venta !== $estado) return false;
            if ($texto === '') return true;
            foreach ([$c->Nombres, $c->Id, $c->Telf, $c->Cod_Aut, $c->Direccion] as $v) {
                if (mb_strpos(mb_strtolower((string) $v), $texto) !== false) return true;
            }
            return false;
        })->sortBy(function ($c) {
            return trim((string) $c->Nombres);
        })->values();

        $dias = ['lu' => 'L', 'Ma' => 'M', 'Mi' => 'X', 'Ju' => 'J', 'Vi' => 'V', 'Sa' => 'S', 'do' => 'D'];
        $nombresDia = ['lu' => 'Lunes', 'Ma' => 'Martes', 'Mi' => 'Miércoles', 'Ju' => 'Jueves', 'Vi' => 'Viernes', 'Sa' => 'Sábado', 'do' => 'Domingo'];
        $t = function ($v) { return trim((string) $v); };
        $sinDia = function ($c) use ($dias) {
            foreach (array_keys($dias) as $campo) {
                if ((int) $c->$campo) return false;
            }
            return true;
        };

        // Cada columna: titulo, ancho, alineacion (c|r|''), tipo (txt: texto
        // forzado, bs: moneda, coord: coordenada, wa/mapa: link, dia, estado)
        // y el valor de la fila. Agrupadas bajo una banda de color.
        $diasVisita = [];
        foreach ($dias as $campo => $letraDia) {
            $diasVisita[] = [$letraDia, 4, 'c', 'dia', function ($c) use ($campo) { return (int) $c->$campo ? '●' : ''; }];
        }
        $diasVisita[] = ['Días/sem', 7, 'c', '', function ($c) use ($dias) {
            return count(array_filter(array_keys($dias), function ($campo) use ($c) { return (int) $c->$campo; }));
        }];
        $columnas = [
            ['CLIENTE', [
                ['Cod', 7, 'c', '', function ($c) { return (int) $c->Cod_Aut; }],
                ['Cliente', 32, '', '', function ($c) use ($t) { return $t($c->Nombres); }],
                ['CI/NIT', 12, '', 'txt', function ($c) use ($t) { return $t($c->Id) . ($t($c->complto) !== '' ? '-' . $t($c->complto) : ''); }],
                ['Celular', 12, '', 'txt', function ($c) use ($t) { return $t($c->Telf); }],
                ['WhatsApp', 10, 'c', 'wa', function ($c) { return $this->numeroWhatsapp($c->Telf) ? 'Abrir chat' : ''; }],
                ['Dirección', 34, '', '', function ($c) use ($t) { return $t($c->Direccion); }],
                ['Empresa / Razón social', 22, '', '', function ($c) use ($t) { return $t($c->Empresa); }],
            ]],
            ['VENDEDOR Y ZONA', [
                ['Vendedor', 24, '', '', function ($c) use ($t) { return $t($c->vendedor) ?: $t($c->CiVend); }],
                ['CI vendedor', 11, 'c', 'txt', function ($c) use ($t) { return $t($c->CiVend); }],
                ['Zona', 11, 'c', '', function ($c) use ($t) { return $t($c->zona); }],
                ['Territorio', 11, 'c', '', function ($c) use ($t) { return $t($c->territorio); }],
            ]],
            ['CANAL', [
                ['Supra canal', 8, 'c', '', function ($c) use ($t) { return $t($c->SupraCanal); }],
                ['Canal', 18, '', '', function ($c) use ($t) { return $t($c->Canal); }],
                ['Subcanal', 12, '', '', function ($c) use ($t) { return $t($c->subcanal); }],
                ['Transporte', 18, '', '', function ($c) use ($t) { return $t($c->transporte); }],
            ]],
            ['DÍAS DE VISITA', $diasVisita],
            ['DEUDA', [
                ['Deuda Bs', 12, 'r', 'bs', function ($c) { return (float) $c->totdeuda; }],
                ['N° deudas', 8, 'c', '', function ($c) { return (int) $c->cantdeuda; }],
                ['Deuda desde', 11, 'c', '', function ($c) { return $c->fechaminima ? date('d/m/Y', strtotime($c->fechaminima)) : ''; }],
                ['Excepción', 9, 'c', '', function ($c) { return (int) $c->excepcion_deuda ? 'Sí' : 'No'; }],
                ['Crédito', 8, 'c', '', function ($c) { return (int) $c->acepta_credito ? 'Sí' : 'No'; }],
            ]],
            ['ESTADO', [
                ['Estado', 10, 'c', 'estado', function ($c) { return $c->venta; }],
                ['Fotos', 6, 'c', '', function ($c) { return (int) $c->fotos_count; }],
            ]],
            ['UBICACIÓN', [
                ['Ubicación', 9, 'c', '', function ($c) { return $this->tieneUbicacion($c) ? 'Sí' : 'No'; }],
                ['Latitud', 11, 'r', 'coord', function ($c) { return $this->tieneUbicacion($c) ? (float) $c->Latitud : ''; }],
                ['Longitud', 11, 'r', 'coord', function ($c) { return $this->tieneUbicacion($c) ? (float) $c->longitud : ''; }],
                ['Mapa', 10, 'c', 'mapa', function ($c) { return $this->tieneUbicacion($c) ? 'Ver mapa' : ''; }],
            ]],
        ];
        $planas = [];
        foreach ($columnas as $grupo) {
            foreach ($grupo[1] as $col) {
                $col[] = Coordinate::stringFromColumnIndex(count($planas) + 1);
                $planas[] = $col;
            }
        }
        $ultima = Coordinate::stringFromColumnIndex(count($planas));
        $letra = function ($titulo) use ($planas) {
            foreach ($planas as $col) {
                if ($col[0] === $titulo) return $col[5];
            }
        };

        $libro = new Spreadsheet();
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Clientes');
        foreach ($planas as $col) {
            $hoja->getColumnDimension($col[5])->setWidth($col[1]);
        }

        // Titulo, filtros aplicados y resumen
        $usuario = $request->user();
        $nombreUsuario = $usuario ? trim($usuario->Nombre1 . ' ' . $usuario->App1) : '';
        $filtros = [];
        if ($texto !== '') $filtros[] = 'Buscar: "' . $request->buscar . '"';
        if ($vendedor !== '') {
            $nombreVend = DB::table('personal')->whereRaw('TRIM(ci) = ?', [$vendedor])
                ->value(DB::raw("TRIM(CONCAT(TRIM(Nombre1),' ',TRIM(App1)))"));
            $filtros[] = 'Vendedor: ' . ($nombreVend ? $nombreVend . ' (' . $vendedor . ')' : $vendedor);
        }
        if ($zona !== '') $filtros[] = 'Zona: ' . $zona;
        if ($estado !== '') $filtros[] = 'Estado: ' . $estado;

        $activos = $clientes->where('venta', 'ACTIVO')->count();
        $conDeuda = $clientes->filter(function ($c) { return (float) $c->totdeuda > 0; });
        $sinUbicacion = $clientes->reject(function ($c) { return $this->tieneUbicacion($c); })->count();

        $this->tituloExcel($hoja, 'LISTADO DE CLIENTES', $ultima, [
            'Filtros: ' . ($filtros ? implode('   ·   ', $filtros) : 'ninguno (todos los clientes)'),
            'Clientes: ' . $clientes->count() . '   ·   Activos: ' . $activos . '   ·   Inactivos: ' . ($clientes->count() - $activos)
                . '   ·   Con deuda: ' . $conDeuda->count()
                . '   ·   Deuda total: ' . number_format($conDeuda->sum(function ($c) { return (float) $c->totdeuda; }), 2) . ' Bs'
                . '   ·   Sin ubicación: ' . $sinUbicacion . '   ·   Sin día de visita: ' . $clientes->filter($sinDia)->count(),
            'Generado por ' . $nombreUsuario . ' el ' . date('d/m/Y H:i') . '   ·   ● = día de visita   ·   Los links de WhatsApp y Mapa se abren con clic',
        ]);

        // Encabezado en dos filas: grupos y columnas
        $grp = 6;
        $cab = 7;
        $colores = ['1F4E78', '385723', '7F6000', '2E75B6', 'C55A11', '595959', '7030A0'];
        $inicio = 1;
        foreach ($columnas as $i => $grupo) {
            $desde = Coordinate::stringFromColumnIndex($inicio);
            $hasta = Coordinate::stringFromColumnIndex($inicio + count($grupo[1]) - 1);
            $hoja->setCellValue($desde . $grp, $grupo[0]);
            $hoja->mergeCells($desde . $grp . ':' . $hasta . $grp);
            $hoja->getStyle($desde . $grp . ':' . $hasta . $cab)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colores[$i]]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
            ]);
            $inicio += count($grupo[1]);
        }
        $hoja->fromArray(array_column($planas, 0), null, 'A' . $cab);
        $hoja->getRowDimension($grp)->setRowHeight(18);
        $hoja->getRowDimension($cab)->setRowHeight(28);

        // Filas
        $fila = $cab + 1;
        foreach ($clientes as $c) {
            $valores = [];
            foreach ($planas as $col) {
                $valores[] = $col[4]($c);
            }
            // strictNullComparison (cuarto argumento): sin el, fromArray deja en blanco los ceros.
            $hoja->fromArray($valores, null, 'A' . $fila, true);
            foreach ($planas as $i => $col) {
                if ($col[3] === 'txt') {
                    // CI y celular como texto: si no, Excel los muestra en notacion cientifica o les quita ceros.
                    $hoja->setCellValueExplicit($col[5] . $fila, (string) $valores[$i], DataType::TYPE_STRING);
                } elseif ($col[3] === 'wa' && $valores[$i] !== '') {
                    $hoja->getCell($col[5] . $fila)->getHyperlink()->setUrl('https://wa.me/' . $this->numeroWhatsapp($c->Telf));
                } elseif ($col[3] === 'mapa' && $valores[$i] !== '') {
                    $hoja->getCell($col[5] . $fila)->getHyperlink()
                        ->setUrl('https://www.google.com/maps?q=' . (float) $c->Latitud . ',' . (float) $c->longitud);
                }
            }
            $fila++;
        }
        $primera = $cab + 1;
        $ultimaFila = max($fila - 1, $primera);

        // Formato de la tabla. Los colores van por formato condicional y no
        // celda por celda: con miles de filas, un estilo por celda dispara la memoria.
        $hoja->getStyle('A' . $primera . ':' . $ultima . $ultimaFila)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'C9D3DF']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
        ]);
        foreach ($planas as $col) {
            $rango = $col[5] . $primera . ':' . $col[5] . $ultimaFila;
            if ($col[2] === 'c') $hoja->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            if ($col[2] === 'r') $hoja->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            if ($col[3] === 'bs') $hoja->getStyle($col[5] . $primera . ':' . $col[5] . ($ultimaFila + 1))->getNumberFormat()->setFormatCode('#,##0.00');
            if ($col[3] === 'coord') $hoja->getStyle($rango)->getNumberFormat()->setFormatCode('0.000000');
            if (in_array($col[3], ['wa', 'mapa'])) $hoja->getStyle($rango)->getFont()->setUnderline(true)->getColor()->setRGB('0563C1');
            if ($col[3] === 'dia') {
                $hoja->getStyle($rango)->getFont()->setBold(true)->getColor()->setRGB('2E7D32');
                $hoja->getStyle($rango)->setConditionalStyles([$this->condicion('LEN(' . $col[5] . $primera . ')>0', 'C6EFCE')]);
            }
        }
        foreach (['Cliente', 'Dirección', 'Empresa / Razón social', 'Transporte'] as $titulo) {
            $hoja->getStyle($letra($titulo) . $primera . ':' . $letra($titulo) . $ultimaFila)->getAlignment()->setWrapText(true);
        }
        $colEstado = $letra('Estado');
        $hoja->getStyle($colEstado . $primera . ':' . $colEstado . $ultimaFila)->setConditionalStyles([
            $this->condicion($colEstado . $primera . '="ACTIVO"', 'C6EFCE', '006100'),
            $this->condicion($colEstado . $primera . '<>"ACTIVO"', 'FFC7CE', '9C0006'),
        ]);
        $colDeuda = $letra('Deuda Bs');
        $hoja->getStyle($colDeuda . $primera . ':' . $colDeuda . $ultimaFila)->setConditionalStyles([
            $this->condicion($colDeuda . $primera . '>0', 'FCE4D6', 'C55A11'),
        ]);
        $colUbic = $letra('Ubicación');
        $hoja->getStyle($colUbic . $primera . ':' . $colUbic . $ultimaFila)->setConditionalStyles([
            $this->condicion($colUbic . $primera . '="No"', 'FFEB9C', '9C5700'),
        ]);
        // Filas alternadas: solo en las columnas sin color propio.
        $hoja->getStyle('A' . $primera . ':' . $letra('Transporte') . $ultimaFila)->setConditionalStyles([
            $this->condicion('MOD(ROW(),2)=0', 'EEF3F9'),
        ]);

        // Fila de total: clientes por dia de visita, deuda y numero de deudas
        $total = $ultimaFila + 1;
        $hoja->setCellValue('A' . $total, 'TOTAL (' . $clientes->count() . ' clientes)');
        $hoja->mergeCells('A' . $total . ':' . $letra('Transporte') . $total);
        foreach (array_values($dias) as $titulo) {
            $col = $letra($titulo);
            $hoja->setCellValue($col . $total, '=COUNTIF(' . $col . $primera . ':' . $col . $ultimaFila . ',"●")');
        }
        $hoja->setCellValue($colDeuda . $total, '=SUM(' . $colDeuda . $primera . ':' . $colDeuda . $ultimaFila . ')');
        $colN = $letra('N° deudas');
        $hoja->setCellValue($colN . $total, '=SUM(' . $colN . $primera . ':' . $colN . $ultimaFila . ')');
        $hoja->getStyle('A' . $total . ':' . $ultima . $total)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1F4E78']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $hoja->getStyle('A' . $total)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $hoja->getStyle($colDeuda . $total)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Encabezado y nombre fijos, autofiltro y hoja lista para imprimir
        $hoja->freezePane('C' . $primera);
        $hoja->setAutoFilter('A' . $cab . ':' . $ultima . $ultimaFila);
        $this->paginaExcel($hoja, $grp, $cab, 'Listado de clientes');
        $hoja->setSelectedCell('A' . $primera);

        $this->resumenClientesExcel($libro->createSheet(), $clientes, $dias, $nombresDia, $filtros, $sinDia);
        $libro->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'xls');
        (new Xlsx($libro))->save($tmp);

        return response()->download($tmp, 'clientes_' . date('Y-m-d_His') . '.xlsx')->deleteFileAfterSend(true);
    }

    /** Hoja Resumen del Excel de clientes: por vendedor, zona, canal y dia de visita. */
    private function resumenClientesExcel($hoja, $clientes, $dias, $nombresDia, $filtros, $sinDia)
    {
        $hoja->setTitle('Resumen');
        $this->tituloExcel($hoja, 'RESUMEN DE CLIENTES', 'P', [
            'Filtros: ' . ($filtros ? implode('   ·   ', $filtros) : 'ninguno (todos los clientes)'),
            'Generado el ' . date('d/m/Y H:i'),
        ]);
        foreach (['A' => 34, 'B' => 10, 'C' => 9, 'D' => 9, 'E' => 10, 'F' => 14, 'G' => 11, 'H' => 10] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
        foreach (range('I', 'P') as $col) {
            $hoja->getColumnDimension($col)->setWidth(7);
        }

        $deuda = function ($lista) { return $lista->sum(function ($c) { return (float) $c->totdeuda; }); };
        $base = function ($nombre, $lista) use ($deuda) {
            return [$nombre, $lista->count(), $lista->where('venta', 'ACTIVO')->count(), $lista->where('venta', '<>', 'ACTIVO')->count(),
                $lista->filter(function ($c) { return (float) $c->totdeuda > 0; })->count(), $deuda($lista)];
        };
        $sinUbicacion = function ($lista) { return $lista->reject(function ($c) { return $this->tieneUbicacion($c); })->count(); };
        $fila = 5;

        // Por vendedor, con cuantos clientes visita cada dia
        $filas = [];
        $porVendedor = $clientes->groupBy(function ($c) {
            return trim((string) $c->vendedor) ?: (trim((string) $c->CiVend) ?: 'SIN VENDEDOR');
        })->sortKeys();
        foreach ($porVendedor as $nombre => $lista) {
            $f = array_merge($base($nombre, $lista), [$sinUbicacion($lista), $lista->filter(function ($c) { return (int) $c->fotos_count > 0; })->count()]);
            foreach (array_keys($dias) as $campo) {
                $f[] = $lista->filter(function ($c) use ($campo) { return (int) $c->$campo; })->count();
            }
            $f[] = $lista->filter($sinDia)->count();
            $filas[] = $f;
        }
        $fila = $this->tablaResumen($hoja, $fila, 'POR VENDEDOR',
            array_merge(['Vendedor', 'Clientes', 'Activos', 'Inactivos', 'Con deuda', 'Deuda Bs', 'Sin ubicación', 'Con fotos'], array_values($dias), ['Sin día']),
            $filas, '1F4E78');

        // Por zona
        $filas = [];
        foreach ($clientes->groupBy(function ($c) { return trim((string) $c->zona) ?: 'SIN ZONA'; })->sortKeys() as $nombre => $lista) {
            $filas[] = array_merge($base($nombre, $lista), [$sinUbicacion($lista),
                $lista->map(function ($c) { return trim((string) $c->CiVend); })->filter()->unique()->count()]);
        }
        $fila = $this->tablaResumen($hoja, $fila, 'POR ZONA',
            ['Zona', 'Clientes', 'Activos', 'Inactivos', 'Con deuda', 'Deuda Bs', 'Sin ubicación', 'Vendedores'], $filas, '385723');

        // Por canal
        $filas = [];
        $porCanal = $clientes->groupBy(function ($c) {
            return (trim((string) $c->SupraCanal) ?: '-') . ' · ' . (trim((string) $c->Canal) ?: 'SIN CANAL');
        })->sortKeys();
        foreach ($porCanal as $nombre => $lista) {
            $filas[] = $base($nombre, $lista);
        }
        $fila = $this->tablaResumen($hoja, $fila, 'POR CANAL (SUPRA CANAL · CANAL)',
            ['Canal', 'Clientes', 'Activos', 'Inactivos', 'Con deuda', 'Deuda Bs'], $filas, '7F6000');

        // Por dia de visita: sin total, un cliente cuenta en cada dia que se le visita
        $filas = [];
        foreach ($nombresDia as $campo => $nombre) {
            $filas[] = $base($nombre, $clientes->filter(function ($c) use ($campo) { return (int) $c->$campo; }));
        }
        $filas[] = $base('Sin día de visita', $clientes->filter($sinDia));
        $this->tablaResumen($hoja, $fila, 'POR DÍA DE VISITA (un cliente cuenta en cada día que se le visita)',
            ['Día', 'Clientes', 'Activos', 'Inactivos', 'Con deuda', 'Deuda Bs'], $filas, '2E75B6', false);

        $hoja->setSelectedCell('A1');
        $this->paginaExcel($hoja, null, null, 'Resumen de clientes');
    }

    /** Tabla del resumen: titulo, encabezado, filas, total (Deuda Bs en la columna F); devuelve la siguiente fila libre. */
    private function tablaResumen($hoja, $fila, $titulo, $cabecera, $filas, $color, $conTotal = true)
    {
        $ultima = Coordinate::stringFromColumnIndex(count($cabecera));
        $hoja->setCellValue('A' . $fila, $titulo);
        $hoja->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB($color);
        $fila++;
        $encabezado = $fila;
        $hoja->fromArray($cabecera, null, 'A' . $fila);
        $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $hoja->getRowDimension($fila)->setRowHeight(28);
        $desde = $fila + 1;
        foreach ($filas as $f) {
            $fila++;
            $hoja->fromArray($f, null, 'A' . $fila, true);
            if (($fila - $desde) % 2 === 1) {
                $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
            }
        }
        $hasta = max($fila, $desde);
        if ($conTotal && $filas) {
            $fila++;
            $hoja->setCellValue('A' . $fila, 'TOTAL');
            for ($i = 2; $i <= count($cabecera); $i++) {
                $col = Coordinate::stringFromColumnIndex($i);
                $hoja->setCellValue($col . $fila, '=SUM(' . $col . $desde . ':' . $col . $hasta . ')');
            }
            $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
            ]);
        }
        $hoja->getStyle('A' . $encabezado . ':' . $ultima . $fila)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('C9D3DF');
        $hoja->getStyle('B' . $desde . ':' . $ultima . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle('F' . $desde . ':F' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle('F' . $desde . ':F' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return $fila + 3;
    }

    /** Banda de titulo y lineas de subtitulo (filtros, totales, autor) arriba de la hoja. */
    private function tituloExcel($hoja, $titulo, $ultima, $lineas)
    {
        $hoja->setCellValue('A1', $titulo);
        $hoja->mergeCells('A1:' . $ultima . '1');
        $hoja->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $hoja->getRowDimension(1)->setRowHeight(26);
        foreach ($lineas as $i => $linea) {
            $f = $i + 2;
            $hoja->setCellValue('A' . $f, $linea);
            $hoja->mergeCells('A' . $f . ':' . $ultima . $f);
        }
        $hoja->getStyle('A3')->getFont()->setBold(true);
        $hoja->getStyle('A' . (count($lineas) + 1))->getFont()->setItalic(true)->getColor()->setRGB('666666');
    }

    private function paginaExcel($hoja, $repetirDesde, $repetirHasta, $pie)
    {
        $pagina = $hoja->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        if ($repetirDesde) $pagina->setRowsToRepeatAtTopByStartAndEnd($repetirDesde, $repetirHasta);
        $hoja->getPageMargins()->setLeft(0.3)->setRight(0.3)->setTop(0.4)->setBottom(0.5);
        $hoja->getHeaderFooter()->setOddFooter('&L&8' . $pie . '&R&8Página &P de &N');
    }

    /** Formato condicional por formula: relleno y, si se da, letra en negrita de ese color. */
    private function condicion($formula, $relleno, $letra = null)
    {
        $c = new Conditional();
        $c->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition($formula);
        $c->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB($relleno);
        if ($letra) $c->getStyle()->getFont()->setBold(true)->getColor()->setRGB($letra);

        return $c;
    }

    // Bolivia: los numeros de ocho digitos van con el 591 adelante.
    private function numeroWhatsapp($telefono)
    {
        $numero = preg_replace('/\D/', '', (string) $telefono);
        if (strlen($numero) < 8) return '';

        return strlen($numero) === 8 ? '591' . $numero : $numero;
    }

    private function tieneUbicacion($c)
    {
        return (float) $c->Latitud != 0 && (float) $c->longitud != 0;
    }

    private function consultaClientes()
    {
        // La deuda sale de cobranzas/creditos (DeudaCliente), ya no de tbctascobrar.
        return DB::select("
        SELECT tbclientes.*,

       '' as tipo,
        COALESCE(d.totdeuda, 0) as totdeuda
        ,COALESCE(d.cantdeuda, 0) as cantdeuda
        ,d.fechaminima
        ,(SELECT TRIM(CONCAT(TRIM(p.Nombre1),' ',TRIM(p.App1))) FROM personal p WHERE TRIM(p.ci)=TRIM(tbclientes.CiVend) LIMIT 1) as vendedor
        ,(SELECT count(*) FROM cliente_photos f WHERE f.cliente_id=tbclientes.Cod_Aut AND f.deleted_at IS NULL) as fotos_count
        FROM tbclientes
        LEFT JOIN (" . DeudaCliente::sqlPorCliente() . ") d ON d.cliente_id = tbclientes.Cod_Aut
        ");
    }

    /**
     * Los canales que valen segun el supra canal. Sin tildes, como el resto de
     * tbclientes (es del sistema anterior). La pantalla Clientes tiene la
     * misma lista para el select.
     */
    const CANALES = [
        'ON' => [
            'COMIDA RAPIDA', 'RESTAURANT', 'POLLERIA', 'VENTA AL PASO', 'INSTITUCION', 'ENTRETENIMIENTO',
            'HOGAR', 'EMPRESA', 'COMEDOR', 'PIZZERIA', 'REPOSTERIA',
        ],
        'OFF' => [
            'FRIAL', 'ABARROTES', 'PUESTO DE MERCADO', 'PUESTO DE MERCADO PET', 'TIENDA DE BARRIO',
            'VETERINARIA', 'MICROMERCADO', 'PET SHOP', 'AGENCIA DE HUEVOS',
        ],
    ];

    /** Las zonas del despegable de Clientes (la misma lista tiene la pantalla). */
    const ZONAS = [
        'NORTE', 'SUD', 'ESTE', 'CENTRO', 'PROVINCIA', 'COLQUIRI', 'HUANUNI', 'LLALLAGUA', 'CARACOLLO',
        'CHALLAPATA', 'UNCIA', 'POOPO', 'MACHACAMARCA',
    ];

    // Campos de tbclientes que se pueden editar desde la pantalla Clientes.
    // Casi todas las columnas son NOT NULL sin default: un texto vacio se guarda como '' y un numero como 0.
    private $camposTexto = [
        'Id', 'Nombres', 'Telf', 'Direccion', 'complto', 'Correcli', 'Empresa', 'profecion', 'sexo',
        'edad', 'EstCiv', 'Cod_ciudad', 'Cod_Nacio', 'clinew', 'CiVend', 'SupraCanal', 'Canal', 'subcanal',
        'zona', 'territorio', 'transporte', 'venta', 'tarjeta', 'TipoPaciente', 'MotivoListBlack', 'Latitud', 'longitud',
    ];
    private $camposNumero = [
        'Tipodocu', 'cod_car', 'Categoria', 'codcli', 'Imp_pieza', 'ctasMont', 'ctasdias',
        'lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'do',
        'ListBlack', 'ListBlanck', 'canmayni', 'baja', 'waths', 'ctasActivo', 'noesempre', 'excepcion_deuda',
        // Permiso para pedir a credito (en Visita, sin el solo contado).
        'acepta_credito',
    ];

    private function datosCliente(Request $request, $codAut = null)
    {
        $request->validate([
            'Nombres' => 'required|string|max:70',
            'Id' => 'required|string|max:15',
            'Latitud' => 'nullable|max:15',
            'longitud' => 'nullable|max:15',
            // Supra canal solo es ON u OFF (en la pantalla es un select).
            'SupraCanal' => 'nullable|in:ON,OFF,on,off',
        ]);
        $ci = trim($request->Id);
        $duplicado = Cliente::whereRaw('TRIM(Id) = ?', [$ci])
            ->when($codAut, fn ($q) => $q->where('Cod_Aut', '!=', $codAut))
            ->exists();
        if ($duplicado) {
            abort(422, 'Ya existe otro cliente con el CI/NIT ' . $ci);
        }

        // En el alta se completan todas las columnas; al editar solo las que llegan.
        $nuevo = $codAut === null;
        $datos = [];
        foreach ($this->camposTexto as $campo) {
            if (!$nuevo && !$request->has($campo)) continue;
            $datos[$campo] = trim((string) $request->input($campo, ''));
        }
        foreach ($this->camposNumero as $campo) {
            if (!$nuevo && !$request->has($campo)) continue;
            $valor = $request->input($campo);
            if (is_bool($valor)) $valor = $valor ? 1 : 0;
            $datos[$campo] = is_numeric($valor) ? $valor : 0;
        }
        $datos['Id'] = $ci;
        // Un cliente nuevo sin elegir queda en OFF, que es lo de casi todos.
        if (array_key_exists('SupraCanal', $datos)) {
            $datos['SupraCanal'] = strtoupper($datos['SupraCanal']) ?: ($nuevo ? 'OFF' : '');
        }
        // La zona sale del despegable; vacia se acepta (se completa al editar).
        if (array_key_exists('zona', $datos) && $datos['zona'] !== '') {
            $datos['zona'] = strtoupper($datos['zona']);
            if (!in_array($datos['zona'], self::ZONAS, true)) {
                abort(422, 'La zona ' . $datos['zona'] . ' no está en la lista');
            }
        }
        // El canal tiene que ser uno de los de su supra canal (vacio se acepta:
        // hay clientes viejos sin canal que se completan al editarlos).
        if (array_key_exists('Canal', $datos) && $datos['Canal'] !== '') {
            $datos['Canal'] = strtoupper($datos['Canal']);
            $supra = $datos['SupraCanal'] ?? trim((string) Cliente::where('Cod_Aut', $codAut)->value('SupraCanal'));
            if (!in_array($datos['Canal'], self::CANALES[$supra] ?? [], true)) {
                abort(422, 'El canal ' . $datos['Canal'] . ' no corresponde al supra canal ' . ($supra ?: '(vacío)'));
            }
        }
        // Con excepcion de deuda no se bloquea: se habilita en el momento, sin esperar al recalculo.
        if (!empty($datos['excepcion_deuda'])) {
            $datos['venta'] = 'ACTIVO';
        }
        return $datos;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $datos = $this->datosCliente($request);
        if (empty($datos['venta'])) $datos['venta'] = 'ACTIVO';
        $codAut = DB::table('tbclientes')->insertGetId($datos, 'Cod_Aut');
        // Todo cliente nuevo arranca con todos los grupos en el precio 1.
        \App\Models\ClientePrecio::completarCliente($codAut, optional($request->user())->CodAut);
        return DB::table('tbclientes')->where('Cod_Aut', $codAut)->first();
    }

    public function comentario(Request $request){
        $obs=DB::SELECT("SELECT * FROM obscliente where ci=trim($request->ci)");
        if(sizeof($obs)==0){
            DB::SELECT("INSERT INTO obscliente(ci, observacion) VALUES (trim($request->ci),'')");
            $obs=DB::SELECT("SELECT * FROM obscliente where ci=trim($request->ci)")[0];
        }
        else $obs=$obs[0];
        return $obs;
    }

    public function updateComentario(Request $request){
        $obs=DB::SELECT("UPDATE obscliente set observacion='$request->observacion' where ci='$request->ci'");
        return $obs;
    }

    // Bloqueo por deuda de cobranzas/creditos; la regla esta en DeudaCliente::bloquear
    // (lo mismo corre en Console/Kernel a las 9, 15 y 18 h de lunes a viernes).
    public function bloquear(){
        DeudaCliente::bloquear();
    }

    public function desbloq2(){
        DeudaCliente::desbloquearMenores();
    }

    public function desbloquear(Request $request){
        if($request->venta=='ACTIVO')
            $request->venta='INACTIVO';
        else
            $request->venta='ACTIVO';

        DB::SELECT("UPDATE tbclientes set venta='$request->venta' where Cod_Aut=$request->Cod_Aut");

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return DB::table('tbclientes')->where('Cod_Aut', $id)->first();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!DB::table('tbclientes')->where('Cod_Aut', $id)->exists()) {
            abort(404, 'Cliente no encontrado');
        }
        $datos = $this->datosCliente($request, $id);
        DB::table('tbclientes')->where('Cod_Aut', $id)->update($datos);
        return DB::table('tbclientes')->where('Cod_Aut', $id)->first();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function listapersonal(){
        return DB::SELECT("SELECT * from personal");
    }

    public function listaclientes(){
        // Solo las columnas que usa la vista Modifica.vue: el SELECT * del join
        // devolvia ~90 columnas por fila (2800+ filas) sin que se usaran.
        return DB::SELECT("
            SELECT c.Cod_Aut, c.Id, c.Nombres, c.Telf, c.Direccion, c.CiVend,
                   c.Latitud, c.longitud,
                   p.Nombre1, p.App1,
                   o.observacion AS obs
            FROM tbclientes c
            INNER JOIN personal p ON c.CiVend = p.ci
            LEFT JOIN obscliente o ON o.ci = TRIM(c.Id)
        ");
    }

    public function modprevent(Request $request){
        DB::SELECT("UPDATE tbclientes set CiVend='$request->vendedor' where Cod_Aut=$request->cliente_id");
        return $request;
    }
}
