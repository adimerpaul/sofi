import { route } from 'quasar/wrappers'
import { createRouter, createMemoryHistory, createWebHistory, createWebHashHistory } from 'vue-router'
import routes from './routes'
import store from '../store'

/*
 * If not building with SSR mode, you can
 * directly export the Router instantiation;
 *
 * The function below can be async too; either use
 * async/await or return a Promise which resolves
 * with the Router instance.
 */

export default route(function (/* { store, ssrContext } */) {
  const createHistory = process.env.SERVER
    ? createMemoryHistory
    : (process.env.VUE_ROUTER_MODE === 'history' ? createWebHistory : createWebHashHistory)

  const Router = createRouter({
    scrollBehavior: () => ({ left: 0, top: 0 }),
    routes,

    // Leave this as is and make changes in quasar.conf.js instead!
    // quasar.conf.js -> build -> vueRouterMode
    // quasar.conf.js -> build -> publicPath
    history: createHistory(process.env.MODE === 'ssr' ? void 0 : process.env.VUE_ROUTER_BASE)
  })
  // Despues de un deploy los archivos de cada pagina cambian de nombre: quien
  // tenia la app abierta pide el viejo, ya borrado, y la pagina no abre. En
  // ese caso se recarga una vez para tomar la version nueva (con un tope por
  // si el error fuera otro, para no quedar recargando sin fin).
  const recargarPorVersionNueva = (destino) => {
    try {
      const ultima = Number(sessionStorage.getItem('recarga-version') || 0)
      if (Date.now() - ultima < 10000) return false
      sessionStorage.setItem('recarga-version', String(Date.now()))
    } catch (e) { /* sin sessionStorage igual se recarga */ }
    if (destino) window.location.hash = '#' + destino
    window.location.reload()
    return true
  }
  if (typeof window !== 'undefined') {
    // Vite avisa con este evento cuando no pudo bajar un archivo de la app.
    window.addEventListener('vite:preloadError', (event) => {
      if (recargarPorVersionNueva()) event.preventDefault()
    })
  }
  Router.onError((error, to) => {
    const mensaje = String(error && error.message)
    if (/dynamically imported module|Importing a module script failed|Failed to fetch|error loading dynamically/i.test(mensaje)) {
      recargarPorVersionNueva(to && to.fullPath)
    }
  })

  Router.beforeEach((to, from, next) => {
    // console.log(store().getters['showcase/isLoggedIn'])
    if(to.matched.some(record => record.meta.requiresAuth)) {
      // console.log(store().getters.l)
      if (store.getters['login/isLoggedIn']) {
        next()
        return
      }
      next('/login')
    } else {
      next()
    }
  })

  return Router
})
