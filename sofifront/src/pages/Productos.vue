<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md">
        <div class="text-h6 text-weight-bold">Productos</div>
        <div class="text-caption text-grey-7">Catálogo con precios y stock disponible</div>
      </div>
      <div class="col-auto">
        <q-chip square dense color="primary" text-color="white" icon="inventory_2">
          <span class="text-caption">Resultados:</span>&nbsp;<b>{{ pagination.rowsNumber }}</b>
        </q-chip>
      </div>
    </div>

    <q-card flat bordered class="q-pa-sm q-mb-md">
      <div class="row q-col-gutter-sm">
        <div class="col-12 col-md-4">
          <q-input
            v-model="filtros.search"
            dense outlined clearable debounce="400"
            label="Buscar por código o nombre"
            @update:model-value="recargar"
          >
            <template v-slot:append><q-icon name="search"/></template>
          </q-input>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <q-select
            v-model="filtros.grupo"
            dense outlined clearable emit-value map-options
            label="Grupo"
            :options="grupos"
            @update:model-value="recargar"
          />
        </div>
        <div class="col-12 col-sm-6 col-md-2">
          <q-select
            v-model="filtros.unidad"
            dense outlined clearable
            label="Unidad"
            :options="unidades"
            @update:model-value="recargar"
          />
        </div>
        <div class="col-12 col-md-3 row items-center q-gutter-x-md">
          <q-toggle
            v-model="filtros.conStock"
            dense label="Solo con stock" color="primary"
            @update:model-value="recargar"
          />
          <q-toggle
            v-model="filtros.incluirInactivos"
            dense label="Ver inactivos" color="grey-7"
            @update:model-value="recargar"
          />
        </div>
      </div>
    </q-card>

    <q-table
      flat bordered dense
      class="tabla-compacta"
      :rows="productos"
      :columns="columns"
      row-key="CodAut"
      v-model:pagination="pagination"
      :loading="loading"
      :visible-columns="visibleColumns"
      :rows-per-page-options="[15, 30, 50, 100, 0]"
      binary-state-sort
      @request="onRequest"
    >
      <template v-slot:top-right>
        <q-btn
          dense unelevated no-caps color="green-8" icon="table_chart"
          label="Excel" class="q-mr-sm" :loading="exportando === 'excel'"
          @click="exportar('excel')"
        />
        <q-btn
          dense unelevated no-caps color="red-7" icon="picture_as_pdf"
          label="PDF" class="q-mr-sm" :loading="exportando === 'pdf'"
          @click="exportar('pdf')"
        />
        <q-select
          v-model="visibleColumns"
          multiple dense outlined options-dense emit-value map-options
          display-value="Columnas"
          :options="columnasOpcionales"
          option-value="name"
          style="min-width: 140px"
        />
      </template>

      <template v-slot:body-cell-cantidad="props">
        <q-td :props="props" class="text-right">
          <q-badge :color="Number(props.value) > 0 ? 'green-6' : 'grey-5'" text-color="white">
            {{ num(props.value) }}
          </q-badge>
        </q-td>
      </template>

      <!-- Opciones: primera columna para que no queden fuera de pantalla. -->
      <template v-slot:body-cell-acciones="props">
        <q-td :props="props" style="white-space: nowrap">
          <q-btn-dropdown
            color="primary" size="sm" dense no-caps icon="menu" label="Opciones"
            :loading="subiendo === props.row.cod_prod"
          >
            <q-list dense style="min-width: 190px">
              <q-item clickable v-close-popup @click="abrirEdicion(props.row)">
                <q-item-section avatar><q-icon name="edit" color="primary"/></q-item-section>
                <q-item-section>Actualizar datos</q-item-section>
              </q-item>

              <q-item clickable v-close-popup @click="elegirImagen(props.row)">
                <q-item-section avatar><q-icon name="photo_camera" color="teal"/></q-item-section>
                <q-item-section>
                  {{ props.row.imagen ? 'Cambiar imagen' : 'Subir imagen' }}
                </q-item-section>
              </q-item>

              <q-item
                clickable v-close-popup
                :disable="!props.row.imagen"
                @click="quitarImagen(props.row)"
              >
                <q-item-section avatar><q-icon name="hide_image" color="orange-8"/></q-item-section>
                <q-item-section>Quitar imagen</q-item-section>
              </q-item>

              <q-separator/>

              <q-item clickable v-close-popup @click="eliminar(props.row)">
                <q-item-section avatar><q-icon name="delete" color="negative"/></q-item-section>
                <q-item-section>
                  Eliminar
                  <q-item-label caption>Lo marca como inactivo</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-btn-dropdown>
        </q-td>
      </template>

      <!-- Clic o arrastrar una imagen encima para cambiar la foto. -->
      <template v-slot:body-cell-imagen="props">
        <q-td :props="props" class="text-center">
          <div
            class="foto-zona"
            :class="{ 'foto-zona--activa': arrastrando === props.row.cod_prod }"
            @click="elegirImagen(props.row)"
            @dragenter.prevent.stop="arrastrando = props.row.cod_prod"
            @dragover.prevent.stop="arrastrando = props.row.cod_prod"
            @dragleave.prevent.stop="arrastrando = null"
            @drop.prevent.stop="soltarImagen($event, props.row)"
          >
            <img v-if="props.row.imagen" :src="urlImagen(props.row.imagen)" alt=""/>
            <q-icon v-else name="add_a_photo" size="20px" color="grey-6"/>

            <q-inner-loading :showing="subiendo === props.row.cod_prod">
              <q-spinner size="18px" color="primary"/>
            </q-inner-loading>

            <q-tooltip>
              {{ props.row.imagen ? 'Clic o arrastra una imagen para cambiarla' : 'Clic o arrastra una imagen aquí' }}
            </q-tooltip>
          </div>

          <q-btn
            v-if="props.row.imagen"
            dense flat round size="xs" icon="close" color="negative"
            @click.stop="quitarImagen(props.row)"
          >
            <q-tooltip>Quitar foto</q-tooltip>
          </q-btn>
        </q-td>
      </template>

      <template v-slot:no-data>
        <div class="full-width row flex-center q-pa-md text-grey-7">
          <q-icon name="inventory_2" size="20px" class="q-mr-sm"/>
          No se encontraron productos con esos filtros
        </div>
      </template>
    </q-table>

    <!-- Selector de archivo oculto: lo dispara la miniatura de la tabla. -->
    <input
      ref="fileImagen"
      type="file"
      accept="image/*"
      class="hidden"
      @change="subirImagen"
    />

    <q-dialog v-model="dialogEditar">
      <q-card style="width: 640px; max-width: 96vw">
        <q-card-section class="bg-primary text-white q-py-sm">
          <div class="text-subtitle1 text-weight-bold">Actualizar producto</div>
          <div class="text-caption">{{ edicion.cod_prod }}</div>
        </q-card-section>

        <q-card-section class="row q-col-gutter-sm">
          <q-input
            v-model.trim="edicion.Producto" outlined dense class="col-12"
            label="Producto" :rules="[v => !!v || 'El nombre es obligatorio']"
          />
          <q-input v-model.trim="edicion.Nomcomer" outlined dense class="col-12" label="Nombre comercial"/>

          <q-select
            v-model="edicion.cod_grup" outlined dense clearable emit-value map-options
            use-input fill-input hide-selected input-debounce="0"
            class="col-12 col-sm-6" label="Grupo"
            :options="gruposFiltrados" @filter="filtrarGrupos"
          />
          <q-select
            v-model="edicion.codUnid" outlined dense clearable
            class="col-6 col-sm-3" label="Unidad" :options="unidades"
          />
          <q-input v-model.trim="edicion.tipo" outlined dense class="col-6 col-sm-3" label="Tipo"/>

          <q-input v-model.number="edicion.Precio" outlined dense type="number" step="0.01" min="0" class="col-6 col-sm-4" label="Precio" prefix="Bs"/>
          <q-input v-model.number="edicion.Precio_Costo" outlined dense type="number" step="0.01" min="0" class="col-6 col-sm-4" label="Precio costo" prefix="Bs"/>
          <q-input v-model.number="edicion.Precio3" outlined dense type="number" step="0.01" min="0" class="col-6 col-sm-4" label="Precio 3" prefix="Bs"/>
          <q-input v-model.number="edicion.Precio4" outlined dense type="number" step="0.01" min="0" class="col-6 col-sm-4" label="Precio 4" prefix="Bs"/>
          <q-input v-model.number="edicion.Precio5" outlined dense type="number" step="0.01" min="0" class="col-6 col-sm-4" label="Precio 5" prefix="Bs"/>
          <q-input v-model.number="edicion.Precio6" outlined dense type="number" step="0.01" min="0" class="col-6 col-sm-4" label="Precio 6" prefix="Bs"/>
        </q-card-section>

        <q-separator/>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense no-caps label="Cancelar" v-close-popup/>
          <q-btn
            color="positive" dense unelevated no-caps icon="save" label="Guardar"
            :disable="!edicion.Producto" :loading="guardando" @click="guardarEdicion"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
export default {
  name: 'ProductosPage',
  data () {
    return {
      productos: [],
      grupos: [],
      unidades: [],
      loading: false,
      filtros: {
        search: '',
        grupo: null,
        unidad: null,
        conStock: false,
        incluirInactivos: false
      },
      pagination: {
        sortBy: 'Producto',
        descending: false,
        page: 1,
        rowsPerPage: 15,
        rowsNumber: 0
      },
      // Precio3..Precio13 quedan ocultas por defecto: son 11 columnas que
      // hacen ilegible la tabla, pero el usuario puede activarlas.
      visibleColumns: ['acciones', 'imagen', 'cod_prod', 'Producto', 'grupo', 'codUnid', 'Precio', 'Precio_Costo', 'cantidad'],
      // Producto cuya foto se está subiendo, para el spinner de la miniatura.
      subiendo: null,
      productoImagen: null,
      // Fila sobre la que se está arrastrando una imagen.
      arrastrando: null,
      dialogEditar: false,
      guardando: false,
      edicion: {},
      // Fila de la tabla que se está editando, para refrescarla al guardar.
      filaEditada: null,
      exportando: null,
      gruposFiltrados: [],
      columns: [
        { name: 'acciones', label: 'Opciones', field: 'acciones', align: 'left' },
        { name: 'imagen', label: 'Foto', field: 'imagen', align: 'center' },
        { name: 'cod_prod', label: 'Código', field: 'cod_prod', align: 'left', sortable: true },
        { name: 'Producto', label: 'Producto', field: 'Producto', align: 'left', sortable: true },
        { name: 'grupo', label: 'Grupo', field: 'grupo', align: 'left', sortable: true },
        { name: 'codUnid', label: 'Unidad', field: 'codUnid', align: 'center', sortable: true },
        { name: 'Precio', label: 'Precio', field: 'Precio', align: 'right', sortable: true, format: v => Number(v || 0).toFixed(2) },
        { name: 'Precio_Costo', label: 'P. Costo', field: 'Precio_Costo', align: 'right', format: v => Number(v || 0).toFixed(2) },
        { name: 'PreCosto', label: 'PreCosto', field: 'PreCosto', align: 'right', sortable: true, format: v => Number(v || 0).toFixed(2) },
        { name: 'cantidad', label: 'Stock', field: 'cantidad', align: 'right', sortable: true }
      ]
    }
  },
  computed: {
    columnasOpcionales () {
      return this.columns.map(c => ({ name: c.name, label: c.label }))
    }
  },
  created () {
    // Precio3..Precio13 se agregan por código para no repetir 11 definiciones.
    for (let i = 3; i <= 13; i++) {
      this.columns.push({
        name: 'Precio' + i,
        label: 'Precio ' + i,
        field: 'Precio' + i,
        align: 'right',
        format: v => Number(v || 0).toFixed(2)
      })
    }
    this.cargarFiltros()
    this.onRequest({ pagination: this.pagination })
  },
  methods: {
    num (v) {
      return Number(v || 0).toFixed(2)
    },

    // Las fotos se sirven desde public/, no desde /api/, de ahi el recorte.
    urlImagen (ruta) {
      return String(this.$url || '').replace(/api\/?$/, '') + ruta
    },

    elegirImagen (row) {
      this.productoImagen = row
      // Se limpia el valor para que elegir la misma foto vuelva a disparar
      // el change.
      this.$refs.fileImagen.value = ''
      this.$refs.fileImagen.click()
    },

    // Arrastrar la imagen desde el explorador sobre la miniatura.
    soltarImagen (evento, row) {
      this.arrastrando = null

      const archivo = evento.dataTransfer?.files?.[0]
      if (!archivo) {
        return
      }

      if (!archivo.type.startsWith('image/')) {
        this.$q.notify({
          message: 'Ese archivo no es una imagen',
          color: 'warning',
          icon: 'info',
          position: 'top'
        })
        return
      }

      this.enviarImagen(archivo, row)
    },

    subirImagen (evento) {
      const archivo = evento.target.files && evento.target.files[0]
      const row = this.productoImagen

      if (!archivo || !row) {
        return
      }

      this.enviarImagen(archivo, row)
    },

    enviarImagen (archivo, row) {
      const datos = new FormData()
      datos.append('imagen', archivo)

      this.subiendo = row.cod_prod

      this.$api.post('productos/' + encodeURIComponent(row.cod_prod) + '/imagen', datos, {
        headers: { 'Content-Type': 'multipart/form-data' }
      }).then(res => {
        row.imagen = res.data.imagen
        this.$q.notify({
          message: res.data.message,
          color: 'positive',
          icon: 'check_circle',
          position: 'top'
        })
      }).catch(err => {
        this.avisarError(err, 'No se pudo subir la imagen')
      }).finally(() => {
        this.subiendo = null
        this.productoImagen = null
      })
    },

    // Los mismos filtros que la tabla, para que la exportación coincida.
    filtrosActuales () {
      return {
        search: this.filtros.search || '',
        grupo: this.filtros.grupo || '',
        unidad: this.filtros.unidad || '',
        conStock: this.filtros.conStock ? 1 : 0,
        incluirInactivos: this.filtros.incluirInactivos ? 1 : 0
      }
    },

    exportar (formato) {
      this.exportando = formato

      this.$api.get('productos/' + formato, {
        params: this.filtrosActuales(),
        responseType: 'blob'
      }).then(res => {
        const tipo = formato === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        const url = window.URL.createObjectURL(new Blob([res.data], { type: tipo }))

        if (formato === 'pdf') {
          // El PDF se abre para verlo; si el navegador bloquea, se descarga.
          if (window.open(url, '_blank')) {
            setTimeout(() => window.URL.revokeObjectURL(url), 60000)
            return
          }
        }

        const link = document.createElement('a')
        link.href = url
        link.download = 'productos.' + (formato === 'pdf' ? 'pdf' : 'xlsx')
        link.click()
        setTimeout(() => window.URL.revokeObjectURL(url), 60000)
      }).catch(err => {
        this.avisarError(err, 'No se pudo exportar')
      }).finally(() => {
        this.exportando = null
      })
    },

    eliminar (row) {
      this.$q.dialog({
        title: 'Eliminar producto',
        message: '¿Dar de baja "' + row.Producto + '"? Se marcará como INACTIVO y ' +
          'dejará de aparecer en el catálogo, pero se conserva el historial de ventas.',
        cancel: true,
        persistent: true,
        ok: { label: 'Eliminar', color: 'negative', noCaps: true }
      }).onOk(() => {
        this.$api.delete('productos/' + encodeURIComponent(row.cod_prod))
          .then(res => {
            this.$q.notify({
              message: res.data.message,
              color: 'positive',
              icon: 'check_circle',
              position: 'top'
            })
            this.onRequest({ pagination: this.pagination })
          })
          .catch(err => { this.avisarError(err, 'No se pudo eliminar') })
      })
    },

    abrirEdicion (row) {
      // Se edita una copia: si cancela, la fila de la tabla queda intacta.
      // Los textos van con '' y no null: las columnas de tbproductos son
      // NOT NULL y un campo vacío llegaría al backend como null.
      this.edicion = {
        cod_prod: row.cod_prod,
        Producto: row.Producto || '',
        Nomcomer: row.Nomcomer || '',
        cod_grup: row.cod_grup || '',
        codUnid: row.codUnid || '',
        tipo: row.tipo || '',
        Precio: Number(row.Precio) || 0,
        Precio_Costo: Number(row.Precio_Costo) || 0,
        Precio3: Number(row.Precio3) || 0,
        Precio4: Number(row.Precio4) || 0,
        Precio5: Number(row.Precio5) || 0,
        Precio6: Number(row.Precio6) || 0
      }
      this.filaEditada = row
      this.gruposFiltrados = this.grupos
      this.dialogEditar = true
    },

    filtrarGrupos (texto, update) {
      update(() => {
        const busca = (texto || '').toLowerCase().trim()
        this.gruposFiltrados = busca === ''
          ? this.grupos
          : this.grupos.filter(g => String(g.label || g).toLowerCase().includes(busca))
      })
    },

    guardarEdicion () {
      this.guardando = true

      this.$api.put('productos/' + encodeURIComponent(this.edicion.cod_prod), this.edicion)
        .then(res => {
          this.$q.notify({
            message: res.data.message,
            color: 'positive',
            icon: 'check_circle',
            position: 'top'
          })
          this.dialogEditar = false
          // Se recarga en vez de parchear la fila: la columna Grupo sale de un
          // join y el stock de tbstock, asi que copiar solo los campos
          // editados dejaba la tabla mostrando el grupo anterior.
          this.onRequest({ pagination: this.pagination })
        })
        .catch(err => { this.avisarError(err, 'No se pudo actualizar el producto') })
        .finally(() => { this.guardando = false })
    },

    quitarImagen (row) {
      this.$q.dialog({
        title: 'Quitar foto',
        message: '¿Quitar la foto de ' + row.Producto + '?',
        cancel: true,
        persistent: true
      }).onOk(() => {
        this.$api.delete('productos/' + encodeURIComponent(row.cod_prod) + '/imagen')
          .then(() => { row.imagen = null })
          .catch(err => { this.avisarError(err, 'No se pudo quitar la imagen') })
      })
    },

    async avisarError (err, porDefecto) {
      let mensaje = err.response?.data?.message

      // En las descargas la respuesta viaja como Blob, así que el motivo real
      // del error hay que leerlo del propio Blob.
      if (err.response?.data instanceof Blob) {
        try {
          mensaje = JSON.parse(await err.response.data.text()).message
        } catch (e) {
          mensaje = null
        }
      }

      this.$q.notify({
        message: mensaje || porDefecto,
        color: 'negative',
        icon: 'error',
        position: 'top'
      })
    },
    cargarFiltros () {
      this.$api.get('filtrosProducto').then(res => {
        this.grupos = res.data.grupos || []
        this.unidades = res.data.unidades || []
      }).catch(() => {
        // Los selects quedan vacíos; la tabla sigue siendo usable con la búsqueda.
      })
    },
    recargar () {
      this.pagination.page = 1
      this.onRequest({ pagination: this.pagination })
    },
    onRequest (props) {
      const { page, rowsPerPage, sortBy, descending } = props.pagination
      this.loading = true

      this.$api.get('productosPaginado', {
        params: {
          page,
          perPage: rowsPerPage,
          sortBy,
          descending,
          search: this.filtros.search || '',
          grupo: this.filtros.grupo || '',
          unidad: this.filtros.unidad || '',
          conStock: this.filtros.conStock ? 1 : 0,
          incluirInactivos: this.filtros.incluirInactivos ? 1 : 0
        }
      }).then(res => {
        this.productos = res.data.data
        this.pagination.page = res.data.current_page
        // Con "Todos" (0) el backend responde con su tope real; hay que
        // conservar el 0 o el selector se queda sin opción marcada.
        this.pagination.rowsPerPage = rowsPerPage === 0 ? 0 : Number(res.data.per_page)
        this.pagination.rowsNumber = res.data.total
        this.pagination.sortBy = sortBy
        this.pagination.descending = descending
      }).catch(err => {
        this.$q.notify({
          message: err.response?.data?.message || 'No se pudo cargar el catálogo de productos',
          color: 'negative',
          icon: 'error',
          position: 'top'
        })
      }).finally(() => {
        this.loading = false
      })
    }
  }
}
</script>

<style scoped>
/* Tabla compacta: con 1.050 productos y muchas columnas, cuanto mas quepa
   por pantalla menos hay que paginar y desplazarse. */
.tabla-compacta :deep(thead th) {
  font-size: 11px;
  padding: 4px 8px;
  white-space: nowrap;
}
.tabla-compacta :deep(tbody td) {
  font-size: 12px;
  padding: 2px 8px;
  height: auto;
}
.tabla-compacta :deep(tbody tr) {
  height: 40px;
}

/* Miniatura que ademas es zona de arrastre. */
.foto-zona {
  position: relative;
  width: 44px;
  height: 44px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px dashed #cfcfcf;
  border-radius: 6px;
  background: #fafafa;
  overflow: hidden;
  cursor: pointer;
  transition: border-color .15s, background .15s;
}
.foto-zona:hover {
  border-color: var(--q-primary);
}
/* Mientras se arrastra encima, para que se vea dónde se va a soltar. */
.foto-zona--activa {
  border-color: var(--q-primary);
  border-style: solid;
  background: #e3f2fd;
}
.foto-zona img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  /* Sin esto el navegador arrastra la propia miniatura al soltar. */
  pointer-events: none;
}
</style>
