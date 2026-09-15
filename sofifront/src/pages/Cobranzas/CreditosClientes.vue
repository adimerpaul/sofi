<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-gutter-sm q-mb-sm">
      <div class="text-h6">Créditos de clientes</div>
      <q-space/>
      <q-btn outline dense no-caps color="primary" icon="local_shipping" label="Reportes de camiones" to="/cobranzas/recojo"/>
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
          <div class="tarjeta-rotulo text-blue-grey-8">VENTAS A CRÉDITO PENDIENTES</div>
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
          <q-btn round flat dense icon="close" v-close-popup/>
        </q-card-section>

        <div v-if="cargandoDetalle" class="flex flex-center q-pa-xl"><q-spinner color="primary" size="40px"/></div>

        <q-card-section v-else class="q-pa-sm" :class="$q.screen.lt.md ? 'col scroll' : ''">
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
              <q-list v-else bordered separator class="rounded-borders">
                <q-item v-for="deuda in deudasVisibles" :key="deuda.clave">
                  <q-item-section>
                    <q-item-label class="text-weight-medium">{{ deuda.concepto }}</q-item-label>
                    <q-item-label caption>
                      {{ deuda.fecha }} · Total Bs {{ money(deuda.monto) }} · Abonado Bs {{ money(deuda.pagado) }}
                    </q-item-label>
                    <q-item-label>
                      <q-badge :color="deuda.saldo > 0 ? 'orange-8' : (deuda.estado.includes('REVISAR') ? 'red-7' : 'green-7')">
                        {{ deuda.estado }}
                      </q-badge>
                    </q-item-label>
                  </q-item-section>
                  <q-item-section side class="text-right">
                    <q-item-label class="text-weight-bolder" :class="deuda.saldo > 0 ? 'text-red-9' : 'text-grey-6'">
                      Bs {{ money(deuda.saldo) }}
                    </q-item-label>
                    <div class="row no-wrap q-gutter-xs q-mt-xs">
                      <q-btn dense unelevated no-caps color="positive" icon="payments" label="Abonar"
                             :disable="deuda.saldo <= 0" @click="abrirAbono(deuda)"/>
                      <q-btn dense flat no-caps color="primary" icon="history" label="Abonos" @click="verHistorial(deuda)"/>
                    </div>
                  </q-item-section>
                </q-item>
              </q-list>
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
    <q-dialog v-model="dialogHistorial">
      <q-card style="width: 780px; max-width: 95vw">
        <q-card-section class="row items-center"><div class="text-h6">Abonos · {{ seleccion.concepto }}</div><q-space/><q-btn flat round icon="close" v-close-popup/></q-card-section>
        <q-card-section><q-table :rows="historial" :columns="columnasHistorial" row-key="id" :loading="cargandoHistorial" no-data-label="Sin abonos registrados" wrap-cells/></q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { uid } from 'quasar'

function detalleVacio () {
  return { cliente: {}, deudas: [], ventas: [], totales: { saldo: 0, deudas: 0, abonado: 0, ventas: 0, vendido: 0 } }
}

export default {
  name: 'CreditosClientes',
  data () {
    return {
      clientes: [],
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
      dialogAbono: false,
      dialogHistorial: false,
      cargandoHistorial: false,
      historial: [],
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
        { name: 'saldo', label: 'Deuda Bs', field: 'saldo', align: 'right', sortable: true }
      ],
      columnasHistorial: [
        { name: 'created_at', label: 'Fecha', field: 'created_at', align: 'left' },
        { name: 'monto', label: 'Monto Bs', field: 'monto' },
        { name: 'forma_pago', label: 'Pago', field: 'forma_pago' },
        { name: 'referencia', label: 'Referencia', field: 'referencia' },
        { name: 'cobrador', label: 'Cobrador', field: 'cobrador' }
      ]
    }
  },
  computed: {
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
    async verHistorial (fila) {
      this.seleccion = fila
      this.historial = []
      this.dialogHistorial = true
      this.cargandoHistorial = true
      try { this.historial = (await this.$api.get(`creditos/${fila.origen}/${fila.id}/abonos`)).data }
      catch (e) { this.dialogHistorial = false; this.$q.notify({ type: 'negative', message: this.mensaje(e) }) }
      finally { this.cargandoHistorial = false }
    }
  }
}
</script>

<style scoped>
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
