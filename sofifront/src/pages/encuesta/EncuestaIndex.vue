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
    <!-- ====== RESÚMENES ====== -->
    <div class="q-mt-xl">

      <!-- Resumen General -->
      <div class="text-subtitle1 text-weight-bold q-mb-sm">RESUMEN GENERAL</div>
      <div class="badge-row q-mb-md">
        <q-badge color="grey-7"  text-color="white" class="q-pa-sm">Total: {{ summary.total }}</q-badge>
        <q-badge color="green-7" text-color="white" class="q-pa-sm">10: {{ summary.c10 }}</q-badge>
        <q-badge color="amber-7" text-color="white" class="q-pa-sm">5: {{ summary.c5 }}</q-badge>
        <q-badge color="red-7"   text-color="white" class="q-pa-sm">0: {{ summary.c0 }}</q-badge>
        <q-badge color="blue-7"  text-color="white" class="q-pa-sm">Promedio: {{ summary.avg.toFixed(2) }}</q-badge>
      </div>

      <!-- Barra tipo termómetro general -->
      <div class="gauge q-mb-xl">
        <div class="gauge-track">
          <div class="gauge-end gauge-min">–</div>
          <div class="gauge-end gauge-max">+</div>

          <!-- Marcador/puntero -->
          <div class="gauge-thumb" :style="{ left: percent(summary.avg) + '%' }">
            <div class="gauge-dot"></div>
          </div>

          <!-- Camioncito (SVG inline) -->
          <div class="gauge-truck" :style="{ left: percent(summary.avg) + '%' }" v-html="truckSvg"></div>
        </div>
      </div>

      <!-- Resumen por camión/usuario -->
      <div class="text-subtitle1 text-weight-bold q-mb-sm">RESUMEN POR CAMIÓN</div>

      <div
        v-for="u in byUsuario"
        :key="u.key"
        class="q-mb-lg"
      >
        <div class="text-body1 text-weight-medium q-mb-xs">{{ u.nombre }}</div>

        <div class="gauge q-mb-sm">
          <div class="gauge-track">
            <div class="gauge-end gauge-min">–</div>
            <div class="gauge-end gauge-max">+</div>

            <div class="gauge-thumb" :style="{ left: percent(u.avg) + '%' }">
              <div class="gauge-dot"></div>
            </div>

            <div class="gauge-truck" :style="{ left: percent(u.avg) + '%' }" v-html="truckSvg"></div>
          </div>
        </div>

        <div class="badge-row">
          <q-badge color="grey-7"  text-color="white" class="q-pa-xs">Total: {{ u.total }}</q-badge>
          <q-badge color="green-7" text-color="white" class="q-pa-xs">10: {{ u.c10 }}</q-badge>
          <q-badge color="amber-7" text-color="white" class="q-pa-xs">5: {{ u.c5 }}</q-badge>
          <q-badge color="red-7"   text-color="white" class="q-pa-xs">0: {{ u.c0 }}</q-badge>
          <q-badge color="blue-7"  text-color="white" class="q-pa-xs">Promedio: {{ u.avg.toFixed(2) }}</q-badge>
        </div>
      </div>
    </div>

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
  computed: {
    summary () {
      const total = this.rows.length
      const c10 = this.rows.filter(r => r.score === 10).length
      const c5  = this.rows.filter(r => r.score === 5).length
      const c0  = this.rows.filter(r => r.score === 0).length
      const sum = this.rows.reduce((s, r) => s + (r.score || 0), 0)
      const avg = total ? (sum / total) : 0
      return { total, c10, c5, c0, avg }
    },
    byUsuario () {
      // Agrupar por usuario (usa CodAut si existe, si no, el nombre)
      const map = new Map()
      for (const r of this.rows) {
        const key = (r.usuario_cod_aut ?? r.usuario_nombre ?? 'Sin usuario') + ''
        if (!map.has(key)) {
          map.set(key, {
            key,
            nombre: r.usuario_nombre || `CodAut ${r.usuario_cod_aut ?? ''}`,
            total: 0, c10: 0, c5: 0, c0: 0, sum: 0
          })
        }
        const it = map.get(key)
        it.total += 1
        if (r.score === 10) it.c10 += 1
        else if (r.score === 5) it.c5 += 1
        else if (r.score === 0) it.c0 += 1
        it.sum += (r.score || 0)
      }
      const arr = Array.from(map.values()).map(it => ({
        ...it,
        avg: it.total ? it.sum / it.total : 0
      }))
      // ordena por nombre o por promedio, como prefieras
      return arr.sort((a, b) => a.nombre.localeCompare(b.nombre))
    },
    truckSvg () {
      // Camioncito SVG inline (evitas imagen externa). Puedes reemplazar por <img src="/images/truck.png">
      return `
      <svg width="70" height="36" viewBox="0 0 70 36" xmlns="http://www.w3.org/2000/svg">
        <rect x="2" y="10" width="34" height="16" rx="2" fill="#e0e0e0" stroke="#9e9e9e"/>
        <rect x="36" y="14" width="15" height="12" rx="2" fill="#eeeeee" stroke="#9e9e9e"/>
        <polygon points="55,26 65,26 67,22 57,22" fill="#e53935"/>
        <circle cx="18" cy="30" r="4.5" fill="#212121"/>
        <circle cx="18" cy="30" r="2.2" fill="#bdbdbd"/>
        <circle cx="50" cy="30" r="4.5" fill="#212121"/>
        <circle cx="50" cy="30" r="2.2" fill="#bdbdbd"/>
      </svg>`
    }
  },
  methods: {
    percent (avg) {
      // 0..10 -> 0..100
      const p = (Number(avg) || 0) * 10
      return Math.max(0, Math.min(100, p))
    },
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

.badge-row > .q-badge { margin-right: 8px; }

.gauge {
  width: 100%;
}

.gauge-track {
  position: relative;
  height: 20px;
  border-radius: 999px;
  /* Gradiente rojo -> amarillo -> verde */
  background: linear-gradient(90deg, #e53935 0%, #ff9800 30%, #ffc107 50%, #8bc34a 80%, #43a047 100%);
  box-shadow: inset 0 0 0 2px rgba(0,0,0,0.05);
}

.gauge-end {
  position: absolute;
  top: -18px;
  font-weight: 700;
  font-size: 14px;
  color: #666;
}
.gauge-min { left: 8px; }
.gauge-max { right: 8px; }

.gauge-thumb {
  position: absolute;
  top: 100%;
  transform: translate(-50%, -2px);
}
.gauge-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #000;
  box-shadow: 0 0 0 3px rgba(0,0,0,0.08);
  margin-top: 6px; /* baja el punto bajo la barra */
}

.gauge-truck {
  position: absolute;
  bottom: 22px;           /* altura “sobre” la barra */
  transform: translateX(-50%);
  pointer-events: none;   /* no interfiera con clicks */
}

</style>
