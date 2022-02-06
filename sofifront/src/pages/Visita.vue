<template>
  <div style="height: 350px; width: 100%;">
    <l-map
      @ready="onReady"
      @locationfound="onLocationFound"
      v-model="zoom"
      :zoom="zoom"
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

      <l-marker @click="clickopciones(c)" v-for="c in clientes" :key="c.Cod_Aut" :lat-lng="[c.Latitud, c.longitud]"  >
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
              <div class="col-2 flex flex-center" @click="getUserPosition" >
                <q-btn icon="my_location" size="xs" color="primary" />
              </div>
              <div class="col-2 flex flex-center" @click="getCentro" >
                <q-btn icon="my_location" size="xs" color="secondary" />
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
    <q-dialog full-width v-model="modalopciones">
      <q-card >
        <q-card-section>
          <div class="text-subtitle2">{{cliente.Cod_Aut}} {{cliente.Nombres}}</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
<!--          <pre>{{cliente}}</pre>-->
          <q-btn class="q-ma-xs" label="generar ruta" color="primary" size="xs" style="width: 100%;" icon="maps" type="a" target="_blank" :href="'https://www.google.com.bo/maps/place/'+cliente.Latitud+','+cliente.longitud+'/@'+cliente.Latitud+','+cliente.longitud+',17z/data=!3m1!4b1!4m6!3m5!1s0x0:0xeda9371aeb8c1574!7e2!8m2!3d-17.981432!4d-67.1061122?hl=es'"/>
          <q-btn class="q-ma-xs" @click="clickpedido" label="realizar pedido" color="positive" size="xs" style="width: 100%;" icon="shopping_cart" />
          <q-btn class="q-ma-xs" label="volver 20 min" color="warning" size="xs" style="width: 100%;" icon="schedule" />
          <q-btn class="q-ma-xs" label="no pedido" color="teal" size="xs" style="width: 100%;" icon="highlight_off" />
        </q-card-section>
        <q-card-actions align="right" class="bg-white text-teal">
          <q-btn flat label="OK" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
    <q-dialog full-width v-model="modalpedido">
      <q-card >
        <q-card-section>
          <div class="text-subtitle2">{{cliente.Cod_Aut}} {{cliente.Nombres}}</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <div class="row">
            <div class="col-10">
              <q-select label="Productos" dense outlined class="q-ma-xs" use-input input-debounce="0"  @filter="filterFn" :options="productos" v-model="producto">
                <template v-slot:no-option>
                  <q-item>
                    <q-item-section class="text-grey">
                      No results
                    </q-item-section>
                  </q-item>
                </template>
              </q-select>
            </div>
            <div class="col-2 flex flex-center">
              <q-btn size="xs" color="primary" icon="add_circle" @click="agregarpedido"/>
            </div>
            <div class="col-12">
              <q-table :rows="misproductos"  :filter="filteproducto" :columns="columnsproducto">
                <template v-slot:body-cell-cantidad="props" >
                  <q-td :props="props" >
                    <q-btn flat @click="agregar(props.row)" class="q-ma-none q-pa-none" color="positive" icon="add_circle"/>{{ props.row.cantidad}}<q-btn flat @click="quitar(props.row,props.rowIndex)"  class="q-ma-none q-pa-none" color="negative" icon="remove_circle"/>
                  </q-td>
                </template>
                <template v-slot:top-right>
                  <div class="row">
                    <div class="col-12">
                      <q-input outlined dense v-model="filteproducto" placeholder="Buscar pedido">
                        <template v-slot:append>
                          <q-icon name="search" />
                        </template>
                      </q-input>
                    </div>
                  </div>
                </template>
                <template v-slot:bottom>
                  <div class="text-subtitle2">Total: {{total}}</div>
                </template>
              </q-table>
            </div>
          </div>

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
      filteproducto:'',
      modalopciones:false,
      modalpedido:false,
      center:[-17.970371, -67.112303],
      filter:'',
      zoom: 16,
      iconWidth: 25,
      iconHeight: 40,
      clientes:[],
      cliente:{},
      productos:[],
      productos2:[],
      misproductos:[],
      producto:{label:''},
      userLocation:{},
      columns:[
        {label:'Cod_Aut',name:'Cod_Aut',field:'Cod_Aut'},
        {label:'Nombres',name:'Nombres',field:'Nombres',align:'left'},
        {label:'opcion',name:'opcion',field:'opcion'},
      ],
      columnsproducto:[
        {label:'subtotal',name:'subtotal',field:'subtotal'},
        {label:'cantidad',name:'cantidad',field:'cantidad'},
        {label:'precio',name:'precio',field:'precio',align:'left'},
        {label:'cod_prod',name:'cod_prod',field:'cod_prod',align:'left'},
        {label:'nombre',name:'nombre',field:'nombre',align:'left'},

      ]
    };
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

    this.$api.get('producto').then(res=>{
      // console.log(res.data)
      this.productos=[]
      // this.productos=res.data
      res.data.forEach(r=>{
        let d=r
        d.label=r.cod_prod+' '+r.Producto+' '+ parseFloat(r.Precio).toFixed(2) +'Bs '+ parseFloat(r.cantidad).toFixed(2)
        this.productos.push(d)
      })
      this.productos2=this.productos
      // this.producto=this.productos[0]
      this.$q.loading.hide()
    })
  },
  methods: {
    agregar(producto){
      producto.cantidad = producto.cantidad+1
      producto.subtotal = (producto.cantidad*parseFloat(producto.precio)).toFixed(2)
    },
    quitar(producto,index){
      if (producto.cantidad==1){
        this.misproductos.splice(index, 1);
      }else {
        producto.cantidad = producto.cantidad-1
        producto.subtotal = (producto.cantidad*parseFloat(producto.precio)).toFixed(2)
      }

    },
    agregarpedido(){
      // console.log(this.producto)
      this.misproductos.push({
        nombre:this.producto.Producto,
        cod_prod:this.producto.cod_prod,
        precio:parseFloat(this.producto.Precio),
        cantidad:1,
        subtotal:parseFloat(this.producto.Precio)
      })
    },
    clickpedido(){
      this.modalopciones=false
      this.modalpedido=true
    },
    clickopciones(cliente){
      this.modalopciones=true
      this.cliente=cliente
    },
    ubicacion(e){
      // console.log(e.latlng)
      if (e.latlng!=undefined)
        this.center=[e.latlng.lat,e.latlng.lng]
    },
    async getCentro() {
      this.center = [-17.970371, -67.112303]
      // check if API is supported
      // if (navigator.geolocation) {
      //   // get  geolocation
      //   navigator.geolocation.getCurrentPosition(pos => {
      //     // set user location
      //     this.center = [
      //       pos.coords.latitude,
      //       pos.coords.longitude
      //     ]
      //   });
      // }
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
    filterFn (val, update) {
      if (val === '') {
        update(() => {
          this.productos = this.productos2

          // here you have access to "ref" which
          // is the Vue reference of the QSelect
        })
        return
      }

      update(() => {
        const needle = val.toLowerCase()
        this.productos = this.productos2.filter(v => v.label.toLowerCase().indexOf(needle) > -1)
      })
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
  computed: {
    // iconUrl() {
    //   return `https://placekitten.com/25/40`;
    // },
    // iconSize() {
    //   return [this.iconWidth, this.iconHeight];
    // },
    total(){
      let total=0
      this.misproductos.forEach(r=>{
        total+=parseFloat(r.subtotal)
      })
      return total.toFixed(2)
    }
  },
};
</script>
