<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md">
        <div class="text-h6 text-weight-bold">Ventas</div>
        <div class="text-caption text-grey-7">Pedidos registrados por rango de fechas</div>
      </div>
      <div class="col-auto">
        <q-chip square dense color="primary" text-color="white" icon="receipt_long">
          <span class="text-caption">Ventas:</span>&nbsp;<b>{{ resumen.ventas }}</b>
        </q-chip>
      </div>
      <div class="col-auto">
        <q-chip square dense color="teal" text-color="white" icon="list_alt">
          <span class="text-caption">Ítems:</span>&nbsp;<b>{{ resumen.items }}</b>
        </q-chip>
      </div>
      <div class="col-auto">
        <q-chip square dense color="positive" text-color="white" icon="attach_money">
          <span class="text-caption">Total Bs:</span>&nbsp;<b>{{ money(resumen.total) }}</b>
        </q-chip>
      </div>
    </div>

    <q-card flat bordered class="q-pa-sm q-mb-md">
      <div class="row q-col-gutter-sm">
        <div class="col-6 col-md-2">
          <q-input v-model="filtros.desde" type="date" dense outlined label="Desde"/>
        </div>
        <div class="col-6 col-md-2">
          <q-input v-model="filtros.hasta" type="date" dense outlined label="Hasta"/>
        </div>
        <div class="col-12 col-md-3">
          <q-input
            v-model="filtros.search"
            dense outlined clearable
            label="Cliente, dirección, zona o Nº"
          >
            <template v-slot:append><q-icon name="search"/></template>
          </q-input>
        </div>
        <div class="col-12 col-sm-6 col-md-2">
          <q-input v-model="filtros.producto" dense outlined clearable label="Producto"/>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <q-select
            v-model="filtros.vendedor"
            dense outlined clearable emit-value map-options
            label="Vendedor"
            :options="opciones.vendedores"
          />
        </div>
        <div class="col-6 col-sm-4 col-md-2">
          <q-select v-model="filtros.tipo" dense outlined clearable label="Tipo" :options="opciones.tipos"/>
        </div>
        <div class="col-6 col-sm-4 col-md-2">
          <q-select v-model="filtros.estado" dense outlined clearable label="Estado" :options="opciones.estados"/>
        </div>
        <div class="col-12 col-sm-4 col-md-4 row items-center q-gutter-sm">
          <q-btn :loading="loading" color="primary" icon="search" no-caps label="Buscar" @click="recargar"/>
          <q-btn flat color="grey-7" icon="layers_clear" no-caps label="Limpiar" @click="limpiar"/>
        </div>
      </div>
    </q-card>

    <q-table
      flat bordered dense
      :rows="ventas"
      :columns="columns"
      :row-key="row => row.NroPed + '-' + row.idCli"
      v-model:pagination="pagination"
      :loading="loading"
      :rows-per-page-options="[10, 20, 50, 100]"
      binary-state-sort
      @request="onRequest"
    >
      <template v-slot:body-cell-NroPed="props">
        <q-td :props="props">
          <q-badge color="red-4" text-color="white">#{{ props.value }}</q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-estado="props">
        <q-td :props="props" class="text-center">
          <q-badge :color="props.value === 'ENVIADO' ? 'green-6' : 'orange-6'" text-color="white">
            {{ props.value }}
          </q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-acciones="props">
        <q-td :props="props" class="text-center">
          <q-btn
            dense flat round size="sm" color="primary" icon="visibility"
            @click="verDetalle(props.row)"
          >
            <q-tooltip>Ver detalle</q-tooltip>
          </q-btn>
        </q-td>
      </template>

      <template v-slot:no-data>
        <div class="full-width row flex-center q-pa-md text-grey-7">
          <q-icon name="receipt_long" size="20px" class="q-mr-sm"/>
          No hay ventas en ese rango de fechas
        </div>
      </template>
    </q-table>

    <q-dialog v-model="dialogDetalle">
      <q-card style="min-width: 340px; max-width: 700px; width: 100%">
        <q-card-section class="bg-primary text-white q-py-sm">
          <div class="text-subtitle1 text-weight-bold">
            Venta #{{ ventaSel.NroPed }}
          </div>
          <div class="text-caption">
            {{ ventaSel.cliente }} &middot; {{ ventaSel.fecha }}
          </div>
        </q-card-section>

        <q-card-section class="q-pa-none">
          <q-markup-table dense flat wrap-cells>
            <thead>
            <tr class="bg-grey-2">
              <th class="text-left">Código</th>
              <th class="text-left">Producto</th>
              <th class="text-right">Cant.</th>
              <th class="text-right">Precio</th>
              <th class="text-right">Subtotal</th>
            </tr>
            </thead>
            <tbody v-if="!loadingDetalle">
            <tr v-for="d in detalle" :key="d.codAut">
              <td class="text-left">{{ d.cod_prod }}</td>
              <td class="text-left">{{ d.producto }}</td>
              <td class="text-right">{{ money(d.cantidad) }} {{ d.unidad }}</td>
              <td class="text-right">{{ money(d.precio) }}</td>
              <td class="text-right text-weight-bold">{{ money(d.subtotal) }}</td>
            </tr>
            <tr v-if="detalle.length === 0">
              <td colspan="5" class="text-center text-grey q-pa-md">Sin productos</td>
            </tr>
            </tbody>
            <tbody v-else>
            <tr>
              <td colspan="5" class="text-center q-pa-md">
                <q-spinner color="primary" size="22px" class="q-mr-sm"/> Cargando…
              </td>
            </tr>
            </tbody>
          </q-markup-table>
        </q-card-section>

        <q-separator/>
        <q-card-actions align="between" class="q-px-md">
          <div class="text-weight-bold">
            Total: Bs. {{ money(ventaSel.total) }}
          </div>
          <q-btn flat no-caps label="Cerrar" color="primary" v-close-popup/>
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { date } from 'quasar'

// El rango de fechas es obligatorio en la práctica: sin él la consulta agrupa
// las ~347.000 filas de tbpedidos y pasa de 0,05 s a más de un segundo.
function filtrosPorDefecto () {
  const hoy = new Date()
  return {
    desde: date.formatDate(date.subtractFromDate(hoy, { days: 30 }), 'YYYY-MM-DD'),
    hasta: date.formatDate(hoy, 'YYYY-MM-DD'),
    search: '',
    producto: '',
    vendedor: null,
    tipo: null,
    estado: null
  }
}

export default {
  name: 'VentasLista',
  data () {
    return {
      ventas: [],
      detalle: [],
      ventaSel: {},
      dialogDetalle: false,
      loading: false,
      loadingDetalle: false,
      resumen: { ventas: 0, items: 0, total: 0 },
      opciones: { vendedores: [], tipos: [], estados: [] },
      filtros: filtrosPorDefecto(),
      pagination: {
        sortBy: 'fecha',
        descending: true,
        page: 1,
        rowsPerPage: 20,
        rowsNumber: 0
      },
      columns: [
        { name: 'NroPed', label: 'Nº', field: 'NroPed', align: 'left', sortable: true },
        { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left', sortable: true },
        { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
        { name: 'zona', label: 'Zona', field: 'zona', align: 'left' },
        { name: 'vendedor', label: 'Vendedor', field: 'vendedor', align: 'left', sortable: true },
        { name: 'tipo', label: 'Tipo', field: 'tipo', align: 'center' },
        { name: 'estado', label: 'Estado', field: 'estado', align: 'center' },
        { name: 'items', label: 'Ítems', field: 'items', align: 'right', sortable: true },
        { name: 'total', label: 'Total Bs.', field: 'total', align: 'right', sortable: true, format: v => Number(v || 0).toFixed(2) },
        { name: 'acciones', label: '', field: 'acciones', align: 'center' }
      ]
    }
  },
  created () {
    this.cargarOpciones()
    this.onRequest({ pagination: this.pagination })
  },
  methods: {
    money (v) {
      return Number(v || 0).toFixed(2)
    },
    params () {
      return {
        desde: this.filtros.desde || '',
        hasta: this.filtros.hasta || '',
        search: this.filtros.search || '',
        producto: this.filtros.producto || '',
        vendedor: this.filtros.vendedor || '',
        tipo: this.filtros.tipo || '',
        estado: this.filtros.estado || ''
      }
    },
    cargarOpciones () {
      this.$api.get('ventas/filtros').then(res => {
        this.opciones.vendedores = res.data.vendedores || []
        this.opciones.tipos = res.data.tipos || []
        this.opciones.estados = res.data.estados || []
      }).catch(() => {
        // Sin opciones los selects quedan vacíos, el resto de filtros funciona.
      })
    },
    limpiar () {
      this.filtros = filtrosPorDefecto()
      this.recargar()
    },
    recargar () {
      this.pagination.page = 1
      this.onRequest({ pagination: this.pagination })
    },
    onRequest (props) {
      const { page, rowsPerPage, sortBy, descending } = props.pagination
      this.loading = true

      this.$api.get('ventas', {
        params: { ...this.params(), page, perPage: rowsPerPage, sortBy, descending }
      }).then(res => {
        this.ventas = res.data.data
        this.pagination.page = res.data.current_page
        this.pagination.rowsPerPage = Number(res.data.per_page)
        this.pagination.rowsNumber = res.data.total
        this.pagination.sortBy = sortBy
        this.pagination.descending = descending
      }).catch(err => {
        this.$q.notify({
          message: err.response?.data?.message || 'No se pudieron cargar las ventas',
          color: 'negative',
          icon: 'error',
          position: 'top'
        })
      }).finally(() => {
        this.loading = false
      })

      this.$api.get('ventas/resumen', { params: this.params() })
        .then(res => { this.resumen = res.data })
        .catch(() => { this.resumen = { ventas: 0, items: 0, total: 0 } })
    },
    verDetalle (row) {
      this.ventaSel = row
      this.detalle = []
      this.dialogDetalle = true
      this.loadingDetalle = true

      this.$api.get('ventas/' + row.NroPed + '/detalle', { params: { idCli: row.idCli } })
        .then(res => { this.detalle = res.data })
        .catch(err => {
          this.$q.notify({
            message: err.response?.data?.message || 'No se pudo cargar el detalle de la venta',
            color: 'negative',
            icon: 'error',
            position: 'top'
          })
        })
        .finally(() => { this.loadingDetalle = false })
    }
  }
}
</script>
