<template>
  <q-page class="q-pa-xs relative-position">
    <fieldset class="contenido" :disabled="ocupado" :inert="ocupado || undefined">
    <div class="row items-center q-gutter-xs q-mb-xs">
      <div class="col-auto text-subtitle1 text-weight-bold">Verificar facturación</div>
      <div v-if="actualizado && !error" class="text-caption text-positive" role="status">· {{ actualizado }}</div>
      <q-space/>
      <!-- Lo que verifico cada usuario entre fecha y hora; con camion elegido, solo de ese camion. -->
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
          <div class="text-caption" :class="camion ? 'text-primary text-weight-bold' : 'text-grey-7'">
            Camión: {{ camion ? nombrePlaca(camion) : 'todos' }}
          </div>
          <q-btn unelevated dense no-caps color="teal-8" icon="qr_code_2" label="Excel QR verificados"
                 :loading="descargandoReporte === 'qr'" :disable="descargandoReporte !== null" @click="descargarReporte('qr')">
            <q-tooltip>Solo lo verificado por QR, con el formato de cobros QR de créditos</q-tooltip>
          </q-btn>
          <q-btn unelevated dense no-caps color="indigo-7" icon="point_of_sale" label="Total efectivo verificado"
                 :loading="descargandoReporte === 'total'" :disable="descargandoReporte !== null" @click="descargarReporte('total')">
            <q-tooltip>Total del efectivo verificado por usuario, con el formato del cierre de caja</q-tooltip>
          </q-btn>
          <q-btn unelevated dense no-caps color="blue-grey-7" icon="list_alt" label="Detalle efectivo verificado"
                 :loading="descargandoReporte === 'detalle'" :disable="descargandoReporte !== null" @click="descargarReporte('detalle')">
            <q-tooltip>Cada comprobante verificado en efectivo con su cliente y monto, para cuadrar el total</q-tooltip>
          </q-btn>
        </div>
      </q-btn-dropdown>
      <q-btn outline dense no-caps size="sm" color="green-8" icon="grid_on" label="Excel día" :loading="exportando === 'excel'" @click="exportar('excel')"/>
      <q-btn outline dense no-caps size="sm" color="red-7" icon="picture_as_pdf" label="PDF" :loading="exportando === 'pdf'" @click="exportar('pdf')"/>
      <q-btn flat dense no-caps size="sm" color="primary" icon="person_search" label="Por cliente" to="/cobranzas/verificacion/clientes"/>
    </div>

    <div class="row q-col-gutter-xs items-center q-mb-xs">
      <div class="col-6 col-md-2">
        <q-input v-model="fecha" type="date" dense outlined label="Fecha" class="filtro" @update:model-value="cambiarFecha"/>
      </div>
      <div class="col-6 col-md-2">
        <q-select
          v-model="camion" dense outlined clearable emit-value map-options label="Camión" class="filtro"
          :options="opcionesCamion" @update:model-value="cargar"
        />
      </div>
      <div class="col-12 col-md-3">
        <q-input v-model="buscar" dense outlined clearable placeholder="Cliente, NIT, comprobante o pedido" class="filtro">
          <template #prepend><q-icon name="search" size="xs"/></template>
        </q-input>
      </div>
      <div class="col-12 col-md row items-center no-wrap">
        <q-btn-toggle
          v-model="estado" no-caps unelevated dense size="sm" toggle-color="primary" color="grey-3" text-color="grey-9"
          :options="[
            { label: 'Por verificar (' + (totales.comprobantes - totales.verificados) + ')', value: 'pendientes' },
            { label: 'Verificados (' + totales.verificados + ')', value: 'verificados' },
            { label: 'Todos', value: 'todos' }
          ]"
        />
        <q-space/>
        <span class="text-caption text-grey-7 q-mr-xs gt-sm">
          <q-icon name="payments" color="green-8"/> Efectivo · <q-icon name="qr_code_2" color="deep-purple-6"/> QR
        </span>
        <q-btn flat dense round size="sm" icon="refresh" color="primary" :loading="cargando" @click="cargar"/>
      </div>
    </div>

    <!-- Avance de cada camion: verificados / entregados. Clic para filtrar. -->
    <div v-if="camiones.length" class="row q-col-gutter-xs q-mb-xs">
      <div class="col-12 col-sm-6 col-md-3 col-lg-2">
        <button type="button" class="camion-fila row items-center no-wrap full-width" :class="!camion ? 'camion-activo' : ''"
                :aria-pressed="!camion" :disabled="cargando" @click="alternarCamion(null)">
          <span class="text-weight-bold camion-placa">TODOS</span>
          <q-linear-progress class="col q-mx-xs" rounded size="8px" :value="resumenEntregas.entregados ? resumenEntregas.entregados_verificados / resumenEntregas.entregados : 0"
                             color="positive" track-color="grey-4"/>
          <span class="camion-conteo">{{ resumenEntregas.entregados_verificados }}/{{ resumenEntregas.entregados }} · {{ porcentaje(resumenEntregas) }}%</span>
        </button>
      </div>
      <div v-for="c in camiones" :key="c.placa" class="col-12 col-sm-6 col-md-3 col-lg-2">
        <button type="button" class="camion-fila row items-center no-wrap full-width" :class="camion === c.placa ? 'camion-activo' : ''"
                :aria-pressed="camion === c.placa" :disabled="cargando" @click="alternarCamion(c.placa)">
          <span class="chip-placa camion-placa ellipsis" :style="estiloColor(c.color)">{{ nombrePlaca(c.placa) }}</span>
          <q-linear-progress class="col q-mx-xs" rounded size="8px" :value="c.entregados ? c.entregados_verificados / c.entregados : 0"
                             :color="c.entregados && c.entregados_verificados === c.entregados ? 'positive' : 'orange-7'" track-color="grey-4"/>
          <span class="camion-conteo">{{ c.entregados_verificados }}/{{ c.entregados }} · {{ porcentaje(c) }}%</span>
          <q-tooltip>Verificados / entregados (incluye entregas parciales) · {{ c.verificados }}/{{ c.total }} en total</q-tooltip>
        </button>
      </div>
    </div>

    <!-- Los montos: lo facturado, lo que trajeron los camiones y lo que cobranzas ya dio por recibido. -->
    <div class="row q-col-gutter-xs q-mb-xs">
      <div class="col-6 col-sm-4 col-md">
        <div class="tarjeta bg-blue-grey-1"><span class="tarjeta-rotulo text-blue-grey-8">FACTURADO</span>
          <span class="tarjeta-valor text-blue-grey-10">{{ money(totales.facturado) }}</span></div>
      </div>
      <div class="col-6 col-sm-4 col-md">
        <div class="tarjeta bg-indigo-1"><span class="tarjeta-rotulo text-indigo-8">RECOGIDO CAMIONES</span>
          <span class="tarjeta-valor text-indigo-10">{{ money(totales.recogido) }}</span></div>
      </div>
      <div class="col-6 col-sm-4 col-md">
        <div class="tarjeta bg-green-1"><span class="tarjeta-rotulo text-green-8"><q-icon name="payments"/> EFECTIVO VERIF.</span>
          <span class="tarjeta-valor text-green-10">{{ money(verificadoLado('efectivo')) }}</span></div>
      </div>
      <div class="col-6 col-sm-4 col-md">
        <div class="tarjeta bg-deep-purple-1"><span class="tarjeta-rotulo text-deep-purple-8"><q-icon name="qr_code_2"/> QR VERIF.</span>
          <span class="tarjeta-valor text-deep-purple-10">{{ money(verificadoLado('qr')) }}</span></div>
      </div>
      <div class="col-6 col-sm-4 col-md">
        <div class="tarjeta" :class="Math.abs(totales.diferencia) > 0.009 ? 'bg-red-1' : 'bg-grey-2'">
          <span class="tarjeta-rotulo" :class="Math.abs(totales.diferencia) > 0.009 ? 'text-red-8' : 'text-grey-8'">DIFERENCIA</span>
          <span class="tarjeta-valor" :class="Math.abs(totales.diferencia) > 0.009 ? 'text-red-10' : 'text-grey-9'">{{ money(totales.diferencia) }}</span></div>
      </div>
      <div class="col-6 col-sm-4 col-md">
        <div class="tarjeta bg-orange-1"><span class="tarjeta-rotulo text-orange-8">VERIFICADOS</span>
          <div class="row items-center no-wrap">
            <span class="tarjeta-valor text-orange-10">{{ totales.verificados }}/{{ totales.comprobantes }}</span>
            <q-linear-progress class="col q-ml-sm" rounded size="8px" :value="avance" color="positive" track-color="orange-2"/>
          </div>
        </div>
      </div>
    </div>

    <q-banner v-if="error" dense class="bg-red-1 text-negative q-mb-xs">{{ error }}</q-banner>

    <q-table
      flat bordered dense :rows="visibles" :columns="columnas" row-key="factura_id" :loading="cargando"
      :rows-per-page-options="[50, 100, 0]" :pagination="{ rowsPerPage: 0, sortBy: 'id', descending: false }" :grid="$q.screen.lt.md"
      no-data-label="No hay comprobantes con ese filtro" class="tabla"
    >
      <template #body="props">
        <q-tr :props="props" :class="props.row.verificado ? 'fila-ok' : ''">
          <q-td key="n" :props="props" class="text-grey-6">{{ props.rowIndex + 1 }}</q-td>
          <q-td key="id" :props="props">
            <b>{{ props.row.factura_id }}</b><span class="tipo-comp">{{ props.row.tipo_comprobante === 'FACTURA' ? 'F' : 'V' }}</span>
            <q-tooltip>
              {{ props.row.tipo_comprobante === 'FACTURA' ? 'Factura' : 'Venta' }} #{{ props.row.factura_id }} ·
              Pedido {{ props.row.pedido || '—' }} · {{ props.row.fecha }} {{ props.row.hora }}
            </q-tooltip>
          </q-td>
          <q-td key="cliente" :props="props">
            <div class="row items-center no-wrap">
              <q-btn flat dense round size="7px" color="primary" icon="history" :disable="!props.row.cliente_id || ocupado" @click="verVentas(props.row)">
                <q-tooltip>Historial de ventas</q-tooltip>
              </q-btn>
              <span class="ellipsis" style="max-width: 190px">{{ nombreCliente(props.row.cliente) }}
                <q-tooltip>{{ props.row.cliente || 'Sin cliente' }} · NIT {{ props.row.nit || '—' }}</q-tooltip>
              </span>
            </div>
          </q-td>
          <q-td key="placa" :props="props">
            <span class="chip-placa" :style="estiloColor(props.row.placa_color)">{{ nombrePlaca(props.row.placa) }}</span>
          </q-td>
          <q-td key="pago" :props="props">
            <div class="row items-center no-wrap">
              <q-icon v-for="i in iconosPago(props.row.forma_pago)" :key="i.icon" :name="i.icon" :color="i.color" size="14px"/>
              <span class="entrega q-ml-xs" :class="colorEntrega(props.row.entrega)">{{ entregaCorta(props.row.entrega) }}</span>
              <q-tooltip>{{ props.row.forma_pago }} · {{ props.row.entrega }}</q-tooltip>
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
              />
              <q-checkbox
                :model-value="props.row[lado.key + '_ok']" dense size="sm" color="positive" class="q-ml-xs"
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
          <q-td key="verifico" :props="props">
            <div v-for="lado in LADOS.filter(l => props.row[l.key + '_ok'])" :key="lado.key" class="verifico row items-center no-wrap">
              <q-icon :name="lado.icon" :color="lado.color" size="12px"/>
              <span class="q-ml-xs ellipsis" style="max-width: 110px">{{ nombreCliente(limpiarNombre(props.row[lado.key + '_por'])) }}</span>
              <span class="q-ml-xs text-grey-7">{{ horaVerificado(props.row[lado.key + '_en']) }}</span>
              <q-tooltip>{{ lado.label }} verificado por {{ limpiarNombre(props.row[lado.key + '_por']) }} el {{ props.row[lado.key + '_en'] }}</q-tooltip>
            </div>
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

      <!-- En el celular: una tarjeta compacta por comprobante. -->
      <template #item="props">
        <div class="col-12 q-pa-xs">
          <q-card flat bordered :class="props.row.verificado ? 'fila-ok' : ''">
            <div class="q-px-sm q-py-xs">
              <div class="row items-center no-wrap">
                <div class="col ellipsis text-weight-bold">{{ nombreCliente(props.row.cliente) }}</div>
                <q-btn flat dense round size="sm" color="primary" icon="history" :disable="!props.row.cliente_id || ocupado" @click="verVentas(props.row)"/>
                <q-icon v-for="i in iconosPago(props.row.forma_pago)" :key="i.icon" :name="i.icon" :color="i.color" size="18px"/>
                <q-icon v-if="props.row.verificado" name="check_circle" color="positive" size="20px" class="q-ml-xs"/>
                <q-badge v-else-if="props.row.efectivo_ok || props.row.qr_ok" class="q-ml-xs" color="orange-8" :label="'Falta ' + (props.row.qr_ok ? 'efectivo' : 'QR')"/>
              </div>
              <div class="row items-center q-gutter-x-xs text-caption">
                <b>{{ props.row.tipo_comprobante === 'FACTURA' ? 'F' : 'V' }} #{{ props.row.factura_id }}</b>
                <span class="text-grey-7">P{{ props.row.pedido || '—' }}</span>
                <span class="chip-placa" :style="estiloColor(props.row.placa_color)">{{ nombrePlaca(props.row.placa) }}</span>
                <span :class="colorEntrega(props.row.entrega)">{{ entregaCorta(props.row.entrega) }}</span>
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

      <template #bottom-row>
        <q-tr class="fila-total">
          <q-td colspan="5" class="text-right">TOTALES · {{ visibles.length }} comprobantes</q-td>
          <q-td class="text-right">{{ money(suma('facturado')) }}</q-td>
          <q-td class="text-right">{{ money(suma('recogido')) }}</q-td>
          <q-td class="text-right">{{ money(sumaLado('efectivo')) }}</q-td>
          <q-td class="text-right">{{ money(sumaLado('qr')) }}</q-td>
          <q-td class="text-right">{{ money(sumaLado('efectivo') + sumaLado('qr') - suma('facturado')) }}</q-td>
          <q-td colspan="2"/>
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
        <q-card-section class="row items-center q-py-sm">
          <div class="col">
            <div class="text-subtitle1 text-weight-bold">Historial de ventas · {{ clienteVentas.cliente }}</div>
            <div class="text-caption text-grey-7">Comprobantes de facturación de todas las fechas</div>
          </div>
          <q-btn flat round dense icon="close" v-close-popup aria-label="Cerrar historial"/>
        </q-card-section>
        <q-banner v-if="errorVentas" dense class="bg-red-1 text-negative">
          {{ errorVentas }}
          <template #action><q-btn flat label="Reintentar" @click="cargarVentas(paginaVentas)"/></template>
        </q-banner>
        <q-card-section class="relative-position q-pt-none">
          <q-table flat dense :rows="ventas" :columns="columnasVentas" row-key="id" hide-bottom
                   :pagination="{ rowsPerPage: 0 }" :loading="cargandoVentas" no-data-label="Sin ventas registradas"/>
          <div class="row justify-center q-mt-sm">
            <q-pagination :model-value="paginaVentas" :max="paginasVentas" :max-pages="6" size="sm" :disable="cargandoVentas" @update:model-value="cargarVentas"/>
          </div>
          <q-inner-loading :showing="cargandoVentas" label="Cargando historial…"/>
        </q-card-section>
      </q-card>
    </q-dialog>
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
  name: 'VerificarFacturacion',
  data () {
    const hoy = date.formatDate(new Date(), 'YYYY-MM-DD')
    return {
      fecha: this.$route.query.fecha || hoy,
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
      // Por comprobante: { efectivo, qr } escritos por cobranzas.
      montos: {},
      totales: { comprobantes: 0, verificados: 0, facturado: 0, recogido: 0, verificado: 0, diferencia: 0 },
      // Reporte por usuario: por defecto el dia elegido de 00:00 a 23:59, todos los usuarios.
      reporte: {
        desde: hoy,
        horaDesde: '00:00',
        hasta: hoy,
        horaHasta: '23:59',
        usuario: null
      },
      verificadores: [],
      cargandoVerificadores: false,
      descargandoReporte: null,
      columnas: [
        { name: 'n', label: 'N°', field: 'factura_id', align: 'left' },
        { name: 'id', label: 'Nro', field: 'factura_id', align: 'left', sortable: true },
        { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
        { name: 'placa', label: 'Camión', field: 'placa', align: 'left', sortable: true },
        { name: 'pago', label: 'Entrega', field: 'forma_pago', align: 'left', sortable: true },
        { name: 'facturado', label: 'Total', field: 'facturado', align: 'right', sortable: true },
        { name: 'recogido', label: 'Recogido', field: 'recogido', align: 'right', sortable: true },
        { name: 'efectivo', label: 'Efectivo', field: 'verificado_efectivo', align: 'left' },
        { name: 'qr', label: 'QR', field: 'verificado_qr', align: 'left' },
        { name: 'diferencia', label: 'Dif.', field: 'diferencia', align: 'right' },
        { name: 'verifico', label: 'Verificó', field: 'verificado_por', align: 'left' },
        { name: 'estado', label: '', field: 'verificado', align: 'center' }
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
      // En orden de nota, igual que la hoja del recojo que firma el caminero.
      }).sort((a, b) => a.factura_id - b.factura_id)
    },
    avance () {
      return this.totales.comprobantes ? this.totales.verificados / this.totales.comprobantes : 0
    }
  },
  created () {
    this.LADOS = [
      { key: 'efectivo', ...ICONOS.efectivo },
      { key: 'qr', ...ICONOS.qr }
    ]
    this.reporte.desde = this.reporte.hasta = this.fecha
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
    // SONIA CORANI MAMANI -> Sonia Corani Mamani
    nombreCliente (nombre) {
      if (!nombre) return 'Sin cliente'
      return String(nombre).toLowerCase().replace(/(^|[\s.(-])(\S)/g, (_, antes, letra) => antes + letra.toUpperCase())
    },
    // 'VENTAS          GENERALES' -> 'VENTAS GENERALES'
    limpiarNombre (nombre) { return String(nombre || '').replace(/\s+/g, ' ').trim() },
    // '2026-10-08 03:28' -> '03:28'; si se verifico otro dia, con la fecha corta.
    horaVerificado (momento) {
      if (!momento) return ''
      const [dia, hora] = String(momento).split(' ')
      return dia === this.fecha ? hora : dia.slice(8, 10) + '/' + dia.slice(5, 7) + ' ' + hora
    },
    // 'ENTREGADO · CONTADO' -> 'Entregado': la forma de pago ya va con su icono.
    entregaCorta (entrega) {
      const estado = String(entrega || '').split(' · ')[0]
      return { 'RETORNO PARCIAL': 'Ret. parcial', 'NO ENTREGADO': 'No entregado' }[estado] ||
        estado.charAt(0) + estado.slice(1).toLowerCase()
    },
    suma (campo) { return this.visibles.reduce((total, f) => total + (Number(f[campo]) || 0), 0) },
    // Lo escrito en un lado (efectivo o QR) de los comprobantes visibles.
    sumaLado (lado) {
      return this.visibles.reduce((total, f) => total + (f.lados.includes(lado) ? Number((this.montos[f.factura_id] || {})[lado]) || 0 : 0), 0)
    },
    // Lo ya tildado de un lado en todo el dia (o camion).
    verificadoLado (lado) {
      return this.filas.reduce((total, f) => total + (f[lado + '_ok'] ? Number(f['verificado_' + lado]) || 0 : 0), 0)
    },
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
    iconosPago (forma) {
      if (forma === 'MIXTO') return [ICONOS.efectivo, ICONOS.qr]
      if (forma === 'QR') return [ICONOS.qr]
      if (forma === 'CRÉDITO') return [ICONOS.credito]
      return [ICONOS.efectivo]
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
      this.reporte.desde = this.reporte.hasta = this.fecha
      this.cargar()
    },
    // De cada lado, lo ya escrito; si no, lo esperado: lo que trajo el
    // camion o, si todavia no hay entrega, el importe segun como se pago.
    montosIniciales (fila) {
      return {
        efectivo: fila.verificado_efectivo ?? fila.esperado_efectivo,
        qr: fila.verificado_qr ?? fila.esperado_qr
      }
    },
    async cargar () {
      if (this.ocupado) return
      this.cargando = true
      this.error = ''
      try {
        const { data } = await this.$api.get('cobranzas/verificacion', { params: this.params() })
        const montos = {}
        data.filas.forEach(f => { montos[f.factura_id] = this.montosIniciales(f) })
        this.montos = montos
        this.filas = data.filas
        this.totales = data.totales
        this.camiones = data.camiones
        this.actualizado = date.formatDate(new Date(), 'HH:mm:ss')
      } catch (e) {
        this.error = this.mensaje(e)
      } finally {
        this.cargando = false
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
    // Si ese lado ya estaba tildado y se corrige el monto, se guarda de nuevo.
    corregirMonto (fila, lado) {
      if (fila[lado + '_ok']) this.marcar(fila, lado, true)
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
    // 'qr': lo verificado por QR; 'total' y 'detalle': el cierre de efectivo. Con camion elegido, solo ese camion.
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
        if (this.camion) params.camion = this.camion
        const { data } = await this.$api.get('cobranzas/verificacion/excel-' + tipo, { params, responseType: 'blob' })
        const url = URL.createObjectURL(data)
        const a = document.createElement('a')
        a.href = url
        a.download = {
          qr: 'VERIFICADOS QR ' + desde + (hasta !== desde ? ' AL ' + hasta : ''),
          total: 'CIERRE EFECTIVO ' + desde,
          detalle: 'DETALLE EFECTIVO ' + desde
        }[tipo] + (this.camion ? ' ' + this.nombrePlaca(this.camion) : '') + '.xlsx'
        a.click()
        URL.revokeObjectURL(url)
      } catch (e) {
        this.$q.notify({ type: 'negative', position: 'top', message: 'No se pudo generar el Excel' })
      } finally {
        this.descargandoReporte = null
      }
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
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  background: #fff;
  padding: 2px 6px;
  cursor: pointer;
  font: inherit;
  text-align: left;
}
.camion-fila:hover { background: #e3f2fd; }
.camion-activo { background: #bbdefb; border-color: #1976d2; }
.camion-placa { width: 78px; font-size: 11px; }
.camion-conteo { white-space: nowrap; text-align: right; font-size: 11px; font-weight: 700; }
.tarjeta { padding: 3px 8px; border-radius: 4px; line-height: 1.2; }
.tarjeta-rotulo { font-size: 9px; font-weight: 700; letter-spacing: 0.4px; display: block; }
.tarjeta-valor { font-size: 14px; font-weight: 800; }
.filtro :deep(.q-field__control), .filtro :deep(.q-field__marginal) { height: 34px; min-height: 34px; }
.filtro :deep(.q-field__native) { min-height: 34px; padding: 0; }
.tabla :deep(th) { font-size: 10px; padding: 1px 3px; height: 22px; }
.tabla :deep(td) { font-size: 11px; padding: 0 3px; height: 24px; white-space: nowrap; }
.tipo-comp { font-size: 9px; color: #9e9e9e; margin-left: 2px; }
.entrega { font-size: 10px; }
.verifico { font-size: 10px; line-height: 1.2; }
.celda-monto { width: 96px; }
.monto { max-width: 72px; }
.monto :deep(.q-field__control), .monto :deep(.q-field__marginal) { height: 20px; min-height: 20px; }
.monto :deep(.q-field__control) { padding: 0 3px; }
.monto :deep(input) { font-size: 11px; padding: 0; }
.fila-ok { background: #e8f5e9; }
.fila-total td { background: #eceff1; font-weight: 800; }
.chip-placa {
  display: inline-block;
  padding: 0 5px;
  border-radius: 3px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}
</style>
