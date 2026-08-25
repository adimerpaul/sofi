<template>
  <q-page class="q-pa-sm q-pb-xl">
    <div class="row items-center q-mb-sm">
      <q-btn flat round dense icon="arrow_back" @click="volver"/>
      <div class="col q-ml-xs">
        <div class="text-subtitle1 text-weight-bold">Pedido #{{ numeroPedido }}</div>
        <div class="text-caption text-grey-7">Revisar y cobrar</div>
      </div>
      <q-badge color="primary">{{ nombreTipo }}</q-badge>
    </div>

    <div v-if="cargando" class="flex flex-center q-pa-xl">
      <q-spinner color="primary" size="44px"/>
    </div>

    <template v-else-if="pedido">
      <q-card flat bordered class="rounded-borders q-mb-sm">
        <q-card-section class="q-pa-sm">
          <div class="text-subtitle2 text-weight-bold">{{ pedido.cliente || 'Sin cliente' }}</div>
          <div class="text-caption text-grey-7">
            NIT {{ pedido.nit || '—' }} · {{ pedido.vendedor || 'Sin preventista' }}
          </div>
          <div class="q-mt-xs">
            <q-chip v-if="pedido.placa" dense square size="sm" icon="local_shipping" :style="estiloCamion">
              {{ pedido.placa }}
            </q-chip>
            <q-chip v-else dense square size="sm" outline color="grey-7" icon="local_shipping">
              Sin camion
            </q-chip>
            <q-chip v-if="pedido.horario" dense square size="sm" outline color="grey-7" icon="schedule">
              {{ pedido.horario }}
            </q-chip>
          </div>
        </q-card-section>
      </q-card>

      <q-card v-if="pedido.detalle_pollo.productos.length || pedido.detalle_pollo.observaciones.length" flat bordered class="rounded-borders q-mb-sm bg-orange-1">
        <q-card-section class="q-pa-sm">
          <div class="text-subtitle2 text-weight-bold"><q-icon name="restaurant"/> Detalle completo de pollo</div>
          <div v-for="(texto, indice) in pedido.detalle_pollo.observaciones" :key="'obs-' + indice" class="text-body2 q-mt-xs">
            <q-icon name="sticky_note_2" color="amber-9"/> {{ texto }}
          </div>
        </q-card-section>
        <q-separator/>
        <q-list dense separator>
          <q-item v-for="(dato, indice) in pedido.detalle_pollo.productos" :key="'especial-' + indice">
            <q-item-section>
              <q-item-label class="text-weight-bold">{{ dato.nombre }}</q-item-label>
              <q-item-label v-if="dato.observacion" caption>{{ dato.observacion }}</q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-item-label>{{ cantidad(dato.cantidad) }} {{ dato.unidad }}</q-item-label>
              <q-item-label v-if="dato.precio" caption>Bs {{ money(dato.precio) }}</q-item-label>
            </q-item-section>
          </q-item>
        </q-list>
      </q-card>

      <div class="row items-center q-mb-xs">
        <div class="col text-subtitle2 text-weight-bold">Productos ({{ items.length }})</div>
        <q-btn color="primary" outline dense no-caps icon="add" label="Agregar producto" @click="abrirCatalogo"/>
      </div>

      <q-card flat bordered class="rounded-borders q-mb-sm">
        <q-list separator>
          <q-item v-for="(item, indice) in items" :key="item.cod_prod + '-' + indice" class="q-pa-sm">
            <q-item-section>
              <q-item-label class="text-weight-bold" lines="2">{{ item.nombre }}</q-item-label>
              <q-item-label caption>
                {{ item.cod_prod }}
                <span v-if="esPeso(item)" class="text-orange-9">· se cobra por peso</span>
              </q-item-label>

              <div class="row q-col-gutter-xs q-mt-xs">
                <div :class="esPeso(item) ? 'col-3' : 'col-5'">
                  <q-input
                    v-model.number="item.cantidad" type="number" min="0.001" step="0.001"
                    dense outlined label="Cantidad" @update:model-value="actualizar(item)"
                  />
                </div>
                <!-- Lo que va por kilo se pesa en el mostrador: ese peso, y no
                     la cantidad de piezas, es lo que multiplica al precio. -->
                <div v-if="esPeso(item)" class="col-3">
                  <q-input
                    v-model.number="item.peso" type="number" min="0.001" step="0.001"
                    dense outlined label="Peso kg" bg-color="orange-1"
                    :error="!Number(item.peso)" hide-bottom-space
                    @update:model-value="actualizar(item)"
                  />
                </div>
                <div :class="esPeso(item) ? 'col-4' : 'col-5'">
                  <q-input
                    v-model.number="item.precio" type="number" min="0" step="0.01"
                    dense outlined label="Precio Bs" @update:model-value="actualizar(item)"
                  />
                </div>
                <div class="col-2 flex flex-center">
                  <q-btn flat round dense color="negative" icon="delete" @click="items.splice(indice, 1)"/>
                </div>
              </div>
            </q-item-section>
            <q-item-section side top class="text-weight-bold q-pl-xs">
              Bs {{ money(item.total) }}
            </q-item-section>
          </q-item>
        </q-list>
      </q-card>

      <q-card flat bordered class="rounded-borders q-mb-lg">
        <q-card-section class="q-pa-sm">
          <q-btn-toggle
            v-model="tipoComprobante" spread no-caps unelevated
            toggle-color="primary" color="grey-3" text-color="grey-8"
            :options="[
              { label: 'Voucher', value: 'VENTA', icon: 'receipt' },
              { label: 'Factura', value: 'FACTURA', icon: 'verified' }
            ]"
          />

          <div class="row q-col-gutter-xs q-mt-xs">
            <div class="col-7">
              <q-input v-model.trim="nit" dense outlined label="NIT o CI"/>
            </div>
            <div class="col-5">
              <q-select v-model="tipoPago" dense outlined label="Pago" :options="tiposPago"/>
            </div>
          </div>
          <q-input v-model.trim="observacion" dense outlined class="q-mt-xs" label="Observación"/>

          <q-separator class="q-my-sm"/>
          <div class="row items-center">
            <div class="col text-subtitle1 text-weight-bold">Total</div>
            <div class="text-h5 text-weight-bolder">Bs {{ money(total) }}</div>
          </div>
          <div v-if="faltanPesos" class="text-caption text-negative">
            <q-icon name="scale"/> Falta pesar productos: el total aún no está completo
          </div>
        </q-card-section>
      </q-card>

      <div class="fixed-bottom bg-white q-pa-sm shadow-up-3">
        <q-btn
          class="full-width q-py-sm text-weight-bold" color="positive" unelevated no-caps
          icon="point_of_sale" :label="tipoComprobante === 'FACTURA' ? 'Emitir factura' : 'Generar voucher'"
          :disable="!items.length || faltanPesos" :loading="guardando" @click="guardar"
        />
      </div>
    </template>

    <q-dialog v-model="dialogCatalogo" maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card>
        <q-bar class="bg-primary text-white">
          <div class="text-weight-bold">Agregar producto</div>
          <q-space/>
          <q-btn dense flat round icon="close" v-close-popup/>
        </q-bar>
        <q-card-section class="q-pa-sm">
          <q-input
            v-model.trim="buscarProducto" dense outlined autofocus clearable
            placeholder="Nombre o código" @update:model-value="buscarConRetraso"
          >
            <template v-slot:prepend><q-icon name="search"/></template>
          </q-input>
        </q-card-section>
        <q-separator/>
        <q-list separator>
          <q-item v-for="producto in productos" :key="producto.cod_prod" clickable @click="agregar(producto)">
            <q-item-section>
              <q-item-label class="text-weight-medium">{{ producto.producto }}</q-item-label>
              <q-item-label caption>{{ producto.cod_prod }} · Stock {{ cantidad(producto.stock) }}</q-item-label>
            </q-item-section>
            <q-item-section side>
              <div class="text-weight-bold">Bs {{ money(producto.precio) }}</div>
              <q-icon name="add_circle" color="positive" size="26px"/>
            </q-item-section>
          </q-item>
        </q-list>
        <div v-if="cargandoProductos" class="flex flex-center q-pa-lg"><q-spinner color="primary" size="36px"/></div>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
export default {
  name: 'PedidoFacturaEditar',
  data () {
    return {
      pedido: null,
      items: [],
      cargando: true,
      guardando: false,
      tipoComprobante: 'VENTA',
      tipoPago: 'EFECTIVO',
      tiposPago: ['EFECTIVO', 'QR', 'TARJETA', 'CRÉDITO'],
      nit: '',
      observacion: '',
      dialogCatalogo: false,
      buscarProducto: '',
      productos: [],
      cargandoProductos: false,
      temporizador: null
    }
  },
  computed: {
    numeroPedido () { return this.$route.params.pedido },
    tipoPedido () { return String(this.$route.params.tipo || '').toUpperCase() },
    nombreTipo () { return this.tipoPedido === 'NORMAL' ? 'EMBUTIDOS' : this.tipoPedido },
    total () {
      return this.items.reduce((suma, item) => suma + this.facturable(item) * Number(item.precio || 0), 0)
    },
    // Mientras falte un peso el total esta incompleto y no se puede cobrar.
    faltanPesos () {
      return this.items.some(item => this.esPeso(item) && !(Number(item.peso) > 0))
    },
    // colorStyle del pedido viene como 'background-color: #RRGGBB'; el texto se
    // pone negro o blanco segun que tan claro sea ese fondo.
    estiloCamion () {
      const estilo = String((this.pedido && this.pedido.placa_color) || '').trim()
      const hex = /#([0-9a-f]{6})/i.exec(estilo)
      if (!hex) return 'background-color: #ECEFF1; color: #37474F'
      const valor = parseInt(hex[1], 16)
      const luz = (0.299 * ((valor >> 16) & 255) + 0.587 * ((valor >> 8) & 255) + 0.114 * (valor & 255)) / 255
      return estilo + '; color: ' + (luz > 0.6 ? '#212121' : '#FFFFFF')
    }
  },
  created () {
    this.cargarPedido()
  },
  methods: {
    money (valor) { return Number(valor || 0).toFixed(2) },
    cantidad (valor) { return Number(valor || 0).toLocaleString('es-BO', { maximumFractionDigits: 3 }) },
    // Los productos por kilo se cobran por el peso de la balanza; el resto,
    // por la cantidad de unidades.
    esPeso (item) { return String(item.unidad || '').toUpperCase() === 'KG' },
    facturable (item) {
      return Number((this.esPeso(item) ? item.peso : item.cantidad) || 0)
    },
    actualizar (item) {
      item.total = Math.round(this.facturable(item) * Number(item.precio || 0) * 100) / 100
    },
    cargarPedido () {
      this.$api.get('facturacion/pedidos/' + this.numeroPedido, { params: { tipo: this.tipoPedido } })
        .then(res => {
          this.pedido = res.data.pedido
          this.items = res.data.items
          // Las lineas por kilo llegan sin pesar, asi que su importe arranca
          // en cero hasta que el cajero escriba el peso.
          this.items.forEach(this.actualizar)
          this.nit = this.pedido.nit || ''
          this.observacion = this.pedido.comentario || ''
          this.tipoComprobante = String(this.pedido.fact || '').toUpperCase() === 'SI' ? 'FACTURA' : 'VENTA'
          this.tipoPago = String(this.pedido.pago || '').toUpperCase().includes('CREDIT') ? 'CRÉDITO' : 'EFECTIVO'
        })
        .catch(this.error)
        .finally(() => { this.cargando = false })
    },
    volver () {
      const query = { fecha: String(this.pedido?.fecha || '').substr(0, 10), tipo: this.tipoPedido }
      // Se devuelve el camion con el que venia filtrado el listado.
      if (this.$route.query.camion) query.camion = this.$route.query.camion
      this.$router.push({ path: '/facturacion/pedidos', query })
    },
    abrirCatalogo () {
      this.dialogCatalogo = true
      this.buscarProducto = ''
      this.cargarProductos()
    },
    buscarConRetraso () {
      clearTimeout(this.temporizador)
      this.temporizador = setTimeout(this.cargarProductos, 300)
    },
    cargarProductos () {
      this.cargandoProductos = true
      this.$api.get('facturacion/catalogo', {
        params: { buscar: this.buscarProducto || '', page: 1, perPage: 50 }
      }).then(res => { this.productos = res.data.data })
        .catch(this.error)
        .finally(() => { this.cargandoProductos = false })
    },
    agregar (producto) {
      const existente = this.items.find(item => item.cod_prod === producto.cod_prod)
      if (existente) {
        existente.cantidad = Number(existente.cantidad || 0) + 1
        this.actualizar(existente)
      } else {
        // El peso entra vacio: lo escribe el cajero con lo que marque la balanza.
        this.items.push({
          cod_prod: producto.cod_prod,
          nombre: producto.producto,
          unidad: producto.unidad,
          cantidad: 1,
          peso: null,
          precio: Number(producto.precio || 0),
          total: producto.unidad === 'KG' ? 0 : Number(producto.precio || 0)
        })
      }
      this.dialogCatalogo = false
    },
    guardar () {
      if (this.tipoComprobante === 'FACTURA' && !this.nit) {
        this.$q.notify({ type: 'warning', position: 'top', message: 'La factura necesita NIT o CI' })
        return
      }
      if (this.items.some(item => Number(item.cantidad) <= 0 || Number(item.precio) < 0)) {
        this.$q.notify({ type: 'warning', position: 'top', message: 'Revisa cantidades y precios' })
        return
      }
      if (this.faltanPesos) {
        this.$q.notify({
          type: 'warning',
          position: 'top',
          message: 'Falta el peso de: ' + this.items
            .filter(item => this.esPeso(item) && !(Number(item.peso) > 0))
            .map(item => item.nombre).join(', ')
        })
        return
      }

      this.guardando = true
      this.$api.post('facturacion', {
        tipo_comprobante: this.tipoComprobante,
        tipo_pago: this.tipoPago,
        cliente_id: this.pedido.cliente_id,
        nit: this.nit,
        nombre: this.pedido.cliente || '',
        observacion: this.observacion,
        pedido_nro: Number(this.numeroPedido),
        pedido_tipo: this.tipoPedido,
        items: this.items.map(item => ({
          cod_prod: item.cod_prod,
          cantidad: Number(item.cantidad),
          // El peso solo viaja en lo que se vende por kilo.
          ...(this.esPeso(item) ? { peso: Number(item.peso) } : {}),
          precio: Number(item.precio)
        }))
      }).then(res => {
        this.$q.notify({
          type: res.data.siat?.estado === 'ERROR' ? 'warning' : 'positive',
          position: 'top', message: res.data.message, timeout: 8000
        })
        this.imprimir(res.data.factura.id, res.data.factura.tipo_comprobante)
        setTimeout(this.volver, 1000)
      }).catch(this.error)
        .finally(() => { this.guardando = false })
    },
    async imprimir (id, tipo) {
      const documento = tipo === 'FACTURA' ? 'factura' : 'voucher'
      try {
        await this.$solicitarImpresion(id, documento)
      } catch (error) {
        this.$q.notify({
          type: 'warning', position: 'top', timeout: 7000,
          message: 'La operacion se guardo, pero no se pudo imprimir el ' + documento
        })
      }
    },
    error (err) {
      this.$q.notify({
        type: 'negative', position: 'top',
        message: err.response?.data?.message || 'No se pudo completar la operación'
      })
    }
  }
}
</script>
