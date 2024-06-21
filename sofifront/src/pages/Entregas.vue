<template>
<q-page class="q-pa-xs">
  <div class="col-6">
    <q-input @change="consula()" v-model="fecha" label="fecha" dense outlined type="date" />
  </div>

    <div class="col-12">
      <q-table title="Entregas Pedidos" :rows="resumen" :columns="columns2" row-key="name" >
        <template v-slot:body-cell-op="props" >
          <q-td :props="props">
             <q-btn color="green" icon="download" dense @click="excel(props.row)"/>
             <q-btn color="info" icon="print" dense @click="impresion(props.row)"/>

          </q-td>
        </template>
        <template v-slot:body-cell-entreg="props" >
          <q-td :props="props">
            <div class="q-pa-xs">
        <q-linear-progress size="16px" rounded :value="calcular(props.row.entreg,props.row.total)" :color="'green-7'" class="full-width ">
          <div class="absolute-full flex flex-center">
            <q-badge color="white" :text-color="'green-7'" :label="props.row.entreg" />
          </div>
        </q-linear-progress>
      </div>
            <div class="q-pa-xs">
              <q-linear-progress size="16px" rounded :value="calcular(props.row.noentreg,props.row.total)" :color="'yellow-10'" class="full-width q-pa-xs">
          <div class="absolute-full flex flex-center">
            <q-badge color="white" :text-color="'yellow-10'" :label="props.row.noentreg" />
          </div>
        </q-linear-progress>
          </div>
          <div class="q-pa-xs">
              <q-linear-progress size="16px" rounded :value="calcular(props.row.rechazado,props.row.total)" :color="'red-7'" class="full-width q-pa-xs">
          <div class="absolute-full flex flex-center">
            <q-badge color="white" :text-color="'red-7'" :label="props.row.rechazado" />
          </div>
        </q-linear-progress>
          </div>
        </q-td>
        </template>
        </q-table>
    </div>
    <div class="row">
      <div class="col-md-6 col-xs-12">
        <div style="height: 350px; width: 100%;">
          <l-map
            @ready="onReady"
            @locationfound="onLocationFound"
            v-model="zoom"
            :zoom="zoom"
            :center="center"
            @move="log('move')"
          >
            <LTileLayer
              url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            ></LTileLayer>
        <!--    @click="clickopciones(c)"-->
            <l-marker v-for="(c,i) in clientes" :key="c.Cod_Aut" :lat-lng="[c.Latitud, c.longitud]" @click="getPedidos(c)" >
              <l-tooltip :content="c.Nombres"></l-tooltip>
              <l-icon >
                <q-badge
                :class="c.estado=='ENTREGADO'?'bg-green text-italic':c.estado=='NO ENTREGADO'?'bg-yellow text-italic':c.estado=='RECHAZADO'?'bg-red text-italic':'bg-blue'"
                style="padding: 2px"
        
                >{{i+1}}
                </q-badge>
              </l-icon>
            </l-marker>
          </l-map>
          </div>
      </div>
      <div class="col-md-6 col-xs-12">
        <q-table title="COMANDAS" :rows="pedidos" :columns="colPed"  dense lang="productos" row-key="name">

          <template v-slot:body="props">
            <q-tr :props="props" :class="props.row.estado=='ENTREGADO'?'bg-green':props.row.estado=='NO ENTREGADO'?'bg-amber':props.row.estado=='RECHAZADO'?'bg-red':''">

              <q-td
                v-for="col in props.cols"
                :key="col.name"
                :props="props"
              >
                {{ col.value }}

              </q-td >
              <q-td auto-width>
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
      </div>
    </div>
    <div class="col-12">
<!--      <pre>{{datos}}</pre>-->
      <q-table title="LISTA ENTREGA COMANDA" :rows="listado"  row-key="name" :filter="filter">
        <template v-slot:top-right>
          <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
            <template v-slot:append>
              <q-icon name="search" />
            </template>
          </q-input>
        </template>
        </q-table>
      
    </div>
    <div class="col-12">
      <q-table title="Entrega" :rows="rcontable"  row-key="name" />

    </div>
      <div class="col-4 q-pa-xs"><q-input dense outlined v-model="fechareporte.ini" label="Fecha Ini" type="date"/></div>
      <div class="col-4 q-pa-xs"><q-input dense outlined v-model="fechareporte.fin" label="Fecha Fin" type="date"/></div>
      <div class="col-4 q-pa-xs"> <q-btn dense color="green" icon="search" @click="reportEnt"/></div>
      <div class="col-12">
        <q-table title="Reporte Canastillos" :rows="reporte" :columns="colrept" row-key="name" />

      </div>
      <div id="myelement" class="hidden"></div>

</q-page>
</template>

<script>
import {date} from "quasar";
import { Printd } from 'printd'
import {
  LMap,
  LIcon,
  LTileLayer,
  LMarker,
  LControlLayers,
  LTooltip,
  LPopup,
  LPolyline,
  LPolygon,
  LRectangle,
} from "@vue-leaflet/vue-leaflet";
import "leaflet/dist/leaflet.css";
import xlsx from "json-as-xlsx"

export default {
  components: {
    LMap,
    LIcon,
    LTileLayer,
    LMarker,
    LTooltip
  },
  name: `Entrega`,
  data(){
    return{
      usuarios:[],
      filter:'',
      clientes:[],
      pedidos:[],
      zoom:16,
      fecha:date.formatDate(new Date(),'YYYY-MM-DD'),
      fechareporte:{ini:date.formatDate(new Date(),'YYYY-MM-DD'),fin:date.formatDate(new Date(),'YYYY-MM-DD')},
      user:{},
      pedido:0,
      retorno:0,
      nopedido:0,
      visitas:[],
      infoventa:[],
      preventistas:[],
      preventista:{},
      productos:[],
      resumen:[],
      listado:[],
      reporte:[],
      rcontable:[],
      center:[-17.970371, -67.112303],
       columns : [
  {
    name: 'name',
    label: 'CLIENTE',
    align: 'left',
    field: 'Nombres',
    sortable: true
  },
  { name: 'estado', align: 'center', label: 'ESTADO', field: 'estado', sortable: true },
  { name: 'personal', align: 'center', label: 'PERSONAL', field: row=> row.Nombre1 + ' ' +row.App1, sortable: true },
  { name: 'observacion', label: 'OBSERVACION', field: 'observacion' },],
  columns2 : [
  { name: 'op',  label: 'OP', field: 'op' },
  { name: 'placa',  label: 'placa', field: 'placa', sortable: true },
  { name: 'fecha', label: 'Fecha',    field: 'fechaEntreg' },
  { name: 'total',  label: 'Total', field: 'total' },
  { name: 'entreg', label: 'Entregado', field: 'entreg' },
],
columns3 : [
  { name: 'codigo',  label: 'CODIGO PROD', field: 'cod_prod', sortable: true },
  { name: 'producto', label: 'PRODUCTO',    field: 'Producto', },
  { name: 'cantidad',  label: 'CANTIDAD', field: 'cantidad', },
],
colrept:[
  { name: 'cinit',  label: 'cinit', field: 'cinit', sortable: true },
  { name: 'Nombres',  label: 'Nombres', field: 'Nombres', sortable: true },
  { name: 'placa',  label: 'placa', field: 'placa', sortable: true },
  { name: 'prestado',  label: 'prestado', field: 'prestado', sortable: true },
  { name: 'devuelto',  label: 'devuelto', field: 'devuelto', sortable: true },
],
colPed:[
        {label:'comanda',name:'comanda',field:'comanda'},
        {label:'Importe',name:'Importe',field:'Importe'},
        {label:'Tipago',name:'Tipago',field:'Tipago',style: 'font-size:16px; font-weight:bold;',},
        {label:'estado',name:'estado',field:'estado'},
        {label:'Observacion',name:'Observacion',field:'observacion'},
        {label:'op',name:'op',field:'op'},
]

    }
  },
  created(){
    this.consula()
  },
  methods:{
    getPedidos(c){
      this.$api.post('ruta',{id:c.Id, fecha:this.fecha} ).then(res=>{
          this.pedidos=res.data
      })
    },
    async misclientes(){
      this.$q.loading.show()
      this.$api.post('listClienteComanda',{fecha:this.fecha} ).then(res=>{
         console.log(res.data)
         this.clientes=res.data
        this.$q.loading.hide()
      }).catch(err=>{
        this.$q.loading.hide()
        this.$q.notify({
          message:'Error al conectarse al server',
          color:'red',
          icon:'error'
        })
      })
    },
    onReady (mapObject) {
      mapObject.locate();
    },
    onLocationFound(location){
      // console.log(location)
      this.center=[location.latlng.lat,location.latlng.lng]
    },
    excel(r){
      console.log(r)
      this.$api.post('reportEntImp',{fecha:this.fecha,placa:r.placa}).then(res=>{
        let datCredito=[]
        let datContado=[]
        let datRechazado=[]
        console.log(res.data)
        res.data.forEach(p => {
            p.fecha=this.fecha
            p.placa=r.placa
            if(p.estado=='RECHAZADO')
              datRechazado.push(p)
            else{
              if(p.Tipago=='CONTADO')
                datContado.push(p)
              else
                datCredito.push(p)
            }
        });
        let datacaja = [
              {
                sheet: "CREDITO",
                columns: [
                  { label: "fecha", value: "fecha" }, // Top level data
                  { label: "placa", value: "placa" }, // Top level data
                  { label: "comanda", value: "comanda" }, // Top level data
                  { label: "Cliente", value: "Nombres" }, // Top level data
                  { label: "monto", value: "monto" }, // Top level data
                  { label: "estado", value: "estado" }, // Top level data
                  { label: "pago", value: "pago" }, // Top level data
                ],
                content: datCredito
              },
              {
                sheet: "CONTADO",
                columns: [
                  { label: "fecha", value: "fecha" }, // Top level data
                  { label: "placa", value: "placa" }, // Top level data
                  { label: "comanda", value: "comanda" }, // Top level data
                  { label: "Cliente", value: "Nombres" }, // Top level data
                  { label: "monto", value: "monto" }, // Top level data
                  { label: "estado", value: "estado" }, // Top level data
                  { label: "pago", value: "pago" }, // Top level data
                ],
                content: datContado
              },
              {
                sheet: "RECHAZADO",
                columns: [
                  { label: "fecha", value: "fecha" }, // Top level data
                  { label: "placa", value: "placa" }, // Top level data
                  { label: "comanda", value: "comanda" }, // Top level data
                  { label: "Cliente", value: "Nombres" }, // Top level data
                  { label: "monto", value: "monto" }, // Top level data
                  { label: "estado", value: "estado" }, // Top level data
                  { label: "pago", value: "pago" }, // Top level data
                ],
                content: datRechazado
              },
                ]

                let settings = {
                  fileName: "Reporte Entregas" , // Name of the resulting spreadsheet
                  extraLength: 5, // A bigger number means that columns will be wider
                  writeOptions: {}, // Style options from https://github.com/SheetJS/sheetjs#writing-options
                }

                xlsx(datacaja, settings) // Will download the excel file
  
      })
      },
    impresion(r){
      console.log(r)
      this.$api.post('reportEntImp',{fecha:this.fecha,placa:r.placa}).then(res=>{
        let contenido=''
        let contenido2=''
        let num=1
        let num2=1
        let totalcred=0
        let totalcont=0
        let totalpago=0
        res.data.forEach(r => {
            if (r.estado ==null) r.estado=''
            if (r.pago ==null) r.pago=0
            if(r.Tipago=='CONTADO'){
              contenido+='<tr><td>'+num+'</td><td>'+r.comanda+'</td><td>'+r.Nombres+'</td><td>'+r.Importe+'</td><td>'+r.estado+'</td><td>'+r.pago+'</td</tr>'
                num++
                totalcred+=parseFloat(r.Importe)

              }
              else{
              contenido2+='<tr><td>'+num2+'</td><td>'+r.comanda+'</td><td>'+r.Nombres+'</td><td>'+r.Importe+'</td><td>'+r.estado+'</td><td>'+r.pago+'</td</tr>'
                num2++
                totalcont+=parseFloat(r.Importe)
              }
            totalpago+=parseFloat(r.pago)
        });
        let cadena=`<style>
        .titulo1{font-size:18px;}
        .titulo2{font-size:14px; text-align:center; font-weight:bold;}
        .tab1{width:100%}</style>
        <table class='tab1'>
          <tr><td><img src="logo.png" alt="logo" width="150" height="100"></td>
            <td class='titulo1' style='color:red; font-weight:bold; font-size:20px;'>ENTREGAS DEL DIA <br> <span style="color:blue">`+date.formatDate(this.fecha,'dddd, DD MMMM YYYY')+`</span></td></tr>
          </table>
          <div class='titulo2'>PEDIDOS AL CONTADO</div>
          <table class='tab1'
            <tr><th>No</th><th>Comanda</th><th>Cliente</th><th>Monto</th><th>Estado</th><th>Pago</th></tr>
            `+contenido+`
          </table><br>
          <div class='titulo2'>PEDIDOS AL CREDITO</div>
          <table class='tab1'
            <tr><th>No</th><th>Comanda</th><th>Cliente</th><th>Monto</th><th>Estado</th><th>Pago</th></tr>
            `+contenido2+`
          </table>
          <div><b>TOTAL CREDITO: </b> `+totalcred.toFixed(2)+` Bs</div>
          <div><b>TOTAL CONTADO: </b> `+totalcont.toFixed(2)+` Bs</div>
          <div><b>TOTAL Pagos: </b> `+totalpago.toFixed(2)+` Bs</div>`
          document.getElementById('myelement').innerHTML = cadena
          const d = new Printd()
          d.print( document.getElementById('myelement') )
          })
      },
    calcular(entrega,total){
      let porcentaje=((entrega/total*100)/100)
      if (porcentaje>1){
        return 1
      }else{
        return porcentaje
      }
    },
    contable(){
      this.$api.get('reportContable/'+this.fecha).then(res=>{
        res.data.forEach(r => {
            r.tcontado=r.tcontado.toFixed(1)
            r.tcredito=r.tcredito.toFixed(1)
            r.cobro=r.cobro.toFixed(1)
        });
        this.rcontable=res.data
      })
    },
    listvendedor(){
      this.$api.post('lispreventista').then(res=>{
          res.data.forEach(r=>{
            r.label=r.Nombre1+' '+r.App1;
          })
          this.preventistas=res.data
      })

    },
    reportEnt(){
      this.$api.post('rePrestamo2',this.fechareporte).then(res=>{
        console.log(res.data)
        this.reporte=res.data
      })
    },
    ventaProducto(){
      this.$api.post('informeProducto',{cod:this.preventista.CodAut,ini:this.fechareporte.ini,fin:this.fechareporte.fin}).then(res=>{
        console.log(res.data)
        this.productos=res.data
      })
    },
    controlvisita(){
      this.$api.post('reporteVenta',{fecha:this.fecha}).then(res=>{
        console.log(res.data)
        this.infoventa=res.data
        this.preventista=this.preventistas[0]
      })
    },
    consula(){
      this.$q.loading.show()
      // console.log()
      this.resumen=[]
      this.listado=[]
      this.$q.loading.show()

      this.$api.post('resumenEntrega',{
        fecha:this.fecha
      }).then(res=>{
        console.log(res.data)
        this.resumen=res.data

        this.$api.post('listRuta',{fecha:this.fecha }).then(res=>{
          console.log(res.data)
          this.listado=res.data;
              this.contable()
              this.misclientes()

        })
        this.$q.loading.hide()
      })
    },
    log(a) {
      // console.log(a);
    },
  }
}
</script>

<style scoped>

</style>
