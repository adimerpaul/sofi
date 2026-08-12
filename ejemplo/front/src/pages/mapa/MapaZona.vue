<template>
  <q-page class="mapa-zona-page q-pa-sm">
    <div class="row q-col-gutter-sm mapa-zona-grid">
      <div class="col-12 col-md-6">
        <q-card flat bordered class="full-height map-card">
          <q-card-section class="row items-center q-col-gutter-sm q-py-xs q-px-sm toolbar-compact">
            <div class="col-auto">
              <div class="text-subtitle2 text-weight-bold">Mapa zona</div>
            </div>
            <div class="col-12 col-sm-auto">
              <div class="row items-center q-col-gutter-xs no-wrap tipo-toolbar">
                <div class="col-auto">
                  <q-btn-toggle
                    v-model="tipoActivo"
                    dense
                    unelevated
                    no-caps
                    toggle-color="primary"
                    color="grey-3"
                    text-color="dark"
                    :options="tipoOpciones"
                    class="tipo-toggle"
                  />
                </div>
                <div class="col-auto">
                  <q-btn
                    dense
                    size="sm"
                    flat
                    round
                    color="negative"
                    icon="delete"
                    :disable="deletingTipo || !tipoSeleccionado"
                    @click="removeTipoActual"
                  />
                </div>
              </div>
            </div>
            <div class="col-grow toolbar-actions">
              <q-btn dense size="sm" color="primary" icon="add_location_alt" no-caps label="Nuevo poligono" @click="startCreate" />
              <q-btn dense size="sm" color="secondary" icon="grid_view" no-caps label="Nuevo mapa zona" @click="openTipoDialog" />
              <q-btn dense size="sm" color="grey-8" icon="refresh" no-caps label="Actualizar" :loading="loading" @click="loadData" />
            </div>
          </q-card-section>
          <q-separator />
          <q-card-section class="q-pa-none">
            <div ref="mapRef" class="mapa-zona-map" />
            <q-resize-observer @resize="handleMapResize" />
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card flat bordered class="full-height editor-card">
            <q-card-section class="q-pb-xs compact-card-section">
              <div class="text-subtitle1 text-weight-bold">{{ form.id ? 'Editar poligono' : 'Nuevo poligono' }}</div>
              <div class="text-caption text-grey-7">Tipo {{ tipoActivo }}. Haz clic en el mapa para agregar puntos.</div>
            </q-card-section>
            <q-card-section class="q-pt-none compact-card-section">
              <q-form @submit.prevent="savePoligono">
                <q-input v-model="form.nombre" dense outlined label="Nombre" :rules="[v => !!v || 'Requerido']" />

                <div class="row q-col-gutter-xs q-mt-xs">
                  <div class="col-4">
                    <q-input v-model.number="form.tipo" dense outlined type="number" min="1" label="Tipo" />
                  </div>
                  <div class="col-8">
                    <q-input v-model="form.color" dense outlined label="Color" hint="#ff0000" />
                  </div>
                </div>

                <div class="row q-col-gutter-xs q-mt-xs">
                  <div class="col-7">
                    <q-select v-model="form.pedido_zona_id" :options="pedidoZonasOptions" dense outlined emit-value map-options clearable label="Zona de pedido" />
                  </div>
                  <div class="col-5">
                    <q-input v-model.number="form.orden" dense outlined type="number" label="Orden" />
                  </div>
                </div>

                <div class="row q-col-gutter-xs q-mt-xs items-center">
                  <div class="col-6">
                    <q-toggle v-model="form.activo" dense size="sm" label="Activo" color="primary" />
                  </div>
                  <div class="col-6 text-right">
                    <q-chip dense square class="preview-chip" :style="{ backgroundColor: form.color || '#607d8b', color: textColor(form.color || '#607d8b') }">
                      {{ form.nombre || 'Vista previa' }}
                    </q-chip>
                  </div>
                </div>

                <div class="row q-col-gutter-xs q-mt-xs">
                  <div class="col-6">
                    <q-btn dense size="sm" color="warning" no-caps icon="undo" label="Deshacer" class="full-width" :disable="form.coordenadas.length === 0" @click="undoPoint" />
                  </div>
                  <div class="col-6">
                    <q-btn dense size="sm" color="negative" no-caps icon="delete_sweep" label="Limpiar" class="full-width" :disable="form.coordenadas.length === 0" @click="clearPoints" />
                  </div>
                </div>

                <div class="text-caption text-grey-7 q-mt-xs q-mb-xs">Vertices: {{ form.coordenadas.length }}</div>

                <div class="points-list compact-points">
                  <div v-for="(point, index) in form.coordenadas" :key="index" class="point-row">
                    <q-input v-model.number="point.lat" dense outlined type="number" step="0.000001" label="Lat" @update:model-value="renderEditor" />
                    <q-input v-model.number="point.lng" dense outlined type="number" step="0.000001" label="Lng" @update:model-value="renderEditor" />
                    <q-btn dense size="sm" flat round color="negative" icon="delete" @click="removePoint(index)" />
                  </div>
                </div>

                <div class="text-right q-mt-sm">
                  <q-btn flat dense size="sm" no-caps color="grey-8" label="Nuevo" @click="startCreate" />
                  <q-btn dense size="sm" color="primary" no-caps :label="form.id ? 'Actualizar' : 'Guardar'" type="submit" :loading="saving" class="q-ml-xs" />
                </div>
              </q-form>
            </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
          <q-card flat bordered class="full-height saved-card">
            <q-card-section class="row items-center q-col-gutter-xs q-pb-xs compact-card-section">
              <div class="col">
                <div class="text-subtitle1 text-weight-bold">Poligonos guardados</div>
                <div class="text-caption text-grey-7">Tipo {{ tipoActivo }}: {{ poligonosFiltrados.length }} registros</div>
              </div>
              <div class="col-12 q-mt-xs">
                <q-input v-model="filter" dense outlined label="Buscar poligono">
                  <template #append><q-icon name="search" /></template>
                </q-input>
              </div>
            </q-card-section>
            <q-separator />
            <q-list separator class="saved-list">
              <q-item v-for="poligono in poligonosFiltrados" :key="poligono.id" clickable @click="editPoligono(poligono)">
                <q-item-section avatar>
                  <div class="polygon-color" :style="{ backgroundColor: poligono.color || '#607d8b' }" />
                </q-item-section>
                <q-item-section>
                  <q-item-label lines="1">{{ poligono.nombre }}</q-item-label>
                  <q-item-label caption lines="2">
                    Tipo {{ poligono.tipo }} | {{ poligono.pedido_zona?.nombre || 'Sin zona' }} | {{ poligono.coordenadas?.length || 0 }} puntos
                  </q-item-label>
                </q-item-section>
                <q-item-section side>
                  <div class="row no-wrap items-center item-side-actions">
                    <q-chip dense square :color="poligono.activo ? 'green-7' : 'grey-6'" text-color="white">
                      {{ poligono.activo ? 'Activo' : 'Inactivo' }}
                    </q-chip>
                    <q-btn dense size="sm" flat round color="negative" icon="delete" @click.stop="removePoligono(poligono)" />
                  </div>
                </q-item-section>
              </q-item>
              <q-item v-if="poligonosFiltrados.length === 0">
                <q-item-section class="text-grey-6">Sin registros para este tipo</q-item-section>
              </q-item>
            </q-list>
          </q-card>
      </div>
    </div>

    <q-dialog v-model="tipoDialog" persistent>
      <q-card style="width: 360px; max-width: 96vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-subtitle1 text-weight-bold">Nuevo mapa zona</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-card-section>
          <q-form @submit.prevent="saveTipo">
            <q-input
              v-model.number="tipoForm.nombre"
              dense
              outlined
              type="number"
              min="1"
              label="Numero"
              :rules="[v => Number(v) > 0 || 'Requerido']"
            />
            <div class="text-right q-mt-sm">
              <q-btn flat dense size="sm" no-caps color="negative" label="Cancelar" v-close-popup />
              <q-btn dense size="sm" color="primary" no-caps label="Guardar" type="submit" :loading="savingTipo" class="q-ml-xs" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const { proxy } = getCurrentInstance()
const ORURO_CENTER = [-17.969721, -67.114493]
const tiposBase = [5, 4, 3]

const mapRef = ref(null)
const map = ref(null)
const polygonsLayer = ref(null)
const editorPolygonLayer = ref(null)
const editorMarkerLayer = ref(null)

const loading = ref(false)
const saving = ref(false)
const savingTipo = ref(false)
const deletingTipo = ref(false)
const filter = ref('')
const tipoActivo = ref(5)
const tipoDialog = ref(false)
const autoFitMap = ref(true)

const poligonos = ref([])
const tipos = ref([])
const pedidoZonas = ref([])
const selectedPolygonId = ref(null)
const tipoForm = ref({ nombre: null })

const form = ref(createEmptyForm())

function createEmptyForm () {
  return {
    id: null,
    nombre: '',
    tipo: tipoActivo.value,
    color: defaultColorByTipo(tipoActivo.value),
    pedido_zona_id: null,
    orden: 0,
    activo: true,
    coordenadas: [],
  }
}

const tipoOpciones = computed(() => {
  const catalogo = tipos.value.map(item => normalizeTipo(item.nombre)).filter(Number.isFinite)
  const merged = [...new Set([...tiposBase, ...catalogo])].sort((a, b) => b - a)
  return merged.map(tipo => ({ label: String(tipo), value: tipo }))
})

const pedidoZonasOptions = computed(() => [
  { label: 'Sin zona', value: null },
  ...pedidoZonas.value.map(zona => ({ label: zona.nombre, value: zona.id })),
])

const poligonosFiltrados = computed(() => {
  const needle = filter.value.trim().toLowerCase()
  const base = poligonos.value.filter(poligono => Number(poligono.tipo) === Number(tipoActivo.value))
  if (!needle) return base
  return base.filter((poligono) => {
    const stack = `${poligono.nombre || ''} ${poligono.pedido_zona?.nombre || ''}`.toLowerCase()
    return stack.includes(needle)
  })
})

const tipoSeleccionado = computed(() =>
  tipos.value.find(item => Number(item.nombre) === Number(tipoActivo.value)) || null
)

function normalizeTipo (value, fallback = 5) {
  const tipo = Number(value)
  return Number.isInteger(tipo) && tipo > 0 ? tipo : fallback
}

function defaultColorByTipo (tipo) {
  if (Number(tipo) === 5) return '#ff0000'
  if (Number(tipo) === 4) return '#00FF00'
  if (Number(tipo) === 3) return '#1f77b4'
  return '#607d8b'
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

function roundCoord (value) {
  return Math.round(Number(value) * 1000000) / 1000000
}

function initMap () {
  if (!mapRef.value || map.value) return

  map.value = L.map(mapRef.value, {
    center: ORURO_CENTER,
    zoom: 13,
    zoomAnimation: false,
    markerZoomAnimation: false,
  })
  const googleRoad = L.tileLayer('https://mt1.google.com/vt/lyrs=r&x={x}&y={y}&z={z}', { maxZoom: 21, attribution: 'Map data Google' })
  const googleSat = L.tileLayer('https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', { maxZoom: 21, attribution: 'Map data Google' })
  const googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { maxZoom: 21, attribution: 'Map data Google' })
  const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' })

  googleRoad.addTo(map.value)
  L.control.layers({
    'Google Calles': googleRoad,
    'Google Satelital': googleSat,
    'Google Hibrido': googleHybrid,
    OpenStreetMap: osm,
  }).addTo(map.value)

  polygonsLayer.value = L.layerGroup().addTo(map.value)
  editorPolygonLayer.value = L.layerGroup().addTo(map.value)
  editorMarkerLayer.value = L.layerGroup().addTo(map.value)

  map.value.on('click', (event) => {
    form.value.coordenadas.push({ lat: roundCoord(event.latlng.lat), lng: roundCoord(event.latlng.lng) })
    renderEditor()
  })

  invalidateMapSize()
}

function renderSavedPolygons () {
  if (!polygonsLayer.value) return
  stopMapTransitions()
  polygonsLayer.value.clearLayers()

  const bounds = []
  poligonosFiltrados.value.forEach((poligono) => {
    const latlngs = Array.isArray(poligono.coordenadas)
      ? poligono.coordenadas.map(point => [Number(point.lat), Number(point.lng)]).filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng))
      : []

    if (latlngs.length < 3) return
    latlngs.forEach(point => bounds.push(point))

    const color = poligono.color || '#607d8b'
    const layer = L.polygon(latlngs, {
      color,
      fillColor: color,
      fillOpacity: selectedPolygonId.value === poligono.id ? 0.34 : 0.18,
      weight: selectedPolygonId.value === poligono.id ? 4 : 2,
    }).addTo(polygonsLayer.value)

    layer.on('click', () => editPoligono(poligono))
  })

  if (bounds.length > 0 && autoFitMap.value) {
    stopMapTransitions()
    map.value.fitBounds(bounds, { padding: [18, 18], maxZoom: 15 })
  }
}

function renderEditor () {
  if (!editorPolygonLayer.value || !editorMarkerLayer.value) return

  stopMapTransitions()
  editorPolygonLayer.value.clearLayers()
  editorMarkerLayer.value.clearLayers()

  const latlngs = form.value.coordenadas.map(point => [Number(point.lat), Number(point.lng)]).filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng))

  if (latlngs.length >= 2) {
    L.polyline(latlngs, { color: form.value.color || defaultColorByTipo(form.value.tipo), weight: 2.5, dashArray: '5 4' }).addTo(editorPolygonLayer.value)
  }

  if (latlngs.length >= 3) {
    L.polygon(latlngs, { color: form.value.color || defaultColorByTipo(form.value.tipo), fillColor: form.value.color || defaultColorByTipo(form.value.tipo), fillOpacity: 0.24, weight: 2.5 }).addTo(editorPolygonLayer.value)
  }

  form.value.coordenadas.forEach((point, index) => {
    const lat = Number(point.lat)
    const lng = Number(point.lng)
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return

    const marker = L.marker([lat, lng], {
      draggable: true,
      icon: L.divIcon({
        className: '',
        html: `<div class="vertex-marker">${index + 1}</div>`,
        iconSize: [20, 20],
        iconAnchor: [10, 10],
      }),
    }).addTo(editorMarkerLayer.value)

    marker.on('drag', (event) => {
      const { latlng } = event.target
      form.value.coordenadas[index].lat = roundCoord(latlng.lat)
      form.value.coordenadas[index].lng = roundCoord(latlng.lng)
      renderEditor()
    })

    marker.on('dblclick', () => removePoint(index))
  })
}

function startCreate () {
  selectedPolygonId.value = null
  form.value = createEmptyForm()
  autoFitMap.value = false
  renderSavedPolygons()
  renderEditor()
}

function openTipoDialog () {
  tipoForm.value = { nombre: null }
  tipoDialog.value = true
}

function editPoligono (poligono) {
  selectedPolygonId.value = poligono.id
  tipoActivo.value = normalizeTipo(poligono.tipo, tipoActivo.value)
  autoFitMap.value = false
  form.value = {
    id: poligono.id,
    nombre: poligono.nombre,
    tipo: normalizeTipo(poligono.tipo, tipoActivo.value),
    color: poligono.color || defaultColorByTipo(poligono.tipo),
    pedido_zona_id: poligono.pedido_zona_id,
    orden: Number(poligono.orden || 0),
    activo: !!poligono.activo,
    coordenadas: Array.isArray(poligono.coordenadas) ? poligono.coordenadas.map(point => ({ lat: Number(point.lat), lng: Number(point.lng) })) : [],
  }
  renderSavedPolygons()
  renderEditor()
}

function undoPoint () {
  form.value.coordenadas.pop()
  renderEditor()
}

function clearPoints () {
  form.value.coordenadas = []
  renderEditor()
}

function removePoint (index) {
  form.value.coordenadas.splice(index, 1)
  renderEditor()
}

async function loadData () {
  loading.value = true
  try {
    const [mapaRes, pedidoZonasRes] = await Promise.all([
      proxy.$axios.get('/mapa-zona'),
      proxy.$axios.get('/pedido-zonas'),
    ])
    poligonos.value = Array.isArray(mapaRes.data?.poligonos) ? mapaRes.data.poligonos : []
    tipos.value = Array.isArray(mapaRes.data?.tipos) ? mapaRes.data.tipos : []
    pedidoZonas.value = Array.isArray(pedidoZonasRes.data) ? pedidoZonasRes.data : []
    if (!tipoOpciones.value.some(option => Number(option.value) === Number(tipoActivo.value))) {
      tipoActivo.value = Number(tipoOpciones.value[0]?.value || 5)
    }
    autoFitMap.value = true
    renderSavedPolygons()
    renderEditor()
    invalidateMapSize()
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo cargar mapa zona')
  } finally {
    loading.value = false
  }
}

function invalidateMapSize () {
  if (!map.value) return

  nextTick(() => {
    setTimeout(() => {
      if (!map.value) return
      stopMapTransitions()
      map.value.invalidateSize(false)
    }, 30)
  })
}

function handleMapResize () {
  invalidateMapSize()
}

function stopMapTransitions () {
  if (!map.value) return
  if (typeof map.value.stop === 'function') {
    map.value.stop()
  }
  if (map.value._panAnim && typeof map.value._panAnim.stop === 'function') {
    map.value._panAnim.stop()
  }
}

async function saveTipo () {
  const nombre = normalizeTipo(tipoForm.value.nombre, 0)
  if (nombre <= 0) {
    proxy.$alert.error('Debes registrar un numero valido')
    return
  }

  savingTipo.value = true
  try {
    const res = await proxy.$axios.post('/mapa-zona/tipos', { nombre })
    const nuevoTipo = normalizeTipo(res.data?.nombre, nombre)
    tipoDialog.value = false
    await loadData()
    tipoActivo.value = nuevoTipo
    startCreate()
    proxy.$alert.success('Mapa zona creado')
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo guardar el mapa zona')
  } finally {
    savingTipo.value = false
  }
}

function removeTipoActual () {
  const tipo = tipoSeleccionado.value
  if (!tipo) {
    proxy.$alert.error('No existe un mapa zona seleccionado para eliminar')
    return
  }

  proxy.$alert.dialog(`Desea eliminar el mapa zona ${tipo.nombre}?`).onOk(async () => {
    deletingTipo.value = true
    try {
      await proxy.$axios.delete(`/mapa-zona/tipos/${tipo.id}`)
      proxy.$alert.success('Mapa zona eliminado')
      await loadData()
      tipoActivo.value = Number(tipoOpciones.value[0]?.value || 5)
      startCreate()
    } catch (e) {
      proxy.$alert.error(e?.response?.data?.message || 'No se pudo eliminar el mapa zona')
    } finally {
      deletingTipo.value = false
    }
  })
}

async function savePoligono () {
  if (form.value.coordenadas.length < 3) {
    proxy.$alert.error('Debes registrar al menos 3 puntos para el poligono')
    return
  }

  saving.value = true
  try {
    const payload = {
      nombre: form.value.nombre,
      tipo: normalizeTipo(form.value.tipo, tipoActivo.value),
      color: form.value.color || defaultColorByTipo(form.value.tipo),
      pedido_zona_id: form.value.pedido_zona_id,
      orden: Number(form.value.orden || 0),
      activo: !!form.value.activo,
      coordenadas: form.value.coordenadas.map(point => ({ lat: roundCoord(point.lat), lng: roundCoord(point.lng) })),
    }

    if (form.value.id) {
      await proxy.$axios.put(`/mapa-zona/poligonos/${form.value.id}`, payload)
      proxy.$alert.success('Poligono actualizado')
    } else {
      await proxy.$axios.post('/mapa-zona/poligonos', payload)
      proxy.$alert.success('Poligono creado')
    }

    await loadData()
    tipoActivo.value = payload.tipo
    startCreate()
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo guardar el poligono')
  } finally {
    saving.value = false
  }
}

function removePoligono (poligono) {
  proxy.$alert.dialog(`Desea eliminar el poligono ${poligono.nombre}?`).onOk(async () => {
    try {
      await proxy.$axios.delete(`/mapa-zona/poligonos/${poligono.id}`)
      proxy.$alert.success('Poligono eliminado')
      if (selectedPolygonId.value === poligono.id) startCreate()
      await loadData()
    } catch (e) {
      proxy.$alert.error(e?.response?.data?.message || 'No se pudo eliminar el poligono')
    }
  })
}

watch(tipoActivo, (tipo) => {
  const normalizedTipo = normalizeTipo(tipo, 5)
  if (normalizedTipo !== tipoActivo.value) {
    tipoActivo.value = normalizedTipo
    return
  }

  if (!form.value.id) {
    form.value.tipo = normalizedTipo
    if (!form.value.color) form.value.color = defaultColorByTipo(normalizedTipo)
  }
  autoFitMap.value = true
  renderSavedPolygons()
  invalidateMapSize()
})

watch(() => form.value.tipo, (tipo) => {
  const normalizedTipo = normalizeTipo(tipo, tipoActivo.value)
  if (normalizedTipo !== form.value.tipo) {
    form.value.tipo = normalizedTipo
  }
})

onMounted(() => {
  initMap()
  loadData()
})

onBeforeUnmount(() => {
  if (map.value) {
    map.value.remove()
    map.value = null
  }
})
</script>

<style scoped>
.mapa-zona-page {
  background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
}

.mapa-zona-grid {
  align-items: stretch;
}

.mapa-zona-map {
  height: 66vh;
  min-height: 420px;
}

.toolbar-compact {
  min-height: 46px;
}

.toolbar-actions {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 4px;
}

.tipo-toggle {
  white-space: nowrap;
}

.tipo-toolbar {
  flex-wrap: nowrap;
}

.compact-card-section {
  padding: 8px 10px;
}

.preview-chip {
  max-width: 100%;
}

.editor-card,
.saved-card {
  height: 100%;
}

.saved-list {
  max-height: calc(66vh - 96px);
  overflow: auto;
}

.points-list {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px;
  background: #f8fafc;
}

.compact-points {
  max-height: 122px;
  overflow: auto;
}

.point-row {
  display: grid;
  grid-template-columns: 1fr 1fr auto;
  gap: 6px;
  align-items: center;
  margin-bottom: 6px;
}

.polygon-color {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  border: 1px solid #fff;
  box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.15);
}

.item-side-actions {
  gap: 4px;
}

:deep(.saved-list .q-item) {
  min-height: 44px;
  padding: 6px 8px;
}

:deep(.saved-list .q-item__section--avatar) {
  min-width: 28px;
}

:deep(.q-field--dense .q-field__control) {
  min-height: 34px;
}

:deep(.q-field--dense .q-field__native),
:deep(.q-field--dense .q-field__input) {
  font-size: 12px;
}

:deep(.q-chip) {
  font-size: 11px;
}

:deep(.vertex-marker) {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #0f172a;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 700;
  border: 1px solid #fff;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
}

@media (max-width: 1439px) {
  .mapa-zona-map {
    height: 58vh;
    min-height: 380px;
  }

  .saved-list {
    max-height: calc(58vh - 88px);
  }
}

@media (max-width: 1023px) {
  .toolbar-actions {
    justify-content: flex-start;
  }

  .mapa-zona-map {
    height: 56vh;
    min-height: 380px;
  }

  .saved-list {
    max-height: 30vh;
  }
}
</style>
