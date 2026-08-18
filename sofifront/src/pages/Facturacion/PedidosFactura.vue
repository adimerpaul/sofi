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
        <div class="col-6 col-md-3">
          <q-select
            v-model="tipo" dense outlined emit-value map-options
            label="Tipo" :options="tipos"
          />
        </div>
        <div class="col-9 col-md-5">
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

      <div v-else-if="!pedidos.length" class="col-12">
        <q-card flat bordered class="text-center text-grey-7 q-pa-xl">
          <q-icon name="assignment" size="36px" class="q-mb-sm"/>
          <div>No hay pedidos para esta fecha</div>
        </q-card>
      </div>

      <div v-for="pedido in pedidos" :key="pedido.nro_pedido + '-' + pedido.tipo" class="col-12 col-sm-6 col-lg-4">
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
            :label="pedido.productos + ' productos del pedido'"
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
            @click="$router.push({ path: '/facturacion', query: { buscar: pedido.factura_id, fecha: fecha } })"
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
  methods: {
    money (valor) {
      return Number(valor || 0).toFixed(2)
    },
    cantidad (valor) {
      return Number(valor || 0).toLocaleString('es-BO', { maximumFractionDigits: 3 })
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
      }).catch(err => {
        this.$q.notify({
          type: 'negative', position: 'top',
          message: err.response?.data?.message || 'No se pudieron recuperar los pedidos'
        })
      }).finally(() => { this.cargando = false })
    },
    revisar (pedido) {
      this.$router.push('/facturacion/pedidos/' + pedido.nro_pedido + '/' + pedido.tipo)
    }
  }
}
</script>
