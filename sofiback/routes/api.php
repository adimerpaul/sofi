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

Route::group(['middleware'=>'auth:sanctum'],function (){
    Route::resource('/cliente',\App\Http\Controllers\ClienteController::class);
    Route::resource('/user',\App\Http\Controllers\UserController::class);
    Route::resource('/asignar',\App\Http\Controllers\AsignarController::class);
    Route::resource('/producto',\App\Http\Controllers\ProductoController::class);
    Route::get('/listdeudores',[\App\Http\Controllers\CobrarController::class,'listdeudores']);
    Route::post('/cxcobrar/{ci}',[\App\Http\Controllers\CobrarController::class,'cxcobrar']);
    Route::post('/insertcobro',[\App\Http\Controllers\CobrarController::class,'insertcobro']);
    Route::post('/misasignaciones',[\App\Http\Controllers\AsignarController::class,'misasignaciones']);
    Route::post('/me',[\App\Http\Controllers\UserController::class,'me']);
    Route::post('/logout',[\App\Http\Controllers\UserController::class,'logout']);
});

