import {boot} from 'quasar/wrappers'
import axios from 'axios'
import {Alert} from "src/addons/Alert";
import {useCounterStore} from "stores/example-store";
import moment from "moment";
// import {io} from "socket.io-client";
import VueApexCharts from "vue3-apexcharts";

// Be careful when using SSR for cross-request state pollution
// due to creating a Singleton instance here;
// If any client changes this (global) instance, it might be a
// good idea to move this instance creation inside of the
// "export default () => {}" function below (which runs individually
// for each client)
const api = axios.create({ baseURL: 'https://api.example.com' })

export default boot(({ app, router }) => {
  app.use(VueApexCharts);
  // for use inside Vue files (Options API) through this.$axios and this.$api

  app.config.globalProperties.$axios = axios.create({ baseURL: import.meta.env.VITE_API_BACK })
  // console.log(import.meta.env.VITE_API_BACK)
  app.config.globalProperties.$alert = Alert
  app.config.globalProperties.$agencias = ['Almacen','Challgua','Socavon','Catalina']
  // app.config.globalProperties.$socket = io(import.meta.env.VITE_API_SOCKET)
  app.config.globalProperties.$store = useCounterStore()
  app.config.globalProperties.$url = import.meta.env.VITE_API_BACK
  app.config.globalProperties.$filters = {
    dateDmYHis (value) {
      const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic']
      const mes = meses[moment(String(value)).format('MM') - 1]
      if (!value) return ''
      return moment(String(value)).format('DD') + ' ' + mes + ' ' + moment(String(value)).format('YYYY') + ' ' + moment(String(value)).format('hh:mm A')
    },
    date: (value) => {
      if (!value) return ''
      return new Date(value).toLocaleDateString()
    },
    time: (value) => {
      if (!value) return ''
      return new Date(value).toLocaleTimeString()
    },
    textUpper: (value) => {
      if (!value) return ''
      const lower = value.toLowerCase()
      return lower.charAt(0).toUpperCase() + lower.slice(1)
    },
  }
  const token = localStorage.getItem('tokenProvidencia')
  if (token) {
    app.config.globalProperties.$axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
    app.config.globalProperties.$axios.get('me').then(response => {
      useCounterStore().isLogged = true
      useCounterStore().user = response.data.user
      useCounterStore().env = response.data.datos
      useCounterStore().permissions = response.data.permissions
      localStorage.setItem('user', JSON.stringify(response.data))
    }).catch(error => {
      console.log(error)
      localStorage.removeItem('tokenProvidencia')
      useCounterStore().isLogged = false
      useCounterStore().permissions = []
      useCounterStore().user = {}
      router.push('/login')
    })
  }
  app.config.globalProperties.$api = api
  // ^ ^ ^ this will allow you to use this.$api (for Vue Options API form)
  //       so you can easily perform requests against your app's API
})

export { api }
