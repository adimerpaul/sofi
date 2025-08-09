<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoSofia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobilController extends Controller{
    public function reporteTotalProductos(Request $request)
    {
        // fecha en formato YYYY-MM-DD; si no envían, toma hoy
        $fecha = $request->input('fecha', now()->toDateString());

        $tbpedidos  = (new Pedido)->getTable();      // "tbpedidos"
        $tbproductos = (new \App\Models\Producto)->getTable(); // "tbproductos"

        $rows = Pedido::query()
            ->whereDate("$tbpedidos.fecha", $fecha)
            ->where("$tbpedidos.estado", 'ENVIADO')
            ->where("$tbpedidos.tipo", 'NORMAL')
            ->join("$tbproductos as prod", "prod.cod_prod", "=", "$tbpedidos.cod_prod")
            ->groupBy("$tbpedidos.cod_prod", "prod.Producto")
            ->select([
                "$tbpedidos.cod_prod",
                DB::raw("COALESCE(prod.Producto, '') as nombre"),
                DB::raw("SUM($tbpedidos.Cant) as total_cantidad"),
                DB::raw("SUM($tbpedidos.subtotal) as total_subtotal"),
            ])
            ->orderByDesc('total_cantidad')
            ->get();

        return response()->json([
            'status' => 'success',
            'fecha'  => $fecha,
            'data'   => $rows,
        ]);
    }
    function importPedido(Request $request){
        $fecha = $request->input('fecha');
        $color = $request->input('color');
        $pedidos = Pedido::with([
            'cliente:id,Cod_Aut,Nombres,Direccion,Telf,zona',
            'user:CodAut,Nombre1,App1',
        ])
            ->whereDate('fecha', $fecha)
            ->where('color', $color)
            ->where('estado', 'ENVIADO')
            ->where('tipo', 'NORMAL')
            ->where('bonificacion', 0)
            ->select(
                'NroPed', 'fecha', 'idCli', 'CIfunc', 'estado', 'fact',
                'comentario', 'pago', 'placa', 'horario', 'colorStyle',
                'cod_prod', 'precio', 'Cant', 'Canttxt', 'subtotal','bonificacion', 'bonificacionAprovacion','bonificacionId'
            )
            ->orderBy('NroPed')
            ->get();
//        return $pedidos;

        $resPedido = $pedidos->groupBy('NroPed')->map(function ($items) {
            $pedido = $items->first();
            $productos = $items->map(function ($p) {
                return [
                    'Nroped' => $p->NroPed,
                    'cod_prod' => $p->cod_prod,
                    'producto' => $p->producto->Producto ?? '',
                    'precio' => $p->precio,
                    'Cant' => $p->Cant,
                    'Canttxt' => $p->Canttxt,
                    'subtotal' => $p->subtotal,
                ];
            });
            return [
                'pedido' => $pedido,
                'productos' => $productos,
                'bonificacionCliente' => $pedido->bonificacionId ? Cliente::where('Cod_Aut', $pedido->bonificacionId)
                    ->select('Cod_Aut', 'Nombres', 'Direccion', 'Telf', 'zona')
                    ->first() : null
            ];
        })->values();
        return response()->json([
            'status' => 'success',
            'data' => $resPedido,
            'message' => 'Pedidos importados correctamente'
        ]);
    }
    public function exportarPedidosFlutter(Request $request)
    {
        $pedidos = $request->input('pedidos', []);

        foreach ($pedidos as $pedidoData) {
            $pedido = PedidoSofia::updateOrCreate(
                ['nro_pedido' => $pedidoData['nro_pedido']],
                [
                    'fecha' => $pedidoData['fecha'],
                    'cliente_id' => $pedidoData['cliente_id'],
                    'cliente_nombre' => $pedidoData['cliente_nombre'],
                    'cliente_direccion' => $pedidoData['cliente_direccion'],
                    'cliente_telefono' => $pedidoData['cliente_telefono'],
                    'cliente_zona' => $pedidoData['cliente_zona'],
                    'user_id' => $pedidoData['user_id'],
                    'user_nombre' => $pedidoData['user_nombre'],
                    'user_apellido' => $pedidoData['user_apellido'],
                    'estado' => $pedidoData['estado'],
                    'fact' => $pedidoData['fact'],
                    'comentario' => $pedidoData['comentario'],
                    'pago' => $pedidoData['pago'],
                    'placa' => $pedidoData['placa'],
                    'horario' => $pedidoData['horario'],
                    'colorStyle' => $pedidoData['colorStyle'],
                    'cod_prod' => $pedidoData['cod_prod'],
                    'precio' => $pedidoData['precio'],
                    'cantidad' => $pedidoData['cantidad'],
                    'cantidad_texto' => $pedidoData['cantidad_texto'],
                    'subtotal' => $pedidoData['subtotal'],
                    'bonificacion' => $pedidoData['bonificacion'],
                    'bonificacion_aprobacion' => $pedidoData['bonificacion_aprobacion'],
                    'bonificacion_id' => $pedidoData['bonificacion_id'],
                    'confirmado' => 1
                ]
            );

            // Limpiar productos anteriores
            PedidoDetalle::where('pedido_id', $pedido->nro_pedido)->delete();

            // Insertar productos
            foreach ($pedidoData['productos'] as $producto) {
                PedidoDetalle::create([
                    'pedido_id' => $pedido->nro_pedido,
                    'cod_prod' => $producto['cod_prod'],
                    'nombre' => $producto['nombre'],
                    'precio' => $producto['precio'],
                    'cantidad' => $producto['cantidad'],
                    'cantidad_texto' => $producto['cantidad_texto'],
                    'subtotal' => $producto['subtotal'],
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pedidos exportados correctamente'
        ]);
    }
}
