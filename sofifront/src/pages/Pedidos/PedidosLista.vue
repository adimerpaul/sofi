<template>
  <div class="q-pa-md">
    <!-- Header + resumen -->
    <div class="row items-center q-gutter-sm q-mb-md">
      <div class="col-12 col-md">
        <div class="text-h6 text-weight-bold">Pedidos (simple)</div>
        <div class="text-caption text-grey-7">Consulta por rango de fechas y búsqueda libre</div>
      </div>
      <div class="col-auto">
        <q-chip square color="primary" text-color="white" dense icon="shopping_cart">
          <span class="text-caption">Pedidos:</span>&nbsp;<b>{{ rows.length }}</b>
        </q-chip>
      </div>
      <div class="col-auto">
        <q-chip square color="teal" text-color="white" dense icon="list_alt">
          <span class="text-caption">Items:</span>&nbsp;<b>{{ totalItems }}</b>
        </q-chip>
      </div>
      <div class="col-auto">
        <q-chip square color="positive" text-color="white" dense icon="attach_money">
          <span class="text-caption">Total Bs:</span>&nbsp;<b>{{ fmt(totalBs) }}</b>
        </q-chip>
      </div>
    </div>

    <!-- Filtros -->
    <q-card flat bordered class="q-pa-sm q-mb-md">
      <div class="row q-col-gutter-sm">
        <div class="col-12 col-sm-3">
          <q-input type="date" v-model="from" dense outlined label="Desde" />
        </div>
        <div class="col-12 col-sm-3">
          <q-input type="date" v-model="to" dense outlined label="Hasta" />
        </div>
        <div class="col-12 col-sm-4">
          <q-input
            v-model="search"
            dense outlined clearable
            label="Buscar (cliente, dirección, pedido, producto)"
          >
            <template #append><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-12 col-sm-2 flex items-center">
          <div class="row q-gutter-sm full-width">
            <q-btn :loading="loading" class="col" color="red-6" icon="search" no-caps label="Buscar" @click="fetchData" />
            <q-btn :loading="loading" class="col" flat color="grey-7" icon="layers_clear" no-caps label="Limpiar" @click="reset" />
          </div>
        </div>
      </div>
    </q-card>

    <!-- Tabla -->
    <q-card flat bordered>
      <div class="q-pa-sm bg-grey-1 text-grey-8 text-caption">
        Resultados: <b>{{ rows.length }}</b>
      </div>

      <q-markup-table
        dense
        wrap-cells
        flat
        class="rounded-borders q-ma-none table-compact"
      >
        <thead>
        <tr class="bg-red-6 text-white">
          <th class="text-left">Pedido</th>
<!--          <th class="text-left">Fecha</th>-->
          <th class="text-left">Cliente</th>
<!--          <th class="text-left">Dirección</th>-->
          <th class="text-right">Ítems</th>
          <th class="text-right">Total Bs.</th>
          <th class="text-left">Productos</th>
        </tr>
        </thead>

        <tbody v-if="!loading">
        <tr
          v-for="p in rows"
          :key="p.nro_pedido"
          class="hoverable"
        >
          <td class="text-left">
            <q-badge color="red-4" text-color="white" class="q-mr-xs">#{{ p.nro_pedido }}</q-badge>
            <q-badge :color="p.confirmado ? 'green-6' : 'orange-6'" text-color="white">
              {{ p.confirmado ? 'CONFIRMADO' : 'PENDIENTE' }}
            </q-badge>
          </td>
<!--          <td class="text-left text-no-wrap">{{ p.fecha }}</td>-->
          <td class="text-left ellipsis">{{ p.cliente_nombre }}</td>
<!--          <td class="text-left ellipsis">{{ p.cliente_direccion }}</td>-->
          <td class="text-right">{{ (p.detalles || []).length }}</td>
          <td class="text-right text-weight-bold text-positive">
            {{ fmt(sumDetalles(p.detalles, 'subtotal')) }}
          </td>
          <td class="text-left">
            <!-- LISTA COMPACTA DE PRODUCTOS -->
            <ul class="prod-list">
              <li v-for="d in p.detalles" :key="d.id">
                <span class="code">{{ d.cod_prod }}</span>
                <span class="name">{{ d.nombre }}</span>
                <q-badge color="blue-2" text-color="blue-10" class="badge-xs q-mx-xs">
                  x{{ n2(d.cantidad) }}
                </q-badge>
                <q-badge
                  v-if="Number(d.peso) > 0"
                  color="grey-2"
                  text-color="grey-9"
                  class="badge-xs q-mr-xs"
                >
                  {{ n2(d.peso) }} kg
                </q-badge>
                <q-badge color="green-2" text-color="green-10" class="badge-xs">
                  Bs. {{ fmt(d.subtotal) }}
                </q-badge>
              </li>
              <li v-if="!p.detalles || p.detalles.length === 0" class="muted">Sin productos</li>
            </ul>
          </td>
        </tr>

        <tr v-if="rows.length === 0">
          <td colspan="7" class="text-center text-grey q-pa-md">No hay resultados…</td>
        </tr>
        </tbody>

        <tbody v-else>
        <tr>
          <td colspan="7" class="text-center q-pa-md">
            <q-spinner color="red-6" size="24px" class="q-mr-sm" /> Cargando…
          </td>
        </tr>
        </tbody>
      </q-markup-table>
    </q-card>
  </div>
</template>

<script>
export default {
  name: 'PedidosSimple',
  data () {
    const today = new Date().toISOString().substr(0,10)
    return {
      from: today,
      to: today,
      search: '',
      loading: false,
      rows: []
    }
  },
  computed: {
    totalItems () {
      return this.rows.reduce((acc, p) => acc + (p.detalles ? p.detalles.length : 0), 0)
    },
    totalBs () {
      return this.rows.reduce((acc, p) => acc + this.sumDetalles(p.detalles, 'subtotal'), 0)
    }
  },
  created () {
    this.fetchData()
  },
  methods: {
    async fetchData () {
      this.loading = true
      try {
        const { data } = await this.$api.get('/pedidos-simple', {
          params: { from: this.from, to: this.to, search: this.search }
        })
        this.rows = Array.isArray(data.data) ? data.data : []
      } catch (e) {
        this.$q.notify({ type: 'negative', message: e.response?.data?.message || e.message })
      } finally {
        this.loading = false
      }
    },
    reset () {
      const today = new Date().toISOString().substr(0,10)
      this.from = today
      this.to = today
      this.search = ''
      this.fetchData()
    },
    sumDetalles (arr, field) {
      if (!Array.isArray(arr)) return 0
      return arr.reduce((acc, x) => acc + Number(x[field] || 0), 0)
    },
    fmt (v) {
      return Number(v || 0).toFixed(2)
    },
    n2 (v) {
      return Number(v || 0).toFixed(2)
    }
  }
}
</script>

<style scoped>
.table-compact th, .table-compact td {
  font-size: 12px;
  padding: 8px 10px;
  vertical-align: top;
}
.hoverable:hover {
  background: #fff7f7; /* leve rojito */
}
.ellipsis {
  max-width: 240px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.prod-list {
  list-style: none;
  padding: 0; margin: 0;
}
.prod-list li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  padding: 2px 0;
}
.code {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  color: #7a7a7a;
}
.name {
  flex: 1;
  text-transform: lowercase;
  min-width: 120px;
}
.badge-xs {
  font-size: 10px !important;
  height: 18px !important;
  line-height: 18px !important;
  padding: 0 6px;
}
.muted { color: #9e9e9e; font-style: italic; }
</style>
