<template>
  <q-page class="q-pa-xs">
    <!-- Barra: busqueda arriba y los cambios pendientes con Grabar a la vista. -->
    <div class="row items-center q-gutter-xs q-mb-xs barra">
      <div class="text-subtitle1 text-weight-bold q-mr-sm">Cambio de precios</div>
      <q-input v-model="buscar" dense outlined clearable debounce="250" autofocus
               placeholder="Buscar por código o descripción" class="col" style="min-width: 220px">
        <template v-slot:prepend><q-icon name="search"/></template>
      </q-input>
      <q-toggle v-model="verInactivos" dense label="Ver inactivos" class="q-mx-sm"/>
      <q-btn flat dense no-caps icon="refresh" label="Recargar" :loading="cargando" @click="pedirRecarga"/>
      <q-btn flat dense no-caps color="grey-8" icon="undo" label="Descartar" :disable="!pendientes"
             @click="descartar"/>
      <q-btn unelevated dense no-caps color="positive" icon="save" padding="2px 12px"
             :label="pendientes ? 'Grabar (' + pendientes + ')' : 'Grabar'"
             :disable="!pendientes" :loading="guardando" @click="grabar"/>
    </div>
    <div class="text-caption text-grey-7 q-mb-xs">
      {{ visibles.length }} productos · Enter, ↑ y ↓ pasan a la fila de abajo o de arriba en la misma columna;
      lo cambiado queda en amarillo hasta grabar.
    </div>

    <q-table
      class="tabla-precios" flat bordered dense virtual-scroll
      :rows="visibles" :columns="columnas" row-key="cod_prod"
      :rows-per-page-options="[0]" hide-pagination :loading="cargando"
      :virtual-scroll-item-size="25" :virtual-scroll-sticky-size-start="30"
      no-data-label="No hay productos con esa búsqueda"
    >
      <template v-slot:body="props">
        <q-tr :props="props" :class="{ 'fila-cambiada': cambios[props.row.cod_prod] }">
          <q-td key="cod_prod" :props="props" class="text-weight-medium">{{ props.row.cod_prod }}</q-td>
          <q-td key="producto" :props="props" class="celda-producto" :title="props.row.producto">
            {{ props.row.producto }}
          </q-td>
          <q-td key="unidad" :props="props" class="text-grey-7">{{ props.row.unidad }}</q-td>
          <q-td v-for="campo in campos" :key="campo" :props="props" class="celda-precio">
            <input
              type="number" step="0.001" min="0" class="input-precio"
              :class="{ cambiado: cambiado(props.row, campo), compra: campo === 'precio_compra' }"
              :value="valor(props.row, campo)"
              :data-fila="props.rowIndex" :data-campo="campo"
              @focus="$event.target.select()"
              @change="editar(props.row, campo, $event.target.value)"
              @keydown="mover($event, props.rowIndex, campo)"
            >
          </q-td>
        </q-tr>
      </template>
    </q-table>
  </q-page>
</template>

<script>
// Los 13 precios en el orden del sistema anterior: Precio es el publico (el de
// venta) y Precio_Costo el "2do precio".
const PRECIOS = [
  { campo: 'Precio', etiqueta: 'Público (venta)' },
  { campo: 'Precio_Costo', etiqueta: '2do' },
  { campo: 'Precio3', etiqueta: '3ro' },
  { campo: 'Precio4', etiqueta: '4to' },
  { campo: 'Precio5', etiqueta: '5to' },
  { campo: 'Precio6', etiqueta: '6to' },
  { campo: 'Precio7', etiqueta: '7mo' },
  { campo: 'Precio8', etiqueta: '8vo' },
  { campo: 'Precio9', etiqueta: '9no' },
  { campo: 'Precio10', etiqueta: '10mo' },
  { campo: 'Precio11', etiqueta: '11vo' },
  { campo: 'Precio12', etiqueta: '12vo' },
  { campo: 'Precio13', etiqueta: '13vo' }
]

export default {
  name: 'CambioPrecios',
  data () {
    return {
      productos: [],
      // cod_prod -> { campo: valor } con lo que se cambio y todavia no se grabo.
      cambios: {},
      buscar: '',
      verInactivos: false,
      cargando: false,
      guardando: false,
      // Compra va primero, al lado de la venta, que son los que mas se miran.
      campos: ['precio_compra', ...PRECIOS.map(p => p.campo)],
      columnas: [
        { name: 'cod_prod', label: 'Código', field: 'cod_prod', align: 'left', sortable: true },
        { name: 'producto', label: 'Descripción del producto', field: 'producto', align: 'left', sortable: true },
        { name: 'unidad', label: 'Und', field: 'unidad', align: 'left' },
        { name: 'precio_compra', label: 'Compra', field: 'precio_compra', align: 'right' },
        ...PRECIOS.map(p => ({ name: p.campo, label: p.etiqueta, field: p.campo, align: 'right' }))
      ]
    }
  },
  computed: {
    visibles () {
      const texto = (this.buscar || '').trim().toLowerCase()
      return this.productos.filter(p =>
        (this.verInactivos || !p.inactivo) &&
        (!texto || p.cod_prod.toLowerCase().includes(texto) || p.producto.toLowerCase().includes(texto)))
    },
    pendientes () {
      return Object.keys(this.cambios).length
    }
  },
  created () {
    this.cargar()
    window.addEventListener('beforeunload', this.avisarSalida)
  },
  beforeUnmount () {
    window.removeEventListener('beforeunload', this.avisarSalida)
  },
  // Salir de la pantalla con precios sin grabar los perderia sin aviso.
  beforeRouteLeave (to, from, next) {
    if (!this.pendientes) return next()
    this.$q.dialog({
      title: 'Hay precios sin grabar',
      message: 'Cambiaste ' + this.pendientes + ' producto(s) y no grabaste. ¿Salir igual y perder esos cambios?',
      cancel: { flat: true, label: 'Quedarme', noCaps: true },
      ok: { color: 'negative', label: 'Salir sin grabar', noCaps: true, unelevated: true },
      persistent: true
    }).onOk(() => next()).onCancel(() => next(false))
  },
  methods: {
    cargar () {
      this.cargando = true
      this.$api.get('precios').then(res => {
        this.productos = res.data
        this.cambios = {}
      }).catch(err => {
        this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudieron cargar los productos' })
      }).finally(() => {
        this.cargando = false
      })
    },
    pedirRecarga () {
      if (!this.pendientes) return this.cargar()
      this.$q.dialog({
        title: 'Recargar',
        message: 'Se van a perder los cambios sin grabar de ' + this.pendientes + ' producto(s).',
        cancel: { flat: true, label: 'Cancelar', noCaps: true },
        ok: { color: 'negative', label: 'Recargar', noCaps: true, unelevated: true }
      }).onOk(this.cargar)
    },
    descartar () {
      this.cambios = {}
    },
    valor (row, campo) {
      const cambio = this.cambios[row.cod_prod]
      const v = cambio && campo in cambio ? cambio[campo] : row[campo]
      return v === null || v === undefined ? '' : v
    },
    cambiado (row, campo) {
      const cambio = this.cambios[row.cod_prod]
      return !!cambio && campo in cambio
    },
    // Si vuelve al valor original deja de contar como cambio.
    editar (row, campo, texto) {
      const limpio = String(texto).trim()
      let nuevo = limpio === '' ? null : Math.max(Number(limpio), 0)
      if (nuevo !== null && isNaN(nuevo)) return
      // Los 13 precios no admiten vacio (son NOT NULL); el de compra si.
      if (nuevo === null && campo !== 'precio_compra') nuevo = 0
      const original = row[campo] === undefined ? null : row[campo]
      const cambio = { ...(this.cambios[row.cod_prod] || {}) }
      if (nuevo === original || (nuevo !== null && original !== null && Math.abs(nuevo - original) < 0.0005)) {
        delete cambio[campo]
      } else {
        cambio[campo] = nuevo
      }
      if (Object.keys(cambio).length) {
        this.cambios = { ...this.cambios, [row.cod_prod]: cambio }
      } else {
        const resto = { ...this.cambios }
        delete resto[row.cod_prod]
        this.cambios = resto
      }
    },
    // Enter y las flechas se mueven como en la planilla; las flechas no
    // suben ni bajan el numero, que es lo que haria el input solo.
    mover (evento, fila, campo) {
      let destino = null
      if (evento.key === 'Enter' || evento.key === 'ArrowDown') destino = fila + 1
      if (evento.key === 'ArrowUp') destino = fila - 1
      if (destino === null) return
      evento.preventDefault()
      // Lo escrito se toma antes de saltar, sin esperar el blur.
      this.editar(this.visibles[fila], campo, evento.target.value)
      if (destino < 0 || destino >= this.visibles.length) return
      this.$nextTick(() => {
        const siguiente = this.$el.querySelector(`input[data-fila="${destino}"][data-campo="${campo}"]`)
        if (siguiente) {
          siguiente.focus()
          siguiente.scrollIntoView({ block: 'nearest' })
        }
      })
    },
    grabar () {
      const lista = Object.entries(this.cambios).map(([codProd, cambio]) => ({ cod_prod: codProd, ...cambio }))
      this.guardando = true
      this.$api.put('precios', { cambios: lista }).then(res => {
        // Lo grabado pasa a ser el valor original de cada fila.
        lista.forEach(({ cod_prod: codProd, ...cambio }) => {
          const fila = this.productos.find(p => p.cod_prod === codProd)
          if (fila) Object.assign(fila, cambio)
        })
        this.cambios = {}
        this.$q.notify({ type: 'positive', message: res.data.message })
      }).catch(err => {
        this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudieron grabar los precios' })
      }).finally(() => {
        this.guardando = false
      })
    },
    avisarSalida (evento) {
      if (!this.pendientes) return
      evento.preventDefault()
      evento.returnValue = ''
    }
  }
}
</script>

<style scoped>
.tabla-precios {
  height: calc(100vh - 140px);
}
.tabla-precios :deep(thead tr th) {
  position: sticky;
  top: 0;
  z-index: 2;
  background: #37474f;
  color: #fff;
  font-size: 11px;
  padding: 4px;
  white-space: nowrap;
}
.tabla-precios :deep(tbody td) {
  font-size: 11px;
  padding: 0 4px;
  height: 25px;
}
.tabla-precios :deep(tbody tr:nth-child(even)) {
  background: #f5f7f8;
}
.tabla-precios :deep(tbody tr.fila-cambiada) {
  background: #fff8e1;
}
.celda-producto {
  max-width: 220px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.tabla-precios :deep(td.celda-precio) {
  padding: 0 1px;
}
.input-precio {
  width: 54px;
  height: 21px;
  border: 1px solid #cfd8dc;
  border-radius: 2px;
  font-size: 11px;
  text-align: right;
  padding: 0 3px;
  background: #fff;
  -moz-appearance: textfield;
}
.input-precio::-webkit-outer-spin-button,
.input-precio::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.input-precio:focus {
  outline: 2px solid #1976d2;
  border-color: #1976d2;
}
.input-precio.compra {
  background: #e3f2fd;
}
.input-precio.cambiado {
  background: #ffeb3b;
  font-weight: bold;
}
</style>
