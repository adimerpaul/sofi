<template>
<q-page class="q-pa-xs">
<div class="row">
  <div class="col-6 flex flex-center">
    <q-btn color="teal" icon="check" label="TODOS PENDIENTE" @click="quitar"  />
  </div>
  <div class="col-6 flex flex-center">
    <q-btn color="warning" icon="list" label="ACTUALIZAR HAB" @click="agregar"  />
  </div>
  <div class="col-12">
    <q-table :rows-per-page-options="[20,50,100,0]" dense title="CLIENTES" :columns="columns" :rows="clientes" :filter="filter">
      <template v-slot:body-cell-opciones="props">
        <q-td :props="props">
          <q-btn @click="hablitar(props.row)"  color="primary"  icon="rule" size="xs"  />
        </q-td>
      </template>
      <template v-slot:body-cell-venta="props">
        <q-td :props="props">
          <q-badge @click="hablitar(props.row)" :color="props.row.venta=='ACTIVO'?'positive':'negative'">{{props.row.venta}}</q-badge>
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

</div>
</q-page>
</template>

<script>
import {date} from "quasar";

export default {
  data(){
    return{
      filter:'',
      asignaciones:[],
      asignar:{},
      cliente:{label:''},
      fecha2:date.formatDate(Date.now(),'YYYY-MM-DD'),
      user:{label:''},
      clientes:[],
      usuarios:[],
      options:[],
      options2:[],
      listado:[],
      dialog_ag:false,
      columns:[
        // {label:'OPCIONES',name:'opciones',field:'opciones'},
        {label:'ESTADO',name:'venta',field:'venta'},
        {label:'DEUDA',name:'totdeuda',field:'totdeuda'},
        {label:'NOMBRES',name:'nombres',field:'Nombres',align:'left'},
        {label:'CI',name:'ci',field:'Id'},

      ],
      fecha:date.formatDate(Date.now(),'YYYY-MM-DD')
    }
  },
  created() {
    if(this.$store.getters['login/user'].ci!='7329536'){
      this.$router.push('/');
    }
    this.misclientes()
  },
  methods:{
      quitar(){
        this.$api.post('bloquear').then(res=>{
            this.misclientes()
        })

      },
      agregar(){
        this.$api.post('desbloq2').then(res=>{
            this.misclientes()
        })
      },
      hablitar(cliente){
        // console.log(cliente)
          // console.log(cliente)
          this.$api.post('desbloquear',cliente).then(res=>{
            // this.misclientes()
          })
        if (cliente.venta=='ACTIVO'){
          cliente.venta='INACTIVO'
        }else{
          cliente.venta='ACTIVO'
        }

      },
        filterFn (val, update) {
      if (val === '') {
        update(() => {
          this.options = this.clientes
          // with Quasar v1.7.4+
          // here you have access to "ref" which
          // is the Vue reference of the QSelect
        })
        return
      }
      update(() => {
        const needle = val.toLowerCase()
        this.options = this.clientes.filter(v => v.label.toLowerCase().indexOf(needle) > -1)
      })
    },

    misclientes(){
      this.clientes=[]
      this.$q.loading.show()
      this.$api.post('todosclientes').then(res=>{
        // console.log(res.data)
        this.$q.loading.hide()
        this.clientes=res.data
      })

    },


  }
}
</script>

<style scoped>

</style>
