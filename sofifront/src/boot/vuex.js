import { boot } from 'quasar/wrappers'
import store from 'src/store'

/*
 * @quasar/app-vite v2 no longer registers Vuex automatically (it only
 * supports Pinia), so the store is installed here. This is what keeps
 * `this.$store` working inside components.
 *
 * Must stay first in the boot list of quasar.config.js.
 */

export default boot(({ app }) => {
  app.use(store)
})
