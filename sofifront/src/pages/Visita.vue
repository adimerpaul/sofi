<template>
  <div style="height: 350px; width: 100%;">
    <l-map
      @ready="onReady"
      @locationfound="onLocationFound"
      v-model="zoom"
      v-model:zoom="zoom"
      :center="center"
      @move="log('move')"

    >
<!--      @click="ubicacion"-->
      <l-tile-layer
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      ></l-tile-layer>
<!--      <l-control-layers />-->
<!--      <l-marker :lat-lng="[-17.970491, -67.113511]" draggable @moveend="log('moveend')">-->
<!--        <l-tooltip>-->
<!--          lol-->
<!--        </l-tooltip>-->
<!--      </l-marker>-->

      <l-marker @click="clickpedido(c)" v-for="c in clientes" :key="c.Cod_Aut" :lat-lng="[c.Latitud, c.longitud]"  >
        <l-icon><q-badge color="info">{{c.Cod_Aut}}</q-badge></l-icon>
      </l-marker>
      <l-marker :lat-lng="center"  >
<!--        <l-icon><q-badge color="info">{{c.Cod_Aut}}</q-badge></l-icon>-->
      </l-marker>

<!--      <l-marker :lat-lng="[50, 50]" draggable @moveend="log('moveend')">-->
<!--        <l-popup>-->
<!--          lol-->
<!--        </l-popup>-->
<!--      </l-marker>-->

<!--      <l-polyline-->
<!--        :lat-lngs="[-->
<!--          [47.334852, -1.509485],-->
<!--          [47.342596, -1.328731],-->
<!--          [47.241487, -1.190568],-->
<!--          [47.234787, -1.358337],-->
<!--        ]"-->
<!--        color="green"-->
<!--      ></l-polyline>-->
<!--      <l-polygon-->
<!--        :lat-lngs="[-->
<!--          [46.334852, -1.509485],-->
<!--          [46.342596, -1.328731],-->
<!--          [46.241487, -1.190568],-->
<!--          [46.234787, -1.358337],-->
<!--        ]"-->
<!--        color="#41b782"-->
<!--        :fill="true"-->
<!--        :fillOpacity="0.5"-->
<!--        fillColor="#41b782"-->
<!--      />-->
<!--      <l-rectangle-->
<!--        :lat-lngs="[-->
<!--          [46.334852, -1.509485],-->
<!--          [46.342596, -1.328731],-->
<!--          [46.241487, -1.190568],-->
<!--          [46.234787, -1.358337],-->
<!--        ]"-->
<!--        :fill="true"-->
<!--        color="#35495d"-->
<!--      />-->
<!--      <l-rectangle-->
<!--        :bounds="[-->
<!--          [46.334852, -1.190568],-->
<!--          [46.241487, -1.090357],-->
<!--        ]"-->
<!--      >-->
<!--        <l-popup>-->
<!--          lol-->
<!--        </l-popup>-->
<!--      </l-rectangle>-->
    </l-map>
<!--    <button @click="changeIcon">New kitten icon</button>-->
    <div class="row">
      <div class="col-12">
        <q-table :rows="clientes" hide-header :filter="filter" :columns="columns">
          <template v-slot:body-cell-Cod_Aut="props">
            <q-td :props="props">
                <q-badge color="info"> {{ props.row.Cod_Aut}}</q-badge>
            </q-td>
          </template>
          <template v-slot:body-cell-Nombres="props">
            <q-td :props="props">
              <div class="text-weight-medium">{{ props.row.Nombres}}</div>
              <div class="text-caption" style="font-size: 8px">{{ props.row.Direccion}}</div>
            </q-td>
          </template>
          <template v-slot:top-right>
            <div class="row">
              <div class="col-4 flex flex-center" @click="getUserPosition" >
                <q-btn icon="my_location" size="xs" color="primary" />
              </div>
              <div class="col-8">
                <q-input filled dense v-model="filter" placeholder="Buscar Cliente">
                  <template v-slot:append>
                    <q-icon name="search" />
                  </template>
                </q-input>
              </div>
            </div>
          </template>
<!--          <template v-slot:top>-->
<!--            a-->
<!--          </template>-->
        </q-table>
<!--        {{center}}-->
      </div>
    </div>
    <q-dialog full-width v-model="modalpedido">
      <q-card >
        <q-card-section>
          <div class="text-h6">Medium</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
<!--          <pre>{{cliente}}</pre>-->
          <q-btn label="generar ruta" icon="maps" type="a" target="_blank" :href="'https://www.google.com.bo/maps/place/'+cliente.Latitud+','+cliente.longitud+'/@'+cliente.Latitud+','+cliente.longitud+',17z/data=!3m1!4b1!4m6!3m5!1s0x0:0xeda9371aeb8c1574!7e2!8m2!3d-17.981432!4d-67.1061122?hl=es'"/>
        </q-card-section>
        <q-card-actions align="right" class="bg-white text-teal">
          <q-btn flat label="OK" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>
<script>
import {
  LMap,
  LIcon,
  LTileLayer,
  LMarker,
  LControlLayers,
  LTooltip,
  LPopup,
  LPolyline,
  LPolygon,
  LRectangle,
} from "@vue-leaflet/vue-leaflet";
import "leaflet/dist/leaflet.css";
export default {
  components: {

    LMap,
    LIcon,
    LTileLayer,
    LMarker,
    // LControlLayers,
    // LTooltip,
    // LPopup,
    // LPolyline,
    // LPolygon,
    // LRectangle,
  },
  data() {
    return {
      modalpedido:false,
      center:[-17.970371, -67.112303],
      filter:'',
      zoom: 16,
      iconWidth: 25,
      iconHeight: 40,
      clientes:[],
      cliente:{},
      userLocation:{},
      columns:[
        {label:'Cod_Aut',name:'Cod_Aut',field:'Cod_Aut'},
        {label:'Nombres',name:'Nombres',field:'Nombres',align:'left'},
        {label:'opcion',name:'opcion',field:'opcion'},
      ]
    };
  },
  computed: {
    // iconUrl() {
    //   return `https://placekitten.com/25/40`;
    // },
    // iconSize() {
    //   return [this.iconWidth, this.iconHeight];
    // },
  },
  created() {
    this.$q.loading.show()
    this.$api.get('cliente').then(res=>{
      // console.log(res.data)
      this.clientes=[]
      // this.clientes=res.data
      res.data.forEach(r=>{
        let d=r
        // if (r.Latitud)
        // console.log(r.Latitud)
        if (parseFloat(r.Latitud)!=NaN && parseFloat(r.longitud)!=NaN && r.Latitud!='' && r.longitud!='' ){
          // console.log( 'id='+r.Cod_Aut+'  '+(r.Latitud!='' && r.longitud!='' )+' R='+parseFloat(r.Latitud)+'---'+parseFloat(r.longitud))
          d.Latitud=parseFloat(r.Latitud)
          d.longitud=parseFloat(r.longitud)
        }else{
          // console.log( (r.Latitud!='' && r.longitud!='' )+' R='+r.Latitud+'---'+r.longitud)
          d.Latitud=0
          d.longitud=0
        }

        this.clientes.push(d)
      })
      this.$q.loading.hide()
    })
  },
  methods: {
    clickpedido(cliente){
      this.modalpedido=true
      this.cliente=cliente
    },
    ubicacion(e){
      // console.log(e.latlng)
      if (e.latlng!=undefined)
        this.center=[e.latlng.lat,e.latlng.lng]
    },
    async getUserPosition() {
      this.center = [0,0]
      // check if API is supported
      if (navigator.geolocation) {
        // get  geolocation
        navigator.geolocation.getCurrentPosition(pos => {
          // set user location
          this.center = [
            pos.coords.latitude,
            pos.coords.longitude
          ]
        });
      }
    },
    onReady (mapObject) {
      mapObject.locate();
    },
    onLocationFound(location){
      // console.log(location)
      this.center=[location.latlng.lat,location.latlng.lng]
    },
    log(a) {
      console.log(a);
    },
    changeIcon() {
      this.iconWidth += 2;
      if (this.iconWidth > this.iconHeight) {
        this.iconWidth = Math.floor(this.iconHeight / 2);
      }
    },
  },
};
</script>
