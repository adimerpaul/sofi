<template>
<q-page class="q-pa-xs">
<div class="row">

  <div class="col-12">
    <q-table :rows-per-page-options="[20,50,100,0]" dense title="CLIENTES" :columns="columns" :rows="clientes" :filter="filter">
      <template v-slot:body-cell-opcion="props">
        <q-td :props="props">
          <q-btn @click="cambiar(props.row)"  color="teal"  icon="check" size="xs"  />
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
    <q-dialog v-model="dialog_mod" persistent>
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">CLIENTE: {{cliente.Nombres}}</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <div class="text-h6">Seleccione Preventista</div>
          <q-select aria-label="Personal" :options="usuarios" v-model="user"/>
        </q-card-section>

        <q-card-actions align="right" class="text-primary">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn flat label="Modificar" @click="modificar" />
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
      dialog_mod:false,
      asignaciones:[],
      asignar:{},
      cliente:{},
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
        {label:'CI/NIT',name:'Id',field:'Id',align:'left'},
        {label:'NOMBRES',name:'nombres',field:'Nombres',align:'left'},
        {label:'telefono',name:'Telf',field:'Telf',align:'left'},
        {label:'Direccion',name:'Direccion',field:'Direccion',align:'left'},
        {label:'NOMBRES',name:'nombres',field:row=>row.Nombre1 + ' ' + row.App1,align:'left'},
        {label:'opcion',name:'opcion',field:'opcion'},

      ],
      fecha:date.formatDate(Date.now(),'YYYY-MM-DD')
    }
  },
  created() {

    this.misclientes()
    this.misuser()
  },
  methods:{
      modificar(){
          
      this.$q.loading.show()
      this.$api.post('modprevent',{vendedor:this.user.ci,cliente_id:this.cliente.Cod_Aut}).then(res=>{

          this.dialog_mod=false
          this.misclientes()
      })
      },
      cambiar(cliente){
          this.cliente=cliente
          this.dialog_mod=true
      },
     misuser(){
      this.usuarios=[]
      this.$q.loading.show()
      this.$api.get('listapersonal').then(res=>{
         console.log(res.data)
        this.$q.loading.hide()
        res.data.forEach(r => {
            r.label=r.Nombre1+' '+r.App1
            this.usuarios.push(r)
        });
        console.log(this.usuarios)
        this.user=this.usuarios[0]
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

    misclientes(){
      this.clientes=[]
      this.$q.loading.show()
      this.$api.get('listaclientes').then(res=>{
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
