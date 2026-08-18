<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md">
        <div class="text-h6 text-weight-bold">Facturación</div>
        <div class="text-caption text-grey-7">
          Ventas y facturas registradas desde el sistema web
        </div>
      </div>
      <div class="col-auto">
        <q-btn
          v-if="can('facturacionNueva')"
          color="positive" unelevated no-caps icon="add_shopping_cart"
          label="Nueva venta" to="/facturacion/nueva"
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
          <q-input v-model="filtros.buscar" dense outlined clearable label="Cliente, NIT o número">
            <template v-slot:append><q-icon name="search"/></template>
          </q-input>
        </div>
        <div class="col-6 col-md-2">
          <q-select
            v-model="filtros.tipo" dense outlined clearable
            label="Tipo" :options="['VENTA', 'FACTURA']"
          />
        </div>
        <div class="col-6 col-md-2">
          <q-select
            v-model="filtros.estado" dense outlined clearable
            label="Estado" :options="['ACTIVO', 'ANULADO']"
          />
        </div>
        <div class="col-12 col-md row items-center q-gutter-sm">
          <q-btn :loading="loading" color="primary" icon="search" no-caps label="Buscar" @click="recargar"/>
          <q-btn flat color="grey-7" icon="layers_clear" no-caps label="Limpiar" @click="limpiar"/>
        </div>
      </div>
    </q-card>

    <q-table
      flat bordered dense
      :rows="facturas"
      :columns="columns"
      row-key="id"
      v-model:pagination="pagination"
      :loading="loading"
      :rows-per-page-options="[10, 20, 50, 100, 0]"
      @request="onRequest"
    >
      <template v-slot:body-cell-id="props">
        <q-td :props="props">
          <q-badge color="red-4" text-color="white">#{{ props.value }}</q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-tipo_comprobante="props">
        <q-td :props="props" class="text-center">
          <q-chip
            dense square
            :color="props.value === 'FACTURA' ? 'green-7' : 'blue-grey-6'"
            text-color="white"
            :icon="props.value === 'FACTURA' ? 'verified' : 'receipt'"
            :label="props.value === 'FACTURA' ? 'Factura' : 'Venta'"
          />
        </q-td>
      </template>

      <template v-slot:body-cell-estado="props">
        <q-td :props="props" class="text-center">
          <q-badge :color="props.value === 'ANULADO' ? 'negative' : 'positive'" text-color="white">
            {{ props.value }}
          </q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-nombre="props">
        <q-td :props="props">
          <template v-if="props.value">
            {{ props.value }}
            <div class="text-caption text-grey-7">NIT {{ props.row.nit || '—' }}</div>
          </template>
          <span v-else class="text-grey-6">Sin cliente</span>
        </q-td>
      </template>

      <template v-slot:body-cell-acciones="props">
        <q-td :props="props" style="white-space: nowrap">
          <q-btn-dropdown
            color="primary" size="sm" dense no-caps icon="menu" label="Opciones"
            :loading="imprimiendo === props.row.id"
          >
            <q-list dense style="min-width: 200px">
              <q-item clickable v-close-popup @click="verDetalle(props.row)">
                <q-item-section avatar><q-icon name="visibility" color="primary"/></q-item-section>
                <q-item-section>Ver detalle</q-item-section>
              </q-item>

              <q-separator/>

              <q-item clickable v-close-popup @click="imprimir(props.row, 'voucher')">
                <q-item-section avatar><q-icon name="receipt" color="blue-grey-7"/></q-item-section>
                <q-item-section>Imprimir voucher</q-item-section>
              </q-item>

              <!-- Solo las que se entregaron como factura tienen factura. -->
              <q-item
                clickable v-close-popup
                :disable="props.row.tipo_comprobante !== 'FACTURA'"
                @click="imprimir(props.row, 'factura')"
              >
                <q-item-section avatar><q-icon name="verified" color="green-7"/></q-item-section>
                <q-item-section>
                  Imprimir factura
                  <q-item-label v-if="props.row.tipo_comprobante !== 'FACTURA'" caption>
                    Se entregó como voucher
                  </q-item-label>
                </q-item-section>
              </q-item>

              <template v-if="can('facturacionAnular') && props.row.estado !== 'ANULADO'">
                <q-separator/>
                <q-item clickable v-close-popup @click="pedirAnulacion(props.row)">
                  <q-item-section avatar><q-icon name="block" color="negative"/></q-item-section>
                  <q-item-section>
                    Anular
                    <q-item-label caption>Devuelve el stock</q-item-label>
                  </q-item-section>
                </q-item>
              </template>
            </q-list>
          </q-btn-dropdown>
        </q-td>
      </template>

      <template v-slot:no-data>
        <div class="full-width row flex-center q-pa-md text-grey-7">
          <q-icon name="request_quote" size="20px" class="q-mr-sm"/>
          Todavía no hay ventas registradas en este rango
        </div>
      </template>
    </q-table>

    <!-- Detalle -->
    <q-dialog v-model="dialogDetalle">
      <q-card style="min-width: 340px; max-width: 700px; width: 100%">
        <q-card-section class="bg-primary text-white q-py-sm">
          <div class="text-subtitle1 text-weight-bold">
            {{ sel.tipo_comprobante === 'FACTURA' ? 'Factura' : 'Venta' }} #{{ sel.id }}
          </div>
          <div class="text-caption">
            {{ sel.nombre || 'Sin cliente' }} · {{ sel.fecha }} {{ sel.hora }}
          </div>
        </q-card-section>

        <q-card-section v-if="sel.estado === 'ANULADO'" class="q-py-sm">
          <q-banner dense rounded class="bg-red-1 text-red-9">
            <template v-slot:avatar><q-icon name="block"/></template>
            Anulada: {{ sel.motivo_anulacion }}
          </q-banner>
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
            <tbody>
            <tr v-for="d in (sel.detalles || [])" :key="d.id">
              <td class="text-left">{{ d.cod_prod }}</td>
              <td class="text-left">{{ d.nombre }}</td>
              <td class="text-right">{{ Number(d.cantidad).toFixed(d.unidad === 'KG' ? 3 : 0) }}</td>
              <td class="text-right">{{ money(d.precio) }}</td>
              <td class="text-right text-weight-bold">{{ money(d.subtotal) }}</td>
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

    <!-- Anulación -->
    <q-dialog v-model="dialogAnular">
      <q-card style="min-width: 340px">
        <q-card-section class="q-py-sm">
          <div class="text-subtitle1 text-weight-bold">Anular #{{ sel.id }}</div>
          <div class="text-caption text-grey-7">
            No se borra: queda registrada como anulada.
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
  return { desde: hoy, hasta: hoy, buscar: '', tipo: null, estado: null }
}

export default {
  name: 'FacturasLista',
  data () {
    return {
      facturas: [],
      sel: {},
      dialogDetalle: false,
      dialogAnular: false,
      motivo: '',
      anulando: false,
      imprimiendo: null,
      loading: false,
      filtros: filtrosPorDefecto(),
      pagination: { page: 1, rowsPerPage: 20, rowsNumber: 0 },
      columns: [
        { name: 'acciones', label: 'Opciones', field: 'acciones', align: 'left' },
        { name: 'id', label: 'Nº', field: 'id', align: 'left' },
        { name: 'tipo_comprobante', label: 'Tipo', field: 'tipo_comprobante', align: 'center' },
        { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left', format: v => String(v || '').substr(0, 10) },
        { name: 'hora', label: 'Hora', field: 'hora', align: 'left' },
        { name: 'nombre', label: 'Cliente', field: 'nombre', align: 'left' },
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

      this.$api.get('facturacion', {
        params: {
          desde: this.filtros.desde || '',
          hasta: this.filtros.hasta || '',
          buscar: this.filtros.buscar || '',
          tipo: this.filtros.tipo || '',
          estado: this.filtros.estado || '',
          page,
          perPage: rowsPerPage === 0 ? 200 : rowsPerPage
        }
      }).then(res => {
        this.facturas = res.data.data
        this.pagination.page = res.data.current_page
        this.pagination.rowsPerPage = rowsPerPage
        this.pagination.rowsNumber = res.data.total
      }).catch(err => {
        this.avisar(err, 'No se pudieron cargar las ventas')
      }).finally(() => {
        this.loading = false
      })
    },

    // 'voucher' o 'factura'. Se abre en pestaña nueva; si el navegador la
    // bloquea, al menos se descarga.
    imprimir (row, documento) {
      this.imprimiendo = row.id

      return this.$api.get('facturacion/' + row.id + '/' + documento, { responseType: 'blob' })
        .then(res => {
          const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))

          if (!window.open(url, '_blank')) {
            const link = document.createElement('a')
            link.href = url
            link.download = documento + '_' + row.id + '.pdf'
            link.click()
          }

          setTimeout(() => window.URL.revokeObjectURL(url), 60000)
        })
        .catch(async err => {
          this.avisar(err, 'No se pudo imprimir el ' + documento)
        })
        .finally(() => { this.imprimiendo = null })
    },

    verDetalle (row) {
      this.sel = row
      this.dialogDetalle = true

      // El listado ya trae el detalle, pero se refresca por si cambió.
      this.$api.get('facturacion/' + row.id)
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

      this.$api.put('facturacion/' + this.sel.id + '/anular', { motivo: this.motivo })
        .then(res => {
          this.$q.notify({
            message: res.data.message,
            color: 'positive',
            icon: 'check_circle',
            position: 'top'
          })
          this.dialogAnular = false
          this.onRequest({ pagination: this.pagination })
        })
        .catch(err => { this.avisar(err, 'No se pudo anular') })
        .finally(() => { this.anulando = false })
    },

    async avisar (err, porDefecto) {
      let mensaje = err.response?.data?.message

      // En las descargas la respuesta viaja como Blob: el motivo real del
      // error hay que leerlo del propio Blob.
      if (err.response?.data instanceof Blob) {
        try {
          mensaje = JSON.parse(await err.response.data.text()).message
        } catch (e) {
          mensaje = null
        }
      }

      this.$q.notify({
        message: mensaje || porDefecto,
        color: 'negative',
        icon: 'error',
        position: 'top'
      })
    }
  }
}
</script>
