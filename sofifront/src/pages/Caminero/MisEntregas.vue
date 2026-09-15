<template>
  <!-- Media pantalla de mapa y media de pedidos. El alto se fija a mano
       porque el layout de la app arranca con min-height 0: sin esto los dos
       bloques nacen aplastados. -->
  <q-page class="pantalla">
    <div class="mitad-mapa">
      <l-map v-model="zoom" :zoom="zoom" :center="centro" @ready="mapaListo">
        <!-- Tiles de Google: en Oruro tienen las calles y los nombres que el
             caminero conoce. El key fuerza a rehacer la capa al cambiar de
             tipo, que si no se queda con la anterior. -->
        <l-tile-layer
          :key="tipoMapa" :url="urlMapa" :subdomains="['mt0', 'mt1', 'mt2', 'mt3']"
          :max-zoom="20" attribution="Google"
        />
        <!-- Una marca por puerta y no por pedido: el mismo cliente pide dos o
             tres veces el mismo dia y las marcas se tapaban entre ellas. El
             globito dice cuantos pedidos hay en ese punto, y al tocarlo se
             abre la lista de todos. -->
        <l-marker
          v-for="punto in marcadores" :key="punto.clave"
          :lat-lng="punto.latLng"
          @click="abrirPunto(punto)"
        >
          <l-icon>
            <div class="marca" :class="clasePunto(punto.entregas)">
              {{ punto.indice + 1 }}
              <span v-if="punto.entregas.length > 1" class="marca-cuantos">
                {{ punto.entregas.length }}
              </span>
            </div>
          </l-icon>
        </l-marker>
      </l-map>

      <!-- Va a la derecha para no taparse con el zoom de Leaflet. -->
      <div class="panel-alto row items-center no-wrap q-gutter-xs">
        <q-input
          v-model="fecha" type="date" dense outlined bg-color="white"
          class="campo-fecha" @update:model-value="cargar"
        />
        <q-btn round dense unelevated color="white" text-color="primary" icon="refresh"
               :loading="cargando" @click="cargar">
          <q-tooltip>Actualizar</q-tooltip>
        </q-btn>
        <q-btn round dense unelevated color="white" text-color="primary" icon="my_location"
               @click="encuadrar">
          <q-tooltip>Centrar mis clientes</q-tooltip>
        </q-btn>
        <q-btn round dense unelevated color="white" text-color="primary" icon="layers">
          <q-tooltip>Tipo de mapa</q-tooltip>
          <q-menu auto-close>
            <q-list dense style="min-width: 150px">
              <q-item
                v-for="tipo in tiposMapa" :key="tipo.valor"
                clickable :active="tipoMapa === tipo.valor" active-class="bg-blue-1"
                @click="cambiarMapa(tipo.valor)"
              >
                <q-item-section avatar style="min-width: 32px">
                  <q-icon :name="tipo.icono" size="18px"/>
                </q-item-section>
                <q-item-section>{{ tipo.label }}</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
        <q-btn round dense unelevated color="white" text-color="primary" icon="summarize"
               to="/caminero/reporte">
          <q-tooltip>Mi reporte del día</q-tooltip>
        </q-btn>
      </div>

      <!-- Lo que tiene que rendir al volver: es el dato por el que le
           preguntan en caja, asi que va fijo sobre el mapa. -->
      <div class="panel-bajo">
        <q-linear-progress
          size="16px" :value="resumen.porcentaje / 100"
          :color="resumen.pendientes ? 'orange-7' : 'positive'" track-color="blue-grey-2"
        >
          <div class="absolute-full flex flex-center">
            <span class="texto-barra">
              {{ resumen.cobradas }} de {{ resumen.comprobantes }} cobrados · {{ resumen.porcentaje }}%
            </span>
          </div>
        </q-linear-progress>
        <div class="row no-wrap caja">
          <div class="col caja-dato">
            <q-icon name="payments" color="green-8" size="15px"/>
            <span class="caja-monto text-green-9">{{ money(resumen.efectivo) }}</span>
          </div>
          <div class="col caja-dato">
            <q-icon name="qr_code_2" color="indigo-8" size="15px"/>
            <span class="caja-monto text-indigo-9">{{ money(resumen.qr) }}</span>
          </div>
          <div class="col caja-dato">
            <q-icon name="schedule" color="orange-9" size="15px"/>
            <span class="caja-monto text-orange-9">{{ money(resumen.por_cobrar) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="row items-center no-wrap q-px-xs barra-buscar">
      <q-input
        v-model.trim="buscar" dense outlined clearable class="col campo-buscar"
        bg-color="white" placeholder="Cliente o pedido"
      >
        <template v-slot:prepend><q-icon name="search" size="16px"/></template>
      </q-input>
      <q-chip dense square size="sm" class="q-ml-xs q-my-none" color="blue-grey-8" text-color="white">
        {{ filtradas.length }}
      </q-chip>
      <q-chip
        v-if="sinComprobante" dense square size="sm" class="q-ml-xs q-my-none"
        color="amber-3" text-color="amber-10" icon="warning"
      >
        {{ sinComprobante }}
        <q-tooltip>Pedidos de tu camión todavía sin comprobante en caja</q-tooltip>
      </q-chip>
    </div>

    <!-- Tabla y no tarjetas: entran el triple de pedidos por pantalla y el
         numero de cada fila es el mismo que el del mapa. -->
    <div class="mitad-lista">
      <div v-if="cargando" class="flex flex-center q-pa-lg">
        <q-spinner color="primary" size="42px"/>
      </div>

      <div v-else-if="!filtradas.length" class="text-center text-grey-6 q-pa-lg">
        <q-icon name="local_shipping" size="36px"/>
        <div class="q-mt-sm">No hay comprobantes de tu camión para esta fecha</div>
      </div>

      <table v-else class="tabla">
        <thead>
          <tr>
            <th class="col-num">#</th>
            <th>CLIENTE</th>
            <th class="col-monto">MONTO</th>
            <th class="col-acciones"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(entrega, indice) in filtradas" :key="entrega.factura_id"
            :class="claseFila(entrega)" @click="abrirPuntoDe(entrega)"
          >
            <td class="col-num">
              <div class="marca" :class="claseEstado(entrega)">{{ indice + 1 }}</div>
            </td>
            <td>
              <div class="linea-cliente ellipsis">{{ entrega.cliente || entrega.nombre || 'Sin cliente' }}</div>
              <div class="linea-pie ellipsis">
                <q-icon name="place" size="11px"/>
                {{ entrega.direccion || 'Sin dirección' }}
              </div>
            </td>
            <td class="col-monto">
              <div class="monto">{{ money(entrega.total) }}</div>
              <div class="linea-pie">
                <q-icon :name="entrega.cobrada ? 'check_circle' : 'schedule'" size="11px"/>
                {{ entrega.cobrada ? entrega.tipago : '#' + entrega.nro_pedido }}
              </div>
            </td>
            <td class="col-acciones">
              <q-btn dense flat round size="sm" icon="receipt_long" color="blue-grey-7"
                     @click.stop="verComprobante(entrega)">
                <q-tooltip>Ver comprobante</q-tooltip>
              </q-btn>
              <q-btn
                v-if="entrega.telefono" dense flat round size="sm" icon="call" color="green-8"
                :href="'tel:' + entrega.telefono" @click.stop
              />
              <q-btn
                v-if="Number(entrega.latitud)" dense flat round size="sm" icon="navigation" color="blue-8"
                :href="rutaMaps(entrega)" target="_blank" @click.stop
              />
              <q-btn
                v-if="!entrega.cobrada" dense unelevated round size="sm" icon="payments"
                color="positive" class="q-ml-xs" @click.stop="abrirPuntoDe(entrega)"
              >
                <q-tooltip>Cobrar</q-tooltip>
              </q-btn>
              <q-icon v-else name="task_alt" color="positive" size="20px" class="q-ml-xs"/>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Lo que sale al tocar una marca: todos los pedidos de esa puerta en una
         tabla, con el mismo formato que la pantalla de ruta para que el
         caminero no tenga que aprender otra pantalla. -->
    <q-dialog
      v-model="dialogoPunto" :maximized="esMovil" :full-width="!esMovil"
      transition-show="slide-up" transition-hide="slide-down"
    >
      <!-- A pantalla completa el encabezado y el pie quedan fijos y solo
           scrollea la lista de pedidos. -->
      <q-card :class="esMovil ? 'column no-wrap full-height' : ''">
        <q-card-section class="row items-center no-wrap q-gutter-sm q-pb-sm">
          <q-icon name="place" size="md" color="blue-grey-8"/>
          <div class="col">
            <div class="text-subtitle1 text-weight-medium ellipsis">
              {{ punto.cliente || punto.entregas.length + ' pedidos en este punto' }}
            </div>
            <div class="text-caption text-grey-7 ellipsis-2-lines">
              <span v-if="punto.cliente && punto.entregas.length > 1">
                {{ punto.entregas.length }} pedidos ·
              </span>
              {{ punto.direccion || 'Sin dirección' }}
            </div>
          </div>
          <!-- La encuesta va arriba: es del cliente de la puerta, no de cada
               nota. Solo baja a las filas si en el punto hay varios clientes. -->
          <q-btn
            v-if="punto.cliente && punto.entregas.length" dense no-caps
            icon="feedback" color="primary" label="Encuesta" class="q-mr-xs"
            @click="abrirEncuesta(punto.entregas[0])"
          />
          <q-btn
            v-if="punto.lat" type="a" target="_blank" no-caps dense
            :href="'https://www.google.com/maps/dir/?api=1&destination=' + punto.lat + ',' + punto.lng"
            icon="navigation" color="blue-8" label="Ir"
          />
        </q-card-section>

        <q-separator/>

        <q-card-section class="q-pa-none col scroll">
          <q-table
            dense flat :rows="punto.entregas" :columns="columnasPunto"
            row-key="factura_id" :rows-per-page-options="[0]" hide-pagination
            :grid="esMovil"
          >
            <!-- En el celular la fila no entra como tabla: cada pedido va en
                 una tarjeta de tres lineas, que es lo que se puede revisar de
                 un vistazo parado al lado del camion. -->
            <template #item="props">
              <div class="col-12 q-px-xs q-pb-xs">
                <q-card flat bordered class="q-pa-xs" :class="claseFila(props.row)">
                  <div class="row items-center no-wrap">
                    <div class="marca q-mr-xs" :class="claseEstado(props.row)">
                      {{ numero(props.row) }}
                    </div>
                    <div class="col" style="min-width: 0">
                      <div v-if="nombreFila(props.rowIndex)" class="text-weight-medium ellipsis">
                        {{ nombreFila(props.rowIndex) }}
                      </div>
                      <div class="text-caption text-grey-7 ellipsis">
                        Pedido #{{ props.row.nro_pedido }} ·
                        {{ props.row.tipo_comprobante }} #{{ props.row.factura_id }}
                      </div>
                      <!-- En la puerta el cliente revisa bulto por bulto: el
                           detalle se abre aca mismo, sin ir al comprobante. -->
                      <div
                        v-if="props.row.detalles && props.row.detalles.length"
                        class="text-caption text-primary linea-detalle"
                        @click.stop="verDetalle(props.row)"
                      >
                        <q-icon :name="detalleAbierto[props.row.factura_id] ? 'expand_less' : 'expand_more'" size="14px"/>
                        {{ props.row.detalles.length }}
                        {{ props.row.detalles.length === 1 ? 'producto' : 'productos' }}
                      </div>
                    </div>
                    <div class="text-subtitle2 text-weight-bolder q-mx-xs">
                      Bs {{ money(props.row.total) }}
                    </div>
                    <q-btn
                      dense flat round size="sm" icon="receipt_long" color="blue-grey-7"
                      @click="verComprobante(props.row)"
                    />
                    <q-btn
                      v-if="encuestaEnFila" dense flat round size="sm" icon="feedback"
                      color="primary" @click="abrirEncuesta(props.row)"
                    />
                    <q-btn
                      v-if="props.row.telefono" dense flat round size="sm" icon="call"
                      color="green-8" :href="'tel:' + props.row.telefono"
                    />
                  </div>

                  <table v-if="detalleAbierto[props.row.factura_id]" class="tabla-detalle">
                    <tr v-for="(item, i) in props.row.detalles" :key="i">
                      <td class="det-cant">{{ cantidadTexto(item) }}</td>
                      <td class="det-nombre">{{ item.nombre }}</td>
                      <td class="det-monto">{{ money(item.subtotal) }}</td>
                    </tr>
                  </table>

                  <div v-if="props.row.cobrada" class="text-caption text-green-9">
                    <q-icon name="task_alt" size="14px"/>
                    {{ props.row.tipago }} · cobrado Bs {{ money(cobrado(props.row)) }}
                    <span v-if="falto(props.row) > 0.009" class="text-red-9 text-weight-bold">
                      · faltó Bs {{ money(falto(props.row)) }}
                    </span>
                  </div>
                  <div v-if="props.row.retorno" class="text-caption text-deep-orange-9">
                    <q-icon name="assignment_return" size="14px"/> {{ props.row.observacion }}
                  </div>

                  <div v-else-if="props.row.entrega_estado" class="text-caption text-red-9 ellipsis">
                    <q-icon name="cancel" size="14px"/>
                    {{ props.row.entrega_estado }}
                    <span v-if="props.row.observacion">· {{ props.row.observacion }}</span>
                  </div>

                  <!-- El credito ya venia decidido de caja: al caminero solo se
                       le recuerda que ahi no cobra nada. -->
                  <div
                    v-else-if="esCredito(props.row)"
                    class="row items-center no-wrap q-mt-xs"
                  >
                    <div class="col text-caption text-orange-9 ellipsis">
                      <q-icon name="schedule" size="14px"/>
                      <b>A crédito</b> · se entrega sin cobrar
                    </div>
                    <q-btn
                      dense flat round size="sm" icon="cancel" color="negative"
                      @click="abrirNoEntrega(props.row)"
                    />
                    <q-btn
                      dense flat round size="sm" icon="assignment_return" color="deep-orange-8"
                      @click="abrirRetorno(props.row)"
                    />
                    <q-btn
                      dense unelevated round size="sm" icon="check" color="positive"
                      class="q-ml-xs" :loading="guardando === props.row.factura_id"
                      @click="cobrar(props.row)"
                    />
                  </div>

                  <template v-else-if="cobros[props.row.factura_id]">
                    <q-btn-toggle
                      :model-value="cobros[props.row.factura_id].forma"
                      spread no-caps unelevated dense size="sm" class="q-mt-xs"
                      toggle-color="primary" color="grey-3" text-color="grey-9"
                      :options="formasPago"
                      @update:model-value="forma => cambiarForma(props.row, forma)"
                    />

                    <div class="row items-center no-wrap q-gutter-xs q-mt-xs">
                      <q-input
                        v-if="cobros[props.row.factura_id].forma !== 'PAGO QR'"
                        v-model.number="cobros[props.row.factura_id].efectivo"
                        type="number" inputmode="decimal" outlined dense
                        class="col campo-monto"
                        :label="cobros[props.row.factura_id].forma === 'MIXTO' ? 'Efectivo' : 'Pagó'"
                        @update:model-value="ajustarQr(props.row)"
                      />
                      <q-input
                        v-if="cobros[props.row.factura_id].forma !== 'CONTADO'"
                        v-model.number="cobros[props.row.factura_id].qr"
                        type="number" inputmode="decimal" outlined dense
                        class="col campo-monto" label="QR"
                      />
                      <div
                        class="col text-caption text-weight-medium ellipsis"
                        :class="claseSaldo(props.row)"
                      >
                        {{ leyendaSaldo(props.row) }}
                      </div>
                      <q-btn
                        dense flat round size="sm" icon="cancel" color="negative"
                        @click="abrirNoEntrega(props.row)"
                      />
                      <q-btn
                        dense flat round size="sm" icon="assignment_return" color="deep-orange-8"
                        @click="abrirRetorno(props.row)"
                      />
                      <q-btn
                        dense unelevated round size="sm" icon="check" color="positive"
                        :loading="guardando === props.row.factura_id"
                        :disable="!cobroValido(props.row)" @click="cobrar(props.row)"
                      />
                    </div>
                  </template>
                </q-card>
              </div>
            </template>

            <template #body="props">
              <q-tr :props="props" :class="claseFila(props.row)">
                <q-td key="nro" :props="props">
                  <div class="marca" :class="claseEstado(props.row)">{{ numero(props.row) }}</div>
                </q-td>

                <q-td key="cliente" :props="props">
                  <div v-if="nombreFila(props.rowIndex)" class="text-weight-medium">
                    {{ nombreFila(props.rowIndex) }}
                  </div>
                  <div class="text-caption text-grey-7">
                    Pedido #{{ props.row.nro_pedido }} ·
                    {{ props.row.tipo_comprobante }} #{{ props.row.factura_id }}
                  </div>
                  <div
                    v-if="props.row.detalles && props.row.detalles.length"
                    class="text-caption text-primary linea-detalle"
                    @click.stop="verDetalle(props.row)"
                  >
                    <q-icon :name="detalleAbierto[props.row.factura_id] ? 'expand_less' : 'expand_more'" size="14px"/>
                    {{ props.row.detalles.length }}
                    {{ props.row.detalles.length === 1 ? 'producto' : 'productos' }}
                  </div>
                  <table v-if="detalleAbierto[props.row.factura_id]" class="tabla-detalle">
                    <tr v-for="(item, i) in props.row.detalles" :key="i">
                      <td class="det-cant">{{ cantidadTexto(item) }}</td>
                      <td class="det-nombre">{{ item.nombre }}</td>
                      <td class="det-monto">{{ money(item.subtotal) }}</td>
                    </tr>
                  </table>
                </q-td>

                <!-- El recojo se hace aca mismo: la nota dice 106.70 pero el
                     cliente entrega 106, asi que el monto se escribe y se
                     guarda lo que de verdad entro. -->
                <q-td key="cobro" :props="props">
                  <div class="text-weight-bolder">Bs {{ money(props.row.total) }}</div>

                  <div v-if="props.row.cobrada" class="text-caption text-green-9">
                    {{ props.row.tipago }} · cobrado Bs {{ money(cobrado(props.row)) }}
                    <span v-if="falto(props.row) > 0.009" class="text-red-9 text-weight-bold">
                      · faltó Bs {{ money(falto(props.row)) }}
                    </span>
                  </div>
                  <div v-if="props.row.retorno" class="text-caption text-deep-orange-9">
                    <q-icon name="assignment_return" size="14px"/> {{ props.row.observacion }}
                  </div>

                  <div v-else-if="props.row.entrega_estado" class="text-caption text-red-9">
                    {{ props.row.entrega_estado }}
                  </div>

                  <div v-else-if="esCredito(props.row)" class="text-caption text-orange-9">
                    <q-icon name="schedule" size="14px"/>
                    <b>A crédito.</b> Se entrega sin cobrar.
                  </div>

                  <template v-else-if="cobros[props.row.factura_id]">
                    <q-btn-toggle
                      :model-value="cobros[props.row.factura_id].forma"
                      spread no-caps unelevated dense size="sm" class="q-mt-xs"
                      toggle-color="primary" color="grey-3" text-color="grey-9"
                      :options="formasPago"
                      @update:model-value="forma => cambiarForma(props.row, forma)"
                    />

                    <div
                      v-if="cobros[props.row.factura_id].forma !== 'CRÉDITO'"
                      class="row q-col-gutter-xs q-mt-xs"
                    >
                      <div v-if="cobros[props.row.factura_id].forma !== 'PAGO QR'" class="col">
                        <q-input
                          v-model.number="cobros[props.row.factura_id].efectivo"
                          type="number" inputmode="decimal" dense outlined
                          :label="cobros[props.row.factura_id].forma === 'MIXTO' ? 'Efectivo' : 'Pagó'"
                          @update:model-value="ajustarQr(props.row)"
                        />
                      </div>
                      <div v-if="cobros[props.row.factura_id].forma !== 'CONTADO'" class="col">
                        <q-input
                          v-model.number="cobros[props.row.factura_id].qr"
                          type="number" inputmode="decimal" dense outlined label="QR"
                        />
                      </div>
                    </div>

                    <div class="text-caption q-mt-xs" :class="claseSaldo(props.row)">
                      {{ leyendaSaldo(props.row) }}
                    </div>
                  </template>
                </q-td>

                <q-td key="opcion" :props="props" class="text-no-wrap">
                  <q-btn
                    dense flat round size="sm" icon="receipt_long" color="blue-grey-7"
                    @click="verComprobante(props.row)"
                  >
                    <q-tooltip>Ver comprobante</q-tooltip>
                  </q-btn>
                  <q-btn
                    v-if="encuestaEnFila" dense flat round size="sm" icon="feedback"
                    color="primary" @click="abrirEncuesta(props.row)"
                  >
                    <q-tooltip>Encuesta</q-tooltip>
                  </q-btn>
                  <q-btn
                    v-if="props.row.telefono" dense flat round size="sm" icon="call"
                    color="green-8" :href="'tel:' + props.row.telefono"
                  />
                  <template v-if="!props.row.cobrada">
                    <q-btn
                      dense flat round size="sm" icon="cancel" color="negative"
                      @click="abrirNoEntrega(props.row)"
                    >
                      <q-tooltip>No entregado</q-tooltip>
                    </q-btn>
                    <q-btn
                      dense flat round size="sm" icon="assignment_return" color="deep-orange-8"
                      @click="abrirRetorno(props.row)"
                    >
                      <q-tooltip>Retorno parcial</q-tooltip>
                    </q-btn>
                    <q-btn
                      dense unelevated round size="sm" icon="check" color="positive"
                      class="q-ml-xs" :loading="guardando === props.row.factura_id"
                      :disable="!cobroValido(props.row)" @click="cobrar(props.row)"
                    >
                      <q-tooltip>
                        {{ esCredito(props.row) ? 'Entregar a crédito' : 'Registrar el cobro' }}
                      </q-tooltip>
                    </q-btn>
                  </template>
                  <q-icon v-else name="task_alt" color="positive" size="20px" class="q-ml-xs"/>
                </q-td>
              </q-tr>
            </template>
          </q-table>
        </q-card-section>

        <q-separator/>

        <q-card-actions class="q-px-sm q-py-xs">
          <div class="text-caption text-grey-7">
            Punto: <b>Bs {{ money(punto.total) }}</b>
            <span v-if="sumaACobrar > 0" class="text-green-9">
              · cobrás Bs {{ money(sumaACobrar) }}
            </span>
          </div>
          <q-space/>
          <q-btn flat dense no-caps color="grey-8" label="Cerrar" v-close-popup/>
          <!-- En una puerta con varias notas se cuenta la plata una sola vez:
               este boton cierra todas las pendientes de un toque. -->
          <q-btn
            v-if="pendientesPunto.length > 1" unelevated no-caps color="positive"
            icon="done_all" :label="'Registrar los ' + pendientesPunto.length"
            class="q-ml-xs" :loading="guardandoTodo" :disable="!todoValido"
            @click="cobrarTodo"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogo" @hide="limpiarCobro">
      <q-card style="min-width: 300px">
        <q-card-section class="q-pb-none">
          <div class="text-subtitle1 text-weight-bold text-red-9">Marcar como no entregado</div>
          <div class="text-caption text-grey-7">
            {{ cobro.cliente }} · Pedido #{{ cobro.nro_pedido }} · Bs {{ money(cobro.total) }}
          </div>
        </q-card-section>

        <q-card-section class="q-pt-sm">
          <q-input
            v-model.trim="motivo" dense outlined autogrow autofocus maxlength="90"
            label="¿Por qué no se entregó?"
          />
        </q-card-section>

        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat no-caps color="grey-8" label="Cerrar" v-close-popup/>
          <q-btn
            unelevated no-caps color="negative" label="No entregado" icon="cancel"
            :loading="guardando === cobro.factura_id" :disable="!motivo"
            @click="noEntregar"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Retorno parcial: el comprobante no se toca. Se anota cuanto se quedo
         el cliente de cada producto y se cobra eso; caja edita despues. -->
    <q-dialog v-model="dialogoRetorno" :maximized="esMovil">
      <q-card :class="esMovil ? 'column no-wrap full-height' : ''" :style="esMovil ? '' : 'width: 560px; max-width: 96vw'">
        <q-card-section class="bg-deep-orange-7 text-white q-py-sm">
          <div class="text-subtitle1 text-weight-bold">Retorno parcial</div>
          <div class="text-caption">
            {{ retornoEntrega.cliente || retornoEntrega.nombre }} · Pedido #{{ retornoEntrega.nro_pedido }} ·
            Bs {{ money(retornoEntrega.total) }}
          </div>
        </q-card-section>

        <q-card-section class="q-pa-none" :class="esMovil ? 'col scroll' : ''">
          <table class="tabla-retorno">
            <thead>
              <tr>
                <th class="text-left">Producto</th>
                <th class="text-right">Salió</th>
                <th class="text-right">Se quedó</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="fila in retornoItems" :key="fila.cod_prod" :class="fila.entregado < fila.original ? 'bg-deep-orange-1' : ''">
                <td>
                  <div class="text-weight-medium">{{ fila.nombre }}</div>
                  <div class="text-caption text-grey-7">Bs {{ money(fila.precio) }} / {{ fila.unidadTexto }}</div>
                </td>
                <td class="text-right text-no-wrap">{{ cantidad(fila.original) }} {{ fila.unidadTexto }}</td>
                <td class="text-right" style="width: 110px">
                  <q-input
                    v-model.number="fila.entregado" type="number" inputmode="decimal" dense outlined
                    :min="0" :max="fila.original" step="0.001"
                    :error="!(fila.entregado >= 0) || fila.entregado > fila.original" hide-bottom-space
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </q-card-section>

        <q-card-section class="q-py-sm">
          <div class="row text-body2">
            <div class="col">Nota original</div>
            <div class="col-auto">Bs {{ money(retornoEntrega.total) }}</div>
          </div>
          <div class="row text-body2 text-weight-bold">
            <div class="col">Se queda el cliente</div>
            <div class="col-auto">Bs {{ money(retornoTotal) }}</div>
          </div>

          <template v-if="retornoCobro.forma !== 'CRÉDITO'">
            <q-btn-toggle
              v-model="retornoCobro.forma" spread no-caps unelevated dense size="sm" class="q-mt-sm"
              toggle-color="primary" color="grey-3" text-color="grey-9" :options="formasPago"
            />
            <div class="row q-col-gutter-xs q-mt-xs">
              <div v-if="retornoCobro.forma !== 'PAGO QR'" class="col">
                <q-input
                  v-model.number="retornoCobro.efectivo" type="number" inputmode="decimal" dense outlined
                  :label="retornoCobro.forma === 'MIXTO' ? 'Efectivo' : 'Pagó'"
                />
              </div>
              <div v-if="retornoCobro.forma !== 'CONTADO'" class="col">
                <q-input
                  v-model.number="retornoCobro.qr" type="number" inputmode="decimal" dense outlined label="QR"
                />
              </div>
            </div>
          </template>
          <div v-else class="text-caption text-orange-9 q-mt-sm">
            <q-icon name="schedule"/> A crédito: se entrega sin cobrar
          </div>

          <q-input
            v-model.trim="retornoMotivo" dense outlined class="q-mt-sm" maxlength="90"
            label="¿Por qué devolvió? (opcional)"
          />
        </q-card-section>

        <q-separator/>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat no-caps color="grey-8" label="Cerrar" v-close-popup/>
          <q-btn
            unelevated no-caps color="deep-orange-7" icon="assignment_return" label="Registrar retorno"
            :loading="guardando === retornoEntrega.factura_id" :disable="!retornoValido"
            @click="registrarRetorno"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogoDetalle">
      <q-card style="min-width: 320px">
        <q-card-section class="q-pb-xs">
          <div class="text-subtitle1 text-weight-bold">{{ verEntrega.cliente }}</div>
          <div class="text-caption text-grey-7">
            {{ verEntrega.tipo_comprobante }} #{{ verEntrega.factura_id }} ·
            Pedido #{{ verEntrega.nro_pedido }} · NIT {{ verEntrega.nit || '—' }}
          </div>
        </q-card-section>
        <q-separator/>
        <div v-if="detalles[verEntrega.factura_id] === 'cargando'" class="q-pa-md text-center">
          <q-spinner color="primary" size="28px"/>
        </div>
        <q-list v-else dense separator style="max-height: 50vh; overflow-y: auto">
          <q-item v-for="item in (detalles[verEntrega.factura_id] || [])" :key="item.id" class="q-px-sm">
            <q-item-section>
              <q-item-label lines="2">{{ item.nombre }}</q-item-label>
              <q-item-label caption>{{ item.cod_prod }}</q-item-label>
            </q-item-section>
            <q-item-section side class="text-right">
              <q-item-label>{{ cantidad(item.cantidad) }} × Bs {{ money(item.precio) }}</q-item-label>
              <q-item-label caption class="text-weight-bold">Bs {{ money(item.subtotal) }}</q-item-label>
            </q-item-section>
          </q-item>
        </q-list>
        <q-separator/>
        <q-card-actions class="q-pa-sm">
          <div class="text-h6 text-weight-bolder text-blue-grey-10">Bs {{ money(verEntrega.total) }}</div>
          <q-space/>
          <q-btn flat no-caps color="grey-8" label="Cerrar" v-close-popup/>
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- El cliente escanea y responde desde su celular; el QR lo arma un
         servicio, asi no hay que sumar una libreria al bundle. -->
    <q-dialog v-model="dialogQR" transition-show="scale" transition-hide="scale">
      <q-card style="min-width: 300px; max-width: 360px">
        <q-card-section class="row items-center no-wrap q-pb-none">
          <q-icon name="reviews" size="26px" class="q-mr-sm" color="primary"/>
          <div class="col">
            <div class="text-subtitle1 text-weight-bold">Encuesta de satisfacción</div>
            <div class="text-caption text-grey-7 ellipsis">{{ qrCliente || 'Cliente' }}</div>
          </div>
          <q-btn round flat dense icon="close" v-close-popup/>
        </q-card-section>

        <q-card-section class="text-center q-pb-sm">
          <q-img :src="qrSrc" ratio="1" spinner-color="primary" style="width: 220px"/>
          <q-input
            v-model="qrLink" dense readonly outlined class="q-mt-sm"
            @focus="$event.target.select()"
          >
            <template v-slot:append>
              <q-btn
                round dense flat icon="content_copy" color="primary"
                :disable="!qrLink" @click="copiarEncuesta"
              >
                <q-tooltip>Copiar enlace</q-tooltip>
              </q-btn>
            </template>
          </q-input>
        </q-card-section>

        <q-card-actions class="column q-px-md q-pb-md q-gutter-y-sm">
          <q-btn
            v-if="qrWhatsapp" :href="qrWhatsapp" target="_blank" class="full-width"
            color="green-7" icon="chat" label="Enviar por WhatsApp" no-caps unelevated
          />
          <q-btn
            :href="qrLink" target="_blank" class="full-width" color="primary"
            icon="open_in_new" label="Abrir encuesta" no-caps outline
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { markRaw } from 'vue'
import { date } from 'quasar'
import { LMap, LIcon, LTileLayer, LMarker } from '@vue-leaflet/vue-leaflet'
import 'leaflet/dist/leaflet.css'

export default {
  name: 'MisEntregas',
  components: { LMap, LIcon, LTileLayer, LMarker },
  data () {
    return {
      fecha: this.$route.query.fecha || date.formatDate(new Date(), 'YYYY-MM-DD'),
      buscar: '',
      cargando: false,
      // El factura_id que se esta grabando, o null.
      guardando: null,
      guardandoTodo: false,
      placa: '',
      sinComprobante: 0,
      entregas: [],
      resumen: { comprobantes: 0, cobradas: 0, pendientes: 0, porcentaje: 0, efectivo: 0, qr: 0, por_cobrar: 0 },
      detalles: {},
      dialogo: false,
      dialogoDetalle: false,
      // El punto abierto se guarda por id y no por copia: asi al recargar
      // despues de cobrar la lista del dialogo se actualiza sola.
      dialogoPunto: false,
      puntoIds: [],
      // Lo que se esta por cobrar en cada fila, por factura_id.
      cobros: {},
      // Qué notas tienen el detalle de productos desplegado, por factura_id.
      detalleAbierto: {},
      // Sin crédito: no lo elige el caminero, ya viene decidido en la venta.
      formasPago: [
        { label: 'Efectivo', value: 'CONTADO' },
        { label: 'QR', value: 'PAGO QR' },
        { label: 'Mixto', value: 'MIXTO' }
      ],
      columnasPunto: [
        { name: 'nro', label: '#', field: 'factura_id', align: 'center' },
        { name: 'cliente', label: 'PEDIDO', field: 'cliente', align: 'left' },
        { name: 'cobro', label: 'COBRO', field: 'total', align: 'left' },
        { name: 'opcion', label: '', field: 'factura_id', align: 'right' }
      ],
      // Encuesta de satisfaccion, igual que en la pantalla de ruta.
      dialogQR: false,
      qrLink: '',
      qrSrc: '',
      qrCliente: '',
      qrWhatsapp: '',
      verEntrega: {},
      // Retorno parcial: la nota, lo que se quedo el cliente de cada producto
      // y como pago eso.
      dialogoRetorno: false,
      retornoEntrega: {},
      retornoItems: [],
      retornoCobro: { forma: 'CONTADO', efectivo: null, qr: null },
      retornoMotivo: '',
      // La entrega del dialogo de "no entregado" y su motivo.
      cobro: {},
      motivo: '',
      zoom: 13,
      // lyrs de Google: r calles, s satelite, y hibrido, p relieve.
      tipoMapa: 'r',
      tiposMapa: [
        { label: 'Mapa', valor: 'r', icono: 'map' },
        { label: 'Satélite', valor: 's', icono: 'satellite' },
        { label: 'Híbrido', valor: 'y', icono: 'layers' },
        { label: 'Relieve', valor: 'p', icono: 'terrain' }
      ],
      centro: [-17.9833, -67.15],
      posicion: null,
      mapa: null
    }
  },
  created () {
    // El tipo de mapa es del caminero, no del dia: se recuerda entre visitas.
    try {
      const guardado = localStorage.getItem('caminero-tipo-mapa')
      if (guardado && this.tiposMapa.some(tipo => tipo.valor === guardado)) {
        this.tipoMapa = guardado
      }
    } catch (e) {}
    this.cargar()
    this.ubicar()
  },
  computed: {
    urlMapa () {
      return 'https://{s}.google.com/vt/lyrs=' + this.tipoMapa + '&x={x}&y={y}&z={z}'
    },
    filtradas () {
      const texto = this.buscar.toLowerCase()
      const visibles = !texto
        ? this.entregas
        : this.entregas.filter(entrega =>
          String(entrega.cliente || '').toLowerCase().includes(texto) ||
          String(entrega.nro_pedido).includes(texto)
        )

      // Lo que ya se cerró —cobrado o marcado como no entregado— se hunde al
      // final: el caminero trabaja de arriba hacia abajo y lo pendiente le
      // queda siempre a mano, sin buscarlo entre lo que ya despachó. sort es
      // estable, asi que dentro de cada grupo se respeta el orden que vino.
      return visibles.slice().sort(
        (a, b) => Number(this.cerrada(a)) - Number(this.cerrada(b))
      )
    },
    /**
     * Las marcas del mapa: una por puerta, no una por pedido.
     *
     * El mismo cliente pide dos y tres veces en el dia, y la tienda de al lado
     * esta cargada en la misma esquina: todo eso llegaba con la misma
     * coordenada y la ultima marca tapaba a las demas. Ahora esas entregas
     * comparten una sola marca y se abren juntas al tocarla.
     *
     * Se agrupa por metros en el terreno y no por pixeles en pantalla para que
     * la marca sea siempre la misma aunque el caminero acerque o aleje: si la
     * agrupacion cambiara con el zoom, Leaflet tendria que rehacer las marcas
     * a cada rato.
     */
    marcadores () {
      const grupos = []

      this.filtradas.forEach((entrega, indice) => {
        const lat = Number(entrega.latitud)
        const lng = Number(entrega.longitud)
        // Sin coordenada no hay nada que poner en el mapa.
        if (!lat || !lng) return

        // Quince metros: la misma puerta aunque el GPS del vendedor la haya
        // marcado dos veces desde la vereda de enfrente.
        const junto = grupos.find(grupo => this.metros(grupo, { lat, lng }) < 15)

        if (junto) junto.entregas.push(entrega)
        else grupos.push({ lat, lng, indice, entregas: [entrega] })
      })

      return grupos.map(grupo => ({
        // La clave no depende del zoom, asi que la marca no se rehace sola.
        clave: grupo.entregas.map(entrega => entrega.factura_id).join('-'),
        latLng: [grupo.lat, grupo.lng],
        indice: grupo.indice,
        entregas: grupo.entregas
      }))
    },
    /** Las entregas del punto abierto, sacadas siempre de la lista viva. */
    punto () {
      const entregas = this.filtradas.filter(
        entrega => this.puntoIds.includes(entrega.factura_id)
      )
      const primera = entregas[0] || {}
      // Casi siempre la puerta es de un solo cliente que pidio varias veces:
      // ahi el nombre va una vez en el encabezado y no en cada fila.
      const nombres = [...new Set(entregas.map(this.clienteDe))]

      return {
        entregas,
        cliente: nombres.length === 1 ? nombres[0] : '',
        direccion: primera.direccion || '',
        lat: Number(primera.latitud) || 0,
        lng: Number(primera.longitud) || 0,
        total: entregas.reduce((suma, entrega) => suma + Number(entrega.total || 0), 0),
        porCobrar: entregas.filter(entrega => !entrega.cobrada).length
      }
    },
    // El caminero trabaja desde el celular: ahi el punto se abre a pantalla
    // completa y sus pedidos van como tarjetas en vez de filas.
    esMovil () {
      return this.$q.screen.lt.md
    },
    /** Con varios clientes en la esquina, la encuesta vuelve a cada fila. */
    encuestaEnFila () {
      return !this.punto.cliente
    },
    /** Las notas del punto que todavia no se cerraron. */
    pendientesPunto () {
      return this.punto.entregas.filter(
        entrega => !entrega.cobrada && !entrega.entrega_estado
      )
    },
    /** Lo que va a entrar si se registra el punto tal como esta escrito. */
    sumaACobrar () {
      return this.pendientesPunto.reduce(
        (suma, entrega) => suma + this.pagando(entrega), 0
      )
    },
    /** Lo que vale lo que el cliente se quedo. */
    retornoTotal () {
      const suma = this.retornoItems.reduce(
        (total, fila) => total + (Number(fila.entregado) || 0) * fila.precio, 0
      )
      return Math.round(suma * 100) / 100
    },
    /** Algo se devolvio, algo se quedo, nada de mas y el cobro cuadra. */
    retornoValido () {
      const filas = this.retornoItems
      if (!filas.length) return false
      if (filas.some(fila => !(fila.entregado >= 0) || fila.entregado - fila.original > 0.001)) return false
      if (!filas.some(fila => fila.original - fila.entregado > 0.001)) return false
      if (!filas.some(fila => fila.entregado > 0)) return false

      const cobro = this.retornoCobro
      if (cobro.forma === 'CRÉDITO') return true
      const pagado = Number(cobro.efectivo || 0) + Number(cobro.qr || 0)
      if (pagado <= 0 || pagado - this.retornoTotal > 0.01) return false
      if (cobro.forma === 'MIXTO' && (!(cobro.efectivo > 0) || !(cobro.qr > 0))) return false
      return true
    },
    todoValido () {
      return this.pendientesPunto.length > 0 &&
        this.pendientesPunto.every(entrega => this.cobroValido(entrega))
    },
    encuestaBase () {
      // La encuesta la renderiza el backend, no el SPA: se deriva del API base
      // (http://localhost:8000/api/ -> http://localhost:8000), igual que en Ruta.
      return String(process.env.API || '').replace(/\/+$/, '').replace(/\/api$/, '')
    }
  },
  methods: {
    money (valor) {
      return Number(valor || 0).toFixed(2)
    },
    cantidad (valor) {
      return Number(valor || 0).toLocaleString('es-BO', { maximumFractionDigits: 3 })
    },
    /** Abre o cierra el detalle de productos de una nota. */
    verDetalle (entrega) {
      const id = entrega.factura_id
      // Reasignar el objeto entero y no una clave suelta: así la tarjeta del
      // celular, que Quasar rearma al desplegar, se entera del cambio.
      this.detalleAbierto = {
        ...this.detalleAbierto,
        [id]: !this.detalleAbierto[id]
      }
    },
    /**
     * La cantidad como la cuenta el caminero en la puerta.
     *
     * En lo que va por peso la nota lleva dos numeros distintos: las piezas
     * que se cargan (2 barras de jamon) y los kilos que se cobran (8.8). El
     * caminero cuenta las piezas y el cliente reclama por los kilos, asi que
     * cuando no coinciden van los dos.
     */
    cantidadTexto (item) {
      const peso = Number(item.peso) || 0
      const cant = Number(item.cantidad) || 0

      if (peso > 0 && Math.abs(peso - cant) > 0.001) {
        return this.cantidad(cant) + ' × ' + this.cantidad(peso) + ' kg'
      }
      if (peso > 0) return this.cantidad(peso) + ' kg'

      return this.cantidad(cant) + (item.unidad ? ' ' + item.unidad : '')
    },
    /** Una nota cerrada: ya se cobró o quedó como no entregada. */
    cerrada (entrega) {
      return !!(entrega.cobrada || entrega.entrega_estado)
    },
    claseFila (entrega) {
      if (entrega.cobrada) return 'fila-cobrada'
      return entrega.entrega_estado ? 'fila-rechazada' : ''
    },
    claseEstado (entrega) {
      if (entrega.cobrada) return 'marca-verde'
      return entrega.entrega_estado ? 'marca-roja' : 'marca-naranja'
    },
    // La marca de un punto con varios pedidos: verde solo cuando ya no queda
    // nada por cobrar ahi, para que el caminero no se vaya de la puerta antes.
    clasePunto (entregas) {
      if (entregas.every(entrega => entrega.cobrada)) return 'marca-verde'
      if (entregas.some(entrega => !entrega.cobrada && !entrega.entrega_estado)) {
        return 'marca-naranja'
      }
      return 'marca-roja'
    },
    clienteDe (entrega) {
      return entrega.cliente || entrega.nombre || 'Sin cliente'
    },
    /**
     * Si la venta salio a credito de caja.
     *
     * Ahi no hay nada que cobrar en la puerta: se entrega y el cliente paga
     * despues, asi que al caminero solo se le avisa.
     */
    esCredito (entrega) {
      const forma = String(entrega.tipo_pago || '').toUpperCase()

      return forma === 'CRÉDITO' || forma === 'CREDITO'
    },
    /**
     * El nombre que va en una fila del punto, o vacio si no hace falta.
     *
     * Si la puerta es de un solo cliente el nombre ya esta arriba; si hay
     * varios, se escribe una vez y las filas que siguen del mismo quedan
     * limpias, que era lo que se leia repetido.
     */
    nombreFila (indice) {
      if (this.punto.cliente) return ''

      const nombre = this.clienteDe(this.punto.entregas[indice])
      if (indice === 0) return nombre

      return this.clienteDe(this.punto.entregas[indice - 1]) === nombre ? '' : nombre
    },
    /** El numero que le toca en la lista de abajo, que es el que se ve. */
    numero (entrega) {
      return this.filtradas.findIndex(
        fila => fila.factura_id === entrega.factura_id
      ) + 1
    },
    abrirPunto (punto) {
      this.puntoIds = punto.entregas.map(entrega => entrega.factura_id)
      // Cada fila arranca en efectivo por el total de la nota, que es lo que
      // pasa casi siempre; el caminero solo corrige cuando le dan de menos.
      this.cobros = {}
      punto.entregas.forEach(entrega => {
        if (entrega.cobrada || entrega.entrega_estado) return
        // El monto arranca vacio a proposito: primero se cuenta la plata que
        // el cliente pone en la mano y recien ahi se escribe. Debajo del campo
        // queda el recordatorio de cuanto tendria que entrar.
        this.cobros[entrega.factura_id] = this.esCredito(entrega)
          ? { forma: 'CRÉDITO', efectivo: null, qr: null }
          : { forma: 'CONTADO', efectivo: null, qr: null }
      })
      this.dialogoPunto = true
    },
    // Desde la lista se abre el mismo punto que desde el mapa: si el cliente
    // tiene otro pedido en la misma puerta aparecen los dos juntos. Las
    // entregas sin coordenada no estan en el mapa y se abren solas.
    abrirPuntoDe (entrega) {
      const punto = this.marcadores.find(marca => marca.entregas.some(
        fila => fila.factura_id === entrega.factura_id
      ))

      this.abrirPunto(punto || { entregas: [entrega] })
    },
    cambiarMapa (valor) {
      this.tipoMapa = valor
      try { localStorage.setItem('caminero-tipo-mapa', valor) } catch (e) {}
      // La capa se rehace y el mapa pierde el encuadre: se vuelve a poner.
      this.$nextTick(this.encuadrar)
    },
    rutaMaps (entrega) {
      return 'https://www.google.com/maps/dir/?api=1&destination=' +
        entrega.latitud + ',' + entrega.longitud
    },
    /**
     * La encuesta de satisfaccion del cliente de esa entrega.
     *
     * Es la misma que usa la pantalla de ruta: la pagina la arma el backend,
     * asi que el cliente puede recargarla sin perder lo que ya respondio.
     */
    abrirEncuesta (entrega) {
      if (!entrega.cliente_id) {
        this.$q.notify({
          type: 'warning', position: 'top',
          message: 'Esta venta no tiene un cliente registrado para encuestar'
        })
        return
      }

      const userId = this.$store.getters['login/user']?.CodAut
      if (!userId) {
        this.$q.notify({
          type: 'negative', position: 'top',
          message: 'No se pudo identificar al caminero'
        })
        return
      }

      this.qrLink = this.encuestaBase + '/encuesta/' +
        encodeURIComponent(entrega.cliente_id) + '/' + encodeURIComponent(userId)
      this.qrCliente = entrega.cliente || entrega.nombre || ''
      this.qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=8&data=' +
        encodeURIComponent(this.qrLink)
      this.qrWhatsapp = this.linkWhatsapp(entrega.telefono, this.qrLink)
      this.dialogQR = true
    },
    // Bolivia: los numeros de ocho digitos van con el 591 adelante.
    linkWhatsapp (telefono, link) {
      const numero = String(telefono || '').replace(/\D/g, '')
      if (numero.length < 7) return ''

      const completo = numero.length === 8 ? '591' + numero : numero
      const texto = 'Hola, gracias por tu compra en Distribuidora Sofía 🐔\n' +
        '¿Nos ayudas con una encuesta rápida? ' + link

      return 'https://wa.me/' + completo + '?text=' + encodeURIComponent(texto)
    },
    copiarEncuesta () {
      if (!this.qrLink) return

      navigator.clipboard.writeText(this.qrLink)
        .then(() => this.$q.notify({ type: 'positive', position: 'top', message: 'Link copiado' }))
        .catch(() => this.$q.notify({ type: 'negative', position: 'top', message: 'No se pudo copiar' }))
    },
    /** Distancia aproximada en metros entre dos coordenadas cercanas. */
    metros (a, b) {
      const grado = 111320
      const x = (a.lng - b.lng) * grado * Math.cos((a.lat * Math.PI) / 180)
      const y = (a.lat - b.lat) * grado

      return Math.hypot(x, y)
    },
    mapaListo (mapa) {
      this.mapa = markRaw(mapa)
      // El mapa se dibuja antes de que el navegador reparta las dos mitades,
      // asi que sin esto queda calculado a un alto que ya no es el suyo.
      setTimeout(() => {
        mapa.invalidateSize()
        this.encuadrar()
      }, 200)
    },
    // Todos los clientes del dia entran en pantalla: el caminero no tiene que
    // buscar sus puntos moviendo el mapa.
    encuadrar () {
      const puntos = this.filtradas
        .filter(entrega => Number(entrega.latitud) && Number(entrega.longitud))
        .map(entrega => [Number(entrega.latitud), Number(entrega.longitud)])
      if (!this.mapa || !puntos.length) return
      this.mapa.fitBounds(puntos, { padding: [40, 40], maxZoom: 16 })
    },
    ubicar () {
      if (!navigator.geolocation) return
      navigator.geolocation.getCurrentPosition(
        posicion => { this.posicion = posicion.coords },
        () => { this.posicion = null },
        { enableHighAccuracy: true, timeout: 8000 }
      )
    },
    cargar () {
      this.cargando = true
      this.$api.get('caminero/entregas', { params: { fecha: this.fecha } })
        .then(res => {
          this.placa = res.data.placa
          this.entregas = res.data.entregas
          this.resumen = res.data.resumen
          this.sinComprobante = res.data.sin_comprobante
          this.$nextTick(this.encuadrar)
        })
        .catch(err => {
          this.$q.notify({
            type: 'negative',
            position: 'top',
            message: err.response?.data?.message || 'No se pudieron cargar tus entregas'
          })
        })
        .finally(() => { this.cargando = false })
    },
    // El detalle se pide una sola vez por comprobante y queda cacheado: en la
    // calle la señal es mala y no conviene repetir la consulta.
    verComprobante (entrega) {
      this.verEntrega = entrega
      this.dialogoDetalle = true
      if (this.detalles[entrega.factura_id]) return
      this.detalles = { ...this.detalles, [entrega.factura_id]: 'cargando' }
      this.$api.get('facturacion/' + entrega.factura_id)
        .then(res => {
          this.detalles = { ...this.detalles, [entrega.factura_id]: res.data.detalles || [] }
        })
        .catch(() => {
          this.detalles = { ...this.detalles, [entrega.factura_id]: [] }
        })
    },
    /** Lo que de verdad entro por una entrega ya cobrada. */
    cobrado (entrega) {
      return Number(entrega.monto_efectivo || 0) + Number(entrega.monto_qr || 0)
    },
    /** Lo que quedo debiendo: la nota menos lo que el cliente entrego. */
    falto (entrega) {
      return Math.max(Number(entrega.total || 0) - this.cobrado(entrega), 0)
    },
    /** Lo que se lleva escrito en la fila que se esta cobrando. */
    pagando (entrega) {
      const cobro = this.cobros[entrega.factura_id]
      if (!cobro) return 0

      return Number(cobro.efectivo || 0) + Number(cobro.qr || 0)
    },
    // Al cambiar de via el monto ya escrito se muda: el caminero cuenta la
    // plata una vez y despues solo dice por donde entro.
    cambiarForma (entrega, forma) {
      const cobro = this.cobros[entrega.factura_id]
      // Lo ya escrito se muda a la via nueva; si todavia no escribio nada, el
      // campo sigue vacio con su recordatorio.
      const monto = this.pagando(entrega) || null

      cobro.forma = forma

      if (forma === 'PAGO QR') {
        cobro.qr = monto
        cobro.efectivo = null
      } else {
        cobro.efectivo = monto
        cobro.qr = null
      }
    },
    // En el mixto el QR se completa solo con lo que falta de la nota, que es
    // el caso normal; si el cliente ademas paga de menos se corrige a mano.
    ajustarQr (entrega) {
      const cobro = this.cobros[entrega.factura_id]
      if (cobro.forma !== 'MIXTO') return

      // Mientras el efectivo este vacio no se inventa nada en el QR.
      if (!(Number(cobro.efectivo) > 0)) {
        cobro.qr = null
        return
      }

      const resto = Number(entrega.total || 0) - Number(cobro.efectivo)
      cobro.qr = Math.round(Math.max(resto, 0) * 100) / 100
    },
    cobroValido (entrega) {
      const cobro = this.cobros[entrega.factura_id]
      if (!cobro) return false
      if (cobro.forma === 'CRÉDITO') return true

      const pagado = this.pagando(entrega)
      if (pagado <= 0) return false
      // De menos se acepta y queda anotado; de mas siempre es un error.
      if (pagado - Number(entrega.total) > 0.01) return false
      if (cobro.forma === 'MIXTO' && (!(cobro.efectivo > 0) || !(cobro.qr > 0))) return false

      return true
    },
    leyendaSaldo (entrega) {
      const cobro = this.cobros[entrega.factura_id]
      if (cobro.forma === 'CRÉDITO') return 'Queda debiendo Bs ' + this.money(entrega.total)

      const pagado = this.pagando(entrega)
      // Sin nada escrito todavia, lo util es recordarle cuanto tiene que pedir.
      if (pagado <= 0) return 'Tendrías que cobrar Bs ' + this.money(entrega.total)

      const diferencia = Math.round((Number(entrega.total) - pagado) * 100) / 100

      if (diferencia > 0.009) return 'Falta Bs ' + this.money(diferencia)
      if (diferencia < -0.009) return 'Se pasó Bs ' + this.money(-diferencia)

      return 'Paga completo'
    },
    claseSaldo (entrega) {
      const cobro = this.cobros[entrega.factura_id]
      if (cobro.forma === 'CRÉDITO') return 'text-orange-9'

      const pagado = this.pagando(entrega)
      if (pagado <= 0) return 'text-blue-grey-7'

      const diferencia = Math.round((Number(entrega.total) - pagado) * 100) / 100
      if (diferencia < -0.009) return 'text-red-9 text-weight-bold'

      return diferencia > 0.009 ? 'text-orange-9' : 'text-green-9'
    },
    cobrar (entrega) {
      const cobro = this.cobros[entrega.factura_id]

      this.enviarEntrega(entrega, {
        estado: 'ENTREGADO',
        tipago: cobro.forma,
        monto_efectivo: cobro.efectivo || 0,
        monto_qr: cobro.qr || 0,
        observacion: null
      })
    },
    /**
     * Cierra de una vez todas las notas pendientes del punto.
     *
     * Van una atras de otra y no en paralelo: si una falla, las anteriores
     * quedan guardadas igual y el caminero ve cuantas entraron.
     */
    async cobrarTodo () {
      const pendientes = this.pendientesPunto.slice()
      if (!pendientes.length) return

      this.guardandoTodo = true
      let hechas = 0

      try {
        for (const entrega of pendientes) {
          const cobro = this.cobros[entrega.factura_id]

          await this.$api.post('caminero/cobrar', {
            factura_id: entrega.factura_id,
            estado: 'ENTREGADO',
            tipago: cobro.forma,
            monto_efectivo: cobro.efectivo || 0,
            monto_qr: cobro.qr || 0,
            observacion: null,
            lat: this.posicion?.latitude || null,
            lng: this.posicion?.longitude || null
          })
          hechas++
        }

        this.$q.notify({
          type: 'positive', position: 'top',
          message: 'Se registraron ' + hechas + ' entregas'
        })
        this.dialogoPunto = false
      } catch (err) {
        this.$q.notify({
          type: 'negative', position: 'top',
          message: (err.response?.data?.message || 'No se pudo registrar la entrega') +
            (hechas ? ' · quedaron guardadas ' + hechas : '')
        })
      } finally {
        this.guardandoTodo = false
        this.cargar()
      }
    },
    /**
     * Abre el retorno parcial con todo como si se hubiera entregado: el
     * caminero solo baja lo que el cliente devolvio.
     */
    abrirRetorno (entrega) {
      this.retornoEntrega = entrega
      this.retornoItems = (entrega.detalles || []).map(item => {
        // Lo que va por kilo se devuelve en kilos, que es lo que se cobra.
        const porPeso = Number(item.peso) > 0
        const original = Number(porPeso ? item.peso : item.cantidad) || 0
        return {
          cod_prod: item.cod_prod,
          nombre: item.nombre,
          unidadTexto: porPeso ? 'kg' : (item.unidad || 'u'),
          original,
          entregado: original,
          precio: Number(item.precio) || 0
        }
      })
      this.retornoCobro = this.esCredito(entrega)
        ? { forma: 'CRÉDITO', efectivo: null, qr: null }
        : { forma: 'CONTADO', efectivo: null, qr: null }
      this.retornoMotivo = ''
      this.dialogoRetorno = true
    },
    registrarRetorno () {
      const entrega = this.retornoEntrega
      this.guardando = entrega.factura_id

      this.$api.post('caminero/retorno-parcial', {
        factura_id: entrega.factura_id,
        items: this.retornoItems.map(fila => ({ cod_prod: fila.cod_prod, entregado: Number(fila.entregado) })),
        tipago: this.retornoCobro.forma,
        monto_efectivo: this.retornoCobro.efectivo || 0,
        monto_qr: this.retornoCobro.qr || 0,
        observacion: this.retornoMotivo || null,
        lat: this.posicion?.latitude || null,
        lng: this.posicion?.longitude || null
      }).then(res => {
        this.$q.notify({ type: 'positive', position: 'top', message: res.data.message })
        this.dialogoRetorno = false
        this.cargar()
      }).catch(err => {
        this.$q.notify({
          type: 'negative', position: 'top',
          message: err.response?.data?.message || 'No se pudo registrar el retorno parcial'
        })
      }).finally(() => { this.guardando = null })
    },
    abrirNoEntrega (entrega) {
      this.cobro = entrega
      this.motivo = ''
      this.dialogo = true
    },
    limpiarCobro () {
      this.cobro = {}
      this.motivo = ''
    },
    noEntregar () {
      this.enviarEntrega(this.cobro, {
        estado: 'NO ENTREGADO',
        tipago: null,
        monto_efectivo: null,
        monto_qr: null,
        observacion: this.motivo
      }, () => { this.dialogo = false })
    },
    enviarEntrega (entrega, cuerpo, alTerminar) {
      this.guardando = entrega.factura_id

      this.$api.post('caminero/cobrar', Object.assign({
        factura_id: entrega.factura_id,
        lat: this.posicion?.latitude || null,
        lng: this.posicion?.longitude || null
      }, cuerpo)).then(() => {
        this.$q.notify({ type: 'positive', position: 'top', message: 'Entrega registrada' })
        if (alTerminar) alTerminar()
        this.cargar()
      }).catch(err => {
        this.$q.notify({
          type: 'negative',
          position: 'top',
          message: err.response?.data?.message || 'No se pudo registrar la entrega'
        })
      }).finally(() => { this.guardando = null })
    }
  }
}
</script>

<style scoped>
/* 50px es el alto del toolbar de la app. */
.pantalla {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 50px);
  overflow: hidden;
}
.mitad-mapa {
  position: relative;
  flex: 0 0 50%;
}
.mitad-lista {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  background: #fff;
}

/* Los paneles flotan sobre el mapa; 500 los deja encima de las capas de
   Leaflet y debajo de sus controles. */
.panel-alto,
.panel-bajo {
  position: absolute;
  z-index: 500;
}
.panel-alto {
  top: 6px;
  right: 6px;
}
.panel-bajo {
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 0 -1px 4px rgba(0, 0, 0, 0.25);
}
.campo-fecha {
  width: 140px;
}
.texto-barra {
  font-size: 10px;
  font-weight: 700;
  color: #263238;
}
.caja {
  padding: 2px 0;
}
.caja-dato {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 3px;
}
.caja-monto {
  font-size: 13px;
  font-weight: 700;
}

.barra-buscar {
  background: #eceff1;
  border-top: 1px solid #cfd8dc;
  border-bottom: 1px solid #cfd8dc;
  padding: 4px 0;
}
.campo-buscar :deep(.q-field--dense .q-field__control),
.campo-buscar :deep(.q-field--dense .q-field__marginal) {
  height: 30px;
  min-height: 30px;
}
.campo-buscar :deep(.q-field__native) {
  font-size: 12px;
  padding: 0;
}

/* El numero de la fila es el mismo que el del marcador en el mapa. */
.marca {
  position: relative;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  line-height: 22px;
  text-align: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
}
/* Cuantos pedidos hay en esa puerta. Va en la esquina y no adentro para que
   no se confunda con el numero de la fila. */
.marca-cuantos {
  position: absolute;
  top: -6px;
  right: -8px;
  min-width: 15px;
  height: 15px;
  padding: 0 3px;
  border-radius: 8px;
  background: #263238;
  color: #fff;
  font-size: 9px;
  line-height: 15px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
}
.marca-verde {
  background: #2e7d32;
}
.marca-naranja {
  background: #ef6c00;
}
.marca-roja {
  background: #c62828;
}

.tabla {
  width: 100%;
  border-collapse: collapse;
}
.tabla th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #cfd8dc;
  color: #37474f;
  font-size: 9px;
  letter-spacing: 0.5px;
  text-align: left;
  padding: 2px 6px;
}
.tabla td {
  padding: 3px 6px;
  border-bottom: 1px solid #eceff1;
  max-width: 0;
}
.tabla tbody tr {
  cursor: pointer;
}
.tabla tbody tr:active {
  background: #e3f2fd;
}
.fila-cobrada {
  background: #e8f5e9;
}
.fila-rechazada {
  background: #ffebee;
}
.linea-cliente {
  font-size: 13px;
  font-weight: 600;
  color: #263238;
}
.linea-pie {
  font-size: 10px;
  color: #78909c;
}
/* El detalle se revisa parado al lado del camion: letra chica pero con las
   cantidades alineadas a la derecha, que es como se cuentan los bultos. */
.linea-detalle {
  cursor: pointer;
  user-select: none;
}
.tabla-detalle {
  width: 100%;
  border-collapse: collapse;
  margin-top: 3px;
  font-size: 11px;
}
.tabla-detalle td {
  padding: 2px 4px;
  border-top: 1px solid #eceff1;
  color: #37474f;
}
.det-cant {
  width: 62px;
  text-align: right;
  font-weight: 700;
  white-space: nowrap;
}
.det-nombre {
  word-break: break-word;
}
.det-monto {
  width: 62px;
  text-align: right;
  white-space: nowrap;
}
.monto {
  font-size: 14px;
  font-weight: 800;
  color: #263238;
  text-align: right;
}
.col-num {
  width: 30px;
}
.col-monto {
  width: 86px;
  text-align: right;
}
.col-acciones {
  width: 150px;
  white-space: nowrap;
  text-align: right;
}
/* Un monto no pasa de cuatro cifras: lo que sobra del ancho es para el
   recordatorio de cuanto habria que cobrar y para los dos botones. */
.campo-monto {
  max-width: 108px;
}
.tabla-retorno {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}
.tabla-retorno th {
  background: #eceff1;
  color: #37474f;
  font-size: 10px;
  padding: 3px 6px;
}
.tabla-retorno td {
  padding: 3px 6px;
  border-bottom: 1px solid #eceff1;
}
</style>
