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
      <q-banner v-if="pedido.baja" dense rounded class="bg-purple-1 text-purple-10 q-mb-sm">
        <template v-slot:avatar><q-icon name="card_giftcard" color="purple"/></template>
        <div class="text-weight-medium">Es una baja: la boleta se imprime a nombre de {{ pedido.baja }}</div>
        <div class="text-caption">{{ pedido.cliente }} va en la observación de la boleta.</div>
      </q-banner>

      <q-banner v-if="pedido.retorno" dense rounded class="bg-deep-orange-1 text-deep-orange-10 q-mb-sm">
        <template v-slot:avatar><q-icon name="assignment_return" color="deep-orange-8"/></template>
        <div class="text-weight-medium">
          Retorno parcial marcado por {{ pedido.retorno.caminero || 'el caminero' }}:
          se quedó Bs {{ money(pedido.retorno.total_entregado) }} de Bs {{ money(pedido.retorno.total_original) }}
        </div>
        <div class="text-caption">{{ pedido.retorno.observacion }}</div>
        <div class="text-caption">Las cantidades ya vienen corregidas; revisalas y emití el nuevo.</div>
      </q-banner>

      <q-banner v-if="pedido.borrador" dense rounded class="bg-amber-1 text-amber-10 q-mb-sm">
        <template v-slot:avatar><q-icon name="save" color="amber-9"/></template>
        <div class="text-weight-medium">
          Guardado sin finalizar
          <span v-if="pedido.borrador.usuario">por {{ pedido.borrador.usuario }}</span>
          · {{ pedido.borrador.guardado }}
        </div>
        <div class="text-caption">Todavía no es venta ni se descontó stock: se hace al finalizar.</div>
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

      <div class="row items-center q-mb-xs q-gutter-xs">
        <div class="col text-subtitle2 text-weight-bold">Productos ({{ items.length }})</div>
        <!-- Pasa todos los productos a un mismo precio de lista de una vez
             (por ejemplo, todos al Precio 8). -->
        <q-btn-dropdown
          v-if="nombresPrecio.length" color="indigo-7" outline dense no-caps
          icon="price_change" label="Cambiar todos a…"
        >
          <q-list dense style="min-width: 160px">
            <q-item v-for="nombre in nombresPrecio" :key="nombre" clickable v-close-popup @click="cambiarTodos(nombre)">
              <q-item-section>{{ nombre }}</q-item-section>
              <q-item-section side class="text-caption">
                {{ conPrecio(nombre) }}/{{ items.length }}
              </q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>
        <q-btn color="primary" outline dense no-caps icon="add" label="Agregar producto" @click="abrirCatalogo"/>
      </div>

      <q-banner v-if="lineasCambiadas.length || lineasNuevas.length" dense rounded class="bg-deep-orange-1 text-deep-orange-10 q-mb-sm">
        <template v-slot:avatar><q-icon name="published_with_changes" color="deep-orange"/></template>
        <span class="text-weight-medium">
          <template v-if="lineasCambiadas.length">
            {{ lineasCambiadas.length }}
            {{ lineasCambiadas.length === 1 ? 'producto sale' : 'productos salen' }}
            con una cantidad distinta a la del pedido
          </template>
          <template v-if="lineasNuevas.length">
            {{ lineasCambiadas.length ? '·' : '' }} {{ lineasNuevas.length }}
            {{ lineasNuevas.length === 1 ? 'agregado' : 'agregados' }}
          </template>
        </span>
        <!-- En un pedido de 20 o 30 productos lo cambiado se pierde: con esto
             se revisa solo eso antes de cobrar. -->
        <template v-slot:action>
          <q-btn
            dense unelevated no-caps size="sm"
            :color="soloCambiados ? 'deep-orange' : 'white'"
            :text-color="soloCambiados ? 'white' : 'deep-orange-10'"
            :icon="soloCambiados ? 'list' : 'filter_alt'"
            :label="soloCambiados ? 'Ver todos' : 'Ver solo cambiados'"
            @click="soloCambiados = !soloCambiados"
          />
        </template>
      </q-banner>

      <q-card flat bordered class="rounded-borders q-mb-sm">
        <q-list separator>
          <!-- La fila entera se pinta si cambio la cantidad o se agrego: en un
               pedido largo el color se ve de lejos al bajar por la lista.
               v-show y no un filtro para que el indice siga siendo el de items. -->
          <q-item
            v-for="(item, indice) in items" :key="item.cod_prod + '-' + indice"
            v-show="!filtrandoCambios || cambioCantidad(item) || esNuevo(item)"
            class="q-pa-sm"
            :class="{ 'linea-cambiada': cambioCantidad(item), 'linea-nueva': esNuevo(item) }"
          >
            <q-item-section>
              <q-item-label class="text-weight-bold" lines="2">
                {{ item.nombre }}
                <!-- Una parte del mismo producto puede ir con otro precio:
                     se copia la linea y cada una lleva su cantidad y precio. -->
                <q-btn
                  flat round dense size="sm" color="primary" icon="content_copy"
                  class="q-ml-xs" @click="copiar(indice)"
                >
                  <q-tooltip>Copiar el producto (para otro precio o cantidad)</q-tooltip>
                </q-btn>
                <!-- Si el preventista lo cargo con la unidad equivocada, aca se
                     cambia a cobrar por kilo o por unidad. -->
                <q-btn
                  dense unelevated no-caps size="sm" class="q-ml-xs q-px-xs"
                  :color="esPeso(item) ? 'orange-8' : 'blue-grey-6'"
                  :icon="esPeso(item) ? 'scale' : 'tag'"
                  :label="esPeso(item) ? 'Por kilo' : 'Por unidad'"
                  @click="cambiarUnidad(item)"
                >
                  <q-tooltip>
                    {{ esPeso(item) ? 'Cambiar a cobrar por unidad' : 'Cambiar a cobrar por kilo' }}
                  </q-tooltip>
                </q-btn>
              </q-item-label>
              <q-item-label caption>
                {{ item.cod_prod }}
                <span v-if="esPeso(item)" class="text-orange-9">· se cobra por peso</span>
                <!-- Lo que se vende por caja: en que unidad lo pidio el preventista. -->
                <q-badge v-if="item.caja" color="indigo-6" class="q-ml-xs" :label="'Pedido en ' + item.caja"/>
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

              <div class="row q-col-gutter-xs q-mt-xs campos-compactos">
                <div :class="esPeso(item) && conCanastillos ? 'col-2' : esPeso(item) ? 'col-3' : 'col-5'">
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
                <!-- Canastillos va antes del precio: se cargan seguido del bruto. -->
                <div v-if="esPeso(item) && conCanastillos" class="col-2">
                  <q-input
                    v-model.number="item.canastillos" type="number" min="0" step="1"
                    dense outlined label="Canast." @update:model-value="actualizar(item)"
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
                <div :class="esPeso(item) && conCanastillos ? 'col-3' : esPeso(item) ? 'col-4' : 'col-5'">
                  <!-- El precio se elige de la lista del producto (Precio 1 a
                       13); con permiso tambien se puede escribir otro. -->
                  <q-select
                    :model-value="Number(item.precio)" :options="opcionesPrecio(item)"
                    emit-value map-options dense outlined options-dense label="Precio Bs"
                    :display-value="textoPrecio(item)"
                    @update:model-value="valor => elegirPrecio(item, valor)"
                  />
                </div>
                <div class="col-2 flex flex-center">
                  <q-btn flat round dense color="negative" icon="delete" @click="items.splice(indice, 1)"/>
                </div>
              </div>

              <!-- Pollo, cerdo y res se pesan dentro de los canastillos: se
                   cobra el neto, el bruto menos lo que pesan los canastillos.
                   Sin canastillos el neto es el mismo bruto. -->
              <div v-if="esPeso(item) && conCanastillos" class="row q-mt-xs items-center">
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
          <!-- Comprobante, NIT/CI y forma de pago vienen del pedido, pero en
               caja se pueden corregir: el cliente muchas veces pide la factura
               -o cambia de forma de pago- recien al momento de pagar. -->
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
              <q-input v-model.trim="nit" dense outlined label="NIT o CI"
                       :rules="[ v => tipoComprobante !== 'FACTURA' || !!v || 'Requerido para factura' ]"
                       hide-bottom-space/>
            </div>
            <div class="col-5">
              <q-select v-model="tipoPago" dense outlined label="Pago" :options="tiposPago"/>
            </div>
          </div>
          <div class="text-caption text-grey-7">
            <q-icon name="edit"/> Vienen del pedido; corregirlos aqui solo cambia este comprobante
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

      <!-- Guardar deja el avance para seguir despues, sin venta ni stock.
           Finalizar es lo que registra la venta y descuenta el stock. -->
      <q-page-sticky expand position="bottom">
        <div class="barra-acciones full-width bg-white shadow-up-3">
          <q-btn
            class="boton-accion boton-guardar" color="primary" outline no-caps
            icon="save" label="Guardar"
            :disable="guardando" :loading="guardandoBorrador" @click="guardarBorrador"
          >
            <q-tooltip>Guarda el avance sin hacer la venta ni descontar stock</q-tooltip>
          </q-btn>
          <q-btn
            class="boton-accion boton-finalizar" color="positive" unelevated no-caps
            icon="check_circle" :disable="!items.length || faltanPesos || guardandoBorrador"
            :loading="guardando" @click="guardar"
          >
            <div class="column items-start q-ml-sm leading">
              <span class="text-weight-bold">Finalizar</span>
              <span class="text-caption">
                {{ tipoComprobante === 'FACTURA' ? 'Emitir factura' : 'Generar voucher' }} · Bs {{ money(total) }}
              </span>
            </div>
          </q-btn>
        </div>
      </q-page-sticky>
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
      // Muestra solo lo que cambio contra el pedido o se agrego.
      soloCambiados: false,
      cargando: true,
      guardando: false,
      guardandoBorrador: false,
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
    nombreTipo () { return { NORMAL: 'EMBUTIDOS', PODIUM: 'PODIUM Y HUEVO' }[this.tipoPedido] || this.tipoPedido },
    // Lo dice el backend: pollo, cerdo y res se pesan en canastillos.
    conCanastillos () { return !!(this.pedido && this.pedido.con_canastillos) },
    kgCanastillo () { return Number((this.pedido && this.pedido.kg_canastillo) || 2) },
    // Sin el permiso el precio queda con el del pedido o el del catalogo.
    puedeCambiarPrecio () { return this.$store.getters['login/can']('facturacionPrecio') },
    total () {
      return this.items.reduce((suma, item) => suma + this.facturable(item) * Number(item.precio || 0), 0)
    },
    // Los nombres de precio que tiene al menos un producto: Pedido primero y
    // despues Precio 1 a 13 en orden.
    nombresPrecio () {
      const nombres = new Set()
      this.items.forEach(item => (item.precios || []).forEach(p => (p.nombres || [p.label]).forEach(n => nombres.add(n))))
      const orden = n => n === 'Pedido' ? 0 : (Number(String(n).replace(/\D/g, '')) || 99)
      return [...nombres].sort((a, b) => orden(a) - orden(b))
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
    // Si ya no queda nada cambiado el aviso (y su boton) se va: se vuelve a
    // ver todo para que la lista no quede vacia.
    filtrandoCambios () {
      return this.soloCambiados && (this.lineasCambiadas.length + this.lineasNuevas.length) > 0
    },
    lineasNuevas () {
      return this.items.filter(item => this.esNuevo(item))
    },
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
    // Los productos a granel se cobran por el peso de la balanza; el resto,
    // por la cantidad de unidades. CAJA no es un bulto cerrado: es el granel
    // que el preventista pide en unidades, cajas o kilos, y que en el
    // mostrador se pesa igual que el de KG.
    esPeso (item) { return ['KG', 'CAJA'].includes(String(item.unidad || '').toUpperCase()) },
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
    /** La opcion de la lista con ese nombre ("Precio 8"), o null. */
    precioLlamado (item, nombre) {
      return (item.precios || []).find(p => (p.nombres || [p.label]).includes(nombre)) || null
    },
    conPrecio (nombre) {
      return this.items.filter(item => this.precioLlamado(item, nombre)).length
    },
    // Las opciones del select: los precios de lista, el actual si no esta en
    // la lista, y con permiso uno escrito a mano.
    opcionesPrecio (item) {
      const lista = (item.precios || []).map(p => ({
        label: p.label + ' · Bs ' + this.money(p.value), value: Number(p.value)
      }))
      const actual = Math.round(Number(item.precio || 0) * 100) / 100
      if (actual > 0 && !lista.some(p => p.value === actual)) {
        lista.unshift({ label: 'Actual · Bs ' + this.money(actual), value: actual })
      }
      if (this.puedeCambiarPrecio) lista.push({ label: 'Otro precio…', value: 'otro' })
      return lista
    },
    // En el campo va el importe y, si es de lista, de que precio es.
    textoPrecio (item) {
      const actual = Math.round(Number(item.precio || 0) * 100) / 100
      const opcion = (item.precios || []).find(p => Number(p.value) === actual)
      return 'Bs ' + this.money(actual) + (opcion ? ' · ' + opcion.label : '')
    },
    elegirPrecio (item, valor) {
      if (valor !== 'otro') {
        item.precio = Number(valor)
        this.actualizar(item)
        return
      }
      this.$q.dialog({
        title: 'Otro precio',
        message: item.nombre,
        prompt: { model: String(item.precio || ''), type: 'number', inputmode: 'decimal' },
        cancel: { flat: true, label: 'Cancelar' },
        ok: { unelevated: true, label: 'Aplicar' }
      }).onOk(texto => {
        const numero = Math.round(Number(texto) * 100) / 100
        if (isNaN(numero) || numero < 0) return
        item.precio = numero
        this.actualizar(item)
      })
    },
    // Todos los productos al mismo precio de lista. Los que no tienen ese
    // precio cargado (o en cero) se quedan como estaban y se avisa cuales.
    cambiarTodos (nombre) {
      const sinPrecio = []
      let cambiados = 0
      this.items.forEach(item => {
        const opcion = this.precioLlamado(item, nombre)
        if (!opcion) {
          sinPrecio.push(item.nombre)
          return
        }
        item.precio = Number(opcion.value)
        this.actualizar(item)
        cambiados++
      })
      this.$q.notify({
        type: sinPrecio.length ? 'warning' : 'positive',
        position: 'top',
        timeout: sinPrecio.length ? 8000 : 3000,
        message: cambiados + (cambiados === 1 ? ' producto pasó' : ' productos pasaron') + ' a ' + nombre +
          (sinPrecio.length ? ' · sin ' + nombre + ' (quedan igual): ' + sinPrecio.join(', ') : '')
      })
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
          // Lo guardado sin finalizar manda sobre lo que trae el pedido.
          const borrador = this.pedido.borrador
          if (borrador) {
            if (borrador.tipo_comprobante) this.tipoComprobante = borrador.tipo_comprobante
            if (borrador.tipo_pago) this.tipoPago = borrador.tipo_pago
            if (borrador.nit !== null) this.nit = borrador.nit || ''
            if (borrador.observacion !== null) this.observacion = borrador.observacion || ''
          }
        })
        .catch(this.error)
        .finally(() => { this.cargando = false })
    },
    volver () {
      // Vuelve a la fecha que estaba elegida en el listado; si se entro sin
      // ella, a la de entrega del pedido, que es por la que filtra el listado.
      const fecha = this.$route.query.fecha ||
        String(this.pedido?.fecha_entrega || this.pedido?.fecha || '').substr(0, 10)
      const query = { fecha, tipo: this.tipoPedido }
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
          precios: producto.precios || [],
          total: this.esPeso(producto) ? 0 : Number(producto.precio || 0)
        })
      }
      this.dialogCatalogo = false
    },
    /**
     * Duplica una linea justo debajo de la original. La copia entra como
     * agregada (sin cantidad pedida, para no contar dos veces el cambio
     * contra el pedido) y sin pesar: el cajero le pone su cantidad, peso y
     * precio.
     */
    copiar (indice) {
      const original = this.items[indice]
      const copia = {
        cod_prod: original.cod_prod,
        nombre: original.nombre,
        unidad: original.unidad,
        caja: original.caja || null,
        cantidad: 1,
        cantidad_pedida: null,
        peso: null,
        peso_bruto: null,
        canastillos: null,
        precio: Number(original.precio || 0),
        precios: original.precios || [],
        total: 0
      }
      this.actualizar(copia)
      this.items.splice(indice + 1, 0, copia)
    },
    // Pasa la linea de unidad a kilo o al reves. Lo pesado se descarta: al
    // pasar a kilo hay que pesarlo; al pasar a unidad se cobra la cantidad.
    cambiarUnidad (item) {
      item.unidad = this.esPeso(item) ? 'UNIDAD' : 'KG'
      item.peso = null
      item.peso_bruto = null
      item.canastillos = null
      this.actualizar(item)
    },
    guardar () {
      if (this.tipoComprobante === 'FACTURA' && !this.nit) {
        this.$q.notify({
          type: 'warning',
          position: 'top',
          message: 'Para facturar hace falta el NIT o CI: escribirlo aqui o corregirlo en la ficha del cliente'
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
          // Puede no coincidir con el catalogo si se cambio la unidad aca.
          por_peso: this.esPeso(item),
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
    // Guarda lo cargado hasta ahora sin crear la venta: puede faltar pesar.
    guardarBorrador () {
      this.guardandoBorrador = true
      this.$api.post('facturacion/pedidos/' + this.numeroPedido + '/borrador', {
        pedido_tipo: this.tipoPedido,
        tipo_comprobante: this.tipoComprobante,
        tipo_pago: this.tipoPago,
        nit: this.nit,
        observacion: this.observacion,
        items: this.items.map(item => ({
          cod_prod: item.cod_prod,
          nombre: item.nombre,
          unidad: item.unidad,
          caja: item.caja || null,
          cantidad: Number(item.cantidad) || 0,
          cantidad_pedida: this.esNuevo(item) ? null : Number(item.cantidad_pedida),
          peso: Number(item.peso) > 0 ? Number(item.peso) : null,
          peso_bruto: Number(item.peso_bruto) > 0 ? Number(item.peso_bruto) : null,
          canastillos: item.canastillos === null || item.canastillos === undefined || item.canastillos === ''
            ? null : Math.max(0, Math.trunc(Number(item.canastillos) || 0)),
          precio: Number(item.precio) || 0,
          recuperado: !!item.recuperado,
          retorno: !!item.retorno
        }))
      }).then(res => {
        this.$q.notify({ type: 'positive', position: 'top', message: res.data.message })
        setTimeout(this.volver, 800)
      }).catch(this.error)
        .finally(() => { this.guardandoBorrador = false })
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

/* Cantidad, peso, canastillos y precio en una fila baja: con varios productos
   entran mas en pantalla. La etiqueta queda fija arriba, chica. */
.campos-compactos :deep(.q-field--dense .q-field__control),
.campos-compactos :deep(.q-field--dense .q-field__marginal) {
  height: 34px;
  min-height: 34px;
}
.campos-compactos :deep(.q-field--dense .q-field__control) {
  padding: 0 6px;
}
.campos-compactos :deep(.q-field__native) {
  font-size: 13px;
  padding-top: 12px;
  padding-bottom: 0;
}
.campos-compactos :deep(.q-field--dense .q-field__label) {
  font-size: 11px;
  top: 9px;
}
.campos-compactos :deep(.q-field--dense.q-field--float .q-field__label) {
  transform: translateY(-55%) scale(0.85);
}
.campos-compactos :deep(.q-field__append .q-icon) {
  font-size: 14px;
}

/* Guardar y Finalizar lado a lado, misma altura; Finalizar ocupa mas porque
   es la accion principal. */
.barra-acciones {
  display: flex;
  gap: 8px;
  padding: 8px 12px;
}
.boton-accion {
  min-height: 52px;
  border-radius: 10px;
}
/* Guardar toma el ancho de su texto (nunca se corta) y Finalizar el resto. */
.boton-guardar {
  flex: 0 0 auto;
  font-weight: 600;
  white-space: nowrap;
  padding: 0 14px;
}
.boton-finalizar {
  flex: 1 1 auto;
  min-width: 0;
}
.leading {
  line-height: 1.15;
  text-align: left;
}
/* Lo que ya no es lo que pidio el cliente: fondo y borde para verlo de lejos. */
.linea-cambiada {
  background: #ffe0b2;
  border-left: 5px solid #e64a19;
}
.linea-nueva {
  background: #e3f2fd;
  border-left: 5px solid #1976d2;
}
</style>
