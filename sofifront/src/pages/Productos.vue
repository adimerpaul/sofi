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
      :rows="productos"
      :columns="columns"
      row-key="CodAut"
      v-model:pagination="pagination"
      :loading="loading"
      :visible-columns="visibleColumns"
      :rows-per-page-options="[10, 20, 50, 100]"
      binary-state-sort
      @request="onRequest"
    >
      <template v-slot:top-right>
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

      <template v-slot:no-data>
        <div class="full-width row flex-center q-pa-md text-grey-7">
          <q-icon name="inventory_2" size="20px" class="q-mr-sm"/>
          No se encontraron productos con esos filtros
        </div>
      </template>
    </q-table>
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
        rowsPerPage: 20,
        rowsNumber: 0
      },
      // Precio3..Precio13 quedan ocultas por defecto: son 11 columnas que
      // hacen ilegible la tabla, pero el usuario puede activarlas.
      visibleColumns: ['cod_prod', 'Producto', 'grupo', 'codUnid', 'Precio', 'Precio_Costo', 'cantidad'],
      columns: [
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
        this.pagination.rowsPerPage = Number(res.data.per_page)
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
