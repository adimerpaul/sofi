<template>
  <q-page class="login-page flex flex-center q-pa-md">
    <div class="login-wrapper">
      <q-card class="login-card" flat bordered>
        <div class="login-header">
          <div class="login-logo">
            <img src="logo.png" alt="Sofia" @error="logoError = true" v-if="!logoError">
            <q-icon v-else name="storefront" size="42px" color="primary"/>
          </div>
          <div class="text-h5 text-weight-bold text-white">Sistema Sofia</div>
          <div class="text-caption text-white text-opacity">Ingresa con tu carnet de identidad</div>
        </div>

        <q-card-section class="q-pt-lg">
          <q-banner
            v-if="error"
            dense
            rounded
            class="bg-red-1 text-red-9 q-mb-md login-error"
          >
            <template v-slot:avatar>
              <q-icon name="error_outline" color="negative"/>
            </template>
            {{ error }}
          </q-banner>

          <q-form @submit.prevent="login" ref="formRef" greedy>
            <q-input
              outlined
              v-model.trim="ci"
              label="Carnet de identidad"
              autofocus
              inputmode="numeric"
              autocomplete="username"
              :disable="loading"
              hide-bottom-space
              lazy-rules
              :rules="ciRules"
              @update:model-value="error = ''"
            >
              <template v-slot:prepend>
                <q-icon name="badge"/>
              </template>
            </q-input>

            <q-input
              class="q-mt-md"
              outlined
              v-model="pasw"
              label="Contraseña"
              :type="isPwd ? 'password' : 'text'"
              autocomplete="current-password"
              :disable="loading"
              hide-bottom-space
              lazy-rules
              :rules="paswRules"
              @update:model-value="error = ''"
            >
              <template v-slot:prepend>
                <q-icon name="lock"/>
              </template>
              <template v-slot:append>
                <q-icon
                  :name="isPwd ? 'visibility_off' : 'visibility'"
                  class="cursor-pointer"
                  @click="isPwd = !isPwd"
                />
              </template>
            </q-input>

            <q-btn
              label="INGRESAR"
              color="primary"
              icon="login"
              class="full-width q-mt-lg"
              size="md"
              unelevated
              no-caps
              type="submit"
              :loading="loading"
            >
              <template v-slot:loading>
                <q-spinner-dots class="q-mr-sm"/>
                Verificando...
              </template>
            </q-btn>
          </q-form>
        </q-card-section>

        <q-separator/>

        <q-card-section class="text-center q-py-sm">
          <a
            class="text-caption text-grey-7 login-link"
            target="_blank"
            rel="noopener"
            href="https://api.whatsapp.com/send?phone=59169603027&text=olvide mi contraseña"
          >
            <q-icon name="help_outline" size="16px"/>
            ¿Olvidaste tu contraseña?
          </a>
        </q-card-section>
      </q-card>
    </div>
  </q-page>
</template>

<script>
export default {
  data () {
    return {
      ci: '',
      pasw: '',
      isPwd: true,
      loading: false,
      error: '',
      logoError: false,
      ciRules: [
        val => (!!val && val.length > 0) || 'Ingresa tu carnet de identidad'
      ],
      paswRules: [
        val => (!!val && val.length > 0) || 'Ingresa tu contraseña'
      ]
    }
  },
  created () {
    if (this.$store.getters['login/isLoggedIn']) {
      this.$router.push('/')
    }
  },
  methods: {
    // Traduce cualquier fallo del login a un mensaje entendible.
    // Antes se leia err.response.data.res directo y reventaba (sin mostrar nada)
    // cuando el backend estaba caido o respondia otro formato.
    mensajeDeError (err) {
      if (err && err.response) {
        const data = err.response.data || {}
        if (data.res) return data.res
        if (data.errors) {
          return Object.values(data.errors).flat().join(' ')
        }
        switch (err.response.status) {
          case 400:
          case 401:
          case 404:
            return 'Usuario o contraseña incorrectos'
          case 403:
            return 'Tu usuario no tiene permiso para ingresar'
          case 419:
            return 'La sesión expiró, vuelve a intentarlo'
          case 429:
            return 'Demasiados intentos, espera un momento'
          default:
            return 'Error del servidor (' + err.response.status + '). Intenta más tarde'
        }
      }
      if (err && (err.code === 'ECONNABORTED' || String(err.message || '').includes('timeout'))) {
        return 'El servidor tardó demasiado en responder. Revisa tu conexión'
      }
      return 'No se pudo conectar con el servidor. Revisa tu conexión a internet'
    },
    async login () {
      const valido = await this.$refs.formRef.validate()
      if (!valido) return

      this.loading = true
      this.error = ''
      this.$store.dispatch('login/login', { ci: this.ci, pasw: this.pasw })
        .then(() => {
          this.loading = false
          this.$q.notify({
            message: 'Bienvenido',
            color: 'positive',
            icon: 'check_circle',
            position: 'top',
            timeout: 1500
          })
          this.$router.push('/')
        })
        .catch(err => {
          this.loading = false
          this.error = this.mensajeDeError(err)
          this.pasw = ''
          this.$q.notify({
            message: this.error,
            color: 'negative',
            icon: 'error',
            position: 'top',
            timeout: 4000
          })
        })
    }
  }
}
</script>

<style scoped>
/* MainLayout aplica style="min-height:0" al router-view (estilo inline),
   por eso hace falta !important para que el fondo cubra toda la pantalla. */
.login-page {
  min-height: calc(100vh - 50px) !important;
  background: linear-gradient(160deg, #ED1C24 0%, #a1121a 55%, #5c0a0e 100%);
}

.login-wrapper {
  width: 100%;
  max-width: 400px;
}

.login-card {
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 12px 32px rgba(0, 0, 0, .28);
}

.login-header {
  background: linear-gradient(135deg, #ED1C24 0%, #b3151b 100%);
  padding: 22px 16px 18px;
  text-align: center;
}

.login-logo {
  width: 72px;
  height: 72px;
  margin: 0 auto 10px;
  border-radius: 50%;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, .2);
}

.login-logo img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  padding: 6px;
}

.text-opacity {
  opacity: .85;
}

.login-error {
  border-left: 4px solid var(--q-negative);
}

.login-link {
  text-decoration: none;
}

.login-link:hover {
  text-decoration: underline;
}
</style>
