// src/boot/firebase.js
import firebase from 'firebase/compat/app'
import 'firebase/compat/auth'

// ⚠️ Pega aquí tus claves del proyecto Firebase
const firebaseConfig = {
  apiKey:        process.env.FB_API_KEY,
  authDomain:    process.env.FB_AUTH_DOMAIN,
  projectId:     process.env.FB_PROJECT_ID,
  appId:         process.env.FB_APP_ID,
  // (opcional) measurementId, etc.
}

if (!firebase.apps.length) {
  firebase.initializeApp(firebaseConfig)
}

export default async ({ Vue, store }) => {
  // intenta restaurar sesión guardada
  const saved = localStorage.getItem('enc_user')
  if (saved) {
    try {
      const parsed = JSON.parse(saved)
      // NO reautentica, solo rehidrata la UI con el email
      Vue.prototype.$encUser = parsed
    } catch (_) {}
  }

  // helper: login con Google
  Vue.prototype.$loginGoogle = async () => {
    const provider = new firebase.auth.GoogleAuthProvider()
    const res = await firebase.auth().signInWithPopup(provider)
    const user = res.user
    const idToken = await user.getIdToken()
    const encUser = {
      uid: user.uid,
      email: user.email,
      name: user.displayName,
      photo: user.photoURL,
      idToken
    }
    localStorage.setItem('enc_user', JSON.stringify(encUser))
    Vue.prototype.$encUser = encUser
    return encUser
  }

  // helper: logout
  Vue.prototype.$logout = async () => {
    await firebase.auth().signOut()
    localStorage.removeItem('enc_user')
    Vue.prototype.$encUser = null
  }

  // expón firebase si lo necesitas
  Vue.prototype.$firebase = firebase
}
