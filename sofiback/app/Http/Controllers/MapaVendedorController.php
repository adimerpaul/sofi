<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\MisVisita;
use App\Models\Pedido;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

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

        // Solo clientes del día seleccionado
        $clientes = Cliente::whereIn('CiVend', $ciUserArray)
//            ->where($campoDia, 1)
            ->select('Cod_Aut', 'Nombres', 'Direccion', 'Latitud', 'longitud', 'CiVend', 'lu', 'ma', 'mi', 'ju', 'vi', 'sa', 'do')
            ->get();

        $clientesIdArray = $clientes->pluck('Cod_Aut')->toArray();

        // Última visita por cliente en la fecha
        $sub = MisVisita::selectRaw('MAX(id) as id')
            ->whereIn('cliente_id', $clientesIdArray)
            ->whereDate('fecha', $fecha)
            ->groupBy('cliente_id');

        $misVisitas = MisVisita::whereIn('id', $sub)->select('estado', 'cliente_id')->get();

        $userRes = [];

        $diaDeFecha = date('w', strtotime($fecha));
        $diasCampos = ['do', 'lu', 'ma', 'mi', 'ju', 'vi', 'sa'];
        $campoDia = $diasCampos[$diaDeFecha];

        foreach ($users as $user) {
            // Filtrar clientes de este usuario y que tengan marcado el día como activo
            $clientesUser = $clientes->filter(function ($cliente) use ($user, $campoDia) {
                return $cliente->CiVend == $user->ci && $cliente->$campoDia == 1;
            });

            $clientesUserRes = [];
            foreach ($clientesUser as $cliente) {
                $ultimaVisita = $misVisitas->where('cliente_id', $cliente->Cod_Aut)->first();
                $clientesUserRes[] = [
                    'Cod_Aut' => $cliente->Cod_Aut,
                    'Nombres' => $cliente->Nombres,
                    'Direccion' => $cliente->Direccion,
                    'Latitud' => $cliente->Latitud,
                    'longitud' => $cliente->longitud,
                    'estado' => isset($ultimaVisita) ? $ultimaVisita->estado : null
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
}
