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
