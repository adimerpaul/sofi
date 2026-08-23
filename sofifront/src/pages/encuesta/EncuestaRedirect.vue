<template>
  <q-page class="flex flex-center column bg-grey-1">
    <q-spinner-dots color="primary" size="52px" />
    <div class="text-subtitle2 text-grey-8 q-mt-md">Abriendo la encuesta…</div>
    <div class="text-caption text-grey-6 q-mt-xs">Si no ocurre nada, toca el enlace:</div>
    <a class="text-primary text-caption q-mt-sm" :href="destino">{{ destino }}</a>
  </q-page>
</template>

<script>
/**
 * Compatibilidad con enlaces/QR antiguos del SPA (#/encuesta/:idcliente/:iduser).
 * La encuesta ahora la renderiza el backend, para que el cliente pueda recargar
 * (F5) y siempre reciba el estado actual desde la BD.
 */
export default {
  name: 'EncuestaRedirect',
  computed: {
    destino () {
      const base = String(process.env.API || '')
        .replace(/\/+$/, '')
        .replace(/\/api$/, '')
      const { idcliente, iduser } = this.$route.params
      return `${base}/encuesta/${encodeURIComponent(idcliente)}/${encodeURIComponent(iduser)}`
    }
  },
  mounted () {
    window.location.replace(this.destino)
  }
}
</script>
