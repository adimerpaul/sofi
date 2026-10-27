<template>
  <q-page class="q-pa-xs">
    <!-- Sin titulo ni tarjeta alrededor: en el celular cada linea que no sea
         pedido es una linea menos de pedidos en pantalla. -->
    <div class="row q-col-gutter-xs items-center q-mb-xs filtros" @keyup.enter="cargar">
      <div class="col-6 col-md-2">
        <q-input v-model="fecha" type="date" dense outlined/>
      </div>
      <div class="col-6 col-md-2">
        <!-- Tipos de Productos > Tipo: filtra los pedidos que traen algun
             producto de ese tipo. La venta sigue siendo el pedido entero. -->
        <q-select v-model="tipo" dense outlined emit-value map-options :options="tipos">
          <template v-slot:option="scope">
            <q-item v-bind="scope.itemProps" dense>
              <q-item-section>
                <q-item-label>
                  <q-badge v-if="scope.opt.color" :color="scope.opt.color" class="q-mr-xs">&nbsp;</q-badge>
                  {{ scope.opt.label }}
                </q-item-label>
              </q-item-section>
            </q-item>
          </template>
        </q-select>
      </div>
      <div class="col">
        <q-input v-model.trim="buscar" dense outlined clearable debounce="400" placeholder="Pedido o cliente"/>
      </div>
      <div class="col-auto">
        <q-btn color="primary" unelevated dense padding="6px 10px" icon="search" :loading="cargando" @click="cargar">
          <q-tooltip>Buscar</q-tooltip>
        </q-btn>
      </div>
      <!-- Cabeceras (cliente y Nro de pedido) del tipo elegido, en carta,
           para imprimir y pegar. -->
      <div class="col-auto">
        <q-btn
          unelevated dense padding="6px 10px" color="deep-purple-7" icon="print" no-caps label="Imprimir"
          type="a" target="_blank" :href="urlApi + 'reportePedidoTipo/' + fecha + '/' + tipo" :disable="!fecha"
        >
          <q-tooltip>Cabeceras de los pedidos {{ (tipos.find(t => t.value === tipo) || {}).label }} del día (PDF carta)</q-tooltip>
        </q-btn>
      </div>
      <!-- Todos los tipos del dia en un solo libro: resumen y una hoja por
           tipo, sin importar el tipo elegido en el filtro. -->
      <div class="col-auto">
        <q-btn
          unelevated dense padding="6px 10px" color="green-8" icon="grid_on" no-caps label="Excel"
          :loading="exportando" :disable="!fecha" @click="descargarExcel"
        >
          <q-tooltip>Todos los pedidos del día en Excel (pollo, embutidos, cerdo, res, podium y huevo)</q-tooltip>
        </q-btn>
      </div>
      <div class="col-auto">
        <q-btn flat dense padding="6px 8px" color="primary" icon="request_quote" to="/facturacion">
          <q-tooltip>Ver facturación</q-tooltip>
        </q-btn>
      </div>
    </div>

    <!-- Una linea por camion con lo que ya lleva cobrado: en el celular se ve
         de un golpe cual falta llenar, y tocando se filtra a ese camion. -->
    <q-card v-if="!cargando && camiones.length" flat bordered class="q-mb-xs rounded-borders">
      <div class="row items-center no-wrap q-px-xs bg-grey-3 camion-titulo">
        <div class="text-weight-bold text-grey-8">CARGA POR CAMION</div>
        <q-space/>
        <div class="text-weight-bolder" :class="facturadosTotal === pedidosContables.length ? 'text-green-9' : 'text-orange-9'">
          {{ facturadosTotal }}/{{ pedidosContables.length }} · {{ porcentajeTotal }}%
          <span v-if="ocultosTotal" class="text-grey-7 text-weight-medium">· {{ ocultosTotal }} oculto{{ ocultosTotal > 1 ? 's' : '' }}</span>
        </div>
        <q-btn v-if="camion" flat dense no-caps size="sm" padding="0 4px" class="q-ml-xs" color="primary"
               icon="clear" label="Todos" @click="camion = null"/>
      </div>
      <!-- Filas planas de 22px: con q-item el padding propio de Quasar hacia
           que nueve camiones empujaran los pedidos fuera de la pantalla. -->
      <div
        v-for="opcion in camiones" :key="opcion.value"
        class="row items-center no-wrap camion-fila"
        :class="camion === opcion.value ? 'bg-blue-2' : ''"
        @click="alternarCamion(opcion.value)"
      >
        <div class="camion-placa ellipsis" :style="estiloColor(opcion.color)">{{ opcion.placa }}</div>
        <!-- Numero de salida del camion ese dia: el mismo que se imprime en el comprobante. -->
        <div class="camion-nro">
          <template v-if="opcion.nro">N° {{ opcion.nro }}</template>
        </div>
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
    </q-card>

    <div class="row q-col-gutter-xs">
      <div v-if="cargando" class="col-12 flex flex-center q-pa-lg">
        <q-spinner color="primary" size="42px"/>
      </div>

      <div v-else-if="!pedidosFiltrados.length" class="col-12">
        <q-card flat bordered class="text-center text-grey-7 q-pa-md">
          <q-icon name="assignment" size="36px" class="q-mb-sm"/>
          <div v-if="pedidos.length">Ningun pedido de esta fecha va en ese camion</div>
          <div v-else>No hay pedidos para esta fecha</div>
        </q-card>
      </div>

      <div v-for="pedido in pedidosFiltrados" :key="pedido.nro_pedido + '-' + pedido.tipo" class="col-12 col-sm-6 col-lg-4">
        <!-- El pedido ya cobrado se pinta entero de verde: en el mostrador se
             ve de un vistazo cual falta sin leer badge por badge. -->
        <!-- El oculto (no va a salir) queda gris y apagado, al fondo de la lista. -->
        <q-card flat bordered class="rounded-borders shadow-1" :class="pedido.factura_id ? 'bg-green-2' : pedido.oculto ? 'bg-grey-3 pedido-oculto' : ''">
          <q-card-section class="q-pa-xs">
            <div class="row items-center no-wrap q-mb-xs">
              <q-badge color="primary" class="text-body2 q-pa-xs">#{{ pedido.nro_pedido }}</q-badge>
              <!-- Con varios tipos en la lista, un mismo numero puede salir
                   dos veces (embutidos y podium): se factura cada uno aparte. -->
              <q-badge outline color="grey-8" class="q-ml-xs">{{ nombreTipoPedido(pedido.tipo) }}</q-badge>
              <div class="text-caption q-ml-sm ellipsis" :class="pedido.factura_id ? 'text-green-9' : 'text-grey-7'">
                {{ hora(pedido.fecha) }} · {{ pedido.estado }}
              </div>
              <q-space/>
              <!-- El que vuelve por una anulacion no arranca de cero: al
                   entrar trae cargado lo que ya se habia cobrado. -->
              <!-- Guardado a medias: todavia no es venta ni bajo stock. -->
              <q-badge v-if="pedido.oculto && !pedido.factura_id" color="grey-7">
                <q-icon name="visibility_off" size="14px" class="q-mr-xs"/>OCULTO
              </q-badge>
              <q-badge v-else-if="pedido.borrador" color="amber-9">
                <q-icon name="save" size="14px" class="q-mr-xs"/>GUARDADO
              </q-badge>
              <q-badge v-else-if="pedido.anulada_id" color="blue-8">
                <q-icon name="history" size="14px" class="q-mr-xs"/>RECUPERABLE
              </q-badge>
              <q-badge v-else-if="!pedido.factura_id" color="orange-8">PENDIENTE</q-badge>
              <q-badge v-else color="positive">LISTO</q-badge>
            </div>

            <div class="text-subtitle2 text-weight-bold ellipsis-2-lines q-mt-xs">
              {{ pedido.cliente || 'Sin cliente' }}
            </div>
            <div class="text-caption ellipsis" :class="pedido.factura_id ? 'text-green-9' : 'text-grey-7'">
              NIT {{ pedido.nit || '—' }} · {{ pedido.vendedor || 'Sin preventista' }}
            </div>

            <div class="q-mt-xs">
              <!-- El pedido ya viene marcado por el preventista (tbpedidos.fact):
                   el chip lo dice antes de entrar, y si ya se cobro manda lo que
                   realmente se emitio. -->
              <q-chip
                dense square size="sm" text-color="white"
                :color="esFactura(pedido) ? 'indigo-8' : 'blue-grey-6'"
              >
                {{ esFactura(pedido) ? 'F' : 'R' }}
                <q-tooltip>{{ esFactura(pedido) ? 'Factura' : 'Recibo' }}</q-tooltip>
              </q-chip>
              <!-- Contado o credito, bien a la vista: el credito no se cobra en
                   la puerta y caja tiene que saberlo antes de entrar. -->
              <q-chip
                dense square size="sm" text-color="white" class="text-weight-bold"
                :color="formaPago(pedido).color" :icon="formaPago(pedido).icono"
              >
                {{ formaPago(pedido).texto }}
              </q-chip>
              <q-chip v-if="pedido.placa" dense square size="sm" icon="local_shipping" :style="estiloCamion(pedido)">
                {{ pedido.placa }}<template v-if="pedido.nro_camion"> · N° {{ pedido.nro_camion }}</template>
                <q-tooltip v-if="pedido.nro_camion">Número de salida del camión en el día de entrega</q-tooltip>
              </q-chip>
              <q-chip v-else dense square size="sm" outline color="grey-7" icon="local_shipping">
                Sin camion
              </q-chip>
            </div>

            <!-- Tipos de producto (Productos > Tipo) que trae el pedido, con
                 cuantas lineas de cada uno: separa huevo, PET, embutido... -->
            <div v-if="tiposDelPedido(pedido).length" class="q-mt-xs">
              <q-chip
                v-for="t in tiposDelPedido(pedido)" :key="t.nombre"
                dense square size="sm" text-color="white" :color="t.color"
              >
                {{ t.nombre }} · {{ t.lineas }}
              </q-chip>
            </div>

            <!-- Un pedido mixto (embutidos y huevo...) se cobra en una boleta
                 por tipo, cada una con su numero: desde cualquiera de sus
                 tarjetas se llega a las demas. -->
            <div v-if="pedido.boletas && pedido.boletas.length > 1" class="q-mt-xs rounded-borders boletas">
              <div class="text-caption text-weight-bold text-grey-8 q-px-xs">
                Este pedido va en {{ pedido.boletas.length }} boletas
              </div>
              <div
                v-for="boleta in pedido.boletas" :key="'b-' + boleta.tipo"
                class="row items-center no-wrap q-px-xs boleta-fila"
                :class="boleta.tipo === pedido.tipo ? 'text-weight-bold' : ''"
              >
                <q-icon :name="boleta.factura_id ? 'check_circle' : 'schedule'"
                        :color="boleta.factura_id ? 'positive' : 'orange-8'" size="16px" class="q-mr-xs"/>
                <div class="col ellipsis">
                  {{ nombreTipoPedido(boleta.tipo) }} · Bs {{ money(boleta.total_pedido) }}
                  <span v-if="boleta.factura_id" class="text-green-9">· {{ boleta.comprobante_emitido }} #{{ boleta.factura_id }}</span>
                  <span v-else class="text-orange-9">· pendiente</span>
                </div>
                <span v-if="boleta.tipo === pedido.tipo" class="text-caption text-grey-7 q-ml-xs">esta</span>
                <template v-else-if="boleta.factura_id">
                  <q-btn flat dense round size="sm" color="primary" icon="visibility" @click="verComprobante(boleta)">
                    <q-tooltip>Ver comprobante #{{ boleta.factura_id }}</q-tooltip>
                  </q-btn>
                  <q-btn flat dense round size="sm" color="primary" icon="print"
                         :loading="imprimiendo === boleta.factura_id" @click="imprimir(boleta, 'ambas')">
                    <q-tooltip>Imprimir #{{ boleta.factura_id }} (original y copia)</q-tooltip>
                  </q-btn>
                </template>
                <q-btn v-else flat dense no-caps size="sm" color="positive" icon="edit_note" label="Facturar"
                       @click="revisar({ nro_pedido: pedido.nro_pedido, tipo: boleta.tipo })"/>
              </div>
            </div>

            <!-- Los productos que trae, a la vista sin abrir el pedido. -->
            <div v-if="pedido.items.length" class="q-mt-xs text-caption productos-tarjeta">
              <div v-for="item in pedido.items.slice(0, 4)" :key="'v-' + item.cod_prod" class="ellipsis">
                <span class="text-weight-bold">{{ cantidad(item.cantidad) }}</span> × {{ item.nombre }}
              </div>
              <div v-if="pedido.items.length > 4" class="text-grey-7">y {{ pedido.items.length - 4 }} más…</div>
            </div>

            <q-banner v-if="pedido.detalle_pollo.observaciones.length" dense rounded class="bg-amber-1 text-amber-10 q-mt-xs">
              <template v-slot:avatar><q-icon name="sticky_note_2" color="amber-9"/></template>
              {{ pedido.detalle_pollo.observaciones.join(' · ') }}
            </q-banner>

            <!-- Pollo, cerdo y res: lo que cargo el preventista (cantidades,
                 precios BS/BS2, horario, pago) a la vista, sin abrir el detalle. -->
            <div v-if="resumenDetalle(pedido).length" class="rounded-borders q-mt-xs q-px-xs bg-orange-1 text-caption resumen-detalle">
              <template v-for="(parte, indice) in resumenDetalle(pedido)" :key="'r-' + indice">
                <span v-if="indice" class="text-grey-6"> · </span>
                <span class="text-grey-8">{{ parte.etiqueta }} </span>
                <span class="text-weight-bold">{{ parte.valor }}</span>
              </template>
            </div>

            <div class="row items-end q-mt-xs">
              <div class="col">
                <div class="text-caption" :class="pedido.factura_id ? 'text-green-9' : 'text-grey-7'">{{ pedido.productos }} productos</div>
                <div class="text-h6 text-weight-bolder text-blue-grey-10">
                  Bs {{ money(pedido.total_pedido) }}
                </div>
              </div>
              <div class="col-auto">
                <q-chip v-if="pedido.factura_id" dense square color="green-8" text-color="white">
                  {{ pedido.comprobante_emitido }} #{{ pedido.factura_id }}
                </q-chip>
              </div>
            </div>
          </q-card-section>

          <q-separator/>
          <q-expansion-item
            dense dense-toggle switch-toggle-side
            icon="shopping_basket"
            label="Ver pedido completo"
            header-class="text-weight-medium"
          >
            <q-list dense separator :class="pedido.factura_id ? 'bg-green-1' : 'bg-grey-1'">
              <q-item v-for="item in pedido.items" :key="item.cod_prod" class="q-px-sm">
                <q-item-section>
                  <q-item-label lines="2">{{ item.nombre }}</q-item-label>
                  <q-item-label caption>
                    {{ item.cod_prod }}
                    <q-badge v-if="item.tipo_nombre" :color="item.tipo_color || 'primary'"
                             class="q-ml-xs" :label="item.tipo_nombre"/>
                  </q-item-label>
                </q-item-section>
                <q-item-section side class="text-right">
                  <q-item-label>{{ cantidad(item.cantidad) }} × Bs {{ money(item.precio) }}</q-item-label>
                  <q-item-label caption class="text-weight-bold">Bs {{ money(item.total) }}</q-item-label>
                </q-item-section>
              </q-item>
              <q-item v-for="(item, indice) in pedido.detalle_pollo.productos" :key="'pollo-' + indice" class="q-px-sm bg-orange-1">
                <q-item-section>
                  <q-item-label class="text-weight-bold">{{ item.nombre }}</q-item-label>
                  <q-item-label v-if="item.observacion" caption>{{ item.observacion }}</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-item-label v-if="item.cantidad !== null">{{ cantidad(item.cantidad) }} {{ item.unidad }}</q-item-label>
                  <q-item-label v-if="item.precio" caption>Bs {{ money(item.precio) }}</q-item-label>
                </q-item-section>
              </q-item>
              <q-item v-for="(dato, indice) in pedido.detalle_pollo.datos" :key="'dato-' + indice" class="q-px-sm bg-orange-1">
                <q-item-section>
                  <q-item-label caption>{{ dato.etiqueta }}</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-item-label class="text-weight-medium">{{ dato.valor }}</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-expansion-item>

          <q-separator/>
          <!-- vertical: los botones van uno debajo del otro, no repartidos
               en la misma fila. -->
          <q-card-actions vertical class="q-pa-xs">
          <!-- Mientras el pedido no tenga factura solo se ofrece facturar: el
               comprobante recien aparece cuando existe algo que mostrar. -->
          <!-- Oculto: solo se ofrece restablecerlo, por si se equivocaron. -->
          <template v-if="!pedido.factura_id && pedido.oculto">
            <q-btn
              class="full-width q-py-xs text-weight-bold" outline no-caps color="grey-8" icon="restore" label="Restablecer"
              :loading="ocultando === claveTarjeta(pedido)" @click="cambiarOculto(pedido, false)"
            />
            <div class="text-caption text-grey-7 q-mt-xs">No va a salir: no cuenta en el avance del día</div>
          </template>
          <template v-else-if="!pedido.factura_id">
            <q-btn
              class="full-width q-py-xs text-weight-bold" color="positive" unelevated no-caps
              :icon="pedido.borrador ? 'save' : pedido.anulada_id ? 'history' : 'edit_note'"
              :label="pedido.borrador ? 'Continuar y finalizar' : pedido.anulada_id ? 'Retomar y facturar' : 'Revisar y facturar'"
              @click="revisar(pedido)"
            />
            <!-- Para el que no va a salir porque no hay producto: lo saca del avance. -->
            <q-btn
              flat dense no-caps size="sm" color="grey-7" icon="visibility_off" label="Ocultar (no sale)" class="q-mt-xs"
              :loading="ocultando === claveTarjeta(pedido)" @click="confirmarOcultar(pedido)"
            />
            <div v-if="pedido.borrador" class="text-caption text-amber-10 q-mt-xs">
              Guardado {{ pedido.borrador.guardado }}
              <span v-if="pedido.borrador.usuario">por {{ pedido.borrador.usuario }}</span>
              · Bs {{ money(pedido.borrador.total) }}; falta finalizar
            </div>
            <div v-if="pedido.anulada_id" class="text-caption text-blue-9 q-mt-xs">
              Se anuló la venta #{{ pedido.anulada_id }} (Bs {{ money(pedido.anulada_total) }});
              al entrar vuelve cargada
            </div>
          </template>
          <template v-else>
            <q-btn
              class="full-width q-py-xs text-weight-bold" outline no-caps color="primary" icon="visibility" label="Ver comprobante"
              @click="verComprobante(pedido)"
            />
            <!-- Tocar el boton saca ORIGINAL y COPIA; la flecha deja elegir una sola. -->
            <q-btn-dropdown
              split class="full-width q-mt-xs text-weight-bold" unelevated no-caps color="primary" icon="print"
              :label="'Imprimir ' + (pedido.comprobante_emitido === 'FACTURA' ? 'factura' : 'voucher') + ' (original y copia)'"
              :loading="imprimiendo === pedido.factura_id"
              @click="imprimir(pedido, 'ambas')"
            >
              <q-list dense>
                <q-item clickable v-close-popup @click="imprimir(pedido, 'ambas')">
                  <q-item-section avatar><q-icon name="print" color="primary"/></q-item-section>
                  <q-item-section>Original y copia</q-item-section>
                </q-item>
                <q-item clickable v-close-popup @click="imprimir(pedido, 'ORIGINAL')">
                  <q-item-section avatar><q-icon name="description" color="primary"/></q-item-section>
                  <q-item-section>Solo original</q-item-section>
                </q-item>
                <q-item clickable v-close-popup @click="imprimir(pedido, 'COPIA')">
                  <q-item-section avatar><q-icon name="content_copy" color="grey-7"/></q-item-section>
                  <q-item-section>Solo copia</q-item-section>
                </q-item>
              </q-list>
            </q-btn-dropdown>
          </template>
          </q-card-actions>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script>
import { date } from 'quasar'
import { imprimirPdfDirecto } from 'src/utils/impresion.js'

export default {
  name: 'PedidosFactura',
  data () {
    return {
      fecha: this.$route.query.fecha || date.formatDate(new Date(), 'YYYY-MM-DD'),
      // Id del tipo de producto, o TODOS. Un enlace viejo con NORMAL/POLLO...
      // cae en TODOS.
      tipo: /^\d+$/.test(this.$route.query.tipo || '') ? Number(this.$route.query.tipo) : 'TODOS',
      // El PDF se abre en otra pestana, directo contra la API.
      urlApi: process.env.API,
      camion: this.$route.query.camion || null,
      buscar: '',
      cargando: false,
      consultaActual: 0,
      imprimiendo: null,
      ocultando: null,
      exportando: false,
      pedidos: [],
      tiposProducto: []
    }
  },
  created () {
    this.cargarTipos()
    this.cargar()
  },
  // Fecha y tipo buscan apenas cambian; el texto, 400 ms despues de dejar de
  // escribir (debounce del q-input). Enter y el boton siguen buscando al toque.
  watch: {
    fecha () {
      this.sincronizarUrl()
      this.cargar()
    },
    tipo () {
      this.sincronizarUrl()
      this.cargar()
    },
    buscar () { this.cargar() },
    camion () { this.sincronizarUrl() }
  },
  computed: {
    tipos () {
      return [{ label: 'Todos', value: 'TODOS' }].concat(
        this.tiposProducto.map(t => ({ label: t.nombre, value: t.id, color: t.color }))
      )
    },
    // Las opciones salen de los pedidos ya cargados: solo se listan los
    // camiones que de verdad tienen pedidos ese dia y de ese tipo, cada uno
    // con cuanto lleva cobrado hasta ahora.
    camiones () {
      const filas = new Map()
      this.pedidos.forEach(pedido => {
        const placa = (pedido.placa || '').trim()
        const clave = placa || 'SIN'
        let fila = filas.get(clave)
        if (!fila) {
          fila = { value: clave, placa: placa || 'Sin camion', color: '', nro: '', total: 0, facturados: 0 }
          filas.set(clave, fila)
        }
        if (!fila.nro && pedido.nro_camion) fila.nro = pedido.nro_camion
        if (!fila.color && pedido.placa_color) fila.color = pedido.placa_color
        // El oculto no va a salir: no cuenta, asi el camion puede llegar al 100%.
        if (this.esOculto(pedido)) return
        fila.total += 1
        if (pedido.factura_id) fila.facturados += 1
      })
      return Array.from(filas.values()).map(fila => {
        const progreso = fila.total ? fila.facturados / fila.total : 0
        return {
          ...fila,
          progreso,
          porcentaje: Math.round(progreso * 100),
          completo: fila.total > 0 && fila.facturados === fila.total
        }
      }).sort((uno, otro) => {
        // Lo que falta llenar queda arriba; los ya completos y el "sin camion"
        // se van al fondo, que es lo que ya no hay que mirar.
        if (uno.value === 'SIN') return 1
        if (otro.value === 'SIN') return -1
        if (uno.completo !== otro.completo) return uno.completo ? 1 : -1
        return uno.placa.localeCompare(otro.placa)
      })
    },
    // Lo que cuenta para el avance: todo menos los ocultos.
    pedidosContables () {
      return this.pedidos.filter(pedido => !this.esOculto(pedido))
    },
    ocultosTotal () {
      return this.pedidos.length - this.pedidosContables.length
    },
    facturadosTotal () {
      return this.pedidosContables.filter(pedido => pedido.factura_id).length
    },
    porcentajeTotal () {
      if (!this.pedidosContables.length) return 0
      return Math.round((this.facturadosTotal / this.pedidosContables.length) * 100)
    },
    pedidosFiltrados () {
      // Sin camion elegido (el select va vacio o recien limpiado) se ven todos.
      let lista = this.pedidos
      if (this.camion === 'SIN') lista = lista.filter(pedido => !(pedido.placa || '').trim())
      else if (this.camion) lista = lista.filter(pedido => (pedido.placa || '').trim() === this.camion)
      // Los ocultos al fondo: ya no hay que mirarlos.
      return lista.filter(pedido => !this.esOculto(pedido)).concat(lista.filter(pedido => this.esOculto(pedido)))
    }
  },
  methods: {
    // Uno ya cobrado cuenta siempre, aunque haya quedado marcado.
    esOculto (pedido) {
      return !!pedido.oculto && !pedido.factura_id
    },
    claveTarjeta (pedido) {
      return pedido.nro_pedido + '-' + pedido.tipo
    },
    confirmarOcultar (pedido) {
      this.$q.dialog({
        title: 'Ocultar pedido #' + pedido.nro_pedido,
        message: 'No va a salir (no hay producto). Deja de contar en el avance del día; se puede restablecer después.',
        cancel: true,
        ok: { label: 'Ocultar', color: 'grey-8', unelevated: true }
      }).onOk(() => this.cambiarOculto(pedido, true))
    },
    cambiarOculto (pedido, oculto) {
      this.ocultando = this.claveTarjeta(pedido)
      this.$api.post('facturacion/pedidos/' + pedido.nro_pedido + '/ocultar', {
        pedido_tipo: pedido.tipo,
        fecha: this.fecha,
        oculto
      }).then(() => {
        pedido.oculto = oculto
      }).catch(err => {
        this.$q.notify({
          type: 'negative', position: 'top',
          message: err.response?.data?.message || 'No se pudo ' + (oculto ? 'ocultar' : 'restablecer') + ' el pedido'
        })
      }).finally(() => { this.ocultando = null })
    },
    // Antes de cobrarse manda lo que pidio el cliente (fact = SI); una vez
    // emitido, lo que de verdad salio, que puede no coincidir.
    esFactura (pedido) {
      if (pedido.factura_id) return pedido.comprobante_emitido === 'FACTURA'
      return String(pedido.fact || '').toUpperCase() === 'SI'
    },
    // Igual que F/R: antes de cobrar manda el pedido (CONTADO, CREDITO, PAGO QR);
    // ya emitido, lo que quedo en el comprobante (EFECTIVO, CRÉDITO).
    formaPago (pedido) {
      const valor = String((pedido.factura_id ? pedido.pago_emitido : pedido.pago) || '').toUpperCase()
      if (valor.includes('CR')) return { texto: 'CRÉDITO', color: 'deep-orange-8', icono: 'schedule' }
      if (valor.includes('QR')) return { texto: 'QR', color: 'indigo-7', icono: 'qr_code_2' }
      return { texto: 'CONTADO', color: 'green-8', icono: 'payments' }
    },
    money (valor) {
      return Number(valor || 0).toFixed(2)
    },
    // Una linea con el detalle de pollo/cerdo/res: productos con cantidad y
    // precio, y los datos que tengan valor (los vacios no ocupan lugar).
    resumenDetalle (pedido) {
      const detalle = pedido.detalle_pollo || {}
      const productos = (detalle.productos || []).map(dato => ({
        etiqueta: dato.nombre,
        valor: [
          dato.cantidad !== null ? this.cantidad(dato.cantidad) + ' ' + dato.unidad : '',
          dato.precio ? 'Bs ' + this.money(dato.precio) : ''
        ].filter(Boolean).join(' ')
      }))
      const datos = (detalle.datos || []).filter(dato => dato.valor && dato.valor !== '—')
      return [...productos, ...datos]
    },
    tiposDelPedido (pedido) {
      const tipos = new Map()
      ;(pedido.items || []).forEach(item => {
        if (!item.tipo_nombre) return
        const tipo = tipos.get(item.tipo_nombre) || { nombre: item.tipo_nombre, color: item.tipo_color || 'primary', lineas: 0 }
        tipo.lineas += 1
        tipos.set(item.tipo_nombre, tipo)
      })
      return Array.from(tipos.values())
    },
    cantidad (valor) {
      return Number(valor || 0).toLocaleString('es-BO', { maximumFractionDigits: 3 })
    },
    // Los filtros viven en la URL: asi al volver del detalle de un pedido, al
    // recargar o al usar el boton atras se vuelve a la misma vista filtrada.
    sincronizarUrl () {
      const query = { fecha: this.fecha, tipo: String(this.tipo) }
      if (this.camion) query.camion = this.camion
      const actual = this.$route.query
      if (actual.fecha === query.fecha && actual.tipo === query.tipo &&
        (actual.camion || null) === (query.camion || null)) return
      this.$router.replace({ path: '/facturacion/pedidos', query })
    },
    // Tocar el camion ya filtrado lo suelta: en el celular no hace falta ir
    // hasta el boton Todos para volver a ver la lista completa.
    alternarCamion (valor) {
      this.camion = this.camion === valor ? null : valor
    },
    estiloCamion (pedido) {
      return this.estiloColor(pedido.placa_color)
    },
    // colorStyle del pedido viene como 'background-color: #RRGGBB'; el texto
    // se pone negro o blanco segun que tan claro sea ese fondo.
    estiloColor (color) {
      const estilo = (color || '').trim()
      const hex = /#([0-9a-f]{6})/i.exec(estilo)
      if (!hex) return 'background-color: #ECEFF1; color: #37474F'
      const valor = parseInt(hex[1], 16)
      const luz = (0.299 * ((valor >> 16) & 255) + 0.587 * ((valor >> 8) & 255) + 0.114 * (valor & 255)) / 255
      return estilo + '; color: ' + (luz > 0.6 ? '#212121' : '#FFFFFF')
    },
    hora (valor) {
      const partes = String(valor || '').split(' ')
      return partes.length > 1 ? partes[1].substr(0, 5) : ''
    },
    nombreTipoPedido (tipo) {
      return { NORMAL: 'Embutidos', POLLO: 'Pollo', CERDO: 'Cerdo', RES: 'Res', PODIUM: 'Podium', HUEVO: 'Huevo' }[tipo] || tipo
    },
    cargarTipos () {
      this.$api.get('tipos-producto').then(res => { this.tiposProducto = res.data })
    },
    cargar () {
      if (!this.fecha) return
      // Con varias busquedas seguidas solo vale la ultima: si una anterior
      // tarda mas en volver, no pisa el resultado nuevo.
      const consulta = (this.consultaActual || 0) + 1
      this.consultaActual = consulta
      this.cargando = true
      this.$api.get('facturacion/pedidos', {
        params: {
          fecha: this.fecha,
          tipo_producto: this.tipo === 'TODOS' ? undefined : this.tipo,
          buscar: this.buscar || ''
        }
      }).then(res => {
        if (consulta !== this.consultaActual) return
        this.pedidos = res.data
        // Si el camion elegido no aparece en la nueva consulta el filtro
        // dejaria la pantalla vacia sin motivo visible: se vuelve a Todos.
        if (this.camion && !this.camiones.some(opcion => opcion.value === this.camion)) {
          this.camion = null
        }
      }).catch(err => {
        if (consulta !== this.consultaActual) return
        this.$q.notify({
          type: 'negative', position: 'top',
          message: err.response?.data?.message || 'No se pudieron recuperar los pedidos'
        })
      }).finally(() => {
        if (consulta === this.consultaActual) this.cargando = false
      })
    },
    // Va por $api (con el token) y se baja como blob: el enlace directo no
    // lleva la sesion.
    descargarExcel () {
      this.exportando = true
      this.$api.get('facturacion/pedidos-excel', { params: { fecha: this.fecha }, responseType: 'blob' })
        .then(res => {
          const url = window.URL.createObjectURL(new Blob([res.data], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
          }))
          const enlace = document.createElement('a')
          enlace.href = url
          enlace.download = 'pedidos_' + this.fecha + '.xlsx'
          enlace.click()
          window.URL.revokeObjectURL(url)
        })
        .catch(async err => {
          let mensaje = null
          if (err.response?.data instanceof Blob) {
            try {
              mensaje = JSON.parse(await err.response.data.text()).message
            } catch (e) { mensaje = null }
          }
          this.$q.notify({ type: 'negative', position: 'top', message: mensaje || 'No se pudo generar el Excel' })
        })
        .finally(() => { this.exportando = false })
    },
    revisar (pedido) {
      // Fecha y camion viajan en la query para que el boton volver del detalle
      // devuelva al listado tal como estaba: la fecha elegida es la de
      // entrega, que no siempre coincide con la del pedido.
      const query = { fecha: this.fecha, lista: String(this.tipo) }
      if (this.camion) query.camion = this.camion
      this.$router.push({
        path: '/facturacion/pedidos/' + pedido.nro_pedido + '/' + pedido.tipo,
        query
      })
    },
    // El comprobante se manda a la impresora desde aca, cuando el cajero lo
    // pide: la venta ya no imprime sola al guardarse.
    // hojas: 'ambas' (ORIGINAL y COPIA), 'ORIGINAL' o 'COPIA'.
    imprimir (pedido, hojas = 'ambas') {
      const documento = pedido.comprobante_emitido === 'FACTURA' ? 'factura' : 'voucher'
      this.imprimiendo = pedido.factura_id
      const params = Object.assign({ imprimir: 1 }, hojas === 'ambas' ? { copias: 1 } : { marca: hojas })

      this.$api.get('facturacion/' + pedido.factura_id + '/' + documento, { params, responseType: 'blob' })
        .then(res => imprimirPdfDirecto(res.data, documento + '_' + pedido.factura_id + '.pdf'))
        .catch(async err => {
          // El PDF viaja como blob, asi que el motivo del rechazo (por ejemplo
          // que el caminero todavia no verifico su carga) hay que leerlo del
          // propio blob o se pierde detras de un mensaje generico.
          let mensaje = err.response?.data?.message
          if (err.response?.data instanceof Blob) {
            try {
              mensaje = JSON.parse(await err.response.data.text()).message
            } catch (e) { mensaje = null }
          }
          this.$q.notify({
            type: 'negative', position: 'top', timeout: 6000,
            message: mensaje || 'No se pudo imprimir el ' + documento
          })
        })
        .finally(() => { this.imprimiendo = null })
    },
    // El comprobante no lleva la fecha del pedido sino la del dia en que se
    // cobro, y se busca por el carnet con el que quedo emitido: buscar por el
    // numero traia de vuelta todo lo que contuviera ese digito.
    verComprobante (pedido) {
      this.$router.push({
        path: '/facturacion',
        query: {
          buscar: pedido.factura_nit || pedido.nit || pedido.factura_id,
          fecha: String(pedido.factura_fecha || '').substr(0, 10) ||
            date.formatDate(new Date(), 'YYYY-MM-DD')
        }
      })
    }
  }
}
</script>

<style scoped>
/* Los filtros van mas bajos que el dense de Quasar: esa fila compite con los
   pedidos por la pantalla del celular y solo se toca al empezar el dia. */
.filtros :deep(.q-field--dense .q-field__control),
.filtros :deep(.q-field--dense .q-field__marginal) {
  height: 30px;
  min-height: 30px;
}
.filtros :deep(.q-field__native),
.filtros :deep(.q-field__input) {
  font-size: 12px;
  padding: 0;
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
.camion-nro {
  width: 52px;
  margin-left: 4px;
  font-size: 11px;
  font-weight: 700;
  color: #424242;
  white-space: nowrap;
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
.pedido-oculto {
  opacity: 0.6;
}
.boletas {
  border: 1px solid #c5cae9;
  background: #f5f6ff;
}
.boleta-fila {
  min-height: 24px;
  font-size: 12px;
  border-top: 1px solid #e8eaf6;
}
.resumen-detalle {
  line-height: 1.35;
  padding-top: 2px;
  padding-bottom: 2px;
}
</style>
