<template>
<q-page class="q-pa-xs">
  <div class="row">
    <div class="col-6">
      <q-select @update:model-value="consula" dense outlined label="Vendero" :options="usuarios" v-model="user" />
    </div>
    <div class="col-6">
      <q-input @change="consula(user)" v-model="fecha" label="fecha" dense outlined type="date" />
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
<!--      <pre>{{datos}}</pre>-->
    </div>
  </div>
</q-page>
</template>

<script>
import {date} from "quasar";

export default {
  name: `Monitoreo`,
  data(){
    return{
      usuarios:[],
      fecha:date.formatDate(new Date(),'YYYY-MM-DD'),
      user:{},
      pedido:0,
      retorno:0,
      nopedido:0,
    }
  },
  created(){
    this.$q.loading.show()
    this.misuser()
  },
  methods:{
    consula(user){
      this.pedido=0
      this.retorno=0
      this.nopedido=0
      this.$q.loading.show()
      // console.log()
      this.$api.post('misvisitas',{
        id:user.CodAut,
        fecha:this.fecha
      }).then(res=>{
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
        // this.datos=res.data

        // console.log(res.data)
        this.$q.loading.hide()
      })
    },
    misuser(){
      this.usuarios=[{CodAut:0,label:'Todos'}]
      // this.$q.loading.show()
      this.$api.get('listapersonal').then(res=>{
        // this.misclientes()
        // console.log(res.data)
        // this.$q.loading.hide()
        res.data.forEach(r => {
          // console.log(r)
          r.label=r.Nombre1+' '+r.App1
          this.usuarios.push(r)
        });
        // console.log(this.usuarios)
        this.user=this.usuarios[0]
        this.consula(this.user)
      })
    },
  }
}
</script>

<style scoped>

</style>
