<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-xs">
      <div class="text-subtitle1 text-weight-bold">Verificación de facturas</div>
      <div class="text-caption text-grey-7 q-ml-sm gt-xs">Libro de ventas del SIAT contra Sofia, por CUF</div>
      <q-space />
      <q-btn dense outline size="sm" color="green-8" icon="grid_on" label="Exportar Excel" no-caps class="q-px-sm q-mr-xs" :loading="exportando" @click="exportarExcel">
        <q-tooltip>Libro del SIAT y ventas facturadas en Sofia del mes elegido (dos hojas)</q-tooltip>
      </q-btn>
      <q-btn dense unelevated size="sm" color="primary" icon="upload_file" label="Subir libro del SIAT" no-caps class="q-px-sm" @click="dialogImportar = true" />
    </div>

    <div class="kpi-row q-mb-xs">
      <q-card flat bordered class="kpi-card">
        <q-card-section class="kpi-body">
          <q-avatar icon="receipt_long" color="blue-1" text-color="primary" size="26px" font-size="15px" />
          <div class="q-ml-sm ellipsis"><div class="kpi-label">Facturas en el SIAT</div><div class="kpi-value">{{ resumen.cantidad }} <span class="kpi-sub">· {{ resumen.anuladas }} anuladas</span></div></div>
        </q-card-section>
      </q-card>
      <q-card flat bordered class="kpi-card">
        <q-card-section class="kpi-body">
          <q-avatar icon="payments" color="green-1" text-color="positive" size="26px" font-size="15px" />
          <div class="q-ml-sm ellipsis"><div class="kpi-label">Importe válido</div><div class="kpi-value">Bs {{ money(resumen.importe_total) }} <span class="kpi-sub">· débito {{ money(resumen.debito_fiscal) }}</span></div></div>
        </q-card-section>
      </q-card>
      <q-card flat bordered class="kpi-card kpi-click" @click="irTab('sin_registrar')">
        <q-card-section class="kpi-body">
          <q-avatar icon="report_problem" color="red-1" text-color="negative" size="26px" font-size="15px" />
          <div class="q-ml-sm ellipsis"><div class="kpi-label">En el SIAT, no en Sofia</div><div class="kpi-value text-negative">{{ resumen.sin_registrar }} <span class="kpi-sub">· Bs {{ money(resumen.importe_sin_registrar) }}</span></div></div>
        </q-card-section>
        <q-tooltip>Facturas que Impuestos tiene y Sofia no. Clic para verlas.</q-tooltip>
      </q-card>
      <q-card flat bordered class="kpi-card kpi-click" @click="irTab('no_impuestos')">
        <q-card-section class="kpi-body">
          <q-avatar icon="cloud_off" color="orange-1" text-color="deep-orange" size="26px" font-size="15px" />
          <div class="q-ml-sm ellipsis"><div class="kpi-label">En Sofia, no en el SIAT</div><div class="kpi-value text-deep-orange">{{ resumen.no_en_impuestos }} <span class="kpi-sub">· {{ resumen.diferencias }} con diferencias</span></div></div>
        </q-card-section>
        <q-tooltip>Facturas de Sofia con CUF que no aparecen en el libro subido. Clic para verlas.</q-tooltip>
      </q-card>
    </div>

    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-xs q-pa-xs items-center filtros">
        <q-input v-model="filtros.q" dense outlined clearable debounce="400" class="col-12 col-sm" placeholder="Buscar Nº factura, CUF, NIT o razón social" @update:model-value="recargar">
          <template #prepend><q-icon name="search" size="18px" /></template>
        </q-input>
        <div class="col-12 col-sm-auto row no-wrap items-center">
          <q-btn dense flat round size="sm" color="primary" icon="chevron_left" @click="moverMes(-1)"><q-tooltip>Mes anterior</q-tooltip></q-btn>
          <q-input v-model="filtros.mes" dense outlined type="month" label="Mes" stack-label class="mes-input" @update:model-value="recargar" />
          <q-btn dense flat round size="sm" color="primary" icon="chevron_right" @click="moverMes(1)"><q-tooltip>Mes siguiente</q-tooltip></q-btn>
          <q-btn dense flat round size="sm" color="primary" icon="today" @click="filtros.mes = mesDe(0); recargar()"><q-tooltip>Último mes cerrado</q-tooltip></q-btn>
        </div>
        <q-select v-if="tab !== 'no_impuestos'" v-model="filtros.estado" :options="['VALIDA', 'ANULADA']" dense options-dense outlined clearable label="Estado SIAT" class="col-6 col-sm-2" @update:model-value="recargar" />
      </q-card-section>

      <q-tabs v-model="tab" dense no-caps inline-label align="left" active-color="primary" indicator-color="primary" class="text-grey-8 tabs" @update:model-value="cambiarTab">
        <q-tab name="todas" icon="receipt_long" :label="`Libro SIAT (${resumen.cantidad})`" />
        <q-tab name="en_sistema" icon="check_circle" :label="`Coinciden (${resumen.en_sistema})`" />
        <q-tab name="diferencias" icon="compare_arrows" class="text-amber-9" :label="`Con diferencias (${resumen.diferencias})`">
          <q-tooltip>Mismo CUF en ambos lados, pero distinto importe o anulada en un lado y válida en el otro</q-tooltip>
        </q-tab>
        <q-tab name="sin_registrar" icon="report_problem" class="text-negative" :label="`En SIAT, no en Sofia (${resumen.sin_registrar})`" />
        <q-tab name="no_impuestos" icon="cloud_off" class="text-deep-orange" :label="`En Sofia, no en SIAT (${resumen.no_en_impuestos})`" />
      </q-tabs>
      <q-separator />

      <q-table flat dense :rows="filas" :columns="columnas" row-key="cuf" :loading="loading" v-model:pagination="pagination"
               :rows-per-page-options="[20, 50, 100, 200]" class="tabla" @request="onRequest">
        <template #body-cell-fecha="p"><q-td :props="p">{{ fecha(p.row.fecha_factura || p.row.fecha) }}</q-td></template>
        <template #body-cell-cuf="p"><q-td :props="p"><span class="cuf">{{ p.row.cuf }}</span><q-tooltip>{{ p.row.cuf }}</q-tooltip></q-td></template>
        <template #body-cell-razon="p"><q-td :props="p"><div class="ellipsis razon">{{ p.row.razon_social || p.row.nombre }}</div></q-td></template>
        <template #body-cell-importe="p"><q-td :props="p" class="text-right text-weight-bold">{{ money(p.row.importe_total) }}</q-td></template>
        <template #body-cell-estado="p"><q-td :props="p"><q-badge :color="p.row.estado === 'VALIDA' ? 'positive' : 'grey-6'" :label="p.row.estado" /></q-td></template>
        <template #body-cell-sistema="p">
          <q-td :props="p">
            <template v-if="p.row.sistema">
              <q-badge :color="p.row.sistema.diferencias.length ? 'amber-9' : 'positive'"
                       :label="`${({ FICTICIA: 'Ficticia', CAJA: 'Caja' })[p.row.sistema.origen] || 'Factura'} ${p.row.sistema.nro_factura || '#' + p.row.sistema.id}`" />
              <q-tooltip>
                Sofia: Bs {{ money(p.row.sistema.total) }} · {{ p.row.sistema.estado }} · SIAT {{ p.row.sistema.estado_siat || '—' }}
                <span v-if="p.row.sistema.diferencias.length"><br>Diferencia en: {{ p.row.sistema.diferencias.join(', ') }}</span>
              </q-tooltip>
            </template>
            <q-badge v-else color="negative" label="No está en Sofia" />
          </q-td>
        </template>
        <template #body-cell-dif="p">
          <q-td :props="p" class="text-right">
            <span v-if="p.row.sistema && p.row.sistema.diferencias.length" class="text-amber-10 text-weight-bold">
              {{ difTexto(p.row) }}
            </span>
          </q-td>
        </template>
        <!-- Pestaña "En Sofia, no en SIAT": filas de facturas / facturas_ficticias -->
        <template #body-cell-origen="p"><q-td :props="p"><q-badge outline :color="p.row.origen === 'FICTICIA' ? 'purple' : 'primary'" :label="p.row.origen === 'FICTICIA' ? 'Ficticia' : 'Factura'" /></q-td></template>
        <template #body-cell-total="p"><q-td :props="p" class="text-right text-weight-bold">{{ money(p.row.total) }}</q-td></template>
        <template #body-cell-v_estado="p"><q-td :props="p"><q-badge :color="p.row.estado === 'ACTIVO' ? 'positive' : 'grey-6'" :label="p.row.estado" /></q-td></template>
        <template #body-cell-estado_siat="p">
          <q-td :props="p">
            <q-badge :color="colorSiat(p.row.estado_siat)" :label="p.row.estado_siat || '—'" />
            <q-tooltip v-if="p.row.mensaje_siat">{{ p.row.mensaje_siat }}</q-tooltip>
          </q-td>
        </template>
        <template #body-cell-acciones="p">
          <q-td :props="p">
            <q-btn v-if="p.row.url_siat" dense flat round size="sm" color="primary" icon="open_in_new" @click="abrir(p.row.url_siat)"><q-tooltip>Ver en Impuestos</q-tooltip></q-btn>
            <q-btn v-if="tab !== 'no_impuestos'" dense flat round size="sm" color="blue-grey" icon="info" @click="verDetalle(p.row)"><q-tooltip>Detalle del libro</q-tooltip></q-btn>
            <q-btn v-if="reenviable(p.row)" dense unelevated no-caps size="sm" color="deep-orange" icon="send" label="Reenviar"
                   class="q-ml-xs q-px-xs" :loading="reenviando === p.row.origen + p.row.id" :disable="!!reenviando" @click="reenviar(p.row)">
              <q-tooltip>Volver a mandar al SIAT con el mismo número y su fecha original (evento significativo)</q-tooltip>
            </q-btn>
            <q-btn v-if="anulable(p.row)" dense unelevated no-caps size="sm" color="negative" icon="block" label="Anular en Impuestos"
                   class="q-ml-xs q-px-xs" @click="pedirAnulacion(p.row)">
              <q-tooltip>Anula solo en el SIAT: esta factura no existe en Sofia</q-tooltip>
            </q-btn>
            <q-badge v-else-if="tab === 'no_impuestos' && p.row.estado === 'ANULADO'" outline color="grey-7" label="Anulada en Sofia" class="q-ml-xs">
              <q-tooltip>Está anulada en Sofia y no existe en el SIAT: cuadra, no hay que reenviarla</q-tooltip>
            </q-badge>
          </q-td>
        </template>
        <template #no-data>
          <div class="full-width text-center text-grey-6 q-py-lg">
            <q-icon name="inbox" size="36px" />
            <div v-if="tab === 'no_impuestos'">Todas las facturas de Sofia de {{ mesTexto(filtros.mes) }} están en el libro del SIAT</div>
            <div v-else-if="!resumen.cantidad">No se subió el libro del SIAT de {{ mesTexto(filtros.mes) }}</div>
            <div v-else>Nada que mostrar en esta pestaña</div>
          </div>
        </template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialogImportar">
      <q-card style="width:520px;max-width:94vw">
        <q-card-section class="row items-center q-py-sm bg-primary text-white">
          <q-avatar color="white" text-color="primary" icon="upload_file" size="32px" />
          <div class="q-ml-sm"><div class="text-subtitle1 text-weight-bold">Subir libro de ventas</div><div class="text-caption">ZIP o XLSX descargado del SIAT</div></div>
          <q-space /><q-btn flat round dense icon="close" color="white" v-close-popup />
        </q-card-section>
        <q-card-section class="q-pa-sm">
          <q-file v-model="archivo" dense outlined accept=".zip,.xlsx,.xls" label="Seleccione el archivo (.zip o .xlsx)" clearable>
            <template #prepend><q-icon name="attach_file" /></template>
          </q-file>
          <div class="text-caption text-grey-7 q-mt-sm">
            Las facturas cuyo <b>código de autorización (CUF)</b> ya esté cargado no se duplican: se actualizan con lo que trae el archivo
            (por ejemplo, si se anularon en el SIAT). Puede subir el mismo archivo varias veces.
          </div>
          <div v-if="resultado" class="q-mt-sm q-pa-sm rounded-borders bg-green-1 text-green-10 text-caption">
            Leídas <b>{{ resultado.total }}</b> · nuevas <b>{{ resultado.insertados }}</b> · ya cargadas <b>{{ resultado.duplicados }}</b>
            · actualizadas <b>{{ resultado.actualizados || 0 }}</b>
            <span v-if="resultado.meses && resultado.meses.length"> · meses: <b>{{ resultado.meses.join(', ') }}</b></span>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense no-caps label="Cerrar" v-close-popup />
          <q-btn unelevated dense no-caps color="primary" icon="cloud_upload" label="Importar" :disable="!archivo" :loading="importando" @click="importar" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Anular solo en Impuestos: PUT verificacion-facturas/{id}/anular -->
    <q-dialog v-model="dialogAnular" :persistent="anulando">
      <q-card v-if="aAnular" style="width:520px;max-width:94vw">
        <q-card-section class="row items-center q-py-sm bg-negative text-white">
          <q-avatar color="white" text-color="negative" icon="block" size="32px" />
          <div class="q-ml-sm">
            <div class="text-subtitle1 text-weight-bold">Anular en Impuestos</div>
            <div class="text-caption">Factura {{ aAnular.numero_factura }} · {{ aAnular.razon_social || 'sin nombre' }} · Bs {{ money(aAnular.importe_total) }}</div>
          </div>
          <q-space /><q-btn flat round dense icon="close" color="white" :disable="anulando" v-close-popup />
        </q-card-section>
        <q-banner dense class="bg-red-1 text-red-10">
          <q-icon name="info" class="q-mr-xs" />Esta factura no existe en Sofia: se anula <b>solo en el SIAT</b> con su CUF.
          No se toca nada de Sofia. Elija el motivo. <b>No se puede deshacer.</b>
        </q-banner>
        <q-list separator>
          <q-item v-for="m in motivosAnulacion" :key="m.codigo" clickable :disable="anulando" @click="confirmarAnulacion(m)">
            <q-item-section avatar><q-avatar color="negative" text-color="white" size="28px">{{ m.codigo }}</q-avatar></q-item-section>
            <q-item-section>{{ m.descripcion }}</q-item-section>
            <q-item-section side>
              <q-spinner v-if="anulando && motivoAnulando === m.codigo" color="negative" />
              <q-icon v-else name="chevron_right" />
            </q-item-section>
          </q-item>
        </q-list>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogDetalle">
      <q-card v-if="detalle" style="width:620px;max-width:94vw">
        <q-card-section class="row items-center q-py-sm bg-primary text-white">
          <q-avatar color="white" text-color="primary" icon="receipt_long" size="32px" />
          <div class="q-ml-sm"><div class="text-subtitle1 text-weight-bold">Factura {{ detalle.numero_factura }}</div><div class="text-caption">{{ fecha(detalle.fecha_factura) }} · {{ detalle.razon_social }}</div></div>
          <q-space /><q-btn flat round dense icon="close" color="white" v-close-popup />
        </q-card-section>
        <q-card-section class="q-pa-sm">
          <div class="text-caption text-grey-7">Código de autorización (CUF)</div>
          <div class="text-body2 q-mb-sm" style="word-break:break-all">{{ detalle.cuf }}</div>
          <q-markup-table flat bordered dense>
            <thead><tr><th class="text-left"></th><th class="text-right">Libro SIAT</th><th class="text-right">Sofia</th></tr></thead>
            <tbody>
              <tr><td>Origen</td><td class="text-right">—</td><td class="text-right">{{ detalleSofia ? (detalle.factura ? 'Facturación' : (detalle.ficticia ? 'Ficticia' : 'Sistema de caja')) : 'No está en Sofia' }}</td></tr>
              <tr><td>Nº factura</td><td class="text-right">{{ detalle.numero_factura }}</td><td class="text-right">{{ detalleSofia && detalleSofia.nro_factura }}</td></tr>
              <tr><td>NIT / CI</td><td class="text-right">{{ detalle.nit_ci_cliente }}<span v-if="detalle.complemento">-{{ detalle.complemento }}</span></td><td class="text-right">{{ detalleSofia && detalleSofia.nit }}</td></tr>
              <tr><td>Razón social</td><td class="text-right">{{ detalle.razon_social }}</td><td class="text-right">{{ detalleSofia && detalleSofia.nombre }}</td></tr>
              <tr :class="difImporte ? 'bg-amber-1' : ''"><td>Importe total</td><td class="text-right text-weight-bold">{{ money(detalle.importe_total) }}</td><td class="text-right text-weight-bold">{{ detalleSofia ? money(detalleSofia.total) : '' }}</td></tr>
              <tr><td>Descuentos</td><td class="text-right">{{ money(detalle.descuentos) }}</td><td class="text-right">{{ detalleSofia ? money(detalleSofia.descuento) : '' }}</td></tr>
              <tr><td>Débito fiscal</td><td class="text-right">{{ money(detalle.debito_fiscal) }}</td><td class="text-right"></td></tr>
              <tr :class="difEstado ? 'bg-amber-1' : ''"><td>Estado</td><td class="text-right">{{ detalle.estado }}</td><td class="text-right">{{ detalleSofia && `${detalleSofia.estado} · SIAT ${detalleSofia.estado_siat || '—'}` }}</td></tr>
              <tr><td>Consolidación</td><td class="text-right">{{ detalle.estado_consolidacion }}</td><td class="text-right"></td></tr>
              <tr><td>Archivo de origen</td><td class="text-right">{{ detalle.archivo_origen }}</td><td class="text-right"></td></tr>
            </tbody>
          </q-markup-table>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn v-if="detalle.url_siat" flat dense no-caps color="primary" icon="open_in_new" label="Ver en Impuestos" @click="abrir(detalle.url_siat)" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
// Consume GET /verificacion-facturas, /resumen, /faltantes, /{id} y POST /importar
// (VerificacionFacturasController). Permiso: verificacionFacturas.
const COLUMNAS_LIBRO = [
  { name: 'acciones', label: '', align: 'left' },
  { name: 'fecha', label: 'Fecha', field: 'fecha_factura', align: 'left' },
  { name: 'numero_factura', label: 'Nº factura', field: 'numero_factura', align: 'left' },
  { name: 'cuf', label: 'CUF', field: 'cuf', align: 'left' },
  { name: 'nit_ci_cliente', label: 'NIT / CI', field: 'nit_ci_cliente', align: 'left' },
  { name: 'razon', label: 'Razón social', field: 'razon_social', align: 'left' },
  { name: 'importe', label: 'Importe SIAT', field: 'importe_total', align: 'right' },
  { name: 'estado', label: 'Estado SIAT', field: 'estado', align: 'center' },
  { name: 'sistema', label: 'En Sofia', field: 'sistema', align: 'center' },
  { name: 'dif', label: 'Diferencia', field: 'sistema', align: 'right' }
]
const COLUMNAS_FALTANTES = [
  { name: 'acciones', label: '', align: 'left' },
  { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left' },
  { name: 'origen', label: 'Origen', field: 'origen', align: 'center' },
  { name: 'nro_factura', label: 'Nº factura', field: 'nro_factura', align: 'left' },
  { name: 'cuf', label: 'CUF', field: 'cuf', align: 'left' },
  { name: 'nit', label: 'NIT / CI', field: 'nit', align: 'left' },
  { name: 'razon', label: 'Cliente', field: 'nombre', align: 'left' },
  { name: 'total', label: 'Total', field: 'total', align: 'right' },
  { name: 'v_estado', label: 'Estado', field: 'estado', align: 'center' },
  { name: 'estado_siat', label: 'Estado SIAT', field: 'estado_siat', align: 'center' }
]

// El SIAT publica el libro del mes ya cerrado: en noviembre lo que interesa es octubre.
function mesDe (offset) {
  const d = new Date()
  d.setDate(1)
  d.setMonth(d.getMonth() + offset - 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

export default {
  name: 'VerificacionFacturas',
  data () {
    return {
      filas: [],
      loading: false,
      tab: 'todas',
      filtros: { q: '', mes: mesDe(0), estado: null },
      pagination: { page: 1, rowsPerPage: 20, rowsNumber: 0 },
      resumen: { cantidad: 0, validas: 0, anuladas: 0, importe_total: 0, debito_fiscal: 0, en_sistema: 0, sin_registrar: 0, importe_sin_registrar: 0, diferencias: 0, no_en_impuestos: 0, meses: [] },
      dialogImportar: false,
      archivo: null,
      importando: false,
      resultado: null,
      dialogDetalle: false,
      detalle: null,
      reenviando: null,
      dialogAnular: false,
      aAnular: null,
      anulando: false,
      motivoAnulando: null,
      motivosAnulacion: [],
      exportando: false
    }
  },
  computed: {
    columnas () {
      return this.tab === 'no_impuestos' ? COLUMNAS_FALTANTES : COLUMNAS_LIBRO
    },
    detalleSofia () {
      return this.detalle ? (this.detalle.factura || this.detalle.ficticia || this.detalle.caja) : null
    },
    difImporte () {
      return this.detalleSofia && Math.abs(Number(this.detalleSofia.total) - Number(this.detalle.importe_total)) > 0.01
    },
    difEstado () {
      return this.detalleSofia && ((this.detalle.estado === 'VALIDA') !== (this.detalleSofia.estado === 'ACTIVO'))
    }
  },
  mounted () {
    this.cargar()
  },
  methods: {
    mesDe,
    money (v) {
      return Number(v || 0).toFixed(2)
    },
    fecha (v) {
      return v ? new Date(String(v).slice(0, 10) + 'T00:00:00').toLocaleDateString('es-BO') : ''
    },
    mesTexto (mes) {
      return mes ? new Date(mes + '-01T00:00:00').toLocaleDateString('es-BO', { month: 'long', year: 'numeric' }) : 'el mes'
    },
    colorSiat (estado) {
      if (estado === 'VALIDADA') return 'positive'
      if (estado === 'ERROR' || estado === 'RECHAZADA') return 'negative'
      if (estado && estado.indexOf('ANULA') === 0) return 'grey-7'
      return 'orange-8'
    },
    difTexto (row) {
      const partes = []
      if (row.sistema.diferencias.includes('importe')) {
        const d = Number(row.sistema.total) - Number(row.importe_total)
        partes.push(`${d > 0 ? '+' : ''}${this.money(d)}`)
      }
      if (row.sistema.diferencias.includes('estado')) partes.push(`Sofia ${row.sistema.estado}`)
      return partes.join(' · ')
    },
    avisar (err, texto) {
      const msg = (err && err.response && err.response.data && err.response.data.message) || texto
      this.$q.notify({ message: msg, color: 'negative', icon: 'error', position: 'top' })
    },
    params () {
      return {
        q: this.filtros.q || '',
        mes: this.filtros.mes || '',
        estado: this.filtros.estado || '',
        tab: this.tab,
        page: this.pagination.page,
        per_page: this.pagination.rowsPerPage
      }
    },
    // GET verificacion-facturas/excel: hoja "Libro SIAT" + hoja "Ventas facturadas" (solo tipo factura con CUF).
    async exportarExcel () {
      this.exportando = true
      try {
        const { data } = await this.$api.get('verificacion-facturas/excel', {
          params: { q: this.filtros.q || '', mes: this.filtros.mes || '', estado: this.filtros.estado || '' },
          responseType: 'blob',
          timeout: 300000
        })
        const url = URL.createObjectURL(data)
        const a = document.createElement('a')
        a.href = url
        a.download = 'FACTURAS SIAT Y VENTAS ' + (this.filtros.mes || mesDe(0)) + '.xlsx'
        a.click()
        URL.revokeObjectURL(url)
      } catch (err) {
        this.$q.notify({ message: 'No se pudo generar el Excel', color: 'negative', icon: 'error', position: 'top' })
      } finally {
        this.exportando = false
      }
    },
    async cargar () {
      this.loading = true
      try {
        const url = this.tab === 'no_impuestos' ? 'verificacion-facturas/faltantes' : 'verificacion-facturas'
        const [lista, resumen] = await Promise.all([
          this.$api.get(url, { params: this.params() }),
          this.$api.get('verificacion-facturas/resumen', { params: this.params() })
        ])
        this.filas = lista.data.data
        this.pagination.rowsNumber = lista.data.total || 0
        this.pagination.page = lista.data.current_page || 1
        Object.assign(this.resumen, resumen.data)
      } catch (err) {
        this.avisar(err, 'No se pudo cargar la verificación')
      } finally {
        this.loading = false
      }
    },
    recargar () {
      this.pagination.page = 1
      this.cargar()
    },
    cambiarTab () {
      this.filas = []
      this.recargar()
    },
    irTab (tab) {
      this.tab = tab
      this.cambiarTab()
    },
    moverMes (paso) {
      const [y, m] = (this.filtros.mes || mesDe(0)).split('-').map(Number)
      const d = new Date(y, m - 1 + paso, 1)
      this.filtros.mes = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
      this.recargar()
    },
    onRequest (req) {
      this.pagination.page = req.pagination.page
      this.pagination.rowsPerPage = req.pagination.rowsPerPage
      this.cargar()
    },
    // Solo en "En Sofia, no en SIAT": activas en Sofia que el SIAT no validó.
    reenviable (row) {
      return this.tab === 'no_impuestos' && row.estado === 'ACTIVO' && row.estado_siat !== 'VALIDADA'
    },
    reenviar (row) {
      this.$q.dialog({
        title: 'Reenviar al SIAT',
        message: `¿Reenviar la factura ${row.nro_factura || '#' + row.id} de ${row.nombre || 'sin nombre'} por Bs ${this.money(row.total)}?<br><br>` +
          '<b>Conserva el mismo número y su fecha y hora original</b>: si falló otro día se manda por <b>evento significativo</b> ' +
          '(fuera de línea) y se declara en el mes de la factura, no en el actual. ' +
          'Antes se consulta al SIAT: si ya la tenía, solo se marca como VALIDADA. Si el SIAT rechaza el evento, ' +
          'no se manda con la fecha de hoy: se muestra el motivo. El CUF cambia, así que hay que volver a imprimirla.',
        html: true,
        cancel: { label: 'Cancelar', flat: true, noCaps: true },
        ok: { label: 'Reenviar', color: 'deep-orange', unelevated: true, noCaps: true },
        persistent: true
      }).onOk(async () => {
        this.reenviando = row.origen + row.id
        try {
          const { data } = await this.$api.post('verificacion-facturas/reenviar', { origen: row.origen, id: row.id }, { timeout: 200000 })
          this.$q.notify({ message: data.message, color: 'positive', icon: 'check', position: 'top', timeout: 6000 })
          this.cargar()
        } catch (err) {
          this.avisar(err, 'No se pudo reenviar la factura')
        } finally {
          this.reenviando = null
        }
      })
    },
    // Solo en "En SIAT, no en Sofia": válida en el SIAT y sin factura en Sofia.
    anulable (row) {
      return this.tab === 'sin_registrar' && !row.sistema && row.estado === 'VALIDA'
    },
    async pedirAnulacion (row) {
      this.aAnular = row
      this.motivoAnulando = null
      if (!this.motivosAnulacion.length) {
        try {
          this.motivosAnulacion = (await this.$api.get('verificacion-facturas/motivos-anulacion')).data
        } catch (err) {
          this.avisar(err, 'No se pudieron cargar los motivos de anulación')
          return
        }
      }
      this.dialogAnular = true
    },
    confirmarAnulacion (motivo) {
      const row = this.aAnular
      this.$q.dialog({
        title: 'Anular en Impuestos',
        message: `¿Anular en el SIAT la factura ${row.numero_factura} de ${row.razon_social || 'sin nombre'} por Bs ${this.money(row.importe_total)}?<br>` +
          `Motivo: <b>${motivo.descripcion}</b>.<br><br>Esto <b>no se puede deshacer</b>.`,
        html: true,
        cancel: { label: 'Cancelar', flat: true, noCaps: true },
        ok: { label: 'Sí, anular', color: 'negative', unelevated: true, noCaps: true },
        persistent: true
      }).onOk(async () => {
        this.anulando = true
        this.motivoAnulando = motivo.codigo
        try {
          const { data } = await this.$api.put(`verificacion-facturas/${row.id}/anular`, { codigo_motivo: motivo.codigo }, { timeout: 120000 })
          this.dialogAnular = false
          this.$q.notify({ message: data.message, color: 'positive', icon: 'check', position: 'top' })
          this.cargar()
        } catch (err) {
          this.avisar(err, 'No se pudo anular la factura en Impuestos')
        } finally {
          this.anulando = false
          this.motivoAnulando = null
        }
      })
    },
    abrir (url) {
      window.open(url, '_blank')
    },
    async verDetalle (row) {
      try {
        const { data } = await this.$api.get(`verificacion-facturas/${row.id}`)
        this.detalle = data
        this.dialogDetalle = true
      } catch (err) {
        this.avisar(err, 'No se pudo cargar la factura')
      }
    },
    async importar () {
      const form = new FormData()
      form.append('archivo', this.archivo)
      this.importando = true
      this.resultado = null
      try {
        const { data } = await this.$api.post('verificacion-facturas/importar', form, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 300000 })
        this.resultado = data
        this.archivo = null
        this.$q.notify({ message: `${data.insertados} facturas nuevas · ${data.actualizados || 0} actualizadas`, color: 'positive', icon: 'check', position: 'top' })
        // Salta al mes del archivo para ver de inmediato lo que se subió.
        if (data.meses && data.meses.length) this.filtros.mes = data.meses[data.meses.length - 1]
        this.recargar()
      } catch (err) {
        this.avisar(err, 'No se pudo importar el archivo')
      } finally {
        this.importando = false
      }
    }
  }
}
</script>

<style scoped>
.kpi-row { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 4px }
.kpi-card { border-radius: 8px }
.kpi-click { cursor: pointer }
.kpi-body { display: flex; align-items: center; flex-wrap: nowrap; padding: 4px 8px }
.kpi-label { font-size: 11px; line-height: 1.1; color: #757575 }
.kpi-value { font-size: 16px; font-weight: 700; line-height: 1.25 }
.kpi-sub { font-size: 11px; font-weight: 400; color: #9e9e9e }
.mes-input { width: 170px }
.razon { max-width: 220px }
.cuf { display: inline-block; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom }
.tabs :deep(.q-tab) { min-height: 32px; padding: 0 10px }
.tabs :deep(.q-tab__label) { font-size: 12.5px }
.tabla :deep(th), .tabla :deep(td) { padding: 2px 6px; height: auto }
.tabla :deep(tbody tr) { height: 28px }
@media (max-width: 900px) { .kpi-row { grid-template-columns: repeat(2, minmax(0, 1fr)) } }
</style>
