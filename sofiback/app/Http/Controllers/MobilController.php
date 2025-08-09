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
        $payload = $request->input('pedidos', []);
//        error_log(json_encode($payload));

        if (!is_array($payload) || empty($payload)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se recibieron pedidos para exportar'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $countPedidos = 0;
            $countDetalles = 0;

            foreach ($payload as $pedidoData) {
                // Campos mínimos
                $nro = $pedidoData['nro_pedido'] ?? null;
                if (!$nro) {
                    throw new \Exception('Falta nro_pedido en un registro.');
                }

                $pedido = PedidoSofia::updateOrCreate(
                    ['nro_pedido' => $nro],
                    [
                        'fecha' => $pedidoData['fecha'] ?? null,
                        'cliente_id' => $pedidoData['cliente_id'] ?? null,
                        'cliente_nombre' => $pedidoData['cliente_nombre'] ?? '',
                        'cliente_direccion' => $pedidoData['cliente_direccion'] ?? '',
                        'cliente_telefono' => $pedidoData['cliente_telefono'] ?? '',
                        'cliente_zona' => $pedidoData['cliente_zona'] ?? '',
                        'user_id' => $pedidoData['user_id'] ?? null,
                        'user_nombre' => $pedidoData['user_nombre'] ?? '',
                        'user_apellido' => $pedidoData['user_apellido'] ?? '',
                        'estado' => $pedidoData['estado'] ?? 'ENVIADO',
                        'fact' => $pedidoData['fact'] ?? '',
                        'comentario' => $pedidoData['comentario'] ?? '',
                        'pago' => $pedidoData['pago'] ?? 0,
                        'placa' => $pedidoData['placa'] ?? '',
                        'horario' => $pedidoData['horario'] ?? '',
                        'colorStyle' => $pedidoData['colorStyle'] ?? '',
                        'cod_prod' => $pedidoData['cod_prod'] ?? null,
                        'precio' => $pedidoData['precio'] ?? 0,
                        'cantidad' => $pedidoData['cantidad'] ?? 0,
                        'cantidad_texto' => $pedidoData['cantidad_texto'] ?? '',
                        'subtotal' => $pedidoData['subtotal'] ?? 0,
                        'bonificacion' => $pedidoData['bonificacion'] ?? 0,
                        'bonificacion_aprobacion' => $pedidoData['bonificacion_aprobacion'] ?? null,
                        'bonificacion_id' => $pedidoData['bonificacion_id'] ?? null,
                        'confirmado' => 1,
                    ]
                );
                $countPedidos++;

                // Borra detalles previos del mismo nro_pedido
                PedidoDetalle::where('pedido_id', $pedido->nro_pedido)->delete();

                $productos = $pedidoData['productos'] ?? [];
                foreach ($productos as $prod) {
                    PedidoDetalle::create([
                        'pedido_id' => $pedido->nro_pedido,
                        'cod_prod' => $prod['cod_prod'] ?? '',
                        'nombre' => $prod['nombre'] ?? '',
                        'precio' => $prod['precio'] ?? 0,
                        'cantidad' => $prod['cantidad'] ?? 0,
                        'peso' => $prod['peso'] ?? 0, // importante si la columna no admite null
                        'cantidad_texto' => $prod['cantidad_texto'] ?? '',
                        'subtotal' => $prod['subtotal'] ?? 0,
                    ]);
                    $countDetalles++;
                }
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Pedidos exportados correctamente',
                'pedidos' => $countPedidos,
                'detalles' => $countDetalles
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
