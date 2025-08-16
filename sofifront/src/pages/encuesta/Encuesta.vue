<template>
  <q-layout view="lhr Lpr lfr" style="min-height: 0">
    <q-page-container>
      <q-page class="flex flex-center bg-grey-1">
        <q-card class="enc-card q-pa-md">
          <div class="row no-wrap items-center">

            <!-- Columna barra + plus -->
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

            <!-- Columna opciones -->
            <div class="col column justify-around q-gutter-md">

              <!-- Perfecto 10 -->
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

              <!-- Bueno 5 -->
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

              <!-- Malo 0 -->
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

              <!-- Mensaje de gracias -->
              <transition name="fade">
                <div v-if="sent" class="q-mt-sm text-center text-positive">
                  ¡Gracias por tu respuesta! ✅
                </div>
              </transition>
            </div>
          </div>

          <!-- Comentario opcional + botón enviar manual (si quieres) -->
          <div class="q-mt-md">
            <q-input
              v-model="comment"
              type="textarea"
              dense
              autogrow
              clearable
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
              unelevated
              rounded
              icon="send"
            />
          </div>
        </q-card>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<script>
import axios from 'axios'

export default {
  name: 'EncuestaSatisfaccion',

  props: {
    apiUrl: { type: String, default: '/api/encuestas' }, // endpoint Laravel
    token:  { type: String, default: '' },               // CSRF token si usas web
    // Si quieres relacionarlo a un pedido/visita:
    refId:  { type: [String, Number], default: null },
    // Auto-enviar al hacer click (sin botón):
    autoSubmit: { type: Boolean, default: true }
  },

  data () {
    return {
      score: null,        // 0 | 5 | 10
      comment: '',
      loading: false,
      sent: false
    }
  },

  computed: {
    fillHeight () {
      // Mueve el marcador en la barra (0 / 50 / 100)
      if (this.score === 10) return 100
      if (this.score === 5)  return 50
      if (this.score === 0)  return 0
      return 0
    }
  },

  methods: {
    isSel (val) { return this.score === val },

    async select (val) {
      this.score = val
      if (this.autoSubmit) {
        await this.submit()
      }
    },

    async submit () {
      if (this.score === null) return
      try {
        this.loading = true
        const payload = {
          score: this.score,        // 0, 5, 10
          comment: this.comment,
          ref_id: this.refId,
          // algo de contexto útil:
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
.enc-card {
  width: 360px;
  max-width: 92vw;
  border-radius: 16px;
}

/* Barra vertical */
.bar-wrapper {
  position: relative;
  height: 220px;
  width: 18px;
}
.bar-track {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  height: 100%;
  width: 8px;
  background: #eaeaea;
  border-radius: 6px;
  overflow: hidden;
  box-shadow: inset 0 0 0 1px rgba(0,0,0,.06);
}
.bar-fill {
  position: absolute;
  left: 0;
  bottom: 0;
  width: 100%;
  border-radius: 0 0 6px 6px;
  transition: height .25s ease;
}
.bar-tick {
  position: absolute;
  left: 50%;
  transform: translate(-50%, 50%);
  width: 14px;
  height: 4px;
  background: rgba(0,0,0,.25);
  border-radius: 2px;
  transition: bottom .25s ease;
}

/* labels “10 / 5 / 0” a la derecha ya van en cada item (score) */
.score {
  font-size: 20px;
  font-weight: 800;
}

/* transiciones */
.fade-enter-active, .fade-leave-active { transition: opacity .2s }
.fade-enter, .fade-leave-to { opacity: 0 }

.rounded-md { border-radius: 12px }
</style>
