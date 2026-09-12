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
          <q-expansion-item
            dense dense-toggle switch-toggle-side icon="shopping_basket"
            label="Ver contenido de la canasta" header-class="text-weight-medium"
          >
            <q-list dense separator :class="comprobante.verificado ? 'bg-green-1' : 'bg-grey-1'">
              <q-item v-for="(item, indice) in comprobante.items" :key="indice" class="q-px-sm">
                <q-item-section>
                  <q-item-label lines="2">{{ item.nombre }}</q-item-label>
                  <q-item-label caption>{{ item.cod_prod }}</q-item-label>
                </q-item-section>
                <q-item-section side class="text-right">
                  <q-item-label class="text-weight-bold">
                    {{ cantidad(item.peso || item.cantidad) }} {{ item.unidad }}
                  </q-item-label>
                  <q-item-label caption>Bs {{ money(item.total) }}</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-expansion-item>

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
              :label="comprobante.verificado ? 'Desmarcar' : 'Verificar'"
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
      intentado: false
    }
  },
  created () {
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
    comprobantesFiltrados () {
      const texto = (this.buscar || '').toLowerCase()
      const visibles = this.comprobantes.filter(comprobante => {
        if (this.soloPendientes && comprobante.verificado) return false
        if (!texto) return true
        return String(comprobante.nro_pedido || '').includes(texto) ||
          String(comprobante.nro_factura || comprobante.factura_id).includes(texto) ||
          (comprobante.cliente || '').toLowerCase().includes(texto)
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
      this.$router.replace({ path: '/caminero/carga', query: { fecha: this.fecha } })
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
      this.$api.post('caminero/carga/verificar', Object.assign({ fecha: this.fecha }, cuerpo))
        .then(res => {
          this.aplicar(res.data)
          if (alTerminar) alTerminar()
        })
        .catch(err => {
          this.$q.notify({
            type: 'negative', position: 'top',
            message: err.response?.data?.message || 'No se pudo guardar la verificación'
          })
        })
        .finally(() => { this.guardando = null })
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
.canasta-ocupada {
  pointer-events: none;
  opacity: 0.7;
}
</style>
