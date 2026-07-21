<template>
  <q-page class="q-pa-xs mapa-page">
    <q-card flat bordered class="q-mb-xs">
      <q-card-section class="q-pa-sm row q-col-gutter-sm items-center">
        <div class="col-12 col-md-2">
          <q-input v-model="fecha" dense outlined type="date" label="Fecha" />
        </div>
        <div class="col-12 col-md-2">
          <q-btn color="info" icon="search" label="Consultar" no-caps class="full-width" :loading="loading" @click="loadData" />
        </div>
        <div class="col-12 col-md-2">
          <q-btn color="green" icon="local_shipping" label="Asignar" no-caps class="full-width" :disable="selectedRows.length === 0" @click="openAsignar" />
        </div>
        <div class="col-12 col-md-2">
          <q-btn-dropdown color="primary" icon="print" label="Reportes" no-caps class="full-width">
            <q-list>
              <q-item clickable v-close-popup @click="openReportePedidosDialog">
                <q-item-section avatar><q-icon name="description" /></q-item-section>
                <q-item-section>Reporte pedidos</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="dialogReporteZona = true">
                <q-item-section avatar><q-icon name="local_shipping" /></q-item-section>
                <q-item-section>Reporte por zona/vehiculo</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="exportarReporteProductosTotales">
                <q-item-section avatar><q-icon name="inventory_2" /></q-item-section>
                <q-item-section>Productos totales</q-item-section>
              </q-item>
            </q-list>
          </q-btn-dropdown>
        </div>
        <div class="col-12 col-md-3">
          <q-select v-model="vendedorId" :options="vendedoresOptions" dense outlined emit-value map-options label="Vendedor" />
        </div>
        
        <div class="col-12 col-md-2">
          <q-btn-toggle
            v-model="tipoMapa"
            :options="tipoMapaOptions"
            unelevated
            spread
            toggle-color="primary"
            color="grey-3"
            text-color="grey-9"
            class="full-width"
          />
        </div>
      </q-card-section>

      <q-separator />

      <q-card-section class="row q-col-gutter-xs q-pa-sm">
        <div class="col-6 col-md-3">
          <q-chip square color="blue-8" text-color="white" icon="groups" class="full-width justify-center">
            Clientes: {{ stats.total_clientes || 0 }}
          </q-chip>
        </div>
        <div class="col-6 col-md-3">
          <q-chip square color="grey-8" text-color="white" icon="person_off" class="full-width justify-center">
            Sin asignar: {{ statsSinAsignar }}
          </q-chip>
        </div>
        <div class="col-6 col-md-3">
          <q-chip square color="deep-orange-8" text-color="white" icon="warning" class="full-width justify-center">
            Fuera de ruta: {{ stats.fuera_de_ruta || 0 }}
          </q-chip>
        </div>
      </q-card-section>
    </q-card>

    <q-card flat bordered class="q-mb-xs">
      <div :key="mapRenderKey" ref="mapRef" class="map-container" />
    </q-card>

    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-sm items-center q-py-sm">
        <div class="col-12 col-md-3">
          <q-input v-model="search" dense outlined label="Buscar cliente/zona/vendedor" debounce="350" @update:model-value="loadData">
            <template #append><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-12 col-md-2">
          <q-select
            v-model="zonaFiltroTabla"
            :options="zonaFiltroOptions"
            dense
            outlined
            emit-value
            map-options
            label="Zona (tabla)"
          />
        </div>
        <div class="col-12 col-md-2">
          <q-select
            v-model="vendedorFiltroTabla"
            :options="vendedorFiltroTablaOptions"
            dense
            outlined
            emit-value
            map-options
            label="Vendedor (tabla)"
          />
        </div>
        <div class="col-12 col-md-3">
          <q-select
            v-model="fueraRutaFiltroTabla"
            :options="fueraRutaFiltroOptions"
            dense
            outlined
            emit-value
            map-options
            label="Fuera de ruta"
          />
        </div>
        <div class="col-12 col-md-2">
          <q-chip color="teal" text-color="white">Seleccionados: {{ selectedRows.length }}</q-chip>
        </div>
        <div v-if="zonaSeleccionRapida.nombre" class="col-12 col-md-4">
          <q-chip square color="deep-purple-7" text-color="white" class="q-mr-sm">
            Zona clic: {{ zonaSeleccionRapida.nombre }} ({{ zonaSeleccionRapida.total }} clientes)
          </q-chip>
          <q-btn flat dense no-caps color="grey-8" icon="close" label="Limpiar seleccion zona" @click="limpiarSeleccionZonaRapida" />
        </div>
      </q-card-section>

      <q-table
        dense
        flat
        bordered
        row-key="id"
        :rows="rowsFiltradasTabla"
        :columns="columns"
        v-model:selected="selectedRows"
        selection="multiple"
        :rows-per-page-options="[0]"
      >
        <template #body="props">
          <q-tr :props="props" :class="rowClass(props.row)">
            <q-td auto-width>
              <q-checkbox v-model="props.selected" dense />
            </q-td>
            <q-td key="op" :props="props">
              <q-btn dense flat color="primary" icon="my_location" @click.stop="focusRow(props.row)" />
            </q-td>
            <q-td key="num" :props="props">{{ props.row.num }}</q-td>
            <q-td key="cliente_codcli" :props="props">{{ props.row.cliente_codcli || '-' }}</q-td>
            <q-td key="cliente_nombre" :props="props">{{ props.row.cliente_nombre || '-' }}</q-td>
            <q-td key="direccion" :props="props">{{ props.row.direccion || '-' }}</q-td>
            <q-td key="importe" :props="props">{{ Number(props.row.importe || 0).toFixed(2) }}</q-td>
            <q-td key="vendedor" :props="props">{{ props.row.vendedor || '-' }}</q-td>
            <q-td key="zona" :props="props">
              <q-chip
                dense
                :style="{ backgroundColor: props.row.zona_color || '#9e9e9e', color: textColor(props.row.zona_color || '#9e9e9e') }"
              >
                {{ props.row.zona || 'SIN ZONA' }}
              </q-chip>
            </q-td>
            <q-td key="usuario_camion" :props="props">{{ props.row.usuario_camion || '-' }}</q-td>
            <q-td key="en_ruta" :props="props">
              <q-chip dense :color="props.row.usuario_camion ? 'green-7' : 'red-7'" text-color="white">
                {{ props.row.usuario_camion ? 'EN RUTA' : 'SIN RUTA' }}
              </q-chip>
            </q-td>
            <q-td key="fuera_ruta" :props="props">
              <q-chip dense :color="props.row.fuera_de_ruta ? 'deep-orange-7' : 'teal-7'" text-color="white">
                {{ props.row.fuera_de_ruta ? 'FUERA DE RUTA' : 'EN DIA' }}
              </q-chip>
            </q-td>
          </q-tr>
        </template>
        <template #body-cell-zona="props">
          <q-td :props="props">
            <q-chip
              dense
              :style="{ backgroundColor: props.row.zona_color || '#9e9e9e', color: textColor(props.row.zona_color || '#9e9e9e') }"
            >
              {{ props.row.zona || 'SIN ZONA' }}
            </q-chip>
          </q-td>
        </template>
        <template #body-cell-en_ruta="props">
          <q-td :props="props">
            <q-chip dense :color="props.row.usuario_camion ? 'green-7' : 'red-7'" text-color="white">
              {{ props.row.usuario_camion ? 'EN RUTA' : 'SIN RUTA' }}
            </q-chip>
          </q-td>
        </template>
        <template #body-cell-fuera_ruta="props">
          <q-td :props="props">
            <q-chip dense :color="props.row.fuera_de_ruta ? 'deep-orange-7' : 'teal-7'" text-color="white">
              {{ props.row.fuera_de_ruta ? 'FUERA DE RUTA' : 'EN DIA' }}
            </q-chip>
          </q-td>
        </template>
        <template #body-cell-op="props">
          <q-td :props="props">
            <q-btn dense flat color="primary" icon="my_location" @click="focusRow(props.row)" />
          </q-td>
        </template>
      </q-table>

      <q-separator />
      <q-card-section class="row q-col-gutter-sm items-center q-py-sm">
        <div class="col-12 col-md-2">
          <q-btn
            color="green"
            icon="local_shipping"
            label="Asignar"
            no-caps
            class="full-width"
            :disable="selectedRows.length === 0"
            @click="openAsignar"
          />
        </div>
        <div class="col-12 col-md-3">
          <q-btn-dropdown color="primary" icon="print" label="Reportes" no-caps class="full-width">
            <q-list>
              <q-item clickable v-close-popup @click="openReportePedidosDialog">
                <q-item-section avatar><q-icon name="description" /></q-item-section>
                <q-item-section>Reporte pedidos</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="dialogReporteZona = true">
                <q-item-section avatar><q-icon name="local_shipping" /></q-item-section>
                <q-item-section>Reporte por zona/vehiculo</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="exportarReporteProductosTotales">
                <q-item-section avatar><q-icon name="inventory_2" /></q-item-section>
                <q-item-section>Productos totales</q-item-section>
              </q-item>
            </q-list>
          </q-btn-dropdown>
        </div>
      </q-card-section>
    </q-card>

    <q-dialog v-model="dialogAsignar" persistent>
      <q-card style="width: 420px; max-width: 96vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Asignar camion / zona</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-card-section>
          <q-form @submit.prevent="asignarSeleccion">
            <q-select
              v-model="asignacion.usuario_camion_id"
              :options="camionesOptions"
              option-label="label"
              option-value="value"
              emit-value
              map-options
              dense
              outlined
              label="Camion"
              :rules="[v => !!v || 'Campo requerido']"
            />
            <q-select
              class="q-mt-sm"
              v-model="asignacion.pedido_zona_id"
              :options="zonasOptions"
              option-label="label"
              option-value="value"
              emit-value
              map-options
              dense
              outlined
              label="Zona / color"
              :rules="[v => !!v || 'Campo requerido']"
            >
              <template #option="scope">
                <q-item v-bind="scope.itemProps">
                  <q-item-section>
                    <div class="zona-option" :style="{ backgroundColor: scope.opt.color, color: textColor(scope.opt.color) }">
                      {{ scope.opt.label }}
                    </div>
                  </q-item-section>
                </q-item>
              </template>
            </q-select>
            <q-card-actions align="right" class="q-px-none q-pt-md">
              <q-btn flat no-caps color="grey-8" label="Cancelar" v-close-popup />
              <q-btn color="primary" no-caps label="Asignar" type="submit" :loading="assigning" />
            </q-card-actions>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogReporteZona">
      <q-card style="width: 380px; max-width: 95vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Reporte por vehiculo</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-card-section>
          <q-select
            v-model="reporteZonaCamionId"
            :options="camionesOptions"
            option-label="label"
            option-value="value"
            emit-value
            map-options
            dense
            outlined
            label="Camion"
            :rules="[v => !!v || 'Campo requerido']"
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat color="grey-8" no-caps label="Cancelar" v-close-popup />
          <q-btn color="primary" no-caps label="Generar" :loading="loadingReport" @click="exportarReporteZonaVehiculo" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogReportePedidosTipos">
      <q-card style="width: 440px; max-width: 96vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Reporte pedidos por tipo</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-card-section>
          <q-option-group
            v-model="reporteTiposSeleccionados"
            :options="reporteTipoOptions"
            type="checkbox"
            color="primary"
          />
          <div class="row q-col-gutter-sm q-mt-sm">
            <div class="col-12 col-md-6">
              <q-btn flat dense no-caps color="grey-8" label="Marcar sugeridos" class="full-width" @click="marcarTiposSugeridos" />
            </div>
            <div class="col-12 col-md-6">
              <q-btn flat dense no-caps color="grey-8" label="Marcar todos" class="full-width" @click="marcarTodosLosTiposReporte" />
            </div>
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat color="grey-8" no-caps label="Cancelar" v-close-popup />
          <q-btn color="primary" no-caps label="Generar" :loading="loadingReport" @click="exportarReportePedidosConTipos" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const { proxy } = getCurrentInstance()

const mapRef = ref(null)
const map = ref(null)
const markersLayer = ref(null)
const polygonsLayer = ref(null)
const mapRenderKey = ref(0)

const fecha = ref(new Date().toISOString().slice(0, 10))
const vendedorId = ref(null)
const tipo = ref('TODOS')
const search = ref('')
const loading = ref(false)
const assigning = ref(false)
const loadingReport = ref(false)
const dialogAsignar = ref(false)
const dialogReporteZona = ref(false)
const dialogReportePedidosTipos = ref(false)
const zonaFiltroTabla = ref('TODAS')
const vendedorFiltroTabla = ref('TODOS')
const fueraRutaFiltroTabla = ref('TODOS')
const reporteZonaCamionId = ref(null)
const reporteTiposSugeridos = ['EMBUTIDO', 'HUEVO', 'PET']
const reporteTiposTodos = ['EMBUTIDO', 'HUEVO', 'PET', 'POLLO', 'CERDO', 'RES']
const reporteTiposSeleccionados = ref([...reporteTiposSugeridos])
const tipoMapa = ref(5)

const rows = ref([])
const selectedRows = ref([])
const stats = ref({})
const vendedores = ref([])
const camiones = ref([])
const zonas = ref([])
const poligonos = ref([])
const zonaSeleccionRapida = ref({ nombre: '', total: 0, poligonoId: null })

const asignacion = ref({
  usuario_camion_id: null,
  pedido_zona_id: null,
})

const tipoMapaOptions = [
  { label: '5', value: 5 },
  { label: '4', value: 4 },
  { label: '3', value: 3 },
]

const tipoOptions = [
  { label: 'Todos', value: 'TODOS' },
  { label: 'Normal', value: 'NORMAL' },
  { label: 'Pollo', value: 'POLLO' },
  { label: 'Res', value: 'RES' },
  { label: 'Cerdo', value: 'CERDO' },
]

const reporteTipoOptions = [
  { label: 'Embutido', value: 'EMBUTIDO' },
  { label: 'Huevo', value: 'HUEVO' },
  { label: 'Pet', value: 'PET' },
  { label: 'Pollo', value: 'POLLO' },
  { label: 'Cerdo', value: 'CERDO' },
  { label: 'Res', value: 'RES' },
]

const columns = [
  { name: 'op', label: 'Op', field: 'op', align: 'left' },
  { name: 'num', label: '#', field: 'num', align: 'center' },
  { name: 'cliente_codcli', label: 'CINIT', field: 'cliente_codcli', align: 'left' },
  { name: 'cliente_nombre', label: 'Cliente', field: 'cliente_nombre', align: 'left' },
  { name: 'direccion', label: 'Direccion', field: 'direccion', align: 'left' },
  { name: 'importe', label: 'Importe', field: row => Number(row.importe || 0).toFixed(2), align: 'right' },
  { name: 'vendedor', label: 'Vendedor', field: 'vendedor', align: 'left' },
  { name: 'zona', label: 'Zona', field: 'zona', align: 'left' },
  { name: 'usuario_camion', label: 'Camion', field: row => row.usuario_camion || '-', align: 'left' },
  { name: 'en_ruta', label: 'Ruta', field: 'en_ruta', align: 'left' },
  { name: 'fuera_ruta', label: 'Fuera Ruta', field: row => !!row.fuera_de_ruta, align: 'left' },
]

const vendedoresOptions = computed(() => [
  { label: 'Todos', value: null },
  ...vendedores.value.map(v => ({ label: v.name, value: v.id })),
])

const camionesOptions = computed(() => camiones.value.map(c => ({
  label: `${c.name}${c.placa ? ` (${c.placa})` : ''}`,
  value: c.id,
})))

const zonasOptions = computed(() => zonas.value.map(z => ({
  label: z.nombre,
  value: z.id,
  color: z.color,
})))

const statsSinAsignar = computed(() => rows.value.filter(r => !r.usuario_camion).length)
const zonaFiltroOptions = computed(() => {
  const set = new Set((rows.value || []).map(r => r.zona || 'SIN ZONA'))
  return [
    { label: 'Todas', value: 'TODAS' },
    ...Array.from(set).sort().map(z => ({ label: z, value: z })),
  ]
})

const vendedorFiltroTablaOptions = computed(() => {
  const set = new Set((rows.value || []).map(r => r.vendedor || 'SIN VENDEDOR'))
  return [
    { label: 'Todos', value: 'TODOS' },
    ...Array.from(set).sort().map(v => ({ label: v, value: v })),
  ]
})

const fueraRutaFiltroOptions = [
  { label: 'Todos', value: 'TODOS' },
  { label: 'Fuera de ruta', value: 'FUERA' },
  { label: 'En dia', value: 'EN_DIA' },
]

const rowsFiltradasTabla = computed(() => {
  return rows.value.filter((r) => {
    const okZona = zonaFiltroTabla.value === 'TODAS' || (r.zona || 'SIN ZONA') === zonaFiltroTabla.value
    const okVendedor = vendedorFiltroTabla.value === 'TODOS' || (r.vendedor || 'SIN VENDEDOR') === vendedorFiltroTabla.value
    const okFueraRuta = fueraRutaFiltroTabla.value === 'TODOS'
      || (fueraRutaFiltroTabla.value === 'FUERA' && !!r.fuera_de_ruta)
      || (fueraRutaFiltroTabla.value === 'EN_DIA' && !r.fuera_de_ruta)
    return okZona && okVendedor && okFueraRuta
  })
})

function regroupRows (items) {
  const grouped = new Map()

  items.forEach((row) => {
    const key = [
      Number(row.cliente_id || 0),
      Number(row.vendedor_id || 0),
      Number(row.pedido_zona_id || 0),
      Number(row.usuario_camion_id || 0),
    ].join('|')

    if (!grouped.has(key)) {
      grouped.set(key, {
        ...row,
        pedido_ids: Array.isArray(row.pedido_ids) ? [...row.pedido_ids] : [],
        cantidad_pedidos: Number(row.cantidad_pedidos || 0),
        importe: Number(row.importe || 0),
      })
      return
    }

    const current = grouped.get(key)
    current.pedido_ids = [...current.pedido_ids, ...(Array.isArray(row.pedido_ids) ? row.pedido_ids : [])]
    current.cantidad_pedidos += Number(row.cantidad_pedidos || 0)
    current.importe += Number(row.importe || 0)
    current.contiene_normal = Boolean(current.contiene_normal || row.contiene_normal)
    current.contiene_pollo = Boolean(current.contiene_pollo || row.contiene_pollo)
    current.contiene_res = Boolean(current.contiene_res || row.contiene_res)
    current.contiene_cerdo = Boolean(current.contiene_cerdo || row.contiene_cerdo)
    current.fuera_de_ruta = Boolean(current.fuera_de_ruta || row.fuera_de_ruta)
  })

  return Array.from(grouped.values()).map((row, index) => ({
    ...row,
    num: index + 1,
    pedido_ids: Array.from(new Set((row.pedido_ids || []).map(id => Number(id)))).sort((a, b) => a - b),
  }))
}

function applyAsignacionLocal (pedidoIds) {
  const selectedPedidoIds = new Set((pedidoIds || []).map(id => Number(id)))
  const camion = camiones.value.find(c => Number(c.id) === Number(asignacion.value.usuario_camion_id)) || null
  const zona = zonas.value.find(z => Number(z.id) === Number(asignacion.value.pedido_zona_id)) || null

  rows.value = regroupRows(rows.value.map((row) => {
    const rowPedidoIds = Array.isArray(row.pedido_ids) ? row.pedido_ids.map(id => Number(id)) : []
    const affected = rowPedidoIds.some(id => selectedPedidoIds.has(id))

    if (!affected) return row

    return {
      ...row,
      usuario_camion_id: camion?.id ?? null,
      usuario_camion: camion?.name ?? null,
      placa_camion: camion?.placa ?? null,
      pedido_zona_id: zona?.id ?? null,
      zona: zona?.nombre ?? null,
      zona_color: zona?.color ?? '#9e9e9e',
    }
  }))

  stats.value = {
    ...stats.value,
    total_clientes: rows.value.length,
  }
  selectedRows.value = []
  renderMarkers()
}

function textColor (bg) {
  const hex = String(bg || '').replace('#', '')
  if (hex.length !== 6) return '#ffffff'
  const r = parseInt(hex.slice(0, 2), 16)
  const g = parseInt(hex.slice(2, 4), 16)
  const b = parseInt(hex.slice(4, 6), 16)
  const yiq = (r * 299 + g * 587 + b * 114) / 1000
  return yiq >= 140 ? '#111827' : '#ffffff'
}

function rowClass (row) {
  if (row?.fuera_de_ruta) return 'row-fuera-ruta'
  return row?.usuario_camion ? 'row-en-ruta' : 'row-sin-ruta'
}

function initMap (view = null) {
  if (!mapRef.value || map.value) return
  const center = view?.center ?? [-17.969721, -67.114493]
  const zoom = view?.zoom ?? 11
  map.value = L.map(mapRef.value, { center, zoom })
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map.value)
  polygonsLayer.value = L.layerGroup().addTo(map.value)
  markersLayer.value = L.layerGroup().addTo(map.value)
}

function wait (ms) {
  return new Promise(resolve => window.setTimeout(resolve, ms))
}

async function refreshMapLayout () {
  await nextTick()
  if (!map.value) return

  map.value.invalidateSize({ pan: false, debounceMoveend: true })
  await wait(80)
  if (!map.value) return

  map.value.invalidateSize({ pan: false, debounceMoveend: true })
  await wait(180)
  if (!map.value) return

  map.value.invalidateSize({ pan: false, debounceMoveend: true })
}

async function rebuildMap (preserveView = true) {
  const fallbackView = { center: [-17.969721, -67.114493], zoom: 11 }
  const currentView = preserveView && map.value
    ? {
        center: [map.value.getCenter().lat, map.value.getCenter().lng],
        zoom: map.value.getZoom(),
      }
    : fallbackView

  if (map.value) {
    map.value.remove()
    map.value = null
  }

  markersLayer.value = null
  polygonsLayer.value = null
  mapRef.value = null
  mapRenderKey.value += 1

  await nextTick()
  initMap(currentView)
  renderPolygons()
  renderMarkers()
  await refreshMapLayout()
}
function markerIcon (row) {
  const color = row.zona_color || '#607d8b'
  const statusColor = row.fuera_de_ruta ? '#dd6b20' : '#0f766e'
  const statusTitle = row.fuera_de_ruta ? 'Fuera de ruta' : 'En dia'
  return L.divIcon({
    className: '',
    html: `
      <div style="
        position:relative;
        width:26px;height:26px;border-radius:13px;
        background:${color};color:${textColor(color)};
        border:2px solid #fff;display:flex;align-items:center;justify-content:center;
        font-size:11px;font-weight:700;box-shadow:0 2px 6px rgba(0,0,0,.35);
      ">
        ${row.num}
        <span title="${statusTitle}" style="
          position:absolute;right:-3px;top:-3px;width:9px;height:9px;border-radius:999px;
          background:${statusColor};border:1px solid #fff;
        "></span>
      </div>
    `,
    iconSize: [26, 26],
    iconAnchor: [13, 13],
  })
}

function tooltipHtml (row) {
  const estadoRuta = row.usuario_camion ? 'EN RUTA' : 'SIN RUTA'
  const estadoFueraRuta = row.fuera_de_ruta ? 'FUERA DE RUTA' : 'EN DIA'
  const estadoFueraRutaColor = row.fuera_de_ruta ? '#dd6b20' : '#0f766e'
  return `
    <div style="font-size:12px;line-height:1.25;">
      <div style="font-weight:700;">${row.cliente_nombre || '-'}</div>
      <div><b>${estadoRuta}</b></div>
      <div><b style="color:${estadoFueraRutaColor};">${estadoFueraRuta}</b></div>
      <div><b>Territorio:</b> ${row.territorio || '-'}</div>
      <div><b>Importe:</b> ${Number(row.importe || 0).toFixed(2)} Bs</div>
      <div><b>Vendedor:</b> ${row.vendedor || '-'}</div>
      <div><b>Direccion:</b> ${row.direccion || '-'}</div>
      <div><b>Camion:</b> ${row.usuario_camion || 'Sin asignar'}</div>
    </div>
  `
}

function renderMarkers (options = {}) {
  if (!markersLayer.value || !map.value) return
  const { adjustView = false } = options
  markersLayer.value.clearLayers()
  const bounds = []

  rowsFiltradasTabla.value.forEach((row) => {
    const lat = Number(row.latitud)
    const lng = Number(row.longitud)
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return

    const marker = L.marker([lat, lng], { icon: markerIcon(row) }).addTo(markersLayer.value)
    marker.bindTooltip(tooltipHtml(row), { sticky: true, direction: 'top' })
    marker.on('click', () => {
      const already = selectedRows.value.some(r => r.id === row.id)
      if (already) {
        selectedRows.value = selectedRows.value.filter(r => r.id !== row.id)
      } else {
        selectedRows.value = [...selectedRows.value, row]
      }
      marker.openTooltip()
    })
    bounds.push([lat, lng])
  })

  if (adjustView && bounds.length > 0) {
    map.value.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 })
  }
}

function renderPolygons () {
  if (!polygonsLayer.value) return
  polygonsLayer.value.clearLayers()

  poligonos.value.filter(poligono => Number(poligono.tipo) === Number(tipoMapa.value)).forEach((poligono) => {
    const latlngs = Array.isArray(poligono.coordenadas)
      ? poligono.coordenadas
          .map(point => [Number(point.lat), Number(point.lng)])
          .filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng))
      : []

    if (latlngs.length < 3) return

    const color = poligono.color || '#607d8b'
    const layer = L.polygon(latlngs, {
      color,
      fillColor: color,
      fillOpacity: 0.14,
      weight: 2,
    }).addTo(polygonsLayer.value)

    layer.bindTooltip(`
      <div style="font-size:12px;line-height:1.25;">
        <div style="font-weight:700;">${poligono.nombre || '-'}</div>
        <div><b>Tipo:</b> ${poligono.tipo?.nombre || '-'}</div>
        <div><b>Zona:</b> ${poligono.pedido_zona?.nombre || '-'}</div>
      </div>
    `, { sticky: true })

    layer.on('click', () => {
      seleccionarClientesPorPoligono(poligono, latlngs)
    })
  })
}

function pointInPolygon (lat, lng, latlngs) {
  // Ray-casting algorithm on lng/lat plane.
  let inside = false
  for (let i = 0, j = latlngs.length - 1; i < latlngs.length; j = i++) {
    const yi = latlngs[i][0]
    const xi = latlngs[i][1]
    const yj = latlngs[j][0]
    const xj = latlngs[j][1]

    const intersects = ((yi > lat) !== (yj > lat)) &&
      (lng < ((xj - xi) * (lat - yi)) / ((yj - yi) || 1e-12) + xi)

    if (intersects) inside = !inside
  }
  return inside
}

function seleccionarClientesPorPoligono (poligono, latlngs) {
  if (!Array.isArray(latlngs) || latlngs.length < 3) return

  const candidatos = rows.value.filter((row) => {
    const lat = Number(row.latitud)
    const lng = Number(row.longitud)
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return false
    return pointInPolygon(lat, lng, latlngs)
  })

  if (candidatos.length === 0) {
    proxy.$alert.error('No se encontraron clientes dentro de esta zona')
    return
  }

  const zonaNombre = poligono?.pedido_zona?.nombre || poligono?.nombre || 'ZONA'
  const coincideZona = zonaFiltroOptions.value.some(z => z.value === zonaNombre)
  zonaFiltroTabla.value = coincideZona ? zonaNombre : 'TODAS'
  selectedRows.value = [...candidatos]
  zonaSeleccionRapida.value = {
    nombre: zonaNombre,
    total: candidatos.length,
    poligonoId: poligono?.id ?? null,
  }
  renderMarkers()
}

function limpiarSeleccionZonaRapida () {
  zonaSeleccionRapida.value = { nombre: '', total: 0, poligonoId: null }
  selectedRows.value = []
}

function focusRow (row) {
  const lat = Number(row.latitud)
  const lng = Number(row.longitud)
  if (!Number.isFinite(lat) || !Number.isFinite(lng) || !map.value) return
  map.value.flyTo([lat, lng], 16)
}

async function loadData () {
  loading.value = true
  try {
    const res = await proxy.$axios.get('/mapa-clientes', {
      params: {
        fecha: fecha.value,
        vendedor_id: vendedorId.value,
        tipo: tipo.value,
        search: search.value,
      },
    })
    rows.value = Array.isArray(res.data?.data) ? res.data.data : []
    zonaFiltroTabla.value = 'TODAS'
    vendedorFiltroTabla.value = 'TODOS'
    fueraRutaFiltroTabla.value = 'TODOS'
    vendedores.value = Array.isArray(res.data?.vendedores) ? res.data.vendedores : []
    camiones.value = Array.isArray(res.data?.camiones) ? res.data.camiones : []
    zonas.value = Array.isArray(res.data?.zonas) ? res.data.zonas : []
    poligonos.value = Array.isArray(res.data?.poligonos) ? res.data.poligonos : []
    stats.value = res.data?.stats || {}
    selectedRows.value = []
    zonaSeleccionRapida.value = { nombre: '', total: 0, poligonoId: null }
    renderPolygons()
    renderMarkers()
    await refreshMapLayout()
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo cargar mapa cliente')
  } finally {
    loading.value = false
  }
}

function openAsignar () {
  if (selectedRows.value.length === 0) return
  asignacion.value = { usuario_camion_id: null, pedido_zona_id: null }
  dialogAsignar.value = true
}

async function asignarSeleccion () {
  const pedidoIds = Array.from(new Set(selectedRows.value.flatMap(r => r.pedido_ids || [])))
  if (pedidoIds.length === 0) {
    proxy.$alert.error('No hay pedidos para asignar')
    return
  }

  assigning.value = true
  try {
    await proxy.$axios.post('/mapa-clientes/asignar', {
      pedido_ids: pedidoIds,
      usuario_camion_id: asignacion.value.usuario_camion_id,
      pedido_zona_id: asignacion.value.pedido_zona_id,
    })
    dialogAsignar.value = false
    applyAsignacionLocal(pedidoIds)
    await rebuildMap()
    proxy.$alert.success('Asignacion aplicada')
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo asignar')
  } finally {
    assigning.value = false
  }
}

function selectedZonaId () {
  if (zonaFiltroTabla.value === 'TODAS') return null
  const zone = zonas.value.find(z => z.nombre === zonaFiltroTabla.value)
  return zone?.id || null
}

function baseReportParams () {
  return {
    fecha: fecha.value,
    vendedor_id: vendedorId.value,
    tipo: tipo.value,
    pedido_zona_id: selectedZonaId(),
  }
}

async function descargarPdf (url, params, defaultFileName) {
  loadingReport.value = true
  try {
    const res = await proxy.$axios.get(url, {
      params,
      responseType: 'blob',
    })
    const blob = new Blob([res.data], { type: 'application/pdf' })
    const disposition = res?.headers?.['content-disposition'] || ''
    const match = disposition.match(/filename="?([^"]+)"?/)
    const fileName = match?.[1] || defaultFileName

    const fileUrl = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = fileUrl
    a.download = fileName
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

function marcarTiposSugeridos () {
  reporteTiposSeleccionados.value = [...reporteTiposSugeridos]
}

function marcarTodosLosTiposReporte () {
  reporteTiposSeleccionados.value = [...reporteTiposTodos]
}

function openReportePedidosDialog () {
  marcarTiposSugeridos()
  dialogReportePedidosTipos.value = true
}

async function exportarReportePedidosConTipos () {
  const tipos = Array.from(new Set((reporteTiposSeleccionados.value || []).map(t => String(t || '').toUpperCase()).filter(Boolean)))
  if (tipos.length === 0) {
    proxy.$alert.error('Debe seleccionar al menos un tipo')
    return
  }
  await descargarPdf(
    '/mapa-clientes/reportes/pedidos',
    { ...baseReportParams(), tipos, tipo: 'TODOS' },
    `reporte_pedidos_${fecha.value}.pdf`,
  )
  dialogReportePedidosTipos.value = false
}

async function exportarReporteZonaVehiculo () {
  if (!reporteZonaCamionId.value) {
    proxy.$alert.error('Debes seleccionar un camion')
    return
  }
  await descargarPdf(
    '/mapa-clientes/reportes/zona-vehiculo',
    {
      ...baseReportParams(),
      usuario_camion_id: reporteZonaCamionId.value,
    },
    `reporte_zona_vehiculo_${fecha.value}.pdf`,
  )
  dialogReporteZona.value = false
}

async function exportarReporteProductosTotales () {
  await descargarPdf(
    '/mapa-clientes/reportes/productos-totales',
    baseReportParams(),
    `reporte_productos_totales_${fecha.value}.pdf`,
  )
}

onMounted(() => {
  initMap()
  loadData()
})

watch(tipoMapa, () => {
  renderPolygons()
  refreshMapLayout()
})

watch(dialogAsignar, (open) => {
  if (!open) {
    refreshMapLayout()
  }
})

watch([zonaFiltroTabla, vendedorFiltroTabla, fueraRutaFiltroTabla], () => {
  selectedRows.value = selectedRows.value.filter((s) => rowsFiltradasTabla.value.some((r) => r.id === s.id))
  if (selectedRows.value.length === 0) {
    zonaSeleccionRapida.value = { nombre: '', total: 0, poligonoId: null }
  }
  renderMarkers()
})

onBeforeUnmount(() => {
  if (map.value) {
    map.value.remove()
    map.value = null
  }
})
</script>

<style scoped>
.mapa-page {
  background: linear-gradient(180deg, #eef4ff 0%, #f8fbff 30%, #ffffff 100%);
}

.map-container {
  height: 52vh;
  min-height: 430px;
}

.zona-option {
  padding: 4px 10px;
  border-radius: 8px;
  font-weight: 600;
}

:deep(.row-en-ruta) {
  background: #e8f5e9;
}

:deep(.row-sin-ruta) {
  background: #fff;
}

:deep(.row-fuera-ruta) {
  background: #fff3e0;
}
</style>
