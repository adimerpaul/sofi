<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md">
        <div class="text-h6 text-weight-bold">Ventas</div>
        <div class="text-caption text-grey-7">
          Lo cobrado en caja, agrupado por comanda. Del día; se puede ampliar el rango de fechas
        </div>
      </div>
      <div class="col-auto">
        <q-chip square dense color="primary" text-color="white" icon="receipt_long">
          <span class="text-caption">Comandas:</span>&nbsp;<b>{{ resumen.ventas }}</b>
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

    <!-- Enter en cualquier campo busca, para no tener que ir al boton. -->
    <q-card flat bordered class="q-pa-sm q-mb-md">
      <div class="row q-col-gutter-sm" @keyup.enter="recargar">
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
            label="Cliente, zona, vendedor, NIT o comanda"
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
            use-input fill-input hide-selected
            input-debounce="0"
            label="Vendedor"
            :options="vendedoresFiltrados"
            @filter="filtrarVendedores"
          >
            <template v-slot:no-option>
              <q-item>
                <q-item-section class="text-grey">Ningún vendedor con ese nombre</q-item-section>
              </q-item>
            </template>
          </q-select>
        </div>
        <div class="col-6 col-sm-4 col-md-2">
          <q-select v-model="filtros.tipo" dense outlined clearable label="Pago" :options="opciones.tipos"/>
        </div>
        <div class="col-6 col-sm-4 col-md-2">
          <q-select v-model="filtros.estado" dense outlined clearable label="Entrega" :options="opciones.estados"/>
        </div>
        <div class="col-6 col-sm-4 col-md-2">
          <q-select
            v-model="filtros.documento"
            dense outlined clearable
            label="Documento"
            :options="['FACTURA', 'VOUCHER']"
          />
        </div>
        <div class="col-12 col-sm-4 col-md-4 row items-center q-gutter-sm">
          <q-btn :loading="loading" color="primary" icon="search" no-caps label="Buscar" @click="recargar"/>
          <q-btn flat color="grey-7" icon="layers_clear" no-caps label="Limpiar" @click="limpiar"/>
        </div>
      </div>
    </q-card>

    <!--
      Ficha de una comanda suelta. Sirve cuando se busca un numero que cae
      fuera del rango de fechas: la tabla no lo muestra, pero la comanda si.
    -->
    <q-card v-if="comandaInfo" flat bordered class="q-mb-md">
      <q-card-section class="q-py-sm">
        <div class="row items-center q-col-gutter-sm">
          <div class="col-12 col-md">
            <div class="text-subtitle2 text-weight-bold">
              Comanda {{ comandaInfo.comanda }}
            </div>
            <div class="text-caption text-grey-7">
              {{ comandaInfo.fecha }} &middot;
              <template v-if="comandaInfo.entrega && comandaInfo.entrega.cliente">
                {{ comandaInfo.entrega.cliente }} &middot; NIT {{ comandaInfo.entrega.nit }}
              </template>
              <template v-else>
                {{ comandaInfo.atendido || 'Venta sin entrega registrada' }}
              </template>
            </div>
          </div>
          <div class="col-auto">
            <q-chip square dense color="teal" text-color="white" icon="list_alt">
              <span class="text-caption">Ítems:</span>&nbsp;<b>{{ comandaInfo.items }}</b>
            </q-chip>
          </div>
          <div class="col-auto">
            <q-chip square dense color="positive" text-color="white" icon="attach_money">
              <span class="text-caption">Total Bs:</span>&nbsp;<b>{{ money(comandaInfo.total) }}</b>
            </q-chip>
          </div>
          <div class="col-auto">
            <q-chip
              v-if="comandaInfo.factura"
              dense clickable square
              color="green-7" text-color="white" icon="receipt"
              :label="'Factura Nº ' + comandaInfo.factura.nrofac"
              @click="imprimirFacturaComanda"
            >
              <q-tooltip>
                Emitida el {{ comandaInfo.factura.FechaFac }} &middot;
                {{ comandaInfo.factura.estado }}. Clic para imprimir.
              </q-tooltip>
            </q-chip>
            <q-chip
              v-else
              dense square outline
              color="blue-grey-7" icon="receipt"
              label="Voucher"
            >
              <q-tooltip>La comanda no llegó a facturarse.</q-tooltip>
            </q-chip>
          </div>
        </div>
      </q-card-section>

      <q-separator/>

      <q-card-actions class="q-px-md">
        <q-btn
          dense flat no-caps color="primary" icon="receipt_long"
          label="Ver boleta de entrega"
          @click="verDetalle(comandaInfo)"
        />
      </q-card-actions>
    </q-card>

    <q-table
      flat bordered dense
      :rows="ventas"
      :columns="columns"
      row-key="comanda"
      v-model:pagination="pagination"
      :loading="loading"
      :rows-per-page-options="[10, 20, 50, 100, 200, 0]"
      binary-state-sort
      @request="onRequest"
    >
      <template v-slot:body-cell-comanda="props">
        <q-td :props="props">
          <q-badge color="red-4" text-color="white">#{{ props.value }}</q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-cliente="props">
        <q-td :props="props">
          <template v-if="props.value">
            {{ props.value }}
            <div class="text-caption text-grey-7">
              NIT {{ props.row.nit }}
              <span v-if="props.row.origenCliente">&middot; {{ origenLabel(props.row) }}</span>
            </div>
          </template>
          <span v-else class="text-grey-6">{{ sinCliente(props.row) }}</span>
        </q-td>
      </template>

      <template v-slot:body-cell-estado="props">
        <q-td :props="props" class="text-center">
          <q-badge v-if="props.value" :color="props.value === 'ENTREGADO' ? 'green-6' : 'orange-6'" text-color="white">
            {{ props.value }}
          </q-badge>
          <span v-else class="text-grey-5">—</span>
        </q-td>
      </template>

      <template v-slot:body-cell-factura="props">
        <q-td :props="props" class="text-center" style="white-space: nowrap">
          <q-chip
            v-if="props.row.factura"
            dense clickable square
            color="green-7" text-color="white" icon="receipt"
            :label="'Factura Nº ' + props.row.factura.nrofac"
            @click="imprimirFactura(props.row)"
          >
            <q-tooltip>
              Facturada el {{ props.row.factura.FechaFac }} &middot;
              NIT {{ props.row.factura.nit }} &middot;
              {{ props.row.factura.estado }}. Clic para imprimir.
            </q-tooltip>
          </q-chip>
          <q-chip
            v-else
            dense square outline
            color="blue-grey-7" icon="receipt"
            label="Voucher"
          >
            <q-tooltip>
              Sin factura fiscal emitida: la venta se entregó con voucher.
            </q-tooltip>
          </q-chip>
        </q-td>
      </template>

      <template v-slot:body-cell-acciones="props">
        <q-td :props="props" style="white-space: nowrap">
          <q-btn-dropdown
            color="primary" size="xs" dense no-caps icon="menu" label="Opciones"
            :loading="imprimiendo === props.row.comanda"
          >
            <q-list style="min-width: 200px">
              <q-item clickable v-close-popup @click="verDetalle(props.row)">
                <q-item-section avatar><q-icon name="visibility"/></q-item-section>
                <q-item-section>Ver Detalle</q-item-section>
              </q-item>
              <q-item
                clickable v-close-popup
                :disable="!props.row.factura"
                @click="imprimirFactura(props.row)"
              >
                <q-item-section avatar><q-icon name="print" color="red"/></q-item-section>
                <q-item-section>
                  Imprimir Factura
                  <q-item-label v-if="!props.row.factura" caption>Sin factura emitida</q-item-label>
                </q-item-section>
              </q-item>
              <q-item
                clickable v-close-popup
                :disable="!props.row.factura"
                type="a" target="_blank"
                :href="props.row.factura ? props.row.factura.siat : undefined"
              >
                <q-item-section avatar><q-icon name="verified" color="blue"/></q-item-section>
                <q-item-section>Ver en Impuestos</q-item-section>
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

    <!--
      Boleta de entrega: replica la que se imprime en papel, con los mismos
      campos y en el mismo orden, para poder cotejarla contra el talonario.
    -->
    <q-dialog v-model="dialogDetalle">
      <q-card style="min-width: 340px; max-width: 900px; width: 100%">
        <q-card-section class="bg-primary text-white q-py-sm row items-center">
          <div class="col">
            <div class="text-subtitle1 text-weight-bold">ALMACEN SOFIA</div>
            <div class="text-caption">Boleta de entrega</div>
          </div>
          <div class="col-auto text-right">
            <div class="text-caption">Nro Pedido</div>
            <div class="text-subtitle1 text-weight-bold">{{ ventaSel.comanda }}</div>
          </div>
        </q-card-section>

        <q-card-section v-if="loadingDetalle" class="text-center q-pa-lg">
          <q-spinner color="primary" size="28px" class="q-mr-sm"/> Cargando…
        </q-card-section>

        <template v-else>
          <q-card-section class="q-py-sm boleta-cab">
            <div class="row q-col-gutter-x-md q-col-gutter-y-xs">
              <div class="col-12 col-sm-6"><b>CI/NIT:</b> {{ cab.nit || '—' }}</div>
              <div class="col-6 col-sm-3"><b>Telf.:</b> {{ cab.telefono || '—' }}</div>
              <div class="col-6 col-sm-3"><b>F. Emisión:</b> {{ fechaCorta(cab.fechaEmision || ventaSel.fecha) }}</div>

              <div class="col-12 col-sm-8">
                <b>Cliente:</b> {{ cab.cliente || sinCliente(boleta) }}
                <span v-if="cab.origen" class="text-grey-7">({{ origenLabel(cab) }})</span>
              </div>
              <div class="col-6 col-sm-2"><b>Zona:</b> {{ cab.zona || '—' }}</div>
              <div class="col-6 col-sm-2"><b>Territorio:</b> {{ cab.territorio || '—' }}</div>

              <div class="col-12 col-sm-8"><b>Dirección:</b> {{ cab.direccion || '—' }}</div>
              <div class="col-12 col-sm-4">
                <b>Lat/Lon:</b>
                <a
                  v-if="cab.lat && cab.lng"
                  :href="'https://maps.google.com/?q=' + cab.lat + ',' + cab.lng"
                  target="_blank" rel="noopener"
                >{{ cab.lat }}, {{ cab.lng }}</a>
                <span v-else>—</span>
              </div>

              <div class="col-12 col-sm-8"><b>Vendedor:</b> {{ cab.vendedor || '—' }}</div>
              <div class="col-12 col-sm-4"><b>Entrega:</b> {{ cab.estado || 'Sin entrega registrada' }}</div>
            </div>
          </q-card-section>

          <q-separator/>

          <q-card-section class="q-pa-none">
            <div class="scroll" style="max-height: 45vh">
              <q-markup-table dense flat wrap-cells>
                <thead>
                <tr class="bg-grey-3">
                  <th class="text-right">CANT</th>
                  <th class="text-left">CODIGO</th>
                  <th class="text-left">CONCEPTO</th>
                  <th class="text-center">UNID</th>
                  <th class="text-right">CJS</th>
                  <th class="text-right">P. NETO</th>
                  <th class="text-right">P. UNIT</th>
                  <th class="text-right">TOTAL</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(d, i) in detalle" :key="i">
                  <td class="text-right">{{ money(d.cant) }}</td>
                  <td class="text-left">{{ d.cod_prod }}</td>
                  <td class="text-left">{{ d.producto }}</td>
                  <td class="text-center">{{ d.unidad || '—' }}</td>
                  <td class="text-right">{{ Number(d.cajas || 0) }}</td>
                  <td class="text-right">{{ money(d.cantidad) }}</td>
                  <td class="text-right">{{ money(d.precio) }}</td>
                  <td class="text-right text-weight-bold">{{ money(d.subtotal) }}</td>
                </tr>
                <tr v-if="detalle.length === 0">
                  <td colspan="8" class="text-center text-grey q-pa-md">Sin productos</td>
                </tr>
                </tbody>
              </q-markup-table>
            </div>
          </q-card-section>

          <q-separator/>

          <q-card-section class="q-py-sm boleta-cab">
            <div class="row q-col-gutter-md items-start">
              <div class="col-12 col-sm">
                <div><b>LITERAL:</b> {{ boleta.literal || '—' }}</div>
                <div><b>PLACA Y DESTINO:</b> {{ cab.placa || cab.despachador || '—' }}</div>
                <div><b>TIPO DE PAGO:</b> {{ cab.tipago || '—' }}</div>
                <div><b>OBS.:</b> {{ cab.observacion || '' }}</div>
              </div>
              <div class="col-12 col-sm-auto" style="min-width: 220px">
                <div class="row justify-between"><span>SUB. TOT Bs.</span><b>{{ money(subTotalDetalle) }}</b></div>
                <div class="row justify-between"><span>DESCT. Bs.</span><b>{{ money(descuentoDetalle) }}</b></div>
                <q-separator class="q-my-xs"/>
                <div class="row justify-between text-subtitle1">
                  <b>TOTAL Bs.</b><b>{{ money(totalDetalle) }}</b>
                </div>
              </div>
            </div>
          </q-card-section>
        </template>

        <q-separator/>
        <q-card-actions align="right" class="q-px-md">
          <q-btn flat no-caps label="Cerrar" color="primary" v-close-popup/>
        </q-card-actions>
      </q-card>
    </q-dialog>

  </q-page>
</template>

<script>
import { date } from 'quasar'

// El rango de fechas es obligatorio en la practica: sin el, agrupar las
// ~388.000 filas de tbventas por comanda tarda varios segundos.
// Por defecto se abre en el dia de hoy; el rango se amplia a mano.
function filtrosPorDefecto () {
  const hoy = new Date()
  return {
    desde: date.formatDate(hoy, 'YYYY-MM-DD'),
    hasta: date.formatDate(hoy, 'YYYY-MM-DD'),
    search: '',
    producto: '',
    vendedor: null,
    tipo: null,
    estado: null,
    documento: null
  }
}

export default {
  name: 'VentasLista',
  data () {
    return {
      ventas: [],
      comandaInfo: null,
      detalle: [],
      boleta: {},
      ventaSel: {},
      dialogDetalle: false,
      loading: false,
      loadingDetalle: false,
      imprimiendo: null,
      resumen: { ventas: 0, items: 0, total: 0 },
      opciones: { vendedores: [], tipos: [], estados: [] },
      // Lo que ve el desplegable de vendedores mientras se escribe.
      vendedoresFiltrados: [],
      filtros: filtrosPorDefecto(),
      pagination: {
        sortBy: 'fecha',
        descending: true,
        page: 1,
        rowsPerPage: 20,
        rowsNumber: 0
      },
      columns: [
        // Primera columna: con la tabla ancha, al final quedaban fuera de pantalla.
        { name: 'acciones', label: 'Opciones', field: 'acciones', align: 'left' },
        { name: 'factura', label: 'Documento', field: 'factura', align: 'center' },
        { name: 'comanda', label: 'Comanda', field: 'comanda', align: 'left', sortable: true },
        { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left', sortable: true },
        { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
        { name: 'zona', label: 'Zona', field: 'zona', align: 'left', sortable: true },
        { name: 'vendedor', label: 'Vendedor', field: 'vendedor', align: 'left', sortable: true },
        { name: 'despachador', label: 'Camión', field: 'despachador', align: 'left' },
        { name: 'tipago', label: 'Pago', field: 'tipago', align: 'center' },
        { name: 'estado', label: 'Entrega', field: 'estado', align: 'center', sortable: true },
        { name: 'items', label: 'Ítems', field: 'items', align: 'right', sortable: true },
        { name: 'total', label: 'Total Bs.', field: 'total', align: 'right', sortable: true, format: v => Number(v || 0).toFixed(2) }
      ]
    }
  },
  created () {
    this.cargarOpciones()
    this.onRequest({ pagination: this.pagination })
  },
  computed: {
    // La fila de la tabla ya trae cliente, zona y vendedor; la entrega del
    // detalle agrega telefono, territorio, coordenadas y observacion. Se
    // combinan para que la cabecera salga completa aunque una de las dos
    // venga a medias (las comandas de mostrador no tienen entrega).
    cab () {
      const cabecera = { ...(this.ventaSel || {}) }
      const entrega = this.boleta.entrega || {}

      Object.keys(entrega).forEach(k => {
        if (entrega[k] !== null && entrega[k] !== undefined && entrega[k] !== '') {
          cabecera[k] = entrega[k]
        }
      })

      return cabecera
    },
    subTotalDetalle () {
      return this.detalle.reduce((a, d) => a + Number(d.subtotal || 0), 0)
    },
    descuentoDetalle () {
      return this.detalle.reduce((a, d) => a + Number(d.descuento || 0), 0)
    },
    totalDetalle () {
      return this.subTotalDetalle - this.descuentoDetalle
    }
  },
  methods: {
    money (v) {
      return Number(v || 0).toFixed(2)
    },
    // La comanda no paso por entregas: se avisa de donde salio el nombre para
    // no hacerlo pasar por un reparto que no existio.
    origenLabel (row) {
      const origen = row.origenCliente || row.origen
      if (origen === 'CREDITO') {
        return 'según cuenta por cobrar'
      }
      if (origen === 'REFERENCIA') {
        return 'según comanda ' + (row.comandaRef || '')
      }
      return 'según factura'
    },

    // Cuando no se pudo resolver el comprador se dice qué es la comanda, en
    // vez de dejarla en blanco. Un adelanto (producto F113) es un cobro en
    // caja contra otra venta: aunque esa otra tampoco tenga nombre, saber su
    // número deja seguir el rastro a mano.
    sinCliente (row) {
      const ref = Number(row.comandaRef) || 0

      if (Number(row.adelanto)) {
        return ref ? 'Adelanto de la comanda #' + ref : 'Adelanto de comanda'
      }
      if (ref) {
        return 'Relacionada con la comanda #' + ref
      }
      return 'Venta de mostrador sin identificar'
    },
    fechaCorta (v) {
      if (!v) {
        return '—'
      }
      // Laravel devuelve 'YYYY-MM-DD HH:mm:ss'; Safari no lo parsea con guiones.
      return date.formatDate(String(v).replace(' ', 'T'), 'DD/MM/YYYY')
    },
    params () {
      return {
        desde: this.filtros.desde || '',
        hasta: this.filtros.hasta || '',
        search: this.filtros.search || '',
        producto: this.filtros.producto || '',
        vendedor: this.filtros.vendedor || '',
        tipo: this.filtros.tipo || '',
        estado: this.filtros.estado || '',
        documento: this.filtros.documento || ''
      }
    },
    cargarOpciones () {
      this.$api.get('ventas/filtros').then(res => {
        this.opciones.vendedores = res.data.vendedores || []
        this.vendedoresFiltrados = this.opciones.vendedores
        this.opciones.tipos = res.data.tipos || []
        this.opciones.estados = res.data.estados || []
      }).catch(() => {
        // Sin opciones los selects quedan vacíos, el resto de filtros funciona.
      })
    },
    // El select de vendedor se escribe: filtra por cualquier parte del nombre,
    // no solo por como empieza (se busca igual por apellido).
    filtrarVendedores (texto, update) {
      update(() => {
        const busca = (texto || '').toLowerCase().trim()

        this.vendedoresFiltrados = busca === ''
          ? this.opciones.vendedores
          : this.opciones.vendedores.filter(v => String(v.label).toLowerCase().includes(busca))
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
        // Con "Todos" (0) el backend responde con su tope real; hay que
        // conservar el 0 o el select se quedaria sin opcion seleccionada.
        this.pagination.rowsPerPage = rowsPerPage === 0 ? 0 : Number(res.data.per_page)
        this.pagination.rowsNumber = res.data.total
        this.pagination.sortBy = sortBy
        this.pagination.descending = descending

        if (rowsPerPage === 0 && res.data.total > res.data.data.length) {
          this.$q.notify({
            message: `Se muestran ${res.data.data.length} de ${res.data.total} comandas: es el máximo por consulta. Acota el rango de fechas para verlas todas.`,
            color: 'warning',
            icon: 'info',
            position: 'top',
            timeout: 6000
          })
        }
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

      this.buscarComanda()
    },

    // Si lo buscado es un numero puede ser una comanda de otro dia, que la
    // tabla no mostraria por el rango de fechas. El 404 es lo normal (el
    // numero era otra cosa o no existe), asi que no se notifica.
    buscarComanda () {
      const numero = String(this.filtros.search || '').trim()
      this.comandaInfo = null

      if (!/^\d+$/.test(numero)) {
        return
      }

      this.$api.get('ventas/comanda/' + numero)
        .then(res => { this.comandaInfo = res.data })
        .catch(() => { this.comandaInfo = null })
    },

    verDetalle (row) {
      this.ventaSel = row
      this.detalle = []
      this.boleta = {}
      this.dialogDetalle = true
      this.loadingDetalle = true

      this.$api.get('ventas/comanda/' + row.comanda)
        .then(res => {
          this.boleta = res.data
          this.detalle = res.data.detalle || []
        })
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

    // El chip ya trae el CodAut de la factura que le corresponde a la comanda,
    // asi que se imprime por ese id: no hay nada que deducir.
    imprimirFactura (row) {
      if (!row.factura) {
        this.$q.notify({
          message: 'Esta venta no tiene factura emitida',
          color: 'warning',
          icon: 'info',
          position: 'top'
        })
        return
      }

      this.imprimiendo = row.comanda

      this.$api.get('facturas/' + row.factura.CodAut + '/pdf', {
        responseType: 'blob',
        headers: { Accept: 'application/pdf' }
      }).then(res => {
        this.abrirBlobPdf(res.data, `factura_${row.factura.nrofac}.pdf`)
      }).catch(async err => {
        this.$q.notify({
          message: await this.mensajeDeError(err, 'No se pudo imprimir la factura ' + row.factura.nrofac),
          color: 'negative',
          icon: 'error',
          position: 'top'
        })
      }).finally(() => { this.imprimiendo = null })
    },

    imprimirFacturaComanda () {
      this.imprimirFactura(this.comandaInfo)
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

<style scoped>
/* Cabecera y pie de la boleta: texto compacto, como el impreso. */
.boleta-cab {
  font-size: 12px;
  line-height: 1.5;
}
</style>
