<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="col">
        <div class="text-h6 text-weight-bold">Nueva compra</div>
        <div class="text-caption text-grey-7">
          Selecciona productos; al guardar se suma al stock
        </div>
      </div>
      <q-btn dense flat no-caps icon="store" label="Proveedores" to="/proveedores" class="q-mr-sm"/>
      <q-btn dense flat no-caps icon="inventory" label="Ver compras" to="/compras"/>
    </div>

    <div class="row q-col-gutter-md">
      <!-- Catálogo -->
      <div class="col-12 col-md-6">
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

          <q-card-section v-if="cargando" class="flex flex-center" style="min-height: 240px">
            <q-spinner color="primary" size="36px"/>
          </q-card-section>

          <q-card-section v-else class="q-pa-sm">
            <div v-if="productos.length === 0" class="text-center text-grey-7 q-pa-lg">
              <q-icon name="inventory_2" size="28px" class="q-mr-sm"/>
              No hay productos con ese criterio
            </div>

            <div class="row q-col-gutter-sm">
              <div v-for="p in productos" :key="p.cod_prod" class="col-4 col-sm-3 col-md-2">
                <!-- En compras se puede comprar aunque el stock esté en 0. -->
                <q-card flat bordered class="producto full-height column cursor-pointer" @click="abrirProducto(p)">
                  <div class="producto-foto">
                    <img v-if="p.imagen" :src="urlImagen(p.imagen)" alt=""/>
                    <q-icon v-else name="inventory_2" size="22px" color="grey-4"/>
                  </div>
                  <q-card-section class="col column justify-between">
                    <div>
                      <div class="text-caption text-weight-bold producto-nombre">{{ p.producto }}</div>
                      <div class="text-grey-6 producto-codigo">{{ p.cod_prod }}</div>
                    </div>
                    <div class="q-mt-xs">
                      <div class="text-weight-bold text-primary producto-precio">
                        Bs {{ money(p.precio) }}<span v-if="p.unidad === 'KG'">/kg</span>
                      </div>
                      <q-badge
                        :color="Number(p.stock) > 0 ? 'positive' : 'grey-6'"
                        :label="cantidad(p.stock, p.unidad) + ' ' + (p.unidad || '')"
                      >
                        <q-tooltip>Stock disponible</q-tooltip>
                      </q-badge>
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

      <!-- Detalle de la compra -->
      <div class="col-12 col-md-6">
        <q-card flat bordered>
          <q-card-section class="row items-center q-py-sm">
            <q-icon name="local_shipping" color="primary" size="22px" class="q-mr-sm"/>
            <div class="text-subtitle1 text-weight-bold">Compra</div>
            <q-space/>
            <q-btn
              v-if="items.length" dense flat no-caps color="negative"
              icon="delete_sweep" label="Vaciar" @click="items = []"
            />
            <q-badge color="primary" :label="items.length"/>
          </q-card-section>

          <q-separator/>

          <q-list v-if="items.length" separator class="carrito" style="max-height: 58vh; overflow-y: auto">
            <!-- Todo editable aqui: al cargar muchas lineas seguidas es mas
                 rapido corregir en el renglon que reabrir el dialogo. -->
            <q-item v-for="(item, i) in items" :key="i" dense class="q-py-none q-px-sm">
              <q-item-section avatar class="carrito-thumb">
                <q-avatar rounded size="30px" color="grey-2">
                  <img v-if="item.imagen" :src="urlImagen(item.imagen)" alt=""/>
                  <q-icon v-else name="inventory_2" size="16px" color="grey-5"/>
                </q-avatar>
              </q-item-section>

              <q-item-section>
                <div class="carrito-nombre ellipsis">{{ item.nombre }}</div>

                <div class="row q-col-gutter-xs items-center no-wrap">
                  <q-input
                    v-model.number="item.cantidad"
                    dense outlined type="number" class="col"
                    :label="item.unidad === 'KG' ? 'Kilos' : 'Cant.'"
                    :step="paso(item)" :min="paso(item)"
                    @update:model-value="normalizar(item)"
                  />
                  <q-input
                    v-model.number="item.precio"
                    dense outlined type="number" class="col" label="Costo" step="0.01" min="0"
                    @update:model-value="sincronizarTotal(item)"
                  />
                  <!-- El total tambien se escribe: cuando la factura del
                       proveedor trae el importe de la linea y no el unitario,
                       se teclea aqui y el costo sale por division. -->
                  <q-input
                    v-model.number="item.total"
                    dense outlined type="number" class="col" label="Total" step="0.01" min="0"
                    @blur="aplicarTotal(item)"
                    @keyup.enter="aplicarTotal(item)"
                  />
                  <q-input
                    v-model.number="item.precio_venta"
                    dense outlined type="number" class="col" label="P. venta" step="0.01" min="0"
                  />
                  <q-input
                    v-model.trim="item.lote" dense outlined class="col" label="Lote" placeholder="Opcional"
                  />
                  <q-input
                    v-model="item.fecha_vencimiento" dense outlined type="date" class="col" label="Vencimiento"
                  />
                  <q-btn
                    dense flat round size="sm" icon="delete" color="negative" class="col-auto"
                    @click="items.splice(i, 1)"
                  />
                </div>
              </q-item-section>
            </q-item>
          </q-list>

          <q-card-section v-else class="text-center text-grey-6 q-py-xl">
            <q-icon name="add_shopping_cart" size="42px"/>
            <div>Agrega productos a la compra</div>
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
              icon="save" label="Registrar compra"
              :disable="items.length === 0"
              @click="dialogConfirmar = true"
            />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <!-- Datos de la línea -->
    <q-dialog v-model="dialogProducto" @hide="enfocarBuscador">
      <q-card class="dialogo-linea" style="width: 380px; max-width: 94vw">
        <q-form @submit.prevent="agregarItem">
          <q-card-section class="bg-primary text-white q-py-xs">
            <div class="text-weight-bold dialogo-titulo ellipsis">{{ elegido.producto }}</div>
            <div class="dialogo-sub">
              {{ elegido.cod_prod }} · Stock {{ cantidad(elegido.stock, elegido.unidad) }} {{ elegido.unidad }}
            </div>
          </q-card-section>

          <!-- Solo lo imprescindible para agregar: el precio de venta, el
               lote y el vencimiento se cargan en el carrito. -->
          <q-card-section class="row q-col-gutter-xs q-pa-sm">
            <q-input
              v-model.number="linea.cantidad"
              outlined dense autofocus type="number" class="col-6"
              :label="elegido.unidad === 'KG' ? 'Kilos' : 'Cantidad'"
              :step="paso(elegido)" :min="paso(elegido)"
              @focus="$event.target.select()"
            />
            <q-input
              v-model.number="linea.precio" outlined dense type="number" step="0.01" min="0"
              class="col-6" label="Precio unitario" prefix="Bs"
            />

            <div class="col-12 row items-center q-px-sm q-py-xs rounded-borders bg-orange-1 text-orange-10">
              <span class="dialogo-sub">Total</span><q-space/>
              <b>Bs {{ money(linea.cantidad * linea.precio) }}</b>
            </div>
          </q-card-section>

          <q-separator/>
          <q-card-actions align="right" class="q-pa-xs">
            <q-btn flat dense size="sm" no-caps label="Cancelar" v-close-popup/>
            <q-btn type="submit" dense size="sm" unelevated color="positive" no-caps icon="add" label="Agregar"/>
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <!-- Confirmación -->
    <q-dialog v-model="dialogConfirmar">
      <q-card class="dialogo-confirmar" style="width: 820px; max-width: 96vw">
        <q-card-section class="bg-primary text-white q-py-sm">
          <div class="text-subtitle1 text-weight-bold">Confirmar compra</div>
          <div class="text-caption">{{ items.length }} productos · Bs {{ money(total) }}</div>
        </q-card-section>

        <q-card-section>
          <div class="row q-col-gutter-sm items-center no-wrap">
            <q-select
              v-model="proveedor"
              outlined dense clearable use-input hide-selected fill-input
              input-debounce="350"
              label="Proveedor" class="col"
              :options="proveedores"
              option-label="nombre"
              :loading="buscandoProveedor"
              @filter="buscarProveedores"
            >
              <template v-slot:option="scope">
                <q-item v-bind="scope.itemProps">
                  <q-item-section>
                    <q-item-label>{{ scope.opt.nombre }}</q-item-label>
                    <q-item-label caption>NIT {{ scope.opt.nit }}</q-item-label>
                  </q-item-section>
                </q-item>
              </template>
              <template v-slot:no-option>
                <q-item>
                  <q-item-section class="text-grey">
                    Sin proveedores con ese nombre; usa el + para crearlo
                  </q-item-section>
                </q-item>
              </template>
            </q-select>

            <!-- Alta rápida sin salir de la compra. -->
            <q-btn
              class="col-auto" color="positive" unelevated dense icon="add"
              @click="abrirProveedorRapido"
            >
              <q-tooltip>Agregar un proveedor nuevo</q-tooltip>
            </q-btn>
          </div>

          <div class="row q-col-gutter-sm q-mt-xs">
            <q-input v-model.trim="nroFactura" outlined dense class="col-12 col-sm-4" label="Nº de factura"/>
            <q-select
              v-model="tipoPago" outlined dense class="col-12 col-sm-4"
              label="Pago" :options="['EFECTIVO', 'TRANSFERENCIA', 'CHEQUE', 'CRÉDITO']"
            />
            <q-input v-model.trim="observacion" outlined dense class="col-12 col-sm-4" label="Observación"/>
          </div>

          <q-banner dense rounded class="bg-blue-1 text-blue-10 q-mt-sm">
            <template v-slot:avatar><q-icon name="inventory"/></template>
            Al guardar, el stock de estos productos sube automáticamente.
          </q-banner>

          <q-markup-table flat bordered dense class="q-mt-sm tabla-resumen">
            <thead>
            <tr class="bg-grey-2">
              <th class="text-left">Producto</th>
              <th class="text-right">Cant.</th>
              <th class="text-right">Costo</th>
              <th class="text-right">P. venta</th>
              <th class="text-left">Lote</th>
              <th class="text-left">Vence</th>
              <th class="text-right">Total</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(item, i) in items" :key="i">
              <td class="text-left">{{ item.nombre }}</td>
              <td class="text-right">{{ cantidad(item.cantidad, item.unidad) }}</td>
              <td class="text-right">{{ money(item.precio) }}</td>
              <!-- Sin precio de venta el del producto no se toca. -->
              <td class="text-right">
                <span v-if="item.precio_venta">{{ money(item.precio_venta) }}</span>
                <span v-else class="text-grey-6">—</span>
              </td>
              <td class="text-left">{{ item.lote || '—' }}</td>
              <td class="text-left">{{ fechaCorta(item.fecha_vencimiento) }}</td>
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
            label="Guardar compra" :loading="guardando" @click="guardar"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Alta rápida de proveedor, desde la propia compra. -->
    <q-dialog v-model="dialogProveedor">
      <q-card style="width: 440px; max-width: 94vw">
        <q-form @submit.prevent="guardarProveedor">
          <q-card-section class="bg-primary text-white q-py-xs">
            <div class="text-weight-bold dialogo-titulo">Nuevo proveedor</div>
            <div class="dialogo-sub">Se selecciona en la compra al guardarlo</div>
          </q-card-section>

          <q-card-section class="row q-col-gutter-xs q-pa-sm">
            <q-input
              v-model.trim="nuevoProveedor.PROVEEDOR" outlined dense autofocus
              class="col-12" label="Nombre o razón social *"
            />
            <q-input
              v-model.trim="nuevoProveedor.NIT" outlined dense
              class="col-5" label="NIT (opcional)"
            />
            <q-input v-model.trim="nuevoProveedor.TELF" outlined dense class="col-7" label="Teléfono"/>
            <q-input v-model.trim="nuevoProveedor.DIRECCION" outlined dense class="col-12" label="Dirección"/>
          </q-card-section>

          <q-separator/>
          <q-card-actions align="right" class="q-pa-xs">
            <q-btn flat dense size="sm" no-caps label="Cancelar" v-close-popup/>
            <q-btn
              type="submit" dense size="sm" unelevated color="positive" no-caps
              icon="save" label="Guardar"
              :disable="!nuevoProveedor.PROVEEDOR"
              :loading="guardandoProveedor"
            />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
// El diálogo solo pide cantidad y precio; el resto se carga en el carrito.
function lineaVacia () {
  return { cantidad: 1, precio: 0 }
}

export default {
  name: 'CompraNueva',
  data () {
    return {
      productos: [],
      categorias: [],
      categoriasFiltradas: [],
      items: [],
      filtros: { buscar: '', grupo: null },
      paginacion: { page: 1, lastPage: 1, total: 0, from: 0, to: 0 },
      cargando: false,
      descuento: 0,
      dialogProducto: false,
      elegido: {},
      linea: lineaVacia(),
      dialogConfirmar: false,
      proveedor: null,
      proveedores: [],
      buscandoProveedor: false,
      dialogProveedor: false,
      guardandoProveedor: false,
      nuevoProveedor: { NIT: '', PROVEEDOR: '', DIRECCION: '', TELF: '' },
      nroFactura: '',
      tipoPago: 'EFECTIVO',
      observacion: '',
      guardando: false,
      temporizador: null
    }
  },
  computed: {
    subtotal () {
      return this.items.reduce((a, i) => a + Number(i.cantidad || 0) * Number(i.precio || 0), 0)
    },
    total () {
      const desc = Math.min(Math.max(Number(this.descuento) || 0, 0), this.subtotal)
      return this.subtotal - desc
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
    urlImagen (ruta) {
      return String(this.$url || '').replace(/api\/?$/, '') + ruta
    },
    paso (p) {
      return p && p.unidad === 'KG' ? 0.001 : 1
    },
    cantidad (v, unidad) {
      return Number(v || 0).toFixed(unidad === 'KG' ? 3 : 0)
    },
    // El input date da 'YYYY-MM-DD'; en la tabla se lee mejor al revés.
    fechaCorta (v) {
      if (!v) {
        return '—'
      }
      const partes = String(v).substr(0, 10).split('-')
      return partes.length === 3 ? partes[2] + '/' + partes[1] + '/' + partes[0] : v
    },

    cargarCategorias () {
      // El catálogo es el mismo que el de facturación: un solo endpoint.
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
          perPage: 50
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

    abrirProveedorRapido () {
      this.nuevoProveedor = { NIT: '', PROVEEDOR: '', DIRECCION: '', TELF: '' }
      this.dialogProveedor = true
    },
    guardarProveedor () {
      this.guardandoProveedor = true

      this.$api.post('proveedores', this.nuevoProveedor)
        .then(res => {
          const p = res.data.proveedor

          // Queda elegido en la compra: es lo que se venía a hacer.
          const elegido = {
            id: p.CodAut,
            nit: String(p.NIT || '').trim(),
            nombre: String(p.PROVEEDOR || '').trim()
          }
          this.proveedores = [elegido]
          this.proveedor = elegido

          this.$q.notify({
            message: res.data.message,
            color: 'positive',
            icon: 'check_circle',
            position: 'top'
          })
          this.dialogProveedor = false
        })
        .catch(err => { this.avisar(err, 'No se pudo crear el proveedor') })
        .finally(() => { this.guardandoProveedor = false })
    },

    buscarProveedores (texto, update) {
      this.buscandoProveedor = true
      this.$api.get('compras/proveedores', { params: { buscar: texto || '' } })
        .then(res => { update(() => { this.proveedores = res.data }) })
        .catch(() => { update(() => { this.proveedores = [] }) })
        .finally(() => { this.buscandoProveedor = false })
    },

    abrirProducto (p) {
      this.elegido = p
      this.linea = lineaVacia()
      this.linea.cantidad = this.paso(p)
      // Se propone el costo que ya tiene cargado el producto.
      this.linea.precio = Number(p.costo) || 0
      this.dialogProducto = true
    },
    normalizar (item) {
      const minimo = this.paso(item)
      if (!item.cantidad || item.cantidad < minimo) {
        item.cantidad = minimo
      }
      this.sincronizarTotal(item)
    },
    // El total mostrado sale de cantidad x costo.
    sincronizarTotal (item) {
      item.total = Math.round(Number(item.cantidad || 0) * Number(item.precio || 0) * 100) / 100
    },
    // Al revés: si escriben el total, el costo unitario se deduce.
    aplicarTotal (item) {
      const total = Number(item.total)
      const cant = Number(item.cantidad)

      if (!(total >= 0) || !(cant > 0)) {
        this.sincronizarTotal(item)
        return
      }

      item.precio = Math.round((total / cant) * 100) / 100
      this.sincronizarTotal(item)
    },
    agregarItem () {
      const cant = Number(this.linea.cantidad)
      if (!cant || cant <= 0) {
        return
      }

      const precio = Number(this.linea.precio) || 0

      this.items.push({
        cod_prod: this.elegido.cod_prod,
        nombre: this.elegido.producto,
        unidad: this.elegido.unidad,
        imagen: this.elegido.imagen,
        cantidad: cant,
        precio,
        total: Math.round(cant * precio * 100) / 100,
        // Estos tres se cargan en el carrito. Van vacíos y con '' en vez de
        // null porque son inputs: un null se vería como el texto "null".
        precio_venta: null,
        lote: '',
        fecha_vencimiento: ''
      })

      this.dialogProducto = false
    },
    enfocarBuscador () {
      this.$refs.buscador && this.$refs.buscador.focus()
    },

    guardar () {
      this.guardando = true

      this.$api.post('compras', {
        proveedor_id: this.proveedor ? this.proveedor.id : null,
        nit: this.proveedor ? this.proveedor.nit : '',
        proveedor: this.proveedor ? this.proveedor.nombre : '',
        nro_factura: this.nroFactura || '',
        tipo_pago: this.tipoPago,
        descuento: Number(this.descuento) || 0,
        observacion: this.observacion || '',
        items: this.items.map(i => ({
          cod_prod: i.cod_prod,
          cantidad: Number(i.cantidad),
          precio: Number(i.precio),
          precio_venta: i.precio_venta ? Number(i.precio_venta) : null,
          // El backend valida 'nullable|date': una cadena vacía no pasaría.
          lote: i.lote || null,
          fecha_vencimiento: i.fecha_vencimiento || null
        }))
      }).then(res => {
        this.$q.notify({
          message: res.data.message,
          color: 'positive',
          icon: 'check_circle',
          position: 'top',
          timeout: 6000
        })

        this.limpiar()
        // El stock cambió: se recarga para que la grilla no mienta.
        this.cargarCatalogo()
      }).catch(err => {
        this.avisar(err, 'No se pudo registrar la compra')
      }).finally(() => {
        this.guardando = false
      })
    },

    limpiar () {
      this.items = []
      this.descuento = 0
      this.proveedor = null
      this.nroFactura = ''
      this.observacion = ''
      this.tipoPago = 'EFECTIVO'
      this.dialogConfirmar = false
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
/* Tarjeta compacta: con 6 por fila en media pantalla, cada una es angosta,
   asi que todo va un punto mas chico de lo habitual. */
.producto {
  min-height: 92px;
  transition: border-color .15s;
}
.producto:hover {
  border-color: var(--q-primary);
}
.producto :deep(.q-card__section) {
  padding: 4px 5px;
}
.producto-foto {
  height: 56px;
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
.producto-nombre {
  font-size: 10px;
  line-height: 1.15;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 2.3em;
}
.producto-codigo {
  font-size: 9px;
}
.producto-precio {
  font-size: 11px;
}
.producto :deep(.q-badge) {
  font-size: 9px;
  padding: 1px 4px;
}

/* Carrito: cuanto menos alto cada renglon, mas lineas se ven sin desplazarse.
   Cada linea trae cantidad, costo, precio de venta, lote y vencimiento, asi
   que los campos van al minimo legible. */
.carrito :deep(.q-field--dense .q-field__control) {
  height: 26px;
  min-height: 26px;
}
.carrito :deep(.q-field__label) {
  font-size: 9px;
  top: 5px;
}
.carrito :deep(.q-field--float .q-field__label) {
  transform: translateY(-9px) scale(.85);
}
.carrito :deep(.q-field__native),
.carrito :deep(.q-field__prefix) {
  font-size: 11px;
  padding: 0;
  min-height: 26px;
}
.carrito :deep(.q-field__marginal) {
  height: 26px;
}
.carrito :deep(.q-item) {
  min-height: 0;
  padding-top: 3px;
  padding-bottom: 3px;
}
.carrito-nombre {
  font-size: 11px;
  font-weight: 700;
  line-height: 1.3;
}
.carrito-thumb {
  min-width: 34px;
  padding-right: 6px;
}
/* Seis campos por renglon: sin esto los numeros no entran. */
.carrito :deep(.q-field__native) {
  text-align: right;
}
.carrito :deep(input[type="date"]) {
  text-align: left;
  font-size: 10px;
}

/* Resumen de la confirmacion: son 7 columnas, asi que van compactas. */
.tabla-resumen :deep(th) {
  font-size: 10px;
  padding: 4px 6px;
  white-space: nowrap;
}
.tabla-resumen :deep(td) {
  font-size: 11px;
  padding: 3px 6px;
}
.dialogo-confirmar :deep(.q-field--dense .q-field__control) {
  height: 32px;
}

/* Dialogo de la linea: mismo criterio, todo un punto mas chico. */
.dialogo-titulo {
  font-size: 13px;
}
.dialogo-sub {
  font-size: 10px;
}
.dialogo-linea :deep(.q-field--dense .q-field__control) {
  height: 32px;
}
.dialogo-linea :deep(.q-field__label) {
  font-size: 10px;
}
.dialogo-linea :deep(.q-field__native),
.dialogo-linea :deep(.q-field__prefix) {
  font-size: 12px;
}
</style>
