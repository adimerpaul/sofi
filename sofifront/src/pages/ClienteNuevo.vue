<template>
  <q-page class="alta-page">
    <div class="alta-wrap">
      <!-- Encabezado con avance -->
      <div class="alta-header">
        <div class="row items-center no-wrap">
          <q-avatar size="40px" color="white" text-color="primary" icon="storefront"/>
          <div class="q-ml-sm col">
            <div class="text-subtitle1 text-weight-bold">Nuevo cliente</div>
            <div class="text-caption alta-header-sub">
              {{ terminado ? 'Enviado para aprobación' : 'Paso ' + (paso + 1) + ' de ' + pasos.length + ' · ' + pasos[paso].titulo }}
            </div>
          </div>
          <q-btn v-if="!terminado && hayDatos" flat round dense icon="restart_alt" color="white" @click="empezarDeNuevo">
            <q-tooltip>Empezar de nuevo</q-tooltip>
          </q-btn>
        </div>
        <q-linear-progress v-if="!terminado" :value="(paso + 1) / pasos.length" color="white" track-color="red-3"
                           rounded size="6px" class="q-mt-sm"/>
      </div>

      <!-- Pantalla final -->
      <q-card v-if="terminado" flat class="alta-card text-center">
        <q-icon name="check_circle" color="positive" size="72px"/>
        <div class="text-h6 q-mt-sm">¡Cliente creado!</div>
        <div class="text-body2 text-grey-8 q-mt-xs">
          <b>{{ creado.Nombres }}</b> (código {{ creado.Cod_Aut }}) quedó registrado a tu nombre.
        </div>
        <q-banner rounded class="bg-orange-1 text-orange-10 q-mt-md text-left">
          <template #avatar><q-icon name="hourglass_top" color="orange-9"/></template>
          Queda <b>pendiente de aprobación</b> de Ventas y de Logística.
        </q-banner>
        <q-btn unelevated rounded color="primary" icon="person_add" label="Crear otro cliente" no-caps
               class="full-width q-mt-lg alta-btn" @click="empezarDeNuevo(true)"/>
      </q-card>

      <!-- Pasos -->
      <q-card v-else flat class="alta-card">
        <transition :name="direccion" mode="out-in" @after-enter="alEntrarPaso">
          <div :key="paso" class="alta-paso">
            <div class="alta-pregunta">
              <q-icon :name="pasos[paso].icono" color="primary" size="28px" class="q-mb-xs"/>
              <div>{{ pasos[paso].pregunta }} <span v-if="pasos[paso].obligatorio !== false" class="text-negative">*</span></div>
              <div v-if="pasos[paso].ayuda" class="alta-ayuda">{{ pasos[paso].ayuda }}</div>
            </div>

            <!-- CI / NIT -->
            <div v-if="pasoId === 'ci'" class="row q-col-gutter-sm">
              <div class="col-8">
                <q-input ref="foco" v-model="f.Id" outlined label="CI o NIT" inputmode="numeric" maxlength="15"
                         class="alta-input" @keyup.enter="siguiente"/>
              </div>
              <div class="col-4">
                <q-input v-model="f.complto" outlined label="Compl." maxlength="5" class="alta-input"/>
              </div>
            </div>

            <!-- Nombre -->
            <q-input v-else-if="pasoId === 'nombre'" ref="foco" v-model="f.Nombres" outlined label="Nombre o razón social"
                     maxlength="70" counter class="alta-input" input-class="text-uppercase" @keyup.enter="siguiente"/>

            <!-- Contacto -->
            <div v-else-if="pasoId === 'contacto'" class="column q-gutter-md">
              <q-input ref="foco" v-model="f.Telf" outlined label="Número de celular *" type="tel" inputmode="numeric"
                       maxlength="15" class="alta-input" @keyup.enter="siguiente">
                <template #prepend><q-icon name="smartphone"/></template>
              </q-input>
              <q-input v-model="f.Correcli" outlined label="Correo electrónico (opcional)" type="email" maxlength="50"
                       class="alta-input" @keyup.enter="siguiente">
                <template #prepend><q-icon name="mail"/></template>
              </q-input>
            </div>

            <!-- Direccion -->
            <q-input v-else-if="pasoId === 'direccion'" ref="foco" v-model="f.Direccion" outlined type="textarea" autogrow
                     label="Dirección" maxlength="100" counter class="alta-input" input-class="text-uppercase"/>

            <!-- Supra canal y canal -->
            <div v-else-if="pasoId === 'canal'">
              <div v-for="supra in ['OFF', 'ON']" :key="supra" class="q-mb-md">
                <div class="alta-grupo">{{ supra === 'OFF' ? 'OFF · Venta para revender' : 'ON · Consumo en el local' }}</div>
                <div class="alta-opciones">
                  <div v-for="canal in catalogos.canales[supra] || []" :key="supra + canal" class="alta-opcion"
                       :class="{activa: f.SupraCanal === supra && f.Canal === canal}"
                       @click="elegirCanal(supra, canal)">
                    <q-icon :name="f.SupraCanal === supra && f.Canal === canal ? 'radio_button_checked' : 'radio_button_unchecked'"/>
                    <span>{{ canal }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Subcanal -->
            <div v-else-if="pasoId === 'subcanal'" class="alta-opciones">
              <div v-for="s in catalogos.subcanales" :key="s" class="alta-opcion" :class="{activa: f.subcanal === s}"
                   @click="elegir('subcanal', s)">
                <q-icon :name="f.subcanal === s ? 'radio_button_checked' : 'radio_button_unchecked'"/>
                <span>{{ s }}</span>
              </div>
            </div>

            <!-- Zona -->
            <div v-else-if="pasoId === 'zona'" class="alta-opciones alta-opciones-2">
              <div v-for="z in catalogos.zonas" :key="z" class="alta-opcion" :class="{activa: f.zona === z}"
                   @click="elegir('zona', z)">
                <q-icon :name="f.zona === z ? 'radio_button_checked' : 'radio_button_unchecked'"/>
                <span>{{ z }}</span>
              </div>
            </div>

            <!-- Ubicacion -->
            <div v-else-if="pasoId === 'ubicacion'">
              <q-btn unelevated rounded color="primary" icon="my_location" no-caps class="full-width alta-btn"
                     :loading="ubicando" label="Usar mi ubicación actual" @click="ubicarme"/>
              <div ref="mapa" class="alta-mapa q-mt-md"></div>
              <div class="text-caption text-grey-7 q-mt-xs">
                Toque el mapa o arrastre el punto para corregir.
                <span v-if="precision"> Precisión del GPS: ±{{ precision }} m.</span>
              </div>
              <div v-if="tieneUbicacion" class="alta-coords q-mt-sm">
                <q-icon name="place" color="primary"/> {{ f.Latitud }}, {{ f.longitud }}
              </div>
            </div>

            <!-- Foto de fachada -->
            <div v-else-if="pasoId === 'foto'" class="text-center">
              <input ref="camara" type="file" accept="image/*" capture="environment" style="display:none" @change="fotoTomada"/>
              <div v-if="fotoUrl" class="alta-foto">
                <img :src="fotoUrl" alt="Fachada">
              </div>
              <div v-else class="alta-foto-vacia" @click="$refs.camara.click()">
                <q-icon name="photo_camera" size="48px" color="grey-6"/>
                <div class="text-grey-7 q-mt-sm">Toque para tomar la foto</div>
              </div>
              <div class="row q-col-gutter-sm q-mt-sm">
                <div class="col">
                  <q-btn unelevated rounded color="primary" no-caps class="full-width alta-btn" :loading="procesandoFoto"
                         :icon="fotoUrl ? 'cameraswitch' : 'photo_camera'" :label="fotoUrl ? 'Tomar otra' : 'Tomar foto'"
                         @click="$refs.camara.click()"/>
                </div>
                <div class="col">
                  <q-btn outline rounded color="primary" no-caps class="full-width alta-btn" icon="image" label="Galería"
                         @click="elegirDeGaleria"/>
                </div>
              </div>
            </div>

            <!-- Dias de visita -->
            <div v-else-if="pasoId === 'dias'" class="alta-opciones">
              <div v-for="d in dias" :key="d.campo" class="alta-opcion" :class="{activa: f[d.campo]}"
                   @click="f[d.campo] = !f[d.campo]">
                <q-icon :name="f[d.campo] ? 'check_box' : 'check_box_outline_blank'"/>
                <span>{{ d.nombre }}</span>
              </div>
            </div>

            <!-- Resumen -->
            <div v-else-if="pasoId === 'resumen'">
              <q-list separator class="alta-resumen">
                <q-item v-for="r in resumen" :key="r.paso" clickable @click="irA(r.paso)">
                  <q-item-section avatar><q-icon :name="pasos[r.paso].icono" color="primary"/></q-item-section>
                  <q-item-section>
                    <q-item-label caption>{{ r.label }}</q-item-label>
                    <q-item-label class="text-weight-medium">{{ r.valor || '—' }}</q-item-label>
                  </q-item-section>
                  <q-item-section side><q-icon name="edit" size="18px" color="grey-6"/></q-item-section>
                </q-item>
              </q-list>
              <div v-if="fotoUrl" class="alta-foto alta-foto-chica q-mt-sm"><img :src="fotoUrl" alt="Fachada"></div>
            </div>

            <div v-if="error" class="alta-error q-mt-md">
              <q-icon name="error_outline"/> {{ error }}
            </div>
          </div>
        </transition>
      </q-card>

      <!-- Mis altas recientes -->
      <div v-if="misAltas.length" class="q-mt-lg">
        <div class="alta-grupo q-px-xs">Mis clientes creados</div>
        <q-card flat class="alta-card q-pa-none">
          <q-list separator>
            <q-item v-for="c in misAltas" :key="c.Cod_Aut">
              <q-item-section>
                <q-item-label class="text-weight-medium">{{ c.Cod_Aut }} · {{ c.Nombres }}</q-item-label>
                <q-item-label caption>{{ c.Canal }} · {{ c.zona }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <div class="column items-end q-gutter-xs">
                  <q-badge :color="Number(c.aprobado_ventas) ? 'positive' : 'orange-8'"
                           :label="'Ventas ' + (Number(c.aprobado_ventas) ? '✓' : 'pendiente')"/>
                  <q-badge :color="Number(c.aprobado_logistica) ? 'positive' : 'orange-8'"
                           :label="'Logística ' + (Number(c.aprobado_logistica) ? '✓' : 'pendiente')"/>
                </div>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card>
      </div>
    </div>

    <!-- Barra de navegacion fija abajo -->
    <div v-if="!terminado" class="alta-nav">
      <q-btn flat round icon="arrow_back" color="primary" size="lg" :disable="paso === 0 || enviando" @click="anterior"/>
      <q-btn v-if="pasoId !== 'resumen'" unelevated rounded color="primary" no-caps size="lg" class="alta-nav-main"
             icon-right="arrow_forward" label="Siguiente" @click="siguiente"/>
      <q-btn v-else unelevated rounded color="positive" no-caps size="lg" class="alta-nav-main" icon="send"
             label="Crear cliente" :loading="enviando" @click="enviar(false)"/>
    </div>
  </q-page>
</template>

<script>
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import {comprimirImagen} from 'src/utils/comprimirImagen.js'

const ORURO_CENTRO = [-17.967, -67.106]
const BORRADOR = 'clienteNuevoBorrador'

const DIAS = [
  {campo: 'lu', nombre: 'Lunes'}, {campo: 'Ma', nombre: 'Martes'}, {campo: 'Mi', nombre: 'Miércoles'},
  {campo: 'Ju', nombre: 'Jueves'}, {campo: 'Vi', nombre: 'Viernes'}, {campo: 'Sa', nombre: 'Sábado'},
  {campo: 'do', nombre: 'Domingo'}
]

// Las mismas preguntas del formulario de JotForm que reemplaza esta pantalla.
const PASOS = [
  {id: 'ci', titulo: 'Documento', icono: 'badge', pregunta: 'Carnet o NIT del cliente'},
  {id: 'nombre', titulo: 'Nombre', icono: 'storefront', pregunta: 'Nombre y razón social del cliente'},
  {id: 'contacto', titulo: 'Contacto', icono: 'smartphone', pregunta: 'Celular y correo'},
  {id: 'direccion', titulo: 'Dirección', icono: 'signpost', pregunta: 'Dirección', ayuda: 'Ej. Calle Santa Cruz entre 6 de Octubre y Potosí'},
  {id: 'canal', titulo: 'Canal', icono: 'category', pregunta: 'Supra canal y canal'},
  {id: 'subcanal', titulo: 'Subcanal', icono: 'sell', pregunta: 'Subcanal'},
  {id: 'zona', titulo: 'Zona', icono: 'map', pregunta: 'Zona'},
  {id: 'ubicacion', titulo: 'Ubicación', icono: 'place', pregunta: 'Coordenadas', ayuda: 'Párese en la puerta del negocio y use su ubicación'},
  {id: 'foto', titulo: 'Fachada', icono: 'photo_camera', pregunta: 'Foto de la fachada'},
  {id: 'dias', titulo: 'Visita', icono: 'event', pregunta: 'Días de visita', ayuda: 'Puede marcar varios'},
  {id: 'resumen', titulo: 'Revisar', icono: 'fact_check', pregunta: 'Revise los datos antes de crear', obligatorio: false}
]

const formularioVacio = () => ({
  Id: '', complto: '', Nombres: '', Telf: '', Correcli: '', Direccion: '',
  SupraCanal: '', Canal: '', subcanal: '', zona: '', Latitud: '', longitud: '',
  lu: false, Ma: false, Mi: false, Ju: false, Vi: false, Sa: false, do: false
})

// Marcador dibujado con CSS: evita depender de las imagenes de leaflet en el build de Vite.
const iconoMarcador = L.divIcon({
  className: 'alta-marker-wrap',
  html: '<div class="alta-marker-pin"></div>',
  iconSize: [28, 28],
  iconAnchor: [14, 14]
})

export default {
  name: 'ClienteNuevo',
  data () {
    return {
      pasos: PASOS,
      dias: DIAS,
      paso: 0,
      direccion: 'avanza',
      f: formularioVacio(),
      foto: null,
      fotoUrl: '',
      procesandoFoto: false,
      ubicando: false,
      precision: null,
      catalogos: {canales: {}, subcanales: [], zonas: []},
      error: '',
      enviando: false,
      terminado: false,
      creado: {},
      misAltas: []
    }
  },
  computed: {
    pasoId () {
      return this.pasos[this.paso].id
    },
    tieneUbicacion () {
      return !!(parseFloat(this.f.Latitud) && parseFloat(this.f.longitud))
    },
    hayDatos () {
      return !!(this.f.Id || this.f.Nombres || this.foto)
    },
    resumen () {
      const diasElegidos = DIAS.filter(d => this.f[d.campo]).map(d => d.nombre).join(', ')
      const i = id => PASOS.findIndex(p => p.id === id)
      return [
        {paso: i('ci'), label: 'CI / NIT', valor: this.f.Id + (this.f.complto ? '-' + this.f.complto : '')},
        {paso: i('nombre'), label: 'Nombre', valor: this.f.Nombres.toUpperCase()},
        {paso: i('contacto'), label: 'Celular / correo', valor: this.f.Telf + (this.f.Correcli ? ' · ' + this.f.Correcli : '')},
        {paso: i('direccion'), label: 'Dirección', valor: this.f.Direccion.toUpperCase()},
        {paso: i('canal'), label: 'Canal', valor: this.f.Canal ? this.f.SupraCanal + ' - ' + this.f.Canal : ''},
        {paso: i('subcanal'), label: 'Subcanal', valor: this.f.subcanal},
        {paso: i('zona'), label: 'Zona', valor: this.f.zona},
        {paso: i('ubicacion'), label: 'Coordenadas', valor: this.tieneUbicacion ? this.f.Latitud + ', ' + this.f.longitud : ''},
        {paso: i('foto'), label: 'Foto de fachada', valor: this.foto ? 'Tomada' : ''},
        {paso: i('dias'), label: 'Días de visita', valor: diasElegidos}
      ]
    }
  },
  watch: {
    f: {
      deep: true,
      handler () {
        this.error = ''
        this.guardarBorrador()
      }
    },
    paso () {
      this.error = ''
    }
  },
  created () {
    if (!this.$store.getters['login/can']('clienteNuevo')) {
      this.$router.push('/')
      return
    }
    // Lo escrito se guarda en el celular: si se cierra la app no se pierde (la foto si).
    try {
      const borrador = JSON.parse(localStorage.getItem(BORRADOR) || 'null')
      if (borrador && borrador.f) {
        this.f = Object.assign(formularioVacio(), borrador.f)
        this.paso = Math.min(Number(borrador.paso) || 0, PASOS.length - 1)
      }
    } catch (e) { /* sin borrador */ }
    this.$api.get('clientes/catalogos').then(res => {
      this.catalogos = res.data
    }).catch(() => {
      this.$q.notify({type: 'negative', message: 'No se pudieron cargar las listas de canal y zona'})
    })
    this.cargarMisAltas()
  },
  mounted () {
    if (this.pasoId === 'ubicacion') this.$nextTick(() => this.iniciarMapa())
  },
  beforeUnmount () {
    if (this.map) this.map.remove()
    if (this.fotoUrl) URL.revokeObjectURL(this.fotoUrl)
  },
  methods: {
    // Con la animacion out-in el paso nuevo recien esta en pantalla aca: el mapa
    // necesita su contenedor ya visible para dibujarse.
    alEntrarPaso () {
      if (this.pasoId === 'ubicacion') this.iniciarMapa()
      if (this.$refs.foco && this.$refs.foco.focus) this.$refs.foco.focus()
    },
    guardarBorrador () {
      try {
        localStorage.setItem(BORRADOR, JSON.stringify({f: this.f, paso: this.paso}))
      } catch (e) { /* sin almacenamiento */ }
    },
    cargarMisAltas () {
      this.$api.get('clientes/mis-altas').then(res => {
        this.misAltas = res.data || []
      }).catch(() => {})
    },
    // Devuelve el problema del paso actual, o '' si se puede seguir.
    validar (id) {
      const f = this.f
      switch (id) {
        case 'ci': return String(f.Id).trim().length >= 4 ? '' : 'Escriba el carnet o NIT'
        case 'nombre': return String(f.Nombres).trim().length >= 3 ? '' : 'Escriba el nombre del cliente'
        case 'contacto':
          if (String(f.Telf).replace(/\D/g, '').length < 7) return 'El celular debe tener al menos 7 dígitos'
          if (f.Correcli && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.Correcli)) return 'El correo no es válido'
          return ''
        case 'direccion': return String(f.Direccion).trim().length >= 5 ? '' : 'Escriba la dirección'
        case 'canal': return f.Canal ? '' : 'Elija el canal'
        case 'subcanal': return f.subcanal ? '' : 'Elija el subcanal'
        case 'zona': return f.zona ? '' : 'Elija la zona'
        case 'ubicacion': return this.tieneUbicacion ? '' : 'Marque la ubicación del cliente'
        case 'foto': return this.foto ? '' : 'Tome la foto de la fachada'
        case 'dias': return DIAS.some(d => f[d.campo]) ? '' : 'Marque al menos un día'
        default: return ''
      }
    },
    siguiente () {
      this.error = this.validar(this.pasoId)
      if (this.error) return
      this.direccion = 'avanza'
      this.paso = Math.min(this.paso + 1, PASOS.length - 1)
      this.guardarBorrador()
    },
    anterior () {
      this.direccion = 'vuelve'
      this.paso = Math.max(this.paso - 1, 0)
    },
    irA (indice) {
      this.direccion = 'vuelve'
      this.paso = indice
    },
    // En las listas, elegir ya pasa a la siguiente pregunta.
    elegir (campo, valor) {
      this.f[campo] = valor
      setTimeout(() => this.siguiente(), 180)
    },
    elegirCanal (supra, canal) {
      this.f.SupraCanal = supra
      this.f.Canal = canal
      setTimeout(() => this.siguiente(), 180)
    },
    iniciarMapa () {
      if (!this.$refs.mapa) return
      if (this.map) this.map.remove()
      const centro = this.tieneUbicacion ? [parseFloat(this.f.Latitud), parseFloat(this.f.longitud)] : ORURO_CENTRO
      this.map = L.map(this.$refs.mapa, {center: centro, zoom: this.tieneUbicacion ? 18 : 13})
      const calle = L.tileLayer('https://mt1.google.com/vt/lyrs=r&x={x}&y={y}&z={z}', {maxZoom: 21, attribution: 'Map data © Google'})
      const satelite = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {maxZoom: 21, attribution: 'Map data © Google'})
      calle.addTo(this.map)
      L.control.layers({'Calle': calle, 'Satélite': satelite}).addTo(this.map)
      this.map.on('click', e => this.ponerUbicacion(e.latlng.lat, e.latlng.lng))
      this.marker = null
      if (this.tieneUbicacion) this.ponerUbicacion(centro[0], centro[1], false)
      setTimeout(() => this.map && this.map.invalidateSize(), 250)
      if (!this.tieneUbicacion) this.ubicarme()
    },
    ponerUbicacion (lat, lng, centrar = true) {
      // Latitud/longitud son varchar(15) en tbclientes.
      this.f.Latitud = lat.toFixed(7)
      this.f.longitud = lng.toFixed(7)
      if (!this.map) return
      if (!this.marker) {
        this.marker = L.marker([lat, lng], {draggable: true, icon: iconoMarcador}).addTo(this.map)
        this.marker.on('dragend', e => {
          const p = e.target.getLatLng()
          this.ponerUbicacion(p.lat, p.lng, false)
          this.precision = null
        })
      } else {
        this.marker.setLatLng([lat, lng])
      }
      if (centrar) this.map.setView([lat, lng], 18)
    },
    ubicarme () {
      if (!navigator.geolocation) {
        this.error = 'Este celular no permite obtener la ubicación: márquela en el mapa'
        return
      }
      this.ubicando = true
      navigator.geolocation.getCurrentPosition(pos => {
        this.ubicando = false
        this.precision = Math.round(pos.coords.accuracy)
        this.ponerUbicacion(pos.coords.latitude, pos.coords.longitude)
      }, () => {
        this.ubicando = false
        this.error = 'No se pudo obtener la ubicación: active el GPS o márquela en el mapa'
      }, {enableHighAccuracy: true, timeout: 15000, maximumAge: 0})
    },
    elegirDeGaleria () {
      // Sin capture: deja elegir de la galeria en vez de abrir la camara.
      const input = this.$refs.camara
      input.removeAttribute('capture')
      input.click()
      setTimeout(() => input.setAttribute('capture', 'environment'), 1000)
    },
    async fotoTomada (e) {
      const archivo = (e.target.files || [])[0]
      e.target.value = ''
      if (!archivo) return
      this.procesandoFoto = true
      try {
        // Se achica en el celular: sube rapido aunque la senal sea mala.
        this.foto = await comprimirImagen(archivo)
        if (this.fotoUrl) URL.revokeObjectURL(this.fotoUrl)
        this.fotoUrl = URL.createObjectURL(this.foto)
        this.error = ''
      } finally {
        this.procesandoFoto = false
      }
    },
    enviar (confirmado) {
      const pendiente = PASOS.findIndex(p => this.validar(p.id))
      if (pendiente >= 0) {
        this.irA(pendiente)
        this.$nextTick(() => { this.error = this.validar(PASOS[pendiente].id) })
        return
      }
      const fd = new FormData()
      Object.keys(this.f).forEach(k => {
        const v = this.f[k]
        fd.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : String(v).trim())
      })
      fd.append('foto', this.foto)
      if (confirmado) fd.append('confirmar_duplicado', '1')
      this.enviando = true
      this.$api.post('clientes/alta-preventista', fd, {headers: {'Content-Type': 'multipart/form-data'}}).then(res => {
        this.creado = res.data.cliente || {}
        this.terminado = true
        try { localStorage.removeItem(BORRADOR) } catch (e) { /* nada */ }
        this.cargarMisAltas()
        window.scrollTo({top: 0, behavior: 'smooth'})
      }).catch(err => {
        const data = err.response?.data || {}
        if (err.response?.status === 409 && data.duplicado) {
          this.confirmarDuplicado(data.clientes || [])
          return
        }
        const errores = data.errors ? Object.values(data.errors).flat() : []
        this.error = errores[0] || data.message || 'No se pudo crear el cliente. Revise su conexión e intente de nuevo.'
      }).finally(() => {
        this.enviando = false
      })
    },
    confirmarDuplicado (clientes) {
      const esc = v => String(v ?? '').replace(/[&<>"]/g, ch => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[ch]))
      const lista = clientes.map(c => `<li><b>${esc(c.Cod_Aut)}</b> · ${esc(c.Nombres)}</li>`).join('')
      this.$q.dialog({
        title: 'CI/NIT repetido',
        message: `Ya existe ${clientes.length === 1 ? 'un cliente' : clientes.length + ' clientes'} con el CI/NIT ` +
          `<b>${esc(this.f.Id)}</b>:<ul class="q-my-sm">${lista}</ul>¿Seguro que quiere crear otro cliente con el mismo CI/NIT?`,
        html: true,
        persistent: true,
        cancel: {label: 'No', flat: true, color: 'grey-8'},
        ok: {label: 'Sí, crear', color: 'orange-9', unelevated: true}
      }).onOk(() => this.enviar(true))
    },
    empezarDeNuevo (sinPreguntar) {
      const limpiar = () => {
        this.f = formularioVacio()
        this.foto = null
        if (this.fotoUrl) URL.revokeObjectURL(this.fotoUrl)
        this.fotoUrl = ''
        this.precision = null
        this.terminado = false
        this.direccion = 'vuelve'
        this.paso = 0
        try { localStorage.removeItem(BORRADOR) } catch (e) { /* nada */ }
      }
      if (sinPreguntar === true) return limpiar()
      this.$q.dialog({title: 'Empezar de nuevo', message: '¿Borrar lo cargado de este cliente?', cancel: true})
        .onOk(limpiar)
    }
  }
}
</script>

<style scoped>
.alta-page {
  background: linear-gradient(180deg, #ed1c24 0, #ed1c24 150px, #f4f5f7 150px);
  padding-bottom: 96px;
}
.alta-wrap {
  max-width: 560px;
  margin: 0 auto;
  padding: 12px;
}
.alta-header {
  color: #fff;
  padding: 4px 4px 14px;
}
.alta-header-sub {
  opacity: .9;
}
.alta-card {
  border-radius: 18px;
  box-shadow: 0 6px 24px rgba(0, 0, 0, .08);
  padding: 20px 16px;
  background: #fff;
}
.alta-paso {
  min-height: 300px;
}
.alta-pregunta {
  font-size: 20px;
  font-weight: 600;
  line-height: 1.25;
  margin-bottom: 18px;
  color: #212121;
}
.alta-ayuda {
  font-size: 13px;
  font-weight: 400;
  color: #757575;
  margin-top: 4px;
}
.alta-input :deep(.q-field__native),
.alta-input :deep(.q-field__label) {
  font-size: 17px;
}
.alta-grupo {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: #9e9e9e;
  margin-bottom: 8px;
}
.alta-opciones {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}
.alta-opciones-2 {
  grid-template-columns: 1fr 1fr;
}
.alta-opcion {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 13px 14px;
  border: 1.5px solid #e0e0e0;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 500;
  color: #424242;
  transition: border-color .15s, background .15s, transform .1s;
  user-select: none;
}
.alta-opcion .q-icon {
  font-size: 22px;
  color: #bdbdbd;
}
.alta-opcion:active {
  transform: scale(.98);
}
.alta-opcion.activa {
  border-color: #ed1c24;
  background: #fff1f1;
  color: #c4161c;
}
.alta-opcion.activa .q-icon {
  color: #ed1c24;
}
.alta-mapa {
  height: 300px;
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid #e0e0e0;
}
.alta-coords {
  font-family: monospace;
  font-size: 13px;
  background: #f5f5f5;
  border-radius: 8px;
  padding: 6px 10px;
}
.alta-foto {
  border-radius: 14px;
  overflow: hidden;
  background: #000;
}
.alta-foto img {
  display: block;
  width: 100%;
  max-height: 340px;
  object-fit: contain;
}
.alta-foto-chica img {
  max-height: 160px;
}
.alta-foto-vacia {
  height: 240px;
  border: 2px dashed #e0e0e0;
  border-radius: 14px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  background: #fafafa;
}
.alta-resumen {
  border: 1px solid #eee;
  border-radius: 12px;
}
.alta-error {
  color: #c62828;
  background: #ffebee;
  border-radius: 10px;
  padding: 10px 12px;
  font-weight: 500;
}
.alta-btn {
  min-height: 48px;
}
.alta-nav {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 10;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px calc(10px + env(safe-area-inset-bottom));
  background: #fff;
  box-shadow: 0 -4px 18px rgba(0, 0, 0, .08);
}
.alta-nav-main {
  flex: 1;
  max-width: 480px;
  margin-left: auto;
}
.avanza-enter-active, .avanza-leave-active, .vuelve-enter-active, .vuelve-leave-active {
  transition: opacity .18s, transform .18s;
}
.avanza-enter-from, .vuelve-leave-to {
  opacity: 0;
  transform: translateX(24px);
}
.avanza-leave-to, .vuelve-enter-from {
  opacity: 0;
  transform: translateX(-24px);
}
:deep(.alta-marker-pin) {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #ed1c24;
  border: 3px solid #fff;
  box-shadow: 0 0 0 2px rgba(237, 28, 36, .35), 0 4px 12px rgba(0, 0, 0, .35);
}
</style>
