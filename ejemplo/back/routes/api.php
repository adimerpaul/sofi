<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');
//app.config.globalProperties.$agencias = ['Challgua','Socavon','Catalina']
Route::post('/login', [App\Http\Controllers\UserController::class, 'login']);
Route::get('/public/mapa-zona', [App\Http\Controllers\MapaZonaController::class, 'publicIndex']);
Route::get('/public/mapa-zona/{tipo}', [App\Http\Controllers\MapaZonaController::class, 'publicIndex'])->whereNumber('tipo');
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [App\Http\Controllers\UserController::class, 'logout']);
    Route::get('/me', [App\Http\Controllers\UserController::class, 'me']);


    Route::get('/users', [App\Http\Controllers\UserController::class, 'index']);
    Route::post('/users', [App\Http\Controllers\UserController::class, 'store']);
    Route::put('/users/{user}', [App\Http\Controllers\UserController::class, 'update']);
    Route::delete('/users/{user}', [App\Http\Controllers\UserController::class, 'destroy']);
    Route::put('/updatePassword/{user}', [App\Http\Controllers\UserController::class, 'updatePassword']);
    Route::post('/{user}/avatar', [App\Http\Controllers\UserController::class, 'updateAvatar']);
    Route::get('/usuariosRole', [App\Http\Controllers\UserController::class, 'usuariosRole']);

    Route::get('/permissions', [App\Http\Controllers\PermissionController::class, 'index']);
    Route::get('/users/{user}/permissions', [App\Http\Controllers\UserController::class, 'getPermissions']);
    Route::put('/users/{user}/permissions', [App\Http\Controllers\UserController::class, 'syncPermissions']);
    Route::get('/pedido-zonas', [App\Http\Controllers\PedidoZonaController::class, 'index']);
    Route::post('/pedido-zonas', [App\Http\Controllers\PedidoZonaController::class, 'store']);
    Route::put('/pedido-zonas/{pedidoZona}', [App\Http\Controllers\PedidoZonaController::class, 'update']);
    Route::delete('/pedido-zonas/{pedidoZona}', [App\Http\Controllers\PedidoZonaController::class, 'destroy']);
    Route::get('/mapa-zona', [App\Http\Controllers\MapaZonaController::class, 'index']);
    Route::post('/mapa-zona/tipos', [App\Http\Controllers\MapaZonaController::class, 'storeTipo']);
    Route::delete('/mapa-zona/tipos/{mapaZonaTipo}', [App\Http\Controllers\MapaZonaController::class, 'destroyTipo']);
    Route::post('/mapa-zona/poligonos', [App\Http\Controllers\MapaZonaController::class, 'storePoligono']);
    Route::put('/mapa-zona/poligonos/{mapaZonaPoligono}', [App\Http\Controllers\MapaZonaController::class, 'updatePoligono']);
    Route::delete('/mapa-zona/poligonos/{mapaZonaPoligono}', [App\Http\Controllers\MapaZonaController::class, 'destroyPoligono']);
    Route::get('/mapa-clientes', [App\Http\Controllers\MapaClienteController::class, 'index']);
    Route::post('/mapa-clientes/asignar', [App\Http\Controllers\MapaClienteController::class, 'asignar']);
    Route::get('/mapa-clientes/reportes/pedidos', [App\Http\Controllers\MapaClienteReporteController::class, 'pedidos']);
    Route::get('/mapa-clientes/reportes/zona-vehiculo', [App\Http\Controllers\MapaClienteReporteController::class, 'zonaVehiculo']);
    Route::get('/mapa-clientes/reportes/productos-totales', [App\Http\Controllers\MapaClienteReporteController::class, 'productosTotales']);
    Route::get('/auxiliar-camara/pedidos', [App\Http\Controllers\AuxiliarCamaraController::class, 'index']);
    Route::put('/auxiliar-camara/pedidos/{pedido}/procesar', [App\Http\Controllers\AuxiliarCamaraController::class, 'procesar']);
    Route::get('/auxiliar-camara/reportes/pedidos', [App\Http\Controllers\AuxiliarCamaraController::class, 'reportePedidos']);
    Route::get('/auxiliar-camara/reportes/pedidos-camion', [App\Http\Controllers\AuxiliarCamaraController::class, 'reportePedidosCamion']);
    Route::get('/auxiliar-camara/reportes/productos-totales', [App\Http\Controllers\AuxiliarCamaraController::class, 'reporteProductosTotales']);
    Route::get('/auxiliar-camara/reportes/ventas-generadas', [App\Http\Controllers\AuxiliarCamaraController::class, 'reporteVentasGeneradas']);
    Route::get('/digitador-factura/pedidos', [App\Http\Controllers\DigitadorFacturaController::class, 'index']);
    Route::put('/digitador-factura/pedidos/{pedido}', [App\Http\Controllers\DigitadorFacturaController::class, 'updatePedido']);
    Route::put('/digitador-factura/ventas/{venta}', [App\Http\Controllers\DigitadorFacturaController::class, 'updateVenta']);
    Route::post('/digitador-factura/generar-factura-todos', [App\Http\Controllers\DigitadorFacturaController::class, 'generarFacturaTodos']);
    Route::get('/digitador-factura/reportes/facturas', [App\Http\Controllers\DigitadorFacturaController::class, 'imprimirFacturas']);
    Route::get('/digitador-factura/reportes/vouchers', [App\Http\Controllers\DigitadorFacturaController::class, 'imprimirVouchers']);
    Route::get('/digitador-factura/reportes/facturas/{venta}', [App\Http\Controllers\DigitadorFacturaController::class, 'imprimirFacturaVenta']);
    Route::get('/digitador-factura/reportes/vouchers/{venta}', [App\Http\Controllers\DigitadorFacturaController::class, 'imprimirVoucherVenta']);
    Route::get('/verificacion/ventas', [App\Http\Controllers\VerificacionController::class, 'index']);
    Route::put('/verificacion/ventas/{venta}', [App\Http\Controllers\VerificacionController::class, 'updateVenta']);
    Route::get('/verificacion/reportes/ventas', [App\Http\Controllers\VerificacionController::class, 'imprimir']);
    Route::get('/verificacion/reportes/ventas/{venta}', [App\Http\Controllers\VerificacionController::class, 'imprimirVenta']);
    Route::get('/despachador/rutas', [App\Http\Controllers\DespachadorController::class, 'rutas']);
    Route::post('/despachador/pagos', [App\Http\Controllers\DespachadorController::class, 'registrarPago']);
    Route::post('/despachador/pagos/lote', [App\Http\Controllers\DespachadorController::class, 'registrarPagosLote']);
    Route::put('/despachador/pagos/{pago}', [App\Http\Controllers\DespachadorController::class, 'actualizarPago']);
    Route::put('/despachador/pedidos/{pedido}/estado', [App\Http\Controllers\DespachadorController::class, 'actualizarEstadoPedido']);
    Route::get('/despachador/despacho', [App\Http\Controllers\DespachadorController::class, 'despacho']);
    Route::get('/despachador/reportes/vouchers', [App\Http\Controllers\DespachadorController::class, 'imprimirVouchers']);
    Route::get('/despachador/reportes/vouchers/{venta}', [App\Http\Controllers\DespachadorController::class, 'imprimirVoucherVenta']);
    Route::get('/cobranzas/deudores', [App\Http\Controllers\CobranzasController::class, 'deudores']);
    Route::get('/cobranzas/historial-clientes', [App\Http\Controllers\CobranzasController::class, 'historialClientes']);
    Route::get('/cobranzas/historial-clientes/{cliente}', [App\Http\Controllers\CobranzasController::class, 'historialClienteDetalle']);
    Route::get('/cobranzas/clientes', [App\Http\Controllers\CobranzasController::class, 'clientes']);
    Route::put('/cobranzas/ventas/{venta}/considerar', [App\Http\Controllers\CobranzasController::class, 'cambiarConsiderarVenta']);
    Route::put('/cobranzas/deudas-manuales/{deuda}/considerar', [App\Http\Controllers\CobranzasController::class, 'cambiarConsiderarDeuda']);
    Route::post('/cobranzas/deudas-manuales', [App\Http\Controllers\CobranzasController::class, 'crearDeudaManual']);
    Route::post('/cobranzas/pagos/ventas', [App\Http\Controllers\CobranzasController::class, 'registrarPagoVenta']);
    Route::put('/cobranzas/pagos/ventas/{pago}', [App\Http\Controllers\CobranzasController::class, 'actualizarPagoVenta']);
    Route::put('/cobranzas/pagos/ventas/{pago}/anular', [App\Http\Controllers\CobranzasController::class, 'anularPagoVenta']);
    Route::post('/cobranzas/deudas-manuales/{deuda}/pagos', [App\Http\Controllers\CobranzasController::class, 'registrarPagoDeudaManual']);
    Route::put('/cobranzas/deudas-manuales/pagos/{pago}', [App\Http\Controllers\CobranzasController::class, 'actualizarPagoDeudaManual']);
    Route::put('/cobranzas/deudas-manuales/pagos/{pago}/anular', [App\Http\Controllers\CobranzasController::class, 'anularPagoDeuda']);


    Route::get('/productos/{id}/historial-compras-ventas', [App\Http\Controllers\ProductoController::class, 'historialComprasVentas']);

    Route::post('/productos', [App\Http\Controllers\ProductoController::class, 'store']);
    Route::get('/productosAll', [App\Http\Controllers\ProductoController::class, 'productosAll']);
    Route::get('/productos', [App\Http\Controllers\ProductoController::class, 'index']);
    Route::get('/productosStock', [App\Http\Controllers\ProductoController::class, 'productosStock']);
    Route::get('/producto-grupo-padres', [App\Http\Controllers\ProductoController::class, 'gruposPadres']);
    Route::get('/producto-grupos', [App\Http\Controllers\ProductoController::class, 'grupos']);
    Route::put('/productos/{producto}', [App\Http\Controllers\ProductoController::class, 'update']);
    Route::delete('/productos/{producto}', [App\Http\Controllers\ProductoController::class, 'destroy']);
    Route::post('/uploadImage', [App\Http\Controllers\ProductoController::class, 'uploadImage']);


    Route::post('/searchCliente', [App\Http\Controllers\ClienteController::class, 'searchCliente']);
    Route::get('/clientes', [App\Http\Controllers\ClienteController::class, 'index']);
    Route::get('/clientes-zonas', [App\Http\Controllers\ClienteController::class, 'zonas']);
    Route::post('/clientes', [App\Http\Controllers\ClienteController::class, 'store']);
    Route::get('/clientes/{cliente}', [App\Http\Controllers\ClienteController::class, 'show']);
    Route::put('/clientes/{cliente}', [App\Http\Controllers\ClienteController::class, 'update']);
    Route::post('/clientes/{cliente}', [App\Http\Controllers\ClienteController::class, 'update']);
    Route::delete('/clientes/{cliente}', [App\Http\Controllers\ClienteController::class, 'destroy']);

    Route::post('/ventas', [App\Http\Controllers\VentaController::class, 'store']);
    Route::get('/ventas', [App\Http\Controllers\VentaController::class, 'index']);
    Route::put('/ventasAnular/{venta}', [App\Http\Controllers\VentaController::class, 'anular']);
    Route::put('/tipoVentasChange/{venta}', [App\Http\Controllers\VentaController::class, 'tipoVentasChange']);

    Route::get('/proveedores', [App\Http\Controllers\ProveedorController::class, 'index']);
    Route::post('/proveedores', [App\Http\Controllers\ProveedorController::class, 'store']);
    Route::put('/proveedores/{proveedor}', [App\Http\Controllers\ProveedorController::class, 'update']);
    Route::delete('/proveedores/{proveedor}', [App\Http\Controllers\ProveedorController::class, 'destroy']);

    Route::get('compras', [App\Http\Controllers\CompraController::class, 'index']);
    Route::put('comprasAnular/{id}', [App\Http\Controllers\CompraController::class, 'anular']);
    Route::post('compras', [App\Http\Controllers\CompraController::class, 'store']);
    Route::put('compras/{compra}/datos', [App\Http\Controllers\CompraController::class, 'updateDatos']);
    Route::post('compras/{compra}/datos', [App\Http\Controllers\CompraController::class, 'updateDatos']);
    Route::get('/productosPorVencer', [App\Http\Controllers\CompraController::class, 'productosPorVencer']);
    Route::get('/productosVencidos', [App\Http\Controllers\CompraController::class, 'productosVencidos']);
    Route::get('/productos/{id}/historial-compras', [App\Http\Controllers\CompraController::class, 'historialCompras']);

    Route::get('/pedidos', [App\Http\Controllers\PedidoController::class, 'index']);
    Route::get('/pedidos-totales', [App\Http\Controllers\PedidoTotalesController::class, 'index']);
    Route::get('/mis-pedidos', [App\Http\Controllers\PedidoController::class, 'misPedidos']);
    Route::get('/mis-pedidos/reporte/{tipo}', [App\Http\Controllers\PedidoReporteController::class, 'exportar']);
    Route::post('/pedidos/enviar-mis-pedidos', [App\Http\Controllers\PedidoController::class, 'enviarMisPedidos']);
    Route::post('/pedidos-totales/enviar-emergencia', [App\Http\Controllers\PedidoTotalesController::class, 'enviarEmergencia']);
    Route::put('/pedidos/{pedido}/enviar', [App\Http\Controllers\PedidoController::class, 'enviar']);
    Route::put('/pedidos/{pedido}', [App\Http\Controllers\PedidoController::class, 'update']);
    Route::post('/pedidos', [App\Http\Controllers\PedidoController::class, 'store']);
    Route::get('/visitas/clientes', [App\Http\Controllers\VisitaController::class, 'clientes']);
    Route::get('/visitas', [App\Http\Controllers\VisitaController::class, 'index']);

    Route::get('/recuperarPedido', [App\Http\Controllers\PedidoController::class, 'recuperarPedido']);

    Route::post('/impuestos/generar-cui', [App\Http\Controllers\ImpuestoController::class, 'generarCUI']);
    Route::post('/impuestos/generar-cufd', [App\Http\Controllers\ImpuestoController::class, 'generarCUFD']);
    Route::post('/impuestos/reintentar-cufd', [App\Http\Controllers\ImpuestoController::class, 'reintentarCufd']);
    Route::get('/impuestos/list-cufd', [App\Http\Controllers\ImpuestoController::class, 'listCUFD']);
    Route::get('/impuestos/auto-cufd/estado', [App\Http\Controllers\ImpuestoController::class, 'estadoAutoCufd']);
    Route::get('/impuestos/fallas', [App\Http\Controllers\ImpuestoController::class, 'fallas']);
    Route::put('/impuestos/fallas/{falla}/resolver', [App\Http\Controllers\ImpuestoController::class, 'resolverFalla']);
    Route::put('/impuestos/fallas/{falla}/ocultar', [App\Http\Controllers\ImpuestoController::class, 'ocultarFalla']);
    Route::delete('/impuestos/fallas/{falla}', [App\Http\Controllers\ImpuestoController::class, 'eliminarFalla']);
    Route::post('/verificarImpuestos/{cuf}', [App\Http\Controllers\ImpuestoController::class, 'verificarImpuestos']);
    Route::post('/eventoSignificativo', [App\Http\Controllers\ImpuestoController::class, 'eventoSignificativo']);
    Route::post('/validarPaquete', [App\Http\Controllers\ImpuestoController::class, 'validarPaquete']);
});

//Route test
Route::get('/test', [App\Http\Controllers\TestController::class, 'index']);
//facturacionOperaciones
Route::get('/facturacionOperaciones', [App\Http\Controllers\ImpuestoController::class, 'facturacionOperaciones']);
