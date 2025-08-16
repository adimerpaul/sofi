// src/boot/firebase.js
import { boot } from 'quasar/wrappers'
import firebase from 'firebase/compat/app'
import 'firebase/compat/auth'

const firebaseConfig = {
  apiKey:     process.env.FB_API_KEY,
  authDomain: process.env.FB_AUTH_DOMAIN,
  projectId:  process.env.FB_PROJECT_ID,
  appId:      process.env.FB_APP_ID
}

if (!firebase.apps.length) {
  firebase.initializeApp(firebaseConfig)
}

export default boot(({ app }) => {
  const auth = firebase.auth()

  // expón firebase si lo necesitas en otros sitios
  app.config.globalProperties.$firebase = firebase

  // estado de auth → hidrata $encUser y guarda en localStorage
  auth.onAuthStateChanged(async (user) => {
    if (user) {
      const token = await user.getIdToken()
      const encUser = {
        uid: user.uid,
        email: user.email,
        name: user.displayName,
        photo: user.photoURL,
        idToken: token
      }
      localStorage.setItem('enc_user', JSON.stringify(encUser))
      app.config.globalProperties.$encUser = encUser
    } else {
      localStorage.removeItem('enc_user')
      app.config.globalProperties.$encUser = null
    }
  })

  // helpers de login
  app.config.globalProperties.$loginGoogle = async () => {
    const provider = new firebase.auth.GoogleAuthProvider()
    const res = await auth.signInWithPopup(provider)
    return res.user
  }

  app.config.globalProperties.$loginWithEmail = async (email, password) => {
    const res = await auth.signInWithEmailAndPassword(email, password)
    return res.user
  }

  app.config.globalProperties.$logout = async () => {
    await auth.signOut()
  }

  // Adjunta email y (opcional) idToken a TODAS las requests de axios
  const api = app.config.globalProperties.$api
  api.interceptors.request.use(async (config) => {
    const user = auth.currentUser
    if (user) {
      const token = await user.getIdToken()
      config.headers['X-User-Email'] = user.email || ''
      // si vas a verificar token Firebase en Laravel, descomenta:
      // config.headers['Authorization'] = `Bearer ${token}`
    }
    return config
  })
})
