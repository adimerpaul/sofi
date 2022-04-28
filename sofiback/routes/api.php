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

    Route::get('/listdeudores',[\App\Http\Controllers\CobrarController::class,'listdeudores']);
    Route::post('/cxcobrar/{ci}',[\App\Http\Controllers\CobrarController::class,'cxcobrar']);
    Route::post('/insertcobro',[\App\Http\Controllers\CobrarController::class,'insertcobro']);
    Route::post('/miscobros',[\App\Http\Controllers\CobrarController::class,'miscobros']);
    Route::post('/impcobros',[\App\Http\Controllers\CobrarController::class,'impcobros']);
    Route::post('/verificar',[\App\Http\Controllers\CobrarController::class,'verificar']);
    Route::post('/misasignaciones',[\App\Http\Controllers\AsignarController::class,'misasignaciones']);
    Route::post('/clientepedido',[\App\Http\Controllers\PedidoController::class,'clientepedido']);
    Route::post('/listpedido',[\App\Http\Controllers\PedidoController::class,'listpedido']);
    Route::post('/listcomanda',[\App\Http\Controllers\PedidoController::class,'listcomanda']);
    Route::post('/updatecomanda',[\App\Http\Controllers\PedidoController::class,'updatecomanda']);
    Route::post('/enviarpedidos',[\App\Http\Controllers\PedidoController::class,'enviarpedidos']);
    Route::post('/envpedido',[\App\Http\Controllers\PedidoController::class,'envpedido']);
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

    Route::post('/me',[\App\Http\Controllers\UserController::class,'me']);
    Route::post('/logout',[\App\Http\Controllers\UserController::class,'logout']);
});

