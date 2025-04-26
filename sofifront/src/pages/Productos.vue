<template>
    <q-page class="q-pa-xs">
    <div class="row">

      <div class="col-12">
        <q-table dense title="PRODUCTOS" :columns="columns" :rows="productos" :filter="filter">

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
          productos:[],
          fecha2:date.formatDate(Date.now(),'YYYY-MM-DD'),
          user:{label:''},
          clientes:[],
          usuarios:[],
          options:[],
          options2:[],
          listado:[],
          ccliente:{},
          cxcobrar:[],
          cxcdatos:[],
          monto:0,
          dialog_cc:false,
          columns:[
            {label:'cod_prod',name:'cod_prod',field:'cod_prod',sortable:true},
            {label:'Producto',name:'Producto',field:'Producto',align:'left',sortable:true},
            {label:'Precio',name:'Precio',field:'Precio',align:'left',sortable:true},
            {label:'Precio_Costo',name:'Precio_Costo',field:'Precio_Costo',align:'left',sortable:true},
            {label:'Precio3',name:'Precio3',field:'Precio3',align:'left',sortable:true},
            {label:'Precio4',name:'Precio4',field:'Precio4',align:'left',sortable:true},
            {label:'Precio5',name:'Precio5',field:'Precio5',align:'left',sortable:true},
            {label:'Precio6',name:'Precio6',field:'Precio6',align:'left',sortable:true},
            {label:'Precio7',name:'Precio7',field:'Precio7',align:'left',sortable:true},
            {label:'Precio8',name:'Precio8',field:'Precio8',align:'left',sortable:true},
            {label:'Precio9',name:'Precio9',field:'Precio9',align:'left',sortable:true},
            {label:'Precio10',name:'Precio10',field:'Precio10',align:'left',sortable:true},
            {label:'Precio11',name:'Precio11',field:'Precio11',align:'left',sortable:true},
            {label:'Precio12',name:'Precio12',field:'Precio12',align:'left',sortable:true},
            {label:'Precio13',name:'Precio13',field:'Precio13',align:'left',sortable:true},
            {label:'PreCosto',name:'PreCosto',field:'PreCosto',align:'left',sortable:true},
    
          ],

          fecha:date.formatDate(Date.now(),'YYYY-MM-DD')
        }
      },
      created() {
        this.misproductos()
      },
      methods:{
   
        misproductos(){
          // this.clientes=[]
          this.$q.loading.show()
          this.$api.get('verProducto').then(res=>{
            console.log(res.data)
            this.$q.loading.hide()
              this.productos=res.data
          })
    
        },

      },
      computed:{

      }
    }
    </script>
    
    <style scoped>
    
    </style>
    