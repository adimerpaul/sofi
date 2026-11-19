<template>
  <q-page class="q-pa-xs avance-ventas">
    <!-- Filtros -->
    <div class="row q-col-gutter-xs items-center q-mb-xs">
      <div class="col-auto text-subtitle1 text-weight-bold q-mr-sm">
        {{ mio ? 'Avance de ventas vendedores' : 'Avance de ventas' }}
        <div v-if="mio && vendedorActual" class="text-caption text-grey-8">{{ $filters.capitalize(vendedorActual.nombre) }}</div>
      </div>
      <div class="col-6 col-sm-2">
        <q-input v-model="mes" type="month" label="Mes" dense outlined @update:model-value="cargar"/>
      </div>
      <div v-if="!mio" class="col-6 col-sm-3">
        <q-select v-model="alcance" :options="opcionesAlcance" label="Vendedor" dense outlined emit-value map-options/>
      </div>
      <div v-if="!mio" class="col-auto">
        <q-btn dense no-caps color="primary" icon="save" label="Guardar objetivos" :disable="!cambios"
               :loading="guardando" @click="guardar"/>
      </div>
      <div class="col-auto">
        <q-btn dense no-caps color="green-8" icon="file_download" label="Excel" :loading="exportando" @click="excel"/>
      </div>
      <div v-if="!mio" class="col-auto">
        <q-btn dense no-caps flat color="grey-8" icon="tune" label="Líneas" @click="abrirLineas"/>
      </div>
      <!-- Como en la planilla AVANCE: dia del mes / dias del mes. -->
      <div v-if="ritmo" class="col-auto">
        <div class="objetivo-dia">
          Objetivo al día <b>{{ ritmo.dia }}/{{ ritmo.dias }}</b>:
          <b class="text-primary">{{ (ritmo.objetivo * 100).toFixed(1) }}%</b>
          <span class="leyenda-avance av-verde">llega</span>
          <span class="leyenda-avance av-amarillo">falta ≤ {{ (100 * 3 / 28).toFixed(1) }} pts</span>
          <span class="leyenda-avance av-rojo">falta más</span>
        </div>
      </div>
      <q-space/>
      <div class="col-auto text-caption text-grey-7">
        Según lo facturado · volumen en kg (lo que va por unidad, por el peso de cada unidad)
      </div>
    </div>

    <div class="row q-col-gutter-xs">
      <!-- Formato -->
      <div class="col-12 col-md-7">
        <q-markup-table flat bordered dense separator="cell" class="formato">
          <thead>
          <tr>
            <th class="text-left">DESCRIPCIÓN</th>
            <th>OBJETIVO KG</th>
            <th>VOLUMEN VENDIDO KG</th>
            <th>AVANCE %</th>
            <th>COBERTURA</th>
            <th>VENDIDO Bs</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="f in filas" :key="f.clave" :class="'fila-' + f.tipo">
            <td class="text-left nombre">{{ f.nombre }}</td>
            <td class="text-right" :class="f.tipo === 'seccion' ? 'amarillo' : ''">
              <!-- Solo las lineas llevan objetivo; seccion y total son la suma. -->
              <input v-if="f.tipo === 'linea' && !mio" type="number" min="0" step="0.01" class="entrada-objetivo"
                     v-model="edicion[f.id]" :placeholder="f.sugerido ? numero(f.sugerido) : ''"
                     :title="f.sugerido ? 'Sin objetivo general: suma de los vendedores' : ''">
              <template v-else>{{ f.objetivo ? numero(f.objetivo) : '' }}</template>
            </td>
            <td class="text-right" :class="f.tipo === 'seccion' ? 'amarillo' : ''">{{ numero(f.vol) }}<span v-if="f.porCantidad" class="text-grey-7"> U</span></td>
            <td class="text-right" :class="f.avance !== null ? colorAvance(f.avance) : (f.tipo === 'seccion' ? 'amarillo' : '')">
              <span v-if="f.avance !== null">{{ (f.avance * 100).toFixed(1) }}%</span>
            </td>
            <td class="text-center">{{ f.cobertura || '' }}</td>
            <td class="text-right text-grey-8">{{ f.bs ? numero(f.bs) : '' }}</td>
          </tr>
          <tr v-if="!filas.length">
            <td colspan="6" class="text-center text-grey-7">{{ cargando ? 'Cargando…' : 'Sin datos' }}</td>
          </tr>
          </tbody>
        </q-markup-table>
      </div>

      <!-- Vendedores -->
      <div class="col-12 col-md-5">
        <q-markup-table flat bordered dense separator="cell" class="formato">
          <thead>
          <tr>
            <th>#</th>
            <th class="text-left">VENDEDOR</th>
            <th>OBJETIVO</th>
            <th>VENDIDO</th>
            <th>AVANCE %</th>
            <th>COB.</th>
            <th>Bs</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="(v, i) in resumenVendedores" :key="v.clave" :class="[mio ? '' : 'cursor-pointer', alcance === v.clave && !mio ? 'bg-blue-1' : '']"
              @click="elegirVendedor(v)">
            <td class="text-center">{{ i + 1 }}</td>
            <td class="text-left ellipsis" style="max-width: 150px">{{ $filters.capitalize(v.nombre) }}</td>
            <td class="text-right">{{ v.objetivo ? numero(v.objetivo) : '—' }}</td>
            <td class="text-right">{{ numero(v.vol) }}</td>
            <!-- Fondo segun el objetivo del dia y barra de datos azul, como la planilla. -->
            <td class="avance-celda" :class="v.avance !== null ? colorAvance(v.avance) : ''">
              <template v-if="v.avance !== null">
                <q-linear-progress :value="Math.min(v.avance, 1)" size="14px" rounded
                                   color="blue-5" track-color="transparent">
                  <div class="absolute-full flex flex-center text-weight-bold texto-barra">
                    {{ (v.avance * 100).toFixed(1) }}%
                  </div>
                </q-linear-progress>
              </template>
              <span v-else class="text-grey-6">sin objetivo</span>
            </td>
            <td class="text-center">{{ v.cobertura }}</td>
            <td class="text-right text-grey-8">{{ numero(v.bs) }}</td>
          </tr>
          <tr v-if="mio"></tr>
          <tr v-else-if="resumenVendedores.length" class="fila-total">
            <td></td>
            <td class="text-left">TOTAL</td>
            <td class="text-right">{{ totalGeneral.objetivo ? numero(totalGeneral.objetivo) : '—' }}</td>
            <td class="text-right">{{ numero(totalGeneral.vol) }}</td>
            <td class="text-right" :class="totalGeneral.avance !== null ? colorAvance(totalGeneral.avance) : ''">
              <span v-if="totalGeneral.avance !== null">{{ (totalGeneral.avance * 100).toFixed(1) }}%</span>
            </td>
            <td class="text-center">{{ totalGeneral.cobertura }}</td>
            <td class="text-right">{{ numero(totalGeneral.bs) }}</td>
          </tr>
          <tr v-else>
            <td colspan="7" class="text-center text-grey-7">Sin ventas en el mes</td>
          </tr>
          </tbody>
        </q-markup-table>
        <div class="text-caption text-grey-7 q-mt-xs">
          <template v-if="mio">Tu avance del mes contra el objetivo que te pusieron. </template>
          <template v-else>Toca un vendedor para ver y cargar su objetivo por línea. </template>
          Cobertura: clientes distintos que compraron.
        </div>
      </div>
    </div>

    <!-- Que grupos y productos junta cada linea -->
    <q-dialog v-model="dialogoLineas">
      <q-card style="width: 760px; max-width: 95vw">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-subtitle1 text-weight-bold">Líneas del avance</div>
          <q-space/>
          <q-btn icon="close" flat round dense v-close-popup/>
        </q-card-section>
        <q-card-section class="text-caption text-grey-7 q-py-xs">
          Cada línea suma los productos de sus grupos. Un producto puesto suelto manda sobre su grupo.
        </q-card-section>
        <q-card-section class="q-pt-none" style="max-height: 70vh; overflow: auto">
          <div v-for="l in lineasEdicion" :key="l.id" class="row q-col-gutter-xs items-center q-mb-xs">
            <div class="col-12 col-sm-3 text-caption text-weight-bold">
              {{ l.nombre }}<div class="text-grey-6">{{ l.seccion }}</div>
            </div>
            <div class="col-12 col-sm-4">
              <q-select v-model="l.grupos" :options="grupos" option-value="cod_grup" option-label="grupo"
                        emit-value map-options multiple use-chips dense outlined label="Grupos" options-dense/>
            </div>
            <div class="col-10 col-sm-4">
              <q-select v-model="l.productos" :options="opcionesProducto" multiple use-chips use-input dense outlined
                        label="Productos sueltos" input-debounce="300" emit-value map-options options-dense
                        option-value="cod_prod" :option-label="o => o.cod_prod + ' · ' + o.producto"
                        @filter="buscarProductos"/>
            </div>
            <div class="col-2 col-sm-1">
              <q-btn dense flat round icon="save" color="primary" :loading="l.guardando" @click="guardarLinea(l)"/>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import moment from 'moment'

export default {
  name: 'AvanceVentas',
  data () {
    return {
      mes: moment().format('YYYY-MM'),
      alcance: 'g',
      datos: null,
      edicion: {},
      original: {},
      cargando: false,
      guardando: false,
      exportando: false,
      dialogoLineas: false,
      lineasEdicion: [],
      grupos: [],
      opcionesProducto: []
    }
  },
  mounted () {
    this.cargar()
  },
  computed: {
    // Ruta /avanceventasvendedores: solo lectura y solo lo del usuario logueado.
    mio () {
      return !!this.$route.meta.mio
    },
    vendedores () {
      return this.datos ? this.datos.vendedores : []
    },
    // { dia, dias, objetivo }: avance esperado a la fecha (AvanceVentasController::ritmo).
    ritmo () {
      return this.datos ? this.datos.ritmo : null
    },
    opcionesAlcance () {
      return [{ label: 'General (todos)', value: 'g' }].concat(
        this.vendedores.map(v => ({ label: this.$filters.capitalize(v.nombre), value: v.clave })))
    },
    vendedorActual () {
      return this.vendedores.find(v => v.clave === this.alcance) || null
    },
    // Objetivos se guardan por CodAut; 0 es el general.
    vendedorId () {
      return this.vendedorActual ? this.vendedorActual.id : 0
    },
    cambios () {
      if (this.alcance !== 'g' && !this.vendedorId) return false
      return Object.keys({ ...this.edicion, ...this.original })
        .some(k => this.valor(this.edicion[k]) !== this.valor(this.original[k]))
    },
    filas () {
      if (!this.datos) return []
      return this.calcularFilas(this.alcance, this.vendedorId, this.edicion)
    },
    resumenVendedores () {
      if (!this.datos) return []
      return this.vendedores.map(v => {
        const objetivos = v.clave === this.alcance ? this.edicion : this.objetivosDe(v.id)
        const total = this.calcularFilas(v.clave, v.id, objetivos).slice(-1)[0]
        return { ...v, ...total }
      })
    },
    totalGeneral () {
      if (!this.datos) return {}
      const objetivos = this.alcance === 'g' ? this.edicion : this.objetivosDe(0)
      return this.calcularFilas('g', 0, objetivos).slice(-1)[0]
    }
  },
  watch: {
    alcance () {
      this.prepararEdicion()
    },
    // /avanceventas y /avanceventasvendedores usan este mismo componente.
    mio () {
      this.alcance = 'g'
      this.datos = null
      this.cargar()
    }
  },
  methods: {
    elegirVendedor (v) {
      if (this.mio) return
      this.alcance = this.alcance === v.clave ? 'g' : v.clave
    },
    valor (v) {
      const n = parseFloat(v)
      return isNaN(n) || n <= 0 ? 0 : Math.round(n * 100) / 100
    },
    numero (n) {
      return (parseFloat(n) || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    // Reglas de la planilla AVANCE: verde si llega al objetivo del dia, amarillo
    // si le faltan hasta 3/28, rojo si le falta mas.
    colorAvance (a) {
      const obj = this.ritmo ? this.ritmo.objetivo : 1
      if (a >= obj - 0.0005) return 'av-verde'
      if (a >= obj - 3 / 28) return 'av-amarillo'
      return 'av-rojo'
    },
    objetivosDe (vendedorId) {
      return (this.datos && this.datos.objetivos[vendedorId]) || {}
    },
    // Mismas reglas que AvanceVentasController::filas: lineas, subtotal por
    // seccion y avance total. En el general, la linea sin objetivo propio toma
    // la suma de los objetivos de los vendedores.
    calcularFilas (clave, vendedorId, objetivos) {
      const res = this.datos.resultados[clave] || {}
      const vacio = { vol: 0, bs: 0, cobertura: 0 }
      const sumaVend = {}
      if (clave === 'g') {
        Object.keys(this.datos.objetivos).forEach(vid => {
          if (Number(vid) === 0) return
          Object.entries(this.datos.objetivos[vid]).forEach(([lid, v]) => {
            sumaVend[lid] = (sumaVend[lid] || 0) + Number(v)
          })
        })
      }
      const filas = []
      const secciones = []
      this.datos.lineas.forEach(l => {
        let s = secciones.find(x => x.nombre === l.seccion)
        if (!s) { s = { nombre: l.seccion, lineas: [] }; secciones.push(s) }
        s.lineas.push(l)
      })
      let totObj = 0
      secciones.forEach(s => {
        let secObj = 0
        s.lineas.forEach(l => {
          const propio = this.valor(objetivos[l.id])
          const sugerido = clave === 'g' ? (sumaVend[l.id] || 0) : 0
          const objetivo = propio || sugerido
          const r = (res.lineas && res.lineas[l.id]) || vacio
          filas.push({
            tipo: 'linea', clave: 'l' + l.id, id: l.id, nombre: l.nombre, porCantidad: l.por_cantidad, objetivo, sugerido: propio ? 0 : sugerido,
            vol: r.vol, bs: r.bs, cobertura: r.cobertura, avance: objetivo > 0 ? r.vol / objetivo : null
          })
          secObj += objetivo
        })
        const r = (res.secciones && res.secciones[s.nombre]) || vacio
        filas.push({
          tipo: 'seccion', clave: 's' + s.nombre, nombre: s.nombre, objetivo: secObj,
          vol: r.vol, bs: r.bs, cobertura: r.cobertura, avance: secObj > 0 ? r.vol / secObj : null
        })
        totObj += secObj
      })
      const r = (res.total && res.total.total) || vacio
      filas.push({
        tipo: 'total', clave: 'total', nombre: 'AVANCE TOTAL', objetivo: totObj,
        vol: r.vol, bs: r.bs, cobertura: r.cobertura, avance: totObj > 0 ? r.vol / totObj : null
      })
      return filas
    },
    prepararEdicion () {
      const base = this.objetivosDe(this.vendedorId)
      const edicion = {}
      ;(this.datos ? this.datos.lineas : []).forEach(l => {
        edicion[l.id] = base[l.id] ? Number(base[l.id]) : ''
      })
      this.original = { ...edicion }
      this.edicion = edicion
    },
    aplicar (data) {
      this.datos = data
      // En "Avance de ventas vendedores" el backend manda solo al usuario logueado.
      if (this.mio) this.alcance = data.vendedores.length ? data.vendedores[0].clave : 'g'
      else if (this.alcance !== 'g' && !data.vendedores.some(v => v.clave === this.alcance)) this.alcance = 'g'
      this.prepararEdicion()
    },
    cargar () {
      if (!this.mes) return
      this.cargando = true
      this.$api.get(this.mio ? 'mi-avance-ventas' : 'avance-ventas', { params: { mes: this.mes } })
        .then(res => this.aplicar(res.data))
        .catch(err => this.error(err, 'No se pudo cargar el avance'))
        .finally(() => { this.cargando = false })
    },
    guardar () {
      if (this.alcance !== 'g' && !this.vendedorId) {
        this.$q.notify({ type: 'warning', message: 'Ese vendedor no está registrado en personal' })
        return
      }
      this.guardando = true
      const objetivos = this.datos.lineas.map(l => ({ linea_id: l.id, objetivo: this.valor(this.edicion[l.id]) }))
      this.$api.post('avance-ventas/objetivos', { mes: this.mes, vendedor_id: this.vendedorId, objetivos })
        .then(res => {
          this.aplicar(res.data)
          this.$q.notify({ type: 'positive', message: 'Objetivos guardados' })
        })
        .catch(err => this.error(err, 'No se pudieron guardar los objetivos'))
        .finally(() => { this.guardando = false })
    },
    async excel () {
      this.exportando = true
      try {
        const ruta = this.mio ? 'mi-avance-ventas/excel' : 'avance-ventas/excel'
        const { data } = await this.$api.get(ruta, { params: { mes: this.mes }, responseType: 'blob' })
        const url = URL.createObjectURL(data)
        const a = document.createElement('a')
        a.href = url
        a.download = 'avance_ventas_' + this.mes + '.xlsx'
        a.click()
        URL.revokeObjectURL(url)
      } catch (err) {
        let mensaje = 'No se pudo generar el Excel'
        try {
          mensaje = JSON.parse(await err.response.data.text()).message || mensaje
        } catch (e) { /* el error no vino en JSON */ }
        this.$q.notify({ type: 'negative', message: mensaje })
      } finally {
        this.exportando = false
      }
    },
    abrirLineas () {
      this.lineasEdicion = (this.datos ? this.datos.lineas : []).map(l => ({
        ...l, grupos: [...l.grupos], productos: [...l.productos], guardando: false
      }))
      // Los productos ya puestos tienen que estar en las opciones para mostrarse.
      this.opcionesProducto = this.lineasEdicion.flatMap(l => l.productos.map(c => ({ cod_prod: c, producto: '' })))
      this.dialogoLineas = true
      if (!this.grupos.length) {
        this.$api.get('avance-ventas/grupos').then(res => { this.grupos = res.data })
      }
    },
    buscarProductos (val, update) {
      if (!val || val.length < 2) { update(); return }
      this.$api.get('avance-ventas/productos', { params: { q: val } })
        .then(res => update(() => {
          const elegidos = this.opcionesProducto.filter(o => this.lineasEdicion.some(l => l.productos.includes(o.cod_prod)))
          this.opcionesProducto = elegidos.concat(res.data.filter(p => !elegidos.some(e => e.cod_prod === p.cod_prod)))
        }))
        .catch(() => update())
    },
    guardarLinea (l) {
      l.guardando = true
      this.$api.put('avance-ventas/lineas/' + l.id, { grupos: l.grupos, productos: l.productos })
        .then(() => {
          this.$q.notify({ type: 'positive', message: l.nombre + ' guardada' })
          this.cargar()
        })
        .catch(err => this.error(err, 'No se pudo guardar la línea'))
        .finally(() => { l.guardando = false })
    },
    error (err, mensaje) {
      this.$q.notify({ type: 'negative', message: (err.response && err.response.data && err.response.data.message) || mensaje })
    }
  }
}
</script>

<style scoped>
.formato :deep(th),
.formato :deep(td) {
  font-size: 11px;
  line-height: 1.15;
  padding: 2px 5px !important;
  height: auto !important;
}
.formato :deep(tr) {
  height: auto !important;
}
.formato :deep(thead th) {
  background: #4a4a2a;
  color: #fff;
  font-weight: 700;
  text-align: center;
}
.fila-seccion .nombre {
  background: #ffc000;
  font-weight: 700;
}
.fila-seccion td {
  font-weight: 700;
}
.amarillo {
  background: #ffff00;
}
.fila-total td {
  font-weight: 700;
  background: #f2f2f2;
}
.fila-total .nombre {
  text-align: center;
}
.entrada-objetivo {
  width: 90px;
  font-size: 11px;
  text-align: right;
  border: 1px solid #ccc;
  border-radius: 3px;
  padding: 1px 4px;
}
.avance-celda {
  min-width: 90px;
}
/* Colores de la planilla AVANCE (formato condicional de Excel). */
.av-verde {
  background: #c6efce !important;
  color: #006100;
  font-weight: 700;
}
.av-amarillo {
  background: #ffeb9c !important;
  color: #9c5700;
  font-weight: 700;
}
.av-rojo {
  background: #ffc7ce !important;
  color: #9c0006;
  font-weight: 700;
}
.objetivo-dia {
  font-size: 12px;
}
.leyenda-avance {
  font-size: 10px;
  padding: 1px 5px;
  border-radius: 3px;
  margin-left: 4px;
}
.texto-barra {
  font-size: 10px;
  color: #000;
}
</style>
