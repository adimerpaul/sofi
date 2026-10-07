<template>
  <q-page class="q-pa-xs relative-position">
    <div class="row items-center q-gutter-xs q-mb-xs">
      <div class="col-auto text-subtitle1 text-weight-bold">Verificar por cliente</div>
      <q-space/>
      <!-- Lo que verifico cada usuario entre fecha y hora, como el cierre de caja de creditos. -->
      <q-btn-dropdown unelevated dense no-caps size="sm" color="green-8" icon="grid_on" label="Excel verificaciones"
                      @show="cargarVerificadores">
        <div class="q-pa-sm column q-gutter-sm" style="min-width: 280px">
          <div class="text-caption text-grey-8">Verificado entre:</div>
          <div class="row q-col-gutter-xs">
            <div class="col-7"><q-input v-model="reporte.desde" type="date" dense outlined label="Desde" @update:model-value="cargarVerificadores"/></div>
            <div class="col-5"><q-input v-model="reporte.horaDesde" type="time" dense outlined label="Hora" @update:model-value="cargarVerificadores"/></div>
            <div class="col-7"><q-input v-model="reporte.hasta" type="date" dense outlined label="Hasta" @update:model-value="cargarVerificadores"/></div>
            <div class="col-5"><q-input v-model="reporte.horaHasta" type="time" dense outlined label="Hora" @update:model-value="cargarVerificadores"/></div>
          </div>
          <q-select
            v-model="reporte.usuario" :options="verificadores" dense outlined clearable emit-value map-options
            option-value="user_id" :option-label="v => v.nombre + ' · ' + v.verificados + ' verif. · Bs ' + money(v.total)"
            label="Usuario que verificó (vacío = todos)" :loading="cargandoVerificadores"
          >
            <template #no-option>
              <q-item><q-item-section class="text-grey">Nadie verificó en ese rango</q-item-section></q-item>
            </template>
          </q-select>
          <q-btn unelevated dense no-caps color="teal-8" icon="qr_code_2" label="Excel QR verificados"
                 :loading="descargandoReporte === 'qr'" :disable="descargandoReporte !== null" @click="descargarReporte('qr')">
            <q-tooltip>Solo lo verificado por QR, con el formato de cobros QR de créditos</q-tooltip>
          </q-btn>
          <q-btn unelevated dense no-caps color="indigo-7" icon="point_of_sale" label="Total verificados"
                 :loading="descargandoReporte === 'total'" :disable="descargandoReporte !== null" @click="descargarReporte('total')">
            <q-tooltip>Total de lo verificado por usuario (QR, efectivo y crédito), con el formato del cierre de caja</q-tooltip>
          </q-btn>
        </div>
      </q-btn-dropdown>
      <q-btn flat dense no-caps size="sm" color="primary" icon="fact_check" label="Verificación del día" to="/cobranzas/verificacion"/>
    </div>

    <q-select
      v-model="cliente" use-input fill-input hide-selected input-debounce="350" dense outlined clearable
      :options="opcionesClientes" option-value="id" option-label="nombre" :loading="buscando"
      label="Buscar cliente por nombre o NIT" class="q-mb-xs"
      @filter="buscarClientes" @update:model-value="elegirCliente"
    >
      <template #prepend><q-icon name="search" size="xs"/></template>
      <template #option="scope">
        <q-item v-bind="scope.itemProps" dense>
          <q-item-section>
            <q-item-label>{{ scope.opt.nombre }}</q-item-label>
            <q-item-label caption>NIT {{ scope.opt.nit || '—' }} · {{ scope.opt.comprobantes }} compras</q-item-label>
          </q-item-section>
          <q-item-section side>
            <q-badge v-if="scope.opt.pendientes" color="orange-8" :label="scope.opt.pendientes + ' por verificar'"/>
            <q-badge v-else-if="scope.opt.comprobantes" color="positive" label="Al día"/>
          </q-item-section>
        </q-item>
      </template>
      <template #no-option>
        <q-item><q-item-section class="text-grey">Escribí al menos 2 letras del nombre o NIT</q-item-section></q-item>
      </template>
    </q-select>

    <div v-if="!cliente" class="text-center text-grey-6 q-pa-lg">
      <q-icon name="person_search" size="48px"/>
      <div>Elegí un cliente para ver sus compras</div>
    </div>

    <template v-else>
      <fieldset class="contenido" :disabled="ocupado" :inert="ocupado || undefined">
      <!-- Resumen y filtros en una sola franja. -->
      <div class="row q-col-gutter-xs items-center q-mb-xs">
        <div class="col-6 col-sm-3 col-md-2">
          <div class="tarjeta bg-blue-grey-1"><span class="tarjeta-rotulo text-blue-grey-8">FACTURADO</span>
            <span class="tarjeta-valor text-blue-grey-10">{{ money(totales.facturado) }}</span></div>
        </div>
        <div class="col-6 col-sm-3 col-md-2">
          <div class="tarjeta bg-green-1"><span class="tarjeta-rotulo text-green-8">VERIFICADO</span>
            <span class="tarjeta-valor text-green-10">{{ money(totales.verificado) }}</span></div>
        </div>
        <div class="col-6 col-sm-3 col-md-2">
          <div class="tarjeta bg-red-1"><span class="tarjeta-rotulo text-red-8">POR VERIFICAR</span>
            <span class="tarjeta-valor text-red-10">{{ money(totales.por_verificar) }}</span></div>
        </div>
        <div class="col-6 col-sm-3 col-md-2">
          <div class="tarjeta bg-orange-1 row items-center no-wrap">
            <span class="tarjeta-valor text-orange-10">{{ totales.verificados }}/{{ totales.comprobantes }}</span>
            <q-linear-progress class="col q-ml-sm" rounded size="8px" :value="avance" color="positive" track-color="orange-2"/>
          </div>
        </div>
        <div class="col-6 col-md">
          <q-input v-model="desde" type="date" dense outlined clearable label="Desde" class="filtro" @update:model-value="cargar(1)"/>
        </div>
        <div class="col-6 col-md">
          <q-input v-model="hasta" type="date" dense outlined clearable label="Hasta" class="filtro" @update:model-value="cargar(1)"/>
        </div>
      </div>
      <div class="row items-center q-mb-xs">
        <q-btn-toggle
          v-model="estado" no-caps unelevated dense size="sm" toggle-color="primary" color="grey-3" text-color="grey-9"
          :options="[
            { label: 'Por verificar (' + (totales.comprobantes - totales.verificados) + ')', value: 'pendientes' },
            { label: 'Verificados (' + totales.verificados + ')', value: 'verificados' },
            { label: 'Todos', value: 'todos' }
          ]"
          @update:model-value="cargar(1)"
        />
        <q-space/>
        <span class="text-caption text-grey-7 q-mr-xs">
          <q-icon name="payments" color="green-8"/> Efectivo · <q-icon name="qr_code_2" color="deep-purple-6"/> QR
        </span>
        <q-btn flat dense round size="sm" icon="refresh" color="primary" :loading="cargando" @click="cargar(pagina)"/>
      </div>

      <q-banner v-if="error" dense class="bg-red-1 text-negative q-mb-xs">{{ error }}</q-banner>

      <q-table
        flat bordered dense :rows="filas" :columns="columnas" row-key="factura_id" :loading="cargando"
        :pagination="{ rowsPerPage: 0 }" hide-bottom :grid="$q.screen.lt.md"
        no-data-label="No hay compras con ese filtro" class="tabla"
      >
        <template #body="props">
          <q-tr :props="props" :class="props.row.verificado ? 'fila-ok' : ''">
            <q-td key="fecha" :props="props">
              {{ props.row.fecha }} <span class="text-grey-6">{{ props.row.hora }}</span>
            </q-td>
            <q-td key="comprobante" :props="props">
              <b>{{ props.row.tipo_comprobante === 'FACTURA' ? 'F' : 'V' }} #{{ props.row.factura_id }}</b>
              <span class="text-grey-7"> · P{{ props.row.pedido || '—' }}</span>
            </q-td>
            <q-td key="placa" :props="props">
              <span class="chip-placa" :style="estiloColor(props.row.placa_color)">{{ nombrePlaca(props.row.placa) }}</span>
            </q-td>
            <q-td key="pago" :props="props">
              <div class="row items-center no-wrap">
                <q-icon v-for="i in iconosPago(props.row.forma_pago)" :key="i.icon" :name="i.icon" :color="i.color" size="18px">
                  <q-tooltip>{{ i.label }}</q-tooltip>
                </q-icon>
                <span class="text-caption q-ml-xs ellipsis" :class="colorEntrega(props.row.entrega)" style="max-width: 120px">
                  {{ props.row.entrega }}
                  <q-tooltip>{{ props.row.forma_pago }} · {{ props.row.entrega }}</q-tooltip>
                </span>
              </div>
            </q-td>
            <q-td key="facturado" :props="props" class="text-weight-bold">{{ money(props.row.facturado) }}</q-td>
            <q-td key="recogido" :props="props">
              <template v-if="props.row.recogido !== null">
                <span :class="props.row.recogido < props.row.facturado ? 'text-orange-9' : 'text-indigo-9'">{{ money(props.row.recogido) }}</span>
                <q-tooltip v-if="props.row.recogido_efectivo !== null">
                  Efectivo {{ money(props.row.recogido_efectivo) }} · QR {{ money(props.row.recogido_qr) }}
                </q-tooltip>
              </template>
              <span v-else class="text-grey-5">—</span>
            </q-td>
            <q-td v-for="lado in LADOS" :key="lado.key" :props="props" class="celda-monto">
              <div v-if="props.row.lados.includes(lado.key)" class="row items-center no-wrap">
                <q-input
                  v-model.number="montos[props.row.factura_id][lado.key]" type="number" step="0.01" min="0" dense outlined
                  hide-bottom-space input-class="text-right" class="monto col"
                  :bg-color="props.row[lado.key + '_ok'] ? 'green-1' : 'white'" @change="corregirMonto(props.row, lado.key)"
                >
                  <template #prepend><q-icon :name="lado.icon" :color="lado.color" size="16px"/></template>
                </q-input>
                <q-checkbox
                  :model-value="props.row[lado.key + '_ok']" dense color="positive" class="q-ml-xs"
                  :disable="guardando === props.row.factura_id" @update:model-value="v => marcar(props.row, lado.key, v)"
                >
                  <q-tooltip>
                    {{ props.row[lado.key + '_ok'] ? lado.label + ' verificado · ' + props.row[lado.key + '_por'] + ' · ' + props.row[lado.key + '_en'] : 'Verificar ' + lado.label }}
                  </q-tooltip>
                </q-checkbox>
              </div>
            </q-td>
            <q-td key="diferencia" :props="props">
              <span :class="claseDiferencia(props.row)">{{ money(diferencia(props.row)) }}</span>
            </q-td>
            <q-td key="estado" :props="props" class="text-center">
              <q-icon v-if="props.row.verificado" name="check_circle" color="positive" size="20px">
                <q-tooltip>Verificado · {{ props.row.verificado_por }} · {{ props.row.verificado_en }}</q-tooltip>
              </q-icon>
              <q-badge v-else-if="props.row.efectivo_ok || props.row.qr_ok" color="orange-8" :label="'Falta ' + (props.row.qr_ok ? 'efectivo' : 'QR')"/>
              <q-icon v-else name="radio_button_unchecked" color="grey-5" size="20px"/>
            </q-td>
          </q-tr>
        </template>

        <!-- En el celular: una tarjeta compacta por compra. -->
        <template #item="props">
          <div class="col-12 q-pa-xs">
            <q-card flat bordered :class="props.row.verificado ? 'fila-ok' : ''">
              <div class="q-px-sm q-py-xs">
                <div class="row items-center no-wrap">
                  <div class="col ellipsis">
                    <b>{{ props.row.tipo_comprobante === 'FACTURA' ? 'F' : 'V' }} #{{ props.row.factura_id }}</b>
                    <span class="text-caption text-grey-7"> · {{ props.row.fecha }} {{ props.row.hora }} · P{{ props.row.pedido || '—' }}</span>
                  </div>
                  <q-icon v-for="i in iconosPago(props.row.forma_pago)" :key="i.icon" :name="i.icon" :color="i.color" size="18px"/>
                  <q-icon v-if="props.row.verificado" name="check_circle" color="positive" size="20px" class="q-ml-xs"/>
                  <q-badge v-else-if="props.row.efectivo_ok || props.row.qr_ok" class="q-ml-xs" color="orange-8" :label="'Falta ' + (props.row.qr_ok ? 'efectivo' : 'QR')"/>
                </div>
                <div class="row items-center q-gutter-x-xs text-caption">
                  <span class="chip-placa" :style="estiloColor(props.row.placa_color)">{{ nombrePlaca(props.row.placa) }}</span>
                  <span :class="colorEntrega(props.row.entrega)">{{ props.row.entrega }}</span>
                  <q-space/>
                  <span>Imp. <b>{{ money(props.row.facturado) }}</b></span>
                  <span class="text-indigo-9">Rec. <b>{{ props.row.recogido !== null ? money(props.row.recogido) : '—' }}</b></span>
                </div>
                <div class="row q-col-gutter-xs items-center q-mt-xs">
                  <div v-for="lado in LADOS.filter(l => props.row.lados.includes(l.key))" :key="lado.key"
                       :class="props.row.lados.length > 1 ? 'col-5' : 'col-10'">
                    <div class="row items-center no-wrap">
                      <q-input
                        v-model.number="montos[props.row.factura_id][lado.key]" type="number" step="0.01" min="0" dense outlined
                        hide-bottom-space input-class="text-right" class="monto col"
                        :bg-color="props.row[lado.key + '_ok'] ? 'green-1' : 'white'" @change="corregirMonto(props.row, lado.key)"
                      >
                        <template #prepend><q-icon :name="lado.icon" :color="lado.color" size="16px"/></template>
                      </q-input>
                      <q-checkbox
                        :model-value="props.row[lado.key + '_ok']" color="positive"
                        :disable="guardando === props.row.factura_id" @update:model-value="v => marcar(props.row, lado.key, v)"
                      />
                    </div>
                  </div>
                  <div class="col-2 text-right text-caption" :class="claseDiferencia(props.row)">{{ money(diferencia(props.row)) }}</div>
                </div>
              </div>
            </q-card>
          </div>
        </template>
      </q-table>

      <div class="row items-center justify-center q-mt-xs q-gutter-sm">
        <q-pagination :model-value="pagina" :max="paginas" :max-pages="6" size="sm" :disable="ocupado" @update:model-value="cargar"/>
        <div class="text-caption text-grey-7">{{ total }} compras</div>
      </div>
      </fieldset>
    </template>

    <q-inner-loading :showing="ocupado" class="carga-pagina">
      <q-spinner color="primary" size="48px"/>
      <div class="q-mt-sm text-primary text-weight-bold" role="status">{{ guardando !== null ? 'Guardando verificación…' : 'Cargando compras…' }}</div>
    </q-inner-loading>
  </q-page>
</template>

<script>
import { date } from 'quasar'

const ICONOS = {
  efectivo: { icon: 'payments', color: 'green-8', label: 'Efectivo' },
  qr: { icon: 'qr_code_2', color: 'deep-purple-6', label: 'QR' },
  credito: { icon: 'credit_score', color: 'orange-9', label: 'Crédito' }
}

export default {
  name: 'VerificarCliente',
  data () {
    return {
      cliente: null,
      opcionesClientes: [],
      buscando: false,
      desde: null,
      hasta: null,
      estado: 'pendientes',
      pagina: 1,
      paginas: 1,
      total: 0,
      solicitud: 0,
      cargando: false,
      guardando: null,
      error: '',
      filas: [],
      // Por comprobante: { efectivo, qr } escritos por cobranzas.
      montos: {},
      totales: { comprobantes: 0, verificados: 0, facturado: 0, verificado: 0, por_verificar: 0 },
      // Reporte por usuario: por defecto hoy de 00:00 a 23:59, todos los usuarios.
      reporte: {
        desde: date.formatDate(new Date(), 'YYYY-MM-DD'),
        horaDesde: '00:00',
        hasta: date.formatDate(new Date(), 'YYYY-MM-DD'),
        horaHasta: '23:59',
        usuario: null
      },
      verificadores: [],
      cargandoVerificadores: false,
      descargandoReporte: null,
      columnas: [
        { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left' },
        { name: 'comprobante', label: 'Comprobante', field: 'factura_id', align: 'left' },
        { name: 'placa', label: 'Camión', field: 'placa', align: 'left' },
        { name: 'pago', label: 'Pago / Entrega', field: 'forma_pago', align: 'left' },
        { name: 'facturado', label: 'Importe', field: 'facturado', align: 'right' },
        { name: 'recogido', label: 'Recogido', field: 'recogido', align: 'right' },
        { name: 'efectivo', label: 'Verificar efectivo', field: 'verificado_efectivo', align: 'left' },
        { name: 'qr', label: 'Verificar QR', field: 'verificado_qr', align: 'left' },
        { name: 'diferencia', label: 'Dif.', field: 'diferencia', align: 'right' },
        { name: 'estado', label: 'Estado', field: 'verificado', align: 'center' }
      ]
    }
  },
  created () {
    this.LADOS = [
      { key: 'efectivo', ...ICONOS.efectivo },
      { key: 'qr', ...ICONOS.qr }
    ]
  },
  computed: {
    ocupado () { return this.cargando || this.guardando !== null },
    avance () {
      return this.totales.comprobantes ? this.totales.verificados / this.totales.comprobantes : 0
    }
  },
  methods: {
    buscarClientes (texto, update, abort) {
      const buscar = (texto || '').trim()
      if (buscar.length < 2) {
        update(() => { this.opcionesClientes = [] })
        return
      }
      this.buscando = true
      this.$api.get('cobranzas/verificacion/clientes', { params: { buscar } })
        .then(({ data }) => update(() => { this.opcionesClientes = data }))
        .catch(e => {
          abort()
          this.$q.notify({ type: 'negative', position: 'top', message: this.mensaje(e) })
        })
        .finally(() => { this.buscando = false })
    },
    elegirCliente () {
      this.filas = []
      this.montos = {}
      this.error = ''
      if (this.cliente) this.cargar(1)
    },
    async cargar (pagina) {
      if (!this.cliente || this.guardando !== null) return
      const solicitud = ++this.solicitud
      this.pagina = pagina
      this.cargando = true
      this.error = ''
      try {
        const { data } = await this.$api.get('cobranzas/verificacion/clientes/' + this.cliente.id + '/facturas', {
          params: { page: pagina, estado: this.estado, desde: this.desde || undefined, hasta: this.hasta || undefined }
        })
        if (solicitud !== this.solicitud) return
        const montos = {}
        data.filas.forEach(f => { montos[f.factura_id] = this.montosIniciales(f) })
        this.montos = montos
        this.filas = data.filas
        this.totales = data.totales
        this.paginas = data.paginas
        this.total = data.total
      } catch (e) {
        if (solicitud === this.solicitud) this.error = this.mensaje(e)
      } finally {
        if (solicitud === this.solicitud) this.cargando = false
      }
    },
    // De cada lado, lo ya escrito; si no, lo esperado: lo que trajo el
    // camion o, si todavia no hay entrega, el importe segun como se pago.
    montosIniciales (fila) {
      return {
        efectivo: fila.verificado_efectivo ?? fila.esperado_efectivo,
        qr: fila.verificado_qr ?? fila.esperado_qr
      }
    },
    // Tilda o destilda un lado: el efectivo o el QR, con su monto.
    async marcar (fila, lado, verificado) {
      if (this.ocupado || this.error) return
      const monto = this.montos[fila.factura_id][lado]
      if (verificado && (monto === '' || monto === null || monto === undefined || !(Number(monto) >= 0))) {
        this.$q.notify({ type: 'warning', position: 'top', message: 'Escribí el monto recibido en ' + (lado === 'qr' ? 'QR' : 'efectivo') })
        return
      }
      this.guardando = fila.factura_id
      try {
        const { data } = await this.$api.post('cobranzas/verificacion', {
          factura_id: fila.factura_id, verificado, lado, monto: Number(monto) || 0
        })
        const indice = this.filas.findIndex(f => f.factura_id === fila.factura_id)
        if (indice >= 0 && data.fila) {
          const anterior = this.filas[indice]
          this.filas.splice(indice, 1, data.fila)
          this.ajustarTotales(anterior, data.fila)
        }
        this.$q.notify({ type: verificado ? 'positive' : 'info', position: 'top', message: data.message, timeout: 1200 })
      } catch (e) {
        this.$q.notify({ type: 'negative', position: 'top', message: this.mensaje(e) })
      } finally {
        this.guardando = null
      }
    },
    // Los totales son de todas las compras del cliente (no solo de esta
    // pagina), asi que se ajustan con la diferencia de la fila cambiada.
    ajustarTotales (antes, ahora) {
      const t = { ...this.totales }
      if (antes.verificado) {
        t.verificados--
        t.verificado -= Number(antes.monto_verificado) || 0
        t.por_verificar += antes.facturado
      }
      if (ahora.verificado) {
        t.verificados++
        t.verificado += Number(ahora.monto_verificado) || 0
        t.por_verificar -= ahora.facturado
      }
      this.totales = t
    },
    paramsReporte () {
      const { desde, hasta, horaDesde, horaHasta } = this.reporte
      return { desde, hasta, hora_desde: horaDesde || '00:00', hora_hasta: horaHasta || '23:59' }
    },
    // Quienes verificaron en el rango, para elegir el usuario del reporte.
    async cargarVerificadores () {
      if (!this.reporte.desde || !this.reporte.hasta) return
      this.cargandoVerificadores = true
      try {
        const { data } = await this.$api.get('cobranzas/verificacion/verificadores', { params: this.paramsReporte() })
        this.verificadores = data
        // Si el usuario elegido no verifico en el rango nuevo, se suelta.
        const usuario = this.reporte.usuario
        if (usuario && !data.some(v => v.user_id === usuario)) this.reporte.usuario = null
      } catch (e) {
        this.verificadores = []
      } finally {
        this.cargandoVerificadores = false
      }
    },
    // 'qr': lo verificado por QR como el Excel de cobros QR; 'total': el cierre por usuario.
    async descargarReporte (tipo) {
      const { desde, hasta, horaDesde, horaHasta, usuario } = this.reporte
      if (!desde || !hasta || (desde + ' ' + (horaDesde || '00:00')) > (hasta + ' ' + (horaHasta || '23:59'))) {
        this.$q.notify({ type: 'warning', position: 'top', message: 'Revisá el rango de fechas y horas' })
        return
      }
      this.descargandoReporte = tipo
      try {
        const params = this.paramsReporte()
        if (usuario) params.user_id = usuario
        const { data } = await this.$api.get('cobranzas/verificacion/excel-' + tipo, { params, responseType: 'blob' })
        const url = URL.createObjectURL(data)
        const a = document.createElement('a')
        a.href = url
        a.download = tipo === 'qr'
          ? 'VERIFICADOS QR ' + desde + (hasta !== desde ? ' AL ' + hasta : '') + '.xlsx'
          : 'CIERRE VERIFICACIONES ' + desde + '.xlsx'
        a.click()
        URL.revokeObjectURL(url)
      } catch (e) {
        this.$q.notify({ type: 'negative', position: 'top', message: 'No se pudo generar el Excel' })
      } finally {
        this.descargandoReporte = null
      }
    },
    // Si ese lado ya estaba tildado y se corrige el monto, se guarda de nuevo.
    corregirMonto (fila, lado) {
      if (fila[lado + '_ok']) this.marcar(fila, lado, true)
    },
    iconosPago (forma) {
      if (forma === 'MIXTO') return [ICONOS.efectivo, ICONOS.qr]
      if (forma === 'QR') return [ICONOS.qr]
      if (forma === 'CRÉDITO') return [ICONOS.credito]
      return [ICONOS.efectivo]
    },
    money (v) { return Number(v || 0).toFixed(2) },
    nombrePlaca (placa) { return placa === 'SIN' ? 'Sin camión' : placa },
    // Lo escrito en los lados que se verifican, contra el importe.
    diferencia (fila) {
      const m = this.montos[fila.factura_id] || {}
      return fila.lados.reduce((suma, lado) => suma + (Number(m[lado]) || 0), 0) - fila.facturado
    },
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
    mensaje (e) { return e.response?.data?.message || 'No se pudo completar la operación' }
  }
}
</script>

<style scoped>
.contenido { border: 0; margin: 0; padding: 0; min-width: 0; }
.carga-pagina { position: fixed; z-index: 2000; }
.tarjeta { padding: 3px 8px; border-radius: 4px; line-height: 1.2; }
.tarjeta-rotulo { font-size: 9px; font-weight: 700; letter-spacing: 0.4px; display: block; }
.tarjeta-valor { font-size: 14px; font-weight: 800; }
.filtro :deep(.q-field__control), .filtro :deep(.q-field__marginal) { height: 34px; min-height: 34px; }
.tabla :deep(th) { font-size: 11px; padding: 2px 6px; }
.tabla :deep(td) { font-size: 12px; padding: 1px 6px; height: 30px; white-space: nowrap; }
.celda-monto { width: 132px; }
.monto :deep(.q-field__control), .monto :deep(.q-field__marginal) { height: 26px; min-height: 26px; }
.monto :deep(.q-field__control) { padding: 0 4px; }
.monto :deep(.q-field__prepend) { padding-right: 2px; }
.monto :deep(input) { font-size: 12px; padding: 0; }
.fila-ok { background: #e8f5e9; }
.chip-placa {
  display: inline-block;
  padding: 0 5px;
  border-radius: 3px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}
</style>
