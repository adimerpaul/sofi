const routes = [
  {
    path: '/',
    component: () => import('layouts/MainLayout.vue'),
    children: [
      { path: '', component: () => import('pages/IndexPage.vue'), meta: { requiresAuth: true } },
      { path: 'usuarios', component: () => import('pages/usuarios/Usuarios.vue'), meta: { requiresAuth: true } },
      { path: 'clientes', name: 'clientes', component: () => import('pages/clientes/Clientes.vue'), meta: { requiresAuth: true, permission: 'Clientes' } },
      { path: 'alta-cliente', name: 'alta-cliente', component: () => import('pages/clientes/AltaCliente.vue'), meta: { requiresAuth: true, permission: 'Clientes' } },
      { path: 'productos', name: 'productos', component: () => import('pages/productos/Productos.vue'), meta: { requiresAuth: true } },
      { path: 'venta', name: 'venta', component: () => import('pages/ventas/Ventas.vue'), meta: { requiresAuth: true } },
      { path: 'ventaNuevo', name: 'ventaNuevo', component: () => import('pages/ventas/VentaNew.vue'), meta: { requiresAuth: true } },
      { path: 'proveedores', name: 'proveedores', component: () => import('pages/proveedores/Proveedores.vue'), meta: { requiresAuth: true } },
      { path: 'compras', name: 'compras', component: () => import('pages/compras/Compras.vue'), meta: { requiresAuth: true } },
      // impuestos
      { path: 'impuestos', name: 'impuestos', component: () => import('pages/impuestos/Impuestos.vue'), meta: { requiresAuth: true } },
      { path: 'compras-create', name: 'compras-create', component: () => import('pages/compras/ComprasCreate.vue'), meta: { requiresAuth: true } },
      { path: 'productos-vencer', name: 'productos-vencer', component: () => import('pages/productos/ProductosVencer.vue'), meta: { requiresAuth: true } },
      { path: 'productos-vencidos', name: 'productos-vencidos', component: () => import('pages/productos/ProductosVencidos.vue'), meta: { requiresAuth: true } },
      { path: 'pedidos', name: 'pedidos', component: () => import('pages/pedidos/Pedidos.vue'), meta: { requiresAuth: true } },
      { path: 'pedidosCompra', name: 'pedidosCompra', component: () => import('pages/pedidos/PedidosCompra.vue'), meta: { requiresAuth: true } },
      { path: 'mis-pedidos', name: 'mis-pedidos', component: () => import('pages/pedidos/MisPedidos.vue'), meta: { requiresAuth: true } },
      { path: 'mis-pedidos-totales', name: 'mis-pedidos-totales', component: () => import('pages/pedidos/MisPedidosTotales.vue'), meta: { requiresAuth: true, permission: 'Mis pedidos totales' } },
      { path: 'mapa-cliente', name: 'mapa-cliente', component: () => import('pages/mapa/MapaCliente.vue'), meta: { requiresAuth: true, permission: 'Mapa cliente' } },
      { path: 'mapa-zona', name: 'mapa-zona', component: () => import('pages/mapa/MapaZona.vue'), meta: { requiresAuth: true, permission: 'Mapa zona' } },
      { path: 'mapa-zonas', name: 'mapa-zonas', component: () => import('pages/mapa/ZonasMapa.vue'), meta: { requiresAuth: true, permission: 'Mapa cliente zonas' } },
      { path: 'auxiliar-camara', name: 'auxiliar-camara', component: () => import('pages/auxiliar/AuxiliarCamara.vue'), meta: { requiresAuth: true, permission: 'Auxiliar de camara' } },
      { path: 'verificacion', name: 'verificacion', component: () => import('pages/ventas/VerificacionVentas.vue'), meta: { requiresAuth: true, permission: 'Verificacion' } },
      { path: 'digitador-factura', name: 'digitador-factura', component: () => import('pages/facturas/DigitadorFactura.vue'), meta: { requiresAuth: true, permission: 'Digitador factura' } },
      { path: 'cobranzas', name: 'cobranzas', component: () => import('pages/cobranzas/Cobranzas.vue'), meta: { requiresAuth: true, permission: 'Cobranzas' } },
      { path: 'historial-cobranzas', name: 'historial-cobranzas', component: () => import('pages/cobranzas/HistorialCobranzas.vue'), meta: { requiresAuth: true, permission: 'Cobranzas' } },
      { path: 'rutas-camion', name: 'rutas-camion', component: () => import('pages/despacho/RutasCamion.vue'), meta: { requiresAuth: true, permission: 'Rutas', allowCamion: true } },
      { path: 'despacho-camion', name: 'despacho-camion', component: () => import('pages/despacho/DespachoCamion.vue'), meta: { requiresAuth: true, permission: 'Despacho', allowCamion: true } },
      { path: 'visitas', name: 'visitas', component: () => import('pages/visitas/Visitas.vue'), meta: { requiresAuth: true } },
    ]
  },
  {
    path: '/login',
    component: () => import('pages/Login.vue')
  },
  // Always leave this as last one,
  // but you can also remove it
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/ErrorNotFound.vue')
  }
]

export default routes
