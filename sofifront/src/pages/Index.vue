<template>
  <q-page class="q-pa-md">
    <q-card v-if="$store.getters['login/can']('cobranzasrecojo')" flat bordered class="q-mb-md">
      <q-card-section>
        <div class="text-h6">Cobrar · Camineros</div>
        <div class="text-grey-7">Consulte lo cobrado por todos los camiones y exporte sus reportes de entrega por fecha.</div>
      </q-card-section>
      <q-card-actions>
        <q-btn color="primary" icon="payments" label="Cobrar / Reportes de camiones" to="/cobranzas/recojo" no-caps/>
        <q-btn outline color="primary" icon="account_balance_wallet" label="Créditos y deudas" to="/cobranzas/creditos" no-caps/>
      </q-card-actions>
    </q-card>
    <q-btn v-if="$store.getters['login/can']('misentregasreporte')" class="q-mb-md" outline color="primary" icon="summarize" label="Mi reporte de entregas" to="/caminero/reporte" no-caps/>
    <div class="flex flex-center">
    <img
      alt="Quasar logo"
      src="logo.png"
    >
    </div>
  </q-page>
</template>

<script>
import { defineComponent } from 'vue';

export default defineComponent({
  name: 'PageIndex'
})
</script>
