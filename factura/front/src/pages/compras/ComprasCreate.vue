<template>
  <q-page class="q-pa-xs">
    <q-card flat bordered>
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6">Compras</div>
        <q-btn flat round dense icon="arrow_back" @click="$router.back()" class="q-mr-sm" />
      </q-card-section>

      <q-card-section class="q-pa-none">
        <q-form @submit="clickDialogCompra">
          <div class="row">
            <!-- Buscar productos -->
            <div class="col-12 col-md-5 q-pa-xs">
              <q-input v-model="productosSearch" outlined clearable label="Buscar producto" dense debounce="300" @update:modelValue="productosGet">
                <template v-slot:append>
                  <q-btn flat round dense icon="search" />
                </template>
              </q-input>
              <div class="flex flex-center">
                <q-pagination
                  v-model="pagination.page"
                  :max="Math.ceil(pagination.rowsNumber / pagination.rowsPerPage)"
                  max-pages="5"
                  size="xs"
                  boundary-numbers
                  @update:model-value="productosGet"
                  class="q-mt-sm"
                />
              </div>
              <div class="row">
                <template v-for="producto in productos">
                  <div class="col-6 col-md-2">
                    <q-card flat bordered class="cursor-pointer" @click="addProducto(producto)">
                      <q-img
                        :src="getImagenUrl(producto.imagen)"
                        class="q-mb-xs"
                        style="height: 120px;"
                      >
                        <div class="absolute-bottom text-center" style="padding: 0;margin: 0;">
                          <div style="max-width: 190px;line-height: 0.9;">
                            {{ $filters.textUpper( producto.nombre ) }}
                          </div>
                          <div style="display: flex;justify-content: space-between;">
                            <span>{{ producto.stock }}</span>
                            <span class="text-bold bg-orange text-black border">{{ producto.precio }} Bs</span>
                          </div>
                        </div>
                      </q-img>
                    </q-card>
                  </div>
                </template>
              </div>
            </div>

            <!-- Lista de productos agregados -->
            <div class="col-12 col-md-7 q-pa-xs">
              <div style="display: flex;align-items: center;justify-content: space-between;">
                <span>
                  <q-btn size="xs" flat round dense icon="delete" color="red" @click="productosCompras = []" class="q-mb-sm" />
                  <span class="text-subtitle2">Productos seleccionados</span>
                </span>
                <span>
                  <q-btn size="xs" dense icon="restore" color="blue" class="q-mb-sm" label="Recuperar pedidos" no-caps @click="recuperarPedido" />
                </span>
              </div>
              <q-markup-table dense wrap-cells flat bordered>
                <thead>
                <tr>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Producto</th>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Cantidad</th>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Precio unitario</th>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Total</th>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Factor</th>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Precio unitario 1.30</th>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Total</th>
                  <th class="pm-none" style="max-width: 60px;line-height: 0.9">Precio venta</th>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Lote</th>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Fecha vencimiento</th>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Dias vencimiento</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(producto, index) in productosCompras" :key="index">
                  <td class="pm-none" style="display: flex;align-items: center;">
                    <q-img :src="getImagenUrl(producto.producto?.imagen)" class="q-mb-xs" style="height: 35px;width: 35px;" />
                    <div style="max-width: 120px; wrap-option: warp;line-height: 0.9;">
                      <q-icon name="delete" color="red" class="cursor-pointer" @click="productosCompras.splice(index, 1)" />
                      {{ $filters.textUpper( producto.producto?.nombre ) }}
                    </div>
                  </td>
                  <td class="pm-none">
                    <input
                      v-model.number="producto.cantidad"
                      type="number"
                      min="0"
                      style="width: 60px;"
                      @input="onCantidadChange(producto)"
                    />
                  </td>

                  <td class="pm-none">
                    <input
                      v-model.number="producto.precio"
                      type="number"
                      min="0"
                      step="0.001"
                      style="width: 70px;"
                      @change="onPrecioChange(producto)"
                    />
                  </td>

                  <td class="text-right pm-none">
                    <input
                      v-model.number="producto.total"
                      type="number"
                      min="0"
                      step="0.001"
                      style="width: 70px;"
                      @input="onTotalChange(producto)"
                    />
                  </td>

                  <td class="pm-none">
                    <input
                      v-model.number="producto.factor"
                      type="number"
                      min="0"
                      step="0.001"
                      style="width: 60px;"
                      @input="onFactorChange(producto)"
                    />
                  </td>
                  <td class="text-right pm-none text-bold">
                    {{ formatPrice(producto.precio * producto.factor) }} Bs
                  </td>
                  <td class="text-right pm-none">
                    {{ formatPrice(producto.cantidad * producto.precio * producto.factor) }} Bs
                  </td>
                  <td class="pm-none">
                    <input v-model="producto.precio_venta" type="number" style="width: 55px;color: red;font-weight: bold"
                           step="0.1"/>
                  </td>
                  <td class="pm-none">
                    <input v-model="producto.lote" type="text" style="width: 70px;" />
                  </td>
                  <td class="pm-none">
                    <input v-model="producto.fecha_vencimiento" type="date" style="width: 100px;" />
                  </td>
                  <td class="pm-none text-right">
                    <span :class="`text-bold ${(new Date(producto.fecha_vencimiento) - new Date()) < 0 ? 'text-red' : (Math.ceil((new Date(producto.fecha_vencimiento) - new Date()) / (1000 * 60 * 60 * 24)) < 30 ? 'text-red' : (Math.ceil((new Date(producto.fecha_vencimiento) - new Date()) / (1000 * 60 * 60 * 24)) < 60 ? 'text-orange' : 'text-green'))}`">
<!--                      {{ producto.fecha_vencimiento ? Math.ceil((new Date(producto.fecha_vencimiento) - new Date()) / (1000 * 60 * 60 * 24)) : '' }}-->
<!--                      computed en a;os dias meses  dias restantes-->
                      {{ tiempoRestante(producto.fecha_vencimiento) }}
                    </span>
                  </td>
                </tr>
                </tbody>
                <tfoot>
                <tr>
                  <td colspan="3" class="text-right">Total</td>
                  <td class="text-right">{{ totalCompra }} Bs</td>
                </tr>
                </tfoot>
              </q-markup-table>
              <q-btn label="Registrar compra" color="primary" class="full-width" no-caps :loading="loading" type="submit" icon="add_circle_outline" />
            </div>
          </div>
        </q-form>
      </q-card-section>
    </q-card>

    <!-- Diálogo de confirmación de compra -->
    <q-dialog v-model="compraDialog">
      <q-card style="width: 600px;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Confirmar compra</div>
          <q-space />
          <q-btn flat round dense icon="close" @click="compraDialog = false" />
        </q-card-section>

        <q-card-section>
          <q-form @submit="submitCompra">
            <div class="row">
              <div class="col-12 col-md-6 q-pa-xs">
                <q-select
                  v-model="proveedor"
                  :options="proveedores"
                  option-label="nombre"
                  option-value="id"
                  label="Proveedor"
                  dense
                  outlined
                  :rules="[val => !!val || 'Campo requerido']"
                  @update:model-value="val => {
                    if (val) {
                      this.compra.nombre = val.nombre || ''
                      this.compra.ci     = val.ci || ''
                    } else {
                      this.compra.nombre = ''
                      this.compra.ci     = ''
                    }
                  }"
                >
                  <template #append>
                    <q-btn
                      round dense flat
                      icon="person_add"
                      @click.stop="openProveedorDialog"
                      title="Nuevo proveedor"
                    />
                  </template>
                </q-select>
              </div>
              <div class="col-12 col-md-6 q-pa-xs">
                <q-select v-model="compra.tipo_pago" :options="['Efectivo', 'QR']" label="Tipo de pago" dense outlined />
              </div>
              <div class="col-12 col-md-6 q-pa-xs">
                <q-input v-model="compra.nro_factura" outlined dense label="Nro. factura" />
              </div>
              <div class="col-12">
                <q-markup-table flat dense wrap-cells bordered>
                  <thead>
                  <tr>
                    <th class="pm-none" style="max-width: 60px;wrap-option: wrap;line-height: 0.9">Producto</th>
                    <th class="pm-none" style="max-width: 60px;wrap-option: wrap;line-height: 0.9">Cantidad</th>
                    <th class="pm-none" style="max-width: 60px;line-height: 0.9">Precio venta</th>
                    <th class="pm-none" style="max-width: 60px;wrap-option: wrap;line-height: 0.9">Lote</th>
                    <th class="pm-none" style="max-width: 60px;wrap-option: wrap;line-height: 0.9">Fecha vencimiento</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr v-for="(producto, index) in productosCompras" :key="index">
                    <td class="pm-none" style="display: flex;align-items: center;">
                      <q-img :src="getImagenUrl(producto.producto?.imagen)" class="q-mb-xs" style="height: 35px;width: 35px;" />
                      <div style="max-width: 120px; wrap-option: warp;line-height: 0.9;">
                        {{ $filters.textUpper( producto.producto?.nombre ) }}
                      </div>
                    </td>
                    <td class="pm-none">
                      {{ producto.cantidad }}
                    </td>
                    <td class="pm-none text-red text-bold text-right">
                      {{ formatPrice(producto.precio_venta) }} Bs
                    </td>
                    <td class="pm-none">
                      {{ producto.lote }}
                    </td>
                    <td class="pm-none">
                      {{ producto.fecha_vencimiento }}
                    </td>
                  </tr>
                  </tbody>
                </q-markup-table>
              </div>
            </div>
            <q-btn label="Guardar compra" color="primary" class="full-width q-mt-md" type="submit" no-caps icon="save" :loading="loading" />
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Diálogo: Nuevo proveedor -->
    <q-dialog v-model="proveedorDialog" persistent>
      <q-card style="width: 520px; max-width: 90vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Nuevo proveedor</div>
          <q-space />
          <q-btn flat round dense icon="close" @click="closeProveedorDialog" />
        </q-card-section>

        <q-card-section>
          <q-form ref="formProveedorRef" @submit="saveProveedor">
            <div class="row q-col-gutter-sm">
              <div class="col-12">
                <q-input
                  v-model="proveedorForm.nombre"
                  label="Nombre *"
                  dense outlined
                  :rules="[v => !!v || 'El nombre es obligatorio']"
                />
              </div>

              <div class="col-12 col-md-6">
                <q-input v-model="proveedorForm.ci" label="CI" dense outlined />
              </div>

              <div class="col-12 col-md-6">
                <q-input v-model="proveedorForm.telefono" label="Teléfono" dense outlined />
              </div>

              <div class="col-12">
                <q-input v-model="proveedorForm.email" label="Email" type="email" dense outlined />
              </div>

              <div class="col-12">
                <q-input
                  v-model="proveedorForm.direccion"
                  label="Dirección"
                  type="textarea"
                  autogrow
                  dense outlined
                />
              </div>
            </div>

            <div class="row q-gutter-sm q-mt-md">
              <q-space />
              <q-btn flat label="Cancelar" color="grey-8" @click="closeProveedorDialog" />
              <q-btn color="primary" label="Guardar" icon="save" type="submit" :loading="loading" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <div id="myElement" class="hidden"></div>
  </q-page>
</template>

<script>
import {Imprimir} from "src/addons/Imprimir";
import moment from "moment";

export default {
  name: "ComprasCreate",
  data() {
    return {
      proveedorDialog: false,
      formProveedorRef: null,
      proveedorForm: {
        nombre: '',
        ci: '',
        telefono: '',
        email: '',
        direccion: ''
      },
      loading: false,
      compraDialog: false,
      productos: [],
      productosSearch: "",
      productosCompras: [],
      proveedores: [],
      proveedor: null,
      compra: {
        nit: "",
        nombre: "",
        tipo_pago: "Efectivo"
      },
      pagination: {
        page: 1,
        rowsPerPage: 24,
        rowsNumber: 0
      }
    };
  },
  computed: {
    totalCompra() {
      let total = 0;
      this.productosCompras.forEach((p) => {
        total += p.cantidad * p.precio;
      });
      return parseFloat(total).toFixed(2);
    },
  },
  methods: {
    tiempoRestante(fechaVencimiento) {
      // anios mese dias con moment
      if (!fechaVencimiento) return '';
      const hoy = moment();
      const vencimiento = moment(fechaVencimiento);
      const diff = vencimiento.diff(hoy);
      if (diff < 0) return 'Vencido';
      const duracion = moment.duration(diff);
      const anios = duracion.years();
      const meses = duracion.months();
      const dias = duracion.days();
      let resultado = '';
      if (anios > 0) resultado += `${anios} a `;
      if (meses > 0) resultado += `${meses} m `;
      if (dias > 0) resultado += `${dias} d`;
      return resultado.trim();
    },
    getImagenUrl(imagenNombre) {
      return `${this.$url}../images/${imagenNombre}`;
    },

    formatPrice(value) {
      const num = Number(value) || 0
      // Redondear a 1 decimal (décimas)
      return parseFloat(num.toFixed(1))
    },

    round2(v) {
      return Math.round((Number(v) || 0) * 100) / 100
    },

    round3(v) {
      return Math.round((Number(v) || 0) * 1000) / 1000
    },

    roundTo(v) {
      const num = Number(v) || 0
      // múltiplo 0.5
      return Math.ceil(num / 0.5) * 0.5
    },

    openProveedorDialog() {
      this.resetProveedorForm()
      this.proveedorDialog = true
    },

    closeProveedorDialog() {
      this.proveedorDialog = false
    },

    resetProveedorForm() {
      this.proveedorForm = {
        nombre: '',
        ci: '',
        telefono: '',
        email: '',
        direccion: ''
      }
    },

    async saveProveedor() {
      const ok = await this.$refs.formProveedorRef.validate()
      if (!ok) return

      this.loading = true
      this.$axios.post('proveedores', this.proveedorForm)
        .then(res => {
          const creado = res.data
          this.proveedores.unshift(creado)
          this.proveedor = creado
          this.compra.nombre = creado.nombre || ''
          this.compra.ci = creado.ci || ''

          this.$alert?.success?.('Proveedor creado correctamente')
          || this.$q.notify({ type: 'positive', message: 'Proveedor creado correctamente' })

          this.proveedorDialog = false
          this.resetProveedorForm()
        })
        .catch(err => {
          console.error('Error creando proveedor:', err)
          this.$alert?.error?.('No se pudo crear el proveedor')
          || this.$q.notify({ type: 'negative', message: 'No se pudo crear el proveedor' })
        })
        .finally(() => {
          this.loading = false
        })
    },

    onCantidadChange(row) {
      const qty = Number(row.cantidad) || 0
      const unit = Number(row.precio) || 0
      row.total = this.round2(qty * unit)
      this.updatePrecioVenta(row)
    },

    onPrecioChange(row) {
      const factor = Number(row.factor) || 1.3
      const ingresado = Number(row.precio) || 0
      row.precio = this.round3(ingresado / factor)
      const qty = Number(row.cantidad) || 0
      row.total = this.round2(qty * row.precio)
      this.updatePrecioVenta(row)
    },

    onTotalChange(row) {
      const qty = Number(row.cantidad) || 0
      const tot = Number(row.total) || 0
      row.precio = qty > 0 ? this.round3(tot / qty) : 0
      this.updatePrecioVenta(row)
    },

    onFactorChange(row) {
      this.updatePrecioVenta(row)
    },

    updatePrecioVenta(row) {
      const unit = Number(row.precio) || 0
      const factor = Number(row.factor) || 1
      const precioBase = unit * factor

      // Redondear a 1 decimal (décimas)
      // Ejemplos: 1.25 -> 1.3, 1.24 -> 1.2, 1.20 -> 1.2
      row.precio_venta = this.roundTo(precioBase, 1)
    },

    recuperarPedido() {
      this.$q.dialog({
        title: "Recuperar pedido",
        message: "Ingrese el ID del pedido",
        prompt: {
          model: "",
          type: "text",
          isValid: (val) => {
            return !!val || "Campo requerido";
          },
        },
        persistent: true,
        cancel: true,
        ok: {
          label: "Recuperar",
          color: "primary",
        },
      }).onOk((data) => {
        this.loading = true;
        this.$axios.get("recuperarPedido", {
          params: {
            id: data
          }
        }).then((res) => {
          if (!res.data.detalles || res.data.detalles.length === 0) {
            this.$alert.error("No se encontraron productos en el pedido");
            return;
          }
          res.data.detalles.forEach((prod) => {
            const producto = prod.producto;
            const existente = this.productosCompras.find(p => p.producto_id === producto.id);
            if (existente) {
              existente.cantidad += 1;
            } else {
              this.productosCompras.push({
                producto_id: producto.id,
                cantidad: parseInt(prod.cantidad),
                precio: '',
                lote: '',
                fecha_vencimiento: '',
                producto,
                factor: 1.30,
                precio_venta: ''
              });
            }
          });
        }).catch((error) => {
          console.error("Error recuperando pedido:", error);
        }).finally(() => {
          this.loading = false;
        });
      });
    },

    productosGet() {
      this.loading = true;
      this.$axios.get("productos", {
        params: {
          search: this.productosSearch,
          page: this.pagination.page,
          per_page: this.pagination.rowsPerPage
        },
      }).then((res) => {
        this.productos = res.data.data;
        this.pagination.rowsNumber = res.data.total;
      }).catch((error) => {
        console.error("Error cargando productos:", error);
      }).finally(() => {
        this.loading = false;
      });
    },

    addProducto(producto) {
      const factor = 1.30
      const precio = this.round3((Number(producto.precio) || 0) / factor)
      this.productosCompras.push({
        producto_id: producto.id,
        cantidad: 1,
        precio,
        total: this.round2(precio),
        lote: '',
        fecha_vencimiento: '',
        producto,
        factor,
        precio_venta: Number(producto.precio) || ''
      });
    },

    clickDialogCompra() {
      if (this.productosCompras.length === 0) {
        this.$alert.error("Debe agregar al menos un producto");
        return;
      }

      const sinPrecio = this.productosCompras.filter(p => !p.precio || p.precio <= 0);
      if (sinPrecio.length > 0) {
        this.$alert.error("Todos los productos deben tener precio unitario");
        return;
      }

      this.compraDialog = true;
    },

    submitCompra() {
      this.loading = true;
      const data = {
        tipo_pago: this.compra.tipo_pago,
        proveedor_id: this.proveedor.id,
        nro_factura: this.compra.nro_factura,
        productos: this.productosCompras,
        agencia: this.compra.agencia,
      };

      this.$axios.post("compras", data).then((res) => {
        this.$alert.success("Compra registrada correctamente");
        this.compraDialog = false;
        this.productosCompras = [];
        Imprimir.reciboCompra(res.data);
        this.productosGet();
        this.compra = {
          nit: "",
          nombre: "",
          tipo_pago: "Efectivo"
        };
        this.proveedor = null;
      }).catch((err) => {
        console.error("Error registrando compra:", err);
        this.$alert.error("Error al registrar la compra");
      }).finally(() => {
        this.loading = false;
      });
    },

    proveedoresGet() {
      this.$axios.get("proveedores").then((res) => {
        this.proveedores = res.data;
      }).catch((error) => {
        console.error("Error cargando proveedores:", error);
      });
    }
  },

  mounted() {
    this.productosGet();
    this.proveedoresGet();
  }
};
</script>
