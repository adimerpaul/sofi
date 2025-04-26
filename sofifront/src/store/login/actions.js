/*
export function someAction (context) {
}
*/
import {api} from '../../boot/axios'
export function login({commit}, user) {
  return new Promise((resolve, reject) => {
    commit('auth_request')
    // api({url: 'http://localhost:8000/axios/login', data: user, method: 'POST' })
    api.post('login', user)
      .then(resp => {
        // console.log(resp.data)
        const token = resp.data.token
        const user = resp.data.user
        localStorage.setItem('tokensofia', token)
        api.defaults.headers.common['Authorization'] = 'Bearer '+token
        commit('auth_success', {token, user})
        resolve(resp)
      })
      .catch(err => {
        commit('auth_error')
        localStorage.removeItem('tokensofia')
        reject(err)
      })
  })
}
export function logout({commit}){
  return new Promise((resolve, reject) => {
    api.post('logout').then(res=>{
      commit('salir')
      localStorage.removeItem('tokensofia')
      delete api.defaults.headers.common['Authorization']
      resolve()
    }).catch(err => {
      commit('auth_error')
      localStorage.removeItem('tokensofia')
      reject(err)
    })
  })
}
