<template>
  <q-page class="q-pa-xs">
    <q-card flat bordered>
      <q-card-section class="q-pa-xs">
        <div class="text-h6">Productos Vencidos</div>

        <div class="row q-col-gutter-sm">
          <div class="col-6">
            <q-btn label="Actualizar" color="red" icon="refresh" @click="consultar" :loading="loading" no-caps />
          </div>
          <div class="col-6 text-right">
            <q-pagination
              v-model="pagina"
              :max="Math.ceil(total / porPagina)"
              max-pages="6"
              size="sm"
              boundary-numbers
              @update:model-value="consultar"
              direction-links
              color="red"
              v-if="total > porPagina"
            />
          </div>
        </div>

        <q-markup-table dense class="q-mt-md" flat bordered>
          <thead>
          <tr>
            <th>#</th>
            <th>Producto</th>
            <th>Cantidad</th>
            <th>Lote</th>
            <th>Fecha de Vencimiento</th>
            <th>Estado</th>
            <th>Días vencido</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="(p, i) in productos" :key="p.id">
            <td>{{ (pagina - 1) * porPagina + i + 1 }}</td>
            <td>{{ p.producto?.nombre }}</td>
            <td>{{ p.cantidad }}</td>
            <td>{{ p.lote }}</td>
            <td>{{ p.fecha_vencimiento }}</td>
            <td>
              <q-badge :color="p.estado === 'Activo' ? 'green' : 'red'" class="q-pa-xs">
                {{ p.estado }}
              </q-badge>
            </td>
            <td>
              <q-badge color="negative" class="q-pa-xs">
                {{ diasVencido(p.fecha_vencimiento) }} días
              </q-badge>
            </td>
          </tr>
          </tbody>
        </q-markup-table>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script>
import moment from "moment";

export default {
  name: "ProductosVencidos",
  data() {
    return {
      productos: [],
      loading: false,
      pagina: 1,
      porPagina: 10,
      total: 0
    };
  },
  mounted() {
    this.consultar();
  },
  methods: {
    consultar() {
      this.loading = true;
      this.$axios.get('/productosVencidos', {
        params: {
          page: this.pagina,
          per_page: this.porPagina
        }
      }).then(res => {
        this.productos = res.data.data;
        this.total = res.data.total;
      }).catch(() => {
        this.$alert.error("Error al consultar productos vencidos");
      }).finally(() => {
        this.loading = false;
      });
    },
    diasVencido(fechaVencimiento) {
      const vencimiento = moment(fechaVencimiento);
      const hoy = moment();
      const dias = hoy.diff(vencimiento, 'days');
      return dias < 0 ? 0 : dias;
    }
  }
}
</script>
