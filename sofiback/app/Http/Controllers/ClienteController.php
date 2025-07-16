<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteController extends Controller{
    function personalCliente(){
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
        $Ids = array_map(function ($id) {
            return "'" . addslashes($id) . "'"; // Escapa caracteres especiales y añade comillas
        }, $Ids);

        $visitas = DB::select("SELECT * FROM misvisitas WHERE cliente_id IN (".implode(',', $codAuts).") AND fecha = '".date('Y-m-d')."'");


        $cuentas = DB::select("SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) as totdeuda, MIN(c.FechaEntreg) as fechaminima, count(*) as cantdeuda, c.CINIT FROM tbctascobrar c WHERE c.CINIT IN (".implode(',', $Ids).") AND c.Nrocierre=0 AND c.Acuenta=0 GROUP BY c.CINIT");

        error_log(json_encode($cuentas));

        $misClientes->map(function ($cliente) use ($visitas, $cuentas) {
            $cliente->tipo = null;
            if (isset($visitas)) {
                foreach ($visitas as $visita) {
                    if ($cliente->Cod_Aut == $visita->cliente_id) {
                        $cliente->tipo = $visita->estado;
                        break;
                    }
                }
            }
            $cliente->totdeuda = 0;

            if (isset($cuentas)) {
                foreach ($cuentas as $cuenta) {
                    if ($cliente->Id == $cuenta->CINIT) {
                        $cliente->totdeuda += $cuenta->totdeuda;
                    }
                }
            }

            $cliente->fechaminima = null;

            if (isset($cuentas)) {
                foreach ($cuentas as $cuenta) {
                    if ($cliente->Id == $cuenta->CINIT) {
                        if ($cliente->fechaminima == null || $cliente->fechaminima > $cuenta->fechaminima) {
                            $cliente->fechaminima = $cuenta->fechaminima;
                        }
                    }
                }
            }


            $cliente->cantdeuda = 0;

            if (isset($cuentas)) {
                foreach ($cuentas as $cuenta) {
                    if ($cliente->Id == $cuenta->CINIT) {
                        $cliente->cantdeuda++;
                    }
                }
            }
        });

        $misClientes = $misClientes->sortByDesc('tipo')->values()->all();

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
    public function filtrarlista(Request $request) {
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
             WHERE p2.idCli = c.Cod_Aut AND p2.CIfunc = ?) as ultima_compra
        FROM tbclientes c
        LEFT JOIN tbpedidos p
            ON p.idCli = c.Cod_Aut
            AND p.CIfunc = ?
            AND p.fecha BETWEEN ? AND ?
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
             WHERE p2.idCli = c.Cod_Aut AND p2.CIfunc = ?) as ultima_compra
        FROM tbclientes c
        LEFT JOIN tbpedidos p
            ON p.idCli = c.Cod_Aut
            AND p.CIfunc = ?
            AND p.fecha BETWEEN ? AND ?
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
        return DB::select("
        SELECT *,

       '' as tipo,
        (SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda))
        FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0) as totdeuda
        ,(SELECT count(*) FROM tbctascobrar WHERE CINIT=tbclientes.Id AND Nrocierre=0  and Acuenta=0) as cantdeuda
        FROM tbclientes

        ");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
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

    public function bloquear(){
        DB::SELECT("UPDATE tbclientes set venta='ACTIVO'");

        DB::SELECT("UPDATE tbclientes set venta='INACTIVO'
        where Id not in ('4041584010','5722359015','7903071014','7313393','2763010019','387115028','7279536013','6656467','2773242015','3509547','5720977','7205489','3501059017','3544875019','2762953013','4034692','8560810','3513987','168266022','341104028','5068381','4525672011','370194024','8025247') and
          ((SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda) )
            FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0 and (c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda))>5 )>12000
        or (SELECT DATEDIFF( curdate(), (select min(c.FechaEntreg) from tbctascobrar c where c.CINIT =tbclientes.Id and c.Nrocierre=0 and Acuenta=0 and (c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda))>=5)))>9 )");
    }

    public function desbloq2(){
        DB::SELECT("UPDATE tbclientes set venta='ACTIVO'
        where (SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0)<12000
        or (SELECT sum(c.Importe-(SELECT sum(c2.Acuenta) from tbctascobrar c2 where c2.comanda=c.comanda)) FROM tbctascobrar c WHERE c.CINIT=tbclientes.Id and c.Nrocierre=0 and Acuenta=0) is null
         ");
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
        //
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
        //
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
        return DB::SELECT("SELECT *,(select o.observacion from obscliente o where o.ci=trim(c.Id))as obs from tbclientes c inner join personal p on c.CiVend=p.ci");
    }

    public function modprevent(Request $request){
        DB::SELECT("UPDATE tbclientes set CiVend='$request->vendedor' where Cod_Aut=$request->cliente_id");
        return $request;
    }
}
