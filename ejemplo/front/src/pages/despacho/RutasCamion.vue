<template>
  <q-page class="q-pa-sm rutas-page">
    <q-card flat bordered class="q-mb-sm">
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="col-12 col-md-2">
          <q-input v-model="fecha" type="date" dense outlined label="Fecha" @update:model-value="loadRutas" />
        </div>
        <div class="col-12 col-md-6">
          <q-input v-model="search" dense outlined label="Buscar cliente/comanda/producto" debounce="250">
            <template #append><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-12 col-md-4">
          <q-btn color="primary" icon="search" no-caps class="full-width" label="Buscar" :loading="loading" @click="loadRutas" />
        </div>
      </q-card-section>
      <q-card-section class="row q-col-gutter-sm q-pt-none">
        <div class="col-6 col-md-3"><q-chip color="blue-8" text-color="white">Comandas: {{ stats.total_entregas || 0 }}</q-chip></div>
        <div class="col-6 col-md-3"><q-chip color="indigo-8" text-color="white">Total Bs: {{ Number(stats.monto_total || 0).toFixed(2) }}</q-chip></div>
        <div class="col-6 col-md-3"><q-chip color="green-8" text-color="white">Cobrado Bs: {{ Number(stats.monto_cobrado || 0).toFixed(2) }}</q-chip></div>
        <div class="col-6 col-md-3"><q-chip color="orange-8" text-color="white">Saldo Bs: {{ Number(stats.saldo_total || 0).toFixed(2) }}</q-chip></div>
      </q-card-section>
    </q-card>

    <q-card flat bordered class="q-mb-sm">
      <div ref="mapRef" class="map-container" />
    </q-card>

    <q-card flat bordered>
      <q-table
        dense
        flat
        row-key="cliente_key"
        :rows="rowsClientes"
        :columns="columns"
        :pagination="{ rowsPerPage: 0 }"
        @row-click="(_, row) => openClienteDialog(row)"
      >
        <template #body-cell-estado="props">
          <q-td :props="props">
            <q-chip dense :color="estadoColor(props.row.despacho_estado)" text-color="white">{{ props.row.despacho_estado }}</q-chip>
          </q-td>
        </template>
        <template #body-cell-total="props"><q-td :props="props">{{ money(props.row.total) }}</q-td></template>
        <template #body-cell-pagado="props"><q-td :props="props">{{ money(props.row.cobrado) }}</q-td></template>
        <template #body-cell-saldo="props"><q-td :props="props">{{ money(props.row.saldo) }}</q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialogCliente" full-width>
      <q-card style="max-width: 1280px; margin: 0 auto;">
        <q-card-section class="row items-center">
          <div class="col">
            <div class="text-subtitle1 text-weight-bold">{{ selectedCliente?.cliente || '-' }}</div>
            <div class="text-caption text-grey-7">{{ selectedCliente?.direccion || '-' }} · {{ selectedCliente?.telefono || '-' }}</div>
          </div>
          <q-btn color="blue-7" icon="map" no-caps label="Abrir mapa" @click="openGoogleMaps(selectedCliente)" />
        </q-card-section>
        <q-separator />
        <q-card-section>
          <div class="row q-col-gutter-sm q-mb-sm">
            <div class="col-12 col-md-3">
              <q-chip color="blue-8" text-color="white" class="full-width justify-center">Seleccionadas: {{ selectedPedidosCount }}</q-chip>
            </div>
            <div class="col-12 col-md-3">
              <q-chip color="indigo-8" text-color="white" class="full-width justify-center">Importe total: {{ money(totalSeleccionado) }}</q-chip>
            </div>
            <div class="col-12 col-md-3">
              <q-chip color="green-8" text-color="white" class="full-width justify-center">Cobro a registrar: {{ money(cobroSeleccionado) }}</q-chip>
            </div>
            <div class="col-12 col-md-3">
              <q-chip color="orange-8" text-color="white" class="full-width justify-center">Saldo: {{ money(saldoSeleccionado) }}</q-chip>
            </div>
          </div>

          <q-markup-table dense flat bordered wrap-cells class="dialog-table">
            <thead>
              <tr>
                <th style="width: 52px;">
                  <q-checkbox :model-value="allPedidosSelected" dense @update:model-value="toggleSelectAll" />
                </th>
                <th style="width: 70px;">Ver</th>
                <th>Comanda</th>
                <th>Importe</th>
                <th>Tipo pago</th>
                <th class="cobrado-col">Cobrado</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(p, idx) in dialogPedidos" :key="p.pedido_id">
                <tr>
                  <td class="text-center">
                    <q-checkbox v-model="p.selected" dense />
                  </td>
                  <td class="text-center">
                    <q-btn
                      dense
                      round
                      flat
                      color="primary"
                      :icon="expandedPedidos[p.pedido_id] ? 'visibility_off' : 'visibility'"
                      @click="toggleDetallePedido(p.pedido_id)"
                    />
                  </td>
                  <td>#{{ p.comanda }}</td>
                  <td>{{ money(p.total) }}</td>
                  <td>
                    <q-chip dense :color="String(p.tipo_pago_venta || '').toUpperCase().includes('CRED') ? 'orange-8' : 'red'" text-color="white">
                      {{ p.tipo_pago_venta || '-' }}
                    </q-chip>
                  </td>
                  <td class="cobrado-col">
                    <q-input
                      :ref="(el) => setPagoInputRef(el, idx)"
                      v-model.number="p.cobroMonto"
                      dense
                      outlined
                      type="number"
                      min="0"
                      step="0.01"
                      :disable="loadingRegister || !!actionLoading[p.pedido_id] || (p.bloqueado && !p.editando)"
                      @focus="p.selected = true"
                      @keyup.enter="registrarSeleccionados"
                    />
                    <div class="q-mt-xs">
                      <q-btn
                        v-if="p.bloqueado && !p.editando && p.puedeCorregir"
                        flat
                        dense
                        no-caps
                        color="primary"
                        label="Corregir"
                        @click="habilitarCorreccion(p)"
                      />
                      <span v-else-if="p.bloqueado && !p.puedeCorregir" class="text-caption text-grey-7">Corrección usada</span>
                    </div>
                  </td>
                </tr>
                <tr v-show="expandedPedidos[p.pedido_id]">
                  <td colspan="6" class="bg-grey-1">
                    <div class="detalle-head q-mb-xs">
                      <span><b>Importe:</b> {{ money(p.total) }}</span>
                      <span><b>Cobrado actual:</b> {{ money(p.cobrado) }}</span>
                      <span><b>Saldo:</b> {{ money(p.saldo) }}</span>
                      <span><b>Estado:</b> {{ p.despacho_estado }}</span>
                    </div>
                    <div v-for="(d, i) in (p.productos || [])" :key="`${p.pedido_id}-${i}`" class="producto-row">
                      <span class="prod-codigo">{{ d.codigo || '-' }}</span>
                      <span class="prod-nombre">{{ d.nombre || '-' }}</span>
                      <span class="prod-cantidad">Cant. {{ Number(d.cantidad || 0).toFixed(2) }}</span>
                    </div>
                    <div class="text-caption text-grey-7 q-mt-xs" v-if="!(p.productos || []).length">Sin productos</div>
                    <div class="q-mt-sm text-caption text-grey-7" v-if="(p.pagos || []).length">
                      Registros de cobro:
                      <span v-for="pg in (p.pagos || [])" :key="`pg-${p.pedido_id}-${pg.id}`" class="q-mr-sm">
                        #{{ pg.id }} {{ money(pg.monto) }}
                      </span>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </q-markup-table>
        </q-card-section>
        <q-separator />
        <q-card-actions align="right">
          <q-btn color="positive" no-caps label="Registrar" :loading="loadingRegister" @click="registrarSeleccionados" />
          <q-btn flat no-caps color="grey-8" label="Cerrar" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const { proxy } = getCurrentInstance()
const fecha = ref(new Date().toISOString().slice(0, 10))
const search = ref('')
const loading = ref(false)
const rows = ref([])
const stats = ref({})
const dialogCliente = ref(false)
const selectedCliente = ref(null)
const dialogPedidos = ref([])
const expandedPedidos = ref({})
const pagoInputRefs = ref({})
const actionLoading = ref({})
const loadingRegister = ref(false)

const mapRef = ref(null)
const map = ref(null)
const layer = ref(null)
let searchTimer = null

const columns = [
  { name: 'pedidos_count', label: 'Pedidos', field: 'pedidos_count', align: 'left' },
  { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left' },
  { name: 'tipo_pago_venta', label: 'Tipo pago', field: 'tipo_pago_venta', align: 'left' },
  { name: 'estado', label: 'Estado', field: 'despacho_estado', align: 'left' },
  { name: 'total', label: 'Total', field: 'total', align: 'right' },
  { name: 'pagado', label: 'Cobrado', field: 'cobrado', align: 'right' },
  { name: 'saldo', label: 'Saldo', field: 'saldo', align: 'right' },
]

const rowsClientes = computed(() => {
  const grouped = new Map()
  rows.value.forEach((row) => {
    const key = row?.cliente_id ? `cli-${row.cliente_id}` : `ped-${row.pedido_id}`
    const base = grouped.get(key) || {
      cliente_key: key,
      cliente_id: row.cliente_id,
      source_pedido_ids: [],
      cliente: row.cliente,
      telefono: row.telefono,
      direccion: row.direccion,
      latitud: row.latitud,
      longitud: row.longitud,
      pedidos_count: 0,
      total: 0,
      cobrado: 0,
      saldo: 0,
      tipo_pago_venta: row.tipo_pago_venta || '',
      despacho_estado: row.despacho_estado || 'PENDIENTE',
    }
    base.pedidos_count += 1
    base.source_pedido_ids.push(row.pedido_id)
    base.total += Number(row.total || 0)
    base.cobrado += Number(row.cobrado || 0)
    base.saldo += Number(row.saldo || 0)
    if (String(base.tipo_pago_venta || '').toUpperCase().includes('CRED') || String(row.tipo_pago_venta || '').toUpperCase().includes('CRED')) {
      base.tipo_pago_venta = 'MIXTO/CREDITO'
    }
    base.despacho_estado = mergeEstado(base.despacho_estado, row.despacho_estado)
    grouped.set(key, base)
  })
  return Array.from(grouped.values()).map((item) => ({
    ...item,
    total: Number(item.total.toFixed(2)),
    cobrado: Number(item.cobrado.toFixed(2)),
    saldo: Number(item.saldo.toFixed(2)),
  }))
})

const selectedPedidos = computed(() => dialogPedidos.value.filter((p) => !!p.selected))
const selectedPedidosCount = computed(() => selectedPedidos.value.length)
const allPedidosSelected = computed(() => dialogPedidos.value.length > 0 && dialogPedidos.value.every((p) => !!p.selected))
const totalSeleccionado = computed(() => selectedPedidos.value.reduce((acc, p) => acc + Number(p.total || 0), 0))
const cobroSeleccionado = computed(() => selectedPedidos.value.reduce((acc, p) => acc + Number(p.cobroMonto || 0), 0))
const saldoSeleccionado = computed(() => selectedPedidos.value.reduce((acc, p) => acc + Number(p.saldo || 0), 0))

function mergeEstado(a, b) {
  const rank = {
    PENDIENTE: 0,
    PARCIAL: 1,
    'NO ENTREGADO': 2,
    RECHAZADO: 3,
    ENTREGADO: 4,
  }
  const ea = String(a || 'PENDIENTE').toUpperCase()
  const eb = String(b || 'PENDIENTE').toUpperCase()
  return (rank[eb] ?? 0) > (rank[ea] ?? 0) ? eb : ea
}

function money(v) {
  const n = Number(v || 0)
  return `Bs ${n.toFixed(2)}`
}

function lastPago(row) {
  const pagos = Array.isArray(row?.pagos) ? row.pagos : []
  return pagos.length ? pagos[pagos.length - 1] : null
}

function enrichPedido(row, selected = false) {
  const cobrado = Number(row.cobrado ?? row.pagado ?? 0)
  const last = lastPago(row)
  const tieneCobro = cobrado > 0 || !!last
  return {
    ...row,
    cobrado,
    cobroMonto: tieneCobro ? cobrado : 0,
    selected,
    bloqueado: tieneCobro,
    editando: false,
    puedeCorregir: !!last && Number(last.correcciones || 0) < 1,
    ultimoPagoId: last?.id || null,
  }
}

function estadoColor(estado) {
  const e = String(estado || 'PENDIENTE').toUpperCase()
  if (e === 'ENTREGADO') return 'green-7'
  if (e === 'PARCIAL') return 'orange-7'
  if (e === 'NO ENTREGADO') return 'amber-8'
  if (e === 'RECHAZADO') return 'negative'
  return 'blue-7'
}

function initMap() {
  if (map.value || !mapRef.value) return
  map.value = L.map(mapRef.value, { center: [-17.969721, -67.114493], zoom: 12 })
  const googleRoad = L.tileLayer('https://mt1.google.com/vt/lyrs=r&x={x}&y={y}&z={z}', { maxZoom: 21, attribution: 'Map data © Google' })
  const googleSat = L.tileLayer('https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', { maxZoom: 21, attribution: 'Map data © Google' })
  const googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { maxZoom: 21, attribution: 'Map data © Google' })
  googleRoad.addTo(map.value)
  L.control.layers({ 'Google Calle': googleRoad, 'Google Satelite': googleSat, 'Google Hibrido': googleHybrid }, {}, { collapsed: true }).addTo(map.value)
  layer.value = L.layerGroup().addTo(map.value)
}

function renderMap() {
  if (!layer.value) return
  layer.value.clearLayers()
  const bounds = []
  const byClient = new Map()
  rows.value.forEach((row) => {
    if (!row.cliente_id || !Number.isFinite(Number(row.latitud)) || !Number.isFinite(Number(row.longitud))) return
    if (!byClient.has(row.cliente_id)) byClient.set(row.cliente_id, row)
  })
  byClient.forEach((row) => {
    const lat = Number(row.latitud)
    const lng = Number(row.longitud)
    const mk = L.marker([lat, lng]).addTo(layer.value)
    mk.bindTooltip(`${row.cliente || ''}`, { sticky: true })
    mk.on('click', () => openClienteDialog(row))
    bounds.push([lat, lng])
  })
  if (bounds.length) map.value.fitBounds(bounds, { padding: [25, 25], maxZoom: 15 })
}

async function loadRutas() {
  loading.value = true
  try {
    const res = await proxy.$axios.get('/despachador/rutas', { params: { fecha: fecha.value, search: search.value || undefined } })
    rows.value = Array.isArray(res.data?.data)
      ? res.data.data.map((row) => enrichPedido(row, false))
      : []
    stats.value = res.data?.stats || {}
    renderMap()
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo cargar rutas')
  } finally {
    loading.value = false
  }
}

function openClienteDialog(row) {
  selectedCliente.value = row
  const ids = Array.isArray(row?.source_pedido_ids) ? row.source_pedido_ids : []
  dialogPedidos.value = rows.value
    .filter((item) => {
      if (ids.length) return ids.includes(item.pedido_id)
      return String(item.cliente_id) === String(row.cliente_id)
    })
    .map((item) => enrichPedido(item, true))
  expandedPedidos.value = {}
  pagoInputRefs.value = {}
  dialogCliente.value = true
}

function toggleDetallePedido(pedidoId) {
  expandedPedidos.value[pedidoId] = !expandedPedidos.value[pedidoId]
}

function toggleSelectAll(value) {
  dialogPedidos.value = dialogPedidos.value.map((item) => ({ ...item, selected: !!value }))
}

function habilitarCorreccion(pedido) {
  if (!pedido?.puedeCorregir) return
  pedido.editando = true
  pedido.selected = true
}

function openGoogleMaps(row) {
  if (!row) return
  const lat = Number(row.latitud)
  const lng = Number(row.longitud)
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    proxy.$alert.error('El cliente no tiene ubicacion registrada')
    return
  }
  window.open(`https://www.google.com/maps/search/?api=1&query=${lat},${lng}`, '_blank')
}

function getCurrentLocation() {
  return new Promise((resolve) => {
    if (!navigator.geolocation) return resolve(null)
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({ latitud: pos.coords.latitude, longitud: pos.coords.longitude }),
      () => resolve(null),
      { enableHighAccuracy: true, timeout: 5000, maximumAge: 30000 }
    )
  })
}

function setPagoInputRef(el, idx) {
  if (!el) return
  pagoInputRefs.value[idx] = el
}

async function registrarSeleccionados() {
  if (!selectedPedidos.value.length) {
    proxy.$alert.error('Seleccione al menos una comanda')
    return
  }

  loadingRegister.value = true
  try {
    const paraCorregir = selectedPedidos.value.filter((pedido) => pedido.editando && pedido.ultimoPagoId)
    const paraRegistrar = selectedPedidos.value.filter((pedido) => !pedido.bloqueado)

    for (const pedido of paraCorregir) {
      const monto = Number(Number(pedido.cobroMonto || 0).toFixed(2))
      if (!Number.isFinite(monto) || monto <= 0) {
        proxy.$alert.error(`La comanda #${pedido.comanda} requiere un monto válido para corregir`)
        return
      }
      await proxy.$axios.put(`/despachador/pagos/${pedido.ultimoPagoId}`, { monto })
    }

    if (paraRegistrar.length) {
      const geo = await getCurrentLocation()
      const items = paraRegistrar.map((pedido) => ({
        venta_id: pedido.venta_id,
        pedido_id: pedido.pedido_id,
        monto: Number(Number(pedido.cobroMonto || 0).toFixed(2)),
        tipo_pago: String(pedido.tipo_pago_venta || '').toUpperCase().includes('CRED') ? 'CREDITO' : 'CONTADO',
        metodo_pago: 'EFECTIVO',
        observacion: null,
        latitud: geo?.latitud,
        longitud: geo?.longitud,
      }))
      await proxy.$axios.post('/despachador/pagos/lote', { items })
    }

    if (!paraRegistrar.length && !paraCorregir.length) {
      proxy.$alert.error('No hay cambios para registrar')
      return
    }

    proxy.$alert.success('Comandas registradas')
    await loadRutas()
    if (selectedCliente.value) {
      const pick = rowsClientes.value.find((item) => String(item.cliente_key) === String(selectedCliente.value.cliente_key))
      if (pick) openClienteDialog(pick)
    }
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo registrar cobro')
  } finally {
    loadingRegister.value = false
  }
}

onMounted(() => {
  initMap()
  loadRutas()
})

watch(search, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadRutas(), 350)
})

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
})
</script>

<style scoped>
.rutas-page {
  background: #f4f7fc;
}

.map-container {
  height: 46vh;
  min-height: 360px;
}

.dialog-table :deep(td),
.dialog-table :deep(th) {
  padding-top: 4px;
  padding-bottom: 4px;
}

.cobrado-col {
  min-width: 190px;
}

.detalle-head {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
  font-size: 12px;
}

.producto-row {
  display: flex;
  gap: 10px;
  padding: 3px 0;
  border-bottom: 1px dotted #d6dce7;
  font-size: 12px;
}

.producto-row:last-child {
  border-bottom: 0;
}

.prod-codigo {
  min-width: 64px;
  color: #475569;
  font-weight: 600;
}

.prod-nombre {
  flex: 1;
}

.prod-cantidad {
  white-space: nowrap;
  font-weight: 700;
}
</style>

