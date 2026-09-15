<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div class="text-h6 col">Cobrar créditos y deudas</div>
      <q-btn flat round icon="refresh" color="primary" :loading="cargando" @click="cargar"><q-tooltip>Actualizar</q-tooltip></q-btn>
    </div>
    <q-input v-model="buscar" outlined dense clearable placeholder="Buscar cliente o CI / NIT" class="q-mb-sm">
      <template #prepend><q-icon name="search"/></template>
    </q-input>
    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-sm">{{ error }}</q-banner>
    <div v-if="cargando" class="text-center q-pa-lg" role="status"><q-spinner color="primary" size="40px"/><div>Cargando deudas…</div></div>
    <template v-else>
      <div class="text-caption text-grey-7 q-mb-sm">Clientes con saldo pendiente · {{ filtrados.length }}</div>
      <q-card v-for="c in filtrados" :key="c.id" flat bordered class="q-mb-sm">
        <q-card-section class="row items-center q-pa-sm">
          <div class="col q-pr-sm">
            <div class="text-weight-bold">{{ c.nombre }}</div>
            <div class="text-caption text-grey-7">CI / NIT: {{ c.nit }}</div>
            <div class="text-negative text-weight-bold">Deuda: Bs {{ money(c.saldo) }}</div>
          </div>
          <q-btn unelevated no-caps color="positive" label="Cobrar" @click="abrir(c)"/>
        </q-card-section>
      </q-card>
      <div v-if="!filtrados.length && !error" class="text-center text-grey-7 q-pa-lg">No hay clientes con deuda para mostrar.</div>
    </template>

    <q-dialog v-model="dialog" :persistent="guardando || enviado" :maximized="$q.screen.lt.sm">
      <q-card class="column no-wrap" style="width: 650px; max-width: 100vw">
        <q-card-section class="q-pb-sm">
          <div class="row items-center">
            <div class="text-h6 col">Cobrar a cliente</div>
            <q-btn flat round icon="close" :disable="guardando || enviado" v-close-popup aria-label="Cerrar"/>
          </div>
          <div class="text-weight-bold">{{ cliente.nombre }}</div>
          <div class="text-caption">CI / NIT: {{ cliente.nit }}</div>
        </q-card-section>
        <q-card-section class="col scroll q-pt-none relative-position">
          <q-banner v-if="errorDetalle" class="bg-red-1 text-negative q-mb-sm">
            {{ errorDetalle }}
            <template v-if="!enviado" #action><q-btn flat label="Reintentar" @click="cargarDeudas"/></template>
          </q-banner>
          <q-banner v-if="enviado && !guardando" class="bg-orange-1 q-mb-sm">No se pudo confirmar el resultado. Reintente el mismo cobro; no se registrará dos veces.</q-banner>
          <div v-if="cargandoDetalle" class="text-center q-pa-lg"><q-spinner size="40px" color="primary"/><div>Cargando saldos…</div></div>
          <template v-else>
            <q-card v-for="d in deudas" :key="d.clave" flat bordered class="q-mb-sm">
              <q-card-section class="q-pa-sm">
                <div class="text-weight-medium">{{ d.concepto }}</div>
                <div class="row justify-between text-caption q-mb-sm"><span>{{ String(d.fecha).slice(0, 10) }}</span><strong>Saldo: Bs {{ money(d.saldo) }}</strong></div>
                <div class="row q-col-gutter-sm">
                  <div class="col-6"><q-input v-model="d.recogido" outlined dense type="number" min="0" :max="d.saldo" step="0.01" label="Monto recogido" prefix="Bs" :disable="guardando || enviado"/></div>
                  <div class="col-6"><q-input v-model="d.boleta" outlined dense label="Nº de boleta" maxlength="100" :disable="guardando || enviado"/></div>
                </div>
              </q-card-section>
            </q-card>
            <div v-if="!deudas.length && !errorDetalle" class="text-center q-pa-md">El cliente no tiene deudas pendientes.</div>
          </template>
        </q-card-section>
        <q-separator/>
        <q-card-section class="q-pa-sm">
          <div class="text-h6 text-positive q-mb-sm">Total a recoger: Bs {{ money(total) }}</div>
          <q-banner v-if="validacion" dense class="bg-orange-1 q-mb-sm">{{ validacion }}</q-banner>
          <div class="text-caption text-grey-7 q-mb-sm">Cobro en efectivo. Se descontará del saldo y quedará visible para cobranzas.</div>
          <q-btn color="positive" unelevated no-caps class="full-width" icon="save" :label="enviado ? 'Reintentar el cobro' : 'Guardar cobros'"
                 :loading="guardando" :disable="cargandoDetalle || (!enviado && (total <= 0 || !!validacion || !!errorDetalle))" @click="guardar"/>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { uid } from 'quasar'

export default {
  name: 'CobrarCreditosVendedor',
  data () {
    return { clientes: [], buscar: '', cargando: false, error: '', dialog: false, cliente: {}, deudas: [], cargandoDetalle: false, errorDetalle: '', guardando: false, enviado: null }
  },
  computed: {
    filtrados () {
      const texto = (this.buscar || '').trim().toLowerCase()
      return this.clientes.filter(c => (c.nombre + ' ' + c.nit).toLowerCase().includes(texto))
    },
    total () { return this.deudas.reduce((s, d) => s + Math.round((Number(d.recogido) || 0) * 100), 0) / 100 },
    validacion () {
      for (const d of this.deudas) {
        if (d.recogido === '') continue
        if (!/^\d+(\.\d{1,2})?$/.test(String(d.recogido)) || Number(d.recogido) < 0) return 'Ingrese montos válidos con hasta dos decimales.'
        if (Math.round(Number(d.recogido) * 100) > Math.round(Number(d.saldo) * 100)) return 'El monto recogido no puede superar el saldo.'
      }
      return ''
    }
  },
  created () { this.cargar() },
  methods: {
    money (v) { return Number(v || 0).toFixed(2) },
    mensaje (e) { return Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || 'No se pudo completar la operación.' },
    async cargar () {
      this.cargando = true
      this.error = ''
      try { this.clientes = (await this.$api.get('vendedor/creditos/clientes')).data }
      catch (e) { this.error = this.mensaje(e) }
      finally { this.cargando = false }
    },
    abrir (cliente) {
      this.cliente = cliente
      this.deudas = []
      this.enviado = null
      this.dialog = true
      this.cargarDeudas()
    },
    async cargarDeudas () {
      const id = this.cliente.id
      this.cargandoDetalle = true
      this.errorDetalle = ''
      try {
        const { data } = await this.$api.get('vendedor/creditos/clientes/' + id)
        if (this.cliente.id !== id) return
        this.deudas = data.map(d => ({ ...d, recogido: '', boleta: '', solicitud_id: uid() }))
      } catch (e) { if (this.cliente.id === id) this.errorDetalle = this.mensaje(e) }
      finally { if (this.cliente.id === id) this.cargandoDetalle = false }
    },
    async guardar () {
      if (this.guardando || this.cargandoDetalle || (!this.enviado && (this.validacion || this.total <= 0))) return
      if (!this.enviado) {
        this.enviado = { cobros: this.deudas.filter(d => Number(d.recogido) > 0).map(d => ({
          origen: d.origen, id: d.id, monto: Number(d.recogido).toFixed(2), referencia: d.boleta.trim() || null, solicitud_id: d.solicitud_id
        })) }
      }
      this.guardando = true
      this.errorDetalle = ''
      try {
        await this.$api.post('vendedor/creditos/clientes/' + this.cliente.id + '/cobros', this.enviado)
        this.enviado = null
        this.dialog = false
        this.$q.notify({ type: 'positive', message: 'Cobros guardados. Cobranzas ya puede verlos.' })
        await this.cargar()
      } catch (e) {
        this.errorDetalle = this.mensaje(e)
        if ([403, 404, 409, 422].includes(e.response?.status)) this.enviado = null
      } finally { this.guardando = false }
    }
  }
}
</script>
