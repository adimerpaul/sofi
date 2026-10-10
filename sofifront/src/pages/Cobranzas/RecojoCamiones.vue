<template>
  <q-page class="q-pa-xs">
    <div class="q-pa-sm">
      <div class="text-h6">Cobrar · Reporte de camineros</div>
      <q-btn flat no-caps color="primary" icon="account_balance_wallet" label="Créditos y deudas de clientes" to="/cobranzas/creditos"/>
      <div class="text-caption text-grey-7">Revise el recojo y las hojas de entrega por fecha y camión.</div>
    </div>
    <div class="row q-col-gutter-xs items-center q-mb-xs filtros no-print">
      <div class="col-6 col-md-2">
        <q-input v-model="fecha" type="date" dense outlined @update:model-value="cambiarFecha"/>
      </div>
      <div class="col-6 col-md-3">
        <q-select
          v-model="camion" dense outlined emit-value map-options
          :options="opcionesCamion" label="Camión" @update:model-value="cargar"
        />
      </div>
      <div class="col-12 col-md-3">
        <q-input v-model.trim="buscar" dense outlined clearable placeholder="Buscar cliente o nota">
          <template v-slot:prepend><q-icon name="search" size="16px"/></template>
        </q-input>
      </div>
      <!-- De entrada solo lo recogido (lo que trae plata). Prendiendolo se ve
           tambien lo dado a credito y lo que no se entrego. -->
      <div class="col-auto">
        <q-toggle
          v-model="mostrarTodo" dense size="sm" color="primary" label="Mostrar todo" class="text-caption"
          @update:model-value="cambiarFecha"
        >
          <q-tooltip>Suma las entregas de la ruta del sistema anterior (sin comprobante)</q-tooltip>
        </q-toggle>
      </div>
      <q-space/>
      <div class="col-auto">
        <q-btn color="primary" unelevated dense padding="6px 10px" icon="refresh" :loading="cargando" @click="cargar">
          <q-tooltip>Actualizar</q-tooltip>
        </q-btn>
      </div>
      <!-- Las mismas hojas que firma el caminero. Al imprimir varios camiones
           cada uno sale en su propia hoja: se firman por separado. -->
      <div class="col-auto">
        <q-btn-dropdown
          color="primary" unelevated dense no-caps icon="print" label="Imprimir"
          padding="6px 10px" :loading="imprimiendo !== null" :disable="!notas"
        >
          <q-list dense style="min-width: 250px">
            <!-- Lo que se entrega en caja: la tabla de cada camion, sola. -->
            <q-item clickable v-close-popup @click="hoja('tabla')">
              <q-item-section avatar><q-icon name="table_view" color="primary"/></q-item-section>
              <q-item-section>
                <b>Tabla del día</b>
                <q-item-label caption>
                  {{ camion ? 'Del camión ' + camion : 'Una por camión' }}: efectivo, QR y crédito
                </q-item-label>
              </q-item-section>
            </q-item>

            <q-separator class="q-my-xs"/>
            <q-item-label header class="q-py-xs">Hojas individuales</q-item-label>

            <q-item clickable v-close-popup @click="hoja('todos')">
              <q-item-section avatar><q-icon name="print" color="grey-7"/></q-item-section>
              <q-item-section>
                Todas las hojas
                <q-item-label caption>
                  {{ camion ? 'Del camión ' + camion : 'De los ' + camiones.length + ' camiones' }},
                  sin las vacías
                </q-item-label>
              </q-item-section>
            </q-item>

            <q-item
              v-for="seccion in SECCIONES" :key="seccion.clave"
              clickable v-close-popup @click="hoja(seccion.clave)"
            >
              <q-item-section avatar>
                <q-icon :name="seccion.icono" :color="seccion.color"/>
              </q-item-section>
              <q-item-section>
                {{ seccion.titulo }}
                <q-item-label caption>
                  {{ conteo(seccion.clave) }} nota{{ conteo(seccion.clave) === 1 ? '' : 's' }} ·
                  Bs {{ money(totales[seccion.clave]) }}
                </q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>
      </div>
    </div>

    <div v-if="cargando" class="flex flex-center q-pa-lg">
      <q-spinner color="primary" size="42px"/>
    </div>

    <div v-else-if="!camiones.length" class="text-center text-grey-6 q-pa-lg">
      Ningún camión registró entregas ni tiene ventas pendientes el {{ fechaLarga }}
    </div>

    <div v-else>
      <!-- El total del día: lo que cobranzas tiene que tener contado al cerrar,
           sumando todos los camiones que se están mostrando. -->
      <q-card flat bordered class="q-mb-xs rounded-borders">
        <div class="row items-center no-wrap q-px-xs bg-grey-3 barra-titulo">
          <div class="text-weight-bold text-grey-8 ellipsis">
            TOTAL DEL DÍA · {{ camiones.length }} camión{{ camiones.length === 1 ? '' : 'es' }} ·
            {{ notas }} nota{{ notas === 1 ? '' : 's' }}
          </div>
          <q-space/>
          <div class="text-weight-bolder text-grey-9">{{ fechaLarga }}</div>
        </div>
        <div class="row text-center caja-totales">
          <div class="col">
            <div class="caja-rotulo">EFECTIVO</div>
            <div class="caja-monto text-green-9">{{ money(totales.efectivo) }}</div>
          </div>
          <div class="col">
            <div class="caja-rotulo">QR</div>
            <div class="caja-monto text-indigo-9">{{ money(totales.qr_cobrado) }}</div>
          </div>
          <div class="col">
            <div class="caja-rotulo">CRÉDITO</div>
            <div class="caja-monto text-blue-grey-8">{{ money(totales.creditos) }}</div>
          </div>
          <div class="col">
            <div class="caja-rotulo">ANULADOS</div>
            <div class="caja-monto text-red-9">{{ money(totales.anulados) }}</div>
          </div>
        </div>
      </q-card>

      <!-- Un bloque por camión: es como se cuenta la plata, un caminero a la
           vez. Arrancan cerrados porque son hasta diez camiones. -->
      <div v-if="buscar && !camionesVisibles.length" class="text-center text-grey-6 q-pa-md">
        Ninguna nota de "{{ buscar }}" el {{ fechaLarga }}
      </div>
      <!-- Buscando, el camion que tiene la nota se abre solo. -->
      <q-expansion-item
        v-for="uno in camionesVisibles" :key="uno.placa + (buscar ? '-b' : '')"
        class="q-mb-xs camion" header-class="bg-grey-2 camion-titulo"
        :default-opened="camionesVisibles.length === 1 || !!buscar"
        expand-icon-class="text-grey-8"
      >
        <template v-slot:header>
          <q-item-section avatar class="camion-avatar">
            <q-icon name="local_shipping" color="grey-8" size="20px"/>
          </q-item-section>
          <q-item-section>
            <div class="text-weight-bolder text-grey-9">{{ uno.placa }}</div>
            <div class="text-caption text-grey-7">
              {{ uno.caminero }} ·
              {{ uno.notas }} nota{{ uno.notas === 1 ? '' : 's' }}
              <q-badge v-if="uno.pendientes && uno.pendientes.length" color="orange-8" class="q-ml-xs">
                {{ uno.pendientes.length }} sin entregar
              </q-badge>
            </div>
          </q-item-section>
          <q-item-section side>
            <div class="row items-center no-wrap q-gutter-x-sm">
              <div class="text-right">
                <div class="caja-rotulo">EFECTIVO</div>
                <div class="monto-camion text-green-9">{{ money(uno.totales.efectivo) }}</div>
              </div>
              <div class="text-right">
                <div class="caja-rotulo">QR</div>
                <div class="monto-camion text-indigo-9">{{ money(uno.totales.qr_cobrado) }}</div>
              </div>
              <q-btn
                flat dense round icon="print" color="primary" size="sm"
                :loading="imprimiendo === uno.placa"
                @click.stop="hoja('tabla', uno.placa)"
              >
                <q-tooltip>Imprimir la tabla de {{ uno.placa }}</q-tooltip>
              </q-btn>
            </div>
          </q-item-section>
        </template>

        <TablaRecojo v-if="uno.notas" :tabla="uno.tabla" :buscar="buscar || ''"/>
        <div v-else class="text-caption text-grey-7 q-pa-sm">
          Este camión todavía no registró ninguna entrega.
        </div>

        <!-- Lo que sigue en el camion sin ninguna entrega: no trae plata
             todavia, pero se ve para saber que falta rendir. -->
        <div v-if="pendientesVisibles(uno).length" class="q-pa-xs">
          <div class="row items-center bg-orange-1 text-orange-10 text-weight-bold q-px-sm barra-titulo">
            SIN ENTREGAR · {{ pendientesVisibles(uno).length }} nota{{ pendientesVisibles(uno).length === 1 ? '' : 's' }}
            <q-space/>
            Bs {{ money(uno.pendientes_total) }}
          </div>
          <q-markup-table dense flat bordered separator="horizontal" class="tabla-pendientes">
            <thead>
              <tr>
                <th class="text-left">Nota</th>
                <th class="text-left">Cliente</th>
                <th class="text-left">Pago</th>
                <th class="text-right">Monto</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in pendientesVisibles(uno)" :key="p.nota">
                <td class="text-left">#{{ p.nota }}</td>
                <td class="text-left">{{ p.cliente }}</td>
                <td class="text-left">{{ p.tipo_pago || '—' }}</td>
                <td class="text-right">{{ money(p.monto) }}</td>
              </tr>
            </tbody>
          </q-markup-table>
        </div>
      </q-expansion-item>
    </div>
  </q-page>
</template>

<script>
import { date } from 'quasar'
import { imprimirPdfDirecto } from 'src/utils/impresion.js'
import TablaRecojo from 'components/TablaRecojo.vue'

// Las mismas hojas que se entregan en papel, en el mismo orden. El mixto no
// tiene hoja propia: su efectivo va en contados y su QR en la hoja de QR.
const SECCIONES = [
  { clave: 'contados', titulo: 'CONTADOS DEL DÍA', icono: 'payments', color: 'green-8', fondo: 'bg-green-2 text-green-10' },
  { clave: 'qr', titulo: 'PAGOS QR', icono: 'qr_code_2', color: 'indigo-8', fondo: 'bg-indigo-2 text-indigo-10' },
  { clave: 'creditos', titulo: 'CRÉDITOS', icono: 'schedule', color: 'blue-grey-8', fondo: 'bg-blue-grey-2 text-blue-grey-10' },
  { clave: 'anulados', titulo: 'ANULADOS', icono: 'cancel', color: 'red-8', fondo: 'bg-red-2 text-red-10' }
]

export default {
  name: 'CobranzasRecojoCamiones',
  components: { TablaRecojo },
  data () {
    return {
      SECCIONES,
      fecha: this.$route.query.fecha || date.formatDate(new Date(), 'YYYY-MM-DD'),
      camion: null,
      // Por defecto solo lo que el camion recogio con la app (entregas con
      // comprobante); el toggle suma la ruta del sistema anterior.
      mostrarTodo: false,
      buscar: '',
      cargando: false,
      // Placa que se está imprimiendo, o 'todos' para el botón de arriba.
      imprimiendo: null,
      listaCamiones: [],
      camiones: [],
      notas: 0,
      totales: { contados: 0, qr: 0, creditos: 0, anulados: 0, efectivo: 0, qr_cobrado: 0 }
    }
  },
  created () {
    this.cargarCamiones()
    this.cargar()
  },
  computed: {
    opcionesCamion () {
      return [{ label: 'Todos los camiones', value: null }].concat(
        this.listaCamiones.map(c => ({ label: c.placa + ' · ' + c.caminero, value: c.placa }))
      )
    },
    // Buscando, solo los camiones que tienen alguna nota que coincide (entre
    // lo rendido o lo que sigue sin entregar).
    camionesVisibles () {
      const texto = String(this.buscar || '').trim().toLowerCase()
      if (!texto) return this.camiones
      const coincide = fila => String(fila.cliente || '').toLowerCase().includes(texto) || String(fila.nota).includes(texto)
      return this.camiones.filter(uno => uno.tabla.filas.some(coincide) || (uno.pendientes || []).some(coincide))
    },
    fechaLarga () {
      return date.formatDate(this.fecha + 'T00:00:00', 'dddd, D [DE] MMMM [DE] YYYY').toUpperCase()
    }
  },
  methods: {
    money (valor) {
      return Number(valor || 0).toFixed(2)
    },
    pendientesVisibles (uno) {
      const texto = String(this.buscar || '').trim().toLowerCase()
      const lista = uno.pendientes || []
      if (!texto) return lista
      return lista.filter(p => String(p.cliente || '').toLowerCase().includes(texto) || String(p.nota).includes(texto))
    },
    /** Cuántas notas hay en una hoja, sumando todos los camiones mostrados. */
    conteo (clave) {
      return this.camiones.reduce((suma, uno) => suma + uno.grupos[clave].length, 0)
    },
    cambiarFecha () {
      // Al cambiar de día el camión elegido puede no haber salido: se vuelve a
      // "todos" para no mostrar una pantalla vacía sin explicación.
      this.camion = null
      this.cargarCamiones()
      this.cargar()
    },
    cargarCamiones () {
      this.$api.get('cobranzas/recojo/camiones', { params: { fecha: this.fecha, todo: this.mostrarTodo ? 1 : 0 } })
        .then(res => { this.listaCamiones = res.data })
        .catch(() => { this.listaCamiones = [] })
    },
    cargar () {
      this.cargando = true

      this.$api.get('cobranzas/recojo', { params: { fecha: this.fecha, camion: this.camion, todo: this.mostrarTodo ? 1 : 0 } })
        .then(res => {
          this.camiones = res.data.camiones
          this.totales = res.data.totales
          this.notas = res.data.notas
        })
        .catch(err => {
          this.$q.notify({
            type: 'negative',
            position: 'top',
            message: err.response?.data?.message || 'No se pudo cargar el recojo del día'
          })
        })
        .finally(() => { this.cargando = false })
    },
    /**
     * Baja las hojas del recojo y las manda directo a la impresora.
     *
     * El PDF lo arma el backend con el mismo servicio que usa el caminero, así
     * que lo que cobranzas imprime es idéntico a lo que él firmó en la ruta.
     */
    hoja (grupo, placa) {
      const camion = placa || this.camion
      this.imprimiendo = placa || 'todos'

      this.$api.get('cobranzas/recojo/pdf', {
        params: { fecha: this.fecha, camion, grupo, todo: this.mostrarTodo ? 1 : 0 }, responseType: 'blob'
      })
        .then(res => imprimirPdfDirecto(
          res.data,
          'recojo_' + (camion ? camion.replace(/\s+/g, '_') + '_' : '') + grupo + '_' + this.fecha + '.pdf'
        ))
        .catch(async err => {
          let mensaje = 'No se pudo generar la hoja'
          // El error viaja como blob por el responseType: hay que leerlo.
          try {
            mensaje = JSON.parse(await err.response.data.text()).message || mensaje
          } catch (e) { /* el error no vino en JSON */ }
          this.$q.notify({ type: 'negative', position: 'top', message: mensaje })
        })
        .finally(() => { this.imprimiendo = null })
    }
  }
}
</script>

<style scoped>
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

.barra-titulo {
  height: 20px;
  font-size: 11px;
}
.caja-totales {
  padding: 2px 0;
}
.caja-rotulo {
  font-size: 9px;
  color: #757575;
  letter-spacing: 0.5px;
}
.caja-monto {
  font-size: 14px;
  font-weight: 700;
  line-height: 1.1;
}
.monto-camion {
  font-size: 12px;
  font-weight: 700;
  line-height: 1.1;
}
.camion {
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  overflow: hidden;
}
.camion :deep(.camion-titulo) {
  min-height: 40px;
  padding: 2px 8px;
}
.tabla-pendientes td, .tabla-pendientes th {
  font-size: 11px;
  padding: 2px 6px;
}
.camion-avatar {
  min-width: 28px;
  padding-right: 8px;
}
</style>
