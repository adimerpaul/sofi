<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="col">
        <div class="text-h6 text-weight-bold">Nueva venta</div>
        <div class="text-caption text-grey-7">
          Arma el carrito y confirma si se entrega venta o factura
        </div>
      </div>
      <q-btn dense flat no-caps icon="receipt_long" label="Ver facturación" to="/facturacion"/>
    </div>

    <div class="row q-col-gutter-md">
      <!-- Catálogo -->
      <div class="col-12 col-md-7">
        <q-card flat bordered>
          <q-card-section class="row q-col-gutter-sm q-pa-sm">
            <q-input
              ref="buscador"
              v-model="filtros.buscar"
              dense outlined clearable autofocus class="col"
              placeholder="Buscar por nombre o código"
              @update:model-value="buscarConRetraso"
            >
              <template v-slot:prepend><q-icon name="search"/></template>
            </q-input>
            <q-select
              v-model="filtros.grupo"
              dense outlined clearable emit-value map-options
              use-input fill-input hide-selected input-debounce="0"
              label="Categoría" style="min-width: 190px"
              :options="categoriasFiltradas"
              @filter="filtrarCategorias"
              @update:model-value="recargarCatalogo"
            />
          </q-card-section>

          <q-separator/>

          <q-card-section v-if="cargando" class="flex flex-center" style="min-height: 260px">
            <q-spinner color="primary" size="36px"/>
          </q-card-section>

          <q-card-section v-else class="q-pa-sm">
            <div v-if="productos.length === 0" class="text-center text-grey-7 q-pa-lg">
              <q-icon name="inventory_2" size="28px" class="q-mr-sm"/>
              No hay productos con ese criterio
            </div>

            <div class="row q-col-gutter-sm">
              <div v-for="p in productos" :key="p.cod_prod" class="col-6 col-sm-4 col-lg-3">
                <q-card
                  flat bordered
                  class="producto full-height column"
                  :class="sinStock(p) ? 'producto--vacio' : 'cursor-pointer'"
                  @click="abrirProducto(p)"
                >
                  <div class="producto-foto">
                    <img v-if="p.imagen" :src="urlImagen(p.imagen)" alt=""/>
                    <q-icon v-else name="inventory_2" size="34px" color="grey-4"/>
                    <div v-if="sinStock(p)" class="producto-sinstock">SIN STOCK</div>
                  </div>
                  <q-card-section class="q-pa-sm col column justify-between">
                    <div>
                      <div class="text-caption text-weight-bold producto-nombre">{{ p.producto }}</div>
                      <div class="text-caption text-grey-6">{{ p.cod_prod }}</div>
                    </div>
                    <div class="q-mt-xs">
                      <div class="text-weight-bold text-primary">
                        Bs {{ money(p.precio) }}<span v-if="p.unidad === 'KG'">/kg</span>
                      </div>
                      <q-badge
                        :color="sinStock(p) ? 'negative' : 'positive'"
                        :label="'Stock ' + cantidad(p.stock, p.unidad) + ' ' + (p.unidad || '')"
                      />
                    </div>
                  </q-card-section>
                </q-card>
              </div>
            </div>
          </q-card-section>

          <q-separator/>
          <q-card-actions class="row items-center justify-between q-px-md">
            <span class="text-caption text-grey-7">
              {{ paginacion.from || 0 }}–{{ paginacion.to || 0 }} de {{ paginacion.total || 0 }} productos
            </span>
            <q-pagination
              v-model="paginacion.page"
              :max="paginacion.lastPage || 1"
              :max-pages="6"
              boundary-numbers direction-links size="sm" color="primary"
              @update:model-value="cargarCatalogo"
            />
          </q-card-actions>
        </q-card>
      </div>

      <!-- Carrito -->
      <div class="col-12 col-md-5">
        <q-card flat bordered>
          <q-card-section class="row items-center q-py-sm">
            <q-icon name="shopping_cart" color="primary" size="22px" class="q-mr-sm"/>
            <div class="text-subtitle1 text-weight-bold">Carrito</div>
            <q-space/>
            <q-btn
              v-if="carrito.length" dense flat no-caps color="negative"
              icon="delete_sweep" label="Vaciar" @click="carrito = []"
            />
            <q-badge color="primary" :label="carrito.length"/>
          </q-card-section>

          <q-separator/>

          <q-list v-if="carrito.length" separator style="max-height: 42vh; overflow-y: auto">
            <q-item v-for="(item, i) in carrito" :key="i" dense class="q-py-sm">
              <q-item-section>
                <q-item-label lines="1" class="text-caption text-weight-medium">
                  {{ item.nombre }}
                </q-item-label>
                <div class="row q-col-gutter-xs q-mt-xs items-center">
                  <q-input
                    v-model.number="item.cantidad"
                    dense outlined type="number" class="col"
                    :label="item.unidad === 'KG' ? 'Kilos' : 'Cant.'"
                    :step="paso(item)" :min="paso(item)"
                    @update:model-value="normalizar(item)"
                  />
                  <q-input
                    v-model.number="item.precio"
                    dense outlined type="number" class="col" label="Precio" step="0.01" min="0"
                  />
                  <div class="col-auto text-right" style="min-width: 74px">
                    <div class="text-caption text-grey-7">Total</div>
                    <b>{{ money(item.cantidad * item.precio) }}</b>
                  </div>
                  <q-btn
                    dense flat round size="sm" icon="delete" color="negative"
                    @click="carrito.splice(i, 1)"
                  />
                </div>
              </q-item-section>
            </q-item>
          </q-list>

          <q-card-section v-else class="text-center text-grey-6 q-py-xl">
            <q-icon name="remove_shopping_cart" size="42px"/>
            <div>Agrega productos</div>
          </q-card-section>

          <q-separator/>

          <q-card-section class="q-pa-md">
            <div class="row text-body2">
              <span>Subtotal</span><q-space/><b>Bs {{ money(subtotal) }}</b>
            </div>
            <div class="row text-body2 text-negative items-center">
              <span>Descuento</span><q-space/>
              <q-input
                v-model.number="descuento" dense borderless input-class="text-right"
                type="number" min="0" :max="subtotal" step="0.01" style="max-width: 90px"
              />
            </div>
            <q-separator class="q-my-xs"/>
            <div class="row text-h6 text-primary">
              <b>Total</b><q-space/><b>Bs {{ money(total) }}</b>
            </div>
          </q-card-section>

          <q-card-actions class="q-pa-sm">
            <q-btn
              class="full-width" color="positive" unelevated no-caps
              icon="point_of_sale" label="Continuar y cobrar"
              :disable="carrito.length === 0"
              @click="dialogCobro = true"
            />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <!-- Cantidad y precio -->
    <q-dialog v-model="dialogProducto" @hide="enfocarBuscador">
      <q-card style="width: 420px; max-width: 94vw">
        <q-form @submit.prevent="agregarAlCarrito">
          <q-card-section class="bg-primary text-white q-py-sm">
            <div class="text-subtitle1 text-weight-bold">{{ elegido.producto }}</div>
            <div class="text-caption">
              {{ elegido.cod_prod }} · Stock {{ cantidad(elegido.stock, elegido.unidad) }} {{ elegido.unidad }}
            </div>
          </q-card-section>

          <q-card-section class="row q-col-gutter-sm">
            <q-input
              v-model.number="cantidadElegida"
              outlined dense autofocus type="number" class="col-12 col-sm-6"
              :label="elegido.unidad === 'KG' ? 'Kilos' : 'Cantidad'"
              :step="paso(elegido)" :min="paso(elegido)"
              @focus="$event.target.select()"
            />
            <q-select
              v-model="precioElegido"
              outlined dense class="col-12 col-sm-6"
              label="Precio Bs" prefix="Bs"
              use-input fill-input hide-selected input-debounce="0"
              new-value-mode="add-unique"
              :options="elegido.precios || []"
              @new-value="precioManual"
            />
            <div class="col-12 row items-center q-pa-sm rounded-borders bg-orange-1 text-orange-10">
              <span>Total</span><q-space/>
              <b class="text-h6">Bs {{ money(cantidadElegida * precioElegido) }}</b>
            </div>
          </q-card-section>

          <q-separator/>
          <q-card-actions align="right" class="q-pa-sm">
            <q-btn flat dense no-caps label="Cancelar" v-close-popup/>
            <q-btn
              type="submit" dense unelevated color="positive" no-caps
              icon="add_shopping_cart" label="Agregar"
            />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <!-- Venta o factura -->
    <q-dialog v-model="dialogCobro">
      <q-card style="width: 640px; max-width: 96vw">
        <q-card-section class="bg-primary text-white q-py-sm">
          <div class="text-subtitle1 text-weight-bold">Confirmar</div>
          <div class="text-caption">{{ carrito.length }} productos · Bs {{ money(total) }}</div>
        </q-card-section>

        <q-card-section>
          <div class="text-caption text-grey-7 q-mb-xs">¿Qué se entrega?</div>
          <q-btn-toggle
            v-model="tipoComprobante" spread no-caps unelevated
            toggle-color="primary" color="grey-3" text-color="grey-8"
            :options="[
              { label: 'Venta (voucher)', value: 'VENTA', icon: 'receipt' },
              { label: 'Factura', value: 'FACTURA', icon: 'verified' }
            ]"
          />

          <q-select
            v-model="cliente"
            class="q-mt-sm"
            outlined dense clearable use-input hide-selected fill-input
            input-debounce="350"
            label="Cliente (busca por NIT o nombre)"
            :options="clientes"
            option-label="nombre"
            :loading="buscandoCliente"
            @filter="buscarClientes"
          >
            <template v-slot:option="scope">
              <q-item v-bind="scope.itemProps">
                <q-item-section>
                  <q-item-label>{{ scope.opt.nombre }}</q-item-label>
                  <q-item-label caption>
                    NIT {{ scope.opt.nit }}
                    <span v-if="scope.opt.zona">· {{ scope.opt.zona }}</span>
                    <span v-if="scope.opt.vendedor">· {{ scope.opt.vendedor }}</span>
                  </q-item-label>
                </q-item-section>
              </q-item>
            </template>
            <template v-slot:no-option>
              <q-item>
                <q-item-section class="text-grey">
                  Escribe al menos 2 letras del nombre o del NIT
                </q-item-section>
              </q-item>
            </template>
          </q-select>

          <div class="row q-col-gutter-sm q-mt-xs">
            <q-input
              v-model.trim="nit" outlined dense class="col-12 col-sm-5"
              :label="tipoComprobante === 'FACTURA' ? 'NIT o CI (obligatorio)' : 'NIT o CI (opcional)'"
            />
            <q-select
              v-model="tipoPago" outlined dense class="col-12 col-sm-3"
              label="Pago" :options="['EFECTIVO', 'QR', 'TARJETA', 'CRÉDITO']"
            />
            <q-input
              v-model.trim="observacion" outlined dense class="col-12 col-sm-4" label="Observación"
            />
          </div>

          <q-banner dense rounded class="q-mt-sm" :class="tipoComprobante === 'FACTURA' ? 'bg-orange-1 text-orange-10' : 'bg-grey-2 text-grey-8'">
            <template v-slot:avatar>
              <q-icon :name="tipoComprobante === 'FACTURA' ? 'verified' : 'receipt'"/>
            </template>
            <span v-if="tipoComprobante === 'FACTURA'">
              Queda registrada como factura con ese NIT. Todavía no se envía nada
              a Impuestos desde aquí.
            </span>
            <span v-else>Se registra como venta con voucher.</span>
          </q-banner>

          <q-markup-table flat bordered dense class="q-mt-sm">
            <thead>
            <tr class="bg-grey-2">
              <th class="text-left">Producto</th>
              <th class="text-right">Cant.</th>
              <th class="text-right">Precio</th>
              <th class="text-right">Total</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(item, i) in carrito" :key="i">
              <td class="text-left">{{ item.nombre }}</td>
              <td class="text-right">{{ cantidad(item.cantidad, item.unidad) }}</td>
              <td class="text-right">{{ money(item.precio) }}</td>
              <td class="text-right text-weight-bold">{{ money(item.cantidad * item.precio) }}</td>
            </tr>
            </tbody>
          </q-markup-table>
        </q-card-section>

        <q-separator/>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense no-caps label="Volver" v-close-popup/>
          <q-btn
            color="positive" dense unelevated no-caps icon="save"
            label="Guardar" :loading="guardando" @click="guardar"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
export default {
  name: 'FacturaNueva',
  data () {
    return {
      productos: [],
      categorias: [],
      categoriasFiltradas: [],
      carrito: [],
      filtros: { buscar: '', grupo: null },
      paginacion: { page: 1, lastPage: 1, total: 0, from: 0, to: 0 },
      cargando: false,
      descuento: 0,
      // Producto sobre el que se elige cantidad y precio.
      dialogProducto: false,
      elegido: {},
      cantidadElegida: 1,
      precioElegido: 0,
      // Cierre.
      dialogCobro: false,
      tipoComprobante: 'VENTA',
      tipoPago: 'EFECTIVO',
      cliente: null,
      clientes: [],
      buscandoCliente: false,
      nit: '',
      observacion: '',
      guardando: false,
      temporizador: null
    }
  },
  computed: {
    subtotal () {
      return this.carrito.reduce((a, i) => a + Number(i.cantidad || 0) * Number(i.precio || 0), 0)
    },
    total () {
      const desc = Math.min(Math.max(Number(this.descuento) || 0, 0), this.subtotal)
      return this.subtotal - desc
    }
  },
  watch: {
    // Al elegir cliente se hereda su NIT, que es lo que se factura.
    cliente (valor) {
      if (valor && valor.nit) {
        this.nit = valor.nit
      }
    }
  },
  created () {
    this.cargarCategorias()
    this.cargarCatalogo()
  },
  methods: {
    money (v) {
      return Number(v || 0).toFixed(2)
    },
    // Las fotos se sirven desde public/, no desde /api/, de ahi el recorte.
    urlImagen (ruta) {
      return String(this.$url || '').replace(/api\/?$/, '') + ruta
    },
    // Lo que va a granel se vende con decimales; el resto por unidad.
    paso (p) {
      return p && p.unidad === 'KG' ? 0.001 : 1
    },
    cantidad (v, unidad) {
      return Number(v || 0).toFixed(unidad === 'KG' ? 3 : 0)
    },
    sinStock (p) {
      return Number(p.stock || 0) <= 0
    },

    cargarCategorias () {
      this.$api.get('facturacion/categorias').then(res => {
        this.categorias = res.data
        this.categoriasFiltradas = res.data
      }).catch(() => {})
    },
    filtrarCategorias (texto, update) {
      update(() => {
        const busca = (texto || '').toLowerCase().trim()
        this.categoriasFiltradas = busca === ''
          ? this.categorias
          : this.categorias.filter(c => String(c.label).toLowerCase().includes(busca))
      })
    },
    recargarCatalogo () {
      this.paginacion.page = 1
      this.cargarCatalogo()
    },
    // Se espera a que deje de teclear para no consultar por cada letra.
    buscarConRetraso () {
      clearTimeout(this.temporizador)
      this.temporizador = setTimeout(this.recargarCatalogo, 300)
    },
    cargarCatalogo () {
      this.cargando = true

      this.$api.get('facturacion/catalogo', {
        params: {
          buscar: this.filtros.buscar || '',
          grupo: this.filtros.grupo || '',
          page: this.paginacion.page,
          perPage: 20
        }
      }).then(res => {
        this.productos = res.data.data
        this.paginacion = {
          page: res.data.current_page,
          lastPage: res.data.last_page,
          total: res.data.total,
          from: res.data.from,
          to: res.data.to
        }
      }).catch(err => {
        this.avisar(err, 'No se pudo cargar el catálogo')
      }).finally(() => {
        this.cargando = false
      })
    },

    buscarClientes (texto, update, abort) {
      if ((texto || '').trim().length < 2) {
        abort()
        return
      }

      this.buscandoCliente = true
      this.$api.get('facturacion/clientes', { params: { buscar: texto } })
        .then(res => { update(() => { this.clientes = res.data }) })
        .catch(() => { abort() })
        .finally(() => { this.buscandoCliente = false })
    },

    abrirProducto (p) {
      if (this.sinStock(p)) {
        this.$q.notify({
          message: 'Sin stock: ' + p.producto,
          color: 'warning',
          icon: 'info',
          position: 'top'
        })
        return
      }

      this.elegido = p
      this.cantidadElegida = this.paso(p)
      this.precioElegido = p.precio
      this.dialogProducto = true
    },
    // El select de precios acepta un importe escrito a mano.
    precioManual (valor, done) {
      const numero = Number(valor)
      if (!isNaN(numero) && numero >= 0) {
        done(numero, 'add-unique')
      }
    },
    normalizar (item) {
      const minimo = this.paso(item)
      if (!item.cantidad || item.cantidad < minimo) {
        item.cantidad = minimo
      }
    },
    agregarAlCarrito () {
      const cant = Number(this.cantidadElegida)
      const precio = Number(this.precioElegido)

      if (!cant || cant <= 0) {
        return
      }

      // Mismo producto al mismo precio: se suma en vez de repetir la línea.
      const existente = this.carrito.find(
        i => i.cod_prod === this.elegido.cod_prod && Number(i.precio) === precio
      )

      if (existente) {
        existente.cantidad = Number(existente.cantidad) + cant
      } else {
        this.carrito.push({
          cod_prod: this.elegido.cod_prod,
          nombre: this.elegido.producto,
          unidad: this.elegido.unidad,
          cantidad: cant,
          precio
        })
      }

      this.dialogProducto = false
    },
    enfocarBuscador () {
      this.$refs.buscador && this.$refs.buscador.focus()
    },

    guardar () {
      if (this.tipoComprobante === 'FACTURA' && !String(this.nit || '').trim()) {
        this.$q.notify({
          message: 'Para factura hace falta el NIT o CI del cliente',
          color: 'warning',
          icon: 'info',
          position: 'top'
        })
        return
      }

      this.guardando = true

      this.$api.post('facturacion', {
        tipo_comprobante: this.tipoComprobante,
        tipo_pago: this.tipoPago,
        cliente_id: this.cliente ? this.cliente.id : null,
        nit: this.nit || '',
        nombre: this.cliente ? this.cliente.nombre : '',
        descuento: Number(this.descuento) || 0,
        observacion: this.observacion || '',
        items: this.carrito.map(i => ({
          cod_prod: i.cod_prod,
          cantidad: Number(i.cantidad),
          precio: Number(i.precio)
        }))
      }).then(res => {
        this.$q.notify({
          message: res.data.message,
          color: 'positive',
          icon: 'check_circle',
          position: 'top',
          timeout: 5000
        })

        this.limpiar()
      }).catch(err => {
        this.avisar(err, 'No se pudo registrar')
      }).finally(() => {
        this.guardando = false
      })
    },

    limpiar () {
      this.carrito = []
      this.descuento = 0
      this.cliente = null
      this.nit = ''
      this.observacion = ''
      this.tipoComprobante = 'VENTA'
      this.tipoPago = 'EFECTIVO'
      this.dialogCobro = false
    },

    avisar (err, porDefecto) {
      this.$q.notify({
        message: err.response?.data?.message || porDefecto,
        color: 'negative',
        icon: 'error',
        position: 'top'
      })
    }
  }
}
</script>

<style scoped>
.producto {
  min-height: 118px;
  transition: border-color .15s;
}
.producto-foto {
  position: relative;
  height: 92px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fafafa;
  overflow: hidden;
}
.producto-foto img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.producto-sinstock {
  position: absolute;
  top: 6px;
  left: 0;
  right: 0;
  text-align: center;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .5px;
  color: #fff;
  background: rgba(211, 47, 47, .85);
  padding: 1px 0;
}
.producto:not(.producto--vacio):hover {
  border-color: var(--q-primary);
}
.producto--vacio {
  opacity: .55;
}
.producto-nombre {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.2;
  min-height: 2.4em;
}
</style>
