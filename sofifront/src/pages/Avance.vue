<template>
  <!-- Avance del vendedor: sus visitas del dia y, sobre facturacion, cada
       venta suya con el camion que la lleva, si ya se entrego (pintado en el
       mapa) y su boleta para descargar. -->
  <q-page class="q-pa-xs avance">
    <div class="row q-col-gutter-xs items-center q-mb-xs">
      <div class="col-auto">
        <q-input v-model="fecha" type="date" dense outlined label="Día de reparto" style="width: 150px"
                 @update:model-value="generar"/>
      </div>
      <div class="col-auto">
        <q-btn dense flat round icon="refresh" color="primary" :loading="cargando" @click="generar">
          <q-tooltip>Actualizar</q-tooltip>
        </q-btn>
      </div>
      <div class="col text-caption text-grey-7 ellipsis">Jornada {{ jornada }}</div>
    </div>

    <!-- Visitas del dia -->
    <div class="row q-col-gutter-xs q-mb-xs">
      <div class="col-3" v-for="v in tarjetasVisita" :key="v.label">
        <q-card flat bordered class="text-center q-pa-xs">
          <div class="text-caption text-weight-bold">{{ v.label }}</div>
          <div class="text-h5 text-weight-bold" :class="v.clase">{{ v.valor }}</div>
        </q-card>
      </div>
    </div>

    <!-- Avance de entrega de sus ventas -->
    <q-card flat bordered class="q-pa-xs q-mb-xs">
      <div class="row items-center q-gutter-xs">
        <div class="text-subtitle2 text-weight-bold">Entrega de mis ventas</div>
        <q-space/>
        <q-chip dense square color="blue-grey-1" text-color="blue-grey-9" icon="local_shipping">
          {{ totales.cerrados }}/{{ totales.total }} · {{ totales.porcentaje }}%
        </q-chip>
        <q-chip dense square color="blue-grey-1" text-color="blue-grey-9" icon="payments">
          Bs {{ money(totales.monto) }}
        </q-chip>
      </div>
      <div class="barra-estados q-mt-xs">
        <div v-for="e in estados" :key="e.clave" :class="'bg-' + e.color"
             :style="{ width: (totales.total ? totales[e.campo] * 100 / totales.total : 0) + '%' }">
          <q-tooltip>{{ e.label }}: {{ totales[e.campo] }}</q-tooltip>
        </div>
      </div>
      <!-- Camiones que llevan sus ventas: tocando uno se filtra. -->
      <div class="row q-gutter-xs q-mt-xs">
        <q-chip
          v-for="c in camiones" :key="c.placa" dense clickable icon="local_shipping"
          :color="placa === c.placa ? 'primary' : 'grey-3'" :text-color="placa === c.placa ? 'white' : 'grey-9'"
          @click="elegirCamion(c.placa)"
        >
          {{ c.placa }} · {{ c.cerrados }}/{{ c.total }}
        </q-chip>
      </div>
    </q-card>

    <div class="row q-col-gutter-xs">
      <!-- Mapa: cada venta pintada segun si ya se entrego. -->
      <div class="col-12 col-md-6">
        <q-card flat bordered class="mapa-caja">
          <l-map v-model:zoom="zoom" :center="centro" :use-global-leaflet="true" @ready="mapaListo">
            <l-tile-layer
              url="https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}" :subdomains="['mt0', 'mt1', 'mt2', 'mt3']"
              :max-zoom="20" attribution="Google"
            />
            <l-marker v-for="m in marcas" :key="m.factura_id" :lat-lng="[m.lat, m.lng]" @click="enfocar(m, false)">
              <l-icon :icon-size="[26, 26]" :icon-anchor="[13, 13]" class-name="">
                <div class="marca" :class="'bg-' + colorEstado(m.estado)"
                     :title="m.cliente + ' · ' + m.placa + ' · Bs ' + money(m.total) + ' · ' + m.estado">
                  {{ numeroDe(m) }}
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

      <!-- Lista de sus ventas -->
      <div class="col-12 col-md-6">
        <q-card flat bordered>
          <div class="row items-center q-col-gutter-xs q-pa-xs">
            <div class="col-12">
              <q-btn-toggle
                v-model="estado" dense no-caps unelevated size="sm" toggle-color="primary" color="grey-2"
                text-color="grey-9" :options="opcionesEstado"
              />
            </div>
            <div class="col-12">
              <q-input v-model="buscar" dense outlined clearable placeholder="Cliente, nro o pedido">
                <template v-slot:prepend><q-icon name="search" size="18px"/></template>
              </q-input>
            </div>
          </div>
          <q-list separator dense class="lista-ventas">
            <q-item v-if="!filas.length">
              <q-item-section class="text-center text-grey-6">
                {{ cargando ? 'Cargando…' : 'Sin ventas tuyas en camiones esta jornada' }}
              </q-item-section>
            </q-item>
            <template v-for="v in filas" :key="v.factura_id">
              <q-item clickable :class="claseFila(v)" @click="enfocar(v, true)">
                <q-item-section avatar>
                  <div class="marca" :class="'bg-' + colorEstado(v.estado)">{{ numeroDe(v) }}</div>
                </q-item-section>
                <q-item-section>
                  <q-item-label class="text-weight-medium">{{ v.cliente }}</q-item-label>
                  <q-item-label caption>
                    #{{ v.factura_id }}
                    {{ v.tipo_comprobante === 'FACTURA' ? '· Fact. ' + v.nro_factura : '· Venta' }}
                    · Ped. {{ v.pedido_nro || 'Directa' }} · {{ v.placa }}
                  </q-item-label>
                  <q-item-label caption>
                    <q-badge :color="colorEstado(v.estado)" :label="v.estado"/>
                    <span v-if="v.entrega_hora" class="q-ml-xs">{{ v.entrega_hora }}</span>
                    <span v-if="v.observacion" class="q-ml-xs text-italic">{{ v.observacion }}</span>
                  </q-item-label>
                </q-item-section>
                <q-item-section side>
                  <div class="text-weight-bold text-black">{{ money(v.total) }}</div>
                  <div class="text-caption">{{ v.tipo_pago }}</div>
                  <div class="row no-wrap">
                    <q-btn dense flat round size="sm" icon="picture_as_pdf" color="red-8"
                           :loading="descargando === v.factura_id" @click.stop="descargarBoleta(v)">
                      <q-tooltip>Descargar boleta</q-tooltip>
                    </q-btn>
                    <q-btn dense flat round size="sm" :icon="abierto[v.factura_id] ? 'expand_less' : 'expand_more'"
                           :loading="cargandoDetalle === v.factura_id" @click.stop="alternarDetalle(v)"/>
                  </div>
                </q-item-section>
              </q-item>
              <q-item v-if="abierto[v.factura_id]" class="bg-grey-1">
                <q-item-section>
                  <table class="tabla-detalle">
                    <tr v-for="(d, i) in detalles[v.factura_id] || []" :key="i">
                      <td>{{ d.nombre }}</td>
                      <td class="text-right">{{ cantidadTexto(d) }}</td>
                      <td class="text-right text-weight-medium">{{ money(d.subtotal) }}</td>
                    </tr>
                  </table>
                </q-item-section>
              </q-item>
            </template>
          </q-list>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script>
import { date } from 'quasar'
import { markRaw } from 'vue'
// Leaflet global: con el que carga vue-leaflet por su cuenta, el l-icon con
// contenido propio no se aplica y salen los pines azules por defecto.
import 'leaflet'
import { LMap, LTileLayer, LMarker, LIcon } from '@vue-leaflet/vue-leaflet'
import 'leaflet/dist/leaflet.css'

export default {
  name: 'avancePage',
  components: { LMap, LTileLayer, LMarker, LIcon },
  data () {
    return {
      fecha: date.formatDate(new Date(), 'YYYY-MM-DD'),
      jornada: '',
      cargando: false,
      pedido: 0,
      retorno: 0,
      nopedido: 0,
      totales: { total: 0, cerrados: 0, porcentaje: 0, monto: 0 },
      camiones: [],
      comprobantes: [],
      placa: null,
      estado: '',
      buscar: '',
      abierto: {},
      detalles: {},
      cargandoDetalle: null,
      descargando: null,
      mapa: null,
      zoom: 13,
      centro: [-17.969721, -67.114493],
      estados: [
        { clave: 'ENTREGADO', label: 'Entregado', campo: 'entregados', color: 'green-6' },
        { clave: 'RETORNO PARCIAL', label: 'Retorno parcial', campo: 'retorno', color: 'deep-orange-5' },
        { clave: 'NO ENTREGADO', label: 'No entregado', campo: 'no_entregados', color: 'amber-7' },
        { clave: 'RECHAZADO', label: 'Rechazado', campo: 'rechazados', color: 'red-6' },
        { clave: 'PENDIENTE', label: 'Pendiente', campo: 'pendientes', color: 'blue-grey-4' }
      ]
    }
  },
  computed: {
    tarjetasVisita () {
      return [
        { label: 'Visitas', valor: this.pedido + this.retorno + this.nopedido, clase: '' },
        { label: 'Pedidos', valor: this.pedido, clase: 'text-green' },
        { label: 'Retorno', valor: this.retorno, clase: 'text-orange' },
        { label: 'No pedidos', valor: this.nopedido, clase: 'text-red' }
      ]
    },
    delCamion () {
      return this.placa ? this.comprobantes.filter(c => c.placa === this.placa) : this.comprobantes
    },
    opcionesEstado () {
      return [{ label: 'Todos ' + this.delCamion.length, value: '' }].concat(this.estados
        .map(e => ({ label: e.label + ' ' + this.delCamion.filter(c => c.estado === e.clave).length, value: e.clave }))
        .filter(o => !o.label.endsWith(' 0')))
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
    marcas () {
      return this.filas.filter(c => c.lat && c.lng)
    }
  },
  mounted () {
    this.generar()
  },
  methods: {
    money (v) { return Number(v || 0).toFixed(2) },
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
    cantidadTexto (d) {
      const peso = Number(d.peso)
      return peso > 0 ? peso + ' kg' : Number(d.cantidad) + ' ' + (d.unidad || '')
    },
    metros (a, b) {
      const g = 111320
      return Math.hypot((a.lng - b.lng) * g * Math.cos(a.lat * Math.PI / 180), (a.lat - b.lat) * g)
    },
    generar () {
      this.visitas()
      this.cargar()
    },
    visitas () {
      this.$api.post('pedidoVenta', { fecha: this.fecha }).then(res => {
        this.pedido = res.data.pedido
        this.retorno = res.data.retorno
        this.nopedido = res.data.nopedido
      }).catch(err => {
        this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'Error al obtener visitas' })
      })
    },
    cargar () {
      this.cargando = true
      this.$api.get('entrega-factura/vendedor', { params: { fecha: this.fecha } })
        .then(res => {
          this.jornada = res.data.jornada
          this.totales = res.data.totales
          this.camiones = res.data.camiones
          this.comprobantes = res.data.comprobantes
          this.abierto = {}
          if (this.placa && !this.camiones.some(c => c.placa === this.placa)) this.placa = null
          this.$nextTick(this.encuadrar)
        })
        .catch(err => {
          this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudieron cargar tus ventas' })
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
    // Los puntos a mas de 60 km del resto son coordenadas mal cargadas: no
    // cuentan para encuadrar.
    encuadrar () {
      const puntos = this.marcas
      if (!this.mapa || !puntos.length) return
      const mediana = lista => { const o = lista.slice().sort((a, b) => a - b); return o[Math.floor(o.length / 2)] }
      const centro = { lat: mediana(puntos.map(c => c.lat)), lng: mediana(puntos.map(c => c.lng)) }
      const cerca = puntos.filter(c => this.metros(c, centro) < 60000).map(c => [c.lat, c.lng])
      this.mapa.fitBounds(cerca.length ? cerca : [[centro.lat, centro.lng]], { padding: [24, 24], maxZoom: 16 })
    },
    // Desde la lista se centra el mapa; desde el mapa se busca en la lista.
    enfocar (comprobante, desdeLista) {
      if (desdeLista) {
        if (comprobante.lat && this.mapa) this.mapa.setView([comprobante.lat, comprobante.lng], 18)
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
    // La boleta es la misma que imprime caja: factura si se facturo, si no el
    // voucher de la venta. No se marca como impresa.
    descargarBoleta (comprobante) {
      const factura = comprobante.tipo_comprobante === 'FACTURA'
      const ruta = 'facturacion/' + comprobante.factura_id + (factura ? '/factura' : '/voucher')
      this.descargando = comprobante.factura_id
      this.$api.get(ruta, { responseType: 'blob', headers: { Accept: 'application/pdf' } })
        .then(res => {
          const blob = new Blob([res.data], { type: 'application/pdf' })
          const link = document.createElement('a')
          link.href = window.URL.createObjectURL(blob)
          link.download = (factura ? 'factura_' : 'boleta_') + comprobante.factura_id + '.pdf'
          link.click()
          window.URL.revokeObjectURL(link.href)
        })
        .catch(async err => {
          // Con responseType blob el mensaje de error tambien llega como blob.
          let mensaje = 'Error al descargar la boleta'
          try {
            mensaje = JSON.parse(await err.response.data.text()).message || mensaje
          } catch (e) {
            console.error(e)
          }
          this.$q.notify({ type: 'negative', message: mensaje })
        })
        .finally(() => { this.descargando = null })
    }
  }
}
</script>

<style scoped>
.barra-estados {
  display: flex;
  height: 12px;
  border-radius: 6px;
  overflow: hidden;
  background: #eceff1;
}
.mapa-caja { height: 420px; position: relative; overflow: hidden; }
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
.lista-ventas { max-height: 520px; overflow-y: auto; }
.fila-entregado { background: #f1f8e9; }
.fila-no-entregado { background: #fff8e1; }
.fila-rechazado { background: #ffebee; }
.tabla-detalle { border-collapse: collapse; font-size: 11.5px; width: 100%; }
.tabla-detalle td { padding: 1px 6px; }
</style>
