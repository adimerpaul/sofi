<template>
  <q-page class="q-pa-xs bg-grey-3">
    <q-card>
      <q-card-section class="q-pa-xs">
        <div class="row">
          <div class="col-2">
            <q-input v-model="fecha" label="Fecha" type="date" outlined dense />
          </div>
          <div class="col-2 flex flex-center">
            <q-btn
              @click="almacenVerificar"
              :disable="loading"
              label="Verificar"
              color="primary"
              :loading="loading"
              icon="search"
              no-caps
            />
          </div>
        </div>
      </q-card-section>
    </q-card>
    <pre>{{almacenes}}</pre>
  </q-page>
</template>
<script>
import moment from "moment";

export default {
  name: "AlmacenVerificarPage",
  data() {
    return {
      // fecha: moment().format("YYYY-MM-DD"),
      fecha: '2024-08-24',
      almacenes: [],
      almacen: {},
      loading: false,
    };
  },
  mounted() {
    this.almacenVerificar()
  },
  methods: {
    almacenVerificar() {
      this.loading = true;
      this.$api.get("almacenPendientes",
        {
          params: {
            fecha: this.fecha,
          },
        }
      ).then((response) => {
          this.almacenes = response.data;
      }).finally(() => {
        this.loading = false;
      });
    },
  },
};
</script>