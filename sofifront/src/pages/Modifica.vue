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
    >
      <l-tile-layer
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      ></l-tile-layer>
  <!--    @click="clickopciones(c)"-->
      <l-marker v-for="(c,i) in clientesMapa" :key="c.Cod_Aut" :lat-lng="[c._lat, c._lng]"  >
        <l-icon><q-badge  class=" text-italic q-pa-none" color="info" >{{i+1}}</q-badge></l-icon>
      </l-marker>
      <l-marker :lat-lng="center"  >
      </l-marker>
    </l-map>
    </div>
  <div class="col-12 q-px-sm q-py-xs text-caption text-grey-8">
    Mostrando {{clientesMapa.length}} de {{clientesConMapa.length}} clientes con ubicación.
    <span v-if="clientesEnVista.length > maxMarcadores">
      Acerca el mapa para ver los {{clientesEnVista.length - maxMarcadores}} restantes de esta zona.
    </span>
  </div>
  <div class="col-12">
    <q-table :rows-per-page-options="[20,50,100]" dense title="CLIENTES" :columns="columns" :rows="filasTabla" :filter="filter">
      <template v-slot:body-cell-opcion="props">
        <q-td :props="props">
<!--          <q-btn @click="cambiar(props.row)"  color="teal"  icon="check" size="xs"  />-->
          <q-select @update:model-value="cambiopreventista($event,props.row)" dense outlined label="preventista" v-model="props.row.user" :options="usuarios"/>
          <q-btn @click="clickclientes(props.row)" icon="my_location" size="xs" color="accent"  />
        </q-td>
      </template>

     <template v-slot:top-right>
       <q-toggle dense class="q-mr-md" v-model="soloEnMapa" label="Solo los del mapa" />
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
      map:null,
      // Limites visibles del mapa; mientras sea null no se dibuja ningun marcador.
      limites:null,
      // Tope de marcadores dibujados a la vez: mas que esto congela el navegador.
      maxMarcadores:300,
      soloEnMapa:false,
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
  beforeUnmount() {
    if (this.map) {
      this.map.off('moveend zoomend', this.actualizarLimites)
      this.map = null
    }
  },
  computed:{
    clientesConMapa(){
      return this.clientes.filter(c => c._ubicado)
    },
    // Clientes dentro del recuadro visible del mapa.
    clientesEnVista(){
      const l = this.limites
      if (!l) return []
      return this.clientesConMapa.filter(c =>
        c._lat >= l.sur && c._lat <= l.norte && c._lng >= l.oeste && c._lng <= l.este
      )
    },
    // Lo que realmente se dibuja: la vista actual, recortada al tope.
    clientesMapa(){
      return this.clientesEnVista.slice(0, this.maxMarcadores)
    },
    filasTabla(){
      if (!this.soloEnMapa) return this.clientes
      return this.clientesEnVista
    }
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
        this.clientes=res.data.map(r=>{
          r.user=this.usuarios.find(u=>u.ci==r.CiVend)
          // Antes se comparaba con != NaN, que siempre da true, y los clientes
          // sin coordenada valida terminaban como marcador en 0,0 o en NaN.
          const lat=parseFloat(r.Latitud)
          const lng=parseFloat(r.longitud)
          r._ubicado=Number.isFinite(lat) && Number.isFinite(lng) && (lat!=0 || lng!=0)
          r._lat=r._ubicado?lat:null
          r._lng=r._ubicado?lng:null
          return r
        })
        this.$q.loading.hide()

      })

    },
    onReady (mapObject) {
      this.map = mapObject
      mapObject.locate();
      mapObject.on('moveend zoomend', this.actualizarLimites)
      this.actualizarLimites()
    },
    // Recalcula que porcion de clientes cae dentro de la pantalla del mapa.
    actualizarLimites(){
      if (!this.map) return
      const b = this.map.getBounds()
      this.limites = {
        norte: b.getNorth(), sur: b.getSouth(),
        este: b.getEast(), oeste: b.getWest()
      }
    },
    onLocationFound(location){
      // console.log(location)
      this.center=[location.latlng.lat,location.latlng.lng]
    },
    clickclientes(c){
      if (!c._ubicado){
        this.$q.notify({type:'warning',message:'Este cliente no tiene ubicación registrada'})
        return
      }
      this.center = [c._lat, c._lng]
    },
  }
}
</script>

<style scoped>

</style>
