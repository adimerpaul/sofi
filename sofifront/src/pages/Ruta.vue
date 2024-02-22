<template>
<q-page class="q-pa-none">
  <div class="row">
    <div class="col-12">
      <q-form @submit.prevent="misclientes">
        <div class="row">
          <div class="col-6">
            <q-input label="fecha" dense outlined v-model="fecha" type="date" />
          </div>
          <div class="col-6 flex flex-center">
            <q-btn class="full-width" type="submit" icon="search" label="buscar" color="primary" />
          </div>
        </div>
      </q-form>
    </div>
  </div>
  <div style="height: 350px; width: 100%;">
  <l-map
    @ready="onReady"
    @locationfound="onLocationFound"
    v-model="zoom"
    :zoom="zoom"
    :center="center"
    @move="log('move')"
  >
    <l-tile-layer
      url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
    ></l-tile-layer>
<!--    @click="clickopciones(c)"-->
    <l-marker v-for="(c,i) in clientes" :key="c.Cod_Aut" :lat-lng="[c.Latitud, c.longitud]"  >
      <l-icon>
        <q-badge
        :class="c.estados=='ENTREGADO'?'bg-green text-italic':c.estados=='NO ENTREGADO'?'bg-red text-italic':''"
        style="padding: 2px"
        >{{i+1}}
        </q-badge>
      </l-icon>
    </l-marker>

  </l-map>
  </div>
  <div class="row">
    <div class="col-12">
      <q-table :rows="clientes" hide-header :filter="filter" :columns="columns" :rows-per-page-options="[0]" class="my-sticky-header-table">
<!--        <template v-slot:body-cell-Cod_Aut="props">-->
<!--          <q-td :class="props.row.tipo=='PEDIDO'?'bg-green-3  text-italic':props.row.tipo=='PARADO'?'bg-yellow-3  text-italic':props.row.tipo=='NO PEDIDO'?'bg-red-3 text-italic':''" @click="clickopciones(props.row)" :props="props">-->
<!--            <q-badge  color="info"> {{ props.row.Cod_Aut}}</q-badge>-->
<!--          </q-td>-->
<!--        </template>-->
        <template v-slot:body-cell-Nombres="props">
          <q-td :class="props.row.estado=='ENTREGADO'?'bg-green text-italic':props.row.estado=='NO ENTREGADO'?'bg-yellow text-italic':''" @click="clickopciones(props.row)" :props="props">
            <div class="text-weight-medium"> {{ props.pageIndex+1 }} {{ props.row.Nombres}}</div>
            <div class="text-caption" style="font-size: 8px">{{ props.row.Direccion}}</div>
          </q-td>
        </template>
        <template v-slot:body-cell-opcion="props">
          <q-td :class="props.row.estados=='ENTREGADO'?'bg-green text-italic':props.row.estado=='NO ENTREGADO'?'bg-yellow text-italic':''">
            <q-btn @click="clickclientes(props.row)" icon="my_location" size="xs" :color="props.row.estado=='ENTREGADO'?'positive':'negative'"  />
          </q-td>
        </template>
        <template v-slot:top-right>
          <div class="row">
            <div class="col-2 flex flex-center" @click="getUserPosition"  >
              <q-btn icon="my_location" size="xs" color="primary"  />
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
    </div>
  </div>
  <q-dialog full-width full-height v-model="dialogentrega">
    <q-card>
      <q-card-section class="text-center text-subtitle2">{{cliente.Nombres}} {{cliente.Direccion}} {{cliente.Telf}}</q-card-section>
      <q-separator></q-separator>
      <q-card-section>
        <q-form>
          <div class="row">
            <div class="col-12 q-pa-xs">
              <q-select dense outlined label="Estado" :options="['','ENTREGADO','NO ENTREGADO']" v-model="estado" required/>
            </div>
            <div class="col-12 q-pa-xs">
              <q-input type="textarea" dense outlined label="observacion" v-model="observacion"/>
            </div>
            <!--<div class="col-12">
              <q-btn type="submit" class="full-width" label="Confirmar" color="positive" icon="add_circle"/>
            </div>-->
            <div class="col-12">
              <q-table dense lang="productos" :rows="pedidos" :columns="columspedido">
                <template v-slot:header="props">
                  <q-tr :props="props">
                    <q-th auto-width />
                    <q-th auto-width />
                    <q-th
                      v-for="col in props.cols"
                      :key="col.name"
                      :props="props"
                    >
                      {{ col.label }}
                    </q-th>
                  </q-tr>
                </template>

                <template v-slot:body="props">
                  <q-tr :props="props" :class="props.row.estado=='ENTREGADO'?'bg-green':props.row.estado=='NO ENTREGADO'?'bg-amber':''">
                    <q-td auto-width>
                      <q-btn size="sm"
                             :color="props.expand ? 'primary' : 'secondary'"
                             :label="props.expand ? 'Ocul' : 'Ver'"
                             no-caps dense @click="props.expand = !props.expand" :icon="props.expand ? 'visibility_off' : 'visibility'"/>
                    </q-td>
                    <td :props="props" key="op" >
                      <q-btn color="green" dense icon="local_shipping" v-if="props.row.estado!='ENTREGADO'" @click="createEntrega(props.row)"/>
                    </td>
                    <q-td
                      v-for="col in props.cols"
                      :key="col.name"
                      :props="props"
                    >
                      {{ col.value }}
                    </q-td>

                  </q-tr>
                  <q-tr v-show="props.expand" :props="props">
                    <q-td colspan="100%">
                      <div class="text-left" v-for="r in props.row.detalle " :key="r"> <b>Codigo:</b> {{r.cod_prod}} <b>Producto:</b> {{r.Producto}} <b>Cantidad:</b> {{r.cant}} <b>Precio:</b> {{ r.PVentUnit }} </div>
                    </q-td>
                  </q-tr>
                </template>
              </q-table>
            </div>
          </div>
        </q-form>
      </q-card-section>
    </q-card>
  </q-dialog>
</q-page>
</template>

<script>
// import {LIcon, LMap, LMarker, LTileLayer} from "@vue-leaflet/vue-leaflet";
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
import {date} from "quasar";
const { addToDate } = date
export default {
  name: `Ruta.vue`,
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
  data(){
    return{
      estado:'',
      observacion:'',
      dialogentrega:false,
      center:[-17.970371, -67.112303],
      zoom:16,
      filter:'',
      clientes:[],
      pedidos:[],
      cliente:{},
      columspedido:[
        {label:'comanda',name:'comanda',field:'comanda'},
        {label:'Importe',name:'Importe',field:'Importe'},
        {label:'Tipago',name:'Tipago',field:'Tipago'},
        {label:'estado',name:'estado',field:'estado'},
        {label:'Observacion',name:'Observacion',field:'Observacion'},

      ],
      columns:[
        // {label:'Cod_Aut',name:'Cod_Aut',field:'Cod_Aut'},
        {label:'opcion',name:'opcion',field:'opcion'},
        {label:'Nombres',name:'Nombres',field:'Nombres',align:'left'},

      ],
      fecha:date.formatDate(addToDate(new Date(), { days: -1}),'YYYY-MM-DD'),
    }
  },
  created() {
    this.misclientes()
  },
  methods:{
    createEntrega(ped){
      if(this.estado=='')
        return false
      this.$q.dialog({
        title:'Seguro de enviar',
        color:'green',
        icon:'send',
        cancel:true
      }).onOk(data=>{
        // if (this.misproductos.length==0){
        //   this.$q.notify({
        //     message:'No tienes productos',
        //     icon:'error',
        //     color:'red'
        //   })
        //   return false
        // }
        this.$q.loading.show()
        let lat=0,lng=0
        if (navigator.geolocation) {
          // get  geolocation
          navigator.geolocation.getCurrentPosition(pos => {
            // set user location
            // this.center = [
            //   pos.coords.latitude,
            //   pos.coords.longitude
            // ]
            lat=pos.coords.latitude
            lng=pos.coords.longitude
            this.insertarpedido(lat,lng,ped)
          });
        }else{
          lat=0
          lng=0
          this.insertarpedido(lat,lng,ped)
        }

      })
    },
    insertarpedido(lat,lng,ped){
      // console.log(this.cliente)
      this.$api.post('entrega',{
        cliente_id:this.cliente.Cod_Aut,
        cinit:this.cliente.Id,
        comanda:ped.comanda,
        monto:ped.Importe,
        fechaEntreg:ped.FechaEntreg,
        lat:lat,
        lng:lng,
        estado:this.estado,
        fecha:this.fecha,
        observacion:this.observacion
      }).then(res=>{
        console.log(res.data)
        //return false
        this.estado=''
        this.observacion=''
        console.log(res.data)
        // return false
        this.dialogentrega=false
        this.misclientes()
      })
    },
    clickopciones(c){

      //if(c.estados!='VAYA')
       //return false
      this.estado=''
      this.observacion=''
      this.cliente=c
      // console.log(c)
      // return false
      this.$q.loading.show()
      this.$api.post('ruta',{
        id:c.Id,
        fecha:this.fecha
      }).then(res=>{
        console.log(res.data)

        this.$q.loading.hide()
        //return false
        this.dialogentrega=true

        this.pedidos=res.data
      })
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
    clickclientes(c){
      console.log(c)
      this.center = [c.Latitud, c.longitud]
    },
    misclientes(){
      this.$q.loading.show()
      this.$api.get('ruta/'+this.fecha).then(res=>{
         console.log(res.data)
        this.$q.loading.hide()
        // return false
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
        // console.log(this.clientes)
        this.$q.loading.hide()
      }).catch(err=>{
        // console.log(err.response)
        this.$q.loading.hide()
        this.$q.notify({
          message:'Error al conectarse al server',
          color:'red',
          icon:'error'
        })
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
      // console.log(a);
    },
  }
}
</script>

<style scoped>

</style>
