<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md">
        <div class="text-h6 text-weight-bold">Ventas y Facturas</div>
        <div class="text-caption text-grey-7">Del día; se puede ampliar el rango de fechas</div>
      </div>
      <template v-if="vista === 'ventas'">
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
      </template>
      <template v-else>
        <div class="col-auto">
          <q-chip square dense color="red" text-color="white" icon="description">
            <span class="text-caption">Facturas:</span>&nbsp;<b>{{ resumenFacturas.facturas }}</b>
          </q-chip>
        </div>
        <div class="col-auto">
          <q-chip square dense color="positive" text-color="white" icon="attach_money">
            <span class="text-caption">Facturado Bs:</span>&nbsp;<b>{{ money(resumenFacturas.total) }}</b>
          </q-chip>
        </div>
      </template>
    </div>

    <q-tabs
      v-model="vista" dense no-caps align="left" class="text-grey-7 q-mb-sm"
      active-color="primary" indicator-color="primary"
      @update:model-value="recargar"
    >
      <q-tab name="ventas" icon="shopping_cart" label="Ventas"/>
      <q-tab name="facturas" icon="description" label="Facturas"/>
    </q-tabs>

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
            :label="vista === 'ventas' ? 'Cliente, dirección, zona o Nº' : 'Cliente, NIT, Nº factura o comanda'"
          >
            <template v-slot:append><q-icon name="search"/></template>
          </q-input>
        </div>
        <template v-if="vista === 'ventas'">
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
        </template>
        <div class="col-12 col-sm-4 col-md-4 row items-center q-gutter-sm">
          <q-btn :loading="loading" color="primary" icon="search" no-caps label="Buscar" @click="recargar"/>
          <q-btn flat color="grey-7" icon="layers_clear" no-caps label="Limpiar" @click="limpiar"/>
        </div>
      </div>
    </q-card>

    <q-table
      v-if="vista === 'ventas'"
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
        <q-td :props="props" style="white-space: nowrap">
          <q-btn-dropdown
            color="primary" size="xs" dense no-caps icon="menu" label="Opciones"
            :loading="imprimiendo === props.row.NroPed"
          >
            <q-list style="min-width: 200px">
              <q-item clickable v-close-popup @click="verDetalle(props.row)">
                <q-item-section avatar><q-icon name="visibility"/></q-item-section>
                <q-item-section>Ver Detalle</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="imprimirFactura(props.row)">
                <q-item-section avatar><q-icon name="print" color="red"/></q-item-section>
                <q-item-section>Imprimir Factura</q-item-section>
              </q-item>
            </q-list>
          </q-btn-dropdown>
        </q-td>
      </template>

      <template v-slot:no-data>
        <div class="full-width row flex-center q-pa-md text-grey-7">
          <q-icon name="receipt_long" size="20px" class="q-mr-sm"/>
          No hay ventas en ese rango de fechas
        </div>
      </template>
    </q-table>

    <q-table
      v-else
      flat bordered dense
      :rows="facturas"
      :columns="columnasFacturas"
      row-key="CodAut"
      v-model:pagination="paginationFacturas"
      :loading="loading"
      :rows-per-page-options="[10, 20, 50, 100]"
      @request="onRequestFacturas"
    >
      <template v-slot:body-cell-nrofac="props">
        <q-td :props="props">
          <q-badge color="red-4" text-color="white">Nº {{ props.value }}</q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-ESTADO="props">
        <q-td :props="props" class="text-center">
          <q-badge :color="props.value === 'VALIDA' ? 'green-6' : 'orange-6'" text-color="white">
            {{ props.value }}
          </q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-acciones="props">
        <q-td :props="props" style="white-space: nowrap">
          <q-btn-dropdown
            color="primary" size="xs" dense no-caps icon="menu" label="Opciones"
            :loading="imprimiendo === props.row.CodAut"
          >
            <q-list style="min-width: 200px">
              <q-item clickable v-close-popup @click="imprimirFacturaDirecta(props.row)">
                <q-item-section avatar><q-icon name="print" color="red"/></q-item-section>
                <q-item-section>Imprimir Factura</q-item-section>
              </q-item>
              <q-item clickable v-close-popup type="a" target="_blank" :href="props.row.siat">
                <q-item-section avatar><q-icon name="verified" color="blue"/></q-item-section>
                <q-item-section>Ver en Impuestos</q-item-section>
              </q-item>
            </q-list>
          </q-btn-dropdown>
        </q-td>
      </template>

      <template v-slot:no-data>
        <div class="full-width row flex-center q-pa-md text-grey-7">
          <q-icon name="description" size="20px" class="q-mr-sm"/>
          No hay facturas emitidas en ese rango de fechas
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
// Por defecto se abre en el día de hoy; el rango se amplía a mano.
function filtrosPorDefecto () {
  const hoy = new Date()
  return {
    desde: date.formatDate(hoy, 'YYYY-MM-DD'),
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
      vista: 'ventas',
      ventas: [],
      facturas: [],
      detalle: [],
      ventaSel: {},
      dialogDetalle: false,
      loading: false,
      loadingDetalle: false,
      imprimiendo: null,
      resumen: { ventas: 0, items: 0, total: 0 },
      resumenFacturas: { facturas: 0, total: 0 },
      opciones: { vendedores: [], tipos: [], estados: [] },
      filtros: filtrosPorDefecto(),
      pagination: {
        sortBy: 'fecha',
        descending: true,
        page: 1,
        rowsPerPage: 20,
        rowsNumber: 0
      },
      paginationFacturas: {
        page: 1,
        rowsPerPage: 20,
        rowsNumber: 0
      },
      columnasFacturas: [
        { name: 'acciones', label: 'Opciones', field: 'acciones', align: 'left' },
        { name: 'nrofac', label: 'Nº Factura', field: 'nrofac', align: 'left' },
        { name: 'FechaFac', label: 'Fecha', field: 'FechaFac', align: 'left' },
        { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left' },
        { name: 'nit', label: 'NIT/CI', field: 'nit', align: 'left' },
        { name: 'comanda', label: 'Comanda', field: 'comanda', align: 'right' },
        { name: 'importe', label: 'Importe Bs.', field: 'importe', align: 'right', format: v => Number(v || 0).toFixed(2) },
        { name: 'ESTADO', label: 'Estado', field: 'ESTADO', align: 'center' }
      ],
      columns: [
        // Primera columna: con la tabla ancha, al final quedaban fuera de pantalla.
        { name: 'acciones', label: 'Opciones', field: 'acciones', align: 'left' },
        { name: 'NroPed', label: 'Nº', field: 'NroPed', align: 'left', sortable: true },
        { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left', sortable: true },
        { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
        { name: 'zona', label: 'Zona', field: 'zona', align: 'left' },
        { name: 'vendedor', label: 'Vendedor', field: 'vendedor', align: 'left', sortable: true },
        { name: 'tipo', label: 'Tipo', field: 'tipo', align: 'center' },
        { name: 'estado', label: 'Estado', field: 'estado', align: 'center' },
        { name: 'items', label: 'Ítems', field: 'items', align: 'right', sortable: true },
        { name: 'total', label: 'Total Bs.', field: 'total', align: 'right', sortable: true, format: v => Number(v || 0).toFixed(2) }
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
      if (this.vista === 'facturas') {
        this.paginationFacturas.page = 1
        this.onRequestFacturas({ pagination: this.paginationFacturas })
        return
      }
      this.pagination.page = 1
      this.onRequest({ pagination: this.pagination })
    },
    onRequestFacturas (props) {
      const { page, rowsPerPage } = props.pagination
      this.loading = true

      this.$api.get('facturas', {
        params: {
          desde: this.filtros.desde || '',
          hasta: this.filtros.hasta || '',
          search: this.filtros.search || '',
          page,
          perPage: rowsPerPage
        }
      }).then(res => {
        this.facturas = res.data.data
        this.paginationFacturas.page = res.data.current_page
        this.paginationFacturas.rowsPerPage = Number(res.data.per_page)
        this.paginationFacturas.rowsNumber = res.data.total
      }).catch(err => {
        this.$q.notify({
          message: err.response?.data?.message || 'No se pudieron cargar las facturas',
          color: 'negative',
          icon: 'error',
          position: 'top'
        })
      }).finally(() => {
        this.loading = false
      })

      this.$api.get('facturas/resumen', {
        params: { desde: this.filtros.desde || '', hasta: this.filtros.hasta || '' }
      })
        .then(res => { this.resumenFacturas = res.data })
        .catch(() => { this.resumenFacturas = { facturas: 0, total: 0 } })
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
    },

    // Desde la pestaña de Facturas la fila ya ES la factura, así que se imprime
    // por su CodAut: es exacto, no hay nada que deducir.
    imprimirFacturaDirecta (factura) {
      this.imprimiendo = factura.CodAut

      this.$api.get('facturas/' + factura.CodAut + '/pdf', {
        responseType: 'blob',
        headers: { Accept: 'application/pdf' }
      }).then(res => {
        this.abrirBlobPdf(res.data, `factura_${factura.nrofac}.pdf`)
      }).catch(async err => {
        this.$q.notify({
          message: await this.mensajeDeError(err, 'No se pudo imprimir la factura ' + factura.nrofac),
          color: 'negative',
          icon: 'error',
          position: 'top'
        })
      }).finally(() => { this.imprimiendo = null })
    },

    // Un clic: el backend elige la factura del cliente que corresponde a esta
    // venta (importe igual y fecha más cercana) y devuelve el PDF.
    imprimirFactura (row) {
      this.imprimiendo = row.NroPed

      this.$api.get('ventas/' + row.NroPed + '/factura', {
        params: { idCli: row.idCli },
        responseType: 'blob',
        headers: { Accept: 'application/pdf' }
      }).then(res => {
        this.abrirBlobPdf(res.data, `factura_venta_${row.NroPed}.pdf`)
      }).catch(async err => {
        this.$q.notify({
          message: await this.mensajeDeError(err, 'No se pudo imprimir la factura de la venta'),
          color: 'negative',
          icon: 'error',
          position: 'top'
        })
      }).finally(() => { this.imprimiendo = null })
    },

    // Las respuestas de error de una petición blob llegan como Blob, no como
    // JSON, así que hay que leerlas para poder mostrar el motivo real.
    async mensajeDeError (err, porDefecto) {
      const data = err.response?.data
      if (data instanceof Blob) {
        try {
          return JSON.parse(await data.text()).message || porDefecto
        } catch (e) {
          return porDefecto
        }
      }
      return data?.message || porDefecto
    },

    abrirBlobPdf (data, nombre) {
      const url = window.URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
      // Si el navegador bloquea la pestaña nueva, al menos se descarga.
      if (!window.open(url, '_blank')) {
        const link = document.createElement('a')
        link.href = url
        link.download = nombre
        link.click()
      }
      setTimeout(() => window.URL.revokeObjectURL(url), 60000)
    }
  }
}
</script>
