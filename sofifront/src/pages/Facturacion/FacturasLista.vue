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
            <q-item-label header class="q-py-xs">Comprobantes del filtro</q-item-label>

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
        <!-- El camion sale del pedido que origino cada comprobante. -->
        <div class="col-6 col-md-2">
          <q-select
            v-model="filtros.camion" dense outlined clearable emit-value map-options
            label="Camión" :options="camiones" @update:model-value="recargar"
          >
            <template v-slot:prepend><q-icon name="local_shipping"/></template>
          </q-select>
        </div>
        <div class="col-6 col-md-2">
          <q-select
            v-model="filtros.estado" dense outlined clearable
            label="Estado" :options="['ACTIVO', 'ANULADO']"
          />
        </div>
        <div class="col-12 col-md row items-center q-gutter-sm">
          <q-btn :loading="loading" color="primary" icon="search" no-caps label="Buscar" @click="recargar"/>
          <q-btn flat color="grey-7" icon="layers_clear" no-caps label="Limpiar" @click="limpiar"/>
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
          <span v-if="carga.con_observacion" class="text-weight-bold">
            · {{ carga.con_observacion }} canastas con observación
          </span>
        </template>
        <template v-else>
          El camión {{ carga.placa }} todavía no verificó su carga
          ({{ carga.verificados }}/{{ carga.comprobantes }} canastas): sus facturas y vouchers
          no se pueden imprimir hasta que el caminero termine de revisarla.
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
          <q-chip
            v-if="props.value !== 'NO_APLICA'"
            dense square
            :color="chipCarga(props.value).color" text-color="white"
            :icon="chipCarga(props.value).icono" :label="chipCarga(props.value).texto"
          >
            <q-tooltip>{{ detalleCarga(props.row) }}</q-tooltip>
          </q-chip>
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
              <q-item clickable v-close-popup @click="verDetalle(props.row)">
                <q-item-section avatar><q-icon name="visibility" color="primary"/></q-item-section>
                <q-item-section>
                  Ver detalle
                  <q-item-label caption>
                    {{ fechaCorta(props.row.fecha) }} {{ props.row.hora }}
                  </q-item-label>
                </q-item-section>
              </q-item>

              <!-- Atajo a lo que cambio respecto del pedido; abre el mismo
                   detalle, donde los cambios salen resaltados. -->
              <q-item
                clickable v-close-popup
                :disable="!cambiadas(props.row).length"
                @click="verDetalle(props.row)"
              >
                <q-item-section avatar>
                  <q-icon name="published_with_changes" color="deep-orange"/>
                </q-item-section>
                <q-item-section>
                  Cambios del pedido
                  <q-item-label caption>
                    {{ cambiadas(props.row).length
                      ? cambiadas(props.row).length + ' producto(s) con otra cantidad'
                      : 'Salió igual a lo pedido' }}
                  </q-item-label>
                </q-item-section>
              </q-item>

              <q-separator/>

              <q-item clickable v-close-popup @click="imprimir(props.row, 'voucher')">
                <q-item-section avatar><q-icon name="receipt" color="blue-grey-7"/></q-item-section>
                <q-item-section>Imprimir voucher</q-item-section>
              </q-item>

              <!-- Solo las que se entregaron como factura tienen factura. -->
              <q-item
                clickable v-close-popup
                :disable="props.row.tipo_comprobante !== 'FACTURA'"
                @click="imprimir(props.row, 'factura')"
              >
                <q-item-section avatar><q-icon name="verified" color="green-7"/></q-item-section>
                <q-item-section>
                  Imprimir factura
                  <q-item-label v-if="props.row.tipo_comprobante !== 'FACTURA'" caption>
                    Se entregó como voucher
                  </q-item-label>
                </q-item-section>
              </q-item>

              <q-separator/>

              <!-- Lo mismo que se imprime, pero guardado en un archivo. -->
              <q-item clickable v-close-popup @click="descargarPdf(props.row)">
                <q-item-section avatar><q-icon name="picture_as_pdf" color="red-7"/></q-item-section>
                <q-item-section>
                  Descargar PDF
                  <q-item-label caption>
                    {{ props.row.tipo_comprobante === 'FACTURA' ? 'Factura' : 'Voucher' }} en archivo
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
                :disable="props.row.tipo_comprobante !== 'FACTURA' || !props.row.cuf"
                @click="abrirEnImpuestos(props.row)"
              >
                <q-item-section avatar><q-icon name="account_balance" color="deep-orange-7"/></q-item-section>
                <q-item-section>
                  Imprimir de Impuestos
                  <q-item-label v-if="!props.row.cuf" caption>
                    La factura todavía no tiene CUF
                  </q-item-label>
                </q-item-section>
              </q-item>

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
            {{ sel.estado === 'ANULADO' ? 'Anular en Impuestos' : 'Anular' }} #{{ sel.id }}
          </div>
          <div class="text-caption text-grey-7">
            {{ sel.estado === 'ANULADO'
              ? 'La anulación local ya existe; ahora se enviará al SIAT sin volver a mover el stock.'
              : 'Se anulará en el SIAT y quedará registrada como anulada en Sofia.' }}
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
            color="negative" dense unelevated no-caps label="Anular"
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
      imprimiendo: null,
      exportando: false,
      camiones: [],
      // Como viene la verificacion de la carga del camion filtrado; null
      // mientras no se este mirando un camion de un solo dia.
      carga: null,
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
    chipCarga (estado) {
      if (estado === 'VERIFICADA') return { color: 'green-7', icono: 'verified', texto: 'Verificada' }
      if (estado === 'CAMBIO') return { color: 'deep-orange-7', icono: 'published_with_changes', texto: 'Cambió' }
      return { color: 'orange-8', icono: 'pending', texto: 'Sin revisar' }
    },

    /** Lo que se lee al pasar por encima del chip. */
    detalleCarga (row) {
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

    // Solo se ofrecen los camiones que de verdad tienen comprobantes en el
    // rango; se recargan con la lista porque dependen de las fechas.
    cargarCamiones () {
      this.$api.get('facturacion/camiones', { params: this.paramsFiltro() })
        .then(res => {
          this.camiones = res.data
            .map(c => ({ label: c.placa + ' (' + c.pedidos + ')', value: c.placa }))
            .concat([{ label: 'Sin camión', value: 'SIN' }])
        })
        .catch(() => { this.camiones = [] })
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

    pedirAnulacion (row) {
      this.sel = row
      this.codigoMotivoAnulacion = null
      this.dialogAnular = true
    },
    anular () {
      this.anulando = true

      this.$api.put('facturacion/' + this.sel.id + '/anular', {
        codigo_motivo: this.codigoMotivoAnulacion
      })
        .then(res => {
          this.$q.notify({
            message: res.data.message,
            color: 'positive',
            icon: 'check_circle',
            position: 'top'
          })
          this.dialogAnular = false
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
