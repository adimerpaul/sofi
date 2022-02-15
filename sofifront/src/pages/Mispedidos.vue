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
    <q-btn color="info" icon="search" label="consulta" @click="misclientes" size="xs" />
  </div>
  <div class="col-12">
    <q-table dense title="Clientes " :columns="columns" :rows="clientes" :filter="filter">
      <template v-slot:body-cell-opciones="props">
        <q-td :props="props">
          <q-btn @click="listpedidos(props.row)" color="primary" label="Pedidos" icon="shop" size="xs"  />
        </q-td>
      </template>
      <template v-slot:top-right>
        <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
          <template v-slot:append>
            <q-icon name="search" />
          </template>
        </q-input>
      </template>
    </q-table>
  </div>

      <q-dialog v-model="dialog_pedido" >
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Agregar a Lista Pedido {{pedido.Nombres}} </div>
        </q-card-section>

        <q-card-section class="q-pt-none">
                <q-radio v-model="pedestado" val="PEDIDO" label="REALIZAR PEDIDO" color="green" />
                <q-radio v-model="pedestado" val="VOLVER" label="VOLVER EN 20M" color="warning"/>
                <q-radio v-model="pedestado" val="NOPEDIDO" label=" NO PEDIDO" color="red"/>
        </q-card-section>

        <q-card-actions align="right" class="text-primary">
        {{pedestado}}
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn flat label="Registrar" @click="agregar" />
        </q-card-actions>
      </q-card>
    </q-dialog>
</div>
</q-page>
</template>

<script>
import {date} from "quasar";
export default {
  data(){
    return{
      filter:'',
      pedestado:'',
      cliente:{label:''},
      fecha1:date.formatDate(Date.now(),'YYYY-MM-DD'),
      fecha2:date.formatDate(Date.now(),'YYYY-MM-DD'),
      clientes:[],
      options:[],
      pedido:{},
      dialog_pedido:false,
      columns:[
        {label:'CI',name:'Id',field:'Id'},
        {label:'Nombre',name:'Nombres',field:'Nombres'},
        {label:'opciones',name:'opciones',field:'opciones'},
      ],
      fecha:date.formatDate(Date.now(),'YYYY-MM-DD')
    }
  },
  created() {
    this.misclientes()
  },
  methods:{
    listpedidos(cliente){
        
      this.$api.post('listpedido',{idCli:cliente.Cod_Aut,fecha1:this.fecha1,fecha2:this.fecha2}).then(res=>{
          console.log(res.data)
      })
    },

    misclientes(){
      this.$q.loading.show()
      this.$api.post('clientepedido',{fecha1:this.fecha1,fecha2:this.fecha2}).then(res=>{
        console.log(res.data)
        this.clientes=res.data
        this.$q.loading.hide()
      })
    },
  }
}
</script>

<style scoped>

</style>
