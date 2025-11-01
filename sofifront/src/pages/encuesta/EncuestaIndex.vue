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
      <div class="col-12 col-sm-2">
        <q-btn
          color="negative"
          icon="picture_as_pdf"
          label="Exportar PDF"
          no-caps
          class="full-width"
          @click="openPdf"
          size="10px"
          :disable="loading"
        />
        <q-btn
          color="teal"
          icon="grid_on"
          label="Exportar Excel"
          no-caps
          class="q-ml-sm full-width"
          size="10px"

          :disable="!rows.length || loading"
          @click="exportXlsx"
        />
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
        <b>Promedio:</b>
        <q-badge v-if="rows.length" color="blue-7" text-color="white" class="q-pa-sm q-ml-sm">
          {{ (rows.reduce((sum, r) => sum + (r.score || 0), 0) / rows.length).toFixed(2) }}
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
import xlsx from 'json-as-xlsx'
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
    async exportXlsx () {
      try {
        if (!this.rows.length) {
          this.$q.notify({ type: 'warning', message: 'No hay datos para exportar' })
          return
        }

        // 1) Contenido: tomamos SOLO lo que ya está en rows (lo visible)
        const content = this.rows.map((r, i) => ({
          n: i + 1,
          encuesta_date: r.encuesta_date || '',
          created_at_fmt: r.created_at ? date.formatDate(r.created_at, 'YYYY-MM-DD HH:mm') : '',
          cliente_nombre: r.cliente_nombre || '',
          cliente_cod_aut: r.cliente_cod_aut ?? '',
          cliente_id: r.cliente_id ?? '',
          cliente_dir: r.cliente_dir || '',
          cliente_zona: r.cliente_zona || '',
          usuario_nombre: r.usuario_nombre || '',
          usuario_cod_aut: r.usuario_cod_aut ?? '',
          usuario_ci: r.usuario_ci || '',
          usuario_correo: r.usuario_correo || '',
          score: r.score ?? '',
          comment: r.comment || '',
          email: r.email || '',
          client_ip: r.client_ip || '',
          origin_host: r.origin_host || '',
          origin_scheme: r.origin_scheme || '',
          origin_path: r.origin_path || '',
          user_agent: r.user_agent || ''
        }))

        // 2) Columnas en el mismo orden que la tabla
        const columns = [
          { label: '#', value: 'n' },
          { label: 'Fecha encuesta', value: 'encuesta_date' },
          { label: 'Creado', value: 'created_at_fmt' },
          { label: 'Cliente nombre', value: 'cliente_nombre' },
          { label: 'Cliente Cod_Aut', value: 'cliente_cod_aut' },
          { label: 'Cliente ID', value: 'cliente_id' },
          { label: 'Cliente dir', value: 'cliente_dir' },
          { label: 'Cliente zona', value: 'cliente_zona' },
          { label: 'Usuario nombre', value: 'usuario_nombre' },
          { label: 'Usuario CodAut', value: 'usuario_cod_aut' },
          { label: 'Usuario CI', value: 'usuario_ci' },
          { label: 'Usuario correo', value: 'usuario_correo' },
          { label: 'Score', value: 'score' },
          { label: 'Comentario', value: 'comment' },
          { label: 'Email', value: 'email' },
          { label: 'IP', value: 'client_ip' },
          { label: 'Host', value: 'origin_host' },
          { label: 'Esquema', value: 'origin_scheme' },
          { label: 'Path', value: 'origin_path' },
          { label: 'Navegador (UA)', value: 'user_agent' }
        ]

        // 3) Definición del archivo
        const data = [{
          sheet: 'Encuestas',
          columns,
          content
        }]

        const now = date.formatDate(Date.now(), 'YYYYMMDD_HHmmss')
        const settings = {
          fileName: `reporte-encuestas_${now}`,
          extraLength: 3
        }

        // 4) Exportar
        xlsx(data, settings)
      } catch (e) {
        console.error(e)
        this.$q.notify({ type: 'negative', message: 'No se pudo exportar el Excel' })
      }
    },
    openPdf () {
      // Construye la misma query de filtros
      const params = new URLSearchParams({
        from:    this.filters.from || '',
        to:      this.filters.to   || '',
        usuario: this.filters.usuario || '',
        score:   this.filters.score ?? ''
      })

      // Si usas boot axios con baseURL, puedes obtenerla así:
      // const base = this.$api.defaults.baseURL.replace(/\/+$/, '')
      // Si no, pon tu URL base manualmente:
      const base = this.$api?.defaults?.baseURL?.replace(/\/+$/, '') || 'http://localhost:8000/api'

      const url = `${base}/encuestas/report-pdf?${params.toString()}`
      window.open(url, '_blank') // abrir en nueva pestaña
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
