<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div class="col">
        <div class="text-h6">Cobros de créditos por confirmar</div>
        <div class="text-caption text-grey-7">Lo que cobraron los vendedores. La deuda solo baja al confirmar.</div>
      </div>
      <q-btn flat round icon="refresh" color="primary" :loading="cargando" @click="cargar"><q-tooltip>Actualizar</q-tooltip></q-btn>
    </div>

    <div class="row q-col-gutter-sm items-center q-mb-sm">
      <div class="col-12 col-sm-auto">
        <q-btn-toggle v-model="estado" no-caps unelevated toggle-color="primary" color="grey-3" text-color="grey-9" @update:model-value="cargar"
                      :options="[{ label: 'Pendientes', value: 'PENDIENTE' }, { label: 'Confirmados', value: 'CONFIRMADO' }, { label: 'Rechazados', value: 'RECHAZADO' }]"/>
      </div>
      <template v-if="estado !== 'PENDIENTE'">
        <div class="col-6 col-sm-2"><q-input v-model="desde" type="date" dense outlined label="Desde" @update:model-value="cargar"/></div>
        <div class="col-6 col-sm-2"><q-input v-model="hasta" type="date" dense outlined label="Hasta" @update:model-value="cargar"/></div>
      </template>
      <div class="col-12 col-sm"><q-input v-model="buscar" dense outlined clearable placeholder="Buscar cliente, vendedor o boleta"><template #prepend><q-icon name="search"/></template></q-input></div>
    </div>

    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-sm">{{ error }}</q-banner>

    <q-table flat bordered dense :rows="filtrados" :columns="columnas" row-key="id" :loading="cargando"
             :pagination="{ rowsPerPage: 50 }" no-data-label="No hay cobros para mostrar">
      <template #top>
        <div class="text-weight-bold">{{ filtrados.length }} cobros · Total Bs {{ money(total) }}</div>
      </template>
      <template #body-cell-monto="props">
        <q-td :props="props" class="text-weight-bold">{{ money(props.value) }}</q-td>
      </template>
      <template #body-cell-saldo="props">
        <q-td :props="props">
          {{ money(props.value) }}
          <q-icon v-if="props.row.estado === 'PENDIENTE' && props.row.monto > props.row.saldo" name="warning" color="negative">
            <q-tooltip>El cobro supera el saldo actual: no se puede confirmar.</q-tooltip>
          </q-icon>
        </q-td>
      </template>
      <template #body-cell-acciones="props">
        <q-td :props="props">
          <template v-if="props.row.estado === 'PENDIENTE'">
            <q-btn dense unelevated no-caps color="positive" icon="check" label="Confirmar" class="q-mr-xs"
                   :loading="procesando === props.row.id" :disable="!!procesando" @click="confirmar(props.row)"/>
            <q-btn dense flat no-caps color="negative" icon="close" label="Rechazar" :disable="!!procesando" @click="abrirRechazo(props.row)"/>
          </template>
          <div v-else class="text-caption">
            {{ props.row.revisado_por }} · {{ String(props.row.revisado_at || '').slice(0, 16) }}
            <div v-if="props.row.motivo_rechazo" class="text-negative">{{ props.row.motivo_rechazo }}</div>
          </div>
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="rechazo.abierto" persistent>
      <q-card style="width: 420px; max-width: 95vw">
        <q-card-section>
          <div class="text-h6">Rechazar cobro</div>
          <div class="text-caption">{{ rechazo.fila.cliente }} · Bs {{ money(rechazo.fila.monto) }} · {{ rechazo.fila.vendedor }}</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <q-input v-model="rechazo.motivo" outlined dense autofocus maxlength="150" label="Motivo" hint="La deuda no cambia."/>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat no-caps label="Cancelar" v-close-popup :disable="!!procesando"/>
          <q-btn unelevated no-caps color="negative" label="Rechazar" :loading="!!procesando" :disable="!rechazo.motivo.trim()" @click="rechazar"/>
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { date } from 'quasar'

export default {
  name: 'CobrosVendedores',
  data () {
    const hoy = date.formatDate(new Date(), 'YYYY-MM-DD')
    return {
      cobros: [],
      estado: 'PENDIENTE',
      desde: hoy,
      hasta: hoy,
      buscar: '',
      cargando: false,
      error: '',
      procesando: null,
      rechazo: { abierto: false, fila: {}, motivo: '' },
      columnas: [
        { name: 'created_at', label: 'Fecha', field: r => String(r.created_at).slice(0, 16), align: 'left', sortable: true },
        { name: 'vendedor', label: 'Vendedor', field: 'vendedor', align: 'left', sortable: true },
        { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
        { name: 'concepto', label: 'Deuda', field: 'concepto', align: 'left' },
        { name: 'referencia', label: 'Boleta', field: 'referencia', align: 'left' },
        { name: 'monto', label: 'Cobrado', field: 'monto', align: 'right', sortable: true },
        { name: 'saldo', label: 'Saldo actual', field: 'saldo', align: 'right' },
        { name: 'acciones', label: '', field: 'id', align: 'right' }
      ]
    }
  },
  computed: {
    filtrados () {
      const texto = (this.buscar || '').trim().toLowerCase()
      if (!texto) return this.cobros
      return this.cobros.filter(c => [c.cliente, c.nit, c.vendedor, c.referencia].join(' ').toLowerCase().includes(texto))
    },
    total () { return this.filtrados.reduce((s, c) => s + Math.round(Number(c.monto) * 100), 0) / 100 }
  },
  created () { this.cargar() },
  methods: {
    money (v) { return Number(v || 0).toFixed(2) },
    mensaje (e) { return Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || 'No se pudo completar la operación.' },
    async cargar () {
      this.cargando = true
      this.error = ''
      try {
        const params = { estado: this.estado }
        if (this.estado !== 'PENDIENTE') Object.assign(params, { desde: this.desde, hasta: this.hasta || this.desde })
        this.cobros = (await this.$api.get('creditos/cobros-vendedor', { params })).data
      } catch (e) { this.error = this.mensaje(e) }
      finally { this.cargando = false }
    },
    confirmar (fila) {
      this.$q.dialog({
        title: 'Confirmar cobro',
        message: `Se descontará Bs ${this.money(fila.monto)} de "${fila.concepto}" de ${fila.cliente}. ¿Recibió el dinero de ${fila.vendedor}?`,
        cancel: { flat: true, noCaps: true, label: 'Cancelar' },
        ok: { unelevated: true, noCaps: true, color: 'positive', label: 'Confirmar' }
      }).onOk(async () => {
        this.procesando = fila.id
        try {
          const { data } = await this.$api.post(`creditos/cobros-vendedor/${fila.id}/confirmar`)
          this.$q.notify({ type: 'positive', message: data.message })
          await this.cargar()
        } catch (e) { this.$q.notify({ type: 'negative', message: this.mensaje(e) }) }
        finally { this.procesando = null }
      })
    },
    abrirRechazo (fila) { this.rechazo = { abierto: true, fila, motivo: '' } },
    async rechazar () {
      const fila = this.rechazo.fila
      this.procesando = fila.id
      try {
        const { data } = await this.$api.post(`creditos/cobros-vendedor/${fila.id}/rechazar`, { motivo: this.rechazo.motivo.trim() })
        this.rechazo.abierto = false
        this.$q.notify({ type: 'info', message: data.message })
        await this.cargar()
      } catch (e) { this.$q.notify({ type: 'negative', message: this.mensaje(e) }) }
      finally { this.procesando = null }
    }
  }
}
</script>
