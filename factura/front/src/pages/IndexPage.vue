<template>
  <q-page class="q-pa-md">

    <!-- Header -->
    <div class="row items-center q-mb-lg">
      <div>
        <div class="text-h5 text-weight-bold text-primary">
          Bienvenido, {{ $store.user.name }}
        </div>
        <div class="text-caption text-grey-6">{{ fechaHoy }} · Farmacia Eliseo</div>
      </div>
      <q-space />
      <q-btn flat round color="primary" icon="refresh" @click="cargar" :loading="loading" />
    </div>

    <!-- KPI Cards -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12 col-sm-6 col-md-3">
        <q-card class="kpi-card" style="background: linear-gradient(135deg, #1565C0 0%, #42A5F5 100%)">
          <q-card-section class="text-white q-pa-md">
            <div class="row items-start no-wrap">
              <div class="col">
                <div class="kpi-label">Ventas hoy</div>
                <div class="kpi-value">Bs. {{ fmt(kpis.total_hoy) }}</div>
                <div class="kpi-sub">{{ kpis.ventas_hoy }} transacciones</div>
              </div>
              <q-icon name="today" size="38px" style="opacity:0.35" />
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card class="kpi-card" style="background: linear-gradient(135deg, #00695C 0%, #4DB6AC 100%)">
          <q-card-section class="text-white q-pa-md">
            <div class="row items-start no-wrap">
              <div class="col">
                <div class="kpi-label">Esta semana</div>
                <div class="kpi-value">Bs. {{ fmt(kpis.total_semana) }}</div>
                <div class="kpi-sub">Últimos 7 días</div>
              </div>
              <q-icon name="date_range" size="38px" style="opacity:0.35" />
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card class="kpi-card" style="background: linear-gradient(135deg, #6A1B9A 0%, #CE93D8 100%)">
          <q-card-section class="text-white q-pa-md">
            <div class="row items-start no-wrap">
              <div class="col">
                <div class="kpi-label">Este mes</div>
                <div class="kpi-value">Bs. {{ fmt(kpis.total_mes) }}</div>
                <div class="kpi-sub">{{ mesActual }}</div>
              </div>
              <q-icon name="calendar_month" size="38px" style="opacity:0.35" />
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card class="kpi-card" style="background: linear-gradient(135deg, #BF360C 0%, #FF8A65 100%)">
          <q-card-section class="text-white q-pa-md">
            <div class="row items-start no-wrap">
              <div class="col">
                <div class="kpi-label">Producto estrella</div>
                <div class="kpi-value" style="font-size: 13px; line-height: 1.3">
                  {{ topProductos[0]?.nombre || '—' }}
                </div>
                <div class="kpi-sub">{{ topProductos[0]?.total_cantidad || 0 }} unidades (30 días)</div>
              </div>
              <q-icon name="star" size="38px" style="opacity:0.35" />
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Gráfico semanal + Mejor vendedor -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12 col-md-8">
        <q-card>
          <q-card-section class="q-pb-none">
            <div class="text-subtitle1 text-weight-bold">Ventas de la semana</div>
            <div class="text-caption text-grey-6">Total en Bs. por día</div>
          </q-card-section>
          <q-card-section>
            <apexchart
              type="bar"
              height="260"
              :options="chartOptions"
              :series="chartSeries"
            />
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-4">
        <q-card style="height: 100%">
          <q-card-section class="q-pb-none">
            <div class="text-subtitle1 text-weight-bold">Mejor vendedor</div>
            <div class="text-caption text-grey-6">Este mes</div>
          </q-card-section>

          <q-card-section v-if="topUsuario" class="text-center q-pt-md">
            <div class="trofeo-avatar q-mx-auto q-mb-sm">
              <q-icon name="emoji_events" size="40px" color="amber-7" />
            </div>
            <div class="text-h6 text-weight-bold q-mb-xs">{{ topUsuario.name }}</div>
            <q-chip color="amber-1" text-color="amber-9" dense icon="workspace_premium" class="q-mb-lg">
              Vendedor del mes
            </q-chip>
            <div class="row q-col-gutter-sm">
              <div class="col-6">
                <div class="stat-box stat-box--blue">
                  <div class="stat-box__value">{{ topUsuario.total_ventas }}</div>
                  <div class="stat-box__label">Ventas</div>
                </div>
              </div>
              <div class="col-6">
                <div class="stat-box stat-box--green">
                  <div class="stat-box__value" style="font-size:12px">Bs. {{ fmt(topUsuario.total_monto) }}</div>
                  <div class="stat-box__label">Total</div>
                </div>
              </div>
            </div>
          </q-card-section>

          <q-card-section v-else class="text-center text-grey-4 q-pt-xl">
            <q-icon name="person_off" size="48px" /><br>
            <span class="text-caption">Sin ventas registradas este mes</span>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Top 5 productos -->
    <div class="row q-col-gutter-md">
      <div class="col-12 col-md-7">
        <q-card>
          <q-card-section class="q-pb-none">
            <div class="text-subtitle1 text-weight-bold">Top 5 · Productos más vendidos</div>
            <div class="text-caption text-grey-6">Últimos 30 días por cantidad</div>
          </q-card-section>
          <q-card-section>
            <div v-if="topProductos.length === 0" class="text-center text-grey-4 q-py-lg">
              <q-icon name="inventory_2" size="40px" /><br>Sin datos
            </div>
            <div v-for="(p, i) in topProductos" :key="i" class="q-mb-md">
              <div class="row items-center q-mb-xs">
                <div class="rank-badge q-mr-sm" :class="'rank-badge--' + i">{{ i + 1 }}</div>
                <span class="col text-body2 text-weight-medium ellipsis">{{ p.nombre }}</span>
                <span class="text-caption text-grey-6 q-ml-sm">{{ p.total_cantidad }} u.</span>
              </div>
              <q-linear-progress
                :value="topProductos[0]?.total_cantidad ? p.total_cantidad / topProductos[0].total_cantidad : 0"
                :color="rankColor(i)"
                size="8px"
                rounded
              />
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <q-inner-loading :showing="loading">
      <q-spinner-dots size="50px" color="primary" />
    </q-inner-loading>
  </q-page>
</template>

<script>
const MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre']

export default {
  name: 'IndexPage',

  data() {
    const now = new Date()
    return {
      loading: false,
      kpis: { total_hoy: 0, total_semana: 0, total_mes: 0, ventas_hoy: 0 },
      ventasSemana: [],
      topProductos: [],
      topUsuario: null,
      fechaHoy: now.toLocaleDateString('es-BO', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
      mesActual: MESES[now.getMonth()] + ' ' + now.getFullYear(),
    }
  },

  computed: {
    chartOptions() {
      return {
        chart: {
          type: 'bar',
          toolbar: { show: false },
          fontFamily: 'inherit',
          animations: { enabled: true, easing: 'easeinout', speed: 500 },
        },
        colors: ['#1976D2'],
        fill: {
          type: 'gradient',
          gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.25, opacityFrom: 1, opacityTo: 0.75 },
        },
        plotOptions: {
          bar: { borderRadius: 7, columnWidth: '52%' },
        },
        dataLabels: { enabled: false },
        xaxis: {
          categories: this.ventasSemana.map(d => d.label),
          axisBorder: { show: false },
          axisTicks: { show: false },
          labels: { style: { colors: '#90A4AE', fontSize: '12px' } },
        },
        yaxis: {
          labels: {
            style: { colors: '#90A4AE', fontSize: '11px' },
            formatter: v => 'Bs.' + v.toLocaleString('es-BO'),
          },
        },
        grid: { borderColor: '#ECEFF1', strokeDashArray: 4 },
        tooltip: {
          y: { formatter: v => 'Bs. ' + v.toLocaleString('es-BO', { minimumFractionDigits: 2 }) },
        },
      }
    },

    chartSeries() {
      return [{ name: 'Ventas', data: this.ventasSemana.map(d => d.total) }]
    },
  },

  mounted() {
    this.cargar()
  },

  methods: {
    cargar() {
      this.loading = true
      this.$axios.get('dashboard')
        .then(res => {
          this.kpis         = res.data.kpis
          this.ventasSemana = res.data.ventas_semana
          this.topProductos = res.data.top_productos
          this.topUsuario   = res.data.top_usuario
        })
        .finally(() => { this.loading = false })
    },

    fmt(v) {
      return Number(v || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },

    rankColor(i) {
      return ['amber-8', 'blue-grey-5', 'orange-6', 'teal-5', 'blue-4'][i] || 'grey-5'
    },
  },
}
</script>

<style scoped>
.kpi-card {
  border-radius: 18px;
  overflow: hidden;
}

.kpi-label {
  font-size: 11px;
  font-weight: 700;
  opacity: 0.8;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-bottom: 6px;
}

.kpi-value {
  font-size: 22px;
  font-weight: 800;
  line-height: 1.15;
  margin-bottom: 4px;
}

.kpi-sub {
  font-size: 12px;
  opacity: 0.72;
}

.trofeo-avatar {
  width: 76px;
  height: 76px;
  border-radius: 50%;
  background: linear-gradient(135deg, #FFF8E1, #FFE082);
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-box {
  border-radius: 10px;
  padding: 10px 6px;
  text-align: center;
}

.stat-box--blue { background: #E3F2FD; }
.stat-box--green { background: #E8F5E9; }

.stat-box__value {
  font-size: 14px;
  font-weight: 700;
  color: #37474F;
  line-height: 1.2;
}

.stat-box__label {
  font-size: 11px;
  color: #90A4AE;
  margin-top: 3px;
}

.rank-badge {
  width: 24px;
  height: 24px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  color: white;
  flex-shrink: 0;
}

.rank-badge--0 { background: #FFB300; }
.rank-badge--1 { background: #78909C; }
.rank-badge--2 { background: #EF6C00; }
.rank-badge--3 { background: #26A69A; }
.rank-badge--4 { background: #42A5F5; }
</style>
