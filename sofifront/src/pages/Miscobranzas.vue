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

export default {
  data(){
    return{
      filter:'',
      fecha1:date.formatDate(Date.now(),'YYYY-MM-DD'),
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
        let total=0;
                let cadena="<style> table, th, td { border: 1px solid;}  table {border-collapse: collapse; width:100%;          }</style>";
        cadena+="<div>NOMBRE:"+ this.$store.getters['login/user'].Nombre1+" "+this.$store.getters['login/user'].Nombre2 +" "+this.$store.getters['login/user'].App1 +" "+this.$store.getters['login/user'].Apm +"</div>";
        cadena+="<div>FECHA: "+this.fecha1+" al "+this.fecha2+"</div>";
        cadena+="<table>        <tr>";
        cadena+="<th>FECHA</th>";
        cadena+="<th>No NOTA</th>";
        cadena+="<th>NOMBRE</th>";
        cadena+="<th>MONTO</th>";
        cadena+="<th>N BOLETA</th></tr>";
        this.cobros.forEach(r => {
             total  = parseFloat(total)+ parseFloat(r.pago) ;
            cadena+="<tr><td>"+r.fecomanda+"</td> <td>"+r.comanda+"</td><td>"+r.Nombres+"</td><td>"+r.pago+"</td><td>"+r.nboleta+"</td></tr>";
                });

        cadena+="</table><div>TOTAL: "+total+" Bs.</div>";
        //this.$api.post('impcobros',{fecha1:this.fecha1,fecha2:this.fecha2}).then(res=>{
        let myWindow = window.open("", "Imprimir", "width=200,height=200");
        myWindow.document.write(cadena);
        myWindow.document.close();
        myWindow.print();
        myWindow.close();
      //  })

      }

    },

}
</script>

<style scoped>

</style>
