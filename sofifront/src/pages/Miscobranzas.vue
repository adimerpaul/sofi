<template>
<q-page class="q-pa-xs">
  <div class="row">
    <div class="col-4">
      <q-input dense outlined v-model="fecha1" label="Fecha Ini" type="date"/>
    </div>

    <div class="col-4 flex flex-center">
      <q-btn color="info" icon="search" label="consulta" @click="mcobros" size="xs" />
    </div>
    <!--<div class="col-12">
    <q-table title="Pagos " :rows="cobros" :columns="columns" row-key="name" />

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
            </div>-->
    <div class="col-12">
      <q-table dense title="Clientes " :columns="columns" :rows="cobros" :filter="filter">
        <template v-slot:top-right>
          <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
            <template v-slot:append>
              <q-icon name="search" />
            </template>
          </q-input>
        </template>
        <template v-slot:body-cell-estado="props">
          <q-td :props="props">
            <q-badge :label="props.row.estado" :color="props.row.estado=='CREADO'?'negative':'positive'" />
          </q-td>
        </template>
      </q-table>
      <q-btn class="full-width" @click="enviarpedidos" color="accent" icon="check" label="Enviar todos los Cobros"> </q-btn>
      <q-btn class="full-width" @click="imprimir" color="info" icon="print" label="Imprimir todos los Cobros"> </q-btn>
    </div>
  </div>
</q-page>
</template>
<script>
import {date} from 'quasar'
import {jsPDF} from "jspdf";
const conversor = require('conversor-numero-a-letras-es-ar');

export default {
  data(){
    return{
      filter:'',
      fecha1:date.formatDate(Date.now(),'YYYY-MM-DD'),
      hoy:date.formatDate(Date.now(),'YYYY-MM-DD'),
      cobros:[],
      columns:[
        {label:'ESTADO',name:'estado',field:'estado'},
        {label:'FECHA',name:'fecha',field:'fecha'},
        {label:'COMANDA',name:'comanda',field:'comanda'},
        {label:'NOMBRE',name:'Nombres',field:'Nombres'},
        {label:'MONTO',name:'pago',field:'pago'},
        {label:'N BOLETA',name:'boleta',field:'nboleta'},
      ],
    }
    },
    created() {

      this.mcobros();

    },
    methods: {
      enviarcow(){
              this.$q.loading.show()
      this.$api.post('copiacow',{fecha1:this.fecha1}).then(res=>{
        console.log(res.data)
        this.$q.loading.hide()
        this.$q.notify({
          color:'green',
          message:'Enviado correctamente',
          icon:'send'
        })
      }).catch(err=>{
        this.$q.loading.hide()
        this.$q.notify({
          color:'red',
          message:err.response.data.message,
          icon:'error'
        })
      })

      },
      mcobros(){
      this.$q.loading.show()

      this.$api.post('miscobros',{fecha1:this.fecha1}).then(res=>{
        //console.log('s')
        console.log(res.data)
       // return false
        this.cobros=res.data;
        this.$q.loading.hide()

      }).catch(err=>{
        // console.log(err.response)
        this.$q.loading.hide()
        this.$q.notify({
          message:err.response.data.message,
          color:'red',
          icon:'error'
        })
      })

      },
      enviarpedidos(){
        if (!confirm('seguro de enviar?')){
          return false
        }
        this.$q.loading.show()
        this.$api.post('verificar',{fecha1:this.fecha1}).then(res=>{
          this.$q.loading.hide()
          this.mcobros();
        }).catch(err=>{
          // console.log(err.response)
          this.$q.notify({
            color:'red',
            message:err.response.data.message,
            icon:'error'
          })
          this.$q.loading.hide()
        })

      },
      imprimir(){
      let cm=this;
      function header(vendedor){
        var img = new Image()
        img.src = 'logo.jpg'
        doc.addImage(img, 'jpg', 0.5, 0.5, 3, 2)
        doc.setFont(undefined,'bold')
        doc.setFontSize(8);
        doc.text(15, 0.5, 'fecha de proceso: '+cm.hoy)
        doc.setFontSize(11);
        doc.text(5, 1, 'RESUMEN DE COBROS')
        doc.text(5, 1.5, 'DE FECHA '+cm.fecha1+' DEL PREVENTISTA '+vendedor)
        doc.text(1, 3, '_____________________________________________________________________________________')
        doc.text(2, 3, 'PAGO')
        doc.text(5, 3, 'CLIENTE')
        doc.text(13, 3, 'COMANDA')
        doc.text(16, 3, 'NBOLETA')
        doc.setFont(undefined,'normal')
      }
      function footer(con,sumtotal){
        doc.setFont(undefined,'bold')
        doc.text(2, y+3.5, 'TOTALES:     '+con+' ')
        doc.text(12, y+3.5, 'TOTAL RECAUDADCION: ')
        let ClaseConversor = conversor.conversorNumerosALetras;
        let miConversor = new ClaseConversor();
        let a = miConversor.convertToText(sumtotal);
        doc.text(1.5, y+4.4, 'SON: ')
        doc.setFont(undefined,'normal')
        doc.text(2.5, y+4.4, a.toUpperCase())
        doc.setFont(undefined,'bold')
        // console.log(a)
        doc.text(1.8, y+5.9, '___________________      __________________________        ____________________')
        doc.text(2, y+6.3, 'FIRMA SELLO CAJERO')
        doc.text(7.5, y+6.3, 'FIRMA SELLO CONTROL INTERNO')
        doc.text(15.5, y+6.3, 'FIRMA SELLO LIQUIDADOR')
        // doc.setFont(undefined,'normal')
        doc.text(18, y+3.5, sumtotal.toFixed(2)+ ' Bs')
      }
      var doc = new jsPDF('p','cm','letter')
      doc.setFont("courier");
      doc.setFontSize(11);

      let y=0
      // let sumtotal=0
      let sumtotal=0
      let con=0
      let vendedor=this.$store.getters['login/user'].Nombre1+" "+this.$store.getters['login/user'].App1
      header(vendedor )
      this.cobros.forEach(r=>{
        
          y+=0.5
          con++
          doc.text(2, y+3, r.pago+'')
          doc.text(5, y+3, r.cliente+'')
          doc.text(13, y+3, r.comanda+'')
          doc.text(16, y+3, r.nboleta+'')
          sumtotal+=parseFloat(r.pago)

          if(y+5>=28){
          y=0

          footer(con,sumtotal)
          doc.addPage();
          header(vendedor)

          }
        })
              footer(con,sumtotal)

      window.open(doc.output('bloburl'), '_blank');

      }

    },

}
</script>

<style scoped>

</style>
