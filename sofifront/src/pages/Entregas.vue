<template>
<q-page class="q-pa-xs">
  <div class="col-6">
    <q-input @change="consula()" v-model="fecha" label="fecha" dense outlined type="date" />
  </div>
  <div class="row">

    <div class="col-12">
      <q-table title="Entregas Pedidos" :rows="resumen" :columns="columns2" row-key="name" >
        <template v-slot:body-cell-entreg="props" >
          <q-td :props="props">
        <q-linear-progress size="18px" rounded :value="calcular(props.row.entreg,props.row.total)" :color="calcular(props.row.entreg,props.row.entreg)<1?'red-7':'green-7'" class="full-width">
          <div class="absolute-full flex flex-center">
            <q-badge color="white" :text-color="calcular(props.row.entreg,props.row.total)<1?'red-7':'green-7'" :label="props.row.entreg" />
          </div>
        </q-linear-progress>
        </q-td>
        </template>
        </q-table>
    </div>

    <div class="col-12">
<!--      <pre>{{datos}}</pre>-->
      <table id="example" class="display" style="width:100%">
        <thead>
        <tr>
          <th>CINIT</th>
          <th>NOMBRE</th>
          <th>COMANDA</th>
          <th>PLACA</th>
          <th>DEPACHADOR</th>
          <th>ESTADO</th>
          <th>OBSERVACION</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="(v,index) in listado" :key="index">
          <td>{{v.CINIT}}</td>
          <td>{{v.Nombres}}</td>
          <td>{{v.comanda}}</td>
          <td>{{v.placa}} </td>
          <td>{{v.despachador}} </td>
          <td>{{v.estado}}</td>
          <td>{{v.observacion}}</td>
        </tr>
        </tbody>
      </table>
    </div>
      <div class="col-4 q-pa-xs"><q-input dense outlined v-model="fechareporte.ini" label="Fecha Ini" type="date"/></div>
      <div class="col-4 q-pa-xs"><q-input dense outlined v-model="fechareporte.fin" label="Fecha Fin" type="date"/></div>
      <div class="col-4 q-pa-xs"> <q-btn dense color="green" icon="search" @click="reportEnt"/></div>
      <div class="col-12">
        <q-table title="Reporte Canastillos" :rows="reporte" :columns="colrept" row-key="name" />
        
      </div>

  </div>
</q-page>
</template>

<script>
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
pdfMake.vfs=pdfFonts.pdfMake.vfs;
window.JSZip=jszip;
export default {
  name: `Entrega`,
  data(){
    return{
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
      preventista:{},
      productos:[],
      resumen:[],
      listado:[],
      reporte:[],
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
  { name: 'placa',  label: 'placa', field: 'placa', sortable: true },
  { name: 'fecha', label: 'Fecha',    field: 'fechaEntreg' },
  { name: 'total',  label: 'Total', field: 'total' },
  { name: 'entreg', label: 'Entregado', field: 'entreg' },
],
columns3 : [
  { name: 'codigo',  label: 'CODIGO PROD', field: 'cod_prod', sortable: true },
  { name: 'producto', label: 'PRODUCTO',    field: 'Producto', },
  { name: 'cantidad',  label: 'CANTIDAD', field: 'cantidad', },
],
colrept:[
  { name: 'cinit',  label: 'cinit', field: 'cinit', sortable: true },
  { name: 'Nombres',  label: 'Nombres', field: 'Nombres', sortable: true },
  { name: 'placa',  label: 'placa', field: 'placa', sortable: true },
  { name: 'prestado',  label: 'prestado', field: 'prestado', sortable: true },
  { name: 'devuelto',  label: 'devuelto', field: 'devuelto', sortable: true },
]

    }
  },
  created(){
           $('#example').DataTable( {
       dom: 'Blfrtip',
       buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
     } );
    this.consula()
  },
  methods:{
    calcular(entrega,total){
      let porcentaje=((entrega/total*100)/100)
      if (porcentaje>1){
        return 1
      }else{
        return porcentaje
      }
    },
    listvendedor(){
      this.$api.post('lispreventista').then(res=>{
          res.data.forEach(r=>{
            r.label=r.Nombre1+' '+r.App1;
          })
          this.preventistas=res.data
      })

    },
    reportEnt(){
      this.$api.post('rePrestamo2',this.fechareporte).then(res=>{
        console.log(res.data)
        this.reporte=res.data
      })
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
    consula(){
      this.$q.loading.show()
      // console.log()
      this.resumen=[]
      this.listado=[]
      $('#example').DataTable().destroy()
      this.$q.loading.show()

      this.$api.post('resumenEntrega',{
        fecha:this.fecha
      }).then(res=>{
        console.log(res.data)
        this.resumen=res.data

        this.$api.post('listRuta',{fecha:this.fecha }).then(res=>{
          console.log(res.data)
          $('#example').DataTable().destroy();
          this.listado=res.data;
          this.$nextTick(()=>{
               $('#example').DataTable( {
                 dom: 'Blfrtip',
                 buttons: [
                   'copy', 'csv', 'excel', 'pdf', 'print'
                 ],
                  "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]     } )})

        })
        this.$q.loading.hide()
      })
    },

  }
}
</script>

<style scoped>

</style>
