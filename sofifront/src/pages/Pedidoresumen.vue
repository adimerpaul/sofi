<template>
<q-page class="q-pa-xs">
<div class="row">
  <div class="col-6">
    <q-input dense outlined v-model="fecha1" label="Fecha Ini" type="date"/>
  </div>
  <div class="col-6 flex flex-center">
    <q-btn color="info" icon="search" label="consulta" @click="mispendiente"  />
  </div>
  <div class="col-12">
    <q-table :rows-per-page-options="[15,50,100,0]" dense title="LISTADO DE PEDIDOS " :columns="columns" :rows="clientes" :filter="filter" >
            <template v-slot:top-right>
        <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
          <template v-slot:append>
            <q-icon name="search" />
          </template>
        </q-input>
      </template>
    </q-table>
  </div>

</div>
</q-page>
</template>

<script>
import {date} from "quasar";
import { jsPDF } from "jspdf";
export default {
  data(){
    return{
      url:process.env.API,
      filter:'',
      datocliente:{label:''},
      fecha1:date.formatDate(Date.now(),'YYYY-MM-DD'),
      clientes:[],
      columns:[
        {label:'CI',name:'Id',field:'Id',align:'left'},
        {label:'NOMBRE',name:'Nombres',field:'Nombres',align:'left'},
        {label:'COMANDA',name:'NroPed',field:'NroPed'},
        {label:'PAGO',name:'pago',field:'pago',align:'left'},
        {label:'FACTURA',name:'fact',field:'fact',align:'left'},
        {label:'Precentista',name:'personal',field:'personal',align:'left'},
      ],
      fecha:date.formatDate(Date.now(),'YYYY-MM-DD')
    }
  },
  created() {
    this.mispendiente()
  },
  methods:{
    mispendiente(){
      this.$q.loading.show()
      this.$api.get('resumenPedidos/'+this.fecha1).then(res=>{
        console.log(res.data)
        this.clientes=res.data
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
