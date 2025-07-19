<template>
  <q-page class="q-pa-md">
    <div class="row">
      <div class="col-12 col-md-8">
        <q-input
          v-model="fecha"
          type="date"
          label="Filtrar por Fecha"
          @change="cargarBonificaciones"
          outlined
          :loading="loading"
          dense
        />
      </div>
      <div class="col-12 col-md-4 flex flex-center">
        <q-btn
          label="Recargar"
          color="primary"
          @click="cargarBonificaciones"
          icon="refresh"
          no-caps
          :loading="loading"
        />
      </div>
    </div>

    <q-table
      :rows="bonificaciones"
      :columns="columns"
      row-key="codAut"
      title="Bonificaciones Pendientes"
      dense
      :rows-per-page-options="[0]"
      wrap-cells
      flat
      bordered
    >
      <template v-slot:body-cell-aprobar="props">
        <q-td :props="props">
          <q-btn
            dense
            size="md"
            no-caps
            label="Aprobar"
            color="green"
            @click="aprobar(props.row.codAut)"
            :loading="loading"
            icon="check_circle_outline"
          />
        </q-td>
      </template>
      <template v-slot:body-cell-productos="props">
        <q-td :props="props">
<!--          <pre>{{props.row.productos}}</pre>-->
          <div v-for="(producto, index) in props.row.productos" :key="index">
            {{ producto.producto }} ({{ producto.cantidad }})
          </div>
        </q-td>
      </template>
    </q-table>
  </q-page>
</template>

<script>
import moment from 'moment';

export default {
  data() {
    return {
      loading: false,
      fecha: moment().format('YYYY-MM-DD'),
      bonificaciones: [],
      columns: [
        {
          name: 'codAut',
          label: 'Código',
          field: 'NroPed',
          align: 'left'
        },
        {
          name: 'cliente',
          label: 'Cliente',
          field: row => row.cliente?.Nombres || '',
          align: 'left'
        },
        {
          name: 'usuario',
          label: 'Usuario',
          field: 'usuario',
          align: 'left'
        },
        {
          name: 'productos',
          label: 'Productos',
          field: 'productos',
          align: 'left'
        },
        // {
        //   name: 'canttxt',
        //   label: 'Cantidad',
        //   field: 'Canttxt',
        //   align: 'center'
        // },
        {
          name: 'comentario',
          label: 'Comentario',
          field: 'comentario',
          align: 'left'
        },
        {
          name: 'aprobar',
          label: 'Acción',
          field: 'aprobar',
          align: 'center'
        }
      ]
    };
  },
  methods: {
    async cargarBonificaciones() {
      this.loading = true;
      try {
        const response = await this.$api.get('/bonificaciones', {
          params: { fecha: this.fecha }
        });
        this.bonificaciones = response.data;
      } catch (error) {
        this.$q.notify({
          message: 'Error al cargar bonificaciones',
          color: 'negative'
        });
      } finally {
        this.loading = false;
      }
    },
    async aprobar(id) {
      this.loading = true;
      try {
        await this.$api.post(`/bonificaciones/${id}/aprobar`);
        this.$q.notify({
          message: 'Bonificación aprobada',
          color: 'positive'
        });
        this.cargarBonificaciones();
      } catch (error) {
        this.$q.notify({
          message: 'Error al aprobar bonificación',
          color: 'negative'
        });
      } finally {
        this.loading = false;
      }
    }
  },
  mounted() {
    this.cargarBonificaciones();
  }
};
</script>
