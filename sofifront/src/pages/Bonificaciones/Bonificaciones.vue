<template>
  <q-page class="q-pa-md">
    <div class="row">
      <div class="col-12 col-md-8">
        <q-input
          v-model="fecha"
          type="date"
          label="Filtrar por Fecha"
          @change="cargarBonificaciones"
          outlined
          :loading="loading"
          dense
        />
      </div>
      <div class="col-12 col-md-4 flex flex-center">
        <q-btn
          label="Recargar"
          color="primary"
          @click="cargarBonificaciones"
          icon="refresh"
          no-caps
          :loading="loading"
        />
      </div>
    </div>

    <q-table
      :rows="bonificaciones"
      :columns="columns"
      row-key="codAut"
      title="Cambios Pendientes"
      dense
      :rows-per-page-options="[0]"
      wrap-cells
      flat
      bordered
    >
      <template v-slot:body-cell-aprobar="props">
        <q-td :props="props">
<!--          btn derodown-->
<!--          <pre>{{props.row.bonificacion}}</pre>-->
          <q-btn-dropdown
            dense
            size="md"
            no-caps
            color="primary"
            label="Acciones"
            :loading="loading">
            <q-list>
              <q-item clickable @click="aprobar(props.row)" v-close-popup v-if="!props.row.bonificacion">
                <q-item-section avatar>
                  <q-icon name="check_circle_outline" />
                </q-item-section>
                <q-item-section>
                  Aprobar
                </q-item-section>
              </q-item>
              <q-item clickable @click="listpedidos(props.row)" v-close-popup>
                <q-item-section avatar>
                  <q-icon name="list" />
                </q-item-section>
                <q-item-section>
                  Ver Pedidos
                </q-item-section>
              </q-item>
            </q-list>
          </q-btn-dropdown>
        </q-td>
      </template>
      <template v-slot:body-cell-productos="props">
        <q-td :props="props">
<!--          <pre>{{props.row.productos}}</pre>-->
          <div v-for="(producto, index) in props.row.productos" :key="index">
            {{ producto.producto }} ({{ producto.cantidad }})
          </div>
        </q-td>
      </template>
    </q-table>
<!--    <pre>{{bonificaciones}}</pre>-->
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
<!--          <div style="display: flex; justify-content: space-between; width: 100%;">-->
<!--            <q-btn label="Clonar" color="green" @click="clonarpedido" :loading="loading" no-caps icon="content_copy"/>-->
<!--            <q-btn flat label="cerrar" color="negative" v-close-popup/>-->
<!--          </div>-->
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
  </q-page>
</template>

<script>
import moment from 'moment';
import {date} from "quasar";

export default {
  data() {
    return {
      loading: false,
      fecha: moment().format('YYYY-MM-DD'),
      bonificaciones: [],
      columns: [
        {
          name: 'aprobar',
          label: 'Acción',
          field: 'aprobar',
          align: 'center'
        },
        {
          name: 'codAut',
          label: 'Código',
          field: 'NroPed',
          align: 'left'
        },
        {
          name: 'cliente',
          label: 'Cliente',
          field: row => row.cliente?.Nombres || '',
          align: 'left'
        },
        {
          name: 'usuario',
          label: 'Usuario',
          field: 'usuario',
          align: 'left'
        },
        {
          name: 'productos',
          label: 'Productos',
          field: 'productos',
          align: 'left'
        },
        // {
        //   name: 'canttxt',
        //   label: 'Cantidad',
        //   field: 'Canttxt',
        //   align: 'center'
        // },
        {
          name: 'comentario',
          label: 'Comentario',
          field: 'comentario',
          align: 'left'
        },
        {
          name: 'aprobado',
          label: 'Aprobado',
          field: row => row.aprobacion || '—',
          align: 'left'
        },
        // clienteBonificacion
        {
          name: 'clienteBonificacion',
          label: 'Tipo',
          field: row => row.clienteBonificacion || '—',
          align: 'left'
        },
      ],
      modalpedido: false,
      modalpollo: false,
      modalnormal: false,
      modalcerdo: false,
      modalres: false,
      dialog_pollo: false,
      dialog_res: false,
      dialog_cerdo: false,
      cliente: {},
      pago: 'CONTADO',
      fact: 'NO',
      horario: '',
      coment: '',
      fecha1: moment().subtract(30, 'days').format('YYYY-MM-DD'),
      fecha2: moment().format('YYYY-MM-DD'),
      fechamenos: moment().subtract(1, 'days').format('YYYY-MM-DD'),
      productos: [],
      producto: null,
      misproductos: [],
      columnsproducto: [
        {label: 'subtotal', name: 'subtotal', field: 'subtotal'},
        {label: 'cantidad', name: 'cantidad', field: 'cantidad'},
        {label: 'precio', name: 'precio', field: 'precio', align: 'left'},
        {label: 'cod_prod', name: 'cod_prod', field: 'cod_prod', align: 'left'},
        {label: 'nombre', name: 'nombre', field: 'nombre', align: 'left'},
        {label: 'observacion', name: 'observacion', field: 'observacion', align: 'left'},
      ],
      filteproducto: '',
      total: 0,
      horarios: ['08:00 - 10:00', '10:00 - 12:00', '12:00 - 14:00', '14:00 - 16:00', '16:00 - 18:00'],
    };
  },
  methods: {
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
          this.$q.loading.hide()
        }).finally(() => {
        this.loading = false
      })
    },
    async cargarBonificaciones() {
      this.loading = true;
      try {
        const response = await this.$api.get('/bonificaciones', {
          params: { fecha: this.fecha }
        });
        this.bonificaciones = response.data;
      } catch (error) {
        this.$q.notify({
          message: 'Error al cargar bonificaciones',
          color: 'negative'
        });
      } finally {
        this.loading = false;
      }
    },
    async aprobar(pedido) {
      this.$q.dialog({
        title: 'Confirmar Aprobación',
        message: `¿Está seguro de aprobar la bonificación del pedido ${pedido.NroPed} de ${pedido.cliente?.Nombres || 'Cliente Desconocido'}?`,
        cancel: true,
        persistent: true
      }).onOk(async () => {
        this.loading = true;
        try {
          await this.$api.post(`/bonificacioneAprobar`, {
            NroPed: pedido.NroPed
          });
          this.$q.notify({
            message: 'Bonificación aprobada',
            color: 'positive'
          });
          this.cargarBonificaciones();
        } catch (error) {
          const msg = error.response?.data?.message || 'Error al aprobar';
          this.$q.notify({
            message: msg,
            color: 'negative'
          });
        }
      });
    },
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
  },
  mounted() {
    this.cargarBonificaciones();
  }
};
</script>
