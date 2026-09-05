<template>
  <q-page class="q-pa-xs">
    <div class="row q-col-gutter-xs items-center q-mb-xs filtros no-print">
      <div class="col-6 col-md-2">
        <q-input v-model="fecha" type="date" dense outlined @update:model-value="cargar"/>
      </div>
      <q-space/>
      <div class="col-auto">
        <q-btn color="primary" unelevated dense padding="6px 10px" icon="refresh" :loading="cargando" @click="cargar">
          <q-tooltip>Actualizar</q-tooltip>
        </q-btn>
      </div>
      <!-- Cada hoja se imprime y se firma por separado, igual que en papel:
           el caminero entrega la de contados, la de QR y la de créditos. -->
      <div class="col-auto">
        <q-btn-dropdown
          color="primary" unelevated dense no-caps icon="print" label="Imprimir"
          padding="6px 10px" :loading="imprimiendo"
        >
          <q-list dense style="min-width: 220px">
            <q-item clickable v-close-popup @click="hoja('todos')">
              <q-item-section avatar><q-icon name="print" color="primary"/></q-item-section>
              <q-item-section>
                Todas las hojas
                <q-item-label caption>Una por forma de pago, sin las vacías</q-item-label>
              </q-item-section>
            </q-item>

            <q-separator class="q-my-xs"/>

            <q-item
              v-for="seccion in secciones" :key="seccion.clave"
              clickable v-close-popup @click="hoja(seccion.clave)"
            >
              <q-item-section avatar>
                <q-icon :name="seccion.icono" :color="seccion.color"/>
              </q-item-section>
              <q-item-section>
                {{ seccion.titulo }}
                <q-item-label caption>
                  {{ seccion.filas.length }} nota{{ seccion.filas.length === 1 ? '' : 's' }} ·
                  Bs {{ money(seccion.total) }}
                </q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>
      </div>
      <div class="col-auto">
        <q-btn flat dense padding="6px 8px" color="primary" icon="local_shipping" to="/caminero/entregas">
          <q-tooltip>Mis entregas</q-tooltip>
        </q-btn>
      </div>
    </div>

    <div v-if="cargando" class="flex flex-center q-pa-lg">
      <q-spinner color="primary" size="42px"/>
    </div>

    <div v-else>
      <q-card flat bordered class="q-mb-xs rounded-borders">
        <div class="row items-center no-wrap q-px-xs bg-grey-3 barra-titulo">
          <div class="text-weight-bold text-grey-8 ellipsis">{{ despachador }} · {{ placa }}</div>
          <q-space/>
          <div class="text-weight-bolder text-grey-9">{{ fechaLarga }}</div>
        </div>
        <q-linear-progress
          size="14px" :value="avance.porcentaje / 100"
          :color="avance.pendientes ? 'orange-7' : 'positive'" track-color="grey-4"
        >
          <div class="absolute-full flex flex-center">
            <span class="texto-barra text-grey-9">
              {{ avance.cerradas }}/{{ avance.pedidos }} notas · {{ avance.porcentaje }}%
            </span>
          </div>
        </q-linear-progress>
        <!-- Lo que se rinde en caja: el mixto ya viene partido por via, asi
             que estas dos cifras son el dinero real que lleva encima. -->
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
            <div class="caja-rotulo">PENDIENTES</div>
            <div class="caja-monto text-orange-9">{{ avance.pendientes }}</div>
          </div>
        </div>
      </q-card>

      <div class="row q-col-gutter-xs">
        <div v-for="seccion in secciones" :key="seccion.clave" class="col-12 col-md-6">
          <q-card flat bordered class="rounded-borders">
            <div class="row items-center no-wrap q-px-xs seccion-titulo" :class="seccion.fondo">
              <div class="text-weight-bolder">{{ seccion.titulo }}</div>
              <q-space/>
              <div class="text-weight-bolder">
                {{ seccion.filas.length }} · Bs {{ money(seccion.total) }}
              </div>
            </div>

            <div v-if="!seccion.filas.length" class="text-center text-grey-6 vacio">Sin registros</div>

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
                <tr v-for="(fila, indice) in seccion.filas" :key="fila.id">
                  <td class="col-n">{{ indice + 1 }}</td>
                  <td class="col-nota">{{ fila.nota }}</td>
                  <td class="ellipsis">
                    {{ fila.cliente || 'Sin cliente' }}
                    <span v-if="seccion.clave === 'mixtos'" class="desglose">
                      (Ef {{ money(fila.monto_efectivo) }} · QR {{ money(fila.monto_qr) }})
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
                  <td :colspan="seccion.clave === 'anulados' ? 4 : 3" class="text-right text-weight-bolder">TOTALES</td>
                  <td class="col-monto text-weight-bolder">{{ money(seccion.total) }}</td>
                </tr>
              </tfoot>
            </table>
          </q-card>
        </div>
      </div>
    </div>
  </q-page>
</template>

<script>
import { date } from 'quasar'
import { imprimirPdfDirecto } from 'src/utils/impresion.js'

export default {
  name: 'ReporteEntregasCaminero',
  data () {
    return {
      fecha: this.$route.query.fecha || date.formatDate(new Date(), 'YYYY-MM-DD'),
      cargando: false,
      imprimiendo: false,
      placa: '',
      despachador: '',
      grupos: { contados: [], qr: [], mixtos: [], creditos: [], anulados: [] },
      totales: { contados: 0, qr: 0, mixtos: 0, creditos: 0, anulados: 0, efectivo: 0, qr_cobrado: 0 },
      avance: { pedidos: 0, cerradas: 0, cobradas: 0, pendientes: 0, porcentaje: 0 }
    }
  },
  created () {
    this.cargar()
  },
  computed: {
    // Las mismas hojas que hoy se entregan en papel, en el mismo orden.
    secciones () {
      return [
        { clave: 'contados', titulo: 'CONTADOS DEL DÍA', icono: 'payments', color: 'green-8', fondo: 'bg-green-2 text-green-10', filas: this.grupos.contados, total: this.totales.contados },
        { clave: 'qr', titulo: 'PAGOS QR', icono: 'qr_code_2', color: 'indigo-8', fondo: 'bg-indigo-2 text-indigo-10', filas: this.grupos.qr, total: this.totales.qr },
        { clave: 'mixtos', titulo: 'MIXTOS', icono: 'call_split', color: 'teal-8', fondo: 'bg-teal-2 text-teal-10', filas: this.grupos.mixtos, total: this.totales.mixtos },
        { clave: 'creditos', titulo: 'CRÉDITOS', icono: 'schedule', color: 'blue-grey-8', fondo: 'bg-blue-grey-2 text-blue-grey-10', filas: this.grupos.creditos, total: this.totales.creditos },
        { clave: 'anulados', titulo: 'ANULADOS', icono: 'cancel', color: 'red-8', fondo: 'bg-red-2 text-red-10', filas: this.grupos.anulados, total: this.totales.anulados }
      ]
    },
    fechaLarga () {
      return date.formatDate(this.fecha + 'T00:00:00', 'dddd, D [DE] MMMM [DE] YYYY').toUpperCase()
    }
  },
  methods: {
    money (valor) {
      return Number(valor || 0).toFixed(2)
    },
    /**
     * Baja una hoja del recojo y la manda directo a la impresora.
     *
     * El PDF lo arma el backend con el formato de la hoja de papel —titulo,
     * fecha larga, notas, total y la firma al pie— para que lo que se firma
     * sea siempre igual, se imprima desde el celular o desde la oficina.
     */
    hoja (grupo) {
      this.imprimiendo = true

      this.$api.get('caminero/reporte/pdf', {
        params: { fecha: this.fecha, grupo }, responseType: 'blob'
      })
        .then(res => imprimirPdfDirecto(res.data, grupo + '_' + this.fecha + '.pdf'))
        .catch(async err => {
          let mensaje = 'No se pudo generar la hoja'
          // El error viaja como blob por el responseType: hay que leerlo.
          try {
            mensaje = JSON.parse(await err.response.data.text()).message || mensaje
          } catch (e) { /* el error no vino en JSON */ }
          this.$q.notify({ type: 'negative', position: 'top', message: mensaje })
        })
        .finally(() => { this.imprimiendo = false })
    },
    cargar () {
      this.cargando = true
      this.$api.get('caminero/reporte', { params: { fecha: this.fecha } })
        .then(res => {
          this.placa = res.data.placa
          this.despachador = res.data.despachador
          this.grupos = res.data.grupos
          this.totales = res.data.totales
          this.avance = res.data.avance
        })
        .catch(err => {
          this.$q.notify({
            type: 'negative',
            position: 'top',
            message: err.response?.data?.message || 'No se pudo cargar tu reporte'
          })
        })
        .finally(() => { this.cargando = false })
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
.texto-barra {
  font-size: 10px;
  font-weight: 700;
  line-height: 1;
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
.seccion-titulo {
  height: 20px;
  font-size: 11px;
}
.vacio {
  font-size: 11px;
  padding: 6px 0;
}

/* Tabla propia y no q-table: son listas de leer y sumar, y asi cada fila
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

@media print {
  .no-print {
    display: none;
  }
  .tabla {
    font-size: 10px;
  }
}
</style>
