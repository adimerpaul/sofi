<template>
<q-page class="q-pa-xs">
<div class="row">
  <div class="col-2 q-pa-xs">
    <q-input dense outlined v-model="fecha1" label="Fecha Ini" type="date" @update:model-value="mispendiente"/>
  </div>
  <div class="col-2 q-pa-xs">
    <q-btn dense color="green" label="Buscar" @click="mispendiente" no-caps icon="search"/>
  </div>
  <div class="col-8 q-pa-xs text-right">
    <q-btn dense color="positive"  label="Descargar" :href="`${url}reportePedido/${fecha1}`" target="_blank" icon="picture_as_pdf" no-caps/>
  </div>
  <div class="col-12 q-pa-xs">
    <q-table :rows-per-page-options="[0]" dense title="Listado de pedidos " :columns="columns" :rows="clientes" :filter="filter" >
            <template v-slot:top-right>
        <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
          <template v-slot:append>
            <q-icon name="search" />
          </template>
        </q-input>
      </template>
      <template v-slot:body-cell-pago="props">
        <q-td :props="props">
          <q-chip :color="props.row.pago=='CONTADO'?'red-7':'indigo'" dense text-color="white">
            {{props.row.pago}}
          </q-chip>
        </q-td>
      </template>
      <template v-slot:body-cell-op="props">
        <q-td :props="props">
           <q-btn color="info" dense flat icon="print" size="9"/>
        </q-td>
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
      pago:'',
      datocliente:{label:''},
      fecha1:date.formatDate(Date.now(),'YYYY-MM-DD'),
      clientes:[],
      clientesAll:[],
      columns:[
        {label:'Opcion',name:'op',field:'op'},
        {label:'Comanda',name:'NroPed',field:'NroPed', sortable: true},
        {label:'CI',name:'Id',field:'Id',align:'left', sortable: true},
        {label:'Nombre',name:'Nombres',field:'Nombres',align:'left', sortable: true},
        {label:'Fecha',name:'fecha',field:'fecha',align:'left', sortable: true},
        {label:'Pago',name:'pago',field:'pago',align:'left'},
        {label:'Factura',name:'fact',field:'fact',align:'left'},
        {label:'Pedido por',name:'personal',field:'personal',align:'left', sortable: true},
      ],
      fecha:date.formatDate(Date.now(),'YYYY-MM-DD')
    }
  },
  created() {
    this.mispendiente()
  },
  methods:{
    generarPdf(){

    },
    filtrarPago(pago){
      console.log(pago)
      if(pago.value=='CONTADO'){
        this.clientes=this.clientesAll.filter(r=>r.pago=='CONTADO')
      }else{
        this.clientes=this.clientesAll.filter(r=>r.pago=='CREDITO')
      }
    },
    mispendiente(){
      this.$q.loading.show()
      this.$api.get('resumenPedidos/'+this.fecha1).then(res=>{
        console.log(res.data)
        this.clientes=res.data
        this.clientesAll=res.data
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
