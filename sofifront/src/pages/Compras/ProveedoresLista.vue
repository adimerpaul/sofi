<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md">
        <div class="text-h6 text-weight-bold">Proveedores</div>
        <div class="text-caption text-grey-7">A quiénes se les compra la mercadería</div>
      </div>
      <div class="col-auto">
        <q-btn color="positive" unelevated no-caps icon="add" label="Nuevo proveedor" @click="abrirNuevo"/>
      </div>
    </div>

    <q-card flat bordered class="q-pa-sm q-mb-md">
      <div class="row q-col-gutter-sm" @keyup.enter="recargar">
        <div class="col-12 col-md-4">
          <q-input v-model="filtros.buscar" dense outlined clearable label="Nombre, NIT o dirección">
            <template v-slot:append><q-icon name="search"/></template>
          </q-input>
        </div>
        <div class="col-12 col-md row items-center q-gutter-sm">
          <q-btn :loading="loading" color="primary" icon="search" no-caps label="Buscar" @click="recargar"/>
          <q-btn flat color="grey-7" icon="layers_clear" no-caps label="Limpiar" @click="limpiar"/>
        </div>
      </div>
    </q-card>

    <q-table
      flat bordered dense
      :rows="proveedores"
      :columns="columns"
      row-key="id"
      v-model:pagination="pagination"
      :loading="loading"
      :rows-per-page-options="[15, 30, 50, 100]"
      @request="onRequest"
    >
      <template v-slot:body-cell-acciones="props">
        <q-td :props="props" style="white-space: nowrap">
          <q-btn-dropdown color="primary" size="sm" dense no-caps icon="menu" label="Opciones">
            <q-list dense style="min-width: 170px">
              <q-item clickable v-close-popup @click="abrirEdicion(props.row)">
                <q-item-section avatar><q-icon name="edit" color="primary"/></q-item-section>
                <q-item-section>Editar</q-item-section>
              </q-item>
              <q-separator/>
              <q-item clickable v-close-popup @click="eliminar(props.row)">
                <q-item-section avatar><q-icon name="delete" color="negative"/></q-item-section>
                <q-item-section>
                  Eliminar
                  <q-item-label v-if="props.row.compras > 0" caption>
                    Tiene {{ props.row.compras }} compra(s)
                  </q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-btn-dropdown>
        </q-td>
      </template>

      <template v-slot:body-cell-compras="props">
        <q-td :props="props" class="text-right">
          <q-badge :color="Number(props.value) > 0 ? 'indigo-5' : 'grey-5'" text-color="white">
            {{ props.value }}
          </q-badge>
        </q-td>
      </template>

      <template v-slot:no-data>
        <div class="full-width row flex-center q-pa-md text-grey-7">
          <q-icon name="local_shipping" size="20px" class="q-mr-sm"/>
          No hay proveedores con ese criterio
        </div>
      </template>
    </q-table>

    <q-dialog v-model="dialogEditar">
      <q-card style="width: 520px; max-width: 96vw">
        <q-card-section class="bg-primary text-white q-py-sm">
          <div class="text-subtitle1 text-weight-bold">
            {{ edicion.id ? 'Editar proveedor' : 'Nuevo proveedor' }}
          </div>
        </q-card-section>

        <q-card-section class="row q-col-gutter-sm">
          <q-input
            v-model.trim="edicion.PROVEEDOR" outlined dense class="col-12" autofocus
            label="Nombre o razón social" :rules="[v => !!v || 'El nombre es obligatorio']"
          />
          <q-input
            v-model.trim="edicion.NIT" outlined dense class="col-12 col-sm-5"
            label="NIT" hint="Opcional"
          />
          <q-input v-model.trim="edicion.TELF" outlined dense class="col-12 col-sm-7" label="Teléfono"/>
          <q-input v-model.trim="edicion.DIRECCION" outlined dense class="col-12" label="Dirección"/>
        </q-card-section>

        <q-separator/>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense no-caps label="Cancelar" v-close-popup/>
          <q-btn
            color="positive" dense unelevated no-caps icon="save" label="Guardar"
            :disable="!edicion.PROVEEDOR"
            :loading="guardando" @click="guardar"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
function proveedorVacio () {
  return { id: null, NIT: '', PROVEEDOR: '', DIRECCION: '', TELF: '' }
}

export default {
  name: 'ProveedoresLista',
  data () {
    return {
      proveedores: [],
      loading: false,
      guardando: false,
      dialogEditar: false,
      edicion: proveedorVacio(),
      filtros: { buscar: '' },
      pagination: { page: 1, rowsPerPage: 15, rowsNumber: 0 },
      columns: [
        { name: 'acciones', label: 'Opciones', field: 'acciones', align: 'left' },
        { name: 'nit', label: 'NIT', field: 'nit', align: 'left' },
        { name: 'proveedor', label: 'Proveedor', field: 'proveedor', align: 'left' },
        { name: 'direccion', label: 'Dirección', field: 'direccion', align: 'left' },
        { name: 'telefono', label: 'Teléfono', field: 'telefono', align: 'left' },
        { name: 'compras', label: 'Compras', field: 'compras', align: 'right' }
      ]
    }
  },
  created () {
    this.onRequest({ pagination: this.pagination })
  },
  methods: {
    limpiar () {
      this.filtros.buscar = ''
      this.recargar()
    },
    recargar () {
      this.pagination.page = 1
      this.onRequest({ pagination: this.pagination })
    },
    onRequest (props) {
      const { page, rowsPerPage } = props.pagination
      this.loading = true

      this.$api.get('proveedores', {
        params: { buscar: this.filtros.buscar || '', page, perPage: rowsPerPage }
      }).then(res => {
        this.proveedores = res.data.data
        this.pagination.page = res.data.current_page
        this.pagination.rowsPerPage = rowsPerPage
        this.pagination.rowsNumber = res.data.total
      }).catch(err => {
        this.avisar(err, 'No se pudieron cargar los proveedores')
      }).finally(() => {
        this.loading = false
      })
    },

    abrirNuevo () {
      this.edicion = proveedorVacio()
      this.dialogEditar = true
    },
    abrirEdicion (row) {
      // Copia: si cancela, la fila de la tabla queda intacta. Y con '' en vez
      // de null, porque las columnas de tbproveedor son NOT NULL.
      this.edicion = {
        id: row.id,
        NIT: row.nit || '',
        PROVEEDOR: row.proveedor || '',
        DIRECCION: row.direccion || '',
        TELF: row.telefono || ''
      }
      this.dialogEditar = true
    },
    guardar () {
      this.guardando = true

      const peticion = this.edicion.id
        ? this.$api.put('proveedores/' + this.edicion.id, this.edicion)
        : this.$api.post('proveedores', this.edicion)

      peticion.then(res => {
        this.$q.notify({
          message: res.data.message,
          color: 'positive',
          icon: 'check_circle',
          position: 'top'
        })
        this.dialogEditar = false
        this.onRequest({ pagination: this.pagination })
      }).catch(err => {
        this.avisar(err, 'No se pudo guardar el proveedor')
      }).finally(() => {
        this.guardando = false
      })
    },

    eliminar (row) {
      this.$q.dialog({
        title: 'Eliminar proveedor',
        message: '¿Eliminar a "' + row.proveedor + '"?',
        cancel: true,
        persistent: true,
        ok: { label: 'Eliminar', color: 'negative', noCaps: true }
      }).onOk(() => {
        this.$api.delete('proveedores/' + row.id)
          .then(res => {
            this.$q.notify({
              message: res.data.message,
              color: 'positive',
              icon: 'check_circle',
              position: 'top'
            })
            this.onRequest({ pagination: this.pagination })
          })
          .catch(err => { this.avisar(err, 'No se pudo eliminar') })
      })
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
