import Login from 'pages/Login'
import Index from "pages/Index";
import Asignar from "pages/Asignar";
import Cobranza from "pages/Cobranza";
import Misasignaciones from "pages/Misasignaciones";
const routes = [
  {
    path: '/',
    component: () => import('layouts/MainLayout.vue'),
    children: [
      { path: '', component: Index},
      { path: '/asignar', component: Asignar,meta: {requiresAuth: true} },
      { path: '/misasignaciones', component: Misasignaciones ,meta: {requiresAuth: true}},
      { path: '/cobranza', component: Cobranza ,meta: {requiresAuth: true}},
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
