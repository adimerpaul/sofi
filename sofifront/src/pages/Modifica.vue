<template>
<q-page class="q-pa-xs">

<div class="row">
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
        <l-icon><q-badge  class=" text-italic q-pa-none" color="info" >{{i+1}}</q-badge></l-icon>
      </l-marker>
      <l-marker :lat-lng="center"  >
      </l-marker>
    </l-map>
    </div>
  <div class="col-12">
    <q-table :rows-per-page-options="[20,50,100,0]" dense title="CLIENTES" :columns="columns" :rows="clientes" :filter="filter">
      <template v-slot:body-cell-opcion="props">
        <q-td :props="props">
<!--          <q-btn @click="cambiar(props.row)"  color="teal"  icon="check" size="xs"  />-->
          <q-select @update:model-value="cambiopreventista($event,props.row)" dense outlined label="preventista" v-model="props.row.user" :options="usuarios"/>
          <q-btn @click="clickclientes(props.row)" icon="my_location" size="xs" color="accent"  />
        </q-td>
      </template>

     <template v-slot:top-right>
       <q-btn icon="refresh" label="actualizar" @click="misclientes" color="primary" />
        <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
          <template v-slot:append>
            <q-icon name="search" />
          </template>
        </q-input>
      </template>
    </q-table>
  </div>
    <q-dialog v-model="dialog_mod" persistent>
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">CLIENTE: {{cliente.Nombres}}</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <div class="text-h6">Seleccione Preventista</div>
          <q-select aria-label="Personal" :options="usuarios" v-model="user"/>
        </q-card-section>

        <q-card-actions align="right" class="text-primary">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn flat label="Modificar" @click="modificar" />
        </q-card-actions>
      </q-card>
    </q-dialog>
</div>
</q-page>
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
import {date} from "quasar";
const { addToDate } = date
export default {
  name: `Modifica.vue`,

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
      filter:'',
      dialog_mod:false,
      center:[-17.970371, -67.112303],
      zoom:16,
      asignaciones:[],
      asignar:{},
      cliente:{},
      fecha2:date.formatDate(Date.now(),'YYYY-MM-DD'),
      user:{label:''},
      clientes:[],
      usuarios:[],
      options:[],
      options2:[],
      listado:[],
      dialog_ag:false,
      columns:[
        {label:'opcion',name:'opcion',field:'opcion'},
        // {label:'OPCIONES',name:'opciones',field:'opciones'},
        {label:'NOMBRES',name:'nombres',field:'Nombres',align:'left'},
        {label:'PREVENTISTA',name:'nombres',field:row=>row.Nombre1 + ' ' + row.App1,align:'left'},
        {label:'CI/NIT',name:'Id',field:'Id',align:'left'},
        {label:'Observacion',name:'obs',field:'obs',align:'left'},
        {label:'telefono',name:'Telf',field:'Telf',align:'left'},
        {label:'Direccion',name:'Direccion',field:'Direccion',align:'left'},


      ],
      fecha:date.formatDate(Date.now(),'YYYY-MM-DD')
    }
  },
  created() {
    this.misuser()
    this.misclientes()


  },
  methods:{
      modificar(){

      this.$q.loading.show()
      this.$api.post('modprevent',{vendedor:this.user.ci,cliente_id:this.cliente.Cod_Aut}).then(res=>{

          this.dialog_mod=false
          this.misclientes()
      })
      },
      cambiar(cliente){
          this.cliente=cliente
          this.dialog_mod=true
      },
    cambiopreventista(user,cliente){
        console.log(user)
      // this.$q.loading.show()
      this.$api.post('modprevent',{vendedor:user.ci,cliente_id:cliente.Cod_Aut}).then(res=>{
        // this.dialog_mod=false
        // console.log(res.data)
        // this.misclientes()
      })
    },
     misuser(){
      this.usuarios=[]
      // this.$q.loading.show()
      this.$api.get('listapersonal').then(res=>{
         // console.log(res.data)
        // this.$q.loading.hide()
        res.data.forEach(r => {
          // console.log(r)
            r.label=r.Nombre1+' '+r.App1
            this.usuarios.push(r)
        });
        // console.log(this.usuarios)
        this.user=this.usuarios[0]
      })
     },

        filterFn (val, update) {
      if (val === '') {
        update(() => {
          this.options = this.clientes
          // with Quasar v1.7.4+
          // here you have access to "ref" which
          // is the Vue reference of the QSelect
        })
        return
      }
      update(() => {
        const needle = val.toLowerCase()
        this.options = this.clientes.filter(v => v.label.toLowerCase().indexOf(needle) > -1)
      })
    },

    misclientes(){
      this.clientes=[]
      this.$q.loading.show()
      this.$api.get('listaclientes').then(res=>{
        // console.log(res.data)
        this.clientes=[]
        // this.clientes=res.data
        res.data.forEach(r=>{
          let d=r

          d.user=this.usuarios.find(u=>u.ci==r.CiVend)
          if ((parseFloat(r.Latitud)!=NaN || parseFloat(r.longitud)!=NaN) && (r.Latitud!='' || r.longitud!='') ){
            // console.log( 'id='+r.Cod_Aut+'  '+(r.Latitud!='' && r.longitud!='' )+' R='+parseFloat(r.Latitud)+'---'+parseFloat(r.longitud))
            d.Latitud=parseFloat(r.Latitud)
            d.longitud=parseFloat(r.longitud)
          }else{
            // console.log( (r.Latitud!='' && r.longitud!='' )+' R='+r.Latitud+'---'+r.longitud)
            d.Latitud=0
            d.longitud=0
          }
          // console.log(r)
          this.clientes.push(d)
        })
        console.log(this.clientes)
        this.$q.loading.hide()

      })

    },
    onReady (mapObject) {
      mapObject.locate();
    },
    onLocationFound(location){
      // console.log(location)
      this.center=[location.latlng.lat,location.latlng.lng]
    },
    clickclientes(c){
      console.log(c)
      this.center = [c.Latitud, c.longitud]
    },
    log(a) {
      // console.log(a);
    },
  }
}
</script>

<style scoped>

</style>
