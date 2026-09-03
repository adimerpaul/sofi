<template>
  <q-page class="q-pa-xs">
    <div class="row q-col-gutter-xs items-center q-mb-xs filtros">
      <div class="col-6 col-md-2">
        <q-input v-model="fecha" type="date" dense outlined @update:model-value="cargar"/>
      </div>
      <div class="col">
        <q-input v-model.trim="buscar" dense outlined clearable placeholder="Cliente o nota"/>
      </div>
      <div class="col-auto">
        <q-btn color="primary" unelevated dense padding="6px 10px" icon="refresh" :loading="cargando" @click="cargar">
          <q-tooltip>Actualizar</q-tooltip>
        </q-btn>
      </div>
      <div class="col-auto">
        <q-btn flat dense padding="6px 8px" color="primary" icon="summarize" to="/caminero/reporte">
          <q-tooltip>Mi reporte del día</q-tooltip>
        </q-btn>
      </div>
    </div>

    <!-- Lo que el caminero tiene que rendir al volver, siempre a la vista: es
         el dato por el que le preguntan en caja. -->
    <q-card flat bordered class="q-mb-xs rounded-borders">
      <div class="row items-center no-wrap q-px-xs bg-grey-3 barra-titulo">
        <div class="text-weight-bold text-grey-8">{{ placa || 'SIN CAMION' }}</div>
        <q-space/>
        <div class="text-weight-bolder" :class="resumen.pendientes ? 'text-orange-9' : 'text-green-9'">
          {{ resumen.cobradas }}/{{ resumen.comprobantes }} · {{ resumen.porcentaje }}%
        </div>
      </div>
      <q-linear-progress
        size="14px" :value="resumen.porcentaje / 100"
        :color="resumen.pendientes ? 'orange-7' : 'positive'" track-color="grey-4"
      />
      <div class="row text-center caja-totales">
        <div class="col">
          <div class="caja-rotulo">EFECTIVO</div>
          <div class="caja-monto text-green-9">{{ money(resumen.efectivo) }}</div>
        </div>
        <div class="col">
          <div class="caja-rotulo">QR</div>
          <div class="caja-monto text-indigo-9">{{ money(resumen.qr) }}</div>
        </div>
        <div class="col">
          <div class="caja-rotulo">POR COBRAR</div>
          <div class="caja-monto text-orange-9">{{ money(resumen.por_cobrar) }}</div>
        </div>
      </div>
      <div v-if="sinComprobante" class="bg-amber-2 text-amber-10 q-px-xs aviso">
        {{ sinComprobante }} pedido(s) de tu camión todavía sin comprobante en caja
      </div>
    </q-card>

    <!-- El mapa arranca cerrado: ocupa media pantalla y el caminero lo abre
         solo cuando no ubica al cliente. -->
    <q-card v-if="conCoordenadas.length" flat bordered class="q-mb-xs rounded-borders">
      <q-expansion-item dense dense-toggle icon="map" label="Ver mapa" header-class="text-weight-medium">
        <div class="mapa">
          <l-map v-model="zoom" :zoom="zoom" :center="centro" @ready="mapaListo">
            <l-tile-layer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"/>
            <l-marker
              v-for="(entrega, indice) in conCoordenadas" :key="entrega.factura_id"
              :lat-lng="[entrega.latitud, entrega.longitud]"
              @click="abrirCobro(entrega)"
            >
              <l-icon>
                <q-badge :class="colorEstado(entrega)" style="padding: 2px">{{ indice + 1 }}</q-badge>
              </l-icon>
            </l-marker>
          </l-map>
        </div>
      </q-expansion-item>
    </q-card>

    <div v-if="cargando" class="flex flex-center q-pa-lg">
      <q-spinner color="primary" size="42px"/>
    </div>

    <q-card v-else-if="!filtradas.length" flat bordered class="text-center text-grey-7 q-pa-md">
      <q-icon name="local_shipping" size="36px" class="q-mb-sm"/>
      <div>No hay comprobantes de tu camión para esta fecha</div>
    </q-card>

    <div v-else class="row q-col-gutter-xs">
      <div v-for="entrega in filtradas" :key="entrega.factura_id" class="col-12 col-sm-6 col-lg-4">
        <q-card flat bordered class="rounded-borders shadow-1" :class="fondoTarjeta(entrega)">
          <q-card-section class="q-pa-xs">
            <div class="row items-center no-wrap q-mb-xs">
              <q-badge color="primary" class="text-body2 q-pa-xs">#{{ entrega.nro_pedido }}</q-badge>
              <div class="text-caption q-ml-sm ellipsis text-grey-7">
                {{ entrega.tipo_comprobante }} #{{ entrega.factura_id }}
              </div>
              <q-space/>
              <q-badge v-if="entrega.cobrada" color="positive">{{ entrega.tipago }}</q-badge>
              <q-badge v-else-if="entrega.entrega_estado" color="red-8">{{ entrega.entrega_estado }}</q-badge>
              <q-badge v-else color="orange-8">POR COBRAR</q-badge>
            </div>

            <div class="text-subtitle2 text-weight-bold ellipsis-2-lines">
              {{ entrega.cliente || entrega.nombre || 'Sin cliente' }}
            </div>
            <div class="text-caption ellipsis text-grey-7">
              {{ entrega.direccion || 'Sin dirección' }}
            </div>

            <div class="row items-end q-mt-xs">
              <div class="col">
                <div class="text-caption text-grey-7">{{ entrega.productos }} productos</div>
                <div class="text-h6 text-weight-bolder text-blue-grey-10">Bs {{ money(entrega.total) }}</div>
              </div>
              <div class="col-auto">
                <q-btn
                  v-if="entrega.telefono" flat dense round color="green-8" icon="call"
                  :href="'tel:' + entrega.telefono"
                />
                <q-btn
                  v-if="entrega.latitud" flat dense round color="blue-8" icon="navigation"
                  :href="'https://www.google.com/maps/dir/?api=1&destination=' + entrega.latitud + ',' + entrega.longitud"
                  target="_blank"
                />
              </div>
            </div>
            <div v-if="entrega.cobrada && entrega.tipago === 'MIXTO'" class="text-caption text-green-9">
              Efectivo Bs {{ money(entrega.monto_efectivo) }} · QR Bs {{ money(entrega.monto_qr) }}
            </div>
            <div v-else-if="entrega.observacion && !entrega.cobrada && entrega.entrega_estado" class="text-caption text-red-9 ellipsis">
              {{ entrega.observacion }}
            </div>
          </q-card-section>

          <q-separator/>
          <q-expansion-item
            dense dense-toggle switch-toggle-side icon="receipt_long"
            label="Ver comprobante" header-class="text-weight-medium"
            @show="verDetalle(entrega)"
          >
            <div v-if="detalles[entrega.factura_id] === 'cargando'" class="q-pa-sm text-center">
              <q-spinner color="primary" size="24px"/>
            </div>
            <q-list v-else-if="detalles[entrega.factura_id]" dense separator class="bg-grey-1">
              <q-item v-for="item in detalles[entrega.factura_id]" :key="item.id" class="q-px-sm">
                <q-item-section>
                  <q-item-label lines="2">{{ item.nombre }}</q-item-label>
                  <q-item-label caption>{{ item.cod_prod }}</q-item-label>
                </q-item-section>
                <q-item-section side class="text-right">
                  <q-item-label>{{ cantidad(item.cantidad) }} × Bs {{ money(item.precio) }}</q-item-label>
                  <q-item-label caption class="text-weight-bold">Bs {{ money(item.subtotal) }}</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-expansion-item>

          <q-separator/>
          <q-card-actions class="q-pa-xs">
            <q-btn
              v-if="!entrega.cobrada"
              class="full-width q-py-xs text-weight-bold" color="positive" unelevated no-caps
              icon="payments" label="Cobrar" @click="abrirCobro(entrega)"
            />
            <div v-else class="full-width text-center text-caption text-green-9 text-weight-bold">
              Cobrado {{ entrega.entrega_hora }} · Bs {{ money(entrega.total) }}
            </div>
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <q-dialog v-model="dialogo" @hide="limpiarCobro">
      <q-card style="min-width: 300px">
        <q-card-section class="q-pb-none">
          <div class="text-subtitle1 text-weight-bold">{{ cobro.cliente }}</div>
          <div class="text-caption text-grey-7">
            Pedido #{{ cobro.nro_pedido }} · {{ cobro.tipo_comprobante }} #{{ cobro.factura_id }}
          </div>
          <div class="text-h5 text-weight-bolder text-blue-grey-10 q-mt-xs">
            Bs {{ money(cobro.total) }}
          </div>
        </q-card-section>

        <q-card-section class="q-pt-sm">
          <q-btn-toggle
            v-model="forma" spread no-caps unelevated dense
            toggle-color="primary" color="grey-3" text-color="grey-9"
            :options="[
              { label: 'Efectivo', value: 'CONTADO' },
              { label: 'QR', value: 'PAGO QR' },
              { label: 'Mixto', value: 'MIXTO' },
              { label: 'Crédito', value: 'CRÉDITO' }
            ]"
          />

          <!-- En el mixto solo se escribe el efectivo: el QR es el resto, que
               es como lo cuenta el caminero en la puerta. -->
          <div v-if="forma === 'MIXTO'" class="row q-col-gutter-xs q-mt-sm">
            <div class="col-6">
              <q-input
                v-model.number="efectivo" type="number" dense outlined label="Efectivo"
                inputmode="decimal" @update:model-value="ajustarQr"
              />
            </div>
            <div class="col-6">
              <q-input v-model.number="qr" type="number" dense outlined label="QR" inputmode="decimal"/>
            </div>
            <div class="col-12">
              <div class="text-caption" :class="cuadra ? 'text-green-9' : 'text-red-9'">
                Suma Bs {{ money(efectivo + qr) }} de Bs {{ money(cobro.total) }}
              </div>
            </div>
          </div>

          <q-input
            v-model.trim="motivo" dense outlined class="q-mt-sm" maxlength="90"
            label="Motivo (solo si no se entrega)"
          />
        </q-card-section>

        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat no-caps color="grey-8" label="Cerrar" v-close-popup/>
          <q-btn
            flat no-caps color="negative" label="No entregado"
            :loading="guardando" @click="registrar('NO ENTREGADO')"
          />
          <q-btn
            unelevated no-caps color="positive" label="Cobrar" icon="check"
            :loading="guardando" :disable="forma === 'MIXTO' && !cuadra"
            @click="registrar('ENTREGADO')"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { date } from 'quasar'
import { LMap, LIcon, LTileLayer, LMarker } from '@vue-leaflet/vue-leaflet'
import 'leaflet/dist/leaflet.css'

export default {
  name: 'MisEntregas',
  components: { LMap, LIcon, LTileLayer, LMarker },
  data () {
    return {
      fecha: this.$route.query.fecha || date.formatDate(new Date(), 'YYYY-MM-DD'),
      buscar: '',
      cargando: false,
      guardando: false,
      placa: '',
      sinComprobante: 0,
      entregas: [],
      resumen: { comprobantes: 0, cobradas: 0, pendientes: 0, porcentaje: 0, efectivo: 0, qr: 0, por_cobrar: 0 },
      detalles: {},
      dialogo: false,
      cobro: {},
      forma: 'CONTADO',
      efectivo: 0,
      qr: 0,
      motivo: '',
      zoom: 13,
      centro: [-17.9833, -67.15],
      posicion: null
    }
  },
  created () {
    this.cargar()
    this.ubicar()
  },
  computed: {
    filtradas () {
      const texto = this.buscar.toLowerCase()
      if (!texto) return this.entregas
      return this.entregas.filter(entrega =>
        String(entrega.cliente || '').toLowerCase().includes(texto) ||
        String(entrega.nro_pedido).includes(texto)
      )
    },
    conCoordenadas () {
      return this.entregas.filter(entrega => Number(entrega.latitud) && Number(entrega.longitud))
    },
    cuadra () {
      return Math.abs((Number(this.efectivo) + Number(this.qr)) - Number(this.cobro.total || 0)) < 0.01
    }
  },
  methods: {
    money (valor) {
      return Number(valor || 0).toFixed(2)
    },
    cantidad (valor) {
      return Number(valor || 0).toLocaleString('es-BO', { maximumFractionDigits: 3 })
    },
    fondoTarjeta (entrega) {
      if (entrega.cobrada) return 'bg-green-2'
      return entrega.entrega_estado ? 'bg-red-1' : ''
    },
    colorEstado (entrega) {
      if (entrega.cobrada) return 'bg-green'
      return entrega.entrega_estado ? 'bg-red' : 'bg-orange'
    },
    mapaListo (mapa) {
      // El mapa nace dentro de un desplegable cerrado, asi que al abrirlo
      // calcula mal su tamaño hasta que se le avisa.
      setTimeout(() => mapa.invalidateSize(), 200)
      if (this.conCoordenadas.length) {
        const primera = this.conCoordenadas[0]
        this.centro = [Number(primera.latitud), Number(primera.longitud)]
      }
    },
    ubicar () {
      if (!navigator.geolocation) return
      navigator.geolocation.getCurrentPosition(
        posicion => { this.posicion = posicion.coords },
        () => { this.posicion = null },
        { enableHighAccuracy: true, timeout: 8000 }
      )
    },
    cargar () {
      this.cargando = true
      this.$api.get('caminero/entregas', { params: { fecha: this.fecha } })
        .then(res => {
          this.placa = res.data.placa
          this.entregas = res.data.entregas
          this.resumen = res.data.resumen
          this.sinComprobante = res.data.sin_comprobante
        })
        .catch(err => {
          this.$q.notify({
            type: 'negative',
            position: 'top',
            message: err.response?.data?.message || 'No se pudieron cargar tus entregas'
          })
        })
        .finally(() => { this.cargando = false })
    },
    // El detalle se pide una sola vez por comprobante y queda cacheado: en la
    // calle la señal es mala y no conviene repetir la consulta.
    verDetalle (entrega) {
      if (this.detalles[entrega.factura_id]) return
      this.detalles = { ...this.detalles, [entrega.factura_id]: 'cargando' }
      this.$api.get('facturacion/' + entrega.factura_id)
        .then(res => {
          this.detalles = { ...this.detalles, [entrega.factura_id]: res.data.detalles || [] }
        })
        .catch(() => {
          this.detalles = { ...this.detalles, [entrega.factura_id]: [] }
        })
    },
    abrirCobro (entrega) {
      if (entrega.cobrada) return
      this.cobro = entrega
      this.forma = 'CONTADO'
      this.efectivo = Number(entrega.total)
      this.qr = 0
      this.motivo = ''
      this.dialogo = true
    },
    limpiarCobro () {
      this.cobro = {}
    },
    ajustarQr () {
      const resto = Number(this.cobro.total || 0) - Number(this.efectivo || 0)
      this.qr = Math.round(Math.max(resto, 0) * 100) / 100
    },
    registrar (estado) {
      if (estado !== 'ENTREGADO' && !this.motivo) {
        this.$q.notify({ type: 'warning', position: 'top', message: 'Escribe el motivo de la no entrega' })
        return
      }

      this.guardando = true
      this.$api.post('caminero/cobrar', {
        factura_id: this.cobro.factura_id,
        estado,
        tipago: estado === 'ENTREGADO' ? this.forma : null,
        monto_efectivo: this.forma === 'MIXTO' ? this.efectivo : null,
        monto_qr: this.forma === 'MIXTO' ? this.qr : null,
        observacion: this.motivo || null,
        lat: this.posicion?.latitude || null,
        lng: this.posicion?.longitude || null
      }).then(() => {
        this.$q.notify({ type: 'positive', position: 'top', message: 'Entrega registrada' })
        this.dialogo = false
        this.cargar()
      }).catch(err => {
        this.$q.notify({
          type: 'negative',
          position: 'top',
          message: err.response?.data?.message || 'No se pudo registrar la entrega'
        })
      }).finally(() => { this.guardando = false })
    }
  }
}
</script>

<style scoped>
.filtros :deep(.q-field--dense .q-field__control),
.filtros :deep(.q-field--dense .q-field__marginal) {
  height: 30px;
  min-height: 30px;
}
.filtros :deep(.q-field__native),
.filtros :deep(.q-field__input) {
  font-size: 12px;
  padding: 0;
}

.barra-titulo {
  height: 20px;
  font-size: 11px;
}
.caja-totales {
  padding: 2px 0;
}
.caja-rotulo {
  font-size: 9px;
  color: #757575;
  letter-spacing: 0.5px;
}
.caja-monto {
  font-size: 14px;
  font-weight: 700;
  line-height: 1.1;
}
.aviso {
  font-size: 11px;
  line-height: 18px;
}
.mapa {
  height: 260px;
}
</style>
