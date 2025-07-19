<template>
  <q-page class="bg-grey-3 q-pa-xs">
    <q-card flat bordered>
      <q-card-section>
        <div class="row">
          <!-- Filtros -->
          <div class="col-12 col-md-3">
            <q-input v-model="fecha" label="Fecha" type="date" dense outlined />
          </div>
          <div class="col-12 col-md-3">
            <q-select v-model="tipo" label="Tipo" dense outlined :options="tipos" emit-value map-options />
          </div>
          <div class="col-12 col-md-2 text-center">
            <q-btn label="Buscar" color="primary" dense @click="buscar" icon="search" no-caps :loading="loading" />
          </div>
          <div class="col-12 col-md-2 text-center">
            <q-btn label="Ver Todos" color="grey" dense flat @click="verTodos" icon="list" no-caps />
          </div>

          <!-- Tabla y Mapa -->
          <div class="col-12 col-md-4">
            <q-markup-table flat bordered dense wrap-cells>
              <thead>
              <tr>
                <th>#</th>
                <th>Pedidos</th>
                <th>Cantidades</th>
              </tr>
              </thead>
              <tbody>
              <tr v-for="(user, index) in pedidos.users" :key="index" @click="seleccionar(user)" class="cursor-pointer">
                <td>{{ index + 1 }}</td>
                <td>
                  <div style="max-width: 80px;">{{ $filters.capitalize(user.nombreCompleto) }}</div>
                </td>
                <td>
                  <q-linear-progress size="20px" :value="1" color="accent">
                    <div class="absolute-full flex flex-center">
                      <q-badge color="white" text-color="black" :label="'Clientes ' + user.clientes.length" />
                    </div>
                  </q-linear-progress>
                  <q-linear-progress size="20px" :value="user.pedidos.creados / user.pedidos.cantidad" color="green">
                    <div class="absolute-full flex flex-center">
                      <q-badge color="white" text-color="black" :label="'Pedido ' + user.pedidos.creados" />
                    </div>
                  </q-linear-progress>
                  <q-linear-progress size="20px" :value="user.pedidos.nopedido / user.pedidos.cantidad" color="red">
                    <div class="absolute-full flex flex-center">
                      <q-badge color="white" text-color="black" :label="'No pedido ' + user.pedidos.nopedido" />
                    </div>
                  </q-linear-progress>
                  <q-linear-progress size="20px" :value="user.pedidos.parado / user.pedidos.cantidad" color="orange">
                    <div class="absolute-full flex flex-center">
                      <q-badge color="white" text-color="black" :label="'Parado ' + user.pedidos.parado" />
                    </div>
                  </q-linear-progress>
                </td>
              </tr>
              </tbody>
              <tfoot>
              <tr>
                <td></td>
                <td class="text-right text-bold">Total</td>
                <td class="text-bold text-right">{{ total }}</td>
              </tr>
              </tfoot>
            </q-markup-table>
          </div>

          <!-- Mapa -->
          <div class="col-12 col-md-8">
            <div style="height: 350px; width: 100%;">
              <l-map v-model="zoom" :zoom="zoom" :center="center">
                <l-tile-layer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
                <template v-for="(user) in pedidosMapa?.users">
                  <template v-for="(cliente, i) in user.clientes">
                    <l-marker
                      v-if="isValidLatLng(cliente.Latitud, cliente.longitud)"
                      :key="i"
                      :lat-lng="[parseFloat(cliente.Latitud), parseFloat(cliente.longitud)]"
                    >
                      <l-tooltip :content="cliente.Nombres"></l-tooltip>
                      <l-icon>
                        <q-badge
                          style="padding: 2px"
                          :color="cliente.estado === 'PEDIDO' ? 'green' : cliente.estado === 'NO PEDIDO' ? 'red' : cliente.estado === 'PARADO' ? 'orange' : 'grey'"
                        >
                          {{ cliente.Cod_Aut }}
                        </q-badge>
                      </l-icon>
                    </l-marker>
                  </template>
                </template>
              </l-map>
            </div>
          </div>
        </div>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script>
import { LIcon, LMap, LMarker, LTileLayer, LTooltip } from "@vue-leaflet/vue-leaflet";
import moment from "moment";

export default {
  name: "MapaVendedorVisita",
  components: {
    LMap,
    LIcon,
    LTileLayer,
    LMarker,
    LTooltip
  },
  data() {
    return {
      center: [-17.969721, -67.114493],
      zoom: 13,
      fecha: moment().format("YYYY-MM-DD"),
      loading: false,
      pedidos: [],
      pedidosAll: [],
      pedidosMapa: [],
      tipo: "",
      tipos: [
        { label: "TODOS", value: "" },
        { label: "CERDO", value: "CERDO" },
        { label: "EMBUTIDO", value: "NORMAL" },
        { label: "POLLO", value: "POLLO" },
        { label: "RES", value: "RES" }
      ]
    };
  },
  mounted() {
    this.buscar();
  },
  methods: {
    isValidLatLng(lat, lng) {
      const latNum = parseFloat(lat);
      const lngNum = parseFloat(lng);
      return !isNaN(latNum) && !isNaN(lngNum) && latNum !== 0 && lngNum !== 0;
    },
    buscar() {
      this.loading = true;
      this.$api.post("mapaVendedorVisita", {
        fecha: this.fecha,
        tipo: this.tipo
      }).then((res) => {
        // contar por estado
        res.data.users.forEach(user => {
          let creados = 0, nopedido = 0, parado = 0;
          user.clientes.forEach(c => {
            if (c.estado === 'PEDIDO') creados++;
            else if (c.estado === 'NO PEDIDO') nopedido++;
            else if (c.estado === 'PARADO') parado++;
          });
          user.pedidos = {
            creados,
            nopedido,
            parado,
            cantidad: user.clientes.length
          };
        });

        this.pedidos = res.data;
        this.pedidosMapa = { ...res.data };
        this.pedidosAll = { ...res.data };
      }).finally(() => {
        this.loading = false;
      });
    },
    seleccionar(user) {
      this.pedidosMapa = {
        ...this.pedidosMapa,
        users: this.pedidosAll.users.filter(u => u.CodAut === user.CodAut)
      };
    },
    verTodos() {
      this.pedidosMapa = { ...this.pedidosAll };
    }
  },
  computed: {
    total() {
      let sum = 0;
      if (this.pedidos?.users?.length) {
        sum = this.pedidos.users.reduce((acc, u) => acc + (u.pedidos?.cantidad || 0), 0);
      }
      return sum;
    }
  }
};
</script>
