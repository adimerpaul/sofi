<template>
  <q-layout view="lhr Lpr lfr" style="min-height: 0">
    <q-page-container>
      <q-page class="flex flex-center bg-grey-1">
        <q-card class="enc-card q-pa-md">

          <!-- Estado de sesión -->
          <q-banner
            class="q-mb-md"
            :class="user ? 'bg-positive text-white' : 'bg-grey-2'"
            rounded dense
          >
            <div class="row items-center no-wrap">
              <div class="col">
                <div v-if="user">
                  Sesión iniciada como
                  <span class="text-weight-bold">{{ user.email }}</span>
                </div>
                <div v-else>
                  Inicia sesión con Google para responder la encuesta
                </div>
              </div>
              <div class="col-auto" v-if="user">
                <q-btn dense flat icon="logout" label="Salir" @click="logout" />
              </div>
            </div>
          </q-banner>

          <!-- Botón Google (solo si NO hay sesión) -->
          <div v-if="!user" class="q-mb-lg">
            <q-btn
              class="btn-google full-width"
              :loading="authLoading"
              @click="loginGoogle"
              unelevated
            >
              <div class="row items-center no-wrap full-width">
                <img class="g-logo q-mr-sm" alt="G" src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg">
                <div class="col text-center text-weight-medium">Continuar con Google</div>
              </div>
            </q-btn>
          </div>

          <!-- Encuesta (solo si HAY sesión) -->
          <div v-else>
            <div class="row no-wrap items-center">
              <!-- Barra -->
              <div class="col-auto q-pr-md column items-center">
                <q-icon name="add" size="24px" class="text-positive q-mb-sm" />
                <div class="bar-wrapper">
                  <div class="bar-track">
                    <div
                      class="bar-fill"
                      :style="{
                        height: fillHeight + '%',
                        background: 'linear-gradient(180deg, #21ba45 0%, #f2c037 50%, #f44336 100%)'
                      }"
                    />
                  </div>
                  <div class="bar-tick" :style="{ bottom: fillHeight + '%' }"></div>
                </div>
              </div>

              <!-- Opciones -->
              <div class="col column justify-around q-gutter-md">

                <!-- 10 -->
                <q-item clickable v-ripple @click="select(10)" class="rounded-md">
                  <q-item-section avatar>
                    <q-btn round unelevated size="lg" :color="isSel(10) ? 'positive' : 'green-5'">
                      <q-icon name="sentiment_very_satisfied" size="28px" />
                    </q-btn>
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-weight-bold">Perfecto!</q-item-label>
                    <q-item-label caption>¡Todo excelente!</q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <div class="score text-positive">10</div>
                  </q-item-section>
                </q-item>

                <!-- 5 -->
                <q-item clickable v-ripple @click="select(5)" class="rounded-md">
                  <q-item-section avatar>
                    <q-btn round unelevated size="lg" :color="isSel(5) ? 'warning' : 'amber-5'">
                      <q-icon name="sentiment_neutral" size="28px" />
                    </q-btn>
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-weight-bold">Bueno</q-item-label>
                    <q-item-label caption>Pudo ser mejor</q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <div class="score text-amber-8">5</div>
                  </q-item-section>
                </q-item>

                <!-- 0 -->
                <q-item clickable v-ripple @click="select(0)" class="rounded-md">
                  <q-item-section avatar>
                    <q-btn round unelevated size="lg" :color="isSel(0) ? 'negative' : 'red-5'">
                      <q-icon name="sentiment_very_dissatisfied" size="28px" />
                    </q-btn>
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-weight-bold">Malo</q-item-label>
                    <q-item-label caption>No me gustó</q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <div class="score text-negative">0</div>
                  </q-item-section>
                </q-item>

                <transition name="fade">
                  <div v-if="sent" class="q-mt-sm text-center text-positive">
                    ¡Gracias por tu respuesta! ✅
                  </div>
                </transition>
              </div>
            </div>

            <!-- Comentario + Enviar -->
            <div class="q-mt-md">
              <q-input
                v-model="comment"
                type="textarea"
                dense autogrow clearable
                placeholder="Comentario (opcional)"
                :disable="loading"
              />
              <q-btn
                class="q-mt-sm"
                color="primary"
                label="Enviar"
                :loading="loading"
                :disable="score === null"
                @click="submit"
                unelevated rounded icon="send"
              />
            </div>
          </div>

        </q-card>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<script>
export default {
  name: 'EncuestaSatisfaccion',
  props: {
    refId: { type: [String, Number], default: null },
    autoSubmit: { type: Boolean, default: true }
  },
  data () {
    return {
      // encuesta
      score: null,
      comment: '',
      loading: false,
      sent: false,

      // auth
      user: null,          // { uid, email, name, photo, idToken }
      authLoading: false,
      // _unsubAuth: null
    }
  },
  computed: {
    fillHeight () {
      if (this.score === 10) return 100
      if (this.score === 5)  return 50
      if (this.score === 0)  return 0
      return 0
    }
  },
  mounted () {
    // rehidratar
    const saved = localStorage.getItem('enc_user')
    if (saved) { try { this.user = JSON.parse(saved) } catch (_) {} }

    // escuchar auth de Firebase (inyectado por boot/firebase.js)
    if (this.$firebase) {
      this._unsubAuth = this.$firebase.auth().onAuthStateChanged(async u => {
        if (u) {
          const idToken = await u.getIdToken()
          this.user = {
            uid: u.uid,
            email: u.email,
            name: u.displayName,
            photo: u.photoURL,
            idToken
          }
          localStorage.setItem('enc_user', JSON.stringify(this.user))
        } else {
          this.user = null
          localStorage.removeItem('enc_user')
        }
      })
    }
  },
  // beforeDestroy () {
  //   if (this._unsubAuth) this._unsubAuth()
  // },
  methods: {
    isSel (v) { return this.score === v },
    async select (v) {
      this.score = v
      if (this.autoSubmit && this.user) await this.submit()
      if (this.autoSubmit && !this.user) {
        this.$q.notify({ type: 'warning', message: 'Inicia sesión con Google para enviar' })
      }
    },

    async loginGoogle () {
      try {
        this.authLoading = true
        // usa helper del boot si existe…
        if (this.$loginGoogle) {
          await this.$loginGoogle()
          return
        }
        // …o fallback directo
        const provider = new this.$firebase.auth.GoogleAuthProvider()
        const res = await this.$firebase.auth().signInWithPopup(provider)
        const u = res.user
        const idToken = await u.getIdToken()
        this.user = { uid: u.uid, email: u.email, name: u.displayName, photo: u.photoURL, idToken }
        localStorage.setItem('enc_user', JSON.stringify(this.user))
      } catch (e) {
        console.error(e)
        this.$q.notify({ type: 'negative', message: 'No se pudo iniciar con Google' })
      } finally {
        this.authLoading = false
      }
    },

    async logout () {
      try {
        if (this.$logout) await this.$logout()
        else await this.$firebase.auth().signOut()
        this.user = null
        localStorage.removeItem('enc_user')
      } catch (e) {
        console.error(e)
      }
    },

    async submit () {
      if (this.score === null) return
      if (!this.user) {
        this.$q.notify({ type: 'warning', message: 'Inicia sesión para enviar' })
        return
      }
      try {
        this.loading = true
        const payload = {
          score: this.score,
          comment: this.comment || null,
          ref_id: this.refId,
          email: this.user.email,       // << envía el email
          ua: navigator.userAgent
        }
        await this.$api.post('encuestas', payload)
        this.sent = true
        this.$q.notify({ type: 'positive', message: '¡Enviado!' })
      } catch (e) {
        console.error(e)
        this.$q.notify({ type: 'negative', message: 'Error al enviar' })
      } finally {
        this.loading = false
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.enc-card { width: 380px; max-width: 92vw; border-radius: 16px; }

/* Google button */
.btn-google {
  background: #fff;
  color: #202124;
  border: 1px solid rgba(0,0,0,.12);
  text-transform: none;
}
.g-logo { width: 18px; height: 18px; }

/* Barra vertical */
.bar-wrapper { position: relative; height: 220px; width: 18px; }
.bar-track {
  position: absolute; left: 50%; transform: translateX(-50%);
  height: 100%; width: 8px; background: #eaeaea; border-radius: 6px;
  overflow: hidden; box-shadow: inset 0 0 0 1px rgba(0,0,0,.06);
}
.bar-fill {
  position: absolute; left: 0; bottom: 0; width: 100%;
  border-radius: 0 0 6px 6px; transition: height .25s ease;
}
.bar-tick {
  position: absolute; left: 50%; transform: translate(-50%, 50%);
  width: 14px; height: 4px; background: rgba(0,0,0,.25);
  border-radius: 2px; transition: bottom .25s ease;
}

.score { font-size: 20px; font-weight: 800; }
.fade-enter-active, .fade-leave-active { transition: opacity .2s }
.fade-enter, .fade-leave-to { opacity: 0 }
.rounded-md { border-radius: 12px }
</style>
