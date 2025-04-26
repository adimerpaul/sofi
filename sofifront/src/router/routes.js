import Login from 'pages/Login'
import Index from "pages/Index";
import Asignar from "pages/Asignar";
import Cobranza from "pages/Cobranza";
import Misasignaciones from "pages/Misasignaciones";
import Mispedidos from "pages/Mispedidos";
import Visita from "pages/Visita";
import Miscobranzas from "pages/Miscobranzas";
import Clientes from "pages/Clientes";
import Generar from "pages/Generar";
import GenerarReporte from "pages/GenerarReporte";
import Cobrosrealizados from "pages/Cobrosrealizados";
import Modifica from "pages/Modifica";
import Pendientes from "pages/Pendientes";
import Monitoreo from "pages/Monitoreo";
import Ruta from "pages/Ruta";
import Reporte from "pages/Reporte";
import Productos from "pages/Productos";
import Nopedido from "pages/Nopedido";
import AlmacenPage from "pages/AlmacenPage.vue";
import Entregas from "pages/Entregas.vue";
import Despacho from "pages/Despacho.vue";
import Clientevisita from "pages/Clientevisita.vue";
import Pedidoresumen from "pages/Pedidoresumen.vue";
import Avance from "pages/Avance.vue";
import AlmacenVerificar from "pages/AlmacenVerificar.vue";
import AlmacenVerificado from "pages/AlmacenVerificado.vue";
import Despachoresumen from "pages/Despachoresumen.vue";

const routes = [
  {
    path: '/',
    component: () => import('layouts/MainLayout.vue'),
    children: [
      { path: '', component: Index},
      { path: '/asignar', component: Asignar,meta: {requiresAuth: true} },
      { path: '/misasignaciones', component: Misasignaciones ,meta: {requiresAuth: true}},
      { path: '/mispedidos', component: Mispedidos ,meta: {requiresAuth: true}},
      { path: '/cobranza', component: Cobranza ,meta: {requiresAuth: true}},
      { path: '/visita', component: Visita ,meta: {requiresAuth: true}},
      { path: '/miscobranzas', component: Miscobranzas ,meta: {requiresAuth: true}},
      { path: '/clientes', component: Clientes ,meta: {requiresAuth: true}},
      { path: '/generar', component: Generar ,meta: {requiresAuth: true}},
      { path: '/genreporte', component: GenerarReporte ,meta: {requiresAuth: true}},
      { path: '/cobrosrealizados', component: Cobrosrealizados ,meta: {requiresAuth: true}},
      { path: '/modifica', component: Modifica ,meta: {requiresAuth: true}},
      { path: '/pendientes', component: Pendientes ,meta: {requiresAuth: true}},
      { path: '/monitoreo', component: Monitoreo ,meta: {requiresAuth: true}},
      { path: '/ruta', component: Ruta ,meta: {requiresAuth: true}},
      { path: '/reporte', component: Reporte ,meta: {requiresAuth: true}},
      { path: '/productos', component: Productos ,meta: {requiresAuth: true}},
      { path: '/nopedido', component: Nopedido ,meta: {requiresAuth: true}},
      { path: '/almacen', component: AlmacenPage ,meta: {requiresAuth: true}},
      { path: '/almacenVerificar', component: AlmacenVerificar ,meta: {requiresAuth: true}},
      { path: '/almacenVerificado', component: AlmacenVerificado ,meta: {requiresAuth: true}},
      { path: '/entrega', component: Entregas ,meta: {requiresAuth: true}},
      { path: '/despacho', component: Despacho ,meta: {requiresAuth: true}},
      { path: '/clientevisita', component: Clientevisita ,meta: {requiresAuth: true}},
      { path: '/clientepedido', component: Pedidoresumen ,meta: {requiresAuth: true}},
      { path: '/listpedido', component: Despachoresumen ,meta: {requiresAuth: true}},
      { path: '/avance', component: Avance ,meta: {requiresAuth: true}},
      { path: '/mapavendedor', component: () => import('pages/MapaVendedor/MapaVendedor.vue') ,meta: {requiresAuth: true}},
      { path: '/mapacliente', component: () => import('pages/MapaCliente/MapaCliente.vue') ,meta: {requiresAuth: true}},
      { path: '/login', component: Login },
    ]
  },

  // Always leave this as last one,
  // but you can also remove it
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/Error404.vue')
  }
]

export default routes
