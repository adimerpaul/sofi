<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md">
        <div class="text-h6 text-weight-bold">Facturación</div>
        <div class="text-caption text-grey-7">
          Ventas y facturas registradas desde el sistema web
        </div>
      </div>
      <!-- Todo lo que se imprime o se exporta del listado sale de este menu, y
           siempre sobre lo que los filtros estan mostrando. -->
      <div class="col-auto">
        <q-btn-dropdown
          color="primary" outline no-caps icon="print" label="Imprimir / Exportar"
          :loading="exportando"
        >
          <q-list dense style="min-width: 280px">
            <q-item-label header class="q-py-xs">Comprobantes del filtro (los anulados no se imprimen)</q-item-label>

            <!-- Lo mas comun al cerrar el dia: el paquete completo, cada venta
                 en el papel que le toca, en un solo tiro de impresora. -->
            <q-item clickable v-close-popup @click="lote('todos', true)">
              <q-item-section avatar><q-icon name="print" color="primary"/></q-item-section>
              <q-item-section>
                Imprimir todos los comprobantes
                <q-item-label caption>Facturas y vouchers juntos, como se entregó cada venta</q-item-label>
              </q-item-section>
            </q-item>

            <q-item clickable v-close-popup @click="lote('voucher', true)">
              <q-item-section avatar><q-icon name="receipt_long" color="blue-grey-7"/></q-item-section>
              <q-item-section>
                Imprimir todos los vouchers
                <q-item-label caption>Solo las ventas que no son factura</q-item-label>
              </q-item-section>
            </q-item>

            <q-item clickable v-close-popup @click="lote('factura', true)">
              <q-item-section avatar><q-icon name="verified" color="green-7"/></q-item-section>
              <q-item-section>
                Imprimir todas las facturas
                <q-item-label caption>Solo las ventas entregadas como factura</q-item-label>
              </q-item-section>
            </q-item>

            <q-item clickable v-close-popup @click="lote('todos', false)">
              <q-item-section avatar><q-icon name="picture_as_pdf" color="red-7"/></q-item-section>
              <q-item-section>
                Descargar todos en PDF
                <q-item-label caption>El paquete completo, como archivo</q-item-label>
              </q-item-section>
            </q-item>

            <q-item clickable v-close-popup @click="lote('voucher', false)">
              <q-item-section avatar><q-icon name="picture_as_pdf" color="red-7"/></q-item-section>
              <q-item-section>
                Descargar vouchers en PDF
                <q-item-label caption>El mismo lote de vouchers, como archivo</q-item-label>
              </q-item-section>
            </q-item>

            <q-item clickable v-close-popup @click="lote('factura', false)">
              <q-item-section avatar><q-icon name="picture_as_pdf" color="red-7"/></q-item-section>
              <q-item-section>Descargar facturas en PDF</q-item-section>
            </q-item>

            <q-separator class="q-my-xs"/>
            <q-item-label header class="q-py-xs">Reporte de ventas</q-item-label>

            <q-item clickable v-close-popup @click="reporte('ventas', 'pdf')">
              <q-item-section avatar><q-icon name="summarize" color="red-7"/></q-item-section>
              <q-item-section>
                Reporte en PDF
                <q-item-label caption>Una fila por venta, con totales</q-item-label>
              </q-item-section>
            </q-item>

            <q-item clickable v-close-popup @click="reporte('ventas', 'excel')">
              <q-item-section avatar><q-icon name="grid_on" color="green-8"/></q-item-section>
              <q-item-section>
                Exportar a Excel
                <q-item-label caption>Con filtros, totales y formato</q-item-label>
              </q-item-section>
            </q-item>

            <q-separator class="q-my-xs"/>
            <q-item-label header class="q-py-xs">Cambios en los pedidos</q-item-label>

            <q-item clickable v-close-popup @click="reporte('cambios', 'pdf')">
              <q-item-section avatar>
                <q-icon name="published_with_changes" color="deep-orange"/>
              </q-item-section>
              <q-item-section>
                Cambios en PDF
                <q-item-label caption>Lo pedido contra lo entregado</q-item-label>
              </q-item-section>
            </q-item>

            <q-item clickable v-close-popup @click="reporte('cambios', 'excel')">
              <q-item-section avatar>
                <q-icon name="published_with_changes" color="green-8"/>
              </q-item-section>
              <q-item-section>Cambios en Excel</q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>
      </div>

      <div class="col-auto">
        <q-btn
          v-if="can('facturacionNueva')"
          color="positive" unelevated no-caps icon="add_shopping_cart"
          label="Nueva venta" to="/facturacion/nueva"
        />
      </div>
    </div>

    <q-card flat bordered class="q-pa-sm q-mb-md">
      <div class="row q-col-gutter-sm" @keyup.enter="recargar">
        <div class="col-6 col-md-2">
          <q-input v-model="filtros.desde" type="date" dense outlined label="Desde"/>
        </div>
        <div class="col-6 col-md-2">
          <q-input v-model="filtros.hasta" type="date" dense outlined label="Hasta"/>
        </div>
        <div class="col-12 col-md-3">
          <q-input v-model="filtros.buscar" dense outlined clearable label="Cliente, NIT, número o pedido">
            <template v-slot:append><q-icon name="search"/></template>
          </q-input>
        </div>
        <!-- El cajero casi siempre mira un solo tipo de comprobante: los chips
             lo cambian de un toque, sin abrir un desplegable. -->
        <div class="col-12 col-md-3 row items-center q-gutter-xs">
          <q-chip
            v-for="opcion in tiposComprobante" :key="opcion.label"
            clickable dense
            :color="filtros.tipo === opcion.valor ? opcion.color : 'grey-3'"
            :text-color="filtros.tipo === opcion.valor ? 'white' : 'grey-8'"
            :icon="opcion.icono"
            :label="opcion.label + ' (' + (conteos[opcion.clave] || 0) + ')'"
            @click="filtrarTipo(opcion.valor)"
          />
        </div>
        <div class="col-6 col-md-2">
          <q-select
            v-model="filtros.estado" dense outlined clearable
            label="Estado" :options="['ACTIVO', 'ANULADO']"
          />
        </div>
        <!-- col-md-auto y no col-md: con los filtros sumando 12 columnas, un
             col-md (ancho base 0) se metia en la misma linea sin ancho y los
             botones desaparecian. -->
        <div class="col-12 col-md-auto row items-center q-gutter-sm">
          <q-btn :loading="loading" color="primary" icon="search" no-caps label="Buscar" @click="recargar"/>
          <q-btn flat color="grey-7" icon="layers_clear" no-caps label="Limpiar" @click="limpiar"/>
        </div>
      </div>

      <!-- Una linea por camion con lo que ya lleva facturado, igual que en
           pedidos por facturar; tocando se filtra a ese camion. -->
      <div v-if="camiones.length" class="q-mt-sm rounded-borders camion-caja">
        <div class="row items-center no-wrap q-px-xs bg-grey-3 camion-titulo">
          <div class="text-weight-bold text-grey-8">CARGA POR CAMION</div>
          <q-space/>
          <div class="text-weight-bolder" :class="facturadosTotal === pedidosTotal ? 'text-green-9' : 'text-orange-9'">
            {{ facturadosTotal }}/{{ pedidosTotal }} · {{ porcentajeTotal }}%
          </div>
          <q-btn v-if="filtros.camion" flat dense no-caps size="sm" padding="0 4px" class="q-ml-xs" color="primary"
                 icon="clear" label="Todos" @click="alternarCamion(filtros.camion)"/>
        </div>
        <div
          v-for="opcion in camiones" :key="opcion.value"
          class="row items-center no-wrap camion-fila"
          :class="filtros.camion === opcion.value ? 'bg-blue-2' : ''"
          @click="alternarCamion(opcion.value)"
        >
          <div class="camion-placa ellipsis" :style="estiloColor(opcion.color)">{{ opcion.placa }}</div>
          <q-linear-progress
            class="col q-mx-xs" rounded size="13px" :value="opcion.progreso"
            :color="opcion.completo ? 'positive' : 'orange-7'" track-color="grey-4"
          >
            <div class="absolute-full flex flex-center">
              <span class="camion-porcentaje" :class="opcion.progreso > 0.55 ? 'text-white' : 'text-grey-9'">
                {{ opcion.porcentaje }}%
              </span>
            </div>
          </q-linear-progress>
          <div class="camion-conteo" :class="opcion.completo ? 'text-green-9' : 'text-grey-9'">
            {{ opcion.facturados }}/{{ opcion.total }}
          </div>
        </div>
      </div>

      <!-- El caminero revisa su carga antes de salir y recien ahi se imprime:
           al filtrar por un camion se dice como viene esa revision, para no
           descubrirlo cuando la impresion rebota. -->
      <q-banner
        v-if="carga" dense rounded class="q-mt-sm"
        :class="carga.completo ? 'bg-green-1 text-green-10' : 'bg-orange-1 text-orange-10'"
      >
        <template v-slot:avatar>
          <q-icon :name="carga.completo ? 'verified' : 'lock'" :color="carga.completo ? 'green-8' : 'orange-9'"/>
        </template>
        <template v-if="carga.completo">
          Carga del camión {{ carga.placa }} verificada por {{ carga.verificado_por || 'el caminero' }}
          <span v-if="carga.verificado_en">el {{ verificadoEn(carga.verificado_en) }}</span>.
          Se puede imprimir.
          <span v-if="carga.observados" class="text-weight-bold text-deep-orange-9">
            · {{ carga.observados }} {{ carga.observados === 1 ? 'canasta observada' : 'canastas observadas' }}
          </span>
        </template>
        <template v-else>
          El camión {{ carga.placa }} todavía no verificó su carga
          ({{ carga.verificados }}/{{ carga.comprobantes }} canastas): sus facturas y vouchers
          no se pueden imprimir hasta que el caminero termine de revisarla.
        </template>
        <!-- Atajo de caja: aprueba todas las canastas del camion de una vez. -->
        <template v-if="!carga.completo && can('facturacionAprobarCarga')" v-slot:action>
          <q-btn
            unelevated no-caps color="green-8" icon="done_all"
            :label="'Aprobar camión ' + carga.placa"
            :loading="aprobandoCarga" @click="aprobarCarga"
          />
        </template>
      </q-banner>
    </q-card>

    <q-table
      flat bordered dense
      class="tabla-compacta"
      :rows="facturas"
      :columns="columns"
      row-key="id"
      v-model:pagination="pagination"
      :loading="loading"
      :rows-per-page-options="[10, 20, 50, 100, 0]"
      @request="onRequest"
    >
      <template v-slot:body-cell-id="props">
        <q-td :props="props">
          <q-badge color="red-4" text-color="white">#{{ props.value }}</q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-tipo_comprobante="props">
        <q-td :props="props" class="text-center">
          <q-chip
            dense square
            :color="props.value === 'FACTURA' ? 'green-7' : 'blue-grey-6'"
            text-color="white"
            :icon="props.value === 'FACTURA' ? 'verified' : 'receipt'"
            :label="props.value === 'FACTURA' ? 'Factura' : 'Venta'"
          />
        </q-td>
      </template>

      <template v-slot:body-cell-fecha="props">
        <q-td :props="props" style="white-space: nowrap">
          {{ fechaCorta(props.value) }}
          <span class="text-grey-7">{{ horaCorta(props.row.hora) }}</span>
        </q-td>
      </template>

      <template v-slot:body-cell-pedido="props">
        <q-td :props="props">
          <template v-if="props.value">
            <q-badge color="primary" text-color="white">#{{ props.value }}</q-badge>
            <div class="text-caption text-grey-7">{{ nombreTipoPedido(props.row.pedido_tipo) }}</div>
          </template>
          <span v-else class="text-grey-6">Venta directa</span>
        </q-td>
      </template>

      <!-- Como viene la revision del caminero sobre la canasta de esta venta:
           mientras quede una sin revisar, el camion no puede imprimir. -->
      <template v-slot:body-cell-carga="props">
        <q-td :props="props" class="text-center">
          <template v-if="props.value !== 'NO_APLICA'">
            <q-chip
              dense square
              :color="chipCarga(props.value).color" text-color="white"
              :icon="chipCarga(props.value).icono" :label="chipCarga(props.value).texto"
            >
              <q-tooltip>{{ detalleCarga(props.row) }}</q-tooltip>
            </q-chip>
            <!-- Caja da por revisada la canasta sin esperar al caminero. -->
            <q-btn
              v-if="can('facturacionAprobarCarga') && puedeRevisar(props.row)"
              dense unelevated no-caps size="sm" padding="0 6px" color="green-8" icon="done"
              label="Revisado" class="q-ml-xs"
              :loading="marcandoCarga === props.row.id" @click="marcarCarga(props.row, true)"
            >
              <q-tooltip>Marcar la carga como revisada</q-tooltip>
            </q-btn>
            <!-- Lo que anoto el caminero va a la vista y no solo en el tooltip:
                 es lo que caja tiene que resolver antes de que salga el camion. -->
            <div
              v-if="props.row.carga_observacion"
              class="text-caption text-weight-medium celda-observacion"
              :class="props.value === 'OBSERVADA' ? 'text-deep-orange-9' : 'text-grey-8'"
            >
              {{ props.row.carga_observacion }}
              <q-tooltip>{{ props.row.carga_observacion }}</q-tooltip>
            </div>
          </template>
          <span v-else class="text-grey-6">—</span>
        </q-td>
      </template>

      <template v-slot:body-cell-entrega="props">
        <q-td :props="props" class="text-center">
          <template v-if="props.value !== 'NO_APLICA'">
            <q-chip
              dense square
              :color="chipEntrega(props.value).color" text-color="white"
              :icon="chipEntrega(props.value).icono" :label="chipEntrega(props.value).texto"
            >
              <q-tooltip>{{ detalleEntrega(props.row) }}</q-tooltip>
            </q-chip>
            <!-- El motivo de una no entrega es lo que caja tiene que resolver,
                 asi que va a la vista y no escondido en el tooltip. -->
            <div
              v-if="props.row.entrega_observacion && props.value !== 'ENTREGADO'"
              class="text-caption text-weight-medium celda-observacion"
              :class="props.value === 'RETORNO PARCIAL' ? 'text-deep-orange-9' : 'text-red-9'"
            >
              {{ props.row.entrega_observacion }}
              <q-tooltip>{{ props.row.entrega_observacion }}</q-tooltip>
            </div>
            <!-- El caminero marco que el cliente devolvio parte: caja edita el
                 comprobante (anula y emite otro con lo que se quedo). -->
            <q-btn
              v-if="retornoPendiente(props.row) && can('facturacionAnular')"
              dense unelevated no-caps size="sm" padding="0 6px" color="deep-orange-7" icon="edit"
              label="Editar" class="q-mt-xs" @click="pedirEdicion(props.row)"
            >
              <q-tooltip>Anular y emitir otro con lo que se quedó el cliente</q-tooltip>
            </q-btn>
          </template>
          <span v-else class="text-grey-6">—</span>
        </q-td>
      </template>

      <template v-slot:body-cell-estado="props">
        <q-td :props="props" class="text-center">
          <q-badge :color="props.value === 'ANULADO' ? 'negative' : 'positive'" text-color="white">
            {{ props.value }}
          </q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-siat="props">
        <q-td :props="props">
          <template v-if="props.row.tipo_comprobante === 'FACTURA'">
            <q-badge
              :color="props.row.estado_siat === 'ERROR' ? 'negative' : (props.row.online ? 'positive' : 'orange-8')"
            >
              {{ props.row.estado_siat || 'NO ENVIADA' }}
            </q-badge>
            <div
              v-if="props.row.estado_siat === 'ERROR' && props.row.mensaje_siat"
              class="text-caption text-negative q-mt-xs"
              style="max-width: 280px; white-space: normal"
            >
              {{ props.row.mensaje_siat }}
            </div>
          </template>
          <span v-else class="text-grey-6">—</span>
        </q-td>
      </template>

      <template v-slot:body-cell-nombre="props">
        <q-td :props="props">
          <template v-if="props.value">
            {{ props.value }}
            <div class="text-caption text-grey-7">NIT {{ props.row.nit || '—' }}</div>
          </template>
          <span v-else class="text-grey-6">Sin cliente</span>
        </q-td>
      </template>

      <template v-slot:body-cell-acciones="props">
        <q-td :props="props" style="white-space: nowrap">
          <q-btn
            flat round dense size="sm" color="grey-8" icon="more_vert"
            :loading="imprimiendo === props.row.id"
          >
            <q-menu>
            <q-list dense style="min-width: 220px">
              <!-- La revision de la canasta, de un lado al otro. -->
              <template v-if="can('facturacionAprobarCarga') && props.row.carga_estado !== 'NO_APLICA'">
                <q-item v-if="puedeRevisar(props.row)" clickable v-close-popup @click="marcarCarga(props.row, true)">
                  <q-item-section avatar><q-icon name="done_all" color="green-8"/></q-item-section>
                  <q-item-section>
                    Marcar carga revisada
                    <q-item-label caption>Pasa de "{{ chipCarga(props.row.carga_estado).texto }}" a revisada</q-item-label>
                  </q-item-section>
                </q-item>
                <!-- Lo observado no se desmarca desde aca: se perderia la nota del caminero. -->
                <q-item v-else-if="props.row.carga_estado === 'VERIFICADA'" clickable v-close-popup @click="marcarCarga(props.row, false)">
                  <q-item-section avatar><q-icon name="undo" color="orange-8"/></q-item-section>
                  <q-item-section>
                    Volver a sin revisar
                    <q-item-label caption>Quita la revisión de la canasta</q-item-label>
                  </q-item-section>
                </q-item>
              </template>

              <q-separator/>

              <!-- Una sola opcion: el tipo de comprobante dice si sale factura o
                   voucher. Lo anulado ya no vale y no se imprime. -->
              <q-item
                clickable v-close-popup
                :disable="props.row.estado === 'ANULADO'"
                @click="imprimir(props.row, documentoDe(props.row))"
              >
                <q-item-section avatar>
                  <q-icon :name="documentoDe(props.row) === 'factura' ? 'verified' : 'receipt'"
                          :color="documentoDe(props.row) === 'factura' ? 'green-7' : 'blue-grey-7'"/>
                </q-item-section>
                <q-item-section>
                  Imprimir
                  <q-item-label caption>
                    {{ props.row.estado === 'ANULADO'
                      ? 'Anulado: no se imprime'
                      : (documentoDe(props.row) === 'factura' ? 'Factura' : 'Voucher') }}
                  </q-item-label>
                </q-item-section>
              </q-item>

              <q-separator/>

              <!-- Lo mismo que se imprime, pero guardado en un archivo. -->
              <q-item
                clickable v-close-popup
                :disable="props.row.estado === 'ANULADO'"
                @click="descargarPdf(props.row)"
              >
                <q-item-section avatar><q-icon name="picture_as_pdf" color="red-7"/></q-item-section>
                <q-item-section>
                  Descargar PDF
                  <q-item-label caption>
                    {{ props.row.estado === 'ANULADO'
                      ? 'Anulado: no se imprime'
                      : (props.row.tipo_comprobante === 'FACTURA' ? 'Factura' : 'Voucher') + ' en archivo' }}
                  </q-item-label>
                </q-item-section>
              </q-item>

              <q-item clickable v-close-popup @click="descargarExcel(props.row)">
                <q-item-section avatar><q-icon name="grid_on" color="green-8"/></q-item-section>
                <q-item-section>
                  Descargar Excel
                  <q-item-label caption>Detalle con lo pedido y lo entregado</q-item-label>
                </q-item-section>
              </q-item>

              <q-separator/>

              <q-item
                v-if="props.row.tipo_comprobante === 'FACTURA' && props.row.estado_siat === 'ERROR'"
                clickable v-close-popup @click="reenviarSiat(props.row)"
              >
                <q-item-section avatar><q-icon name="cloud_upload" color="orange-8"/></q-item-section>
                <q-item-section>
                  Reenviar al SIAT
                  <q-item-label caption>Corrige el envío fiscal pendiente</q-item-label>
                </q-item-section>
              </q-item>

              <q-item
                clickable v-close-popup
                :disable="props.row.tipo_comprobante !== 'FACTURA' || !props.row.cuf || props.row.estado === 'ANULADO'"
                @click="abrirEnImpuestos(props.row)"
              >
                <q-item-section avatar><q-icon name="account_balance" color="deep-orange-7"/></q-item-section>
                <q-item-section>
                  Imprimir de Impuestos
                  <q-item-label v-if="!props.row.cuf" caption>
                    La factura todavía no tiene CUF
                  </q-item-label>
                  <q-item-label v-else-if="props.row.estado === 'ANULADO'" caption>
                    Anulada: no se imprime
                  </q-item-label>
                </q-item-section>
              </q-item>

              <!-- Un comprobante emitido no se corrige: se anula y el pedido
                   vuelve a abrirse con todo cargado para emitir uno nuevo. -->
              <template v-if="can('facturacionAnular') && props.row.estado !== 'ANULADO'">
                <q-separator/>
                <q-item clickable v-close-popup :disable="!props.row.pedido_nro" @click="pedirEdicion(props.row)">
                  <q-item-section avatar><q-icon name="edit" color="deep-orange-7"/></q-item-section>
                  <q-item-section>
                    Editar
                    <q-item-label caption>
                      {{ props.row.pedido_nro
                        ? 'Anula este y crea uno nuevo con los cambios'
                        : 'Venta directa: anular y registrar otra' }}
                    </q-item-label>
                  </q-item-section>
                </q-item>
              </template>

              <template v-if="can('facturacionAnular') && puedeAnular(props.row)">
                <q-separator/>
                <q-item clickable v-close-popup @click="pedirAnulacion(props.row)">
                  <q-item-section avatar><q-icon name="block" color="negative"/></q-item-section>
                  <q-item-section>
                    {{ props.row.estado === 'ANULADO' ? 'Anular en Impuestos' : 'Anular' }}
                    <q-item-label caption>
                      {{ props.row.estado === 'ANULADO' ? 'Completa la anulación pendiente en el SIAT' : 'Anula en el SIAT y devuelve el stock' }}
                    </q-item-label>
                  </q-item-section>
                </q-item>
              </template>
            </q-list>
            </q-menu>
          </q-btn>
        </q-td>
      </template>

      <template v-slot:no-data>
        <div class="full-width row flex-center q-pa-md text-grey-7">
          <q-icon name="request_quote" size="20px" class="q-mr-sm"/>
          Todavía no hay ventas registradas en este rango
        </div>
      </template>
    </q-table>

    <!-- Detalle -->
    <q-dialog v-model="dialogDetalle">
      <q-card style="min-width: 340px; max-width: 700px; width: 100%">
        <q-card-section class="bg-primary text-white q-py-sm">
          <div class="text-subtitle1 text-weight-bold">
            {{ sel.tipo_comprobante === 'FACTURA' ? 'Factura' : 'Venta' }} #{{ sel.id }}
          </div>
          <div class="text-caption">
            {{ sel.nombre || 'Sin cliente' }} · {{ fechaHora(sel) }}
          </div>
          <div class="text-caption">
            <q-icon name="badge"/>
            {{ sel.vendedor ? nombreVendedor(sel.vendedor) : 'Sin vendedor' }}
            <span v-if="sel.vendedor_ci">· CI {{ sel.vendedor_ci }}</span>
          </div>
          <div class="text-caption">
            <template v-if="sel.pedido_nro">
              <q-icon name="assignment"/>
              Pedido #{{ sel.pedido_nro }} · {{ nombreTipoPedido(sel.pedido_tipo) }}
            </template>
            <template v-else><q-icon name="point_of_sale"/> Venta directa, sin pedido</template>
          </div>
        </q-card-section>

        <q-card-section v-if="sel.estado === 'ANULADO'" class="q-py-sm">
          <q-banner dense rounded class="bg-red-1 text-red-9">
            <template v-slot:avatar><q-icon name="block"/></template>
            Anulada: {{ sel.motivo_anulacion }}
          </q-banner>
        </q-card-section>

        <q-card-section v-if="sel.estado_siat === 'ERROR'" class="q-py-sm">
          <q-banner dense rounded class="bg-red-1 text-red-9">
            <template v-slot:avatar><q-icon name="cloud_off"/></template>
            <div class="text-weight-bold">Factura no enviada al SIAT</div>
            <div>{{ sel.mensaje_siat || 'Impuestos no informó el motivo' }}</div>
          </q-banner>
        </q-card-section>

        <!-- Lo que salio distinto de lo que pedia el pedido. -->
        <q-card-section v-if="cambiadas(sel).length" class="q-py-sm">
          <q-banner dense rounded class="bg-deep-orange-1 text-deep-orange-10">
            <template v-slot:avatar><q-icon name="published_with_changes"/></template>
            <div class="text-weight-bold">
              {{ cambiadas(sel).length }}
              {{ cambiadas(sel).length === 1 ? 'producto salió' : 'productos salieron' }}
              con otra cantidad
            </div>
            <div v-for="d in cambiadas(sel)" :key="'cambio-' + d.id" class="text-caption">
              {{ d.nombre }}: pedido {{ cant(d.cantidad_pedida, d.unidad) }} ·
              entregado {{ cant(d.cantidad, d.unidad) }} ({{ diferencia(d) }})
            </div>
          </q-banner>
        </q-card-section>

        <q-card-section class="q-pa-none">
          <q-markup-table dense flat wrap-cells>
            <thead>
            <tr class="bg-grey-2">
              <th class="text-left">Código</th>
              <th class="text-left">Producto</th>
              <th class="text-right">Pedido</th>
              <th class="text-right">Entregado</th>
              <th class="text-right">Peso kg</th>
              <th class="text-right">Precio</th>
              <th class="text-right">Subtotal</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="d in (sel.detalles || [])" :key="d.id" :class="cambioCantidad(d) ? 'bg-deep-orange-1' : ''">
              <td class="text-left">{{ d.cod_prod }}</td>
              <td class="text-left">{{ d.nombre }}</td>
              <!-- Lo pedido al lado de lo entregado: la diferencia es lo que el
                   cajero cambio al cobrar. -->
              <td class="text-right">
                {{ d.cantidad_pedida === null ? '—' : cant(d.cantidad_pedida, d.unidad) }}
              </td>
              <td class="text-right" :class="cambioCantidad(d) ? 'text-deep-orange text-weight-bold' : ''">
                {{ cant(d.cantidad, d.unidad) }}
                <div v-if="cambioCantidad(d)" class="text-caption">{{ diferencia(d) }}</div>
              </td>
              <!-- Lo que va a granel se cobra por este peso, no por la cantidad. -->
              <td class="text-right">{{ Number(d.peso) > 0 ? Number(d.peso).toFixed(3) : '—' }}</td>
              <td class="text-right">{{ money(d.precio) }}</td>
              <td class="text-right text-weight-bold">{{ money(d.subtotal) }}</td>
            </tr>
            </tbody>
          </q-markup-table>
        </q-card-section>

        <q-separator/>
        <q-card-actions align="between" class="q-px-md">
          <div>
            <div class="text-caption text-grey-7">
              Subtotal Bs {{ money(sel.subtotal) }} · Descuento Bs {{ money(sel.descuento) }}
            </div>
            <div class="text-weight-bold">Total: Bs {{ money(sel.total) }}</div>
          </div>
          <q-btn flat no-caps label="Cerrar" color="primary" v-close-popup/>
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Anulación -->
    <q-dialog v-model="dialogAnular">
      <q-card style="min-width: 340px">
        <q-card-section class="q-py-sm">
          <div class="text-subtitle1 text-weight-bold">
            {{ editando ? 'Editar' : (sel.estado === 'ANULADO' ? 'Anular en Impuestos' : 'Anular') }} #{{ sel.id }}
          </div>
          <div class="text-caption text-grey-7">
            <template v-if="editando">
              Este comprobante se anula y se abre el pedido #{{ sel.pedido_nro }} con todo lo cobrado
              cargado, para corregirlo y emitir uno nuevo.
            </template>
            <template v-else>
              {{ sel.estado === 'ANULADO'
                ? 'La anulación local ya existe; ahora se enviará al SIAT sin volver a mover el stock.'
                : 'Se anulará en el SIAT y quedará registrada como anulada en Sofia.' }}
            </template>
          </div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <div class="text-subtitle2 q-mb-sm">Tipo de anulación</div>
          <q-option-group
            v-model="codigoMotivoAnulacion"
            :options="motivosAnulacion"
            type="radio"
            color="negative"
          />
        </q-card-section>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense no-caps label="Cancelar" v-close-popup/>
          <q-btn
            :color="editando ? 'deep-orange-7' : 'negative'" dense unelevated no-caps
            :label="editando ? 'Anular y editar' : 'Anular'"
            :disable="!codigoMotivoAnulacion" :loading="anulando" @click="anular"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

  </q-page>
</template>

<script>
import { date } from 'quasar'
import xlsx from 'json-as-xlsx'
import { imprimirPdfDirecto } from 'src/utils/impresion.js'

function filtrosPorDefecto () {
  const hoy = date.formatDate(new Date(), 'YYYY-MM-DD')
  return { desde: hoy, hasta: hoy, buscar: '', tipo: null, estado: null, camion: null }
}

/**
 * La fecha viaja como ISO ('2026-08-25T04:00:00.000000Z') y asi no se lee.
 * Se corta la parte del dia antes de formatear: interpretarla como fecha con
 * hora la correria un dia segun la zona horaria del navegador.
 */
function fechaCorta (valor) {
  const dia = String(valor || '').substr(0, 10)
  return dia ? date.formatDate(dia + 'T00:00:00', 'DD/MM/YYYY') : '—'
}

export default {
  name: 'FacturasLista',
  data () {
    return {
      facturas: [],
      sel: {},
      dialogDetalle: false,
      dialogAnular: false,
      codigoMotivoAnulacion: null,
      motivosAnulacion: [
        { label: '1 - Factura mal emitida', value: 1 },
        { label: '2 - Datos de emisión incorrectos', value: 2 },
        { label: '3 - Factura o nota devuelta', value: 3 },
        { label: '4 - Sustitución de factura emitida en contingencia', value: 4 }
      ],
      anulando: false,
      // El dialogo de anulacion tambien sirve para Editar: anula y abre el pedido.
      editando: false,
      imprimiendo: null,
      exportando: false,
      camiones: [],
      // Como viene la verificacion de la carga del camion filtrado; null
      // mientras no se este mirando un camion de un solo dia.
      carga: null,
      aprobandoCarga: false,
      // Id del comprobante cuya canasta se esta marcando desde la grilla.
      marcandoCarga: null,
      loading: false,
      // En el mostrador 'VENTA' es el voucher: la venta que no se entrego
      // como factura.
      tiposComprobante: [
        { label: 'Todos', valor: null, clave: 'TODOS', icono: 'list', color: 'primary' },
        { label: 'Facturas', valor: 'FACTURA', clave: 'FACTURA', icono: 'verified', color: 'green-7' },
        { label: 'Vouchers', valor: 'VENTA', clave: 'VENTA', icono: 'receipt', color: 'blue-grey-6' }
      ],
      // Cuantos comprobantes de cada tipo hay con los filtros puestos; el
      // backend los cuenta ignorando el chip elegido.
      conteos: { TODOS: 0, FACTURA: 0, VENTA: 0 },
      filtros: filtrosPorDefecto(),
      pagination: { page: 1, rowsPerPage: 20, rowsNumber: 0 },
      columns: [
        // Sin titulo: la columna es solo el boton de los tres puntos y la
        // palabra 'Opciones' la hacia mas ancha que su contenido.
        { name: 'acciones', label: '', field: 'acciones', align: 'center' },
        { name: 'id', label: 'Nº', field: 'id', align: 'left' },
        { name: 'tipo_comprobante', label: 'Tipo', field: 'tipo_comprobante', align: 'center' },
        // La comanda del pedido que origino el comprobante: es la relacion que
        // ata la venta con el pedido del preventista.
        { name: 'pedido', label: 'Pedido', field: 'pedido_nro', align: 'left' },
        // Fecha y hora van en la misma celda: son un solo dato para el cajero
        // y separadas se comian dos columnas de la grilla.
        { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left' },
        { name: 'nombre', label: 'Cliente', field: 'nombre', align: 'left' },
        { name: 'placa', label: 'Camión', field: 'placa', align: 'left', format: v => v || '—' },
        // El visto bueno del caminero sobre la canasta de este comprobante.
        { name: 'carga', label: 'Carga', field: 'carga_estado', align: 'center' },
        // Si el comprobante llego o no al cliente: lo marca el caminero al
        // entregar, y es lo que caja mira para saber que quedo en la calle.
        { name: 'entrega', label: 'Entrega', field: 'entrega_estado', align: 'center' },
        { name: 'tipo_pago', label: 'Pago', field: 'tipo_pago', align: 'center' },
        { name: 'estado', label: 'Estado', field: 'estado', align: 'center' },
        { name: 'siat', label: 'Estado SIAT', field: 'estado_siat', align: 'left' },
        { name: 'total', label: 'Total Bs.', field: 'total', align: 'right', format: v => Number(v || 0).toFixed(2) }
      ]
    }
  },
  computed: {
    can () {
      return this.$store.getters['login/can']
    },
    pedidosTotal () {
      return this.camiones.reduce((suma, fila) => suma + fila.total, 0)
    },
    facturadosTotal () {
      return this.camiones.reduce((suma, fila) => suma + fila.facturados, 0)
    },
    porcentajeTotal () {
      return this.pedidosTotal ? Math.round((this.facturadosTotal / this.pedidosTotal) * 100) : 0
    }
  },
  created () {
    if (this.$route.query.buscar) {
      this.filtros.buscar = String(this.$route.query.buscar)
    }
    if (this.$route.query.fecha) {
      this.filtros.desde = String(this.$route.query.fecha)
      this.filtros.hasta = String(this.$route.query.fecha)
    }
    this.onRequest({ pagination: this.pagination })
  },
  methods: {
    money (v) {
      return Number(v || 0).toFixed(2)
    },
    fechaCorta,
    /** En el legado los embutidos van como NORMAL; en pantalla se dicen asi. */
    nombreTipoPedido (tipo) {
      const valor = String(tipo || '').toUpperCase()
      if (!valor) return ''
      return valor === 'NORMAL' ? 'EMBUTIDOS' : valor
    },
    /** La hora del legado viene con segundos; en la grilla sobran. */
    horaCorta (valor) {
      return String(valor || '').substr(0, 5)
    },
    fechaHora (factura) {
      return fechaCorta(factura.fecha) + ' ' + String(factura.hora || '')
    },
    /** Las cantidades a granel llevan decimales; las de unidad, no. */
    cant (valor, unidad) {
      return Number(valor || 0).toFixed(unidad === 'KG' ? 3 : 0)
    },
    // Solo hay cambio cuando la linea vino de un pedido: en la venta directa y
    // en lo que se agrego del catalogo no hay cantidad pedida que comparar.
    cambioCantidad (detalle) {
      return detalle.cantidad_pedida !== null &&
        detalle.cantidad_pedida !== undefined &&
        Number(detalle.cantidad) !== Number(detalle.cantidad_pedida)
    },
    cambiadas (factura) {
      return (factura.detalles || []).filter(this.cambioCantidad)
    },
    diferencia (detalle) {
      const resta = Number(detalle.cantidad) - Number(detalle.cantidad_pedida)
      return (resta > 0 ? '+' : '−') + this.cant(Math.abs(resta), detalle.unidad)
    },
    nombreVendedor (vendedor) {
      return [vendedor.Nombre1, vendedor.Nombre2, vendedor.App1, vendedor.Apm]
        .map(parte => String(parte || '').trim())
        .filter(Boolean)
        .join(' ') || 'Sin vendedor'
    },
    /**
     * Como se pinta la canasta de un comprobante. Verificada es lo unico que
     * deja imprimir; lo demas dice por que todavia no.
     */
    chipEntrega (estado) {
      if (estado === 'ENTREGADO') return { color: 'green-7', icono: 'inventory', texto: 'Entregada' }
      if (estado === 'NO ENTREGADO') return { color: 'red-7', icono: 'block', texto: 'No entregada' }
      if (estado === 'RECHAZADO') return { color: 'deep-orange-9', icono: 'thumb_down', texto: 'Rechazada' }
      if (estado === 'RETORNO PARCIAL') return { color: 'deep-orange-7', icono: 'assignment_return', texto: 'Retorno parcial' }
      return { color: 'blue-grey-5', icono: 'local_shipping', texto: 'En camión' }
    },
    /**
     * Retorno parcial todavia sin aplicar: la entrega sigue colgada del
     * comprobante original. Una vez editado, pasa al comprobante nuevo.
     */
    retornoPendiente (row) {
      return row.entrega_estado === 'RETORNO PARCIAL' && row.estado !== 'ANULADO' &&
        !!row.entrega_retorno && Number(row.entrega_retorno.factura_original) === Number(row.id)
    },
    detalleEntrega (row) {
      if (row.entrega_estado === 'PENDIENTE') {
        return 'Todavía va en el camión: el caminero no la marcó'
      }

      const cuando = row.entrega_fecha
        ? ' el ' + this.verificadoEn(row.entrega_fecha + ' ' + (row.entrega_hora || '00:00:00'))
        : ''

      if (row.entrega_estado === 'ENTREGADO') {
        const como = row.entrega_tipago ? ' · Cobrada por ' + row.entrega_tipago : ''
        const monto = row.entrega_monto != null ? ' · Bs ' + this.money(row.entrega_monto) : ''
        return 'Entregada' + cuando + como + monto
      }

      const motivo = row.entrega_observacion ? ' · ' + row.entrega_observacion : ''
      if (row.entrega_estado === 'RETORNO PARCIAL') {
        const monto = row.entrega_monto != null ? ' · se quedó Bs ' + this.money(row.entrega_monto) : ''
        const aplicado = this.retornoPendiente(row) ? ' · falta editar el comprobante' : ' · ya aplicado'
        return 'Retorno parcial' + cuando + monto + aplicado + motivo
      }
      return (row.entrega_estado === 'RECHAZADO' ? 'Rechazada' : 'No entregada') + cuando + motivo
    },
    chipCarga (estado) {
      if (estado === 'VERIFICADA') return { color: 'green-7', icono: 'verified', texto: 'Verificada' }
      // Observada tambien esta revisada y no frena la impresion, pero se
      // distingue del visto bueno limpio.
      if (estado === 'OBSERVADA') return { color: 'deep-orange-7', icono: 'report_problem', texto: 'Observada' }
      if (estado === 'CAMBIO') return { color: 'deep-orange-7', icono: 'published_with_changes', texto: 'Cambió' }
      return { color: 'orange-8', icono: 'pending', texto: 'Sin revisar' }
    },

    /** Lo que se lee al pasar por encima del chip. */
    detalleCarga (row) {
      if (row.carga_estado === 'OBSERVADA') {
        const quien = row.carga_verificado_por || 'el caminero'
        const cuando = row.carga_verificado_en ? ' el ' + this.verificadoEn(row.carga_verificado_en) : ''
        return 'Observada por ' + quien + cuando + ' · ' + (row.carga_observacion || 'sin motivo')
      }

      if (row.carga_estado === 'VERIFICADA') {
        const quien = row.carga_verificado_por || 'el caminero'
        const cuando = row.carga_verificado_en ? ' el ' + this.verificadoEn(row.carga_verificado_en) : ''
        const nota = row.carga_observacion ? ' · Observación: ' + row.carga_observacion : ''
        return 'Canasta revisada por ' + quien + cuando + nota
      }

      if (row.carga_estado === 'CAMBIO') {
        return 'La canasta se revisó, pero después cambió la venta: el caminero tiene que volver a revisarla'
      }

      if (row.carga_observacion) {
        return 'Observación del caminero: ' + row.carga_observacion
      }

      return 'El caminero todavía no revisó esta canasta; hasta que lo haga no se imprime'
    },

    /** Momento en que el caminero cerro la verificacion de su carga. */
    verificadoEn (valor) {
      return date.formatDate(String(valor).replace(' ', 'T'), 'DD/MM/YYYY HH:mm')
    },
    puedeAnular (row) {
      if (row.estado !== 'ANULADO') return true

      const estadoSiat = String(row.estado_siat || '').toUpperCase()
      return row.tipo_comprobante === 'FACTURA' && !!row.cuf && !estadoSiat.includes('ANUL')
    },
    /** Los chips son excluyentes; 'Todos' es simplemente el filtro vacio. */
    filtrarTipo (valor) {
      if (this.filtros.tipo === valor) return
      this.filtros.tipo = valor
      this.recargar()
    },
    limpiar () {
      this.filtros = filtrosPorDefecto()
      this.recargar()
    },
    recargar () {
      this.pagination.page = 1
      this.onRequest({ pagination: this.pagination })
    },
    onRequest (props) {
      const { page, rowsPerPage } = props.pagination
      this.loading = true

      this.cargarCamiones()
      this.cargarCarga()

      this.$api.get('facturacion', {
        params: Object.assign(this.paramsFiltro(), {
          page,
          perPage: rowsPerPage === 0 ? 200 : rowsPerPage
        })
      }).then(res => {
        this.facturas = res.data.data
        this.conteos = res.data.conteos || { TODOS: 0, FACTURA: 0, VENTA: 0 }
        this.pagination.page = res.data.current_page
        this.pagination.rowsPerPage = rowsPerPage
        this.pagination.rowsNumber = res.data.total
      }).catch(err => {
        this.avisar(err, 'No se pudieron cargar las ventas')
      }).finally(() => {
        this.loading = false
      })
    },

    // 'voucher' o 'factura'.
    imprimir (row, documento) {
      this.imprimiendo = row.id

      return this.$api.get('facturacion/' + row.id + '/' + documento, { responseType: 'blob' })
        .then(res => imprimirPdfDirecto(res.data, documento + '_' + row.id + '.pdf'))
        .catch(err => {
          this.avisar(err, 'No se pudo imprimir el ' + documento)
        })
        .finally(() => { this.imprimiendo = null })
    },

    /** Los mismos filtros que ve el usuario; lo exportado tiene que coincidir. */
    paramsFiltro () {
      return {
        desde: this.filtros.desde || '',
        hasta: this.filtros.hasta || '',
        buscar: this.filtros.buscar || '',
        tipo: this.filtros.tipo || '',
        estado: this.filtros.estado || '',
        camion: this.filtros.camion || ''
      }
    },

    /**
     * Estado de la verificacion del camion filtrado.
     *
     * La carga es de un dia concreto, asi que solo tiene sentido preguntarla
     * cuando se esta mirando un solo dia y un camion de verdad ('SIN' no es un
     * camion, son las ventas de mostrador).
     */
    cargarCarga () {
      const camion = this.filtros.camion
      if (!camion || camion === 'SIN' || !this.filtros.desde ||
        this.filtros.desde !== this.filtros.hasta) {
        this.carga = null
        return
      }

      this.$api.get('facturacion/carga', { params: { fecha: this.filtros.desde, camion } })
        .then(res => { this.carga = res.data })
        .catch(() => { this.carga = null })
    },

    /**
     * Caja aprueba de una vez todas las canastas del camion filtrado, sin
     * esperar al caminero. Lo que el caminero dejo observado no se toca.
     */
    // Sin revisar o cambiada despues de revisarse: lo que todavia frena la
    // impresion y caja puede dar por bueno.
    puedeRevisar (row) {
      return ['PENDIENTE', 'CAMBIO'].includes(row.carga_estado)
    },

    /** Marca (o desmarca) la canasta de un solo comprobante. */
    marcarCarga (row, verificado) {
      this.marcandoCarga = row.id
      this.$api.post('facturacion/' + row.id + '/carga', { verificado })
        .then(res => {
          this.$q.notify({ type: 'positive', position: 'top', message: res.data.message })
          this.onRequest({ pagination: this.pagination })
        })
        .catch(err => { this.avisar(err, 'No se pudo cambiar la carga') })
        .finally(() => { this.marcandoCarga = null })
    },

    aprobarCarga () {
      const { placa, pendientes } = this.carga
      this.$q.dialog({
        title: 'Aprobar camión ' + placa,
        message: 'Se darán por verificadas las ' + pendientes + ' canastas pendientes del ' +
          this.fechaCorta(this.filtros.desde) + ' y ya se podrán imprimir sus comprobantes.',
        cancel: { flat: true, label: 'Cancelar', noCaps: true },
        ok: { color: 'green-8', label: 'Aprobar', noCaps: true, unelevated: true },
        persistent: true
      }).onOk(() => {
        this.aprobandoCarga = true
        this.$api.post('facturacion/carga/aprobar', { fecha: this.filtros.desde, camion: placa })
          .then(res => {
            this.$q.notify({ type: 'positive', position: 'top', message: res.data.message })
            this.recargar()
          })
          .catch(err => { this.avisar(err, 'No se pudo aprobar el camión') })
          .finally(() => { this.aprobandoCarga = false })
      })
    },

    // Los camiones con pedidos enviados en el rango, cada uno con cuanto lleva
    // facturado; se recargan con la lista porque dependen de las fechas.
    cargarCamiones () {
      this.$api.get('facturacion/camiones', { params: this.paramsFiltro() })
        .then(res => {
          this.camiones = res.data.map(fila => {
            const progreso = fila.total ? fila.facturados / fila.total : 0
            return {
              value: fila.placa,
              placa: fila.placa === 'SIN' ? 'Sin camion' : fila.placa,
              color: fila.color,
              total: fila.total,
              facturados: fila.facturados,
              progreso,
              porcentaje: Math.round(progreso * 100),
              completo: fila.total > 0 && fila.facturados === fila.total
            }
          }).sort((uno, otro) => {
            // Lo que falta facturar arriba; completos y "sin camion" al fondo.
            if (uno.value === 'SIN') return 1
            if (otro.value === 'SIN') return -1
            if (uno.completo !== otro.completo) return uno.completo ? 1 : -1
            return uno.placa.localeCompare(otro.placa)
          })
        })
        .catch(() => { this.camiones = [] })
    },
    // Tocar el camion ya filtrado lo suelta y vuelve a mostrar todos.
    alternarCamion (valor) {
      this.filtros.camion = this.filtros.camion === valor ? null : valor
      this.recargar()
    },
    // colorStyle del pedido viene como 'background-color: #RRGGBB'; el texto
    // se pone negro o blanco segun que tan claro sea ese fondo.
    estiloColor (color) {
      const estilo = (color || '').trim()
      const hex = /#([0-9a-f]{6})/i.exec(estilo)
      if (!hex) return 'background-color: #ECEFF1; color: #37474F'
      const valor = parseInt(hex[1], 16)
      const luz = (0.299 * ((valor >> 16) & 255) + 0.587 * ((valor >> 8) & 255) + 0.114 * (valor & 255)) / 255
      return estilo.replace(/;\s*$/, '') + '; color: ' + (luz > 0.6 ? '#212121' : '#FFFFFF')
    },

    /**
     * Descarga un archivo del backend. Los errores llegan como blob, asi que
     * hay que leerlos antes de poder mostrar el motivo.
     */
    bajarArchivo (url, params, nombre, imprimir) {
      this.exportando = true

      return this.$api.get(url, { params, responseType: 'blob' })
        .then(res => {
          if (imprimir) return imprimirPdfDirecto(res.data, nombre)

          const enlace = document.createElement('a')
          enlace.href = window.URL.createObjectURL(res.data)
          enlace.download = nombre
          enlace.click()
          window.URL.revokeObjectURL(enlace.href)
        })
        .catch(async err => {
          let mensaje = 'No se pudo generar el archivo'
          try {
            mensaje = JSON.parse(await err.response.data.text()).message || mensaje
          } catch (e) { /* el error no vino en JSON */ }
          this.$q.notify({ type: 'negative', position: 'top', message: mensaje })
        })
        .finally(() => { this.exportando = false })
    },

    /**
     * Los comprobantes del filtro en un solo PDF.
     *
     * documento: 'voucher', 'factura' o 'todos' (el paquete mezclado, cada
     * venta en el papel con el que se entregó).
     */
    lote (documento, imprimir) {
      const nombre = documento === 'todos' ? 'comprobantes' : documento + 's'

      return this.bajarArchivo(
        'facturacion/lote/' + documento,
        this.paramsFiltro(),
        nombre + '_' + (this.filtros.desde || 'todo') + '.pdf',
        imprimir
      )
    },

    /** contenido: 'ventas' o 'cambios'; formato: 'pdf' o 'excel'. */
    reporte (contenido, formato) {
      return this.bajarArchivo(
        'facturacion/reporte',
        Object.assign({ contenido, formato }, this.paramsFiltro()),
        contenido + '_' + (this.filtros.desde || 'todo') + (formato === 'excel' ? '.xlsx' : '.pdf'),
        false
      )
    },

    /** El mismo documento que se imprime, pero guardado como archivo. */
    descargarPdf (row) {
      const documento = row.tipo_comprobante === 'FACTURA' ? 'factura' : 'voucher'
      this.imprimiendo = row.id

      return this.$api.get('facturacion/' + row.id + '/' + documento, { responseType: 'blob' })
        .then(res => {
          const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
          const enlace = document.createElement('a')
          enlace.href = url
          enlace.download = documento + '_' + row.id + '.pdf'
          enlace.click()
          window.URL.revokeObjectURL(url)
        })
        .catch(err => { this.avisar(err, 'No se pudo descargar el ' + documento) })
        .finally(() => { this.imprimiendo = null })
    },

    /**
     * Detalle de la venta en Excel, con lo pedido al lado de lo entregado para
     * que se vea en la planilla lo que se cambio al cobrar.
     */
    descargarExcel (row) {
      const detalles = row.detalles || []

      // La comanda va en el nombre de la hoja y del archivo: asi la planilla
      // suelta sigue diciendo de que pedido salio.
      const referencia = (row.tipo_comprobante === 'FACTURA' ? 'Factura ' : 'Venta ') + row.id +
        (row.pedido_nro ? ' - Pedido ' + row.pedido_nro : '')

      const hoja = [{
        sheet: referencia.substr(0, 31),
        columns: [
          { label: 'Código', value: 'cod_prod' },
          { label: 'Producto', value: 'nombre' },
          { label: 'Unidad', value: 'unidad' },
          { label: 'Pedido', value: 'pedido' },
          { label: 'Entregado', value: 'entregado' },
          { label: 'Diferencia', value: 'diferencia' },
          { label: 'Peso kg', value: 'peso' },
          { label: 'Precio Bs', value: 'precio' },
          { label: 'Subtotal Bs', value: 'subtotal' }
        ],
        content: detalles.map(d => ({
          cod_prod: d.cod_prod,
          nombre: d.nombre,
          unidad: d.unidad,
          pedido: d.cantidad_pedida === null || d.cantidad_pedida === undefined
            ? '' : Number(d.cantidad_pedida),
          entregado: Number(d.cantidad),
          diferencia: this.cambioCantidad(d)
            ? Number(d.cantidad) - Number(d.cantidad_pedida)
            : '',
          peso: Number(d.peso) > 0 ? Number(d.peso) : '',
          precio: Number(d.precio),
          subtotal: Number(d.subtotal)
        }))
      }]

      xlsx(hoja, {
        fileName: (row.tipo_comprobante === 'FACTURA' ? 'factura_' : 'venta_') + row.id +
          (row.pedido_nro ? '_pedido_' + row.pedido_nro : ''),
        extraLength: 5,
        writeOptions: {}
      })
    },

    /** Abre la consulta publica del SIAT usando el CUF de esta factura. */
    abrirEnImpuestos (row) {
      const ventana = window.open('', '_blank')

      this.$api.get('facturacion/' + row.id + '/url-impuestos')
        .then(res => {
          if (ventana) {
            ventana.location.href = res.data.url
          } else {
            window.location.href = res.data.url
          }
        })
        .catch(err => {
          if (ventana) ventana.close()
          this.avisar(err, 'No se pudo abrir la factura en Impuestos')
        })
    },

    reenviarSiat (row) {
      this.$q.dialog({
        title: 'Reenviar al SIAT',
        message: 'Se volverá a generar y enviar la factura fiscal #' + row.id + '. ¿Continuar?',
        cancel: true,
        persistent: true
      }).onOk(() => {
        this.$api.post('impuestos/facturas/' + row.id + '/reenviar')
          .then(res => {
            this.$q.notify({
              type: 'positive', position: 'top', timeout: 8000,
              message: res.data.message
            })
            this.onRequest({ pagination: this.pagination })
          })
          .catch(err => { this.avisar(err, 'No se pudo reenviar al SIAT') })
      })
    },


    verDetalle (row) {
      this.sel = row
      this.dialogDetalle = true

      // El listado ya trae el detalle, pero se refresca por si cambió.
      this.$api.get('facturacion/' + row.id)
        .then(res => { this.sel = res.data })
        .catch(() => {})
    },

    /** 'factura' o 'voucher', segun como se emitio el comprobante. */
    documentoDe (row) {
      return row.tipo_comprobante === 'FACTURA' ? 'factura' : 'voucher'
    },

    pedirAnulacion (row) {
      this.sel = row
      this.editando = false
      this.codigoMotivoAnulacion = null
      this.dialogAnular = true
    },

    /**
     * Editar es anular y volver a emitir: se anula este comprobante y se abre
     * su pedido, que llega con lo cobrado ya cargado (cantidades, pesos y
     * precios) para corregirlo y emitir uno nuevo.
     */
    pedirEdicion (row) {
      this.sel = row
      this.editando = true
      // Con retorno parcial el motivo es la nota devuelta; si no, mal emitida.
      this.codigoMotivoAnulacion = this.retornoPendiente(row) ? 3 : 1
      this.dialogAnular = true
    },

    anular () {
      this.anulando = true

      this.$api.put('facturacion/' + this.sel.id + '/anular', {
        codigo_motivo: this.codigoMotivoAnulacion
      })
        .then(res => {
          this.dialogAnular = false
          if (this.editando && this.sel.pedido_nro) {
            this.$q.notify({
              type: 'positive', position: 'top',
              message: 'Comprobante #' + this.sel.id + ' anulado: corregí el pedido y emití el nuevo'
            })
            this.$router.push({
              path: '/facturacion/pedidos/' + this.sel.pedido_nro + '/' + this.sel.pedido_tipo,
              query: this.sel.placa ? { camion: this.sel.placa } : {}
            })
            return
          }
          this.$q.notify({
            message: res.data.message,
            color: 'positive',
            icon: 'check_circle',
            position: 'top'
          })
          this.onRequest({ pagination: this.pagination })
        })
        .catch(err => { this.avisar(err, 'No se pudo anular') })
        .finally(() => { this.anulando = false })
    },

    async avisar (err, porDefecto) {
      let mensaje = err.response?.data?.message

      // En las descargas la respuesta viaja como Blob: el motivo real del
      // error hay que leerlo del propio Blob.
      if (err.response?.data instanceof Blob) {
        try {
          mensaje = JSON.parse(await err.response.data.text()).message
        } catch (e) {
          mensaje = null
        }
      }

      this.$q.notify({
        message: mensaje || porDefecto,
        color: 'negative',
        icon: 'error',
        position: 'top'
      })
    }
  }
}
</script>

<style lang="scss" scoped>
/*
  El cajero mira esta grilla todo el dia y necesita ver muchas ventas de una:
  se le quita el aire a las celdas y se achica la letra para que entren mas
  filas y columnas sin tener que desplazar la pantalla a lo ancho.
*/
/* La observacion del caminero se recorta a dos lineas: es texto libre y sin
   tope estiraria la fila. El tooltip de la celda muestra el resto. */
.celda-observacion {
  max-width: 190px;
  margin: 2px auto 0;
  line-height: 1.15;
  white-space: normal;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

/* Mismas filas planas de 22px que en pedidos por facturar. */
.camion-caja {
  border: 1px solid #e0e0e0;
  overflow: hidden;
}
.camion-titulo {
  height: 20px;
  font-size: 11px;
}
.camion-fila {
  height: 22px;
  padding: 0 4px;
  cursor: pointer;
  border-top: 1px solid #e0e0e0;
}
.camion-placa {
  width: 88px;
  height: 17px;
  line-height: 17px;
  padding: 0 4px;
  border-radius: 3px;
  font-size: 11px;
  font-weight: 600;
}
.camion-porcentaje {
  font-size: 10px;
  font-weight: 700;
  line-height: 1;
}
.camion-conteo {
  width: 42px;
  text-align: right;
  font-size: 11px;
  font-weight: 700;
}

.tabla-compacta {
  font-size: 12px;

  :deep(th),
  :deep(td) {
    padding: 2px 6px;
  }

  :deep(thead th) {
    font-size: 11px;
    font-weight: 600;
  }

  /* Los chips de tipo, estado y carga son la mayor parte del ancho: sin esto
     cada uno se lleva el espacio de una columna de texto. */
  :deep(.q-chip),
  :deep(.q-badge) {
    font-size: 10px;
    padding: 2px 5px;
  }

  :deep(.q-chip .q-icon) {
    font-size: 13px;
  }

  /* El caption del cliente y del pedido va como segunda linea; achicarlo evita
     que la fila crezca de alto por esa linea. */
  :deep(.text-caption) {
    font-size: 10px;
    line-height: 1.2;
  }
}
</style>
