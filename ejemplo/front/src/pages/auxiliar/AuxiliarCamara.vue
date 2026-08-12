<template>
  <q-page class="q-pa-sm auxiliar-page">
    <q-card flat bordered class="q-mb-sm">
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="col-12 col-sm-6 col-md-2">
          <q-input v-model="fecha" dense outlined type="date" label="Fecha" />
        </div>
        <div class="col-12 col-sm-6 col-md-4">
          <q-btn color="primary" icon="search" label="Consultar pedidos" no-caps class="full-width" :loading="loading" @click="importarPedidos" />
        </div>
        <div class="col-12 col-sm-12 col-md-4">
          <q-btn-dropdown color="deep-orange" icon="print" label="Reporte" no-caps class="full-width" :loading="loadingReport">
            <q-list style="min-width: 260px">
              <q-item clickable v-close-popup @click="reportePedidos">
                <q-item-section avatar><q-icon name="description" color="indigo" /></q-item-section>
                <q-item-section>Pedidos</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="openReporteCamionDialog">
                <q-item-section avatar><q-icon name="local_shipping" color="blue-8" /></q-item-section>
                <q-item-section>Pedidos por camion</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="reporteVentasGeneradas">
                <q-item-section avatar><q-icon name="receipt_long" color="secondary" /></q-item-section>
                <q-item-section>Ventas generales</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="reporteProductosTotalesConClientes">
                <q-item-section avatar><q-icon name="groups" color="deep-orange" /></q-item-section>
                <q-item-section>Productos totales con clientes</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="reporteProductosTotalesSinClientes">
                <q-item-section avatar><q-icon name="inventory_2" color="brown" /></q-item-section>
                <q-item-section>Productos totales sin clientes</q-item-section>
              </q-item>
            </q-list>
          </q-btn-dropdown>
        </div>
      </q-card-section>
<!--      <q-card-section class="q-pt-none text-caption text-grey-7">-->
<!--        Ultima actualizacion: {{ lastSync || 'sin datos' }}-->
<!--      </q-card-section>-->
<!--      <q-card-section class="row q-col-gutter-sm q-pt-none">-->
<!--        <div class="col-6 col-md-2"><q-chip color="blue-8" text-color="white">Pedidos: {{ stats.total_pedidos || 0 }}</q-chip></div>-->
<!--        <div class="col-6 col-md-2"><q-chip color="teal-7" text-color="white">Bs: {{ Number(stats.total_bs || 0).toFixed(2) }}</q-chip></div>-->
<!--        <div class="col-6 col-md-2"><q-chip color="orange-8" text-color="white">Pendientes: {{ stats.pendientes || 0 }}</q-chip></div>-->
<!--        <div class="col-6 col-md-2"><q-chip color="green-8" text-color="white">Hechos: {{ stats.hechos || 0 }}</q-chip></div>-->
<!--        <div class="col-6 col-md-2"><q-chip color="purple-7" text-color="white">Modificados: {{ stats.modificados || 0 }}</q-chip></div>-->
<!--      </q-card-section>-->
    </q-card>

    <q-card flat bordered class="q-mb-sm">
      <q-card-section class="row q-col-gutter-sm">
        <div class="col-12 col-md-2">
          <q-select v-model="tipo" :options="tipoOptions" dense outlined emit-value map-options label="Tipo" />
        </div>
        <div class="col-12 col-md-3">
          <q-select v-model="camionId" :options="camionOptions" dense outlined emit-value map-options label="Camion" />
        </div>
<!--        <div class="col-12 col-md-2">-->
<!--          <q-select v-model="auxiliarEstado" :options="auxEstadoOptions" dense outlined emit-value map-options label="Estado auxiliar" />-->
<!--        </div>-->
<!--        <div class="col-12 col-md-2">-->
<!--          <q-select v-model="clienteId" :options="clienteOptions" dense outlined emit-value map-options label="Cliente" />-->
<!--        </div>-->
<!--        <div class="col-12 col-md-2">-->
<!--          <q-select v-model="vendedorId" :options="vendedorOptions" dense outlined emit-value map-options label="Vendedor" />-->
<!--        </div>-->
<!--        <div class="col-12 col-md-2">-->
<!--          <q-select v-model="camionId" :options="camionOptions" dense outlined emit-value map-options label="Camion" />-->
<!--        </div>-->
<!--        <div class="col-12 col-md-2">-->
<!--          <q-select v-model="zonaId" :options="zonaOptions" dense outlined emit-value map-options label="Zona" />-->
<!--        </div>-->
        <div class="col-12 col-md-5">
          <q-input v-model="search" dense outlined debounce="300" label="Buscar cliente / direccion / pedido">
            <template #append><q-icon name="search" /></template>
          </q-input>
        </div>
      </q-card-section>
    </q-card>

    <q-card flat bordered>
      <q-card-section class="text-subtitle2">
        Pedidos: {{ rows.length }}
      </q-card-section>
      <q-separator />
      <q-list separator>
        <q-expansion-item
          v-for="pedido in rows"
          :key="pedido.id"
          expand-separator
          dense
        >
          <template #header>
            <q-item-section>
              <q-item-label class="text-weight-medium">
                #{{ pedido.id }} - {{ pedido.cliente?.nombre || 'SIN CLIENTE' }}
              </q-item-label>
              <q-item-label caption>
                {{ pedido.vendedor?.name || '-' }} | {{ pedido.camion?.name || 'SIN CAMION' }} | {{ pedido.zona?.nombre || 'SIN ZONA' }}
              </q-item-label>
            </q-item-section>
            <q-item-section side top>
              <div class="row no-wrap items-center q-gutter-xs">
                <q-chip dense color="blue-grey-7" text-color="white">
                  Bs {{ Number(pedido.total || 0).toFixed(2) }}
                </q-chip>
                <q-chip dense :color="chipEstadoColor(pedido.auxiliar_estado)" text-color="white">
                  {{ pedido.auxiliar_estado || 'PENDIENTE' }}
                </q-chip>
              </div>
            </q-item-section>
          </template>
          <q-card flat>
            <q-card-section class="row q-col-gutter-sm">
              <div class="col-12 col-md-2">
                <q-chip :color="chipEstadoColor(pedido.auxiliar_estado)" text-color="white">
                  {{ pedido.auxiliar_estado || 'PENDIENTE' }}
                </q-chip>
              </div>
              <div class="col-12 col-md-2 text-weight-bold">
                Total: {{ Number(pedido.total || 0).toFixed(2) }} Bs
              </div>
              <div class="col-12 col-md-4 text-grey-8">
                {{ pedido.cliente?.direccion || '-' }}
              </div>
              <div class="col-12 col-md-4 text-grey-8">
                Venta: {{ pedido.venta_id ? `#${pedido.venta_id}` : 'No generada' }}
              </div>
            </q-card-section>

            <q-table
              dense
              flat
              bordered
              :rows="pedido.detalles || []"
              :columns="detalleColumns"
              row-key="id"
              :pagination="{ rowsPerPage: 0 }"
            >
              <template #body-cell-imagen="props">
                <q-td :props="props">
                  <q-avatar size="30px" rounded>
                    <q-img v-if="props.row.imagen" :src="imgUrl(props.row.imagen)" />
                    <span v-else>-</span>
                  </q-avatar>
                </q-td>
              </template>
              <template #body-cell-producto="props">
                <q-td :props="props">
                  <div class="detalle-producto-wrap">
                    <div class="row items-center q-gutter-xs q-mb-xs">
                      <q-chip dense square size="sm" :color="unidadColor(props.row.codigo_unidad)" text-color="white">
                        {{ unidadLabel(props.row.codigo_unidad) }}
                      </q-chip>
                      <q-chip dense square size="sm" color="primary" text-color="white">
                        Cant {{ Number(getEditCantidad(pedido.id, props.row) || 0).toFixed(2) }}
                      </q-chip>
                      <q-chip dense square size="sm" color="indigo" text-color="white">
                        Bs {{ Number(getEditPrecio(pedido.id, props.row) || 0).toFixed(2) }}
                      </q-chip>
                      <q-chip dense square size="sm" color="grey-8" text-color="white">
                        Peso {{ Number(getEditPesoEstimado(pedido.id, props.row) || 1).toFixed(3) }}
                      </q-chip>
                    </div>
                    <div class="row items-center q-gutter-xs">
                      <span class="text-weight-medium">{{ props.row.producto || '-' }}</span>
                      <q-chip dense square size="sm" :color="tipoColor(props.row.tipo)" text-color="white">
                        {{ tipoLabel(props.row.tipo) }}
                      </q-chip>
                    </div>
                    <div v-if="getDetalleComentario(props.row)" class="text-caption text-grey-7 q-mt-xs">
                      Comentario: {{ getDetalleComentario(props.row) }}
                    </div>
                  </div>
                </q-td>
              </template>
              <template #body-cell-cantidad="props">
                <q-td :props="props">
                  <input
                    :value="getEditCantidad(pedido.id, props.row)"
                    type="number"
                    step="0.01"
                    min="0"
                    class="edit-input-native"
                    :disabled="isLocked(pedido)"
                    @input="(e) => setEditCantidad(pedido.id, props.row.id, e.target.value)"
                  />
                </q-td>
              </template>
              <template #body-cell-precio="props">
                <q-td :props="props">
                  <input
                    :value="getEditPrecio(pedido.id, props.row)"
                    type="number"
                    step="0.01"
                    min="0"
                    class="edit-input-native"
                    :disabled="isLocked(pedido)"
                    @input="(e) => setEditPrecio(pedido.id, props.row.id, e.target.value)"
                  />
                </q-td>
              </template>
              <template #body-cell-peso_estimado="props">
                <q-td :props="props">
                  <input
                    :value="getEditPesoEstimado(pedido.id, props.row)"
                    type="number"
                    step="0.001"
                    min="0"
                    class="edit-input-native"
                    :disabled="isLocked(pedido)"
                    @input="(e) => setEditPesoEstimado(pedido.id, props.row.id, e.target.value)"
                  />
                </q-td>
              </template>
              <template #body-cell-total="props">
                <q-td :props="props">
                  {{ Number(lineTotal(pedido.id, props.row)).toFixed(2) }}
                </q-td>
              </template>
            </q-table>

            <q-card-section class="row q-col-gutter-sm items-center">
              <div class="col-12 col-md-6">
                <q-input
                  v-model="observacionesAux[pedido.id]"
                  dense
                  outlined
                  label="Observacion auxiliar"
                  maxlength="600"
                  :disable="isLocked(pedido)"
                />
              </div>
              <div class="col-12 col-md-6">
                <q-btn
                  v-if="!pedido.venta_generada"
                  color="green"
                  icon="check_circle"
                  label="Hecho + generar venta"
                  no-caps
                  class="full-width"
                  :loading="processingId === pedido.id"
                  @click="marcarHecho(pedido)"
                />
                <q-btn
                  v-else-if="isLocked(pedido)"
                  color="amber-8"
                  text-color="black"
                  icon="lock_open"
                  label="Volver a modificar"
                  no-caps
                  class="full-width"
                  @click="habilitarEdicion(pedido)"
                />
                <q-btn
                  v-else
                  color="primary"
                  icon="save"
                  label="Guardar cambios"
                  no-caps
                  class="full-width"
                  :loading="processingId === pedido.id"
                  @click="guardarCambios(pedido)"
                />
              </div>
            </q-card-section>
          </q-card>
        </q-expansion-item>
      </q-list>
    </q-card>

    <q-dialog v-model="showReportCamionDialog">
      <q-card style="width: 420px; max-width: 95vw">
        <q-card-section class="text-subtitle1 text-weight-medium">
          Reporte pedidos por camion
        </q-card-section>
        <q-card-section>
          <q-select
            v-model="reportCamionId"
            :options="camionReportOptions"
            dense
            outlined
            emit-value
            map-options
            label="Camion"
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
          <q-btn color="primary" no-caps label="Generar PDF" :loading="loadingReport" @click="reportePedidosCamionConfirm" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, ref, watch } from 'vue'

const { proxy } = getCurrentInstance()

const fecha = ref(new Date().toISOString().slice(0, 10))
const loading = ref(false)
const loadingReport = ref(false)
const processingId = ref(null)
const lastSync = ref('')

const rows = ref([])
const stats = ref({})
const editCantidad = ref({})
const editPrecio = ref({})
const editPesoEstimado = ref({})
const observacionesAux = ref({})
const unlocked = ref({})

const search = ref('')
const tipo = ref('TODOS')
const auxiliarEstado = ref('TODOS')
const clienteId = ref(null)
const vendedorId = ref(null)
const camionId = ref(null)
const zonaId = ref(null)
const showReportCamionDialog = ref(false)
const reportCamionId = ref(null)

const tipoOptions = computed(() => {
  const base = Array.isArray(proxy?.$tiposProducto) && proxy.$tiposProducto.length
    ? proxy.$tiposProducto
    : ['embutido', 'huevo', 'pet', 'pollo', 'cerdo', 'res']
  const unique = [...new Set(base.map(t => normalizeTipo(t)).filter(Boolean))]
  return [
    { label: 'Todos', value: 'TODOS' },
    ...unique.map(value => ({ label: tipoLabel(value), value })),
  ]
})
const auxEstadoOptions = [
  { label: 'Todos', value: 'TODOS' },
  { label: 'Pendiente', value: 'PENDIENTE' },
  { label: 'Hecho', value: 'HECHO' },
  { label: 'Modificado', value: 'MODIFICADO' },
]

const detalleColumns = [
  { name: 'cantidad', label: 'Cantidad', field: 'cantidad', align: 'left' },
  { name: 'precio', label: 'Precio', field: row => Number(row.precio || 0).toFixed(2), align: 'right' },
  { name: 'peso_estimado', label: 'Peso est.', field: row => Number(row.peso_estimado || 1).toFixed(3), align: 'right' },
  { name: 'total', label: 'Subtotal', field: row => Number(row.total || 0).toFixed(2), align: 'right' },
  { name: 'imagen', label: 'Img', field: 'imagen', align: 'left' },
  { name: 'codigo', label: 'Codigo', field: 'codigo', align: 'left' },
  { name: 'producto', label: 'Producto', field: 'producto', align: 'left' },
]

const clienteOptions = computed(() => {
  const map = new Map()
  rows.value.forEach((r) => {
    if (r?.cliente?.id) map.set(r.cliente.id, r.cliente.nombre || `Cliente ${r.cliente.id}`)
  })
  return [{ label: 'Todos', value: null }, ...Array.from(map.entries()).map(([id, label]) => ({ label, value: id }))]
})

const vendedorOptions = computed(() => {
  const map = new Map()
  rows.value.forEach((r) => {
    if (r?.vendedor?.id) map.set(r.vendedor.id, r.vendedor.name || `Vendedor ${r.vendedor.id}`)
  })
  return [{ label: 'Todos', value: null }, ...Array.from(map.entries()).map(([id, label]) => ({ label, value: id }))]
})

const camionOptions = computed(() => {
  const map = new Map()
  rows.value.forEach((r) => {
    if (r?.camion?.id) {
      const extra = r.camion.placa ? ` (${r.camion.placa})` : ''
      map.set(r.camion.id, `${r.camion.name || `Camion ${r.camion.id}`}${extra}`)
    }
  })
  return [{ label: 'Todos', value: null }, ...Array.from(map.entries()).map(([id, label]) => ({ label, value: id }))]
})
const camionReportOptions = computed(() => camionOptions.value.filter(opt => opt.value !== null))

const zonaOptions = computed(() => {
  const map = new Map()
  rows.value.forEach((r) => {
    if (r?.zona?.id) map.set(r.zona.id, r.zona.nombre || `Zona ${r.zona.id}`)
  })
  return [{ label: 'Todas', value: null }, ...Array.from(map.entries()).map(([id, label]) => ({ label, value: id }))]
})

function imgUrl (path) {
  if (!path) return ''
  const base = String(proxy.$url || '').replace(/\/+$/, '')
  return `${base}/../${String(path).replace(/^\/+/, '')}`
}

function chipEstadoColor (estado) {
  if (estado === 'HECHO') return 'green'
  if (estado === 'MODIFICADO') return 'purple'
  return 'orange'
}

function normalizeTipo (tipoValue) {
  const value = String(tipoValue || 'EMBUTIDO').trim().toUpperCase()
  if (!value || value === 'NORMAL') return 'EMBUTIDO'
  return value
}

function tipoColor (tipo) {
  const value = normalizeTipo(tipo)
  if (value === 'HUEVO') return 'amber-8'
  if (value === 'PET') return 'blue-grey'
  if (value === 'POLLO') return 'orange'
  if (value === 'RES') return 'red'
  if (value === 'CERDO') return 'brown'
  return 'green-7'
}

function tipoLabel (tipo) {
  const value = normalizeTipo(tipo)
  if (value === 'HUEVO') return 'Huevo'
  if (value === 'PET') return 'Pet'
  if (value === 'POLLO') return 'Pollo'
  if (value === 'RES') return 'Res'
  if (value === 'CERDO') return 'Cerdo'
  return 'Embutido'
}

function unidadLabel (codigoUnidad) {
  const value = String(codigoUnidad || '').trim().toUpperCase()
  if (value === 'KG') return 'Kilogramo'
  if (value === 'U' || value === 'UNIDA') return 'Unidad'
  return value || 'Unidad'
}

function unidadColor (codigoUnidad) {
  const value = String(codigoUnidad || '').trim().toUpperCase()
  if (value === 'KG') return 'teal'
  if (value === 'U' || value === 'UNIDA') return 'deep-orange'
  return 'grey-7'
}

function syncEditMaps () {
  const nextQty = {}
  const nextPrecio = {}
  const nextPeso = {}
  const nextObs = {}
  rows.value.forEach((pedido) => {
    nextQty[pedido.id] = {}
    nextPrecio[pedido.id] = {}
    nextPeso[pedido.id] = {}
    ;(pedido.detalles || []).forEach((d) => {
      nextQty[pedido.id][d.id] = d.cantidad
      nextPrecio[pedido.id][d.id] = d.precio
      const peso = Number(d.peso_estimado)
      nextPeso[pedido.id][d.id] = Number.isFinite(peso) && peso > 0 ? peso : 1
    })
    nextObs[pedido.id] = pedido.auxiliar_observacion || ''
  })
  editCantidad.value = nextQty
  editPrecio.value = nextPrecio
  editPesoEstimado.value = nextPeso
  observacionesAux.value = nextObs
  unlocked.value = {}
}

function getEditCantidad (pedidoId, detalle) {
  return editCantidad.value?.[pedidoId]?.[detalle.id] ?? detalle.cantidad
}

function setEditCantidad (pedidoId, detalleId, value) {
  if (!editCantidad.value[pedidoId]) editCantidad.value[pedidoId] = {}
  const num = Number(value)
  editCantidad.value[pedidoId][detalleId] = Number.isFinite(num) && num >= 0 ? num : 0
}

function getEditPrecio (pedidoId, detalle) {
  return editPrecio.value?.[pedidoId]?.[detalle.id] ?? detalle.precio
}

function setEditPrecio (pedidoId, detalleId, value) {
  if (!editPrecio.value[pedidoId]) editPrecio.value[pedidoId] = {}
  const num = Number(value)
  editPrecio.value[pedidoId][detalleId] = Number.isFinite(num) && num >= 0 ? num : 0
}

function getEditPesoEstimado (pedidoId, detalle) {
  const edited = editPesoEstimado.value?.[pedidoId]?.[detalle.id]
  if (edited !== undefined && edited !== null && edited !== '') return edited
  const fromRow = Number(detalle.peso_estimado)
  return Number.isFinite(fromRow) && fromRow > 0 ? fromRow : 1
}

function setEditPesoEstimado (pedidoId, detalleId, value) {
  if (!editPesoEstimado.value[pedidoId]) editPesoEstimado.value[pedidoId] = {}
  const num = Number(value)
  editPesoEstimado.value[pedidoId][detalleId] = Number.isFinite(num) && num > 0 ? num : 1
}

function lineTotal (pedidoId, detalle) {
  const cantidad = Number(getEditCantidad(pedidoId, detalle) || 0)
  const precio = Number(getEditPrecio(pedidoId, detalle) || 0)
  const peso = Number(getEditPesoEstimado(pedidoId, detalle) || 1)
  return cantidad * precio * (peso > 0 ? peso : 1)
}

function getDetalleComentario (detalle) {
  const directo = String(detalle?.observacion_detalle || '').trim()
  if (directo) return directo
  const extra = detalle?.detalle_extra
  const fromExtra = typeof extra === 'object' && extra !== null ? String(extra.observacion || '').trim() : ''
  return fromExtra
}

function isLocked (pedido) {
  return !!pedido?.venta_generada && !unlocked.value[pedido.id]
}

function habilitarEdicion (pedido) {
  proxy.$alert.dialog('¿Seguro que quieres volver a modificar este pedido?')
    .onOk(() => {
      unlocked.value[pedido.id] = true
    })
}

function getRequestFilters () {
  return {
    fecha: fecha.value,
    tipo: tipo.value === 'TODOS' ? undefined : normalizeTipo(tipo.value),
    auxiliar_estado: auxiliarEstado.value,
    cliente_id: clienteId.value,
    vendedor_id: vendedorId.value,
    usuario_camion_id: camionId.value,
    pedido_zona_id: zonaId.value,
    search: search.value || undefined,
  }
}

async function importarPedidos () {
  loading.value = true
  try {
    const res = await proxy.$axios.get('/auxiliar-camara/pedidos', { params: getRequestFilters() })
    rows.value = Array.isArray(res.data?.data) ? res.data.data : []
    stats.value = res.data?.meta || {}
    lastSync.value = new Date().toLocaleString()
    syncEditMaps()
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo cargar pedidos')
  } finally {
    loading.value = false
  }
}

async function procesarPedido (pedido, generarVenta) {
  processingId.value = pedido.id
  try {
    const detallesPayload = (pedido.detalles || []).map((d) => ({
      id: d.id,
      cantidad: Number(getEditCantidad(pedido.id, d)),
      precio: Number(getEditPrecio(pedido.id, d)),
      peso_estimado: Number(getEditPesoEstimado(pedido.id, d) || 1),
    }))
    await proxy.$axios.put(`/auxiliar-camara/pedidos/${pedido.id}/procesar`, {
      generar_venta: generarVenta,
      auxiliar_observacion: observacionesAux.value[pedido.id] || null,
      detalles: detallesPayload,
    })
    await importarPedidos()
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo procesar pedido')
  } finally {
    processingId.value = null
  }
}

async function marcarHecho (pedido) {
  await procesarPedido(pedido, true)
}

async function guardarCambios (pedido) {
  await procesarPedido(pedido, false)
}

async function descargarPdf (url, fileName, extraParams = {}) {
  loadingReport.value = true
  try {
    const res = await proxy.$axios.get(url, {
      params: { ...getRequestFilters(), ...extraParams },
      responseType: 'blob',
    })
    const blob = new Blob([res.data], { type: 'application/pdf' })
    const d = res?.headers?.['content-disposition'] || ''
    const m = d.match(/filename="?([^"]+)"?/)
    const finalName = m?.[1] || fileName
    const fileUrl = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = fileUrl
    a.download = finalName
    document.body.appendChild(a)
    a.click()
    a.remove()
    window.URL.revokeObjectURL(fileUrl)
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo generar reporte')
  } finally {
    loadingReport.value = false
  }
}

async function reportePedidos () {
  await descargarPdf('/auxiliar-camara/reportes/pedidos', `auxiliar_pedidos_${fecha.value}.pdf`)
}

function openReporteCamionDialog () {
  const current = Number(camionId.value || 0)
  if (current > 0) {
    reportCamionId.value = current
  } else if (!reportCamionId.value && camionReportOptions.value.length > 0) {
    reportCamionId.value = camionReportOptions.value[0].value
  }
  showReportCamionDialog.value = true
}

async function reportePedidosCamionConfirm () {
  const selected = Number(reportCamionId.value || 0)
  if (selected <= 0) {
    proxy.$alert.error('Seleccione un camion para generar el reporte')
    return
  }
  await descargarPdf('/auxiliar-camara/reportes/pedidos-camion', `auxiliar_pedidos_camion_${selected}_${fecha.value}.pdf`, {
    usuario_camion_id: selected,
  })
  showReportCamionDialog.value = false
}

async function reporteProductosTotales (incluirClientes = true) {
  await descargarPdf('/auxiliar-camara/reportes/productos-totales', `auxiliar_productos_${incluirClientes ? 'con_clientes' : 'sin_clientes'}_${fecha.value}.pdf`, {
    incluir_clientes: incluirClientes ? 1 : 0,
  })
}

async function reporteProductosTotalesConClientes () {
  await reporteProductosTotales(true)
}

async function reporteProductosTotalesSinClientes () {
  await reporteProductosTotales(false)
}

async function reporteVentasGeneradas () {
  await descargarPdf('/auxiliar-camara/reportes/ventas-generadas', `auxiliar_ventas_generadas_${fecha.value}.pdf`)
}

let refreshTimer = null
watch([fecha, tipo, auxiliarEstado, clienteId, vendedorId, camionId, zonaId, search], () => {
  if (refreshTimer) clearTimeout(refreshTimer)
  refreshTimer = setTimeout(() => {
    importarPedidos()
  }, 280)
})

importarPedidos()
</script>

<style scoped>
.auxiliar-page {
  background: linear-gradient(180deg, #eef4ff 0%, #f8fbff 40%, #ffffff 100%);
}

.edit-input {
  width: 110px;
}

.edit-input-native {
  width: 62px;
  min-width: 62px;
  height: 28px;
  padding: 2px 6px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 12px;
  line-height: 1;
  outline: none;
  background: #fff;
}

.edit-input-native:focus {
  border-color: #1976d2;
  box-shadow: 0 0 0 1px rgba(25, 118, 210, 0.2);
}

.edit-input-native:disabled {
  background: #f3f4f6;
  color: #6b7280;
}

.detalle-producto-wrap {
  min-width: 200px;
  line-height: 1.1;
}

@media (max-width: 768px) {
  :deep(.q-btn-dropdown) {
    width: 100%;
  }

  .edit-input {
    width: 130px;
  }

  .edit-input-native {
    width: 54px;
    min-width: 54px;
    height: 26px;
    padding: 2px 4px;
    font-size: 11px;
  }

  .detalle-producto-wrap {
    min-width: 170px;
  }
}
</style>
