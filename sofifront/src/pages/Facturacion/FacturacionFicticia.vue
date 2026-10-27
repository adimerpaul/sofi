<template>
  <q-page class="q-pa-md">
    <!-- Facturas al SIAT que no pasan por la facturacion ni mueven inventario. -->
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md-auto text-h6">Facturación ficticia</div>
      <q-space />
      <div class="col-6 col-md-2">
        <q-input v-model="filtros.desde" type="date" label="Desde" dense outlined @update:model-value="cargar(1)" />
      </div>
      <div class="col-6 col-md-2">
        <q-input v-model="filtros.hasta" type="date" label="Hasta" dense outlined @update:model-value="cargar(1)" />
      </div>
      <div class="col-6 col-md-2">
        <q-select v-model="filtros.estado" :options="['ACTIVO', 'ANULADO']" label="Estado" dense outlined clearable
                  @update:model-value="cargar(1)" />
      </div>
      <div class="col-6 col-md-2">
        <q-input v-model="filtros.buscar" label="Buscar NIT, nombre o Nº" dense outlined clearable
                 debounce="400" @update:model-value="cargar(1)" />
      </div>
      <div class="col-12 col-md-auto">
        <q-btn color="primary" icon="add" label="Nueva factura" no-caps @click="abrirNueva" />
      </div>
    </div>

    <q-table
      :rows="facturas"
      :columns="columnas"
      row-key="id"
      dense flat bordered
      :loading="loading"
      v-model:pagination="pagination"
      :rows-per-page-options="[20, 50, 100]"
      @request="r => cargar(r.pagination.page, r.pagination.rowsPerPage)"
    >
      <template #body-cell-estado="props">
        <q-td :props="props">
          <q-badge :color="props.row.estado === 'ANULADO' ? 'negative' : 'positive'" :label="props.row.estado" />
        </q-td>
      </template>
      <template #body-cell-estado_siat="props">
        <q-td :props="props">
          <q-badge :color="colorSiat(props.row.estado_siat)" :label="props.row.estado_siat || '—'" />
          <q-tooltip v-if="props.row.mensaje_siat" max-width="320px">{{ props.row.mensaje_siat }}</q-tooltip>
        </q-td>
      </template>
      <template #body-cell-acciones="props">
        <q-td :props="props" class="q-gutter-xs">
          <q-btn dense flat round icon="print" color="primary" :loading="trabajando === props.row.id"
                 @click="imprimir(props.row)"><q-tooltip>Imprimir factura</q-tooltip></q-btn>
          <q-btn dense flat round icon="visibility" color="grey-8" @click="detalle = props.row">
            <q-tooltip>Ver detalle</q-tooltip></q-btn>
          <q-btn v-if="props.row.estado !== 'ANULADO' && props.row.estado_siat !== 'VALIDADA'"
                 dense flat round icon="send" color="orange-8" :loading="trabajando === props.row.id"
                 @click="reenviar(props.row)"><q-tooltip>Reenviar al SIAT</q-tooltip></q-btn>
          <q-btn v-if="props.row.cuf" dense flat round icon="verified" color="teal"
                 :loading="trabajando === props.row.id" @click="verificar(props.row)">
            <q-tooltip>Verificar en el SIAT</q-tooltip></q-btn>
          <q-btn v-if="props.row.estado !== 'ANULADO'" dense flat round icon="block" color="negative"
                 @click="abrirAnular(props.row)"><q-tooltip>Anular</q-tooltip></q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- Nueva factura: cliente + productos. No descuenta stock. -->
    <q-dialog v-model="dialogNueva" persistent maximized-on-mobile>
      <q-card style="width: 900px; max-width: 96vw">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Nueva factura ficticia</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>
        <q-card-section class="text-caption text-grey-8 q-pt-xs">
          Se emite al SIAT. No afecta la facturación ni el inventario.
        </q-card-section>

        <q-card-section class="row q-col-gutter-sm">
          <div class="col-12">
            <q-select
              v-model="cliente" :options="clientes" option-label="nombre" label="Cliente (NIT o nombre)"
              use-input input-debounce="300" dense outlined clearable :loading="buscandoCliente"
              @filter="buscarClientes" @update:model-value="elegirCliente"
            >
              <template #option="scope">
                <q-item v-bind="scope.itemProps">
                  <q-item-section>
                    <q-item-label>{{ scope.opt.nombre }}</q-item-label>
                    <q-item-label caption>NIT/CI {{ scope.opt.nit }} · {{ scope.opt.direccion }}</q-item-label>
                  </q-item-section>
                </q-item>
              </template>
              <template #no-option>
                <q-item><q-item-section class="text-grey">Escriba al menos 2 letras</q-item-section></q-item>
              </template>
            </q-select>
          </div>
          <div class="col-6 col-md-4"><q-input v-model="form.nit" label="NIT / CI *" dense outlined /></div>
          <div class="col-6 col-md-5"><q-input v-model="form.nombre" label="Razón social *" dense outlined /></div>
          <div class="col-12 col-md-3">
            <q-select v-model="form.tipo_pago" :options="tiposPago" label="Forma de pago" dense outlined />
          </div>

          <div class="col-12">
            <q-select
              v-model="productoBuscado" :options="productos" option-label="producto" label="Agregar producto"
              use-input input-debounce="300" dense outlined clearable :loading="buscandoProducto"
              @filter="buscarProductos" @update:model-value="agregarProducto"
            >
              <template #prepend><q-icon name="search" /></template>
              <template #option="scope">
                <q-item v-bind="scope.itemProps">
                  <q-item-section>
                    <q-item-label>{{ scope.opt.producto }}</q-item-label>
                    <q-item-label caption>{{ scope.opt.cod_prod }} · {{ scope.opt.unidad }} · Bs {{ scope.opt.precio }}</q-item-label>
                  </q-item-section>
                </q-item>
              </template>
            </q-select>
          </div>

          <div class="col-12">
            <q-markup-table dense flat bordered>
              <thead>
                <tr>
                  <th class="text-left">Código</th>
                  <th class="text-left">Producto</th>
                  <th style="width: 110px">Cantidad</th>
                  <th style="width: 110px">Precio</th>
                  <th class="text-right">Importe</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, i) in form.items" :key="i">
                  <td>{{ item.cod_prod }}</td>
                  <td>{{ item.nombre }} <span class="text-grey">({{ item.unidad }})</span></td>
                  <td><q-input v-model.number="item.cantidad" type="number" min="0" step="0.001" dense /></td>
                  <td><q-input v-model.number="item.precio" type="number" min="0" step="0.01" dense /></td>
                  <td class="text-right">{{ importe(item).toFixed(2) }}</td>
                  <td><q-btn dense flat round icon="delete" color="negative" @click="form.items.splice(i, 1)" /></td>
                </tr>
                <tr v-if="!form.items.length">
                  <td colspan="6" class="text-center text-grey">Agregue productos</td>
                </tr>
              </tbody>
            </q-markup-table>
          </div>

          <div class="col-12 col-md-6">
            <q-input v-model="form.observacion" label="Observación" dense outlined maxlength="255" />
          </div>
          <div class="col-6 col-md-3">
            <q-input v-model.number="form.descuento" type="number" min="0" step="0.01" label="Descuento Bs" dense outlined />
          </div>
          <div class="col-6 col-md-3 text-right">
            <div class="text-caption text-grey-8">Subtotal Bs {{ subtotal.toFixed(2) }}</div>
            <div class="text-h6">Total Bs {{ total.toFixed(2) }}</div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup no-caps />
          <q-btn color="primary" icon="send" label="Emitir al SIAT" no-caps :loading="guardando"
                 :disable="!puedeGuardar" @click="guardar" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Anulacion: primero en Impuestos, luego aca. -->
    <q-dialog v-model="dialogAnular">
      <q-card style="width: 420px; max-width: 95vw">
        <q-card-section class="text-h6">Anular factura {{ anulando?.nro_factura || anulando?.id }}</q-card-section>
        <q-card-section class="q-gutter-sm">
          <q-select v-model="anulacion.codigo_motivo" :options="motivos" emit-value map-options
                    label="Motivo (SIAT)" dense outlined />
          <q-input v-model="anulacion.observacion" label="Observación" dense outlined maxlength="255" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup no-caps />
          <q-btn color="negative" label="Anular" no-caps :loading="trabajando === anulando?.id" @click="anular" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog :model-value="!!detalle" @update:model-value="v => { if (!v) detalle = null }">
      <q-card v-if="detalle" style="width: 700px; max-width: 95vw">
        <q-card-section>
          <div class="text-h6">Factura {{ detalle.nro_factura || '—' }} · {{ detalle.nombre }}</div>
          <div class="text-caption">NIT {{ detalle.nit }} · {{ detalle.fecha }} {{ detalle.hora }} · {{ detalle.tipo_pago }}</div>
          <div v-if="detalle.motivo_anulacion" class="text-negative text-caption">
            Anulada: {{ detalle.motivo_anulacion }} {{ detalle.anulacion_observacion || '' }}
          </div>
        </q-card-section>
        <q-markup-table dense flat>
          <thead><tr><th class="text-left">Producto</th><th>Cantidad</th><th>Precio</th><th class="text-right">Importe</th></tr></thead>
          <tbody>
            <tr v-for="d in detalle.detalles" :key="d.id">
              <td>{{ d.nombre }}</td><td class="text-center">{{ d.cantidad }}</td>
              <td class="text-center">{{ d.precio }}</td><td class="text-right">{{ d.subtotal }}</td>
            </tr>
          </tbody>
        </q-markup-table>
        <q-card-section class="text-right text-subtitle1">Total Bs {{ detalle.total }}</q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { date } from 'quasar'
import { imprimirPdfDirecto } from 'src/utils/impresion.js'

export default {
  name: 'FacturacionFicticia',
  data () {
    const hoy = date.formatDate(new Date(), 'YYYY-MM-DD')
    return {
      facturas: [],
      loading: false,
      pagination: { page: 1, rowsPerPage: 20, rowsNumber: 0 },
      filtros: { desde: hoy, hasta: hoy, estado: null, buscar: '' },
      columnas: [
        { name: 'nro_factura', label: 'Nº factura', field: 'nro_factura', align: 'left' },
        { name: 'fecha', label: 'Fecha', field: r => (r.fecha || '').substr(0, 10) + ' ' + (r.hora || ''), align: 'left' },
        { name: 'nit', label: 'NIT', field: 'nit', align: 'left' },
        { name: 'nombre', label: 'Razón social', field: 'nombre', align: 'left' },
        { name: 'total', label: 'Total', field: 'total', align: 'right' },
        { name: 'estado', label: 'Estado', field: 'estado', align: 'center' },
        { name: 'estado_siat', label: 'SIAT', field: 'estado_siat', align: 'center' },
        { name: 'acciones', label: '', field: 'id', align: 'right' }
      ],
      trabajando: null,
      detalle: null,

      dialogNueva: false,
      guardando: false,
      form: this.formVacio(),
      cliente: null,
      clientes: [],
      buscandoCliente: false,
      productoBuscado: null,
      productos: [],
      buscandoProducto: false,
      tiposPago: ['EFECTIVO', 'TARJETA', 'CHEQUE', 'TRANSFERENCIA', 'DEPOSITO', 'CREDITO', 'OTRO'],

      dialogAnular: false,
      anulando: null,
      anulacion: { codigo_motivo: 1, observacion: '' },
      motivos: [
        { value: 1, label: 'Factura mal emitida' },
        { value: 2, label: 'Datos de emisión incorrectos' },
        { value: 3, label: 'Factura o nota devuelta' },
        { value: 4, label: 'Sustitución de factura emitida en contingencia' }
      ]
    }
  },
  computed: {
    subtotal () {
      return this.form.items.reduce((s, i) => s + this.importe(i), 0)
    },
    total () {
      return Math.round((this.subtotal - Number(this.form.descuento || 0)) * 100) / 100
    },
    puedeGuardar () {
      return (this.form.nit || '').trim() && (this.form.nombre || '').trim() && this.form.items.length &&
        this.form.items.every(i => Number(i.cantidad) > 0 && Number(i.precio) > 0) && this.total > 0
    }
  },
  mounted () {
    this.cargar(1)
  },
  methods: {
    formVacio () {
      return { cliente_id: null, nit: '', nombre: '', tipo_pago: 'EFECTIVO', descuento: 0, observacion: '', items: [] }
    },
    importe (item) {
      return Math.round(Number(item.cantidad || 0) * Number(item.precio || 0) * 100) / 100
    },
    colorSiat (estado) {
      if (estado === 'VALIDADA') return 'positive'
      if (estado === 'ERROR' || estado === 'RECHAZADA') return 'negative'
      if (estado === 'ANULADA') return 'grey-7'
      return 'orange-8'
    },

    cargar (page = this.pagination.page, rowsPerPage = this.pagination.rowsPerPage) {
      this.loading = true
      this.$api.get('facturacion-ficticia', {
        params: {
          page,
          perPage: rowsPerPage,
          desde: this.filtros.desde || '',
          hasta: this.filtros.hasta || '',
          estado: this.filtros.estado || '',
          buscar: this.filtros.buscar || ''
        }
      }).then(res => {
        this.facturas = res.data.data
        this.pagination = { page: res.data.current_page, rowsPerPage, rowsNumber: res.data.total }
      }).catch(err => {
        this.avisar(err, 'No se pudieron cargar las facturas')
      }).finally(() => { this.loading = false })
    },

    abrirNueva () {
      this.form = this.formVacio()
      this.cliente = null
      this.productoBuscado = null
      this.dialogNueva = true
    },

    // Se reutilizan las busquedas de la facturacion: solo leen, no tocan nada.
    buscarClientes (texto, update, abort) {
      if ((texto || '').trim().length < 2) {
        abort()
        return
      }
      this.buscandoCliente = true
      this.$api.get('facturacion/clientes', { params: { buscar: texto } })
        .then(res => { update(() => { this.clientes = res.data }) })
        .catch(() => { abort() })
        .finally(() => { this.buscandoCliente = false })
    },
    elegirCliente (c) {
      this.form.cliente_id = c ? c.id : null
      if (c) {
        this.form.nit = c.nit || ''
        this.form.nombre = c.nombre || ''
      }
    },
    buscarProductos (texto, update, abort) {
      if ((texto || '').trim().length < 2) {
        abort()
        return
      }
      this.buscandoProducto = true
      this.$api.get('facturacion/catalogo', { params: { buscar: texto, perPage: 30 } })
        .then(res => { update(() => { this.productos = res.data.data }) })
        .catch(() => { abort() })
        .finally(() => { this.buscandoProducto = false })
    },
    agregarProducto (p) {
      if (!p) return
      const existente = this.form.items.find(i => i.cod_prod === p.cod_prod)
      if (existente) {
        existente.cantidad = Number(existente.cantidad || 0) + 1
      } else {
        this.form.items.push({ cod_prod: p.cod_prod, nombre: p.producto, unidad: p.unidad, cantidad: 1, precio: Number(p.precio) || 0 })
      }
      this.$nextTick(() => { this.productoBuscado = null })
    },

    guardar () {
      this.guardando = true
      this.$api.post('facturacion-ficticia', this.form).then(res => {
        const factura = res.data.factura
        const conError = factura.estado_siat === 'ERROR'
        this.$q.notify({
          message: res.data.message,
          color: conError ? 'warning' : 'positive',
          icon: conError ? 'warning' : 'check',
          position: 'top',
          timeout: conError ? 8000 : 3000
        })
        this.dialogNueva = false
        this.cargar(1)
        if (!conError) this.imprimir(factura)
      }).catch(err => {
        this.avisar(err, 'No se pudo guardar la factura')
      }).finally(() => { this.guardando = false })
    },

    imprimir (row) {
      this.trabajando = row.id
      return this.$api.get('facturacion-ficticia/' + row.id + '/pdf', { responseType: 'blob' })
        .then(res => imprimirPdfDirecto(res.data, 'factura_' + (row.nro_factura || row.id) + '.pdf'))
        .catch(err => { this.avisar(err, 'No se pudo imprimir la factura') })
        .finally(() => { this.trabajando = null })
    },
    reenviar (row) {
      this.trabajando = row.id
      this.$api.post('facturacion-ficticia/' + row.id + '/reenviar').then(res => {
        this.$q.notify({ message: res.data.message, color: 'positive', icon: 'check', position: 'top' })
        this.cargar()
      }).catch(err => {
        this.avisar(err, 'No se pudo reenviar')
        this.cargar()
      }).finally(() => { this.trabajando = null })
    },
    verificar (row) {
      this.trabajando = row.id
      this.$api.post('facturacion-ficticia/' + row.id + '/verificar').then(res => {
        this.$q.notify({ message: (res.data.estado || '') + ' ' + (res.data.mensaje || ''), color: 'info', position: 'top' })
        this.cargar()
      }).catch(err => {
        this.avisar(err, 'No se pudo verificar')
      }).finally(() => { this.trabajando = null })
    },
    abrirAnular (row) {
      this.anulando = row
      this.anulacion = { codigo_motivo: 1, observacion: '' }
      this.dialogAnular = true
    },
    anular () {
      const row = this.anulando
      this.trabajando = row.id
      this.$api.put('facturacion-ficticia/' + row.id + '/anular', this.anulacion).then(res => {
        this.$q.notify({ message: res.data.message, color: 'positive', icon: 'check', position: 'top' })
        this.dialogAnular = false
        this.cargar()
      }).catch(err => {
        this.avisar(err, 'No se pudo anular')
      }).finally(() => { this.trabajando = null })
    },

    async avisar (err, porDefecto) {
      let mensaje = err.response?.data?.message
      if (err.response?.data instanceof Blob) {
        try {
          mensaje = JSON.parse(await err.response.data.text()).message
        } catch (e) {
          mensaje = null
        }
      }
      this.$q.notify({ message: mensaje || porDefecto, color: 'negative', icon: 'error', position: 'top' })
    }
  }
}
</script>
