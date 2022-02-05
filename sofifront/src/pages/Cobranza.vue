<template>
<q-page class="q-pa-xs">
<div class="row">
  <div class="text-h6">
  CUENTAS POR COBRAR
  </div>
  <div class="col-12">
    <q-table dense title="Clientes por cobrar" :columns="columns" :rows="clientes" :filter="filter">
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
          <q-btn @click="ccobrar(props.row)" color="accent" label="Cuentas x Cobrar" icon="local_atm" size="xs"  />
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

      <q-dialog v-model="dialog_cc" >
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Cuentas por Cobrar</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
        <div class="row"><div class="col-4">CINIT: {{ccliente.Id}}</div><div>Nombre: {{ccliente.Nombres}}</div></div>
        <div><q-input outlined v-model="monto" type="number" step="0.01" label="Monto" /></div>
        <div>{{totalpago}}</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <div class="row" v-for="(r,index) in cxcobrar" :key="index">
          <div class="col-4">Comanda: {{r.comanda}}</div>
          <div class="col-4">Saldo: {{r.saldo}}</div>
          <div class="col-4"> <q-input dense outlined v-model="r.pago"  type="number" step="0.01"      
          :rules="[
           val => ((val<=parseFloat(r.saldo)&&val>=0) || val=='') || 'No debe exceder',
           
        ]"
      lazy-rules/></div>           </div>
        </q-card-section>

        <q-card-actions align="right" class="text-primary">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn flat label="Guardar" @click="registrar"/>
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
      ccliente:{},
      cxcobrar:[],
      cxcdatos:[],
      monto:0,
      dialog_cc:false,
      columns:[
        {label:'CINIT',name:'CINIT',field:'Id'},
        {label:'Nombres',name:'Nombres',field:'Nombres'},
        {label:'opciones',name:'opciones',field:'opciones'},
      ],
      fecha:date.formatDate(Date.now(),'YYYY-MM-DD')
    }
  },
  created() {
    this.misclientes()
    this.misusuarios()
  },
  methods:{
    registrar(){
      if(parseFloat(this.monto) != parseFloat(this.totalpago) && parseFloat(this.monto)>0)
      {
        this.$q.notify({
            message:'Cantidad y Monto de ser igual',
            color:'red',
            icon:'error'
          })
          return false
      }
      this.cxcdatos=[]
      this.cxcobrar.forEach(element => {
          if(element.pago>0)
          this.cxcdatos.push(element);
      });
      this.$api.post('insertcobro',{pagos:this.cxcdatos}).then(res=>{
          console.log(res.data)
      })

    },
    ccobrar(cliente){
      this.ccliente=cliente;
      this.cxcobrar=[]
      this.$api.post('cxcobrar/'+cliente.Id).then(res=>{
        console.log(res.data);
        res.data.forEach(element => {
          element.pago=0;
        });
        this.cxcobrar=res.data;
        this.dialog_cc=true
      })

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

    misclientes(){
      this.clientes=[]
      this.$api.get('listdeudores').then(res=>{
        // console.log(res.data)
          this.clientes=res.data
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
  },
  computed:{
    totalpago(){
      let suma=0
      this.cxcobrar.forEach(element => {
        if(element.pago!=undefined || element.pago!='')
        suma=suma+parseFloat(element.pago)
      });
      return suma;
    }
  }
}
</script>

<style scoped>

</style>
