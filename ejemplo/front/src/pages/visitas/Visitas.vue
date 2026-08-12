<template>
  <q-page class="q-pa-xs">
    <q-card flat bordered>
      <div ref="mapRef" class="visitas-map" />

      <div class="map-toolbar-left">
        <q-btn
          color="orange"
          icon="format_list_bulleted"
          :outline="!showAllDays"
          @click="setDayFilter(false)"
          no-caps
          label="Del dia"
        />
        <q-btn
          color="primary"
          icon="calendar_month"
          :outline="showAllDays"
          @click="setDayFilter(true)"
          no-caps
          label="Todos"
          class="q-ml-xs"
        />
      </div>

      <div class="map-toolbar-right">
        <q-btn color="negative" icon="my_location" @click="locateMe" round dense />
      </div>


      <div class="map-debug-summary">
        <div class="map-debug-row">
          <q-chip dense square color="positive" text-color="white">P: {{ resumenVisitas.pedidos }}</q-chip>
          <q-chip dense square color="negative" text-color="white">NP: {{ resumenVisitas.noPedidos }}</q-chip>
          <q-chip dense square color="warning" text-color="black">R: {{ resumenVisitas.retornar }}</q-chip>
          <q-chip dense square color="red" text-color="white">Total: {{ resumenVisitas.total }}</q-chip>
          <q-chip dense square :color="resumenVisitas.efectividad === 100 ? 'teal' : 'deep-orange'" text-color="white">
            Efec: {{ resumenVisitas.efectividad }}%
          </q-chip>
        </div>
      </div>
    </q-card>

    <q-card flat bordered class="q-mt-xs">
      <q-card-section class="row items-center q-col-gutter-sm q-py-sm">
        <div class="col-12 col-md-4">
          <q-input v-model="search" dense outlined label="Buscar cliente" debounce="350" @update:model-value="cargarClientes">
            <template #append><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-12 col-md-auto">
          <q-chip color="teal" text-color="white">Clientes: {{ clientesContables.length }}</q-chip>
        </div>
        <div class="col-12 col-md-auto">
          <q-chip color="primary" text-color="white">Dia: {{ dayLabel }}</q-chip>
        </div>
      </q-card-section>

      <q-markup-table dense flat wrap-cells>
        <thead>
        <tr>
          <th>Accion</th>
          <th>Cliente</th>
          <th>Direccion</th>
          <th>Telefono</th>
          <th>Estado</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="c in clientesOrdenados" :key="c.id" :class="[selectedCliente?.id === c.id ? 'row-selected' : '', rowClassByCliente(c)]" @click="openAcciones(c)">
          <td>
            <q-btn color="purple" icon="visibility" dense round @click.stop="verClienteEnMapa(c)" />
          </td>
          <td>
            {{ c.codcli }} -{{ c.nombre }}
          </td>
          <td>{{ c.direccion || '-' }}</td>
          <td>{{ c.telefono || '-' }}</td>
          <td>
            <q-chip dense :color="statusColor(clienteStatus(c.id))" text-color="white">
              {{ clienteStatus(c.id) }}
            </q-chip>
          </td>
        </tr>
        </tbody>
      </q-markup-table>
    </q-card>

    <q-dialog
      v-model="dialogAcciones"
      :maximized="$q.screen.lt.md"
      transition-show="slide-up"
      transition-hide="slide-down"
    >
      <q-card class="visitas-acciones-card">
        <q-card-section class="row items-center q-pb-none visitas-acciones-header">
          <div class="visitas-acciones-title">
            <div class="text-h6">{{ selectedCliente?.codcli || selectedCliente?.id }} {{ selectedCliente?.nombre }}</div>
          </div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-card-section class="visitas-acciones-body">
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-md-5">
              <div><b>Cel:</b> {{ selectedCliente?.telefono || '-' }}</div>
              <div><b>Direccion:</b> {{ selectedCliente?.direccion || '-' }}</div>
              <div class="q-mt-sm">
                Estado para pedidos:
                <q-chip dense :color="statusColor(clienteStatus(selectedCliente?.id))" text-color="white">
                  {{ clienteStatus(selectedCliente?.id) }}
                </q-chip>
              </div>
            </div>
            <div class="col-12 col-md-7">
              <q-input v-model="comentario" label="Comentario" outlined dense type="textarea" autogrow />
            </div>
            <div class="col-12">
              <div class="text-subtitle2 q-mb-sm">Fotos del cliente</div>
              <div v-if="clienteFotosSeleccionado.length" class="row q-col-gutter-sm">
                <div v-for="(foto, index) in clienteFotosSeleccionado" :key="`${foto}-${index}`" class="col-6 col-sm-4 col-md-4">
                  <q-card flat bordered class="cliente-foto-card">
                    <q-img :src="clienteFotoUrl(foto)" class="cliente-foto-img" fit="cover" />
                  </q-card>
                </div>
              </div>
              <div v-else class="text-grey-7">Este cliente no tiene fotos registradas.</div>
            </div>
          </div>
        </q-card-section>
        <q-card-actions align="stretch" class="row q-col-gutter-sm q-px-md q-pb-md visitas-acciones-footer">
          <div class="col-12 col-md-6">
            <q-btn color="green" icon="shopping_cart" no-caps class="full-width" label="Realizar pedido" @click="accionSeleccionada('REALIZAR_PEDIDO')" :loading="loadingAccion === 'REALIZAR_PEDIDO'" :disable="Boolean(loadingAccion)" />
          </div>
          <div class="col-12 col-md-6">
            <q-btn color="warning" icon="history" no-caps class="full-width" label="Retornar" @click="accionSeleccionada('RETORNAR')" :loading="loadingAccion === 'RETORNAR'" :disable="Boolean(loadingAccion)" />
          </div>
          <div class="col-12 col-md-6">
            <q-btn color="negative" icon="close" no-caps class="full-width" label="No pedido" @click="accionSeleccionada('NO_PEDIDO')" :loading="loadingAccion === 'NO_PEDIDO'" :disable="Boolean(loadingAccion)" />
          </div>
          <div class="col-12 col-md-6">
            <q-btn color="purple" icon="map" no-caps class="full-width" label="Generar ruta" @click="accionSeleccionada('GENERAR_RUTA')" :loading="loadingAccion === 'GENERAR_RUTA'" :disable="Boolean(loadingAccion)" />
          </div>
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogPedido" maximized>
      <q-card>
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ selectedCliente?.codcli || selectedCliente?.id }} {{ selectedCliente?.nombre }}</div>
          <q-space />
          <q-btn flat round dense icon="close" @click="dialogPedido = false" />
        </q-card-section>

        <q-card-section class="q-pt-sm">
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-md-4">
              <q-option-group
                v-model="tipoPago"
                :options="tiposPagoPedido"
                type="radio"
                color="primary"
                inline
              />
              <div v-if="!clientePuedeCredito" class="text-caption text-negative q-mt-xs">
                Este cliente no puede tener credito.
              </div>
            </div>
            <div class="col-12 col-md-2">
              <q-toggle v-model="facturadoPedido" label="Facturado" />
            </div>
            <div class="col-12 col-md-3">
              <q-input v-model="fechaPedido" type="date" label="Fecha" dense outlined />
            </div>
            <div class="col-12 col-md-3">
              <q-select
                v-model="horaPedido"
                :options="horariosPedido"
                label="Horario"
                dense
                outlined
                emit-value
                map-options
                clearable
              />
            </div>
            <div class="col-12 col-md-12">
              <q-input v-model="comentario" label="Comentario" dense outlined />
            </div>
            <div v-if="requiereClienteBaja" class="col-12 col-md-6">
              <q-select
                v-model="selectedClienteBajaId"
                :options="clientesBajaOptions"
                label="Cliente asociado"
                dense
                outlined
                emit-value
                map-options
                clearable
              />
            </div>
            <div class="col-12 col-md-10">
              <q-select
                v-model="productoSeleccionado"
                :options="productos"
                option-label="label"
                option-value="id"
                emit-value
                map-options
                dense
                outlined
                label="Productos (todos, con busqueda y paginacion)"
                :loading="loadingProductos"
                use-input
                input-debounce="350"
                @filter="filtrarProductos"
              >
                <template #selected-item="scope">
                  <div class="row items-center no-wrap q-gutter-xs">
                    <q-avatar rounded size="24px">
                      <q-img :src="productImageUrl(scope?.opt?.imagen)" />
                    </q-avatar>
                    <span class="ellipsis">{{ scope?.opt?.label || '' }}</span>
                    <q-chip
                      dense
                      square
                      size="sm"
                      :color="tipoProductoColor(scope?.opt?.tipo)"
                      text-color="white"
                    >
                      ({{ tipoProductoLabel(scope?.opt?.tipo) }})
                    </q-chip>
                    <q-chip
                      v-if="scope?.opt?.codigo_unidad"
                      dense
                      square
                      size="sm"
                      :color="unidadProductoColor(scope?.opt?.codigo_unidad)"
                      text-color="white"
                    >
                      ({{ unidadProductoLabel(scope?.opt?.codigo_unidad) }})
                    </q-chip>
                  </div>
                </template>
                <template #option="scope">
                  <div>
                    <q-item v-bind="scope.itemProps">
                      <q-item-section avatar>
                        <q-avatar rounded size="28px">
                          <q-img :src="productImageUrl(scope.opt.imagen)" />
                        </q-avatar>
                      </q-item-section>
                      <q-item-section>
                        <q-item-label class="row items-center q-gutter-xs">
                          <span>{{ scope.opt.label }}</span>
                          <q-chip
                            dense
                            square
                            size="sm"
                            :color="tipoProductoColor(scope.opt.tipo)"
                            text-color="white"
                          >
                            ({{ tipoProductoLabel(scope.opt.tipo) }})
                          </q-chip>
                          <q-chip
                            v-if="scope.opt.codigo_unidad"
                            dense
                            square
                            size="sm"
                            :color="unidadProductoColor(scope.opt.codigo_unidad)"
                            text-color="white"
                          >
                            ({{ unidadProductoLabel(scope.opt.codigo_unidad) }})
                          </q-chip>
                        </q-item-label>
                      </q-item-section>
                    </q-item>
                    <q-separator v-if="scope.index < (productos.length - 1)" color="grey-4" inset />
                  </div>
                </template>
              </q-select>
              <div class="row items-center q-col-gutter-xs q-mt-xs">
                <div class="col-auto">
                  <q-btn
                    flat
                    dense
                    icon="chevron_left"
                    @click="cambiarPaginaProductos(-1)"
                    :disable="loadingProductos || productosPagination.page <= 1"
                  />
                </div>
                <div class="col-auto">
                  <q-btn
                    flat
                    dense
                    icon="chevron_right"
                    @click="cambiarPaginaProductos(1)"
                    :disable="loadingProductos || productosPagination.page >= productosPagination.lastPage"
                  />
                </div>
                <div class="col-auto">
                  <q-chip dense square color="primary" text-color="white">
                    Pagina {{ productosPagination.page }} / {{ productosPagination.lastPage }}
                  </q-chip>
                </div>
                <div class="col-auto">
                  <q-chip dense square color="grey-7" text-color="white">
                    Total: {{ productosPagination.rowsNumber }}
                  </q-chip>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-2">
              <q-btn color="negative" icon="add" class="full-width" @click="agregarProducto" />
            </div>
          </div>

          <q-markup-table dense flat bordered class="q-mt-sm">
            <thead>
            <tr>
              <th>Detalle</th>
              <th>Subtotal</th>
              <th>Cantidad</th>
              <th>Precio</th>
              <th>Peso est.</th>
              <th>Cod</th>
              <th>Nombre</th>
              <th>Obs</th>
<!--              <th></th>-->
            </tr>
            </thead>
            <tbody>
            <tr v-for="(p, index) in pedidoItems" :key="`${p.producto_id}-${index}`">
              <td>
<!--                <q-btn flat dense round icon="tune" color="purple" @click="openDetalleDialog(p, index)" />-->
                <q-btn-dropdown dense :label="'Op(' + p.tipo + ')'" :color="tipoProductoColor(p.tipo)" no-caps size="10px">
                  <q-list>
                    <q-item clickable v-ripple @click="openDetalleDialog(p, index)" v-close-popup>
                      <q-item-section avatar><q-icon name="tune" color="purple" /></q-item-section>
                      <q-item-section>Editar detalle</q-item-section>
                    </q-item>
                    <q-item clickable v-ripple @click="removePedidoItem(index)" v-close-popup>
                      <q-item-section avatar><q-icon name="delete" color="negative" /></q-item-section>
                      <q-item-section>Eliminar producto</q-item-section>
                    </q-item>
                  </q-list>
                </q-btn-dropdown>
<!--                <pre>{{p.tipo}}</pre>-->
              </td>
              <td>{{ lineSubtotal(p).toFixed(2) }}</td>
              <td>
                <input type="number" v-model="p.cantidad" style="width: 55px" min="1" step="1" @blur="normalizePedidoItemNumbers(p)" />
              </td>
              <td>
                <input type="number" v-model="p.precio" style="width: 65px" min="0" step="0.01" @blur="normalizePedidoItemNumbers(p)" />
              </td>
              <td>
                <input type="number" v-model="p.peso_estimado" style="width: 70px" min="0" step="0.001" placeholder="1.000" @blur="normalizePedidoItemNumbers(p)" />
              </td>
              <td>{{ p.codigo || p.producto_id }}</td>
              <td>
                <div class="row items-center no-wrap q-gutter-sm">
                  <q-avatar rounded size="30px">
                    <q-img :src="productImageUrl(p.imagen)" />
                  </q-avatar>
                  <div class="row items-center q-gutter-xs">
                    <span>{{ p.nombre }}</span>
                    <q-chip
                      dense
                      square
                      size="sm"
                      :color="tipoProductoColor(p.tipo)"
                      text-color="white"
                    >
                      ({{ tipoProductoLabel(p.tipo) }})
                    </q-chip>
                    <q-chip
                      v-if="p.codigo_unidad"
                      dense
                      square
                      size="sm"
                      :color="unidadProductoColor(p.codigo_unidad)"
                      text-color="white"
                    >
                      ({{ unidadProductoLabel(p.codigo_unidad) }})
                    </q-chip>
                  </div>
                </div>
              </td>
              <td>{{ p.observacion || '-' }}</td>
<!--              <td>-->
<!--                <q-btn-->
<!--                  flat-->
<!--                  dense-->
<!--                  round-->
<!--                  icon="delete"-->
<!--                  color="negative"-->
<!--                  @click="pedidoItems.splice(index, 1)"-->
<!--                />-->
<!--              </td>-->
            </tr>
            <tr v-if="pedidoItems.length === 0">
              <td colspan="9" class="text-grey-7">Sin datos disponibles</td>
            </tr>
            </tbody>
          </q-markup-table>

          <div class="text-h6 q-mt-sm">Total: {{ totalPedido.toFixed(2) }} Bs.</div>
        </q-card-section>

        <q-card-actions align="between" class="q-pa-md">
          <q-btn flat color="negative" label="Cerrar" @click="dialogPedido = false" />
          <q-btn color="green" no-caps icon="send" label="Realizar pedido" :loading="loadingPedido" :disable="loadingPedido || Boolean(loadingAccion)" @click="guardarPedido" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogDetalle">
      <q-card style="width: 450px; max-width: 96vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Pedido {{ detalleTipoLabel }}</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-card-section>
          <div class="row q-col-gutter-sm" v-if="detalleTipo === 'EMBUTIDO'">
            <div class="col-12">
              <q-input v-model="detalleEdit.observacion" dense outlined label="Observacion detalle" />
            </div>
          </div>

          <div class="row q-col-gutter-sm" v-else-if="detalleTipo === 'HUEVO'">
            <div class="col-12">
              <q-input v-model="detalleEdit.observacion" dense outlined label="Observacion detalle huevo" />
            </div>
          </div>

          <div class="row q-col-gutter-sm" v-else-if="detalleTipo === 'RES'">
            <div class="col-12 col-md-4"><q-input v-model="detalleEdit.precio_res" dense outlined label="Precio RES" /></div>
            <div class="col-12 col-md-4"><q-input v-model="detalleEdit.res_trozado" dense outlined label="Res trozado" /></div>
            <div class="col-12 col-md-4"><q-input v-model="detalleEdit.res_entero" dense outlined label="Res entero" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.res_pierna" dense outlined label="Res pierna" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.res_brazo" dense outlined label="Res brazo" /></div>
            <div class="col-12"><q-input v-model="detalleEdit.observacion" dense outlined label="Observacion detalle" /></div>
          </div>

          <div class="row q-col-gutter-sm" v-else-if="detalleTipo === 'CERDO'">
            <div class="col-12 col-md-4"><q-input v-model="detalleEdit.cerdo_precio_total" dense outlined label="Precio total" /></div>
            <div class="col-12 col-md-4"><q-input v-model="detalleEdit.cerdo_entero" dense outlined label="Cerdo entero" /></div>
            <div class="col-12 col-md-4"><q-input v-model="detalleEdit.cerdo_kilo" dense outlined label="Cerdo kilo" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.cerdo_desmembrado" dense outlined label="Cerdo desmembrado" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.cerdo_corte" dense outlined label="Cerdo corte" /></div>
            <div class="col-12"><q-input v-model="detalleEdit.observacion" dense outlined label="Observacion detalle" /></div>
          </div>

          <div class="row q-col-gutter-sm" v-else-if="detalleTipo === 'POLLO'">
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_b5" dense outlined label="Cja b5" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_b5" dense outlined label="Uni b5" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_b6" dense outlined label="Cja b6" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_b6" dense outlined label="Uni b6" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_104" dense outlined label="Cja-104 (1.5-1.7)" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_104" dense outlined label="Unid-104" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_105" dense outlined label="Cja-105 (1.7-1.9)" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_105" dense outlined label="Unid-105" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_106" dense outlined label="Cja-106 (1.9-2.2)" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_106" dense outlined label="Unid-106" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_107" dense outlined label="Cja-107 (2.2-2.5)" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_107" dense outlined label="Unid-107" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_108" dense outlined label="Cja-108 (2.6-2.7)" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_108" dense outlined label="Unid-108" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_109" dense outlined label="Cja-109 (2.8-2.9)" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_109" dense outlined label="Unid-109" /></div>
            <div class="col-12"><q-input v-model="detalleEdit.pollo_rango_unidades" dense outlined label="Rango pollo (unidades)" /></div>

            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_ala" dense outlined label="Ala" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_ala_unidad" :options="unidadesPollo" dense outlined label="Unidad ala" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cadera" dense outlined label="Cadera" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_cadera_unidad" :options="unidadesPollo" dense outlined label="Unidad cadera" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_pecho" dense outlined label="Pecho" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_pecho_unidad" :options="unidadesPollo" dense outlined label="Unidad pecho" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_pi_mu" dense outlined label="Pi/Mu" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_pi_mu_unidad" :options="unidadesPollo" dense outlined label="Unidad pi/mu" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_filete" dense outlined label="Filete" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_filete_unidad" :options="unidadesPollo" dense outlined label="Unidad filete" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cuello" dense outlined label="Cuello" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_cuello_unidad" :options="unidadesPollo" dense outlined label="Unidad cuello" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_hueso" dense outlined label="Hueso" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_hueso_unidad" :options="unidadesPollo" dense outlined label="Unidad hueso" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_menudencia" dense outlined label="Menudencia" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_menudencia_unidad" :options="unidadesPollo" dense outlined label="Unidad menudencia" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_bs" dense outlined label="BS" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_bs2" dense outlined label="BS2" /></div>
            <div class="col-12"><q-input v-model="detalleEdit.observacion" dense outlined label="Observacion detalle" /></div>
          </div>

          <div class="row q-col-gutter-sm" v-else>
            <div class="col-12">
              <q-input v-model="detalleEdit.observacion" dense outlined label="Observacion detalle" />
            </div>
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat color="negative" label="Cerrar" v-close-popup />
          <q-btn color="primary" label="Guardar detalle" @click="saveDetalleDialog" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import markerIcon2xUrl from 'leaflet/dist/images/marker-icon-2x.png'
import markerIconUrl from 'leaflet/dist/images/marker-icon.png'
import markerShadowUrl from 'leaflet/dist/images/marker-shadow.png'

L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2xUrl,
  iconUrl: markerIconUrl,
  shadowUrl: markerShadowUrl
})

const ORURO_CENTER = [-17.967, -67.106]
const DAY_MAP = ['do', 'lu', 'ma', 'mi', 'ju', 'vi', 'sa']
const CLIENTES_EXTRA_VISITAS = [1520, 1179]

export default {
  name: 'VisitasPage',
  data () {
    return {
      loading: false,
      loadingPedido: false,
      loadingAccion: '',
      map: null,
      markersLayer: null,
      zonasLayer: null,
      meMarker: null,
      search: '',
      showAllDays: false,
      clientes: [],
      clientesBajaSource: [],
      visitasByCliente: {},
      selectedCliente: null,
      selectedClienteBajaId: null,
      comentario: '',
      dialogAcciones: false,
      dialogPedido: false,
      tiposPago: [
        { label: 'Contado', value: 'Contado' },
        { label: 'Pago QR', value: 'QR' },
        { label: 'Credito', value: 'Credito' },
        { label: 'Boleta anterior', value: 'Boleta anterior' }
      ],
      tipoPago: 'Contado',
      facturadoPedido: false,
      fechaPedido: new Date().toISOString().slice(0, 10),
      horariosPedido: [
        { label: 'Sin horario', value: null },
        { label: '06:00-07:30', value: '06:00-07:30' },
        { label: '07:30-09:00', value: '07:30-09:00' },
        { label: '09:00-10:30', value: '09:00-10:30' },
        { label: '10:30-12:00', value: '10:30-12:00' },
        { label: 'SEGUNDA VUELTA', value: 'SEGUNDA VUELTA' },
        { label: 'SE RECOGE', value: 'SE RECOGE' },
      ],
      horaPedido: null,
      productos: [],
      loadingProductos: false,
      productosBusqueda: '',
      productosPagination: {
        page: 1,
        rowsPerPage: 25,
        rowsNumber: 0,
        lastPage: 1
      },
      productoSeleccionado: null,
      pedidoItems: [],
      dialogDetalle: false,
      detalleEditIndex: -1,
      detalleEdit: {},
      detalleTipo: 'EMBUTIDO',
      unidadesPollo: ['KG', 'CAJA', 'UNIDAD'],
      isAlive: true
    }
  },
  computed: {
    dayCode () {
      return DAY_MAP[new Date().getDay()]
    },
    dayLabel () {
      const labels = { lu: 'Lunes', ma: 'Martes', mi: 'Miercoles', ju: 'Jueves', vi: 'Viernes', sa: 'Sabado', do: 'Domingo' }
      return labels[this.dayCode]
    },
    totalPedido () {
      return this.pedidoItems.reduce((acc, p) => acc + this.lineSubtotal(p), 0)
    },
    clientePuedeCredito () {
      return this.selectedCliente?.puede_credito !== false
    },
    tiposPagoPedido () {
      return this.tiposPago.filter(option => option.value !== 'Credito' || this.clientePuedeCredito)
    },
    clientesContables () {
      return this.clientes.filter(cliente => !CLIENTES_EXTRA_VISITAS.includes(Number(cliente?.id)))
    },
    requiereClienteBaja () {
      return CLIENTES_EXTRA_VISITAS.includes(Number(this.selectedCliente?.id))
    },
    clientesBajaOptions () {
      return this.clientesBajaSource
        .filter(cliente => Number(cliente?.id) !== Number(this.selectedCliente?.id))
        .map(cliente => ({
          label: `${cliente.codcli || cliente.id} - ${cliente.nombre}`,
          value: cliente.id
        }))
        .sort((a, b) => String(a.label).localeCompare(String(b.label), 'es'))
    },
    resumenVisitas () {
      const total = this.clientesContables.length
      let pedidos = 0
      let noPedidos = 0
      let retornar = 0

      this.clientesContables.forEach((cliente) => {
        const status = this.clienteStatus(cliente?.id)
        if (status === 'REALIZAR_PEDIDO') pedidos += 1
        else if (status === 'NO_PEDIDO') noPedidos += 1
        else if (status === 'RETORNAR') retornar += 1
      })

      const gestionados = pedidos + noPedidos + retornar
      const efectividad = total > 0 ? Math.round((gestionados / total) * 100) : 0

      return {
        pedidos,
        noPedidos,
        retornar,
        total,
        efectividad
      }
    },
    clientesOrdenados () {
      const prioridad = {
        ACTIVO: 1,
        RETORNAR: 2,
        NO_PEDIDO: 3,
        REALIZAR_PEDIDO: 4,
      }

      return [...this.clientes].sort((a, b) => {
        const sa = this.clienteStatus(a?.id)
        const sb = this.clienteStatus(b?.id)
        const pa = prioridad[sa] || 99
        const pb = prioridad[sb] || 99

        if (pa !== pb) return pa - pb

        const na = String(a?.nombre || '').toLowerCase()
        const nb = String(b?.nombre || '').toLowerCase()
        return na.localeCompare(nb, 'es')
      })
    },
    detalleTipoLabel () {
      if (this.detalleTipo === 'RES') return 'Res'
      if (this.detalleTipo === 'CERDO') return 'Cerdo'
      if (this.detalleTipo === 'POLLO') return 'Pollo'
      if (this.detalleTipo === 'HUEVO') return 'Huevo'
      if (this.detalleTipo === 'PET') return 'Pet'
      return 'Embutido'
    },
    clienteFotosSeleccionado () {
      return Array.isArray(this.selectedCliente?.fotos) ? this.selectedCliente.fotos : []
    }
  },
  mounted () {
    this.initMap()
    this.cargarClientes()
    this.cargarClientesBaja()
    this.cargarProductos()
  },
  beforeUnmount () {
    this.isAlive = false
    try {
      if (this.map) {
        this.map.off()
        this.map.remove()
      }
    } catch (_) {}
    this.map = null
    this.markersLayer = null
    this.zonasLayer = null
    this.meMarker = null
  },
  methods: {
    markerColor (status) {
      const s = this.normalizeStatus(status)
      if (s === 'REALIZAR_PEDIDO') return '#16a34a'
      if (s === 'RETORNAR') return '#f4b400'
      if (s === 'NO_PEDIDO') return '#e53935'
      if (s === 'GENERAR_RUTA') return '#7e22ce'
      return '#1e88e5'
    },
    statusColor (status) {
      const s = this.normalizeStatus(status)
      if (s === 'RETORNAR') return 'warning'
      if (s === 'NO_PEDIDO') return 'negative'
      if (s === 'REALIZAR_PEDIDO') return 'positive'
      if (s === 'GENERAR_RUTA') return 'purple'
      return 'primary'
    },
    normalizeStatus (status) {
      return String(status || 'ACTIVO').trim().toUpperCase()
    },
    normalizeTipoProducto (tipo) {
      const t = String(tipo || 'EMBUTIDO').trim().toUpperCase()
      if (t === 'NORMAL') return 'EMBUTIDO'
      if (!t) return 'EMBUTIDO'
      return t
    },
    tipoProductoLabel (tipo) {
      const t = this.normalizeTipoProducto(tipo)
      if (t === 'HUEVO') return 'Huevo'
      if (t === 'PET') return 'Pet'
      if (t === 'RES') return 'Res'
      if (t === 'CERDO') return 'Cerdo'
      if (t === 'POLLO') return 'Pollo'
      return 'Embutido'
    },
    tipoProductoColor (tipo) {
      const t = this.normalizeTipoProducto(tipo)
      if (t === 'HUEVO') return 'amber-8'
      if (t === 'PET') return 'blue-grey'
      if (t === 'RES') return 'red'
      if (t === 'CERDO') return 'brown'
      if (t === 'POLLO') return 'orange'
      return 'green-7'
    },
    unidadProductoLabel (codigoUnidad) {
      const unidad = String(codigoUnidad || '').trim().toUpperCase()
      if (unidad === 'KG') return 'Kilogramo'
      if (unidad === 'U' || unidad === 'UNIDA') return 'Unidad'
      return unidad
    },
    unidadProductoColor (codigoUnidad) {
      const unidad = String(codigoUnidad || '').trim().toUpperCase()
      if (unidad === 'KG') return 'teal'
      if (unidad === 'U' || unidad === 'UNIDA') return 'deep-orange'
      return 'grey-7'
    },
    getPedidoTipos () {
      return [...new Set(this.pedidoItems.map(item => this.normalizeTipoProducto(item?.tipo)))]
    },
    getPedidoTipoActual () {
      const tipos = this.getPedidoTipos()
      return tipos.length === 1 ? tipos[0] : null
    },
    pesoMultiplicador (item) {
      const peso = Number(item?.peso_estimado)
      return Number.isFinite(peso) && peso > 0 ? peso : 1
    },
    lineSubtotal (item) {
      const cantidad = Number(item?.cantidad || 0)
      const precio = Number(item?.precio || 0)
      return cantidad * precio * this.pesoMultiplicador(item)
    },
    normalizePedidoItemNumbers (item) {
      if (!item) return
      const cantidad = Number(item.cantidad)
      const precio = Number(item.precio)
      const peso = Number(item.peso_estimado)

      item.cantidad = Number.isFinite(cantidad) && cantidad > 0 ? cantidad : 1
      item.precio = Number.isFinite(precio) && precio >= 0 ? precio : 0
      item.peso_estimado = Number.isFinite(peso) && peso > 0 ? peso : null
    },
    sanitizeDetalleExtra (tipo, detalle = {}) {
      const defaults = this.detalleDefaultsByTipo(this.normalizeTipoProducto(tipo))
      const sanitized = { ...defaults }

      Object.keys(defaults).forEach((key) => {
        if (Object.prototype.hasOwnProperty.call(detalle, key)) {
          sanitized[key] = detalle[key]
        }
      })

      return sanitized
    },
    detalleDefaultsByTipo (tipo) {
      if (tipo === 'RES') {
        return {
          precio_res: '',
          res_trozado: '',
          res_entero: '',
          res_pierna: '',
          res_brazo: '',
          observacion: '',
        }
      }
      if (tipo === 'CERDO') {
        return {
          cerdo_precio_total: '',
          cerdo_entero: '',
          cerdo_desmembrado: '',
          cerdo_corte: '',
          cerdo_kilo: '',
          observacion: '',
        }
      }
      if (tipo === 'POLLO') {
        return {
          pollo_cja_b5: '',
          pollo_uni_b5: '',
          pollo_cja_b6: '',
          pollo_uni_b6: '',
          pollo_cja_104: '',
          pollo_uni_104: '',
          pollo_cja_105: '',
          pollo_uni_105: '',
          pollo_cja_106: '',
          pollo_uni_106: '',
          pollo_cja_107: '',
          pollo_uni_107: '',
          pollo_cja_108: '',
          pollo_uni_108: '',
          pollo_cja_109: '',
          pollo_uni_109: '',
          pollo_rango_unidades: '',
          pollo_ala: '',
          pollo_ala_unidad: 'KG',
          pollo_cadera: '',
          pollo_cadera_unidad: 'KG',
          pollo_pecho: '',
          pollo_pecho_unidad: 'KG',
          pollo_pi_mu: '',
          pollo_pi_mu_unidad: 'KG',
          pollo_filete: '',
          pollo_filete_unidad: 'KG',
          pollo_cuello: '',
          pollo_cuello_unidad: 'KG',
          pollo_hueso: '',
          pollo_hueso_unidad: 'KG',
          pollo_menudencia: '',
          pollo_menudencia_unidad: 'KG',
          pollo_bs: '',
          pollo_bs2: '',
          observacion: '',
        }
      }
      return { observacion: '' }
    },
    openDetalleDialog (item, index) {
      this.detalleEditIndex = index
      this.detalleTipo = this.normalizeTipoProducto(item?.tipo)
      const defaults = this.detalleDefaultsByTipo(this.detalleTipo)
      this.detalleEdit = { ...defaults, ...this.sanitizeDetalleExtra(this.detalleTipo, item?.detalle_extra || {}) }
      this.dialogDetalle = true
    },
    saveDetalleDialog () {
      if (this.detalleEditIndex < 0 || !this.pedidoItems[this.detalleEditIndex]) return
      const current = this.pedidoItems[this.detalleEditIndex]
      current.detalle_extra = this.sanitizeDetalleExtra(this.detalleTipo, this.detalleEdit)
      current.observacion = this.detalleEdit.observacion || current.observacion || ''
      this.dialogDetalle = false
      this.detalleEditIndex = -1
      this.detalleEdit = {}
    },
    removePedidoItem (index) {
      this.pedidoItems.splice(index, 1)
    },
    rowClassByCliente (cliente) {
      const status = this.clienteStatus(cliente?.id)
      if (status === 'RETORNAR') return 'cliente-retornar'
      if (status === 'NO_PEDIDO') return 'cliente-no-pedido'
      if (status === 'REALIZAR_PEDIDO') return 'cliente-pedido'
      return ''
    },
    clienteStatus (clienteId) {
      if (!clienteId) return 'ACTIVO'
      return this.normalizeStatus(this.visitasByCliente[clienteId]?.tipo_visita || 'ACTIVO')
    },
    mapReady () {
      return !!(this.map && this.map._loaded && this.map.getPane && this.map.getPane('mapPane'))
    },
    clearZonasLayer () {
      if (!this.zonasLayer) return
      this.zonasLayer.clearLayers()
    },
    renderZonasFondo (poligonos) {
      if (!this.mapReady() || !this.zonasLayer) return
      this.clearZonasLayer()

      const rows = Array.isArray(poligonos) ? poligonos : []
      rows.forEach((poligono) => {
        const latlngs = Array.isArray(poligono?.coordenadas)
          ? poligono.coordenadas
            .map(point => [Number(point?.lat), Number(point?.lng)])
            .filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng))
          : []

        if (latlngs.length < 3) return

        const color = poligono?.color || '#607d8b'

        L.polygon(latlngs, {
          pane: 'zonasVisitaPane',
          color,
          fillColor: color,
          weight: 1,
          opacity: 0.45,
          fillOpacity: 0.07,
          interactive: false,
          smoothFactor: 1
        }).addTo(this.zonasLayer)

        latlngs.forEach(([lat, lng]) => {
          L.circleMarker([lat, lng], {
            pane: 'zonasVisitaPane',
            radius: 2,
            color,
            weight: 1,
            opacity: 0.5,
            fillColor: color,
            fillOpacity: 0.18,
            interactive: false
          }).addTo(this.zonasLayer)
        })
      })
    },
    async cargarZonasFondo () {
      if (!this.mapReady()) return
      try {
        const res = await fetch('https://bsofiafactu.tuprogam.com/api/public/mapa-zona/2')
        if (!res.ok) throw new Error('No se pudo cargar mapa zona')
        const data = await res.json()
        if (!this.isAlive) return
        this.renderZonasFondo(data)
      } catch (_) {
        this.clearZonasLayer()
      }
    },
    initMap () {
      if (!this.$refs.mapRef || !this.isAlive) return
      this.map = L.map(this.$refs.mapRef, { center: ORURO_CENTER, zoom: 13, zoomAnimation: false, fadeAnimation: false, markerZoomAnimation: false })

      const googleRoad = L.tileLayer('https://mt1.google.com/vt/lyrs=r&x={x}&y={y}&z={z}', { maxZoom: 21, attribution: 'Map data © Google' })
      const googleSat = L.tileLayer('https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', { maxZoom: 21, attribution: 'Map data © Google' })
      const googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { maxZoom: 21, attribution: 'Map data © Google' })
      const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' })

      googleRoad.addTo(this.map)
      L.control.layers({
        'Google Calle': googleRoad,
        'Google Satelite': googleSat,
        'Google Hibrido': googleHybrid,
        OpenStreetMap: osm
      }, {}, { position: 'topleft' }).addTo(this.map)

      this.map.createPane('zonasVisitaPane')
      const zonasPane = this.map.getPane('zonasVisitaPane')
      if (zonasPane) {
        zonasPane.style.zIndex = 350
        zonasPane.style.pointerEvents = 'none'
      }

      this.zonasLayer = L.layerGroup().addTo(this.map)
      this.markersLayer = L.layerGroup().addTo(this.map)
      this.cargarZonasFondo()
    },
    async cargarClientes () {
      this.loading = true
      try {
        const res = await this.$axios.get('visitas/clientes', {
          params: {
            search: this.search,
            per_page: 500,
            solo_mios: 1,
            solo_dia: this.showAllDays ? 0 : 1,
            dia: this.dayCode
          }
        })
        if (!this.isAlive) return
        this.clientes = res.data?.data || []
        await this.cargarVisitas()
        this.renderMarkers()
      } catch (e) {
        this.$alert.error(e.response?.data?.message || 'No se pudo cargar clientes de visitas')
      } finally {
        if (this.isAlive) this.loading = false
      }
    },
    async cargarClientesBaja () {
      try {
        const res = await this.$axios.get('clientes', {
          params: {
            per_page: 5000,
            solo_mios: 1,
            solo_dia: 0
          }
        })
        if (!this.isAlive) return
        const data = res.data?.data || []
        this.clientesBajaSource = data
          .filter(cliente => !CLIENTES_EXTRA_VISITAS.includes(Number(cliente?.id)))
      } catch (_) {
        this.clientesBajaSource = []
      }
    },
    renderMarkers () {
      if (!this.mapReady() || !this.markersLayer) return
      this.markersLayer.clearLayers()
      const bounds = []

      this.clientes.forEach(c => {
        if (!Number.isFinite(Number(c.latitud)) || !Number.isFinite(Number(c.longitud))) return
        const lat = Number(c.latitud)
        const lng = Number(c.longitud)
        const idLabel = c.codcli || c.id
        const status = this.clienteStatus(c.id)
        const color = this.markerColor(status)
        const marker = L.circleMarker([lat, lng], {
          radius: 9,
          fillColor: color,
          color: '#fff',
          weight: 3,
          fillOpacity: 0.95
        }).addTo(this.markersLayer)
        marker.bindTooltip(String(idLabel), { permanent: true, direction: 'top', className: 'cliente-id-tooltip' })
        marker.on('click', () => this.openAcciones(c))
        bounds.push([lat, lng])
      })

      if (bounds.length > 0 && this.mapReady()) {
        try {
          this.map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16, animate: false })
        } catch (_) {}
      }
    },
    setDayFilter (allDays) {
      this.showAllDays = allDays
      this.cargarClientes()
    },
    async cargarVisitas () {
      try {
        const res = await this.$axios.get('visitas', {
          params: {
            solo_mios: 1,
            all_days: 0,
            latest_per_cliente: 1,
            fecha: new Date().toISOString().slice(0, 10),
          }
        })
        const data = Array.isArray(res.data) ? res.data : (res.data?.data || [])
        const byCliente = {}
        data.forEach(v => {
          if (!v?.cliente_id) return
          byCliente[v.cliente_id] = v
        })
        this.visitasByCliente = byCliente
      } catch (_) {
        this.visitasByCliente = {}
      }
    },
    locateMe () {
      if (!this.mapReady()) return
      if (!navigator.geolocation) {
        this.$alert.error('Geolocalizacion no disponible')
        return
      }
      navigator.geolocation.getCurrentPosition((pos) => {
        if (!this.mapReady()) return
        const lat = Number(pos.coords.latitude.toFixed(7))
        const lng = Number(pos.coords.longitude.toFixed(7))

        if (this.meMarker) this.map.removeLayer(this.meMarker)
        this.meMarker = L.circleMarker([lat, lng], {
          radius: 10,
          fillColor: '#ff1744',
          color: '#fff',
          weight: 3,
          fillOpacity: 0.95
        }).addTo(this.map)
        this.meMarker.bindPopup('Aqui estoy yo').openPopup()
        try {
          this.map.setView([lat, lng], 16, { animate: false })
        } catch (_) {}
      }, () => this.$alert.error('No se pudo obtener tu ubicacion'))
    },
    verClienteEnMapa (cliente) {
      if (!cliente) return
      const lat = Number(cliente.latitud)
      const lng = Number(cliente.longitud)

      if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        this.$alert.error('El cliente no tiene coordenadas registradas')
        return
      }

      this.selectedCliente = cliente

      if (!this.mapReady()) {
        this.$alert.error('El mapa aun no esta listo')
        return
      }

      try {
        this.map.setView([lat, lng], 18, { animate: true })
      } catch (_) {}
    },
    openAcciones (cliente) {
      this.selectedCliente = cliente
      this.selectedClienteBajaId = null
      this.loadingAccion = ''
      this.dialogAcciones = true
    },
    async accionSeleccionada (accion) {
      if (!this.selectedCliente) return
      if (accion === 'REALIZAR_PEDIDO') {
        this.dialogAcciones = false
        this.loadingPedido = false
        this.pedidoItems = []
        this.selectedClienteBajaId = null
        this.tipoPago = this.clientePuedeCredito ? 'Contado' : 'Contado'
        this.facturadoPedido = false
        this.fechaPedido = new Date().toISOString().slice(0, 10)
        this.horaPedido = null
        this.dialogPedido = true
        return
      }
      if (accion === 'GENERAR_RUTA') {
        this.generarRuta()
      }else {
        this.guardarAccion(accion)
      }

    },
    async guardarAccion (accion) {
      if (!this.selectedCliente) return
      this.loadingAccion = accion
      this.loading = true
      try {
        await this.$axios.post('pedidos', {
          tipo_pedido: accion,
          cliente_id: this.selectedCliente.id,
          comentario_visita: this.comentario || '',
          observaciones: this.comentario || '',
          productos: []
        })
        this.$alert.success('Accion registrada')
        this.dialogAcciones = false
        this.comentario = ''
        await this.cargarVisitas()
        this.renderMarkers()
      } catch (e) {
        this.$alert.error(e.response?.data?.message || 'No se pudo registrar la accion')
      } finally {
        this.loadingAccion = ''
        this.loading = false
      }
    },
    generarRuta () {
      if (!this.selectedCliente) return
      const lat = Number(this.selectedCliente.latitud)
      const lng = Number(this.selectedCliente.longitud)
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        this.$alert.error('El cliente no tiene coordenadas')
        return
      }
      window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`, '_blank')
    },
    mapStockOption (p) {
      const pesoEstimado = Number(p.peso_estimado)
      return {
        id: p.id,
        label: `${p.codigo || p.id}-${p.nombre} ${Number(p.precio1 || 0).toFixed(2)}Bs ${Number(p.stock || 0).toFixed(2)}U`,
        nombre: p.nombre,
        codigo: p.codigo,
        imagen: p.imagen || 'uploads/default.png',
        precio: Number(p.precio1 || 0),
        peso_estimado: Number.isFinite(pesoEstimado) && pesoEstimado > 0 ? pesoEstimado : null,
        stock: Number(p.stock || 0),
        tipo: this.normalizeTipoProducto(p.tipo),
        codigo_unidad: p.codigo_unidad || '',
      }
    },
    productImageUrl (path) {
      const safe = path || 'uploads/default.png'
      return `${this.$url}../${safe}`
    },
    clienteFotoUrl (path) {
      const safe = path || 'uploads/default.png'
      return `${this.$url}../${safe}`
    },
    async cargarProductos (search = '', page = 1) {
      this.loadingProductos = true
      const safeSearch = String(search || '').trim()
      const safePage = Number(page || 1)
      const targetPage = Number.isFinite(safePage) && safePage > 0 ? safePage : 1
      try {
        const res = await this.$axios.get('productos', {
          params: {
            search: safeSearch,
            page: targetPage,
            per_page: this.productosPagination.rowsPerPage,
          }
        })
        const rows = Array.isArray(res.data?.data) ? res.data.data : []
        const total = Number(res.data?.total || rows.length || 0)
        const lastPage = Math.max(1, Number(res.data?.last_page || Math.ceil(total / this.productosPagination.rowsPerPage) || 1))

        this.productosBusqueda = safeSearch
        this.productosPagination.page = Math.min(targetPage, lastPage)
        this.productosPagination.rowsNumber = total
        this.productosPagination.lastPage = lastPage
        this.productos = rows.map(this.mapStockOption)
      } catch (_) {
        this.productos = []
        this.productosPagination.rowsNumber = 0
        this.productosPagination.lastPage = 1
      } finally {
        this.loadingProductos = false
      }
    },
    filtrarProductos (val, update) {
      const search = String(val || '').trim()
      this.productosPagination.page = 1
      this.cargarProductos(search, 1).finally(() => {
        update(() => {})
      })
    },
    cambiarPaginaProductos (delta) {
      const nextPage = this.productosPagination.page + Number(delta || 0)
      if (nextPage < 1 || nextPage > this.productosPagination.lastPage) return
      this.cargarProductos(this.productosBusqueda, nextPage)
    },
    agregarProducto () {
      if (!this.productoSeleccionado) return
      const p = this.productos.find(x => x.id === this.productoSeleccionado)
      if (!p) return
      const tipoProducto = this.normalizeTipoProducto(p.tipo)

      this.pedidoItems.push({
        producto_id: p.id,
        codigo: p.codigo,
        nombre: p.nombre,
        imagen: p.imagen || 'uploads/default.png',
        cantidad: 1,
        precio: Number(p.precio || 0),
        peso_estimado: p.peso_estimado ?? null,
        observacion: '',
        tipo: tipoProducto,
        codigo_unidad: p.codigo_unidad || '',
        detalle_extra: this.detalleDefaultsByTipo(tipoProducto),
      })
      this.productoSeleccionado = null
    },
    async guardarPedido () {
      if (!this.selectedCliente) return
      if (this.requiereClienteBaja && !this.selectedClienteBajaId) {
        this.$alert.error('Debe seleccionar el cliente asociado')
        return
      }
      if (this.pedidoItems.length === 0) {
        this.$alert.error('Debe agregar al menos un producto')
        return
      }
      if (this.tipoPago === 'Credito' && !this.clientePuedeCredito) {
        this.$alert.error('Este cliente no puede tener credito')
        this.tipoPago = 'Contado'
        return
      }

      const productos = this.pedidoItems.map(p => {
        this.normalizePedidoItemNumbers(p)
        const peso = this.pesoMultiplicador(p)
        return {
          producto_id: p.producto_id,
          cantidad: Number(p.cantidad || 0),
          // El backend multiplica cantidad * precio, por eso incorporamos peso en el precio final.
          precio: Number((Number(p.precio || 0) * peso).toFixed(6)),
          observacion: p.observacion || '',
          detalle_extra: this.sanitizeDetalleExtra(this.normalizeTipoProducto(p.tipo), p.detalle_extra || {}),
        }
      }).filter(p => p.cantidad > 0 && p.precio >= 0)

      if (productos.length === 0) {
        this.$alert.error('Revise cantidades y precios')
        return
      }

      this.loadingPedido = true
      this.loading = true
      try {
        await this.$axios.post('pedidos', {
          tipo_pedido: 'REALIZAR_PEDIDO',
          tipo_pago: this.tipoPago,
          facturado: this.facturadoPedido,
          fecha: this.fechaPedido,
          hora: this.horaPedido,
          cliente_id: this.selectedCliente.id,
          cliente_baja_id: this.requiereClienteBaja ? this.selectedClienteBajaId : null,
          comentario_visita: this.comentario || '',
          observaciones: this.comentario || '',
          productos
        })
        this.$alert.success('Pedido registrado')
        this.dialogPedido = false
        this.comentario = ''
        this.selectedClienteBajaId = null
        this.pedidoItems = []
        await this.cargarVisitas()
        this.renderMarkers()
      } catch (e) {
        this.$alert.error(e.response?.data?.message || 'No se pudo registrar pedido')
      } finally {
        this.loadingPedido = false
        this.loading = false
      }
    }
  }
}
</script>

<style scoped>
.visitas-map {
  height: 48vh;
  min-height: 330px;
  border-radius: 10px;
}
.map-toolbar-left {
  position: absolute;
  left: 16px;
  bottom: 12px;
  z-index: 500;
}
.map-toolbar-right {
  position: absolute;
  right: 16px;
  bottom: 12px;
  z-index: 500;
}
.row-selected {
  background: #f0f8ff;
}
.cliente-link {
  justify-content: flex-start;
  width: 100%;
  text-align: left;
}
.cliente-pedido {
  background: #dcfce7;
}
.cliente-retornar {
  background: #fff2b3;
}
.cliente-no-pedido {
  background: #ffd6d6;
}
.visitas-acciones-card {
  width: min(920px, 95vw);
  max-width: 95vw;
  display: flex;
  flex-direction: column;
}
.visitas-acciones-header {
  padding-top: 14px;
}
.visitas-acciones-title {
  min-width: 0;
  flex: 1 1 auto;
}
.visitas-acciones-body {
  overflow-y: auto;
}
.visitas-acciones-footer {
  margin-top: auto;
}
.cliente-foto-card {
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
}
.cliente-foto-img {
  height: 170px;
}
:deep(.cliente-id-tooltip) {
  background: #22b8cf;
  color: #fff;
  border: 0;
  border-radius: 10px;
  padding: 2px 8px;
  font-weight: 700;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
}
:deep(.cliente-id-tooltip:before) {
  border-top-color: #22b8cf;
}
.map-debug-summary {
  position: absolute;
  top: 0;
  right: 0;
  left: auto;
  z-index: 600;
  padding: 4px 4px;
  border-radius: 5px;
  background: rgba(255, 255, 255, 0.5);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.14);
}
.map-debug-row {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  justify-content: flex-end;
}
:deep(.map-debug-summary .q-chip) {
  min-height: 22px;
  padding: 0 7px;
  font-size: 11px;
  font-weight: 700;
}
@media (max-width: 700px) {
  .visitas-acciones-card {
    width: 100vw;
    max-width: 100vw;
    min-height: 100vh;
    border-radius: 0;
  }

  .visitas-acciones-header {
    padding: 10px 12px 0;
  }

  .visitas-acciones-title .text-h6 {
    font-size: 16px;
    line-height: 1.15;
  }

  .visitas-acciones-body {
    padding: 12px;
  }

  .visitas-acciones-footer {
    padding: 0 12px 12px;
  }

  .cliente-foto-img {
    height: 112px;
  }

  .map-debug-summary {
    top: 8px;
    right: 12px;
    left: auto;
    max-width: calc(100% - 88px);
  }
}
</style>
