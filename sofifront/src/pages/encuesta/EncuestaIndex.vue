<template>
  <q-page class="q-pa-md">
    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12 col-sm-3">
        <q-input v-model="filters.from" type="date" label="Desde" dense outlined />
      </div>
      <div class="col-12 col-sm-3">
        <q-input v-model="filters.to" type="date" label="Hasta" dense outlined />
      </div>
      <div class="col-12 col-sm-2 flex items-center">
        <q-btn :loading="loading" color="primary" icon="search" label="Buscar" no-caps @click="load" />
      </div>
      <div class="col-12 col-sm-4 flex items-center justify-end">
        <q-badge color="grey-8" text-color="white" class="q-pa-sm">
          Total: {{ rows.length }}
        </q-badge>
      </div>
    </div>

    <q-markup-table flat bordered dense>
      <thead>
      <tr>
        <th class="text-left">#</th>
        <th class="text-left">Fecha</th>
        <th class="text-left">Email</th>
        <th class="text-left">Score</th>
        <th class="text-left">Comentario</th>
        <th class="text-left">Ref ID</th>
        <th class="text-left">IP</th>
      </tr>
      </thead>

      <tbody v-if="!loading && rows.length">
      <tr v-for="(r, i) in rows" :key="r.id">
        <td class="text-left">{{ i + 1 }}</td>
        <td class="text-left">{{ formatDate(r.created_at) }}</td>
        <td class="text-left">{{ r.email || '-' }}</td>
        <td class="text-left">
          <q-chip
            square dense text-color="white"
            :color="r.score === 10 ? 'positive' : r.score === 5 ? 'amber-7' : 'negative'"
          >
            {{ r.score }}
          </q-chip>
        </td>
        <td class="text-left">{{ r.comment || '-' }}</td>
        <td class="text-left">{{ r.ref_id || '-' }}</td>
        <td class="text-left">{{ r.ip || '-' }}</td>
      </tr>
      </tbody>

      <tbody v-else-if="!loading && !rows.length">
      <tr>
        <td colspan="7" class="text-center text-grey">Sin datos para el rango seleccionado</td>
      </tr>
      </tbody>

      <tbody v-else>
      <tr>
        <td colspan="7" class="text-center">
          <q-spinner size="24px" class="q-mr-sm" /> Cargando...
        </td>
      </tr>
      </tbody>
    </q-markup-table>
  </q-page>
</template>

<script>
import { date } from 'quasar'

export default {
  name: 'EncuestaIndexSimple',
  data () {
    const today = date.formatDate(Date.now(), 'YYYY-MM-DD')
    return {
      loading: false,
      rows: [],
      filters: {
        from: today,
        to: today
      }
    }
  },
  methods: {
    formatDate (val) {
      if (!val) return ''
      return date.formatDate(val, 'YYYY-MM-DD HH:mm')
    },
    async load () {
      this.loading = true
      try {
        const params = {
          from: this.filters.from || undefined,
          to:   this.filters.to   || undefined,
          all:  1                 // << traer TODO sin paginar
        }
        const res = await this.$api.get('encuestas', { params })
        this.rows = res.data
      } catch (e) {
        console.error(e)
        this.$q.notify({ type: 'negative', message: 'No se pudo cargar encuestas' })
        this.rows = []
      } finally {
        this.loading = false
      }
    }
  },
  mounted () {
    this.load()
  }
}
</script>
