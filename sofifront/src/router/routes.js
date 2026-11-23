import Login from 'pages/Login.vue'

const routes = [
  {
    path: '/',
    component: () => import('layouts/MainLayout.vue'),
    children: [
      { path: '', component: () => import('pages/Index.vue')},
      { path: '/asignar', component: () => import('pages/Asignar.vue'),meta: {requiresAuth: true} },
      { path: '/misasignaciones', component: () => import('pages/Misasignaciones.vue') ,meta: {requiresAuth: true}},
      { path: '/mispedidos', component: () => import('pages/Mispedidos.vue') ,meta: {requiresAuth: true}},
      { path: '/mispedidostotales', component: () => import('pages/Mispedidostotales.vue') ,meta: {requiresAuth: true}},
      { path: '/cobranza', component: () => import('pages/Cobranza.vue') ,meta: {requiresAuth: true}},
      { path: '/visita', component: () => import('pages/Visita.vue') ,meta: {requiresAuth: true}},
      { path: '/miscobranzas', component: () => import('pages/Miscobranzas.vue') ,meta: {requiresAuth: true}},
      { path: '/clientes', component: () => import('pages/Clientes.vue') ,meta: {requiresAuth: true}},
      { path: '/generar', component: () => import('pages/Generar.vue') ,meta: {requiresAuth: true}},
      { path: '/genreporte', component: () => import('pages/GenerarReporte.vue') ,meta: {requiresAuth: true}},
      { path: '/cobrosrealizados', component: () => import('pages/Cobrosrealizados.vue') ,meta: {requiresAuth: true}},
      { path: '/modifica', component: () => import('pages/Modifica.vue') ,meta: {requiresAuth: true}},
      { path: '/pendientes', component: () => import('pages/Pendientes.vue') ,meta: {requiresAuth: true}},
      { path: '/monitoreo', component: () => import('pages/Monitoreo.vue') ,meta: {requiresAuth: true}},
      { path: '/ruta', component: () => import('pages/Ruta.vue') ,meta: {requiresAuth: true}},
      { path: '/reporte', component: () => import('pages/Reporte.vue') ,meta: {requiresAuth: true}},
      { path: '/productos', component: () => import('pages/Productos.vue') ,meta: {requiresAuth: true}},
      { path: '/precios', component: () => import('pages/Precios.vue'), meta: { requiresAuth: true } },
      { path: '/nopedido', component: () => import('pages/Nopedido.vue') ,meta: {requiresAuth: true}},
      { path: '/almacen', component: () => import('pages/AlmacenPage.vue') ,meta: {requiresAuth: true}},
      { path: '/almacenVerificar', component: () => import('pages/AlmacenVerificar.vue') ,meta: {requiresAuth: true}},
      { path: '/almacenVerificado', component: () => import('pages/AlmacenVerificado.vue') ,meta: {requiresAuth: true}},
      { path: '/almacenRiesgo', component: () => import('pages/AlmacenRiesgo.vue') ,meta: {requiresAuth: true}},
      { path: '/entrega', component: () => import('pages/Entregas.vue') ,meta: {requiresAuth: true}},
      // Entregas del dia sobre facturacion (reemplaza a /entrega).
      { path: '/entregafactura', component: () => import('pages/EntregaFactura.vue'), meta: { requiresAuth: true } },
      { path: '/despacho', component: () => import('pages/Despacho.vue') ,meta: {requiresAuth: true}},
      { path: '/clientevisita', component: () => import('pages/Clientevisita.vue') ,meta: {requiresAuth: true}},
      // Alta de cliente paso a paso del preventista (reemplaza el JotForm de "Alta Cliente").
      { path: '/paretoclientes', component: () => import('pages/ParetoClientes.vue'), meta: { requiresAuth: true } },
      { path: '/avanceventas', component: () => import('pages/AvanceVentas.vue'), meta: { requiresAuth: true } },
      { path: '/avanceventasvendedores', component: () => import('pages/AvanceVentas.vue'), meta: { requiresAuth: true, mio: true } },
      { path: '/clientenuevo', component: () => import('pages/ClienteNuevo.vue'), meta: { requiresAuth: true } },
      { path: '/clientepedido', component: () => import('pages/Pedidoresumen.vue') ,meta: {requiresAuth: true}},
      { path: '/listpedido', component: () => import('pages/Despachoresumen.vue') ,meta: {requiresAuth: true}},
      { path: '/avance', component: () => import('pages/Avance.vue') ,meta: {requiresAuth: true}},
      { path: '/mapavendedor', component: () => import('pages/MapaVendedor/MapaVendedor.vue') ,meta: {requiresAuth: true}},
      { path: '/mapavendedorvisita', component: () => import('pages/MapaVendedor/MapaVendedorVisita.vue') ,meta: {requiresAuth: true}},
      { path: '/mapacliente', component: () => import('pages/MapaCliente/MapaCliente.vue') ,meta: {requiresAuth: true}},
      { path: '/bonificaciones', component: () => import('pages/Bonificaciones/Bonificaciones.vue'), meta: { requiresAuth: true } },
      { path: '/pedidos', component: () => import('pages/Pedidos/PedidosLista.vue'), meta: { requiresAuth: true } },
      { path: '/ventas', component: () => import('pages/Ventas/VentasLista.vue'), meta: { requiresAuth: true } },
      { path: '/facturacion', component: () => import('pages/Facturacion/FacturasLista.vue'), meta: { requiresAuth: true } },
      { path: '/facturacion/pedidos', component: () => import('pages/Facturacion/PedidosFactura.vue'), meta: { requiresAuth: true } },
      { path: '/facturacion/pedidos/:pedido/:tipo', component: () => import('pages/Facturacion/PedidoFacturaEditar.vue'), meta: { requiresAuth: true } },
      { path: '/facturacion/nueva', component: () => import('pages/Facturacion/FacturaNueva.vue'), meta: { requiresAuth: true } },
      { path: '/facturacion/reportes', component: () => import('pages/Facturacion/Reportes.vue'), meta: { requiresAuth: true } },
      { path: '/facturacion/motivos-anulacion', component: () => import('pages/Facturacion/MotivosAnulacion.vue'), meta: { requiresAuth: true } },
      { path: '/facturacion/horarios-envio', component: () => import('pages/Facturacion/HorariosPedido.vue'), meta: { requiresAuth: true } },
      { path: '/impuestos', component: () => import('pages/Impuestos/Impuestos.vue'), meta: { requiresAuth: true } },
      { path: '/facturacion-ficticia', component: () => import('pages/Facturacion/FacturacionFicticia.vue'), meta: { requiresAuth: true } },
      // Libro de ventas del SIAT contra facturas de Sofia por CUF: /verificacion-facturas*.
      { path: '/verificacion-facturas', component: () => import('pages/Facturacion/VerificacionFacturas.vue'), meta: { requiresAuth: true } },
      { path: '/compras', component: () => import('pages/Compras/ComprasLista.vue'), meta: { requiresAuth: true } },
      { path: '/compras/nueva', component: () => import('pages/Compras/CompraNueva.vue'), meta: { requiresAuth: true } },
      { path: '/compras/transformaciones', component: () => import('pages/Compras/Transformaciones.vue'), props: { modo: 'ver' }, meta: { requiresAuth: true } },
      { path: '/compras/transformaciones/nueva', component: () => import('pages/Compras/Transformaciones.vue'), props: { modo: 'crear' }, meta: { requiresAuth: true } },
      { path: '/proveedores', component: () => import('pages/Compras/ProveedoresLista.vue'), meta: { requiresAuth: true } },
      // Lo del caminero: la placa sale de su usuario, asi que ambas paginas
      // muestran solo su camion sin filtro que elegir.
      { path: '/caminero/carga', component: () => import('pages/Caminero/CargaVerificar.vue'), meta: { requiresAuth: true } },
      { path: '/caminero/entregas', component: () => import('pages/Caminero/MisEntregas.vue'), meta: { requiresAuth: true } },
      { path: '/caminero/reporte', component: () => import('pages/Caminero/ReporteEntregas.vue'), meta: { requiresAuth: true } },
      // Administracion del recojo: consume GET /cobranzas/recojo, /camiones y /pdf.
      { path: '/cobranzas/recojo', component: () => import('pages/Cobranzas/RecojoCamiones.vue'), meta: { requiresAuth: true } },
      // GET /creditos y /creditos/clientes; POST /creditos; GET/POST /creditos/{origen}/{id}/abonos.
      { path: '/cobranzas/creditos', component: () => import('pages/Cobranzas/CreditosClientes.vue'), meta: { requiresAuth: true } },
      // Cobros de vendedores por confirmar: GET /creditos/cobros-vendedor; POST /{id}/confirmar y /{id}/rechazar.
      { path: '/cobranzas/creditos/cobros', component: () => import('pages/Cobranzas/CobrosVendedores.vue'), meta: { requiresAuth: true } },
      // Cobro simple: GET /vendedor/creditos/clientes y /{cliente}; POST /{cliente}/cobros.
      { path: '/vendedor/creditos', component: () => import('pages/Cobranzas/CobrarCreditosVendedor.vue'), meta: { requiresAuth: true } },
      // Lo que cobro el vendedor y su estado: GET /vendedor/creditos/cobros?desde&hasta.
      { path: '/vendedor/creditos/cobros', component: () => import('pages/Cobranzas/MisCobrosCreditos.vue'), meta: { requiresAuth: true } },
      { path: '/cobranzas/verificacion', component: () => import('pages/Cobranzas/VerificarFacturacion.vue'), meta: { requiresAuth: true } },
      // GET /cobranzas/verificacion/clientes y /clientes/{cliente}/facturas; el tilde usa POST /cobranzas/verificacion.
      { path: '/cobranzas/verificacion/clientes', component: () => import('pages/Cobranzas/VerificarCliente.vue'), meta: { requiresAuth: true } },
      { path: '/encuestasIndex', component: () => import('pages/encuesta/EncuestaIndex.vue'), meta: { requiresAuth: true } },
      { path: '/horariosenvio', component: () => import('pages/HorariosEnvio.vue'), meta: { requiresAuth: true } },
      // ClienteFotografias
      { path: '/clientefotografias', component: () => import('pages/clientefotografias/ClienteFotografias.vue'), meta: { requiresAuth: true } },
      { path: '/usuario', component: () => import('pages/Usuario.vue'), meta: { requiresAuth: true } },
      { path: '/login', component: Login },
    ]
  },
  {
    // La encuesta pública ya NO vive en el SPA: la renderiza el backend
    // (GET /encuesta/{idcliente}/{iduser}) para que el cliente pueda recargar
    // con F5 y siempre vea los datos actuales. Esta ruta solo redirige los
    // QR/enlaces antiguos con hash.
    path: '/encuesta/:idcliente/:iduser',
    component: () => import('pages/encuesta/EncuestaRedirect.vue'),
  },

  // Always leave this as last one,
  // but you can also remove it
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/Error404.vue')
  }
]

export default routes
