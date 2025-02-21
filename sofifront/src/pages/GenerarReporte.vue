<template>
<q-page class="q-pa-xs">
<div class="row">
  <div class="col-12">
    <q-form @submit="consultar">
      <div class="row">
        <div class="col-3"><q-input type="date" dense outlined label="fecha" v-model="fecha"/></div>
        <div class="col-3"><q-input type="date" dense outlined label="fecha" v-model="fecha2"/></div>
        <div class="col-3 flex flex-center"><q-btn color="info" icon="search" label="Consultar" type="submit" /></div>
        <div class="col-2 flex flex-center"><q-btn color="green" icon="description" label="Pollo EXCEL" @click="exportPollo" dense/></div>
        <div class="col-2 flex flex-center"><q-btn color="accent" icon="description" label="Cerdo EXCEL" @click="exportCerdo" dense/></div>
        <div class="col-2 flex flex-center"><q-btn color="orange-10" icon="description" label="Embut EXCEL" @click="exportEmbutido" dense/></div>
      </div>
    </q-form>
  </div>
  <div class="col-12">
    <q-table dense title="Pedidos" :columns="columspedido" :rows="personales" :filter="filter">
      <template v-slot:top-right>
        <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
          <template v-slot:append>
            <q-icon name="search" />
          </template>
        </q-input>
      </template>
      <template v-slot:body-cell-excel="props">
        <q-td :props="props">
            <q-btn  color="green"  icon="list" size="xs" label="Excel" @click="generarConsulta(props.row)" />
        </q-td>
      </template>
      <template v-slot:body-cell-vendedor="props">
        <q-td :props="props">
          {{props.row.vendedor}}
        </q-td>
      </template>

    </q-table>
  </div>
<!--  <div class="col-4">-->
<!--    <q-input dense outlined v-model="fecha1" label="Fecha Ini" type="date"/>-->
<!--  </div>-->
<!--    <div class="col-4">-->
<!--    <q-input dense outlined v-model="fecha2" label="Fecha Fin" type="date"/>-->
<!--  </div>-->
<!--  <div class="col-4">-->
<!--        <q-btn style="width: 100%" @click="expedidos" color="red" icon="download" label="importar pedidos"> </q-btn>-->
<!--  </div>-->
</div>
</q-page>
</template>

<script>
import {date} from "quasar";
import xlsx from "json-as-xlsx"

export default {
  name: "Generar",
  data(){
    return{
      url:process.env.API,
      filter:'',
      personales:[],
      pedPollo:[],
      pedCerdo:[],
      pedido:[],
      columspedido:[
        {label:'VENDEDOR',name:'vendedor',field:'vendedor',align:'left'},
        {label:'EXCEL',name:'excel',field:'excel',align:'left'},
      ],
      fecha:date.formatDate(new Date(),'YYYY-MM-DD'),
      fecha1:date.formatDate(new Date(),'YYYY-MM-DD'),
      fecha2:date.formatDate(new Date(),'YYYY-MM-DD'),
    }
  },
  created() {
    this.consultar()
  },
  methods:{
    exportPollo(){
      this.$q.loading.show()

      this.$api.post('reportePollo2',{ini:this.fecha,fin:this.fecha2}).then(res=>{
        if(res.data.length==0)
        {
          this.$q.notify({
            message:'No Ay pedido Pollo',
            color:'red',
            icon:'info'
          })
          return false
        }
        let datacaja = [
  {
    sheet: "Pollo",
    columns: [
      { label: "preventista", value: "preventista" }, // Top level data
      { label: "Cliente", value: "Nombres" }, // Top level data
      { label: "fecha", value: "fecha" }, // Top level data
      { label: "Observaciones", value: "Observaciones" }, // Top level data
      { label: "producto", value: "producto" }, // Top level data
      { label: "cantidad", value: "cantidad" }, // Top level data
      { label: "precio", value: "precio" }, // Top level data
      { label: "pago", value: row=>row.pago=='CONTADO'?'si':'no' }, // Top level data
      { label: "fact", value: "fact" }, // Top level data
      { label: "horario", value: "horario" }, // Top level data
      { label: "comentario", value: "comentario" }, // Top level data
    ],
    content: res.data
  },
    ]

    let settings = {
      fileName: "Pollo Frial" , // Name of the resulting spreadsheet
      extraLength: 5, // A bigger number means that columns will be wider
      writeOptions: {}, // Style options from https://github.com/SheetJS/sheetjs#writing-options
    }

    xlsx(datacaja, settings) // Will download the excel file
  
      })
      this.$q.loading.hide()

    },
    exportCerdo(){
      this.$q.loading.show()

      this.$api.post('reporteCerdoTodo',{ini:this.fecha,fin:this.fecha2}).then(res=>{
        if(res.data.length==0)
        {
          this.$q.notify({
            message:'No Ay pedido Cerdo',
            color:'red',
            icon:'info'
          })
          return false
        }
        let datacaja = [
  {
    
    sheet: "Cerdo",
    columns: [
      { label: "fecha", value: "fecha" }, // Top level data
      { label: "preventista", value: row=>row.Nombre1 + ' ' +row.App1+' '+row.Apm }, // Top level data
      { label: "CI/NIT", value: "Id" }, // Top level data
      { label: "cliente", value: "Nombres" }, // Top level data
      { label: "pfrial", value: "pfrial" }, // Top level data
      { label: "entero", value: "entero" }, // Top level data
      { label: "desmembre", value: "desmembre" }, // Top level data
      { label: "corte", value: "corte" }, // Top level data
      { label: "kilo", value: "kilo" }, // Top level data
      { label: "observaciones", value: "Observaciones" }, // Top level data
      { label: "pago", value: row=>row.pago=='CONTADO'?'si':'no' }, // Top level data
      { label: "fact", value: "fact" }, // Top level data
      { label: "horario", value: "horario" }, // Top level data
      { label: "comentario", value: "comentario" }, // Top level data
    ],
    content: res.data
  },
    ]

    let settings = {
      fileName: "Cerdo Frial" , // Name of the resulting spreadsheet
      extraLength: 5, // A bigger number means that columns will be wider
      writeOptions: {}, // Style options from https://github.com/SheetJS/sheetjs#writing-options
    }

    xlsx(datacaja, settings) // Will download the excel file
  
      })
      this.$q.loading.hide()

    },
    exportEmbutido(){
      this.$q.loading.show()

      this.$api.post('reporteEmbutidoTodo',{ini:this.fecha,fin:this.fecha2}).then(res=>{
        if(res.data.length==0)
        {
          this.$q.notify({
            message:'No Ay pedido Embutido',
            color:'red',
            icon:'info'
          })
          return false
        }
        let datacaja = [
  {
    sheet: "Embutido",
    columns: [
      { label: "fecha", value: "fecha" }, // Top level data
      { label: "preventista", value: row=>row.Nombre1 + ' ' +row.App1+' '+row.Apm }, // Top level data
      { label: "CI/NIT", value: "Id" }, // Top level data
      { label: "cliente", value: "Nombres" }, // Top level data
      { label: "NroPed", value: "NroPed" }, // Top level data
      { label: "cod_prod", value: "cod_prod" }, // Top level data
      { label: "Cant", value: "Cant" }, // Top level data
      { label: "Producto", value: "Producto" }, // Top level data
      { label: "precio", value: "precio" }, // Top level data
      { label: "observaciones", value: "Observaciones" }, // Top level data
      { label: "pago", value: row=>row.pago=='CONTADO'?'si':'no' }, // Top level data
      { label: "fact", value: "fact" }, // Top level data
      { label: "horario", value: "horario" }, // Top level data
      { label: "comentario", value: "comentario" }, // Top level data
    ],
    content: res.data
  },
    ]

    let settings = {
      fileName: "Embutidos" , // Name of the resulting spreadsheet
      extraLength: 5, // A bigger number means that columns will be wider
      writeOptions: {}, // Style options from https://github.com/SheetJS/sheetjs#writing-options
    }

    xlsx(datacaja, settings) // Will download the excel file
  
      })
      this.$q.loading.hide()

    },
    consultar(){
      this.$q.loading.show()
      this.$api.post('listregistro',{ini:this.fecha,fin:this.fecha2}).then(res=>{
        // console.log(res.data)
        this.personales=[]
        res.data.forEach(r=>{
          r.vendedor=r.Nombre1+' '+r.App1
          this.personales.push(r)
        })
        this.$q.loading.hide()
      })
    },

    generarConsulta(per){
      this.getCerdo(per)
      this.getEmbutido(per)
      this.getPollo(per)
      let datacaja = [
  {
    sheet: "Cerdo",
    columns: [
      { label: "fecha", value: "fecha" }, // Top level data
      { label: "CI/NIT", value: "Id" }, // Top level data
      { label: "cliente", value: "Nombres" }, // Top level data
      { label: "pfrial", value: "pfrial" }, // Top level data
      { label: "entero", value: "entero" }, // Top level data
      { label: "desmembre", value: "desmembre" }, // Top level data
      { label: "corte", value: "corte" }, // Top level data
      { label: "kilo", value: "kilo" }, // Top level data
      { label: "observaciones", value: "Observaciones" }, // Top level data
      { label: "pago", value: row=>row.pago=='CONTADO'?'si':'no' }, // Top level data
      { label: "fact", value: "fact" }, // Top level data
      { label: "horario", value: "horario" }, // Top level data
      { label: "comentario", value: "comentario" }, // Top level data
    ],
    content: this.pedCerdo
  },
  {
    sheet: "Embutido",
    columns: [
      { label: "fecha", value: "fecha" }, // Top level data
      { label: "CI/NIT", value: "Id" }, // Top level data
      { label: "cliente", value: "Nombres" }, // Top level data
      { label: "NroPed", value: "NroPed" }, // Top level data
      { label: "cod_prod", value: "cod_prod" }, // Top level data
      { label: "Cant", value: "Cant" }, // Top level data
      { label: "Producto", value: "Producto" }, // Top level data
      { label: "precio", value: "precio" }, // Top level data
      { label: "observaciones", value: "Observaciones" }, // Top level data
      { label: "pago", value: row=>row.pago=='CONTADO'?'si':'no' }, // Top level data
      { label: "fact", value: "fact" }, // Top level data
      { label: "horario", value: "horario" }, // Top level data
      { label: "comentario", value: "comentario" }, // Top level data
       
    ],
    content: this.pedido
  },
  {
    sheet: "Pollo",
    columns: [
      { label: "fecha", value: "fecha" }, // Top level data
      { label: "CI/NIT", value: "Id" }, // Top level data
      { label: "cliente", value: "Nombres" }, // Top level data
      { label: "cbrasa5", value: "cbrasa5" }, // Top level data
      { label: "ubrasa5", value: "ubrasa5" }, // Top level data
      { label: "cbrasa6", value: "cbrasa6" }, // Top level data
      { label: "ubrasa6", value: "ubrasa6" }, // Top level data
      { label: "c104", value: "c104" }, // Top level data
      { label: "u104", value: "u104" }, // Top level data
      { label: "c105", value: "c105" }, // Top level data
      { label: "u105", value: "u105" }, // Top level data
      { label: "c106", value: "c106" }, // Top level data
      { label: "u106", value: "u106" }, // Top level data
      { label: "c107", value: "c107" }, // Top level data
      { label: "u107", value: "u107" }, // Top level data
      { label: "c108", value: "c108" }, // Top level data
      { label: "u108", value: "u108" }, // Top level data
      { label: "c109", value: "c109" }, // Top level data
      { label: "u109", value: "u109" }, // Top level data
      { label: "rango p", value: "rango" }, // Top level data
      { label: "ala", value: row=>row.ala==null?'':row.ala+' '+row.unidala }, // Top level data
      { label: "cadera", value: row=>row.cadera==null?'':row.cadera+' '+row.unidcadera  }, // Top level data
      { label: "pecho", value: row=>row.pecho==null?'':row.pecho+' '+row.unidpecho  }, // Top level data
      { label: "pie", value: row=>row.pie==null?'':row.pie+' '+row.unidpie  }, // Top level data
      { label: "filete", value: row=>row.filete==null?'':row.filete+' '+row.unidfilete  }, // Top level data
      { label: "cuello", value: row=>row.cuello==null?'':row.cuello+' '+row.unidcuello  }, // Top level data
      { label: "hueso", value: row=>row.hueso==null?'':row.hueso+' '+row.unidhueso  }, // Top level data
      { label: "menu", value: row=>row.menu==null?'':row.menu+' '+row.unidmenu  }, // Top level data
      { label: "bs", value: "bs" }, // Top level data
      { label: "bs2", value: "bs2" }, // Top level data
      { label: "pago", value: row=>row.pago=='CONTADO'?'si':'no' }, // Top level data
      { label: "observaciones", value: "Observaciones" }, // Top level data
      { label: "fact", value: "fact" }, // Top level data
      { label: "horario", value: "horario" }, // Top level data
      { label: "comentario", value: "comentario" }, // Top level data
    ],
    content: this.pedPollo
  },
    ]

    let settings = {
      fileName: "Embutido - Vendedor " + per.vendedor, // Name of the resulting spreadsheet
      extraLength: 5, // A bigger number means that columns will be wider
      writeOptions: {}, // Style options from https://github.com/SheetJS/sheetjs#writing-options
    }

    xlsx(datacaja, settings) // Will download the excel file
  
    },

    getCerdo(per){
      this.$api.post('reporteCerdo',{ini:this.fecha,fin:this.fecha2,codaut:per.CodAut}).then(res=>{
        this.pedCerdo=res.data
        if(res.data.length==0)
        {
          this.$q.notify({
            message:'No Ay pedido Cerdo',
            color:'red',
            icon:'info'
          })
          return false
        }
      })

    },

    getEmbutido(per){
      this.$api.post('reporteEmbutido',{ini:this.fecha,fin:this.fecha2,codaut:per.CodAut}).then(res=>{
        this.pedido=res.data
        if(res.data.length==0)
        {
          this.$q.notify({
            message:'No Ay pedido ',
            color:'red',
            icon:'info'
          })
          return false
        }
      })
    },

    getPollo(per){
      this.$api.post('reportePollo',{ini:this.fecha,fin:this.fecha2,codaut:per.CodAut}).then(res=>{
        console.log(res.data)
        this.pedPollo=res.data
        if(res.data.length==0)
        {
          this.$q.notify({
            message:'No Ay pedido Pollo ',
            color:'red',
            icon:'info'
          })
          return false
        }
  })
      },
 
  }
}
</script>

<style scoped>

</style>
