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
            <q-item clickable v-close-popup @click="hoja('todos')">
              <q-item-section avatar><q-icon name="print" color="primary"/></q-item-section>
              <q-item-section>
                Todas las hojas
                <q-item-label caption>
                  {{ camion ? 'Del camión ' + camion : 'De los ' + camiones.length + ' camiones' }},
                  sin las vacías
                </q-item-label>
              </q-item-section>
            </q-item>

            <q-separator class="q-my-xs"/>

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
      Ningún camión registró entregas el {{ fechaLarga }}
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
      <q-expansion-item
        v-for="uno in camiones" :key="uno.placa"
        class="q-mb-xs camion" header-class="bg-grey-2 camion-titulo"
        :default-opened="camiones.length === 1"
        expand-icon-class="text-grey-8"
      >
        <template v-slot:header>
          <q-item-section avatar class="camion-avatar">
            <q-icon name="local_shipping" color="grey-8" size="20px"/>
          </q-item-section>
          <q-item-section>
            <div class="text-weight-bolder text-grey-9">{{ uno.placa }}</div>
            <div class="text-caption text-grey-7">{{ uno.caminero }} · {{ uno.notas }} notas</div>
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
                @click.stop="hoja('todos', uno.placa)"
              >
                <q-tooltip>Imprimir las hojas de {{ uno.placa }}</q-tooltip>
              </q-btn>
            </div>
          </q-item-section>
        </template>

        <div class="row q-col-gutter-xs q-pa-xs">
          <div v-for="seccion in SECCIONES" :key="seccion.clave" class="col-12 col-md-6">
            <q-card flat bordered class="rounded-borders">
              <div class="row items-center no-wrap q-px-xs seccion-titulo" :class="seccion.fondo">
                <div class="text-weight-bolder">{{ seccion.titulo }}</div>
                <q-space/>
                <div class="text-weight-bolder">
                  {{ uno.grupos[seccion.clave].length }} · Bs {{ money(uno.totales[seccion.clave]) }}
                </div>
              </div>

              <div v-if="!uno.grupos[seccion.clave].length" class="text-center text-grey-6 vacio">
                Sin registros
              </div>

              <table v-else class="tabla">
                <thead>
                  <tr>
                    <th class="col-n">N°</th>
                    <th class="col-nota">NOTA</th>
                    <th>NOMBRE DEL CLIENTE</th>
                    <th v-if="seccion.clave === 'anulados'" class="col-motivo">MOTIVO</th>
                    <th class="col-monto">MONTO</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(fila, indice) in uno.grupos[seccion.clave]" :key="fila.id">
                    <td class="col-n">{{ indice + 1 }}</td>
                    <td class="col-nota">{{ fila.nota }}</td>
                    <td class="ellipsis">
                      {{ fila.cliente || 'Sin cliente' }}
                      <!-- El mixto sale en las dos hojas de cobro, cada una por
                           su parte: se avisa para que no parezca pago de menos. -->
                      <span v-if="fila.tipago === 'MIXTO' && seccion.clave !== 'anulados'" class="desglose">
                        (mixto: Ef {{ money(fila.monto_efectivo) }} · QR {{ money(fila.monto_qr) }})
                      </span>
                    </td>
                    <td v-if="seccion.clave === 'anulados'" class="col-motivo ellipsis">
                      {{ fila.motivo || fila.estado }}
                    </td>
                    <td class="col-monto">{{ money(fila.monto) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td :colspan="seccion.clave === 'anulados' ? 4 : 3" class="text-right text-weight-bolder">
                      TOTALES
                    </td>
                    <td class="col-monto text-weight-bolder">{{ money(uno.totales[seccion.clave]) }}</td>
                  </tr>
                </tfoot>
              </table>
            </q-card>
          </div>
        </div>
      </q-expansion-item>
    </div>
  </q-page>
</template>

<script>
import { date } from 'quasar'
import { imprimirPdfDirecto } from 'src/utils/impresion.js'

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
  data () {
    return {
      SECCIONES,
      fecha: this.$route.query.fecha || date.formatDate(new Date(), 'YYYY-MM-DD'),
      camion: null,
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
    fechaLarga () {
      return date.formatDate(this.fecha + 'T00:00:00', 'dddd, D [DE] MMMM [DE] YYYY').toUpperCase()
    }
  },
  methods: {
    money (valor) {
      return Number(valor || 0).toFixed(2)
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
      this.$api.get('cobranzas/recojo/camiones', { params: { fecha: this.fecha } })
        .then(res => { this.listaCamiones = res.data })
        .catch(() => { this.listaCamiones = [] })
    },
    cargar () {
      this.cargando = true

      this.$api.get('cobranzas/recojo', { params: { fecha: this.fecha, camion: this.camion } })
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
        params: { fecha: this.fecha, camion, grupo }, responseType: 'blob'
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
.camion-avatar {
  min-width: 28px;
  padding-right: 8px;
}
.seccion-titulo {
  height: 20px;
  font-size: 11px;
}
.vacio {
  font-size: 11px;
  padding: 6px 0;
}

/* Tabla propia y no q-table: son listas de leer y sumar, y así cada fila
   ocupa 18px en el celular y sale igual al papel al imprimir. */
.tabla {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
}
.tabla th {
  background: #eceff1;
  font-size: 9px;
  letter-spacing: 0.5px;
  text-align: left;
  padding: 1px 3px;
}
.tabla td {
  padding: 1px 3px;
  border-top: 1px solid #eee;
  height: 18px;
  max-width: 0;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
.tabla tfoot td {
  border-top: 1px solid #bdbdbd;
  background: #fafafa;
}
.col-n {
  width: 22px;
  text-align: right;
  color: #9e9e9e;
}
.col-nota {
  width: 54px;
  font-weight: 600;
}
.col-monto {
  width: 62px;
  text-align: right;
  font-weight: 600;
}
.col-motivo {
  width: 34%;
  color: #b71c1c;
}
.desglose {
  color: #00695c;
  font-size: 10px;
}
</style>
