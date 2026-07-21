<template>
  <q-page class="q-pa-xs">
    <q-card flat bordered>
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6">Ventas</div>
        <q-btn flat round dense icon="arrow_back" @click="$router.go(-1)" />
        <q-btn flat round dense icon="refresh" @click="productosGet" :loading="loading" />
        <q-space />
      </q-card-section>

      <q-card-section class="q-pa-none">
        <q-form @submit="clickDialogVenta">
          <div class="row">
            <div class="col-12 col-md-7 q-pa-xs">
              <q-input
                ref="inputBuscarProducto"
                v-model="productosSearch"
                outlined
                clearable
                label="Buscar producto"
                dense
                debounce="300"
                @update:modelValue="productosGet"
              >
                <template #append>
                  <q-btn flat round dense icon="search" />
                </template>
              </q-input>

              <div class="flex flex-center">
                <q-pagination
                  size="xs"
                  v-model="pagination.page"
                  :max="Math.ceil(pagination.rowsNumber / pagination.rowsPerPage)"
                  color="primary"
                  @update:modelValue="productosGet"
                  boundary-numbers
                  max-pages="5"
                />
              </div>

              <div class="row">
                <template v-for="producto in productos" :key="producto.id">
                  <div class="col-6 col-md-2">
                    <q-card
                      flat
                      bordered
                      class="cursor-pointer"
                      @click="addProducto(producto)"
                    >
                      <q-img
                        :src="imageUrl(producto.imagen)"
                        class="q-mb-xs"
                        style="height: 120px;"
                      >
                        <div class="absolute-bottom text-center" style="padding: 0; margin: 0;">
                          <div style="max-width: 190px; line-height: 0.9;">
                            {{ $filters.textUpper(producto.nombre) }}
                          </div>
                          <div style="display: flex; justify-content: space-between;">
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

            <div class="col-12 col-md-5 q-pa-xs">
              <div class="text-right flex items-center">
                <q-space />
                <q-btn
                  icon="delete"
                  size="10px"
                  color="red"
                  dense
                  flat
                  no-caps
                  label="Limpiar"
                  @click="productosVentas = []; receta_id = null"
                />
              </div>

              <q-markup-table dense wrap-cells flat bordered>
                <thead>
                <tr>
                  <th>Producto</th>
                  <th>Cantidad</th>
                  <th>Precio</th>
                  <th>Subtotal</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(item, index) in productosVentas" :key="index">
                  <td style="padding: 0; margin: 0; display: flex; align-items: center;">
                    <q-img
                      :src="imageUrl(item.producto?.imagen)"
                      class="q-mb-xs"
                      style="height: 35px; width: 35px;"
                    />
                    <div style="max-width: 190px; overflow: hidden; text-overflow: ellipsis; line-height: .9;">
                      <q-icon
                        name="delete"
                        color="red"
                        class="cursor-pointer"
                        @click="productosVentas.splice(index, 1)"
                      />
                      {{ $filters.textUpper(item.producto?.nombre) }}
                    </div>
                  </td>
                  <td style="padding: 0; margin: 0;">
                    <input v-model.number="item.cantidad" type="number" style="width: 60px;" min="1" />
                  </td>
                  <td style="padding: 0; margin: 0;">
                    <input v-model.number="item.precio" type="number" style="width: 70px;" step="0.01" />
                  </td>
                  <td class="text-right">
                    {{ (Number(item.cantidad) * Number(item.precio)).toFixed(2) }} Bs
                  </td>
                </tr>
                </tbody>
                <tfoot>
                <tr>
                  <td colspan="3" class="text-right text-bold">Total</td>
                  <td class="text-right text-bold">
                    {{ totalVenta.toFixed(2) }} Bs
                  </td>
                </tr>
                </tfoot>
              </q-markup-table>

              <q-btn
                label="Realizar venta"
                color="positive"
                class="full-width"
                no-caps
                :loading="loading"
                type="submit"
                icon="add_circle_outline"
              />
            </div>
          </div>
        </q-form>
      </q-card-section>
    </q-card>

    <q-dialog v-model="ventaDialog">
      <q-card style="max-width: 750px; width: 90vw">
        <q-card-section class="q-pb-none row items-center">
          <div class="text-h6">Nueva venta</div>
          <q-space />
          <q-btn flat round dense icon="close" @click="ventaDialog = false" />
        </q-card-section>

        <q-card-section>
          <q-form @submit="submitVenta">
            <div class="row">
              <div class="col-12 col-md-3 q-pa-xs">
                <q-input v-model="venta.nit" outlined dense label="CI/NIT" @update:modelValue="searchCliente" :debounce="500" />
              </div>
              <div class="col-12 col-md-3 q-pa-xs">
                <q-input v-model="venta.nombre" outlined dense label="Nombre" />
              </div>
              <div class="col-12 col-md-3 q-pa-xs">
                <q-input v-model="venta.email" outlined dense label="Email" />
              </div>
              <div class="col-12 col-md-3 q-pa-xs">
                <q-input v-model="venta.complemento" outlined dense label="Complemento" />
              </div>
              <div class="col-12 col-md-3 q-pa-xs">
                <q-select v-model="venta.tipo_pago" outlined dense label="Tipo de pago" :options="['Efectivo', 'QR']" />
              </div>
              <div class="col-12 col-md-6 q-pa-xs">
                <q-select
                  v-model="venta.codigoTipoDocumentoIdentidad"
                  outlined
                  dense
                  label="Tipo de documento"
                  :options="codigoTipoDocumentoIdentidades"
                  emit-value
                  map-options
                />
              </div>

              <div class="col-12 q-pa-xs">
                <q-markup-table dense wrap-cells flat bordered>
                  <thead>
                  <tr>
                    <th>ID</th>
                    <th>Producto</th>
                    <th>Cant.</th>
                    <th>Precio</th>
                    <th>Subtotal</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr v-for="(item, index) in productosVentas" :key="'prev-'+index">
                    <td>{{ item.producto_id }}</td>
                    <td style="padding: 0; margin: 0;">
                      <div style="max-width: 190px; overflow: hidden; text-overflow: ellipsis; line-height: .9;">
                        {{ $filters.textUpper(item.producto?.nombre || '') }}
                      </div>
                    </td>
                    <td>{{ item.cantidad }}</td>
                    <td>{{ Number(item.precio).toFixed(2) }} Bs</td>
                    <td class="text-right">
                      {{ (Number(item.cantidad) * Number(item.precio)).toFixed(2) }} Bs
                    </td>
                  </tr>
                  </tbody>
                  <tfoot>
                  <tr>
                    <td colspan="4" class="text-right text-bold">Total</td>
                    <td class="text-right text-bold">{{ totalVenta.toFixed(2) }} Bs</td>
                  </tr>
                  <tr>
                    <td colspan="4" class="text-right text-bold">Efectivo</td>
                    <td class="text-right">
                      <input v-model.number="efectivo" type="number" step="0.01" style="width: 100px" />
                    </td>
                  </tr>
                  <tr>
                    <td colspan="4" class="text-right text-bold">Cambio</td>
                    <td class="text-right">{{ cambio }}</td>
                  </tr>
                  </tfoot>
                </q-markup-table>
              </div>

              <div class="col-12 q-pa-xs">
                <q-btn label="Realizar venta" color="positive" class="full-width" no-caps :loading="loading" type="submit" />
              </div>
            </div>
          </q-form>
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
        { value: 5, label: 'NIT - NUMERO DE IDENTIFICACION TRIBUTARIA' },
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
      },
      pagination: {
        page: 1,
        rowsPerPage: 24,
        rowsNumber: 0,
      },
      receta_id: null,
      recognition: null,
      activeField: null,
      productos: [],
      productosSearch: "",
      productosVentas: [],
    };
  },
  mounted() {
    this.$nextTick(() => {
      this.$refs.inputBuscarProducto?.focus();
    });
    this.productosGet();
  },
  methods: {
    imageUrl(path) {
      const safe = path || 'uploads/default.png';
      return `${this.$url}../${safe}`;
    },
    addProducto(producto) {
      const existente = this.productosVentas.find(p => p.producto_id === producto.id);
      if (existente) {
        existente.cantidad = Number(existente.cantidad || 0) + 1;
        return;
      }
      this.productosVentas.push({
        producto_id: producto.id,
        cantidad: 1,
        precio: Number(producto.precio || 0),
        producto,
      });
    },
    searchCliente() {
      const nit = (this.venta.nit || '').toString().trim();
      if (!nit) {
        this.venta.nombre = "SN";
        this.venta.email = "";
        this.venta.codigoTipoDocumentoIdentidad = 1;
        this.venta.complemento = "";
        return;
      }
      this.loading = true;
      this.$axios.post("searchCliente", { nit })
        .then((res) => {
          this.venta.nombre = "SN";
          this.venta.email = "";
          this.venta.codigoTipoDocumentoIdentidad = 1;
          this.venta.complemento = "";
          if (res.data?.nombre) this.venta.nombre = res.data.nombre;
          if (res.data?.email) this.venta.email = res.data.email;
          if (res.data?.codigoTipoDocumentoIdentidad) this.venta.codigoTipoDocumentoIdentidad = parseInt(res.data.codigoTipoDocumentoIdentidad);
          if (res.data?.complemento) this.venta.complemento = res.data.complemento;
        })
        .catch((error) => console.error(error))
        .finally(() => (this.loading = false));
    },
    clickDialogVenta() {
      if (this.productosVentas.length === 0) {
        this.$alert?.error?.("Debe agregar al menos un producto a la venta");
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
          this.addProducto(this.productos[0]);
          this.productosSearch = "";
        }
      }).catch((error) => {
        console.error(error);
      }).finally(() => {
        this.loading = false;
      });
    },
    submitVenta() {
      this.loading = true;
      this.$axios.post("ventas", {
        ci: this.venta.nit,
        nit: this.venta.nit,
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
        this.$alert?.success?.("Venta realizada con exito");
        this.productosVentas = [];
        this.venta = {
          nit: "0",
          nombre: "SN",
          codigoTipoDocumentoIdentidad: 1,
          tipo_venta: "Interno",
          tipo_pago: "Efectivo",
        };
        Imprimir.printFactura(res.data);
        this.receta_id = null;
        this.$nextTick(() => this.$refs.inputBuscarProducto?.focus());
        this.productosGet();
      }).catch((error) => {
        console.error(error);
        this.$alert?.error?.(error?.response?.data?.message || "No se pudo realizar la venta");
      }).finally(() => {
        this.loading = false;
      });
    },
  },
  computed: {
    totalVenta() {
      return this.productosVentas.reduce(
        (acc, it) => acc + (Number(it.cantidad) * Number(it.precio)), 0
      );
    },
    cambio() {
      let cambio = Number(this.efectivo || 0) - this.totalVenta;
      if (cambio < 0) cambio = 0;
      return cambio.toFixed(2);
    },
  },
};
</script>
