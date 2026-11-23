<template>
  <q-page class="q-pa-sm">
    <q-card flat bordered>
      <div class="row items-center q-gutter-xs q-pa-xs barra">
        <q-input dense outlined v-model="fecha" type="date" style="width:140px" />
        <q-btn dense unelevated label="Consultar" no-caps color="primary" icon="search" :loading="loading" @click="getAlmacenes" />
        <q-select v-model="codigoSelect" outlined dense clearable label="Código" :options="codigoOpciones" style="width:100px" @clear="codigoSelect = ''" />
        <q-input dense outlined clearable v-model="filter" placeholder="Buscar en todo" debounce="250" style="width:200px">
          <template #prepend><q-icon name="search" size="16px" /></template>
        </q-input>
        <q-btn v-if="hayFiltros" dense flat no-caps color="grey-8" icon="filter_alt_off" label="Limpiar filtros" @click="limpiarFiltros" />
        <span class="text-caption text-grey-8 q-ml-sm">{{ almacenes.length }} de {{ almacenesAll.length }} filas</span>
        <q-space />
        <template v-if="$store.getters['login/can']('almacenRiesgo')">
          <q-btn dense unelevated label="Todo a riesgo" no-caps color="red-9" icon="move_up" :loading="exportandoRiesgo" @click="todoARiesgo">
            <q-tooltip>Vacía Productos en riesgo y lleva todo lo contado en este inventario</q-tooltip>
          </q-btn>
          <q-btn dense unelevated label="Productos en riesgo" no-caps color="deep-orange" icon="warning" :to="{ path: '/almacenRiesgo', query: { fecha } }" />
        </template>
        <q-btn dense unelevated label="Porcentaje" no-caps color="indigo" icon="visibility" :loading="loading" @click="verPorcentajeClick()" />
        <q-btn dense unelevated label="Exportar Excel" no-caps color="orange" icon="cloud_download" :loading="loading" @click="exportarExcel">
          <q-tooltip>Exporta las filas filtradas</q-tooltip>
        </q-btn>
        <q-btn dense unelevated label="Cargar Excel" no-caps color="green" icon="cloud_upload" :loading="loading" @click="dialogFile = true" />
        <q-btn dense unelevated label="Eliminar todos" no-caps color="red" icon="delete_sweep" :loading="loading" @click="eliminarTodos" />
      </div>
      <q-table class="tabla-excel" flat dense square separator="cell" virtual-scroll :virtual-scroll-item-size="22" :virtual-scroll-sticky-size-start="48"
               :rows-per-page-options="[0]" :pagination="{ rowsPerPage: 0 }" hide-bottom :loading="loading" :columns="columns" :rows="almacenes" row-key="id"
               no-data-label="No hay datos" no-results-label="No hay resultados">
        <template #header="props">
          <q-tr :props="props">
            <q-th v-for="col in props.cols" :key="col.name" :props="props">{{ col.label }}</q-th>
          </q-tr>
          <q-tr class="fila-filtros">
            <q-th v-for="col in props.cols" :key="col.name">
              <select v-if="col.filtro === 'select'" v-model="filtros[col.name]" class="filtro">
                <option value="">Todos</option>
                <option v-for="op in opcionesFiltro(col.name)" :key="op" :value="op">{{ op }}</option>
              </select>
              <input v-else-if="col.filtro" v-model="filtros[col.name]" class="filtro" :placeholder="col.filtro === 'num' ? '>0, <5, =0' : 'filtrar'" />
            </q-th>
          </q-tr>
        </template>
        <template #body="props">
          <q-tr :props="props">
            <q-td key="opciones" :props="props" class="text-no-wrap">
              <span class="text-grey-7 num-fila">{{ props.rowIndex + 1 }}</span>
              <q-btn flat dense round size="xs" icon="edit" color="primary" @click="clickEdit(props.row)"><q-tooltip>Editar</q-tooltip></q-btn>
              <q-btn flat dense round size="xs" icon="list_alt" color="teal" @click="clickDatos(props.row)"><q-tooltip>Ver datos (conteos)</q-tooltip></q-btn>
              <q-btn flat dense round size="xs" icon="delete" color="negative" @click="eliminar(props.row.id)"><q-tooltip>Eliminar</q-tooltip></q-btn>
            </q-td>
            <q-td key="codigo" :props="props"><span class="codigo" :style="{ background: getColor(props.row.codigo) }">{{ props.row.codigo }}</span></q-td>
            <q-td key="codigo_producto" :props="props">{{ props.row.codigo_producto }}</q-td>
            <q-td key="producto" :props="props" class="celda-producto" :title="props.row.producto">{{ props.row.producto }}</q-td>
            <q-td key="unidad" :props="props">{{ props.row.unidad }}</q-td>
            <q-td key="grupo" :props="props" class="celda-grupo" :title="props.row.grupo">{{ props.row.grupo }}</q-td>
            <q-td key="saldo" :props="props">{{ num(props.row.saldo) }}</q-td>
            <q-td key="appCantidad" :props="props" :class="Number(props.row.appCantidad) === Number(props.row.saldo) ? 'text-positive' : 'text-negative'">{{ num(props.row.appCantidad) }}</q-td>
            <q-td key="diferencia" :props="props" :class="diferencia(props.row) === 0 ? 'text-grey-6' : diferencia(props.row) > 0 ? 'bg-red-1 text-negative text-weight-bold' : 'bg-indigo-1 text-indigo text-weight-bold'">{{ num(diferencia(props.row)) }}</q-td>
            <q-td key="se_descargo" :props="props"><span :class="props.row.se_descargo === 'EXPORTADO' ? 'text-positive' : props.row.se_descargo === 'IMPORTADO' ? 'text-orange-9' : 'text-grey-6'" class="text-weight-medium">{{ props.row.se_descargo || '—' }}</span></q-td>
          </q-tr>
        </template>
      </q-table>
    </q-card>
    <q-dialog v-model="dialogFile" >
      <q-card>
        <q-card-section class="q-pb-none row">
          <div class="text-h6">Cargar Excel</div>
          <q-space />
          <q-btn icon="close" flat no-caps round @click="dialogFile = false" />
        </q-card-section>
        <q-card-section class="q-pa-md">
          <div class="row">
            <div class="col-12 q-pa-sm">
              <q-file dense outlined v-model="file" label="Seleccionar archivo" @input="fileChange" accept=".xls,.xlsx">
                <template v-slot:prepend>
                  <q-icon name="attach_file" />
                </template>
              </q-file>
            </div>
            <div class="col-12 q-pa-sm">
              <q-input dense outlined v-model="fecha" label="Fecha" type="date" />
            </div>
            <div class="col-12 q-pa-sm">
              <q-btn no-caps color="primary" class="full-width" label="Subir" :loading="loading" @click="cargarExcel" icon="cloud_upload" />
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
    <q-dialog v-model="dialogAlmacen">
      <q-card style="width: 860px; max-width: 96vw">
        <q-card-section class="row items-center q-py-xs">
          <div class="text-subtitle1 text-weight-bold">Editar producto</div>
          <q-space />
          <q-btn icon="close" flat dense round v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section class="q-py-sm">
          <div class="row q-col-gutter-xs">
            <q-input class="col-2" dense outlined v-model="almacen.codigo" label="Código" />
            <q-input class="col-3" dense outlined v-model="almacen.codigo_producto" label="Código producto" />
            <q-input class="col-7" dense outlined v-model="almacen.producto" label="Producto" />
            <q-input class="col-2" dense outlined v-model="almacen.unidad" label="Unidad" />
            <q-input class="col-2" dense outlined v-model.number="almacen.saldo" type="number" step="0.01" label="Saldo" />
            <q-input class="col-4" dense outlined v-model="almacen.grupo" label="Grupo" />
            <q-input class="col-4" dense outlined v-model="almacen.fecha_registro" type="date" stack-label label="Fecha inventario" />
            <q-input class="col-6" dense outlined v-model="almacen.registro" type="date" stack-label label="Registro" />
            <q-input class="col-6" dense outlined v-model="almacen.vencimiento" type="date" stack-label label="Vencimiento" />
          </div>
        </q-card-section>
        <q-separator />
        <q-card-section class="q-py-sm">
          <div class="row items-center q-mb-xs">
            <div class="text-weight-bold">Conteos (10)</div>
            <div class="text-caption text-grey-7 q-ml-sm">Llena cantidad y vencimiento; las filas sin cantidad no se guardan.</div>
            <q-space />
            <div class="text-weight-bold">Total: {{ totalConteos.toFixed(2) }} {{ almacen.unidad }}</div>
          </div>
          <q-markup-table flat bordered dense separator="cell" class="tabla-conteos">
            <thead>
              <tr><th>#</th><th>Cantidad</th><th>Vencimiento</th><th>Lote</th><th>Comentario</th><th>Contó</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in registros" :key="i">
                <td class="text-grey-7 text-center">{{ i + 1 }}</td>
                <td style="width: 110px"><input v-model="r.cantidad" type="number" min="0" step="0.01" class="celda-input text-right" /></td>
                <td style="width: 140px"><input v-model="r.fecha_vencimiento" type="date" class="celda-input" /></td>
                <td style="width: 120px"><input v-model="r.lote" class="celda-input" /></td>
                <td><input v-model="r.comentario" class="celda-input" /></td>
                <td class="text-caption text-grey-7 text-no-wrap">{{ r.usuario }}</td>
                <td style="width: 28px"><q-btn v-if="filaLlena(r)" flat dense round size="xs" icon="clear" color="grey-7" @click="limpiarFila(r)"><q-tooltip>Vaciar fila</q-tooltip></q-btn></td>
              </tr>
            </tbody>
          </q-markup-table>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
          <q-btn unelevated no-caps color="orange" icon="save" label="Guardar" :loading="loading" @click="modficarAlmacen" />
        </q-card-actions>
      </q-card>
    </q-dialog>
    <q-dialog v-model="dialogPorcentaje">
      <q-card style="min-width: 750px">
        <q-card-section class="q-pb-none row">
          <div class="text-h6">Porcentaje de carga</div>
          <q-space />
          <q-btn icon="close" flat no-caps round v-close-popup />
        </q-card-section>
        <q-card-section class="q-pt-none">
          <div class="row">
            <div class="col-12">
              <q-markup-table>
                <thead>
                <tr>
                  <th>#</th>
                  <th>Codigo</th>
                  <th>Total</th>
                  <th>Cantidad</th>
                  <th>Porcentaje</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(porcentaje,i) in porcentajes" :key="porcentaje.id">
                  <td>{{ i+1 }}</td>
                  <td>{{ porcentaje.codigo }}</td>
                  <td>{{ porcentaje.total }}</td>
                  <td>{{ porcentaje.realizado }}</td>
                  <td>
                    <q-linear-progress size="25px" :value="parseFloat(porcentaje.porcentaje/100)" color="primary" track-color="grey-3" rounded>
                      <div class="absolute-full flex flex-center">
                        <q-badge color="white" text-color="accent" :label="porcentaje.porcentaje + '%'" />
                      </div>
                    </q-linear-progress>
                  </td>
                </tr>
                </tbody>
              </q-markup-table>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>
<script>
import {date} from "quasar";
import xlsx from "json-as-xlsx"

export default {
  name: "AlmacenPage",
  data() {
    return {
      codigo: '4',
      codigos: [
        {label: 'A', value: '1', color: 'red'},
        {label: 'B', value: '2', color: 'green'},
        {label: 'C', value: '3', color: 'blue'},
        {label: 'D', value: '4', color: 'yellow'},
        {label: 'E', value: '5', color: 'purple'},
        {label: 'F', value: '6', color: 'orange'},
        {label: 'G', value: '7', color: 'pink'},
        {label: 'H', value: '8', color: 'brown'},
        {label: 'I', value: '9', color: 'grey'},
        {label: 'J', value: '10', color: 'black'},
      ],
      fecha: date.formatDate(new Date(), 'YYYY-MM-DD'),
      file: null,
      almacenesAll: [],
      codigoSelect: '',
      exportandoRiesgo: false,
      dialogPorcentaje: false,
      porcentajes: [],
      porcentaje: 0,
      almacen: {},
      loading: false,
      filter: '',
      dialogFile: false,
      dialogAlmacen: false,
      registros: [],
      columns: [
        {name: 'opciones', label: '#', align: 'left', field: 'id'},
        {name: 'codigo', label: 'Cód', align: 'center', field: 'codigo', sortable: true, filtro: 'select'},
        {name: 'codigo_producto', label: 'Cód. prod', align: 'left', field: 'codigo_producto', sortable: true, filtro: 'texto'},
        {name: 'producto', label: 'Producto', align: 'left', field: 'producto', sortable: true, filtro: 'texto'},
        {name: 'unidad', label: 'Und', align: 'center', field: 'unidad', sortable: true, filtro: 'select'},
        {name: 'grupo', label: 'Grupo', align: 'left', field: 'grupo', sortable: true, filtro: 'select'},
        {name: 'saldo', label: 'Saldo', align: 'right', field: row => Number(row.saldo || 0), sortable: true, filtro: 'num'},
        {name: 'appCantidad', label: 'App cant.', align: 'right', field: row => Number(row.appCantidad || 0), sortable: true, filtro: 'num'},
        {name: 'diferencia', label: 'Diferencia', align: 'right', field: row => Number(row.saldo || 0) - Number(row.appCantidad || 0), sortable: true, filtro: 'num'},
        {name: 'se_descargo', label: 'Estado', align: 'left', field: 'se_descargo', sortable: true, filtro: 'select'},
      ],
      filtros: {codigo_producto: '', producto: '', unidad: '', grupo: '', codigo: '', saldo: '', appCantidad: '', diferencia: '', se_descargo: ''},
    }
  },
  computed: {
    totalConteos() {
      return this.registros.reduce((s, r) => s + (Number(r.cantidad) || 0), 0)
    },
    // Filas visibles: código de arriba + buscador general + filtro de cada columna (como en Excel).
    almacenes() {
      const texto = (this.filter || '').toLowerCase().trim()
      const activos = this.columns.filter(c => c.filtro && String(this.filtros[c.name] || '').trim() !== '')
      return this.almacenesAll.filter(row => {
        if (this.codigoSelect && row.codigo !== this.codigoSelect) return false
        if (texto && ![row.codigo, row.codigo_producto, row.producto, row.grupo, row.unidad, row.se_descargo]
          .some(v => String(v || '').toLowerCase().includes(texto))) return false
        return activos.every(col => {
          const filtro = String(this.filtros[col.name]).trim()
          const valor = typeof col.field === 'function' ? col.field(row) : row[col.field]
          if (col.filtro === 'select') return String(valor || '') === filtro
          if (col.filtro === 'num') return this.cumpleNumero(Number(valor || 0), filtro)
          return String(valor || '').toLowerCase().includes(filtro.toLowerCase())
        })
      })
    },
    codigoOpciones() {
      return [...new Set(this.almacenesAll.map(r => r.codigo).filter(Boolean))].sort()
    },
    hayFiltros() {
      return !!(this.codigoSelect || this.filter || Object.values(this.filtros).some(v => String(v || '').trim() !== ''))
    },
  },
  mounted() {
    this.getAlmacenes();
  },
  methods: {
    // Filtro numérico estilo Excel: "5" (igual), ">0", "<=10", "<>0", "!=0".
    cumpleNumero(valor, filtro) {
      const m = filtro.replace(',', '.').match(/^(>=|<=|<>|!=|>|<|=)?\s*(-?\d+(\.\d+)?)$/)
      if (!m) return true
      const n = Number(m[2]), v = Math.round(valor * 100) / 100
      switch (m[1]) {
        case '>': return v > n
        case '<': return v < n
        case '>=': return v >= n
        case '<=': return v <= n
        case '<>': case '!=': return v !== n
        default: return v === n
      }
    },
    opcionesFiltro(nombre) {
      if (nombre === 'codigo') return this.codigoOpciones
      return [...new Set(this.almacenesAll.map(r => r[nombre]).filter(v => v !== null && v !== undefined && v !== ''))].sort()
    },
    limpiarFiltros() {
      this.codigoSelect = ''
      this.filter = ''
      Object.keys(this.filtros).forEach(k => { this.filtros[k] = '' })
    },
    num(v) {
      return Number(v || 0).toFixed(2)
    },
    diferencia(row) {
      return Math.round((Number(row.saldo || 0) - Number(row.appCantidad || 0)) * 100) / 100
    },
    verPorcentajeClick() {
      this.loading = true;
      this.$api.get('porcentaje', {
        params: {
          fecha: this.fecha,
        }
      }).then(res => {
        this.porcentajes = res.data;
        this.dialogPorcentaje = true;
      }).catch(err => {
        console.error(err);
      }).finally(() => {
        this.loading = false;
      });
    },
    exportarExcel() {
      let data = [
        {
          sheet: "Adults",
          columns: [
            { label: "Codigo", value: "codigo" },
            { label: "Codigo Producto", value: "codigo_producto" },
            { label: "Producto", value: "producto" },
            { label: "Saldo", value: "saldo" },
            { label: "Grupo", value: "grupo" },
            { label: "Cantidad", value: "cantidad" },
            { label: "App Cantidad", value: "appCantidad" },
            { label: "Diferencia", value: (row) =>  row.appCantidad - row.saldo  },
            { label: "Se Descargo", value: "se_descargo" },
            { label: "Cantidad", value: "cantidad" },
            { label: "Cantidad 1", value: "cantidad1" },
            { label: "Fecha Vencimi 1", value: "fechaVencimiento1" },
            { label: "Cantidad 2", value: "cantidad2" },
            { label: "Fecha Vencimi 2", value: "fechaVencimiento2" },
            { label: "Cantidad 3", value: "cantidad3" },
            { label: "Fecha Vencimi 3", value: "fechaVencimiento3" },
            { label: "Cantidad 4", value: "cantidad4" },
            { label: "Fecha Vencimi 4", value: "fechaVencimiento4" },
            { label: "Cantidad 5", value: "cantidad5" },
            { label: "Fecha Vencimi 5", value: "fechaVencimiento5" },
            { label: "Cantidad 6", value: "cantidad6" },
            { label: "Fecha Vencimi 6", value: "fechaVencimiento6" },
            { label: "Cantidad 7", value: "cantidad7" },
            { label: "Fecha Vencimi 7", value: "fechaVencimiento7" },
            { label: "Cantidad 8", value: "cantidad8" },
            { label: "Fecha Vencimi 8", value: "fechaVencimiento8" },
            { label: "Cantidad 9", value: "cantidad9" },
            { label: "Fecha Vencimi 9", value: "fechaVencimiento9" },
            { label: "Cantidad 10", value: "cantidad10" },
            { label: "Fecha Vencimi 10", value: "fechaVencimiento10" },
            { label: "Comentario 1", value: "comentario1" },
            { label: "Comentario 2", value: "comentario2" },
            { label: "Comentario 3", value: "comentario3" },
            { label: "Comentario 4", value: "comentario4" },
            { label: "Comentario 5", value: "comentario5" },
            { label: "Comentario 6", value: "comentario6" },
            { label: "Comentario 7", value: "comentario7" },
            { label: "Comentario 8", value: "comentario8" },
            { label: "Comentario 9", value: "comentario9" },
            { label: "Comentario 10", value: "comentario10" },
          ],
          content: this.almacenes
        }
      ]

      let settings = {
        fileName: "Almacenes", // The name of the file
        // extraLength: 3, // A bigger number means that columns will be wider
        // writeMode: "writeFile", // The available parameters are 'WriteFile' and 'write'. This setting is optional. Useful in such cases https://docs.sheetjs.com/docs/solutions/output#example-remote-file
        // writeOptions: {}, // Style options from https://docs.sheetjs.com/docs/api/write-options
        // RTL: true, // Display the columns from right-to-left (the default value is false)
      }

      xlsx(data, settings) // Will download the excel file
    },
    getColor(codigo) {
      let color = this.codigos.find(c => c.label === codigo);
      return color ? color.color : 'grey';
    },
    fileChange(event) {
      this.file = event.target.files[0];
    },
    // Editar y "Ver datos" abren el mismo diálogo: datos del producto + los 10 conteos.
    clickEdit(almacen) {
      const fecha = v => (v ? String(v).slice(0, 10) : '')
      this.almacen = {...almacen, registro: fecha(almacen.registro), vencimiento: fecha(almacen.vencimiento), fecha_registro: fecha(almacen.fecha_registro)};
      this.registros = this.filasVacias([]);
      this.dialogAlmacen = true;
      this.$api.get('registros', {params: {almacen_id: almacen.id}}).then(res => {
        this.registros = this.filasVacias(res.data);
      }).catch(err => {
        console.error(err);
      });
    },
    clickDatos(almacen) {
      this.clickEdit(almacen);
    },
    filasVacias(existentes) {
      const filas = existentes.slice(0, 10).map(r => ({
        id: r.id,
        cantidad: r.cantidad,
        fecha_vencimiento: r.fecha_vencimiento ? String(r.fecha_vencimiento).slice(0, 10) : '',
        lote: r.lote && r.lote !== 'null' ? r.lote : '',
        comentario: r.comentario && r.comentario !== 'null' ? r.comentario : '',
        usuario: r.user ? String(r.user.Nombre1 || '').trim() : '',
      }));
      while (filas.length < 10) filas.push({id: null, cantidad: '', fecha_vencimiento: '', lote: '', comentario: '', usuario: ''});
      return filas;
    },
    filaLlena(r) {
      return r.cantidad !== '' && r.cantidad !== null && r.cantidad !== undefined;
    },
    limpiarFila(r) {
      Object.assign(r, {cantidad: '', fecha_vencimiento: '', lote: '', comentario: ''});
    },
    avisoError(err, porDefecto) {
      const d = err.response?.data || {};
      const primero = d.errors ? Object.values(d.errors)[0]?.[0] : null;
      this.$q.notify({color: 'negative', position: 'top', icon: 'report_problem', message: primero || d.message || porDefecto});
    },
    modficarAlmacen() {
      const llenas = this.registros.filter(this.filaLlena);
      const sinCantidad = this.registros.find(r => !this.filaLlena(r) && (r.fecha_vencimiento || r.lote || r.comentario));
      if (sinCantidad) {
        return this.$q.notify({color: 'negative', position: 'top', icon: 'report_problem', message: `Falta la cantidad en el conteo ${this.registros.indexOf(sinCantidad) + 1}`});
      }
      this.loading = true;
      this.$api.put('almacenes/' + this.almacen.id, this.almacen)
        .then(() => this.$api.post('almacenes/' + this.almacen.id + '/registros', {
          registros: llenas.map(r => ({id: r.id, cantidad: r.cantidad, fecha_vencimiento: r.fecha_vencimiento || null, lote: r.lote, comentario: r.comentario})),
        }))
        .then(() => {
          this.$q.notify({color: 'positive', position: 'top', icon: 'check', message: `${this.almacen.producto} guardado`});
          this.dialogAlmacen = false;
          this.getAlmacenes();
        })
        .catch(err => this.avisoError(err, 'No se pudo guardar'))
        .finally(() => {
          this.loading = false;
        });
    },
    eliminar(id) {
      this.$q.dialog({
        title: 'Eliminar',
        message: '¿Está seguro de eliminar el registro?',
        cancel: true,
        persistent: true,
      }).onOk(() => {
        this.loading = true;
        this.$api.delete('almacenes/' + id).then(res => {
          this.getAlmacenes();
        }).catch(err => {
          console.error(err);
        }).finally(() => {
          // this.loading = false;
        });
      });
    },
    // Lleva todo lo contado del inventario de esta fecha a Productos en riesgo:
    // la lista anterior se quita (queda en el historial) y queda solo este inventario.
    todoARiesgo() {
      this.$q.dialog({
        title: 'Todo a productos en riesgo',
        message: `Se quitará todo lo que hay en Productos en riesgo (queda en el historial) y se llevará todo lo contado en el inventario del ${this.fecha}, venza cuando venza. ¿Continuar?`,
        cancel: true,
        persistent: true,
      }).onOk(() => {
        this.exportandoRiesgo = true;
        this.$api.post('productosRiesgo/generar', {fecha: this.fecha, todo: true}).then(res => {
          this.$q.notify({color: 'positive', position: 'top', icon: 'check', message: `${res.data.agregados} conteo(s) llevados a riesgo; ${res.data.quitados} anteriores quitados`});
          this.$router.push('/almacenRiesgo');
        }).catch(err => this.avisoError(err, 'No se pudo llevar a riesgo')).finally(() => {
          this.exportandoRiesgo = false;
        });
      });
    },
    eliminarTodos() {
      this.$q.dialog({
        title: 'Eliminar todos',
        message: `¿Está seguro de eliminar TODOS los registros del ${this.fecha}? También se borrarán los conteos de la app. No se puede deshacer.`,
        cancel: true,
        persistent: true,
      }).onOk(() => {
        this.loading = true;
        this.$api.post('almacenesEliminarTodos', { fecha: this.fecha }).then(res => {
          this.$q.notify({
            color: 'positive',
            position: 'top',
            message: `Se eliminaron ${res.data.eliminados} registros`,
            icon: 'check',
          });
          this.getAlmacenes();
        }).catch(err => {
          console.error(err);
          this.loading = false;
        });
      });
    },
    cargarExcel() {
      if (!this.file) return this.$q.notify({
        color: 'negative',
        position: 'top',
        message: 'Seleccione un archivo',
        icon: 'report_problem',
      });
      this.$q.dialog({
        title: 'Cargar Excel',
        message: '¿Está seguro de cargar el archivo? remplazará los datos existentes',
        cancel: true,
        persistent: true,
      }).onOk(() => {
        this.loading = true;
        let formData = new FormData();
        formData.append('file', this.file);
        formData.append('fecha', this.fecha);
        formData.append('codigo', this.codigo);
        this.$api.post('cargarExcel', formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        }).then(res => {
          this.dialogFile = false;
          this.file = null;
          this.getAlmacenes();
        }).catch(err => {
          this.$q.notify({
            color: 'negative',
            position: 'top',
            message: 'El archivo no es válido',
            icon: 'report_problem',
          });
        }).finally(() => {
          this.loading = false;
        });
      });
    },
    getAlmacenes() {
      this.loading = true;
      this.$api.get('almacenes', {
        params: {
          fecha: this.fecha,
        }
      }).then(res => {
        this.almacenesAll = res.data.almacenes;
        this.porcentaje = res.data.porcentaje;
      }).catch(err => {
        console.error(err);
      }).finally(() => {
        this.loading = false;
      });
    },
  },
}
</script>
<style scoped>
.barra :deep(.q-field--dense .q-field__control), .barra :deep(.q-field--dense .q-field__marginal) { height: 30px; min-height: 30px }
.barra :deep(.q-btn) { font-size: 12px }
/* Tabla tipo Excel: celdas con borde, letra chica, encabezado y filtros fijos al bajar. */
.tabla-excel { height: calc(100vh - 110px) }
.tabla-excel :deep(td), .tabla-excel :deep(th) { padding: 1px 5px; height: 22px; font-size: 12px; line-height: 1.2; white-space: nowrap }
.tabla-excel :deep(thead tr th) { position: sticky; z-index: 2; background: #eef1f6; font-weight: 600 }
.tabla-excel :deep(thead tr:first-child th) { top: 0 }
.tabla-excel :deep(thead tr.fila-filtros th) { top: 22px; background: #f7f8fa; padding: 1px 2px }
.tabla-excel :deep(tbody tr:nth-child(even)) { background: #fafbfc }
.tabla-excel :deep(tbody tr:hover) { background: #fff8e1 }
.filtro { width: 100%; min-width: 50px; height: 20px; border: 1px solid #cfd4dc; border-radius: 2px; font-size: 11px; padding: 0 3px; background: #fff; font-weight: normal }
.celda-producto { max-width: 360px; overflow: hidden; text-overflow: ellipsis }
.celda-grupo { max-width: 160px; overflow: hidden; text-overflow: ellipsis }
.codigo { display: inline-block; min-width: 20px; padding: 0 4px; border-radius: 3px; color: #fff; font-weight: 600; text-align: center; text-shadow: 0 0 2px rgba(0,0,0,.4) }
.num-fila { display: inline-block; min-width: 26px; font-size: 11px }
</style>
<style scoped>
.tabla-conteos td, .tabla-conteos th { padding: 1px 3px !important; height: 26px !important; font-size: 12px }
.celda-input { width: 100%; height: 24px; border: 1px solid transparent; border-radius: 2px; padding: 0 4px; font-size: 12px; background: transparent; outline: none }
.celda-input:hover { border-color: #cfd4dc }
.celda-input:focus { border-color: #1976d2; background: #fff }
</style>
