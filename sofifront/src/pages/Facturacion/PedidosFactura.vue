<template>
  <q-page class="q-pa-xs">
    <!-- Sin titulo ni tarjeta alrededor: en el celular cada linea que no sea
         pedido es una linea menos de pedidos en pantalla. -->
    <div class="row q-col-gutter-xs items-center q-mb-xs filtros" @keyup.enter="cargar">
      <div class="col-6 col-md-2">
        <q-input v-model="fecha" type="date" dense outlined/>
      </div>
      <div class="col-6 col-md-2">
        <q-select v-model="tipo" dense outlined emit-value map-options :options="tipos"/>
      </div>
      <div class="col">
        <q-input v-model.trim="buscar" dense outlined clearable placeholder="Pedido o cliente"/>
      </div>
      <div class="col-auto">
        <q-btn color="primary" unelevated dense padding="6px 10px" icon="search" :loading="cargando" @click="cargar">
          <q-tooltip>Buscar</q-tooltip>
        </q-btn>
      </div>
      <div class="col-auto">
        <q-btn flat dense padding="6px 8px" color="primary" icon="request_quote" to="/facturacion">
          <q-tooltip>Ver facturación</q-tooltip>
        </q-btn>
      </div>
    </div>

    <!-- Una linea por camion con lo que ya lleva cobrado: en el celular se ve
         de un golpe cual falta llenar, y tocando se filtra a ese camion. -->
    <q-card v-if="!cargando && camiones.length" flat bordered class="q-mb-xs rounded-borders">
      <div class="row items-center no-wrap q-px-xs bg-grey-3 camion-titulo">
        <div class="text-weight-bold text-grey-8">CARGA POR CAMION</div>
        <q-space/>
        <div class="text-weight-bolder" :class="facturadosTotal === pedidos.length ? 'text-green-9' : 'text-orange-9'">
          {{ facturadosTotal }}/{{ pedidos.length }} · {{ porcentajeTotal }}%
        </div>
        <q-btn v-if="camion" flat dense no-caps size="sm" padding="0 4px" class="q-ml-xs" color="primary"
               icon="clear" label="Todos" @click="camion = null"/>
      </div>
      <!-- Filas planas de 22px: con q-item el padding propio de Quasar hacia
           que nueve camiones empujaran los pedidos fuera de la pantalla. -->
      <div
        v-for="opcion in camiones" :key="opcion.value"
        class="row items-center no-wrap camion-fila"
        :class="camion === opcion.value ? 'bg-blue-2' : ''"
        @click="alternarCamion(opcion.value)"
      >
        <div class="camion-placa ellipsis" :style="estiloColor(opcion.color)">{{ opcion.placa }}</div>
        <q-linear-progress
          class="col q-mx-xs" rounded size="13px" :value="opcion.progreso"
          :color="opcion.completo ? 'positive' : 'orange-7'" track-color="grey-4"
        >
          <div class="absolute-full flex flex-center">
            <span class="camion-porcentaje" :class="opcion.progreso > 0.55 ? 'text-white' : 'text-grey-9'">
              {{ opcion.porcentaje }}%
            </span>
          </div>
        </q-linear-progress>
        <div class="camion-conteo" :class="opcion.completo ? 'text-green-9' : 'text-grey-9'">
          {{ opcion.facturados }}/{{ opcion.total }}
        </div>
      </div>
    </q-card>

    <div class="row q-col-gutter-xs">
      <div v-if="cargando" class="col-12 flex flex-center q-pa-lg">
        <q-spinner color="primary" size="42px"/>
      </div>

      <div v-else-if="!pedidosFiltrados.length" class="col-12">
        <q-card flat bordered class="text-center text-grey-7 q-pa-md">
          <q-icon name="assignment" size="36px" class="q-mb-sm"/>
          <div v-if="pedidos.length">Ningun pedido de esta fecha va en ese camion</div>
          <div v-else>No hay pedidos para esta fecha</div>
        </q-card>
      </div>

      <div v-for="pedido in pedidosFiltrados" :key="pedido.nro_pedido + '-' + pedido.tipo" class="col-12 col-sm-6 col-lg-4">
        <!-- El pedido ya cobrado se pinta entero de verde: en el mostrador se
             ve de un vistazo cual falta sin leer badge por badge. -->
        <q-card flat bordered class="rounded-borders shadow-1" :class="pedido.factura_id ? 'bg-green-2' : ''">
          <q-card-section class="q-pa-xs">
            <div class="row items-center no-wrap q-mb-xs">
              <q-badge color="primary" class="text-body2 q-pa-xs">#{{ pedido.nro_pedido }}</q-badge>
              <div class="text-caption q-ml-sm ellipsis" :class="pedido.factura_id ? 'text-green-9' : 'text-grey-7'">
                {{ hora(pedido.fecha) }} · {{ pedido.estado }}
              </div>
              <q-space/>
              <!-- El que vuelve por una anulacion no arranca de cero: al
                   entrar trae cargado lo que ya se habia cobrado. -->
              <q-badge v-if="pedido.anulada_id" color="blue-8">
                <q-icon name="history" size="14px" class="q-mr-xs"/>RECUPERABLE
              </q-badge>
              <q-badge v-else-if="!pedido.factura_id" color="orange-8">PENDIENTE</q-badge>
              <q-badge v-else color="positive">LISTO</q-badge>
            </div>

            <div class="text-subtitle2 text-weight-bold ellipsis-2-lines q-mt-xs">
              {{ pedido.cliente || 'Sin cliente' }}
            </div>
            <div class="text-caption ellipsis" :class="pedido.factura_id ? 'text-green-9' : 'text-grey-7'">
              NIT {{ pedido.nit || '—' }} · {{ pedido.vendedor || 'Sin preventista' }}
            </div>

            <div class="q-mt-xs">
              <!-- El pedido ya viene marcado por el preventista (tbpedidos.fact):
                   el chip lo dice antes de entrar, y si ya se cobro manda lo que
                   realmente se emitio. -->
              <q-chip
                dense square size="sm" text-color="white"
                :color="esFactura(pedido) ? 'indigo-8' : 'blue-grey-6'"
              >
                {{ esFactura(pedido) ? 'F' : 'R' }}
                <q-tooltip>{{ esFactura(pedido) ? 'Factura' : 'Recibo' }}</q-tooltip>
              </q-chip>
              <q-chip v-if="pedido.placa" dense square size="sm" icon="local_shipping" :style="estiloCamion(pedido)">
                {{ pedido.placa }}
              </q-chip>
              <q-chip v-else dense square size="sm" outline color="grey-7" icon="local_shipping">
                Sin camion
              </q-chip>
            </div>

            <q-banner v-if="pedido.detalle_pollo.observaciones.length" dense rounded class="bg-amber-1 text-amber-10 q-mt-xs">
              <template v-slot:avatar><q-icon name="sticky_note_2" color="amber-9"/></template>
              {{ pedido.detalle_pollo.observaciones.join(' · ') }}
            </q-banner>

            <div class="row items-end q-mt-xs">
              <div class="col">
                <div class="text-caption" :class="pedido.factura_id ? 'text-green-9' : 'text-grey-7'">{{ pedido.productos }} productos</div>
                <div class="text-h6 text-weight-bolder text-blue-grey-10">
                  Bs {{ money(pedido.total_pedido) }}
                </div>
              </div>
              <div class="col-auto">
                <q-chip v-if="pedido.factura_id" dense square color="green-8" text-color="white">
                  {{ pedido.comprobante_emitido }} #{{ pedido.factura_id }}
                </q-chip>
              </div>
            </div>
          </q-card-section>

          <q-separator/>
          <q-expansion-item
            dense dense-toggle switch-toggle-side
            icon="shopping_basket"
            label="Ver pedido completo"
            header-class="text-weight-medium"
          >
            <q-list dense separator :class="pedido.factura_id ? 'bg-green-1' : 'bg-grey-1'">
              <q-item v-for="item in pedido.items" :key="item.cod_prod" class="q-px-sm">
                <q-item-section>
                  <q-item-label lines="2">{{ item.nombre }}</q-item-label>
                  <q-item-label caption>{{ item.cod_prod }}</q-item-label>
                </q-item-section>
                <q-item-section side class="text-right">
                  <q-item-label>{{ cantidad(item.cantidad) }} × Bs {{ money(item.precio) }}</q-item-label>
                  <q-item-label caption class="text-weight-bold">Bs {{ money(item.total) }}</q-item-label>
                </q-item-section>
              </q-item>
              <q-item v-for="(item, indice) in pedido.detalle_pollo.productos" :key="'pollo-' + indice" class="q-px-sm bg-orange-1">
                <q-item-section>
                  <q-item-label class="text-weight-bold">{{ item.nombre }}</q-item-label>
                  <q-item-label v-if="item.observacion" caption>{{ item.observacion }}</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-item-label>{{ cantidad(item.cantidad) }} {{ item.unidad }}</q-item-label>
                  <q-item-label v-if="item.precio" caption>Bs {{ money(item.precio) }}</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-expansion-item>

          <q-separator/>
          <!-- vertical: los botones van uno debajo del otro, no repartidos
               en la misma fila. -->
          <q-card-actions vertical class="q-pa-xs">
          <!-- Mientras el pedido no tenga factura solo se ofrece facturar: el
               comprobante recien aparece cuando existe algo que mostrar. -->
          <template v-if="!pedido.factura_id">
            <q-btn
              class="full-width q-py-xs text-weight-bold" color="positive" unelevated no-caps
              :icon="pedido.anulada_id ? 'history' : 'edit_note'"
              :label="pedido.anulada_id ? 'Retomar y facturar' : 'Revisar y facturar'"
              @click="revisar(pedido)"
            />
            <div v-if="pedido.anulada_id" class="text-caption text-blue-9 q-mt-xs">
              Se anuló la venta #{{ pedido.anulada_id }} (Bs {{ money(pedido.anulada_total) }});
              al entrar vuelve cargada
            </div>
          </template>
          <template v-else>
            <q-btn
              class="full-width q-py-xs text-weight-bold" outline no-caps color="primary" icon="visibility" label="Ver comprobante"
              @click="verComprobante(pedido)"
            />
            <q-btn
              class="full-width q-py-xs text-weight-bold q-mt-xs" unelevated no-caps color="primary" icon="print"
              :label="'Imprimir ' + (pedido.comprobante_emitido === 'FACTURA' ? 'factura' : 'voucher')"
              :loading="imprimiendo === pedido.factura_id"
              @click="imprimir(pedido)"
            />
          </template>
          </q-card-actions>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script>
import { date } from 'quasar'
import { imprimirPdfDirecto } from 'src/utils/impresion.js'

export default {
  name: 'PedidosFactura',
  data () {
    return {
      fecha: this.$route.query.fecha || date.formatDate(new Date(), 'YYYY-MM-DD'),
      tipo: this.$route.query.tipo || 'NORMAL',
      camion: this.$route.query.camion || null,
      buscar: '',
      cargando: false,
      imprimiendo: null,
      pedidos: [],
      tipos: [
        { label: 'Embutidos', value: 'NORMAL' },
        { label: 'Pollo', value: 'POLLO' },
        { label: 'Cerdo', value: 'CERDO' },
        { label: 'Res', value: 'RES' }
      ]
    }
  },
  created () {
    this.cargar()
  },
  watch: {
    fecha () { this.sincronizarUrl() },
    tipo () { this.sincronizarUrl() },
    camion () { this.sincronizarUrl() }
  },
  computed: {
    // Las opciones salen de los pedidos ya cargados: solo se listan los
    // camiones que de verdad tienen pedidos ese dia y de ese tipo, cada uno
    // con cuanto lleva cobrado hasta ahora.
    camiones () {
      const filas = new Map()
      this.pedidos.forEach(pedido => {
        const placa = (pedido.placa || '').trim()
        const clave = placa || 'SIN'
        let fila = filas.get(clave)
        if (!fila) {
          fila = { value: clave, placa: placa || 'Sin camion', color: '', total: 0, facturados: 0 }
          filas.set(clave, fila)
        }
        if (!fila.color && pedido.placa_color) fila.color = pedido.placa_color
        fila.total += 1
        if (pedido.factura_id) fila.facturados += 1
      })
      return Array.from(filas.values()).map(fila => {
        const progreso = fila.total ? fila.facturados / fila.total : 0
        return {
          ...fila,
          progreso,
          porcentaje: Math.round(progreso * 100),
          completo: fila.total > 0 && fila.facturados === fila.total
        }
      }).sort((uno, otro) => {
        // Lo que falta llenar queda arriba; los ya completos y el "sin camion"
        // se van al fondo, que es lo que ya no hay que mirar.
        if (uno.value === 'SIN') return 1
        if (otro.value === 'SIN') return -1
        if (uno.completo !== otro.completo) return uno.completo ? 1 : -1
        return uno.placa.localeCompare(otro.placa)
      })
    },
    facturadosTotal () {
      return this.pedidos.filter(pedido => pedido.factura_id).length
    },
    porcentajeTotal () {
      if (!this.pedidos.length) return 0
      return Math.round((this.facturadosTotal / this.pedidos.length) * 100)
    },
    pedidosFiltrados () {
      // Sin camion elegido (el select va vacio o recien limpiado) se ven todos.
      if (!this.camion) return this.pedidos
      if (this.camion === 'SIN') return this.pedidos.filter(pedido => !(pedido.placa || '').trim())
      return this.pedidos.filter(pedido => (pedido.placa || '').trim() === this.camion)
    }
  },
  methods: {
    // Antes de cobrarse manda lo que pidio el cliente (fact = SI); una vez
    // emitido, lo que de verdad salio, que puede no coincidir.
    esFactura (pedido) {
      if (pedido.factura_id) return pedido.comprobante_emitido === 'FACTURA'
      return String(pedido.fact || '').toUpperCase() === 'SI'
    },
    money (valor) {
      return Number(valor || 0).toFixed(2)
    },
    cantidad (valor) {
      return Number(valor || 0).toLocaleString('es-BO', { maximumFractionDigits: 3 })
    },
    // Los filtros viven en la URL: asi al volver del detalle de un pedido, al
    // recargar o al usar el boton atras se vuelve a la misma vista filtrada.
    sincronizarUrl () {
      const query = { fecha: this.fecha, tipo: this.tipo }
      if (this.camion) query.camion = this.camion
      const actual = this.$route.query
      if (actual.fecha === query.fecha && actual.tipo === query.tipo &&
        (actual.camion || null) === (query.camion || null)) return
      this.$router.replace({ path: '/facturacion/pedidos', query })
    },
    // Tocar el camion ya filtrado lo suelta: en el celular no hace falta ir
    // hasta el boton Todos para volver a ver la lista completa.
    alternarCamion (valor) {
      this.camion = this.camion === valor ? null : valor
    },
    estiloCamion (pedido) {
      return this.estiloColor(pedido.placa_color)
    },
    // colorStyle del pedido viene como 'background-color: #RRGGBB'; el texto
    // se pone negro o blanco segun que tan claro sea ese fondo.
    estiloColor (color) {
      const estilo = (color || '').trim()
      const hex = /#([0-9a-f]{6})/i.exec(estilo)
      if (!hex) return 'background-color: #ECEFF1; color: #37474F'
      const valor = parseInt(hex[1], 16)
      const luz = (0.299 * ((valor >> 16) & 255) + 0.587 * ((valor >> 8) & 255) + 0.114 * (valor & 255)) / 255
      return estilo + '; color: ' + (luz > 0.6 ? '#212121' : '#FFFFFF')
    },
    hora (valor) {
      const partes = String(valor || '').split(' ')
      return partes.length > 1 ? partes[1].substr(0, 5) : ''
    },
    cargar () {
      this.cargando = true
      this.$api.get('facturacion/pedidos', {
        params: { fecha: this.fecha, tipo: this.tipo, buscar: this.buscar || '' }
      }).then(res => {
        this.pedidos = res.data
        // Si el camion elegido no aparece en la nueva consulta el filtro
        // dejaria la pantalla vacia sin motivo visible: se vuelve a Todos.
        if (this.camion && !this.camiones.some(opcion => opcion.value === this.camion)) {
          this.camion = null
        }
      }).catch(err => {
        this.$q.notify({
          type: 'negative', position: 'top',
          message: err.response?.data?.message || 'No se pudieron recuperar los pedidos'
        })
      }).finally(() => { this.cargando = false })
    },
    revisar (pedido) {
      // El camion viaja en la query para que el boton volver del detalle
      // devuelva al listado con el mismo camion filtrado.
      this.$router.push({
        path: '/facturacion/pedidos/' + pedido.nro_pedido + '/' + pedido.tipo,
        query: this.camion ? { camion: this.camion } : {}
      })
    },
    // El comprobante se manda a la impresora desde aca, cuando el cajero lo
    // pide: la venta ya no imprime sola al guardarse.
    imprimir (pedido) {
      const documento = pedido.comprobante_emitido === 'FACTURA' ? 'factura' : 'voucher'
      this.imprimiendo = pedido.factura_id

      this.$api.get('facturacion/' + pedido.factura_id + '/' + documento, { responseType: 'blob' })
        .then(res => imprimirPdfDirecto(res.data, documento + '_' + pedido.factura_id + '.pdf'))
        .catch(async err => {
          // El PDF viaja como blob, asi que el motivo del rechazo (por ejemplo
          // que el caminero todavia no verifico su carga) hay que leerlo del
          // propio blob o se pierde detras de un mensaje generico.
          let mensaje = err.response?.data?.message
          if (err.response?.data instanceof Blob) {
            try {
              mensaje = JSON.parse(await err.response.data.text()).message
            } catch (e) { mensaje = null }
          }
          this.$q.notify({
            type: 'negative', position: 'top', timeout: 6000,
            message: mensaje || 'No se pudo imprimir el ' + documento
          })
        })
        .finally(() => { this.imprimiendo = null })
    },
    // El comprobante no lleva la fecha del pedido sino la del dia en que se
    // cobro, y se busca por el carnet con el que quedo emitido: buscar por el
    // numero traia de vuelta todo lo que contuviera ese digito.
    verComprobante (pedido) {
      this.$router.push({
        path: '/facturacion',
        query: {
          buscar: pedido.factura_nit || pedido.nit || pedido.factura_id,
          fecha: String(pedido.factura_fecha || '').substr(0, 10) ||
            date.formatDate(new Date(), 'YYYY-MM-DD')
        }
      })
    }
  }
}
</script>

<style scoped>
/* Los filtros van mas bajos que el dense de Quasar: esa fila compite con los
   pedidos por la pantalla del celular y solo se toca al empezar el dia. */
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

.camion-titulo {
  height: 20px;
  font-size: 11px;
}
.camion-fila {
  height: 22px;
  padding: 0 4px;
  cursor: pointer;
  border-top: 1px solid #e0e0e0;
}
.camion-placa {
  width: 88px;
  height: 17px;
  line-height: 17px;
  padding: 0 4px;
  border-radius: 3px;
  font-size: 11px;
  font-weight: 600;
}
.camion-porcentaje {
  font-size: 10px;
  font-weight: 700;
  line-height: 1;
}
.camion-conteo {
  width: 42px;
  text-align: right;
  font-size: 11px;
  font-weight: 700;
}
</style>
