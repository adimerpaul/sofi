<template>
  <q-page class="q-pa-sm verificacion-page">
    <q-card flat bordered class="hero-card q-mb-sm">
      <q-card-section class="row items-center q-col-gutter-sm">
        <div class="col-12 col-md-3">
          <div class="text-subtitle2 text-grey-7">Ventas del dia</div>
          <div class="text-h6 text-weight-bold">Verificacion</div>
        </div>
        <div class="col-12 col-md-2">
          <q-input v-model="fecha" type="date" dense outlined label="Fecha" />
        </div>
        <div class="col-12 col-md-2">
          <q-select v-model="filtroVerificado" :options="verificadoOptions" dense outlined emit-value map-options label="Verificado" />
        </div>
        <div class="col-12 col-md-3">
          <q-input v-model="search" dense outlined label="Buscar pedido/cliente/camion/producto" debounce="250">
            <template #append><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-12 col-md-2">
          <q-btn color="primary" icon="search" no-caps class="full-width" label="Consultar" :loading="loading" @click="cargarVentas" />
        </div>
      </q-card-section>

      <q-separator />
      <q-card-section class="row q-col-gutter-sm">
        <div class="col-6 col-md-2"><q-chip color="blue-8" text-color="white" class="full-width justify-center">Total: {{ stats.total }}</q-chip></div>
        <div class="col-6 col-md-2"><q-chip color="green-8" text-color="white" class="full-width justify-center">Verificados: {{ stats.verificados }}</q-chip></div>
        <div class="col-6 col-md-2"><q-chip color="red-7" text-color="white" class="full-width justify-center">No verificados: {{ stats.no_verificados }}</q-chip></div>
        <div class="col-6 col-md-2"><q-chip color="teal" text-color="white" class="full-width justify-center">Facturados: {{ stats.facturados }}</q-chip></div>
        <div class="col-6 col-md-2"><q-chip color="deep-orange" text-color="white" class="full-width justify-center">No facturados: {{ stats.no_facturados }}</q-chip></div>
        <div class="col-12 col-md-2">
          <q-btn color="primary" icon="print" no-caps class="full-width text-weight-bold" label="Imprimir" :loading="loadingPrint" @click="imprimirReporte" />
        </div>
      </q-card-section>
    </q-card>

    <q-card flat bordered>
      <q-markup-table flat dense wrap-cells class="dense-table">
        <thead>
          <tr class="bg-grey-2">
            <th>Verif.</th>
            <th>Fact.</th>
            <th>Venta</th>
            <th>Pedido</th>
            <th>Hora</th>
            <th>Camión</th>
            <th>Cliente</th>
            <th>Productos</th>
            <th>Quién verificó</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="venta in ventasFiltradas" :key="venta.venta_id">
            <td>
              <q-checkbox
                :model-value="!!venta.verificado"
                dense
                checked-icon="task_alt"
                unchecked-icon="radio_button_unchecked"
                @update:model-value="(value) => cambiarVerificacion(venta, value)"
              />
              <q-chip dense :color="venta.verificado ? 'green-8' : 'grey-7'" text-color="white">
                {{ venta.verificado ? 'VERIFICADO' : 'PENDIENTE' }}
              </q-chip>
              <div class="text-caption text-grey-7" v-if="venta.verificado_at">{{ venta.verificado_at }}</div>
            </td>
            <td>
              <q-chip dense :color="venta.facturado ? 'green-8' : 'negative'" text-color="white">
                {{ venta.facturado ? 'SI' : 'NO' }}
              </q-chip>
            </td>
            <td>#{{ venta.venta_id }}</td>
            <td>#{{ venta.comanda || '-' }}</td>
            <td>{{ venta.fecha }} {{ venta.hora || '' }}</td>
            <td>
              <div>{{ venta.camion || 'SIN CAMIÓN' }}</div>
              <div class="text-caption text-grey-7" v-if="venta.camion_placa">{{ venta.camion_placa }}</div>
            </td>
            <td>{{ venta.cliente || '-' }}</td>
            <td class="productos-col">
              <div v-for="producto in (venta.productos || [])" :key="`${venta.venta_id}-${producto.id}`" class="producto-linea">
                <span class="codigo">{{ producto.codigo }}</span>
                <span class="nombre">{{ producto.nombre }}</span>
                <span class="cantidad">x {{ formatCantidad(producto.cantidad) }}</span>
              </div>
            </td>
            <td>{{ venta.verificado_por || '-' }}</td>
          </tr>
          <tr v-if="ventasFiltradas.length === 0">
            <td colspan="9" class="text-center text-grey-7">Sin ventas para la fecha seleccionada</td>
          </tr>
        </tbody>
      </q-markup-table>
    </q-card>
  </q-page>
</template>

<script>
export default {
  name: 'VerificacionVentas',
  data() {
    const today = new Date().toISOString().slice(0, 10)
    return {
      fecha: today,
      search: '',
      filtroVerificado: 'TODOS',
      loading: false,
      loadingPrint: false,
      updatingVentaId: null,
      ventas: [],
      stats: {
        total: 0,
        verificados: 0,
        no_verificados: 0,
        facturados: 0,
        no_facturados: 0,
      },
      verificadoOptions: [
        { label: 'Todos', value: 'TODOS' },
        { label: 'Verificados', value: 'SI' },
        { label: 'No verificados', value: 'NO' },
      ],
    }
  },
  computed: {
    ventasFiltradas() {
      const term = String(this.search || '').trim().toLowerCase()
      if (!term) return this.ventas
      return this.ventas.filter((venta) => {
        const productos = (venta.productos || []).map((item) => `${item.codigo} ${item.nombre}`).join(' ').toLowerCase()
        const base = `${venta.venta_id || ''} ${venta.comanda || ''} ${venta.camion || ''} ${venta.vendedor || ''} ${venta.cliente || ''} ${venta.verificado_por || ''}`.toLowerCase()
        return `${base} ${productos}`.includes(term)
      })
    },
  },
  mounted() {
    this.cargarVentas()
  },
  methods: {
    formatCantidad(value) {
      const num = Number(value || 0)
      return Number.isInteger(num) ? `${num}` : num.toFixed(2)
    },
    async cargarVentas() {
      this.loading = true
      try {
        const res = await this.$axios.get('/verificacion/ventas', {
          params: {
            fecha: this.fecha,
            verificado: this.filtroVerificado,
            search: this.search || null,
          },
        })
        const data = res && res.data ? res.data : {}
        this.ventas = Array.isArray(data.data) ? data.data : []
        this.stats = data.stats || this.stats
      } catch (e) {
        this.$alert.error((e && e.response && e.response.data && e.response.data.message) || 'No se pudo cargar el modulo de verificacion')
      } finally {
        this.loading = false
      }
    },
    async cambiarVerificacion(venta, value) {
      if (!venta || this.updatingVentaId === venta.venta_id) return
      const previous = !!venta.verificado
      venta.verificado = !!value
      this.updatingVentaId = venta.venta_id
      try {
        const res = await this.$axios.put(`/verificacion/ventas/${venta.venta_id}`, {
          verificado: !!value,
        })
        const updated = res && res.data && res.data.venta ? res.data.venta : null
        if (updated) {
          const index = this.ventas.findIndex((row) => Number(row.venta_id) === Number(updated.venta_id))
          if (index >= 0) this.ventas.splice(index, 1, updated)
        }
        this.recalcularStats()
      } catch (e) {
        venta.verificado = previous
        this.$alert.error((e && e.response && e.response.data && e.response.data.message) || 'No se pudo actualizar la verificacion')
      } finally {
        this.updatingVentaId = null
      }
    },
    recalcularStats() {
      const rows = Array.isArray(this.ventas) ? this.ventas : []
      this.stats = {
        total: rows.length,
        verificados: rows.filter((row) => !!row.verificado).length,
        no_verificados: rows.filter((row) => !row.verificado).length,
        facturados: rows.filter((row) => !!row.facturado).length,
        no_facturados: rows.filter((row) => !row.facturado).length,
      }
    },
    async descargarPdf(url) {
      const res = await this.$axios.get(url, { responseType: 'blob' })
      const blob = new Blob([res.data], { type: 'application/pdf' })
      const disposition = (res && res.headers && res.headers['content-disposition']) || ''
      const match = disposition.match(/filename=\"?([^\"]+)\"?/)
      const fileName = (match && match[1]) || 'verificacion.pdf'
      const link = document.createElement('a')
      const fileUrl = window.URL.createObjectURL(blob)
      link.href = fileUrl
      link.download = fileName
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(fileUrl)
    },
    async imprimirReporte() {
      this.loadingPrint = true
      try {
        await this.descargarPdf(`/verificacion/reportes/ventas?fecha=${encodeURIComponent(this.fecha)}&verificado=${encodeURIComponent(this.filtroVerificado)}`)
      } catch (e) {
        this.$alert.error((e && e.response && e.response.data && e.response.data.message) || 'No se pudo generar la impresion')
      } finally {
        this.loadingPrint = false
      }
    },
  },
}
</script>

<style scoped>
.verificacion-page {
  background: linear-gradient(180deg, #eff6ff 0%, #f8fbff 35%, #ffffff 100%);
}

.hero-card {
  border: 1px solid #dbe7ff;
  background:
    radial-gradient(1200px 280px at 5% 0%, rgba(37, 99, 235, 0.12), transparent 70%),
    radial-gradient(900px 220px at 100% 10%, rgba(14, 165, 233, 0.10), transparent 70%),
    #fff;
}

.dense-table :deep(td),
.dense-table :deep(th) {
  padding-top: 3px;
  padding-bottom: 3px;
}

.productos-col {
  min-width: 260px;
  font-size: 11px;
  line-height: 1.15;
}

.producto-linea {
  display: flex;
  gap: 6px;
  border-bottom: 1px dotted #d1d5db;
  padding: 1px 0;
}

.producto-linea:last-child {
  border-bottom: 0;
}

.codigo {
  min-width: 56px;
  color: #475569;
  font-weight: 600;
}

.nombre {
  flex: 1;
}

.cantidad {
  white-space: nowrap;
  font-weight: 700;
}
</style>
