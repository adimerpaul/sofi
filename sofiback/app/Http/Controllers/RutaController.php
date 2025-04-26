<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RutaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
       /* return DB::table('tbpedidos')
            ->where('idCli',$request->id)
            ->whereDate('fecha',$request->fecha)
            ->where('tipo','NORMAL')
            ->get();*/
        $resul=[];
        $list= DB::SELECT("SELECT c.CodAuto,c.CINIT,c.comanda,c.FechaEntreg,c.Importe,c.Tipago,
        (SELECT e.observacion from entregas e where e.cinit=c.CINIT and e.comanda=c.comanda order by e.estado asc limit 1 ) observacion,
        (SELECT e.estado from entregas e where e.cinit=c.CINIT and e.comanda=c.comanda order by e.estado asc limit 1 ) estado
        FROM tbctascobrar c WHERE c.CINIT='$request->id' and c.FechaEntreg='$request->fecha'
            and (c.CodAuto, c.comanda) in
                    (SELECT min(c2.CodAuto) ,c2.comanda
                          from tbctascobrar c2
                          WHERE date(c2.FechaEntreg)='$request->fecha' and c2.CINIT='$request->id' and c.placa = '".$request->user()->placa."' group by c2.comanda)

        group by c.CodAuto,c.CINIT,c.comanda,c.FechaEntreg,c.Importe,c.Tipago,c.Observacion");

        //return $list;
        foreach ($list as $r) {
            # code...
            //return $rcomanda;
            $prod=DB::SELECT("SELECT p.cod_prod,p.Producto,v.PVentUnit,v.cant,v.Monto from tbventas v inner join tbproductos p on v.cod_pro=p.cod_prod
            where v.Comanda=".$r->comanda);
            $r->detalle=$prod;
            array_push($resul,$r);
        }

        return json_encode($resul);
    }

    public function repComanda(Request $request)
    {
        $consulta='';
        if($request->placa!='TODOS'){
            $consulta="and c.placa = '".$request->placa."'";
        }

        $resul=[];
        $list= DB::SELECT("SELECT c.CodAuto,c.CINIT,c.comanda,c.FechaEntreg,c.Importe,c.Tipago,
        (SELECT e.observacion from entregas e where e.cinit=c.CINIT and e.comanda=c.comanda order by e.estado asc limit 1 ) observacion,
        (SELECT e.estado from entregas e where e.cinit=c.CINIT and e.comanda=c.comanda order by e.estado asc limit 1 ) estado
        FROM tbctascobrar c WHERE c.CINIT='$request->id' and c.FechaEntreg='$request->fecha'
            and (c.CodAuto, c.comanda) in
                    (SELECT min(c2.CodAuto) ,c2.comanda
                          from tbctascobrar c2
                          WHERE date(c2.FechaEntreg)='$request->fecha' and c2.CINIT='$request->id'  group by c2.comanda)

        group by c.CodAuto,c.CINIT,c.comanda,c.FechaEntreg,c.Importe,c.Tipago,c.Observacion");

        //return $list;
        foreach ($list as $r) {
            # code...
            $prod=DB::SELECT("SELECT p.cod_prod,p.Producto,v.PVentUnit,v.cant,v.Monto from tbventas v inner join tbproductos p on v.cod_pro=p.cod_prod
            where v.Comanda=".$r->comanda);
            $r->detalle=$prod;
            array_push($resul,$r);
        }

        return json_encode($resul);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($fecha, Request $request)
    {
       /* return DB::select("
        SELECT p.idCli,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud,p.estados
FROM tbpedidos p
INNER JOIN tbclientes c ON c.Cod_Aut=p.idCli
WHERE date(p.fecha)='".$fecha."'
GROUP BY p.idCli,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud,p.estados;
");*/
        $user= $request->user();
    return DB::select(" SELECT c.Cod_Aut,p.CINIT,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud,
        (select e.estado from entregas e
            where e.cliente_id=c.Cod_Aut and e.fechaEntreg='$fecha' order by e.estado asc limit 1  ) estado
    FROM tbctascobrar p
    INNER JOIN tbclientes c ON c.Id=p.CINIT
    WHERE date(p.FechaEntreg)='".$fecha."'
    and p.placa='".$user->placa."'
    GROUP BY c.Cod_Aut,p.CINIT,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud
    order by estado asc
    ");
    }

    public function listClienteComanda(Request $request)
    {/*
        $consulta='';
        if($request->placa!='TODOS'){
            $consulta="and p.placa = '".$request->placa."'";
        }
        if($request->grupo=='TODOS'){
            return DB::select("
            SELECT c.Cod_Aut,p.CINIT,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud,
            (select e.estado from entregas e where e.cliente_id=c.Cod_Aut and e.fechaEntreg='".$request->fecha."' order by e.estado asc limit 1  ) estado
            FROM tbctascobrar p
            INNER JOIN tbclientes c ON c.Id=p.CINIT
            WHERE date(p.FechaEntreg)='".$request->fecha."' $consulta
            GROUP BY c.Cod_Aut,p.CINIT,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud
            order by estado asc
            ");
        }else {
            switch ($request->grupo) {
                case 'CARNE POLLO':
                    $in="in (3)";
                    break;
                case 'CARNE CERDO':
                    $in="in (2)";
                    break;
                case 'PODIUM':
                    $in="in (9)";
                    break;
                case 'POLLO CERDO':
                    $in="in (2,3)";
                    break;
                default:
                    $in="not in (2,3,9)";
                break;
            }

            $sql="SELECT c.Cod_Aut,p.CINIT,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud,
            (select e.estado from entregas e where e.cliente_id=c.Cod_Aut and e.fechaEntreg='".$request->fecha."' order by e.estado asc limit 1  ) estado
            FROM tbctascobrar p
            INNER JOIN tbclientes c ON c.Id=p.CINIT
            WHERE date(p.FechaEntreg)='".$request->fecha."'
            GROUP BY c.Cod_Aut,p.CINIT,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud
            order by estado asc
            ";

            error_log($sql);
            $datos=DB::select($sql);
            $resDatos = [];
            foreach ($datos as  $value) {
                $CINIT = $value->CINIT;
                $fecha = $request->fecha;
                $sql="SELECT c.comanda,v.cod_pro,p.cod_grup,g.Cod_pdr
                from tbctascobrar c
                inner join tbventas v on c.comanda = v.comanda
                inner join tbproductos p on v.cod_pro = p.cod_prod
                inner join tbgrupos g on p.cod_grup = g.cod_grup
                where c.CINIT='$CINIT'
                AND c.FechaEntreg = '$fecha'
                AND g.Cod_pdr $in
                limit 1
            ";
//                error_log($sql);
                $comanda=DB::select($sql);
                if(count($comanda)>0){
                    $resDatos[] = $value;
                }
            }
            return $resDatos;
        }
*/
$parametros = [$request->fecha, $request->fecha];
$condiciones = ["DATE(p.FechaEntreg) = ?"];

if ($request->placa !== 'TODOS') {
    $condiciones[] = "p.placa = ?";
    $parametros[] = $request->placa;
}

$sql = "SELECT c.Cod_Aut, p.CINIT, c.Id, c.Nombres, c.Telf, c.Direccion, c.Latitud, c.longitud,
               (SELECT e.estado FROM entregas e
                WHERE e.cliente_id = c.Cod_Aut
                AND e.fechaEntreg = ?
                ORDER BY e.estado ASC LIMIT 1) AS estado
        FROM tbctascobrar p
        INNER JOIN tbclientes c ON c.Id = p.CINIT
        WHERE " . implode(" AND ", $condiciones) . "
        GROUP BY c.Cod_Aut, p.CINIT, c.Id, c.Nombres, c.Telf, c.Direccion, c.Latitud, c.longitud
        ORDER BY estado ASC";

$datos = DB::select($sql, $parametros);

return ($request->grupo === 'TODOS') ? $datos : $this->filtrarPorGrupo($datos, $request);
}

public function filtrarPorGrupo($datos, $request) {
$grupos = [
    'CARNE POLLO'   => [3],
    'CARNE CERDO'   => [2],
    'PODIUM'        => [9],
    'POLLO CERDO'   => [2, 3],
    'OTROS'         => ['NOT IN', [2, 3, 9]]
];

$grupoFiltro = $grupos[$request->grupo] ?? ['NOT IN', [2, 3, 9]];
$operador = is_array($grupoFiltro) ? "IN" : $grupoFiltro[0];
$valores = implode(",", is_array($grupoFiltro) ? $grupoFiltro : $grupoFiltro[1]);

$CINITs = array_column($datos, 'CINIT');
if (empty($CINITs)) return [];

$sqlGrupo = "SELECT DISTINCT c.CINIT
             FROM tbctascobrar c
             INNER JOIN tbventas v ON c.comanda = v.comanda
             INNER JOIN tbproductos p ON v.cod_pro = p.cod_prod
             INNER JOIN tbgrupos g ON p.cod_grup = g.cod_grup
             WHERE c.CINIT IN (" . implode(",", array_fill(0, count($CINITs), "?")) . ")
             AND c.FechaEntreg = ?
             AND g.Cod_pdr $operador ($valores)";
//return ($sqlGrupo, array_merge($CINITs, [$request->fecha]));
$clientesFiltrados = DB::select($sqlGrupo, array_merge($CINITs, [$request->fecha]));
//return $clientesFiltrados;
$clientesFiltradosIds = array_column($clientesFiltrados, 'CINIT');
//return $clientesFiltradosIds;

return array_values(array_filter($datos, fn($item) => in_array($item->CINIT, $clientesFiltradosIds)));
 }

    public function listEntrega($fecha){
        return DB::select(" SELECT c.Cod_Aut,p.CINIT,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud,
        (select e.estado from entregas e where e.cliente_id=c.Cod_Aut and e.fechaEntreg='$fecha' order by e.estado asc limit 1  ) estado
        FROM tbctascobrar p
        INNER JOIN tbclientes c ON c.Id=p.CINIT
        WHERE date(p.FechaEntreg)='".$fecha."'
        GROUP BY c.Cod_Aut,p.CINIT,c.Id,c.Nombres,c.Telf,c.Direccion,c.Latitud,c.longitud
        order by estado asc
        ");
    }

    public function listRuta(Request $request)
    {
        $consulta='';
        if($request->placa!='TODOS'){
            $consulta="and c.placa = '".$request->placa."'";
        }
        $user= $request->user();
    return DB::select(" SELECT e.hora,c.CINIT,l.Nombres,c.comanda,c.placa,e.despachador,e.estado,e.distancia,e.pago,e.observacion, l.Latitud latitud,l.longitud
    from tbctascobrar c inner join tbclientes l on c.CINIT=l.Id
    left join entregas e on e.comanda=c.comanda where c.FechaEntreg='$request->fecha'
    and (c.CodAuto, c.comanda) in
                    (SELECT min(c2.CodAuto) ,c2.comanda
                          from tbctascobrar c2
                          WHERE date(c2.FechaEntreg)='$request->fecha' $consulta group by c2.comanda)
   ORDER BY
    CASE
        WHEN e.hora IS NULL OR e.hora = '' THEN 1
        ELSE 0
    END,
    e.hora ASC;
    ");
    }

    public function reportContable($fecha){
        return DB::SELECT("SELECT e.despachador,sum(pago) cobro,
        (select sum(monto) from entregas e2 where e2.despachador=e.despachador and e2.tipago='CONTADO' and e2.fechaEntreg='$fecha') tcontado,
        (select sum(monto) from entregas e2 where e2.despachador=e.despachador and e2.tipago='CRÉDITO' and e2.fechaEntreg='$fecha') tcredito
        from entregas e where fechaEntreg='$fecha' and estado='ENTREGADO' group by e.despachador;");
    }

    public function listPedidos(Request $request){
        return DB::SELECT("SELECT c.CINIT,l.Nombres,c.comanda,c.Tipago,p.Nombre1,p.App1
          from tbctascobrar c inner join tbclientes l on c.CINIT=l.Id inner join personal p on p.ci=c.CIFunc
        where c.FechaEntreg='$request->fecha' order by c.comanda desc;");
    }

    public function resumenEntrega(Request $request){
       /* return DB::SELECT("SELECT c.placa,c.fechaEntreg, COUNT(DISTINCT(c.comanda)) total,
        (select count(*) from entregas e
            WHERE e.fechaEntreg=c.FechaEntreg and c.placa=e.placa and e.estado='ENTREGADO') entreg,
        (select count(*) from entregas e
            WHERE e.fechaEntreg=c.FechaEntreg and c.placa=e.placa and e.estado='NO ENTREGADO') noentreg,
        (select count(*) from entregas e
            WHERE e.fechaEntreg=c.FechaEntreg and c.placa=e.placa and e.estado='RECHAZADO') rechazado
         from tbctascobrar c where c.FechaEntreg='$request->fecha' and c.placa!='' GROUP by c.placa,c.fechaEntreg ");*/

         return DB::SELECT("SELECT c.placa,c.fechaEntreg, COUNT(DISTINCT(c.CINIT)) total,
	        (Select count(DISTINCT(e.cinit)) from entregas e where e.placa=c.placa and e.estado='ENTREGADO' and e.fecha='$request->fecha' ) AS entreg,
    	(Select count(DISTINCT(e.cinit)) from entregas e where e.placa=c.placa and e.estado='NO ENTREGADO' and e.fecha='$request->fecha' ) AS noentreg,
        	(Select count(DISTINCT(e.cinit)) from entregas e where e.placa=c.placa and e.estado='RECHAZADO' and e.fecha='$request->fecha' ) AS rechazado
         from tbctascobrar c where c.FechaEntreg='$request->fecha' and c.placa!='' GROUP by c.placa,c.fechaEntreg;");
    }

    public function reporteDes(Request $request){
        $resul=[];
        $list= DB::SELECT("SELECT c.CINIT,l.Nombres,c.comanda,c.Importe,c.placa,e.despachador,c.Tipago,e.observacion
        from tbctascobrar c inner join tbclientes l on c.CINIT=l.Id inner join entregas e on e.comanda=c.comanda
         where c.FechaEntreg='$request->fecha' and e.estado='ENTREGADO' and e.placa='".$request->user()->placa."' order by c.CINIT");

        foreach ($list as $r) {
            # code...
            $prod=DB::SELECT("SELECT p.cod_prod,p.Producto,v.PVentUnit,v.cant,v.Monto from tbventas v inner join tbproductos p on v.cod_pro=p.cod_prod
            where v.Comanda=".$r->comanda);
            $r->detalle=$prod;
            array_push($resul,$r);
        }

        return json_encode($resul);
    }
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {

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
}
