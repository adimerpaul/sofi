<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md">
        <div class="text-h6 text-weight-bold">Compras</div>
        <div class="text-caption text-grey-7">
          Ingresos de mercadería a proveedores; cada compra suma al stock
        </div>
      </div>
      <div class="col-auto">
        <q-btn
          v-if="can('comprasNueva')"
          color="positive" unelevated no-caps icon="add_shopping_cart"
          label="Nueva compra" to="/compras/nueva"
        />
      </div>
    </div>

    <q-card flat bordered class="q-pa-sm q-mb-md">
      <div class="row q-col-gutter-sm" @keyup.enter="recargar">
        <div class="col-6 col-md-2">
          <q-input v-model="filtros.desde" type="date" dense outlined label="Desde"/>
        </div>
        <div class="col-6 col-md-2">
          <q-input v-model="filtros.hasta" type="date" dense outlined label="Hasta"/>
        </div>
        <div class="col-12 col-md-3">
          <q-input v-model="filtros.buscar" dense outlined clearable label="Proveedor, NIT o factura">
            <template v-slot:append><q-icon name="search"/></template>
          </q-input>
        </div>
        <div class="col-6 col-md-2">
          <q-select v-model="filtros.estado" dense outlined clearable label="Estado" :options="['ACTIVO', 'ANULADO']"/>
        </div>
        <div class="col-12 col-md row items-center q-gutter-sm">
          <q-btn :loading="loading" color="primary" icon="search" no-caps label="Buscar" @click="recargar"/>
          <q-btn flat color="grey-7" icon="layers_clear" no-caps label="Limpiar" @click="limpiar"/>
        </div>
      </div>
    </q-card>

    <q-table
      flat bordered dense
      :rows="compras"
      :columns="columns"
      row-key="id"
      v-model:pagination="pagination"
      :loading="loading"
      :rows-per-page-options="[15, 30, 50, 100]"
      @request="onRequest"
    >
      <template v-slot:body-cell-id="props">
        <q-td :props="props">
          <q-badge color="indigo-5" text-color="white">#{{ props.value }}</q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-estado="props">
        <q-td :props="props" class="text-center">
          <q-badge :color="props.value === 'ANULADO' ? 'negative' : 'positive'" text-color="white">
            {{ props.value }}
          </q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-proveedor="props">
        <q-td :props="props">
          <template v-if="props.value">
            {{ props.value }}
            <div class="text-caption text-grey-7">NIT {{ props.row.nit || '—' }}</div>
          </template>
          <span v-else class="text-grey-6">Sin proveedor</span>
        </q-td>
      </template>

      <template v-slot:body-cell-acciones="props">
        <q-td :props="props" style="white-space: nowrap">
          <q-btn dense flat round size="sm" icon="visibility" color="primary" @click="verDetalle(props.row)">
            <q-tooltip>Ver detalle</q-tooltip>
          </q-btn>
          <q-btn
            v-if="can('comprasAnular') && props.row.estado !== 'ANULADO'"
            dense flat round size="sm" icon="block" color="negative"
            @click="pedirAnulacion(props.row)"
          >
            <q-tooltip>Anular y devolver el stock</q-tooltip>
          </q-btn>
        </q-td>
      </template>

      <template v-slot:no-data>
        <div class="full-width row flex-center q-pa-md text-grey-7">
          <q-icon name="inventory" size="20px" class="q-mr-sm"/>
          No hay compras en este rango
        </div>
      </template>
    </q-table>

    <q-dialog v-model="dialogDetalle">
      <q-card style="min-width: 340px; max-width: 720px; width: 100%">
        <q-card-section class="bg-primary text-white q-py-sm">
          <div class="text-subtitle1 text-weight-bold">Compra #{{ sel.id }}</div>
          <div class="text-caption">
            {{ sel.proveedor || 'Sin proveedor' }} · {{ String(sel.fecha || '').substr(0, 10) }} {{ sel.hora }}
            <span v-if="sel.nro_factura"> · Factura {{ sel.nro_factura }}</span>
          </div>
        </q-card-section>

        <q-card-section v-if="sel.estado === 'ANULADO'" class="q-py-sm">
          <q-banner dense rounded class="bg-red-1 text-red-9">
            <template v-slot:avatar><q-icon name="block"/></template>
            Anulada: {{ sel.motivo_anulacion }} — el stock fue devuelto.
          </q-banner>
        </q-card-section>

        <q-card-section class="q-pa-none">
          <q-markup-table dense flat wrap-cells>
            <thead>
            <tr class="bg-grey-2">
              <th class="text-left">Código</th>
              <th class="text-left">Producto</th>
              <th class="text-right">Cant.</th>
              <th class="text-right">Costo</th>
              <th class="text-right">Subtotal</th>
              <th class="text-left">Lote</th>
              <th class="text-left">Vence</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="d in (sel.detalles || [])" :key="d.id">
              <td class="text-left">{{ d.cod_prod }}</td>
              <td class="text-left">{{ d.nombre }}</td>
              <td class="text-right">{{ Number(d.cantidad).toFixed(d.unidad === 'KG' ? 3 : 0) }}</td>
              <td class="text-right">{{ money(d.precio) }}</td>
              <td class="text-right text-weight-bold">{{ money(d.subtotal) }}</td>
              <td class="text-left">{{ d.lote || '—' }}</td>
              <td class="text-left">{{ fechaCorta(d.fecha_vencimiento) }}</td>
            </tr>
            </tbody>
          </q-markup-table>
        </q-card-section>

        <q-separator/>
        <q-card-actions align="between" class="q-px-md">
          <div>
            <div class="text-caption text-grey-7">
              Subtotal Bs {{ money(sel.subtotal) }} · Descuento Bs {{ money(sel.descuento) }}
            </div>
            <div class="text-weight-bold">Total: Bs {{ money(sel.total) }}</div>
          </div>
          <q-btn flat no-caps label="Cerrar" color="primary" v-close-popup/>
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogAnular">
      <q-card style="min-width: 340px">
        <q-card-section class="q-py-sm">
          <div class="text-subtitle1 text-weight-bold">Anular compra #{{ sel.id }}</div>
          <div class="text-caption text-grey-7">
            Se devolverá al stock todo lo que había ingresado.
          </div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <q-input v-model.trim="motivo" outlined dense autofocus autogrow label="Motivo"/>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense no-caps label="Cancelar" v-close-popup/>
          <q-btn
            color="negative" dense unelevated no-caps label="Anular"
            :disable="!motivo" :loading="anulando" @click="anular"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { date } from 'quasar'

function filtrosPorDefecto () {
  const hoy = date.formatDate(new Date(), 'YYYY-MM-DD')
  return { desde: hoy, hasta: hoy, buscar: '', estado: null }
}

export default {
  name: 'ComprasLista',
  data () {
    return {
      compras: [],
      sel: {},
      dialogDetalle: false,
      dialogAnular: false,
      motivo: '',
      anulando: false,
      loading: false,
      filtros: filtrosPorDefecto(),
      pagination: { page: 1, rowsPerPage: 15, rowsNumber: 0 },
      columns: [
        { name: 'acciones', label: 'Opciones', field: 'acciones', align: 'left' },
        { name: 'id', label: 'Nº', field: 'id', align: 'left' },
        { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left', format: v => String(v || '').substr(0, 10) },
        { name: 'hora', label: 'Hora', field: 'hora', align: 'left' },
        { name: 'proveedor', label: 'Proveedor', field: 'proveedor', align: 'left' },
        { name: 'nro_factura', label: 'Factura', field: 'nro_factura', align: 'left' },
        { name: 'tipo_pago', label: 'Pago', field: 'tipo_pago', align: 'center' },
        { name: 'estado', label: 'Estado', field: 'estado', align: 'center' },
        { name: 'total', label: 'Total Bs.', field: 'total', align: 'right', format: v => Number(v || 0).toFixed(2) }
      ]
    }
  },
  computed: {
    can () {
      return this.$store.getters['login/can']
    }
  },
  created () {
    this.onRequest({ pagination: this.pagination })
  },
  methods: {
    money (v) {
      return Number(v || 0).toFixed(2)
    },
    // La fecha llega como 'YYYY-MM-DD…'; se muestra al modo de acá.
    fechaCorta (v) {
      if (!v) {
        return '—'
      }
      const partes = String(v).substr(0, 10).split('-')
      return partes.length === 3 ? partes[2] + '/' + partes[1] + '/' + partes[0] : v
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
      const { page, rowsPerPage } = props.pagination
      this.loading = true

      this.$api.get('compras', {
        params: {
          desde: this.filtros.desde || '',
          hasta: this.filtros.hasta || '',
          buscar: this.filtros.buscar || '',
          estado: this.filtros.estado || '',
          page,
          perPage: rowsPerPage
        }
      }).then(res => {
        this.compras = res.data.data
        this.pagination.page = res.data.current_page
        this.pagination.rowsPerPage = rowsPerPage
        this.pagination.rowsNumber = res.data.total
      }).catch(err => {
        this.avisar(err, 'No se pudieron cargar las compras')
      }).finally(() => {
        this.loading = false
      })
    },

    verDetalle (row) {
      this.sel = row
      this.dialogDetalle = true

      this.$api.get('compras/' + row.id)
        .then(res => { this.sel = res.data })
        .catch(() => {})
    },

    pedirAnulacion (row) {
      this.sel = row
      this.motivo = ''
      this.dialogAnular = true
    },
    anular () {
      this.anulando = true

      this.$api.put('compras/' + this.sel.id + '/anular', { motivo: this.motivo })
        .then(res => {
          this.$q.notify({
            message: res.data.message,
            color: 'positive',
            icon: 'check_circle',
            position: 'top',
            timeout: 6000
          })
          this.dialogAnular = false
          this.onRequest({ pagination: this.pagination })
        })
        .catch(err => { this.avisar(err, 'No se pudo anular') })
        .finally(() => { this.anulando = false })
    },

    avisar (err, porDefecto) {
      this.$q.notify({
        message: err.response?.data?.message || porDefecto,
        color: 'negative',
        icon: 'error',
        position: 'top'
      })
    }
  }
}
</script>
