<template>
<q-page class="q-pa-xs">
<div class="row">
  <div class="col-4">
    <q-input dense outlined v-model="fecha1" label="Fecha Ini" type="date"/>
  </div>
    <div class="col-4">
    <q-input dense outlined v-model="fecha2" label="Fecha Fin" type="date"/>
  </div>
  <div class="col-4 flex flex-center">
    <q-btn color="info" icon="search" label="consulta" @click="misclientes" size="xs" />
  </div>
  <div class="col-12">
    <q-table dense title="Clientes " :columns="columns" :rows="clientes" :filter="filter">
      <template v-slot:body-cell-opciones="props">
        <q-td :props="props">
          <q-btn  @click="listpedidos(props.row)" :color="props.row.estado=='CREADO'?'primary':'warning'" :label="props.row.estado=='CREADO'?'MODIFICAR':'ENVIADO'" icon="shop" size="xs"  />        </q-td>
      </template>
      <template v-slot:top-right>
        <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
          <template v-slot:append>
            <q-icon name="search" />
          </template>
        </q-input>
      </template>
    </q-table>
    <q-btn style="width: 100%" @click="enviarpedidos" color="warning" icon="check" label="Enviar todos los pedidos"> </q-btn>
  </div>

    <q-dialog full-width full-height v-model="modalpedido">
      <q-card >
        <q-card-section>
          <div class="text-subtitle2">{{cliente.Cod_Aut}} {{cliente.Nombres}}</div>
        </q-card-section>
        <q-card-section class="q-pt-none">

          <div class="row">
            <div class="col-10">
              <q-select label="Productos" dense outlined class="q-ma-xs" use-input input-debounce="0"  @filter="filterFn" :options="productos" v-model="producto">
                <template v-slot:no-option>
                  <q-item>
                    <q-item-section class="text-grey">
                      No results
                    </q-item-section>
                  </q-item>
                </template>
              </q-select>
            </div>
            <div class="col-2 flex flex-center">
              <q-btn  class="q-pa-xs q-ma-none" color="primary" v-if="cliente.estado=='CREADO'" icon="add_circle" @click="agregarpedido"/>
            </div>
            <div class="col-12">
              <q-table :rows="misproductos"  :filter="filteproducto" :columns="columnsproducto">
                <template v-slot:body-cell-subtotal="props" >
                  <q-td :props="props" auto-width >
                    <q-btn flat @click="seleccionartipo(props.row)" class="q-ma-none q-pa-none" color="accent" icon="tune" />
                    {{props.row.subtotal}}
                    <q-badge :color="props.row.tipo=='NORMAL'?'primary':props.row.tipo=='POLLO'?'secondary':props.row.tipo=='CERDO'?'info':'positive'" >{{props.row.tipo.substring(0,1)}}</q-badge>
                  </q-td>
                </template>
                <template v-slot:body-cell-cantidad="props" >
                  <q-td :props="props" auto-width >
                    <template v-if="props.row.tipo=='NORMAL'">
                        <q-btn flat @click="agregar(props.row)" class="q-ma-none q-pa-none" color="positive" icon="add_circle"/>
                        <input type="number" @keyup="tecleado(props.row)" v-model="props.row.cantidad" style="width: 2.5em"  >
                    </template>
                        <q-btn flat @click="quitar(props.row,props.rowIndex)"  class="q-ma-none q-pa-none" color="negative" icon="remove_circle"/>
                  </q-td>
                </template>
                <template v-slot:body-cell-precio="props" >
                  <q-td :props="props" auto-width >
                    <input type="number" @keyup="tecleado(props.row)" v-model="props.row.precio" style="width: 3em"  >
                  </q-td>
                </template>
                <template v-slot:top-right>
                  <div class="row">
                    <div class="col-12">
                      <q-input outlined dense v-model="filteproducto" placeholder="Buscar pedido">
                        <template v-slot:append>
                          <q-icon name="search" />
                        </template>
                      </q-input>
                    </div>
                  </div>
                </template>
                <template v-slot:bottom-row>
                  <q-tr>
                    <q-td colspan="100%">
                      <div class="text-subtitle2">Total: {{total}} Bs.</div>
                    </q-td>
                  </q-tr>
                </template>
              </q-table>
              <q-btn v-if="cliente.estado=='CREADO'" @click="modificarcomanda"  style="width: 100%" label="Modificar pedido" icon="edit" color="warning" />
              <q-btn v-if="cliente.estado=='CREADO'" @click="eliminarcomanda"  style="width: 100%" label="Eliminar pedido" icon="delete" color="red" />
            </div>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="bg-white text-teal">
          <q-btn flat label="cerrar"  color="negative" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="modalpollo" full-width>
      <q-card>
        <q-card-section>
          <div class="text-h6">Pedido Pollo</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <div class="row">
            <div class="col-6" ><q-input type="number" dense outlined label="Cja-brasa5" v-model="miproducto.cbrasa5"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Unid-brasa5" v-model="miproducto.ubrasa5"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Cja-brasa6" v-model="miproducto.cbrasa6"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Unid-brasa6" v-model="miproducto.cubrasa6"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Cja-104" v-model="miproducto.c104"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Unid-104" v-model="miproducto.u104"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Cja-105" v-model="miproducto.c105"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Unid-105" v-model="miproducto.u105"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Cja-106" v-model="miproducto.c106"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Unid-106" v-model="miproducto.u106"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Cja-107" v-model="miproducto.c107"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Unid-107" v-model="miproducto.u107"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Cja-108" v-model="miproducto.c108"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Unid-108" v-model="miproducto.u108"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Cja-109" v-model="miproducto.c109"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Unid-109" v-model="miproducto.u109"/></div>
            <div class="col-5" ><q-input type="number" dense outlined label="ala" v-model="miproducto.ala"/></div>
            <div class="col-7">
              <div class="q-gutter-sm">
                <q-radio size="xs" dense v-model="miproducto.unidala" val="KG" label="KG" />
                <q-radio size="xs" dense v-model="miproducto.unidala" val="CJA" label="CJA" />
                <q-radio size="xs" dense v-model="miproducto.unidala" val="U" label="U" />
              </div>
            </div>
            <div class="col-5" ><q-input type="number" dense outlined label="Cadera" v-model="miproducto.cadera"/></div>
            <div class="col-7">
              <div class="q-gutter-sm">
                <q-radio size="xs" dense v-model="miproducto.unidcadera" val="KG" label="KG" />
                <q-radio size="xs" dense v-model="miproducto.unidcadera" val="CJA" label="CJA" />
                <q-radio size="xs" dense v-model="miproducto.unidcadera" val="U" label="U" />
              </div>
            </div>
            <div class="col-5" ><q-input type="number" dense outlined label="Pecho" v-model="miproducto.pecho"/></div>
                        <div class="col-7">
              <div class="q-gutter-sm">
                <q-radio size="xs" dense v-model="miproducto.unidpecho" val="KG" label="KG" />
                <q-radio size="xs" dense v-model="miproducto.unidpecho" val="CJA" label="CJA" />
                <q-radio size="xs" dense v-model="miproducto.unidpecho" val="U" label="U" />
              </div>
            </div>
            <div class="col-5" ><q-input type="number" dense outlined label="Pi/Mu" v-model="miproducto.pie"/></div>
                        <div class="col-7">
              <div class="q-gutter-sm">
                <q-radio size="xs" dense v-model="miproducto.unidpie" val="KG" label="KG" />
                <q-radio size="xs" dense v-model="miproducto.unidpie" val="CJA" label="CJA" />
                <q-radio size="xs" dense v-model="miproducto.unidpie" val="U" label="U" />
              </div>
            </div>
            <div class="col-5" ><q-input type="number" dense outlined label="Filete" v-model="miproducto.filete"/></div>
                        <div class="col-7">
              <div class="q-gutter-sm">
                <q-radio size="xs" dense v-model="miproducto.unidfilete" val="KG" label="KG" />
                <q-radio size="xs" dense v-model="miproducto.unidfilete" val="CJA" label="CJA" />
                <q-radio size="xs" dense v-model="miproducto.unidfilete" val="U" label="U" />
              </div>
            </div>
            <div class="col-5" ><q-input type="number" dense outlined label="Cuello" v-model="miproducto.cuello"/></div>
                        <div class="col-7">
              <div class="q-gutter-sm">
                <q-radio size="xs" dense v-model="miproducto.unidcuello" val="KG" label="KG" />
                <q-radio size="xs" dense v-model="miproducto.unidcuello" val="CJA" label="CJA" />
                <q-radio size="xs" dense v-model="miproducto.unidcuello" val="U" label="U" />
              </div>
            </div>
            <div class="col-5" ><q-input type="number" dense outlined label="Hueso" v-model="miproducto.hueso"/></div>
                        <div class="col-7">
              <div class="q-gutter-sm">
                <q-radio size="xs" dense v-model="miproducto.unidhueso" val="KG" label="KG" />
                <q-radio size="xs" dense v-model="miproducto.unidhueso" val="CJA" label="CJA" />
                <q-radio size="xs" dense v-model="miproducto.unidhueso" val="U" label="U" />
              </div>
            </div>
            <div class="col-5" ><q-input type="number" dense outlined label="Menud" v-model="miproducto.menu"/></div>
                        <div class="col-7">
              <div class="q-gutter-sm">
                <q-radio size="xs" dense v-model="miproducto.unidmenu" val="KG" label="KG" />
                <q-radio size="xs" dense v-model="miproducto.unidmenu" val="CJA" label="CJA" />
                <q-radio size="xs" dense v-model="miproducto.unidmenu" val="U" label="U" />
              </div>
            </div>
            <div class="col-5" ><q-input type="number" dense outlined label="bs" v-model="miproducto.bs"/></div>
            <div class="col-5" ><q-input type="number" dense outlined label="bs2" v-model="miproducto.bs2"/></div>
            <div class="col-5" ><q-input type="number" dense outlined label="contado" v-model="miproducto.contado"/></div>
            <div class="col-12" ><q-input  dense outlined label="observacion" v-model="miproducto.observacion"/></div>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="bg-white text-teal">
          <q-btn flat label="cerrar"  color="negative" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>


    <q-dialog v-model="modalnormal" full-width>
      <q-card>
        <q-card-section>
          <div class="text-h6">Pedido Normal</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <div class="row">
            <div class="col-12" ><q-input dense outlined label="observacion" v-model="miproducto.observacion"/></div>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="bg-white text-teal">
          <q-btn flat label="cerrar"  color="negative" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="modalcerdo" full-width>
      <q-card>
        <q-card-section>
          <div class="text-h6">Pedido Cerdo</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <div class="row">
            <div class="col-4"><q-input dense outlined label="precio" v-model="miproducto.precio" /></div>
            <div class="col-4"><q-input dense outlined label="total" v-model="miproducto.total" /></div>
            <div class="col-4"><q-input dense outlined label="entero" v-model="miproducto.entero" /></div>
            <div class="col-4"><q-input dense outlined label="desmembre" v-model="miproducto.desmembre" /></div>
            <div class="col-4"><q-input dense outlined label="corte" v-model="miproducto.corte" /></div>
            <div class="col-4"><q-input dense outlined label="kilo" v-model="miproducto.kilo" /></div>
            <div class="col-12" ><q-input dense outlined label="observacion" v-model="miproducto.observacion"/></div>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="bg-white text-teal">
          <q-btn flat label="cerrar"  color="negative" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="modalres" full-width>
      <q-card>
        <q-card-section>
          <div class="text-h6">Pedido Cerdo</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <div class="row">
            <div class="col-4"><q-input dense outlined label="precio" v-model="miproducto.precio" /></div>
            <div class="col-4"><q-input dense outlined label="trozado" v-model="miproducto.trozado" /></div>
            <div class="col-4"><q-input dense outlined label="entero" v-model="miproducto.entero" /></div>
            <div class="col-4"><q-input dense outlined label="pierna" v-model="miproducto.pierna" /></div>
            <div class="col-4"><q-input dense outlined label="brazo" v-model="miproducto.brazo" /></div>
            <div class="col-12" ><q-input dense outlined label="observacion" v-model="miproducto.observacion"/></div>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="bg-white text-teal">
          <q-btn flat label="cerrar"  color="negative" v-close-popup />
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
      pedestado:'',
      miproducto:{},
      modalpedido:false,
      modalcerdo:false,
      modalres:false,
      modalnormal:false,
      modalpollo:false,
      datocliente:{label:''},
      fecha1:date.formatDate(Date.now(),'YYYY-MM-DD'),
      fecha2:date.formatDate(Date.now(),'YYYY-MM-DD'),
      clientes:[],
      options:[],
      cliente:{},
      pedido:{},
      dialog_pedido:false,
            productos:[],
      productos2:[],
      misproductos:[],
      filteproducto:'',
      producto:{label:''},
      columns:[
        {label:'Comanda',name:'NroPed',field:'NroPed'},
        {label:'CI',name:'Id',field:'Id'},
        {label:'Nombre',name:'Nombres',field:'Nombres'},
        {label:'opciones',name:'opciones',field:'opciones'},
      ],
      columnsproducto:[
        {label:'subtotal',name:'subtotal',field:'subtotal'},
        {label:'cantidad',name:'cantidad',field:'cantidad'},
        {label:'precio',name:'precio',field:'precio',align:'left'},
        {label:'cod_prod',name:'cod_prod',field:'cod_prod',align:'left'},
        {label:'nombre',name:'nombre',field:'nombre',align:'left'},
        {label:'observacion',name:'observacion',field:'observacion',align:'left'},
      ],
      fecha:date.formatDate(Date.now(),'YYYY-MM-DD')
    }
  },
  created() {
    this.misclientes()
          this.$api.get('producto').then(res=>{
        // console.log(res.data)
        this.productos=[]
        // this.productos=res.data
        res.data.forEach(r=>{
          let d=r
          // console.log(d)
          d.label=r.cod_prod+'-'+r.Producto+' '+ parseFloat(r.Precio).toFixed(2) +'Bs '+ parseFloat(r.cantidad).toFixed(2)+r.codUnid
          this.productos.push(d)
        })
        this.productos2=this.productos
        // this.producto=this.productos[0]
        this.$q.loading.hide()
      })
  },

  methods:{
    tecleado(e){
      e.subtotal=(e.cantidad*e.precio).toFixed(2);
    },
    enviarpedidos(){
      this.$q.loading.show()
      this.$api.post('enviarpedidos',{clientes:this.clientes}).then(res=>{        // console.log(res.data)
        this.$q.loading.hide()
        // this.modalpedido=false
        this.misclientes()
      })
    },
    modificarcomanda(){
      // console.log(this.misproductos)
      // console.log(this.cliente)
      this.$q.loading.show()
      this.$api.post('updatecomanda',{comanda:this.cliente.NroPed,idCli:this.cliente.Cod_Aut,productos:this.misproductos}).then(res=>{
        // console.log(res.data)
        this.$q.loading.hide()
        this.modalpedido=false
      })
    },
        eliminarcomanda(){
           this.$api.post('deletecomanda',{comanda:this.cliente.NroPed}).then(res=>{
        this.modalpedido=false
        this.misclientes()
      })
    },
        agregarpedido(){
                if(this.producto.Producto==undefined){
        this.$q.notify({
          message:"No seleccionaste productos",
          color:"red",
          icon:"error"
        })
        return false
      }
      // console.log(this.cliente)
      this.misproductos.push({
        trozado:'',
        pierna:'',
        brazo:'',
        total:'',
        entero:'',
        desmembre:'',
        corte:'',
        kilo:'',
        observacion:'',
        cbrasa5:'',
        ubrasa5:'',
        cbrasa6:'',
        cubrasa6:'',
        c104:'',
        u104:'',
        c105:'',
        u105:'',
        c106:'',
        u106:'',
        c107:'',
        u107:'',
        c108:'',
        u108:'',
        c109:'',
        u109:'',
        ala:'',
        cadera:'',
        pecho:'',
        pie:'',
        filete:'',
        cuello:'',
        hueso:'',
        menu:'',
        unidala:'KG',
        unidcadera:'KG',
        unidpecho:'KG',
        unidpie:'KG',
        unidfilete:'KG',
        unidcuello:'KG',
        unidhueso:'KG',
        unidmenu:'KG',
        bs:'',
        bs2:'',
        contado:'',
         tipo:this.producto.tipo,
        nombre:this.producto.Producto,
        cod_prod:this.producto.cod_prod,
        precio:parseFloat(this.producto.Precio).toFixed(2),
        cantidad:1,
        subtotal:parseFloat(this.producto.Precio).toFixed(2)
      })
    },

        filterFn (val, update) {
      if (val === '') {
        update(() => {
          this.productos = this.productos2

          // here you have access to "ref" which
          // is the Vue reference of the QSelect
        })
        return
      }

      update(() => {
        const needle = val.toLowerCase()
        this.productos = this.productos2.filter(v => v.label.toLowerCase().indexOf(needle) > -1)
      })
    },
    agregar(producto){
      producto.cantidad = parseFloat(producto.cantidad)+1
      producto.subtotal = (producto.cantidad*parseFloat(producto.precio)).toFixed(2)
    },
    quitar(producto,index){
      if (parseFloat(producto.cantidad)==1){
        this.misproductos.splice(index, 1);
      }else {
        producto.cantidad = parseFloat(producto.cantidad)-1
        producto.subtotal = (producto.cantidad*parseFloat(producto.precio)).toFixed(2)
      }
    },
    listpedidos(cliente){
      this.cliente=cliente
      this.$q.loading.show()
        this.$api.post('listpedido',{idCli:cliente.Cod_Aut,fecha1:this.fecha1,fecha2:this.fecha2}).then(res=>{
          // console.log(res.data)
          this.misproductos=res.data[0].pedidos
          this.modalpedido=true
          this.$q.loading.hide()
      })
    },
        seleccionartipo(m){
      this.miproducto=m
        if (this.miproducto.tipo=='NORMAL'){
          this.modalnormal=true
        }else if (this.miproducto.tipo=='POLLO'){
          this.modalpollo=true
        }else if (this.miproducto.tipo=='CERDO'){
          this.modalcerdo=true
        }else if (this.miproducto.tipo=='RES'){
          this.modalres=true
        }else{
        }
    },

    misclientes(){
      this.$q.loading.show()
      this.$api.post('clientepedido',{fecha1:this.fecha1,fecha2:this.fecha2}).then(res=>{
        // console.log(res.data)
        this.clientes=res.data
        this.$q.loading.hide()
      })
    },
  },
    computed: {
    total(){
      let total=0
      this.misproductos.forEach(r=>{
        total+=parseFloat(r.subtotal)
      })
      return total.toFixed(2)
    }
  },
}
</script>

<style scoped>

</style>
