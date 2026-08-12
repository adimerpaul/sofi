<template>
  <q-page class="q-pa-sm">
    <q-card flat bordered>
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6">Registro de cliente</div>
        <q-space />
      </q-card-section>

      <q-card-section>
        <q-form @submit.prevent="handlePrimaryAction">
          <q-tabs v-model="tab" dense active-color="primary" align="left" class="text-grey-8">
            <q-tab name="basico" icon="badge" label="Basico" />
            <q-tab name="ubicacion" icon="place" label="Ubicacion" />
            <q-tab name="fotos" icon="photo_camera" label="Fotos" />
          </q-tabs>
          <q-separator class="q-my-sm" />

          <q-tab-panels v-model="tab" animated>
            <q-tab-panel name="basico">
              <div class="row q-col-gutter-sm">
                <div class="col-12 col-md-4"><q-input v-model="cliente.nombre" label="Nombre" dense outlined :rules="[v => !!v || 'Requerido']" /></div>
                <div class="col-12 col-md-2"><q-input v-model="cliente.ci" label="CI" dense outlined /></div>
                <div class="col-12 col-md-2"><q-input v-model="cliente.nit" label="NIT" dense outlined /></div>
                <div class="col-12 col-md-2"><q-input v-model="cliente.complemento" label="Complemento" dense outlined /></div>
                <div class="col-12 col-md-2"><q-input v-model="cliente.codigoTipoDocumentoIdentidad" label="Tipo doc" dense outlined /></div>
                <div class="col-12 col-md-2"><q-input v-model="cliente.id_externo" label="ID externo" dense outlined /></div>
                <div class="col-12 col-md-3"><q-toggle v-model="cliente.puede_credito" color="positive" checked-icon="verified" unchecked-icon="block" label="Puede tener credito" /></div>
                <div class="col-12 col-md-3"><q-input v-model="cliente.telefono" label="Telefono" dense outlined /></div>
                <div class="col-12 col-md-3"><q-input v-model="cliente.email" label="Email" dense outlined type="email" /></div>
                <div class="col-12 col-md-6"><q-input v-model="cliente.direccion" label="Direccion" dense outlined /></div>
                <div class="col-12 col-md-3"><q-input v-model="cliente.empresa" label="Empresa" dense outlined /></div>
                <div class="col-12 col-md-3"><q-input v-model="cliente.profecion" label="Profesion" dense outlined /></div>
                <div class="col-12 col-md-2"><q-input v-model="cliente.sexo" label="Sexo" dense outlined /></div>
                <div class="col-12 col-md-2"><q-input v-model="cliente.edad" label="Edad" dense outlined /></div>
                <div class="col-12 col-md-2"><q-input v-model="cliente.est_civ" label="Estado civil" dense outlined /></div>
                <div class="col-12 col-md-2"><q-input v-model="cliente.cod_ciudad" label="Cod ciudad" dense outlined /></div>
                <div class="col-12 col-md-2"><q-input v-model="cliente.cod_nacio" label="Cod nacio" dense outlined /></div>
                <div class="col-12 col-md-2"><q-input v-model.number="cliente.tipodocu" label="Tipo documento" dense outlined type="number" /></div>
              </div>
            </q-tab-panel>

            <q-tab-panel name="ubicacion">
              <div class="row q-col-gutter-sm">
                <div class="col-12 col-md-3"><q-input v-model.number="cliente.latitud" label="Latitud" dense outlined type="number" step="0.0000001" /></div>
                <div class="col-12 col-md-3"><q-input v-model.number="cliente.longitud" label="Longitud" dense outlined type="number" step="0.0000001" /></div>
                <div class="col-12 col-md-2"><q-btn color="primary" no-caps label="Centrar" class="full-width" @click="centerMap" /></div>
                <div class="col-12 col-md-2"><q-btn color="secondary" no-caps label="Mi ubicacion" class="full-width" @click="useCurrentLocation" /></div>
                <div class="col-12 col-md-2"><q-btn color="teal" no-caps label="Oruro centro" class="full-width" @click="goToOruro" /></div>
                <div class="col-12 col-md-6">
                  <q-btn
                    color="info"
                    no-caps
                    icon="open_in_new"
                    label="Abrir en Google Maps"
                    class="full-width"
                    :disable="!hasValidCoordinates()"
                    @click="openGoogleMaps"
                  />
                </div>
                <div class="col-12">
                  <q-chip outline color="primary" icon="place">{{ coordsLabel() }}</q-chip>
                </div>
                <div class="col-12">
                  <div ref="mapRef" class="map-canvas" />
                  <div class="text-caption q-mt-xs">Click en el mapa o arrastra el marcador para actualizar coordenadas.</div>
                </div>
              </div>
            </q-tab-panel>

            <q-tab-panel name="fotos">
              <div class="row q-col-gutter-sm">
                <div class="col-12">
                  <q-btn color="primary" no-caps icon="photo_camera" label="Agregar fotos (max. 3)" @click="$refs.fotosInput.click()" />
                  <input ref="fotosInput" type="file" accept="image/*" multiple style="display:none" @change="onFotosChange" />
                </div>
                <div class="col-12 text-caption">Puedes cargar hasta 3 fotos del lugar.</div>
                <div class="col-12 row q-col-gutter-sm">
                  <div class="col-6 col-md-3" v-for="(f, idx) in previewFotos" :key="idx">
                    <q-card flat bordered>
                      <q-img :src="fotoUrl(f)" style="height: 130px" fit="cover" />
                      <q-card-actions align="right">
                        <q-btn flat dense color="negative" icon="delete" @click="removeFoto(idx)" />
                      </q-card-actions>
                    </q-card>
                  </div>
                </div>
              </div>
            </q-tab-panel>
          </q-tab-panels>

          <div class="row items-center justify-between q-mt-md">
            <div class="text-caption text-grey-7">
              Paso {{ currentStepIndex + 1 }} de {{ tabsOrder.length }}
            </div>
            <div class="text-right">
              <q-btn
                v-if="currentStepIndex > 0"
                flat
                no-caps
                label="Anterior"
                color="primary"
                class="q-mr-sm"
                @click="goPrev"
                :loading="loading"
              />
            <q-btn flat no-caps label="Cancelar" color="grey-8" @click="$router.back()" :loading="loading" />
              <q-btn
                color="primary"
                no-caps
                :label="isLastStep ? 'Guardar' : 'Siguiente'"
                type="submit"
                class="q-ml-sm"
                :loading="loading"
                :icon-right="isLastStep ? 'save' : 'arrow_forward'"
              />
            </div>
          </div>
        </q-form>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script>
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import markerIcon2xUrl from 'leaflet/dist/images/marker-icon-2x.png'
import markerIconUrl from 'leaflet/dist/images/marker-icon.png'
import markerShadowUrl from 'leaflet/dist/images/marker-shadow.png'

const ORURO_CENTER = [-17.967, -67.106]

L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2xUrl,
  iconUrl: markerIconUrl,
  shadowUrl: markerShadowUrl
})

const visibleMarkerIcon = L.divIcon({
  className: 'cliente-marker-wrap',
  html: '<div class="cliente-marker-pin"></div>',
  iconSize: [28, 28],
  iconAnchor: [14, 14]
})

const emptyCliente = () => ({
  nombre: '', nit: '', ci: '', telefono: '', direccion: '', complemento: '', codigoTipoDocumentoIdentidad: '', email: '',
  id_externo: '', cod_ciudad: '', cod_nacio: '', cod_car: null, est_civ: '', edad: '', empresa: '', categoria: null,
  imp_pieza: null, ci_vend: '', list_blanck: false, motivo_list_black: '', list_black: false, tipo_paciente: '', supra_canal: '',
  canal: '', subcanal: '', zona: '', latitud: ORURO_CENTER[0], longitud: ORURO_CENTER[1], transporte: '', territorio: '', codcli: null, clinew: '',
  venta_estado: 'INACTIVO', complto: '', tipodocu: null, lu: false, ma: false, mi: false, ju: false, vi: false, sa: false, do: false,
  correcli: '', canmayni: false, baja: false, profecion: '', waths: false, ctas_activo: false, ctas_mont: null, ctas_dias: null,
  sexo: '', noesempre: false, tarjeta: '', puede_credito: true, fotos: []
})

export default {
  name: 'AltaClientePage',
  computed: {
    tabsOrder () {
      return ['basico', 'ubicacion', 'fotos']
    },
    currentStepIndex () {
      return this.tabsOrder.indexOf(this.tab)
    },
    isLastStep () {
      return this.currentStepIndex === this.tabsOrder.length - 1
    }
  },
  data () {
    return {
      loading: false,
      tab: 'basico',
      cliente: emptyCliente(),
      previewFotos: [],
      map: null,
      marker: null,
      layersControl: null,
      mapReady: false,
      vendedores: []
    }
  },
  mounted () {
    this.vendedoresGet()
  },
  watch: {
    tab (val) {
      if (val === 'ubicacion') {
        this.$nextTick(() => this.initMap())
      }
    },
    'cliente.latitud' () {
      this.syncMarkerFromModel()
    },
    'cliente.longitud' () {
      this.syncMarkerFromModel()
    }
  },
  methods: {
    getAuthUser () {
      const storeUser = this.$store?.getters?.['auth/user'] || this.$store?.state?.auth?.user
      if (storeUser) return storeUser
      try {
        return JSON.parse(localStorage.getItem('user') || 'null') || {}
      } catch {
        return {}
      }
    },
    goNext () {
      const nextTab = this.tabsOrder[this.currentStepIndex + 1]
      if (nextTab) this.tab = nextTab
    },
    goPrev () {
      const prevTab = this.tabsOrder[this.currentStepIndex - 1]
      if (prevTab) this.tab = prevTab
    },
    handlePrimaryAction () {
      if (this.isLastStep) {
        this.guardarCliente()
        return
      }
      this.goNext()
    },
    vendedorAvatarUrl (vendedor) {
      const avatar = vendedor?.avatar || 'default.png'
      return `${this.$url}../images/${avatar}`
    },
    fotoUrl (pathOrBlob) {
      if (!pathOrBlob) return ''
      if (pathOrBlob.startsWith('blob:')) return pathOrBlob
      return `${this.$url}../${pathOrBlob}`
    },
    async vendedoresGet () {
      try {
        const res = await this.$axios.get('users')
        const users = Array.isArray(res.data) ? res.data : []
        this.vendedores = users
          .filter(u => !!u?.username)
          .map(u => ({
            id: u.id,
            name: u.name || u.username,
            username: u.username,
            avatar: u.avatar || 'default.png',
            label: `${u.name || u.username} (@${u.username})`
          }))
      } catch (e) {
        this.vendedores = []
      }
    },
    initMap () {
      if (!this.$refs.mapRef) return
      const hasCoords = this.hasValidCoordinates()
      const lat = hasCoords ? Number(this.cliente.latitud) : ORURO_CENTER[0]
      const lng = hasCoords ? Number(this.cliente.longitud) : ORURO_CENTER[1]

      if (!this.map) {
        this.map = L.map(this.$refs.mapRef, {
          center: [lat, lng],
          zoom: hasCoords ? 15 : 13
        })

        const googleRoad = L.tileLayer('https://mt1.google.com/vt/lyrs=r&x={x}&y={y}&z={z}', {
          attribution: 'Map data © Google',
          maxZoom: 21
        })
        const googleSat = L.tileLayer('https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
          attribution: 'Map data © Google',
          maxZoom: 21
        })
        const googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
          attribution: 'Map data © Google',
          maxZoom: 21
        })
        const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap contributors',
          maxZoom: 19
        })

        googleRoad.addTo(this.map)
        this.layersControl = L.control.layers({
          'Google Calle': googleRoad,
          'Google Satelite': googleSat,
          'Google Hibrido': googleHybrid,
          OpenStreetMap: osm
        }).addTo(this.map)

        this.map.on('click', (e) => {
          this.setLatLng(e.latlng.lat, e.latlng.lng, false)
        })
      }

      this.map.invalidateSize()
      this.map.setView([lat, lng], hasCoords ? 15 : 13)
      this.syncMarkerFromModel()
      this.mapReady = true
    },
    hasValidCoordinates () {
      const lat = Number(this.cliente.latitud)
      const lng = Number(this.cliente.longitud)
      return Number.isFinite(lat) && Number.isFinite(lng)
    },
    coordsLabel () {
      if (!this.hasValidCoordinates()) return 'Sin coordenadas'
      return `Lat: ${Number(this.cliente.latitud).toFixed(6)} | Lng: ${Number(this.cliente.longitud).toFixed(6)}`
    },
    setLatLng (lat, lng, fly = true) {
      this.cliente.latitud = Number(lat.toFixed(7))
      this.cliente.longitud = Number(lng.toFixed(7))
      this.syncMarkerFromModel(fly)
    },
    syncMarkerFromModel (fly = false) {
      if (!this.map) return

      if (!this.hasValidCoordinates()) {
        if (this.marker) {
          this.map.removeLayer(this.marker)
          this.marker = null
        }
        return
      }

      const latlng = [Number(this.cliente.latitud), Number(this.cliente.longitud)]
      if (!this.marker) {
        this.marker = L.marker(latlng, { draggable: true, icon: visibleMarkerIcon }).addTo(this.map)
        this.marker.on('dragend', (e) => {
          const point = e.target.getLatLng()
          this.setLatLng(point.lat, point.lng, false)
        })
      } else {
        this.marker.setLatLng(latlng)
      }

      if (fly) {
        this.map.flyTo(latlng, Math.max(this.map.getZoom(), 15))
      }
    },
    centerMap () {
      if (!this.map || !this.hasValidCoordinates()) return
      this.syncMarkerFromModel(true)
    },
    goToOruro () {
      this.setLatLng(ORURO_CENTER[0], ORURO_CENTER[1], true)
    },
    openGoogleMaps () {
      if (!this.hasValidCoordinates()) return
      const lat = Number(this.cliente.latitud)
      const lng = Number(this.cliente.longitud)
      window.open(`https://www.google.com/maps/search/?api=1&query=${lat},${lng}`, '_blank')
    },
    useCurrentLocation () {
      if (!navigator.geolocation) {
        this.$alert.error('Geolocalizacion no disponible')
        return
      }
      navigator.geolocation.getCurrentPosition((pos) => {
        this.setLatLng(pos.coords.latitude, pos.coords.longitude, true)
      }, () => this.$alert.error('No se pudo obtener ubicacion'))
    },
    toWebP (file) {
      return new Promise(resolve => {
        const img = new Image()
        const url = URL.createObjectURL(file)
        img.onload = () => {
          const MAX = 800
          let w = img.naturalWidth
          let h = img.naturalHeight
          if (w > MAX || h > MAX) {
            if (w >= h) { h = Math.round(h * MAX / w); w = MAX }
            else { w = Math.round(w * MAX / h); h = MAX }
          }
          const canvas = document.createElement('canvas')
          canvas.width = w
          canvas.height = h
          canvas.getContext('2d').drawImage(img, 0, 0, w, h)
          URL.revokeObjectURL(url)
          canvas.toBlob(blob => {
            const name = file.name.replace(/\.[^.]+$/, '.webp')
            resolve(new File([blob], name, { type: 'image/webp' }))
          }, 'image/webp', 0.82)
        }
        img.src = url
      })
    },
    async onFotosChange (e) {
      const files = Array.from(e.target.files || [])
      const existingCount = this.previewFotos.length
      const available = Math.max(0, 3 - existingCount)
      const selected = files.slice(0, available)
      e.target.value = ''

      const converted = await Promise.all(selected.map(f => this.toWebP(f)))

      converted.forEach(f => {
        f.__preview = URL.createObjectURL(f)
        this.previewFotos.push(f.__preview)
      })

      const current = this.cliente.fotos_files || []
      this.cliente.fotos_files = [...current, ...converted]
    },
    removeFoto (index) {
      const current = this.previewFotos[index]
      if (typeof current === 'string' && current.startsWith('blob:')) {
        URL.revokeObjectURL(current)
        const files = this.cliente.fotos_files || []
        const i = this.previewFotos.slice(0, index).filter(x => x.startsWith('blob:')).length
        files.splice(i, 1)
        this.cliente.fotos_files = files
      } else {
        const remove = this.cliente.remove_fotos || []
        remove.push(current)
        this.cliente.remove_fotos = remove
      }
      this.previewFotos.splice(index, 1)
    },
    async guardarCliente () {
      this.loading = true
      const authUser = this.getAuthUser()
      // La relacion vendedor usa username; como respaldo mantenemos ci si ese dato viene en sesiones antiguas.
      this.cliente.ci_vend = this.cliente.ci_vend || authUser.username || authUser.ci || ''
      this.cliente.venta_estado = 'INACTIVO'
      try {
        const fd = new FormData()
        const c = this.cliente

        const fields = [
          'nombre', 'nit', 'ci', 'telefono', 'direccion', 'complemento', 'codigoTipoDocumentoIdentidad', 'email',
          'id_externo', 'cod_ciudad', 'cod_nacio', 'cod_car', 'est_civ', 'edad', 'empresa', 'categoria',
          'imp_pieza', 'ci_vend', 'list_blanck', 'motivo_list_black', 'list_black', 'tipo_paciente',
          'supra_canal', 'canal', 'subcanal', 'zona', 'latitud', 'longitud', 'transporte', 'territorio',
          'codcli', 'clinew', 'venta_estado', 'complto', 'tipodocu', 'lu', 'ma', 'mi', 'ju', 'vi', 'sa', 'do',
          'correcli', 'canmayni', 'baja', 'profecion', 'waths', 'ctas_activo', 'ctas_mont', 'ctas_dias',
          'sexo', 'noesempre', 'tarjeta', 'puede_credito'
        ]

        fields.forEach(k => {
          if (c[k] !== undefined && c[k] !== null) {
            if (typeof c[k] === 'boolean') fd.append(k, c[k] ? '1' : '0')
            else fd.append(k, c[k])
          }
        })

        ;(c.remove_fotos || []).forEach((f, i) => fd.append(`remove_fotos[${i}]`, f))
        ;(c.fotos_files || []).forEach((f, i) => fd.append(`fotos[${i}]`, f))

        await this.$axios.post('clientes', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        this.$alert.success('Cliente inactivo creado')

        this.$router.back()
      } catch (e) {
        this.$alert.error(e.response?.data?.message || e.response?.data?.error || 'No se pudo guardar cliente')
      } finally {
        this.loading = false
      }
    }
  },
  beforeUnmount () {
    if (this.map) {
      this.map.remove()
      this.map = null
      this.marker = null
      this.layersControl = null
    }
  }
}
</script>

<style scoped>
.seller-card {
  background: linear-gradient(135deg, #eef6ff 0%, #ffffff 100%);
  border-color: #c9ddff;
}
.map-canvas {
  height: 400px;
  border-radius: 12px;
  border: 1px solid #d7e3f8;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
}
:deep(.cliente-marker-wrap) {
  background: transparent;
  border: 0;
}
:deep(.cliente-marker-pin) {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #e53935;
  border: 3px solid #fff;
  box-shadow: 0 0 0 2px rgba(229, 57, 53, 0.35), 0 4px 12px rgba(0, 0, 0, 0.35);
}
</style>
