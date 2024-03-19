<template>
<q-page class="q-pa-xs">
  <div class="col-6">
    <q-input @change="consulta()" v-model="fecha" label="fecha" dense outlined type="date" />
  </div>
  <div class="row">

    <div class="col-12">
<!--      <pre>{{datos}}</pre>-->
    <q-table title="ENTREGAS" :rows="listado" :columns="columns" row-key="name" />
      <div>Total Contado: {{totalEnt}} Bs.</div>
    </div>

  </div>
</q-page>
</template>

<script>
import {date} from "quasar";
export default {
  name: `Despacho`,
  data(){
    return{
      fecha:date.formatDate(new Date(),'YYYY-MM-DD'),
      fechareporte:{ini:date.formatDate(new Date(),'YYYY-MM-DD'),fin:date.formatDate(new Date(),'YYYY-MM-DD')},
      listado:[],

       columns : [
        { name: 'CINIT',label: 'CINIT',align: 'left',field: 'CINIT',   sortable: true},
        { name: 'CLIENTE', label: 'CLIENTE',align: 'left',field: 'Nombres',sortable: true},
  { name: 'comanda', align: 'center', label: 'COMANDA', field: 'comanda', sortable: true },
  { name: 'Importe', align: 'center', label: 'IMPORTE', field: 'Importe', sortable: true },
  { name: 'Placa', align: 'center', label: 'PLACA', field: 'placa', sortable: true },
  { name: 'despachador', align: 'center', label: 'DESPACHADOR', field: 'despachador', sortable: true },
  { name: 'Tipago', align: 'center', label: 'TIPO PAGO', field: 'Tipago', sortable: true },
  { name: 'observacion', label: 'OBSERVACION', field: 'observacion' },],

    }
  },
  created(){
    this.consulta()
  },
  methods:{

    consulta(){
      this.$q.loading.show()
      // console.log()
      this.listado=[]
      this.$q.loading.show()

        this.$api.post('reporteDes',{fecha:this.fecha }).then(res=>{
          console.log(res.data)
          this.listado=res.data;

          this.$q.loading.hide()
        })
    },

  },
  computed:{
    totalEnt(){
      let res=0
      this.listado.forEach(r => {
         if(r.Tipago=='CONTADO'){
            res+=parseFloat(r.Importe)
         }
      })
      return res
    }
  }
}
</script>

<style scoped>

</style>
