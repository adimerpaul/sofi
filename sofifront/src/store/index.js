import { createStore } from 'vuex'

// import example from './module-example'
import login from './login'

/*
 * @quasar/app-vite v2 ya no instancia el store por nosotros (solo soporta
 * Pinia), asi que aqui se exporta directamente la instancia de Vuex.
 * El registro en la app se hace en /src/boot/vuex.js.
 */

const Store = createStore({
  modules: {
    // example
    login
  },

  // enable strict mode (adds overhead!)
  // for dev mode and --debug builds only
  strict: process.env.DEBUGGING
})

export default Store
