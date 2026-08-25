<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-col-gutter-sm q-mb-sm">
      <div class="col">
        <div class="text-h6 text-weight-bold">Pedido factura</div>
        <div class="text-caption text-grey-7">Pedidos listos para cobrar</div>
      </div>
      <div class="col-auto">
        <q-btn flat round dense icon="request_quote" to="/facturacion">
          <q-tooltip>Ver facturación</q-tooltip>
        </q-btn>
      </div>
    </div>

    <q-card flat bordered class="q-pa-sm q-mb-sm rounded-borders">
      <div class="row q-col-gutter-sm items-center" @keyup.enter="cargar">
        <div class="col-6 col-md-2">
          <q-input v-model="fecha" type="date" dense outlined label="Fecha"/>
        </div>
        <div class="col-6 col-md-2">
          <q-select
            v-model="tipo" dense outlined emit-value map-options
            label="Tipo" :options="tipos"
          />
        </div>
        <div class="col-12 col-md-3">
          <q-select
            v-model="camion" dense outlined clearable emit-value map-options
            label="Camion" :options="camiones"
            :disable="!camiones.length"
          >
            <template v-slot:prepend><q-icon name="local_shipping"/></template>
          </q-select>
        </div>
        <div class="col-9 col-md-4">
          <q-input v-model.trim="buscar" dense outlined clearable placeholder="Pedido o cliente">
            <template v-slot:append><q-icon name="search"/></template>
          </q-input>
        </div>
        <div class="col-3 col-md-auto">
          <q-btn class="full-width" color="primary" unelevated icon="search" :loading="cargando" @click="cargar">
            <q-tooltip>Buscar</q-tooltip>
          </q-btn>
        </div>
      </div>
    </q-card>

    <div class="row q-col-gutter-sm">
      <div v-if="cargando" class="col-12 flex flex-center q-pa-xl">
        <q-spinner color="primary" size="42px"/>
      </div>

      <div v-else-if="!pedidosFiltrados.length" class="col-12">
        <q-card flat bordered class="text-center text-grey-7 q-pa-xl">
          <q-icon name="assignment" size="36px" class="q-mb-sm"/>
          <div v-if="pedidos.length">Ningun pedido de esta fecha va en ese camion</div>
          <div v-else>No hay pedidos para esta fecha</div>
        </q-card>
      </div>

      <div v-for="pedido in pedidosFiltrados" :key="pedido.nro_pedido + '-' + pedido.tipo" class="col-12 col-sm-6 col-lg-4">
        <q-card flat bordered class="rounded-borders shadow-1">
          <q-card-section class="q-pa-sm">
            <div class="row items-center no-wrap q-mb-xs">
              <q-badge color="primary" class="text-body2 q-pa-xs">#{{ pedido.nro_pedido }}</q-badge>
              <div class="text-caption text-grey-7 q-ml-sm ellipsis">
                {{ hora(pedido.fecha) }} · {{ pedido.estado }}
              </div>
              <q-space/>
              <q-badge v-if="!pedido.factura_id" color="orange-8">PENDIENTE</q-badge>
              <q-badge v-else color="positive">LISTO</q-badge>
            </div>

            <div class="text-subtitle2 text-weight-bold ellipsis-2-lines q-mt-xs">
              {{ pedido.cliente || 'Sin cliente' }}
            </div>
            <div class="text-caption text-grey-7 ellipsis">
              NIT {{ pedido.nit || '—' }} · {{ pedido.vendedor || 'Sin preventista' }}
            </div>

            <div class="q-mt-xs">
              <q-chip v-if="pedido.placa" dense square size="sm" icon="local_shipping" :style="estiloCamion(pedido)">
                {{ pedido.placa }}
              </q-chip>
              <q-chip v-else dense square size="sm" outline color="grey-7" icon="local_shipping">
                Sin camion
              </q-chip>
            </div>

            <q-banner v-if="pedido.detalle_pollo.observaciones.length" dense rounded class="bg-amber-1 text-amber-10 q-mt-sm">
              <template v-slot:avatar><q-icon name="sticky_note_2" color="amber-9"/></template>
              {{ pedido.detalle_pollo.observaciones.join(' · ') }}
            </q-banner>

            <div class="row items-end q-mt-sm">
              <div class="col">
                <div class="text-caption text-grey-7">{{ pedido.productos }} productos</div>
                <div class="text-h6 text-weight-bolder text-blue-grey-10">
                  Bs {{ money(pedido.total_pedido) }}
                </div>
              </div>
              <div class="col-auto">
                <q-chip v-if="pedido.factura_id" dense square color="green-1" text-color="green-9">
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
            <q-list dense separator class="bg-grey-1">
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
          <q-card-actions class="q-pa-sm">
          <q-btn
            v-if="!pedido.factura_id"
            class="full-width q-py-sm text-weight-bold" color="positive" unelevated no-caps
            icon="edit_note" label="Revisar y facturar"
            @click="revisar(pedido)"
          />
          <q-btn
            v-else class="full-width q-py-sm text-weight-bold" outline no-caps color="primary" icon="visibility" label="Ver comprobante"
            @click="verComprobante(pedido)"
          />
          </q-card-actions>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script>
import { date } from 'quasar'

export default {
  name: 'PedidosFactura',
  data () {
    return {
      fecha: this.$route.query.fecha || date.formatDate(new Date(), 'YYYY-MM-DD'),
      tipo: this.$route.query.tipo || 'NORMAL',
      camion: this.$route.query.camion || null,
      buscar: '',
      cargando: false,
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
    // Las opciones salen de los pedidos ya cargados: solo se ofrecen los
    // camiones que de verdad tienen pedidos ese dia y de ese tipo.
    camiones () {
      const conteo = new Map()
      let sinCamion = 0
      this.pedidos.forEach(pedido => {
        const placa = (pedido.placa || '').trim()
        if (!placa) { sinCamion += 1; return }
        conteo.set(placa, (conteo.get(placa) || 0) + 1)
      })
      const opciones = []
      Array.from(conteo.keys()).sort().forEach(placa => {
        opciones.push({ label: placa + ' (' + conteo.get(placa) + ')', value: placa })
      })
      if (sinCamion) {
        opciones.push({ label: 'Sin camion (' + sinCamion + ')', value: 'SIN' })
      }
      return opciones
    },
    pedidosFiltrados () {
      // Sin camion elegido (el select va vacio o recien limpiado) se ven todos.
      if (!this.camion) return this.pedidos
      if (this.camion === 'SIN') return this.pedidos.filter(pedido => !(pedido.placa || '').trim())
      return this.pedidos.filter(pedido => (pedido.placa || '').trim() === this.camion)
    }
  },
  methods: {
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
    // colorStyle del pedido viene como 'background-color: #RRGGBB'; el texto
    // se pone negro o blanco segun que tan claro sea ese fondo.
    estiloCamion (pedido) {
      const estilo = (pedido.placa_color || '').trim()
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
