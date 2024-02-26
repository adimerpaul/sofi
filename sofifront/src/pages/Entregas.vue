<template>
<q-page class="q-pa-xs">
  <div class="col-6">
    <q-input @change="consula()" v-model="fecha" label="fecha" dense outlined type="date" />
  </div>
  <div class="row">

    <div class="col-12">
      <q-table title="Entregas Pedidos" :rows="resumen" :columns="columns2" row-key="name" />
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
  { name: 'noentreg', label: 'No Entregado', field: 'noentreg' },
],
columns3 : [
  { name: 'codigo',  label: 'CODIGO PROD', field: 'cod_prod', sortable: true },
  { name: 'producto', label: 'PRODUCTO',    field: 'Producto', },
  { name: 'cantidad',  label: 'CANTIDAD', field: 'cantidad', },
]

    }
  },
  created(){
           $('#example').DataTable( {
       dom: 'Blfrtip',
       buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
     } );
    this.$q.loading.show()
    this.consula()
  },
  methods:{
    listvendedor(){
      this.$api.post('lispreventista').then(res=>{
          res.data.forEach(r=>{
            r.label=r.Nombre1+' '+r.App1;
          })
          this.preventistas=res.data
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
