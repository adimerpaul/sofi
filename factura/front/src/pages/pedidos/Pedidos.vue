<template>
  <q-page class="q-pa-xs">
    <!-- Estadísticas de pedidos -->
    <div class="row">
      <div class="col-12 col-md-4 q-pa-xs">
        <q-card flat bordered>
          <q-card-section class="q-pa-none">
            <q-item class="bg-info">
              <q-item-section avatar>
                <q-icon name="shopping_cart" size="50px" color="white" />
              </q-item-section>
              <q-item-section>
                <q-item-label caption class="text-white">Pedidos Pendientes</q-item-label>
                <q-item-label class="text-white text-h4">{{ totalPendientes }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-4 q-pa-xs">
        <q-card flat bordered>
          <q-card-section class="q-pa-none">
            <q-item class="bg-green">
              <q-item-section avatar>
                <q-icon name="local_shipping" size="50px" color="white" />
              </q-item-section>
              <q-item-section>
                <q-item-label caption class="text-white">Pedidos Aceptados</q-item-label>
                <q-item-label class="text-white text-h4">{{ totalEnviados }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-4 q-pa-xs">
        <q-card flat bordered>
          <q-card-section class="q-pa-none">
            <q-item class="bg-red">
              <q-item-section avatar>
                <q-icon name="cancel" size="50px" color="white" />
              </q-item-section>
              <q-item-section>
                <q-item-label caption class="text-white">Pedidos Anulados</q-item-label>
                <q-item-label class="text-white text-h4">{{ pedidos.filter(p => p.estado === 'Anulado').length }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Filtros -->
    <q-card flat bordered class="q-mt-sm">
      <q-card-section class="q-pa-none">
        <div class="row q-col-gutter-sm q-pa-sm">
          <div class="col-12 col-md-3">
            <q-input v-model="fechaInicio" label="Fecha inicio" dense outlined type="date" />
          </div>
          <div class="col-12 col-md-3">
            <q-input v-model="fechaFin" label="Fecha fin" dense outlined type="date" />
          </div>
          <div class="col-12 col-md-3">
            <q-btn color="primary" label="Buscar" icon="search" @click="cargarPedidos" :loading="loading" no-caps />
          </div>
          <div class="col-12 col-md-3">
            <q-btn color="positive" label="Nuevo Pedido" icon="add_circle_outline" @click="$router.push('pedidosCompra')" no-caps />
          </div>
        </div>
      </q-card-section>
    </q-card>

    <!-- Tabla de pedidos -->
    <q-markup-table dense wrap-cells>
      <thead>
      <tr class="bg-primary text-white">
        <th>Opciones</th>
        <th>ID</th>
        <th>Fecha</th>
        <th>Detalle</th>
        <th>Usuario</th>
        <th>Estado</th>
<!--        <th>Total</th>-->
        <th>Observaciones</th>
      </tr>
      </thead>
      <tbody>
      <tr v-for="pedido in pedidos" :key="pedido.id">
        <td>
          <q-btn-dropdown color="primary" label="Opciones" no-caps dense size="10px">
            <q-item clickable @click="aceptar(pedido.id)" v-close-popup>
              <q-item-section avatar><q-icon name="check" /></q-item-section>
              <q-item-section>Aceptar</q-item-section>
            </q-item>
            <q-item clickable @click="anular(pedido.id)" v-close-popup>
              <q-item-section avatar><q-icon name="delete" /></q-item-section>
              <q-item-section>Anular</q-item-section>
            </q-item>
<!--            imprimri cambiar observacion-->
            <q-item clickable @click="imprimir(pedido)" v-close-popup>
              <q-item-section avatar><q-icon name="print" /></q-item-section>
              <q-item-section>Imprimir</q-item-section>
            </q-item>
            <q-item clickable @click="observaciones(pedido)" v-close-popup>
              <q-item-section avatar><q-icon name="edit" /></q-item-section>
              <q-item-section>Observaciones</q-item-section>
            </q-item>
          </q-btn-dropdown>
        </td>
        <td>{{ pedido.id }}</td>
        <td>{{ pedido.fecha }} {{ pedido.hora }}</td>
        <td style="max-width: 120px; wrap-option: wrap;line-height: 0.9;">
          {{ pedido.textDetalle }}
        </td>
        <td>{{ pedido.user?.name }}</td>
        <td>
          <q-chip :color="pedido.estado === 'Pendiente' ? 'orange' : pedido.estado === 'Aceptado' ? 'green' : 'red'" class="text-white" dense>
            {{ pedido.estado }}
          </q-chip>
        </td>
<!--        <td class="text-bold">{{ pedido.total }} Bs</td>-->
        <td>{{ pedido.observaciones }}</td>
      </tr>
      </tbody>
    </q-markup-table>
    <div id="myElement" class="hidden"></div>
  </q-page>
</template>

<script>
import moment from 'moment';
import {Imprimir} from "src/addons/Imprimir.js";

export default {
  data() {
    return {
      pedidos: [],
      fechaInicio: moment().format('YYYY-MM-DD'),
      fechaFin: moment().format('YYYY-MM-DD'),
      loading: false,
      estados: [
        { label: 'Pendiente', value: 'Pendiente' },
        { label: 'Aceptado', value: 'Aceptado' },
        { label: 'Anulado', value: 'Anulado' }
      ]
    };
  },
  computed: {
    totalPendientes() {
      return this.pedidos.filter(p => p.estado === 'Pendiente').length;
    },
    totalEnviados() {
      return this.pedidos.filter(p => p.estado === 'Aceptado').length;
    }
  },
  methods: {
    observaciones(pedido) {
      this.$q.dialog({
        title: 'Observaciones',
        message: 'Ingrese las observaciones del pedido',
        prompt: {
          model: pedido.observaciones,
          type: 'textarea',
          outlined: true,
          label: 'Observaciones',

        },
        cancel: true,
        persistent: true
      }).onOk((observaciones) => {
        this.$axios.put(`/pedidos/${pedido.id}`, { observaciones }).then(() => {
          this.cargarPedidos();
          this.$alert.success("Observaciones guardadas");
        }).catch(() => {
          this.$alert.error("Error al guardar las observaciones");
        });
      });
    },
    imprimir(pedido) {
      Imprimir.reciboPedido(pedido);
    },
    aceptar(id) {
      this.$q.dialog({
        title: 'Aceptar Pedido',
        message: '¿Está seguro de aceptar este pedido?',
        cancel: true,
        persistent: true
      }).onOk(() => {
        this.$axios.put(`/pedidos/${id}`, { estado: 'Aceptado' }).then(() => {
          this.cargarPedidos();
          this.$alert.success("Pedido aceptado");
        }).catch(() => {
          this.$alert.error("Error al aceptar el pedido");
        });
      });
    },
    anular(id) {
      this.$q.dialog({
        title: 'Anular Pedido',
        message: '¿Está seguro de anular este pedido?',
        cancel: true,
        persistent: true
      }).onOk(() => {
        this.$axios.put(`/pedidos/${id}`, { estado: 'Anulado' }).then(() => {
          this.cargarPedidos();
          this.$alert.success("Pedido anulado");
        }).catch(() => {
          this.$alert.error("Error al anular el pedido");
        });
      });
    },
    cargarPedidos() {
      this.loading = true;
      console.log(this.fechaInicio, this.fechaFin);
      this.$axios.get('/pedidos', {
        params: {
          fechaInicio: this.fechaInicio,
          fechaFin: this.fechaFin
        }
      }).then(res => {
        this.pedidos = res.data.data || res.data;
      }).catch(() => {
        this.$alert.error("Error al obtener pedidos");
      }).finally(() => {
        this.loading = false;
      });
    }
  },
  mounted() {
    this.cargarPedidos();
  }
};
</script>
