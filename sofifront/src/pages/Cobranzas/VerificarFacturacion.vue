<template>
  <q-page class="q-pa-sm relative-position">
    <fieldset class="contenido" :disabled="ocupado" :inert="ocupado || undefined">
    <div class="row items-center q-gutter-sm q-mb-sm">
      <div class="col-auto">
        <div class="text-h6">Verificar facturación</div>
        <div class="text-caption text-grey-7">Cada comprobante del día contra lo que trajo el camión</div>
      </div>
      <q-space/>
      <div v-if="actualizado && !error" class="text-caption text-positive" role="status">Actualizado a las {{ actualizado }}</div>
      <q-btn outline dense no-caps color="green-8" icon="grid_on" label="Excel" :loading="exportando === 'excel'" @click="exportar('excel')"/>
      <q-btn outline dense no-caps color="red-7" icon="picture_as_pdf" label="PDF" :loading="exportando === 'pdf'" @click="exportar('pdf')"/>
    </div>

    <div class="row q-col-gutter-xs items-center q-mb-sm">
      <div class="col-6 col-md-2">
        <q-input v-model="fecha" type="date" dense outlined label="Fecha" @update:model-value="cambiarFecha"/>
      </div>
      <div class="col-6 col-md-2">
        <q-select
          v-model="camion" dense outlined clearable emit-value map-options label="Camión"
          :options="opcionesCamion" @update:model-value="cargar"
        />
      </div>
      <div class="col-12 col-md-3">
        <q-input v-model="buscar" dense outlined clearable placeholder="Cliente, NIT, comprobante o pedido">
          <template #prepend><q-icon name="search"/></template>
        </q-input>
      </div>
      <div class="col-12 col-md-auto">
        <q-btn-toggle
          v-model="estado" no-caps unelevated dense toggle-color="primary" color="grey-3" text-color="grey-9"
          :options="[
            { label: 'Por verificar (' + (totales.comprobantes - totales.verificados) + ')', value: 'pendientes' },
            { label: 'Verificados (' + totales.verificados + ')', value: 'verificados' },
            { label: 'Todos', value: 'todos' }
          ]"
        />
      </div>
      <div class="col-auto">
        <q-btn flat dense round icon="refresh" color="primary" :loading="cargando" @click="cargar"/>
      </div>
    </div>

    <q-card v-if="camiones.length" flat bordered class="q-mb-sm">
      <div class="row items-center bg-grey-3 q-px-sm">
        <div class="text-caption text-weight-bold">VERIFICACIÓN POR CAMIÓN · {{ porcentaje(resumenEntregas) }}%</div>
        <q-space/>
        <q-btn flat dense no-caps label="Todos" color="primary" :disable="cargando" @click="alternarCamion(null)"/>
      </div>
      <div class="text-caption text-grey-7 q-px-sm">Verificados / entregados (incluye entregas parciales)</div>
      <button
        v-for="c in camiones" :key="c.placa" type="button"
        class="camion-fila row items-center no-wrap full-width"
        :class="camion === c.placa ? 'bg-blue-2' : ''"
        :aria-pressed="camion === c.placa" :disabled="cargando" @click="alternarCamion(c.placa)"
      >
        <span class="chip-placa camion-placa ellipsis" :style="estiloColor(c.color)">{{ nombrePlaca(c.placa) }}</span>
        <q-linear-progress class="col q-mx-xs" rounded size="14px" :value="c.entregados ? c.entregados_verificados / c.entregados : 0"
                           :color="c.entregados && c.entregados_verificados === c.entregados ? 'positive' : 'orange-7'" track-color="grey-4"/>
        <span class="camion-conteo">{{ c.entregados_verificados }}/{{ c.entregados }} · {{ porcentaje(c) }}%</span>
      </button>
    </q-card>

    <!-- Los montos del día: lo facturado, lo que trajeron los camiones y lo
         que cobranzas ya dio por recibido. -->
    <div class="row q-col-gutter-xs q-mb-sm">
      <div class="col-6 col-md">
        <q-card flat bordered class="tarjeta bg-blue-grey-1">
          <div class="tarjeta-rotulo text-blue-grey-8">FACTURADO</div>
          <div class="tarjeta-valor text-blue-grey-10">Bs {{ money(totales.facturado) }}</div>
        </q-card>
      </div>
      <div class="col-6 col-md">
        <q-card flat bordered class="tarjeta bg-indigo-1">
          <div class="tarjeta-rotulo text-indigo-8">RECOGIDO POR CAMIONES</div>
          <div class="tarjeta-valor text-indigo-10">Bs {{ money(totales.recogido) }}</div>
        </q-card>
      </div>
      <div class="col-6 col-md">
        <q-card flat bordered class="tarjeta bg-green-1">
          <div class="tarjeta-rotulo text-green-8">VERIFICADO</div>
          <div class="tarjeta-valor text-green-10">Bs {{ money(totales.verificado) }}</div>
        </q-card>
      </div>
      <div class="col-6 col-md">
        <q-card flat bordered class="tarjeta" :class="Math.abs(totales.diferencia) > 0.009 ? 'bg-red-1' : 'bg-grey-2'">
          <div class="tarjeta-rotulo" :class="Math.abs(totales.diferencia) > 0.009 ? 'text-red-8' : 'text-grey-8'">DIFERENCIA</div>
          <div class="tarjeta-valor" :class="Math.abs(totales.diferencia) > 0.009 ? 'text-red-10' : 'text-grey-9'">Bs {{ money(totales.diferencia) }}</div>
        </q-card>
      </div>
      <div class="col-12 col-md">
        <q-card flat bordered class="tarjeta bg-orange-1">
          <div class="tarjeta-rotulo text-orange-8">VERIFICADOS</div>
          <div class="row items-center no-wrap">
            <div class="tarjeta-valor text-orange-10">{{ totales.verificados }}/{{ totales.comprobantes }}</div>
            <q-linear-progress class="col q-ml-sm" rounded size="10px" :value="avance" color="positive" track-color="orange-2"/>
          </div>
        </q-card>
      </div>
    </div>

    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-sm">{{ error }}</q-banner>

    <q-table
      flat bordered dense :rows="visibles" :columns="columnas" row-key="factura_id" :loading="cargando"
      :rows-per-page-options="[50, 100, 0]" :pagination="{ rowsPerPage: 0 }" :grid="$q.screen.lt.md"
      no-data-label="No hay comprobantes con ese filtro" class="tabla"
    >
      <template #body="props">
        <q-tr :props="props" :class="props.row.verificado ? 'fila-ok' : ''">
          <q-td key="comprobante" :props="props">
            <div class="text-weight-bold">{{ props.row.tipo_comprobante === 'FACTURA' ? 'Factura' : 'Venta' }} #{{ props.row.factura_id }}</div>
            <div class="text-caption text-grey-7">Pedido #{{ props.row.pedido || '—' }} · {{ props.row.hora }}</div>
          </q-td>
          <q-td key="cliente" :props="props">
            <div>{{ props.row.cliente || 'Sin cliente' }}</div>
            <div class="text-caption text-grey-7">NIT {{ props.row.nit || '—' }}</div>
            <q-btn flat dense no-caps color="primary" icon="history" label="Historial de ventas" :disable="!props.row.cliente_id || ocupado" @click="verVentas(props.row)"/>
          </q-td>
          <q-td key="placa" :props="props">
            <span class="chip-placa" :style="estiloColor(props.row.placa_color)">{{ nombrePlaca(props.row.placa) }}</span>
          </q-td>
          <q-td key="pago" :props="props">
            <div>{{ props.row.tipo_pago }}</div>
            <div class="text-caption" :class="colorEntrega(props.row.entrega)">{{ props.row.entrega }}</div>
          </q-td>
          <q-td key="facturado" :props="props" class="text-weight-bold">{{ money(props.row.facturado) }}</q-td>
          <q-td key="recogido" :props="props">
            <span v-if="props.row.recogido !== null" :class="props.row.recogido < props.row.facturado ? 'text-orange-9' : 'text-indigo-9'">
              {{ money(props.row.recogido) }}
            </span>
            <span v-else class="text-grey-5">—</span>
          </q-td>
          <q-td key="monto" :props="props" style="width: 120px">
            <q-input
              v-model.number="montos[props.row.factura_id]" type="number" step="0.01" min="0" dense outlined
              input-class="text-right" :bg-color="props.row.verificado ? 'green-1' : 'white'"
              @change="corregirMonto(props.row)"
            />
          </q-td>
          <q-td key="diferencia" :props="props">
            <span :class="claseDiferencia(props.row)">{{ money(diferencia(props.row)) }}</span>
          </q-td>
          <q-td key="cancela" :props="props" class="text-center">
            <q-checkbox
              :model-value="props.row.verificado" size="lg" color="positive"
              :disable="guardando === props.row.factura_id" @update:model-value="v => marcar(props.row, v)"
            />
            <div v-if="props.row.verificado" class="text-caption text-grey-7 ellipsis" style="max-width: 110px">
              {{ props.row.verificado_por }}
            </div>
          </q-td>
        </q-tr>
      </template>

      <!-- En el celular: una tarjeta por comprobante con el tilde grande. -->
      <template #item="props">
        <div class="col-12 q-pa-xs">
          <q-card flat bordered :class="props.row.verificado ? 'fila-ok' : ''">
            <q-card-section class="q-pa-sm">
              <div class="row items-start no-wrap">
                <div class="col" style="min-width: 0">
                  <div class="text-weight-bold ellipsis">{{ props.row.cliente || 'Sin cliente' }}</div>
                  <q-btn flat dense no-caps color="primary" icon="history" label="Historial de ventas" :disable="!props.row.cliente_id || ocupado" @click="verVentas(props.row)"/>
                  <div class="text-caption text-grey-7">
                    {{ props.row.tipo_comprobante === 'FACTURA' ? 'Factura' : 'Venta' }} #{{ props.row.factura_id }} ·
                    Pedido #{{ props.row.pedido || '—' }}
                  </div>
                  <div class="q-mt-xs">
                    <span class="chip-placa" :style="estiloColor(props.row.placa_color)">{{ nombrePlaca(props.row.placa) }}</span>
                    <span class="text-caption q-ml-xs" :class="colorEntrega(props.row.entrega)">{{ props.row.entrega }}</span>
                  </div>
                </div>
                <q-checkbox
                  :model-value="props.row.verificado" size="xl" color="positive"
                  :disable="guardando === props.row.factura_id" @update:model-value="v => marcar(props.row, v)"
                />
              </div>
              <div class="row q-col-gutter-xs items-center q-mt-xs">
                <div class="col-4">
                  <div class="text-caption text-grey-7">Facturado</div>
                  <div class="text-weight-bold">{{ money(props.row.facturado) }}</div>
                </div>
                <div class="col-4">
                  <div class="text-caption text-grey-7">Recogido</div>
                  <div class="text-weight-bold text-indigo-9">{{ props.row.recogido !== null ? money(props.row.recogido) : '—' }}</div>
                </div>
                <div class="col-4">
                  <q-input
                    v-model.number="montos[props.row.factura_id]" type="number" step="0.01" min="0" dense outlined label="Monto"
                    input-class="text-right" @change="corregirMonto(props.row)"
                  />
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </template>

      <template #bottom-row>
        <q-tr class="fila-total">
          <q-td colspan="4" class="text-right">TOTALES · {{ visibles.length }} comprobantes</q-td>
          <q-td class="text-right">{{ money(suma('facturado')) }}</q-td>
          <q-td class="text-right">{{ money(suma('recogido')) }}</q-td>
          <q-td class="text-right">{{ money(sumaMontos) }}</q-td>
          <q-td class="text-right">{{ money(sumaMontos - suma('facturado')) }}</q-td>
          <q-td/>
        </q-tr>
      </template>
    </q-table>
    </fieldset>
    <q-inner-loading :showing="ocupado" class="carga-pagina">
      <q-spinner color="primary" size="48px"/>
      <div class="q-mt-sm text-primary text-weight-bold" role="status">{{ guardando !== null ? 'Guardando verificación…' : 'Actualizando datos…' }}</div>
    </q-inner-loading>
    <q-dialog v-model="dialogVentas">
      <q-card style="width: 950px; max-width: 95vw">
        <q-card-section class="row items-center">
          <div class="col">
            <div class="text-h6">Historial de ventas · {{ clienteVentas.cliente }}</div>
            <div class="text-caption text-grey-7">Comprobantes de facturación de todas las fechas</div>
          </div>
          <q-btn flat round icon="close" v-close-popup aria-label="Cerrar historial"/>
        </q-card-section>
        <q-banner v-if="errorVentas" class="bg-red-1 text-negative">
          {{ errorVentas }}
          <template #action><q-btn flat label="Reintentar" @click="cargarVentas(paginaVentas)"/></template>
        </q-banner>
        <q-card-section class="relative-position">
          <q-table flat dense :rows="ventas" :columns="columnasVentas" row-key="id" hide-bottom
                   :pagination="{ rowsPerPage: 0 }" :loading="cargandoVentas" no-data-label="Sin ventas registradas"/>
          <div class="row justify-center q-mt-sm">
            <q-pagination :model-value="paginaVentas" :max="paginasVentas" :max-pages="6" :disable="cargandoVentas" @update:model-value="cargarVentas"/>
          </div>
          <q-inner-loading :showing="cargandoVentas" label="Cargando historial…"/>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { date } from 'quasar'

export default {
  name: 'VerificarFacturacion',
  data () {
    return {
      fecha: this.$route.query.fecha || date.formatDate(new Date(), 'YYYY-MM-DD'),
      camion: null,
      buscar: '',
      estado: 'pendientes',
      cargando: false,
      actualizado: '',
      dialogVentas: false,
      clienteVentas: {},
      ventas: [],
      cargandoVentas: false,
      errorVentas: '',
      paginaVentas: 1,
      paginasVentas: 1,
      solicitudVentas: 0,
      columnasVentas: [
        { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left', format: v => String(v).slice(0, 10) },
        { name: 'id', label: 'Comprobante', field: 'id', align: 'left', format: (v, fila) => fila.tipo_comprobante + ' #' + v },
        { name: 'pedido', label: 'Pedido', field: 'pedido_nro', align: 'left' },
        { name: 'pago', label: 'Pago', field: 'tipo_pago', align: 'left' },
        { name: 'total', label: 'Total Bs', field: 'total', align: 'right', format: v => Number(v).toFixed(2) },
        { name: 'estado', label: 'Estado', field: 'estado', align: 'left' }
      ],
      guardando: null,
      exportando: null,
      error: '',
      filas: [],
      camiones: [],
      // Lo escrito en la columna Monto, por comprobante.
      montos: {},
      totales: { comprobantes: 0, verificados: 0, facturado: 0, recogido: 0, verificado: 0, diferencia: 0 },
      columnas: [
        { name: 'comprobante', label: 'Comprobante', field: 'factura_id', align: 'left', sortable: true },
        { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
        { name: 'placa', label: 'Camión', field: 'placa', align: 'left', sortable: true },
        { name: 'pago', label: 'Pago / Entrega', field: 'tipo_pago', align: 'left' },
        { name: 'facturado', label: 'Importe', field: 'facturado', align: 'right', sortable: true },
        { name: 'recogido', label: 'Recogido', field: 'recogido', align: 'right', sortable: true },
        { name: 'monto', label: 'Monto', field: 'monto_verificado', align: 'right' },
        { name: 'diferencia', label: 'Diferencia', field: 'diferencia', align: 'right' },
        { name: 'cancela', label: 'Verificar', field: 'verificado', align: 'center' }
      ]
    }
  },
  computed: {
    ocupado () { return this.cargando || this.guardando !== null },
    resumenEntregas () {
      return this.camiones.reduce((total, c) => ({
        entregados: total.entregados + c.entregados,
        entregados_verificados: total.entregados_verificados + c.entregados_verificados
      }), { entregados: 0, entregados_verificados: 0 })
    },
    opcionesCamion () {
      return this.camiones.map(c => ({
        label: this.nombrePlaca(c.placa) + ' (' + c.verificados + '/' + c.total + ')',
        value: c.placa
      }))
    },
    visibles () {
      const texto = (this.buscar || '').trim().toLowerCase()
      return this.filas.filter(f => {
        if (this.estado === 'pendientes' && f.verificado) return false
        if (this.estado === 'verificados' && !f.verificado) return false
        if (!texto) return true
        return String(f.cliente || '').toLowerCase().includes(texto) ||
          String(f.nit || '').includes(texto) ||
          String(f.factura_id).includes(texto) ||
          String(f.pedido || '').includes(texto)
      })
    },
    sumaMontos () {
      return this.visibles.reduce((total, f) => total + (Number(this.montos[f.factura_id]) || 0), 0)
    },
    avance () {
      return this.totales.comprobantes ? this.totales.verificados / this.totales.comprobantes : 0
    }
  },
  created () {
    this.cargar()
  },
  methods: {
    verVentas (fila) {
      if (!fila.cliente_id || this.ocupado) return
      this.clienteVentas = fila
      this.ventas = []
      this.paginasVentas = 1
      this.dialogVentas = true
      this.cargarVentas(1)
    },
    async cargarVentas (pagina) {
      const solicitud = ++this.solicitudVentas
      this.paginaVentas = pagina
      this.cargandoVentas = true
      this.errorVentas = ''
      this.ventas = []
      try {
        const { data } = await this.$api.get('cobranzas/verificacion/clientes/' + this.clienteVentas.cliente_id + '/ventas', { params: { page: pagina } })
        if (solicitud !== this.solicitudVentas) return
        this.ventas = data.data
        this.paginasVentas = data.last_page
      } catch (e) {
        if (solicitud === this.solicitudVentas) this.errorVentas = this.mensaje(e)
      } finally {
        if (solicitud === this.solicitudVentas) this.cargandoVentas = false
      }
    },
    porcentaje (c) { return c.entregados ? Math.round(c.entregados_verificados * 100 / c.entregados) : 0 },
    alternarCamion (placa) {
      this.camion = this.camion === placa ? null : placa
      this.cargar()
    },
    money (v) { return Number(v || 0).toFixed(2) },
    nombrePlaca (placa) { return placa === 'SIN' ? 'Sin camión' : placa },
    suma (campo) { return this.visibles.reduce((total, f) => total + (Number(f[campo]) || 0), 0) },
    // Lo que se verifico, o lo que se va a verificar, contra el importe.
    diferencia (fila) { return (Number(this.montos[fila.factura_id]) || 0) - fila.facturado },
    claseDiferencia (fila) {
      const dif = this.diferencia(fila)
      if (Math.abs(dif) < 0.01) return 'text-grey-6'
      return dif < 0 ? 'text-red-9 text-weight-bold' : 'text-orange-9 text-weight-bold'
    },
    colorEntrega (entrega) {
      if (String(entrega).startsWith('ENTREGADO')) return 'text-green-8'
      if (String(entrega).startsWith('RETORNO')) return 'text-deep-orange-8'
      if (String(entrega).startsWith('PENDIENTE')) return 'text-grey-6'
      return 'text-red-8'
    },
    estiloColor (color) {
      const estilo = (color || '').trim()
      const hex = /#([0-9a-f]{6})/i.exec(estilo)
      if (!hex) return 'background-color: #ECEFF1; color: #37474F'
      const valor = parseInt(hex[1], 16)
      const luz = (0.299 * ((valor >> 16) & 255) + 0.587 * ((valor >> 8) & 255) + 0.114 * (valor & 255)) / 255
      return estilo.replace(/;\s*$/, '') + '; color: ' + (luz > 0.6 ? '#212121' : '#FFFFFF')
    },
    mensaje (e) { return e.response?.data?.message || 'No se pudo completar la operación' },
    params () { return { fecha: this.fecha, camion: this.camion || '' } },
    cambiarFecha () {
      this.camion = null
      this.cargar()
    },
    // El monto arranca con lo ya verificado; si no, con lo que trajo el
    // camion; y si todavia no hay entrega, con el importe del comprobante.
    montoInicial (fila) {
      if (fila.monto_verificado !== null) return fila.monto_verificado
      if (fila.recogido !== null) return fila.recogido
      return fila.facturado
    },
    async cargar () {
      if (this.ocupado) return
      this.cargando = true
      this.error = ''
      try {
        const { data } = await this.$api.get('cobranzas/verificacion', { params: this.params() })
        this.filas = data.filas
        this.totales = data.totales
        this.camiones = data.camiones
        const montos = {}
        data.filas.forEach(f => { montos[f.factura_id] = this.montoInicial(f) })
        this.montos = montos
        this.actualizado = date.formatDate(new Date(), 'HH:mm:ss')
      } catch (e) {
        this.error = this.mensaje(e)
      } finally {
        this.cargando = false
      }
    },
    async marcar (fila, verificado) {
      if (this.ocupado || this.error) return
      const monto = Number(this.montos[fila.factura_id])
      if (verificado && !(monto >= 0)) {
        this.$q.notify({ type: 'warning', position: 'top', message: 'Escribí el monto recibido' })
        return
      }
      this.guardando = fila.factura_id
      try {
        const { data } = await this.$api.post('cobranzas/verificacion', {
          factura_id: fila.factura_id, verificado, monto: verificado ? monto : null
        })
        // Se reemplaza la fila y se recalculan los totales sin volver a pedir todo.
        const indice = this.filas.findIndex(f => f.factura_id === fila.factura_id)
        if (indice >= 0 && data.fila) this.filas.splice(indice, 1, data.fila)
        this.recalcular()
        this.actualizado = date.formatDate(new Date(), 'HH:mm:ss')
        this.$q.notify({ type: verificado ? 'positive' : 'info', position: 'top', message: data.message, timeout: 1200 })
      } catch (e) {
        this.$q.notify({ type: 'negative', position: 'top', message: this.mensaje(e) })
      } finally {
        this.guardando = null
      }
    },
    // Si ya estaba tildado y se corrige el monto, se guarda de nuevo.
    corregirMonto (fila) {
      if (fila.verificado) this.marcar(fila, true)
    },
    recalcular () {
      const verificadas = this.filas.filter(f => f.verificado)
      const suma = (lista, campo) => lista.reduce((t, f) => t + (Number(f[campo]) || 0), 0)
      this.totales = {
        comprobantes: this.filas.length,
        verificados: verificadas.length,
        facturado: suma(this.filas, 'facturado'),
        recogido: suma(this.filas, 'recogido'),
        verificado: suma(verificadas, 'monto_verificado'),
        diferencia: suma(verificadas, 'monto_verificado') - suma(verificadas, 'facturado')
      }
      this.camiones = this.camiones.map(c => this.camion && c.placa !== this.camion ? c : ({
        ...c,
        entregados: this.filas.filter(f => f.placa === c.placa && f.entregado).length,
        entregados_verificados: this.filas.filter(f => f.placa === c.placa && f.entregado && f.verificado).length,
        verificados: this.filas.filter(f => f.placa === c.placa && f.verificado).length
      }))
    },
    async exportar (tipo) {
      this.exportando = tipo
      try {
        const res = await this.$api.get('cobranzas/verificacion/' + tipo, { params: this.params(), responseType: 'blob' })
        const url = window.URL.createObjectURL(res.data)
        const enlace = document.createElement('a')
        enlace.href = url
        enlace.download = 'verificacion_' + this.fecha + (this.camion ? '_' + this.camion.replace(/\s+/g, '_') : '') +
          (tipo === 'excel' ? '.xlsx' : '.pdf')
        enlace.click()
        window.URL.revokeObjectURL(url)
      } catch (e) {
        let mensaje = 'No se pudo exportar'
        try { mensaje = JSON.parse(await e.response.data.text()).message || mensaje } catch (x) { /* no era JSON */ }
        this.$q.notify({ type: 'negative', position: 'top', message: mensaje })
      } finally {
        this.exportando = null
      }
    }
  }
}
</script>

<style scoped>
.contenido { border: 0; margin: 0; padding: 0; min-width: 0; }
.carga-pagina { position: fixed; z-index: 2000; }
.camion-fila {
  border: 0;
  background: transparent;
  padding: 3px 8px;
  cursor: pointer;
  font: inherit;
  text-align: left;
}
.camion-fila:hover { background: #e3f2fd; }
.camion-placa { width: 110px; }
.camion-conteo { min-width: 95px; text-align: right; font-size: 12px; font-weight: 700; }
.tarjeta {
  padding: 6px 10px;
}
.tarjeta-rotulo {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.4px;
}
.tarjeta-valor {
  font-size: 17px;
  font-weight: 800;
  line-height: 1.2;
}
.tabla :deep(td) {
  font-size: 12px;
}
.fila-ok {
  background: #e8f5e9;
}
.fila-total td {
  background: #eceff1;
  font-weight: 800;
}
.chip-placa {
  display: inline-block;
  padding: 1px 6px;
  border-radius: 3px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}
</style>
