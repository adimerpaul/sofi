<template>
<q-page class="q-pa-xs">
  <div class="col-6">
    <q-input @change="consula()" v-model="fecha" label="fecha" dense outlined type="date" />
  </div>
  <div class="row">

    <div class="col-12">
      <q-table title="Entregas Pedidos" :rows="resumen" :columns="columns2" row-key="name" >
        <template v-slot:body-cell-op="props" >
          <q-td :props="props">
             <q-btn color="info" icon="print" dense @click="impresion(props.row)"/>

          </q-td>
        </template>
        <template v-slot:body-cell-entreg="props" >
          <q-td :props="props">
            <div class="q-pa-xs">
        <q-linear-progress size="16px" rounded :value="calcular(props.row.entreg,props.row.total)" :color="'green-7'" class="full-width ">
          <div class="absolute-full flex flex-center">
            <q-badge color="white" :text-color="'green-7'" :label="props.row.entreg" />
          </div>
        </q-linear-progress>
      </div>
            <div class="q-pa-xs">
              <q-linear-progress size="16px" rounded :value="calcular(props.row.noentreg,props.row.total)" :color="'yellow-10'" class="full-width q-pa-xs">
          <div class="absolute-full flex flex-center">
            <q-badge color="white" :text-color="'yellow-10'" :label="props.row.noentreg" />
          </div>
        </q-linear-progress>
          </div>
          <div class="q-pa-xs">
              <q-linear-progress size="16px" rounded :value="calcular(props.row.rechazado,props.row.total)" :color="'red-7'" class="full-width q-pa-xs">
          <div class="absolute-full flex flex-center">
            <q-badge color="white" :text-color="'red-7'" :label="props.row.rechazado" />
          </div>
        </q-linear-progress>
          </div>
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
          <th>DISTANCIA</th>
          <th>PAGO</th>
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
          <td>{{v.distancia}}</td>
          <td>{{v.pago}}</td>
          <td>{{v.observacion}}</td>
        </tr>
        </tbody>
      </table>
    </div>
    <div class="col-12">
      <q-table title="Entrega" :rows="rcontable"  row-key="name" />

    </div>
      <div class="col-4 q-pa-xs"><q-input dense outlined v-model="fechareporte.ini" label="Fecha Ini" type="date"/></div>
      <div class="col-4 q-pa-xs"><q-input dense outlined v-model="fechareporte.fin" label="Fecha Fin" type="date"/></div>
      <div class="col-4 q-pa-xs"> <q-btn dense color="green" icon="search" @click="reportEnt"/></div>
      <div class="col-12">
        <q-table title="Reporte Canastillos" :rows="reporte" :columns="colrept" row-key="name" />

      </div>
      <div id="myelement" class="hidden"></div>

  </div>
</q-page>
</template>

<script>
import {date} from "quasar";
import { Printd } from 'printd'

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
      rcontable:[],
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
  { name: 'op',  label: 'OP', field: 'op' },
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
    impresion(r){
      console.log(r)
      this.$api.post('reportEntImp',{fecha:this.fecha,placa:r.placa}).then(res=>{
        let contenido=''
        let num=1
        let total=0
        let totalpago=0
        res.data.forEach(r => {
            if (r.estado ==null) r.estado=''
            if (r.pago ==null) r.pago=0
            contenido+='<tr><td>'+num+'</td><td>'+r.comanda+'</td><td>'+r.Nombres+'</td><td>'+r.Importe+'</td><td>'+r.Tipago+'</td><td>'+r.estado+'</td><td>'+r.pago+'</td</tr>'
            num++
            total+=parseFloat(r.Importe)
            totalpago+=parseFloat(r.pago)
        });
        let cadena=`<style>
        .titulo1{font-size:18px;}
        .tab1{width:100%}</style>
        <table class='tab1'>
          <tr><td><img src="logo.png" alt="logo" width="150" height="100"></td>
            <td class='titulo1' style='color:red; font-weight:bold; font-size:20px;'>ENTREGAS DEL DIA <br> <span style="color:blue">`+date.formatDate(this.fecha,'dddd, DD MMMM YYYY')+`</span></td></tr>
          </table>
          <table class='tab1'
            <tr><th>No</th><th>Comanda</th><th>Cliente</th><th>Monto</th><th>Tipo</th><th>Estado</th><th>Pago</th></tr>
            `+contenido+`
          </table>
          <div><b>TOTAL Comanda: </b> `+total.toFixed(2)+` Bs</div>
          <div><b>TOTAL Pagos: </b> `+totalpago.toFixed(2)+` Bs</div>`
          document.getElementById('myelement').innerHTML = cadena
          const d = new Printd()
          d.print( document.getElementById('myelement') )
          })
      },
    calcular(entrega,total){
      let porcentaje=((entrega/total*100)/100)
      if (porcentaje>1){
        return 1
      }else{
        return porcentaje
      }
    },
    contable(){
      this.$api.get('reportContable/'+this.fecha).then(res=>{
        this.rcontable=res.data
      })

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
              this.contable()

        })
        this.$q.loading.hide()
      })
    },

  }
}
</script>

<style scoped>

</style>
