<template>
  <q-page class="q-pa-xs">
    <div class="row">
      <div class="col-6">
        <q-input dense outlined v-model="fecha1" label="Fecha Ini" type="date"/>
      </div>
      <!--    <div class="col-6">-->
      <!--    <q-input dense outlined v-model="fecha2" label="Fecha Fin" type="date"/>-->
      <!--  </div>-->
      <div class="col-6 flex flex-center">
        <q-btn :loading="loading" color="info" icon="search" label="consulta" @click="misclientes"/>
      </div>
      <div class="col-4 col-sm-4 flex flex-center">
        <q-btn :loading="loading" class="full-width" color="green" type="a"
               :href="url+'excel/p/'+fecha1+'/'+fecha2+'/'+$store.state.login.user.CodAut" target="_blank" icon="list"
               label="Reporte Pollo" @click1="generarpollo"/>
      </div>
      <div class="col-4 col-sm-4 flex flex-center">
        <q-btn :loading="loading" class="full-width" color="accent" type="a"
               :href="url+'excel/r/'+fecha1+'/'+fecha2+'/'+$store.state.login.user.CodAut" target="_blank" icon="list"
               label="Reporte Res" @click1="generarres"/>
      </div>
      <div class="col-4 col-sm-4 flex flex-center">
        <q-btn :loading="loading" class="full-width" color="teal" type="a"
               :href="url+'excel/c/'+fecha1+'/'+fecha2+'/'+$store.state.login.user.CodAut" target="_blank" icon="list"
               label="Reporte Cerdo" @click1="generarcerdo"/>
      </div>
      <!--  <div class="col-6 col-sm-3 flex flex-center">-->
      <!--    <q-btn class="full-width" color="teal" icon="list" label="Reporte Comanda" @click="generarcomanda" />-->
      <!--  </div>-->
      <div class="col-12">
        <q-table :rows-per-page-options="[0]" dense title="Clientes " :columns="columns" :rows="clientes"
                 :filter="filter" wrap-cells>
          <template v-slot:body-cell-opciones="props">
            <q-td :props="props">
              <q-btn @click="listpedidos(props.row)" :color="props.row.estado=='CREADO'?'primary':'warning'"
                     :label="props.row.estado=='CREADO'?'Modificar':'Enviado'" icon="shop" size="xs" dense no-caps/>
              <q-btn @click="imprimirboleta(props.row)" color="info" icon="print" size="xs"
                     v-if="props.row.estado=='ENVIADO'" class="q-ml-xs"/>
<!--              quiero un chip si su bonificaion es true-->
              <br>
              <q-chip v-if="props.row.bonificacion==1" color="orange" text-color="white" size="xs" class="q-ml-xs" :label="props.row.clienteBonificacion"/>
            </q-td>
          </template>
          <template v-slot:top-right>
            <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
              <template v-slot:append>
                <q-icon name="search"/>
              </template>
            </q-input>
          </template>
        </q-table>
        <q-btn style="width: 100%" @click="enviarpedidos" color="warning" icon="check"
               label="Enviar todos los pedidos"></q-btn>
        <!--    <q-btn style="width: 100%" @click="expedidos" color="red" icon="warning" label="export pedidos"> </q-btn>-->
      </div>
<!--      <div>-->
<!--        <pre>{{clientes}}</pre>-->
<!--      </div>-->

      <q-dialog full-width full-height v-model="modalpedido">
        <q-card>
          <q-card-section>
            <div class="text-subtitle2">{{ cliente.Cod_Aut }} {{ cliente.Nombres }}</div>
          </q-card-section>
          <q-card-section class="q-pt-none">
            <div class="row">

              <!-- <div class="text-bold col-6 flex flex-center">
             -- <div class="q-gutter-sm col-md-6 col-sm-12" >
                <q-radio  dense v-model="pago" val="CONTADO" label="Contado" />
                <q-radio  dense v-model="pago" val="CREDITO" label="Credito" />
--              </div>
          </div>-->
              <div class="col-md-6 col-xs-6">
                <!--            <q-select dense outlined v-model="pago" :options="tipopagos" label="Tip Pagos" /></div>-->
                <div>
                  <q-radio v-model="pago" checked-icon="task_alt" dense unchecked-icon="panorama_fish_eye" val="CONTADO"
                           label="Contado"/>
                </div>
                <div>
                  <q-radio v-model="pago" checked-icon="task_alt" dense unchecked-icon="panorama_fish_eye" val="PAGO QR"
                           label="Pago QR"/>
                </div>
                <div>
                  <q-radio v-model="pago" checked-icon="task_alt" dense unchecked-icon="panorama_fish_eye" val="CREDITO"
                           label="Credito"/>
                </div>
                <div>
                  <q-radio v-model="pago" checked-icon="task_alt" dense unchecked-icon="panorama_fish_eye"
                           val="BOLETA ANTERIOR" label="Boleta anterior"/>
                </div>
              </div>
              <div class="col-6">
                <q-toggle
                  :label="fact+' FACTURA'"
                  color="green"
                  false-value="NO"
                  true-value="SI"
                  v-model="fact"/>
              </div>
              <div class="col-6">
                <q-input label="Fecha" v-model="fecha" type="date" dense outlined :min="fechamenos"/>
              </div>
              <div class="col-6">
                <q-select label="Horario" v-model="horario" dense outlined :options="horarios"/>
              </div>
              <div class="col-12">
                <q-input square outlined dense v-model="coment" label="Comentario"/>
              </div>
            </div>
            <div class="row">
              <div class="col-10">
                <q-select label="Productos" dense outlined class="q-ma-xs" use-input input-debounce="0"
                          @filter="filterFn" :options="productos" v-model="producto">
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
                <q-btn class="q-pa-xs q-ma-none" color="primary" v-if="cliente.estado=='CREADO'" icon="add_circle"
                       @click="agregarpedido"/>
              </div>
              <div class="col-12">
<!--                <pre>{{cliente.cliente}}</pre>-->
                <q-table :rows="misproductos" :filter="filteproducto" :columns="columnsproducto" dense
                         :title="'Pedido de '+cliente.cliente.Nombres"
                         :rows-per-page-options="[0]" row-key="id" wrap-cells flat bordered>
                  <template v-slot:body-cell-subtotal="props">
                    <q-td :props="props" auto-width>
                      <q-btn flat @click="seleccionartipo(props.row)" class="q-ma-none q-pa-none" color="accent"
                             icon="tune"/>
                      {{ props.row.subtotal }}
                      <!--                    <pre>{{props.row}}</pre>-->
                      <q-badge
                        :color="props.row.tipo=='NORMAL'?'primary':props.row.tipo=='POLLO'?'secondary':props.row.tipo=='CERDO'?'info':'positive'">
                        {{ props.row.tipo.substring(0, 1) }}
                      </q-badge>
                    </q-td>
                  </template>
                  <template v-slot:body-cell-cantidad="props">
                    <q-td :props="props" auto-width>
                      <template v-if="props.row.tipo=='NORMAL'">
                        <q-btn flat @click="agregar(props.row)" class="q-ma-none q-pa-none" color="positive"
                               icon="add_circle"/>
                        <input type="number" @keyup="tecleado(props.row)" v-model="props.row.cantidad"
                               style="width: 2.5em">
                      </template>
                      <q-btn flat @click="quitar(props.row,props.rowIndex)" class="q-ma-none q-pa-none" color="negative"
                             icon="remove_circle"/>
                    </q-td>
                  </template>
                  <template v-slot:body-cell-precio="props">
                    <q-td :props="props" auto-width>
                      <input type="number" @keyup="tecleado(props.row)" v-model="props.row.precio" style="width: 3em">
                    </q-td>
                  </template>
                  <template v-slot:top-right>
                    <div class="row">
                      <div class="col-12">
                        <q-input outlined dense v-model="filteproducto" placeholder="Buscar pedido">
                          <template v-slot:append>
                            <q-icon name="search"/>
                          </template>
                        </q-input>
                      </div>
                    </div>
                  </template>
                  <template v-slot:bottom-row>
                    <q-tr>
                      <q-td colspan="100%">
                        <div class="text-subtitle2">Total: {{ total }} Bs.</div>
                      </q-td>
                    </q-tr>
                  </template>
                </q-table>
                <q-btn v-if="cliente.estado=='CREADO'" @click="modificarcomanda" style="width: 100%" class="q-ma-xs"
                       label="Modificar pedido" icon="edit" color="warning"/>
                <q-btn v-if="cliente.estado=='CREADO'" @click="enviarcomanda" style="width: 100%" label="Enviar pedido" class="q-ma-xs"
                       icon="send" color="teal"/>
                <q-btn v-if="cliente.estado=='CREADO'" @click="eliminarcomanda" style="width: 100%" class="q-ma-xs"
                       label="Eliminar pedido" icon="delete" color="red"/>
              </div>
            </div>
          </q-card-section>
          <q-card-actions align="right" class="bg-white text-teal">
            <!--          alineadort betwe-->
            <div style="display: flex; justify-content: space-between; width: 100%;">
              <q-btn label="Clonar" color="green" @click="clonarpedido" :loading="loading" no-caps icon="content_copy"/>
              <q-btn flat label="cerrar" color="negative" v-close-popup/>
            </div>
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
              <!--            <pre>{{miproducto}}</pre>-->
              <div class="col-6">
                <q-input type="number" dense outlined label="Cja b5" v-model="miproducto.cbrasa5"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="Uni b5" v-model="miproducto.ubrasa5"/>
              </div>

              <div class="col-6">
                <q-input type="number" dense outlined label="Cja b6" v-model="miproducto.cbrasa6"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="Uni b6" v-model="miproducto.cubrasa6"/>
              </div>

              <div class="col-6">
                <q-input type="number" dense outlined label="Cja-104" v-model="miproducto.c104"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="Unid-104" v-model="miproducto.u104"/>
              </div>

              <div class="col-6">
                <q-input type="number" dense outlined label="Cja-105" v-model="miproducto.c105"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="Unid-105" v-model="miproducto.u105"/>
              </div>

              <div class="col-6">
                <q-input type="number" dense outlined label="Cja-106" v-model="miproducto.c106"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="Unid-106" v-model="miproducto.u106"/>
              </div>

              <div class="col-6">
                <q-input type="number" dense outlined label="Cja-107" v-model="miproducto.c107"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="Unid-107" v-model="miproducto.u107"/>
              </div>

              <div class="col-6">
                <q-input type="number" dense outlined label="Cja-108" v-model="miproducto.c108"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="Unid-108" v-model="miproducto.u108"/>
              </div>

              <div class="col-6">
                <q-input type="number" dense outlined label="Cja-109" v-model="miproducto.c109"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="Unid-109" v-model="miproducto.u109"/>
              </div>

              <div class="col-6">
                <q-input type="number" dense outlined label="Rango Po" v-model="miproducto.rango"/>
              </div>
              <div class="col-6"></div>

              <div class="col-6">
                <q-input type="number" dense outlined label="ala" v-model="miproducto.ala"/>
              </div>
              <div class="col-6">
                <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidala" label="Unidad"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="cadera" v-model="miproducto.cadera"/>
              </div>
              <div class="col-6">
                <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidcadera" label="Unidad"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="pecho" v-model="miproducto.pecho"/>
              </div>
              <div class="col-6">
                <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidpecho" label="Unidad"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="pie" v-model="miproducto.pie"/>
              </div>
              <div class="col-6">
                <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidpie" label="Unidad"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="filete" v-model="miproducto.filete"/>
              </div>
              <div class="col-6">
                <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidfilete" label="Unidad"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="cuello" v-model="miproducto.cuello"/>
              </div>
              <div class="col-6">
                <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidcuello" label="Unidad"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="hueso" v-model="miproducto.hueso"/>
              </div>
              <div class="col-6">
                <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidhueso" label="Unidad"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="menu" v-model="miproducto.menu"/>
              </div>
              <div class="col-6">
                <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidmenu" label="Unidad"/>
              </div>
              <div class="col-6">
                <q-input type="number" dense outlined label="BS" v-model="miproducto.bs"/>
              </div>
              <div class="col-6">
                <q-input type="text" dense outlined label="BS2" v-model="miproducto.bs2"/>
              </div>
              <div class="col-12">
                <q-input type="text" dense outlined label="OBS" v-model="miproducto.observacion"/>
              </div>
            </div>
          </q-card-section>
          <q-card-actions align="right" class="bg-white text-teal">
            <q-btn flat label="cerrar" color="negative" v-close-popup/>
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
              <div class="col-12">
                <q-input dense outlined label="observacion" v-model="miproducto.observacion"/>
              </div>
            </div>
          </q-card-section>
          <q-card-actions align="right" class="bg-white text-teal">
            <q-btn flat label="cerrar" color="negative" v-close-popup/>
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
              <div class="col-4">
                <q-input dense outlined label="precio" v-model="miproducto.pfrial"/>
              </div>
              <div class="col-4">
                <q-input dense outlined label="total" v-model="miproducto.total"/>
              </div>
              <div class="col-4">
                <q-input dense outlined label="entero" v-model="miproducto.entero"/>
              </div>
              <div class="col-4">
                <q-input dense outlined label="desmembre" v-model="miproducto.desmembre"/>
              </div>
              <div class="col-4">
                <q-input dense outlined label="corte" v-model="miproducto.corte"/>
              </div>
              <div class="col-4">
                <q-input dense outlined label="kilo" v-model="miproducto.kilo"/>
              </div>
              <div class="col-12">
                <q-input dense outlined label="observacion" v-model="miproducto.observacion"/>
              </div>
            </div>
          </q-card-section>
          <q-card-actions align="right" class="bg-white text-teal">
            <q-btn flat label="cerrar" color="negative" v-close-popup/>
          </q-card-actions>
        </q-card>
      </q-dialog>
      <q-dialog v-model="modalres" full-width>
        <q-card>
          <q-card-section>
            <div class="text-h6">Pedido Res</div>
          </q-card-section>
          <q-card-section class="q-pt-none">
            <div class="row">
              <div class="col-4">
                <q-input dense outlined label="precio" v-model="miproducto.pfrial"/>
              </div>
              <div class="col-4">
                <q-input dense outlined label="trozado" v-model="miproducto.trozado"/>
              </div>
              <div class="col-4">
                <q-input dense outlined label="entero" v-model="miproducto.entero"/>
              </div>
              <div class="col-4">
                <q-input dense outlined label="pierna" v-model="miproducto.pierna"/>
              </div>
              <div class="col-4">
                <q-input dense outlined label="brazo" v-model="miproducto.brazo"/>
              </div>
              <div class="col-12">
                <q-input dense outlined label="observacion" v-model="miproducto.observacion"/>
              </div>
            </div>
          </q-card-section>
          <q-card-actions align="right" class="bg-white text-teal">
            <q-btn flat label="cerrar" color="negative" v-close-popup/>
          </q-card-actions>
        </q-card>
      </q-dialog>
      <q-dialog v-model="dialog_pollo" full-width full-height>
        <q-card>
          <q-card-section>
            <div class="text-h6">PEDIDO POLLO</div>
            <q-btn color="accent" icon="print" label="IMPRIMIR" @click="imprimirpollo"/>

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
                <th>RANGO</th>
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
                <td>{{ index + 1 }}</td>
                <td>{{ v.Nombres }}</td>
                <td>{{ v.cbrasa5 }}</td>
                <td>{{ v.ubrasa5 }}</td>
                <td>{{ v.bsbrasa5 }}</td>
                <td>{{ v.obsbrasa5 }}</td>
                <td>{{ v.cbrasa6 }}</td>
                <td>{{ v.cubrasa6 }}</td>
                <td>{{ v.bsbrasa6 }}</td>
                <td>{{ v.obsbrasa6 }}</td>
                <td>{{ v.c104 }}</td>
                <td>{{ v.u104 }}</td>
                <td>{{ v.bs104 }}</td>
                <td>{{ v.obs104 }}</td>
                <td>{{ v.c105 }}</td>
                <td>{{ v.u105 }}</td>
                <td>{{ v.bs105 }}</td>
                <td>{{ v.obs105 }}</td>
                <td>{{ v.c106 }}</td>
                <td>{{ v.u106 }}</td>
                <td>{{ v.bs106 }}</td>
                <td>{{ v.obs106 }}</td>
                <td>{{ v.c107 }}</td>
                <td>{{ v.u107 }}</td>
                <td>{{ v.bs107 }}</td>
                <td>{{ v.obs107 }}</td>
                <td>{{ v.c108 }}</td>
                <td>{{ v.u108 }}</td>
                <td>{{ v.bs108 }}</td>
                <td>{{ v.obs108 }}</td>
                <td>{{ v.c109 }}</td>
                <td>{{ v.u109 }}</td>
                <td>{{ v.bs109 }}</td>
                <td>{{ v.obs109 }}</td>
                <td>{{ v.rango }}</td>
                <td>{{ v.ala }}</td>
                <td>{{ v.unidala }}</td>
                <td>{{ v.bsala }}</td>
                <td>{{ v.obsala }}</td>
                <td>{{ v.cadera }}</td>
                <td>{{ v.unidcadera }}</td>
                <td>{{ v.bscadera }}</td>
                <td>{{ v.obscadera }}</td>
                <td>{{ v.pecho }}</td>
                <td>{{ v.unidpecho }}</td>
                <td>{{ v.bspecho }}</td>
                <td>{{ v.obspecho }}</td>
                <td>{{ v.pie }}</td>
                <td>{{ v.unidpie }}</td>
                <td>{{ v.bspie }}</td>
                <td>{{ v.obspie }}</td>
                <td>{{ v.filete }}</td>
                <td>{{ v.unidfilete }}</td>
                <td>{{ v.bsfilete }}</td>
                <td>{{ v.obsfilete }}</td>
                <td>{{ v.cuello }}</td>
                <td>{{ v.unidcuello }}</td>
                <td>{{ v.bscuello }}</td>
                <td>{{ v.obscuello }}</td>
                <td>{{ v.hueso }}</td>
                <td>{{ v.unidhueso }}</td>
                <td>{{ v.bshueso }}</td>
                <td>{{ v.obshueso }}</td>
                <td>{{ v.menu }}</td>
                <td>{{ v.unidmenu }}</td>
                <td>{{ v.bsmenu }}</td>
                <td>{{ v.obsmenu }}</td>
                <td>{{ v.pago }}</td>
              </tr>
              </tbody>
            </table>
          </q-card-section>

          <q-card-actions align="right" class="text-primary">
            <q-btn flat label="Cancel" v-close-popup/>
            <q-btn flat label="Add address" v-close-popup/>
          </q-card-actions>
        </q-card>
      </q-dialog>
      <q-dialog v-model="dialog_res" full-width>
        <q-card>
          <q-card-section>
            <div class="text-h6">PEDIDO RES</div>
            <q-btn color="accent" icon="print" label="IMPRIMIR" @click="imprimires"/>
          </q-card-section>
          <q-card-section class="q-pt-none">
            <table id="example2" class="display" style="width:100%">
              <thead>
              <tr>
                <th>No</th>
                <th>CLIENTE</th>
                <th>PRECIO</th>
                <th>TROZADO</th>
                <th>ENT/MED</th>
                <th>PIERNA</th>
                <th>BRAZO</th>
                <th>CONT</th>
                <th>OBSERVACION</th>
              </tr>
              </thead>
              <tbody>
              <tr v-for="(v,index) in res" :key="index">
                <td>{{ index + 1 }}</td>
                <td>{{ v.Nombres }}</td>
                <td>{{ v.precio }}</td>
                <td>{{ v.trozado }}</td>
                <td>{{ v.entero }}</td>
                <td>{{ v.pierna }}</td>
                <td>{{ v.brazo }}</td>
                <td>{{ v.pago }}</td>
                <td>{{ v.observaciones }}</td>
              </tr>
              </tbody>
            </table>
          </q-card-section>

          <q-card-actions align="right" class="text-primary">
            <q-btn flat label="Cancel" v-close-popup/>
            <q-btn flat label="Add address" v-close-popup/>
          </q-card-actions>
        </q-card>
      </q-dialog>
      <q-dialog v-model="dialog_cerdo" full-width>
        <q-card>
          <q-card-section>
            <div class="text-h6">PEDIDO CERDO</div>
            <q-btn color="accent" icon="print" label="IMPRIMIR" @click="imprimircerdo"/>
          </q-card-section>
          <q-card-section class="q-pt-none">
            <table id="example3" class="display" style="width:100%">
              <thead>
              <tr>
                <th>No</th>
                <th>CLIENTE</th>
                <th>PRECIO</th>
                <th>TOTAL</th>
                <th>ENTERO</th>
                <th>DESMEMBRADO</th>
                <th>CORTE</th>
                <th>CORTE/KILO</th>
                <th>CONT</th>
                <th>OBSERVACION</th>
              </tr>
              </thead>
              <tbody>
              <tr v-for="(v,index) in cerdo" :key="index">
                <td>{{ index + 1 }}</td>
                <td>{{ v.Nombres }}</td>
                <td>{{ v.precio }}</td>
                <td>{{ v.total }}</td>
                <td>{{ v.entero }}</td>
                <td>{{ v.desmembre }}</td>
                <td>{{ v.corte }}</td>
                <td>{{ v.kilo }}</td>
                <td>{{ v.pago }}</td>
                <td>{{ v.observaciones }}</td>
              </tr>
              </tbody>
            </table>
          </q-card-section>

          <q-card-actions align="right" class="text-primary">
            <q-btn flat label="Cancel" v-close-popup/>
            <q-btn flat label="Add address" v-close-popup/>
          </q-card-actions>
        </q-card>
      </q-dialog>
    </div>
  </q-page>
</template>

<script>
import {date} from "quasar";

var $ = require('jquery');
require('datatables.net-buttons/js/buttons.html5.js')();
require('datatables.net-buttons/js/buttons.print.js')();
require('datatables.net-buttons/js/dataTables.buttons');
require('datatables.net-dt/css/jquery.dataTables.min.css');
import print from 'datatables.net-buttons/js/buttons.print';
import jszip from 'jszip/dist/jszip';
import pdfMake from 'pdfmake/build/pdfmake';
import pdfFonts from 'pdfmake/build/vfs_fonts';

pdfMake.vfs = pdfFonts.pdfMake.vfs;
window.JSZip = jszip;
import {jsPDF} from "jspdf";

const {addToDate} = date
export default {
  data() {
    return {
      horarios: ['06:00-07:30', '07:30-09:00', '09:00-10:30', '10:30-12:00', 'SEGUNDA VUELTA', 'SE RECOGE'],
      tipopagos: ['CONTADO', 'PAGO QR', 'CREDITO', 'BOLETA ANTERIOR'],
      horario: '',
      coment: '',
      url: process.env.API,
      filter: '',
      pedestado: '',
      pago: 'CONTADO',
      fact: 'NO',
      miproducto: {},
      modalpedido: false,
      modalcerdo: false,
      modalres: false,
      modalnormal: false,
      modalpollo: false,
      pollo: [],
      res: [],
      cerdo: [],
      datocliente: {label: ''},
      fecha1: date.formatDate(Date.now(), 'YYYY-MM-DD'),
      fecha2: date.formatDate(Date.now(), 'YYYY-MM-DD'),
      clientes: [],
      options: [],
      cliente: {},
      pedido: {},
      dialog_pollo: false,
      dialog_res: false,
      dialog_cerdo: false,
      dialog_pedido: false,
      productos: [],
      productos2: [],
      misproductos: [],
      filteproducto: '',
      producto: {label: ''},
      columns: [
        { label: 'Opciones', name: 'opciones', field: 'opciones' },
        { label: 'Comanda', name: 'NroPed', field: 'NroPed' },
        {
          label: 'Nombre',
          name: 'Nombres',
          field: row => row.cliente?.Nombres || '—',
          align: 'left'
        },
        { label: 'CI', name: 'Id', field: row => row.cliente?.Id || '', align: 'left' },
        { label: 'Fec/Hora', name: 'fecha', field: 'fecha', align: 'left' },
        { label: 'PAGO', name: 'pago', field: 'pago', align: 'left' },
        { label: 'FACTURA', name: 'fact', field: 'fact', align: 'left' }
      ],
      columnsproducto: [
        {label: 'subtotal', name: 'subtotal', field: 'subtotal'},
        {label: 'cantidad', name: 'cantidad', field: 'cantidad'},
        {label: 'precio', name: 'precio', field: 'precio', align: 'left'},
        {label: 'cod_prod', name: 'cod_prod', field: 'cod_prod', align: 'left'},
        {label: 'nombre', name: 'nombre', field: 'nombre', align: 'left'},
        {label: 'observacion', name: 'observacion', field: 'observacion', align: 'left'},
      ],
      fecha: date.formatDate(Date.now(), 'YYYY-MM-DD'),
      fechaClonacion: date.formatDate(Date.now(), 'YYYY-MM-DD'),
      fechamenos: date.formatDate(addToDate(new Date(), {days: 0}), 'YYYY-MM-DD'),
      loading: false,
      bonificacion: 0,
      bonificacionAprovacion: '',
      bonificacionId: '',
    }
  },
  created() {
    //       $('#example').DataTable( {
    //   dom: 'Blfrtip',
    //   buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
    // } );
    //       $('#example2').DataTable( {
    //   dom: 'Blfrtip',
    //   buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
    // } );
    //                 $('#example3').DataTable( {
    //   dom: 'Blfrtip',
    //   buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
    // } );
    this.misclientes()
    this.$api.get('producto').then(res => {
      // console.log(res.data)
      this.productos = []
      // this.productos=res.data
      res.data.forEach(r => {
        let d = r
        // console.log(d)
        if (r.cantidad == null) r.cantidad = 0
        d.label = r.cod_prod + '-' + r.Producto + ' ' + parseFloat(r.Precio).toFixed(2) + 'Bs ' + parseFloat(r.cantidad).toFixed(2) + r.codUnid
        this.productos.push(d)
      })
      this.productos2 = this.productos
      // this.producto=this.productos[0]
      this.$q.loading.hide()
    })
  },

  methods: {
    clonarpedido() {
      this.fechaClonacion = date.formatDate(Date.now(), 'YYYY-MM-DD')
      this.$q.dialog({
        title: 'Clonar pedido',
        message: 'Coloca la fecha de la clonacion',
        prompt: {
          model: this.fechaClonacion,
          type: 'date'
        },
        persistent: true,
        cancel: true,
      }).onOk((data) => {
        this.loading = true
        this.$api.post('clonarpedido', {NroPed: this.cliente.NroPed, fecha: data}).then(res => {
          console.log(res.data)
          this.$q.notify({
            type: 'positive',
            message: 'Pedido clonado con exito',
          })
        }).finally(() => {
          this.loading = false
        }).catch(err => {
          console.log(err)
          this.$q.notify({
            type: 'negative',
            message: err.response.data.message,
          })
        })
      })
    },
    imprimirpollo() {
      this.$q.loading.show()
      let mc = this
      let nom = '';
      this.$api.post('pollo', {fecha1: this.fecha1, fecha2: this.fecha2}).then(res => {
        this.$q.loading.hide()
        console.log(res.data)

        function header() {
          doc.setFont(undefined, 'bold')
          doc.text(10, 0.5, 'PEDIDOS DE POLLOS ')
          doc.text(1, 1, 'NOMBRE ' + mc.$store.getters["login/user"].Nombre1)
          doc.text(12, 1, 'DE ' + mc.fecha1 + ' AL ' + mc.fecha2)
          doc.text(9, 1, 'No')

          // doc.text(1.5,2,  'C Br5')
          // doc.text(1.5,2.5,  'U Br5')
          // doc.text(1.5,3,  'Bs')
          // doc.text(1.5,3.5,  'Obs')
          // doc.text(1.5,4,  'C Br6')
          // doc.text(1.5,4.5,  'U Br6')
          // doc.text(1.5,5,  'Bs')
          // doc.text(1.5,5.5,  'Obs')
          // doc.text(1.5,6,  'C 104')
          // doc.text(1.5,6.5,  'U 104')
          // doc.text(1.5,7,  'Bs')
          // doc.text(1.5,7.5,  'Obs')
          // doc.text(1.5,8,  'C 105')
          // doc.text(1.5,8.5,  'U 105')
          // doc.text(1.5,9,  'Bs')
          // doc.text(1.5,9.5,  'Obs')
          // doc.text(1.5,10,  'C 106')
          // doc.text(1.5,10.5,  'U 106')
          // doc.text(1.5,11,  'Bs')
          // doc.text(1.5,11.5,  'Obs')
          // doc.text(1.5,12,  'C 107')
          // doc.text(1.5,12.5,  'U 107')
          // doc.text(1.5,13,  'Bs')
          // doc.text(1.5,13.5,  'Obs')
          // doc.text(1.5,14,  'C 108')
          // doc.text(1.5,14.5,  'U 108')
          // doc.text(1.5,15,  'Bs')
          // doc.text(1.5,15.5,  'Obs')
          // doc.text(1.5,16,  'C 109')
          // doc.text(1.5,16.5,  'U 109')
          // doc.text(1.5,17,  'Bs')
          // doc.text(1.5,17.5,  'Obs')
          // doc.text(1.5,18,  'Ala')
          // doc.text(1.5,18.5,  'Unid')
          // doc.text(1.5,19,  'Bs')
          // doc.text(1.5,19.5,  'Obs')
          // doc.text(1.5,20,  'Cadera')
          // doc.text(1.5,20.5,  'Unid')
          // doc.text(1.5,21,  'Bs')
          // doc.text(1.5,21.5,  'Obs')
          // doc.text(1.5,22,  'Pecho')
          // doc.text(1.5,22.5,  'Unid')
          // doc.text(1.5,23,  'Bs')
          // doc.text(1.5,23.5,  'Obs')
          // doc.text(1.5,24,  'Pi/Mu')
          // doc.text(1.5,24.5,  'Unid')
          // doc.text(1.5,25,  'Bs')
          // doc.text(1.5,25.5,  'Obs')
          // doc.text(1.5,26,  'Filete')
          // doc.text(1.5,26.5,  'Unid')
          // doc.text(1.5,27,  'Bs')
          // doc.text(1.5,27.5,  'Obs')
          // doc.text(1.5,28,  'Cuello')
          // doc.text(1.5,28.5,  'Unid')
          // doc.text(1.5,29,  'Bs')
          // doc.text(1.5,29.5,  'Obs')
          // doc.text(1.5,30,  'Hueso')
          // doc.text(1.5,30.5,  'Unid')
          // doc.text(1.5,31,  'Bs')
          // doc.text(1.5,31.5,  'Obs')
          // doc.text(1.5,32,  'Menud')
          // doc.text(1.5,32.5,  'Unid')
          // doc.text(1.5,33,  'Bs')
          // doc.text(1.5,33.5,  'Obs')
          // doc.text(1.5,34,  'Cont')
          doc.setLineWidth(0.1);
          doc.line(1, 1.1, 21, 1.1);
          doc.setFont(undefined, 'normal')
        }

        var doc = new jsPDF('L', 'cm', 'legal')
        // console.log(dat);
        doc.setFont("courier");
        doc.setFontSize(10);
        // var x=0,y=
        header()
        // let xx=x
        // let yy=y
        let y = 1.5
        let tsaldo = 0
        let tacuenta = 0
        let total = 0
        let caja = 0
        // xx+=0.5

        res.data.forEach(r => {
          doc.text(1, y - 0.4, '_______________________________________________________________________________________________')
          doc.setFont(undefined, 'bold')
          doc.text(1.5, y, 'CI')
          doc.text(3.5, y, 'CLIENTE')
          doc.text(10.5, y, 'COMANDA')
          doc.setFont(undefined, 'normal')

          doc.text(1, y + 0.4, r.Id)
          doc.text(3.5, y + 0.4, r.Nombres.substring(0, 35))
          doc.text(10.5, y + 0.4, r.NroPed + '')
          y += 0.5
          if (r.bsbrasa5 != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, 'C Bra5')
            doc.text(15.5, y, 'U Bra5')
            doc.text(17.5, y, 'Bs Bra5')
            doc.text(20, y, 'OBS Bra5')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.cbrasa5 == null ? '' : r.cbrasa5 + '')
            doc.text(15.5, y + 0.4, r.ubrasa5 == null ? '' : r.ubrasa5 + '')
            doc.text(17.5, y + 0.4, r.bsbrasa5 == null ? '' : r.bsbrasa5 + '')
            doc.text(20, y + 0.4, r.obsbrasa5 == null ? '' : r.obsbrasa5 + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
          if (r.bsbrasa6 != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, 'C Bra6')
            doc.text(15.5, y, 'U Bra6')
            doc.text(17.5, y, 'Bs Bra6')
            doc.text(20, y, 'OBS Bra6')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.cbrasa6 == null ? '' : r.cbrasa6 + '')
            doc.text(15.5, y + 0.4, r.cubrasa6 == null ? '' : r.cubrasa6 + '')
            doc.text(17.5, y + 0.4, r.bsbrasa6 == null ? '' : r.bsbrasa6 + '')
            doc.text(20, y + 0.4, r.obsbrasa6 == null ? '' : r.obsbrasa6 + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
          if (r.bs104 != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, 'C 104')
            doc.text(15.5, y, 'U 104')
            doc.text(17.5, y, 'Bs 104')
            doc.text(20, y, 'OBS 104')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.c104 == null ? '' : r.c104 + '')
            doc.text(15.5, y + 0.4, r.u104 == null ? '' : r.u104 + '')
            doc.text(17.5, y + 0.4, r.bs104 == null ? '' : r.bs104 + '')
            doc.text(20, y + 0.4, r.obs104 == null ? '' : r.obs104 + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
          if (r.bs105 != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, 'C 105')
            doc.text(15.5, y, 'U 105')
            doc.text(17.5, y, 'Bs 105')
            doc.text(20, y, 'OBS 105')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.c105 == null ? '' : r.c105 + '')
            doc.text(15.5, y + 0.4, r.u105 == null ? '' : r.u105 + '')
            doc.text(17.5, y + 0.4, r.bs105 == null ? '' : r.bs105 + '')
            doc.text(20, y + 0.4, r.obs105 == null ? '' : r.obs105 + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
          if (r.bs106 != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, 'C 106')
            doc.text(15.5, y, 'U 106')
            doc.text(17.5, y, 'Bs 106')
            doc.text(20, y, 'OBS 106')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.c106 == null ? '' : r.c106 + '')
            doc.text(15.5, y + 0.4, r.u106 == null ? '' : r.u106 + '')
            doc.text(17.5, y + 0.4, r.bs106 == null ? '' : r.bs106 + '')
            doc.text(20, y + 0.4, r.obs106 == null ? '' : r.obs106 + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
          if (r.bs107 != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, 'C 107')
            doc.text(15.5, y, 'U 107')
            doc.text(17.5, y, 'Bs 107')
            doc.text(20, y, 'OBS 107')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.c107 == null ? '' : r.c107 + '')
            doc.text(15.5, y + 0.4, r.u107 == null ? '' : r.u107 + '')
            doc.text(17.5, y + 0.4, r.bs107 == null ? '' : r.bs107 + '')
            doc.text(20, y + 0.4, r.obs107 == null ? '' : r.obs107 + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
          if (r.bs108 != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, 'C 108')
            doc.text(15.5, y, 'U 108')
            doc.text(17.5, y, 'Bs 108')
            doc.text(20, y, 'OBS 108')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.c108 == null ? '' : r.c108 + '')
            doc.text(15.5, y + 0.4, r.u108 == null ? '' : r.u108 + '')
            doc.text(17.5, y + 0.4, r.bs108 == null ? '' : r.bs108 + '')
            doc.text(20, y + 0.4, r.obs108 == null ? '' : r.obs108 + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
          if (r.bs109 != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, 'C 109')
            doc.text(15.5, y, 'U 109')
            doc.text(17.5, y, 'Bs 109')
            doc.text(20, y, 'OBS 109')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.c109 == null ? '' : r.c109 + '')
            doc.text(15.5, y + 0.4, r.u109 == null ? '' : r.u109 + '')
            doc.text(17.5, y + 0.4, r.bs109 == null ? '' : r.bs109 + '')
            doc.text(20, y + 0.4, r.obs109 == null ? '' : r.obs109 + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
          if (r.bsala != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, 'ala')
            doc.text(15.5, y, 'Unid ala')
            doc.text(17.5, y, 'Bs ala')
            doc.text(20, y, 'OBS ala')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.ala == null ? '' : r.ala + '')
            doc.text(15.5, y + 0.4, r.unidala == null ? '' : r.unidala + '')
            doc.text(17.5, y + 0.4, r.bsala == null ? '' : r.bsala + '')
            doc.text(20, y + 0.4, r.obsala == null ? '' : r.obsala + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
          if (r.bscadera != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, 'cadera')
            doc.text(15.5, y, 'U-cadera')
            doc.text(17.5, y, 'Bs cadera')
            doc.text(20, y, 'OBS cadera')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.cadera == null ? '' : r.cadera + '')
            doc.text(15.5, y + 0.4, r.unidcadera == null ? '' : r.unidcadera + '')
            doc.text(17.5, y + 0.4, r.bscadera == null ? '' : r.bscadera + '')
            doc.text(20, y + 0.4, r.obscadera == null ? '' : r.obscadera + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
          if (r.bspecho != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, ' pecho')
            doc.text(15.5, y, 'U-pecho')
            doc.text(17.5, y, 'Bs pecho')
            doc.text(20, y, 'OBS pecho')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.pecho == null ? '' : r.pecho + '')
            doc.text(15.5, y + 0.4, r.unidpecho == null ? '' : r.unidpecho + '')
            doc.text(17.5, y + 0.4, r.bspecho == null ? '' : r.bspecho + '')
            doc.text(20, y + 0.4, r.obspecho == null ? '' : r.obspecho + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
          if (r.bspie != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, 'Pi/Mu')
            doc.text(15.5, y, 'Unid p')
            doc.text(17.5, y, 'Bs pi')
            doc.text(20, y, 'OBS pi')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.pie == null ? '' : r.pie + '')
            doc.text(15.5, y + 0.4, r.unidpie == null ? '' : r.unidpie + '')
            doc.text(17.5, y + 0.4, r.bspie == null ? '' : r.bspie + '')
            doc.text(20, y + 0.4, r.obspie == null ? '' : r.obspie + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
          if (r.bsfilete != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, ' filete')
            doc.text(15.5, y, 'U-filete')
            doc.text(17.5, y, 'Bs filete')
            doc.text(20, y, 'OBS filete')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.filete == null ? '' : r.filete + '')
            doc.text(15.5, y + 0.4, r.unidfilete == null ? '' : r.unidfilete + '')
            doc.text(17.5, y + 0.4, r.bsfilete == null ? '' : r.bsfilete + '')
            doc.text(20, y + 0.4, r.obsfilete == null ? '' : r.obsfilete + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
          if (r.bscuello != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, ' cuello')
            doc.text(15.5, y, 'U-cuello')
            doc.text(17.5, y, 'Bs cuello')
            doc.text(20, y, 'OBS cuello')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.cuello == null ? '' : r.cuello + '')
            doc.text(15.5, y + 0.4, r.unidcuello == null ? '' : r.unidcuello + '')
            doc.text(17.5, y + 0.4, r.bscuello == null ? '' : r.bscuello + '')
            doc.text(20, y + 0.4, r.obscuello == null ? '' : r.obscuello + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }


          if (r.bshueso != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, ' hueso')
            doc.text(15.5, y, 'U-hueso')
            doc.text(17.5, y, 'Bs hueso')
            doc.text(20, y, 'OBS hueso')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.hueso == null ? '' : r.hueso + '')
            doc.text(15.5, y + 0.4, r.unidhueso == null ? '' : r.unidhueso + '')
            doc.text(17.5, y + 0.4, r.bshueso == null ? '' : r.bshueso + '')
            doc.text(20, y + 0.4, r.obshueso == null ? '' : r.obshueso + '')
            y += 0.8
          }
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
          if (r.bsmenu != null) {
            doc.setFont(undefined, 'bold')
            doc.text(13.5, y, ' menu')
            doc.text(15.5, y, 'U-menu')
            doc.text(17.5, y, 'Bs menu')
            doc.text(20, y, 'OBS menu')
            doc.setFont(undefined, 'normal')
            doc.text(13.5, y + 0.4, r.menu == null ? '' : r.menu + '')
            doc.text(15.5, y + 0.4, r.unidmenu == null ? '' : r.unidmenu + '')
            doc.text(17.5, y + 0.4, r.bsmenu == null ? '' : r.bsmenu + '')
            doc.text(20, y + 0.4, r.obsmenu == null ? '' : r.obsmenu + '')
            y += 0.8
          }

          // doc.text(6.5, y, 'C Braza5')
          // doc.text(8, y, 'C Braza5')
          // doc.text(9.5, y, 'C Braza5')
          // doc.text(11, y, 'C Braza5')
          // doc.text(12.5, y, 'C Braza5')
          // doc.text(14, y, 'C Braza5')
          // doc.text(6.5, y, 'C Braza5')
          // doc.text(7, y, 'C Braza5')
          // doc.text(7.5, y, 'C Braza5')
          // doc.text(8, y, 'C Braza5')
          // doc.text(8.5, y, 'C Braza5')
          // doc.text(9, y, 'C Braza5')
          // doc.text(9.5, y, 'C Braza5')
          // doc.text(10, y, 'C Braza5')
          // doc.text(10.5, y, 'C Braza5')
          // doc.text(11, y, 'C Braza5')
          // doc.text(11.5, y, 'C Braza5')


          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
        })


        doc.save("Pollo -" + date.formatDate(Date.now(), 'DD-MM-YYYY') + ".pdf");
        //window.open(doc.output('bloburl'), '_blank');
      })

      // console.log(this.$store.getters["login/user"])

    },

    imprimires() {
      this.$q.loading.show()
      let mc = this
      let nom = '';
      this.$api.post('res', {fecha1: this.fecha1, fecha2: this.fecha2}).then(res => {
        this.$q.loading.hide()
        console.log(res.data)

        function header() {
          doc.setFont(undefined, 'bold')
          doc.text(10, 0.5, 'PEDIDOS DE RES ')
          doc.text(1, 1, 'NOMBRE ' + mc.$store.getters["login/user"].Nombre1)
          doc.text(12, 1, 'DE ' + mc.fecha1 + ' AL ' + mc.fecha2)
          doc.text(9, 1, 'No')

          doc.setLineWidth(0.1);
          doc.line(1, 1.1, 21, 1.1);

          doc.text(1, 1.5, 'CINIT')
          doc.text(3.5, 1.5, 'CLIENTE')
          doc.text(8, 1.5, 'NPED')
          doc.text(9.5, 1.5, 'PRECIO')
          doc.text(11.5, 1.5, 'TROZA')
          doc.text(13, 1.5, 'EN/MD')
          doc.text(14.5, 1.5, 'PIER')
          doc.text(16, 1.5, 'BRAZO')
          doc.text(17.5, 1.5, 'OBSERVACION')
          doc.setFont(undefined, 'normal')
        }

        var doc = new jsPDF('L', 'cm', 'legal')
        // console.log(dat);
        doc.setFont("courier");
        doc.setFontSize(9);
        // var x=0,y=
        header()
        // let xx=x
        // let yy=y
        let y = 1.5
        // xx+=0.5

        res.data.forEach(r => {
          doc.text(1, y + 0.4, '_____________________________________________________________________________________________________')
          doc.setFont(undefined, 'bold')
          //doc.text(1,y,  'CINIT')
          //doc.text(2.5,y,  'CLIENTE')
          //doc.text(6.5,y,  'COMANDA')
          doc.setFont(undefined, 'normal')

          doc.text(1, y + 0.4, r.Id)
          doc.text(3.5, y + 0.4, r.Nombres.substring(0, 20))
          doc.text(8, y + 0.4, r.NroPed + '')
          doc.text(9.5, y + 0.4, r.precio + '')
          doc.text(11.5, y + 0.4, r.trozado == null ? '' : r.trozado + '')
          doc.text(13, y + 0.4, r.entero == null ? '' : r.entero + '')
          doc.text(14.5, y + 0.4, r.pierna == null ? '' : r.pierna + '')
          doc.text(16, y + 0.4, r.brazo == null ? '' : r.brazo + '')
          doc.text(17.5, y + 0.4, r.Observaciones == null ? '' : r.Observaciones + '')
          y += 0.5
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
        })
        doc.save("Res -" + date.formatDate(Date.now(), 'DD-MM-YYYY') + ".pdf");
        //window.open(doc.output('bloburl'), '_blank');
      })
    },

    imprimircomanda() {
      this.$q.loading.show()
      let mc = this
      let nom = '';
      this.$api.post('listcomanda', {fecha1: this.fecha1, fecha2: this.fecha2}).then(res => {
        this.$q.loading.hide()
        console.log(res.data)

        function header() {
          doc.setFont(undefined, 'bold')
          doc.text(10, 0.5, 'PEDIDOS  ')
          doc.text(1, 1, 'NOMBRE ' + mc.$store.getters["login/user"].Nombre1)
          doc.text(12, 1, 'DE ' + mc.fecha1 + ' AL ' + mc.fecha2)
          doc.text(9, 1, 'No')

          doc.setLineWidth(0.1);
          doc.line(1, 1.1, 21, 1.1);

          doc.text(1, 1.5, 'CINIT')
          doc.text(3.5, 1.5, 'CLIENTE')
          doc.text(8, 1.5, 'NPED')
          doc.text(9.5, 1.5, 'CODIGO')
          doc.text(11.5, 1.5, 'PRODUCTO')
          doc.text(17, 1.5, 'CANT')
          doc.text(18.5, 1.5, 'PRECIO')
          doc.text(21, 1.5, 'OBSERVACION')
          doc.setFont(undefined, 'normal')
        }

        var doc = new jsPDF('L', 'cm', 'legal')
        // console.log(dat);
        doc.setFont("courier");
        doc.setFontSize(9);
        // var x=0,y=
        header()
        // let xx=x
        // let yy=y
        let y = 1.5
        // xx+=0.5

        res.data.forEach(r => {
          doc.text(1, y + 0.4, '_____________________________________________________________________________________________________')
          doc.setFont(undefined, 'bold')
          //doc.text(1,y,  'CINIT')
          //doc.text(2.5,y,  'CLIENTE')
          //doc.text(6.5,y,  'COMANDA')
          doc.setFont(undefined, 'normal')

          doc.text(1, y + 0.4, r.Id)
          doc.text(3.5, y + 0.4, r.Nombres.substring(0, 20))
          doc.text(8, y + 0.4, r.NroPed + '')
          doc.text(9.5, y + 0.4, r.cod_prod)
          doc.text(11.5, y + 0.4, r.Producto.substring(0, 25))
          doc.text(17, y + 0.4, r.Cant + '')
          doc.text(18.5, y + 0.4, r.precio + '')
          doc.text(20, y + 0.4, r.Observaciones == null ? '' : r.Observaciones + '')
          y += 0.5
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
        })
        doc.save("COMAD -" + date.formatDate(Date.now(), 'DD-MM-YYYY') + ".pdf");
        //window.open(doc.output('bloburl'), '_blank');
      })
    },

    imprimircomanda2() {
      this.$q.loading.show()
      let mc = this
      let nom = '';
      this.$api.post('listcomanda', {fecha1: this.fecha1, fecha2: this.fecha2}).then(res => {
        this.$q.loading.hide()
        console.log(res.data)

        function header1(nrop, nombre, direccion, fec) {
          doc.setFont(undefined, 'bold')
          doc.text(100, 5, 'SOLICITUD DE PEDIDO ')
          doc.text(10, 10, 'NroPed ' + nrop)
          doc.text(10, 15, 'Cliente :' + nombre)
          doc.text(10, 20, 'Direccion: ' + direccion)
          doc.text(150, 10, 'Fec Pedido' + fec)
        }

        function header() {

          doc.text(10, y + 10, 'COD PROD')
          doc.text(30, y + 10, 'PRODUCTO')
          doc.text(80, y + 10, 'CANT')
          doc.text(95, y + 10, 'PRECIO')
          doc.text(115, y + 10, 'OBSERVACION')
          doc.setFont(undefined, 'normal')
        }

        var doc = new jsPDF('l', 'mm', [148, 210])
        // console.log(dat);
        doc.setFont("courier");
        doc.setFontSize(9);
        let y = 15
        // xx+=0.5
        let comanda = res.data[0].NroPed
        header1(res.data[0].NroPed, res.data[0].Nombres, res.data[0].Direccion, res.data[0].fecha)
        header()
        y = 25

        res.data.forEach(r => {
          if (comanda != r.NroPed) {
            doc.addPage();
            header1(r.NroPed, r.Nombres, r.Direccion, r.fecha)
            y = 15
            header()
            y += 10
            comanda = r.NroPed

          }

          doc.setFont(undefined, 'normal')

          doc.text(10, y + 4, r.cod_prod)
          doc.text(30, y + 4, r.Producto)
          doc.text(80, y + 4, r.Cant + '')
          doc.text(95, y + 4, r.precio + '')
          doc.text(115, y + 4, r.Observaciones == null ? '' : r.Observaciones + '')
          y += 5
          if (y + 30 > 140) {
            doc.addPage();
            header()
            y = 5
          }
        })
        doc.save("COMAD -" + date.formatDate(Date.now(), 'DD-MM-YYYY') + ".pdf");
        //window.open(doc.output('bloburl'), '_blank');
      })
    },

    imprimircerdo() {
      this.$q.loading.show()
      let mc = this
      let nom = '';
      this.$api.post('cerdo', {fecha1: this.fecha1, fecha2: this.fecha2}).then(res => {
        this.$q.loading.hide()
        console.log(res.data)

        function header() {
          doc.setFont(undefined, 'bold')
          doc.text(10, 0.5, 'PEDIDOS DE CERDO ')
          doc.text(1, 1, 'NOMBRE ' + mc.$store.getters["login/user"].Nombre1)
          doc.text(12, 1, 'DE ' + mc.fecha1 + ' AL ' + mc.fecha2)
          doc.text(9, 1, 'No')

          doc.setLineWidth(0.1);
          doc.line(1, 1.1, 21, 1.1);

          doc.text(1, 1.5, 'CINIT')
          doc.text(3.5, 1.5, 'CLIENTE')
          doc.text(8, 1.5, 'NPED')
          doc.text(9.5, 1.5, 'PRECIO')
          doc.text(11.5, 1.5, 'TOTAL')
          doc.text(13, 1.5, 'ENTERO')
          doc.text(14.5, 1.5, 'DESMEM')
          doc.text(16, 1.5, 'CORTE')
          doc.text(17.5, 1.5, 'CKILO')
          doc.text(19, 1.5, 'OBSERVACION')
          doc.setFont(undefined, 'normal')
        }

        var doc = new jsPDF('L', 'cm', 'legal')
        // console.log(dat);
        doc.setFont("courier");
        doc.setFontSize(9);
        // var x=0,y=
        header()
        // let xx=x
        // let yy=y
        let y = 1.5
        // xx+=0.5

        res.data.forEach(r => {
          doc.text(1, y + 0.4, '_____________________________________________________________________________________________________')
          doc.setFont(undefined, 'bold')
          //doc.text(1,y,  'CINIT')
          //doc.text(2.5,y,  'CLIENTE')
          //doc.text(6.5,y,  'COMANDA')
          doc.setFont(undefined, 'normal')

          doc.text(1, y + 0.4, r.Id)
          doc.text(3.5, y + 0.4, r.Nombres.substring(0, 20))
          doc.text(8, y + 0.4, r.NroPed + '')
          doc.text(9.5, y + 0.4, r.precio + '')
          doc.text(11.5, y + 0.4, r.total == null ? '' : r.total + '')
          doc.text(13, y + 0.4, r.entero == null ? '' : r.entero + '')
          doc.text(14.5, y + 0.4, r.desmembre == null ? '' : r.desmembre + '')
          doc.text(16, y + 0.4, r.corte == null ? '' : r.corte + '')
          doc.text(17.5, y + 0.4, r.kilo == null ? '' : r.kilo + '')
          doc.text(19, y + 0.4, r.Observaciones == null ? '' : r.Observaciones + '')
          y += 0.5
          if (y + 3 > 21) {
            doc.addPage();
            header()
            y = 1.5
          }
        })

        doc.save("Cerdo -" + date.formatDate(Date.now(), 'DD-MM-YYYY') + ".pdf");
        //window.open(doc.output('bloburl'), '_blank');
      })
    },

    generarpollo() {
      // this.$api.post('excel',{fecha1:this.fecha1,fecha2:this.fecha2}).then(res=>{
      //   console.log(res.data)
      // })
      this.imprimirpollo()
      //   $('#example').DataTable().destroy();
      //
      // this.$api.post('rpollo',{fecha1:this.fecha1,fecha2:this.fecha2}).then(res=>{
      //   console.log(res.data)
      //   $('#example').DataTable().destroy();
      //   this.pollo=res.data;
      //     $('#example').DataTable( {
      //       dom: 'Blfrtip',
      //       buttons: [
      //         'copy', 'csv', 'excel', 'pdf', 'print'
      //       ],
      //        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
      //     } );
      //   })
      //   this.dialog_pollo=true
    },

    generarres() {
      this.imprimires()
      //   $('#example2').DataTable().destroy();
      //
      // this.$api.post('rres',{fecha1:this.fecha1,fecha2:this.fecha2}).then(res=>{
      //
      //   console.log(res.data)
      //   $('#example2').DataTable().destroy();
      //   this.res=res.data;
      //     $('#example2').DataTable( {
      //       dom: 'Blfrtip',
      //       buttons: [
      //         'copy', 'csv', 'excel', 'pdf', 'print'
      //       ],
      //        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
      //     } );
      //   })
      //   this.dialog_res=true
    },

    generarcerdo() {
      this.imprimircerdo()
      //   $('#example3').DataTable().destroy();
      //
      // this.$api.post('rcerdo',{fecha1:this.fecha1,fecha2:this.fecha2}).then(res=>{
      //
      //   console.log(res.data)
      //   $('#example3').DataTable().destroy();
      //   this.cerdo=res.data;
      //     $('#example3').DataTable( {
      //       dom: 'Blfrtip',
      //       buttons: [
      //         'copy', 'csv', 'excel', 'pdf', 'print'
      //       ],
      //        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
      //     } );
      //   })
      //   this.dialog_cerdo=true
    },
    generarcomanda() {
      this.imprimircomanda2()

    },

    tecleado(e) {
      e.subtotal = (e.cantidad * e.precio).toFixed(2);
    },
    enviarpedidos() {
      this.$q.loading.show()
      this.$api.post('enviarpedidos', {clientes: this.clientes}).then(res => {        // console.log(res.data)
        this.$q.loading.hide()
        // this.modalpedido=false
        this.misclientes()
      })
    },
    expedidos() {
      this.$q.loading.show()
      this.$api.post('export', {fecha1: this.fecha1, fecha2: this.fecha2}).then(res => {
        console.log(res.data)
        this.$q.loading.hide()
        this.$q.notify({
          color: 'green',
          message: 'Enviado correctamente',
          icon: 'send'
        })
      }).catch(err => {
        this.$q.loading.hide()
        this.$q.notify({
          color: 'red',
          message: err.response.data.message,
          icon: 'error'
        })
      })
    },
    enviarcomanda() {
      this.$q.loading.show()
      this.$api.post('envpedido', {NroPed: this.cliente.NroPed}).then(res => {
        this.modalpedido = false
        this.$q.loading.hide()
        this.misclientes()
      }).catch(err => {
        this.$q.loading.hide()
        this.$q.notify({
          color: 'red',
          message: err.response.data.message,
          icon: 'error',
          position: "top"
        })
      })
    },

    modificarcomanda() {
      // console.log(this.misproductos)
      console.log(this.cliente)
      this.$q.loading.show()
      this.$api.post('updatecomanda', {
        comanda: this.cliente.NroPed,
        idCli: this.cliente.cliente.Cod_Aut,
        bonificacion: this.bonificacion,
        bonificacionAprovacion: this.bonificacionAprovacion,
        bonificacionId: this.bonificacionId,
        productos: this.misproductos,
        pago: this.pago,
        fact: this.fact,
        fecha: this.fecha,
        horario: this.horario
      }).then(res => {
        // console.log(res.data)
        this.pago = 'CONTADO'
        this.fact = 'NO'
        this.$q.loading.hide()
        this.modalpedido = false
        this.misclientes()
      })
    },
    eliminarcomanda() {
      this.$api.post('deletecomanda', {comanda: this.cliente.NroPed}).then(res => {
        this.modalpedido = false
        this.misclientes()
      })
    },
    imprimirboleta(comanda1) {

      this.$api.post('rnormal', {comanda: comanda1.NroPed}).then(res => {
        console.log(res.data)
        let tot = 0
        let cadena = '<div>COMANDA: ' + comanda1.NroPed + '</div>'
        cadena += '<div>CLIENTE: ' + comanda1.Nombres + '</div>'
        cadena += '<table><tr><th>CODIGO</th><th>PRODUCTO</th><th>CANTIDAD</th><th>PRECIO</th><th>SUBTOTAL</th><th>OBSERVACION</th></tr>'
        res.data.forEach(r => {
          tot = tot + parseFloat(r.subtotal)
          cadena += '<tr><td>' + r.cod_prod + '</td><td>' + r.Producto + '</td><td>' + r.Cant + '</td><td>' + r.precio + '</td><td>' + r.subtotal + '</td><td>' + (r.Observaciones == null ? '' : r.Observaciones) + '</td></tr>'

        });
        cadena += '<tr><td></td><td></td><td></td><td>TOTAL</td><td>' + tot + '</td><td></td></tr>'

        cadena += '</table>'
        let myWindow = window.open("", "Imprimir", "width=1000,height=1000");
        myWindow.document.write(cadena);
        myWindow.document.close();
        myWindow.print();
        myWindow.close();
      })


    },
    agregarpedido() {
      if (this.producto.Producto == undefined) {
        this.$q.notify({
          message: "No seleccionaste productos",
          color: "red",
          icon: "error"
        })
        return false
      }
      // console.log(this.cliente)
      this.misproductos.push({
        trozado: '',
        pierna: '',
        brazo: '',
        total: '',
        entero: '',
        desmembre: '',
        corte: '',
        kilo: '',
        observacion: '',
        cbrasa5: '',
        ubrasa5: '',
        bsbrasa5: '',
        obsbrasa5: '',
        cbrasa6: '',
        cubrasa6: '',
        bsbrasa6: '',
        obsbrasa6: '',
        c104: '',
        u104: '',
        bs104: '',
        obs104: '',
        c105: '',
        u105: '',
        bs105: '',
        obs105: '',
        c106: '',
        u106: '',
        bs106: '',
        obs106: '',
        c107: '',
        u107: '',
        bs107: '',
        obs107: '',
        c108: '',
        u108: '',
        bs108: '',
        obs108: '',
        c109: '',
        u109: '',
        bs109: '',
        obs109: '',
        rango: '',
        ala: '',
        bsala: '',
        obsala: '',
        cadera: '',
        bscadera: '',
        obscadera: '',
        pecho: '',
        bspecho: '',
        obspecho: '',
        pie: '',
        bspie: '',
        obspie: '',
        filete: '',
        bsfilete: '',
        obsfilete: '',
        cuello: '',
        bscuello: '',
        obscuello: '',
        hueso: '',
        bshueso: '',
        obshueso: '',
        menu: '',
        bsmenu: '',
        obsmenu: '',
        unidala: 'KG',
        unidcadera: 'KG',
        unidpecho: 'KG',
        unidpie: 'KG',
        unidfilete: 'KG',
        unidcuello: 'KG',
        unidhueso: 'KG',
        unidmenu: 'KG',
        bs: '',
        bs2: '',
        contado: '',
        pfrial: '',

        tipo: this.producto.tipo,
        nombre: this.producto.Producto,
        cod_prod: this.producto.cod_prod,
        precio: parseFloat(this.producto.Precio).toFixed(2),
        cantidad: 1,
        subtotal: parseFloat(this.producto.Precio).toFixed(2)
      })
    },

    filterFn(val, update) {
      if (val === '') {
        update(() => {
          this.productos = this.productos2
        })
        return
      }
      update(() => {
        const needle = val.toLowerCase()
        this.productos = this.productos2.filter(v => v.label.toLowerCase().indexOf(needle) > -1)
      })
    },
    agregar(producto) {
      producto.cantidad = parseFloat(producto.cantidad) + 1
      producto.subtotal = (producto.cantidad * parseFloat(producto.precio)).toFixed(2)
    },
    quitar(producto, index) {
      if (parseFloat(producto.cantidad) == 1) {
        this.misproductos.splice(index, 1);
      } else {
        producto.cantidad = parseFloat(producto.cantidad) - 1
        producto.subtotal = (producto.cantidad * parseFloat(producto.precio)).toFixed(2)
      }
    },
    listpedidos(cliente) {
      this.cliente = cliente
      console.log(this.cliente)
      // this.$q.loading.show()
      this.loading = true
      this.$api.post('listpedido', {NroPed: cliente.NroPed, fecha1: this.fecha1, fecha2: this.fecha2})
        .then(res => {
          console.log(res.data)
          // return false
          this.pago = res.data[0].pago
          this.fact = res.data[0].fact
          this.horario = res.data[0].horario
          this.coment = res.data[0].comentario
          this.fecha = date.formatDate(res.data[0].fecha, 'YYYY-MM-DD')
          this.misproductos = res.data[0].pedidos
          this.modalpedido = true
          this.bonificacion = res.data[0].bonificacion
          this.bonificacionAprovacion = res.data[0].bonificacionAprovacion
          this.bonificacionId = res.data[0].bonificacionId
          this.$q.loading.hide()
        }).finally(() => {
        this.loading = false
      })
    },
    seleccionartipo(m) {
      // console.log(m)
      this.miproducto = m
      if (this.miproducto.tipo == 'NORMAL') {
        this.modalnormal = true
      } else if (this.miproducto.tipo == 'POLLO') {
        this.modalpollo = true
      } else if (this.miproducto.tipo == 'CERDO') {
        this.modalcerdo = true
      } else if (this.miproducto.tipo == 'RES') {
        this.modalres = true
      } else {
      }
    },

    misclientes() {
      this.$q.loading.show()
      this.$api.post('clientepedido', {fecha1: this.fecha1, fecha2: this.fecha2}).then(res => {
        // console.log(res.data)
        this.clientes = res.data
        this.$q.loading.hide()
      })
    },
  },
  computed: {
    total() {
      let total = 0
      this.misproductos.forEach(r => {
        total += parseFloat(r.subtotal)
      })
      return total.toFixed(2)
    }
  },
}
</script>

<style scoped>

</style>
