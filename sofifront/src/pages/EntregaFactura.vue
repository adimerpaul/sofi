<template>
  <!-- Entregas del dia sobre facturacion: el reemplazo de /entrega (que leia
       la ruta del sistema anterior). Arriba los filtros, a la izquierda el
       avance de cada camion, a la derecha el mapa y abajo cada comprobante. -->
  <q-page class="q-pa-xs entrega-factura">
    <div class="row q-col-gutter-xs items-center q-mb-xs">
      <div class="col-auto">
        <q-input v-model="fecha" type="date" dense outlined label="Día de reparto" style="width: 150px"
                 @update:model-value="cargar"/>
      </div>
      <div class="col-auto">
        <q-btn-toggle
          v-model="tipo" dense no-caps unelevated toggle-color="primary" color="grey-3" text-color="grey-9"
          :options="opcionesTipo" @update:model-value="cargar"
        />
      </div>
      <div class="col-auto">
        <q-btn dense flat round icon="refresh" color="primary" :loading="cargando" @click="cargar">
          <q-tooltip>Actualizar</q-tooltip>
        </q-btn>
      </div>
      <div class="col text-caption text-grey-7 ellipsis">
        Jornada {{ jornada }}
      </div>
      <div class="col-auto row q-gutter-xs">
        <q-chip dense square color="blue-grey-1" text-color="blue-grey-9" icon="receipt_long">
          {{ totales.cerrados }}/{{ totales.total }} · {{ totales.porcentaje }}%
        </q-chip>
        <q-chip dense square color="green-1" text-color="green-9" icon="payments">
          Ef. {{ money(totales.efectivo) }}
        </q-chip>
        <q-chip dense square color="indigo-1" text-color="indigo-9" icon="qr_code_2">
          QR {{ money(totales.qr) }}
        </q-chip>
        <q-chip dense square color="orange-1" text-color="orange-9" icon="schedule">
          Créd. {{ money(totales.credito) }}
        </q-chip>
      </div>
    </div>

    <div class="row q-col-gutter-xs">
      <!-- Avance por camion. Tocando uno se filtran el mapa y la lista. -->
      <div class="col-12 col-md-5">
        <q-card flat bordered>
          <q-markup-table dense flat separator="horizontal" class="tabla-camiones">
            <thead>
              <tr>
                <th class="text-left">Camión</th>
                <th class="text-left" style="min-width: 150px">Avance</th>
                <th class="text-right">Monto</th>
                <th class="text-right">Ef. / QR</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!camiones.length">
                <td colspan="5" class="text-center text-grey-6 q-pa-md">
                  {{ cargando ? 'Cargando…' : 'Sin comprobantes en camiones esta jornada' }}
                </td>
              </tr>
              <tr
                v-for="c in camiones" :key="c.placa" class="cursor-pointer"
                :class="placa === c.placa ? 'bg-blue-1' : ''" @click="elegirCamion(c.placa)"
              >
                <td>
                  <div class="text-weight-bold">{{ c.placa }}</div>
                  <div class="row q-gutter-xs no-wrap">
                    <span v-for="z in c.zonas" :key="z.zona + z.hex" class="zona-chip"
                          :style="estiloZona(z.hex)">
                      {{ z.zona }} {{ z.cantidad }}
                    </span>
                  </div>
                </td>
                <td>
                  <!-- Una barra partida por estado, en el orden en que importa. -->
                  <div class="barra-estados">
                    <div v-for="e in estados" :key="e.clave" :class="'bg-' + e.color"
                         :style="{ width: (c.total ? c[e.campo] * 100 / c.total : 0) + '%' }">
                      <q-tooltip>{{ e.label }}: {{ c[e.campo] }}</q-tooltip>
                    </div>
                  </div>
                  <div class="text-caption leyenda-avance">
                    <b>{{ c.cerrados }}/{{ c.total }}</b> · {{ c.porcentaje }}%
                    <span class="text-green-8">E {{ c.entregados }}</span>
                    <span v-if="c.retorno" class="text-deep-orange-8">R {{ c.retorno }}</span>
                    <span v-if="c.no_entregados" class="text-amber-9">N {{ c.no_entregados }}</span>
                    <span v-if="c.rechazados" class="text-red-8">X {{ c.rechazados }}</span>
                    <span v-if="c.pendientes" class="text-grey-7">P {{ c.pendientes }}</span>
                  </div>
                </td>
                <td class="text-right">{{ money(c.monto) }}</td>
                <td class="text-right text-caption">
                  <div class="text-green-9">{{ money(c.efectivo) }}</div>
                  <div class="text-indigo-9">{{ money(c.qr) }}</div>
                </td>
                <td class="text-no-wrap">
                  <q-btn dense flat round size="sm" icon="download" color="green-8" @click.stop="excel(c.placa)">
                    <q-tooltip>Excel</q-tooltip>
                  </q-btn>
                  <q-btn dense flat round size="sm" icon="print" color="blue-grey-8" @click.stop="imprimir(c.placa)">
                    <q-tooltip>Imprimir</q-tooltip>
                  </q-btn>
                </td>
              </tr>
            </tbody>
          </q-markup-table>
          <div v-if="placa" class="row items-center q-px-sm q-py-xs bg-blue-1 text-caption">
            Mostrando solo <b class="q-mx-xs">{{ placa }}</b>
            <q-space/>
            <q-btn dense flat no-caps size="sm" color="primary" icon="clear" label="Ver todos" @click="placa = null"/>
          </div>
        </q-card>
      </div>

      <!-- El mapa: una marca por comprobante, pintada por su estado y con el
           borde del color de su zona. Las encimadas se abren en circulo con
           una linea a su punto real, igual que en la pantalla del caminero. -->
      <div class="col-12 col-md-7">
        <q-card flat bordered class="mapa-caja">
          <l-map v-model:zoom="zoom" :center="centro" :use-global-leaflet="true" @ready="mapaListo">
            <l-tile-layer
              url="https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}" :subdomains="['mt0', 'mt1', 'mt2', 'mt3']"
              :max-zoom="20" attribution="Google"
            />
            <template v-for="m in marcas" :key="'guia-' + m.clave">
              <template v-if="m.separada">
                <l-polyline :lat-lngs="[m.real, m.latLng]" color="#37474F" :weight="1.5" dash-array="3 3"/>
                <l-circle-marker :lat-lng="m.real" :radius="3" color="#fff" :weight="1" fill-color="#37474F" :fill-opacity="1"/>
              </template>
            </template>
            <l-marker v-for="m in marcas.filter(x => x.grupo)" :key="m.clave" :lat-lng="m.latLng" @click="acercar(m.latLng)">
              <l-icon :icon-size="[34, 34]" :icon-anchor="[17, 17]" class-name="">
                <div class="marca-grupo" :title="m.grupo + ' comprobantes · tocar para acercar'">{{ m.grupo }}</div>
              </l-icon>
            </l-marker>
            <l-marker v-for="m in marcas.filter(x => !x.grupo)" :key="m.clave" :lat-lng="m.latLng" @click="enfocar(m.comprobante, false)">
              <l-icon :icon-size="[26, 26]" :icon-anchor="[13, 13]" class-name="">
                <!-- title y no l-tooltip: el tooltip de vue-leaflet falla al
                     quitar marcas (al filtrar) con getPopup is not a function. -->
                <div class="marca" :class="'bg-' + colorEstado(m.comprobante.estado)"
                     :style="{ borderColor: m.comprobante.zona_hex || '#fff' }"
                     :title="m.comprobante.cliente + ' · ' + m.comprobante.placa + ' · Bs ' + money(m.comprobante.total) + ' · ' + m.comprobante.estado">
                  {{ m.numero }}
                </div>
              </l-icon>
            </l-marker>
          </l-map>
          <div class="leyenda-mapa row q-gutter-xs">
            <span v-for="e in estados" :key="e.clave" class="row items-center no-wrap">
              <span class="punto-leyenda" :class="'bg-' + e.color"/> {{ e.label }}
            </span>
          </div>
        </q-card>
      </div>
    </div>

    <!-- Cada comprobante de la jornada. Tocando una fila se centra en el mapa. -->
    <q-card flat bordered class="q-mt-xs">
      <div class="row items-center q-col-gutter-xs q-pa-xs">
        <div class="col-auto text-subtitle2 text-weight-bold">
          Comprobantes <span class="text-grey-7">({{ filas.length }})</span>
        </div>
        <div class="col-auto">
          <q-btn-toggle
            v-model="estado" dense no-caps unelevated size="sm" toggle-color="primary" color="grey-2" text-color="grey-9"
            :options="opcionesEstado"
          />
        </div>
        <q-space/>
        <div class="col-12 col-sm-3">
          <q-input v-model="buscar" dense outlined clearable placeholder="Cliente, nro o pedido">
            <template v-slot:prepend><q-icon name="search" size="18px"/></template>
          </q-input>
        </div>
      </div>
      <q-table
        dense flat :rows="filas" :columns="columnas" row-key="factura_id"
        :rows-per-page-options="[0]" hide-pagination class="tabla-comprobantes"
        :loading="cargando"
      >
        <template v-slot:body="props">
          <q-tr :props="props" class="cursor-pointer" :class="claseFila(props.row)" @click="enfocar(props.row, true)">
            <q-td key="numero" :props="props">{{ numeroDe(props.row) }}</q-td>
            <q-td key="nro" :props="props">
              <div class="text-weight-medium">#{{ props.row.factura_id }}</div>
              <div class="text-caption text-grey-7">
                {{ props.row.tipo_comprobante === 'FACTURA' ? 'Fact. ' + props.row.nro_factura : 'Venta' }}
              </div>
            </q-td>
            <q-td key="pedido" :props="props">
              <div>{{ props.row.pedido_nro || 'Directa' }}</div>
              <div class="text-caption text-grey-7">{{ nombreTipo(props.row.pedido_tipo) }}</div>
            </q-td>
            <q-td key="cliente" :props="props" class="col-cliente">
              <div class="text-weight-medium ellipsis">{{ props.row.cliente }}</div>
              <div class="text-caption text-grey-7 ellipsis">{{ props.row.direccion }}</div>
            </q-td>
            <q-td key="camion" :props="props">
              <div class="row items-center no-wrap">
                <span class="cuadro-zona q-mr-xs" :style="{ background: props.row.zona_hex || '#E0E0E0' }"/>
                {{ props.row.placa }}
              </div>
              <div class="text-caption text-grey-7">{{ props.row.zona || 'Sin zona' }}</div>
            </q-td>
            <q-td key="total" :props="props">
              <div class="text-weight-bold">{{ money(props.row.total) }}</div>
              <div class="text-caption text-grey-7">{{ props.row.tipo_pago }}</div>
            </q-td>
            <q-td key="estado" :props="props">
              <q-badge :color="colorEstado(props.row.estado)" :label="props.row.estado"/>
              <div v-if="props.row.entrega_hora" class="text-caption text-grey-7">
                {{ props.row.entrega_hora }}<span v-if="props.row.caminero"> · {{ props.row.caminero }}</span>
              </div>
            </q-td>
            <q-td key="cobro" :props="props">
              <template v-if="props.row.efectivo || props.row.qr">
                <div v-if="props.row.efectivo" class="text-green-9">Ef. {{ money(props.row.efectivo) }}</div>
                <div v-if="props.row.qr" class="text-indigo-9">QR {{ money(props.row.qr) }}</div>
              </template>
              <span v-else-if="props.row.tipago" class="text-caption">{{ props.row.tipago }}</span>
              <span v-else class="text-grey-5">—</span>
            </q-td>
            <q-td key="carga" :props="props">
              <q-icon :name="props.row.carga === 'VERIFICADA' ? 'inventory' : props.row.carga === 'OBSERVADA' ? 'report' : 'pending'"
                      :color="props.row.carga === 'VERIFICADA' ? 'positive' : props.row.carga === 'OBSERVADA' ? 'orange' : 'grey-5'"
                      size="18px">
                <q-tooltip>Carga {{ props.row.carga.toLowerCase() }}<span v-if="props.row.canasta"> · canasta {{ props.row.canasta }}</span></q-tooltip>
              </q-icon>
            </q-td>
            <q-td key="observacion" :props="props" class="col-obs">
              <span class="text-caption">{{ props.row.observacion }}</span>
            </q-td>
            <q-td key="detalle" :props="props" auto-width>
              <q-btn dense flat round size="sm" :icon="abierto[props.row.factura_id] ? 'expand_less' : 'expand_more'"
                     :loading="cargandoDetalle === props.row.factura_id" @click.stop="alternarDetalle(props.row)"/>
            </q-td>
          </q-tr>
          <q-tr v-if="abierto[props.row.factura_id]" :props="props" class="bg-grey-1">
            <q-td colspan="100%" class="q-py-xs">
              <table class="tabla-detalle">
                <tr v-for="(d, i) in detalles[props.row.factura_id] || []" :key="i">
                  <td class="text-grey-7">{{ d.cod_prod }}</td>
                  <td>{{ d.nombre }}</td>
                  <td class="text-right">{{ cantidadTexto(d) }}</td>
                  <td class="text-right">{{ money(d.precio) }}</td>
                  <td class="text-right text-weight-medium">{{ money(d.subtotal) }}</td>
                </tr>
              </table>
            </q-td>
          </q-tr>
        </template>
      </q-table>
    </q-card>
  </q-page>
</template>

<script>
import { date } from 'quasar'
import { markRaw } from 'vue'
// Leaflet global: con el que carga vue-leaflet por su cuenta, el l-icon con
// contenido propio no se aplica y salen los pines azules por defecto.
import 'leaflet'
import { LMap, LTileLayer, LMarker, LIcon, LPolyline, LCircleMarker } from '@vue-leaflet/vue-leaflet'
import 'leaflet/dist/leaflet.css'
import xlsx from 'json-as-xlsx'
import { Printd } from 'printd'

const NOMBRES_TIPO = { NORMAL: 'Embutidos', POLLO: 'Pollo', CERDO: 'Cerdo', RES: 'Res', PODIUM: 'Podium y huevo' }

export default {
  name: 'EntregaFactura',
  components: { LMap, LTileLayer, LMarker, LIcon, LPolyline, LCircleMarker },
  data () {
    return {
      fecha: date.formatDate(new Date(), 'YYYY-MM-DD'),
      tipo: '',
      tipos: ['NORMAL', 'POLLO', 'CERDO', 'RES', 'PODIUM'],
      jornada: '',
      cargando: false,
      totales: { total: 0, cerrados: 0, porcentaje: 0, efectivo: 0, qr: 0, credito: 0 },
      camiones: [],
      comprobantes: [],
      // Camion elegido en la tabla; null = todos.
      placa: null,
      estado: '',
      buscar: '',
      abierto: {},
      detalles: {},
      cargandoDetalle: null,
      mapa: null,
      zoom: 13,
      centro: [-17.969721, -67.114493],
      // Estados en el orden de la barra, con su campo del resumen.
      estados: [
        { clave: 'ENTREGADO', label: 'Entregado', campo: 'entregados', color: 'green-6' },
        { clave: 'RETORNO PARCIAL', label: 'Retorno parcial', campo: 'retorno', color: 'deep-orange-5' },
        { clave: 'NO ENTREGADO', label: 'No entregado', campo: 'no_entregados', color: 'amber-7' },
        { clave: 'RECHAZADO', label: 'Rechazado', campo: 'rechazados', color: 'red-6' },
        { clave: 'PENDIENTE', label: 'Pendiente', campo: 'pendientes', color: 'blue-grey-4' }
      ],
      columnas: [
        { name: 'numero', label: '#', field: 'factura_id', align: 'center' },
        { name: 'nro', label: 'NRO', field: 'factura_id', align: 'left', sortable: true },
        { name: 'pedido', label: 'PEDIDO', field: 'pedido_nro', align: 'left', sortable: true },
        { name: 'cliente', label: 'CLIENTE', field: 'cliente', align: 'left', sortable: true },
        { name: 'camion', label: 'CAMIÓN', field: 'placa', align: 'left', sortable: true },
        { name: 'total', label: 'TOTAL', field: 'total', align: 'right', sortable: true },
        { name: 'estado', label: 'ESTADO', field: 'estado', align: 'left', sortable: true },
        { name: 'cobro', label: 'COBRO', field: 'efectivo', align: 'left' },
        { name: 'carga', label: 'CARGA', field: 'carga', align: 'center' },
        { name: 'observacion', label: 'OBSERVACIÓN', field: 'observacion', align: 'left' },
        { name: 'detalle', label: '', field: 'factura_id' }
      ]
    }
  },
  computed: {
    opcionesTipo () {
      return [{ label: 'Todos', value: '' }].concat(this.tipos.map(t => ({ label: NOMBRES_TIPO[t] || t, value: t })))
    },
    // Los del camion elegido, que son los que cuentan para el mapa y la lista.
    delCamion () {
      return this.placa ? this.comprobantes.filter(c => c.placa === this.placa) : this.comprobantes
    },
    opcionesEstado () {
      return [{ label: 'Todos ' + this.delCamion.length, value: '' }].concat(this.estados
        .map(e => ({ label: e.label + ' ' + this.delCamion.filter(c => c.estado === e.clave).length, value: e.clave })))
    },
    filas () {
      const texto = String(this.buscar || '').toLowerCase().trim()
      return this.delCamion.filter(c => {
        if (this.estado && c.estado !== this.estado) return false
        if (!texto) return true
        return String(c.cliente).toLowerCase().includes(texto) ||
          String(c.factura_id).includes(texto) || String(c.pedido_nro || '').includes(texto) ||
          String(c.nro_factura || '').includes(texto)
      })
    },
    /**
     * Una marca por comprobante con coordenada. Las que en pantalla quedarian
     * una encima de otra se abren en circulo alrededor de su lugar, medido en
     * pixeles del zoom actual: al acercar vuelven a su sitio.
     */
    marcas () {
      const puntos = this.filas.filter(c => c.lat && c.lng)
        .map(c => ({ comprobante: c, lat: c.lat, lng: c.lng }))
      const metrosPorPixel = 156543.03 * Math.cos(-17.97 * Math.PI / 180) / Math.pow(2, this.zoom || 13)
      const choque = 28 * metrosPorPixel
      const montones = []
      puntos.forEach(p => {
        const monton = montones.find(m => m.some(o => this.metros(o, p) < choque))
        if (monton) monton.push(p)
        else montones.push([p])
      })

      const marcas = []
      montones.forEach(monton => {
        const lat = monton.reduce((s, p) => s + p.lat, 0) / monton.length
        const lng = monton.reduce((s, p) => s + p.lng, 0) / monton.length
        // Muchas juntas no se abren (serian rayas por todo el mapa): van en un
        // globo con la cantidad, que al tocarlo acerca.
        if (monton.length > 6) {
          marcas.push({
            clave: 'grupo-' + monton.map(p => p.comprobante.factura_id).join('-'),
            latLng: [lat, lng],
            grupo: monton.length,
            comprobante: monton[0].comprobante
          })
          return
        }
        const radio = Math.max(22, (monton.length * 28) / (2 * Math.PI)) * metrosPorPixel
        monton.forEach((p, i) => {
          let latLng = [p.lat, p.lng]
          if (monton.length > 1) {
            const angulo = -Math.PI / 2 + (2 * Math.PI * i) / monton.length
            latLng = [
              lat + (radio * Math.sin(angulo)) / 111320,
              lng + (radio * Math.cos(angulo)) / (111320 * Math.cos(lat * Math.PI / 180))
            ]
          }
          marcas.push({
            clave: p.comprobante.factura_id,
            latLng,
            real: [p.lat, p.lng],
            separada: monton.length > 1,
            numero: this.numeroDe(p.comprobante),
            comprobante: p.comprobante
          })
        })
      })
      return marcas
    }
  },
  created () {
    this.cargar()
  },
  methods: {
    money (v) { return Number(v || 0).toFixed(2) },
    nombreTipo (tipo) { return tipo ? (NOMBRES_TIPO[tipo] || tipo) : '' },
    colorEstado (estado) {
      const e = this.estados.find(x => x.clave === estado)
      return e ? e.color : 'blue-grey-4'
    },
    // El numero de la marca y de la fila es el mismo, para ubicarse rapido.
    numeroDe (comprobante) {
      return this.delCamion.indexOf(comprobante) + 1
    },
    claseFila (c) {
      if (c.estado === 'RECHAZADO') return 'fila-rechazado'
      if (c.estado === 'NO ENTREGADO') return 'fila-no-entregado'
      if (c.estado === 'PENDIENTE') return ''
      return 'fila-entregado'
    },
    estiloZona (hex) {
      if (!hex) return { background: '#E0E0E0', color: '#424242' }
      const v = parseInt(hex.slice(1), 16)
      const luz = (0.299 * ((v >> 16) & 255) + 0.587 * ((v >> 8) & 255) + 0.114 * (v & 255)) / 255
      return { background: hex, color: luz > 0.6 ? '#212121' : '#fff' }
    },
    cantidadTexto (d) {
      const peso = Number(d.peso)
      return peso > 0 ? peso + ' kg' : Number(d.cantidad) + ' ' + (d.unidad || '')
    },
    metros (a, b) {
      const g = 111320
      return Math.hypot((a.lng - b.lng) * g * Math.cos(a.lat * Math.PI / 180), (a.lat - b.lat) * g)
    },
    cargar () {
      this.cargando = true
      this.$api.get('entrega-factura', { params: { fecha: this.fecha, tipo: this.tipo || undefined } })
        .then(res => {
          this.jornada = res.data.jornada
          this.tipos = res.data.tipos || this.tipos
          this.totales = res.data.totales
          this.camiones = res.data.camiones
          this.comprobantes = res.data.comprobantes
          this.abierto = {}
          if (this.placa && !this.camiones.some(c => c.placa === this.placa)) this.placa = null
          this.$nextTick(this.encuadrar)
        })
        .catch(err => {
          this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudieron cargar las entregas' })
        })
        .finally(() => { this.cargando = false })
    },
    elegirCamion (placa) {
      this.placa = this.placa === placa ? null : placa
      this.$nextTick(this.encuadrar)
    },
    mapaListo (mapa) {
      this.mapa = markRaw(mapa)
      setTimeout(() => { mapa.invalidateSize(); this.encuadrar() }, 200)
    },
    // Todos los puntos visibles entran en pantalla. Los que estan a mas de
    // 60 km del resto son coordenadas mal cargadas: no cuentan, si no el mapa
    // se aleja hasta mostrar medio pais.
    encuadrar () {
      const puntos = this.filas.filter(c => c.lat && c.lng)
      if (!this.mapa || !puntos.length) return
      const mediana = lista => { const o = lista.slice().sort((a, b) => a - b); return o[Math.floor(o.length / 2)] }
      const centro = { lat: mediana(puntos.map(c => c.lat)), lng: mediana(puntos.map(c => c.lng)) }
      const cerca = puntos.filter(c => this.metros(c, centro) < 60000).map(c => [c.lat, c.lng])
      this.mapa.fitBounds(cerca.length ? cerca : [[centro.lat, centro.lng]], { padding: [24, 24], maxZoom: 16 })
    },
    acercar (latLng) {
      if (this.mapa) this.mapa.setView(latLng, Math.min((this.zoom || 13) + 3, 19))
    },
    // Desde la lista se centra el mapa en el cliente; desde el mapa se abre
    // su detalle en la lista.
    enfocar (comprobante, desdeLista) {
      if (desdeLista && comprobante.lat && this.mapa) {
        this.mapa.setView([comprobante.lat, comprobante.lng], 18)
        return
      }
      this.buscar = String(comprobante.factura_id)
      this.abrirDetalle(comprobante)
    },
    alternarDetalle (comprobante) {
      if (this.abierto[comprobante.factura_id]) {
        this.abierto = { ...this.abierto, [comprobante.factura_id]: false }
        return
      }
      this.abrirDetalle(comprobante)
    },
    abrirDetalle (comprobante) {
      const id = comprobante.factura_id
      this.abierto = { ...this.abierto, [id]: true }
      if (this.detalles[id]) return
      this.cargandoDetalle = id
      this.$api.get('facturacion/' + id)
        .then(res => { this.detalles = { ...this.detalles, [id]: res.data.detalles || [] } })
        .catch(() => { this.$q.notify({ type: 'negative', message: 'No se pudo traer el detalle' }) })
        .finally(() => { this.cargandoDetalle = null })
    },
    deCamion (placa) {
      return this.comprobantes.filter(c => c.placa === placa)
    },
    // Una hoja por estado, como el reporte antiguo pero con lo cobrado.
    excel (placa) {
      const columnas = [
        { label: 'Nro', value: 'factura_id' },
        { label: 'Pedido', value: row => row.pedido_nro || 'DIRECTA' },
        { label: 'Tipo', value: row => this.nombreTipo(row.pedido_tipo) },
        { label: 'Cliente', value: 'cliente' },
        { label: 'Zona', value: 'zona' },
        { label: 'Total', value: 'total', format: '#,##0.00' },
        { label: 'Tipo pago', value: 'tipo_pago' },
        { label: 'Estado', value: 'estado' },
        { label: 'Efectivo', value: 'efectivo', format: '#,##0.00' },
        { label: 'QR', value: 'qr', format: '#,##0.00' },
        { label: 'Observación', value: row => row.observacion || '' }
      ]
      const filas = this.deCamion(placa)
      const hojas = [{ sheet: 'TODOS', columns: columnas, content: filas }].concat(this.estados
        .map(e => ({ sheet: e.label.toUpperCase().slice(0, 31), columns: columnas, content: filas.filter(c => c.estado === e.clave) }))
        .filter(h => h.content.length))
      xlsx(hojas, { fileName: 'Entregas ' + placa + ' ' + this.fecha, extraLength: 3 })
    },
    imprimir (placa) {
      const filas = this.deCamion(placa)
      const c = this.camiones.find(x => x.placa === placa) || {}
      const esc = t => String(t ?? '').replace(/[&<>"]/g, s => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[s]))
      const bloque = (titulo, lista) => !lista.length ? '' : `
        <h3>${esc(titulo)} (${lista.length})</h3>
        <table><tr><th>#</th><th>Nro</th><th>Pedido</th><th>Cliente</th><th>Zona</th><th>Total</th><th>Pago</th><th>Efectivo</th><th>QR</th><th>Obs.</th></tr>
        ${lista.map((r, i) => `<tr><td>${i + 1}</td><td>${r.factura_id}</td><td>${r.pedido_nro || 'DIR'}</td><td>${esc(r.cliente)}</td>
          <td>${esc(r.zona)}</td><td class="r">${this.money(r.total)}</td><td>${esc(r.tipago || r.tipo_pago)}</td>
          <td class="r">${this.money(r.efectivo)}</td><td class="r">${this.money(r.qr)}</td><td>${esc(r.observacion || '')}</td></tr>`).join('')}
        <tr><td colspan="5" class="r"><b>Total</b></td><td class="r"><b>${this.money(lista.reduce((s, r) => s + r.total, 0))}</b></td><td></td>
          <td class="r"><b>${this.money(lista.reduce((s, r) => s + r.efectivo, 0))}</b></td><td class="r"><b>${this.money(lista.reduce((s, r) => s + r.qr, 0))}</b></td><td></td></tr>
        </table>`
      const html = `<style>
          body{font-family:Arial,sans-serif;font-size:10px} h2{margin:0 0 2px;font-size:15px} h3{margin:10px 0 2px;font-size:12px}
          table{width:100%;border-collapse:collapse} th,td{border:1px solid #999;padding:2px 3px} th{background:#eee} .r{text-align:right}
        </style>
        <h2>ENTREGAS DEL DÍA · ${esc(placa)}</h2>
        <div>Jornada ${esc(this.jornada)}${this.tipo ? ' · ' + esc(this.nombreTipo(this.tipo)) : ''} ·
          ${c.cerrados}/${c.total} cerrados (${c.porcentaje}%) · Efectivo ${this.money(c.efectivo)} · QR ${this.money(c.qr)}</div>
        ${this.estados.map(e => bloque(e.label.toUpperCase(), filas.filter(r => r.estado === e.clave))).join('')}`
      const div = document.createElement('div')
      div.innerHTML = html
      new Printd().print(div)
    }
  }
}
</script>

<style scoped>
.entrega-factura :deep(.q-table th), .entrega-factura :deep(.q-table td) {
  padding: 2px 6px;
  font-size: 12px;
}
.tabla-camiones td { vertical-align: top; }
.zona-chip {
  font-size: 9.5px;
  font-weight: 700;
  border-radius: 3px;
  padding: 0 4px;
  line-height: 15px;
  white-space: nowrap;
}
.barra-estados {
  display: flex;
  height: 12px;
  border-radius: 6px;
  overflow: hidden;
  background: #eceff1;
}
.leyenda-avance span { margin-left: 4px; font-weight: 600; }
.mapa-caja { height: 380px; position: relative; overflow: hidden; }
.leyenda-mapa {
  position: absolute; bottom: 4px; left: 4px; z-index: 500;
  background: rgba(255, 255, 255, .9); border-radius: 4px; padding: 2px 6px; font-size: 11px;
}
.punto-leyenda { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 3px; }
.marca {
  width: 26px; height: 26px; border-radius: 50%;
  border: 3px solid #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .45);
  color: #fff; font-size: 10px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
}
.marca-grupo {
  width: 34px; height: 34px; border-radius: 50%;
  background: rgba(55, 71, 79, .85); border: 3px solid #fff; box-shadow: 0 1px 4px rgba(0, 0, 0, .5);
  color: #fff; font-size: 12px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
}
.cuadro-zona { display: inline-block; width: 10px; height: 10px; border-radius: 2px; }
.col-cliente { max-width: 220px; }
.col-obs { max-width: 180px; white-space: normal; }
.fila-entregado { background: #f1f8e9; }
.fila-no-entregado { background: #fff8e1; }
.fila-rechazado { background: #ffebee; }
.tabla-detalle { border-collapse: collapse; font-size: 11.5px; }
.tabla-detalle td { padding: 1px 8px; }
</style>
