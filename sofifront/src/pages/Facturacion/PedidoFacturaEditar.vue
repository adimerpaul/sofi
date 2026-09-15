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
          </div>
        </q-card-section>
      </q-card>

      <!-- El pedido volvio a la cola porque su venta se anulo: lo que ya se
           habia pesado y corregido llega cargado, no hay que rehacerlo. -->
      <!-- El caminero marco un retorno parcial: las cantidades ya vienen con
           lo que el cliente se quedo, listas para emitir el comprobante nuevo. -->
      <q-banner v-if="pedido.retorno" dense rounded class="bg-deep-orange-1 text-deep-orange-10 q-mb-sm">
        <template v-slot:avatar><q-icon name="assignment_return" color="deep-orange-8"/></template>
        <div class="text-weight-medium">
          Retorno parcial marcado por {{ pedido.retorno.caminero || 'el caminero' }}:
          se quedó Bs {{ money(pedido.retorno.total_entregado) }} de Bs {{ money(pedido.retorno.total_original) }}
        </div>
        <div class="text-caption">{{ pedido.retorno.observacion }}</div>
        <div class="text-caption">Las cantidades ya vienen corregidas; revisalas y emití el nuevo.</div>
      </q-banner>

      <q-banner v-if="pedido.anulada" dense rounded class="bg-blue-1 text-blue-10 q-mb-sm">
        <template v-slot:avatar><q-icon name="history" color="blue-8"/></template>
        <div class="text-weight-medium">
          Se recuperó lo cobrado en la venta #{{ pedido.anulada.id }}, que fue anulada
        </div>
        <div class="text-caption">
          {{ pedido.anulada.lineas }}
          {{ pedido.anulada.lineas === 1 ? 'producto' : 'productos' }} ·
          Bs {{ money(pedido.anulada.total) }} ·
          {{ pedido.anulada.anulado_at || pedido.anulada.fecha }}
          <span v-if="pedido.anulada.motivo"> · {{ pedido.anulada.motivo }}</span>
        </div>
        <div class="text-caption">Revisá los pesos y cantidades antes de volver a cobrar.</div>
      </q-banner>

      <!-- Todo lo que cargo el preventista en una sola linea, como en la hoja
           de pesos: productos, observacion y precios. -->
      <div v-if="detalleLinea.length" class="rounded-borders q-mb-sm q-px-sm q-py-xs bg-orange-1 text-body2 detalle-linea">
        <q-icon name="restaurant" color="orange-9" class="q-mr-xs"/>
        <template v-for="(parte, indice) in detalleLinea" :key="'parte-' + indice">
          <span v-if="indice" class="text-grey-6"> · </span>
          <span v-if="parte.etiqueta" class="text-grey-8">{{ parte.etiqueta }} </span>
          <span class="text-weight-bold">{{ parte.valor }}</span>
        </template>
      </div>

      <div class="row items-center q-mb-xs">
        <div class="col text-subtitle2 text-weight-bold">Productos ({{ items.length }})</div>
        <q-btn color="primary" outline dense no-caps icon="add" label="Agregar producto" @click="abrirCatalogo"/>
      </div>

      <q-banner v-if="lineasCambiadas.length" dense rounded class="bg-deep-orange-1 text-deep-orange-10 q-mb-sm">
        <template v-slot:avatar><q-icon name="published_with_changes" color="deep-orange"/></template>
        <span class="text-weight-medium">
          {{ lineasCambiadas.length }}
          {{ lineasCambiadas.length === 1 ? 'producto sale' : 'productos salen' }}
          con una cantidad distinta a la del pedido
        </span>
      </q-banner>

      <q-card flat bordered class="rounded-borders q-mb-sm">
        <q-list separator>
          <q-item v-for="(item, indice) in items" :key="item.cod_prod + '-' + indice" class="q-pa-sm">
            <q-item-section>
              <q-item-label class="text-weight-bold" lines="2">{{ item.nombre }}</q-item-label>
              <q-item-label caption>
                {{ item.cod_prod }}
                <span v-if="esPeso(item)" class="text-orange-9">· se cobra por peso</span>
              </q-item-label>

              <!-- Aviso de que lo que se entrega ya no es lo que pidio el
                   cliente. Se ve solo aca, al revisar: no se guarda ni sale en
                   el comprobante impreso. -->
              <q-item-label v-if="item.retorno" caption class="text-deep-orange-9 text-weight-medium">
                <q-icon name="assignment_return"/> Corregido por el retorno parcial
              </q-item-label>
              <q-item-label v-if="item.recuperado" caption class="text-blue-9 text-weight-medium">
                <q-icon name="history"/> Recuperado de la venta anulada
              </q-item-label>
              <q-item-label v-if="esNuevo(item)" caption class="text-blue-9 text-weight-medium">
                <q-icon name="add_circle_outline"/> Agregado: no estaba en el pedido
              </q-item-label>
              <q-item-label v-else-if="cambioCantidad(item)" caption class="text-deep-orange text-weight-medium">
                <q-icon name="published_with_changes"/>
                Pedido {{ cantidad(item.cantidad_pedida) }} · se entrega {{ cantidad(item.cantidad) }}
                ({{ diferencia(item) }})
              </q-item-label>

              <div class="row q-col-gutter-xs q-mt-xs">
                <div :class="esPeso(item) ? 'col-3' : 'col-5'">
                  <q-input
                    v-model.number="item.cantidad" type="number" min="0.001" step="0.001"
                    dense outlined label="Cantidad" @update:model-value="actualizar(item)"
                    :bg-color="cambioCantidad(item) ? 'deep-orange-1' : ''"
                  />
                </div>
                <!-- Lo que va por kilo se pesa en el mostrador: ese peso, y no
                     la cantidad de piezas, es lo que multiplica al precio. -->
                <div v-if="esPeso(item) && conCanastillos" class="col-3">
                  <q-input
                    v-model.number="item.peso_bruto" type="number" min="0.001" step="0.001"
                    dense outlined label="P. bruto kg" bg-color="orange-1"
                    :error="!(Number(item.peso) > 0)" hide-bottom-space
                    @update:model-value="actualizar(item)"
                  />
                </div>
                <div v-else-if="esPeso(item)" class="col-3">
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
                    :readonly="!puedeCambiarPrecio"
                  >
                    <template v-if="!puedeCambiarPrecio" v-slot:append>
                      <q-icon name="lock" size="14px" color="grey">
                        <q-tooltip>No tiene permiso para cambiar el precio</q-tooltip>
                      </q-icon>
                    </template>
                  </q-input>
                </div>
                <div class="col-2 flex flex-center">
                  <q-btn flat round dense color="negative" icon="delete" @click="items.splice(indice, 1)"/>
                </div>
              </div>

              <!-- Pollo, cerdo y res se pesan dentro de los canastillos: se
                   cobra el neto, el bruto menos lo que pesan los canastillos.
                   Sin canastillos el neto es el mismo bruto. -->
              <div v-if="esPeso(item) && conCanastillos" class="row q-col-gutter-xs q-mt-xs items-center">
                <div class="col-3">
                  <q-input
                    v-model.number="item.canastillos" type="number" min="0" step="1"
                    dense outlined label="Canastillos" @update:model-value="actualizar(item)"
                  />
                </div>
                <div class="col text-caption">
                  <span v-if="Number(item.canastillos) > 0" class="text-grey-8">
                    {{ item.canastillos }} × {{ kgCanastillo }} kg = {{ cantidad(kgCanastillos(item)) }} kg ·
                  </span>
                  <span :class="Number(item.peso) > 0 ? 'text-weight-bold' : 'text-negative text-weight-bold'">
                    Peso neto {{ Number(item.peso) > 0 ? cantidad(item.peso) + ' kg' : '—' }}
                  </span>
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
          <!-- Comprobante, NIT/CI y forma de pago salen del pedido tal como lo
               tomo el preventista: el cajero los ve pero no los cambia, para
               que lo cobrado coincida con lo pactado con el cliente. -->
          <q-btn-toggle
            v-model="tipoComprobante" spread no-caps unelevated disable
            toggle-color="primary" color="grey-3" text-color="grey-8"
            :options="[
              { label: 'Voucher', value: 'VENTA', icon: 'receipt' },
              { label: 'Factura', value: 'FACTURA', icon: 'verified' }
            ]"
          />

          <div class="row q-col-gutter-xs q-mt-xs">
            <div class="col-7">
              <q-input v-model.trim="nit" dense outlined disable label="NIT o CI"/>
            </div>
            <div class="col-5">
              <q-select v-model="tipoPago" dense outlined disable label="Pago" :options="tiposPago"/>
            </div>
          </div>
          <div class="text-caption text-grey-7">
            <q-icon name="lock"/> Comprobante, NIT/CI y pago vienen del pedido
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
    // Lo dice el backend: pollo, cerdo y res se pesan en canastillos.
    conCanastillos () { return !!(this.pedido && this.pedido.con_canastillos) },
    kgCanastillo () { return Number((this.pedido && this.pedido.kg_canastillo) || 2) },
    // Sin el permiso el precio queda con el del pedido o el del catalogo.
    puedeCambiarPrecio () { return this.$store.getters['login/can']('facturacionPrecio') },
    total () {
      return this.items.reduce((suma, item) => suma + this.facturable(item) * Number(item.precio || 0), 0)
    },
    // El detalle de pollo armado como una sola linea: productos con su
    // cantidad, observaciones y los datos con valor (los vacios no ocupan lugar).
    detalleLinea () {
      const detalle = (this.pedido && this.pedido.detalle_pollo) || {}
      const productos = (detalle.productos || []).map(dato => ({
        etiqueta: dato.nombre,
        valor: [
          dato.cantidad !== null ? this.cantidad(dato.cantidad) + ' ' + dato.unidad : '',
          dato.precio ? 'Bs ' + this.money(dato.precio) : '',
          dato.observacion || ''
        ].filter(Boolean).join(' ')
      }))
      const observaciones = (detalle.observaciones || []).map(texto => ({ etiqueta: '', valor: texto }))
      const datos = (detalle.datos || []).filter(dato => dato.valor && dato.valor !== '—')
      return [...productos, ...observaciones, ...datos]
    },
    // Lo que se entrega distinto de lo que pidio el cliente, para avisarlo
    // arriba de la lista. Es informativo: no viaja al backend.
    lineasCambiadas () {
      return this.items.filter(item => this.cambioCantidad(item))
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
    // Los productos que el cajero suma del catalogo no vienen del pedido, asi
    // que no hay cantidad pedida con la cual compararlos.
    esNuevo (item) { return item.cantidad_pedida === null || item.cantidad_pedida === undefined },
    cambioCantidad (item) {
      return !this.esNuevo(item) && Number(item.cantidad || 0) !== Number(item.cantidad_pedida)
    },
    diferencia (item) {
      const resta = Number(item.cantidad || 0) - Number(item.cantidad_pedida)
      return (resta > 0 ? '+' : '−') + this.cantidad(Math.abs(resta))
    },
    facturable (item) {
      return Number((this.esPeso(item) ? item.peso : item.cantidad) || 0)
    },
    kgCanastillos (item) {
      return Math.max(0, Math.trunc(Number(item.canastillos) || 0)) * this.kgCanastillo
    },
    actualizar (item) {
      // Con canastillos el peso que se cobra no se escribe: sale del bruto.
      if (this.esPeso(item) && this.conCanastillos) {
        const bruto = Number(item.peso_bruto) || 0
        item.peso = bruto > 0 ? Math.round((bruto - this.kgCanastillos(item)) * 1000) / 1000 : null
      }
      item.total = Math.round(this.facturable(item) * Number(item.precio || 0) * 100) / 100
    },
    cargarPedido () {
      this.$api.get('facturacion/pedidos/' + this.numeroPedido, { params: { tipo: this.tipoPedido } })
        .then(res => {
          this.pedido = res.data.pedido
          this.items = res.data.items
          // Lo recuperado de una venta anulada antes de los canastillos solo
          // tiene el peso: se toma como bruto sin canastillos.
          if (this.conCanastillos) {
            this.items.forEach(item => {
              if (this.esPeso(item) && !(Number(item.peso_bruto) > 0) && Number(item.peso) > 0) {
                item.peso_bruto = Number(item.peso)
                item.canastillos = 0
              }
            })
          }
          // Las lineas por kilo llegan sin pesar, asi que su importe arranca
          // en cero hasta que el cajero escriba el peso.
          this.items.forEach(this.actualizar)
          this.nit = this.pedido.nit || ''
          // Si la venta anterior se anulo, la observacion con la que se cobro
          // vuelve tal cual; si no, la del pedido.
          this.observacion = this.pedido.anulada?.observacion || this.pedido.comentario || ''
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
          // No viene del pedido: no hay cantidad pedida contra que comparar.
          cantidad_pedida: null,
          peso: null,
          peso_bruto: null,
          canastillos: null,
          precio: Number(producto.precio || 0),
          total: producto.unidad === 'KG' ? 0 : Number(producto.precio || 0)
        })
      }
      this.dialogCatalogo = false
    },
    guardar () {
      if (this.tipoComprobante === 'FACTURA' && !this.nit) {
        this.$q.notify({
          type: 'warning',
          position: 'top',
          message: 'El pedido pide factura pero el cliente no tiene NIT o CI: corregirlo en la ficha del cliente'
        })
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
          // Lo pedido viaja junto con lo entregado para que quede guardado en
          // el detalle de la factura lo que se cambio.
          ...(this.esNuevo(item) ? {} : { cantidad_pedida: Number(item.cantidad_pedida) }),
          // El peso solo viaja en lo que se vende por kilo.
          ...(this.esPeso(item) ? { peso: Number(item.peso) } : {}),
          // Con canastillos va el bruto: el backend recalcula el neto.
          ...(this.esPeso(item) && this.conCanastillos
            ? { peso_bruto: Number(item.peso_bruto), canastillos: Math.trunc(Number(item.canastillos) || 0) }
            : {}),
          precio: Number(item.precio)
        }))
      }).then(res => {
        this.$q.notify({
          type: res.data.siat?.estado === 'ERROR' ? 'warning' : 'positive',
          position: 'top', message: res.data.message, timeout: 8000
        })
        // La venta ya no imprime sola: el comprobante se manda a la impresora
        // desde el boton Imprimir de la tarjeta del pedido.
        setTimeout(this.volver, 1000)
      }).catch(this.error)
        .finally(() => { this.guardando = false })
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

<style scoped>
/* Una linea apretada; si en el celular no entra, sigue abajo en vez de cortarse. */
.detalle-linea {
  line-height: 1.3;
}
</style>
