<template>
  <q-page class="q-pa-sm page-wrap">

    <!-- Header: fecha + KPIs -->
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm-3">
        <q-input
          v-model="fecha"
          type="date"
          label="Fecha"
          dense
          outlined
          @change="consulta(); reporte();"
          :loading="$q.loading.isActive"
        />
      </div>

      <!-- KPIs -->
<!--      <div class="col-12 col-sm-9">-->
<!--        <div class="row q-col-gutter-sm">-->
<!--          <div class="col-6 col-md-3">-->
<!--            <q-card class="kpi-card">-->
<!--              <q-card-section class="kpi-top">-->
<!--                <div class="kpi-title">Total</div>-->
<!--                <div class="kpi-value">{{ totalPedidos }}</div>-->
<!--              </q-card-section>-->
<!--              <q-separator />-->
<!--              <q-card-section class="kpi-bottom">-->
<!--                Pedidos del día-->
<!--              </q-card-section>-->
<!--            </q-card>-->
<!--          </div>-->

<!--          <div class="col-6 col-md-3">-->
<!--            <q-card class="kpi-card kpi-green">-->
<!--              <q-card-section class="kpi-top">-->
<!--                <div class="kpi-title">Entregados</div>-->
<!--                <div class="kpi-value">{{ entregados }}</div>-->
<!--              </q-card-section>-->
<!--              <q-separator />-->
<!--              <q-card-section class="kpi-bottom">-->
<!--                {{ pct(entregados, totalPedidos) }}% del total-->
<!--              </q-card-section>-->
<!--            </q-card>-->
<!--          </div>-->

<!--          <div class="col-6 col-md-3">-->
<!--            <q-card class="kpi-card kpi-amber">-->
<!--              <q-card-section class="kpi-top">-->
<!--                <div class="kpi-title">No entregado</div>-->
<!--                <div class="kpi-value">{{ noEntregados }}</div>-->
<!--              </q-card-section>-->
<!--              <q-separator />-->
<!--              <q-card-section class="kpi-bottom">-->
<!--                {{ pct(noEntregados, totalPedidos) }}% del total-->
<!--              </q-card-section>-->
<!--            </q-card>-->
<!--          </div>-->

<!--          <div class="col-6 col-md-3">-->
<!--            <q-card class="kpi-card kpi-grey">-->
<!--              <q-card-section class="kpi-top">-->
<!--                <div class="kpi-title">Pendientes</div>-->
<!--                <div class="kpi-value">{{ pendientes }}</div>-->
<!--              </q-card-section>-->
<!--              <q-separator />-->
<!--              <q-card-section class="kpi-bottom">-->
<!--                Sin estado registrado-->
<!--              </q-card-section>-->
<!--            </q-card>-->
<!--          </div>-->
<!--        </div>-->
<!--      </div>-->
    </div>

    <!-- Segunda fila de KPIs -->
<!--    <div class="row q-col-gutter-sm q-mb-md">-->
<!--      <div class="col-12 col-md-3">-->
<!--        <q-card class="kpi-card kpi-red">-->
<!--          <q-card-section class="kpi-top">-->
<!--            <div class="kpi-title">Rechazados</div>-->
<!--            <div class="kpi-value">{{ rechazados }}</div>-->
<!--          </q-card-section>-->
<!--          <q-separator />-->
<!--          <q-card-section class="kpi-bottom">-->
<!--            {{ pct(rechazados, totalPedidos) }}% del total-->
<!--          </q-card-section>-->
<!--        </q-card>-->
<!--      </div>-->

<!--      <div class="col-12 col-md-3">-->
<!--        <q-card class="kpi-card">-->
<!--          <q-card-section class="kpi-top">-->
<!--            <div class="kpi-title">Total Contado</div>-->
<!--            <div class="kpi-value">Bs. {{ fmt(totalEnt) }}</div>-->
<!--          </q-card-section>-->
<!--          <q-separator />-->
<!--          <q-card-section class="kpi-bottom">-->
<!--            Prom. contado: Bs. {{ fmt(promedioContado) }}-->
<!--          </q-card-section>-->
<!--        </q-card>-->
<!--      </div>-->

<!--      <div class="col-12 col-md-3">-->
<!--        <q-card class="kpi-card">-->
<!--          <q-card-section class="kpi-top">-->
<!--            <div class="kpi-title">Prom. Importe</div>-->
<!--            <div class="kpi-value">Bs. {{ fmt(promedioImporte) }}</div>-->
<!--          </q-card-section>-->
<!--          <q-separator />-->
<!--          <q-card-section class="kpi-bottom">-->
<!--            Sobre pedidos con importe-->
<!--          </q-card-section>-->
<!--        </q-card>-->
<!--      </div>-->

<!--      <div class="col-12 col-md-3">-->
<!--        <q-card class="kpi-card">-->
<!--          <q-card-section class="kpi-top">-->
<!--            <div class="kpi-title">Prestamos (día)</div>-->
<!--            <div class="kpi-value">Bs. {{ fmt(totalPrestado) }}</div>-->
<!--          </q-card-section>-->
<!--          <q-separator />-->
<!--          <q-card-section class="kpi-bottom">-->
<!--            Devuelto: Bs. {{ fmt(totalDevuelto) }}-->
<!--          </q-card-section>-->
<!--        </q-card>-->
<!--      </div>-->
<!--    </div>-->

    <!-- ENTREGAS -->
    <q-card flat bordered class="q-mb-lg">
      <q-card-section class="section-title">
        ENTREGAS
      </q-card-section>

      <div class="table-scroll">
        <q-markup-table flat bordered dense separator="cell" class="pretty-table">
          <thead>
          <tr>
            <th>CINIT</th>
            <th>Cliente</th>
            <th>Comanda</th>
            <th>Importe</th>
            <th>Placa</th>
            <th>Despachador</th>
            <th>Tipo Pago</th>
            <th>Estado</th>
            <th style="width:1%"></th>
          </tr>
          </thead>
          <tbody>
          <template v-for="(row, idx) in listado" :key="idx">
            <tr :class="rowClass(row)">
              <td>{{ row.CINIT }}</td>
              <td class="text-weight-medium">{{ row.Nombres }}</td>
              <td class="text-center">{{ row.comanda }}</td>
              <td class="text-right">Bs. {{ fmt(row.Importe) }}</td>
              <td class="text-center">{{ row.placa }}</td>
              <td class="text-center">{{ row.despachador }}</td>
              <td class="text-center">
                <q-badge :color="row.Tipago==='CONTADO' ? 'primary' : 'grey-7'" text-color="white">
                  {{ row.Tipago || '—' }}
                </q-badge>
              </td>
              <td class="text-center">
                <q-chip dense square :color="estadoColor(row.estado)" text-color="black">
                  {{ row.estado ?? 'PENDIENTE' }}
                </q-chip>
              </td>
              <td class="text-center">
                <q-btn
                  dense size="sm"
                  :icon="isExpanded(row) ? 'visibility_off' : 'visibility'"
                  :label="isExpanded(row) ? 'Ocul' : 'Ver'"
                  :color="isExpanded(row) ? 'primary' : 'secondary'"
                  no-caps
                  @click="toggleExpand(row)"
                />
              </td>
            </tr>

            <!-- Detalle expandible -->
            <tr v-show="isExpanded(row)">
              <td colspan="9" class="bg-grey-2">
                <div v-if="row.detalle && row.detalle.length" class="q-mt-xs">
                  <div
                    v-for="(d, i) in row.detalle"
                    :key="i"
                    class="detail-row"
                  >
                    <div><b>Código:</b> {{ d.cod_prod }}</div>
                    <div class="ellipsis"><b>Producto:</b> {{ d.Producto }}</div>
                    <div><b>Cant.:</b> {{ d.cant }}</div>
                  </div>
                </div>
                <div v-else class="text-grey-7">Sin detalle.</div>
              </td>
            </tr>
          </template>
          </tbody>
        </q-markup-table>
      </div>

      <q-separator />
      <q-card-actions align="right" class="q-pa-md">
        <div class="text-subtitle2">
          Total Contado: <b>Bs. {{ fmt(totalEnt) }}</b>
        </div>
      </q-card-actions>
    </q-card>

    <!-- PRESTAMOS -->
    <q-card flat bordered>
      <q-card-section class="section-title">
        PRESTAMOS
      </q-card-section>

      <div class="table-scroll">
        <q-markup-table flat bordered dense separator="cell" class="pretty-table">
          <thead>
          <tr>
            <th>Fecha</th>
            <th>CINIT</th>
            <th>Nombres</th>
            <th>Prestado</th>
            <th>Devuelto</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="(r, i) in listreporte" :key="i">
            <td>{{ r.fecha }}</td>
            <td>{{ r.cinit }}</td>
            <td>{{ r.Nombres }}</td>
            <td class="text-right">Bs. {{ fmt(r.prestado) }}</td>
            <td class="text-right">Bs. {{ fmt(r.devuelto) }}</td>
          </tr>
          <tr v-if="!listreporte.length">
            <td colspan="5" class="text-center text-grey">Sin datos</td>
          </tr>
          </tbody>
          <tfoot v-if="listreporte.length">
          <tr>
            <th colspan="3" class="text-right">Totales</th>
            <th class="text-right">Bs. {{ fmt(totalPrestado) }}</th>
            <th class="text-right">Bs. {{ fmt(totalDevuelto) }}</th>
          </tr>
          </tfoot>
        </q-markup-table>
      </div>
    </q-card>

  </q-page>
</template>

<script>
import { date } from 'quasar'

export default {
  name: 'despachoPage',
  data () {
    return {
      fecha: date.formatDate(new Date(), 'YYYY-MM-DD'),
      listado: [],
      entrega: {},
      fechareporte: {
        ini: date.formatDate(new Date(), 'YYYY-MM-DD'),
        fin: date.formatDate(new Date(), 'YYYY-MM-DD')
      },
      listreporte: [],
      // mapa de expansión por CINIT+comanda (o Cod_Aut si lo prefieres)
      expandedMap: {}
    }
  },
  created () {
    this.consulta()
    this.reporte()
  },
  methods: {
    reporte () {
      this.$q.loading.show()
      this.$api.post('rePrestamo', {
        fecha: this.fecha,
        placa: this.$store.getters['login/user'].placa
      }).then(res => {
        this.listreporte = res.data
        this.$q.loading.hide()
      })
    },
    consulta () {
      this.listado = []
      this.$q.loading.show()
      this.$api.post('reporteDes', { fecha: this.fecha }).then(res => {
        this.listado = res.data
        this.$q.loading.hide()
      })
    },
    fmt (n) {
      const val = parseFloat(n || 0)
      return val.toFixed(2)
    },
    pct (a, b) {
      if (!b) return 0
      return ((a / b) * 100).toFixed(0)
    },
    rowClass (row) {
      if (row.estado === 'ENTREGADO') return 'row-green'
      if (row.estado === 'NO ENTREGADO') return 'row-amber'
      if (row.estado === 'RECHAZADO') return 'row-red'
      return 'row-pending'
    },
    estadoColor (estado) {
      if (estado === 'ENTREGADO') return 'green-3'
      if (estado === 'NO ENTREGADO') return 'amber-3'
      if (estado === 'RECHAZADO') return 'red-3'
      return 'grey-3'
    },
    keyOf (row) {
      // clave estable por fila (ajústala si prefieres otra)
      return `${row.CINIT || ''}-${row.comanda || ''}`
    },
    isExpanded (row) {
      return !!this.expandedMap[this.keyOf(row)]
    },
    toggleExpand (row) {
      const k = this.keyOf(row)
      this.$set
        ? this.$set(this.expandedMap, k, !this.expandedMap[k])
        : (this.expandedMap = { ...this.expandedMap, [k]: !this.expandedMap[k] })
    }
  },
  computed: {
    totalEnt () {
      let res = 0
      this.listado.forEach(r => {
        if (r.Tipago === 'CONTADO') {
          res += parseFloat(r.Importe || 0)
        }
      })
      return parseFloat(res.toFixed(2))
    },
    totalPedidos () {
      return this.listado.length
    },
    entregados () {
      return this.listado.filter(r => r.estado === 'ENTREGADO').length
    },
    noEntregados () {
      return this.listado.filter(r => r.estado === 'NO ENTREGADO').length
    },
    rechazados () {
      return this.listado.filter(r => r.estado === 'RECHAZADO').length
    },
    pendientes () {
      return this.listado.filter(r => !r.estado).length
    },
    promedioImporte () {
      const conImporte = this.listado
        .map(r => parseFloat(r.Importe))
        .filter(v => !isNaN(v) && v > 0)
      if (!conImporte.length) return 0
      const sum = conImporte.reduce((a, b) => a + b, 0)
      return parseFloat((sum / conImporte.length).toFixed(2))
    },
    promedioContado () {
      const contados = this.listado
        .filter(r => r.Tipago === 'CONTADO')
        .map(r => parseFloat(r.Importe))
        .filter(v => !isNaN(v) && v > 0)
      if (!contados.length) return 0
      const sum = contados.reduce((a, b) => a + b, 0)
      return parseFloat((sum / contados.length).toFixed(2))
    },
    totalPrestado () {
      if (!this.listreporte.length) return 0
      return this.listreporte
        .map(r => parseFloat(r.prestado || 0))
        .reduce((a, b) => a + b, 0)
    },
    totalDevuelto () {
      if (!this.listreporte.length) return 0
      return this.listreporte
        .map(r => parseFloat(r.devuelto || 0))
        .reduce((a, b) => a + b, 0)
    }
  }
}
</script>

<style scoped>
.page-wrap {
  background:
    radial-gradient(700px 400px at 15% 0%, rgba(125, 97, 255, .08), rgba(255,255,255,0) 60%),
    radial-gradient(600px 350px at 85% 20%, rgba(142, 114, 255, .06), rgba(255,255,255,0) 60%),
    linear-gradient(180deg, #ffffff 0%, #faf8ff 50%, #f2ecff 100%);
}

/* KPIs */
.kpi-card {
  border-radius: 16px;
  overflow: hidden;
}
.kpi-top {
  padding-bottom: 8px;
}
.kpi-bottom {
  font-size: 12px;
  color: #666;
}
.kpi-title {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #666;
}
.kpi-value {
  font-size: 26px;
  line-height: 1.1;
  font-weight: 800;
}
.kpi-green .kpi-top { background: rgba(76, 175, 80, .08); }
.kpi-amber .kpi-top { background: rgba(255, 193, 7, .12); }
.kpi-red   .kpi-top { background: rgba(244, 67, 54, .10); }
.kpi-grey  .kpi-top { background: rgba(158, 158, 158, .10); }

/* Secciones */
.section-title {
  font-weight: 700;
  letter-spacing: .02em;
  color: #3b3b3b;
  font-size: 15px;
}

/* Tabla */
.table-scroll {
  overflow-x: auto;
}
.pretty-table {
  border-radius: 12px;
}
.pretty-table thead th {
  background: #f8f6ff;
  color: #5a4dbd;
  font-weight: 700;
  white-space: nowrap;
}
.pretty-table tbody td {
  vertical-align: middle;
}

.row-green { background: rgba(76, 175, 80, 0.08); }
.row-amber { background: rgba(255, 193, 7, 0.12); }
.row-red   { background: rgba(244, 67, 54, 0.10); }
.row-pending { background: rgba(158, 158, 158, 0.10); }

.detail-row {
  display: grid;
  grid-template-columns: 140px 1fr 100px;
  gap: 12px;
  padding: 6px 0;
  border-bottom: 1px dashed #e0e0e0;
}
.detail-row:last-child { border-bottom: none; }
.ellipsis {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
