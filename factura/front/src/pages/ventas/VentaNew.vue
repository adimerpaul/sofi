<template>
  <q-page class="venta-page">
    <!-- Header -->
    <div class="row items-center venta-header q-px-sm">
      <q-btn flat round dense icon="arrow_back" @click="$router.go(-1)" size="sm" />
      <span class="text-subtitle1 text-weight-bold q-ml-xs">Ventas - Farmacia</span>
      <q-space />
      <q-btn flat round dense icon="refresh" @click="productosGet" :loading="loading" size="sm" />
    </div>

    <div class="venta-body row no-wrap">
      <!-- Panel izquierdo: productos -->
      <div class="productos-panel col">
        <!-- Buscador -->
        <div class="q-pa-xs">
          <q-input
            ref="inputBuscarProducto"
            v-model="productosSearch"
            outlined dense
            placeholder="Buscar producto"
            debounce="300"
            @update:modelValue="productosGet"
            bg-color="white"
          >
            <template #append>
              <q-icon name="search" color="grey-6" />
              <q-btn round dense flat icon="mic" size="xs" v-if="recognition" @click="startRecognition('productosSearch')" />
            </template>
          </q-input>
        </div>

        <!-- Paginación -->
        <div class="flex flex-center q-py-xs">
          <q-pagination
            v-model="pagination.page"
            :max="Math.ceil(pagination.rowsNumber / pagination.rowsPerPage)"
            color="primary"
            @update:model-value="productosGet"
            boundary-numbers
            max-pages="7"
            size="sm"
          />
        </div>

        <!-- Grid de productos -->
        <div class="productos-grid">
          <div class="row" style="margin: 0 -1px;">
            <div
              v-for="producto in productos"
              :key="producto.id"
              class="prod-col"
            >
              <q-card
                flat
                bordered
                class="product-card cursor-pointer"
                :class="{
                  'product-card--selected': isProductInCart(producto),
                  'product-card--nostock': producto.stock <= 0
                }"
                @click="handleProductClick(producto)"
              >
                <q-img
                  :src="getImagenUrl(producto.imagen)"
                  class="q-mb-xs"
                  style="height: 120px;"
                >
                  <template v-slot:error>
                    <div class="absolute-full flex flex-center bg-grey-2">
                      <q-icon name="medication" size="22px" color="grey-5" />
                    </div>
                  </template>

                  <div class="absolute-bottom text-center" style="padding: 0; margin: 0;">
                    <div style="line-height: 0.9; font-size: 11px; font-weight: 600;">
                      {{ $filters.textUpper(producto.nombre) }}
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 1px 3px;">
                      <span class="prod-stock" :class="producto.stock > 0 ? 'stock-ok' : 'stock-no'">
                        {{ producto.stock > 0 ? producto.stock : '0' }}
                      </span>
                      <span class="text-bold bg-orange text-black" style="font-size: 10px; padding: 0 2px;">
                        {{ formatPrice(producto.precio) }} Bs
                      </span>
                    </div>
                  </div>
                </q-img>
              </q-card>
            </div>

            <div v-if="productos.length === 0 && !loading" class="full-width text-center q-py-xl">
              <q-icon name="search_off" size="48px" color="grey-5" />
              <div class="text-grey-6 q-mt-sm">No se encontraron productos</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Panel derecho: carrito -->
      <div class="cart-panel">
        <!-- Header del carrito -->
        <div class="cart-top row items-center q-px-sm">
          <span class="text-caption text-weight-bold text-grey-8">
            Cantidad de productos: {{ productosVentas.length }}
          </span>
          <q-space />
          <q-btn
            v-if="productosVentas.length > 0"
            flat round dense icon="delete_sweep"
            color="negative" size="xs"
            @click="productosVentas = []"
            title="Vaciar carrito"
          />
        </div>

        <!-- Botón realizar venta (arriba, con total) -->
        <div class="q-px-sm q-py-xs">
          <q-btn
            :label="`Realizar venta${productosVentas.length > 0 ? ' (' + formatPrice(totalVenta) + ' Bs)' : ''}`"
            icon="add_circle_outline"
            color="positive"
            class="full-width"
            no-caps
            size="md"
            :disable="productosVentas.length === 0"
            :loading="loading"
            @click="clickDialogVenta"
          />
        </div>

        <!-- Tabla de items del carrito -->
        <div class="cart-scroll">
          <table class="cart-table" v-if="productosVentas.length > 0">
            <thead>
              <tr>
                <th class="text-left">Producto</th>
                <th class="text-center">Lote</th>
                <th class="text-center">Vence</th>
                <th class="text-center">Cantidad</th>
                <th class="text-right">Precio</th>
                <th class="text-right">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in productosVentas" :key="index" class="cart-row">
                <td>
                  <div class="row items-center no-wrap">
                    <span class="del-btn" @click="productosVentas.splice(index, 1)" title="Quitar">&#x2715;</span>
                    <q-img
                      :src="getImagenUrl(item.producto?.imagen)"
                      style="width: 26px; height: 26px; border-radius: 2px; flex-shrink: 0; background: #f5f5f5;"
                      fit="contain"
                      class="q-mx-xs"
                    />
                    <span class="cart-prod-name">{{ $filters.textUpper(item.producto?.nombre || '') }}</span>
                  </div>
                </td>
                <td class="text-center cart-text">{{ item.lote || '—' }}</td>
                <td class="text-center cart-text">{{ item.fecha_vencimiento || '—' }}</td>
                <td class="text-center">
                  <input
                    v-model.number="item.cantidad"
                    type="number" min="1"
                    class="cart-input"
                    style="width: 44px; text-align: center;"
                    @change="updateSubtotal(item)"
                  />
                </td>
                <td class="text-right">
                  <input
                    v-model.number="item.precio"
                    type="number" step="0.01" min="0"
                    class="cart-input"
                    style="width: 58px; text-align: right;"
                    @change="updateSubtotal(item)"
                  />
                </td>
                <td class="text-right cart-subtotal">{{ formatPrice(item.precio * item.cantidad) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="cart-total-row">
                <td colspan="5" class="text-right">Total</td>
                <td class="text-right cart-total-val">{{ formatPrice(totalVenta) }} Bs</td>
              </tr>
            </tfoot>
          </table>

          <div v-else class="flex flex-center column q-py-lg">
            <q-icon name="shopping_cart" size="36px" color="grey-4" />
            <div class="text-caption text-grey-5 q-mt-xs">Carrito vacío</div>
          </div>
        </div>

      </div>
    </div>

    <!-- Diálogo de confirmación de venta -->
    <q-dialog v-model="ventaDialog" maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card class="full-height">
        <q-card-section class="row items-center q-pb-none bg-primary text-white">
          <div class="text-h5">Confirmar Venta</div>
          <q-space />
          <q-btn flat round dense icon="close" @click="ventaDialog = false" />
        </q-card-section>

        <q-card-section>
          <div class="row q-col-gutter-lg">
            <!-- Datos del cliente -->
            <div class="col-12 col-md-4">
              <q-card flat bordered>
                <q-card-section class="bg-blue-grey-1">
                  <div class="text-h6">Datos del Cliente</div>
                </q-card-section>
                <q-card-section>
                  <q-form>
                    <div class="row q-col-gutter-sm">
                      <div class="col-12">
                        <q-input v-model="venta.nit" outlined dense label="CI/NIT *"
                          @update:model-value="searchCliente" :debounce="500"
                          :rules="[val => !!val || 'Campo requerido']">
                          <template #prepend><q-icon name="badge" /></template>
                        </q-input>
                      </div>
                      <div class="col-12">
                        <q-input v-model="venta.nombre" outlined dense label="Nombre *"
                          :rules="[val => !!val || 'Campo requerido']">
                          <template #prepend><q-icon name="person" /></template>
                        </q-input>
                      </div>
                      <div class="col-12">
                        <q-input v-model="venta.email" outlined dense label="Email" type="email">
                          <template #prepend><q-icon name="email" /></template>
                        </q-input>
                      </div>
                      <div class="col-12">
                        <q-select v-model="venta.codigoTipoDocumentoIdentidad" outlined dense
                          label="Tipo de documento" :options="codigoTipoDocumentoIdentidades"
                          emit-value map-options>
                          <template #prepend><q-icon name="description" /></template>
                        </q-select>
                      </div>
                      <div class="col-12">
                        <q-input v-model="venta.complemento" outlined dense label="Complemento">
                          <template #prepend><q-icon name="info" /></template>
                        </q-input>
                      </div>
                      <div class="col-12">
                        <q-select v-model="venta.tipo_pago" outlined dense label="Tipo de pago *"
                          :options="['Efectivo', 'QR']" :rules="[val => !!val || 'Campo requerido']">
                          <template #prepend><q-icon name="payments" /></template>
                        </q-select>
                      </div>
                    </div>
                  </q-form>
                </q-card-section>
              </q-card>
            </div>

            <!-- Detalles de la venta -->
            <div class="col-12 col-md-8">
              <q-card flat bordered>
                <q-card-section class="bg-blue-grey-1">
                  <div class="row items-center">
                    <div class="text-h6">Detalles de la Venta</div>
                    <q-space />
                    <div class="text-h5 text-primary text-weight-bold">
                      Total: {{ formatPrice(totalVenta) }} Bs
                    </div>
                  </div>
                </q-card-section>
                <q-card-section>
                  <div class="scrollable-table">
                    <q-markup-table dense flat bordered class="no-shadow">
                      <thead class="bg-grey-3">
                      <tr>
                        <th class="text-left">Producto</th>
                        <th class="text-center">Lote</th>
                        <th class="text-center">Vence</th>
                        <th class="text-center">Cant.</th>
                        <th class="text-right">Precio Unit.</th>
                        <th class="text-right">Subtotal</th>
                      </tr>
                      </thead>
                      <tbody>
                      <tr v-for="(item, index) in productosVentas" :key="index">
                        <td>
                          <div class="row items-center">
                            <q-img :src="getImagenUrl(item.producto?.imagen)"
                              style="width: 40px; height: 40px; border-radius: 4px;" class="q-mr-sm" />
                            <div class="text-caption">{{ $filters.textUpper(item.producto?.nombre || '') }}</div>
                          </div>
                        </td>
                        <td class="text-center">{{ item.lote || '—' }}</td>
                        <td class="text-center">{{ item.fecha_vencimiento || '—' }}</td>
                        <td class="text-center">{{ item.cantidad }}</td>
                        <td class="text-right">{{ formatPrice(item.precio) }} Bs</td>
                        <td class="text-right text-weight-bold">{{ formatPrice(item.precio * item.cantidad) }} Bs</td>
                      </tr>
                      </tbody>
                      <tfoot class="bg-grey-2">
                      <tr>
                        <td colspan="4" class="text-right text-h6">TOTAL</td>
                        <td colspan="2" class="text-right text-h5 text-primary text-weight-bold">
                          {{ formatPrice(totalVenta) }} Bs
                        </td>
                      </tr>
                      <tr v-if="venta.tipo_pago === 'Efectivo'">
                        <td colspan="4" class="text-right text-h6">Efectivo Recibido</td>
                        <td colspan="2" class="text-right">
                          <q-input v-model.number="efectivo" type="number" step="0.01" min="0"
                            dense outlined style="max-width: 150px; float: right;"
                            @update:model-value="calculateChange" />
                        </td>
                      </tr>
                      <tr v-if="venta.tipo_pago === 'Efectivo'">
                        <td colspan="4" class="text-right text-h6">Cambio</td>
                        <td colspan="2" class="text-right text-h5 text-green text-weight-bold">
                          {{ formatPrice(cambio) }} Bs
                        </td>
                      </tr>
                      </tfoot>
                    </q-markup-table>
                  </div>
                </q-card-section>
              </q-card>
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="q-pa-md bg-grey-2">
          <q-btn label="Cancelar" color="grey" @click="ventaDialog = false" no-caps class="q-px-lg" />
          <q-btn label="Confirmar Venta" color="positive" icon="check" @click="submitVenta"
            :loading="loading" no-caps class="q-px-lg" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Diálogo de selección de lote -->
    <q-dialog v-model="loteDialog" persistent>
      <q-card style="min-width: 480px; max-width: 600px; width: 90vw;">
        <q-card-section class="row items-center q-pb-sm">
          <div class="text-h6">Seleccionar lote</div>
          <q-space />
          <q-btn flat round dense icon="close" @click="loteDialog = false" />
        </q-card-section>
        <q-separator />
        <q-card-section class="q-pt-sm">
          <!-- Info del producto -->
          <q-card flat bordered class="q-mb-sm bg-grey-1">
            <q-card-section class="q-pa-sm">
              <div class="row items-center no-wrap">
                <q-img
                  :src="getImagenUrl(loteProducto?.imagen)"
                  style="width: 44px; height: 44px; border-radius: 4px; flex-shrink: 0;"
                  fit="contain"
                  class="bg-white q-mr-sm"
                />
                <div>
                  <div class="text-caption text-grey-6">Producto seleccionado</div>
                  <div class="text-body2 text-weight-bold">{{ $filters.textUpper(loteProducto?.nombre || '') }}</div>
                  <div class="row q-gutter-xs q-mt-xs">
                    <q-badge color="blue-7" outline>
                      <q-icon name="layers" size="10px" class="q-mr-xs" />{{ lotes.length }} lote(s)
                    </q-badge>
                    <q-badge color="green-7" outline>
                      <q-icon name="inventory" size="10px" class="q-mr-xs" />Max stock por lote: {{ maxStockLote }}
                    </q-badge>
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>

          <!-- Tabla de lotes -->
          <q-markup-table dense flat bordered v-if="lotes.length > 0">
            <thead class="bg-grey-2">
              <tr>
                <th class="text-center" style="width: 30px;">#</th>
                <th class="text-left">Lote</th>
                <th class="text-center">Vencimiento</th>
                <th class="text-center">Disponible</th>
                <th class="text-center">Stock</th>
                <th class="text-center">Elegir</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(l, i) in lotes" :key="l.id" :class="loteSelected?.id === l.id ? 'bg-blue-1' : ''">
                <td class="text-center">{{ i + 1 }}</td>
                <td>{{ l.lote || 'N/A' }}</td>
                <td class="text-center">{{ l.fecha_vencimiento || '—' }}</td>
                <td class="text-center">{{ l.disponible }}</td>
                <td class="text-center">
                  <q-badge :color="getDaysColor(l.fecha_vencimiento)" style="font-size: 9px;">
                    {{ getDaysRemaining(l.fecha_vencimiento) }}
                  </q-badge>
                </td>
                <td class="text-center">
                  <span
                    v-if="l.disponible > 0"
                    class="lote-elegir cursor-pointer"
                    @click="onPickLote(l)"
                  >Elegir</span>
                  <span v-else class="text-grey-4" style="font-size: 11px;">—</span>
                </td>
              </tr>
            </tbody>
          </q-markup-table>

          <div v-else class="text-center q-py-md">
            <q-icon name="inventory_2" size="40px" color="grey-4" />
            <div class="text-grey-6 q-mt-xs">Sin lotes disponibles</div>
          </div>

          <!-- Cantidad + Agregar (cuando hay lote seleccionado) -->
          <div v-if="loteSelected" class="row items-center q-mt-sm q-col-gutter-sm">
            <div class="col-4">
              <q-input
                v-model.number="loteCantidad"
                type="number" dense outlined
                label="Cantidad a vender"
                :min="1"
                :max="loteSelected.disponible"
              />
            </div>
            <div class="col-4">
              <div class="text-caption">Lote: <b>{{ loteSelected.lote || '—' }}</b></div>
              <div class="text-caption">Vence: <b>{{ loteSelected.fecha_vencimiento || '—' }}</b></div>
            </div>
            <div class="col-4">
              <q-btn
                color="dark"
                icon="shopping_cart"
                label="Agregar a venta"
                no-caps
                class="full-width"
                @click="confirmarLote"
                :loading="loading"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <div id="myElement" class="hidden"></div>
  </q-page>
</template>

<script>
import { Imprimir } from "src/addons/Imprimir";

export default {
  name: "VentasNew",
  data() {
    return {
      codigoTipoDocumentoIdentidades: [
        { value: 1, label: 'CI - CEDULA DE IDENTIDAD' },
        { value: 2, label: 'CEX - CEDULA DE IDENTIDAD DE EXTRANJERO' },
        { value: 5, label: 'NIT - NÚMERO DE IDENTIFICACIÓN TRIBUTARIA' },
        { value: 3, label: 'PAS - PASAPORTE' },
        { value: 4, label: 'OD - OTRO DOCUMENTO DE IDENTIDAD' },
      ],
      loading: false,
      ventaDialog: false,
      efectivo: '',
      venta: {
        nit: "0",
        nombre: "SN",
        codigoTipoDocumentoIdentidad: 1,
        tipo_venta: "Interno",
        tipo_pago: "Efectivo",
        complemento: "",
        email: ""
      },
      pagination: {
        page: 1,
        rowsPerPage: 36,
        rowsNumber: 0,
      },
      receta_id: null,
      recognition: null,
      activeField: null,
      productos: [],
      productosSearch: "",
      productosVentas: [],
      loteDialog: false,
      lotesLoading: false,
      lotes: [],
      loteSelected: null,
      loteCantidad: 1,
      lotePrecio: 0,
      loteProducto: null,
    };
  },
  computed: {
    totalVenta() {
      return this.productosVentas.reduce(
        (acc, it) => acc + (Number(it.cantidad) * Number(it.precio)), 0
      );
    },
    cambio() {
      const efectivoNum = Number(this.efectivo) || 0;
      const cambio = efectivoNum - this.totalVenta;
      return cambio > 0 ? cambio : 0;
    },
    maxStockLote() {
      if (!this.lotes || this.lotes.length === 0) return 0;
      return Math.max(...this.lotes.map(l => Number(l.disponible) || 0));
    }
  },
  mounted() {
    this.$nextTick(() => {
      this.$refs.inputBuscarProducto?.focus();
    });
    this.productosGet();

    if ("webkitSpeechRecognition" in window || "SpeechRecognition" in window) {
      const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
      this.recognition = new SpeechRecognition();
      this.recognition.lang = "es-ES";
      this.recognition.interimResults = false;
      this.recognition.continuous = false;
      this.recognition.onresult = (event) => {
        const text = event.results[0][0].transcript;
        if (this.activeField) this.venta[this.activeField] += text;
      };
      this.recognition.onerror = (event) => {
        console.error("Error en reconocimiento de voz:", event.error);
      };
    }
  },
  methods: {
    getImagenUrl(imagenNombre) {
      return `${this.$url}../images/${imagenNombre}`;
    },
    formatPrice(value) {
      const num = Number(value) || 0;
      return parseFloat(num.toFixed(2));
    },
    getDaysRemaining(fecha) {
      if (!fecha) return 'N/A';
      const today = new Date();
      const vencimiento = new Date(fecha);
      const diffTime = vencimiento - today;
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      return diffDays + ' días';
    },
    getDaysColor(fecha) {
      if (!fecha) return 'grey';
      const today = new Date();
      const vencimiento = new Date(fecha);
      const diffTime = vencimiento - today;
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      if (diffDays <= 0) return 'red';
      if (diffDays <= 30) return 'orange';
      return 'green';
    },
    isProductInCart(producto) {
      return this.productosVentas.some(item => item.producto_id === producto.id);
    },
    handleProductClick(producto) {
      if (producto.stock <= 0) {
        this.$q.notify({ type: 'warning', message: 'Producto sin stock disponible', timeout: 2000 });
        return;
      }
      this.openLoteDialog(producto);
    },
    updateSubtotal(item) {
      if (item.cantidad < 1) item.cantidad = 1;
      return item.precio * item.cantidad;
    },
    async openLoteDialog(producto) {
      this.loteProducto = producto;
      this.loteDialog = true;
      this.lotes = [];
      this.loteSelected = null;
      this.loteCantidad = 1;
      this.lotePrecio = Number(producto.precio) || 0;
      this.lotesLoading = true;

      try {
        const res = await this.$axios.get(`productos/${producto.id}/historial-compras-ventas`);
        this.lotes = res.data || [];

        if (this.lotes.length === 1 && this.lotes[0].disponible > 0) {
          this.onPickLote(this.lotes[0]);
        }
      } catch (e) {
        console.error(e);
        this.$q.notify({ type: 'negative', message: 'No se pudieron cargar los lotes', timeout: 3000 });
      } finally {
        this.lotesLoading = false;
      }
    },
    onPickLote(lote) {
      this.loteSelected = lote;
      this.lotePrecio = Number(this.loteProducto?.precio) || 0;
      this.loteCantidad = 1;
    },
    confirmarLote() {
      if (!this.loteSelected) {
        this.$q.notify({ type: 'warning', message: 'Selecciona un lote', timeout: 2000 });
        return;
      }

      const disp = Number(this.loteSelected.disponible || 0);
      const cant = Number(this.loteCantidad || 0);
      const precio = Number(this.lotePrecio || 0);

      if (cant <= 0) {
        this.$q.notify({ type: 'negative', message: 'La cantidad debe ser mayor a 0', timeout: 2000 });
        return;
      }
      if (cant > disp) {
        this.$q.notify({ type: 'negative', message: `Cantidad excede el disponible (${disp})`, timeout: 3000 });
        return;
      }
      if (precio <= 0) {
        this.$q.notify({ type: 'negative', message: 'El precio debe ser mayor a 0', timeout: 2000 });
        return;
      }

      const existingIndex = this.productosVentas.findIndex(
        item => item.compra_detalle_id === this.loteSelected.id
      );

      if (existingIndex >= 0) {
        this.productosVentas[existingIndex].cantidad += cant;
        this.$q.notify({ type: 'info', message: 'Cantidad actualizada en el carrito', timeout: 2000 });
      } else {
        this.productosVentas.push({
          producto_id: this.loteProducto.id,
          cantidad: cant,
          precio: precio,
          producto: this.loteProducto,
          compra_detalle_id: this.loteSelected.id,
          lote: this.loteSelected.lote,
          fecha_vencimiento: this.loteSelected.fecha_vencimiento,
        });
        this.$q.notify({ type: 'positive', message: 'Producto agregado al carrito', icon: 'check', timeout: 1500 });
      }

      this.loteDialog = false;
      this.loteSelected = null;
      this.loteProducto = null;
      this.loteCantidad = 1;
      this.lotePrecio = 0;
    },
    searchCliente() {
      this.loading = true;
      this.$axios.post("searchCliente", { nit: this.venta.nit })
        .then((res) => {
          this.venta.nombre = "SN";
          this.venta.email = "";
          this.venta.codigoTipoDocumentoIdentidad = 1;
          if (res.data.nombre) this.venta.nombre = res.data.nombre;
          if (res.data.email) this.venta.email = res.data.email;
          if (res.data.codigoTipoDocumentoIdentidad) this.venta.codigoTipoDocumentoIdentidad = parseInt(res.data.codigoTipoDocumentoIdentidad);
          if (res.data.complemento) this.venta.complemento = res.data.complemento;
        })
        .catch((error) => console.error(error))
        .finally(() => (this.loading = false));
    },
    clickDialogVenta() {
      if (this.productosVentas.length === 0) {
        this.$q.notify({ type: 'warning', message: 'Debe agregar al menos un producto', timeout: 3000 });
        return;
      }
      const sinPrecio = this.productosVentas.filter(item => !item.precio || item.precio <= 0);
      if (sinPrecio.length > 0) {
        this.$q.notify({ type: 'negative', message: 'Todos los productos deben tener precio válido', timeout: 3000 });
        return;
      }
      this.ventaDialog = true;
      this.efectivo = '';
    },
    productosGet() {
      this.loading = true;
      this.$axios.get("productosStock", {
        params: {
          search: this.productosSearch,
          page: this.pagination.page,
          per_page: this.pagination.rowsPerPage,
        },
      }).then((res) => {
        this.productos = res.data.data;
        this.pagination.rowsNumber = res.data.total;
        this.pagination.page = res.data.current_page;

        if (this.productos.length === 1 && this.productos[0].barra === this.productosSearch) {
          this.openLoteDialog(this.productos[0]);
          this.productosSearch = "";
        }
      }).catch((error) => {
        console.error(error);
        this.$q.notify({ type: 'negative', message: 'Error al cargar productos', timeout: 3000 });
      }).finally(() => {
        this.loading = false;
      });
    },
    submitVenta() {
      if (!this.venta.nit || this.venta.nit.trim() === '') {
        this.$q.notify({ type: 'warning', message: 'El CI/NIT es requerido', timeout: 3000 });
        return;
      }

      this.loading = true;
      this.$axios.post("ventas", {
        ci: this.venta.nit,
        nombre: this.venta.nombre,
        email: this.venta.email,
        codigoTipoDocumentoIdentidad: this.venta.codigoTipoDocumentoIdentidad,
        complemento: this.venta.complemento,
        productos: this.productosVentas,
        tipo_venta: this.venta.tipo_venta,
        tipo_pago: this.venta.tipo_pago,
        receta_id: this.receta_id,
      }).then((res) => {
        this.ventaDialog = false;
        this.$q.notify({ type: 'positive', message: 'Venta realizada con éxito', icon: 'check', timeout: 3000 });

        if (res.data.siat_warning) {
          this.$q.notify({ type: 'warning', message: res.data.siat_warning, icon: 'cloud_off', timeout: 8000, multiLine: true });
        }

        if (res.data.tipo_comprobante === 'FACTURA') {
          Imprimir.printFactura(res.data);
        } else {
          Imprimir.nota(res.data);
        }

        this.productosVentas = [];
        this.venta = {
          nit: "0",
          nombre: "SN",
          codigoTipoDocumentoIdentidad: 1,
          tipo_venta: "Interno",
          tipo_pago: "Efectivo",
          complemento: "",
          email: ""
        };
        this.receta_id = null;
        this.productosGet();
        this.$nextTick(() => this.$refs.inputBuscarProducto?.focus());
      }).catch((error) => {
        console.error(error);
        const errorMsg = error?.response?.data?.message || "No se pudo realizar la venta";
        this.$q.notify({ type: 'negative', message: errorMsg, timeout: 5000 });
      }).finally(() => {
        this.loading = false;
      });
    },
    calculateChange() {},
    startRecognition(field) {
      if (this.recognition) {
        this.activeField = field;
        this.recognition.start();
      } else {
        this.$q.notify({ type: 'warning', message: "El reconocimiento de voz no está disponible", timeout: 3000 });
      }
    },
  }
};
</script>

<style scoped>
/* ── Layout principal ── */
.venta-page {
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
  background: #f0f2f5;
  padding: 0;
}

.venta-header {
  height: 40px;
  background: #fff;
  border-bottom: 1px solid #e0e0e0;
  flex-shrink: 0;
}

.venta-body {
  flex: 1;
  overflow: hidden;
  gap: 6px;
  padding: 6px;
}

/* ── Panel productos ── */
.productos-panel {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #fff;
  border-radius: 6px;
  border: 1px solid #e0e0e0;
}

.productos-grid {
  flex: 1;
  overflow-y: auto;
  padding: 2px 4px 4px;
}

.prod-col {
  width: calc(100% / 6);
  padding: 1px;
  box-sizing: border-box;
}

/* ── Tarjeta de producto ── */
.product-card {
  border-radius: 3px;
  overflow: hidden;
  border: 1px solid #ddd;
  transition: box-shadow 0.12s, border-color 0.12s;
}

.product-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.18);
  border-color: #1976d2;
}

.product-card--selected {
  border: 2px solid #1976d2;
}

.product-card--nostock {
  opacity: 0.55;
}

.prod-stock {
  font-size: 10px;
  font-weight: 700;
  border-radius: 2px;
  padding: 0 3px;
  min-width: 16px;
  text-align: center;
}

.stock-ok {
  background: #2e7d32;
  color: #fff;
}

.stock-no {
  background: #c62828;
  color: #fff;
}

/* ── Panel carrito ── */
.cart-panel {
  width: 400px;
  min-width: 320px;
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: 6px;
  border: 1px solid #e0e0e0;
  overflow: hidden;
}

.cart-top {
  height: 32px;
  background: #f5f5f5;
  border-bottom: 1px solid #e0e0e0;
  flex-shrink: 0;
}

.cart-scroll {
  flex: 1;
  overflow-y: auto;
}

/* ── Tabla del carrito ── */
.cart-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 10px;
}

.cart-table thead tr {
  background: #eeeeee;
}

.cart-table thead th {
  padding: 4px 5px;
  font-size: 10px;
  font-weight: 600;
  border-bottom: 1px solid #ccc;
  white-space: nowrap;
}

.cart-table tbody td {
  padding: 3px 4px;
  border-bottom: 1px solid #f0f0f0;
  vertical-align: middle;
}

.cart-row:hover {
  background: #f9fbe7;
}

.cart-prod-name {
  font-size: 9px;
  line-height: 1.2;
  word-break: break-word;
  overflow: hidden;
  max-height: 28px;
}

.cart-text {
  font-size: 9px;
  color: #555;
}

.cart-subtotal {
  font-size: 10px;
  font-weight: 700;
  color: #1565c0;
  white-space: nowrap;
}

.cart-input {
  border: 1px solid #ddd;
  border-radius: 3px;
  padding: 1px 3px;
  font-size: 10px;
  outline: none;
  background: #fafafa;
}

.cart-input:focus {
  border-color: #1976d2;
  background: #fff;
}

.cart-table tfoot td {
  padding: 4px 5px;
  border-top: 1px solid #ccc;
  background: #f5f5f5;
}

.cart-total-row td {
  font-size: 11px;
  font-weight: 600;
}

.cart-total-val {
  font-size: 12px;
  font-weight: 700;
  color: #1565c0;
  white-space: nowrap;
}

/* Botón eliminar fila */
.del-btn {
  font-size: 11px;
  color: #c62828;
  cursor: pointer;
  padding: 0 2px;
  flex-shrink: 0;
  line-height: 1;
  user-select: none;
}

.del-btn:hover {
  color: #b71c1c;
}

/* ── Lote dialog ── */
.lote-elegir {
  font-size: 12px;
  color: #1976d2;
  text-decoration: underline;
}

.lote-elegir:hover {
  color: #0d47a1;
}

/* ── Tabla en diálogo de confirmación ── */
.scrollable-table {
  max-height: 400px;
  overflow-y: auto;
}

.no-shadow {
  box-shadow: none !important;
}
</style>
