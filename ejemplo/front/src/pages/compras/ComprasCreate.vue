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
            <div class="col-12 col-md-6 q-pa-xs">
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
                    <q-card flat bordered class="cursor-pointer compra-product-card" @click="addProducto(producto)">
                      <q-img
                        :src="imageUrl(producto.imagen)"
                        class="q-mb-xs compra-product-image"
                      >
                        <div class="absolute-bottom text-center compra-product-overlay">
                          <div class="text-caption compra-product-name">
                            {{ $filters.textUpper( producto.nombre ) }}
                          </div>
                          <div class="row items-center justify-center q-gutter-xs q-px-xs q-pb-xs compra-chip-row">
                            <q-chip dense square size="sm" class="compra-mini-chip" :color="tipoProductoColor(producto.tipo)" text-color="white">
                              {{ tipoProductoLabel(producto.tipo) }}
                            </q-chip>
                            <q-chip dense square size="sm" class="compra-mini-chip" :color="unidadProductoColor(producto.codigo_unidad)" text-color="white">
                              {{ unidadProductoLabel(producto.codigo_unidad) }}
                            </q-chip>
                          </div>
                          <div class="compra-product-meta">
                            <span class="text-caption">{{ producto.precio1 }}</span>
                            <span class="text-bold bg-orange text-black border compra-product-code">{{ producto.codigo }}</span>
                          </div>
                          <div class="text-caption text-right text-bold compra-product-stock">
                            {{ Number(producto.stock || 0).toFixed(3) }}
                          </div>
                        </div>
                      </q-img>
                    </q-card>
                  </div>
                </template>
              </div>
            </div>

            <!-- Lista de productos agregados -->
            <div class="col-12 col-md-6 q-pa-xs">
              <div style="display: flex;align-items: center;justify-content: space-between;">
                <span>
                  <q-btn size="xs" flat round dense icon="delete" color="red" @click="productosCompras = []" class="q-mb-sm" />
                  <span class="text-subtitle2">Productos seleccionados</span>
                </span>
                <span>
<!--                  <q-btn size="xs" dense icon="restore" color="blue" class="q-mb-sm" label="Recuperar pedidos" no-caps @click="recuperarPedido" />-->
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
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Precio unitario 1.25</th>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Total</th>
                  <th class="pm-none" style="max-width: 60px;line-height: 0.9">Precio venta</th>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Lote</th>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Fecha vencimiento</th>
                  <th class="pm-none" style="max-width: 70px;line-height: 0.9">Dias vencimiento</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(producto, index) in productosCompras" :key="index">
                  <td class="pm-none compra-selected-cell">
                    <q-img :src="imageUrl(producto.producto?.imagen)" class="q-mb-xs compra-selected-thumb" />
                    <div class="compra-selected-copy">
                      <q-icon name="delete" color="red" class="cursor-pointer" @click="productosCompras.splice(index, 1)" />
<!--                      {{ producto.producto?.nombre }}-->
                        {{ $filters.textUpper( producto.producto?.nombre ) }}
<!--                      <div class="row items-center q-gutter-xs q-mt-xs">-->
<!--                        <q-chip dense square size="sm" class="compra-mini-chip" :color="tipoProductoColor(producto.producto?.tipo)" text-color="white">-->
<!--                          {{ tipoProductoLabel(producto.producto?.tipo) }}-->
<!--                        </q-chip>-->
<!--                        <q-chip dense square size="sm" class="compra-mini-chip" :color="unidadProductoColor(producto.producto?.codigo_unidad)" text-color="white">-->
<!--                          {{ unidadProductoLabel(producto.producto?.codigo_unidad) }}-->
<!--                        </q-chip>-->
<!--                      </div>-->
                    </div>
                  </td>
<!--                  <td class="pm-none">-->
<!--                    <input v-model="producto.cantidad" type="number" style="width: 50px;" @keyup="updatePrecioVenta(producto)" @update="updatePrecioVenta(producto)" />-->
<!--                  </td>-->
<!--                  <td class="pm-none">-->
<!--                    <input v-model="producto.precio" type="number" style="width: 55px;" step="0.001" @keyup="updatePrecioVenta(producto)" @update="updatePrecioVenta(producto)" />-->
<!--                  </td>-->
<!--                  <td class="text-right pm-none">-->
<!--                    <input v-model="producto.total" type="number" style="width: 55px;" step="0.001" @keyup="updatePrecioVenta(producto)" @update="updatePrecioVenta(producto)" />-->
<!--                  </td>-->
<!--                  <td class="pm-none">-->
<!--                    <input v-model="producto.factor" type="number" style="width: 55px;" step="0.001" @keyup="updatePrecioVenta(producto)" @update="updatePrecioVenta(producto)" />-->
<!--                  </td>-->
                  <td class="pm-none">
                    <input
                      v-model.number="producto.cantidad"
                      type="number"
                      min="0"
                      class="compra-mini-input compra-mini-input--xs"
                      @input="onCantidadChange(producto)"
                    />
                  </td>

                  <td class="pm-none">
                    <input
                      v-model.number="producto.precio"
                      type="number"
                      min="0"
                      step="0.001"
                      class="compra-mini-input"
                      @input="onPrecioChange(producto)"
                    />
                  </td>

                  <td class="text-right pm-none">
                    <input
                      v-model.number="producto.total"
                      type="number"
                      min="0"
                      step="0.001"
                      class="compra-mini-input"
                      @input="onTotalChange(producto)"
                    />
                  </td>

                  <td class="pm-none">
                    <input
                      v-model.number="producto.factor"
                      type="number"
                      min="0"
                      step="0.001"
                      class="compra-mini-input compra-mini-input--xs"
                      @input="onFactorChange(producto)"
                    />
                  </td>
                  <td class="text-right pm-none text-bold">
                    {{ parseFloat(producto.precio * producto.factor).toFixed(2) }}
                  </td>
                  <td class="text-right pm-none">
                    {{ parseFloat(producto.cantidad * producto.precio * producto.factor).toFixed(2) }}
                  </td>
                  <td class="pm-none">
                    <input v-model="producto.precio_venta" type="number" class="compra-mini-input compra-mini-input--price"
                           step="0.01"/>
                  </td>
                  <td class="pm-none">
                    <input v-model="producto.lote" type="text" class="compra-mini-input" />
                  </td>
                  <td class="pm-none">
                    <input v-model="producto.fecha_vencimiento" type="date" class="compra-mini-input compra-mini-input--date" />
                  </td>
                  <td class="pm-none text-right">
                    <span :class="`text-bold ${(new Date(producto.fecha_vencimiento) - new Date()) < 0 ? 'text-red' : (Math.ceil((new Date(producto.fecha_vencimiento) - new Date()) / (1000 * 60 * 60 * 24)) < 30 ? 'text-red' : (Math.ceil((new Date(producto.fecha_vencimiento) - new Date()) / (1000 * 60 * 60 * 24)) < 60 ? 'text-orange' : 'text-green'))}`">
                      {{ producto.fecha_vencimiento ? Math.ceil((new Date(producto.fecha_vencimiento) - new Date()) / (1000 * 60 * 60 * 24)) : '' }}
                    </span>
                  </td>
                </tr>
                </tbody>
                <tfoot>
                <tr>
                  <td colspan="8" class="text-right">Total</td>
                  <td colspan="3" class="text-right text-bold">{{ totalCompra }} Bs</td>
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
      <q-card style="width: 800px;max-width: 95vw;">
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
                  @update:model-value="onProveedorChange"
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
                <q-input v-model="compra.fecha_hora" outlined dense label="Fecha y hora" type="datetime-local" />
              </div>
              <div class="col-12 col-md-6 q-pa-xs">
                <q-input v-model="compra.nro_factura" outlined dense label="Nro. factura" />
              </div>
<!--              <div class="col-12 col-md-6 q-pa-xs">-->
<!--                <q-select v-model="compra.agencia" :options="$agencias" label="Agencia" dense outlined />-->
<!--              </div>-->
              <div class="col-12 col-md-6 q-pa-xs flex items-center">
                <q-toggle v-model="compra.facturado" label="Facturado" />
              </div>
              <div class="col-12 col-md-6 q-pa-xs">
                <q-input v-model="compra.ci" outlined dense label="CI proveedor" readonly />
              </div>
              <div class="col-12 col-md-6 q-pa-xs">
                <q-input v-model="compra.nombre" outlined dense label="Nombre proveedor" readonly />
              </div>
              <div class="col-12 col-md-6 q-pa-xs">
                <q-btn flat color="primary" icon="photo_camera" label="Adjuntar foto" no-caps @click="$refs.compraFotoInput.click()" />
                <input ref="compraFotoInput" type="file" accept="image/*" style="display:none" @change="onCompraFotoChange" />
                <div v-if="compraFotoName" class="text-caption q-mt-xs">{{ compraFotoName }}</div>
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
                      <q-img :src="imageUrl(producto.producto?.imagen)" class="q-mb-xs" style="height: 35px;width: 35px;" />
                      <div style="max-width: 120px; wrap-option: warp;line-height: 0.9;">
                        {{ $filters.textUpper( producto.producto?.nombre ) }}
<!--                        <div class="row items-center q-gutter-xs q-mt-xs">-->
<!--                          <q-chip dense square size="sm" :color="tipoProductoColor(producto.producto?.tipo)" text-color="white">-->
<!--                            {{ tipoProductoLabel(producto.producto?.tipo) }}-->
<!--                          </q-chip>-->
<!--                          <q-chip dense square size="sm" :color="unidadProductoColor(producto.producto?.codigo_unidad)" text-color="white">-->
<!--                            {{ unidadProductoLabel(producto.producto?.codigo_unidad) }}-->
<!--                          </q-chip>-->
<!--                        </div>-->
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
        tipo_pago: "Efectivo",
        nro_factura: "",
        facturado: false,
        fecha_hora: "",
        ci: "",
      },
      compraFoto: null,
      compraFotoName: "",
      pagination: {
        page: 1,
        rowsPerPage: 24,
        rowsNumber: 0
      }
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
    imageUrl(path) {
      const safe = path || 'uploads/default.png'
      return `${this.$url}../${safe}`
    },
    tipoProductoLabel(tipo) {
      const value = String(tipo || 'NORMAL').trim().toUpperCase()
      if (value === 'POLLO') return 'Pollo'
      if (value === 'RES') return 'Res'
      if (value === 'CERDO') return 'Cerdo'
      return 'Normal'
    },
    tipoProductoColor(tipo) {
      const value = String(tipo || 'NORMAL').trim().toUpperCase()
      if (value === 'POLLO') return 'orange'
      if (value === 'RES') return 'red'
      if (value === 'CERDO') return 'brown'
      return 'blue-grey'
    },
    unidadProductoLabel(codigoUnidad) {
      const value = String(codigoUnidad || '').trim().toUpperCase()
      if (value === 'KG') return 'Kilogramo'
      if (value === 'U' || value === 'UNIDA') return 'Unidad'
      return value || 'Unidad'
    },
    unidadProductoColor(codigoUnidad) {
      const value = String(codigoUnidad || '').trim().toUpperCase()
      if (value === 'KG') return 'teal'
      if (value === 'U' || value === 'UNIDA') return 'deep-orange'
      return 'grey-7'
    },
    openProveedorDialog() {
      this.resetProveedorForm()
      this.proveedorDialog = true
    },
    onProveedorChange(val) {
      if (val) {
        this.compra.nombre = val.nombre || ''
        this.compra.ci = val.ci || ''
      } else {
        this.compra.nombre = ''
        this.compra.ci = ''
      }
    },
    onCompraFotoChange(e) {
      const file = e.target.files?.[0]
      this.compraFoto = file || null
      this.compraFotoName = file ? file.name : ''
    },
    nowDateTimeLocal() {
      const d = new Date()
      const pad = n => String(n).padStart(2, '0')
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
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
          const creado = res.data  // {id, nombre, ci, telefono, email, direccion, ...}

          // 1) Agregar a la lista local
          this.proveedores.unshift(creado)

          // 2) Seleccionarlo y propagar a 'compra'
          this.proveedor      = creado
          this.compra.nombre  = creado.nombre || ''
          this.compra.ci      = creado.ci || ''

          // 3) Feedback y cerrar
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
      // si hay precio, recalcular total; si no, dejar total tal cual
      row.total = this.round2(qty * unit)
      this.updatePrecioVenta(row)
    },

    onPrecioChange(row) {
      const qty = Number(row.cantidad) || 0
      const unit = Number(row.precio) || 0
      row.total = this.round2(qty * unit)
      this.updatePrecioVenta(row)
    },

    onTotalChange(row) {
      const qty = Number(row.cantidad) || 0
      const tot = Number(row.total) || 0
      // si cantidad > 0, calcular precio desde total; si no, precio = 0
      row.precio = qty > 0 ? this.round3(tot / qty) : 0
      this.updatePrecioVenta(row)
    },

    onFactorChange(row) {
      this.updatePrecioVenta(row)
    },

    updatePrecioVenta(row) {
      const unit = Number(row.precio) || 0
      const factor = Number(row.factor) || 0
      // tu lógica original con ceil, o usa redondeo a 2 decimales
      // row.precio_venta = Math.ceil(unit * factor)
      row.precio_venta = this.round2(unit * factor)
    },

    round2(v) { return Math.round((Number(v) || 0) * 100) / 100 },
    round3(v) { return Math.round((Number(v) || 0) * 1000) / 1000 },
    recuperarPedido() {
      // COlcoar el id del pedido
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
              existente.cantidad += parseFloat(prod.cantidad) || 1;
              this.onCantidadChange(existente);
            } else {
              const precio = Number(producto.precio1 || 0)
              this.productosCompras.push({
                producto_id: producto.id,
                cantidad: parseInt(prod.cantidad),
                precio,
                total: this.round2((parseInt(prod.cantidad) || 0) * precio),
                lote: '',
                fecha_vencimiento: '',
                producto,
                factor: 1.25,
                precio_venta: this.round2(precio * 1.25),
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
          per_page: this.pagination.rowsPerPage,
          active: 1
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
        this.onCantidadChange(existente);
      } else {
        // const precio = Number(producto.precio1 || 0) dibidirlo entre 1.25
        const precio = this.round2(Number(producto.precio1 || 0) / 1.25)
        this.productosCompras.push({
          producto_id: producto.id,
          cantidad: 1,
          precio,
          total: precio,
          lote: '',
          fecha_vencimiento: '',
          producto,
          factor: 1.25,
          precio_venta: this.round2(precio * 1.25),
        });
      }
    },
    clickDialogCompra() {
      if (this.productosCompras.length === 0) {
        this.$alert.error("Debe agregar al menos un producto");
        return;
      }

      const invalidos = this.productosCompras.filter(p =>
        !p.precio || Number(p.precio) <= 0 ||
        !p.cantidad || Number(p.cantidad) <= 0 ||
        !p.precio_venta || Number(p.precio_venta) < 0 ||
        !p.lote || !String(p.lote).trim() ||
        !p.fecha_vencimiento
      );
      if (invalidos.length > 0) {
        this.$alert.error("Completa cantidad, precio, precio venta, lote y fecha de vencimiento en todos los productos");
        return;
      }
      if (!this.compra.fecha_hora) {
        this.compra.fecha_hora = this.nowDateTimeLocal()
      }
      this.compraDialog = true;
    },
    buscarProveedor() {
      if (!this.compra.nit) return;
      this.loading = true;
      this.$axios.post("searchProveedor", { nit: this.compra.nit }).then((res) => {
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
      const proveedorId = typeof this.proveedor === 'object' ? this.proveedor?.id : this.proveedor
      if (!proveedorId) {
        this.$alert.error("Debe seleccionar proveedor");
        return;
      }

      this.loading = true;
      const fd = new FormData()
      fd.append('tipo_pago', this.compra.tipo_pago || 'Efectivo')
      fd.append('proveedor_id', proveedorId)
      fd.append('nro_factura', this.compra.nro_factura || '')
      fd.append('facturado', this.compra.facturado ? '1' : '0')
      fd.append('fecha_hora', this.compra.fecha_hora || this.nowDateTimeLocal())
      if (this.compraFoto) fd.append('foto', this.compraFoto)

      this.productosCompras.forEach((p, i) => {
        fd.append(`productos[${i}][producto_id]`, p.producto_id)
        fd.append(`productos[${i}][cantidad]`, Number(p.cantidad))
        fd.append(`productos[${i}][precio]`, Number(p.precio))
        fd.append(`productos[${i}][factor]`, Number(p.factor || 1.25))
        fd.append(`productos[${i}][precio_venta]`, Number(p.precio_venta))
        fd.append(`productos[${i}][lote]`, String(p.lote || '').trim())
        fd.append(`productos[${i}][fecha_vencimiento]`, p.fecha_vencimiento)
      })

      this.$axios.post("compras", fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
      }).then((res) => {
        this.$alert.success("Compra registrada correctamente");
        this.compraDialog = false;
        this.productosCompras = [];
        Imprimir.reciboCompra(res.data);
        this.productosGet();
        this.compra = {
          nit: "",
          nombre: "",
          tipo_pago: "Efectivo",
          nro_factura: "",
          facturado: false,
          fecha_hora: "",
          ci: "",
        };
        this.compraFoto = null;
        this.compraFotoName = "";
        this.proveedor = null;
      }).catch((err) => {
        console.error("Error registrando compra:", err);
        this.$alert.error("Error al registrar la compra");
      }).finally(() => {
        this.loading = false;
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
    this.compra.fecha_hora = this.nowDateTimeLocal();
  }
};
</script>

<style scoped>
.compra-product-card {
  overflow: hidden;
}

.compra-product-image {
  height: 120px;
}

.compra-product-overlay {
  padding: 0;
  margin: 0;
}

.compra-product-name {
  max-width: 190px;
  line-height: 0.9;
  margin: 0 auto;
}

.compra-chip-row {
  margin-top: 2px;
}

.compra-mini-chip {
  min-height: 16px;
  font-size: 9px;
  padding: 0 4px;
}

.compra-product-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 4px;
  padding: 0 4px 2px;
}

.compra-product-code {
  font-size: 10px;
  padding: 0 3px;
}

.compra-product-stock {
  padding: 0 4px 2px;
  font-size: 10px;
}

.compra-selected-cell {
  display: flex;
  align-items: flex-start;
  gap: 4px;
}

.compra-selected-thumb {
  height: 30px;
  width: 30px;
  flex: 0 0 30px;
}

.compra-selected-copy {
  max-width: 108px;
  line-height: 0.85;
  font-size: 11px;
}

.compra-mini-input {
  width: 62px;
  font-size: 11px;
}

.compra-mini-input--xs {
  width: 50px;
}

.compra-mini-input--price {
  width: 52px;
  color: red;
  font-weight: 700;
}

.compra-mini-input--date {
  width: 92px;
}

@media (max-width: 768px) {
  .compra-product-image {
    height: 98px;
  }

  .compra-product-name {
    max-width: 100px;
    font-size: 10px;
  }

  .compra-mini-chip {
    min-height: 14px;
    font-size: 8px;
    padding: 0 3px;
  }

  .compra-product-code,
  .compra-product-stock {
    font-size: 9px;
  }

  :deep(.q-markup-table) {
    font-size: 11px;
  }

  .compra-selected-copy {
    max-width: 92px;
    font-size: 10px;
  }

  .compra-mini-input {
    width: 54px;
    font-size: 10px;
  }

  .compra-mini-input--xs {
    width: 44px;
  }

  .compra-mini-input--price {
    width: 48px;
  }

  .compra-mini-input--date {
    width: 86px;
  }
}
</style>
