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
    <l-marker v-for="(c,i) in clientes" :key="c.Cod_Aut" :lat-lng="[c.Latitud, c.longitud]"  @click="clickopciones(c)">
      <l-icon >
        <q-badge
        :class="c.estado=='ENTREGADO'?'bg-green text-italic':c.estado=='NO ENTREGADO'?'bg-yellow text-italic':c.estado=='RECHAZADO'?'bg-red text-italic':'bg-blue'"
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
          <q-td :class="props.row.estado=='ENTREGADO'?'bg-green text-italic':props.row.estado=='NO ENTREGADO'?'bg-yellow text-italic':props.row.estado=='RECHAZADO'?'bg-red text-italic':''" @click="clickopciones(props.row)" :props="props">
            <div class="text-weight-medium"> {{ props.pageIndex+1 }} {{ props.row.Nombres}}</div>
            <div class="text-caption" style="font-size: 8px">{{ props.row.Direccion}}</div>
          </q-td>
        </template>
        <template v-slot:body-cell-opcion="props">
          <q-td :class="props.row.estado=='ENTREGADO'?'bg-green text-italic':props.row.estado=='NO ENTREGADO'?'bg-yellow text-italic':props.row.estado=='RECHAZADO'?'bg-red text-italic':''">
            <q-btn @click="clickclientes(props.row)" icon="my_location" size="xs" :color="props.row.estado=='ENTREGADO'?'green':props.row.estado=='NO ENTREGADO'?'yellow-10':'red-10'"  />
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
      <q-card-section class="text-center text-subtitle2"><q-btn class="q-ma-xs"  icon="map" color="accent" size="sm" type="a" target="_blank" :href="'https://www.google.com.bo/maps/place/'+cliente.Latitud+','+cliente.longitud+'/@'+cliente.Latitud+','+cliente.longitud+',17z/data=!3m1!4b1!4m6!3m5!1s0x0:0xeda9371aeb8c1574!7e2!8m2!3d-17.981432!4d-67.1061122?hl=es'"/>{{cliente.Nombres}} {{cliente.Direccion}} {{cliente.Telf}}</q-card-section>
      <q-separator></q-separator>
      <q-card-section>
        <q-form>
          <div class="row">
            <div class="col-12 text-h6">PRESTAMO / DEVOLUCION DE CANASTILLOS</div>

            <div class="col-4 q-pa-xs"><q-input dense outlined v-model="prestamo.ingreso" type="number" label="Prestamo" /></div>
            <div class="col-4 q-pa-xs"><q-input dense outlined v-model="prestamo.salida" type="number" label="Devolucion" /></div>
            <div class="col-4 q-pa-xs"><q-btn dense color="green" icon="unarchive" label="Registrar" @click="regPrestamo" /></div>
          </div>
            <q-separator>
        </q-separator>

          <div class="row">
            <div class="col-12 text-h6">ENTREGA DE COMANDAS</div>

           <!-- <div class="col-12 q-pa-xs">
              <q-select dense outlined label="Estado" :options="['','ENTREGADO','NO ENTREGADO']" v-model="estado" required/>
            </div>-->
            <div class="col-12 q-pa-xs">
              <q-input type="textarea" dense outlined label="observacion" v-model="observacion"/>
            </div>
            <!--<div class="col-12">
              <q-btn type="submit" class="full-width" label="Confirmar" color="positive" icon="add_circle"/>
            </div>-->
            <div class="col-12">
              <q-table dense lang="productos" :rows="pedidos" :columns="columspedido"
              row-key="comanda"
              selection="multiple"
              v-model:selected="listado">

                <template v-slot:body="props">
                  <q-tr :props="props" :class="props.row.estado=='ENTREGADO'?'bg-green':props.row.estado=='NO ENTREGADO'?'bg-amber':''">
                    <q-td auto-width>
                    <q-checkbox v-model="props.selected" />
                      <q-btn size="sm"
                             :color="props.expand ? 'primary' : 'secondary'"
                             :label="props.expand ? 'Ocul' : 'Ver'"
                             no-caps dense @click="props.expand = !props.expand" :icon="props.expand ? 'visibility_off' : 'visibility'"/>
                    </q-td>
                    <q-td
                      v-for="col in props.cols"
                      :key="col.name"
                      :props="props"
                    >
                      {{ col.value }}

                    </q-td >
                    <td :props="props" :key="pago"><q-input square outlined v-model="props.row.pago" dense type="number" step="0.01" style="background-color:white; width:100px;"/></td>

                  </q-tr>
                  <q-tr v-show="props.expand" :props="props">
                    <q-td colspan="100%">
                      <div class="text-left" v-for="r in props.row.detalle " :key="r"> <b>Codigo:</b> {{r.cod_prod}} <b>Producto:</b> {{r.Producto}} <b>Cantidad:</b> {{r.cant}} </div>
                    </q-td>
                  </q-tr>
                </template>
              </q-table>
            </div>
          </div>
        </q-form>
      </q-card-section>
      <q-card-section align="right" >
          <q-btn class="q-pa-xs" color="green" dense label="ENTREGADO"  @click="createEntrega('ENTREGADO')" />
          <q-btn class="q-pa-xs" color="amber" dense label="NO ENTREGADO"  @click="createEntrega('NO ENTREGADO')"/>
          <q-btn class="q-pa-xs" color="red-10" dense label="RECHAZADO"  @click="createEntrega('RECHAZADO')"/>
          <q-btn class="q-pa-xs" color="red" dense label="CERRAR"  v-close-popup />

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
      prestamo:{ingreso:0,salida:0},
      estado:'',
      observacion:'',
        listado:[],
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
        {label:'Tipago',name:'Tipago',field:'Tipago',style: 'font-size:18px; font-weight:bold;',},
        {label:'estado',name:'estado',field:'estado'},
        {label:'Observacion',name:'Observacion',field:'observacion'},
        {label:'pago',name:'pago'},
     //   {label:'monto',name:'monto',field:'monto'},

      ],
      columns:[
        // {label:'Cod_Aut',name:'Cod_Aut',field:'Cod_Aut'},
        {label:'opcion',name:'opcion',field:'opcion'},
        {label:'Nombres',name:'Nombres',field:'Nombres',align:'left'},

      ],
      fecha:date.formatDate(new Date(),'YYYY-MM-DD'),
      hoy:date.formatDate(new Date(),'YYYY-MM-DD'),
    }
  },
  async created() {
    await this.misclientes()
  },
  methods:{
    regPrestamo(){
      console.log(this.prestamo)
      if(this.prestamo.ingreso < 0 || this.prestamo.salida < 0 || this.prestamo.ingreso=='' || this.prestamo.salida==''){
        this.$q.notify({
         message:'ingrese Valores correctos',
         color:'red',
         icon:'error'
        })
        return false
      }
      this.$q.dialog({
        title:'Seguro de enviar',
        color:'green',
        icon:'send',
        cancel:true
      }).onOk(data=>{

        this.$q.loading.show()
        this.$api.post('prestamo',{
        cliente_id:this.cliente.Cod_Aut,
        cinit:this.cliente.Id,
        ingreso:this.prestamo.ingreso,
        salida:this.prestamo.salida
      }).then(res=>{
        this.$q.loading.hide()
       this.$q.notify({
         message:'Registrado Prestamo',
         color:'green',
         icon:'info'
        })
        this.prestamo={ingreso:0,salida:0}
      })
      })
    },
    createEntrega(estado){

      this.$q.dialog({
        title:'Seguro de enviar',
        color:'green',
        icon:'send',
        cancel:true
      }).onOk(data=>{

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
            this.insertarpedido(lat,lng,estado)
          });
        }else{
          lat=0
          lng=0
          this.insertarpedido(lat,lng,estado)
        }

      })
    },
    insertarpedido(lat,lng,esta){
      // console.log(this.cliente)
      this.$q.loading.show()
      this.$api.post('regTodo',{
        cliente_id:this.cliente.Cod_Aut,
        cinit:this.cliente.Id,
        listado:this.listado,
        lat:lat,
        lng:lng,
        estado:esta,
        fecha:this.fecha,
        observacion:this.observacion
      }).then(async res=>{
        this.cliente={}
        this.listado=[]
        console.log(res.data)
        this.observacion=''
        console.log(res.data)
        // return false
        this.dialogentrega=false
        //this.clickopciones(this.cliente)
        await this.misclientes()
      })
    },
    clickopciones(c){

      //if(c.estados!='VAYA')
       //return false
      this.estado=''
      this.observacion=''
      this.cliente=c
      this.listado=[]
      // console.log(c)
      // return false
      this.$q.loading.show()
      this.$api.post('ruta',{
        id:c.Id,
        fecha:this.fecha
      }).then(res=>{
        this.pedidos=[]
        console.log(res.data)
        res.data.forEach(r => {
          r.pago=0
          this.pedidos.push(r)
        });
        //return false

        this.dialogentrega=true
        this.$q.loading.hide()

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
    async misclientes(){
      //if(this.fecha<this.hoy)
      //{this.clientes=[]
       // return false}

      this.$q.loading.show()
      this.$api.get('ruta/'+this.fecha).then(res=>{
         console.log(res.data)
        // return false
        this.clientes=[]
        // this.clientes=res.data
        /*res.data.forEach(r=>{
          let d=r
          // if (r.Latitud)
          // console.log(r.Latitud)
          //if (parseFloat(r.Latitud)!=NaN && parseFloat(r.longitud)!=NaN && r.Latitud!='' && r.longitud!='' ){
            // console.log( 'id='+r.Cod_Aut+'  '+(r.Latitud!='' && r.longitud!='' )+' R='+parseFloat(r.Latitud)+'---'+parseFloat(r.longitud))
            d.Latitud=parseFloat(r.Latitud)
            d.longitud=parseFloat(r.longitud)
          }else{
            // console.log( (r.Latitud!='' && r.longitud!='' )+' R='+r.Latitud+'---'+r.longitud)
            d.Latitud=0
            d.longitud=0
          }

          this.clientes.push(d)
        })/*/
        this.clientes=res.data
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
