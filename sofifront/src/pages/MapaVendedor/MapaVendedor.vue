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
                    <td>
<!--                      v-if ver si es mayor que sero los enviados-->
                        <q-btn
                          class="q-mr-sm"
                          color="orange"
                          size="xs"
                          icon="send"
                          label="Enviar emegencia"
                          :loading="loading"
                          dense
                          v-if="user.pedidos.creados > 0"
                          no-caps
                          @click.stop="enviarPedidosEmergencia(user)"
                        />
                      <div style="max-width: 80px; wrap-option: wrap">
                        {{ $filters.capitalize(user.nombreCompleto) }}
                      </div>
                    </td>
                    <td>
<!--                      btn enaviar pedidos-->
                      <q-linear-progress size="20px" :value="1" color="accent">
                        <div class="absolute-full flex flex-center">
                          <q-badge color="white" text-color="black" :label="'Total '+user.pedidos.cantidad" />
                        </div>
                      </q-linear-progress>
                      <q-linear-progress size="20px" :value="user.pedidos.creados / user.pedidos.cantidad" color="blue">
                        <div class="absolute-full flex flex-center">
                          <q-badge color="white" text-color="black" :label="'Creados '+user.pedidos.creados" />
                        </div>
                      </q-linear-progress>
                      <q-linear-progress size="20px" :value="user.pedidos.enviados / user.pedidos.cantidad" color="green">
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
                  <l-marker
                    v-for="(pedido, i) in user?.pedidos?.pedidos.filter(p => p.pedido?.cliente?.Latitud && p.pedido?.cliente?.longitud)"
                    :key="i"
                    :lat-lng="[parseFloat(pedido.pedido.cliente.Latitud), parseFloat(pedido.pedido.cliente.longitud)]"
                  >
                    <l-tooltip :content="pedido.pedido.cliente.Nombres" />
                    <l-icon>
                      <q-badge style="padding: 2px" :color="pedido.pedido.estado === 'CREADO' ? 'blue' : 'green'">
                        {{ pedido.pedido.idCli }}
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
          <div class="col-12 q-mt-md">
            <q-card flat bordered>
              <q-card-section>
                <div class="text-bold">Clientes con coordenadas faltantes</div>
                <q-markup-table flat dense bordered v-if="pedidosInvalidos.length > 0">
                  <thead>
                  <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    <th>ID Cliente</th>
                    <th>Vendedor</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr v-for="(pedido, index) in pedidosInvalidos" :key="index">
                    <td>{{ index + 1 }}</td>
                    <td>
                      {{ pedido.NroPed }}
                      {{ pedido.nombre }}
<!--                      <pre>{{pedido}}</pre>-->
                    </td>
                    <td>{{ pedido.idCli }}</td>
                    <td>{{ pedido.vendedor }}</td>
                  </tr>
                  </tbody>
                </q-markup-table>
                <div v-else class="text-positive">
                  Todos los pedidos tienen coordenadas.
                </div>
              </q-card-section>
            </q-card>
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
        { label: "EMBUTIDO", value: "NORMAL" },
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
    enviarPedidosEmergencia(user) {
      this.loading = true;
      this.$api.post("enviarPedidosEmergencia", {
        user: user.CodAut,
        fecha: this.fecha,
      }).then((res) => {
        const pedidosCondeuda = res.data.pedidosCondeuda;
        let deudores='';
        for (let i = 0; i < pedidosCondeuda.length; i++) {
          deudores += `<div> DEUDA ${i + 1}.- ${pedidosCondeuda[i].Nombres} <br> ${pedidosCondeuda[i].Telf} <br> ${pedidosCondeuda[i].Direccion}</div>`;
        }
        this.$q.notify({
          type: "positive",
          message: 'Se enviaron los pedidos de emergencia <br> <div style="max-height: 200px; overflow-y: auto;">' + deudores + '</div>',
          position: "top",
          html: true,
        });
        this.buscar();
      }).catch((err) => {
        this.$q.notify({
          type: "negative",
          message: err.response.data.message,
        });
      }).finally(() => {
        this.loading = false;
      });
    },
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
    pedidosInvalidos() {
      const invalidos = [];
      for (const user of this.pedidosMapa?.users || []) {
        for (const pedidoObj of user?.pedidos?.pedidos || []) {
          const cliente = pedidoObj?.pedido?.cliente;
          if (!cliente || !cliente.Latitud || !cliente.longitud) {
            // console.error(`Pedido sin coordenadas: ${pedidoObj.pedido.idCli}`, pedidoObj);
            invalidos.push({
              NroPed: pedidoObj.pedido.NroPed,
              nombre: cliente?.Nombres || 'SIN NOMBRE',
              idCli: pedidoObj.pedido.idCli,
              vendedor: user.nombreCompleto,
            });
          }
        }
      }
      return invalidos;
    },
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
