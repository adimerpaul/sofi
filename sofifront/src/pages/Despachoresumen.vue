<template>
<q-page class="q-pa-xs">
<div class="row">
  <div class="col-12 col-md-2 q-pa-xs">
    <q-input dense outlined v-model="fecha1" label="Fecha Ini" type="date" @update:model-value="mispendiente"/>
  </div>
  <div class="col-12 col-md-2 q-pa-xs">
    <q-btn :loading="loading" dense color="green" label="Buscar" @click="mispendiente" no-caps icon="search"/>
  </div>
  <div class="col-12 col-md-2 q-pa-xs">
    <q-btn :loading="loading" dense color="orange" label="Actulizar Comandas" href="http://192.168.1.200:3000/ventas" target="_blank" no-caps/>
  </div>
  <div class="col-12 col-md-2 q-pa-xs">
    <q-btn :loading="loading" dense color="orange" label="Actulizar Productos" href="http://192.168.1.200:3000/cuentas" target="_blank" no-caps/>
  </div>
<!--  http://192.168.1.200:3000/ventas-->
  <div class="col-12 col-md-2 q-pa-xs text-right">
<!--    <q-btn :loading="loading" dense color="positive"  label="Descargar"  icon="picture_as_pdf" no-caps @click="generarPdf"/>-->
    <q-btn-dropdown color="info" icon="print" label="Reportes" no-caps>
      <q-list>
        <q-item clickable @click="generarPdf" v-close-popup>
          <q-item-section avatar>
            <q-icon name="print"/>
          </q-item-section>
          <q-item-section>Imprimir Pedidos</q-item-section>
        </q-item>
        <q-item clickable @click="generarPdfZona" v-close-popup>
          <q-item-section avatar>
            <q-icon name="print"/>
          </q-item-section>
          <q-item-section>Imprimir Pedidos Por Zona</q-item-section>
        </q-item>
        <q-item clickable @click="generarPdfProductos" v-close-popup>
          <q-item-section avatar>
            <q-icon name="print" />
          </q-item-section>
          <q-item-section>Imprimir Productos Totales</q-item-section>
        </q-item>
      </q-list>
    </q-btn-dropdown>
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
      <template v-slot:body="props">
        <q-tr :props="props" :class="{'bg-blue-3':props.row.impreso==1}">
          <q-td key="op" :props="props">
            <q-btn color="positive" dense flat icon="print" size="12px" style="height: 0" @click="generarPdfOnly(props.row)"/>
          </q-td>
          <q-td key="NroPed" :props="props">
            {{props.row.NroPed}}
          </q-td>
          <q-td key="Id" :props="props">
            {{props.row.Id}}
          </q-td>
          <q-td key="Nombres" :props="props">
            {{props.row.Nombres}}
          </q-td>
          <q-td key="fecha" :props="props">
            {{props.row.fecha}}
          </q-td>
          <q-td key="envio" :props="props">
            {{props.row.envio}}
          </q-td>
          <q-td key="pago" :props="props">
            <q-chip :color="props.row.pago=='CONTADO'?'red-7':'indigo'" dense text-color="white">
              {{props.row.pago}}
            </q-chip>
          </q-td>
          <q-td key="fact" :props="props">
            {{props.row.fact}}
          </q-td>
          <q-td key="personal" :props="props">
            {{props.row.personal}}
          </q-td>
        </q-tr>
      </template>
<!--      <template v-slot:body-cell-pago="props">-->
<!--        <q-td :props="props">-->
<!--          <q-chip :color="props.row.pago=='CONTADO'?'red-7':'indigo'" dense text-color="white">-->
<!--            {{props.row.pago}}-->
<!--          </q-chip>-->
<!--        </q-td>-->
<!--      </template>-->
<!--      <template v-slot:body-cell-op="props">-->
<!--        <q-td :props="props">-->
<!--           <q-btn color="info" dense flat icon="print" size="9"/>-->
<!--        </q-td>-->
<!--      </template>-->
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
        {label:'Envio',name:'envio',field:'envio',align:'left', sortable: true},
        {label:'Pago',name:'pago',field:'pago',align:'left'},
        {label:'Factura',name:'fact',field:'fact',align:'left'},
        {label:'Pedido por',name:'personal',field:'personal',align:'left', sortable: true},
      ],
      fecha:date.formatDate(Date.now(),'YYYY-MM-DD'),
      loading: false,
    }
  },
  created() {
    this.mispendiente()
  },
  methods:{
    generarPdfOnly(row){
      const url = `${this.url}reportePedidoOnly/${row.NroPed}`
      window.open(url, '_blank')
      this.mispendiente()
    },
    generarPdf(){
    // :href="`${url}reportePedido/${fecha1}`" target="_blank"
      const url = `${this.url}reportePedido/${this.fecha1}`
      window.open(url, '_blank')
      this.mispendiente()
    },
    generarPdfZona() {
      const urlapi = `${this.url}reportePedidoZona/${this.fecha1}`
      window.open(urlapi, '_blank')
    },
    generarPdfProductos() {
      const urlapi = `${this.url}reportePedidoProductos/${this.fecha1}`
      window.open(urlapi, '_blank')
    },
    filtrarPago(pago){
      console.log(pago)
      if(pago.value=='CONTADO'){
        this.clientes=this.clientesAll.filter(r=>r.pago=='CONTADO')
      }else{
        this.clientes=this.clientesAll.filter(r=>r.pago=='CREDITO')
      }
    },
    async clickventas() {
      this.loading = true
      await this.$api.get('http://192.168.1.200:3000/ventas').then(res=>{
        $.q.notify({
          color: 'positive',
          message: 'Comandas actualizadas',
          icon: 'check_circle'
        })
      }).catch(e => {
        this.$q.notify({
          color: 'negative',
          message: 'Error al actualizar las comandas',
          icon: 'report_problem'
        })
      }).finally(() => {
        this.loading = false
      });
    },
    async clickcuentas(){
      this.loading = true
      await this.$api.get('http://192.168.1.200:3000/cuentas').then(res=>{
        $.q.notify({
          color: 'positive',
          message: 'Productos actualizados',
          icon: 'check_circle'
        })
      }).catch(e => {
        this.$q.notify({
          color: 'negative',
          message: 'Error al actualizar los productos',
          icon: 'report_problem'
        })
      }).finally(() => {
        this.loading = false
      });
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
