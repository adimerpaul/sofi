<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pedido;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class MapaVendedorController extends Controller{
    function mapaVendedor(Request $request){
        $fecha = $request->fecha;
        $pedidos = Pedido::whereDate('fecha', $fecha)
            ->select('NroPed', 'fecha', 'idCli', 'CIfunc', 'estado','fact','comentario','pago')
//            ->where('estado', 'ENVIADO')
            ->with(['cliente' => function ($query) {
                $query->select('Cod_Aut', 'Nombres', 'Direccion', 'Telf','zona','Latitud','longitud');
            }])
            ->with(['user' => function ($query) {
                $query->select('CodAut', 'Nombre1', 'App1','ci');
            }])
            ->groupBy('NroPed', 'fecha', 'idCli', 'CIfunc', 'estado','fact','comentario','pago')
            ->orderBy('NroPed')
            ->where('tipo','NORMAL')
            ->get();
        $pedidosAll = Pedido::whereDate('fecha', $fecha)
            ->select('NroPed','cod_prod','precio','Cant','Canttxt','subtotal')
            ->with(['producto' => function ($query) {
                $query->select('cod_prod', 'Producto');
            }])
            ->where('tipo','NORMAL')
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
}
