<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-gutter-sm q-mb-sm">
      <div class="text-h6">Créditos de clientes</div>
      <q-space/>
      <span class="text-caption">Total general</span>
      <acciones-reporte-credito alcance="total general" :disable="cargando || !!error || !clientes.length || reportando" @reporte="reporteTotal($event)"/>
      <q-btn outline dense no-caps color="primary" icon="local_shipping" label="Reportes de camiones" to="/cobranzas/recojo"/>
      <q-btn unelevated dense no-caps color="red-7" icon="add" label="Agregar deuda" @click="nuevaDeuda()"/>
      <q-btn flat dense round icon="refresh" color="primary" :loading="cargando" @click="cargar">
        <q-tooltip>Actualizar</q-tooltip>
      </q-btn>
    </div>

    <!-- Lo que se mira primero: cuanto hay en la calle y quienes lo deben. -->
    <div class="row q-col-gutter-sm q-mb-sm">
      <div class="col-6 col-md-3">
        <q-card flat bordered class="tarjeta bg-red-1">
          <div class="tarjeta-rotulo text-red-9">DEUDA TOTAL</div>
          <div class="tarjeta-valor text-red-10">Bs {{ money(totales.saldo) }}</div>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card flat bordered class="tarjeta bg-orange-1 cursor-pointer" @click="filtro = 'deuda'">
          <div class="tarjeta-rotulo text-orange-9">CLIENTES QUE DEBEN</div>
          <div class="tarjeta-valor text-orange-10">{{ totales.con_deuda }}</div>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card flat bordered class="tarjeta bg-blue-grey-1">
          <div class="tarjeta-rotulo text-blue-grey-8">DEUDAS PENDIENTES</div>
          <div class="tarjeta-valor text-blue-grey-10">{{ totales.deudas }}</div>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card flat bordered class="tarjeta bg-grey-2 cursor-pointer" @click="filtro = 'todos'">
          <div class="tarjeta-rotulo text-grey-8">CLIENTES</div>
          <div class="tarjeta-valor text-grey-9">{{ totales.clientes }}</div>
        </q-card>
      </div>
    </div>

    <div class="row q-col-gutter-xs items-center q-mb-sm">
      <div class="col-12 col-md-4">
        <q-input v-model="buscar" dense outlined clearable placeholder="Buscar por nombre, NIT o teléfono">
          <template #prepend><q-icon name="search"/></template>
        </q-input>
      </div>
      <div class="col-12 col-md-auto">
        <q-btn-toggle
          v-model="filtro" no-caps unelevated dense toggle-color="primary" color="grey-3" text-color="grey-9"
          :options="[
            { label: 'Con deuda (' + totales.con_deuda + ')', value: 'deuda' },
            { label: 'Sin deuda', value: 'sin' },
            { label: 'Todos', value: 'todos' }
          ]"
        />
      </div>
      <div class="col-6 col-md-2">
        <q-select v-model="zona" dense outlined clearable :options="zonas" label="Zona"/>
      </div>
      <div class="col-6 col-md-2">
        <q-select v-model="vendedor" dense outlined clearable :options="vendedores" label="Preventista"/>
      </div>
    </div>

    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-sm">{{ error }}</q-banner>

    <div class="row items-center justify-end q-gutter-sm q-mb-sm">
      <span class="text-caption">Filtrados: {{ filtrados.length }} clientes · Saldo Bs {{ money(filtrados.reduce((total, c) => total + Number(c.saldo), 0)) }}</span>
      <acciones-reporte-credito alcance="clientes filtrados" :disable="cargando || !!error || !filtrados.length || reportando" @reporte="reporteTotal($event, true)"/>
    </div>
    <q-table
      flat bordered dense :rows="filtrados" :columns="columnas" row-key="id" :loading="cargando"
      :pagination="paginacion" :rows-per-page-options="[20, 50, 100, 0]" :grid="$q.screen.lt.md"
      no-data-label="No hay clientes con ese filtro" class="tabla-clientes"
      @row-click="(evento, fila) => abrirCliente(fila)"
    >
      <template #body-cell-nombre="props">
        <q-td :props="props">
          <div class="text-weight-medium">{{ props.row.nombre }}</div>
          <div class="text-caption text-grey-7">NIT {{ props.row.nit || '—' }}<span v-if="!props.row.activo"> · inactivo</span></div>
        </q-td>
      </template>
      <template #body-cell-saldo="props">
        <q-td :props="props">
          <span v-if="props.row.saldo > 0" class="text-weight-bolder text-red-9">Bs {{ money(props.row.saldo) }}</span>
          <span v-else class="text-grey-5">—</span>
        </q-td>
      </template>
      <template #body-cell-dias="props">
        <q-td :props="props">
          <q-badge v-if="props.row.saldo > 0" :color="colorDias(props.row.dias)">{{ props.row.dias }} d</q-badge>
        </q-td>
      </template>

      <template #body-cell-reportes="props">
        <q-td :props="props"><acciones-reporte-credito compacto :alcance="props.row.nombre" :disable="reportando" @reporte="exportarCliente($event, props.row)"/></q-td>
      </template>
      <!-- En el celular cada cliente es una tarjeta: nombre, deuda y datos. -->
      <template #item="props">
        <div class="col-12 q-pa-xs">
          <q-card flat bordered class="cursor-pointer" :class="props.row.saldo > 0 ? 'borde-deuda' : ''" @click="abrirCliente(props.row)">
            <q-card-section class="q-pa-sm">
              <div class="row items-start no-wrap">
                <div class="col" style="min-width: 0">
                  <div class="text-weight-bold ellipsis">{{ props.row.nombre }}</div>
                  <div class="text-caption text-grey-7 ellipsis">
                    NIT {{ props.row.nit || '—' }} · {{ props.row.zona || 'Sin zona' }}
                  </div>
                  <div class="text-caption text-grey-7 ellipsis">
                    <q-icon name="place" size="12px"/> {{ props.row.direccion || 'Sin dirección' }}
                  </div>
                  <div class="text-caption text-grey-7 ellipsis">
                    <q-icon name="call" size="12px"/> {{ props.row.telefono || '—' }} ·
                    <q-icon name="badge" size="12px"/> {{ props.row.vendedor || 'Sin preventista' }}
                  </div>
                </div>
                <div class="text-right q-ml-sm">
                  <template v-if="props.row.saldo > 0">
                    <div class="text-caption text-red-8">Debe</div>
                    <div class="text-subtitle1 text-weight-bolder text-red-9">Bs {{ money(props.row.saldo) }}</div>
                    <q-badge :color="colorDias(props.row.dias)">{{ props.row.deudas }} · {{ props.row.dias }} días</q-badge>
                  </template>
                  <div v-else class="text-caption text-green-8">Sin deuda</div>
                </div>
              </div>
              <div class="row justify-end q-mt-sm"><acciones-reporte-credito :alcance="props.row.nombre" :disable="reportando" @reporte="exportarCliente($event, props.row)"/></div>
            </q-card-section>
          </q-card>
        </div>
      </template>
    </q-table>

    <!-- Detalle del cliente: sus datos, lo que debe y las ventas a crédito. -->
    <q-dialog v-model="dialogCliente" :maximized="$q.screen.lt.md">
      <q-card :class="$q.screen.lt.md ? 'column no-wrap full-height' : ''" :style="$q.screen.lt.md ? '' : 'width: 900px; max-width: 96vw'">
        <q-card-section class="row items-center no-wrap q-py-sm bg-primary text-white">
          <q-icon name="person" size="26px" class="q-mr-sm"/>
          <div class="col" style="min-width: 0">
            <div class="text-subtitle1 text-weight-bold ellipsis">{{ detalle.cliente.nombre || 'Cliente' }}</div>
            <div class="text-caption">NIT {{ detalle.cliente.nit || '—' }}</div>
          </div>
          <q-btn flat dense no-caps icon="add" label="Agregar deuda" class="q-mr-xs" @click="nuevaDeuda(detalle.cliente)"/>
          <q-btn round flat dense icon="close" v-close-popup/>
        </q-card-section>

        <div v-if="cargandoDetalle" class="flex flex-center q-pa-xl"><q-spinner color="primary" size="40px"/></div>

        <q-card-section v-else class="q-pa-sm" :class="$q.screen.lt.md ? 'col scroll' : ''">
          <div class="row justify-end q-mb-sm"><acciones-reporte-credito alcance="estado de cuenta completo" :disable="cargandoDetalle || reportando || !detalle.cliente.id" @reporte="exportarCliente($event)"/></div>
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-md-6">
              <q-list dense bordered class="rounded-borders">
                <q-item><q-item-section avatar><q-icon name="call" color="green-8"/></q-item-section>
                  <q-item-section>
                    <q-item-label caption>Teléfono</q-item-label>
                    <q-item-label>
                      <a v-if="detalle.cliente.telefono" :href="'tel:' + detalle.cliente.telefono">{{ detalle.cliente.telefono }}</a>
                      <span v-else>—</span>
                    </q-item-label>
                  </q-item-section>
                </q-item>
                <q-item><q-item-section avatar><q-icon name="place" color="blue-8"/></q-item-section>
                  <q-item-section>
                    <q-item-label caption>Dirección · {{ detalle.cliente.zona || 'Sin zona' }}</q-item-label>
                    <q-item-label>{{ detalle.cliente.direccion || '—' }}</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item><q-item-section avatar><q-icon name="badge" color="blue-grey-7"/></q-item-section>
                  <q-item-section>
                    <q-item-label caption>Preventista · Canal</q-item-label>
                    <q-item-label>{{ detalle.cliente.vendedor || '—' }} · {{ detalle.cliente.canal || '—' }}</q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </div>
            <div class="col-12 col-md-6">
              <div class="row q-col-gutter-xs">
                <div class="col-6">
                  <q-card flat bordered class="tarjeta bg-red-1">
                    <div class="tarjeta-rotulo text-red-9">DEBE</div>
                    <div class="tarjeta-valor text-red-10">Bs {{ money(detalle.totales.saldo) }}</div>
                  </q-card>
                </div>
                <div class="col-6">
                  <q-card flat bordered class="tarjeta bg-green-1">
                    <div class="tarjeta-rotulo text-green-9">ABONADO</div>
                    <div class="tarjeta-valor text-green-10">Bs {{ money(detalle.totales.abonado) }}</div>
                  </q-card>
                </div>
                <div class="col-6">
                  <q-card flat bordered class="tarjeta bg-orange-1">
                    <div class="tarjeta-rotulo text-orange-9">DEUDAS PENDIENTES</div>
                    <div class="tarjeta-valor text-orange-10">{{ detalle.totales.deudas }}</div>
                  </q-card>
                </div>
                <div class="col-6">
                  <q-card flat bordered class="tarjeta bg-blue-grey-1">
                    <div class="tarjeta-rotulo text-blue-grey-8">VENDIDO A CRÉDITO</div>
                    <div class="tarjeta-valor text-blue-grey-10">Bs {{ money(detalle.totales.vendido) }}</div>
                  </q-card>
                </div>
              </div>
            </div>
          </div>

          <q-tabs v-model="pestana" dense no-caps align="left" active-color="primary" indicator-color="primary" class="q-mt-sm text-grey-8">
            <q-tab name="deudas" icon="request_quote" :label="'Deudas (' + detalle.totales.deudas + ')'"/>
            <q-tab name="ventas" icon="receipt_long" :label="'Ventas a crédito (' + detalle.totales.ventas + ')'"/>
          </q-tabs>
          <q-separator/>

          <q-tab-panels v-model="pestana" animated>
            <q-tab-panel name="deudas" class="q-pa-none q-pt-sm">
              <div class="row items-center q-mb-xs">
                <q-space/>
                <q-toggle v-model="soloPendientes" dense label="Solo pendientes"/>
              </div>
              <div v-if="!deudasVisibles.length" class="text-center text-grey-6 q-pa-md">
                {{ soloPendientes ? 'No debe nada' : 'Sin deudas registradas' }}
              </div>
              <div v-for="deuda in deudasVisibles" :key="deuda.clave" class="deuda-compacta q-mb-xs">
                <div class="row items-center q-col-gutter-xs cabecera-deuda">
                  <div class="col-12 col-sm">
                    <strong>{{ deuda.concepto }}</strong>
                    <span class="text-grey-7 q-ml-xs">{{ String(deuda.fecha).slice(0, 10) }}</span>
                    <q-badge class="q-ml-xs" :color="deuda.saldo > 0 ? 'orange-8' : (deuda.estado.includes('REVISAR') ? 'red-7' : 'green-7')">{{ deuda.estado }}</q-badge>
                    <div class="resumen-deuda">Total: Bs {{ money(deuda.monto) }} ? Abonado: Bs {{ money(deuda.pagado) }} ? <strong class="text-red-9">Saldo: Bs {{ money(deuda.saldo) }}</strong></div>
                  </div>
                  <div class="col-auto row items-center q-gutter-xs acciones-deuda">
                    <q-btn dense flat no-caps size="sm" color="positive" icon="payments" label="Abonar" :disable="deuda.saldo <= 0" @click="abrirAbono(deuda)"/>
                    <acciones-reporte-credito :alcance="deuda.concepto" :disable="reportando" @reporte="exportarDeuda($event, deuda)"/>
                  </div>
                </div>
                <q-table v-if="(abonosPorDeuda[deuda.clave] || []).length" flat dense class="tabla-abonos"
                         :rows="abonosPorDeuda[deuda.clave]" :columns="columnasHistorial" row-key="id"
                         :pagination="{ rowsPerPage: 0 }" hide-bottom separator="cell">
                  <template #body-cell-referencia="props"><q-td :props="props">{{ props.row.referencia || '-' }}</q-td></template>
                  <template #body-cell-cobrador="props"><q-td :props="props">{{ props.row.cobrador || '-' }}</q-td></template>
                </q-table>
                <div v-else class="sin-abonos text-grey-6">Sin abonos registrados</div>
              </div>
            </q-tab-panel>

            <q-tab-panel name="ventas" class="q-pa-none q-pt-sm">
              <div v-if="!detalle.ventas.length" class="text-center text-grey-6 q-pa-md">Sin ventas a crédito</div>
              <q-list v-else bordered separator class="rounded-borders">
                <q-expansion-item v-for="venta in detalle.ventas" :key="venta.id" dense expand-separator>
                  <template #header>
                    <q-item-section>
                      <q-item-label class="text-weight-medium">
                        {{ venta.comprobante === 'FACTURA' ? 'Factura' : 'Venta' }} #{{ venta.id }}
                        <span v-if="venta.pedido" class="text-grey-7"> · Pedido #{{ venta.pedido }}</span>
                      </q-item-label>
                      <q-item-label caption>
                        {{ venta.fecha }} {{ venta.hora }} · {{ venta.productos.length }} productos
                      </q-item-label>
                    </q-item-section>
                    <q-item-section side class="text-right">
                      <q-item-label class="text-weight-bold">Bs {{ money(venta.total) }}</q-item-label>
                      <q-badge :color="venta.estado === 'PENDIENTE' ? 'orange-8' : (venta.estado === 'ANULADA' ? 'grey-6' : 'green-7')">
                        {{ venta.estado }}<span v-if="venta.estado === 'PENDIENTE'">&nbsp;· Bs {{ money(venta.saldo) }}</span>
                      </q-badge>
                    </q-item-section>
                  </template>
                  <div class="contenedor-productos">
                    <div class="row justify-end q-pa-sm"><acciones-reporte-credito :alcance="'venta #' + venta.id" :disable="reportando" @reporte="exportarVenta($event, venta)"/></div>
                    <table class="tabla-productos">
                      <thead>
                        <tr><th class="text-left">Producto</th><th class="text-right">Cant.</th><th class="text-right">Precio</th><th class="text-right">Subtotal</th></tr>
                      </thead>
                      <tbody>
                        <tr v-for="(producto, i) in venta.productos" :key="i">
                          <td>{{ producto.nombre }}<div class="text-caption text-grey-6">{{ producto.cod_prod }}</div></td>
                          <td class="text-right text-no-wrap">
                            {{ producto.peso > 0 ? cantidad(producto.peso) + ' kg' : cantidad(producto.cantidad) + ' ' + producto.unidad }}
                          </td>
                          <td class="text-right">{{ money(producto.precio) }}</td>
                          <td class="text-right text-weight-bold">{{ money(producto.subtotal) }}</td>
                        </tr>
                      </tbody>
                      <tfoot>
                        <tr><td colspan="3" class="text-right">Total</td><td class="text-right">{{ money(venta.total) }}</td></tr>
                        <tr v-if="venta.pagado > 0"><td colspan="3" class="text-right">Abonado</td><td class="text-right text-green-8">{{ money(venta.pagado) }}</td></tr>
                      </tfoot>
                    </table>
                  </div>
                </q-expansion-item>
              </q-list>
            </q-tab-panel>
          </q-tab-panels>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Deuda agregada a mano: algo que el cliente debe y no salio de una venta a credito. -->
    <q-dialog v-model="dialogDeuda" persistent>
      <q-card style="width: 480px; max-width: 95vw">
        <q-form @submit="guardarDeuda">
          <q-card-section class="row items-center no-wrap q-py-sm bg-red-7 text-white">
            <q-icon name="add_card" size="24px" class="q-mr-sm"/>
            <div class="col text-subtitle1 text-weight-bold">Agregar deuda</div>
            <q-btn round flat dense icon="close" :disable="guardando" v-close-popup/>
          </q-card-section>
          <q-card-section class="q-gutter-sm">
            <q-select
              v-model="formDeuda.cliente" :options="opcionesCliente" option-label="nombre" use-input input-debounce="200"
              outlined label="Cliente" :rules="[v => !!v || 'Elegí el cliente']" @filter="filtrarClientes"
            >
              <template #option="scope">
                <q-item v-bind="scope.itemProps">
                  <q-item-section>
                    <q-item-label>{{ scope.opt.nombre }}</q-item-label>
                    <q-item-label caption>NIT {{ scope.opt.nit || '—' }} · {{ scope.opt.zona || 'Sin zona' }}</q-item-label>
                  </q-item-section>
                  <q-item-section v-if="scope.opt.saldo > 0" side class="text-red-8">Debe Bs {{ money(scope.opt.saldo) }}</q-item-section>
                </q-item>
              </template>
              <template #no-option><q-item><q-item-section class="text-grey">Escribí al menos 2 letras del nombre o NIT</q-item-section></q-item></template>
            </q-select>
            <q-input v-model="formDeuda.fecha" type="date" outlined label="Fecha" :rules="[v => !!v || 'Indicá la fecha']"/>
            <q-input v-model="formDeuda.concepto" outlined label="Concepto (por qué debe)" maxlength="255" :rules="[v => !!(v || '').trim() || 'Indicá el concepto']"/>
            <q-input v-model="formDeuda.monto" type="number" step="0.01" min="0.01" outlined label="Monto Bs" :rules="[montoValido]"/>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat no-caps label="Cancelar" :disable="guardando" v-close-popup/>
            <q-btn type="submit" unelevated no-caps color="red-7" icon="save" label="Guardar deuda" :loading="guardando"/>
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogAbono" persistent>
      <q-card style="width: 480px; max-width: 95vw">
        <q-form @submit="guardarAbono">
          <q-card-section><div class="text-h6">Cobrar abono</div>{{ seleccion.cliente }} · {{ seleccion.concepto }}<div>Saldo: <b>Bs {{ money(seleccion.saldo) }}</b></div></q-card-section>
          <q-card-section class="q-gutter-md">
            <q-input v-model="abono.monto" outlined type="number" step="0.01" min="0.01" :max="seleccion.saldo" label="Monto a cobrar Bs" :rules="[montoValido, v => Number(v) <= seleccion.saldo || 'Supera el saldo']"/>
            <q-select v-model="abono.forma_pago" outlined label="Forma de pago" :options="['EFECTIVO', 'QR', 'TRANSFERENCIA']"/>
            <q-input v-model="abono.referencia" outlined label="Boleta / referencia" maxlength="100"/>
          </q-card-section>
          <q-card-actions align="right"><q-btn flat label="Cancelar" :disable="guardando" v-close-popup/><q-btn color="positive" type="submit" label="Registrar cobro" :loading="guardando"/></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { date, uid } from 'quasar'
import AccionesReporteCredito from 'components/AccionesReporteCredito.vue'
import { reporteGeneral, reporteCliente, emitirReporte } from 'src/utils/reportesCreditos'

function detalleVacio () {
  return { cliente: {}, deudas: [], abonos: [], ventas: [], totales: { saldo: 0, deudas: 0, abonado: 0, ventas: 0, vendido: 0 } }
}

export default {
  name: 'CreditosClientes',
  components: { AccionesReporteCredito },
  data () {
    return {
      clientes: [],
      reportando: false,
      totales: { clientes: 0, con_deuda: 0, saldo: 0, deudas: 0 },
      buscar: '',
      // Arranca en los que deben: es lo que cobranzas viene a mirar.
      filtro: 'deuda',
      zona: null,
      vendedor: null,
      cargando: false,
      error: '',
      paginacion: { rowsPerPage: 20, sortBy: 'saldo', descending: true },
      dialogCliente: false,
      cargandoDetalle: false,
      clienteId: null,
      detalle: detalleVacio(),
      pestana: 'deudas',
      soloPendientes: true,
      guardando: false,
      dialogDeuda: false,
      formDeuda: { cliente: null, fecha: '', concepto: '', monto: '', solicitud_id: '' },
      opcionesCliente: [],
      dialogAbono: false,
      seleccion: {},
      abono: {},
      columnas: [
        { name: 'nombre', label: 'Cliente', field: 'nombre', align: 'left', sortable: true },
        { name: 'telefono', label: 'Teléfono', field: 'telefono', align: 'left' },
        { name: 'direccion', label: 'Dirección', field: 'direccion', align: 'left', classes: 'celda-direccion' },
        { name: 'zona', label: 'Zona', field: 'zona', align: 'left', sortable: true },
        { name: 'vendedor', label: 'Preventista', field: 'vendedor', align: 'left', sortable: true },
        { name: 'deudas', label: 'Ventas', field: 'deudas', align: 'center', sortable: true, format: v => v || '' },
        { name: 'dias', label: 'Desde', field: 'dias', align: 'center', sortable: true },
        { name: 'saldo', label: 'Deuda Bs', field: 'saldo', align: 'right', sortable: true },
        { name: 'reportes', label: 'Reportes', field: 'id', align: 'right' }
      ],
      columnasHistorial: [
        { name: 'id', label: '#', field: 'id', align: 'left', sortable: true },
        { name: 'created_at', label: 'Fecha', field: 'created_at', align: 'left' },
        { name: 'monto', label: 'Monto Bs', field: 'monto', align: 'right', sortable: true, format: v => this.money(v) },
        { name: 'forma_pago', label: 'Pago', field: 'forma_pago' },
        { name: 'referencia', label: 'Boleta / referencia', field: 'referencia' },
        { name: 'cobrador', label: 'Cobrador', field: 'cobrador' }
      ]
    }
  },
  computed: {
    abonosPorDeuda () {
      return (this.detalle.abonos || []).reduce((grupos, abono) => {
        const clave = abono.origen + ':' + abono.deuda_id
        if (!grupos[clave]) grupos[clave] = []
        grupos[clave].push(abono)
        return grupos
      }, {})
    },
    zonas () {
      return [...new Set(this.clientes.map(c => c.zona).filter(Boolean))].sort()
    },
    vendedores () {
      return [...new Set(this.clientes.map(c => c.vendedor).filter(Boolean))].sort()
    },
    filtrados () {
      const texto = (this.buscar || '').trim().toLowerCase()
      return this.clientes.filter(c => {
        if (this.filtro === 'deuda' && !(c.saldo > 0)) return false
        if (this.filtro === 'sin' && c.saldo > 0) return false
        if (this.zona && c.zona !== this.zona) return false
        if (this.vendedor && c.vendedor !== this.vendedor) return false
        if (!texto) return true
        return c.nombre.toLowerCase().includes(texto) || c.nit.includes(texto) || c.telefono.includes(texto)
      })
    },
    deudasVisibles () {
      return this.detalle.deudas.filter(d => !this.soloPendientes || d.saldo > 0 || d.estado.includes('REVISAR'))
    }
  },
  mounted () { this.cargar() },
  methods: {
    async generarReporte (formato, preparar) {
      if (this.reportando) return
      this.reportando = true
      try { emitirReporte(await preparar(), formato) }
      catch (e) { this.$q.notify({ type: 'negative', message: this.mensaje(e) }) }
      finally { this.reportando = false }
    },
    reporteTotal (formato, filtrado = false) {
      const alcance = filtrado
        ? `Clientes filtrados · Estado: ${{ deuda: 'Con deuda', sin: 'Sin deuda', todos: 'Todos' }[this.filtro]} · Buscar: ${this.buscar || 'Todos'} · Zona: ${this.zona || 'Todas'} · Preventista: ${this.vendedor || 'Todos'}`
        : 'Total general · Todos los clientes'
      return this.generarReporte(formato, () => reporteGeneral(filtrado ? this.filtrados : this.clientes, alcance))
    },
    exportarCliente (formato, cliente) {
      return this.generarReporte(formato, async () => {
        const detalle = cliente ? (await this.$api.get('creditos/clientes/' + cliente.id)).data : this.detalle
        return reporteCliente(detalle)
      })
    },
    exportarDeuda (formato, deuda) {
      const detalle = this.detalle
      return this.generarReporte(formato, async () => {
        const { data } = await this.$api.get(`creditos/${deuda.origen}/${deuda.id}/abonos`)
        return reporteCliente(detalle, deuda, data)
      })
    },
    exportarVenta (formato, venta) {
      const deuda = this.detalle.deudas.find(d => d.origen === 'factura' && String(d.id) === String(venta.id))
      return this.exportarDeuda(formato, deuda || { origen: 'factura', id: venta.id, fecha: venta.fecha, concepto: 'Venta #' + venta.id, estado: venta.estado, monto: venta.total, pagado: venta.pagado, saldo: venta.saldo })
    },
    money (v) { return Number(v || 0).toFixed(2) },
    cantidad (v) { return Number(v || 0).toLocaleString('es-BO', { maximumFractionDigits: 3 }) },
    colorDias (dias) {
      if (dias > 30) return 'red-8'
      if (dias > 15) return 'orange-8'
      return 'blue-grey-6'
    },
    montoValido (v) { return (/^\d+(\.\d{1,2})?$/.test(String(v)) && Number(v) > 0) || 'Indique un monto positivo con hasta dos decimales' },
    mensaje (e) { return Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'No se pudo completar la operación. Intente nuevamente.' },
    async cargar () {
      this.cargando = true
      this.error = ''
      try {
        const { data } = await this.$api.get('creditos/resumen')
        this.clientes = data.clientes
        this.totales = data.totales
      } catch (e) {
        this.error = this.mensaje(e)
      } finally {
        this.cargando = false
      }
    },
    abrirCliente (fila) {
      this.clienteId = fila.id
      this.detalle = detalleVacio()
      this.detalle.cliente = { nombre: fila.nombre, nit: fila.nit }
      this.pestana = 'deudas'
      this.soloPendientes = true
      this.dialogCliente = true
      this.cargarDetalle()
    },
    async cargarDetalle () {
      this.cargandoDetalle = true
      try {
        this.detalle = (await this.$api.get('creditos/clientes/' + this.clienteId)).data
      } catch (e) {
        this.dialogCliente = false
        this.$q.notify({ type: 'negative', message: this.mensaje(e) })
      } finally {
        this.cargandoDetalle = false
      }
    },
    /** Abre el formulario; desde el detalle llega con el cliente ya elegido. */
    nuevaDeuda (cliente) {
      const elegido = cliente && cliente.id ? (this.clientes.find(c => c.id === cliente.id) || cliente) : null
      this.formDeuda = {
        cliente: elegido,
        fecha: date.formatDate(Date.now(), 'YYYY-MM-DD'),
        concepto: '',
        monto: '',
        // Evita que un doble toque registre la deuda dos veces.
        solicitud_id: uid()
      }
      this.opcionesCliente = elegido ? [elegido] : []
      this.dialogDeuda = true
    },
    // Se busca sobre la lista ya cargada: son pocos miles y responde al instante.
    filtrarClientes (texto, update) {
      const buscado = (texto || '').trim().toLowerCase()
      update(() => {
        this.opcionesCliente = buscado.length < 2
          ? []
          : this.clientes.filter(c => c.nombre.toLowerCase().includes(buscado) || c.nit.includes(buscado)).slice(0, 40)
      })
    },
    async guardarDeuda () {
      if (this.guardando) return
      this.guardando = true
      try {
        const { cliente, ...resto } = this.formDeuda
        await this.$api.post('creditos', { ...resto, concepto: resto.concepto.trim(), cliente_id: cliente.id })
        this.dialogDeuda = false
        this.$q.notify({ type: 'positive', message: 'Deuda agregada a ' + cliente.nombre })
        const tareas = [this.cargar()]
        if (this.dialogCliente && this.clienteId === cliente.id) tareas.push(this.cargarDetalle())
        await Promise.all(tareas)
      } catch (e) { this.$q.notify({ type: 'negative', message: this.mensaje(e) }) }
      finally { this.guardando = false }
    },
    abrirAbono (fila) {
      this.seleccion = fila
      this.abono = { monto: '', forma_pago: 'EFECTIVO', referencia: '', solicitud_id: uid() }
      this.dialogAbono = true
    },
    async guardarAbono () {
      if (this.guardando) return
      this.guardando = true
      try {
        await this.$api.post(`creditos/${this.seleccion.origen}/${this.seleccion.id}/abonos`, this.abono)
        this.dialogAbono = false
        this.$q.notify({ type: 'positive', message: 'Abono registrado y saldo actualizado' })
        // Se refrescan el detalle abierto y la lista, que muestra la deuda.
        await Promise.all([this.cargarDetalle(), this.cargar()])
      } catch (e) { this.$q.notify({ type: 'negative', message: this.mensaje(e) }) }
      finally { this.guardando = false }
    },

  }
}
</script>

<style scoped>
.deuda-compacta { border: 1px solid #dce2e5; border-radius: 4px; overflow: hidden; }
.cabecera-deuda { padding: 4px 6px; background: #f1f4f6; font-size: 12px; }
.resumen-deuda, .sin-abonos { font-size: 11px; line-height: 1.4; }
.sin-abonos { padding: 3px 6px; }
.acciones-deuda :deep(.q-btn) { min-height: 24px; font-size: 11px; padding: 2px 5px; }

.tabla-abonos :deep(th), .tabla-abonos :deep(td) {
  padding: 2px 5px;
  height: 22px;
  font-size: 11px;
  line-height: 1.25;
}
.tabla-abonos :deep(th) { background: #eceff1; font-weight: 700; }
.tabla-abonos :deep(tbody tr:nth-child(even)) { background: #f7faf8; }
.tabla-abonos :deep(td) { white-space: normal; min-width: 75px; }

.tarjeta {
  padding: 6px 10px;
}
.tarjeta-rotulo {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.4px;
}
.tarjeta-valor {
  font-size: 18px;
  font-weight: 800;
  line-height: 1.2;
}
.tabla-clientes :deep(tbody tr) {
  cursor: pointer;
}
.tabla-clientes :deep(.celda-direccion) {
  max-width: 260px;
  white-space: normal;
}
.borde-deuda {
  border-left: 4px solid #e53935;
}
.contenedor-productos {
  overflow-x: auto;
  padding: 0 8px 8px;
}
.tabla-productos {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}
.tabla-productos th {
  background: #eceff1;
  font-size: 10px;
  padding: 3px 6px;
}
.tabla-productos td {
  padding: 3px 6px;
  border-bottom: 1px solid #eee;
}
.tabla-productos tfoot td {
  font-weight: 700;
  border-bottom: none;
}
</style>
