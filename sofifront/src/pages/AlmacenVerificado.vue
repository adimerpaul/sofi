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
              label="Verificar"
              color="primary"
              :loading="loading"
              icon="search"
              no-caps
            />
          </div>
          <div class="col-12">
            <q-markup-table dense wrap-cells>
              <thead>
                <tr>
<!--                  <th>Opciones</th>-->
                  <th>Código</th>
                  <th>Código Producto</th>
                  <th>Producto</th>
                  <th>Grupo</th>
                  <th>Registros</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="almacen in almacenes" :key="almacen.id">
<!--                  <td>-->
<!--                    <q-btn-->
<!--                      @click="almacen = almacen"-->
<!--                      label="Ver"-->
<!--                      color="primary"-->
<!--                      icon="search"-->
<!--                      no-caps-->
<!--                    />-->
<!--                  </td>-->
                  <td>{{ almacen.codigo }}</td>
                  <td>{{ almacen.codigo_producto }}</td>
                  <td>{{ $filters.capitalize(almacen.producto) }}</td>
                  <td>{{ almacen.grupo }}</td>
                  <td>
                    <ul style="list-style: none;margin: 0;padding: 0;">
                      <template v-for="registro in almacen.registros" :key="registro.id">
                        <li v-if="registro.verificado == 'Verificado'" style="margin: 0;padding: 0;font-size:9px;">
                          {{ $filters.dateYmd(registro.fecha_vencimiento) }}
                          - <q-chip dense size="10px" :color="calculateColor(registro.dias_vencimiento)">{{ registro.dias_vencimiento }}</q-chip>
                          - {{ registro.cantidad }}
<!--                          <input type="checkbox" v-model="registro.verificadoBool" @change="cambioCheck(registro)">-->
<!--                          <q-chip dense size="10px" :color="registro.verificado === 'Verificado' ? 'positive' : 'warning'">{{ registro.verificado }}</q-chip>-->
                        </li>
                      </template>
                    </ul>
                  </td>
                </tr>
              </tbody>
            </q-markup-table>
          </div>
        </div>
      </q-card-section>
    </q-card>
<!--    <pre>{{almacenes}}</pre>-->
  </q-page>
</template>
<script>
import moment from "moment";

export default {
  name: "AlmacenVerificadoPage",
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
    calculateColor(dias) {
      if (dias < 0) {
        return "negative";
      } else if (dias < 30) {
        return "warning";
      } else {
        return "positive"
      }
    },
    cambioCheck(registro) {
      if (registro.verificadoBool) {
        registro.verificado = "Verificado";
      } else {
        registro.verificado = "Pendiente";
      }
      this.$api.put("almacenRegistroVerificar/" + registro.id, {
        verificado: registro.verificado,
      }).then((response) => {
        console.log(response.data);
      });
    },
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