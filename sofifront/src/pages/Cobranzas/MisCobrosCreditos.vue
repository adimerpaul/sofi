<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div class="text-h6 col">Mis cobros de créditos</div>
      <q-btn flat round icon="refresh" color="primary" :loading="cargando" @click="cargar"><q-tooltip>Actualizar</q-tooltip></q-btn>
    </div>
    <div class="row q-col-gutter-sm q-mb-sm">
      <div class="col-6"><q-input v-model="desde" type="date" dense outlined label="Desde" @update:model-value="cargar"/></div>
      <div class="col-6"><q-input v-model="hasta" type="date" dense outlined label="Hasta" @update:model-value="cargar"/></div>
    </div>

    <!-- Lo cobrado en el rango y en que quedo: solo lo confirmado ya bajo la deuda. -->
    <div class="row q-col-gutter-sm q-mb-sm">
      <div class="col-6 col-sm-3">
        <q-card flat bordered class="q-pa-sm"><div class="text-caption">Total cobrado</div><div class="text-h6">Bs {{ money(suma()) }}</div></q-card>
      </div>
      <div v-for="e in estados" :key="e.valor" class="col-6 col-sm-3">
        <q-card flat bordered class="q-pa-sm">
          <div class="text-caption">{{ e.titulo }} · {{ cobros.filter(c => c.estado === e.valor).length }}</div>
          <div :class="'text-h6 text-' + e.color">Bs {{ money(suma(e.valor)) }}</div>
        </q-card>
      </div>
    </div>

    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-sm">{{ error }}</q-banner>
    <div v-if="cargando" class="text-center q-pa-lg" role="status"><q-spinner color="primary" size="40px"/></div>
    <template v-else>
      <q-card v-for="c in cobros" :key="c.id" flat bordered class="q-mb-sm">
        <q-card-section class="q-pa-sm">
          <div class="row items-center">
            <div class="col text-weight-bold">{{ c.cliente }}</div>
            <q-badge :color="color(c.estado)">{{ c.estado }}</q-badge>
          </div>
          <div v-if="c.concepto" class="text-caption text-grey-8">{{ c.concepto }}</div>
          <div class="row justify-between text-caption">
            <span>{{ String(c.created_at).slice(0, 16) }}<span v-if="c.referencia"> · Boleta {{ c.referencia }}</span></span>
            <strong class="text-body2">Bs {{ money(c.monto) }}</strong>
          </div>
          <div v-if="c.estado !== 'PENDIENTE'" class="text-caption text-grey-7">
            {{ c.estado === 'CONFIRMADO' ? 'Confirmado' : 'Rechazado' }} por {{ c.revisado_por }} · {{ String(c.revisado_at || '').slice(0, 16) }}
          </div>
          <div v-if="c.estado === 'RECHAZADO'" class="text-caption text-negative">Motivo: {{ c.motivo_rechazo }}</div>
        </q-card-section>
      </q-card>
      <div v-if="!cobros.length && !error" class="text-center text-grey-7 q-pa-lg">No hay cobros en estas fechas.</div>
    </template>
  </q-page>
</template>

<script>
import { date } from 'quasar'

const ESTADOS = [
  { valor: 'PENDIENTE', titulo: 'Por confirmar', color: 'orange-9' },
  { valor: 'CONFIRMADO', titulo: 'Confirmado', color: 'positive' },
  { valor: 'RECHAZADO', titulo: 'Rechazado', color: 'negative' }
]

export default {
  name: 'MisCobrosCreditos',
  data () {
    const hoy = date.formatDate(new Date(), 'YYYY-MM-DD')
    return { cobros: [], desde: hoy, hasta: hoy, cargando: false, error: '', estados: ESTADOS }
  },
  created () { this.cargar() },
  methods: {
    money (v) { return Number(v || 0).toFixed(2) },
    color (estado) { return (ESTADOS.find(e => e.valor === estado) || {}).color || 'grey' },
    // Los rechazados no cuentan en el total cobrado.
    suma (estado) {
      const filas = estado ? this.cobros.filter(c => c.estado === estado) : this.cobros.filter(c => c.estado !== 'RECHAZADO')
      return filas.reduce((s, c) => s + Math.round(Number(c.monto) * 100), 0) / 100
    },
    async cargar () {
      if (!this.desde) return
      this.cargando = true
      this.error = ''
      try {
        this.cobros = (await this.$api.get('vendedor/creditos/cobros', { params: { desde: this.desde, hasta: this.hasta || this.desde } })).data
      } catch (e) {
        this.error = Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || 'No se pudieron cargar los cobros.'
      } finally { this.cargando = false }
    }
  }
}
</script>
