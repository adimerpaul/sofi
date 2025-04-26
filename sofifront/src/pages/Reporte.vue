<template>
<q-page class="q-pa-xs">
<div class="row">
  <div class="col-6">
    <q-input dense outlined v-model="fecha1" label="Fecha Ini" type="date"/>
  </div>
  <div class="col-6 flex flex-center">
    <q-btn color="info" icon="search" label="consulta" @click="misclientes"  />
  </div>

  <div class="col-12">
     <table id="example" class="display" style="width:100%">
                  <thead>
                  <tr>
                    <th>FECHA</th>
                    <th>HORA</th>
                    <th>CI</th>
                    <th>CLIENTE</th>
                    <th>ESTADO</th>
                    <th>DESPACHADOR</th>
                    <th>DISTANCIA</th>
                    <th>OBSERVACION</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr v-for="(v,index) in clientes" :key="index">
                    <td>{{v.fecha}}</td>
                    <td>{{v.hora}}</td>
                    <td>{{v.Id}}</td>
                    <td>{{v.Nombres}}</td>
                    <td>{{v.estado}}</td>
                    <td>{{v.despachador}}</td>
                    <td>{{v.distancia}}</td>
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
import { jsPDF } from "jspdf";
export default {
     name: `Reporte.vue`,
  data(){
    return{
      url:process.env.API,
      filter:'',
      pedestado:'',
      miproducto:{},
      modalpedido:false,
      modalcerdo:false,
      modalres:false,
      modalnormal:false,
      modalpollo:false,
      pollo:[],
      res:[],
      cerdo:[],
      datocliente:{label:''},
      fecha1:date.formatDate(Date.now(),'YYYY-MM-DD'),
      clientes:[],
      options:[],
      cliente:{},
      pedido:{},
      producto:{label:''},

    }
  },
  created() {

      this.misclientes()

  },

  methods:{

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



    misclientes(){
         $('#example').DataTable().destroy();

      this.$q.loading.show()
      this.$api.get('entrega/'+this.fecha1).then(res=>{
         console.log(res.data)
        this.clientes=res.data
         this.$nextTick(()=>{
           $('#example').DataTable( {
             dom: 'Blfrtip',
             buttons: [
               'copy', 'csv', 'excel', 'pdf', 'print'
             ],
              "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
           } )})
        this.$q.loading.hide()
      })
    },
  },
    computed: {
    total(){
      let total=0
      this.misproductos.forEach(r=>{
        total+=parseFloat(r.subtotal)
      })
      return total.toFixed(2)
    }
  },
}
</script>

<style scoped>

</style>
