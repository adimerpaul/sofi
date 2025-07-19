<template>
  <div style="height: 350px; width: 100%;">
    <l-map
      @ready="onReady"
      @locationfound="onLocationFound"
      v-model="zoom"
      :zoom="zoom"
      :center="center"
      @move="log('move')"

    >
<!--      @click="ubicacion"-->
      <l-tile-layer
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      ></l-tile-layer>
<!--      <l-control-layers />-->
<!--      <l-marker :lat-lng="[-17.970491, -67.113511]" draggable @moveend="log('moveend')">-->
<!--        <l-tooltip>-->
<!--          lol-->
<!--        </l-tooltip>-->
<!--      </l-marker>-->

      <l-marker @click="clickopciones(c)" v-for="c in clientes" :key="c.Cod_Aut" :lat-lng="[c.Latitud, c.longitud]"  >
        <l-icon><q-badge  :class="c.tipo=='PEDIDO'?'bg-green-5  text-italic':c.tipo=='PARADO'?'bg-yellow-5  text-italic':c.tipo=='NO PEDIDO'?'bg-red-5 text-italic':''"  class="q-pa-none" color="info" >{{c.Cod_Aut}}</q-badge></l-icon>
      </l-marker>
      <l-marker :lat-lng="center"  >
<!--        <l-icon><q-badge color="info">{{c.Cod_Aut}}</q-badge></l-icon>-->
      </l-marker>

<!--      <l-marker :lat-lng="[50, 50]" draggable @moveend="log('moveend')">-->
<!--        <l-popup>-->
<!--          lol-->
<!--        </l-popup>-->
<!--      </l-marker>-->

<!--      <l-polyline-->
<!--        :lat-lngs="[-->
<!--          [47.334852, -1.509485],-->
<!--          [47.342596, -1.328731],-->
<!--          [47.241487, -1.190568],-->
<!--          [47.234787, -1.358337],-->
<!--        ]"-->
<!--        color="green"-->
<!--      ></l-polyline>-->
<!--      <l-polygon-->
<!--        :lat-lngs="[-->
<!--          [46.334852, -1.509485],-->
<!--          [46.342596, -1.328731],-->
<!--          [46.241487, -1.190568],-->
<!--          [46.234787, -1.358337],-->
<!--        ]"-->
<!--        color="#41b782"-->
<!--        :fill="true"-->
<!--        :fillOpacity="0.5"-->
<!--        fillColor="#41b782"-->
<!--      />-->
<!--      <l-rectangle-->
<!--        :lat-lngs="[-->
<!--          [46.334852, -1.509485],-->
<!--          [46.342596, -1.328731],-->
<!--          [46.241487, -1.190568],-->
<!--          [46.234787, -1.358337],-->
<!--        ]"-->
<!--        :fill="true"-->
<!--        color="#35495d"-->
<!--      />-->
<!--      <l-rectangle-->
<!--        :bounds="[-->
<!--          [46.334852, -1.190568],-->
<!--          [46.241487, -1.090357],-->
<!--        ]"-->
<!--      >-->
<!--        <l-popup>-->
<!--          lol-->
<!--        </l-popup>-->
<!--      </l-rectangle>-->
    </l-map>
<!--    <button @click="changeIcon">New kitten icon</button>-->
    <div class="row">
      <div class="col-12">
        <q-table :rows="clientes" hide-header :filter="filter" :columns="columns" :rows-per-page-options="[0]" class="my-sticky-header-table">
          <template v-slot:body-cell-Cod_Aut="props">
            <q-td :class="props.row.tipo=='PEDIDO'?'bg-green-3  text-italic':props.row.tipo=='PARADO'?'bg-yellow-3  text-italic':props.row.tipo=='NO PEDIDO'?'bg-red-3 text-italic':''" @click="clickopciones(props.row)" :props="props">
                <q-badge  color="info"> {{ props.row.Cod_Aut}}</q-badge>
            </q-td>
          </template>
          <template v-slot:body-cell-Nombres="props">
            <q-td :class="props.row.tipo=='PEDIDO'?'bg-green-3  text-italic':props.row.tipo=='PARADO'?'bg-yellow-3  text-italic':props.row.tipo=='NO PEDIDO'?'bg-red-3 text-italic':''" @click="clickopciones(props.row)" :props="props">
              <div class="text-weight-medium">{{ props.row.Nombres}}</div>
              <div class="text-caption" style="font-size: 8px">{{ props.row.Direccion}}</div>
            </q-td>
          </template>
          <template v-slot:body-cell-opcion="props">
            <q-td :class="props.row.tipo=='PEDIDO'?'bg-green-3  text-italic':props.row.tipo=='PARADO'?'bg-yellow-3  text-italic':props.row.tipo=='NO PEDIDO'?'bg-red-3 text-italic':''" :props="props">
              <q-btn @click="clickclientes(props.row)" icon="my_location" size="xs" color="accent"  />
            </q-td>
          </template>
          <template v-slot:top-right>
            <div class="row">
              <div class="col-2 flex flex-center"   >
                <q-btn icon="my_location" size="xs" color="primary"  @click="getUserPosition" :loading="loading"/>
              </div>
              <div class="col-2 flex flex-center"  >
                <q-btn icon="my_location" size="xs" color="secondary" @click="getCentro" :loading="loading"/>
              </div>
              <div class="col-8">
                <q-input filled dense v-model="filter" placeholder="Buscar Cliente" clearable>
                  <template v-slot:append>
                    <q-icon name="search" />
                  </template>
                </q-input>
              </div>
            </div>
          </template>
                    <template v-slot:top-left>
            <div class="row">
              <div class="col-6 "  @click="misclientes" >
                <q-btn icon="list" size="xs" color="orange" :loading="loading"/>
              </div>
              <div class="col-6 " @click="listhoy" >
                <q-btn icon="today" size="xs" color="cyan" :loading="loading"/>
              </div>

            </div>
          </template>
<!--          <template v-slot:top>-->
<!--            a-->
<!--          </template>-->
        </q-table>
<!--        {{center}}-->
      </div>
    </div>

    <q-dialog full-width v-model="modalopciones">
      <q-card >
        <q-card-section>
          <div class="row">
          <div class="col-md-6 col-xs-12"><div class="text-subtitle2 ">{{cliente.Cod_Aut}} {{cliente.Nombres}} </div>
          <div class="text-subtitle2">Cel: {{cliente.Telf}}</div>
          <div class="text-subtitle2">{{cliente.Direccion}}</div>
          <div class="text-subtitle2">{{cliente.Canal}}</div>
          <div class="text-subtitle2" >Monto Deuda: <q-badge color="negative">{{cliente.totdeuda}} Bs </q-badge> NumPedidos: <q-badge color="negative">{{cliente.cantdeuda}}</q-badge></div>
          <div class="text-subtitle2" >Fecha minima: <q-badge color="negative">{{cliente.fechaminima}} </q-badge> </div>
          <div class="text-h5" >Estado para pedidos: <q-badge :color="cliente.venta=='ACTIVO'?'green':'negative'" class="text-h5">{{cliente.venta}} </q-badge> </div>


        </div>
        <div class="col-md-6 col-xs-12"><q-input dense outlined v-model="comentario.observacion" label="Comentario" >
          <template v-slot:after>
            <q-btn round dense flat icon="edit" @click="modcomentario"/>
          </template>
      </q-input></div></div>

        </q-card-section>
        <q-card-section class="q-pt-none">
<!--          <pre>{{cliente}}</pre>-->
          <div class="row">
            <div class="col-12 col-sm-6" >
              <q-btn :loading="loading" class="q-ma-xs" @click="clickpedido" label="realizar pedido" color="positive" style="width: 100%;" icon="shopping_cart" />
            </div>
            <div class="col-12 col-sm-6">
              <q-btn :loading="loading" class="q-ma-xs" @click="clickretornar" label="retornar" color="warning" style="width: 100%;" icon="schedule" />
            </div>
            <div class="col-12 col-sm-6">
              <q-btn :loading="loading" class="q-ma-xs" @click="clicknopedido" label="no pedido" color="negative" style="width: 100%;" icon="highlight_off" />
            </div>
            <div class="col-12 col-sm-6">
              <q-btn :loading="loading" class="q-ma-xs" label="generar ruta" color="accent" style="width: 100%;" icon="maps" type="a" target="_blank" :href="'https://www.google.com.bo/maps/place/'+cliente.Latitud+','+cliente.longitud+'/@'+cliente.Latitud+','+cliente.longitud+',17z/data=!3m1!4b1!4m6!3m5!1s0x0:0xeda9371aeb8c1574!7e2!8m2!3d-17.981432!4d-67.1061122?hl=es'"/>
            </div>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="bg-white text-teal">
          <q-btn flat label="OK" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
    <q-dialog full-width full-height v-model="modalpedido" persistent>
      <q-card >
        <q-card-section>
          <div class="row">
            <div class="col-12 row items-center">
              {{cliente.Cod_Aut}} {{cliente.Nombres}}
              <q-space/>
              <q-btn icon="close" flat @click="modalpedido=false" class="q-ma-xs" color="negative" />
            </div>
          <!-- <div class="text-bold col-6 flex flex-center">
             -- <div class="q-gutter-sm col-md-6 col-sm-12" >
                <q-radio  dense v-model="pago" val="CONTADO" label="Contado" />
                <q-radio  dense v-model="pago" val="CREDITO" label="Credito" />
--              </div>
          </div>-->
          <div class="col-md-6 col-xs-6">
<!--            <q-select dense outlined v-model="pago" :options="tipopagos" label="Tip Pagos" />-->
            <div><q-radio v-model="pago" checked-icon="task_alt" dense unchecked-icon="panorama_fish_eye" val="CONTADO" label="Contado" /></div>
            <div><q-radio v-model="pago" checked-icon="task_alt" dense unchecked-icon="panorama_fish_eye" val="PAGO QR" label="Pago QR" /></div>
            <div><q-radio v-model="pago" checked-icon="task_alt" dense unchecked-icon="panorama_fish_eye" val="CREDITO" label="Credito" /></div>
            <div><q-radio v-model="pago" checked-icon="task_alt" dense unchecked-icon="panorama_fish_eye" val="BOLETA ANTERIOR" label="Boleta anterior" /></div>
          </div>
          <div class="col-md-6 col-xs-6">
            <q-toggle
            :label="fact+' FACTURA'"
            color="green"
            false-value="NO"
            true-value="SI"
            v-model="fact"/>
            </div>
            <div class="col-md-6 col-xs-6">
              <q-input label="Fecha" v-model="fecha" type="date" dense outlined :min="fechamenos" />
            </div>
            <div class="col-md-6 col-xs-6"><q-select dense outlined v-model="horario" :options="horarios" label="Horario" /></div>
            <div class="col-12"><q-input dense outlined v-model="coment" label="Comentario" /></div>
          </div>
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
              <q-btn  class="q-pa-xs q-ma-none" color="primary" icon="add_circle" @click="agregarpedido"/>
            </div>
            <div class="col-12">
              <q-table dense :rows="misproductos"  :filter="filteproducto" :columns="columnsproducto" :rows-per-page-options="[0]"
                       style="height: 300px">
              >
                <template v-slot:body-cell-subtotal="props" >
                  <q-td :props="props" auto-width >
                    <q-btn flat @click="seleccionartipo(props.row)" class="q-ma-none q-pa-none" color="accent" icon="tune"/>
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
                    <div class="col-1 text-bold flex flex-center" >
                      {{misproductos.length}}
                    </div>
                    <div class="col-11">
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
<!--                      <div class="text-subtitle2">Cantidad de pedidos: {{misproductos.length}}</div>-->
                    </q-td>
                  </q-tr>
                </template>
              </q-table>
              <q-btn @click="enviarpedido" style="width: 100%" label="Realizar pedido" icon="send" color="positive" :loading="loading"/>
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
            <div class="col-6" ><q-input type="number" dense outlined label="Cja b5" v-model="miproducto.cbrasa5"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Uni b5" v-model="miproducto.ubrasa5"/></div>

            <div class="col-6" ><q-input type="number" dense outlined label="Cja b6" v-model="miproducto.cbrasa6"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Uni b6" v-model="miproducto.cubrasa6"/></div>

            <div class="col-6" ><q-input type="number" dense outlined label="Cja-104(1.5-1.7)" v-model="miproducto.c104"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Unid-104" v-model="miproducto.u104"/></div>

            <div class="col-6" ><q-input type="number" dense outlined label="Cja-105(1.7-1.9)" v-model="miproducto.c105"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Unid-105" v-model="miproducto.u105"/></div>

            <div class="col-6" ><q-input type="number" dense outlined label="Cja-106(1.9-2.2)" v-model="miproducto.c106"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Unid-106" v-model="miproducto.u106"/></div>

            <div class="col-6" ><q-input type="number" dense outlined label="Cja-107(2.2-2.5)" v-model="miproducto.c107"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Unid-107" v-model="miproducto.u107"/></div>

            <div class="col-6" ><q-input type="number" dense outlined label="Cja-108(2.6-2.7)" v-model="miproducto.c108"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Unid-108" v-model="miproducto.u108"/></div>

            <div class="col-6" ><q-input type="number" dense outlined label="Cja-109(2.8-2.9)" v-model="miproducto.c109"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="Unid-109" v-model="miproducto.u109"/></div>

            <div class="col-6" ><q-input type="number" dense outlined label="Rango Pollo (unidades)" v-model="miproducto.rango"/></div>
            <div class="col-6" ></div>

            <div class="col-6" ><q-input type="number" dense outlined label="ala" v-model="miproducto.ala"/></div>
            <div class="col-6">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidala" label="Unidad" />
            </div>

            <div class="col-6" ><q-input type="number" dense outlined label="cadera" v-model="miproducto.cadera"/></div>
            <div class="col-6">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidcadera" label="Unidad" />
            </div>

            <div class="col-6" ><q-input type="number" dense outlined label="pecho" v-model="miproducto.pecho"/></div>
            <div class="col-6">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidpecho" label="Unidad" />
            </div>

            <div class="col-6" ><q-input type="number" dense outlined label="pi/mu" v-model="miproducto.pie"/></div>
            <div class="col-6">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidpie" label="Unidad" />
            </div>

            <div class="col-6" ><q-input type="number" dense outlined label="filete" v-model="miproducto.filete"/></div>
            <div class="col-6">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidfilete" label="Unidad" />
            </div>

            <div class="col-6" ><q-input type="number" dense outlined label="cuello" v-model="miproducto.cuello"/></div>
            <div class="col-6">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidcuello" label="Unidad" />
            </div>

            <div class="col-6" ><q-input type="number" dense outlined label="hueso" v-model="miproducto.hueso"/></div>
            <div class="col-6">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidhueso" label="Unidad" />
            </div>

            <div class="col-6" ><q-input type="number" dense outlined label="menudencia" v-model="miproducto.menu"/></div>
            <div class="col-6">
              <q-select dense outlined :options="['KG','CJA','U']" v-model="miproducto.unidmenu" label="Unidad" />
            </div>
            <div class="col-6" ><q-input type="number" dense outlined label="BS" v-model="miproducto.bs"/></div>
            <div class="col-6" ><q-input type="number" dense outlined label="BS2" v-model="miproducto.bs2"/></div>
            <div class="col-12" ><q-input type="text" dense outlined label="OBSERVACION" v-model="miproducto.observacion"/></div>


<!--            <div class="col-5" ><q-input type="number" dense outlined label="Cadera" v-model="miproducto.cadera"/></div>-->
<!--            <div class="col-7">-->
<!--              <div class="q-gutter-sm">-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidcadera" val="KG" label="KG" />-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidcadera" val="CJA" label="CJA" />-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidcadera" val="U" label="U" />-->
<!--              </div>-->
<!--            </div>-->
<!--            <div class="col-5" ><q-input type="number" dense outlined label="Pecho" v-model="miproducto.pecho"/></div>-->
<!--                        <div class="col-7">-->
<!--              <div class="q-gutter-sm">-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidpecho" val="KG" label="KG" />-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidpecho" val="CJA" label="CJA" />-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidpecho" val="U" label="U" />-->
<!--              </div>-->
<!--            </div>-->
<!--            <div class="col-5" ><q-input type="number" dense outlined label="Pi/Mu" v-model="miproducto.pie"/></div>-->
<!--                        <div class="col-7">-->
<!--              <div class="q-gutter-sm">-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidpie" val="KG" label="KG" />-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidpie" val="CJA" label="CJA" />-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidpie" val="U" label="U" />-->
<!--              </div>-->
<!--            </div>-->
<!--            <div class="col-5" ><q-input type="number" dense outlined label="Filete" v-model="miproducto.filete"/></div>-->
<!--                        <div class="col-7">-->
<!--              <div class="q-gutter-sm">-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidfilete" val="KG" label="KG" />-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidfilete" val="CJA" label="CJA" />-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidfilete" val="U" label="U" />-->
<!--              </div>-->
<!--            </div>-->
<!--            <div class="col-5" ><q-input type="number" dense outlined label="Cuello" v-model="miproducto.cuello"/></div>-->
<!--                        <div class="col-7">-->
<!--              <div class="q-gutter-sm">-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidcuello" val="KG" label="KG" />-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidcuello" val="CJA" label="CJA" />-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidcuello" val="U" label="U" />-->
<!--              </div>-->
<!--            </div>-->
<!--            <div class="col-5" ><q-input type="number" dense outlined label="Hueso" v-model="miproducto.hueso"/></div>-->
<!--                        <div class="col-7">-->
<!--              <div class="q-gutter-sm">-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidhueso" val="KG" label="KG" />-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidhueso" val="CJA" label="CJA" />-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidhueso" val="U" label="U" />-->
<!--              </div>-->
<!--            </div>-->
<!--            <div class="col-5" ><q-input type="number" dense outlined label="Menud" v-model="miproducto.menu"/></div>-->
<!--                        <div class="col-7">-->
<!--              <div class="q-gutter-sm">-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidmenu" val="KG" label="KG" />-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidmenu" val="CJA" label="CJA" />-->
<!--                <q-radio size="xs" dense v-model="miproducto.unidmenu" val="U" label="U" />-->
<!--              </div>-->
<!--            </div>-->
<!--            <div class="col-5" ><q-input type="number" dense outlined label="bs" v-model="miproducto.bs"/></div>-->
<!--            <div class="col-5" ><q-input type="number" dense outlined label="bs2" v-model="miproducto.bs2"/></div>-->
<!--            <div class="col-5" ><q-input type="number" dense outlined label="contado" v-model="miproducto.contado"/></div>-->
<!--            <div class="col-12" ><q-input  dense outlined label="observacion" v-model="miproducto.observacion"/></div>-->
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
            <div class="col-4"><q-input dense outlined label="precio" v-model="miproducto.pfrial" /></div>
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
          <div class="text-h6">Pedido Res</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <div class="row">
            <div class="col-4"><q-input dense outlined label="precio" v-model="miproducto.pfrial" /></div>
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
</template>
<script>
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
import {date} from "quasar";
const { addToDate } = date


export default {
  components: {
    LMap,
    LIcon,
    LTileLayer,
    LMarker,
    // LControlLayers,
    // LTooltip,
    // LPopup,
    // LPolyline,
    // LPolygon,
    // LRectangle,
  },
  data() {
    return {
      horarios:['06:00-07:30','07:30-09:00','09:00-10:30','10:30-12:00','SEGUNDA VUELTA','SE RECOGE'],
      tipopagos:['CONTADO','PAGO QR','CREDITO','BOLETA ANTERIOR'],
      horario:'',
      coment:'',
      fecha:date.formatDate(new Date(),'YYYY-MM-DD'),
      fechamenos:date.formatDate(addToDate(new Date(), { days: 0}),'YYYY-MM-DD'),
      filteproducto:'',
      modalopciones:false,
      modalpedido:false,
      modalnormal:false,
      modalpollo:false,
      modalcerdo:false,
      modalres:false,
      center:[-17.970371, -67.112303],
      filter:'',
      zoom: 16,
      iconWidth: 25,
      iconHeight: 40,
      clientes:[],
      cliente:{},
      productos:[],
      productos2:[],
      misproductos:[],
      miproducto:{},
      producto:{label:''},
      userLocation:{},
      pago:'',
      comentario:{},
      fact:'',
      columns:[
        {label:'Cod_Aut',name:'Cod_Aut',field:'Cod_Aut'},
        {label:'Nombres',name:'Nombres',field:'Nombres',align:'left'},
        {label:'opcion',name:'opcion',field:'opcion'},
      ],
      columnsproducto:[
        {label:'subtotal',name:'subtotal',field:'subtotal'},
        {label:'cantidad',name:'cantidad',field:'cantidad'},
        {label:'precio',name:'precio',field:'precio',align:'left'},
        {label:'cod_prod',name:'cod_prod',field:'cod_prod',align:'left'},
        {label:'nombre',name:'nombre',field:'nombre',align:'left'},
        {label:'observacion',name:'observacion',field:'observacion',align:'left'},
      ],
      loading:false,
    };
  },

  created() {
    this.listhoy()
    //this.misclientes()
    this.consultamisproductos()
    // this.$q.notify({
    //   message:'aaaa',
    //   color:'red',
    //   icon:'error'
    // })
  },
  methods: {
    modcomentario(){
      console.log(this.comentario)
      this.$api.post('updateComentario',this.comentario).then(res=>{
        console.log(res.data)
        this.$q.notify({
          message:'Modificado Comentario',
          color:'green',
          icon:'info'
        })
      })

    },
    listhoy(){
      // this.$q.loading.show()
      this.loading=true
      this.$api.post('filtrarlista',{filtradia:8}).then(res=>{
         console.log(res.data)
        this.clientes=[]
        // this.clientes=res.data
        res.data.forEach(r=>{
          let d=r
          // if (r.Latitud)
          // console.log(r.Latitud)
          if (parseFloat(r.Latitud)!=NaN && parseFloat(r.longitud)!=NaN && r.Latitud!='' && r.longitud!='' ){
            // console.log( 'id='+r.Cod_Aut+'  '+(r.Latitud!='' && r.longitud!='' )+' R='+parseFloat(r.Latitud)+'---'+parseFloat(r.longitud))
            d.Latitud=parseFloat(r.Latitud)
            d.longitud=parseFloat(r.longitud)
          }else{
            // console.log( (r.Latitud!='' && r.longitud!='' )+' R='+r.Latitud+'---'+r.longitud)
            d.Latitud=0
            d.longitud=0
          }

          this.clientes.push(d)
        })
        // console.log(this.clientes)
        // this.$q.loading.hide()
        // this.loading=false
      }).catch(err=>{
        // this.loading=false
        // console.log(err.response)
        // this.$q.loading.hide()
        this.$q.notify({
          message:err.response.data.message,
          color:'red',
          icon:'error'
        })
      }).finally(()=>{
        this.loading=false
      })
    },
    misclientes(){
      // this.$q.loading.show()
      this.loading=true
      this.$api.get('cliente').then(res=>{
         console.log(res.data)
        this.clientes=[]
        // this.clientes=res.data
        res.data.forEach(r=>{
          let d=r
          // if (r.Latitud)
          // console.log(r.Latitud)
          if (parseFloat(r.Latitud)!=NaN && parseFloat(r.longitud)!=NaN && r.Latitud!='' && r.longitud!='' ){
            // console.log( 'id='+r.Cod_Aut+'  '+(r.Latitud!='' && r.longitud!='' )+' R='+parseFloat(r.Latitud)+'---'+parseFloat(r.longitud))
            d.Latitud=parseFloat(r.Latitud)
            d.longitud=parseFloat(r.longitud)
          }else{
            // console.log( (r.Latitud!='' && r.longitud!='' )+' R='+r.Latitud+'---'+r.longitud)
            d.Latitud=0
            d.longitud=0
          }

          this.clientes.push(d)
        })
        // console.log(this.clientes)
        // this.$q.loading.hide()
        this.loading=false
      }).catch(err=>{
        // console.log(err.response)
        // this.$q.loading.hide()
        this.loading=false
        this.$q.notify({
          message:err.response.data.message,
          color:'red',
          icon:'error'
        })
      })
    },
    consultamisproductos(){
      this.$api.get('producto').then(res=>{
        // console.log(res.data)
        this.productos=[]
        // this.productos=res.data
        res.data.forEach(r=>{
          let d=r
          // console.log(d)
          if(d.cantidad==null || d.cantidad==undefined) {d.cantidad=0}
          d.label=r.cod_prod+'-'+r.Producto+' '+ parseFloat(r.Precio).toFixed(2) +'Bs '+ parseFloat(r.cantidad).toFixed(2)+r.codUnid
          this.productos.push(d)
        })
        this.productos2=this.productos
        // this.producto=this.productos[0]
        // this.$q.loading.hide()
      }).catch(err=>{
        // console.log(err.response)
        this.$q.loading.hide()
        this.$q.notify({
          message:err.response.data.message,
          color:'red',
          icon:'error'
        })
      })
    },
    enviarpedido(){
      if (this.misproductos.length==0){
        this.$q.notify({
          message:'No tienes productos',
          icon:'error',
          color:'red',
          position:'top'
        })
        return false
      }
      if ( this.fact!='SI' && this.fact!='NO' ){
        this.$q.notify({
          message:'Debe Marcar Factura',
          color:'red',
          icon:'error',
          position:'top'
        })
        return false
      }
      if ( this.pago=='' || this.pago==undefined ){
        this.$q.notify({
          message:'Debe Marcar tipo de pago',
          color:'red',
          icon:'error',
          position:'top'
        })
        return false
      }
      this.dialog=true
      this.$q.dialog({
        title:'Seguro de enviar pedido',
        color:'green',
        icon:'send',
        cancel:true
      }).onOk(data=>{
        // this.$q.loading.show()
        this.loading=true
        var lat=0,lng=0
        if (navigator.geolocation) {
          // get  geolocation
          navigator.geolocation.getCurrentPosition(pos => {
            // set user location
            // this.center = [
            //   pos.coords.latitude,
            //   pos.coords.longitude
            // ]
            lat=pos.coords.latitude
            lng=pos.coords.longitude
            this.insertarpedido(lat,lng)
          });
        }else{
          lat=0
          lng=0
          this.insertarpedido(lat,lng)
        }

      })
    },
    clickretornar(){
      this.loading=true
      var lat=0,lng=0
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
          lat=pos.coords.latitude
          lng=pos.coords.longitude
          this.insertarpedidoestado(lat,lng,'PARADO','')
        });
      }else{
        lat=0
        lng=0
        this.insertarpedidoestado(lat,lng,'PARADO','')
      }
    },
    clicknopedido(){

      this.$q.dialog({
        title: 'NO PEDIDO',
        message: 'INGRESE ALGUN COMENTARIO?',
        prompt: {
          model: '',
          type: 'text' // optional
        },
        cancel: true,
        persistent: false
      }).onOk(data => {
        console.log(data)
              var lat=0,lng=0
      if (navigator.geolocation) {
        // get  geolocation
        navigator.geolocation.getCurrentPosition(pos => {
          // set user location
          // this.center = [
          //   pos.coords.latitude,
          //   pos.coords.longitude
          // ]
          lat=pos.coords.latitude
          lng=pos.coords.longitude
        this.insertarpedidoestado(lat,lng,'NO PEDIDO',data)

        })
      }
      else{

        this.insertarpedidoestado(lat,lng,'NO PEDIDO',data)
      }
      }).onCancel(() => {
        // console.log('>>>> Cancel')
      }).onDismiss(() => {
        // console.log('I am triggered on both OK and Cancel')
      })


    },
    insertarpedidoestado(lat,lng,estado,obs){
      this.$api.put('pedido/1',{idCli:this.cliente.Cod_Aut,lat:lat,lng:lng,estado:estado,observacion:obs}).then(res=>{
        // console.log(res.data)
        // return false
        this.modalopciones=false
        this.listhoy()
        //this.misclientes()
      }).catch(err=>{
        // console.log(err.response)
        this.$q.loading.hide()
        this.$q.notify({
          message:err.response.data.message,
          color:'red',
          icon:'error'
        })
      })
    },
    insertarpedido(lat,lng){
      if ( this.fecha==null || this.fecha==undefined || this.fecha==''){
        this.$q.notify({
          message:'Debes selecionar una fecha',
          color:'red',
          icon:'error',
          position:'top'
        })
        return false
      }
      if ( this.fact!='SI' && this.fact!='NO' ){
        this.$q.notify({
          message:'Debe Marcar Factura',
          color:'red',
          icon:'error',
          position:'top'
        })
        return false
      }
        if ( this.pago=='' || this.pago==undefined ){
        this.$q.notify({
          message:'Debe Seleccionar tipo pago',
          color:'red',
          icon:'error',
          position:'top'
        })
        return false
      }
      this.$api.post('pedido',{idCli:this.cliente.Cod_Aut,lat:lat,lng:lng,productos:this.misproductos,pago:this.pago,fact:this.fact,fecha:this.fecha,horario:this.horario,comentario:this.coment}).then(res=>{
         // console.log(res.data)
        // return false
        //this.pago=''
        this.fact=''
        this.horario=''
        this.coment=''
        this.modalpedido=false
        this.fecha=date.formatDate(new Date(),'YYYY-MM-DD')
        this.listhoy()
        this.loading=false
        //this.misclientes()
      }).catch(err=>{
        // console.log(err.response)
        // this.$q.loading.hide()
        this.loading=false
        this.$q.notify({
          message:err.response.data.message,
          color:'red',
          icon:'error'
        })
      })
    },
    seleccionartipo(m){
      console.log(m)
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
      // this.$q.dialog({
      //   title: 'Seleccionar tipo',
      //   // message: 'Choose an option:',
      //   options: {
      //     type: 'radio',
      //     model: this.miproducto.tipo,
      //     // inline: true
      //     items: [
      //       { label: 'Normal', value: 'NORMAL', color: 'secondary' },
      //       { label: 'Pollo', value: 'POLLO', color: 'secondary' },
      //       { label: 'Cerdo', value: 'CERDO', color: 'info' },
      //       { label: 'Res', value: 'RES', color: 'accent' }
      //     ]
      //   },
      //   cancel: true,
      //   // persistent: true
      // }).onOk(data => {
      //   // console.log(data)
      //   if (data=='NORMAL'){
      //     this.modalnormal=true
      //     this.miproducto.tipo='NORMAL'
      //   }else if (data=='POLLO'){
      //     this.modalpollo=true
      //     this.miproducto.tipo='POLLO'
      //   }else if (data=='CERDO'){
      //     this.modalcerdo=true
      //     this.miproducto.tipo='CERDO'
      //   }else if (data=='RES'){
      //     this.modalres=true
      //     this.miproducto.tipo='RES'
      //   }else{
      //   }
      // })

    },
    agregar(producto){
      producto.cantidad = producto.cantidad+1
      producto.subtotal = (producto.cantidad*parseFloat(producto.precio)).toFixed(2)
    },
    quitar(producto,index){
      if (producto.cantidad==1){
        this.misproductos.splice(index, 1);
      }else {
        producto.cantidad = producto.cantidad-1
        producto.subtotal = (producto.cantidad*parseFloat(producto.precio)).toFixed(2)
      }
    },
    tecleado(e){
      // console.log(e)
      e.subtotal=(e.cantidad*e.precio).toFixed(2)
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
      this.misproductos.unshift({
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
        rango:'',
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
        pfrial:'',
        tipo:this.producto.tipo,
        nombre:this.producto.Producto,
        cod_prod:this.producto.cod_prod,
        precio:parseFloat(this.producto.Precio).toFixed(2),
        cantidad:1,
        subtotal:parseFloat(this.producto.Precio).toFixed(2)
      })
      this.producto={label:''} //comentarpedido
    },
    clickpedido(){
      this.coment=''
      this.horario=''
      this.pago=''
      this.modalopciones=false
      this.modalpedido=true
      this.misproductos=[]
    },
    clickopciones(cliente){
      this.modalopciones=true
      this.cliente=cliente
      this.comentario={}

      this.$api.post('comentario',{ci:this.cliente.Id}).then(res=>{
          //console.log(res.data)
          this.comentario=res.data
      })
    },
    ubicacion(e){
      // console.log(e.latlng)
      if (e.latlng!=undefined)
        this.center=[e.latlng.lat,e.latlng.lng]
    },
    async getCentro() {
      this.center = [-17.970371, -67.112303]
      // check if API is supported
      // if (navigator.geolocation) {
      //   // get  geolocation
      //   navigator.geolocation.getCurrentPosition(pos => {
      //     // set user location
      //     this.center = [
      //       pos.coords.latitude,
      //       pos.coords.longitude
      //     ]
      //   });
      // }
    },
    async getUserPosition() {
      this.loading=true
      this.center = [0,0]
      // check if API is supported
      if (navigator.geolocation) {
        // get  geolocation
        await navigator.geolocation.getCurrentPosition(pos => {
          // set user location
          this.center = [
            pos.coords.latitude,
            pos.coords.longitude
          ]
        });
      }
      this.loading=false
    },
    clickclientes(c){
      console.log(c)
      this.center = [c.Latitud, c.longitud]
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
    onReady (mapObject) {
      mapObject.locate();
    },
    onLocationFound(location){
      // console.log(location)
      this.center=[location.latlng.lat,location.latlng.lng]
    },
    log(a) {
      console.log(a);
    },
    changeIcon() {
      this.iconWidth += 2;
      if (this.iconWidth > this.iconHeight) {
        this.iconWidth = Math.floor(this.iconHeight / 2);
      }
    },
  },
  computed: {
    // iconUrl() {
    //   return `https://placekitten.com/25/40`;
    // },
    // iconSize() {
    //   return [this.iconWidth, this.iconHeight];
    // },
    total(){
      let total=0
      this.misproductos.forEach(r=>{
        total+=parseFloat(r.subtotal)
      })
      return total.toFixed(2)
    }
  },
};
</script>
<style lang="sass">
.my-sticky-header-table
  /* height or max-height is important */
  height: 450px

  .q-table__top,
  .q-table__bottom,
  thead tr:first-child th
    /* bg color is important for th; just specify one */
    background-color: white

  thead tr th
    position: sticky
    z-index: 1
  thead tr:first-child th
    top: 0

  /* this is when the loading indicator appears */
  &.q-table--loading thead tr:last-child th
    /* height of all previous header rows */
    top: 48px
</style>
