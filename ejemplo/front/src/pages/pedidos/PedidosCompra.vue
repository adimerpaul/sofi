<template>
  <q-page class="q-pa-xs">
    <q-card flat bordered>
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6">Compras</div>
        <q-btn flat round dense icon="arrow_back" @click="$router.go(-1)" />
      </q-card-section>

      <q-card-section class="q-pa-none">
        <q-form @submit="registrarPedido">
          <div class="row">
            <!-- Buscar productos -->
            <div class="col-12 col-md-7 q-pa-xs">
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
              <!--              <q-markup-table dense wrap-cells flat bordered>-->
              <!--                <thead>-->
              <!--                <tr>-->
              <!--                  <th>ID</th>-->
              <!--                  <th>Nombre</th>-->
              <!--                  <th>Unidad</th>-->
              <!--                  <th>Precio</th>-->
              <!--                </tr>-->
              <!--                </thead>-->
              <!--                <tbody>-->
              <!--                <tr v-for="(producto, index) in productos" :key="index" @click="addProducto(producto)">-->
              <!--                  <td>{{ producto.id }}</td>-->
              <!--                  <td>-->
              <!--                    <div style="max-width: 200px; wrap-option: warp;line-height: 0.9;">-->
              <!--                      {{ producto.nombre }}-->
              <!--                    </div>-->
              <!--                  </td>-->
              <!--                  <td>-->
              <!--                    <div style="max-width: 100px; wrap-option: warp;line-height: 0.9;">-->
              <!--                      {{ producto.unidad }}-->
              <!--                    </div>-->
              <!--                  </td>-->
              <!--                  <td class="text-right">{{ producto.precio }}</td>-->
              <!--                </tr>-->
              <!--                </tbody>-->
              <!--              </q-markup-table>-->
              <div class="row">
                <template v-for="producto in productos">
                  <div class="col-6 col-md-2">
                    <q-card flat bordered class="cursor-pointer" @click="addProducto(producto)">
                      <q-img
                        :src="`${$url}../images/${producto.imagen}`"
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
            <div class="col-12 col-md-5 q-pa-xs">
              <div>
                <q-btn size="xs" flat round dense icon="delete" color="red" @click="productosCompras = []" class="q-mb-sm" />
                <span class="text-subtitle2">Productos seleccionados</span>
              </div>
              <q-markup-table dense wrap-cells flat bordered>
                <thead>
                <tr>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Producto</th>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Cantidad</th>
<!--                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Precio unitario</th>-->
<!--                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Total</th>-->
<!--                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Factor</th>-->
<!--                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Precio unitario 1.25</th>-->
<!--                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Total</th>-->
<!--                  <th class="pm-none" style="max-width: 60px;line-height: 0.9">Precio venta</th>-->
<!--                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Lote</th>-->
<!--                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Fecha vencimiento</th>-->
<!--                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Dias vencimiento</th>-->
                </tr>
                </thead>
                <tbody>
                <tr v-for="(producto, index) in productosCompras" :key="index">
                  <td class="pm-none" style="display: flex;align-items: center;">
                    <q-img :src="`${$url}../images/${producto.producto?.imagen}`" class="q-mb-xs" style="height: 35px;width: 35px;" />
                    <div style="max-width: 120px; wrap-option: warp;line-height: 0.9;">
                      <q-icon name="delete" color="red" class="cursor-pointer" @click="productosCompras.splice(index, 1)" />
                      <!--                      {{ producto.producto?.nombre }}-->
                      {{ $filters.textUpper( producto.producto?.nombre ) }}
                    </div>
                  </td>
                  <td class="pm-none">
                    <input v-model="producto.cantidad" type="number" style="width: 50px;" @keyup="updatePrecioVenta(producto)" @update="updatePrecioVenta(producto)" />
                  </td>
<!--                  <td class="pm-none">-->
<!--                    <input v-model="producto.precio" type="number" style="width: 55px;" step="0.001" @keyup="updatePrecioVenta(producto)" @update="updatePrecioVenta(producto)" />-->
<!--                  </td>-->
<!--                  <td class="text-right pm-none">-->
<!--                    {{ parseFloat(producto.cantidad * producto.precio).toFixed(2) }}-->
<!--                  </td>-->
<!--                  <td class="pm-none">-->
<!--                    <input v-model="producto.factor" type="number" style="width: 55px;" step="0.001" @keyup="updatePrecioVenta(producto)" @update="updatePrecioVenta(producto)" />-->
<!--                  </td>-->
<!--                  <td class="text-right pm-none text-bold">-->
<!--                    {{ parseFloat(producto.precio * producto.factor).toFixed(2) }}-->
<!--                  </td>-->
<!--                  <td class="text-right pm-none">-->
<!--                    {{ parseFloat(producto.cantidad * producto.precio * producto.factor).toFixed(2) }}-->
<!--                  </td>-->
<!--                  <td class="pm-none">-->
<!--                    <input v-model="producto.precio_venta" type="number" style="width: 55px;color: red;font-weight: bold"-->
<!--                           step="0.01"/>-->
<!--                  </td>-->
<!--                  <td class="pm-none">-->
<!--                    <input v-model="producto.lote" type="text" style="width: 70px;" />-->
<!--                  </td>-->
<!--                  <td class="pm-none">-->
<!--                    <input v-model="producto.fecha_vencimiento" type="date" style="width: 100px;" />-->
<!--                  </td>-->
<!--                  <td class="pm-none text-right">-->
<!--                    <span :class="`text-bold ${(new Date(producto.fecha_vencimiento) - new Date()) < 0 ? 'text-red' : (Math.ceil((new Date(producto.fecha_vencimiento) - new Date()) / (1000 * 60 * 60 * 24)) < 30 ? 'text-red' : (Math.ceil((new Date(producto.fecha_vencimiento) - new Date()) / (1000 * 60 * 60 * 24)) < 60 ? 'text-orange' : 'text-green'))}`">-->
<!--                      {{ producto.fecha_vencimiento ? Math.ceil((new Date(producto.fecha_vencimiento) - new Date()) / (1000 * 60 * 60 * 24)) : '' }}-->
<!--                    </span>-->
<!--                  </td>-->
                </tr>
                </tbody>
                <tfoot>
<!--                <tr>-->
<!--                  <td colspan="3" class="text-right">Total</td>-->
<!--                  <td class="text-right">{{ totalCompra }} Bs</td>-->
<!--                </tr>-->
                </tfoot>
              </q-markup-table>
              <q-input v-model="observaciones" outlined dense label="Observaciones" class="q-mt-md" type="textarea" />
              <q-btn label="Registrar pedido" color="green" class="full-width" no-caps :loading="loading" type="submit" icon="add_circle_outline" />
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
                <q-select v-model="proveedor" :options="proveedores" option-label="nombre" option-value="id" label="Proveedor" dense outlined @update:modelValue="buscarProveedor"
                          :rules="[
                            val => !!val || 'Campo requerido',
                            val => {
                              if (val) {
                                this.compra.nit = val.nit;
                                this.compra.nombre = val.nombre;
                              }
                              return true;
                            }
                          ]"
                ></q-select>
              </div>
              <div class="col-12 col-md-6 q-pa-xs">
                <q-select v-model="compra.tipo_pago" :options="['Efectivo', 'QR']" label="Tipo de pago" dense outlined />
              </div>
              <div class="col-12 col-md-6 q-pa-xs">
                <q-input v-model="compra.nro_factura" outlined dense label="Nro. factura" />
              </div>
              <div class="col-12 col-md-6 q-pa-xs">
                <!--                agencias-->
                <q-select v-model="compra.agencia" :options="$agencias" label="Agencia" dense outlined :rules="[
                  val => !!val || 'Campo requerido',
                ]" />
              </div>
              <div class="col-12">
                <!--                table-->
                <q-markup-table flat dense wrap-cells bordered>
                  <thead>
                  <tr>
                    <th class="pm-none" style="max-width: 60px;wrap-option: wrap;line-height: 0.9">Producto</th>
                    <th class="pm-none" style="max-width: 60px;wrap-option: wrap;line-height: 0.9">Cantidad</th>
                    <!--                    <th class="pm-none" style="max-width: 60px;wrap-option: wrap;line-height: 0.9">Precio unitario</th>-->
                    <!--                    <th class="pm-none" style="max-width: 60px;wrap-option: wrap;line-height: 0.9">Total</th>-->
                    <!--                    <th class="pm-none" style="max-width: 60px;wrap-option: wrap;line-height: 0.9">Precio unitario 1.3</th>-->
                    <!--                    <th class="pm-none" style="max-width: 60px;wrap-option: wrap;line-height: 0.9">Total</th>-->
                    <th class="pm-none" style="max-width: 60px;line-height: 0.9">Precio venta</th>
                    <th class="pm-none" style="max-width: 60px;wrap-option: wrap;line-height: 0.9">Lote</th>
                    <th class="pm-none" style="max-width: 60px;wrap-option: wrap;line-height: 0.9">Fecha vencimiento</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr v-for="(producto, index) in productosCompras" :key="index">
                    <td class="pm-none" style="display: flex;align-items: center;">
                      <q-img :src="`${$url}../images/${producto.producto?.imagen}`" class="q-mb-xs" style="height: 35px;width: 35px;" />
                      <div style="max-width: 120px; wrap-option: warp;line-height: 0.9;">
                        {{ $filters.textUpper( producto.producto?.nombre ) }}
                      </div>
                    </td>
                    <td class="pm-none">
                      {{ producto.cantidad }}
                    </td>
                    <!--                    <td class="pm-none">-->
                    <!--                      {{ producto.precio }}-->
                    <!--                    </td>-->
                    <!--                    <td class="text-right pm-none">-->
                    <!--                      {{ parseFloat(producto.cantidad * producto.precio).toFixed(2) }}-->
                    <!--                    </td>-->
                    <!--                    <td class="text-right pm-none text-bold">-->
                    <!--                      {{ parseFloat(producto.precio * 1.3).toFixed(2) }}-->
                    <!--                    </td>-->
                    <!--                    <td class="text-right pm-none">-->
                    <!--                      {{ parseFloat(producto.cantidad * producto.precio * 1.3).toFixed(2) }}-->
                    <!--                    </td>-->
                    <td class="pm-none text-red text-bold text-right">
                      <!--                      {{ parseFloat(producto.precio_venta).toFixed(2) }}-->
                      {{ producto.precio_venta }} Bs
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
    <!--    myElement-->
    <div id="myElement" class="hidden"></div>
  </q-page>
</template>
<script>
import {Imprimir} from "src/addons/Imprimir";

export default {
  name: "ComprasCreate",
  data() {
    return {
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
      },
      observaciones: "",
    };
  },
  computed: {
    totalCompra() {
      // return this.productosCompras.reduce(
      //   (acc, p) => acc + (p.cantidad * p.precio),
      //   0
      // );
      let total = 0;
      this.productosCompras.forEach((p) => {
        total += p.cantidad * p.precio;
      });
      return parseFloat(total).toFixed(2);
    },
  },
  methods: {
    registrarPedido() {
      if (this.productosCompras.length === 0) {
        this.$alert.error("Debe agregar al menos un producto");
        return;
      }

      this.$q.dialog({
        title: "Registrar pedido",
        message: "¿Está seguro de registrar el pedido?",
        cancel: true,
        persistent: true,
      }).onOk(() => {
        this.loading = true;
        this.$axios.post("pedidos", {
          productos: this.productosCompras,
          observaciones: this.observaciones,
        }).then((res) => {
          this.$alert.success("Pedido registrado correctamente");
          this.productosCompras = [];
          // this.productosGet();
          Imprimir.reciboPedido(res.data);
        }).catch((err) => {
          console.error("Error registrando pedido:", err);
          this.$alert.error("Error al registrar el pedido");
        }).finally(() => {
          this.loading = false;
        });
      });

    },
    updatePrecioVenta(productoVenta) {
      const precio_venta = Math.ceil(productoVenta.precio * productoVenta.factor);
      productoVenta.precio_venta = precio_venta;
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
      const existente = this.productosCompras.find(p => p.producto_id === producto.id);
      if (existente) {
        existente.cantidad += 1;
      } else {
        this.productosCompras.push({
          producto_id: producto.id,
          cantidad: 1,
          precio: '',
          lote: '',
          fecha_vencimiento: '',
          producto,
          factor: 1.25,
        });
      }
    },
    clickDialogCompra() {
      if (this.productosCompras.length === 0) {
        this.$alert.error("Debe agregar al menos un producto");
        return;
      }

      const sinPrecio = this.productosCompras.filter(p => !p.precio);
      if (sinPrecio.length > 0) {
        this.$alert.error("Todos los productos deben tener precio unitario");
        return;
      }
      this.compraDialog = true;
    },
    buscarProveedor() {
      if (!this.compra.nit) return;
      this.loading = true;
      this.$axios.post("searchProveedor", {nit: this.compra.nit}).then((res) => {
        if (res.data?.nombre) {
          this.compra.nombre = res.data.nombre;
        }
      }).catch((err) => {
        console.warn("Proveedor no encontrado", err);
      }).finally(() => {
        this.loading = false;
      });
    },
    submitCompra() {
      this.loading = true;
      const data = {
        // ci: this.compra.nit,
        // nombre: this.compra.nombre,
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
        // this.loading = false;
      });
    },
    proveedoresGet() {
      // this.loading = true;
      this.$axios.get("proveedores").then((res) => {
        this.proveedores = res.data;
      }).catch((error) => {
        console.error("Error cargando proveedores:", error);
      }).finally(() => {
        // this.loading = false;
      });
    }
  },
  mounted() {
    this.productosGet();
    this.proveedoresGet();
  }
};
</script>
