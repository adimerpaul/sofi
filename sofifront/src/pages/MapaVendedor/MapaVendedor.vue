<template>
  <q-page class="bg-grey-3 q-pa-xs">
    <q-card flat bordered>
      <q-card-section>
        <div class="row">
          <div class="col-12 col-md-3">
            <q-input v-model="fecha" label="Fecha" type="date" dense outlined />
          </div>
          <div class="col-12 col-md-2 text-center">
            <q-btn label="Buscar" color="primary" dense @click="buscar"  icon="search" no-caps :loading="loading" />
          </div>
          <div class="col-12 col-md-12 ">
            <pre>{{pedidos}}</pre>
          </div>
        </div>
      </q-card-section>
    </q-card>
  </q-page>
</template>
<script>
import moment from "moment";

export default {
  name: "MapaVendedor",
  data() {
    return {
      fecha: moment().format("YYYY-MM-DD"),
      loading: false,
      pedidos: [],
    };
  },
  mounted() {
    this.buscar();
  },
  methods: {
    buscar() {
      this.loading = true;
      this.$api.post("mapaVendedor", { fecha: this.fecha }).then((res) => {
        this.pedidos = res.data;
      }).finally(() => {
        this.loading = false;
      });
    },
  },
};
</script>
