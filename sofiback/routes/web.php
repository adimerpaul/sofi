<?php

use App\Http\Controllers\EncuestaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
| Encuesta de satisfacción (pública, sin login).
| Se renderiza en el backend a propósito: el cliente puede recargar (F5)
| y siempre verá el estado real y actual traído desde la BD.
| El QR de Ruta.vue apunta aquí.
*/
Route::get('/encuesta/{idcliente}/{iduser}', [EncuestaController::class, 'publicForm'])
    ->whereNumber(['idcliente', 'iduser'])
    ->name('encuesta.publica');

Route::post('/encuesta/{idcliente}/{iduser}', [EncuestaController::class, 'publicStore'])
    ->whereNumber(['idcliente', 'iduser'])
    ->middleware('throttle:20,1')
    ->name('encuesta.publica.store');
