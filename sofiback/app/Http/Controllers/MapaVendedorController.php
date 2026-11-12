<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\MisVisita;
use App\Models\Pedido;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapaVendedorController extends Controller{
    function mapaVendedor(Request $request){
        $fecha = $request->fecha;
        $tipo = $request->tipo;
        $pedidos = Pedido::whereDate('fecha', $fecha)
            ->select('NroPed', 'fecha', 'idCli', 'CIfunc', 'estado','fact','comentario','pago','tipo')
//            ->where('estado', 'ENVIADO')
            ->with(['cliente' => function ($query) {
                $query->select('Cod_Aut', 'Nombres', 'Direccion', 'Telf','zona', 'Latitud','longitud');
            }])
            ->with(['user' => function ($query) {
                $query->select('CodAut', 'Nombre1', 'App1','ci');
            }])
            ->groupBy('NroPed', 'fecha', 'idCli', 'CIfunc', 'estado','fact','comentario','pago','tipo')
            ->orderBy('NroPed')
            ->whereRaw('tipo like "%'.$tipo.'%"')
            ->get();
        $pedidosAll = Pedido::whereDate('fecha', $fecha)
            ->select('NroPed','cod_prod','precio','Cant','Canttxt','subtotal')
            ->with(['producto' => function ($query) {
                $query->select('cod_prod', 'Producto');
            }])
            ->whereRaw('tipo like "%'.$tipo.'%"')
            ->get();

        $resPedido=[];

        foreach ($pedidos as $p){
            $productos=$pedidosAll->where('NroPed',$p->NroPed);
            $resProducto=[];
            foreach ($productos as $pro){
                $resProducto[]=[
                    'Nroped'=>$pro->NroPed,
                    'cod_prod'=>$pro->cod_prod,
                    'producto'=>isset($pro->producto->Producto)?$pro->producto->Producto:'',
                    'precio'=>$pro->precio,
                    'Cant'=>$pro->Cant,
                    'Canttxt'=>$pro->Canttxt,
                    'subtotal'=>$pro->subtotal
                ];
            }
            $resPedido[]=[
                'pedido'=>$p,
                'productos'=>$productos
            ];
        }

        $users = [];

        foreach ($resPedido as $p) {
            // Verifica si el CodAut ya existe en el array $users
            if (!in_array($p['pedido']['CIfunc'], array_column($users, 'CodAut'))) {
                // Filtra los pedidos correspondientes a este usuario
                $pedidosAllUser = array_filter($resPedido, function ($pedido) use ($p) {
                    return $pedido['pedido']['CIfunc'] == $p['pedido']['CIfunc'];
                });

                // Estructura los datos correctamente en $users
                $users[] = [
                    'CodAut' => $p['pedido']['CIfunc'],
                    'nombreCompleto' => trim($p['pedido']['user']['Nombre1']) . ' ' . trim($p['pedido']['user']['App1']),
                    'pedidos' => [
                        'cantidad' => count($pedidosAllUser),
                        'enviados' => count(array_filter($pedidosAllUser, function ($pedido) {
                            return $pedido['pedido']['estado'] == 'ENVIADO';
                        })),
                        'creados' => count(array_filter($pedidosAllUser, function ($pedido) {
                            return $pedido['pedido']['estado'] == 'CREADO';
                        })),
                        'pedidos' => array_values($pedidosAllUser),
                    ]
                ];
            }
        }

        $data = [
//            'pedidos' => $resPedido,
            'users' => $users
        ];
        return $data;
    }
    function mapaVendedorVisita(Request $request)
    {
        $fecha = $request->fecha;
        $tipo = $request->tipo;


        // Vendedores que realizaron algún pedido ese día
        $idUserArray = Pedido::whereDate('fecha', $fecha)
            ->orderBy('CIfunc')
            ->select('CIfunc')
            ->whereRaw('tipo like ?', ["%$tipo%"])
            ->distinct()
            ->pluck('CIfunc')
            ->toArray();

        $users = User::whereIn('CodAut', $idUserArray)
            ->select('CodAut', 'Nombre1', 'App1', 'ci')
            ->get();

        $ciUserArray = $users->pluck('ci')->toArray();

        $clientes = Cliente::whereIn('CiVend', $ciUserArray)
            ->select('Cod_Aut', 'Id', 'Nombres', 'Direccion', 'Telf', 'zona', 'Latitud', 'longitud', 'CiVend',
                'lu', 'ma', 'mi', 'ju', 'vi', 'sa', 'do')
            ->get();

        $clientesIdArray = $clientes->pluck('Cod_Aut')->toArray();

        // Última visita por cliente en la fecha
        $sub = MisVisita::selectRaw('MAX(id) as id')
            ->whereIn('cliente_id', $clientesIdArray)
            ->whereDate('fecha', $fecha)
            ->groupBy('cliente_id');

        $misVisitas = MisVisita::whereIn('id', $sub)
            ->select('estado', 'cliente_id', 'hora', 'observacion', 'distancia')
            ->get()
            ->keyBy('cliente_id');

        // Pedidos del dia por cliente (del tipo filtrado): numeros, tipos, total y hora.
        $pedidosDia = DB::table('tbpedidos')
            ->whereDate('fecha', $fecha)
            ->whereNull('deleted_at')
            ->whereIn('idCli', $clientesIdArray ?: [0])
            ->whereRaw('tipo like ?', ["%$tipo%"])
            ->groupBy('idCli')
            ->get([
                'idCli',
                DB::raw('GROUP_CONCAT(DISTINCT NroPed ORDER BY NroPed) as nros'),
                DB::raw('GROUP_CONCAT(DISTINCT tipo) as tipos'),
                DB::raw('COUNT(*) as lineas'),
                DB::raw('SUM(subtotal) as total'),
                DB::raw('MIN(hora) as hora'),
            ])
            ->keyBy('idCli');

        $userRes = [];

        $diaDeFecha = date('w', strtotime($fecha));
        $diasCampos = ['do', 'lu', 'ma', 'mi', 'ju', 'vi', 'sa'];
        $diasNombres = ['do' => 'Do', 'lu' => 'Lu', 'ma' => 'Ma', 'mi' => 'Mi', 'ju' => 'Ju', 'vi' => 'Vi', 'sa' => 'Sa'];
        $campoDia = $diasCampos[$diaDeFecha];

        foreach ($users as $user) {
            // De la ruta del dia (dia marcado) o fuera de ruta: no le toca hoy
            // pero se lo visito o se le hizo pedido igual.
            $clientesUser = $clientes->filter(function ($cliente) use ($user, $campoDia, $misVisitas, $pedidosDia) {
                if ($cliente->CiVend != $user->ci) return false;
                return $cliente->$campoDia == 1
                    || isset($misVisitas[$cliente->Cod_Aut])
                    || isset($pedidosDia[$cliente->Cod_Aut]);
            });

            $clientesUserRes = [];
            foreach ($clientesUser as $cliente) {
                $visita = $misVisitas[$cliente->Cod_Aut] ?? null;
                $pedido = $pedidosDia[$cliente->Cod_Aut] ?? null;
                $estado = $visita ? $visita->estado : null;
                // Pedido cargado sin visita marcada: cuenta como pedido.
                if (!$estado && $pedido) $estado = 'PEDIDO';
                $dias = [];
                foreach ($diasNombres as $campo => $nombre) {
                    if ($cliente->$campo == 1) $dias[] = $nombre;
                }
                $clientesUserRes[] = [
                    'Cod_Aut' => $cliente->Cod_Aut,
                    'Id' => trim((string) $cliente->Id),
                    'Nombres' => trim((string) $cliente->Nombres),
                    'Direccion' => trim((string) $cliente->Direccion),
                    'Telf' => trim((string) $cliente->Telf),
                    'zona' => trim((string) $cliente->zona),
                    'Latitud' => $cliente->Latitud,
                    'longitud' => $cliente->longitud,
                    'estado' => $estado,
                    'en_ruta' => $cliente->$campoDia == 1,
                    'dias' => implode(' ', $dias),
                    'visita_hora' => $visita ? $visita->hora : null,
                    'visita_obs' => $visita ? trim((string) $visita->observacion) : null,
                    'visita_distancia' => $visita ? $visita->distancia : null,
                    'pedido_nros' => $pedido ? $pedido->nros : null,
                    'pedido_tipos' => $pedido ? $pedido->tipos : null,
                    'pedido_lineas' => $pedido ? (int) $pedido->lineas : 0,
                    'pedido_total' => $pedido ? round((float) $pedido->total, 2) : 0,
                    'pedido_hora' => $pedido ? $pedido->hora : null,
                ];
            }

            $userRes[] = [
                'CodAut' => $user->CodAut,
                'nombreCompleto' => trim($user->Nombre1) . ' ' . trim($user->App1),
                'ci' => $user->ci,
                'clientes' => $clientesUserRes
            ];
        }

        return [
            'users' => $userRes
        ];
    }

    /**
     * Registro de visitas del dia en Excel: cada marca de misvisitas por hora
     * (no solo la ultima por cliente), con "no visitado" cuando la marca se
     * hizo a mas de 100 m del cliente y si el cliente estaba en ruta ese dia.
     * Filtros opcionales: vendedor (CodAut) y ruta (TODOS, RUTA, FUERA).
     */
    function excelVisitas(Request $request)
    {
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->fecha) ? $request->fecha : date('Y-m-d');
        $vendedor = (int) $request->vendedor;
        $ruta = in_array($request->ruta, ['RUTA', 'FUERA']) ? $request->ruta : 'TODOS';
        $campoDia = ['do', 'lu', 'ma', 'mi', 'ju', 'vi', 'sa'][date('w', strtotime($fecha))];

        $visitas = DB::table('misvisitas as v')
            ->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'v.cliente_id')
            ->leftJoin('personal as p', 'p.CodAut', '=', 'v.personal_id')
            ->whereDate('v.fecha', $fecha)
            ->when($vendedor, function ($q) use ($vendedor) {
                $q->where('v.personal_id', $vendedor);
            })
            ->when($ruta === 'RUTA', function ($q) use ($campoDia) {
                $q->where("c.$campoDia", 1);
            })
            ->when($ruta === 'FUERA', function ($q) use ($campoDia) {
                $q->where(function ($w) use ($campoDia) {
                    $w->whereNull("c.$campoDia")->orWhere("c.$campoDia", '<>', 1);
                });
            })
            ->orderBy('v.hora')
            ->orderBy('v.id')
            ->get([
                'v.hora', 'v.estado', 'v.distancia', 'v.observacion',
                DB::raw('TRIM(c.Nombres) as cliente'),
                DB::raw("TRIM(CONCAT(TRIM(p.Nombre1), ' ', TRIM(p.App1))) as personal"),
                DB::raw("c.$campoDia as en_ruta"),
            ]);

        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Visitas');
        $hoja->setCellValue('A1', 'Sofia - Visitas del ' . date('d/m/Y', strtotime($fecha))
            . ($ruta === 'RUTA' ? ' (ruta normal)' : ($ruta === 'FUERA' ? ' (fuera de ruta)' : '')));
        $hoja->mergeCells('A1:I1');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $hoja->fromArray(['No', 'HORA', 'CLIENTE', 'ESTADO', 'PERSONAL', 'DISTANCIA',
            'NO VISITADO = DISTANCIA >100 Metros', 'OBSERVACION', 'EN RUTA/ FUERA DE RUTA'], null, 'A2');
        $hoja->getStyle('A2:I2')->getFont()->setBold(true);
        $hoja->getStyle('A2:I2')->getAlignment()->setWrapText(true)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        foreach (['G2', 'I2'] as $celda) {
            $hoja->getStyle($celda)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFFF00');
        }

        $fila = 3;
        foreach ($visitas as $i => $v) {
            $distancia = $v->distancia !== null ? round((float) $v->distancia, 1) : null;
            $lejos = $distancia !== null && $distancia > 100;
            $hoja->fromArray([
                $i + 1,
                $v->hora,
                $v->cliente,
                $v->estado,
                $v->personal,
                $distancia,
                $lejos ? 'NO VISITADO' : '',
                trim((string) $v->observacion),
                $v->en_ruta == 1 ? 'EN RUTA' : 'FUERA DE RUTA',
            ], null, 'A' . $fila);
            if ($lejos) {
                $hoja->getStyle("G$fila")->getFont()->setBold(true)->getColor()->setRGB('C00000');
            }
            if ($v->en_ruta != 1) {
                $hoja->getStyle("I$fila")->getFont()->getColor()->setRGB('7030A0');
            }
            $fila++;
        }
        $ultima = max(2, $fila - 1);
        $hoja->getStyle("A2:I$ultima")->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $hoja->getStyle("F3:F$ultima")->getNumberFormat()->setFormatCode('#,##0.0');
        foreach (['A' => 6, 'B' => 11, 'C' => 45, 'D' => 12, 'E' => 26, 'F' => 12, 'G' => 20, 'H' => 24, 'I' => 18] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
        $hoja->freezePane('A3');
        $hoja->setAutoFilter("A2:I$ultima");

        return response()->streamDownload(function () use ($libro) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save('php://output');
        }, 'visitas_' . $fecha . '.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
