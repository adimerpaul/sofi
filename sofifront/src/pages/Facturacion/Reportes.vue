<template>
  <q-page class="q-pa-sm">
    <!-- Una pestaña por reporte; por ahora solo el quiebre de stock. -->
    <q-tabs v-model="reporte" dense align="left" no-caps active-color="primary" indicator-color="primary"
            class="text-grey-8">
      <q-tab name="quiebre" icon="production_quantity_limits" label="Quiebre de stock embutido"/>
    </q-tabs>
    <q-separator/>

    <q-tab-panels v-model="reporte" animated>
      <q-tab-panel name="quiebre" class="q-pa-none q-pt-sm">
        <div class="row items-center q-gutter-sm">
          <q-input v-model="fecha" type="date" dense outlined label="Fecha del pedido" style="width: 170px"
                   @update:model-value="consultar"/>
          <q-btn color="primary" icon="refresh" label="Actualizar" no-caps unelevated :loading="cargando"
                 @click="consultar"/>
          <q-btn color="green-8" icon="download" label="Excel" no-caps unelevated :loading="descargando"
                 :disable="!filas.length" @click="descargarExcel"/>
          <q-space/>
          <div class="text-caption text-grey-8">
            {{ ciudad }} · Semana N° <b>{{ semana }}</b>
          </div>
        </div>

        <div class="text-caption text-grey-7 q-mt-xs">
          Lo que pidió cada cliente de embutidos contra lo que se le vendió en caja. Solo entran los pedidos que ya
          tienen comprobante.
        </div>

        <!-- Totales -->
        <div class="row q-col-gutter-sm q-mt-xs">
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Pedido</div>
              <div class="tarjeta-valor">{{ numero(totales.pedido) }}</div>
            </q-card>
          </div>
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Vendido</div>
              <div class="tarjeta-valor text-blue-9">{{ numero(totales.vendido) }}</div>
            </q-card>
          </div>
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Quiebre</div>
              <div class="tarjeta-valor text-negative">{{ numero(totales.quiebre) }}</div>
              <div class="tarjeta-sub">{{ porcentaje }}% de lo pedido</div>
            </q-card>
          </div>
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Productos con quiebre</div>
              <div class="tarjeta-valor">{{ filas.length }}</div>
              <div class="tarjeta-sub">
                {{ totales.parcial }} parcial · {{ totales.total }} total · {{ totales.clientes }} clientes
              </div>
            </q-card>
          </div>
        </div>

        <div class="row items-center q-gutter-sm q-mt-sm">
          <q-btn-toggle v-model="estado" dense no-caps unelevated toggle-color="primary" color="grey-3"
                        text-color="grey-9" :options="[
                          { label: 'Todos', value: '' },
                          { label: 'Parcial', value: 'QUIEBRE PARCIAL' },
                          { label: 'Total', value: 'QUIEBRE TOTAL' }
                        ]"/>
          <q-input v-model="buscar" dense outlined clearable placeholder="Buscar vendedor, cliente o producto"
                   class="col">
            <template v-slot:append><q-icon name="search"/></template>
          </q-input>
        </div>

        <q-markup-table flat bordered dense separator="horizontal" class="q-mt-sm tabla-quiebre">
          <thead>
          <tr class="bg-grey-3">
            <th class="text-left">Estado</th>
            <th class="text-left">Vendedor</th>
            <th class="text-left">Cliente</th>
            <th class="text-left">Producto</th>
            <th class="text-right">Pedido</th>
            <th class="text-right">Vendido</th>
            <th class="text-right">Quiebre</th>
          </tr>
          </thead>
          <tbody>
          <tr v-if="!visibles.length">
            <td colspan="7" class="text-center text-grey-7 q-pa-md">
              {{ cargando ? 'Cargando…' : 'No hay quiebres de stock en esta fecha' }}
            </td>
          </tr>
          <!-- Como la tabla dinamica: estado, vendedor y cliente solo cuando cambian. -->
          <tr v-for="(f, i) in visibles" :key="f.pedido_nro + '-' + f.cod_prod"
              :class="{ 'corte-estado': i > 0 && f._estado }">
            <td>
              <q-badge v-if="f._estado" :color="f.estado === 'QUIEBRE TOTAL' ? 'negative' : 'orange-8'"
                       :label="f.estado"/>
            </td>
            <td class="text-weight-bold">{{ f._vendedor ? f.vendedor : '' }}</td>
            <td>{{ f._cliente ? f.cliente : '' }}</td>
            <td>
              {{ f.producto }}
              <span class="text-grey-6">· {{ f.cod_prod }}</span>
            </td>
            <td class="text-right">{{ numero(f.pedido) }}</td>
            <td class="text-right text-blue-9">{{ numero(f.vendido) }}</td>
            <td class="text-right text-weight-bold text-negative">{{ numero(f.quiebre) }}</td>
          </tr>
          </tbody>
          <tfoot v-if="visibles.length">
          <tr class="bg-grey-3 text-weight-bold">
            <td colspan="4">Total general</td>
            <td class="text-right">{{ numero(suma('pedido')) }}</td>
            <td class="text-right">{{ numero(suma('vendido')) }}</td>
            <td class="text-right text-negative">{{ numero(suma('quiebre')) }}</td>
          </tr>
          </tfoot>
        </q-markup-table>
      </q-tab-panel>
    </q-tab-panels>
  </q-page>
</template>

<script>
import { date } from 'quasar'

export default {
  name: 'ReportesVentas',
  data () {
    return {
      reporte: 'quiebre',
      fecha: date.formatDate(Date.now(), 'YYYY-MM-DD'),
      ciudad: '',
      semana: '',
      filas: [],
      totales: { pedido: 0, vendido: 0, quiebre: 0, parcial: 0, total: 0, clientes: 0 },
      estado: '',
      buscar: '',
      cargando: false,
      descargando: false
    }
  },
  computed: {
    // Filtradas, y marcando en que fila cambia estado, vendedor o cliente.
    visibles () {
      const texto = (this.buscar || '').toLowerCase()
      const lista = this.filas.filter(f =>
        (!this.estado || f.estado === this.estado) &&
        (!texto || [f.vendedor, f.cliente, f.producto, f.cod_prod].some(v => String(v).toLowerCase().includes(texto))))
      return lista.map((f, i) => {
        const prev = lista[i - 1]
        const _estado = !prev || prev.estado !== f.estado
        const _vendedor = _estado || prev.vendedor !== f.vendedor
        return { ...f, _estado, _vendedor, _cliente: _vendedor || prev.pedido_nro !== f.pedido_nro }
      })
    },
    porcentaje () {
      return this.totales.pedido > 0 ? Math.round(this.totales.quiebre / this.totales.pedido * 100) : 0
    }
  },
  created () {
    this.consultar()
  },
  methods: {
    consultar () {
      this.cargando = true
      this.$api.get('reportes/quiebre-embutido', { params: { fecha: this.fecha } }).then(res => {
        this.filas = res.data.filas
        this.totales = res.data.totales
        this.ciudad = res.data.ciudad
        this.semana = res.data.semana
      }).catch(err => {
        this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudo cargar el reporte' })
      }).finally(() => {
        this.cargando = false
      })
    },
    async descargarExcel () {
      this.descargando = true
      try {
        const res = await this.$api.get('reportes/quiebre-embutido/excel', {
          params: { fecha: this.fecha },
          responseType: 'blob'
        })
        const url = window.URL.createObjectURL(res.data)
        const enlace = document.createElement('a')
        enlace.href = url
        enlace.download = 'quiebre_embutido_' + this.fecha + '.xlsx'
        enlace.click()
        window.URL.revokeObjectURL(url)
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'No se pudo descargar el Excel' })
      } finally {
        this.descargando = false
      }
    },
    suma (campo) {
      return this.visibles.reduce((s, f) => s + Number(f[campo] || 0), 0)
    },
    numero (valor) {
      return +Number(valor || 0).toFixed(3)
    }
  }
}
</script>

<style scoped>
.tarjeta {
  padding: 4px 8px;
  text-align: center;
  line-height: 1.2;
}
.tarjeta-titulo {
  font-size: 10px;
  text-transform: uppercase;
  color: rgba(0, 0, 0, 0.55);
}
.tarjeta-valor {
  font-size: 18px;
  font-weight: 700;
}
.tarjeta-sub {
  font-size: 10px;
  color: rgba(0, 0, 0, 0.55);
}
.tabla-quiebre td,
.tabla-quiebre th {
  font-size: 12px;
}
.tabla-quiebre tr.corte-estado td {
  border-top: 2px solid #9e9e9e;
}
</style>
