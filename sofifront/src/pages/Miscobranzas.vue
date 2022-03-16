<template>
<q-page class="q-pa-xs">
  <div class="row">
    <div class="col-4">
      <q-input dense outlined v-model="fecha1" label="Fecha Ini" type="date"/>
    </div>
    <div class="col-4">
      <q-input dense outlined v-model="fecha2" label="Fecha Fin" type="date"/>
    </div>
    <div class="col-4 flex flex-center">
      <q-btn color="info" icon="search" label="consulta" @click="mcobros" size="xs" />
    </div>
                <div class=" table " style="width:100%">
                <table id="example" style="width:100%">
                <thead>
                <tr>
                <th>FECHA</th>
                <th>COMANDA</th>
                <th>NOMBRE</th>
                <th>MONTO</th>
                <th>ESTADO</th>
                <th>N BOLETA</th>
                </tr>
                </thead>
                  <tbody>
                  <tr v-for="v in cobros" :key="v.id">
                  <td>{{v.fecha}}</td>
                  <td>{{v.comanda}}</td>
                  <td>{{v.Nombres}}</td>
                  <td>{{v.pago}}</td>
                  <td>{{v.estado}}</td>
                  <td>{{v.nboleta}}</td>
                  </tr>
                  </tbody>
                </table>
            </div>
    <div class="col-12">
      <q-table dense title="Clientes " :columns="columns" :rows="cobros" :filter="filter">
        <template v-slot:body-cell-opciones="props">
          <q-td :props="props">
            <q-btn  @click="listpedidos(props.row)" :color="props.row.estado=='CREADO'?'primary':'warning'" :label="props.row.estado=='CREADO'?'MODIFICAR':'ENVIADO'" icon="shop" size="xs"  />        </q-td>
        </template>
        <template v-slot:top-right>
          <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
            <template v-slot:append>
              <q-icon name="search" />
            </template>
          </q-input>
        </template>
      </q-table>
      <q-btn style="width: 100%" @click="enviarpedidos" color="accent" icon="check" label="Enviar todos los Cobros"> </q-btn>
      <q-btn style="width: 100%" @click="imprimir" color="info" icon="print" label="Imprimir todos los Cobros"> </q-btn>
    </div>
  </div>
</q-page>
</template>
<script>
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
import {date} from 'quasar'
// import { jsPDF } from "jspdf";

export default {
  data(){
    return{
      filter:'',
      fecha1:date.formatDate(Date.now(),'YYYY-MM-DD'),
      fecha2:date.formatDate(Date.now(),'YYYY-MM-DD'),
      cobros:[],
      columns:[
        {label:'FECHA',name:'fecha',field:'fecha'},
        {label:'COMANDA',name:'comanda',field:'comanda'},
        {label:'NOMBRE',name:'Nombres',field:'Nombres'},
        {label:'MONTO',name:'pago',field:'pago'},
        {label:'ESTADO',name:'estado',field:'estado'},
        {label:'N BOLETA',name:'boleta',field:'nboleta'},
      ],
    }
    },
    created() {
      $('#example').DataTable( {
        dom: 'Blfrtip',
        buttons: [
          'copy', 'csv', 'excel', 'pdf', 'print'
        ]
      });
      this.mcobros();

    },
    methods: {
      mcobros(){
      this.$api.post('miscobros',{fecha1:this.fecha1,fecha2:this.fecha2}).then(res=>{
        //console.log('s')
        console.log(res.data)
       // return false
        this.cobros=res.data;
                $('#example').DataTable().destroy();
        this.$nextTick(()=>{
          $('#example').DataTable( {
            dom: 'Blfrtip',
            buttons: [
              'copy', 'csv', 'excel', 'pdf', 'print'
            ]
          } );
        })
      })

      },
      enviarpedidos(){},
      imprimir(){
        this.$api.post('impcobros',{fecha1:this.fecha1,fecha2:this.fecha2}).then(res=>{
        let myWindow = window.open("", "Imprimir", "width=200,height=200");
        myWindow.document.write(res.data);
        myWindow.document.close();
        myWindow.print();
        myWindow.close();
        })

      }

    },

}
</script>

<style scoped>

</style>
