<?php

use App\Http\Controllers\BonificacionController;
use App\Http\Controllers\EncuestaController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\MobilController;
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

Route::post('encuestas', [EncuestaController::class, 'store']);
Route::get('/encuestas/check', [EncuestaController::class, 'check']);
Route::get('encuestas/report-pdf', [EncuestaController::class, 'reportPdf']);

Route::post('/exportData',[\App\Http\Controllers\AlmacenController::class,'exportData']);

Route::group(['middleware'=>'auth:sanctum'],function (){
    Route::post('/me',[\App\Http\Controllers\UserController::class,'me']);
    Route::get('/permisosList',[\App\Http\Controllers\UserController::class,'permisosList']);
    Route::get('/usuarioPermisos/{id}',[\App\Http\Controllers\UserController::class,'usuarioPermisos']);
    Route::post('/usuarioPermisos/{id}',[\App\Http\Controllers\UserController::class,'updateUsuarioPermisos']);
    Route::resource('/cliente',\App\Http\Controllers\ClienteController::class);
    Route::resource('/user',\App\Http\Controllers\UserController::class);

    Route::get('encuestas', [EncuestaController::class, 'index']);

    Route::resource('/asignar',\App\Http\Controllers\AsignarController::class);
    Route::resource('/producto',\App\Http\Controllers\ProductoController::class);
    Route::resource('/pedido',\App\Http\Controllers\PedidoController::class);
    Route::post('/clonarpedido',[\App\Http\Controllers\PedidoController::class,'clonarpedido']);
    Route::resource('/pollo',\App\Http\Controllers\PolloController::class);
    Route::resource('/res',\App\Http\Controllers\ResController::class);
    Route::resource('/cerdo',\App\Http\Controllers\CerdoController::class);
    Route::resource('/cobrosrealizados',\App\Http\Controllers\CobrosrealizadosController::class);
    Route::resource('/misvisitas',\App\Http\Controllers\MisvisitasController::class);
    Route::resource('/ruta',\App\Http\Controllers\RutaController::class);
    Route::resource('/entrega',\App\Http\Controllers\EntregaController::class);
    Route::resource('/prestamo',\App\Http\Controllers\PrestamoController::class);
    Route::resource('/horarioenvio',\App\Http\Controllers\HorarioEnvioController::class);

    Route::get('/listdeudores',[\App\Http\Controllers\CobrarController::class,'listdeudores']);
    Route::get('/listProducto',[\App\Http\Controllers\ProductoController::class,'listProducto']);
    Route::get('/verProducto',[\App\Http\Controllers\ProductoController::class,'verProducto']);
    Route::get('/productosPaginado',[\App\Http\Controllers\ProductoController::class,'paginado']);
    Route::get('/filtrosProducto',[\App\Http\Controllers\ProductoController::class,'filtrosProducto']);
    Route::get('/productos/excel',[\App\Http\Controllers\ProductoController::class,'exportarExcel']);
    Route::get('/productos/pdf',[\App\Http\Controllers\ProductoController::class,'exportarPdf']);
    Route::post('/productos/actualizarPollos',[\App\Http\Controllers\ProductoController::class,'actualizarPollos']);
    Route::post('/productos/grupos',[\App\Http\Controllers\ProductoController::class,'crearGrupo']);
    // Cambio de precios: los 13 precios y el de compra de todos los productos en una grilla.
    Route::get('/precios',[\App\Http\Controllers\PrecioController::class,'index']);
    Route::put('/precios',[\App\Http\Controllers\PrecioController::class,'guardar']);
    Route::post('/productos',[\App\Http\Controllers\ProductoController::class,'crear']);
    Route::put('/productos/{codProd}',[\App\Http\Controllers\ProductoController::class,'actualizar']);
    Route::delete('/productos/{codProd}',[\App\Http\Controllers\ProductoController::class,'eliminar']);
    Route::post('/productos/{codProd}/imagen',[\App\Http\Controllers\ProductoController::class,'subirImagen']);
    Route::delete('/productos/{codProd}/imagen',[\App\Http\Controllers\ProductoController::class,'quitarImagen']);

    Route::get('/ventas',[\App\Http\Controllers\VentaController::class,'index']);
    Route::get('/ventas/resumen',[\App\Http\Controllers\VentaController::class,'resumen']);
    Route::get('/ventas/filtros',[\App\Http\Controllers\VentaController::class,'filtros']);
    Route::get('/ventas/comanda/{comanda}',[\App\Http\Controllers\VentaController::class,'comanda']);
    // Facturacion propia: no toca tbventas, vive en facturas/factura_detalles.
    Route::get('/facturacion',[\App\Http\Controllers\FacturacionController::class,'index']);
    Route::get('/facturacion/catalogo',[\App\Http\Controllers\FacturacionController::class,'catalogo']);
    Route::get('/facturacion/categorias',[\App\Http\Controllers\FacturacionController::class,'categorias']);
    Route::get('/facturacion/clientes',[\App\Http\Controllers\FacturacionController::class,'clientes']);
    Route::get('/facturacion/pedidos',[\App\Http\Controllers\FacturacionController::class,'pedidos']);
    Route::get('/facturacion/pedidos/{pedido}',[\App\Http\Controllers\FacturacionController::class,'pedido']);
    // Guarda el avance del cobro sin crear la venta ni descontar stock.
    Route::post('/facturacion/pedidos/{pedido}/borrador',[\App\Http\Controllers\FacturacionController::class,'guardarBorrador']);
    // Van antes de /facturacion/{factura}: si no, 'lote' y 'reporte' entrarian
    // como si fueran el id de una factura.
    Route::get('/facturacion/lote/{documento}',[\App\Http\Controllers\FacturacionController::class,'lote']);
    Route::get('/facturacion/reporte',[\App\Http\Controllers\FacturacionController::class,'reporte']);
    Route::get('/facturacion/carga',[\App\Http\Controllers\FacturacionController::class,'carga']);
    // Caja aprueba la carga de todo un camion de una vez.
    Route::post('/facturacion/carga/aprobar',[\App\Http\Controllers\FacturacionController::class,'aprobarCarga']);
    // Caja marca como revisadas las canastas elegidas en la grilla.
    Route::post('/facturacion/carga/marcar',[\App\Http\Controllers\FacturacionController::class,'marcarCargaVarias']);
    Route::get('/facturacion/camiones',[\App\Http\Controllers\FacturacionController::class,'camiones']);
    // Entregas del dia sobre facturacion (reemplaza al reporte de /entrega).
    Route::get('/entrega-factura',[\App\Http\Controllers\EntregaFacturaController::class,'index']);
    // Solo las ventas del vendedor que entra, para /avance.
    Route::get('/entrega-factura/vendedor',[\App\Http\Controllers\EntregaFacturaController::class,'vendedor']);
    // Color de zona de la ultima asignacion de cada camion (venta directa).
    Route::get('/facturacion/camiones-color',[\App\Http\Controllers\FacturacionController::class,'coloresCamion']);
    // Zonas (colores) para cambiar el color de un comprobante.
    Route::get('/facturacion/colores',[\App\Http\Controllers\FacturacionController::class,'colores']);
    // Reportes de Ventas y Facturacion: quiebre de stock (pedido contra vendido).
    Route::get('/reportes/quiebre-embutido',[\App\Http\Controllers\ReporteVentasController::class,'quiebreEmbutido']);
    Route::get('/reportes/quiebre-embutido/excel',[\App\Http\Controllers\ReporteVentasController::class,'quiebreEmbutidoExcel']);
    // Detalle de lo facturado en el turno (ayer 18:00 a hoy 18:00), una fila por producto.
    Route::get('/reportes/detalle-ventas',[\App\Http\Controllers\ReporteVentasController::class,'detalleVentas']);
    Route::get('/reportes/detalle-ventas/excel',[\App\Http\Controllers\ReporteVentasController::class,'detalleVentasExcel']);
    // Reporte auxiliar del dia de pollo: stock, ventas por preventista y stock final.
    Route::get('/reportes/auxiliar-pollo',[\App\Http\Controllers\ReporteVentasController::class,'auxiliarPollo']);
    Route::get('/reportes/auxiliar-pollo/excel',[\App\Http\Controllers\ReporteVentasController::class,'auxiliarPolloExcel']);
    // Kardex: entradas y salidas de un producto con su existencia.
    Route::get('/reportes/kardex/productos',[\App\Http\Controllers\ReporteVentasController::class,'kardexProductos']);
    Route::get('/reportes/kardex',[\App\Http\Controllers\ReporteVentasController::class,'kardex']);
    Route::get('/reportes/kardex/excel',[\App\Http\Controllers\ReporteVentasController::class,'kardexExcel']);
    Route::get('/facturacion/{factura}/voucher',[\App\Http\Controllers\FacturacionController::class,'voucher']);
    Route::get('/facturacion/{factura}/factura',[\App\Http\Controllers\FacturacionController::class,'factura']);
    Route::get('/facturacion/{factura}/url-impuestos',[\App\Http\Controllers\FacturacionController::class,'urlImpuestos']);
    Route::get('/facturacion/{factura}',[\App\Http\Controllers\FacturacionController::class,'show']);
    Route::post('/facturacion',[\App\Http\Controllers\FacturacionController::class,'store']);
    // Caja marca la canasta de un comprobante como revisada o sin revisar.
    Route::post('/facturacion/{factura}/carga',[\App\Http\Controllers\FacturacionController::class,'marcarCarga']);
    Route::put('/facturacion/{factura}/anular',[\App\Http\Controllers\FacturacionController::class,'anular']);
    // Pasa el comprobante (y su pedido) a otro camion.
    Route::put('/facturacion/{factura}/camion',[\App\Http\Controllers\FacturacionController::class,'cambiarCamion']);
    // Contado (efectivo o QR) <-> credito de un comprobante ya emitido.
    Route::put('/facturacion/{factura}/pago',[\App\Http\Controllers\FacturacionController::class,'cambiarPago']);
    // Retorno parcial: anula el comprobante y emite otro con lo entregado.
    Route::put('/facturacion/{factura}/retorno-parcial',[\App\Http\Controllers\FacturacionController::class,'retornoParcial']);

    // El caminero solo ve lo de su camion: la placa sale de su usuario, no
    // viaja en la peticion.
    Route::get('/caminero/entregas',[\App\Http\Controllers\CamineroController::class,'entregas']);
    Route::post('/caminero/cobrar',[\App\Http\Controllers\CamineroController::class,'cobrar']);
    // El cliente se queda con parte de la nota: no toca el comprobante, lo edita caja.
    Route::post('/caminero/retorno-parcial',[\App\Http\Controllers\CamineroController::class,'retornoParcial']);
    Route::get('/caminero/reporte',[\App\Http\Controllers\CamineroController::class,'reporte']);
    // Las hojas del recojo en PDF, una por forma de pago, para firmar.
    Route::get('/caminero/reporte/pdf',[\App\Http\Controllers\CamineroController::class,'reportePdf']);
    // Lo mismo en Excel: la tabla del dia, contados y QR, una hoja cada una.
    Route::get('/caminero/reporte/excel',[\App\Http\Controllers\CamineroController::class,'reporteExcel']);
    // Antes de salir, el caminero revisa la carga de su camion producto por
    // producto; hasta que no este completa, caja no imprime sus comprobantes.
    // Cobranzas ve el recojo de todos los camiones, no solo el suyo: es
    // quien recibe la plata al final del dia. Mismo servicio que el caminero.
    Route::get('/cobranzas/recojo',[\App\Http\Controllers\CobranzaRecojoController::class,'reporte']);
    Route::get('/cobranzas/recojo/camiones',[\App\Http\Controllers\CobranzaRecojoController::class,'camiones']);
    Route::get('/cobranzas/recojo/pdf',[\App\Http\Controllers\CobranzaRecojoController::class,'reportePdf']);
    // Cobranzas verifica la facturacion del dia contra lo que trajo cada camion.
    Route::get('/cobranzas/verificacion',[\App\Http\Controllers\CobranzaVerificacionController::class,'index']);
    Route::get('/cobranzas/verificacion/clientes',[\App\Http\Controllers\CobranzaVerificacionController::class,'buscarClientes']);
    Route::get('/cobranzas/verificacion/clientes/{cliente}/facturas',[\App\Http\Controllers\CobranzaVerificacionController::class,'facturasCliente'])->where('cliente', '[0-9]+');
    Route::get('/cobranzas/verificacion/clientes/{cliente}/ventas',[\App\Http\Controllers\CobranzaVerificacionController::class,'historialVentas'])->where('cliente', '[0-9]+');
    Route::post('/cobranzas/verificacion',[\App\Http\Controllers\CobranzaVerificacionController::class,'verificar']);
    Route::get('/cobranzas/verificacion/excel',[\App\Http\Controllers\CobranzaVerificacionController::class,'excel']);
    Route::get('/cobranzas/verificacion/pdf',[\App\Http\Controllers\CobranzaVerificacionController::class,'pdf']);
    Route::get('/cobranzas/verificacion/verificadores',[\App\Http\Controllers\CobranzaVerificacionController::class,'verificadores']);
    Route::get('/cobranzas/verificacion/excel-qr',[\App\Http\Controllers\CobranzaVerificacionController::class,'excelQr']);
    Route::get('/cobranzas/verificacion/excel-total',[\App\Http\Controllers\CobranzaVerificacionController::class,'excelTotal']);
    Route::get('/cobranzas/verificacion/excel-detalle',[\App\Http\Controllers\CobranzaVerificacionController::class,'excelDetalle']);
    Route::get('/creditos/clientes',[\App\Http\Controllers\CreditoController::class,'clientes']);
    Route::get('/vendedor/creditos/clientes',[\App\Http\Controllers\CreditoController::class,'clientesVendedor']);
    Route::get('/vendedor/creditos/clientes/{cliente}',[\App\Http\Controllers\CreditoController::class,'deudasVendedor'])->where('cliente', '[0-9]+');
    Route::post('/vendedor/creditos/clientes/{cliente}/cobros',[\App\Http\Controllers\CreditoController::class,'cobrarVendedor'])->where('cliente', '[0-9]+');
    // Todos los clientes con su deuda, y el detalle de uno (deudas y ventas a credito).
    Route::get('/creditos/resumen',[\App\Http\Controllers\CreditoController::class,'resumen']);
    Route::get('/creditos/excel-deudores',[\App\Http\Controllers\CreditoController::class,'excelDeudores']);
    Route::get('/creditos/excel-cobros',[\App\Http\Controllers\CreditoController::class,'excelCobros']);
    // Cierre de caja de cobros: lo cobrado por cada usuario entre fecha/hora.
    Route::get('/creditos/cobradores',[\App\Http\Controllers\CreditoController::class,'cobradores']);
    Route::get('/creditos/excel-cierre',[\App\Http\Controllers\CreditoController::class,'excelCierre']);
    Route::get('/creditos/clientes/{id}',[\App\Http\Controllers\CreditoController::class,'detalle'])->where('id', '[0-9]+');
    Route::get('/creditos',[\App\Http\Controllers\CreditoController::class,'index']);
    Route::post('/creditos',[\App\Http\Controllers\CreditoController::class,'store']);
    Route::get('/creditos/{origen}/{id}/abonos',[\App\Http\Controllers\CreditoController::class,'historial']);
    Route::post('/creditos/{origen}/{id}/abonos',[\App\Http\Controllers\CreditoController::class,'abonar']);
    // Un abono cobrado por equivocacion no se borra: se anula con su motivo.
    Route::put('/creditos/abonos/{abono}/anular',[\App\Http\Controllers\CreditoController::class,'anularAbono'])->where('abono', '[0-9]+');

    Route::get('/caminero/carga',[\App\Http\Controllers\CamineroController::class,'carga']);
    Route::get('/caminero/carga/reporte',[\App\Http\Controllers\CamineroController::class,'cargaReporte']);
    Route::post('/caminero/carga/verificar',[\App\Http\Controllers\CamineroController::class,'verificarCarga']);
    // Tilde de a un producto dentro de la canasta.
    Route::post('/caminero/carga/productos',[\App\Http\Controllers\CamineroController::class,'verificarProductos']);
    // Numero de canasta y nota de cada comprobante; se ven despues en sus entregas.
    Route::post('/caminero/carga/canasta',[\App\Http\Controllers\CamineroController::class,'canasta']);
    Route::post('/caminero/carga/verificar-todo',[\App\Http\Controllers\CamineroController::class,'verificarCargaTodo']);

    // Compras a proveedor: suben el stock de tbstock.
    Route::get('/compras',[\App\Http\Controllers\CompraController::class,'index']);
    Route::get('/compras/proveedores',[\App\Http\Controllers\CompraController::class,'proveedores']);
    Route::get('/compras/{compra}',[\App\Http\Controllers\CompraController::class,'show']);
    Route::post('/compras',[\App\Http\Controllers\CompraController::class,'store']);
    Route::put('/compras/{compra}/anular',[\App\Http\Controllers\CompraController::class,'anular']);

    // Impuestos (SIAT): datos del emisor y codigos CUIS/CUFD. Todo vive en la
    // base (siat_configuraciones) y se edita desde la pantalla /impuestos.
    Route::get('/impuestos/configuracion',[\App\Http\Controllers\ImpuestoController::class,'configuracion']);
    Route::put('/impuestos/configuracion',[\App\Http\Controllers\ImpuestoController::class,'guardarConfiguracion']);
    Route::post('/impuestos/probar',[\App\Http\Controllers\ImpuestoController::class,'probar']);
    Route::get('/impuestos/cuis',[\App\Http\Controllers\ImpuestoController::class,'cuis']);
    Route::post('/impuestos/cuis',[\App\Http\Controllers\ImpuestoController::class,'generarCuis']);
    Route::delete('/impuestos/cuis/{id}',[\App\Http\Controllers\ImpuestoController::class,'eliminarCuis']);
    Route::get('/impuestos/cufd',[\App\Http\Controllers\ImpuestoController::class,'cufds']);
    Route::post('/impuestos/cufd',[\App\Http\Controllers\ImpuestoController::class,'generarCufd']);
    Route::delete('/impuestos/cufd/{id}',[\App\Http\Controllers\ImpuestoController::class,'eliminarCufd']);
    // Catalogo de motivos de anulacion; lo usa el dialogo de facturacion.
    Route::get('/impuestos/motivos-anulacion',[\App\Http\Controllers\ImpuestoController::class,'motivosAnulacion']);
    Route::post('/impuestos/motivos-anulacion/sincronizar',[\App\Http\Controllers\ImpuestoController::class,'sincronizarMotivosAnulacion']);
    // Control de lo enviado a facturar: estado en el SIAT y reenvio.
    Route::get('/impuestos/facturas',[\App\Http\Controllers\ImpuestoController::class,'facturas']);
    Route::post('/impuestos/facturas/{id}/verificar',[\App\Http\Controllers\ImpuestoController::class,'verificarFactura']);
    Route::post('/impuestos/facturas/{id}/reenviar',[\App\Http\Controllers\ImpuestoController::class,'reenviarFactura']);

    // Administracion de proveedores (tbproveedor).
    Route::get('/proveedores',[\App\Http\Controllers\ProveedorController::class,'index']);
    Route::post('/proveedores',[\App\Http\Controllers\ProveedorController::class,'store']);
    Route::put('/proveedores/{id}',[\App\Http\Controllers\ProveedorController::class,'update']);
    Route::delete('/proveedores/{id}',[\App\Http\Controllers\ProveedorController::class,'destroy']);
    Route::get('/facturas/{codAut}/pdf',[\App\Http\Controllers\FacturaFiscalController::class,'pdf']);
    Route::post('/cxcobrar/{ci}',[\App\Http\Controllers\CobrarController::class,'cxcobrar']);
    Route::post('/insertcobro',[\App\Http\Controllers\CobrarController::class,'insertcobro']);
    Route::post('/miscobros',[\App\Http\Controllers\CobrarController::class,'miscobros']);
    Route::post('/impcobros',[\App\Http\Controllers\CobrarController::class,'impcobros']);
    Route::post('/verificar',[\App\Http\Controllers\CobrarController::class,'verificar']);
    Route::post('/delcobro',[\App\Http\Controllers\CobrarController::class,'delcobro']);
    Route::post('/misasignaciones',[\App\Http\Controllers\AsignarController::class,'misasignaciones']);
    Route::post('/clientepedido',[\App\Http\Controllers\PedidoController::class,'clientepedido']);
    Route::post('/clientepedidototales',[\App\Http\Controllers\PedidoController::class,'clientepedidototales']);
    Route::post('/pedidoauditoria',[\App\Http\Controllers\PedidoController::class,'pedidoauditoria']);
    Route::post('/pedidoseliminados',[\App\Http\Controllers\PedidoController::class,'pedidoseliminados']);
    Route::post('/habilitarpedido',[\App\Http\Controllers\PedidoController::class,'habilitarpedido']);
    Route::post('/pedpendiente',[\App\Http\Controllers\PedidoController::class,'pedpendiente']);
    Route::post('/listpedido',[\App\Http\Controllers\PedidoController::class,'listpedido']);
    Route::post('/listcomanda',[\App\Http\Controllers\PedidoController::class,'listcomanda']);
    Route::post('/updatecomanda',[\App\Http\Controllers\PedidoController::class,'updatecomanda']);
    Route::post('/enviarpedidos',[\App\Http\Controllers\PedidoController::class,'enviarpedidos']);
    Route::post('/enviarPedidosEmergencia',[\App\Http\Controllers\PedidoController::class,'enviarPedidosEmergencia']);
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
    Route::get('/personalCliente',[\App\Http\Controllers\ClienteController::class,'personalCliente']);
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
    Route::post('/listsinpedido/exportar', [\App\Http\Controllers\ClienteController::class, 'exportarSinPedido']);


    Route::post('/reporteEmbutido',[\App\Http\Controllers\ExcelController::class,'reporteEmbutido']);
    Route::post('/reporteEmbutidoTodo',[\App\Http\Controllers\ExcelController::class,'reporteEmbutidoTodo']);
    // El Excel de embutidos ya armado y con formato; enviados=1 deja solo lo
    // que los preventistas mandaron, que es lo que se va a despachar.
    Route::get('/reporteEmbutidoExcel',[\App\Http\Controllers\ExcelController::class,'reporteEmbutidoExcel']);
    Route::post('/reporteCerdo',[\App\Http\Controllers\ExcelController::class,'reporteCerdo']);
    Route::post('/reporteCerdoTodo',[\App\Http\Controllers\ExcelController::class,'reporteCerdoTodo']);
    Route::post('/reportePollo',[\App\Http\Controllers\ExcelController::class,'reportePollo']);
    Route::post('/listregistro',[\App\Http\Controllers\ExcelController::class,'listregistro']);

    Route::post('/reportePollo2',[\App\Http\Controllers\ExcelController::class,'reportePollo2']);
    Route::get('/almacenes',[\App\Http\Controllers\AlmacenController::class,'index']);
    Route::get('/almacenPendientes',[\App\Http\Controllers\AlmacenController::class,'almacenPendientes']);
//    almacenRegistroVerificar
    Route::put('/almacenRegistroVerificar/{id}',[\App\Http\Controllers\AlmacenController::class,'almacenRegistroVerificar']);
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
    Route::get('/camionesPedidos/{fecha}',[\App\Http\Controllers\PedidoController::class,'camionesPedidos']);
    Route::post('/reportEntImp',[\App\Http\Controllers\EntregaController::class,'reportEntImp']);

    Route::post('/listClienteComanda',[\App\Http\Controllers\RutaController::class,'listClienteComanda']);

    Route::post('/listClientePrev',[\App\Http\Controllers\MisvisitasController::class,'listClientePrev']);
    Route::post('/pedidoVenta',[\App\Http\Controllers\MisvisitasController::class,'pedidoVenta']);
    Route::post('/reportEntregVend',[\App\Http\Controllers\MisvisitasController::class,'reportEntregVend']);

    Route::post('/repComanda',[\App\Http\Controllers\RutaController::class,'repComanda']);
    Route::post('/mapClient',[\App\Http\Controllers\PedidoController::class,'mapClient']);
    Route::post('/mapaVendedor',[\App\Http\Controllers\MapaVendedorController::class,'mapaVendedor']);
    Route::post('/mapaVendedorVisita',[\App\Http\Controllers\MapaVendedorController::class,'mapaVendedorVisita']);

    Route::post('/mapClientes',[\App\Http\Controllers\PedidoController::class,'mapClientes']);
    Route::post('/detallePedMap',[\App\Http\Controllers\PedidoController::class,'detallePedMap']);

    Route::post('/listVehiculo',[\App\Http\Controllers\PedidoController::class,'listVehiculo']);
    Route::post('/updaVehiPed',[\App\Http\Controllers\PedidoController::class,'updaVehiPed']);

    Route::get('/generarXlsPollo/{fecha}',[\App\Http\Controllers\ExcelController::class,'generarXlsPollo']);
    Route::get('/generarXlsCerdo/{fecha}',[\App\Http\Controllers\ExcelController::class,'generarXlsCerdo']);
    Route::get('/factura/{comanda}', [FacturaController::class, 'generarPDF']);

    Route::get('/bonificaciones', [BonificacionController::class, 'bonificaciones']);
    Route::post('/bonificacioneAprobar', [BonificacionController::class, 'bonificacioneAprobar']);

    Route::get('/cliente-photos', [\App\Http\Controllers\ClientePhotoController::class, 'index']);
    Route::post('/cliente-photos', [\App\Http\Controllers\ClientePhotoController::class, 'store']);
    Route::delete('/cliente-photos/{id}', [\App\Http\Controllers\ClientePhotoController::class, 'destroy']);
    // Precio (1 a 13) del cliente por grupo de productos: pestaña Precios de Clientes.
    Route::get('/cliente/{cliente}/precios', [\App\Http\Controllers\ClientePrecioController::class, 'index'])->where('cliente', '[0-9]+');
    Route::put('/cliente/{cliente}/precios', [\App\Http\Controllers\ClientePrecioController::class, 'guardar'])->where('cliente', '[0-9]+');
});
Route::get('/facturaV/{comanda}', [FacturaController::class, 'generarPDF']);
Route::get('/generarXlsPollo/{fecha}',[\App\Http\Controllers\ExcelController::class,'generarXlsPollo']);
Route::get('/generarXlsBrasa/{fecha}',[\App\Http\Controllers\ExcelController::class,'generarXlsBrasa']);
Route::get('/generarXlsPreparacion/{fecha}/{especie?}',[\App\Http\Controllers\ExcelController::class,'generarXlsPreparacion']);

Route::get('/generarXlsCerdo/{fecha}',[\App\Http\Controllers\ExcelController::class,'generarXlsCerdo']);
Route::get('/reportePedido/{fecha}',[\App\Http\Controllers\PedidoController::class,'reportePedido']);
Route::get('/reportePedido3/{fecha}',[\App\Http\Controllers\PedidoController::class,'reportePedido3']);
Route::get('/reportePedidoTipo/{fecha}/{tipo}',[\App\Http\Controllers\PedidoController::class,'reportePedidoTipo']);
Route::get('/reportePedidoZona/{fecha}/{placa}',[\App\Http\Controllers\PedidoController::class,'reportePedidoZona']);
Route::get('/reportePedidoZonaTotal/{fecha}',[\App\Http\Controllers\PedidoController::class,'reportePedidoZonaTotal']);
Route::get('/reportePedidoProductos/{fecha}', [\App\Http\Controllers\PedidoController::class,'reportePedidoProductos']);
Route::get('/reportePedidoOnly/{id}',[\App\Http\Controllers\PedidoController::class,'reportePedidoOnly']);


Route::post('/importPedido',[\App\Http\Controllers\MobilController::class,'importPedido']);
Route::post('/exportar-pedidos', [MobilController::class, 'exportarPedidosFlutter']);
Route::post('/reporteTotalProductos', [MobilController::class, 'reporteTotalProductos']);

Route::get('/pedidos-simple', [\App\Http\Controllers\MobilController::class, 'pedidosSimple']);
