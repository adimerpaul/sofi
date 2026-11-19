<template>
  <q-page class="q-pa-sm">
    <!-- Hora limite para mandar pedidos por tipo (tabla horarios_pedido). Despues
         de la hora el pedido se registra igual, pero sale el aviso "fuera de
         horario" y queda oculto en Facturacion > Pedidos. Con el switch
         encendido se sigue mandando normal. -->
    <q-card flat bordered style="max-width: 720px">
      <q-card-section class="q-py-sm">
        <div class="text-subtitle1 text-weight-bold">Horarios de envío de pedidos</div>
        <div class="text-caption text-grey-7">
          Después de la hora límite el pedido se puede registrar (Visita, Mis pedidos, Pedidos totales), pero se
          avisa que se mandó <b>fuera de horario</b> y queda oculto en Facturación &gt; Pedidos.
          Encienda <b>Seguir mandando</b> para aceptar pedidos de ese tipo después de la hora.
        </div>
        <div class="text-caption q-mt-xs">Hora actual: <b>{{ ahora }}</b></div>
      </q-card-section>

      <q-markup-table flat dense separator="horizontal">
        <thead>
        <tr>
          <th class="text-left">Tipo de pedido</th>
          <th class="text-left">Hora límite</th>
          <th class="text-left">Seguir mandando después de la hora</th>
          <th class="text-left">Ahora</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="h in horarios" :key="h.id">
          <td class="text-weight-bold">{{ h.nombre }}</td>
          <td>
            <q-input v-model="h.hora" type="time" dense outlined style="width: 130px" :disable="!puedeEditar"
                     @change="guardar(h)"/>
          </td>
          <td>
            <q-toggle v-model="h.habilitado" color="positive" :disable="!puedeEditar || h.guardando"
                      :label="h.habilitado ? 'Habilitado' : 'Deshabilitado'" @update:model-value="guardar(h)"/>
          </td>
          <td>
            <q-badge v-if="estado(h) === 'fuera'" color="deep-orange-8" label="Fuera de horario"/>
            <q-badge v-else-if="estado(h) === 'habilitado'" color="positive" label="Pasó la hora, pero habilitado"/>
            <q-badge v-else color="blue-grey-6" label="En horario"/>
            <div v-if="h.actualizado_por" class="text-caption text-grey-6">
              {{ h.actualizado_por }} · {{ (h.updated_at || '').substring(0, 16) }}
            </div>
          </td>
        </tr>
        <tr v-if="!horarios.length">
          <td colspan="4" class="text-center text-grey-7">{{ cargando ? 'Cargando…' : 'Sin horarios' }}</td>
        </tr>
        </tbody>
      </q-markup-table>
      <q-card-section v-if="!puedeEditar" class="text-caption text-grey-7">
        Solo quien tiene el permiso de horarios de envío puede cambiarlos.
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script>
import moment from 'moment'

export default {
  name: 'HorariosPedido',
  data () {
    return {
      horarios: [],
      cargando: false,
      ahora: moment().format('HH:mm'),
      reloj: null
    }
  },
  computed: {
    puedeEditar () {
      return this.$store.getters['login/can']('horariosPedido')
    }
  },
  mounted () {
    this.cargar()
    this.reloj = setInterval(() => { this.ahora = moment().format('HH:mm') }, 30000)
  },
  beforeUnmount () {
    clearInterval(this.reloj)
  },
  methods: {
    cargar () {
      this.cargando = true
      this.$api.get('horarios-pedido')
        .then(res => {
          this.horarios = res.data.map(h => ({
            ...h, hora: String(h.hora_limite).substring(0, 5), habilitado: !!h.habilitado, guardando: false
          }))
        })
        .catch(err => this.avisar(err, 'No se pudieron cargar los horarios'))
        .finally(() => { this.cargando = false })
    },
    // Hora del navegador; el backend decide con la del servidor (America/La_Paz).
    estado (h) {
      if (this.ahora < h.hora) return 'en'
      return h.habilitado ? 'habilitado' : 'fuera'
    },
    guardar (h) {
      if (!/^\d{2}:\d{2}$/.test(h.hora || '')) return
      h.guardando = true
      this.$api.put('horarios-pedido/' + h.id, { hora_limite: h.hora, habilitado: h.habilitado })
        .then(() => {
          this.$q.notify({ type: 'positive', message: h.nombre + ': guardado' })
          this.cargar()
        })
        .catch(err => {
          this.avisar(err, 'No se pudo guardar')
          this.cargar()
        })
        .finally(() => { h.guardando = false })
    },
    avisar (err, mensaje) {
      const datos = err.response && err.response.data
      const errores = datos && datos.errors ? Object.values(datos.errors).flat()[0] : null
      this.$q.notify({ type: 'negative', message: errores || (datos && datos.message) || mensaje })
    }
  }
}
</script>
