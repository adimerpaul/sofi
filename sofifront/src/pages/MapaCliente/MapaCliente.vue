<template>
  <q-page class="bg-grey-3 q-pa-xs">
    <q-card flat bordered>
      <q-card-section>
        <div class="row">
          <div class="col-xs-12 col-md-6">
          <div class="col-xs-12 col-md-10">
            <q-input v-model="fecha" label="Fecha" type="date" dense outlined v-bind:min="minimo " @update:model-value="buscar"/>
          </div><br>

          <div class="col-xs-12 col-md-4">
            <q-table dense  :rows="clientes" :columns="column" row-key="name" :rows-per-page-options="['0']" :filter="filtro" style="font-size:10px">
              <template v-slot:top-right>
                <q-input outlined dense debounce="300" v-model="filtro" placeholder="buscar">
                  <template v-slot:append>
                    <q-icon name="search" />
                  </template>
                </q-input>
              </template>
              <template v-slot:body="props">
                <tr
                  :class=" props.row.selected?'bg-red':'bg-'+props.row.color"
                  style=" font-size:10px "
                  @click="toggleSeleccion(props.row)"
                >
<!--                  {{props.row.color}}-->
                  <td v-for="col in column" :key="col.name"
                      style="font-size:10px">
                    {{ props.row[col.field] }}
                  </td>
                </tr>
              </template>
              </q-table>
              <!--
            <q-markup-table flat bordered dense wrap-cells class="bg-primary text-white cursor-pointer" >
              <thead>
                <tr>
                  <th>#</th>
                  <th>Cinit</th>
                  <th>Cliente</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(user, index) in clientes" :key="index" @click="toggleSeleccion(user)" :class="user.selected?'bg-red':'bg-'+user.color">
                  <td>{{ user.num }}</td>
                  <td>{{ user.Id }} </td>
                  <td>
                    <div style="text-transform: lowercase; line-height: 0.9;">
                      {{ user.Nombres }}
                    </div>
                  </td>
                </tr>
              </tbody>
            </q-markup-table>-->
          </div>
        </div>
        <div class="col-xs-12 col-md-6">
            <div style="height: 500px; width: 100%;">
              <l-map
                v-model="zoom"
                :zoom="zoom"
                :center="center"
              >
                <LTileLayer
                  url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                ></LTileLayer>
                <l-marker
                  v-for="(pedido, i) in clientes"
                  :key="i"
                  :lat-lng="[parseFloat(pedido.Latitud), parseFloat(pedido.longitud)]"
                  @click="toggleSeleccion(pedido)"
                >
                  <l-tooltip :content="pedido.Nombres"></l-tooltip>
                  <l-icon>
                    <q-badge
                      style="padding: 2px"
                      :color="pedido.selected ? 'red' : pedido.color"
                    >
                      {{ pedido.num }}
                    </q-badge>
                  </l-icon>
                </l-marker>
              </l-map>
<!--              <pre>{{ clientes }}</pre>-->
            </div>
            <br>
            <div class="col-12 col-md-4">
              <div class="row">
                <div class="col-12 col-md-6">
                  <q-select
                    dense
                    outlined
                    v-model="auto"
                    :options="vehiculos"
                    option-label="placa"
                    label="Camion Asignar"
                  />
                </div>
                <div class="col-12 col-md-6">
                  <q-btn color="green" icon="local_shipping" @click="cambiar" no-caps label="Asignar" :loading="loading" />
                  <q-btn color="info" icon="print" @click="generarPdf" dense />
                </div>
              </div>
            </div>
        </div>
        </div>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script>
import moment from "moment";
import { LIcon, LMap, LMarker, LTileLayer,LTooltip } from "@vue-leaflet/vue-leaflet";

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
      center: [-17.969721, -67.114493],
      filtro:'',
      listado: [],
      vehiculos: [],
      auto:{},
      zoom: 13,
      fecha: moment().format("YYYY-MM-DD"),
      minimo: moment().subtract(1, 'days').format("YYYY-MM-DD"),
      loading: false,
      clientes: [],
      seleccionados: [], // Lista de clientes seleccionados
      column:[
        {label:'OP',name:'op',field:'op',sortable:true},
        {label:'N',name:'id',field:'num',sortable:true, align:'center'},
        {label:'CINIT',name:'id',field:'Id',sortable:true, align:'center'},
        {label:'CLIENTE',name:'nombre',field:'Nombres',sortable:true, align:'left'},
        {label:'VENDEDOR',name:'vendedor',field:'vendedor',sortable:true},
        {label:'ZONA',name:'zona',field:'territorio',sortable:true},
        {label:'PLACA',name:'placa',field:'placa',sortable:true},
      ]
    };
  },
  mounted() {
    this.buscar();
    this.getVehiculo();
  },
  methods: {
    generarPdf(){
    // :href="`${url}reportePedido/${fecha1}`" target="_blank"
      const url = `${this.url}reportePedido/${this.fecha}`
      window.open(url, '_blank')
    },
    getVehiculo() {
      this.$api.post("listVehiculo").then((res) => {
        this.vehiculos = res.data;
        this.auto=this.vehiculos[0]
      });
    },
    buscar() {
      this.loading = true;
      this.clientes = [];
      this.$api.post("mapClientes", { fecha: this.fecha }).then((res) => {
        console.log(res.data)
        let numero=1
        res.data.forEach(r => {
          r.num=numero
          numero++
        });
        this.clientes = res.data.map((cliente) => ({
          ...cliente,
          selected: false
        }));
      }).finally(() => {
        this.loading = false;
      });
    },
    toggleSeleccion(cliente) {
      // console.log(cliente)
      // Cambiar el estado seleccionado
      this.center=[cliente.Latitud,cliente.longitud]
      this.zoom=15
      cliente.selected = !cliente.selected;

      if (cliente.selected) {
        // Agregar a la lista de seleccionados
        this.seleccionados.push(cliente);
      } else {
        // Remover de la lista de seleccionados
        this.seleccionados = this.seleccionados.filter((item) => item.Id !== cliente.Id);
      }
    },
    cambiar(){
      this.loading = true;
      this.$api.post("updaVehiPed", { fecha: this.fecha,placa:this.auto.placa,listado:this.seleccionados }).then((res) => {
        this.seleccionados=[]
        this.buscar()
      })

    }
  }
};
</script>
