<template>
  <q-page class="q-pa-xs">
    <q-card flat bordered>
      <q-card-section class="q-pa-xs">
        <div class="text-right">
          <div>
            <q-btn color="primary" label="Actualizar" no-caps icon="refresh" :loading="loading" @click="productosGet" />
            <q-btn color="primary" label="Descargar" no-caps icon="fa-solid fa-file-excel" :loading="loading" @click="exportExcel" />
            <q-btn color="green" label="Nuevo" @click="productoNew" no-caps icon="add_circle_outline" :loading="loading" />
          </div>
          <q-input v-model="filter" label="Buscar" dense outlined debounce="300" @update:modelValue="productosGet">
            <template v-slot:append>
              <q-icon name="search" />
            </template>
          </q-input>
        </div>
        <div class="flex flex-center">
          <q-pagination
            v-model="pagination.page"
            :max="Math.ceil(pagination.rowsNumber / pagination.rowsPerPage)"
            :rows-per-page-options="[10, 25, 50, 100]"
            :rows-per-page="pagination.rowsPerPage"
            :rows-number="pagination.rowsNumber"
            color="primary"
            @update:modelValue="productosGet"
            boundary-numbers
            max-pages="5"
          />
        </div>
        <q-markup-table dense wrap-cells>
          <thead>
          <tr>
            <th>Opciones</th>
            <th>Imagen</th>
            <th v-for="column in columns" :key="column.name" :class="column.align">
              {{ column.label }}
            </th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="producto in productos" :key="producto.id">
            <td>
              <q-btn-dropdown label="Opciones" no-caps size="10px" dense color="primary">
                <q-list>
                  <q-item clickable @click="productoEdit(producto)" v-close-popup>
                    <q-item-section avatar>
                      <q-icon name="edit" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>Editar</q-item-label>
                    </q-item-section>
                  </q-item>
                  <q-item clickable @click="productoDelete(producto.id)" v-close-popup>
                    <q-item-section avatar>
                      <q-icon name="delete" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>Eliminar</q-item-label>
                    </q-item-section>
                  </q-item>
                  <q-item clickable @click="verHistorial(producto)" v-close-popup>
                    <q-item-section avatar><q-icon name="history" /></q-item-section>
                    <q-item-section><q-item-label>Historial de compras</q-item-label></q-item-section>
                  </q-item>
                </q-list>
              </q-btn-dropdown>
            </td>
            <td
              @dragover.prevent="draggingOverId = producto.id"
              @dragleave="draggingOverId = null"
              @drop.prevent="onImageDrop($event, producto)"
              :style="{
                background: draggingOverId === producto.id ? '#e3f2fd' : '',
                outline: draggingOverId === producto.id ? '2px dashed #1976d2' : '',
                transition: 'background 0.15s, outline 0.15s',
                borderRadius: '4px'
              }"
            >
              <div class="text-center" style="min-width: 64px; min-height: 64px; display:flex; align-items:center; justify-content:center;">
                <q-spinner v-if="uploadingImageId === producto.id" color="primary" size="40px" />
                <template v-else>
                  <q-img
                    v-if="producto.imagen"
                    :src="getImagenUrl(producto.imagen)"
                    style="width: 60px; height: 60px; border-radius: 4px; cursor: pointer;"
                    @click="verImagenAmpliada(producto.imagen)"
                  >
                    <template v-slot:error>
                      <div class="absolute-full flex flex-center bg-grey-3 text-grey-8">
                        <q-icon name="image" size="24px" />
                      </div>
                    </template>
                  </q-img>
                  <div v-else class="text-grey-5" style="font-size:11px; text-align:center;">
                    <q-icon name="image" size="24px" /><br>arrastra imagen
                  </div>
                </template>
              </div>
            </td>
            <td>
              <div style="max-width: 150px; wrap-option: wrap;line-height: 0.9;">
                {{ producto.nombre }}
              </div>
            </td>
            <td>
              <input
                v-model.number="producto.precio"
                type="number"
                step="0.01"
                min="0"
                style="width: 55px; text-align: right"
                @keyup="debouncedCambioPrecio(producto)"
              />
            </td>
            <td>
              <input
                v-model.number="producto.barra"
                style="width: 150px; text-align: right"
                @keyup="debouncedCambioBarra(producto)"
              />
            </td>
            <td>{{ producto.stock_disponible }}</td>
          </tr>
          </tbody>
        </q-markup-table>
      </q-card-section>
    </q-card>

    <q-dialog v-model="productoDialog" persistent>
      <q-card style="width: 500px; margin: 0 auto">
        <q-card-section class="q-pb-none row items-center">
          <div class="text-h6">
            {{ actionPeriodo }} producto
          </div>
          <q-space />
          <q-btn icon="close" flat round dense @click="cerrarDialogo" />
        </q-card-section>
        <q-card-section class="q-pt-none">
          <q-form @submit="producto.id ? productoPut() : productoPost()" class="q-gutter-md">

            <!-- Campo de imagen -->
            <div class="text-center q-mb-md">
              <div v-if="imagenPreview" class="q-mb-sm">
                <q-img
                  :src="imagenPreview"
                  style="max-width: 200px; max-height: 200px; border-radius: 8px;"
                  class="q-mb-sm"
                />
                <q-btn
                  icon="delete"
                  color="negative"
                  size="sm"
                  round
                  dense
                  @click="eliminarImagenPreview"
                />
              </div>

              <q-file
                v-model="imagenFile"
                label="Subir imagen"
                accept=".jpg,.jpeg,.png,.gif,.webp"
                max-files="1"
                outlined
                dense
                @update:model-value="cargarImagenPreview"
                class="full-width"
              >
                <template v-slot:prepend>
                  <q-icon name="attach_file" />
                </template>
              </q-file>
              <div class="text-caption text-grey">
                Formatos: JPG, PNG, GIF, WEBP. Máx: 2MB
              </div>
            </div>

            <q-input
              v-model="producto.nombre"
              label="Nombre *"
              dense
              outlined
              :rules="[val => !!val || 'Campo requerido']"
            />

            <q-input
              v-model="producto.descripcion"
              label="Descripción"
              dense
              outlined
              type="textarea"
              rows="2"
            />

            <div class="row q-gutter-md">
              <q-input
                v-model="producto.unidad"
                label="Unidad"
                dense
                outlined
                class="col"
                required
              />

              <q-input
                v-model="producto.precio"
                label="Precio *"
                dense
                outlined
                type="number"
                step="0.01"
                min="0"
                :rules="[val => val >= 0 || 'Precio debe ser positivo']"
                class="col"
              />
            </div>

            <q-input
              v-model="producto.barra"
              label="Código de barras"
              dense
              outlined
            />

            <div class="text-right q-pt-md">
              <q-btn
                color="negative"
                label="Cancelar"
                @click="cerrarDialogo"
                no-caps
                :loading="loading"
              />
              <q-btn
                color="primary"
                label="Guardar"
                type="submit"
                no-caps
                :loading="loading"
                class="q-ml-sm"
              />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <q-dialog v-model="historialDialog" persistent>
      <q-card style="width: 800px;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Historial de Compras: {{ productoHistorialNombre }}</div>
          <q-space />
          <q-btn icon="close" flat round dense @click="historialDialog = false" />
        </q-card-section>
        <q-card-section class="q-pt-none">
          <q-markup-table dense wrap-cells flat bordered>
            <thead>
            <tr>
              <th>#</th>
              <th>Fecha</th>
              <th>Lote</th>
              <th>Vencimiento</th>
              <th>Cantidad</th>
              <th>Precio</th>
              <th>Total</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(item, i) in historialCompras" :key="item.id">
              <td>{{ i + 1 }}</td>
              <td>{{ item.compra?.fecha }}</td>
              <td>{{ item.lote }}</td>
              <td>{{ item.fecha_vencimiento }}</td>
              <td>{{ item.stock }}</td>
              <td>{{ item.precio }}</td>
              <td>{{ item.total }}</td>
            </tr>
            </tbody>
          </q-markup-table>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Diálogo para ver imagen ampliada -->
    <q-dialog v-model="imagenDialog" maximized>
      <q-card class="bg-transparent">
        <q-card-actions align="right">
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-actions>
        <q-card-section class="flex flex-center">
          <img
            :src="imagenAmpliadaUrl"
            style="max-width: 90vw; max-height: 90vh; object-fit: contain;"
            @error="imagenError = true"
          />
          <div v-if="imagenError" class="text-center q-pa-lg">
            <q-icon name="error" size="48px" color="negative" />
            <div class="text-h6 q-mt-md">Error al cargar la imagen</div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import moment from 'moment'
import {Excel} from "src/addons/Excel";
import {debounce} from "quasar";

export default {
  name: 'ProductosPage',
  data() {
    return {
      productos: [],
      producto: {},
      productoDialog: false,
      loading: false,
      actionPeriodo: '',
      filter: '',
      imagenFile: null,
      imagenPreview: null,
      pagination: {
        page: 1,
        rowsPerPage: 15,
        rowsNumber: 0,
      },
      columns: [
        { name: 'nombre', label: 'Nombre', align: 'left', field: 'nombre' },
        { name: 'precio', label: 'Precio', align: 'left', field: 'precio' },
        { name: 'barra', label: 'Código de barras', align: 'left', field: 'barra' },
        { name: 'stock', label: 'Stock', align: 'left', field: 'stock' },
      ],
      historialDialog: false,
      historialCompras: [],
      productoHistorialNombre: '',
      imagenDialog: false,
      imagenAmpliadaUrl: '',
      imagenError: false,
      draggingOverId: null,
      uploadingImageId: null,
    }
  },
  mounted() {
    this.productosGet()
    this.debouncedCambioPrecio = debounce(this.cambioPrecio, 500)
    this.debouncedCambioBarra = debounce(this.cambioBarra, 500)
  },
  methods: {
    async onImageDrop(event, producto) {
      this.draggingOverId = null
      const file = event.dataTransfer.files[0]
      if (!file || !file.type.startsWith('image/')) {
        this.$alert.error('Solo se aceptan archivos de imagen')
        return
      }
      this.uploadingImageId = producto.id
      const formData = new FormData()
      formData.append('imagen', file)
      try {
        const res = await this.$axios.post(`productos/${producto.id}/imagen`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        producto.imagen = res.data.imagen
        this.$alert.success('Imagen actualizada')
      } catch (error) {
        this.$alert.error(error.response?.data?.message || 'Error al subir imagen')
      } finally {
        this.uploadingImageId = null
      }
    },

    getImagenUrl(imagenNombre) {
      return `${this.$url}../images/${imagenNombre}`;
    },

    verImagenAmpliada(imagenNombre) {
      this.imagenAmpliadaUrl = this.getImagenUrl(imagenNombre);
      this.imagenError = false;
      this.imagenDialog = true;
    },

    cargarImagenPreview(file) {
      if (file) {
        const reader = new FileReader()
        reader.onload = (e) => {
          this.imagenPreview = e.target.result
        }
        reader.readAsDataURL(file)
      } else {
        this.imagenPreview = null
      }
    },

    eliminarImagenPreview() {
      this.imagenFile = null
      this.imagenPreview = null
      // Si estamos editando y existe imagen, marcar para eliminarla
      if (this.producto.id && this.producto.imagen) {
        this.producto.eliminarImagen = true
      }
    },

    cerrarDialogo() {
      this.productoDialog = false
      this.producto = {}
      this.imagenFile = null
      this.imagenPreview = null
      if (this.producto.eliminarImagen) {
        delete this.producto.eliminarImagen
      }
    },

    cambioStockA(producto) {
      this.loading = true
      this.$axios.put('productos/' + producto.id, { stockAlmacen: producto.stockAlmacen }).then(res => {
        this.productosGet()
        this.$alert.success('Cantidad Almacen actualizada')
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },

    cambioStock1(producto) {
      this.loading = true
      this.$axios.put('productos/' + producto.id, { stockChallgua: producto.stockChallgua }).then(res => {
        this.productosGet()
        this.$alert.success('Cantidad Sucursal 1 actualizada')
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },

    cambioStock2(producto) {
      this.loading = true
      this.$axios.put('productos/' + producto.id, { stockSocavon: producto.stockSocavon }).then(res => {
        this.productosGet()
        this.$alert.success('Cantidad Sucursal 2 actualizada')
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },

    cambioStock3(producto) {
      this.loading = true
      this.$axios.put('productos/' + producto.id, { stockCatalina: producto.stockCatalina }).then(res => {
        this.productosGet()
        this.$alert.success('Cantidad Sucursal 3 actualizada')
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },

    verHistorial(producto) {
      this.loading = true;
      this.productoHistorialNombre = producto.nombre;
      this.$axios.get(`productos/${producto.id}/historial-compras`)
        .then(res => {
          this.historialCompras = res.data;
          this.historialDialog = true;
        }).catch(err => {
        this.$alert.error("Error al obtener historial");
      }).finally(() => {
        this.loading = false;
      });
    },

    cambioBarra(producto) {
      this.loading = true
      this.$axios.put('productos/' + producto.id, { barra: producto.barra }).then(res => {
        this.productosGet()
        this.$alert.success('Código de barras actualizado')
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },

    cambioStock(producto) {
      this.loading = true
      this.$axios.put('productos/' + producto.id, { stock: producto.stock }).then(res => {
        this.productosGet()
        this.$alert.success('Stock actualizado')
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },

    cambioPrecio(producto) {
      this.loading = true
      this.$axios.put('productos/' + producto.id, { precio: producto.precio }).then(res => {
        this.productosGet()
        this.$alert.success('Precio actualizado')
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },

    exportExcel() {
      this.loading = true
      this.$axios.get('productosAll').then(res => {
        let data = [{
          columns: [
            {label: "Nombre", value: "nombre"},
            {label: "Descripción", value: "descripcion"},
            {label: "Unidad", value: "unidad"},
            {label: "Precio", value: "precio"},
            {label: "Stock", value: "stock"},
            {label: "Stock mínimo", value: "stock_minimo"},
            {label: "Stock máximo", value: "stock_maximo"},
            {label: "Código de barras", value: "barra"},
            {label: "Imagen", value: "imagen"},
          ],
          content: res.data
        }]
        Excel.export(data,'Productos')
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },

    productoNew() {
      this.producto = {
        nombre: '',
        descripcion: '',
        unidad: '',
        precio: 0,
        barra: '',
        imagen: null
      }
      this.actionPeriodo = 'Nuevo'
      this.imagenFile = null
      this.imagenPreview = null
      this.productoDialog = true
    },

    productosGet() {
      this.loading = true
      this.$axios.get('productos', {
        params: {
          search: this.filter,
          page: this.pagination.page,
          per_page: this.pagination.rowsPerPage
        }
      }).then(res => {
        this.productos = res.data.data
        this.pagination.rowsNumber = res.data.total
      }).catch(error => {
        this.$alert.error(error.response?.data?.message || 'Error al cargar productos')
      }).finally(() => {
        this.loading = false
      })
    },

    gestionGet() {
      this.loading = true
      this.$axios.get('gestiones').then(res => {
        this.gestiones = res.data
        this.loading = false
      }).catch(error => {
        this.$alert.error(error.response.data.message)
        this.loading = false
      })
    },

    async productoPost() {
      this.loading = true

      const formData = new FormData()

      // Agregar campos del producto
      Object.keys(this.producto).forEach(key => {
        if (this.producto[key] !== null && this.producto[key] !== undefined) {
          formData.append(key, this.producto[key])
        }
      })

      // Agregar imagen si existe
      if (this.imagenFile) {
        formData.append('imagen', this.imagenFile)
      }

      try {
        const res = await this.$axios.post('productos', formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        })
        this.productosGet()
        this.$alert.success('Producto creado')
        this.cerrarDialogo()
      } catch (error) {
        this.$alert.error(error.response?.data?.message || 'Error al crear producto')
      } finally {
        this.loading = false
      }
    },

    async productoPut() {
      this.loading = true

      const formData = new FormData()

      // Agregar campos del producto (excepto imagen que se maneja aparte)
      const campos = ['nombre', 'descripcion', 'unidad', 'precio', 'barra']
      campos.forEach(key => {
        if (this.producto[key] !== null && this.producto[key] !== undefined) {
          formData.append(key, this.producto[key])
        }
      })

      // Agregar imagen si se seleccionó una nueva
      if (this.imagenFile) {
        formData.append('imagen', this.imagenFile)
      }

      // Si se marcó para eliminar la imagen existente
      if (this.producto.eliminarImagen) {
        try {
          await this.$axios.delete(`productos/${this.producto.id}/imagen`)
        } catch (error) {
          console.error('Error al eliminar imagen:', error)
        }
      }

      try {
        const res = await this.$axios.post(`productos/${this.producto.id}`, formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        })
        this.productosGet()
        this.$alert.success('Producto actualizado')
        this.cerrarDialogo()
      } catch (error) {
        this.$alert.error(error.response?.data?.message || 'Error al actualizar producto')
      } finally {
        this.loading = false
      }
    },

    productoEdit(producto) {
      this.producto = { ...producto }
      this.actionPeriodo = 'Editar'
      this.imagenFile = null
      this.imagenPreview = producto.imagen ? this.getImagenUrl(producto.imagen) : null
      this.productoDialog = true
    },

    productoDelete(id) {
      this.$alert.dialog('¿Desea eliminar el producto? Esta acción también eliminará la imagen asociada.')
        .onOk(() => {
          this.loading = true
          this.$axios.delete('productos/' + id).then(res => {
            this.productosGet()
            this.$alert.success('Producto eliminado')
          }).catch(error => {
            this.$alert.error(error.response.data.message)
          }).finally(() => {
            this.loading = false
          })
        })
    }
  }
}
</script>
