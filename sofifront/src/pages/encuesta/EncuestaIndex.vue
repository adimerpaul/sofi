<template>
  <q-page class="q-pa-md">
    <!-- Filtros -->
    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12 col-sm-3">
        <q-input v-model="filters.from" type="date" label="Desde (fecha encuesta)" dense outlined />
      </div>
      <div class="col-12 col-sm-3">
        <q-input v-model="filters.to" type="date" label="Hasta (fecha encuesta)" dense outlined />
      </div>

      <div class="col-12 col-sm-3">
        <q-input
          v-model="filters.usuario"
          dense outlined
          label="Usuario (ID o texto)"
          placeholder="Ej: 123 o Juan / 123456 / @mail"
          clearable
        />
      </div>

      <div class="col-12 col-sm-1">
        <q-select
          v-model="filters.score"
          dense outlined clearable
          label="Score"
          :options="[{label:'10',value:10},{label:'5',value:5},{label:'0',value:0}]"
          emit-value map-options
        />
      </div>

      <div class="col-12 col-sm-2 flex items-center">
        <q-btn :loading="loading" color="primary" icon="search" label="Buscar" no-caps class="full-width" @click="load" />
      </div>

      <div class="col-12 q-mt-sm">
        <q-badge color="grey-8" text-color="white" class="q-pa-sm">
          Total: {{ rows.length }}
        </q-badge>
        <q-badge v-if="rows.length" color="green-7" text-color="white" class="q-pa-sm q-ml-sm">
          10: {{ rows.filter(r => r.score === 10).length }}
        </q-badge>
        <q-badge v-if="rows.length" color="amber-7" text-color="white" class="q-pa-sm q-ml-sm">
          5: {{ rows.filter(r => r.score === 5).length }}
        </q-badge>
        <q-badge v-if="rows.length" color="red-7" text-color="white" class="q-pa-sm q-ml-sm">
          0: {{ rows.filter(r => r.score === 0).length }}
        </q-badge>
      </div>
    </div>

    <!-- Tabla -->
    <q-markup-table flat bordered dense>
      <thead>
      <tr>
        <th class="text-left">#</th>
        <th class="text-left">Fecha (encuesta / creación)</th>
        <th class="text-left">Cliente</th>
        <th class="text-left">Usuario</th>
        <th class="text-left">Score</th>
        <th class="text-left">Comentario</th>
        <th class="text-left">Email</th>
        <th class="text-left">IP</th>
        <th class="text-left">Host</th>
        <th class="text-left">Navegador</th>
      </tr>
      </thead>

      <tbody v-if="!loading && rows.length">
      <tr v-for="(r, i) in rows" :key="r.id">
        <td class="text-left">{{ i + 1 }}</td>

        <td class="text-left">
          <div>{{ r.encuesta_date || '-' }}</div>
          <div class="text-caption text-grey-7">{{ formatDateTime(r.created_at) }}</div>
        </td>

        <td class="text-left">
          <div class="text-weight-medium">{{ r.cliente_nombre || '-' }}</div>
          <div class="text-caption text-grey-7">
            Cod_Aut: {{ r.cliente_cod_aut }} · ID: {{ r.cliente_id || '-' }}
          </div>
          <div class="text-caption ellipsis">
            {{ r.cliente_dir || '-' }} ({{ r.cliente_zona || '-' }})
          </div>
        </td>

        <td class="text-left">
          <div class="text-weight-medium">{{ r.usuario_nombre || '-' }}</div>
          <div class="text-caption text-grey-7">
            CodAut: {{ r.usuario_cod_aut }} · CI: {{ r.usuario_ci || '-' }}
          </div>
          <div class="text-caption">{{ r.usuario_correo || '-' }}</div>
        </td>

        <td class="text-left">
          <q-chip
            square dense text-color="white"
            :color="r.score === 10 ? 'positive' : r.score === 5 ? 'amber-7' : 'negative'"
          >
            {{ r.score }}
          </q-chip>
        </td>

        <td class="text-left">
          <span>{{ r.comment || '-' }}</span>
        </td>

        <td class="text-left">{{ r.email || '-' }}</td>
        <td class="text-left">{{ r.client_ip || '-' }}</td>

        <td class="text-left">
          <div>{{ r.origin_host || '-' }}</div>
          <div class="text-caption text-grey-7">{{ r.origin_scheme || '-' }}{{ r.origin_path ? ' · ' + r.origin_path : '' }}</div>
        </td>

        <td class="text-left">
          <q-tooltip v-if="r.user_agent">{{ r.user_agent }}</q-tooltip>
          <span>{{ shortUA(r.user_agent) }}</span>
        </td>
      </tr>
      </tbody>

      <tbody v-else-if="!loading && !rows.length">
      <tr>
        <td colspan="10" class="text-center text-grey">Sin datos para el filtro seleccionado</td>
      </tr>
      </tbody>

      <tbody v-else>
      <tr>
        <td colspan="10" class="text-center">
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
  name: 'EncuestaIndex',
  data () {
    const today = date.formatDate(Date.now(), 'YYYY-MM-DD')
    return {
      loading: false,
      rows: [],
      filters: {
        from: today,
        to: today,
        usuario: '',   // puede ser número (CodAut) o texto (nombre/ci/correo)
        score: null    // 10,5,0
      }
    }
  },
  methods: {
    formatDateTime (val) {
      if (!val) return ''
      return date.formatDate(val, 'YYYY-MM-DD HH:mm')
    },
    shortUA (ua) {
      if (!ua) return '-'
      // abrevia un poco
      if (ua.length <= 48) return ua
      return ua.slice(0, 48) + '…'
    },
    async load () {
      this.loading = true
      try {
        const params = {
          from:    this.filters.from || undefined,
          to:      this.filters.to   || undefined,
          usuario: this.filters.usuario || undefined,
          score:   this.filters.score ?? undefined,
          all:     1
        }
        const res = await this.$api.get('encuestas', { params })
        // si envías paginado, adapta a res.data.data
        this.rows = Array.isArray(res.data) ? res.data : (res.data?.data || [])
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

<style scoped>
.ellipsis { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 360px; display: inline-block; }
</style>
