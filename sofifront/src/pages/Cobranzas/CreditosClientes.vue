<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-gutter-sm q-mb-md">
      <div class="text-h6">Créditos y deudas de clientes</div>
      <q-space/>
      <q-btn outline no-caps color="primary" label="Reportes de camiones" to="/cobranzas/recojo"/>
      <q-btn color="primary" no-caps icon="add" label="Crear deuda" @click="nuevaDeuda"/>
    </div>
    <q-select v-model="cliente" :options="clientes" option-label="nombre" use-input clearable outlined dense
              label="Buscar cliente por nombre o NIT (vacío: todos)" input-debounce="300"
              @filter="buscarClientes" @update:model-value="cargar" class="q-mb-md">
      <template #option="scope"><q-item v-bind="scope.itemProps"><q-item-section>{{ scope.opt.nombre }} · {{ scope.opt.nit }}</q-item-section></q-item></template>
    </q-select>
    <div class="row items-center q-gutter-sm q-mb-sm">
      <div class="text-subtitle1">Saldo pendiente: <b>Bs {{ money(saldo) }}</b></div>
      <q-space/>
      <q-toggle v-model="soloPendientes" label="Solo pendientes"/>
      <q-btn flat icon="refresh" label="Actualizar" no-caps @click="cargar" :loading="cargando"/>
    </div>
    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-sm">{{ error }}</q-banner>
    <q-table :rows="filas" :columns="columnas" row-key="clave" :loading="cargando" :filter="buscar" :pagination="{ rowsPerPage: 20 }" wrap-cells>
      <template #top-right><q-input v-model="buscar" dense outlined placeholder="Buscar en las deudas"/></template>
      <template #body-cell-opciones="props">
        <q-td :props="props">
          <template v-if="props.row.origen !== 'caja'">
            <q-btn dense no-caps color="positive" label="Cobrar abono" :disable="props.row.saldo <= 0" @click="abrirAbono(props.row)"/>
            <q-btn flat dense no-caps color="primary" label="Historial" @click="verHistorial(props.row)"/>
          </template>
          <template v-else>
            <q-btn v-if="$store.getters['login/can']('cobranza')" flat dense no-caps label="Cobranza de caja" to="/cobranza"/>
            <div class="text-caption">Por conciliar: Bs {{ money(props.row.por_conciliar) }}</div>
          </template>
        </q-td>
      </template>
    </q-table>
    <div class="text-caption text-grey-7 q-mt-sm">Las cuentas de caja muestran el saldo contable; sus cobros por conciliar se muestran aparte. Las ventas anuladas con abonos permanecen en el historial para su revisión.</div>

    <q-dialog v-model="dialogDeuda" persistent>
      <q-card style="width: 500px; max-width: 95vw">
        <q-form @submit="guardarDeuda">
          <q-card-section class="text-h6">Crear deuda de cliente</q-card-section>
          <q-card-section class="q-gutter-md">
            <q-select v-model="formCliente" :options="clientes" option-label="nombre" use-input outlined label="Cliente" @filter="buscarClientes" :rules="[v => !!v || 'Seleccione un cliente']"/>
            <q-input v-model="deuda.fecha" type="date" outlined label="Fecha" :rules="[v => !!v || 'Indique la fecha']"/>
            <q-input v-model="deuda.concepto" outlined label="Concepto / referencia de la deuda" maxlength="255" :rules="[v => !!v.trim() || 'Indique el concepto']"/>
            <q-input v-model="deuda.monto" type="number" step="0.01" min="0.01" outlined label="Monto Bs" :rules="[montoValido]"/>
          </q-card-section>
          <q-card-actions align="right"><q-btn flat label="Cancelar" :disable="guardando" v-close-popup/><q-btn type="submit" color="primary" label="Guardar deuda" :loading="guardando"/></q-card-actions>
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
    <q-dialog v-model="dialogHistorial">
      <q-card style="width: 780px; max-width: 95vw">
        <q-card-section class="row items-center"><div class="text-h6">Abonos · {{ seleccion.concepto }}</div><q-space/><q-btn flat round icon="close" v-close-popup/></q-card-section>
        <q-card-section><q-table :rows="historial" :columns="columnasHistorial" row-key="id" :loading="cargandoHistorial" no-data-label="Sin abonos registrados" wrap-cells/></q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { date, uid } from 'quasar'

export default {
  name: 'CreditosClientes',
  data () {
    return {
      cliente: null, clientes: [], deudas: [], saldo: 0, buscar: '', soloPendientes: true,
      cargando: false, guardando: false, error: '', dialogDeuda: false, dialogAbono: false,
      dialogHistorial: false, cargandoHistorial: false, historial: [], seleccion: {}, formCliente: null,
      deuda: { fecha: '', concepto: '', monto: '' }, abono: {}, secuencia: 0,
      columnas: [
        { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
        { name: 'fecha', label: 'Fecha', field: 'fecha', sortable: true },
        { name: 'concepto', label: 'Deuda / venta', field: 'concepto', align: 'left' },
        ...['monto', 'pagado', 'saldo'].map(k => ({ name: k, label: { monto: 'Monto Bs', pagado: 'Abonado Bs', saldo: 'Saldo Bs' }[k], field: k, format: v => Number(v || 0).toFixed(2), sortable: true })),
        { name: 'estado', label: 'Estado', field: 'estado' },
        { name: 'opciones', label: 'Opciones', field: 'opciones' }
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
    filas () { return this.deudas.filter(d => !this.soloPendientes || d.saldo > 0 || d.estado.includes('REVISAR')) }
  },
  mounted () { this.cargar() },
  methods: {
    money (v) { return Number(v || 0).toFixed(2) },
    montoValido (v) { return (/^\d+(\.\d{1,2})?$/.test(String(v)) && Number(v) > 0) || 'Indique un monto positivo con hasta dos decimales' },
    mensaje (e) { return Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'No se pudo completar la operación. Intente nuevamente.' },
    async buscarClientes (texto, update) {
      if (texto.trim().length < 2) { update(() => { this.clientes = [] }); return }
      try {
        const { data } = await this.$api.get('creditos/clientes', { params: { buscar: texto.trim() } })
        update(() => { this.clientes = data })
      } catch (e) { update(() => { this.clientes = [] }); this.$q.notify({ type: 'negative', message: this.mensaje(e) }) }
    },
    async cargar () {
      const secuencia = ++this.secuencia
      this.cargando = true
      this.error = ''
      try {
        const { data } = await this.$api.get('creditos', { params: { cliente_id: this.cliente?.id } })
        if (secuencia !== this.secuencia) return
        this.deudas = data.deudas
        this.saldo = data.saldo
      } catch (e) { if (secuencia === this.secuencia) { this.error = this.mensaje(e); this.deudas = []; this.saldo = 0 } }
      finally { if (secuencia === this.secuencia) this.cargando = false }
    },
    nuevaDeuda () {
      this.formCliente = this.cliente
      this.deuda = { fecha: date.formatDate(Date.now(), 'YYYY-MM-DD'), concepto: '', monto: '', solicitud_id: uid() }
      this.dialogDeuda = true
    },
    async guardarDeuda () {
      if (this.guardando) return
      this.guardando = true
      try {
        await this.$api.post('creditos', { ...this.deuda, cliente_id: this.formCliente.id })
        this.dialogDeuda = false
        this.cliente = this.formCliente
        this.$q.notify({ type: 'positive', message: 'Deuda registrada' })
        await this.cargar()
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
        await this.cargar()
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
