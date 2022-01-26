<template>
<q-page class="q-pa-xs">
<div class="row">
  <div class="col-4">
    <q-input dense outlined v-model="fecha" label="Fecha" />
  </div>
  <div class="col-4 flex flex-center">
    <q-btn color="info" icon="search" label="consulta" size="xs" />
  </div>
  <div class="col-4 flex flex-center">
    <q-btn color="positive" icon="add_circle" label="Asignar" size="xs" />
  </div>
  <div class="col-12">
    <q-table dense title="Asignaciones" :columns="columns" :rows="asignaciones">
      <template v-slot:body-cell-personal="props">
        <q-td :props="props">
          {{props.row.Nombre1.trim()}} {{props.row.Nombre2.trim()}}
        </q-td>
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
      asignaciones:[],
      columns:[
        {label:'fecha',name:'fecha',field:'fecha'},
        {label:'personal',name:'personal',field:'personal'},
        {label:'cliente',name:'cliente',field:'cliente'},
      ],
      fecha:date.formatDate(Date.now(),'YYYY-MM-DD')
    }
  },
  created() {
    this.misasignaciones()
  },
  methods:{
    misasignaciones(){
      this.$api.get('asignar/'+this.fecha).then(res=>{
        console.log(res.data)
        this.asignaciones=res.data
      })
    }
  }
}
</script>

<style scoped>

</style>
