<template>
  <div class="row inline items-center q-gutter-xs" @click.stop>
    <q-btn outline dense no-caps color="primary" icon="print" :label="compacto ? undefined : 'Imprimir'" :aria-label="'Imprimir ' + alcance" :disable="disable" @click="$emit('reporte', 'imprimir')"><q-tooltip>Imprimir {{ alcance }}</q-tooltip></q-btn>
    <q-btn outline dense no-caps color="green-8" icon="download" :label="compacto ? undefined : 'Excel'" :aria-label="'Exportar Excel ' + alcance" :disable="disable" @click="$emit('reporte', 'excel')"><q-tooltip>Exportar Excel {{ alcance }}</q-tooltip></q-btn>
  </div>
</template>

<script>
export default {
  name: 'AccionesReporteCredito',
  props: { alcance: { type: String, default: '' }, disable: Boolean, compacto: Boolean },
  emits: ['reporte']
}
</script>
