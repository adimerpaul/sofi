<template>
  <!-- El recojo en una sola tabla: una fila por nota y una columna por via.
       Credito y lo que falto tambien tienen columna, asi las columnas suman
       siempre el total de lo entregado. Lo usan el caminero y cobranzas. -->
  <div class="contenedor">
    <table class="tabla">
      <thead>
        <tr>
          <th class="col-n">N°</th>
          <th class="col-nota">NOTA</th>
          <th>CLIENTE</th>
          <th class="col-monto">TOTAL</th>
          <th class="col-monto text-green-9">EFECTIVO</th>
          <th class="col-monto text-indigo-9">QR</th>
          <th class="col-monto text-blue-grey-8">CRÉDITO</th>
          <th class="col-monto text-red-9">FALTA</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="!filas.length">
          <td colspan="8" class="text-center text-grey-6 vacio">
            {{ tabla.filas.length ? 'Ninguna nota coincide' : 'Sin notas este día' }}
          </td>
        </tr>
        <tr v-for="(fila, indice) in filas" :key="fila.id" :class="fila.entregada ? '' : 'sin-entregar'">
          <td class="col-n">{{ indice + 1 }}</td>
          <td class="col-nota">{{ fila.nota }}</td>
          <td class="celda-cliente">
            {{ fila.cliente || 'Sin cliente' }}
            <div v-if="fila.motivo" class="motivo">{{ fila.motivo }}</div>
          </td>
          <td class="col-monto" :class="fila.entregada ? 'text-weight-bold' : ''">{{ money(fila.monto) }}</td>
          <template v-if="fila.entregada">
            <td class="col-monto text-green-9">{{ vacio(fila.efectivo) }}</td>
            <td class="col-monto text-indigo-9">{{ vacio(fila.qr) }}</td>
            <td class="col-monto text-blue-grey-8">{{ vacio(fila.credito) }}</td>
            <td class="col-monto text-red-9">{{ vacio(fila.falta) }}</td>
          </template>
          <td v-else colspan="4" class="text-center text-red-8 estado">{{ fila.estado }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="text-right">
            TOTALES · {{ totales.notas }} {{ soloRecogido ? 'recogidas' : 'entregadas' }}
          </td>
          <td class="col-monto">{{ money(totales.monto) }}</td>
          <td class="col-monto">{{ money(totales.efectivo) }}</td>
          <td class="col-monto">{{ money(totales.qr) }}</td>
          <td class="col-monto">{{ money(totales.credito) }}</td>
          <td class="col-monto">{{ money(totales.falta) }}</td>
        </tr>
      </tfoot>
    </table>

    <!-- La cuenta que se hace en caja, escrita: si no cuadra salta a la vista. -->
    <div class="cuadre row items-center no-wrap" :class="cuadra ? 'text-green-9' : 'text-red-9'">
      <q-icon :name="cuadra ? 'check_circle' : 'error'" size="16px" class="q-mr-xs"/>
      <div class="col">
        Efectivo + QR + Crédito + Falta = <b>Bs {{ money(suma) }}</b>
        · Total {{ soloRecogido ? 'recogido' : 'entregado' }} <b>Bs {{ money(totales.monto) }}</b>
      </div>
      <div class="text-weight-bolder text-grey-9">
        A rendir: Bs {{ money(totales.efectivo + totales.qr) }}
      </div>
    </div>
    <div v-if="ocultas" class="text-caption text-grey-7 q-px-xs">
      {{ ocultas }} nota{{ ocultas === 1 ? '' : 's' }} sin plata (crédito o sin entregar) no se muestran
    </div>
    <div v-else-if="tabla.totales.no_entregadas && !soloRecogido" class="text-caption text-grey-7 q-px-xs">
      {{ tabla.totales.no_entregadas }} sin entregar por Bs {{ money(tabla.totales.monto_no_entregado) }} (no suman)
    </div>
  </div>
</template>

<script>
export default {
  name: 'TablaRecojo',
  props: {
    tabla: {
      type: Object,
      default: () => ({ filas: [], totales: { notas: 0, monto: 0, efectivo: 0, qr: 0, credito: 0, falta: 0 } })
    },
    // Cobranzas: solo las notas en las que entro plata (efectivo o QR); el
    // credito y lo no entregado quedan fuera. El caminero ve todo.
    soloRecogido: { type: Boolean, default: false },
    // Texto para encontrar una nota por cliente o numero.
    buscar: { type: String, default: '' }
  },
  computed: {
    filas () {
      const texto = String(this.buscar || '').trim().toLowerCase()
      return this.tabla.filas.filter(fila => {
        if (this.soloRecogido && !this.recogida(fila)) return false
        if (!texto) return true
        return String(fila.cliente || '').toLowerCase().includes(texto) || String(fila.nota).includes(texto)
      })
    },
    // Lo que no se muestra por no tener plata, para avisarlo al pie.
    ocultas () {
      return this.soloRecogido ? this.tabla.filas.filter(fila => !this.recogida(fila)).length : 0
    },
    // Sin filtro, los totales del backend; filtrando, los de lo que se ve.
    totales () {
      if (!this.soloRecogido && !String(this.buscar || '').trim()) return this.tabla.totales
      const entregadas = this.filas.filter(fila => fila.entregada)
      const sumar = campo => Math.round(entregadas.reduce((s, fila) => s + Number(fila[campo] || 0), 0) * 100) / 100
      return {
        notas: entregadas.length,
        monto: sumar('monto'),
        efectivo: sumar('efectivo'),
        qr: sumar('qr'),
        credito: sumar('credito'),
        falta: sumar('falta')
      }
    },
    suma () {
      const t = this.totales
      return Number(t.efectivo || 0) + Number(t.qr || 0) + Number(t.credito || 0) + Number(t.falta || 0)
    },
    cuadra () {
      return Math.abs(this.suma - Number(this.totales.monto || 0)) < 0.01
    }
  },
  methods: {
    money (valor) {
      return Number(valor || 0).toFixed(2)
    },
    recogida (fila) {
      return fila.entregada && (Number(fila.efectivo) > 0 || Number(fila.qr) > 0)
    },
    // Las columnas en cero van vacias: se lee de un vistazo por donde entro.
    vacio (valor) {
      return Number(valor) > 0 ? this.money(valor) : ''
    }
  }
}
</script>

<style scoped>
/* En el celular la tabla se desliza de costado en vez de apretar los montos. */
.contenedor {
  overflow-x: auto;
}
.tabla {
  width: 100%;
  min-width: 560px;
  border-collapse: collapse;
  font-size: 11px;
}
.tabla th {
  background: #eceff1;
  font-size: 9px;
  letter-spacing: 0.5px;
  text-align: left;
  padding: 2px 4px;
  white-space: nowrap;
}
.tabla td {
  padding: 2px 4px;
  border-top: 1px solid #eee;
  height: 20px;
}
.tabla tfoot td {
  border-top: 2px solid #90a4ae;
  background: #eceff1;
  font-weight: 800;
}
.col-n {
  width: 24px;
  text-align: right;
  color: #9e9e9e;
}
.col-nota {
  width: 56px;
  font-weight: 600;
}
.col-monto {
  width: 70px;
  text-align: right;
  white-space: nowrap;
}
.celda-cliente {
  white-space: normal;
  line-height: 1.2;
}
.motivo {
  font-size: 9px;
  color: #bf360c;
}
.sin-entregar td {
  background: #fafafa;
  color: #9e9e9e;
}
.estado {
  font-size: 10px;
  font-weight: 600;
}
.vacio {
  padding: 8px 0;
}
.cuadre {
  font-size: 11px;
  padding: 4px;
  border-top: 1px solid #e0e0e0;
}
</style>
