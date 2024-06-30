<template>
<q-page class="q-pa-xs">
  <div>LISTADO DE PEDIDOS CLIENTE</div>
  <div class="row">
    <div class="col-12">
      <q-input @change="generar()" v-model="fecha" label="fecha" dense outlined type="date" />
    </div>
    <div class="col-3 text-center q-pa-xs">
      <div class="text-subtitle2 text-bold">Totales</div>
      <div class="text-h3 text-bold ">{{pedido+retorno+nopedido}}</div>
    </div>
    <div class="col-3 text-center q-pa-xs">
      <div class="text-subtitle2 text-bold">Pedidos</div>
      <div class="text-h3 text-bold text-green " >{{pedido}}</div>
    </div>
    <div class="col-3 text-center q-pa-xs">
      <div class="text-subtitle2 text-bold">Retorno</div>
      <div class="text-h3 text-bold text-yellow " >{{retorno}}</div>
    </div>
    <div class="col-3 text-center q-pa-xs">
      <div class="text-subtitle2 text-bold">No pedidos</div>
      <div class="text-h3 text-bold text-red " >{{nopedido}}</div>
    </div>

    <div class="col-12">
    <q-table title="Listado de Clientes Pedido" :rows="visitas" :columns="columns" row-key="name" dense/>

    </div>
    <div class="col-12">
      <q-table title="Listado de Entregas" :rows="pedidos" :columns="colped" row-key="name" dense/>
      
    </div>
  </div>
</q-page>
</template>

<script>
import {date} from "quasar";
export default {
  name: `avance`,
  data(){
    return{
      usuarios:[],
      fecha:date.formatDate(new Date(),'YYYY-MM-DD'),
      fechareporte:{ini:date.formatDate(new Date(),'YYYY-MM-DD'),fin:date.formatDate(new Date(),'YYYY-MM-DD')},
      user:'',
      pedido:0,
      retorno:0,
      nopedido:0,
      visitas:[],
      infoventa:[],
      preventistas:[],
      preventista:{},
      productos:[],
      pedidos:[],

       columns : [
  {
    name: 'name',
    label: 'CLIENTE',
    align: 'left',
    field: 'Nombres',
    sortable: true
  },
  { name: 'estado', align: 'center', label: 'ESTADO', field: 'tipo', sortable: true }],

colped : [
  { name: 'CI',  label: 'ci', field: 'Id', sortable: true,align:'left' },
  { name: 'CLIENTE', label: 'nombre',    field: 'Nombres', sortable: true,align:'left'  },
  { name: 'COMANDA',  label: 'comanda', field: 'comanda', sortable: true  },
  { name: 'ESTADO',  label: 'estado', field: 'estado', },
]

    }
  },
  created(){
    this.user=this.$store.getters['login/user'].ci
    console.log(this.user)
    this.consulta()
    this.clientes()
    this.entregas()
    //this.controlvisita()

  },
  methods:{
    generar(){
      this.consulta()
      this.clientes()
      this.entregas()
    },
    consulta(){
      this.pedido=0
      this.retorno=0
      this.nopedido=0
      this.$q.loading.show()

      this.$api.post('pedidoVenta',{
        fecha:this.fecha
      }).then(res=>{
        console.log(res.data)
        //return false
        if (res.data.length>0){
          res.data.forEach(r=>{
            if (r.estado=='PEDIDO'){
              this.pedido=r.cantidad
            }
            if (r.estado=='PARADO'){
              this.retorno=r.cantidad
            }
            if (r.estado=='NO PEDIDO'){
              this.nopedido=r.cantidad
            }
          })
        }
        this.$q.loading.hide()
      })
    },
    clientes(){
      this.$api.post('listClientePrev',{fecha:this.fecha }).then(res=>{
        console.log(res.data)
         this.visitas=res.data
        })
    },
    entregas(){
      this.$api.post('reportEntregVend',{fecha:this.fecha }).then(res=>{
        console.log(res.data)
         this.pedidos=res.data
        })
    }

  }
}
</script>

<style scoped>

</style>
