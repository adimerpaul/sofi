<template>
<q-page class="q-pa-xs">
  <div class="row">
    <div class="col-6">
      <q-select @update:model-value="consula(user)" dense outlined label="Vendero" :options="usuarios" v-model="user" />
    </div>
    <div class="col-6">
      <q-input @change="consula(user)" v-model="fecha" label="fecha" dense outlined type="date" />
    </div>
    <div class="col-3 text-center q-pa-xs">
      <div class="text-subtitle2 text-bold">Totales</div>
      <div class="text-h3 text-bold ">{{pedido+retorno+nopedido}}</div>
    </div>
    <div class="col-3 text-center q-pa-xs">
      <div class="text-subtitle2 text-bold">Pedidos</div>
      <div class="text-h3 text-bold text-green " >{{pedido}}</div>
    </div>
    <div class="col-3 text-center q-pa-xs">
      <div class="text-subtitle2 text-bold">Retorno</div>
      <div class="text-h3 text-bold text-yellow " >{{retorno}}</div>
    </div>
    <div class="col-3 text-center q-pa-xs">
      <div class="text-subtitle2 text-bold">No pedidos</div>
      <div class="text-h3 text-bold text-red " >{{nopedido}}</div>
    </div>
    <div class="col-12">
      <q-table title="Efectividad Pedido Cliente" :rows="infoventa" :columns="columns2" row-key="name" />
      
    </div>
    <div class="col-12">
<!--      <pre>{{datos}}</pre>-->
      <table id="example" class="display" style="width:100%">
        <thead>
        <tr>
          <th>No</th>
          <th>HORA</th>
          <th>CLIENTE</th>
          <th>ESTADO</th>
          <th>PERSONAL</th>
          <th>DISTANCIA</th>
          <th>OBSERVACION</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="(v,index) in visitas" :key="index">
          <td>{{index + 1}}</td>
          <td>{{v.hora}}</td>
          <td>{{v.Nombres}}</td>
          <td>{{v.estado}}</td>
          <td>{{v.Nombre1}} {{v.App1}}</td>
          <td>{{v.distancia}}</td>
          <td>{{v.observacion}}</td>
        </tr>
        </tbody>
      </table>
    </div>
    <div class="col-12">
      <div class="row">
        <div class="col-6">
          <q-select dense outlined v-model="prev" :options="prevent" label="Pre Ventista" /> 
          <q-btn color="info" icon="search" dense flat @click="listmap" />
        
          <q-table title="Entregas Pedidos" :rows="clientes" :columns="colmap" row-key="name" dense :filter="filter">
            <template v-slot:top-right>
              <q-input borderless dense debounce="300" v-model="filter" placeholder="Search">
                <template v-slot:append>
                  <q-icon name="search" />
                </template>
              </q-input>
            </template>
            <template v-slot:body-cell-op="props" >
              <q-td :props="props" auto-width >
                 <q-btn icon="place" color="blue" @click="consultaRow(props.row)"  dense flat/>
                
              </q-td>
            </template>
          </q-table>
        </div>
        <div class="col-12 col-md-6">
          <!--      <div class="col-md-6 col-xs-12">-->
          <div style="height: 350px; width: 100%;">
    
            <l-map
              v-model="zoom"
              :zoom="zoom"
              :center="center"
            >
              <LTileLayer
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
              ></LTileLayer>
              <!--    @click="clickopciones(c)"-->
              <l-marker v-for="(c) in clientes" :key="c.Cod_Aut" :lat-lng="[c.Latitud, c.longitud]"  >
                <l-tooltip :content="c.Nombres"></l-tooltip>
                <l-icon >
                  <q-badge style="padding: 2px" >{{c.idCli}}
                  </q-badge>
                </l-icon>
              </l-marker>
            </l-map>
          </div>
          <!--      </div>-->
        </div>
      </div>
      </div>
    </div>
    <div class="col-12">
      <div class="row">
        <div class="col-3"><q-select dense outlined v-model="preventista" :options="preventistas" label="Pre Ventista" /></div>
        <div class="col-3"><q-input dense outlined v-model="fechareporte.ini" label="Inicio" type="date" /></div>
        <div class="col-3"><q-input dense outlined v-model="fechareporte.fin" label="Fin" type="date" /></div>
        <div class="col-3"> <q-btn color="green" label="Reporte" icon="search" @click="ventaProducto"/>
        </div>
      </div>
      <q-table title="Productos Vendidos" :rows="productos" :columns="columns3" row-key="name" />
      
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
var $  = require( 'jquery' );
require( 'datatables.net-buttons/js/buttons.html5.js' )();
require( 'datatables.net-buttons/js/buttons.print.js' )();
require('datatables.net-buttons/js/dataTables.buttons');
require('datatables.net-dt/css/jquery.dataTables.min.css');
import print from 'datatables.net-buttons/js/buttons.print';
import jszip from 'jszip/dist/jszip';
import pdfMake from 'pdfmake/build/pdfmake';
import pdfFonts from 'pdfmake/build/vfs_fonts';
import { filter } from "jszip";
pdfMake.vfs=pdfFonts.pdfMake.vfs;
window.JSZip=jszip;
export default {
  name: `Monitoreo`,
  components: {
    LMap,
    LIcon,
    LTileLayer,
    LMarker,
    LTooltip
  },
  data(){
    return{
      center:[-17.969721, -67.114493],
      clientes:[],
      filter:'',
      zoom:13,  
      usuarios:[],
      fecha:date.formatDate(new Date(),'YYYY-MM-DD'),
      fechareporte:{ini:date.formatDate(new Date(),'YYYY-MM-DD'),fin:date.formatDate(new Date(),'YYYY-MM-DD')},
      user:{},
      pedido:0,
      retorno:0,
      nopedido:0,
      visitas:[],
      infoventa:[],
      preventistas:[],
      prevent:[],
      prev:{},
      preventista:{},
      productos:[],

       columns : [
  {
    name: 'name',
    label: 'CLIENTE',
    align: 'left',
    field: 'Nombres',
    sortable: true
  },
  { name: 'estado', align: 'center', label: 'ESTADO', field: 'estado', sortable: true },
  { name: 'personal', align: 'center', label: 'PERSONAL', field: row=> row.Nombre1 + ' ' +row.App1, sortable: true },
  { name: 'observacion', label: 'OBSERVACION', field: 'observacion' },],
  columns2 : [
  { name: 'personal',  label: 'PERSONAL', field: row=> row.Nombre1 + ' ' +row.App1, sortable: true },
  { name: 'totclient', label: 'T PEDIDOS',    field: 'totclient', },
  { name: 'totvisita',  label: 'T DIA', field: 'totvisita', },
  { name: 'numcli', label: 'NUM ASIGNADO', field: 'numcli' },
  { name: 'numcli', label: 'Efectividad', field: row=>Math.round((parseFloat(row.totvisita) / parseFloat(row.numcli)*100))+' %' },
  { name: 'npedido', label: 'NUM PEDIDO', field: 'npedido' },
  { name: 'nopedido', label: 'NUM NO PEDIDO', field: 'nopedido' },
  { name: 'nparado', label: 'NUM VOLVER', field: 'nparado' },
],
columns3 : [
  { name: 'codigo',  label: 'CODIGO PROD', field: 'cod_prod', sortable: true },
  { name: 'producto', label: 'PRODUCTO',    field: 'Producto', },
  { name: 'cantidad',  label: 'CANTIDAD', field: 'cantidad', },
],
colmap:[
  { name: 'op',  label: 'op', field: 'op', },
  { name: 'codaut',  label: 'id', field: 'idCli', },
  { name: 'id',  label: 'CiNIT', field: 'Id', },
  { name: 'nombre',  label: 'Nombre', field: 'Nombres', },
  { name: 'vendedor',  label: 'Vendedor', field: 'vendedor', },

]

    }
  },
  created(){
           $('#example').DataTable( {
       dom: 'Blfrtip',
       buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
     } );
    this.$q.loading.show()
    this.misuser()
    this.consula(this.user)
    this.controlvisita()
    this.listvendedor()
    this.listmap()
  },
  methods:{
    consultaRow(r){
        this.zoom=16
        this.center=[r.Latitud,r.longitud]
    },
    listmap(){
      this.$api.post('mapClient',{'fecha':this.fecha,'id':this.prev.CodAut}).then(res=>{
        console.log(res.data)
        this.clientes=res.data
      })
    },
    listvendedor(){
      this.prevent=[{CodAut:0,label:'TODOS'}]
      this.$api.post('lispreventista').then(res=>{
          res.data.forEach(r=>{
            r.label=r.Nombre1+' '+r.App1;
            this.prevent.push(r)
          })
          this.preventistas=res.data
      })
      this.prev=this.prevent[0]
    },
    ventaProducto(){
      this.$api.post('informeProducto',{cod:this.preventista.CodAut,ini:this.fechareporte.ini,fin:this.fechareporte.fin}).then(res=>{
        console.log(res.data)
        this.productos=res.data
      })

    },
    controlvisita(){
      this.$api.post('reporteVenta',{fecha:this.fecha}).then(res=>{
        console.log(res.data)
        this.infoventa=res.data
        this.preventista=this.preventistas[0]
      })
    },
    consula(user){
      this.pedido=0
      this.retorno=0
      this.nopedido=0
      this.$q.loading.show()
      // console.log()
      this.visitas=[]

      $('#example').DataTable().destroy()

      this.$api.post('misvisitas',{
        id:user.CodAut,
        fecha:this.fecha
      }).then(res=>{
        console.log(res.data)
        if (res.data.length>0){
          res.data.forEach(r=>{
            if (r.estado=='PEDIDO'){
              this.pedido=r.cantidad
            }
            if (r.estado=='PARADO'){
              this.retorno=r.cantidad
            }
            if (r.estado=='NO PEDIDO'){
              this.nopedido=r.cantidad
            }
          })
        }
        // this.datos=res.data

        // console.log(res.data)
        this.$api.post('listvisita',{id:user.CodAut,fecha:this.fecha }).then(res=>{
        console.log(res.data)
          $('#example').DataTable().destroy();
          this.visitas=res.data;
          this.$nextTick(()=>{
               $('#example').DataTable( {
                 dom: 'Blfrtip',
                 buttons: [
                   'copy', 'csv', 'excel', 'pdf', 'print'
                 ],
                  "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]     } )})

        })
        this.controlvisita()
        this.$q.loading.hide()
      })
    },
    misuser(){
      this.usuarios=[{CodAut:0,label:'Todos'}]
      // this.$q.loading.show()
      this.$api.get('listapersonal').then(res=>{
        // this.misclientes()
        // console.log(res.data)
        // this.$q.loading.hide()
        res.data.forEach(r => {
          // console.log(r)
          r.label=r.Nombre1+' '+r.App1
          this.usuarios.push(r)
        });
        // console.log(this.usuarios)
        this.user=this.usuarios[0]
        this.consula(this.user)
      })
    },
  }
}
</script>

<style scoped>

</style>
