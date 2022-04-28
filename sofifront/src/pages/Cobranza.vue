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

      <q-dialog full-width full-height v-model="dialog_cc" >
      <q-card >
        <q-card-section>
          <div class="text-h6">Cuentas por Cobrar</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
        <div class="row">
          <div class="col-6"><div class="text-subtitle2">CINIT:</div> {{ccliente.Id}}</div>
          <div class="col-6"><div class="text-subtitle2">Nombre:</div> {{ccliente.Nombres}}</div>
        </div>
<!--        <div><q-input outlined v-model="monto" type="number" step="0.01" label="Monto" /></div>-->
<!--        <div>{{totalpago}}</div>-->
        </q-card-section>
        <q-card-section class="q-pt-none">
        <q-table dense :rows="cxcobrar" :columns="columns2" row-key="name" >
        <template v-slot:body-cell-monto="props">
  <!--        <q-tr :props="props">-->

            <q-td key="monto" :props="props" >
              <q-input outlined style="width: 5em" label="monto" dense type="number" step="0.01" v-model="props.row.pago"
                       :rules="[val => ((val<=parseFloat(props.row.saldo)&&val>=0) || val=='') || 'No debe exceder',]" lazy-rules/>
            </q-td>
  <!--        </q-tr>-->
        </template>
        <template v-slot:body-cell-boleta="props">
            <q-td key="boleta" :props="props" >
              <q-input outlined style="width: 5em" label="boleta" dense type="text" v-model="props.row.boleta"
                       :rules="[val => (parseFloat(props.row.saldo)>0 )  || 'Tiene que tener boleta',]" lazy-rules/>
            </q-td>
        </template>
        </q-table>
          <q-badge class="text-center full-width text-subtitle2" style="text-align: center">{{totalpago}} Bs.</q-badge>

        </q-card-section>

        <q-card-actions align="right" class="text-primary">
          <q-btn icon="delete" label="Cancel" color="negative" v-close-popup />
          <q-btn icon="send" label="Guardar" color="positive" @click="registrar"/>
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
        {label:'opciones',name:'opciones',field:'opciones'},
        {label:'CINIT',name:'CINIT',field:'Id',align:'left'},
        {label:'Nombres',name:'Nombres',field:'Nombres',align:'left'},
        {label:'deuda',name:'deuda',field:'deuda'},

      ],
      columns2:[
        {label:'fecha',name:'fecha',field:'FechaEntreg'},
        {label:'comanda',name:'comanda',field:'comanda'},
        {label:'saldo ',name:'saldo',field:'saldo',align:'right'},
        {label:'monto',name:'monto',field:'monto',align:'center'},
        {label:'boleta',name:'boleta',field:'boleta',align:'center'},

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
      /*if(parseFloat(this.monto) != parseFloat(this.totalpago) && parseFloat(this.monto)>0)
      {
        this.$q.notify({
            message:'Cantidad y Monto de ser igual',
            color:'red',
            icon:'error'
          })
          return false
      }*/
      this.$q.loading.show()
      this.cxcdatos=[]
      this.cxcobrar.forEach(element => {
          if(element.pago>0)
          this.cxcdatos.push(element);
      });
      this.$api.post('insertcobro',{pagos:this.cxcdatos}).then(res=>{
        this.$q.loading.hide()
        this.dialog_cc=false;
          console.log(res.data)
        this.$q.notify({
            message:'Guardado Correctamente',
            color:'green',
            icon:'check'
          })
      })

    },
    ccobrar(cliente){
      this.ccliente=cliente;
      this.cxcobrar=[]
      this.$q.loading.show()
      this.$api.post('cxcobrar/'+cliente.Id).then(res=>{
        console.log(res.data);
        res.data.forEach(element => {
          element.pago=0;
        });
        this.$q.loading.hide()
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

      // this.clientes=[]
      this.$q.loading.show()
      this.$api.get('listdeudores').then(res=>{
        console.log(res.data)
        this.$q.loading.hide()
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
