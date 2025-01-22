<template>
  <q-page class="bg-grey-3 q-pa-xs">
    <q-card flat bordered>
      <q-card-section>
        <div class="row">
          <div class="col-12 col-md-3">
            <q-input v-model="fecha" label="Fecha" type="date" dense outlined />
          </div>
          <div class="col-12 col-md-3">
            <q-select v-model="tipo" label="Tipo" dense outlined :options="tipos"
                      emit-value map-options
            />
          </div>
          <div class="col-12 col-md-2 text-center">
            <q-btn label="Buscar" color="primary" dense @click="buscar"  icon="search" no-caps :loading="loading" />
          </div>
          <div class="col-12">
          </div>
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
                  <tr v-for="(user, index) in pedidos.users" :key="index" @click="seleccionar(user)">
                    <td>{{ index + 1 }}</td>
                    <td>{{ user.nombreCompleto }}</td>
                    <td>
                      <q-linear-progress size="20px" :value="1" color="accent">
                        <div class="absolute-full flex flex-center">
                          <q-badge color="white" text-color="black" :label="'Total '+user.pedidos.cantidad" />
                        </div>
                      </q-linear-progress>
                      <q-linear-progress size="20px" :value="user.pedidos.creados / user.pedidos.cantidad" color="green">
                        <div class="absolute-full flex flex-center">
                          <q-badge color="white" text-color="black" :label="'Creados '+user.pedidos.creados" />
                        </div>
                      </q-linear-progress>
                      <q-linear-progress size="20px" :value="user.pedidos.enviados / user.pedidos.cantidad" color="blue">
                        <div class="absolute-full flex flex-center">
                          <q-badge color="white" text-color="black" :label="'Enviados '+user.pedidos.enviados" />
                        </div>
                      </q-linear-progress>
                    </td>
                  </tr>
                </tbody>
              <tfoot>
                <tr>
                  <td></td>
                  <td class="text-right text-bold">Total</td>
                  <td class="text-bold text-right">
                    {{ total }}
                  </td>
                </tr>
              </tfoot>
            </q-markup-table>
          </div>
          <div class="col-12 col-md-8">
            <div style="height: 350px; width: 100%;">
              <l-map
                v-model="zoom"
                :zoom="zoom"
                :center="center"
              >
                <LTileLayer
                  url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                ></LTileLayer>
                <template v-for="(user) in pedidosMapa?.users">
                  <l-marker v-for="(pedido,i) in user?.pedidos?.pedidos" :key="i" :lat-lng="[parseFloat(pedido.pedido.cliente.Latitud), parseFloat(pedido.pedido.cliente.longitud)]">
                    <l-tooltip :content="pedido.pedido.cliente.Nombres">
                    </l-tooltip>
                    <l-icon >
                      <q-badge style="padding: 2px" :color="pedido.pedido.estado === 'CREADO' ? 'green' : 'blue'">
                        {{pedido.pedido.idCli}}
                      </q-badge>
                    </l-icon>
                  </l-marker>
                </template>
              </l-map>
<!--              <template v-for="(user,i) in pedidosMapa?.users" :key="i">-->
<!--                <div v-for="(pedido,j) in user?.pedidos?.pedidos" :key="j">-->
<!--                  <q-badge style="padding: 2px" :color="pedido.pedido.estado === 'CREADO' ? 'green' : 'blue'">-->
<!--                    {{i++}} {{pedido.pedido.cliente.Latitud}}  {{pedido.pedido.cliente.longitud}}-->
<!--                  </q-badge>-->
<!--                </div>-->
<!--              </template>-->
            </div>
          </div>
<!--          <div class="col-12 col-md-12 ">-->
<!--            <pre>{{pedidosMapa}}</pre>-->
<!--          </div>-->
        </div>
      </q-card-section>
    </q-card>
  </q-page>
</template>
<script>
import moment from "moment";
import {LIcon, LMap, LMarker, LTileLayer, LTooltip} from "@vue-leaflet/vue-leaflet";

export default {
  name: "MapaVendedor",
  components: {
    LMap,
    LIcon,
    LTileLayer,
    LMarker,
    LTooltip
  },
  data() {
    return {
      center:[-17.969721, -67.114493],
      zoom: 13,
      fecha: moment().format("YYYY-MM-DD"),
      loading: false,
      pedidos: [],
      pedidosAll: [],
      pedidosMapa: [],
      tipos: [
        { label: "TODOS", value: "" },
        { label: "CERDO", value: "CERDO" },
        { label: "NORMAL", value: "NORMAL" },
        { label: "POLLO", value: "POLLO" },
        { label: "RES", value: "RES" },
      ],
      tipo: "",
    };
  },
  mounted() {
    this.buscar();
  },
  methods: {
    seleccionar(user) {
      this.pedidosMapa.users = this.pedidosAll.users.filter((u) => u.CodAut === user.CodAut)
    },
    buscar() {
      this.loading = true;
      this.$api.post("mapaVendedor", {
        fecha: this.fecha,
        tipo: this.tipo,
      }).then((res) => {
        this.pedidos = res.data;
        this.pedidosMapa = {...res.data};
        this.pedidosAll = {...res.data};
      }).finally(() => {
        this.loading = false;
      });
    },
  },
  computed: {
    total() {
      let sum = 0;
      for (let i = 0; i < this.pedidos?.users?.length; i++) {
        sum += this.pedidos.users[i].pedidos.cantidad;
      }
      return sum;
    },
  },
};
</script>
