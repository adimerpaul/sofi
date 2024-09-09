import { boot } from 'quasar/wrappers'
import axios from 'axios'
import store from "src/store";
import moment from "moment";
// import HighchartsVue from 'highcharts-vue'
// Be careful when using SSR for cross-request state pollution
// due to creating a Singleton instance here;
// If any client changes this (global) instance, it might be a
// good idea to move this instance creation inside of the
// "export default () => {}" function below (which runs individually
// for each client)
const api = axios.create({ baseURL: process.env.API })

export default boot(({ app,store }) => {
  // app.use(HighchartsVue)
  // for use inside Vue files (Options API) through this.$axios and this.$api
  // console.log(store.state.login.url)
  // app.config.globalProperties.$axios = axios
  // ^ ^ ^ this will allow you to use this.$axios (for Vue Options API form)
  //       so you won't necessarily have to import axios in each vue file

  app.config.globalProperties.$api = api
  app.config.globalProperties.$url = process.env.API
  app.config.globalProperties.$filters = {
    capitalize: function (value) {
        if (!value) return ''
        value = value.toString()
        value = value.toLowerCase()
        return value.charAt(0).toUpperCase() + value.slice(1)
    },
    dateYmd: function (value) {
        if (!value) return ''
        const meses=['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic']
        return moment(value).format('DD')+'-'+meses[moment(value).format('MM')-1]+'-'+moment(value).format('YYYY')
    }
  }
  //app.config.globalProperties.$api.defaults.baseURL=store.state.login.url+'api/'
  const token = localStorage.getItem('tokensofia')
  if (token) {
    app.config.globalProperties.$token = token
    app.config.globalProperties.$api.defaults.headers.common['Authorization'] = 'Bearer '+token
    app.config.globalProperties.$api.post(process.env.API+'me').then(res=>{
      // console.log(res.data);
      // return false;
      // store.state.user=res.data;
      // store().commit('login/auth_success', {token:token,user:res.data})
      store.commit('login/auth_success',{token:token,user:res.data})
    }).catch(err=>{
      // console.error('aas')
      store.commit('login/salir')
      localStorage.removeItem('tokensofia')
    })
  }
  // ^ ^ ^ this will allow you to use this.$api (for Vue Options API form)
  //       so you can easily perform requests against your app's API
})

export { api }
