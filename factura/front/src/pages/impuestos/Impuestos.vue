<template>
  <q-page class="q-pa-xs">
  <q-card flat bordered>
    <q-card-section class="q-pa-xs">
      <div class="text-right">
        <q-btn color="primary" label="Generar CUI" icon="add" no-caps @click="generarCUI" :loading="loading" />
        <q-btn color="secondary" label="Generar CUFD" icon="add" no-caps class="q-ml-sm" @click="generarCUFD" :loading="loading" />
      </div>
      <q-markup-table dense wrap-cells flat bordered>
        <thead>
          <tr>
            <th>ID</th>
            <th>Opciones</th>
<!--            <th>Código</th>-->
            <th>Código Control</th>
<!--            <th>Dirección</th>-->
            <th>Fecha Vigencia</th>
            <th>Fecha Creación</th>
<!--            <th>Código Punto Venta</th>-->
<!--            <th>Código Sucursal</th>-->
          </tr>
        </thead>
        <tbody>
          <tr v-for="cufd in cufds" :key="cufd.id">
            <td>{{ cufd.id }}</td>
            <td></td>
<!--            <td>{{ cufd.codigo }}</td>-->
            <td>{{ cufd.codigoControl }}</td>
<!--            <td>{{ cufd.direccion || 'N/A' }}</td>-->
            <td>{{ cufd.fechaVigencia }}</td>
            <td>{{ cufd.fechaCreacion }}</td>
<!--            <td>{{ cufd.codigoPuntoVenta }}</td>-->
<!--            <td>{{ cufd.codigoSucursal }}</td>-->
          </tr>
        </tbody>
      </q-markup-table>
    </q-card-section>
<!--    [-->
<!--    {-->
<!--    "id": 2,-->
<!--    "codigo": "BQUE5Ql92fUZBODzI5NjBGODkzRDY=QsKhaXBqRlVKWlVE1QjZCRTdERTE1N",-->
<!--    "codigoControl": "73EFBF848A12F74",-->
<!--    "direccion": null,-->
<!--    "fechaVigencia": "2025-09-19 23:59:59",-->
<!--    "fechaCreacion": "2025-09-19 05:37:38",-->
<!--    "codigoPuntoVenta": 0,-->
<!--    "codigoSucursal": 0-->
<!--    }-->
<!--    ]-->
<!--    <pre>{{cufds}}</pre>-->
  </q-card>
  </q-page>
</template>
<script>
export default {
  name: 'ImpuestosPage',
  data() {
    return {
      loading : false,
      cufds: []
    }
  },
  mounted() {
    this.listCUFD();
  },
  methods: {
    listCUFD() {
      this.loading = true;
      this.$axios.get('impuestos/list-cufd').then(response => {
        this.cufds = response.data;
      }).catch(error => {
        this.$alert.error(error.response.data.message || 'Error al listar CUFD');
      }).finally(() => {
        this.loading = false;
      });
    },
    generarCUI() {
      this.loading = true;
      this.$axios.post('impuestos/generar-cui').then(response => {
        this.$alert.success('CUI generado correctamente');
      }).catch(error => {
        this.$alert.error(error.response.data.message || 'Error al generar CUI');
      }).finally(() => {
        this.loading = false;
      });
    },
    generarCUFD() {
      this.loading = true;
      this.$axios.post('impuestos/generar-cufd').then(response => {
        this.$alert.success('CUFD generado correctamente');
        this.listCUFD();
      }).catch(error => {
        this.$alert.error(error.response.data.message || 'Error al generar CUFD');
      }).finally(() => {
        this.loading = false;
      });
    }
  }
}
</script>
