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
        'NORTE', 'SUD', 'CENTRO', 'PROVINCIA', 'COLQUIRI', 'HUANUNI', 'LLALLAGUA', 'CARACOLLO',
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
