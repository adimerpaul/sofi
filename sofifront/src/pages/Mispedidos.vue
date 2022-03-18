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
    <q-btn color="info" icon="search" label="consulta" @click="misclientes"  />
  </div>
    <q-btn color="green" icon="list" label="Rep Pollo" @click="generarpollo" />
    <q-btn color="accent" icon="list" label="Rep Res" @click="generarres" />
    <q-btn color="teal" icon="list" label="Rep Cerdo" @click="generarcerdo" />

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
                    <pre>{{props.row}}</pre>
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
            <pre>{{miproducto}}</pre>
            <div class="col-3" ><q-input type="number" dense outlined label="Cja b5" v-model="miproducto.cbrasa5"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Uni b5" v-model="miproducto.ubrasa5"/></div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bsbrasa5"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obsbrasa5"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Cja b6" v-model="miproducto.cbrasa6"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Uni b6" v-model="miproducto.cubrasa6"/></div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bsbrasa6"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obsbrasa6"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Cja-104" v-model="miproducto.c104"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Unid-104" v-model="miproducto.u104"/></div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bs104"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obs104"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Cja-105" v-model="miproducto.c105"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Unid-105" v-model="miproducto.u105"/></div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bs105"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obs105"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Cja-106" v-model="miproducto.c106"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Unid-106" v-model="miproducto.u106"/></div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bs106"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obs106"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Cja-107" v-model="miproducto.c107"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Unid-107" v-model="miproducto.u107"/></div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bs107"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obs107"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Cja-108" v-model="miproducto.c108"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Unid-108" v-model="miproducto.u108"/></div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bs108"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obs108"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Cja-109" v-model="miproducto.c109"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="Unid-109" v-model="miproducto.u109"/></div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bs109"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obs109"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="ala" v-model="miproducto.ala"/></div>
            <div class="col-3">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidala" label="Unidad" />
            </div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bsala"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obsala"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="cadera" v-model="miproducto.cadera"/></div>
            <div class="col-3">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidcadera" label="Unidad" />
            </div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bscadera"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obscadera"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="pecho" v-model="miproducto.pecho"/></div>
            <div class="col-3">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidpecho" label="Unidad" />
            </div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bspecho"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obspecho"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="pie" v-model="miproducto.pie"/></div>
            <div class="col-3">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidpie" label="Unidad" />
            </div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bspie"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obspie"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="filete" v-model="miproducto.filete"/></div>
            <div class="col-3">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidfilete" label="Unidad" />
            </div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bsfilete"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obsfilete"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="cuello" v-model="miproducto.cuello"/></div>
            <div class="col-3">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidcuello" label="Unidad" />
            </div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bscuello"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obscuello"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="hueso" v-model="miproducto.hueso"/></div>
            <div class="col-3">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidhueso" label="Unidad" />
            </div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bshueso"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obshueso"/></div>
            <div class="col-3" ><q-input type="number" dense outlined label="menu" v-model="miproducto.menu"/></div>
            <div class="col-3">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidmenu" label="Unidad" />
            </div>
            <div class="col-2" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bsmenu"/></div>
            <div class="col-4" ><q-input type="text" dense outlined label="OBS" v-model="miproducto.obsmenu"/></div>
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

    <q-dialog v-model="dialog_pollo" full-width>
      <q-card >
        <q-card-section>
          <div class="text-h6">PEDIDO POLLO</div>
           <q-btn color="accent" icon="print" label="IMPRIMIR" @click="imprimirpollo" />

        </q-card-section>

        <q-card-section class="q-pt-none">
                <table id="example" class="display" style="width:100%">
                  <thead>
                  <tr>
                    <th>No</th>
                    <th>CLIENTE</th>
                    <th>C BRASA5</th>
                    <th>U BRASA5</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>C BRASA6</th>
                    <th>U BRASA6</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>C 104</th>
                    <th>U 104</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>C 105</th>
                    <th>U 105</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>C 106</th>
                    <th>U 106</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>C 107</th>
                    <th>U 107</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>C 108</th>
                    <th>U 108</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>C 109</th>
                    <th>U 109</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>ALA</th>
                    <th>UNID</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>CADERA</th>
                    <th>UNID</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>PECHO</th>
                    <th>UNID</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>PI/MU</th>
                    <th>UNID</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>FILETE</th>
                    <th>UNID</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>PECHO</th>
                    <th>UNID</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>HUESO</th>
                    <th>UNID</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>MENUD</th>
                    <th>CONT</th>
                    <th>BS</th>
                    <th>OBS</th>
                    <th>CONT</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr v-for="(v,index) in pollo" :key="index">
                    <td>{{index + 1}}</td>
                    <td>{{v.Nombres}}</td>
                    <td>{{v.cbrasa5}}</td>
                    <td>{{v.ubrasa5}}</td>
                    <td>{{v.bsbrasa5}}</td>
                    <td>{{v.obsbrasa5}}</td>
                    <td>{{v.cbrasa6}}</td>
                    <td>{{v.cubrasa6}}</td>
                    <td>{{v.bsbrasa6}}</td>
                    <td>{{v.obsbrasa6}}</td>
                    <td>{{v.c104}}</td>
                    <td>{{v.u104}}</td>
                    <td>{{v.bs104}}</td>
                    <td>{{v.obs104}}</td>
                    <td>{{v.c105}}</td>
                    <td>{{v.u105}}</td>
                    <td>{{v.bs105}}</td>
                    <td>{{v.obs105}}</td>
                    <td>{{v.c106}}</td>
                    <td>{{v.u106}}</td>
                    <td>{{v.bs106}}</td>
                    <td>{{v.obs106}}</td>
                    <td>{{v.c107}}</td>
                    <td>{{v.u107}}</td>
                    <td>{{v.bs107}}</td>
                    <td>{{v.obs107}}</td>
                    <td>{{v.c108}}</td>
                    <td>{{v.u108}}</td>
                    <td>{{v.bs108}}</td>
                    <td>{{v.obs108}}</td>
                    <td>{{v.c109}}</td>
                    <td>{{v.u109}}</td>
                    <td>{{v.bs109}}</td>
                    <td>{{v.obs109}}</td>
                    <td>{{v.ala}}</td>
                    <td>{{v.unidala}}</td>
                    <td>{{v.bsala}}</td>
                    <td>{{v.obsala}}</td>
                    <td>{{v.cadera}}</td>
                    <td>{{v.unidcadera}}</td>
                    <td>{{v.bscadera}}</td>
                    <td>{{v.obscadera}}</td>
                    <td>{{v.pecho}}</td>
                    <td>{{v.unidpecho}}</td>
                    <td>{{v.bspecho}}</td>
                    <td>{{v.obspecho}}</td>
                    <td>{{v.pie}}</td>
                    <td>{{v.unidpie}}</td>
                    <td>{{v.bspie}}</td>
                    <td>{{v.obspie}}</td>
                    <td>{{v.filete}}</td>
                    <td>{{v.unidfilete}}</td>
                    <td>{{v.bsfilete}}</td>
                    <td>{{v.obsfilete}}</td>
                    <td>{{v.cuello}}</td>
                    <td>{{v.unidcuello}}</td>
                    <td>{{v.bscuello}}</td>
                    <td>{{v.obscuello}}</td>
                    <td>{{v.hueso}}</td>
                    <td>{{v.unidhueso}}</td>
                    <td>{{v.bshueso}}</td>
                    <td>{{v.obshueso}}</td>
                    <td>{{v.menu}}</td>
                    <td>{{v.unidmenu}}</td>
                    <td>{{v.bsmenu}}</td>
                    <td>{{v.obsmenu}}</td>
                    <td>{{v.pago}}</td>
                  </tr>
                  </tbody>
                </table>
        </q-card-section>

        <q-card-actions align="right" class="text-primary">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn flat label="Add address" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
</div>
</q-page>
</template>

<script>
import {date} from "quasar";

import $ from "jquery";
import { jsPDF } from "jspdf";
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
      pollo:[],
      res:[],
      cerdo:[],
      datocliente:{label:''},
      fecha1:date.formatDate(Date.now(),'YYYY-MM-DD'),
      fecha2:date.formatDate(Date.now(),'YYYY-MM-DD'),
      clientes:[],
      options:[],
      cliente:{},
      pedido:{},
      dialog_pollo:false,
      dialog_res:false,
      dialog_cerdo:false,
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
                $('#example').DataTable( {
            dom: 'Blfrtip',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
          } );
                $('#example2').DataTable( {
            dom: 'Blfrtip',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
          } );
                          $('#example3').DataTable( {
            dom: 'Blfrtip',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
          } );
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

    imprimirpollo(){
      let mc=this
      let nom='' ;

      function header(){
        doc.setFont(undefined,'bold')
        doc.text(10, 0.5, 'PEDIDOS DE POLLOS ' )
        doc.text(1, 1,  'NOMBRE VENDEDOR'+nom)
        doc.text(12, 1,  'DE '+mc.fecha1+' AL '+mc.fecha2)

        doc.text(1.5,1,  'No')
        doc.text(1.5,1.5,  'CLIENTE')
        doc.text(1.5,2,  'C Br5')
        doc.text(1.5,2.5,  'U Br5')
        doc.text(1.5,3,  'Bs')
        doc.text(1.5,3.5,  'Obs')
        doc.text(1.5,4,  'C Br6')
        doc.text(1.5,4.5,  'U Br6')
        doc.text(1.5,5,  'Bs')
        doc.text(1.5,5.5,  'Obs')
        doc.text(1.5,6,  'C 104')
        doc.text(1.5,6.5,  'U 104')
        doc.text(1.5,7,  'Bs')
        doc.text(1.5,7.5,  'Obs')
        doc.text(1.5,8,  'C 105')
        doc.text(1.5,8.5,  'U 105')
        doc.text(1.5,9,  'Bs')
        doc.text(1.5,9.5,  'Obs')
        doc.text(1.5,10,  'C 106')
        doc.text(1.5,10.5,  'U 106')
        doc.text(1.5,11,  'Bs')
        doc.text(1.5,11.5,  'Obs')
        doc.text(1.5,12,  'C 107')
        doc.text(1.5,12.5,  'U 107')
        doc.text(1.5,13,  'Bs')
        doc.text(1.5,13.5,  'Obs')
        doc.text(1.5,14,  'C 108')
        doc.text(1.5,14.5,  'U 108')
        doc.text(1.5,15,  'Bs')
        doc.text(1.5,15.5,  'Obs')
        doc.text(1.5,16,  'C 109')
        doc.text(1.5,16.5,  'U 109')
        doc.text(1.5,17,  'Bs')
        doc.text(1.5,17.5,  'Obs')
        doc.text(1.5,18,  'Ala')
        doc.text(1.5,18.5,  'Unid')
        doc.text(1.5,19,  'Bs')
        doc.text(1.5,19.5,  'Obs')
        doc.text(1.5,20,  'Cadera')
        doc.text(1.5,20.5,  'Unid')
        doc.text(1.5,21,  'Bs')
        doc.text(1.5,21.5,  'Obs')
        doc.text(1.5,22,  'Pecho')
        doc.text(1.5,22.5,  'Unid')
        doc.text(1.5,23,  'Bs')
        doc.text(1.5,23.5,  'Obs')
        doc.text(1.5,24,  'Pi/Mu')
        doc.text(1.5,24.5,  'Unid')
        doc.text(1.5,25,  'Bs')
        doc.text(1.5,25.5,  'Obs')
        doc.text(1.5,26,  'Filete')
        doc.text(1.5,26.5,  'Unid')
        doc.text(1.5,27,  'Bs')
        doc.text(1.5,27.5,  'Obs')
        doc.text(1.5,28,  'Cuello')
        doc.text(1.5,28.5,  'Unid')
        doc.text(1.5,29,  'Bs')
        doc.text(1.5,29.5,  'Obs')
        doc.text(1.5,30,  'Hueso')
        doc.text(1.5,30.5,  'Unid')
        doc.text(1.5,31,  'Bs')
        doc.text(1.5,31.5,  'Obs')
        doc.text(1.5,32,  'Menud')
        doc.text(1.5,32.5,  'Unid')
        doc.text(1.5,33,  'Bs')
        doc.text(1.5,33.5,  'Obs')
        doc.text(1.5,34,  'Cont')
        doc.setLineWidth(0.1);
         doc.line(1, 1.2, 35, 1.2);
        doc.setFont(undefined,'normal')
      }
      var doc = new jsPDF('P','cm','legal')
      // console.log(dat);
      doc.setFont("courier");
      doc.setFontSize(8);
      // var x=0,y=
      header()
      // let xx=x
      // let yy=y
      let y=0
      let tsaldo=0
      let tacuenta=0
      let total=0
      let caja=0
        // xx+=0.5
        y+=0.5
        if (y+3>25){
          doc.addPage();
          header()
          y=0
        }


      // doc.save("Pago"+date.formatDate(Date.now(),'DD-MM-YYYY')+".pdf");
      window.open(doc.output('bloburl'), '_blank');
    },

    generarpollo(){
        $('#example').DataTable().destroy();

      this.$api.post('rpollo',{fecha1:this.fecha1,fecha2:this.fecha2}).then(res=>{

        console.log(res.data)
        $('#example').DataTable().destroy();
        this.pollo=res.data;
          $('#example').DataTable( {
            dom: 'Blfrtip',
            buttons: [
              'copy', 'csv', 'excel', 'pdf', 'print'
            ],
             "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
          } );
        })
        this.dialog_pollo=true
    },

        generarres(){
        $('#example2').DataTable().destroy();

      this.$api.post('rres',{fecha1:this.fecha1,fecha2:this.fecha2}).then(res=>{

        console.log(res.data)
        $('#example2').DataTable().destroy();
        this.res=res.data;
          $('#example2').DataTable( {
            dom: 'Blfrtip',
            buttons: [
              'copy', 'csv', 'excel', 'pdf', 'print'
            ],
             "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
          } );
        })
        this.dialog_res=true
    },

        generarcerdo(){
        $('#example3').DataTable().destroy();

      this.$api.post('rcerdo',{fecha1:this.fecha1,fecha2:this.fecha2}).then(res=>{

        console.log(res.data)
        $('#example3').DataTable().destroy();
        this.cerdo=res.data;
          $('#example3').DataTable( {
            dom: 'Blfrtip',
            buttons: [
              'copy', 'csv', 'excel', 'pdf', 'print'
            ],
             "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
          } );
        })
        this.dialog_cerdo=true
    },

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
        bsbrasa5:'',
        obsbrasa5:'',
        cbrasa6:'',
        cubrasa6:'',
        bsbrasa6:'',
        obsbrasa6:'',
        c104:'',
        u104:'',
        bs104:'',
        obs104:'',
        c105:'',
        u105:'',
        bs105:'',
        obs105:'',
        c106:'',
        u106:'',
        bs106:'',
        obs106:'',
        c107:'',
        u107:'',
        bs107:'',
        obs107:'',
        c108:'',
        u108:'',
        bs108:'',
        obs108:'',
        c109:'',
        u109:'',
        bs109:'',
        obs109:'',
        ala:'',
        bsala:'',
        obsala:'',
        cadera:'',
        bscadera:'',
        obscadera:'',
        pecho:'',
        bspecho:'',
        obspecho:'',
        pie:'',
        bspie:'',
        obspie:'',
        filete:'',
        bsfilete:'',
        obsfilete:'',
        cuello:'',
        bscuello:'',
        obscuello:'',
        hueso:'',
        bshueso:'',
        obshueso:'',
        menu:'',
        bsmenu:'',
        obsmenu:'',
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

          this.misproductos=res.data[0].pedidos
          console.log(this.misproductos)
          this.modalpedido=true
          this.$q.loading.hide()
      })
    },
        seleccionartipo(m){
      // console.log(m)
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
