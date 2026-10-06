<template>
  <!-- Media pantalla de mapa y media de pedidos. El alto se fija a mano
       porque el layout de la app arranca con min-height 0: sin esto los dos
       bloques nacen aplastados. -->
  <q-page class="pantalla">
    <div class="mitad-mapa">
      <!-- Con dos dedos se gira el mapa como en Google Maps (leaflet-rotate,
           que vive en el L global: por eso use-global-leaflet). -->
      <l-map
        v-model="zoom" :zoom="zoom" :center="centro" :use-global-leaflet="true"
        :options="opcionesMapa" @ready="mapaListo"
      >
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

        <!-- Donde esta parado el caminero: el circulo es el margen de error
             del GPS y el punto azul su posicion, como en Google Maps. -->
        <template v-if="miUbicacion">
          <l-circle
            :lat-lng="[miUbicacion.lat, miUbicacion.lng]" :radius="miUbicacion.precision || 0"
            color="#1E88E5" :weight="1" fill-color="#1E88E5" :fill-opacity="0.12"
          />
          <!-- Icono propio, distinto de los numeros de los clientes: circulo
               azul con una persona y una onda que late. -->
          <l-marker :lat-lng="[miUbicacion.lat, miUbicacion.lng]" :z-index-offset="1000">
            <l-icon :icon-size="[44, 44]" :icon-anchor="[22, 22]" class-name="">
              <div class="mi-ubicacion">
                <span class="mi-onda"></span>
                <span class="mi-punto"><i class="material-icons">person_pin_circle</i></span>
                <span class="mi-etiqueta">Yo</span>
              </div>
            </l-icon>
          </l-marker>
        </template>
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
        <q-btn round dense unelevated color="white" text-color="primary" icon="zoom_out_map"
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
        <!-- Al lado del tipo de mapa: muestra y sigue la posicion del caminero. -->
        <q-btn round dense unelevated
               :color="miUbicacion ? 'blue-7' : 'white'" :text-color="miUbicacion ? 'white' : 'primary'"
               :icon="miUbicacion ? 'my_location' : 'location_searching'"
               :loading="buscandoUbicacion" @click="mostrarMiUbicacion">
          <q-tooltip>Mi ubicación</q-tooltip>
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
        <!-- Flotan justo encima de la barra: la brujula aparece solo con el
             mapa girado y lo vuelve a poner con el norte arriba; el otro lleva
             directo a donde esta el caminero. -->
        <div class="botones-mapa column items-end q-gutter-sm">
          <q-btn
            v-if="Math.abs(rumbo) > 0.5" round unelevated color="white" class="shadow-3"
            @click="norteArriba"
          >
            <q-icon name="navigation" color="red-7" size="26px" :style="{ transform: 'rotate(' + (-rumbo) + 'deg)' }"/>
            <q-tooltip>Norte arriba</q-tooltip>
          </q-btn>
          <q-btn
            unelevated no-caps class="shadow-3 boton-volver"
            :round="!lejosDeMi" :rounded="lejosDeMi"
            :color="miUbicacion ? 'blue-7' : 'white'" :text-color="miUbicacion ? 'white' : 'blue-7'"
            icon="my_location" :label="lejosDeMi ? 'Volver a mi ubicación' : undefined"
            :loading="buscandoUbicacion" @click="mostrarMiUbicacion"
          >
            <q-tooltip>Volver a mi ubicación</q-tooltip>
          </q-btn>
        </div>
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
              <div class="linea-cliente ellipsis">
                <!-- La canasta en la que va lo del cliente, anotada al cargar. -->
                <span v-if="entrega.nro_canasta" class="canasta-num">C {{ entrega.nro_canasta }}</span>
                {{ entrega.cliente || entrega.nombre || 'Sin cliente' }}
              </div>
              <div class="linea-pie ellipsis">
                <q-icon name="place" size="11px"/>
                {{ entrega.direccion || 'Sin dirección' }}
              </div>
              <div v-if="entrega.nota_carga" class="linea-pie nota-carga ellipsis">
                <q-icon name="sticky_note_2" size="11px"/> {{ entrega.nota_carga }}
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
        <!-- En el celular el nombre va completo (sin cortar) y Encuesta / Ir
             bajan a su propia fila, grandes; en la compu siguen al costado. -->
        <q-card-section class="q-pb-sm" :class="esMovil ? 'q-px-sm q-pt-sm' : ''">
          <div class="row items-start no-wrap q-gutter-sm">
            <q-icon name="place" size="md" color="blue-grey-8" class="q-mt-xs"/>
            <div class="col" style="min-width: 0">
              <div class="text-subtitle1 text-weight-bold nombre-punto" :class="esMovil ? '' : 'ellipsis'">
                {{ punto.cliente || punto.entregas.length + ' pedidos en este punto' }}
              </div>
              <div class="text-caption text-grey-7" :class="esMovil ? '' : 'ellipsis-2-lines'">
                <span v-if="punto.cliente && punto.entregas.length > 1">
                  {{ punto.entregas.length }} pedidos ·
                </span>
                {{ punto.direccion || 'Sin dirección' }}
              </div>
            </div>
            <template v-if="!esMovil">
              <!-- La encuesta va arriba: es del cliente de la puerta, no de
                   cada nota. -->
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
            </template>
            <!-- Cerrar arriba y como icono: a pantalla completa en el celular
                 queda a mano sin bajar hasta el pie. -->
            <q-btn round flat dense icon="close" size="md" color="grey-8" v-close-popup>
              <q-tooltip>Cerrar</q-tooltip>
            </q-btn>
          </div>

          <div
            v-if="esMovil && ((punto.cliente && punto.entregas.length) || punto.lat)"
            class="row q-col-gutter-sm q-mt-xs"
          >
            <div v-if="punto.cliente && punto.entregas.length" class="col">
              <q-btn
                class="full-width boton-cabecera" unelevated no-caps
                icon="feedback" color="primary" label="Encuesta"
                @click="abrirEncuesta(punto.entregas[0])"
              />
            </div>
            <div v-if="punto.lat" class="col">
              <q-btn
                class="full-width boton-cabecera" unelevated no-caps type="a" target="_blank"
                :href="'https://www.google.com/maps/dir/?api=1&destination=' + punto.lat + ',' + punto.lng"
                icon="navigation" color="blue-8" label="Ir"
              />
            </div>
          </div>

          <!-- Las fotos del cliente (las que se cargan en Clientes): sirven
               para reconocer la puerta. Tocando una se ve grande. -->
          <div v-if="cargandoFotos" class="q-mt-sm text-caption text-grey-7">
            <q-spinner size="14px" class="q-mr-xs"/> Cargando fotos…
          </div>
          <div v-else-if="fotosPunto.length" class="row no-wrap q-gutter-xs q-mt-sm fotos-punto">
            <q-img
              v-for="foto in fotosPunto" :key="foto.id"
              :src="foto.url" ratio="1" class="foto-punto cursor-pointer rounded-borders"
              @click="fotoGrande = foto.url"
            />
          </div>
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
                      <div v-if="props.row.nro_canasta || props.row.nota_carga" class="q-mt-xs">
                        <span v-if="props.row.nro_canasta" class="canasta-num">Canasta {{ props.row.nro_canasta }}</span>
                        <span v-if="props.row.nota_carga" class="text-caption nota-carga">{{ props.row.nota_carga }}</span>
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
                    <div class="text-subtitle1 text-weight-bolder q-mx-xs">
                      Bs {{ money(props.row.total) }}
                    </div>
                  </div>

                  <table v-if="detalleAbierto[props.row.factura_id]" class="tabla-detalle">
                    <tr v-for="(item, i) in props.row.detalles" :key="i">
                      <td class="det-cant">{{ cantidadTexto(item) }}</td>
                      <td class="det-nombre">{{ item.nombre }}</td>
                      <td class="det-monto">{{ money(item.subtotal) }}</td>
                    </tr>
                  </table>

                  <!-- No entregado no es final: se puede volver y entregar. -->
                  <div v-if="esNoEntregado(props.row)" class="text-caption text-red-9 text-weight-medium">
                    <q-icon name="cancel" size="14px"/>
                    No entregado<span v-if="props.row.observacion"> · {{ props.row.observacion }}</span>
                    · se puede volver a entregar
                  </div>

                  <div v-if="esRectificacion(props.row)" class="text-caption text-primary text-weight-medium">
                    <q-icon name="edit_note" size="14px"/>
                    Rectificando · antes: {{ props.row.entrega_estado }}<span v-if="props.row.tipago"> {{ props.row.tipago }}</span>
                  </div>

                  <div v-if="props.row.cobrada && !esRectificacion(props.row)" class="text-caption text-green-9">
                    <q-icon name="task_alt" size="14px"/>
                    {{ props.row.tipago }} · cobrado Bs {{ money(cobrado(props.row)) }}
                    <span v-if="falto(props.row) > 0.009" class="text-red-9 text-weight-bold">
                      · faltó Bs {{ money(falto(props.row)) }}
                    </span>
                    <div v-if="props.row.retorno" class="text-deep-orange-9">
                      <q-icon name="assignment_return" size="14px"/> {{ props.row.observacion }}
                    </div>
                  </div>

                  <div v-else-if="cerrada(props.row)" class="text-caption text-red-9 ellipsis">
                    <q-icon name="cancel" size="14px"/>
                    {{ props.row.entrega_estado }}
                    <span v-if="props.row.observacion">· {{ props.row.observacion }}</span>
                  </div>

                  <!-- El credito ya venia decidido de caja: al caminero solo se
                       le recuerda que ahi no cobra nada. -->
                  <div v-else-if="esCredito(props.row)" class="text-body2 text-orange-9 q-mt-xs">
                    <q-icon name="schedule" size="16px"/>
                    <b>A crédito</b> · se entrega sin cobrar
                  </div>

                  <template v-else-if="cobros[props.row.factura_id]">
                    <q-btn-toggle
                      :model-value="cobros[props.row.factura_id].forma"
                      spread no-caps unelevated class="q-mt-xs"
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
                        class="col text-body2 text-weight-medium"
                        :class="claseSaldo(props.row)"
                      >
                        {{ leyendaSaldo(props.row) }}
                      </div>
                    </div>
                  </template>

                  <q-btn
                    v-if="puedeRectificar(props.row)" class="full-width q-mt-xs" outline dense no-caps
                    color="primary" icon="edit_note" label="Rectificar" @click="rectificar(props.row)"
                  />

                  <!-- Las cuatro acciones de la puerta, grandes y con su
                       nombre (de a dos por fila): se tocan con el celular en
                       la mano. -->
                  <div v-if="!cerrada(props.row) && (esCredito(props.row) || cobros[props.row.factura_id])" class="row q-col-gutter-xs q-mt-sm">
                    <div class="col-6">
                      <q-btn
                        class="full-width boton-accion" unelevated no-caps stack color="positive" icon="check_circle"
                        :label="esCredito(props.row) ? 'Entregar' : 'Entregar y cobrar'"
                        :loading="guardando === props.row.factura_id"
                        :disable="!cobroValido(props.row)" @click="cobrar(props.row)"
                      />
                    </div>
                    <div class="col-6">
                      <q-btn
                        class="full-width boton-accion" outline no-caps stack color="amber-9" icon="schedule"
                        :label="esMasTarde(props.row) ? 'Quitar más tarde' : 'Volver más tarde'"
                        @click="alternarMasTarde(props.row)"
                      />
                    </div>
                    <div class="col-6">
                      <q-btn
                        class="full-width boton-accion" outline no-caps stack color="deep-orange-8" icon="assignment_return"
                        label="Retornar" @click="abrirRetorno(props.row)"
                      />
                    </div>
                    <div v-if="!esNoEntregado(props.row)" class="col-6">
                      <q-btn
                        class="full-width boton-accion" outline no-caps stack color="negative" icon="cancel"
                        label="No entregado" @click="abrirNoEntrega(props.row)"
                      />
                    </div>
                  </div>
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
                  <div v-if="props.row.nro_canasta || props.row.nota_carga" class="q-mt-xs">
                    <span v-if="props.row.nro_canasta" class="canasta-num">Canasta {{ props.row.nro_canasta }}</span>
                    <span v-if="props.row.nota_carga" class="text-caption nota-carga">{{ props.row.nota_carga }}</span>
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

                  <div v-if="esNoEntregado(props.row)" class="text-caption text-red-9 text-weight-medium">
                    No entregado<span v-if="props.row.observacion"> · {{ props.row.observacion }}</span>
                    · se puede volver a entregar
                  </div>

                  <div v-if="esRectificacion(props.row)" class="text-caption text-primary text-weight-medium">
                    <q-icon name="edit_note" size="14px"/>
                    Rectificando · antes: {{ props.row.entrega_estado }}<span v-if="props.row.tipago"> {{ props.row.tipago }}</span>
                  </div>

                  <div v-if="props.row.cobrada && !esRectificacion(props.row)" class="text-caption text-green-9">
                    {{ props.row.tipago }} · cobrado Bs {{ money(cobrado(props.row)) }}
                    <span v-if="falto(props.row) > 0.009" class="text-red-9 text-weight-bold">
                      · faltó Bs {{ money(falto(props.row)) }}
                    </span>
                    <div v-if="props.row.retorno" class="text-deep-orange-9">
                      <q-icon name="assignment_return" size="14px"/> {{ props.row.observacion }}
                    </div>
                  </div>

                  <div v-else-if="cerrada(props.row)" class="text-caption text-red-9">
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
                  <div v-if="!cerrada(props.row)" class="row no-wrap q-gutter-xs justify-end">
                    <q-btn v-if="!esNoEntregado(props.row)"
                      class="boton-accion" outline no-caps stack color="negative" icon="cancel"
                      label="No entregado" @click="abrirNoEntrega(props.row)"
                    />
                    <q-btn
                      class="boton-accion" outline no-caps stack color="deep-orange-8" icon="assignment_return"
                      label="Retornar" @click="abrirRetorno(props.row)"
                    />
                    <q-btn
                      class="boton-accion" outline no-caps stack color="amber-9" icon="schedule"
                      :label="esMasTarde(props.row) ? 'Quitar más tarde' : 'Volver más tarde'"
                      @click="alternarMasTarde(props.row)"
                    />
                    <q-btn
                      class="boton-accion" unelevated no-caps stack color="positive" icon="check_circle"
                      :label="esCredito(props.row) ? 'Entregar' : 'Entregar y cobrar'"
                      :loading="guardando === props.row.factura_id"
                      :disable="!cobroValido(props.row)" @click="cobrar(props.row)"
                    />
                  </div>
                  <div v-else class="row no-wrap items-center q-gutter-xs justify-end">
                    <q-btn
                      v-if="puedeRectificar(props.row)" class="boton-accion" outline no-caps stack
                      color="primary" icon="edit_note" label="Rectificar" @click="rectificar(props.row)"
                    />
                    <q-icon v-if="props.row.cobrada" name="task_alt" color="positive" size="24px"/>
                  </div>
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
        <q-card-section class="bg-deep-orange-7 text-white q-py-sm row items-center no-wrap">
          <div class="col">
            <div class="text-subtitle1 text-weight-bold">Retorno parcial</div>
            <div class="text-caption">
              {{ retornoEntrega.cliente || retornoEntrega.nombre }} · Pedido #{{ retornoEntrega.nro_pedido }} ·
              Bs {{ money(retornoEntrega.total) }}
            </div>
          </div>
          <q-btn round flat dense icon="close" color="white" v-close-popup/>
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
              :model-value="retornoCobro.forma" spread no-caps unelevated class="q-mt-sm"
              toggle-color="primary" color="grey-3" text-color="grey-9" :options="formasPago"
              @update:model-value="cambiarFormaRetorno"
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
          <div v-if="verEntrega.nro_canasta || verEntrega.nota_carga" class="q-mt-xs">
            <span v-if="verEntrega.nro_canasta" class="canasta-num">Canasta {{ verEntrega.nro_canasta }}</span>
            <span v-if="verEntrega.nota_carga" class="text-caption nota-carga">{{ verEntrega.nota_carga }}</span>
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

    <!-- Foto del cliente en grande. -->
    <q-dialog :model-value="!!fotoGrande" @update:model-value="v => { if (!v) fotoGrande = null }">
      <q-card style="width: 640px; max-width: 96vw">
        <q-img :src="fotoGrande" fit="contain" style="max-height: 80vh"/>
        <q-card-actions align="right" class="q-pa-xs">
          <q-btn flat dense no-caps label="Cerrar" v-close-popup/>
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { markRaw } from 'vue'
import { date } from 'quasar'
// Primero Leaflet (deja L en window) y despues el plugin que lo extiende
// para girar el mapa; el orden de estos dos imports importa.
import 'leaflet'
import 'leaflet-rotate'
import { LMap, LIcon, LTileLayer, LMarker, LCircle } from '@vue-leaflet/vue-leaflet'
import 'leaflet/dist/leaflet.css'

export default {
  name: 'MisEntregas',
  components: { LMap, LIcon, LTileLayer, LMarker, LCircle },
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
      // Fotos del cliente del punto abierto y la que se esta viendo grande.
      fotosPunto: [],
      cargandoFotos: false,
      fotoGrande: null,
      puntoIds: [],
      // Lo que se esta por cobrar en cada fila, por factura_id.
      cobros: {},
      // Notas ya registradas que el caminero reabrio para rectificar, por
      // factura_id: se registran de nuevo y reemplazan a la entrega anterior.
      rectificando: {},
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
      // Lo que se dibuja en el mapa al pedir "Mi ubicación": { lat, lng, precision }.
      miUbicacion: null,
      buscandoUbicacion: false,
      // Giro del mapa en grados (0 = norte arriba) y si el centro quedo lejos
      // del caminero, para ofrecerle volver.
      rumbo: 0,
      lejosDeMi: false,
      // Despues de "volver a mi ubicación" el mapa sigue al caminero hasta
      // que lo arrastre con el dedo.
      siguiendo: false,
      opcionesMapa: {
        rotate: true,
        touchRotate: true,
        // La brujula la pone la pantalla, mas grande y junto a "volver".
        rotateControl: false,
        bearing: 0
      },
      // Notas que el caminero dejo para despues ("Volver más tarde"):
      // { factura_id: true }. Siguen pendientes, solo bajan en la lista.
      masTarde: {},
      mapa: null
    }
  },
  // Al salir de la pantalla se deja de seguir el GPS: gasta bateria.
  beforeUnmount () {
    this.dejarDeSeguir()
  },
  created () {
    // Id del seguimiento del GPS de "Mi ubicación"; null si no esta activo.
    this.vigia = null
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
      // Pendientes; despues lo dejado para mas tarde y lo no entregado (se
      // puede volver a entregar); al final lo cerrado.
      const orden = entrega => this.cerrada(entrega) ? 2 : (this.esMasTarde(entrega) || this.esNoEntregado(entrega) ? 1 : 0)
      return visibles.slice().sort((a, b) => orden(a) - orden(b))
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
    /** Las notas del punto que todavia no se cerraron. */
    pendientesPunto () {
      return this.punto.entregas.filter(
        entrega => !this.cerrada(entrega)
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
     * Lo que va a granel (KG o CAJA) se cobra por los kilos de la balanza, no
     * por las piezas que se cargan, asi que la linea se lee como la cuenta que
     * da el importe: el precio por los kilos. Poner ahi las piezas hacia leer
     * una multiplicacion que no existe (20 cajas x 3 kg no son 60).
     */
    cantidadTexto (item) {
      const peso = Number(item.peso) || 0
      const cant = Number(item.cantidad) || 0

      if (peso > 0) {
        return 'Bs ' + this.money(item.precio) + ' × ' + this.cantidad(peso) + ' kg'
      }

      return this.cantidad(cant) + (item.unidad ? ' ' + item.unidad : '')
    },
    /** Una nota cerrada: ya se cobró o quedó como no entregada. */
    // No entregado no cierra: el caminero puede volver mas tarde y entregar.
    cerrada (entrega) {
      if (this.rectificando[entrega.factura_id]) return false
      return !!(entrega.cobrada || (entrega.entrega_estado && !this.esNoEntregado(entrega)))
    },
    /** Lo cerrado se puede corregir solo el mismo dia en que se registro. */
    puedeRectificar (entrega) {
      return this.cerrada(entrega) && !!entrega.rectificable
    },
    /**
     * Reabre una nota ya registrada para cargarla de nuevo desde cero. La
     * entrega anterior no se borra: la nueva la reemplaza al guardarse.
     */
    rectificar (entrega) {
      this.$q.dialog({
        title: 'Rectificar entrega',
        message: 'Se vuelve a registrar la nota de ' + this.clienteDe(entrega) +
          ' (Bs ' + this.money(entrega.total) + ') desde cero. Lo que guardes reemplaza a lo registrado.',
        cancel: { label: 'Cancelar', flat: true },
        ok: { label: 'Rectificar', color: 'primary', unelevated: true },
        persistent: true
      }).onOk(() => {
        this.rectificando = { ...this.rectificando, [entrega.factura_id]: true }
        this.cobros[entrega.factura_id] = this.esCredito(entrega)
          ? { forma: 'CRÉDITO', efectivo: null, qr: null }
          : { forma: 'CONTADO', efectivo: null, qr: null }
      })
    },
    esRectificacion (entrega) {
      return !!this.rectificando[entrega.factura_id]
    },
    esNoEntregado (entrega) {
      return !entrega.cobrada && entrega.entrega_estado === 'NO ENTREGADO'
    },
    claseFila (entrega) {
      if (entrega.cobrada) return 'fila-cobrada'
      if (entrega.entrega_estado) return 'fila-rechazada'
      return this.esMasTarde(entrega) ? 'fila-mas-tarde' : ''
    },
    claseEstado (entrega) {
      if (entrega.cobrada) return 'marca-verde'
      if (entrega.entrega_estado) return 'marca-roja'
      return this.esMasTarde(entrega) ? 'marca-amarilla' : 'marca-naranja'
    },
    esMasTarde (entrega) {
      return !this.cerrada(entrega) && !!this.masTarde[entrega.factura_id]
    },
    // Lo dejado para despues se recuerda en el celular, por dia: no es un
    // estado de la entrega, solo el orden en que el caminero hace su ruta.
    claveMasTarde () {
      return 'caminero-mas-tarde-' + this.fecha
    },
    leerMasTarde () {
      try {
        this.masTarde = JSON.parse(localStorage.getItem(this.claveMasTarde()) || '{}') || {}
      } catch (e) {
        this.masTarde = {}
      }
    },
    alternarMasTarde (entrega) {
      const marcas = Object.assign({}, this.masTarde)
      if (marcas[entrega.factura_id]) {
        delete marcas[entrega.factura_id]
      } else {
        marcas[entrega.factura_id] = true
        this.$q.notify({
          type: 'warning', position: 'top', icon: 'schedule',
          message: 'Queda para más tarde: ' + this.clienteDe(entrega)
        })
        this.dialogoPunto = false
      }
      this.masTarde = marcas
      try { localStorage.setItem(this.claveMasTarde(), JSON.stringify(marcas)) } catch (e) {}
    },
    // La marca de un punto con varios pedidos: verde solo cuando ya no queda
    // nada por cobrar ahi, para que el caminero no se vaya de la puerta antes.
    clasePunto (entregas) {
      if (entregas.every(entrega => entrega.cobrada)) return 'marca-verde'
      if (entregas.some(entrega => !this.cerrada(entrega) && !this.esMasTarde(entrega) && !this.esNoEntregado(entrega))) {
        return 'marca-naranja'
      }
      if (entregas.some(this.esMasTarde)) return 'marca-amarilla'
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
      this.rectificando = {}
      // Cada fila arranca en efectivo por el total de la nota, que es lo que
      // pasa casi siempre; el caminero solo corrige cuando le dan de menos.
      this.cobros = {}
      punto.entregas.forEach(entrega => {
        if (this.cerrada(entrega)) return
        // El monto arranca vacio a proposito: primero se cuenta la plata que
        // el cliente pone en la mano y recien ahi se escribe. Debajo del campo
        // queda el recordatorio de cuanto tendria que entrar.
        this.cobros[entrega.factura_id] = this.esCredito(entrega)
          ? { forma: 'CRÉDITO', efectivo: null, qr: null }
          : { forma: 'CONTADO', efectivo: null, qr: null }
      })
      this.dialogoPunto = true
      this.cargarFotos(punto)
    },
    // Fotos de los clientes del punto; si falla, la entrega sigue igual.
    async cargarFotos (punto) {
      const ids = [...new Set(punto.entregas.map(entrega => entrega.cliente_id).filter(Boolean))]
      this.fotosPunto = []
      if (!ids.length) return
      this.cargandoFotos = true
      try {
        const listas = await Promise.all(ids.map(id =>
          this.$api.get('cliente-photos', { params: { cliente_id: id } }).then(res => res.data || []).catch(() => [])
        ))
        this.fotosPunto = listas.flat()
      } finally {
        this.cargandoFotos = false
      }
    },
    // Desde la lista se abren todas las notas de ese cliente, y solo las
    // suyas: en un mercado varios clientes caen en el mismo punto del mapa y
    // no tienen que aparecer mezclados al tocar uno.
    abrirPuntoDe (entrega) {
      const clave = fila => fila.cliente_id ? 'id:' + fila.cliente_id : 'nombre:' + this.clienteDe(fila)
      const entregas = this.filtradas.filter(fila => clave(fila) === clave(entrega))

      this.abrirPunto({ entregas: entregas.length ? entregas : [entrega] })
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
      if (mapa.getBearing) {
        mapa.on('rotate', () => { this.rumbo = mapa.getBearing() })
      }
      mapa.on('moveend', this.revisarDistancia)
      // Arrastrar el mapa a mano corta el seguimiento, como en Google Maps.
      mapa.on('dragstart', () => { this.siguiendo = false })
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
    // Primer toque: busca la posicion, la dibuja y la sigue mientras camina.
    // Los siguientes: vuelve a centrar el mapa en el caminero.
    mostrarMiUbicacion () {
      if (!navigator.geolocation) {
        this.$q.notify({ type: 'warning', position: 'top', message: 'Este celular no da la ubicación' })
        return
      }
      if (this.miUbicacion) {
        this.centrarEnMi()
        return
      }
      if (this.vigia !== null && this.vigia !== undefined) return
      this.buscandoUbicacion = true
      let primera = true
      this.vigia = navigator.geolocation.watchPosition(
        posicion => {
          this.posicion = posicion.coords
          this.miUbicacion = {
            lat: posicion.coords.latitude,
            lng: posicion.coords.longitude,
            precision: posicion.coords.accuracy
          }
          this.buscandoUbicacion = false
          if (primera) {
            primera = false
            this.centrarEnMi()
          } else if (this.siguiendo && this.mapa) {
            // Siguiendo: el mapa acompaña al caminero sin cambiarle el zoom.
            this.mapa.panTo([this.miUbicacion.lat, this.miUbicacion.lng], { animate: true })
          } else {
            this.revisarDistancia()
          }
        },
        error => {
          this.buscandoUbicacion = false
          this.dejarDeSeguir()
          this.$q.notify({
            type: 'negative', position: 'top',
            message: error.code === 1
              ? 'Activá el permiso de ubicación del navegador para verte en el mapa'
              : 'No se pudo obtener tu ubicación; probá de nuevo al aire libre'
          })
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
      )
    },
    centrarEnMi () {
      if (!this.mapa || !this.miUbicacion) return
      this.mapa.setView([this.miUbicacion.lat, this.miUbicacion.lng], Math.max(this.mapa.getZoom(), 16))
      this.lejosDeMi = false
      this.siguiendo = true
    },
    // Si el caminero arrastro el mapa a mas de ~150 m de donde esta, el boton
    // se agranda con el texto "Volver a mi ubicación".
    revisarDistancia () {
      if (!this.mapa || !this.miUbicacion) {
        this.lejosDeMi = false
        return
      }
      const centro = this.mapa.getCenter()
      this.lejosDeMi = this.metros({ lat: centro.lat, lng: centro.lng }, this.miUbicacion) > 150
    },
    norteArriba () {
      if (this.mapa && this.mapa.setBearing) this.mapa.setBearing(0)
      this.rumbo = 0
    },
    dejarDeSeguir () {
      if (this.vigia !== null && this.vigia !== undefined && navigator.geolocation) {
        navigator.geolocation.clearWatch(this.vigia)
      }
      this.vigia = null
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
      this.leerMasTarde()
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
        // Por QR se transfiere justo lo de la nota: el monto entra completo y
        // el caminero lo corrige solo si le mandaron otra cosa.
        cobro.qr = Number(entrega.total) || null
        cobro.efectivo = null
      } else {
        cobro.efectivo = monto
        cobro.qr = null
      }
    },
    // Lo mismo en el retorno parcial, contra lo que se quedo el cliente.
    cambiarFormaRetorno (forma) {
      const cobro = this.retornoCobro
      const monto = Number(cobro.efectivo || 0) + Number(cobro.qr || 0) || null
      cobro.forma = forma
      if (forma === 'PAGO QR') {
        cobro.qr = this.retornoTotal || null
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
            rectificar: this.esRectificacion(entrega),
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
        rectificar: this.esRectificacion(entrega),
        lat: this.posicion?.latitude || null,
        lng: this.posicion?.longitude || null
      }).then(res => {
        this.$q.notify({ type: 'positive', position: 'top', message: res.data.message })
        this.dialogoRetorno = false
        this.dialogoPunto = false
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
        rectificar: this.esRectificacion(entrega),
        lat: this.posicion?.latitude || null,
        lng: this.posicion?.longitude || null
      }, cuerpo)).then(() => {
        this.$q.notify({
          type: 'positive', position: 'top',
          message: this.esRectificacion(entrega) ? 'Entrega rectificada' : 'Entrega registrada'
        })
        // Cobrada o anulada, la nota ya esta cerrada: se vuelve a la lista.
        this.dialogoPunto = false
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
/* Numero de canasta: lo primero que se busca al bajar la mercaderia. */
.canasta-num {
  display: inline-block;
  background: #e65100;
  color: #fff;
  font-weight: 800;
  font-size: 11px;
  line-height: 1.4;
  padding: 0 6px;
  border-radius: 4px;
  margin-right: 4px;
}
.nota-carga {
  color: #6d4c41;
  font-style: italic;
}
.fotos-punto {
  overflow-x: auto;
}
.foto-punto {
  width: 72px;
  min-width: 72px;
  border: 1px solid rgba(0, 0, 0, .12);
}
/* El nombre del cliente puede ser largo: en el celular se parte en varias
   lineas en vez de cortarse con puntos suspensivos. */
.nombre-punto {
  line-height: 1.25;
  word-break: break-word;
}
.boton-cabecera {
  min-height: 40px;
  border-radius: 8px;
}
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
/* Encima de la barra de abajo, a la derecha, como en Google Maps. */
.botones-mapa {
  position: absolute;
  right: 10px;
  bottom: calc(100% + 10px);
}
.boton-volver {
  min-height: 44px;
  min-width: 44px;
  font-weight: 600;
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
/* Mi ubicacion: azul y redonda para no confundirse con las marcas
   numeradas de los clientes; la onda late para encontrarla rapido. */
.mi-ubicacion {
  position: relative;
  width: 44px;
  height: 44px;
}
.mi-punto {
  position: absolute;
  left: 7px;
  top: 7px;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: #1e88e5;
  border: 3px solid #fff;
  box-shadow: 0 1px 6px rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  z-index: 2;
}
.mi-punto .material-icons {
  font-size: 18px;
}
.mi-onda {
  position: absolute;
  left: 0;
  top: 0;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: rgba(30, 136, 229, 0.35);
  animation: mi-latido 1.8s ease-out infinite;
  z-index: 1;
}
.mi-etiqueta {
  position: absolute;
  left: 50%;
  top: 40px;
  transform: translateX(-50%);
  background: #1e88e5;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  line-height: 1;
  padding: 2px 6px;
  border-radius: 8px;
  white-space: nowrap;
  z-index: 2;
}
@keyframes mi-latido {
  0% { transform: scale(0.6); opacity: 0.9; }
  100% { transform: scale(1.6); opacity: 0; }
}
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
.marca-amarilla {
  background: #f9a825;
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
.fila-mas-tarde {
  background: #fff8e1;
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
  width: 96px;
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
/* Las acciones de la puerta: se tocan con el pulgar, asi que etiqueta grande. */
.boton-accion {
  min-height: 52px;
  font-size: 14px;
  font-weight: 700;
  line-height: 1.1;
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
