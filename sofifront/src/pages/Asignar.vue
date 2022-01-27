<template>
<q-page class="q-pa-xs">
<div class="row">
  <div class="col-4">
    <q-input dense outlined v-model="fecha" label="Fecha" />
  </div>
  <div class="col-4 flex flex-center">
    <q-btn color="info" icon="search" label="consulta" @click="misasignaciones" size="xs" />
  </div>
  <div class="col-4 flex flex-center">
    <q-btn color="positive" icon="add_circle" label="Asignar" size="xs" @click="dialog_ag=true;listado=[]"/>
  </div>
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
          <q-btn @click="eliminar(props.row)" flat color="negative" label="eliminar" icon="delete" size="xs"  />
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
      <q-dialog v-model="dialog_ag" >
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Asignar</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <q-input outlined label="Fecha" v-model="fecha2"/>
                      <q-select outlined v-model="user"
                        use-input
              input-debounce="0"
              :options="options2"
              @filter="filterFn2" label="Personal" />
                      <div class="row">
                        <div class="col-10">
                                    <q-select outlined v-model="cliente"
                        use-input
              input-debounce="0"
              :options="options"
              @filter="filterFn" label="Clientes" />
                        </div>
                        <div class="col-2">
                          <q-btn color="positive" icon="add" @click="listcl" />
                        </div>
                      </div>
          <div>
            <ul>
              <li v-for="(l,index) in listado " :key="index">{{l.label}}</li>

            </ul>
          </div>

        </q-card-section>
        <q-card-actions align="right" class="text-primary">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn flat label="Asignar"  @click="masignar"/>
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
    this.misclientes()
    this.misusuarios()
  },
  methods:{
    masignar(){
      if(this.user.id==undefined)
       return false
      if(this.listado.lenght == 0)
        return false
      if(this.fecha==undefined)
        return false
      console.log(this.listado)
      this.$api.post('asignar',{fecha:this.fecha,user_id:this.user.id,clientes:this.listado}).then(res=>{
        console.log(res.data)
      })

    },
    listcl(){
      console.log(this.cliente)
      if(this.cliente.id!=undefined)
      var validar=false
      this.listado.forEach(el => {
          if(this.cliente.cliente.Cod_Aut==el.cliente.Cod_Aut)
          validar=true
      });
      if(!validar)
      this.listado.push(this.cliente);
    },
    misusuarios(){
      this.usuarios=[]
      this.$api.get('user').then(res=>{
        // console.log(res.data)
        res.data.forEach(element => {
          this.usuarios.push({label:element.ci+' '+element.Nombre1+' '+element.Nombre2+' '+element.App1+' '+element.Apm,id:element.CodAut})
        });
      })

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
            filterFn2 (val, update) {
      if (val === '') {
        update(() => {
          this.options2 = this.usuarios
          // with Quasar v1.7.4+
          // here you have access to "ref" which
          // is the Vue reference of the QSelect
        })
        return
      }
      update(() => {
        const needle = val.toLowerCase()
        this.options2 = this.usuarios.filter(v => v.label.toLowerCase().indexOf(needle) > -1)
      })
    },
    misclientes(){
      this.clientes=[]
      this.$api.get('cliente').then(res=>{
        // console.log(res.data)
        res.data.forEach(element => {
          this.clientes.push({label:element.Id+' '+element.Nombres,cliente:element})
        });
      })

    },
    misasignaciones(){
      this.$q.loading.show()
      this.$api.get('asignar/'+this.fecha).then(res=>{
        // console.log(res.data)
        this.asignaciones=res.data
        this.$q.loading.hide()
      })
    },
    eliminar(asignacion){
      if (confirm('Seguro de eliminar?')){
        this.$q.loading.show()
        this.$api.delete('asignar/'+asignacion.id).then(res=>{
          // console.log(res.data)
          // this.asignaciones=res.data
          this.misasignaciones()
        })
      }

    }
  }
}
</script>

<style scoped>

</style>
