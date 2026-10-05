<template>
  <q-page class="q-pa-xs">
    <!-- El caminero mira esto de pie al lado del camion, con el celular en una
         mano: filas altas, una canasta por tarjeta y nada que apuntar. -->
    <div class="row q-col-gutter-xs items-center q-mb-xs filtros">
      <div class="col-6 col-md-2">
        <q-input v-model="fecha" type="date" dense outlined @update:model-value="cargar"/>
      </div>
      <!-- La fecha queda en la URL, asi que al volver a entrar se puede estar
           mirando un dia viejo sin darse cuenta: el boton vuelve a hoy de un
           toque y se pinta cuando no se esta en hoy. -->
      <div class="col-auto">
        <q-btn
          dense no-caps padding="6px 10px" icon="today" label="Hoy"
          :color="esHoy ? 'grey-5' : 'orange-8'" :flat="esHoy" :unelevated="!esHoy"
          :disable="esHoy" @click="irAHoy"
        />
      </div>
      <div class="col">
        <q-input v-model.trim="buscar" dense outlined clearable placeholder="Pedido o cliente"/>
      </div>
      <div class="col-auto">
        <q-btn color="primary" unelevated dense padding="6px 10px" icon="refresh"
               :loading="cargando" @click="cargar">
          <q-tooltip>Actualizar</q-tooltip>
        </q-btn>
      </div>
    </div>

    <!-- Tipo de pedido: el pollo se carga aparte de los embutidos, asi que se
         revisa por separado. Cada chip dice cuantos pedidos lleva. -->
    <div v-if="!error && tiposCarga.length > 1" class="row no-wrap q-gutter-xs q-mb-xs chips-scroll">
      <q-chip
        v-for="opcion in [{ valor: null, nombre: 'Todos', total: comprobantes.length, revisados: verificadosDe(comprobantes) }, ...tiposCarga]"
        :key="opcion.valor || 'todos'"
        clickable dense square
        :color="tipo === opcion.valor ? 'primary' : 'grey-3'"
        :text-color="tipo === opcion.valor ? 'white' : 'grey-9'"
        :icon="iconoTipo(opcion.valor)"
        class="text-weight-medium"
        @click="elegirTipo(opcion.valor)"
      >
        {{ opcion.nombre }}
        <q-badge rounded class="q-ml-xs" :color="opcion.revisados === opcion.total ? 'positive' : 'orange-8'">
          {{ opcion.revisados }}/{{ opcion.total }}
        </q-badge>
      </q-chip>
    </div>

    <!-- Un chip por cliente con cuantos pedidos tiene en la carga: tocandolo
         quedan solo los suyos; tocandolo de nuevo se suelta. -->
    <div v-if="!error && clientesCarga.length" class="row no-wrap q-gutter-xs q-mb-xs chips-scroll">
      <q-chip
        v-for="fila in clientesCarga" :key="fila.cliente"
        clickable dense
        :color="cliente === fila.cliente ? 'indigo-7' : (fila.revisados === fila.total ? 'green-1' : 'white')"
        :text-color="cliente === fila.cliente ? 'white' : 'grey-9'"
        :outline="cliente !== fila.cliente && fila.revisados !== fila.total"
        :icon="fila.revisados === fila.total ? 'check_circle' : 'person'"
        @click="cliente = cliente === fila.cliente ? null : fila.cliente"
      >
        <span class="chip-cliente ellipsis">{{ fila.cliente }}</span>
        <q-badge rounded class="q-ml-xs" :color="cliente === fila.cliente ? 'white' : 'indigo-6'"
                 :text-color="cliente === fila.cliente ? 'indigo-9' : 'white'">
          {{ fila.total }} {{ fila.total === 1 ? 'pedido' : 'pedidos' }}
        </q-badge>
      </q-chip>
    </div>

    <!-- Con muchos pedidos conviene revisar por producto: se elige uno y se
         ven solo las canastas que lo llevan, con esa linea sola para tildar. -->
    <div v-if="!error && productosCarga.length" class="q-mb-xs filtros">
      <q-select
        v-model="producto" :options="opcionesProducto" dense outlined clearable
        use-input input-debounce="0" emit-value map-options
        option-value="cod_prod" option-label="nombre"
        placeholder="Filtrar por producto" @filter="filtrarOpciones"
      >
        <template v-slot:prepend><q-icon name="inventory_2" size="18px"/></template>
        <template v-slot:option="scope">
          <q-item v-bind="scope.itemProps" dense>
            <q-item-section>
              <q-item-label>{{ scope.opt.nombre }}</q-item-label>
              <q-item-label caption>
                {{ scope.opt.cod_prod }} · {{ scope.opt.canastas }} canasta{{ scope.opt.canastas === 1 ? '' : 's' }}
                · {{ cantidad(scope.opt.total) }} {{ scope.opt.unidad }}
              </q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-badge :color="scope.opt.revisados === scope.opt.canastas ? 'positive' : 'orange-8'">
                {{ scope.opt.revisados }}/{{ scope.opt.canastas }}
              </q-badge>
            </q-item-section>
          </q-item>
        </template>
      </q-select>
    </div>

    <q-card v-if="productoElegido" flat bordered class="q-mb-xs rounded-borders bg-blue-1">
      <div class="row items-center no-wrap q-px-sm q-py-xs">
        <q-icon name="inventory_2" color="blue-9" size="22px" class="q-mr-sm"/>
        <div class="col">
          <div class="text-weight-bold ellipsis">{{ productoElegido.nombre }}</div>
          <div class="text-caption text-blue-10">
            {{ productoElegido.canastas }} canastas · total {{ cantidad(productoElegido.total) }} {{ productoElegido.unidad }}
            · revisado en {{ productoElegido.revisados }}/{{ productoElegido.canastas }}
          </div>
        </div>
        <q-btn
          dense unelevated no-caps color="positive" icon="done_all" label="Tildar en todas"
          :disable="productoElegido.revisados === productoElegido.canastas"
          @click="tildarProductoEnTodas"
        />
      </div>
    </q-card>

    <q-banner v-if="!esHoy && !error" dense rounded class="bg-orange-2 text-orange-10 q-mb-xs">
      <template v-slot:avatar><q-icon name="event_busy" color="orange-9"/></template>
      Estás viendo la salida del {{ fechaLarga(fecha) }}, que no es hoy.
      <template v-slot:action>
        <q-btn flat dense no-caps color="orange-10" label="Ver hoy" @click="irAHoy"/>
      </template>
    </q-banner>

    <q-banner v-if="error" dense rounded class="bg-red-2 text-red-10 q-mb-sm">
      <template v-slot:avatar><q-icon name="error" color="red-8"/></template>
      {{ error }}
    </q-banner>

    <q-card v-if="!error" flat bordered class="q-mb-xs rounded-borders">
      <div class="row items-center no-wrap q-px-sm q-py-xs" :class="resumen.completo ? 'bg-green-2' : 'bg-grey-3'">
        <q-icon name="local_shipping" size="20px" class="q-mr-xs" :color="resumen.completo ? 'green-9' : 'grey-8'"/>
        <div>
          <div class="text-weight-bolder">{{ placa || 'Sin camión' }}</div>
          <div class="text-caption text-grey-8">{{ caminero }}</div>
        </div>
        <q-space/>
        <div class="text-right">
          <div class="text-h6 text-weight-bolder" :class="resumen.completo ? 'text-green-9' : 'text-orange-9'">
            {{ resumen.verificados }}/{{ resumen.comprobantes }}
          </div>
          <div class="text-caption text-grey-8">Bs {{ money(resumen.total) }}</div>
        </div>
      </div>

      <q-linear-progress
        :value="resumen.comprobantes ? resumen.verificados / resumen.comprobantes : 0"
        size="10px" :color="resumen.completo ? 'positive' : 'orange-7'" track-color="grey-4"
      />

      <!-- Mientras falte una canasta, caja no imprime los comprobantes de este
           camion: se dice aca para que el caminero sepa que lo estan esperando. -->
      <div class="q-pa-xs">
        <q-banner v-if="resumen.completo" dense rounded class="bg-green-1 text-green-10">
          <template v-slot:avatar><q-icon name="check_circle" color="green-8"/></template>
          Carga verificada{{ resumen.verificado_en ? ' el ' + fechaHora(resumen.verificado_en) : '' }}.
          Caja ya puede imprimir los comprobantes de este camión.
        </q-banner>
        <q-banner v-else dense rounded class="bg-amber-1 text-amber-10">
          <template v-slot:avatar><q-icon name="pending_actions" color="amber-9"/></template>
          Faltan {{ resumen.pendientes }} canastas por revisar. Hasta terminarlas, caja no
          puede imprimir las facturas ni los vouchers de este camión.
        </q-banner>

        <!-- Los pedidos que caja todavia no facturo no tienen comprobante que
             imprimir, asi que no estan en la lista; se avisa para que el
             caminero no crea que se le perdio media carga. -->
        <div v-if="sinFacturar" class="text-caption text-blue-9 q-mt-xs q-px-xs">
          <q-icon name="hourglass_top" size="14px"/>
          {{ sinFacturar }} pedidos de tu camión todavía sin facturar en caja
        </div>

        <div v-if="resumen.con_observacion" class="text-caption text-red-9 q-mt-xs q-px-xs">
          <q-icon name="report_problem" size="14px"/>
          {{ resumen.con_observacion }} canastas con observación
        </div>

        <div class="row q-col-gutter-xs q-mt-xs">
          <div class="col">
            <q-btn
              class="full-width" color="positive" unelevated no-caps icon="done_all"
              label="Verificar todo lo que falta" :disable="!resumen.pendientes"
              :loading="guardando === 'todo'" @click="verificarTodo"
            />
          </div>
          <div class="col-auto">
            <q-btn
              color="primary" outline no-caps icon="print" label="Reporte"
              :loading="imprimiendo" @click="imprimir"
            >
              <q-tooltip>Reporte de carga para firmar</q-tooltip>
            </q-btn>
          </div>
        </div>
      </div>
    </q-card>

    <div v-if="!error" class="row items-center q-px-xs q-mb-xs">
      <q-toggle v-model="soloPendientes" dense size="sm" label="Solo lo que falta" class="text-caption"/>
      <q-space/>
      <div class="text-caption text-grey-7">{{ comprobantesFiltrados.length }} canastas</div>
    </div>

    <div v-if="cargando" class="flex flex-center q-pa-lg">
      <q-spinner color="primary" size="42px"/>
    </div>

    <q-card v-else-if="!comprobantesFiltrados.length && !error" flat bordered class="text-center text-grey-7 q-pa-md">
      <q-icon name="shopping_basket" size="36px" class="q-mb-sm"/>
      <div v-if="comprobantes.length">Ya revisaste todas las canastas de este filtro</div>
      <template v-else-if="sinFacturar">
        <div class="text-weight-bold">Caja todavía no facturó tu carga</div>
        <div class="text-caption q-mt-xs">
          Tenés {{ sinFacturar }} pedidos asignados a tu camión esperando que caja
          los cobre. A medida que los vayan facturando aparecen acá para revisar.
        </div>
      </template>
      <div v-else>Tu camión no tiene comprobantes para esta fecha</div>
    </q-card>

    <div v-else class="row q-col-gutter-xs">
      <div
        v-for="comprobante in comprobantesFiltrados" :key="comprobante.factura_id"
        class="col-12 col-sm-6 col-lg-4"
      >
        <q-card
          flat bordered class="rounded-borders shadow-1"
          :class="{
            'bg-green-2': comprobante.verificado && !comprobante.observado,
            'bg-deep-orange-1': comprobante.observado,
            'canasta-ocupada': guardando !== null
          }"
        >
          <q-card-section class="q-pa-xs cursor-pointer" @click="alternar(comprobante)">
            <div class="row items-center no-wrap">
              <!-- Tocando la tarjeta el boton de abajo puede quedar fuera de la
                   pantalla, asi que el aviso de que se esta grabando va aca. -->
              <q-spinner
                v-if="guardando === comprobante.factura_id"
                color="primary" size="30px" class="q-mr-sm"
              />
              <q-icon
                v-else
                :name="comprobante.verificado ? 'check_circle' : 'radio_button_unchecked'"
                :color="comprobante.verificado ? 'positive' : 'grey-6'" size="30px" class="q-mr-sm"
              />
              <div class="col">
                <div class="row items-center no-wrap">
                  <!-- F de factura, R de recibo, igual que en facturacion. -->
                  <q-badge :color="esFactura(comprobante) ? 'indigo-8' : 'blue-grey-6'" class="q-pa-xs">
                    {{ esFactura(comprobante) ? 'F' : 'R' }}
                  </q-badge>
                  <div class="text-caption q-ml-xs text-grey-8 ellipsis">
                    #{{ comprobante.nro_factura || comprobante.factura_id }}
                    <span v-if="comprobante.nro_pedido">· pedido {{ comprobante.nro_pedido }}</span>
                    <span v-if="comprobante.hora">· {{ String(comprobante.hora).substr(0, 5) }}</span>
                  </div>
                  <q-space/>
                  <div class="text-subtitle2 text-weight-bolder text-blue-grey-10">
                    Bs {{ money(comprobante.total) }}
                  </div>
                </div>
                <div class="text-subtitle2 text-weight-bold ellipsis-2-lines">
                  {{ comprobante.cliente || 'Sin cliente' }}
                </div>
                <div class="text-caption ellipsis" :class="comprobante.verificado ? 'text-green-9' : 'text-grey-7'">
                  {{ comprobante.productos }} producto{{ comprobante.productos === 1 ? '' : 's' }}
                  <span v-if="comprobante.zona">· {{ comprobante.zona }}</span>
                  <span v-if="comprobante.cambio" class="text-orange-9 text-weight-bold">
                    · cambió la venta, revisar de nuevo
                  </span>
                </div>
              </div>
            </div>

            <!-- Lo que el caminero anoto al revisar: queda a la vista porque es
                 lo que despues sale en el papel que firma. -->
            <div v-if="comprobante.observacion" class="text-caption text-weight-bold q-mt-xs"
                 :class="comprobante.observado ? 'text-deep-orange-9' : 'text-red-9'">
              <q-icon name="report_problem" size="14px"/>
              <span v-if="comprobante.observado" class="q-mr-xs">OBSERVADA:</span>{{ comprobante.observacion }}
            </div>
          </q-card-section>

          <q-separator/>
          <!-- Producto por producto: cada uno se tilda al verlo subir. Con
               todos tildados la canasta queda verificada sola. La verificada
               se pliega para no ocupar pantalla, pero se puede volver a abrir. -->
          <div
            class="row items-center no-wrap q-px-sm q-py-xs cursor-pointer"
            @click="alternarLista(comprobante)"
          >
            <q-icon name="shopping_basket" size="18px" class="q-mr-xs"
                    :color="comprobante.verificado ? 'green-8' : 'grey-7'"/>
            <div class="text-caption text-weight-medium">
              Productos revisados
              <span :class="comprobante.verificado ? 'text-green-9' : 'text-orange-9'" class="text-weight-bolder">
                {{ revisadosDe(comprobante) }}/{{ comprobante.items.length }}
              </span>
            </div>
            <q-space/>
            <q-icon :name="listaAbierta(comprobante) ? 'expand_less' : 'expand_more'" size="20px" color="grey-7"/>
          </div>
          <q-linear-progress
            :value="comprobante.items.length ? revisadosDe(comprobante) / comprobante.items.length : 0"
            size="4px" :color="comprobante.verificado ? 'positive' : 'orange-7'" track-color="grey-3"
          />
          <q-slide-transition>
            <q-list v-show="listaAbierta(comprobante)" separator
                    :class="comprobante.verificado ? 'bg-green-1' : 'bg-grey-1'">
              <q-item
                v-for="item in itemsVisibles(comprobante)" :key="item.id"
                clickable v-ripple class="q-px-xs producto"
                :class="{ 'producto-revisado': item.revisado }"
                @click="alternarProducto(comprobante, item)"
              >
                <q-item-section avatar class="q-pr-xs" style="min-width: 0">
                  <q-icon
                    :name="item.revisado ? 'check_box' : 'check_box_outline_blank'"
                    :color="item.revisado ? 'positive' : 'grey-6'" size="28px"
                  />
                </q-item-section>
                <q-item-section>
                  <q-item-label lines="2" class="text-weight-medium">{{ item.nombre }}</q-item-label>
                  <q-item-label caption>{{ item.cod_prod }}</q-item-label>
                </q-item-section>
                <q-item-section side class="text-right">
                  <q-item-label class="text-weight-bold text-blue-grey-10">
                    {{ cantidad(item.peso || item.cantidad) }} {{ item.unidad }}
                  </q-item-label>
                  <q-item-label caption>Bs {{ money(item.total) }}</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-slide-transition>

          <q-separator/>
          <q-card-actions class="q-pa-xs">
            <!-- Revisar una canasta termina de dos maneras: visto bueno, u
                 observada con el motivo. Las dos la dan por revisada y dejan
                 salir el camion; la observada llega marcada a facturacion. -->
            <q-btn
              dense no-caps size="sm" :outline="!comprobante.observado" unelevated
              :color="comprobante.observado ? 'deep-orange-7' : 'deep-orange-8'"
              :text-color="comprobante.observado ? 'white' : undefined"
              icon="report_problem"
              :label="comprobante.observado ? 'Ver observación' : 'Observar'"
              :loading="guardando === comprobante.factura_id"
              @click="abrirObservacion(comprobante)"
            />
            <q-space/>
            <q-btn
              dense no-caps size="sm" unelevated
              :color="comprobante.verificado ? 'grey-6' : 'positive'"
              :icon="comprobante.verificado ? 'undo' : 'check'"
              :label="comprobante.verificado ? 'Desmarcar' : 'Verificar todo'"
              :loading="guardando === comprobante.factura_id"
              @click="alternar(comprobante)"
            />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <!-- La observacion es donde queda escrito lo que no cuadraba: producto que
         falto, canasta cambiada, lo que sea que despues haya que reclamar. -->
    <q-dialog v-model="dialogo">
      <q-card style="min-width: 300px">
        <q-card-section class="q-pb-none">
          <div class="text-subtitle2 text-weight-bold">
            {{ esFactura(editando) ? 'Factura' : 'Recibo' }}
            #{{ editando.nro_factura || editando.factura_id }} · {{ editando.cliente }}
          </div>
          <div class="text-caption text-grey-7">
            {{ editando.productos }} productos · Bs {{ money(editando.total) }}
          </div>
        </q-card-section>
        <q-card-section>
          <q-input
            v-model.trim="observacion" dense outlined autogrow autofocus
            label="Qué pasó con esta canasta" maxlength="190" counter
            :error="intentado && !observacion"
            error-message="Escribí el motivo: es lo que va a ver caja"
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat no-caps label="Cancelar" color="grey-8" v-close-popup/>
          <!-- Quitar la observacion deja la canasta con el visto bueno normal. -->
          <q-btn
            v-if="editando.observado" flat no-caps color="grey-8" icon="undo" label="Quitar observación"
            :loading="guardando === editando.factura_id" @click="quitarObservacion"
          />
          <q-btn
            unelevated no-caps color="deep-orange-8" icon="report_problem" label="Guardar observación"
            :loading="guardando === editando.factura_id" @click="guardarObservacion"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { date } from 'quasar'
import { imprimirPdfDirecto } from 'src/utils/impresion.js'

export default {
  name: 'CargaVerificar',
  data () {
    return {
      fecha: this.$route.query.fecha || date.formatDate(new Date(), 'YYYY-MM-DD'),
      buscar: '',
      soloPendientes: false,
      // Filtros por chip: tipo de pedido (POLLO, NORMAL...) y cliente.
      tipo: this.$route.query.tipo || null,
      cliente: null,
      cargando: false,
      imprimiendo: false,
      // Id del comprobante que se esta grabando, o 'todo'.
      guardando: null,
      error: '',
      placa: '',
      caminero: '',
      // Pedidos ya asignados al camion que caja todavia no facturo: no tienen
      // comprobante, asi que no estan en la lista.
      sinFacturar: 0,
      comprobantes: [],
      resumen: {
        comprobantes: 0, verificados: 0, pendientes: 0, con_observacion: 0,
        completo: false, porcentaje: 0, verificado_en: null, total: 0
      },
      dialogo: false,
      editando: {},
      observacion: '',
      // Recien despues de intentar guardar se marca en rojo el campo vacio.
      intentado: false,
      // Canastas cuya lista de productos el caminero abrio o cerro a mano;
      // sin tocarla, abierta mientras falte revisar y plegada al verificarse.
      listas: {},
      // cod_prod elegido en el filtro por producto, y el texto que se escribe
      // en ese selector para achicar la lista.
      producto: null,
      textoProducto: ''
    }
  },
  created () {
    // Lo que se manda de cada canasta va en fila: los tildes de producto se
    // graban en el orden en que se tocaron, sin pisarse entre ellos.
    this.colas = {}
    this.enCurso = {}
    this.cargar()
  },
  watch: {
    // La fecha viaja en la URL para que al recargar o volver atras se vea
    // exactamente el dia que se estaba revisando, sin quedar en otro sin
    // darse cuenta.
    fecha () { this.sincronizarUrl() }
  },
  computed: {
    esHoy () {
      return this.fecha === date.formatDate(new Date(), 'YYYY-MM-DD')
    },
    // Todos los productos de la carga del dia, sumados entre canastas, para
    // el filtro por producto.
    // Los tipos de pedido que trae la carga, con cuantos pedidos y cuantos
    // ya revisados tiene cada uno.
    tiposCarga () {
      const porTipo = {}
      this.comprobantes.forEach(comprobante => {
        const valor = comprobante.pedido_tipo || 'NORMAL'
        const fila = porTipo[valor] || (porTipo[valor] = { valor, nombre: this.nombreTipo(valor), total: 0, revisados: 0 })
        fila.total++
        if (comprobante.verificado) fila.revisados++
      })
      const orden = ['POLLO', 'NORMAL', 'CERDO', 'RES', 'PODIUM']
      return Object.values(porTipo).sort((a, b) => orden.indexOf(a.valor) - orden.indexOf(b.valor))
    },
    // La carga del tipo elegido: de ahi salen los chips de cliente y la
    // lista de productos, para que todo hable del mismo grupo.
    comprobantesDelTipo () {
      return this.tipo
        ? this.comprobantes.filter(comprobante => (comprobante.pedido_tipo || 'NORMAL') === this.tipo)
        : this.comprobantes
    },
    clientesCarga () {
      const porCliente = {}
      this.comprobantesDelTipo.forEach(comprobante => {
        const nombre = comprobante.cliente || 'Sin cliente'
        const fila = porCliente[nombre] || (porCliente[nombre] = { cliente: nombre, total: 0, revisados: 0 })
        fila.total++
        if (comprobante.verificado) fila.revisados++
      })
      // Los que tienen mas pedidos primero: son los que mas cuesta armar.
      return Object.values(porCliente)
        .sort((a, b) => b.total - a.total || a.cliente.localeCompare(b.cliente))
    },
    productosCarga () {
      const porCodigo = {}
      this.comprobantesDelTipo.forEach(comprobante => {
        const contados = new Set()
        comprobante.items.forEach(item => {
          const codigo = String(item.cod_prod).trim()
          const fila = porCodigo[codigo] || (porCodigo[codigo] = {
            cod_prod: codigo, nombre: item.nombre, unidad: item.unidad, canastas: 0, revisados: 0, total: 0, _pendiente: {}
          })
          fila.total += Number(item.peso || item.cantidad || 0)
          if (!contados.has(codigo)) {
            contados.add(codigo)
            fila.canastas++
          }
          // La canasta cuenta como revisada en ese producto si todas sus
          // lineas de ese codigo estan tildadas.
          if (!item.revisado) fila._pendiente[comprobante.factura_id] = true
        })
      })
      return Object.values(porCodigo).map(fila => {
        fila.revisados = fila.canastas - Object.keys(fila._pendiente).length
        return fila
      }).sort((a, b) => a.nombre.localeCompare(b.nombre))
    },
    opcionesProducto () {
      const texto = this.textoProducto.toLowerCase()
      if (!texto) return this.productosCarga
      return this.productosCarga.filter(fila =>
        fila.nombre.toLowerCase().includes(texto) || fila.cod_prod.includes(texto))
    },
    productoElegido () {
      return this.producto ? this.productosCarga.find(fila => fila.cod_prod === this.producto) || null : null
    },
    comprobantesFiltrados () {
      const texto = (this.buscar || '').toLowerCase()
      const visibles = this.comprobantes.filter(comprobante => {
        if (this.tipo && (comprobante.pedido_tipo || 'NORMAL') !== this.tipo) return false
        if (this.cliente && (comprobante.cliente || 'Sin cliente') !== this.cliente) return false
        if (this.producto && !this.lineasDelProducto(comprobante).length) return false
        // Con producto elegido, "lo que falta" es ese producto sin tildar.
        if (this.soloPendientes) {
          if (this.producto) {
            if (this.lineasDelProducto(comprobante).every(item => item.revisado)) return false
          } else if (comprobante.verificado) return false
        }
        if (!texto) return true
        return String(comprobante.nro_pedido || '').includes(texto) ||
          String(comprobante.nro_factura || comprobante.factura_id).includes(texto) ||
          (comprobante.cliente || '').toLowerCase().includes(texto) ||
          // Tambien se encuentra la canasta por lo que lleva.
          comprobante.items.some(item => (item.nombre || '').toLowerCase().includes(texto) ||
            String(item.cod_prod).includes(texto))
      })

      // Lo que falta revisar va primero: el caminero trabaja de arriba hacia
      // abajo y lo ya verificado se le hunde solo, sin tener que buscarlo
      // entre las canastas que todavia tiene que mirar. El orden dentro de
      // cada grupo es el que vino del backend (por comprobante), y sort es
      // estable, asi que no se altera.
      return visibles.slice().sort((a, b) => Number(a.verificado) - Number(b.verificado))
    }
  },
  methods: {
    money (valor) {
      return Number(valor || 0).toFixed(2)
    },
    cantidad (valor) {
      return Number(valor || 0).toLocaleString('es-BO', { maximumFractionDigits: 3 })
    },
    esFactura (comprobante) {
      return comprobante.tipo_comprobante === 'FACTURA'
    },
    fechaHora (valor) {
      return date.formatDate(String(valor).replace(' ', 'T'), 'DD/MM/YYYY HH:mm')
    },
    fechaLarga (valor) {
      return date.formatDate(String(valor) + 'T00:00:00', 'DD/MM/YYYY')
    },
    irAHoy () {
      this.fecha = date.formatDate(new Date(), 'YYYY-MM-DD')
      this.cargar()
    },
    sincronizarUrl () {
      if (this.$route.query.fecha === this.fecha) return
      this.$router.replace({ path: '/caminero/carga', query: Object.assign({}, this.$route.query, { fecha: this.fecha }) })
    },
    cargar () {
      this.cargando = true
      this.error = ''
      this.$api.get('caminero/carga', { params: { fecha: this.fecha } })
        .then(res => this.aplicar(res.data))
        .catch(err => {
          this.comprobantes = []
          this.error = err.response?.data?.message || 'No se pudo cargar la lista'
        })
        .finally(() => { this.cargando = false })
    },
    aplicar (datos) {
      if (datos.placa !== undefined) this.placa = datos.placa
      if (datos.caminero !== undefined) this.caminero = datos.caminero
      if (datos.sin_facturar !== undefined) this.sinFacturar = datos.sin_facturar
      // Al tildar una canasta vuelve solo esa: se cambia en su lugar en vez de
      // reemplazar la lista entera, que en el celular se notaba como demora.
      if (datos.comprobante) {
        const indice = this.comprobantes.findIndex(
          comprobante => comprobante.factura_id === datos.comprobante.factura_id
        )
        if (indice !== -1) this.comprobantes.splice(indice, 1, datos.comprobante)
      } else if (datos.comprobantes) {
        this.comprobantes = datos.comprobantes
      }
      if (datos.resumen) this.resumen = datos.resumen
    },
    // Tocar la tarjeta alcanza para el caso normal: la canasta subio completa.
    // El visto bueno limpio: si la canasta estaba observada, deja de estarlo.
    alternar (comprobante) {
      this.enviar({
        factura_id: comprobante.factura_id,
        verificado: !comprobante.verificado,
        observado: false,
        // Al desmarcar se borra lo anotado: la canasta vuelve a estar sin mirar.
        observacion: ''
      })
    },
    revisadosDe (comprobante) {
      return comprobante.items.filter(item => item.revisado).length
    },
    nombreTipo (valor) {
      return { POLLO: 'Pollo', NORMAL: 'Embutidos', CERDO: 'Cerdo', RES: 'Res', PODIUM: 'Podium y Huevo' }[valor] || valor
    },
    iconoTipo (valor) {
      return { POLLO: 'egg', NORMAL: 'lunch_dining', CERDO: 'savings', RES: 'kebab_dining', PODIUM: 'pets' }[valor] || 'local_shipping'
    },
    verificadosDe (lista) {
      return lista.filter(comprobante => comprobante.verificado).length
    },
    // Cambiar de tipo suelta el cliente y el producto, que pueden no existir
    // en el otro grupo. Queda en la URL para volver al mismo tipo.
    elegirTipo (valor) {
      this.tipo = valor
      this.cliente = null
      this.producto = null
      const query = Object.assign({}, this.$route.query)
      if (valor) query.tipo = valor
      else delete query.tipo
      this.$router.replace({ path: '/caminero/carga', query })
    },
    lineasDelProducto (comprobante) {
      return comprobante.items.filter(item => String(item.cod_prod).trim() === this.producto)
    },
    // Con un producto elegido, en cada canasta se ve solo esa linea.
    itemsVisibles (comprobante) {
      return this.producto ? this.lineasDelProducto(comprobante) : comprobante.items
    },
    filtrarOpciones (texto, actualizar) {
      actualizar(() => { this.textoProducto = texto || '' })
    },
    // Tilda el producto elegido en todas las canastas a la vista (respeta el
    // tipo y el cliente elegidos) donde falta.
    tildarProductoEnTodas () {
      this.comprobantesFiltrados.forEach(comprobante => {
        const lineas = this.lineasDelProducto(comprobante).filter(item => !item.revisado)
        if (!lineas.length) return
        lineas.forEach(item => { item.revisado = true })
        this.grabarProductos(comprobante)
      })
    },
    listaAbierta (comprobante) {
      // Filtrando por producto la linea tiene que estar a la vista.
      if (this.producto) return true
      const elegido = this.listas[comprobante.factura_id]
      return elegido === undefined ? !comprobante.verificado : elegido
    },
    alternarLista (comprobante) {
      this.listas[comprobante.factura_id] = !this.listaAbierta(comprobante)
    },
    // Se tilda en pantalla al instante y se graba detras; la respuesta dice si
    // con eso la canasta quedo completa.
    alternarProducto (comprobante, item) {
      item.revisado = !item.revisado
      this.grabarProductos(comprobante)
    },
    grabarProductos (comprobante) {
      const completa = comprobante.items.every(producto => producto.revisado)
      // Si una verificada pierde un tilde vuelve a pendiente (la observada no).
      if (!comprobante.observado) comprobante.verificado = completa
      this.encolar(comprobante.factura_id, () => this.$api.post('caminero/carga/productos', {
        fecha: this.fecha,
        factura_id: comprobante.factura_id,
        revisados: comprobante.items.filter(producto => producto.revisado).map(producto => producto.id)
      }))
    },
    // Pone el envio detras de lo que ya iba de esa canasta. Solo la ultima
    // respuesta reemplaza la tarjeta: las intermedias traerian tildes viejos.
    encolar (facturaId, peticion, alTerminar) {
      this.enCurso[facturaId] = (this.enCurso[facturaId] || 0) + 1
      const anterior = this.colas[facturaId] || Promise.resolve()
      const actual = anterior.then(() => peticion().then(res => {
        this.enCurso[facturaId]--
        if (this.enCurso[facturaId] === 0) {
          this.aplicar(res.data)
        } else if (res.data.resumen) {
          this.resumen = res.data.resumen
        }
        if (alTerminar) alTerminar()
      }, err => {
        this.enCurso[facturaId]--
        this.$q.notify({
          type: 'negative', position: 'top',
          message: err.response?.data?.message || 'No se pudo guardar la verificación'
        })
        // Lo que se ve tiene que ser lo grabado: se vuelve a pedir la carga.
        if (this.enCurso[facturaId] === 0) this.cargar()
      }))
      this.colas[facturaId] = actual
      return actual
    },
    abrirObservacion (comprobante) {
      this.editando = comprobante
      this.observacion = comprobante.observacion || ''
      this.intentado = false
      this.dialogo = true
    },
    // Observar cierra la revision igual que el visto bueno, pero dejando el
    // motivo escrito. Sin motivo no se guarda: caja recibiria una canasta
    // marcada y ninguna explicacion.
    guardarObservacion () {
      this.intentado = true
      if (!this.observacion) return

      this.enviar({
        factura_id: this.editando.factura_id,
        verificado: true,
        observado: true,
        observacion: this.observacion
      }, () => { this.dialogo = false })
    },
    quitarObservacion () {
      this.enviar({
        factura_id: this.editando.factura_id,
        verificado: true,
        observado: false,
        observacion: ''
      }, () => { this.dialogo = false })
    },
    enviar (cuerpo, alTerminar) {
      // Con el celular en la mano es facil tocar dos veces la misma canasta
      // mientras se esta grabando la anterior.
      if (this.guardando !== null) return
      this.guardando = cuerpo.factura_id
      // Va en la misma fila que los tildes de producto de esa canasta: si
      // quedaba uno grabandose, este sale despues y no lo pisa al reves.
      this.encolar(
        cuerpo.factura_id,
        () => this.$api.post('caminero/carga/verificar', Object.assign({ fecha: this.fecha }, cuerpo)),
        alTerminar
      ).finally(() => { this.guardando = null })
    },
    verificarTodo () {
      this.$q.dialog({
        title: 'Verificar todo',
        message: 'Vas a dar por recibidas todas las canastas que faltan. ' +
          'Las que ya tengan una observación anotada no se tocan.',
        cancel: true,
        ok: { label: 'Verificar', color: 'positive', noCaps: true },
        persistent: true
      }).onOk(() => {
        this.guardando = 'todo'
        this.$api.post('caminero/carga/verificar-todo', { fecha: this.fecha })
          .then(res => {
            this.aplicar(res.data)
            this.$q.notify({ type: 'positive', position: 'top', message: res.data.message })
          })
          .catch(err => {
            this.$q.notify({
              type: 'negative', position: 'top',
              message: err.response?.data?.message || 'No se pudo verificar la carga'
            })
          })
          .finally(() => { this.guardando = null })
      })
    },
    // El papel se lleva firmado a almacen: dice que canastas subieron y que
    // faltaba, y por eso se puede sacar aunque la revision no este terminada.
    imprimir () {
      this.imprimiendo = true
      this.$api.get('caminero/carga/reporte', {
        params: { fecha: this.fecha }, responseType: 'blob'
      })
        .then(res => imprimirPdfDirecto(res.data, 'carga_' + this.fecha + '.pdf'))
        .catch(() => {
          this.$q.notify({
            type: 'negative', position: 'top', message: 'No se pudo imprimir el reporte'
          })
        })
        .finally(() => { this.imprimiendo = false })
    }
  }
}
</script>

<style scoped>
/* Misma altura reducida que usan los filtros de facturacion: esta fila solo se
   toca al empezar y no tiene por que comerse la pantalla del celular. */
.filtros :deep(.q-field--dense .q-field__control),
.filtros :deep(.q-field--dense .q-field__marginal) {
  height: 32px;
  min-height: 32px;
}
.filtros :deep(.q-field__native),
.filtros :deep(.q-field__input) {
  font-size: 12px;
  padding: 0;
}
/* Mientras se graba, las tarjetas no aceptan toques: el caminero ve que algo
   esta pasando y no encola tildes que despues no sabe si entraron. */
/* Los chips corren de costado con el dedo en vez de ocupar varias filas. */
.chips-scroll {
  overflow-x: auto;
  scrollbar-width: none;
  margin-left: 0;
}
.chips-scroll::-webkit-scrollbar {
  display: none;
}
.chips-scroll > .q-chip {
  flex: 0 0 auto;
}
.chip-cliente {
  max-width: 150px;
  display: inline-block;
  vertical-align: middle;
}
/* Filas de producto altas, faciles de tocar con el pulgar. */
.producto {
  min-height: 52px;
}
.producto-revisado .q-item__label:first-child {
  color: #2e7d32;
}
.canasta-ocupada {
  pointer-events: none;
  opacity: 0.7;
}
</style>
