<template>
  <q-page class="q-pa-md bg-grey-2">
    <div v-if="isAdmin">
    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row q-col-gutter-sm items-end">
        <div class="col-12 col-md-4">
          <div class="text-h5 text-weight-bold">Dashboard</div>
          <div class="text-caption text-grey-7">Resumen operativo de ventas, compras y pedidos</div>
        </div>

        <div class="col-12 col-sm-3 col-md-2">
          <q-input v-model="filters.date_from" dense outlined label="Desde" type="date" />
        </div>
        <div class="col-12 col-sm-3 col-md-2">
          <q-input v-model="filters.date_to" dense outlined label="Hasta" type="date" />
        </div>
        <div class="col-12 col-sm-3 col-md-2" v-if="isAdmin">
          <q-select
            v-model="filters.user_id"
            dense
            outlined
            clearable
            emit-value
            map-options
            :options="userOptions"
            label="Vendedor"
          />
        </div>
        <div class="col-12 col-sm-3 col-md-2">
          <q-btn color="primary" no-caps icon="refresh" label="Actualizar" class="full-width" :loading="loading" @click="fetchDashboard" />
        </div>
      </q-card-section>

      <q-card-section class="q-pt-none row q-col-gutter-sm">
        <div class="col-auto"><q-btn color="primary" outline no-caps label="Hoy" @click="setRange('today')" /></div>
        <div class="col-auto"><q-btn color="primary" outline no-caps label="Semana" @click="setRange('week')" /></div>
        <div class="col-auto"><q-btn color="primary" outline no-caps label="Mes" @click="setRange('month')" /></div>
        <div class="col-auto"><q-btn color="teal" no-caps icon="shopping_cart_checkout" label="Nueva venta" @click="$router.push('/ventaNuevo')" /></div>
        <div class="col-auto"><q-btn color="orange" no-caps icon="storefront" label="Compras" @click="$router.push('/compras')" /></div>
        <div class="col-auto"><q-btn color="indigo" no-caps icon="inventory_2" label="Productos" @click="$router.push('/productos')" /></div>
      </q-card-section>
    </q-card>

    <div class="row q-col-gutter-sm q-mb-sm">
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered class="kpi-card kpi-ingresos">
          <q-card-section>
            <div class="text-caption text-grey-7">Ingresos</div>
            <div class="text-h5 text-weight-bold">{{ money(kpis.ingresos) }} Bs</div>
            <div class="text-caption">Efectivo: {{ money(kpis.efectivo) }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered class="kpi-card kpi-egresos">
          <q-card-section>
            <div class="text-caption text-grey-7">Egresos</div>
            <div class="text-h5 text-weight-bold">{{ money(kpis.egresos) }} Bs</div>
            <div class="text-caption">Compras activas</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered class="kpi-card kpi-neto">
          <q-card-section>
            <div class="text-caption text-grey-7">Neto</div>
            <div class="text-h5 text-weight-bold">{{ money(kpis.neto) }} Bs</div>
            <div class="text-caption">QR: {{ money(kpis.qr) }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered class="kpi-card">
          <q-card-section>
            <div class="text-caption text-grey-7">Operaciones</div>
            <div class="text-h5 text-weight-bold">{{ kpis.ventas }} ventas</div>
            <div class="text-caption">Pedidos: {{ kpis.pedidos }} | Items: {{ kpis.items }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <div class="row q-col-gutter-sm">
      <div class="col-12 col-md-8">
        <q-card flat bordered>
          <q-card-section class="text-subtitle1 text-weight-bold">Movimiento por dia</q-card-section>
          <q-separator />
          <q-card-section>
            <apexchart
              v-if="chartDaily.series.length"
              type="line"
              height="320"
              :options="chartDaily.options"
              :series="chartDaily.series"
            />
            <div v-else class="text-grey text-center q-pa-md">Sin datos para el rango.</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-4">
        <q-card flat bordered>
          <q-card-section class="text-subtitle1 text-weight-bold">Ventas por pago</q-card-section>
          <q-separator />
          <q-card-section>
            <apexchart
              v-if="chartPago.series.length"
              type="donut"
              height="320"
              :options="chartPago.options"
              :series="chartPago.series"
            />
            <div v-else class="text-grey text-center q-pa-md">Sin ventas en el rango.</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <div class="row q-col-gutter-sm q-mt-sm">
      <div class="col-12 col-md-7">
        <q-card flat bordered>
          <q-card-section class="text-subtitle1 text-weight-bold">Productos mas usados</q-card-section>
          <q-separator />
          <q-card-section class="q-pa-none">
            <q-markup-table dense flat bordered>
              <thead>
              <tr>
                <th>Producto</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Total</th>
              </tr>
              </thead>
              <tbody>
              <tr v-for="p in topProductos" :key="p.nombre">
                <td>{{ p.nombre }}</td>
                <td class="text-right">{{ num(p.cantidad) }}</td>
                <td class="text-right">{{ money(p.total) }}</td>
              </tr>
              <tr v-if="topProductos.length === 0">
                <td colspan="3" class="text-center text-grey q-pa-md">Sin datos</td>
              </tr>
              </tbody>
            </q-markup-table>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-5">
        <q-card flat bordered>
          <q-card-section class="text-subtitle1 text-weight-bold">Resumen rapido</q-card-section>
          <q-separator />
          <q-list dense>
            <q-item>
              <q-item-section avatar><q-icon name="sell" color="primary" /></q-item-section>
              <q-item-section>Ventas activas</q-item-section>
              <q-item-section side class="text-weight-bold">{{ kpis.ventas }}</q-item-section>
            </q-item>
            <q-item>
              <q-item-section avatar><q-icon name="receipt_long" color="orange" /></q-item-section>
              <q-item-section>Pedidos registrados</q-item-section>
              <q-item-section side class="text-weight-bold">{{ kpis.pedidos }}</q-item-section>
            </q-item>
            <q-item>
              <q-item-section avatar><q-icon name="inventory_2" color="teal" /></q-item-section>
              <q-item-section>Items vendidos</q-item-section>
              <q-item-section side class="text-weight-bold">{{ num(kpis.items) }}</q-item-section>
            </q-item>
            <q-item>
              <q-item-section avatar><q-icon name="point_of_sale" color="green" /></q-item-section>
              <q-item-section>Ticket promedio</q-item-section>
              <q-item-section side class="text-weight-bold">{{ money(kpis.ticket_promedio) }} Bs</q-item-section>
            </q-item>
          </q-list>
        </q-card>
      </div>
    </div>
    </div>
    <div v-else class="dashboard-logo-wrap">
      <q-card flat class="dashboard-logo-card">
        <q-card-section class="column items-center justify-center q-pa-xl">
          <q-img src="/logo.png" fit="contain" class="dashboard-logo-image" />
          <div class="text-h5 text-weight-bold text-primary q-mt-lg">Sofia Factu</div>
        </q-card-section>
      </q-card>
    </div>
  </q-page>
</template>

<script>
import moment from 'moment'

export default {
  name: 'IndexPage',
  data () {
    const today = moment().format('YYYY-MM-DD')
    return {
      loading: false,
      users: [],
      filters: {
        date_from: today,
        date_to: today,
        user_id: null
      },
      ventas: [],
      compras: [],
      pedidos: [],
      kpis: {
        ingresos: 0,
        egresos: 0,
        neto: 0,
        efectivo: 0,
        qr: 0,
        ventas: 0,
        pedidos: 0,
        items: 0,
        ticket_promedio: 0
      },
      chartDaily: { series: [], options: {} },
      chartPago: { series: [], options: {} },
      topProductos: []
    }
  },
  computed: {
    isAdmin () {
      return ['ADMIN', 'ADMINISTRADOR'].includes(String(this.$store?.user?.role || '').trim().toUpperCase())
    },
    userOptions () {
      return [{ label: 'Todos', value: null }, ...this.users.map(u => ({ label: u.name, value: u.id }))]
    }
  },
  mounted () {
    if (!this.isAdmin) return
    this.usersGet()
    this.fetchDashboard()
  },
  methods: {
    setRange (range) {
      const now = moment()
      if (range === 'today') {
        this.filters.date_from = now.format('YYYY-MM-DD')
        this.filters.date_to = now.format('YYYY-MM-DD')
      } else if (range === 'week') {
        this.filters.date_from = now.startOf('week').format('YYYY-MM-DD')
        this.filters.date_to = moment().endOf('week').format('YYYY-MM-DD')
      } else if (range === 'month') {
        this.filters.date_from = now.startOf('month').format('YYYY-MM-DD')
        this.filters.date_to = moment().endOf('month').format('YYYY-MM-DD')
      }
      this.fetchDashboard()
    },
    async usersGet () {
      if (!this.isAdmin) return
      try {
        const { data } = await this.$axios.get('users')
        this.users = Array.isArray(data) ? data : []
      } catch (_) {
        this.users = []
      }
    },
    async fetchDashboard () {
      this.loading = true
      try {
        const params = {
          fechaInicio: this.filters.date_from,
          fechaFin: this.filters.date_to,
          user: this.filters.user_id || ''
        }
        const [ventasRes, comprasRes, pedidosRes] = await Promise.all([
          this.$axios.get('ventas', { params }),
          this.$axios.get('compras', { params }),
          this.$axios.get('pedidos', { params })
        ])

        this.ventas = Array.isArray(ventasRes.data) ? ventasRes.data : []
        this.compras = Array.isArray(comprasRes.data) ? comprasRes.data : []
        this.pedidos = Array.isArray(pedidosRes.data) ? pedidosRes.data : []

        this.computeKpis()
        this.computeCharts()
        this.computeTopProductos()
      } catch (e) {
        this.$alert.error(e?.response?.data?.message || 'No se pudo cargar el dashboard')
      } finally {
        this.loading = false
      }
    },
    computeKpis () {
      const ventasActivas = this.ventas.filter(v => (v.estado || '').toLowerCase() === 'activo')
      const comprasActivas = this.compras.filter(c => (c.estado || '').toLowerCase() === 'activo')

      const ingresos = ventasActivas.reduce((a, v) => a + Number(v.total || 0), 0)
      const egresos = comprasActivas.reduce((a, c) => a + Number(c.total || 0), 0)
      const efectivo = ventasActivas
        .filter(v => (v.tipo_pago || '').toLowerCase() === 'efectivo')
        .reduce((a, v) => a + Number(v.total || 0), 0)
      const qr = ventasActivas
        .filter(v => (v.tipo_pago || '').toLowerCase() === 'qr')
        .reduce((a, v) => a + Number(v.total || 0), 0)
      const items = ventasActivas.reduce((acc, v) => acc + (v.venta_detalles || v.ventaDetalles || []).reduce((x, d) => x + Number(d.cantidad || 0), 0), 0)

      this.kpis = {
        ingresos,
        egresos,
        neto: ingresos - egresos,
        efectivo,
        qr,
        ventas: ventasActivas.length,
        pedidos: this.pedidos.length,
        items,
        ticket_promedio: ventasActivas.length ? ingresos / ventasActivas.length : 0
      }
    },
    computeCharts () {
      const dayMap = {}
      const addDay = (date, key, amount) => {
        if (!dayMap[date]) dayMap[date] = { ingreso: 0, egreso: 0 }
        dayMap[date][key] += amount
      }

      this.ventas
        .filter(v => (v.estado || '').toLowerCase() === 'activo')
        .forEach(v => addDay(v.fecha, 'ingreso', Number(v.total || 0)))
      this.compras
        .filter(c => (c.estado || '').toLowerCase() === 'activo')
        .forEach(c => addDay(c.fecha, 'egreso', Number(c.total || 0)))

      const dates = Object.keys(dayMap).sort()
      const ingresos = dates.map(d => dayMap[d].ingreso)
      const egresos = dates.map(d => dayMap[d].egreso)
      const neto = dates.map(d => dayMap[d].ingreso - dayMap[d].egreso)

      this.chartDaily = {
        series: [
          { name: 'Ingresos', data: ingresos },
          { name: 'Egresos', data: egresos },
          { name: 'Neto', data: neto }
        ],
        options: {
          chart: { toolbar: { show: false } },
          stroke: { curve: 'smooth', width: 3 },
          dataLabels: { enabled: false },
          xaxis: { categories: dates },
          tooltip: { y: { formatter: (v) => `${this.money(v)} Bs` } },
          legend: { position: 'top' },
          colors: ['#2e7d32', '#c62828', '#1565c0']
        }
      }

      const pagos = {}
      this.ventas
        .filter(v => (v.estado || '').toLowerCase() === 'activo')
        .forEach(v => {
          const key = v.tipo_pago || 'OTRO'
          pagos[key] = (pagos[key] || 0) + Number(v.total || 0)
        })

      const labels = Object.keys(pagos)
      const series = labels.map(l => pagos[l])
      this.chartPago = {
        series,
        options: {
          labels,
          legend: { position: 'bottom' },
          tooltip: { y: { formatter: (v) => `${this.money(v)} Bs` } }
        }
      }
    },
    computeTopProductos () {
      const map = {}
      this.ventas
        .filter(v => (v.estado || '').toLowerCase() === 'activo')
        .forEach(v => {
          const detalles = v.venta_detalles || v.ventaDetalles || []
          detalles.forEach(d => {
            const name = d?.producto?.nombre || d?.nombre || `Prod ${d.producto_id}`
            if (!map[name]) map[name] = { nombre: name, cantidad: 0, total: 0 }
            map[name].cantidad += Number(d.cantidad || 0)
            map[name].total += Number(d.precio || 0) * Number(d.cantidad || 0)
          })
        })

      this.topProductos = Object.values(map)
        .sort((a, b) => b.cantidad - a.cantidad)
        .slice(0, 10)
    },
    money (v) {
      return Number(v || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    num (v) {
      return Number(v || 0).toLocaleString('es-BO', { maximumFractionDigits: 2 })
    }
  }
}
</script>

<style scoped>
.dashboard-logo-wrap {
  min-height: calc(100vh - 120px);
  display: flex;
  align-items: center;
  justify-content: center;
}
.dashboard-logo-card {
  width: min(680px, 92vw);
  border-radius: 28px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.96) 0%, rgba(244, 248, 255, 0.96) 100%);
  box-shadow: 0 24px 50px rgba(15, 23, 42, 0.08);
}
.dashboard-logo-image {
  width: min(360px, 70vw);
  max-width: 100%;
}
.kpi-card {
  border-radius: 14px;
}
.kpi-ingresos {
  box-shadow: inset 0 0 0 2px rgba(76, 175, 80, 0.25);
}
.kpi-egresos {
  box-shadow: inset 0 0 0 2px rgba(244, 67, 54, 0.25);
}
.kpi-neto {
  box-shadow: inset 0 0 0 2px rgba(33, 150, 243, 0.25);
}
</style>
