<template>
  <q-page class="bg-grey-3 q-pa-xs">
    <q-card flat bordered>
      <q-card-section>
        <div class="row">
          <div class="col-xs-12 col-md-10">
            <q-input v-model="fecha" label="Fecha" type="date" dense outlined v-bind:min="minimo " @update:model-value="buscar"/>
          </div><br>
          <div class="col-xs-12 col-md-12">
            <div style="height: 500px; width: 100%;">
              <l-map
                v-model="zoom"
                :zoom="zoom"
                :center="center"
              >
                <LTileLayer
                  url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                ></LTileLayer>
                <LGeoJson :geojson="geojsonData" :options="geoJsonOptions"   />
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
          <div class="col-xs-12 col-md-12">
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

      </q-card-section>
    </q-card>
  </q-page>
</template>

<script>
import moment from "moment";
import { LIcon, LMap, LMarker, LTileLayer,LTooltip,LGeoJson } from "@vue-leaflet/vue-leaflet";


export default {
  name: "MapaVendedor",
  components: {
    LMap,
    LIcon,
    LTileLayer,
    LMarker,
    LTooltip,
    LGeoJson
  },
  data() {

    return {
      styleGeoJSON: (feature) => ({
      color: feature.properties.color || 'red', // Usa el color de las propiedades o transparente
      weight: 2,
      opacity: 0.2,
      fillOpacity: 0.3, // Ajusta la opacidad del relleno
    }),
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
      ],
      geojsonData : {
    type: "FeatureCollection",
    features: [
      {
        type: "Feature",
        properties: {
          name: "NORTE",
          color: "#ff0000" // Rojo semitransparente
        },
        geometry: {
          type: "Polygon",
          coordinates: [[
            [-67.099879, -17.95474], [-67.100311, -17.944827], [-67.085978, -17.93315],
            [-67.071816, -17.93217], [-67.075506, -17.919267], [-67.113353, -17.911143],
            [-67.118482, -17.893828], [-67.134844, -17.895579], [-67.130279, -17.92691],
            [-67.121165, -17.94142], [-67.120854, -17.948468], [-67.122543, -17.949004],
            [-67.12654, -17.94755], [-67.127607, -17.956572], [-67.125864, -17.957169],
            [-67.122549, -17.957674], [-67.117989, -17.956378], [-67.115403, -17.956363],
            [-67.114395, -17.956552], [-67.109594, -17.957144], [-67.107067, -17.957802],
            [-67.10601, -17.95376], [-67.104272, -17.954179], [-67.103274, -17.954444],
            [-67.101654, -17.954852], [-67.100496, -17.955036], [-67.099879, -17.95474]
          ]]
        }
      },
      {
        type: "Feature",
        properties: {
          name: "SUD",
          color: "green" // Verde semitransparente
        },
        geometry: {
          type: "Polygon",
          coordinates: [[
            [-67.092556, -17.963821], [-67.093142, -17.971621], [-67.098464, -17.970519],
            [-67.100556, -17.977601], [-67.108699, -17.975938], [-67.108957, -17.976918],
            [-67.111124, -17.976509], [-67.110909, -17.975468], [-67.115952, -17.974713],
            [-67.116317, -17.975489], [-67.116617, -17.976754], [-67.123312, -17.978958],
            [-67.127775, -17.976999], [-67.133354, -17.972346], [-67.138847, -17.965569],
            [-67.144598, -17.96663], [-67.142624, -17.973529], [-67.14477, -17.976958],
            [-67.145242, -17.981693], [-67.141036, -17.983979], [-67.137045, -17.985898],
            [-67.138847, -17.99349], [-67.136358, -18.006061], [-67.10606, -18.001],
            [-67.106575, -17.98998], [-67.096447, -17.983285], [-67.091297, -17.993082],
            [-67.070526, -18.000428], [-67.049898, -17.981904], [-67.047409, -17.97223],
            [-67.07303, -17.964433], [-67.074703, -17.973006], [-67.075905, -17.974639],
            [-67.081012, -17.966515], [-67.092556, -17.963821]
          ]]
        }
      },
      {
        type: "Feature",
        properties: {
          name: "BOLIVAR",
          color: "blue" // Azul semitransparente
        },
        geometry: {
          type: "Polygon",
          coordinates: [[
            [-67.098232, -17.969964], [-67.09815, -17.969718], [-67.097392, -17.967165],
            [-67.105855, -17.965936], [-67.106005, -17.965896], [-67.106906, -17.96859],
            [-67.108699, -17.975938], [-67.100598, -17.977326], [-67.098464, -17.970519],
            [-67.093142, -17.971621], [-67.095195, -17.970559], [-67.098171, -17.970048],
            [-67.098232, -17.969964]
          ]]
        }
      },
      {
        type: "Feature",
        properties: {
          name: "CENTRO",
          color: "orange" // Naranja semitransparente
        },
        geometry: {
          type: "Polygon",
          coordinates: [[
            [-67.107257, -17.964448], [-67.112021, -17.964469], [-67.112665, -17.965122],
            [-67.112922, -17.965897], [-67.118587, -17.963938], [-67.120196, -17.964836],
            [-67.120432, -17.967877], [-67.122557, -17.967938], [-67.122771, -17.971939],
            [-67.119316, -17.972449], [-67.11687, -17.974286], [-67.111763, -17.975041],
            [-67.110948, -17.975164], [-67.109875, -17.971408], [-67.10891, -17.971551],
            [-67.107107, -17.964489], [-67.107257, -17.964448]
          ]]
        }
      },
      {
        type: "Feature",
        properties: {
          name: "APOYO",
          color: "purple" // Morado semitransparente
        },
        geometry: {
          type: "Polygon",
          coordinates: [[
            [-67.107067, -17.957679], [-67.10971, -17.957368], [-67.112739, -17.956852],
            [-67.113297, -17.964405], [-67.107031, -17.963751], [-67.106859, -17.965548],
            [-67.097392, -17.967042], [-67.098232, -17.969964], [-67.093142, -17.971621],
            [-67.092556, -17.963821], [-67.081012, -17.966392], [-67.089865, -17.947666],
            [-67.083771, -17.944808], [-67.089092, -17.936153], [-67.100311, -17.944704],
            [-67.099948, -17.954411], [-67.09995, -17.955219], [-67.105829, -17.953913],
            [-67.107067, -17.957679]
          ]]
        }
      }]
    },
    enableTooltip: true,
    options:{
      onEachFeature: this.onEachFeatureFunction
    },
    geoJsonOptions: {
        style: (feature) => ({
          color: feature.properties.color, // Color según la propiedad del GeoJSON
          weight: 0.5,
          opacity: 0.1,
          fillOpacity: 0.2,
        }),
      }
  }
  },
  mounted() {
    this.buscar();
    this.getVehiculo();
  },
  methods: {
    onEachFeatureFunction() {
      if (!this.enableTooltip) {
        return () => {};
      }
      return (feature, layer) => {
        layer.bindTooltip(
          "<div>" +
            feature.properties.name +
            "</div>",
          { permanent: false, sticky: true }
        );
      };
    
  },
    // Función que se ejecuta cuando el mouse pasa sobre un polígono
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
