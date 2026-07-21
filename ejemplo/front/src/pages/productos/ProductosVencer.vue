<template>
  <q-page class="q-pa-xs">
    <q-card flat bordered>
      <q-card-section class="q-pa-xs">
        <div class="text-h6">Productos por Vencer</div>
        <div class="row">
          <div class="col-12 col-md-2">
            <q-input type="number" v-model="dias" outlined dense label="Número de días" min="1" max="365" @change="consultar">
              <template v-slot:append>
                <q-icon name="calendar_today" class="cursor-pointer" @click.stop />
              </template>
            </q-input>
          </div>
          <div class="col-12 col-md-4 q-pa-xs">
            <q-option-group
              v-model="diasSelect"
              dense
              inline
              :options="[
                { label: 'Días', value: 'Dias' },
                { label: 'Semanas', value: 'Semanas' },
                { label: 'Meses', value: 'Meses' },
                { label: 'Año', value: 'Año' }
              ]"
              color="primary"
              @update:modelValue="consultar"
            />
          </div>
          <div class="col-12 col-md-2">
            <q-btn label="Consultar" color="green" icon="search" @click="consultar" :loading="loading" no-caps />
          </div>
        </div>
        <div class="flex flex-center">
          <q-pagination
            v-model="pagination.page"
            :max="Math.ceil(pagination.rowsNumber / pagination.rowsPerPage)"
            @update:modelValue="consultar"
            color="primary"
            class="q-mt-md"
            v-if="pagination.rowsNumber > pagination.rowsPerPage"
          />
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
            <th>Días restantes</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="(p,i) in productos" :key="p.id">
            <td>{{ i + 1 }}</td>
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
              <q-badge :color="diasRestantesColor(p.fecha_vencimiento).color" class="q-pa-xs">
                {{ diasRestantesColor(p.fecha_vencimiento).dias }} días
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
  name: "ProductosPorVencer",
  data() {
    return {
      dias: 30,
      diasSelect: 'Dias',
      productos: [],
      loading: false,
      pagination: {
        page: 1,
        rowsPerPage: 10,
        rowsNumber: 0
      }
    };
  },
  mounted() {
    this.consultar();
  },
  methods: {
    consultar() {
      this.loading = true;

      const dias = this.diasSelect === 'Dias' ? this.dias : this.dias * (
        this.diasSelect === 'Semanas' ? 7 :
          this.diasSelect === 'Meses' ? 30 : 365
      );

      this.$axios.get('/productosPorVencer', {
        params: {
          dias: dias,
          page: this.pagination.page,
          perPage: this.pagination.rowsPerPage
        }
      })
        .then(res => {
          this.productos = res.data.data; // Laravel pagination structure
          this.pagination.rowsNumber = res.data.total;
        })
        .catch(() => {
          this.$alert.error("Error al consultar productos por vencer");
        })
        .finally(() => {
          this.loading = false;
        });
    },
    diasRestantesColor(fechaVencimiento) {
      const hoy = moment();
      const vencimiento = moment(fechaVencimiento);
      const diasRestantes = vencimiento.diff(hoy, 'days');

      const tercio = Math.ceil(this.dias / 3);

      if (diasRestantes <= tercio) {
        return { color: 'red', dias: diasRestantes };
      } else if (diasRestantes <= tercio * 2) {
        return { color: 'orange', dias: diasRestantes };
      } else {
        return { color: 'green', dias: diasRestantes };
      }
    }
  }

}
</script>
