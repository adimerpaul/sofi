<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
 //   return $request->user();
    //});
Route::post('/login',[\App\Http\Controllers\UserController::class,'login']);
Route::post('/ctacobrar',[\App\Http\Controllers\CobrarController::class,'ctacobrar']);
Route::resource('/excel',\App\Http\Controllers\ExcelController::class);
Route::get('/excel/{t}/{f1}/{f2}/{CodAut}',[\App\Http\Controllers\ExcelController::class,'consulta']);
Route::get('users',[\App\Http\Controllers\UserController::class,'users']);
Route::post('/importData',[\App\Http\Controllers\AlmacenController::class,'importData']);
Route::post('/exportData',[\App\Http\Controllers\AlmacenController::class,'exportData']);
Route::get('/boletaentrega/{comanda}',[\App\Http\Controllers\EntregaController::class,'boletaentrega']);

Route::group(['middleware'=>'auth:sanctum'],function (){
    Route::post('/me',[\App\Http\Controllers\UserController::class,'me']);
    Route::resource('/cliente',\App\Http\Controllers\ClienteController::class);
    Route::resource('/user',\App\Http\Controllers\UserController::class);
    Route::resource('/asignar',\App\Http\Controllers\AsignarController::class);
    Route::resource('/producto',\App\Http\Controllers\ProductoController::class);
    Route::resource('/pedido',\App\Http\Controllers\PedidoController::class);
    Route::resource('/pollo',\App\Http\Controllers\PolloController::class);
    Route::resource('/res',\App\Http\Controllers\ResController::class);
    Route::resource('/cerdo',\App\Http\Controllers\CerdoController::class);
    Route::resource('/cobrosrealizados',\App\Http\Controllers\CobrosrealizadosController::class);
    Route::resource('/misvisitas',\App\Http\Controllers\MisvisitasController::class);
    Route::resource('/ruta',\App\Http\Controllers\RutaController::class);
    Route::resource('/entrega',\App\Http\Controllers\EntregaController::class);
    Route::resource('/prestamo',\App\Http\Controllers\PrestamoController::class);

    Route::get('/listdeudores',[\App\Http\Controllers\CobrarController::class,'listdeudores']);
    Route::get('/listProducto',[\App\Http\Controllers\ProductoController::class,'listProducto']);
    Route::get('/verProducto',[\App\Http\Controllers\ProductoController::class,'verProducto']);
    Route::post('/cxcobrar/{ci}',[\App\Http\Controllers\CobrarController::class,'cxcobrar']);
    Route::post('/insertcobro',[\App\Http\Controllers\CobrarController::class,'insertcobro']);
    Route::post('/miscobros',[\App\Http\Controllers\CobrarController::class,'miscobros']);
    Route::post('/impcobros',[\App\Http\Controllers\CobrarController::class,'impcobros']);
    Route::post('/verificar',[\App\Http\Controllers\CobrarController::class,'verificar']);
    Route::post('/delcobro',[\App\Http\Controllers\CobrarController::class,'delcobro']);
    Route::post('/misasignaciones',[\App\Http\Controllers\AsignarController::class,'misasignaciones']);
    Route::post('/clientepedido',[\App\Http\Controllers\PedidoController::class,'clientepedido']);
    Route::post('/pedpendiente',[\App\Http\Controllers\PedidoController::class,'pedpendiente']);
    Route::post('/listpedido',[\App\Http\Controllers\PedidoController::class,'listpedido']);
    Route::post('/listcomanda',[\App\Http\Controllers\PedidoController::class,'listcomanda']);
    Route::post('/updatecomanda',[\App\Http\Controllers\PedidoController::class,'updatecomanda']);
    Route::post('/enviarpedidos',[\App\Http\Controllers\PedidoController::class,'enviarpedidos']);
    Route::post('/envpedido',[\App\Http\Controllers\PedidoController::class,'envpedido']);
    Route::post('/envped',[\App\Http\Controllers\PedidoController::class,'envped']);
    Route::post('/deletecomanda',[\App\Http\Controllers\PedidoController::class,'deletecomanda']);
    Route::post('/rpollo',[\App\Http\Controllers\PedidoController::class,'rpollo']);
    Route::post('/export',[\App\Http\Controllers\PedidoController::class,'export']);
    Route::post('/copiacow',[\App\Http\Controllers\CobrarController::class,'copiacow']);
    Route::post('/rres',[\App\Http\Controllers\PedidoController::class,'rres']);
    Route::post('/rcerdo',[\App\Http\Controllers\PedidoController::class,'rcerdo']);
    Route::post('/rnormal',[\App\Http\Controllers\PedidoController::class,'rnormal']);
    Route::post('/bloquear',[\App\Http\Controllers\ClienteController::class,'bloquear']);
    Route::post('/desbloq2',[\App\Http\Controllers\ClienteController::class,'desbloq2']);
    Route::post('/todosclientes',[\App\Http\Controllers\ClienteController::class,'todosclientes']);
    Route::post('/desbloquear',[\App\Http\Controllers\ClienteController::class,'desbloquear']);
    Route::get('/listapersonal',[\App\Http\Controllers\ClienteController::class,'listapersonal']);
    Route::get('/listaclientes',[\App\Http\Controllers\ClienteController::class,'listaclientes']);
    Route::post('/modprevent',[\App\Http\Controllers\ClienteController::class,'modprevent']);
    Route::post('/filtrarlista',[\App\Http\Controllers\ClienteController::class,'filtrarlista']);
    Route::post('/listvisita',[\App\Http\Controllers\MisvisitasController::class,'listvisita']);
    Route::post('/reporteVenta',[\App\Http\Controllers\PedidoController::class,'reporteVenta']);
    Route::post('/comentario',[\App\Http\Controllers\ClienteController::class,'comentario']);
    Route::post('/updateComentario',[\App\Http\Controllers\ClienteController::class,'updateComentario']);
    Route::post('/lispreventista',[\App\Http\Controllers\PedidoController::class,'lispreventista']);
    Route::post('/informeProducto',[\App\Http\Controllers\PedidoController::class,'informeProducto']);

    Route::post('/me',[\App\Http\Controllers\UserController::class,'me']);
    Route::post('/logout',[\App\Http\Controllers\UserController::class,'logout']);
    Route::post('/listsinpedido',[\App\Http\Controllers\ClienteController::class,'listsinpedido']);

    Route::post('/reporteEmbutido',[\App\Http\Controllers\ExcelController::class,'reporteEmbutido']);
    Route::post('/reporteEmbutidoTodo',[\App\Http\Controllers\ExcelController::class,'reporteEmbutidoTodo']);
    Route::post('/reporteCerdo',[\App\Http\Controllers\ExcelController::class,'reporteCerdo']);
    Route::post('/reporteCerdoTodo',[\App\Http\Controllers\ExcelController::class,'reporteCerdoTodo']);
    Route::post('/reportePollo',[\App\Http\Controllers\ExcelController::class,'reportePollo']);
    Route::post('/listregistro',[\App\Http\Controllers\ExcelController::class,'listregistro']);

    Route::post('/reportePollo2',[\App\Http\Controllers\ExcelController::class,'reportePollo2']);
    Route::get('/almacenes',[\App\Http\Controllers\AlmacenController::class,'index']);
    Route::put('/almacenes/{id}',[\App\Http\Controllers\AlmacenController::class,'update']);
    Route::delete('/almacenes/{id}',[\App\Http\Controllers\AlmacenController::class,'destroy']);
    Route::post('/cargarExcel',[\App\Http\Controllers\AlmacenController::class,'cargarExcel']);
    Route::get('/porcentaje',[\App\Http\Controllers\AlmacenController::class,'porcentaje']);

    Route::get('/registros',[\App\Http\Controllers\AlmacenController::class,'registros']);

    Route::post('/listRuta',[\App\Http\Controllers\RutaController::class,'listRuta']);
    Route::post('/resumenEntrega',[\App\Http\Controllers\RutaController::class,'resumenEntrega']);
    Route::post('/regTodo',[\App\Http\Controllers\EntregaController::class,'regTodo']);

    Route::post('/rePrestamo',[\App\Http\Controllers\PrestamoController::class,'rePrestamo']);
    Route::post('/rePrestamo2',[\App\Http\Controllers\PrestamoController::class,'rePrestamo2']);
    Route::post('/reporteDes',[\App\Http\Controllers\RutaController::class,'reporteDes']);
    Route::get('/reportContable/{fecha}',[\App\Http\Controllers\RutaController::class,'reportContable']);
    
    Route::get('/resumenPedidos/{fecha}',[\App\Http\Controllers\PedidoController::class,'resumenPedidos']);
    Route::post('/reportEntImp',[\App\Http\Controllers\EntregaController::class,'reportEntImp']);
    
    Route::post('/listClienteComanda',[\App\Http\Controllers\RutaController::class,'listClienteComanda']);
    
    Route::post('/listClientePrev',[\App\Http\Controllers\MisvisitasController::class,'listClientePrev']);
    Route::post('/pedidoVenta',[\App\Http\Controllers\MisvisitasController::class,'pedidoVenta']);
    Route::post('/reportEntregVend',[\App\Http\Controllers\MisvisitasController::class,'reportEntregVend']);

    Route::post('/repComanda',[\App\Http\Controllers\RutaController::class,'repComanda']);
    
    
});

