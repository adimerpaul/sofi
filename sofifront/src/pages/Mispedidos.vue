<template>
  <q-page class="q-pa-xs">
    <div class="row">
      <div class="col-12 row items-center q-gutter-xs barra-pedidos">
        <q-input dense outlined v-model="fecha1" type="date" class="barra-fecha" @update:model-value="misclientes"/>
        <q-btn :loading="loading" dense unelevated color="info" icon="search" @click="misclientes">
          <q-tooltip>Consultar</q-tooltip>
        </q-btn>
        <q-btn :loading="loading" dense unelevated no-caps color="green" type="a" icon="list" label="Pollo"
               :href="url+'excel/p/'+fecha1+'/'+fecha2+'/'+$store.state.login.user.CodAut" target="_blank"/>
        <q-btn :loading="loading" dense unelevated no-caps color="accent" type="a" icon="list" label="Res"
               :href="url+'excel/r/'+fecha1+'/'+fecha2+'/'+$store.state.login.user.CodAut" target="_blank"/>
        <q-btn :loading="loading" dense unelevated no-caps color="teal" type="a" icon="list" label="Cerdo"
               :href="url+'excel/c/'+fecha1+'/'+fecha2+'/'+$store.state.login.user.CodAut" target="_blank"/>
        <q-btn dense unelevated no-caps color="red-8" icon="picture_as_pdf" label="PDF" @click="exportarPdf"
               :disable="!clientes.length"/>
        <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar" class="col barra-buscar">
          <template v-slot:append>
            <q-icon name="search" size="xs"/>
          </template>
        </q-input>
      </div>
      <!-- Tarjetas de totales del dia -->
      <div class="col-12 row q-col-gutter-xs q-mt-none q-mb-xs">
        <div class="col-3">
          <q-card flat bordered class="tarjeta-total">
            <div class="tarjeta-titulo">Pedidos</div>
            <div class="tarjeta-valor">{{ resumen.pedidos }}</div>
            <div class="tarjeta-sub">{{ resumen.creados }} creados · {{ resumen.enviados }} env.</div>
          </q-card>
        </div>
        <div class="col-3">
          <q-card flat bordered class="tarjeta-total">
            <div class="tarjeta-titulo">Total Bs</div>
            <div class="tarjeta-valor text-positive">{{ resumen.total }}</div>
            <div class="tarjeta-sub">prom. {{ resumen.promedio }}</div>
          </q-card>
        </div>
        <div class="col-3">
          <q-card flat bordered class="tarjeta-total">
            <div class="tarjeta-titulo">Productos</div>
            <div class="tarjeta-valor text-primary">{{ resumen.productos }}</div>
            <div class="tarjeta-sub">{{ resumen.lineas }} líneas</div>
          </q-card>
        </div>
        <div class="col-3">
          <q-card flat bordered class="tarjeta-total cursor-pointer" @click="verResumen = !verResumen">
            <div class="tarjeta-titulo">Cantidad</div>
            <div class="tarjeta-valor text-orange-9">{{ resumen.cantidad }}</div>
            <div class="tarjeta-sub">
              {{ verResumen ? 'ocultar' : 'ver detalle' }}
              <q-icon :name="verResumen ? 'expand_less' : 'expand_more'"/>
            </div>
          </q-card>
        </div>
        <!-- Por tipo: NORMAL son los embutidos -->
        <div v-for="t in resumenTipos" :key="t.tipo" :class="resumenTipos.length > 2 ? 'col-3' : 'col-6'">
          <q-card flat bordered class="tarjeta-total" :class="'tarjeta-' + t.tipo.toLowerCase()">
            <div class="tarjeta-titulo">{{ t.etiqueta }}</div>
            <div class="tarjeta-valor">{{ t.pedidos }} <span class="tarjeta-sub">ped.</span></div>
            <div class="tarjeta-sub">{{ t.lineas }} lín. · {{ t.subtotal }} Bs</div>
          </q-card>
        </div>
        <div class="col-12" v-if="verResumen">
          <q-markup-table dense flat bordered separator="cell" class="tabla-compacta">
            <thead>
            <tr>
              <th class="text-left">Producto</th>
              <th class="text-right">Cant.</th>
              <th class="text-right">Pedidos</th>
              <th class="text-right">Bs</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="p in resumenProductos" :key="p.nombre">
              <td class="text-left">{{ p.nombre }}</td>
              <td class="text-right text-weight-bold">{{ p.cantidad }}</td>
              <td class="text-right">{{ p.pedidos }}</td>
              <td class="text-right">{{ p.subtotal }}</td>
            </tr>
            </tbody>
          </q-markup-table>
        </div>
      </div>
      <div class="col-12">
        <q-table :rows-per-page-options="[0]" hide-pagination dense flat bordered :columns="columns" :rows="clientes"
                 :filter="filter" row-key="NroPed" class="tabla-compacta" separator="cell">
          <template v-slot:body-cell-numero_dia="props">
            <q-td :props="props">
              <q-badge color="primary" class="text-weight-bold" :label="props.row.numero_dia || '-'"/>
            </q-td>
          </template>
          <template v-slot:body-cell-opciones="props">
            <q-td :props="props">
              <div class="row no-wrap items-center">
                <q-btn @click="listpedidos(props.row)" :color="props.row.estado=='CREADO'?'primary':'warning'"
                       :icon="props.row.estado=='CREADO'?'edit':'send'" size="xs" dense unelevated>
                  <q-tooltip>{{ props.row.estado=='CREADO'?'Modificar':'Enviado' }}</q-tooltip>
                </q-btn>
                <q-btn @click="imprimirboleta(props.row)" color="info" icon="print" size="xs" dense unelevated
                       v-if="props.row.estado=='ENVIADO'" class="q-ml-xs"/>
              </div>
            </q-td>
          </template>
          <template v-slot:body-cell-Nombres="props">
            <q-td :props="props">
              <div class="text-weight-medium ellipsis-cliente">{{ props.row.cliente?.Nombres || '—' }}</div>
              <q-chip v-if="props.row.bonificacion==1" color="orange" text-color="white" dense size="xs"
                      class="q-ma-none" :label="props.row.clienteBonificacion"/>
            </q-td>
          </template>
        </q-table>
        <q-btn class="full-width q-mt-xs" dense unelevated @click="enviarpedidos" color="warning" icon="check"
               label="Enviar todos los pedidos"/>
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
                      <div class="row items-center no-wrap">
                        <!-- Pollo, cerdo y res se cargan por piezas en el dialogo
                             del icono de al lado, por eso no llevan cantidad. La
                             excepcion es el que se vende por caja: ese se pide
                             por bulto. -->
                        <template v-if="props.row.tipo=='NORMAL' || props.row.codUnid == 'CAJA'">
                          <q-btn flat dense @click="agregar(props.row)" class="q-ma-none q-pa-none" color="positive"
                                 icon="add_circle"/>
                          <input type="number" min="0" step="0.001" @keyup="tecleado(props.row)"
                                 v-model="props.row.cantidad" class="entrada-pedido entrada-cantidad">
                        </template>
                        <!-- Lo que se vende por caja se pidio en unidades, cajas
                             o kilos; se recupera de tbpedidos.caja para poder
                             corregirlo al modificar la comanda. -->
                        <select v-if="props.row.codUnid == 'CAJA'" v-model="props.row.caja"
                                class="entrada-pedido entrada-caja q-ml-xs">
                          <option value="U">U</option>
                          <option value="CAJA">CAJA</option>
                          <option value="KG">KG</option>
                        </select>
                        <q-btn flat dense @click="quitar(props.row,props.rowIndex)" class="q-ma-none q-pa-none"
                               color="negative" icon="remove_circle"/>
                      </div>
                    </q-td>
                  </template>
                  <template v-slot:body-cell-precio="props">
                    <q-td :props="props" auto-width>
                      <!-- El precio ya no se escribe a mano: solo se cambia eligiendo
                           uno de los precios cargados del producto, igual que al
                           tomar el pedido en la visita. -->
                      <div class="row items-center no-wrap cursor-pointer">
                        <input type="number" readonly tabindex="-1"
                               v-model="props.row.precio" class="entrada-pedido entrada-precio entrada-precio-bloqueada">
                        <q-btn flat dense size="sm" color="primary" icon="expand_more"
                               class="q-ma-none q-pa-none"
                               :disable="!(props.row.precios || []).length"/>
                        <q-menu auto-close v-if="(props.row.precios || []).length">
                          <q-list dense style="min-width: 165px">
                            <q-item-label header class="q-py-xs">Precios del producto</q-item-label>
                            <q-item v-for="opcion in props.row.precios" :key="opcion.etiqueta" clickable
                                    :active="String(props.row.precio) === opcion.valor"
                                    @click="elegirPrecio(props.row, opcion.valor)">
                              <q-item-section>{{ opcion.etiqueta }}</q-item-section>
                              <q-item-section side class="text-weight-bold text-primary">
                                {{ opcion.valor }} Bs
                              </q-item-section>
                            </q-item>
                          </q-list>
                        </q-menu>
                        <q-tooltip>Elegir uno de los precios del producto</q-tooltip>
                      </div>
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

import $ from 'jquery';
import 'datatables.net-dt/css/jquery.dataTables.min.css';
import 'datatables.net-buttons/js/dataTables.buttons';
import 'datatables.net-buttons/js/buttons.html5.js';
import print from 'datatables.net-buttons/js/buttons.print';
import jszip from 'jszip/dist/jszip';
import pdfMake from 'pdfmake/build/pdfmake';
import pdfFonts from 'pdfmake/build/vfs_fonts';

// pdfmake >= 0.2.8 exporta el vfs directamente; antes venia en .pdfMake.vfs
pdfMake.vfs = pdfFonts.pdfMake ? pdfFonts.pdfMake.vfs : pdfFonts;
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
      verResumen: false,
      columns: [
        { label: 'N°', name: 'numero_dia', field: 'numero_dia', align: 'center', sortable: true },
        { label: '', name: 'opciones', field: 'opciones', align: 'left' },
        {
          label: 'Cliente',
          name: 'Nombres',
          field: row => row.cliente?.Nombres || '—',
          align: 'left'
        },
        { label: 'Bs', name: 'total', field: 'total', align: 'right', sortable: true },
        { label: 'Hora', name: 'fecha', field: row => (row.fecha || '').substring(11, 16), align: 'center' },
        { label: 'Pago', name: 'pago', field: 'pago', align: 'left' },
        { label: 'Fac', name: 'fact', field: 'fact', align: 'center' },
        { label: 'Com.', name: 'NroPed', field: 'NroPed', align: 'right' },
        { label: 'CI', name: 'Id', field: row => row.cliente?.Id || '', align: 'left' }
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
    // Boleta de pedido enviado: encabezado con datos del pedido y cliente,
    // detalle de embutidos (rnormal) y lineas de pollo/res/cerdo, totales y firmas.
    imprimirboleta(comanda1) {
      this.$api.post('rnormal', {comanda: comanda1.NroPed}).then(res => {
        const esc = v => String(v ?? '').replace(/[&<>"]/g, ch => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[ch]))
        const num = v => (parseFloat(v) || 0).toFixed(2)
        const cli = comanda1.cliente || {}
        const user = this.$store.state.login.user || {}
        const usuario = ((user.Nombre1 || '') + ' ' + (user.App1 || '')).trim()
        const normales = res.data || []
        const primera = normales[0] || {}
        const otros = (comanda1.productos || []).filter(p => p.tipo && p.tipo !== 'NORMAL')
        const etiquetas = {POLLO: 'Pollo', RES: 'Res', CERDO: 'Cerdo'}

        let tot = 0
        let n = 0
        let filas = ''
        normales.forEach(r => {
          tot += parseFloat(r.subtotal) || 0
          filas += `<tr><td class="c">${++n}</td><td>${esc((r.cod_prod || '').trim())}</td><td>${esc(r.Producto)}` +
            (r.Observaciones ? `<div class="obs">${esc(r.Observaciones)}</div>` : '') + '</td>' +
            `<td class="r">${esc(r.Cant)}${r.caja ? ' ' + esc(r.caja) : ''}</td><td class="r">${num(r.precio)}</td>` +
            `<td class="r b">${num(r.subtotal)}</td></tr>`
        })
        otros.forEach(p => {
          tot += parseFloat(p.subtotal) || 0
          filas += `<tr><td class="c">${++n}</td><td>${esc(p.cod_prod)}</td><td>${esc(p.nombre)} ` +
            `<span class="tag">${esc(etiquetas[p.tipo] || p.tipo)}</span></td>` +
            `<td class="r">${p.cantidad ? esc(p.cantidad) : '-'}</td><td class="r">-</td><td class="r b">${num(p.subtotal)}</td></tr>`
        })
        if (!n) filas = '<tr><td colspan="6" class="c">Sin productos</td></tr>'

        const fechaPedido = (comanda1.fecha || '').substring(0, 16)
        const html = `<!doctype html><html><head><meta charset="utf-8"><title>Pedido ${esc(comanda1.NroPed)}</title>
<style>
  *{box-sizing:border-box} body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#111;margin:16px}
  .cab{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #111;padding-bottom:6px}
  .emp{font-size:20px;font-weight:bold;letter-spacing:1px} .sub{color:#555;font-size:11px}
  .num{text-align:right} .num .dia{font-size:26px;font-weight:bold;line-height:1} .num .cmd{font-size:13px}
  .datos{display:grid;grid-template-columns:1fr 1fr;gap:2px 16px;margin:8px 0;padding:6px 8px;border:1px solid #bbb;border-radius:4px}
  .datos b{display:inline-block;min-width:70px;color:#444}
  table{width:100%;border-collapse:collapse;margin-top:6px}
  th{background:#eee;border:1px solid #999;padding:4px;font-size:11px;text-transform:uppercase}
  td{border:1px solid #bbb;padding:3px 4px;vertical-align:top}
  .c{text-align:center} .r{text-align:right;white-space:nowrap} .b{font-weight:bold}
  .obs{font-size:10px;color:#555;font-style:italic} .tag{font-size:9px;border:1px solid #777;border-radius:3px;padding:0 3px}
  tfoot td{font-size:14px;font-weight:bold;background:#f5f5f5}
  .coment{margin-top:6px;padding:4px 8px;border-left:3px solid #555;background:#f7f7f7}
  .firmas{display:flex;justify-content:space-around;margin-top:48px}
  .firmas div{width:35%;border-top:1px solid #111;text-align:center;padding-top:3px;font-size:11px}
  .pie{margin-top:14px;font-size:10px;color:#666;display:flex;justify-content:space-between}
  @media print{body{margin:6mm} th,tfoot td{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head><body>
<div class="cab">
  <div><div class="emp">SOFIA</div><div class="sub">Pollo, embutidos y carnes · Nota de pedido</div></div>
  <div class="num"><div class="dia">N° ${esc(comanda1.numero_dia || '-')}</div><div class="cmd">Comanda ${esc(comanda1.NroPed)}</div>
    <div class="sub">${esc(comanda1.estado)}</div></div>
</div>
<div class="datos">
  <div><b>Cliente:</b> ${esc(cli.Nombres)}</div><div><b>CI/NIT:</b> ${esc(cli.Id)}</div>
  <div><b>Dirección:</b> ${esc(cli.Direccion)}</div><div><b>Teléfono:</b> ${esc(cli.Telf)}</div>
  <div><b>Fecha:</b> ${esc(fechaPedido)}</div><div><b>Horario:</b> ${esc(primera.horario || '-')}</div>
  <div><b>Pago:</b> ${esc(comanda1.pago)}</div><div><b>Factura:</b> ${esc(comanda1.fact)}</div>
  <div><b>Vendedor:</b> ${esc(usuario)}</div>
  ${comanda1.bonificacion == 1 ? `<div><b>Bonificación:</b> ${esc(comanda1.clienteBonificacion)}</div>` : ''}
</div>
${primera.comentario ? `<div class="coment"><b>Comentario:</b> ${esc(primera.comentario)}</div>` : ''}
<table>
  <thead><tr><th>#</th><th>Código</th><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subtotal</th></tr></thead>
  <tbody>${filas}</tbody>
  <tfoot><tr><td colspan="3">${n} producto(s)</td><td colspan="2" class="r">TOTAL Bs</td><td class="r">${num(tot)}</td></tr></tfoot>
</table>
<div class="firmas"><div>Entregado por</div><div>Recibido por (cliente)</div></div>
<div class="pie"><span>Impreso por ${esc(usuario)}</span><span>${date.formatDate(new Date(), 'DD/MM/YYYY HH:mm:ss')}</span></div>
</body></html>`
        const myWindow = window.open('', 'Imprimir', 'width=900,height=1000')
        myWindow.document.write(html)
        myWindow.document.close()
        myWindow.focus()
        myWindow.print()
        myWindow.close()
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
        codUnid: String(this.producto.codUnid || '').trim(),
        // Solo los productos por caja eligen unidad; por defecto se piden en cajas.
        caja: String(this.producto.codUnid || '').trim() == 'CAJA' ? 'CAJA' : null,
        precio: parseFloat(this.producto.Precio).toFixed(2),
        precios: this.listaPrecios(this.producto),
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
    // tbproductos guarda 13 precios de venta: Precio es el 1 y Precio_Costo el
    // 2 (el nombre es heredado, no es el costo), despues Precio3..Precio13.
    // Se descartan los que estan en cero y los repetidos para que la lista solo
    // muestre precios que de verdad se pueden cobrar.
    listaPrecios(producto) {
      if (!producto) return []
      const campos = ['Precio', 'Precio_Costo', 'Precio3', 'Precio4', 'Precio5',
        'Precio6', 'Precio7', 'Precio8', 'Precio9', 'Precio10', 'Precio11',
        'Precio12', 'Precio13']
      const vistos = []
      const opciones = []
      campos.forEach((campo, indice) => {
        const valor = parseFloat(producto[campo])
        if (!valor || isNaN(valor)) return
        const texto = valor.toFixed(2)
        if (vistos.indexOf(texto) !== -1) return
        vistos.push(texto)
        opciones.push({etiqueta: 'Precio ' + (indice + 1), valor: texto})
      })
      return opciones
    },
    elegirPrecio(fila, valor) {
      fila.precio = valor
      this.tecleado(fila)
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
          // La comanda guarda un solo precio; los demas precios del producto se
          // sacan de la lista ya cargada para poder cambiarlo desde el menu.
          this.misproductos = res.data[0].pedidos.map(p => {
            const prod = this.productos2.find(x => String(x.cod_prod).trim() === String(p.cod_prod).trim())
            p.precios = this.listaPrecios(prod)
            p.codUnid = String(p.codUnid || (prod ? prod.codUnid : '') || '').trim()
            return p
          })
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

    // PDF liviano dibujado a mano con jsPDF (sin autotable ni imagenes):
    // totales del dia, lista de pedidos y resumen por producto.
    exportarPdf() {
      const doc = new jsPDF('p', 'mm', 'letter')
      const alto = doc.internal.pageSize.getHeight()
      const margen = 10
      const fila = 5
      let y = margen
      const user = this.$store.state.login.user || {}
      const usuario = ((user.Nombre1 || '') + ' ' + (user.App1 || '')).trim() || 'usuario'
      const ahora = new Date()
      const corta = (texto, ancho) => doc.splitTextToSize(String(texto ?? ''), ancho)[0] || ''
      const saltoSiHaceFalta = (encabezado) => {
        if (y + fila > alto - margen) {
          doc.addPage()
          y = margen
          if (encabezado) encabezado()
        }
      }
      const tabla = (cols, filas) => {
        const encabezado = () => {
          doc.setFont('helvetica', 'bold')
          doc.setFillColor(230, 230, 230)
          doc.rect(margen, y - 3.6, 196, fila, 'F')
          cols.forEach(c => doc.text(c.t, c.a == 'r' ? c.x + c.w : c.x, y, {align: c.a == 'r' ? 'right' : 'left'}))
          doc.setFont('helvetica', 'normal')
          y += fila
        }
        encabezado()
        filas.forEach(f => {
          saltoSiHaceFalta(encabezado)
          cols.forEach((c, i) => doc.text(corta(f[i], c.w), c.a == 'r' ? c.x + c.w : c.x, y, {align: c.a == 'r' ? 'right' : 'left'}))
          doc.setDrawColor(220, 220, 220)
          doc.line(margen, y + 1.2, margen + 196, y + 1.2)
          y += fila
        })
      }

      doc.setFontSize(12)
      doc.setFont('helvetica', 'bold')
      doc.text('Mis pedidos ' + this.fecha1, margen, y)
      doc.setFontSize(8)
      doc.setFont('helvetica', 'normal')
      doc.text('Creado por: ' + usuario, margen + 196, y - 3, {align: 'right'})
      doc.text('Fecha/hora: ' + date.formatDate(ahora, 'DD/MM/YYYY HH:mm:ss'), margen + 196, y + 1, {align: 'right'})
      y += fila + 1
      const r = this.resumen
      doc.text(`Pedidos: ${r.pedidos} (${r.creados} creados, ${r.enviados} enviados)   Total: ${r.total} Bs   ` +
        `Promedio: ${r.promedio} Bs   Productos: ${r.productos}   Lineas: ${r.lineas}   Cantidad: ${r.cantidad}`, margen, y)
      y += fila
      doc.text(this.resumenTipos.map(t => `${t.etiqueta}: ${t.pedidos} ped. / ${t.lineas} lin. / ${t.subtotal} Bs`).join('    '), margen, y)
      y += fila + 2

      const pedidos = [...this.clientes].sort((a, b) => (a.numero_dia || 9999) - (b.numero_dia || 9999) || a.NroPed - b.NroPed)
      tabla([
        {t: 'N°', x: 10, w: 8, a: 'r'},
        {t: 'Cliente', x: 21, w: 72},
        {t: 'Bs', x: 94, w: 18, a: 'r'},
        {t: 'Hora', x: 115, w: 10},
        {t: 'Pago', x: 127, w: 26},
        {t: 'Fac', x: 155, w: 8},
        {t: 'Comanda', x: 165, w: 16, a: 'r'},
        {t: 'Estado', x: 185, w: 20},
      ], pedidos.map(p => [
        p.numero_dia || '-',
        p.cliente?.Nombres || '',
        parseFloat(p.total || 0).toFixed(2),
        (p.fecha || '').substring(11, 16),
        p.pago,
        p.fact,
        p.NroPed,
        p.estado,
      ]))

      y += 3
      saltoSiHaceFalta()
      doc.setFont('helvetica', 'bold')
      doc.text('Resumen por producto', margen, y)
      doc.setFont('helvetica', 'normal')
      y += fila
      tabla([
        {t: 'Producto', x: 10, w: 120},
        {t: 'Cant.', x: 132, w: 20, a: 'r'},
        {t: 'Pedidos', x: 156, w: 16, a: 'r'},
        {t: 'Bs', x: 176, w: 29, a: 'r'},
      ], this.resumenProductos.map(p => [p.nombre, p.cantidad, p.pedidos, p.subtotal]))

      // Pie en cada pagina: quien lo creo, cuando y numero de pagina
      const paginas = doc.getNumberOfPages()
      for (let i = 1; i <= paginas; i++) {
        doc.setPage(i)
        doc.setFontSize(7)
        doc.setTextColor(120)
        doc.text('Creado por ' + usuario + ' el ' + date.formatDate(ahora, 'DD/MM/YYYY HH:mm:ss'), margen, alto - 5)
        doc.text('Pag. ' + i + ' de ' + paginas, margen + 196, alto - 5, {align: 'right'})
      }

      // Nombre: usuario_fecha_hora de generacion (sin espacios ni acentos)
      const limpio = usuario.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9]+/g, '_')
      doc.save(limpio + '_' + date.formatDate(ahora, 'YYYY-MM-DD_HH-mm') + '.pdf')
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
    resumen() {
      let total = 0
      let lineas = 0
      let cantidad = 0
      let creados = 0
      const codigos = new Set()
      this.clientes.forEach(c => {
        total += parseFloat(c.total || 0)
        if (c.estado == 'CREADO') creados++
        ;(c.productos || []).forEach(p => {
          lineas++
          cantidad += parseFloat(p.cantidad || 0)
          codigos.add(p.cod_prod)
        })
      })
      const pedidos = this.clientes.length
      return {
        pedidos,
        creados,
        enviados: pedidos - creados,
        total: total.toFixed(2),
        promedio: pedidos ? (total / pedidos).toFixed(2) : '0.00',
        productos: codigos.size,
        lineas,
        cantidad: +cantidad.toFixed(3)
      }
    },
    // Pedidos, lineas y Bs por tipo de producto (NORMAL = embutidos)
    resumenTipos() {
      const etiquetas = {NORMAL: 'Embutidos', POLLO: 'Pollo', RES: 'Res', CERDO: 'Cerdo'}
      const mapa = {}
      this.clientes.forEach(c => {
        const vistos = new Set()
        ;(c.productos || []).forEach(p => {
          const tipo = p.tipo || 'NORMAL'
          const r = mapa[tipo] || (mapa[tipo] = {tipo, etiqueta: etiquetas[tipo] || tipo, pedidos: 0, lineas: 0, subtotal: 0})
          r.lineas++
          r.subtotal += parseFloat(p.subtotal || 0)
          if (!vistos.has(tipo)) {
            vistos.add(tipo)
            r.pedidos++
          }
        })
      })
      const orden = ['NORMAL', 'POLLO', 'RES', 'CERDO']
      return Object.values(mapa)
        .map(r => ({...r, subtotal: r.subtotal.toFixed(2)}))
        .sort((a, b) => (orden.indexOf(a.tipo) + 1 || 99) - (orden.indexOf(b.tipo) + 1 || 99))
    },
    resumenProductos() {
      const mapa = {}
      this.clientes.forEach(c => {
        (c.productos || []).forEach(p => {
          const r = mapa[p.nombre] || (mapa[p.nombre] = {nombre: p.nombre, cantidad: 0, subtotal: 0, pedidos: 0})
          r.cantidad += parseFloat(p.cantidad || 0)
          r.subtotal += parseFloat(p.subtotal || 0)
          r.pedidos++
        })
      })
      return Object.values(mapa)
        .map(r => ({...r, cantidad: +r.cantidad.toFixed(3), subtotal: r.subtotal.toFixed(2)}))
        .sort((a, b) => b.cantidad - a.cantidad)
    },
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

<style lang="sass" scoped>
.barra-fecha
  width: 140px

.barra-buscar
  min-width: 110px

.tarjeta-total
  padding: 2px 6px
  line-height: 1.15
  text-align: center

.tarjeta-normal
  border-left: 3px solid $primary

.tarjeta-pollo
  border-left: 3px solid $orange-8

.tarjeta-res
  border-left: 3px solid $red-7

.tarjeta-cerdo
  border-left: 3px solid $pink-4

.tarjeta-titulo
  font-size: 10px
  text-transform: uppercase
  color: rgba(0, 0, 0, 0.55)

.tarjeta-valor
  font-size: 16px
  font-weight: 700

.tarjeta-sub
  font-size: 10px
  color: rgba(0, 0, 0, 0.55)
  white-space: nowrap
  overflow: hidden
  text-overflow: ellipsis

// Tabla apretada: el vendedor llega a ~70 pedidos al dia y debe verlos sin paginar
.tabla-compacta :deep(th),
.tabla-compacta :deep(td)
  padding: 1px 4px !important
  height: auto !important
  font-size: 12px

.tabla-compacta :deep(thead tr)
  height: 24px !important

.tabla-compacta :deep(tbody tr)
  height: 24px !important

.ellipsis-cliente
  max-width: 190px
  white-space: nowrap
  overflow: hidden
  text-overflow: ellipsis

.entrada-pedido
  border: 1px solid rgba(0, 0, 0, 0.24)
  border-radius: 4px
  font-size: 14px
  padding: 2px 4px
  text-align: right

.entrada-cantidad
  width: 3em

.entrada-caja
  text-align: left
  background: white

.entrada-precio
  width: 4em

.entrada-precio-bloqueada
  background-color: #f0f0f0
  color: rgba(0, 0, 0, 0.7)
  cursor: pointer
  pointer-events: none
</style>
