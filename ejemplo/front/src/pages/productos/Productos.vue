<template>
  <q-page class="q-pa-sm">
    <q-card flat bordered>
      <q-card-section class="q-pa-sm">
        <div class="row q-col-gutter-sm items-end">
          <div class="col-12 col-md-4">
            <q-input
              v-model="filter"
              label="Buscar por codigo o nombre"
              dense
              outlined
              debounce="300"
              clearable
              @update:model-value="productosGet"
            >
              <template #append><q-icon name="search" /></template>
            </q-input>
          </div>
          <div class="col-12 col-md-3">
            <q-select
              v-model="filterTipo"
              :options="tiposFiltro"
              label="Filtrar tipo"
              dense
              outlined
              emit-value
              map-options
              @update:model-value="onTipoFilterChange"
            />
          </div>
          <div class="col-12 col-md-2">
            <q-select
              v-model="filterUnidad"
              :options="unidadesFiltro"
              label="Filtrar unidad"
              dense
              outlined
              emit-value
              map-options
              @update:model-value="onUnidadFilterChange"
            />
          </div>
          <div class="col-6 col-md-auto">
            <q-btn color="primary" label="Actualizar" no-caps icon="refresh" class="full-width" :loading="loading" @click="productosGet" />
          </div>
          <div class="col-6 col-md-auto">
            <q-btn color="primary" label="Descargar" no-caps icon="fa-solid fa-file-excel" class="full-width" :loading="loading" @click="exportExcel" />
          </div>
          <div class="col-6 col-md-auto">
            <q-btn color="green" label="Nuevo" no-caps icon="add_circle_outline" class="full-width" :loading="loading" @click="productoNew" />
          </div>
        </div>

        <div class="row q-col-gutter-sm q-mt-sm">
          <div class="col-12 col-sm-auto">
            <q-chip square color="primary" text-color="white" icon="inventory_2">
              Total productos: {{ totalProductosFiltrados }}
            </q-chip>
          </div>
          <div class="col-12 col-sm-auto">
            <q-chip square color="teal" text-color="white" icon="view_list">
              En esta pagina: {{ totalProductosPagina }}
            </q-chip>
          </div>
          <div class="col-12 col-sm-auto">
            <q-chip square color="grey-8" text-color="white" icon="warehouse">
              Stock estimado (pagina): {{ totalStockPagina.toFixed(3) }}
            </q-chip>
          </div>
          <div class="col-12 col-sm-auto">
            <q-chip square color="positive" text-color="white" icon="check_circle">
              Activos (pagina): {{ totalActivosPagina }}
            </q-chip>
          </div>
        </div>

        <div class="row justify-center q-mt-md">
          <q-pagination
            v-model="pagination.page"
            :max="Math.ceil(pagination.rowsNumber / pagination.rowsPerPage) || 1"
            color="primary"
            @update:model-value="productosGet"
            boundary-numbers
            max-pages="6"
          />
        </div>

        <q-markup-table dense flat bordered wrap-cells class="q-mt-sm">
          <thead>
            <tr>
              <th>Opciones</th>
              <th>Imagen</th>
              <th>Codigo</th>
              <th>Nombre</th>
              <th>Tipo</th>
              <th>Unidad</th>
              <th>Tipo producto</th>
              <th>Grupo padre</th>
              <th>Grupo</th>
              <th>Precio 1</th>
              <th>Peso estimado</th>
              <th>Activo</th>
              <th>Stock</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="producto in productos" :key="producto.id">
              <td>
                <q-btn-dropdown label="Opciones" no-caps size="10px" dense color="primary">
                  <q-list>
                    <q-item clickable @click="productoEdit(producto)" v-close-popup>
                      <q-item-section avatar><q-icon name="edit" /></q-item-section>
                      <q-item-section><q-item-label>Editar</q-item-label></q-item-section>
                    </q-item>
                    <q-item clickable @click="prodImgEdit(producto)" v-close-popup>
                      <q-item-section avatar><q-icon name="image" /></q-item-section>
                      <q-item-section><q-item-label>Cambiar imagen</q-item-label></q-item-section>
                    </q-item>
                    <q-item clickable @click="productoDelete(producto.id)" v-close-popup>
                      <q-item-section avatar><q-icon name="delete" /></q-item-section>
                      <q-item-section><q-item-label>Eliminar</q-item-label></q-item-section>
                    </q-item>
                    <q-item clickable @click="verHistorial(producto)" v-close-popup>
                      <q-item-section avatar><q-icon name="history" /></q-item-section>
                      <q-item-section><q-item-label>Historial de compras</q-item-label></q-item-section>
                    </q-item>
                  </q-list>
                </q-btn-dropdown>
              </td>
              <td>
                <q-avatar rounded size="48px">
                  <q-img :src="imageUrl(producto.imagen)" />
                </q-avatar>
              </td>
              <td>{{ producto.codigo }}</td>
              <td style="min-width: 220px">{{ producto.nombre }}</td>
              <td>
                <q-chip dense square :color="tipoColor(producto.tipo)" text-color="white">
                  {{ tipoLabel(producto.tipo) }}
                </q-chip>
              </td>
              <td>
                <q-chip dense square :color="unidadColor(producto.codigo_unidad)" text-color="white">
                  {{ unidadLabel(producto.codigo_unidad) }}
                </q-chip>
              </td>
              <td>{{ producto.tipo_producto }}</td>
              <td>{{ producto.producto_grupo_padre?.nombre || '-' }}</td>
              <td>{{ producto.producto_grupo?.nombre || '-' }}</td>
              <td class="text-right">{{ Number(producto.precio1 || 0).toFixed(3) }}</td>
              <td class="text-right">{{ producto.peso_estimado !== null && producto.peso_estimado !== '' ? Number(producto.peso_estimado).toFixed(3) : '-' }}</td>
              <td>
                <q-badge :color="producto.active ? 'positive' : 'negative'" text-color="white">
                  {{ producto.active ? 'SI' : 'NO' }}
                </q-badge>
              </td>
              <td class="text-right">{{ Number(producto.stock || 0).toFixed(3) }}</td>
            </tr>
          </tbody>
        </q-markup-table>
      </q-card-section>
    </q-card>

    <q-dialog v-model="productoDialog" persistent>
      <q-card style="width: 980px; max-width: 98vw">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-subtitle1 text-bold">{{ actionPeriodo }} producto</div>
          <q-space />
          <q-btn icon="close" flat round dense @click="productoDialog = false" />
        </q-card-section>

        <q-card-section class="q-pt-sm">
          <q-form @submit="producto.id ? productoPut() : productoPost()">
            <div class="row q-col-gutter-sm">
              <div class="col-12 col-md-4">
                <q-input v-model="producto.codigo" label="Codigo" dense outlined maxlength="25" :rules="[v => !!v || 'Requerido']" />
              </div>
              <div class="col-12 col-md-2">
                <q-btn color="secondary" no-caps label="Generar" class="full-width" @click="autogenerarCodigo" />
              </div>
              <div class="col-12 col-md-6">
                <q-input v-model="producto.nombre" label="Nombre" dense outlined maxlength="105" :rules="[v => !!v || 'Requerido']" />
              </div>

              <div class="col-12 col-md-3">
                <q-select
                  v-model="producto.producto_grupo_padre_id"
                  :options="grupoPadres"
                  option-value="id"
                  option-label="nombre"
                  emit-value
                  map-options
                  label="Grupo padre"
                  dense
                  outlined
                  clearable
                  @update:model-value="onPadreChange"
                />
              </div>
              <div class="col-12 col-md-3">
                <q-select
                  v-model="producto.producto_grupo_id"
                  :options="grupos"
                  option-value="id"
                  option-label="nombre"
                  emit-value
                  map-options
                  label="Grupo"
                  dense
                  outlined
                  clearable
                />
              </div>
              <div class="col-12 col-md-2">
                <q-input v-model="producto.tipo_producto" label="Tipo prod. (3)" dense outlined maxlength="3" :rules="[v => !!v || 'Requerido']" />
              </div>
              <div class="col-12 col-md-2">
                <q-select
                  v-model="producto.tipo"
                  :options="tiposProducto"
                  label="Tipo"
                  dense
                  outlined
                  emit-value
                  map-options
                />
              </div>
              <div class="col-12 col-md-2 flex items-center">
                <q-toggle v-model="producto.active" label="Activo" />
              </div>

              <div class="col-12 col-md-2">
                <q-select
                  v-model="producto.codigo_unidad"
                  :options="unidadesCodigo"
                  option-value="value"
                  option-label="label"
                  emit-value
                  map-options
                  label="Codigo unidad"
                  dense
                  outlined
                  clearable
                />
              </div>
              <div class="col-12 col-md-2">
                <q-input v-model.number="producto.unidades_caja" label="Unidades caja" dense outlined type="number" step="0.01" />
              </div>
              <div class="col-12 col-md-2">
                <q-input v-model.number="producto.cantidad_presentacion" label="Cant. presentacion" dense outlined type="number" step="0.001" />
              </div>
              <div class="col-12 col-md-2">
                <q-input v-model.number="producto.peso_estimado" label="Peso estimado" dense outlined type="number" step="0.001" clearable />
              </div>
              <div class="col-12 col-md-2">
                <q-input v-model="producto.codigo_producto_sin" label="Codigo prod SIN" dense outlined maxlength="100" />
              </div>
              <div class="col-12 col-md-2">
                <q-input v-model="producto.codigo_grupo_sin" label="Codigo grupo SIN" dense outlined maxlength="100" />
              </div>
              <div class="col-12 col-md-2">
                <q-input v-model.number="producto.credito" label="Credito" dense outlined type="number" step="0.001" />
              </div>

              <div class="col-12 col-md-6">
                <q-input v-model="producto.presentacion" label="Presentacion" dense outlined maxlength="300" />
              </div>
              <div class="col-12 col-md-6">
                <q-input v-model="producto.oferta" label="Oferta" dense outlined maxlength="255" />
              </div>

              <div class="col-12 row items-center q-col-gutter-sm">
                <div class="col-12 col-md-3">
                  <q-input v-model.number="producto.precio1" label="Precio base (precio1)" dense outlined type="number" step="0.001" :rules="[v => v >= 0 || 'Min 0']" />
                </div>
                <div class="col-12 col-md-3">
                  <q-btn color="secondary" no-caps label="Copiar precio1 a precio2..13" class="full-width" @click="copiarPrecio1" />
                </div>
              </div>

              <div class="col-12">
                <div class="text-subtitle2 q-mb-sm">Precios 1..13</div>
                <div class="row q-col-gutter-sm">
                  <div class="col-6 col-md-2" v-for="n in 13" :key="n">
                    <q-input
                      v-model.number="producto['precio' + n]"
                      :label="`Precio ${n}`"
                      dense
                      outlined
                      type="number"
                      step="0.001"
                      :rules="[v => v >= 0 || 'Min 0']"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div class="text-right q-mt-md">
              <q-btn color="negative" label="Cancelar" @click="productoDialog = false" no-caps :loading="loading" />
              <q-btn color="primary" label="Guardar" type="submit" no-caps :loading="loading" class="q-ml-sm" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogImg" persistent @show="addPasteListener" @hide="removePasteListener">
      <q-card style="width: 420px; max-width: 95vw">
        <q-card-section class="q-pb-none row items-center text-bold">
          Cambiar imagen de {{ producto?.nombre || 'producto' }}
          <q-space />
          <q-btn icon="close" flat round dense @click="closeImgDialog" />
        </q-card-section>

        <q-card-section class="q-pt-sm">
          <div class="text-center q-mb-sm">
            <q-avatar size="220px" rounded>
              <q-img :src="imagePreview || imageUrl(producto.imagen)" />
            </q-avatar>
          </div>

          <div
            class="drop-zone q-pa-md text-center cursor-pointer"
            :class="{ 'drop-zone--active': isDragging }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
            @click="$refs.productImageInput.click()"
          >
            <q-icon name="cloud_upload" size="2rem" :color="isDragging ? 'primary' : 'grey-6'" />
            <div class="text-body2 q-mt-xs text-grey-7">
              Arrastra una imagen aquí, pega con <strong>Ctrl+V</strong><br>o haz clic para seleccionar
            </div>
            <input
              ref="productImageInput"
              type="file"
              accept="image/*"
              style="display: none"
              @change="onImageChange"
            />
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn color="negative" flat no-caps label="Cancelar" @click="closeImgDialog" :loading="loading" />
          <q-btn color="primary" no-caps label="Guardar imagen" @click="uploadImage" :loading="loading" :disable="!selectedImage" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="historialDialog" persistent>
      <q-card style="width: 850px; max-width: 98vw">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Historial de compras: {{ productoHistorialNombre }}</div>
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
                <th>Vendida</th>
                <th>Disponible</th>
                <th>% Vendido</th>
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
                <td>{{ Number(item.cantidad || 0).toFixed(3) }}</td>
                <td>{{ Number(item.cantidad_vendida || 0).toFixed(3) }}</td>
                <td>{{ Number(item.cantidad_disponible || 0).toFixed(3) }}</td>
                <td>{{ Number(item.porcentaje_vendido || 0).toFixed(2) }}%</td>
                <td>{{ Number(item.precio || 0).toFixed(3) }}</td>
                <td>{{ Number(item.total || 0).toFixed(3) }}</td>
              </tr>
            </tbody>
          </q-markup-table>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { Excel } from 'src/addons/Excel'

const normalizeTipoValue = (tipo) => String(tipo || '').trim().toUpperCase()

const emptyProducto = (defaultTipo = 'EMBUTIDO') => {
  const base = {
    codigo: '',
    imagen: 'uploads/default.png',
    producto_grupo_id: null,
    producto_grupo_padre_id: null,
    nombre: '',
    tipo_producto: 'PF',
    codigo_unidad: '',
    unidades_caja: null,
    cantidad_presentacion: null,
    peso_estimado: null,
    tipo: normalizeTipoValue(defaultTipo || 'EMBUTIDO'),
    oferta: ' ',
    codigo_producto_sin: '',
    presentacion: '',
    codigo_grupo_sin: '',
    credito: null,
    active: true,
  }

  for (let i = 1; i <= 13; i++) {
    base[`precio${i}`] = 0
  }

  return base
}

export default {
  name: 'ProductosPage',
  data () {
    const tiposBase = Array.isArray(this.$tiposProducto) && this.$tiposProducto.length
      ? this.$tiposProducto
      : ['huevo', 'pet', 'pollo', 'cerdo', 'embutido', 'res']
    const tiposNormalizados = tiposBase.map(tipo => normalizeTipoValue(tipo)).filter(Boolean)
    const tiposUnicos = [...new Set(tiposNormalizados)]
    const defaultTipoProducto = tiposUnicos.includes('EMBUTIDO') ? 'EMBUTIDO' : (tiposUnicos[0] || 'EMBUTIDO')
    const tiposProducto = tiposUnicos.map(tipo => ({ label: tipo, value: tipo }))

    return {
      productos: [],
      producto: emptyProducto(defaultTipoProducto),
      productoDialog: false,
      dialogImg: false,
      loading: false,
      actionPeriodo: '',
      filter: '',
      filterTipo: 'TODOS',
      filterUnidad: 'TODOS',
      pagination: {
        page: 1,
        rowsPerPage: 15,
        rowsNumber: 0,
      },
      historialDialog: false,
      historialCompras: [],
      productoHistorialNombre: '',
      grupoPadres: [],
      grupos: [],
      defaultTipoProducto,
      tiposProducto,
      tiposFiltro: [{ label: 'TODOS', value: 'TODOS' }, ...tiposProducto],
      unidadesCodigo: [
        { label: 'KG', value: 'KG' },
        { label: 'U', value: 'U' },
        // { label: 'UNIDA', value: 'UNIDA' },
      ],
      unidadesFiltro: [
        { label: 'TODOS', value: 'TODOS' },
        { label: 'KG', value: 'KG' },
        { label: 'U', value: 'U' },
        { label: 'UNIDA', value: 'UNIDA' },
      ],
      selectedImage: null,
      imagePreview: '',
      isDragging: false,
    }
  },
  mounted () {
    this.bootstrap()
  },
  computed: {
    totalProductosFiltrados () {
      return Number(this.pagination.rowsNumber || 0)
    },
    totalProductosPagina () {
      return this.productos.length
    },
    totalStockPagina () {
      return this.productos.reduce((acc, producto) => acc + Number(producto?.stock || 0), 0)
    },
    totalActivosPagina () {
      return this.productos.filter(producto => Boolean(producto?.active)).length
    },
  },
  methods: {
    tipoLabel (tipo) {
      const value = normalizeTipoValue(tipo || this.defaultTipoProducto)
      if (value === 'HUEVO') return 'Huevo'
      if (value === 'PET') return 'Pet'
      if (value === 'POLLO') return 'Pollo'
      if (value === 'RES') return 'Res'
      if (value === 'CERDO') return 'Cerdo'
      return 'Embutido'
    },
    tipoColor (tipo) {
      const value = normalizeTipoValue(tipo || this.defaultTipoProducto)
      if (value === 'HUEVO') return 'amber-8'
      if (value === 'PET') return 'blue-grey'
      if (value === 'POLLO') return 'orange'
      if (value === 'RES') return 'red'
      if (value === 'CERDO') return 'brown'
      return 'green-7'
    },
    unidadLabel (codigoUnidad) {
      const value = String(codigoUnidad || '').trim().toUpperCase()
      if (value === 'KG') return 'Kilogramo'
      if (value === 'U') return 'Unidad'
      if (value === 'UNIDA') return 'Unidad'
      return value || '-'
    },
    unidadColor (codigoUnidad) {
      const value = String(codigoUnidad || '').trim().toUpperCase()
      if (value === 'KG') return 'teal'
      if (value === 'U') return 'deep-orange'
      if (value === 'UNIDA') return 'indigo'
      return 'grey-7'
    },
    async bootstrap () {
      await Promise.all([this.loadGrupoPadres(), this.loadGrupos()])
      this.productosGet()
    },
    imageUrl (path) {
      const safe = path || 'uploads/default.png'
      return `${this.$url}../${safe}`
    },
    async loadGrupoPadres () {
      try {
        const res = await this.$axios.get('producto-grupo-padres')
        this.grupoPadres = res.data
      } catch (error) {
        this.$alert.error(error.response?.data?.message || 'No se pudo cargar grupo padre')
      }
    },
    async loadGrupos (padreId = null) {
      try {
        const params = {}
        if (padreId) params.producto_grupo_padre_id = padreId
        const res = await this.$axios.get('producto-grupos', { params })
        this.grupos = res.data
      } catch (error) {
        this.$alert.error(error.response?.data?.message || 'No se pudo cargar grupos')
      }
    },
    onPadreChange (padreId) {
      this.producto.producto_grupo_id = null
      this.loadGrupos(padreId)
    },
    onTipoFilterChange () {
      this.pagination.page = 1
      this.productosGet()
    },
    onUnidadFilterChange () {
      this.pagination.page = 1
      this.productosGet()
    },
    copiarPrecio1 () {
      const base = Number(this.producto.precio1 || 0)
      for (let i = 2; i <= 13; i++) {
        this.producto[`precio${i}`] = base
      }
    },
    autogenerarCodigo () {
      const cleaned = (this.producto.nombre || '')
        .toUpperCase()
        .replace(/[^A-Z0-9 ]/g, ' ')
        .trim()
        .split(/\s+/)
        .slice(0, 3)
        .map(p => p.slice(0, 3))
        .join('')
      const suffix = Date.now().toString().slice(-5)
      this.producto.codigo = cleaned ? `${cleaned}-${suffix}`.slice(0, 25) : `PROD-${suffix}`
    },
    prodImgEdit (producto) {
      this.producto = { ...producto }
      this.selectedImage = null
      this.imagePreview = ''
      this.dialogImg = true
    },
    addPasteListener () {
      document.addEventListener('paste', this.onPaste)
    },
    removePasteListener () {
      document.removeEventListener('paste', this.onPaste)
    },
    onDrop (e) {
      this.isDragging = false
      const file = e.dataTransfer?.files?.[0]
      if (file && file.type.startsWith('image/')) this.setImageFile(file)
    },
    onPaste (e) {
      const items = e.clipboardData?.items
      if (!items) return
      for (const item of items) {
        if (item.type.startsWith('image/')) {
          const file = item.getAsFile()
          if (file) { this.setImageFile(file); break }
        }
      }
    },
    setImageFile (file) {
      if (this.imagePreview) URL.revokeObjectURL(this.imagePreview)
      this.selectedImage = file
      this.imagePreview = URL.createObjectURL(file)
    },
    onImageChange (e) {
      const file = e.target.files?.[0]
      if (!file) return
      this.setImageFile(file)
    },
    closeImgDialog () {
      this.dialogImg = false
      this.selectedImage = null
      if (this.imagePreview) URL.revokeObjectURL(this.imagePreview)
      this.imagePreview = ''
    },
    async uploadImage () {
      if (!this.selectedImage || !this.producto.id) return
      this.loading = true
      try {
        const fd = new FormData()
        fd.append('image', this.selectedImage)
        fd.append('id', this.producto.id)
        await this.$axios.post('uploadImage', fd, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        this.$alert.success('Imagen actualizada')
        this.closeImgDialog()
        this.productosGet()
      } catch (error) {
        this.$alert.error(error.response?.data?.message || 'No se pudo actualizar imagen')
      } finally {
        this.loading = false
      }
    },
    verHistorial (producto) {
      this.loading = true
      this.productoHistorialNombre = producto.nombre
      this.$axios.get(`productos/${producto.id}/historial-compras`)
        .then(res => {
          this.historialCompras = res.data
          this.historialDialog = true
        }).catch(() => {
          this.$alert.error('Error al obtener historial')
        }).finally(() => {
          this.loading = false
        })
    },
    exportExcel () {
      this.loading = true
      this.$axios.get('productosAll').then(res => {
        const columns = [
          { label: 'Codigo', value: 'codigo' },
          { label: 'Nombre', value: 'nombre' },
          { label: 'Tipo producto', value: 'tipo_producto' },
          { label: 'Tipo', value: 'tipo' },
          { label: 'Precio1', value: 'precio1' },
          { label: 'Precio13', value: 'precio13' },
          { label: 'Peso estimado', value: 'peso_estimado' },
          { label: 'Activo', value: row => row.active ? 'SI' : 'NO' },
        ]
        const data = [{ columns, content: res.data }]
        Excel.export(data, 'Productos')
      }).catch(error => {
        this.$alert.error(error.response?.data?.message || 'Error al exportar')
      }).finally(() => {
        this.loading = false
      })
    },
    productoNew () {
      this.producto = emptyProducto(this.defaultTipoProducto)
      this.actionPeriodo = 'Nuevo'
      this.productoDialog = true
      this.loadGrupos()
    },
    productosGet () {
      this.loading = true
      this.$axios.get('productos', {
        params: {
          search: this.filter,
          tipo: this.filterTipo === 'TODOS' ? '' : this.filterTipo,
          codigo_unidad: this.filterUnidad === 'TODOS' ? '' : this.filterUnidad,
          page: this.pagination.page,
          per_page: this.pagination.rowsPerPage,
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
    normalizeProductoBeforeSave () {
      this.producto.codigo = (this.producto.codigo || '').trim()
      this.producto.nombre = (this.producto.nombre || '').trim()
      this.producto.tipo_producto = (this.producto.tipo_producto || 'PF').trim().toUpperCase()
      this.producto.tipo = normalizeTipoValue(this.producto.tipo || this.defaultTipoProducto)
      const pesoEstimado = Number(this.producto.peso_estimado)
      this.producto.peso_estimado = Number.isFinite(pesoEstimado) && pesoEstimado >= 0 ? pesoEstimado : null

      if (!this.producto.codigo) this.autogenerarCodigo()

      for (let i = 1; i <= 13; i++) {
        const k = `precio${i}`
        const v = Number(this.producto[k])
        this.producto[k] = Number.isFinite(v) ? v : 0
      }

      if (this.producto.producto_grupo_id && !this.producto.producto_grupo_padre_id) {
        const g = this.grupos.find(x => x.id === this.producto.producto_grupo_id)
        if (g) this.producto.producto_grupo_padre_id = g.producto_grupo_padre_id
      }
    },
    productoPost () {
      this.normalizeProductoBeforeSave()
      this.loading = true
      this.$axios.post('productos', this.producto).then(() => {
        this.productosGet()
        this.productoDialog = false
        this.$alert.success('Producto creado')
      }).catch(error => {
        this.$alert.error(error.response?.data?.message || 'No se pudo crear')
      }).finally(() => {
        this.loading = false
      })
    },
    productoPut () {
      this.normalizeProductoBeforeSave()
      this.loading = true
      this.$axios.put('productos/' + this.producto.id, this.producto).then(() => {
        this.productosGet()
        this.productoDialog = false
        this.$alert.success('Producto actualizado')
      }).catch(error => {
        this.$alert.error(error.response?.data?.message || 'No se pudo actualizar')
      }).finally(() => {
        this.loading = false
      })
    },
    async productoEdit (producto) {
      this.producto = { ...emptyProducto(this.defaultTipoProducto), ...producto }
      this.actionPeriodo = 'Editar'
      if (this.producto.producto_grupo_padre_id) {
        await this.loadGrupos(this.producto.producto_grupo_padre_id)
      } else {
        await this.loadGrupos()
      }
      this.productoDialog = true
    },
    productoDelete (id) {
      this.$alert.dialog('Desea eliminar el producto?')
        .onOk(() => {
          this.loading = true
          this.$axios.delete('productos/' + id).then(() => {
            this.productosGet()
            this.$alert.success('Producto eliminado')
          }).catch(error => {
            this.$alert.error(error.response?.data?.message || 'No se pudo eliminar')
          }).finally(() => {
            this.loading = false
          })
        })
    },
  }
}
</script>

<style scoped>
.drop-zone {
  border: 2px dashed #90a4ae;
  border-radius: 8px;
  transition: border-color 0.2s, background 0.2s;
  user-select: none;
}
.drop-zone--active {
  border-color: #1976d2;
  background: #e3f2fd;
}
.drop-zone:hover {
  border-color: #1976d2;
}

@media (max-width: 768px) {
  :deep(.q-pagination) {
    justify-content: center;
    flex-wrap: wrap;
  }

  :deep(.q-markup-table) {
    font-size: 12px;
  }
}
</style>
