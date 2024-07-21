<template>
<q-page class="q-pa-xs">
  <div class="col-6">
    <q-input @change="consulta(); reporte();" v-model="fecha" label="fecha" dense outlined type="date" />
  </div>
  <div class="row">

    <div class="col-12">
<!--      <pre>{{datos}}</pre>-->
    <q-table title="ENTREGAS" :rows="listado" :columns="columns" row-key="name" lang="productos">
      <template v-slot:body="props">
        <q-tr :props="props" :class="props.row.estado=='ENTREGADO'?'bg-green':props.row.estado=='NO ENTREGADO'?'bg-amber':props.row.estado=='RECHAZADO'?'bg-red':''">
          <q-td
            v-for="col in props.cols"
            :key="col.name"
            :props="props"
          >
            {{ col.value }}

          </q-td >
          <q-td auto-width >
            <q-btn size="sm"
                   :color="props.expand ? 'primary' : 'secondary'"
                   :label="props.expand ? 'Ocul' : 'Ver'"
                   no-caps dense @click="props.expand = !props.expand" :icon="props.expand ? 'visibility_off' : 'visibility'"/>
          </q-td>
          

        </q-tr>
        
        <q-tr v-show="props.expand" :props="props">
          <q-td colspan="100%">
            <div class="text-left" v-for="r in props.row.detalle " :key="r"> <b>Codigo:</b> {{r.cod_prod}} <b>Producto:</b> {{r.Producto}} <b>Cantidad:</b> {{r.cant}} </div>
          </q-td>
        </q-tr>
      </template>
      </q-table>
      <div>Total Contado: {{totalEnt}} Bs.</div>
    </div>
    <div>
      <q-table title="PRESTAMOS" :rows="listreporte" :columns="colreport" row-key="name" />
      
    </div>
  </div>
</q-page>
</template>

<script>
import {date} from "quasar";
export default {
  name: 'despachoPage',
  data(){
    return{
      fecha:date.formatDate(new Date(),'YYYY-MM-DD'),
      entrega:{},
      fechareporte:{ini:date.formatDate(new Date(),'YYYY-MM-DD'),fin:date.formatDate(new Date(),'YYYY-MM-DD')},
       columns : [
        { name: 'CINIT',label: 'CINIT',align: 'left',field: 'CINIT',   sortable: true},
        { name: 'CLIENTE', label: 'CLIENTE',align: 'left',field: 'Nombres',sortable: true},
  { name: 'comanda', align: 'center', label: 'COMANDA', field: 'comanda', sortable: true },
  { name: 'Importe', align: 'center', label: 'IMPORTE', field: 'Importe', sortable: true },
  { name: 'Placa', align: 'center', label: 'PLACA', field: 'placa', sortable: true },
  { name: 'despachador', align: 'center', label: 'DESPACHADOR', field: 'despachador', sortable: true },
  { name: 'Tipago', align: 'center', label: 'TIPO PAGO', field: 'Tipago', sortable: true },
  { name: 'observacion', label: 'OBSERVACION', field: 'observacion' },],
  listreporte:[],
  colreport:[
    { name: 'fecha', label: 'fecha', field: 'fecha' },
    { name: 'cinit', label: 'cinit', field: 'cinit' },
    { name: 'nombres', label: 'Nombres', field: 'Nombres' },
    { name: 'prestado', label: 'prestado', field: 'prestado' },
    { name: 'devuelto', label: 'devuelto', field: 'devuelto' },

  ]

    }
  },
  created(){
    this.consulta()
    this.reporte()
  },
  methods:{
    reporte(){
      this.$q.loading.show()
      this.$api.post('rePrestamo',{fecha:this.fecha,placa:this.$store.getters['login/user'].placa}).then(res=>{
        this.listreporte=res.data
        this.$q.loading.hide()
      })

    },
    consulta(){
      // console.log()
      this.listado=[]
      this.$q.loading.show()

        this.$api.post('reporteDes',{fecha:this.fecha }).then(res=>{
          console.log(res.data)
          this.listado=res.data;

          this.$q.loading.hide()
        })
    },

  },
  computed:{
    totalEnt(){
      let res=0
      this.listado.forEach(r => {
         if(r.Tipago=='CONTADO'){
            res+=parseFloat(r.Importe)
         }
      })
      return res.toFixed(2)
    }
  }
}
</script>

<style scoped>

</style>
