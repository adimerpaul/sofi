<template>
<q-page class="q-pa-xs">
  <div class="row">
    <div class="col-12">
      <q-form @submit.prevent="miscobrosrealizados">
        <div class="row">
          <div class="col-4">
            <q-input label="fecha" v-model="fecha" outlined dense type="date" />
          </div>
          <div class="col-4">
            <q-btn type="submit" color="positive" icon="check" label="consultar" class="full-width full-height"/>
          </div>
          <div class="col-4">
            <q-btn color="accent" label="CtaCobrar" icon="import" type="button" @click="recuperar"/>
          </div>
        </div>
      </q-form>
    </div>
<!--    <div class="col-4"> <q-btn color="accent" label="CtaCobrar" @click="recuperar"/>    </div>-->
    <div class="col-12">
      <q-table :filter="filter" dense title="Cobros realizados" :columns="columscobros" :rows="cobros">
            <template v-slot:body-cell-opcion="props">
        <q-td :props="props">
           <q-btn dense color="red"  icon='delete' v-if="props.row.estado=='CREADO'" @click="eliminar(props.row.codAut)"/>
        </q-td>
      </template>
        <template v-slot:top-right>
          <q-input placeholder="Buscar" dense outlined v-model="filter" />
        </template>
      </q-table>
    </div>
    <div class="col-12">
      <q-btn @click="imprimir" color="info" icon="print" label="Imprimir" class="full-width" />
    </div>
  </div>
</q-page>
</template>
<script>
import {date} from "quasar"
import {jsPDF} from "jspdf";
const conversor = require('conversor-numero-a-letras-es-ar');
export default {
  name: `Cobrosrealizados`,
  data(){
    return{
      filter:'',
      fecha:date.formatDate(new Date(),'YYYY-MM-DD'),
      hoy:date.formatDate(new Date(),'YYYY-MM-DD'),
      cobros:[],
      columscobros:[
        // {label:"opcion",name:"opcion",field:"opcion",align:'left'},
        {label:"vendedor",name:"vendedor",field:"vendedor",align:'left'},
        {label:"pago",name:"pago",field:"pago"},
        {label:"cliente",name:"cliente",field:"cliente",align:'left'},
        {label:"comanda",name:"comanda",field:"comanda"},
        // {label:"fecomanda",name:"fecomanda",field:"fecomanda"},
        {label:"nboleta",name:"nboleta",field:"nboleta"},
        {label:"estado",name:"estado",field:"estado"},
        {label:"OPCION",name:"opcion",field:"opcion"},
      ],
    }
  },
  created() {
    this.miscobrosrealizados()
  },
  methods:{
    recuperar(){
      this.$q.loading.show()
      this.$api.post('ctacobrar').then(res=>{
        console.log(res.data)
                  this.$q.notify({
          color:'green',
          message:'Actualizado cuentas',
          icon:'check'
        })
        this.$q.loading.hide()

      })
    },
    eliminar(cod){
      this.$q.loading.show()
      this.$api.post('delcobro',{'codAut':cod}).then(res=>{
          this.$q.notify({
          color:'green',
          message:'Eliminado Registro',
          icon:'delete'
        })
        this.miscobrosrealizados()
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
        doc.text(5, 1.5, 'DE FECHA '+cm.fecha+' DEL PREVENTISTA '+vendedor)
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
      let vendedor=this.cobros[0].vendedor
      header(vendedor)
      this.cobros.forEach(r=>{
        if (vendedor!=r.vendedor){
          footer(con,sumtotal)
          doc.addPage();
          vendedor=r.vendedor
          header(vendedor)
          y=0
          con=0
          sumtotal=0
          y+=0.5
          con++
          doc.text(2, y+3, r.pago+'')
          doc.text(5, y+3, r.cliente+'')
          doc.text(13, y+3, r.comanda+'')
          doc.text(16, y+3, r.nboleta+'')
          sumtotal+=parseFloat(r.pago)
        }else{
          y+=0.5
          con++
          doc.text(2, y+3, r.pago+'')
          doc.text(5, y+3, r.cliente+'')
          doc.text(13, y+3, r.comanda+'')
          doc.text(16, y+3, r.nboleta+'')
          sumtotal+=parseFloat(r.pago)
        }

        // if (con==55){
        //   doc.addPage();
        //   header(this.fecha)
        //   y=0P
        // }
      })
      footer(con,sumtotal)

      // doc.save("Pago"+date.formatDate(Date.now(),'DD-MM-YYYY')+".pdf");
      window.open(doc.output('bloburl'), '_blank');
    },
    miscobrosrealizados(){
      this.$q.loading.show()
      this.$api.get('cobrosrealizados/'+this.fecha).then(res=>{
        this.cobros=[]
        res.data.forEach(r=>{
          r.vendedor=r.Nombre1+' '+r.App1
          r.cliente=r.Nombres
          this.cobros.push(r)
        })
        // console.log(res.data)
        this.$q.loading.hide()
      })
    }
  }
}
</script>
<style scoped>
</style>
