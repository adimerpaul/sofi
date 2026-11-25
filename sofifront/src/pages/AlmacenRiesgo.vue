<template>
  <q-page class="q-pa-sm">
    <q-card flat bordered>
      <q-card-section class="row items-center q-col-gutter-sm q-pb-sm">
        <div class="col-12 col-md">
          <div class="text-subtitle1 text-weight-bold">Productos en riesgo</div>
          <div class="text-caption text-grey-7">
            <template v-if="generacion">
              Inventario del <b>{{ formatoFecha(generacion.fecha_inventario) }}</b>,
              <template v-if="generacion.dias >= 9999">todo lo contado</template><template v-else>vence en {{ generacion.dias ?? 20 }} días o menos</template>
              · llenado por {{ generacion.generado_por }} {{ formatoFechaHora(generacion.generado_at) }}
            </template>
            <template v-else>Todavía no se llenó la lista</template>
            · no modifica el inventario
          </div>
        </div>
        <div class="col-12 col-md-auto row q-gutter-xs items-center">
          <q-input dense outlined v-model="fecha" type="date" label="Inventario del" stack-label style="width:160px" />
          <q-input dense outlined v-model.number="dias" type="number" min="0" label="Días" style="width:80px" />
          <q-btn dense unelevated no-caps color="orange-9" icon="playlist_add" label="Llenar con inventario" :loading="generando" @click="generar" />
          <q-btn dense unelevated no-caps color="primary" icon="sync" label="Actualizar datos" :disable="!generacion" :loading="actualizando" @click="actualizar">
            <q-tooltip>Vuelve a leer el inventario de la lista actual: agrega lo nuevo y actualiza lo ya llenado</q-tooltip>
          </q-btn>
          <q-btn dense unelevated no-caps color="teal-8" icon="add_circle_outline" label="Agregar producto" :disable="!generacion" @click="abrirAgregar">
            <q-tooltip>Agrega a mano un producto a la lista actual (no toca el inventario)</q-tooltip>
          </q-btn>
          <q-input dense outlined v-model="deposito" label="Depósito" style="width:110px" />
          <q-btn dense unelevated no-caps color="green-8" icon="grid_on" label="Excel" :disable="!generacion" :loading="exportando" @click="excel">
            <q-tooltip>Hoja de trabajo: Depósito, fecha de conteo, semana y CHECK por colores</q-tooltip>
          </q-btn>
          <q-btn dense flat no-caps icon="arrow_back" label="Almacén" to="/almacen" />
        </div>
      </q-card-section>
      <q-separator />
      <div class="row items-center q-px-sm">
        <q-tabs v-model="tab" dense no-caps align="left" active-color="primary" indicator-color="primary" class="text-grey-8" @update:model-value="cargar()">
          <q-tab name="ACTIVO" icon="warning" :label="`Activos (${totales.ACTIVO})`" />
          <q-tab name="QUITADO" icon="history" :label="`Quitados (${totales.QUITADO})`" />
          <q-tab name="TODOS" icon="list" :label="`Todos (${totales.ACTIVO + totales.QUITADO})`" />
        </q-tabs>
        <q-space />
        <q-input v-model="buscar" dense outlined clearable debounce="400" placeholder="Buscar producto, lote, usuario" style="width:260px" @update:model-value="cargar()">
          <template #prepend><q-icon name="search" /></template>
        </q-input>
        <q-btn dense flat round icon="refresh" :loading="loading" @click="cargar(false)" />
      </div>
      <q-table flat dense square class="tabla-riesgo" :rows="rows" :columns="columnasVisibles" row-key="id" :loading="loading"
               v-model:pagination="pagination" :rows-per-page-options="[50, 100, 200]"
               no-data-label="Sin productos en riesgo" @request="onRequest">
        <template #body="p">
          <q-tr :props="p" :class="p.row.estado === 'QUITADO' ? 'fila-quitada' : ''">
            <q-td key="foto" :props="p" class="q-pa-none">
              <div class="foto">
                <img v-if="p.row.imagen" :src="urlImagen(p.row.imagen)" loading="lazy" alt="" />
                <q-icon v-else name="inventory_2" size="18px" color="grey-4" />
              </div>
              <q-tooltip v-if="p.row.imagen" anchor="center right" self="center left" class="bg-white shadow-4 q-pa-xs">
                <img :src="urlImagen(p.row.imagen)" style="width:200px;height:200px;object-fit:contain" alt="" />
              </q-tooltip>
            </q-td>
            <q-td key="producto" :props="p" class="celda-producto">
              <div class="ellipsis text-weight-medium">{{ p.row.producto }}</div>
              <div class="ellipsis text-grey-7 chico">
                {{ p.row.codigo_producto }}<span v-if="p.row.lote"> · L {{ p.row.lote }}</span><span v-if="p.row.grupo"> · {{ p.row.grupo }}</span><span v-if="p.row.comentario"> · Conteo: {{ p.row.comentario }}</span>
                <q-badge v-if="!p.row.registro_almacen_id" class="q-ml-xs" outline color="teal-8" label="A MANO" />
              </div>
              <q-tooltip v-if="p.row.contado_por" :delay="600">Inventario {{ formatoFecha(p.row.fecha_inventario) }} {{ p.row.codigo }} · contó {{ p.row.contado_por }}</q-tooltip>
            </q-td>
            <q-td key="cantidad" :props="p" :class="editable(p.row) ? 'editable' : ''">
              {{ Number(p.row.cantidad || 0).toFixed(2) }} <span class="text-grey-6 chico">{{ p.row.unidad }}</span>
              <q-icon v-if="p.row.cantidad_original !== null && p.row.cantidad_original !== undefined" name="edit" size="11px" color="orange-9">
                <q-tooltip>Conteo: {{ Number(p.row.cantidad_original).toFixed(2) }}</q-tooltip>
              </q-icon>
              <q-popup-edit v-if="editable(p.row)" :model-value="p.row.cantidad" v-slot="scope" buttons label-set="Guardar" label-cancel="Cancelar"
                            :validate="v => v !== '' && v !== null && Number(v) >= 0" @save="v => guardar(p.row, { cantidad: v })">
                <q-input v-model.number="scope.value" type="number" min="0" step="0.01" dense autofocus :suffix="p.row.unidad" label="Cantidad" @keyup.enter="scope.set" />
              </q-popup-edit>
            </q-td>
            <q-td key="vence" :props="p" :class="editable(p.row) ? 'editable' : ''">
              {{ formatoFecha(p.row.fecha_vencimiento) }}
              <q-badge class="q-ml-xs" :color="colorDias(p.row)" :label="textoDias(p.row)" />
              <q-icon v-if="p.row.fecha_vencimiento_original" name="edit" size="11px" color="orange-9">
                <q-tooltip>Conteo: {{ formatoFecha(p.row.fecha_vencimiento_original) }}</q-tooltip>
              </q-icon>
              <q-popup-edit v-if="editable(p.row)" :model-value="p.row.fecha_vencimiento ? String(p.row.fecha_vencimiento).slice(0, 10).replace(/-/g, '/') : null" v-slot="scope"
                            @save="v => guardar(p.row, { fecha_vencimiento: String(v).replace(/\//g, '-') })">
                <q-date v-model="scope.value" minimal @update:model-value="scope.set" />
              </q-popup-edit>
            </q-td>
            <q-td key="observacion" :props="p" class="chico editable celda-obs">
              <div v-if="p.row.observacion" class="obs-texto">{{ p.row.observacion }}</div>
              <span v-else class="text-grey-5">Agregar comentario…</span>
              <q-popup-edit :model-value="p.row.observacion || ''" v-slot="scope" buttons label-set="Guardar" label-cancel="Cancelar"
                            @save="v => guardar(p.row, { observacion: v })">
                <q-input v-model="scope.value" type="textarea" autogrow dense autofocus maxlength="500" counter label="Comentario" style="min-width:280px" />
              </q-popup-edit>
            </q-td>
            <q-td key="agregado" :props="p" class="chico">
              <span class="text-weight-medium">{{ p.row.agregado_por_nombre }}</span> <span class="text-grey-7">{{ formatoFechaHora(p.row.agregado_at) }}</span>
              <div v-if="p.row.editado_at" class="text-orange-9">Editó {{ p.row.editado_por_nombre }} {{ formatoFechaHora(p.row.editado_at) }}</div>
            </q-td>
            <q-td key="quitado" :props="p" class="chico">
              <template v-if="p.row.estado === 'QUITADO'">
                <span class="text-weight-medium">{{ p.row.quitado_por_nombre }}</span> <span class="text-grey-7">{{ formatoFechaHora(p.row.quitado_at) }}</span>
                <div v-if="p.row.motivo_quitado" class="ellipsis text-grey-8" style="max-width:220px">{{ p.row.motivo_quitado }}<q-tooltip>{{ p.row.motivo_quitado }}</q-tooltip></div>
              </template>
              <q-badge v-else color="orange-9" label="ACTIVO" />
            </q-td>
            <q-td key="acciones" :props="p" class="no-wrap">
              <q-btn dense flat round size="sm" color="primary" icon="point_of_sale" @click="verVentas(p.row)"><q-tooltip>Ventas desde el inventario</q-tooltip></q-btn>
              <q-btn v-if="p.row.estado === 'ACTIVO'" dense flat round size="sm" color="negative" icon="remove_circle_outline" :loading="p.row._guardando" @click="quitar(p.row)"><q-tooltip>Quitar</q-tooltip></q-btn>
              <q-btn v-else dense flat round size="sm" color="orange-9" icon="replay" :loading="p.row._guardando" @click="reagregar(p.row)"><q-tooltip>Volver a agregar</q-tooltip></q-btn>
            </q-td>
          </q-tr>
        </template>
      </q-table>
    </q-card>

    <!-- Agregar producto a mano -->
    <q-dialog v-model="agregar.abierto" persistent>
      <q-card style="width:460px;max-width:95vw">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-subtitle1 text-weight-bold">Agregar producto en riesgo</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>
        <q-form @submit="guardarNuevo">
          <q-card-section class="q-gutter-sm">
            <q-select v-model="agregar.producto" :options="agregar.opciones" option-label="producto" dense outlined use-input
                      input-debounce="300" label="Producto *" :loading="agregar.buscando" @filter="buscarCatalogo"
                      :rules="[v => !!v || 'Elija el producto']" hide-bottom-space>
              <template #option="scope">
                <q-item v-bind="scope.itemProps">
                  <q-item-section>
                    <q-item-label>{{ scope.opt.producto }}</q-item-label>
                    <q-item-label caption>{{ scope.opt.cod_prod }}<span v-if="scope.opt.unidad"> · {{ scope.opt.unidad }}</span><span v-if="scope.opt.grupo"> · {{ scope.opt.grupo }}</span></q-item-label>
                  </q-item-section>
                </q-item>
              </template>
              <template #no-option><q-item><q-item-section class="text-grey">Escriba para buscar</q-item-section></q-item></template>
            </q-select>
            <div class="row q-col-gutter-sm">
              <q-input class="col-6" v-model.number="agregar.cantidad" type="number" min="0" step="0.01" dense outlined label="Cantidad *"
                       :suffix="agregar.producto?.unidad || ''" :rules="[v => (v !== '' && v !== null && Number(v) >= 0) || 'Falta la cantidad']" hide-bottom-space />
              <q-input class="col-6" v-model="agregar.fecha_vencimiento" type="date" stack-label dense outlined label="Vencimiento *"
                       :rules="[v => !!v || 'Falta el vencimiento']" hide-bottom-space />
            </div>
            <q-input v-model="agregar.lote" dense outlined label="Lote" />
            <q-input v-model="agregar.observacion" type="textarea" autogrow dense outlined maxlength="500" label="Comentario" />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat no-caps label="Cancelar" v-close-popup />
            <q-btn unelevated no-caps color="teal-8" icon="add" label="Agregar" type="submit" :loading="agregar.guardando" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <!-- Ventas del producto desde el inventario -->
    <q-dialog v-model="ventas.abierto">
      <q-card style="width:900px;max-width:96vw">
        <q-card-section class="row items-center q-pb-sm">
          <div>
            <div class="text-subtitle1 text-weight-bold">Ventas · {{ ventas.producto }}</div>
            <div class="text-caption text-grey-7">{{ ventas.codigo_producto }} · desde el inventario del {{ formatoFecha(ventas.desde) }} hasta hoy · sin anulados</div>
          </div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>
        <q-card-section class="row q-gutter-sm q-pt-none">
          <q-chip dense square color="grey-2" icon="receipt_long">{{ ventas.totales.ventas }} venta(s)</q-chip>
          <q-chip dense square color="grey-2" icon="numbers">Cantidad {{ numero(ventas.totales.cantidad) }}</q-chip>
          <q-chip dense square color="grey-2" icon="scale">Peso {{ numero(ventas.totales.peso) }} kg</q-chip>
          <q-chip dense square color="green-1" text-color="green-9" icon="payments">Bs {{ numero(ventas.totales.importe) }}</q-chip>
        </q-card-section>
        <q-table flat dense square class="tabla-riesgo" :rows="ventas.data" :columns="columnasVentas" row-key="factura_id"
                 :loading="ventas.loading" :pagination="{ rowsPerPage: 20 }" :rows-per-page-options="[20, 50, 0]"
                 no-data-label="Sin ventas desde el inventario" />
      </q-card>
    </q-dialog>
  </q-page>
</template>
<script>
import { date } from 'quasar'

const COLUMNAS = [
  { name: 'foto', label: '', field: 'imagen', align: 'center' },
  { name: 'producto', label: 'Producto', field: 'producto', align: 'left' },
  { name: 'cantidad', label: 'Cantidad ✎', field: 'cantidad', align: 'right' },
  { name: 'vence', label: 'Vencimiento ✎', field: 'fecha_vencimiento', align: 'left' },
  { name: 'observacion', label: 'Comentario ✎', field: 'observacion', align: 'left' },
  { name: 'agregado', label: 'Agregado por', field: 'agregado_at', align: 'left' },
  { name: 'quitado', label: 'Quitado por', field: 'quitado_at', align: 'left' },
  { name: 'acciones', label: '', field: 'id', align: 'center' }
]

export default {
  name: 'AlmacenRiesgo',
  data () {
    return {
      fecha: this.$route.query.fecha || date.formatDate(new Date(), 'YYYY-MM-DD'),
      dias: 20,
      deposito: localStorage.getItem('riesgoDeposito') || 'ORURO',
      exportando: false,
      tab: 'ACTIVO',
      buscar: '',
      rows: [],
      totales: { ACTIVO: 0, QUITADO: 0 },
      generacion: null,
      loading: false,
      generando: false,
      actualizando: false,
      pagination: { page: 1, rowsPerPage: 50, rowsNumber: 0 },
      agregar: { abierto: false, opciones: [], buscando: false, guardando: false, producto: null, cantidad: null, fecha_vencimiento: '', lote: '', observacion: '' },
      ventas: { abierto: false, loading: false, producto: '', codigo_producto: '', desde: null, data: [], totales: { ventas: 0, cantidad: 0, peso: 0, importe: 0 } },
      columnasVentas: [
        { name: 'fecha', label: 'Fecha', field: r => this.formatoFecha(r.fecha) + ' ' + String(r.hora || '').slice(0, 5), align: 'left', sortable: true, sort: (a, b, x, y) => String(x.fecha + x.hora).localeCompare(String(y.fecha + y.hora)) },
        { name: 'comprobante', label: 'Comprobante', field: r => (r.tipo_comprobante || '') + (r.nro_factura ? ' ' + r.nro_factura : ''), align: 'left' },
        { name: 'pedido', label: 'Pedido', field: 'pedido_nro', align: 'left' },
        { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
        { name: 'cantidad', label: 'Cant.', field: r => this.numero(r.cantidad) + ' ' + (r.unidad || ''), align: 'right' },
        { name: 'peso', label: 'Peso', field: r => Number(r.peso) > 0 ? this.numero(r.peso) : '', align: 'right' },
        { name: 'precio', label: 'Precio', field: r => this.numero(r.precio), align: 'right' },
        { name: 'subtotal', label: 'Subtotal', field: r => this.numero(r.subtotal), align: 'right', sortable: true, sort: (a, b, x, y) => Number(x.subtotal) - Number(y.subtotal) }
      ]
    }
  },
  computed: {
    columnasVisibles () {
      return this.tab === 'ACTIVO' ? COLUMNAS.filter(c => c.name !== 'quitado') : COLUMNAS
    }
  },
  mounted () {
    this.cargar()
  },
  methods: {
    urlImagen (ruta) {
      return String(this.$url || '').replace(/api\/?$/, '') + ruta
    },
    formatoFecha (v) {
      return v ? date.formatDate(String(v).slice(0, 10) + 'T12:00:00', 'DD/MM/YYYY') : '—'
    },
    formatoFechaHora (v) {
      return v ? date.formatDate(v, 'DD/MM/YYYY HH:mm') : ''
    },
    diasRestantes (row) {
      if (!row.fecha_vencimiento) return null
      const hoy = new Date(date.formatDate(new Date(), 'YYYY-MM-DD') + 'T12:00:00')
      return Math.round((new Date(String(row.fecha_vencimiento).slice(0, 10) + 'T12:00:00') - hoy) / 86400000)
    },
    // Mismos cortes que el Excel: vencido / crítico 0-10 / riesgo 11-30 / ok +30.
    colorDias (row) {
      const d = this.diasRestantes(row)
      return d === null ? 'grey-9' : d < 0 ? 'black' : d <= 10 ? 'negative' : d <= 30 ? 'amber-8' : 'positive'
    },
    textoDias (row) {
      const d = this.diasRestantes(row)
      if (d === null) return 'Sin fecha'
      return d < 0 ? `Vencido hace ${-d} días` : d === 0 ? 'Vence hoy' : `${d} días`
    },
    async excel () {
      this.exportando = true
      try {
        const { data } = await this.$api.get('productosRiesgo/excel', { params: { deposito: this.deposito }, responseType: 'blob' })
        localStorage.setItem('riesgoDeposito', this.deposito)
        const url = URL.createObjectURL(data)
        const a = document.createElement('a')
        a.href = url
        a.download = 'productos_riesgo_' + (this.generacion?.fecha_inventario ? String(this.generacion.fecha_inventario).slice(0, 10) : this.fecha) + '.xlsx'
        a.click()
        URL.revokeObjectURL(url)
      } catch (err) {
        let mensaje = 'No se pudo generar el Excel'
        try {
          mensaje = JSON.parse(await err.response.data.text()).message || mensaje
        } catch (e) { /* el error no vino en JSON */ }
        this.$q.notify({ color: 'negative', position: 'top', message: mensaje })
      } finally {
        this.exportando = false
      }
    },
    error (e, porDefecto) {
      this.$q.notify({ color: 'negative', position: 'top', icon: 'report_problem', message: e.response?.data?.message || porDefecto })
    },
    ok (message) {
      this.$q.notify({ color: 'positive', position: 'top', icon: 'check', message })
    },
    // Paginado en el servidor; buscar o cambiar de pestaña vuelve a la página 1.
    cargar (reiniciar = true) {
      if (reiniciar) this.pagination.page = 1
      this.loading = true
      this.$api.get('productosRiesgo', {
        params: { estado: this.tab, q: this.buscar || '', page: this.pagination.page, per_page: this.pagination.rowsPerPage }
      }).then(res => {
        this.rows = res.data.data
        this.totales = res.data.totales
        this.generacion = res.data.generacion
        this.pagination.rowsNumber = res.data.total
        if (this.pagination.page > (res.data.last_page || 1)) {
          this.pagination.page = res.data.last_page || 1
          this.cargar(false)
        }
      }).catch(e => this.error(e, 'No se pudo cargar')).finally(() => { this.loading = false })
    },
    onRequest ({ pagination }) {
      this.pagination.page = pagination.page
      this.pagination.rowsPerPage = pagination.rowsPerPage
      this.cargar(false)
    },
    generar () {
      this.$q.dialog({
        title: 'Llenar con inventario',
        message: `Se quitarán todos los productos activos (quedan en el historial) y entrará lo contado en el inventario del ${this.formatoFecha(this.fecha)} que vence en ${this.dias} días o menos. ¿Continuar?`,
        cancel: true,
        persistent: true
      }).onOk(() => {
        this.generando = true
        this.$api.post('productosRiesgo/generar', { fecha: this.fecha, dias: this.dias }).then(res => {
          this.ok(`${res.data.agregados} producto(s) en riesgo agregados; ${res.data.quitados} anteriores quitados`)
          this.tab = 'ACTIVO'
          this.cargar()
        }).catch(e => this.error(e, 'No se pudo llenar la lista')).finally(() => { this.generando = false })
      })
    },
    actualizar () {
      this.actualizando = true
      this.$api.post('productosRiesgo/actualizar').then(res => {
        const r = res.data
        this.ok(`Actualizado: ${r.agregados} nuevo(s), ${r.actualizados} con cambios, ${r.quitados} ya no están en el inventario`)
        this.cargar(false)
      }).catch(e => this.error(e, 'No se pudo actualizar')).finally(() => { this.actualizando = false })
    },
    numero (v) {
      return Number(v || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    abrirAgregar () {
      Object.assign(this.agregar, { abierto: true, producto: null, cantidad: null, fecha_vencimiento: '', lote: '', observacion: '' })
    },
    buscarCatalogo (texto, update) {
      if (!texto || texto.length < 2) {
        update(() => { this.agregar.opciones = [] })
        return
      }
      this.agregar.buscando = true
      this.$api.get('productosRiesgo/catalogo', { params: { q: texto } })
        .then(res => update(() => { this.agregar.opciones = res.data }))
        .catch(e => { update(); this.error(e, 'No se pudo buscar') })
        .finally(() => { this.agregar.buscando = false })
    },
    guardarNuevo () {
      const a = this.agregar
      a.guardando = true
      this.$api.post('productosRiesgo', {
        codigo_producto: a.producto.cod_prod,
        cantidad: a.cantidad,
        fecha_vencimiento: a.fecha_vencimiento,
        lote: a.lote || null,
        observacion: a.observacion || null
      }).then(() => {
        this.ok(`${a.producto.producto} agregado a productos en riesgo`)
        a.abierto = false
        this.tab = 'ACTIVO'
        this.cargar()
      }).catch(e => this.error(e, 'No se pudo agregar')).finally(() => { a.guardando = false })
    },
    verVentas (row) {
      Object.assign(this.ventas, { abierto: true, loading: true, producto: row.producto, codigo_producto: row.codigo_producto, desde: row.fecha_inventario, data: [] })
      this.$api.get(`productosRiesgo/${row.id}/ventas`).then(res => {
        Object.assign(this.ventas, res.data)
      }).catch(e => this.error(e, 'No se pudieron cargar las ventas')).finally(() => { this.ventas.loading = false })
    },
    editable (row) {
      return row.estado === 'ACTIVO'
    },
    // Corrige cantidad o vencimiento solo en esta lista; el inventario no cambia.
    guardar (row, cambios) {
      row._guardando = true
      this.$api.put(`productosRiesgo/${row.id}`, cambios).then(res => {
        Object.assign(row, res.data)
        this.ok(`${row.producto} actualizado`)
      }).catch(e => this.error(e, 'No se pudo guardar')).finally(() => { row._guardando = false })
    },
    quitar (row) {
      this.$q.dialog({
        title: 'Quitar',
        message: `¿Quitar ${row.producto}${row.lote ? ` (lote ${row.lote})` : ''} de productos en riesgo? Motivo (opcional):`,
        prompt: { model: '', type: 'text', outlined: true, dense: true },
        cancel: true
      }).onOk(motivo => {
        row._guardando = true
        this.$api.put(`productosRiesgo/${row.id}/quitar`, { motivo: motivo || null }).then(() => {
          this.ok(`${row.producto} quitado`)
          this.cargar(false)
        }).catch(e => this.error(e, 'No se pudo quitar')).finally(() => { row._guardando = false })
      })
    },
    reagregar (row) {
      this.$q.dialog({ title: 'Volver a agregar', message: `¿Volver a agregar ${row.producto} a productos en riesgo?`, cancel: true }).onOk(() => {
        row._guardando = true
        this.$api.post(`productosRiesgo/${row.id}/reagregar`).then(() => {
          this.ok(`${row.producto} agregado otra vez`)
          this.cargar(false)
        }).catch(e => this.error(e, 'No se pudo agregar')).finally(() => { row._guardando = false })
      })
    }
  }
}
</script>
<style scoped>
.foto { width: 30px; height: 30px; border-radius: 4px; background: #fafafa; display: flex; align-items: center; justify-content: center; overflow: hidden; margin: auto }
.tabla-riesgo :deep(td), .tabla-riesgo :deep(th) { padding: 2px 6px; height: auto; font-size: 12px; line-height: 1.25 }
.celda-producto { max-width: 340px }
.celda-obs { min-width: 160px; max-width: 260px }
.obs-texto { white-space: pre-wrap; word-break: break-word }
.chico { font-size: 11px }
.editable { cursor: pointer }
.editable:hover { background: #fff3e0 }
.foto img { width: 100%; height: 100%; object-fit: contain }
.fila-quitada { background: #f7f7f7; color: #757575 }
</style>
