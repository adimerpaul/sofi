<template>
  <q-page class="bg-grey-3 q-pa-xs">
    <q-card flat bordered>
      <q-card-section>
        <div class="row">
          <div class="col-12 col-md-3">
            <q-input v-model="fecha" label="Fecha" type="date" dense outlined />
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
                  <tr v-for="(user, index) in pedidos.users" :key="index">
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
<!--                <l-marker v-for="(c,i) in pedidos?.users" :key="i" :lat-lng="[c.cliente?.Latitud, c.cliente?.longitud]">-->
<!--                  <l-tooltip :content="c.Nombres"></l-tooltip>-->
<!--                  <l-icon >-->
<!--                    <q-badge style="padding: 2px" >{{c.idCli}}-->
<!--                    </q-badge>-->
<!--                  </l-icon>-->
<!--                </l-marker>-->
                <template v-for="(user) in pedidos?.users">
                  <l-marker v-for="(pedido,i) in user?.pedidos?.pedidos" :key="i" :lat-lng="[pedido.pedido.cliente.Latitud, pedido.pedido.cliente.longitud]">
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
            </div>
          </div>
          <div class="col-12 col-md-12 ">
            <pre>{{pedidos}}</pre>
<!--            {-->
<!--            "users": [-->
<!--            {-->
<!--            "CodAut": "28",-->
<!--            "nombreCompleto": "JUAN RIOS",-->
<!--            "pedidos": {-->
<!--            "cantidad": 3,-->
<!--            "enviados": 0,-->
<!--            "creados": 3,-->
<!--            "pedidos": [-->
<!--            {-->
<!--            "pedido": {-->
<!--            "NroPed": 110566,-->
<!--            "fecha": "2025-01-08 11:49:45",-->
<!--            "idCli": "1709",-->
<!--            "CIfunc": "28",-->
<!--            "estado": "CREADO",-->
<!--            "fact": "NO",-->
<!--            "comentario": null,-->
<!--            "pago": "CREDITO",-->
<!--            "cliente": {-->
<!--            "Cod_Aut": 1709,-->
<!--            "Nombres": "PAMELA NICKOL MIRANDA CANAZA",-->
<!--            "Direccion": "BRASIL Y BOLIVAR PLENA ESQ.",-->
<!--            "Telf": "76365165",-->
<!--            "zona": "CENTRAL",-->
<!--            "Latitud": "-17.9723975",-->
<!--            "longitud": "-67.1069087"-->
<!--            },-->
<!--            "user": {-->
<!--            "CodAut": 28,-->
<!--            "Nombre1": "JUAN",-->
<!--            "App1": "RIOS",-->
<!--            "ci": "7360035"-->
<!--            }-->
<!--            },-->
<!--            "productos": [-->
<!--            {-->
<!--            "NroPed": 110566,-->
<!--            "cod_prod": "533329",-->
<!--            "precio": "46.80",-->
<!--            "Cant": "6.00",-->
<!--            "Canttxt": "",-->
<!--            "subtotal": "280.80",-->
<!--            "producto": {-->
<!--            "cod_prod": "533329",-->
<!--            "Producto": "CHORIZO CHURRASQUERO AL VACIO DE 1 KG                                                               "-->
<!--            }-->
<!--            }-->
<!--            ]-->
<!--            },-->
<!--            {-->
<!--            "pedido": {-->
<!--            "NroPed": 110728,-->
<!--            "fecha": "2025-01-08 11:32:08",-->
<!--            "idCli": "1845",-->
<!--            "CIfunc": "28",-->
<!--            "estado": "CREADO",-->
<!--            "fact": "NO",-->
<!--            "comentario": null,-->
<!--            "pago": "CONTADO",-->
<!--            "cliente": {-->
<!--            "Cod_Aut": 1845,-->
<!--            "Nombres": "JENNY XIMENA CHURA NINA",-->
<!--            "Direccion": "MERCADO  CAMPERO",-->
<!--            "Telf": "73846886",-->
<!--            "zona": "CENTRO",-->
<!--            "Latitud": "-17.9707683",-->
<!--            "longitud": "-67.1102802"-->
<!--            },-->
<!--            "user": {-->
<!--            "CodAut": 28,-->
<!--            "Nombre1": "JUAN",-->
<!--            "App1": "RIOS",-->
<!--            "ci": "7360035"-->
<!--            }-->
<!--            },-->
<!--            "productos": {-->
<!--            "2": {-->
<!--            "NroPed": 110728,-->
<!--            "cod_prod": "533329",-->
<!--            "precio": "46.80",-->
<!--            "Cant": "5.00",-->
<!--            "Canttxt": "",-->
<!--            "subtotal": "234.00",-->
<!--            "producto": {-->
<!--            "cod_prod": "533329",-->
<!--            "Producto": "CHORIZO CHURRASQUERO AL VACIO DE 1 KG                                                               "-->
<!--            }-->
<!--            }-->
<!--            }-->
<!--            },-->
<!--            {-->
<!--            "pedido": {-->
<!--            "NroPed": 110750,-->
<!--            "fecha": "2025-01-08 14:20:12",-->
<!--            "idCli": "318",-->
<!--            "CIfunc": "28",-->
<!--            "estado": "CREADO",-->
<!--            "fact": "NO",-->
<!--            "comentario": null,-->
<!--            "pago": "CONTADO",-->
<!--            "cliente": {-->
<!--            "Cod_Aut": 318,-->
<!--            "Nombres": "LUZ MARIA ROCHA GONZALEZ",-->
<!--            "Direccion": "INTERIOR MERCADO CAMPERO",-->
<!--            "Telf": "69295987",-->
<!--            "zona": "CENTRO",-->
<!--            "Latitud": "-17.9706412",-->
<!--            "longitud": "-67.1101146"-->
<!--            },-->
<!--            "user": {-->
<!--            "CodAut": 28,-->
<!--            "Nombre1": "JUAN",-->
<!--            "App1": "RIOS",-->
<!--            "ci": "7360035"-->
<!--            }-->
<!--            },-->
<!--            "productos": {-->
<!--            "6": {-->
<!--            "NroPed": 110750,-->
<!--            "cod_prod": "533329",-->
<!--            "precio": "46.80",-->
<!--            "Cant": "4.00",-->
<!--            "Canttxt": "",-->
<!--            "subtotal": "187.20",-->
<!--            "producto": {-->
<!--            "cod_prod": "533329",-->
<!--            "Producto": "CHORIZO CHURRASQUERO AL VACIO DE 1 KG                                                               "-->
<!--            }-->
<!--            }-->
<!--            }-->
<!--            }-->
<!--            ]-->
<!--            }-->
<!--            },-->
<!--            {-->
<!--            "CodAut": "43",-->
<!--            "nombreCompleto": "DANIELA LOPEZ",-->
<!--            "pedidos": {-->
<!--            "cantidad": 1,-->
<!--            "enviados": 0,-->
<!--            "creados": 1,-->
<!--            "pedidos": [-->
<!--            {-->
<!--            "pedido": {-->
<!--            "NroPed": 110648,-->
<!--            "fecha": "2025-01-08 17:29:17",-->
<!--            "idCli": "3428",-->
<!--            "CIfunc": "43",-->
<!--            "estado": "CREADO",-->
<!--            "fact": "NO",-->
<!--            "comentario": null,-->
<!--            "pago": "CONTADO",-->
<!--            "cliente": {-->
<!--            "Cod_Aut": 3428,-->
<!--            "Nombres": "CARMEN GABRIELA MAMANI SANTOS",-->
<!--            "Direccion": "Adolfo Mier entre Tejerina y Brasil acera sud",-->
<!--            "Telf": "77151223",-->
<!--            "zona": "CENTRO",-->
<!--            "Latitud": "-17.97174",-->
<!--            "longitud": "-67.10590"-->
<!--            },-->
<!--            "user": {-->
<!--            "CodAut": 43,-->
<!--            "Nombre1": "DANIELA",-->
<!--            "App1": "LOPEZ",-->
<!--            "ci": "5773491"-->
<!--            }-->
<!--            },-->
<!--            "productos": {-->
<!--            "1": {-->
<!--            "NroPed": 110648,-->
<!--            "cod_prod": "650303",-->
<!--            "precio": "279.10",-->
<!--            "Cant": "5.00",-->
<!--            "Canttxt": "P2",-->
<!--            "subtotal": "1395.50",-->
<!--            "producto": {-->
<!--            "cod_prod": "650303",-->
<!--            "Producto": "P.U. 21 KG MICAN CACHORRO CANINO                                                                    "-->
<!--            }-->
<!--            }-->
<!--            }-->
<!--            }-->
<!--            ]-->
<!--            }-->
<!--            },-->

          </div>
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
    };
  },
  mounted() {
    this.buscar();
  },
  methods: {
    buscar() {
      this.loading = true;
      this.$api.post("mapaVendedor", { fecha: this.fecha }).then((res) => {
        this.pedidos = res.data;
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
