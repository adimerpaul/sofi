<template>
<q-page class="q-pa-xs">
<div class="row">
  <div class="col-4">
    <q-input dense outlined v-model="fecha" label="Fecha" />
  </div>
  <div class="col-4 flex flex-center">
    <q-btn color="info" icon="search" label="consulta" @click="misasignaciones" size="xs" />
  </div>
<!--  <div class="col-4 flex flex-center">-->
<!--    <q-btn color="positive" icon="add_circle" label="Asignar" size="xs" @click="dialog_ag=true"/>-->
<!--  </div>-->
  <div class="col-12">
    <q-table dense title="Asignaciones" :columns="columns" :rows="asignaciones" :filter="filter">
      <template v-slot:body-cell-personal="props">
        <q-td :props="props">
          {{props.row.Nombre1.trim()}} {{props.row.Nombre2.trim()}}
        </q-td>
      </template>
      <template v-slot:body-cell-cliente="props">
        <q-td :props="props">
          {{props.row.Nombres.trim()}}
        </q-td>
      </template>
      <template v-slot:body-cell-opciones="props">
        <q-td :props="props">
          <q-btn @click="atender(props.row)"  color="primary" label="atender" icon="shop" size="xs"  />
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
      dialog_ag:false,
      columns:[
        {label:'fecha',name:'fecha',field:'fecha'},
        {label:'personal',name:'personal',field:'personal'},
        {label:'cliente',name:'cliente',field:'cliente'},
        {label:'opciones',name:'opciones',field:'opciones'},
      ],
      fecha:date.formatDate(Date.now(),'YYYY-MM-DD')
    }
  },
  created() {
    this.misasignaciones()
  },
  methods:{
    atender(asignar){
      console.log(asignar)
    },
    misasignaciones(){
      this.$q.loading.show()
      this.$api.post('misasignaciones',{fecha:this.fecha}).then(res=>{
        // console.log(res.data)
        this.asignaciones=res.data
        this.$q.loading.hide()
      })
    },
  }
}
</script>

<style scoped>

</style>
