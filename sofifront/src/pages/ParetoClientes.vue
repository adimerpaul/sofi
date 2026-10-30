<template>
  <q-page class="q-pa-sm">
    <div class="text-h6 text-weight-bold">Pareto de clientes</div>
    <div class="text-caption text-grey-7 q-mb-sm">
      Busca un producto y mira qué clientes tuyos lo compraron más (según lo facturado) y cuándo fue la última vez.
      Toca un cliente para ver sus compras.
    </div>

    <div class="row q-col-gutter-sm">
      <!-- El grupo solo ya arma el pareto; elegido un producto, manda el producto. -->
      <div class="col-12 col-md-6">
        <q-select v-model="grupos" :options="opcionesGrupo" option-value="cod_grup" option-label="grupo"
                  multiple use-chips use-input input-debounce="0" dense outlined clearable
                  label="Grupo" placeholder="Todos los grupos" :loading="cargandoGrupos"
                  @filter="filtrarGrupos" @update:model-value="cambioGrupos">
          <template v-slot:prepend><q-icon name="category"/></template>
          <template v-slot:option="scope">
            <q-item v-bind="scope.itemProps">
              <q-item-section>
                <q-item-label>{{ scope.opt.grupo }}</q-item-label>
                <q-item-label caption>{{ scope.opt.clientes }} clientes</q-item-label>
              </q-item-section>
            </q-item>
          </template>
        </q-select>
      </div>
      <div class="col-12 col-md-6">
        <q-select v-model="productos" :options="opciones" option-value="cod_prod" option-label="producto"
                  multiple use-chips use-input input-debounce="300" dense outlined clearable
                  label="Producto" placeholder="Escribe para buscar…" :loading="buscando"
                  @filter="buscarProductos" @update:model-value="consultar">
          <template v-slot:prepend><q-icon name="search"/></template>
          <template v-slot:option="scope">
            <q-item v-bind="scope.itemProps">
              <q-item-section>
                <q-item-label>{{ scope.opt.producto }}</q-item-label>
                <q-item-label caption>{{ scope.opt.cod_prod }} · {{ scope.opt.grupo }} · {{ scope.opt.clientes }} clientes</q-item-label>
              </q-item-section>
            </q-item>
          </template>
          <template v-slot:no-option>
            <q-item><q-item-section class="text-grey-7">No vendiste ese producto en estas fechas</q-item-section></q-item>
          </template>
        </q-select>
      </div>
      <div class="col-6 col-md-3">
        <q-input v-model="desde" type="date" dense outlined label="Desde" @update:model-value="cambioFechas"/>
      </div>
      <div class="col-6 col-md-3">
        <q-input v-model="hasta" type="date" dense outlined label="Hasta" @update:model-value="cambioFechas"/>
      </div>
    </div>

    <div class="row items-center q-gutter-sm q-mt-xs">
      <q-btn-toggle v-model="medida" dense no-caps unelevated rounded toggle-color="primary" color="grey-3"
                    text-color="grey-9" :options="[
                      { label: 'Por peso', value: 'peso', icon: 'scale' },
                      { label: 'Por cantidad', value: 'cantidad', icon: 'tag' }
                    ]" @update:model-value="consultar"/>
      <q-btn-toggle v-model="orden" dense no-caps unelevated rounded toggle-color="teal" color="grey-3"
                    text-color="grey-9" :options="[
                      { label: 'Más compra', value: 'mas' },
                      { label: 'Más reciente', value: 'reciente' }
                    ]"/>
    </div>

    <div v-if="!hayFiltro" class="text-center text-grey-6 q-pa-xl">
      <q-icon name="manage_search" size="64px"/>
      <div class="q-mt-sm">Elige un grupo o un producto para ver a quién se lo vendiste</div>
    </div>

    <template v-else>
      <div class="row q-col-gutter-sm q-mt-xs">
        <div class="col-4">
          <q-card flat bordered class="tarjeta">
            <div class="tarjeta-titulo">Clientes</div>
            <div class="tarjeta-valor">{{ datos.totales.clientes }}</div>
          </q-card>
        </div>
        <div class="col-4">
          <q-card flat bordered class="tarjeta">
            <div class="tarjeta-titulo">Clase A</div>
            <div class="tarjeta-valor text-positive">{{ datos.totales.clase_a }}</div>
            <div class="tarjeta-sub">el 80%</div>
          </q-card>
        </div>
        <div class="col-4">
          <q-card flat bordered class="tarjeta">
            <div class="tarjeta-titulo">{{ medida === 'peso' ? 'Peso kg' : 'Cantidad' }}</div>
            <div class="tarjeta-valor">{{ numero(datos.totales[medida]) }}</div>
          </q-card>
        </div>
      </div>

      <q-input v-model="buscar" dense outlined clearable placeholder="Buscar cliente, zona o dirección"
               class="q-mt-sm">
        <template v-slot:append><q-icon name="search"/></template>
      </q-input>

      <q-inner-loading :showing="cargando"/>

      <div v-if="!cargando && !visibles.length" class="text-center text-grey-6 q-pa-lg">
        Ningún cliente con eso en estas fechas
      </div>

      <q-list bordered separator class="rounded-borders q-mt-sm">
        <q-item v-for="f in visibles" :key="f.cliente_id" class="q-py-sm" clickable v-ripple @click="verDetalle(f)">
          <q-item-section avatar top>
            <q-avatar :color="colorClase(f.clase)" text-color="white" size="34px" class="text-weight-bold">
              {{ f.puesto }}
            </q-avatar>
            <q-badge :color="colorClase(f.clase)" outline :label="'Clase ' + f.clase" class="q-mt-xs"/>
          </q-item-section>
          <q-item-section>
            <q-item-label class="text-weight-bold">{{ f.cliente }}</q-item-label>
            <q-item-label caption>
              <span v-if="f.zona">{{ f.zona }}</span><span v-if="f.zona && f.direccion"> · </span>{{ f.direccion }}
            </q-item-label>
            <q-linear-progress :value="maximo ? f[medida] / maximo : 0" :color="colorClase(f.clase)"
                               track-color="grey-3" rounded size="6px" class="q-mt-xs"/>
            <q-item-label caption class="q-mt-xs">
              <q-icon name="history" size="14px"/>
              Última vez: <b>{{ fechaCorta(f.ultima_fecha) }}</b>
              <span :class="f.dias > 14 ? 'text-negative' : 'text-grey-7'">({{ hace(f.dias) }})</span>
              · {{ numero(f.ultima_cantidad) }} {{ f.ultima_unidad }}
            </q-item-label>
            <q-item-label caption>
              {{ f.comprobantes }} compras · Bs {{ bs(f.importe) }} · {{ f.porcentaje }}% · acumulado {{ f.acumulado }}%
            </q-item-label>
          </q-item-section>
          <q-item-section side top class="text-right">
            <q-item-label class="text-subtitle1 text-weight-bold text-grey-9">{{ numero(f[medida]) }}</q-item-label>
            <q-item-label caption>{{ medida === 'peso' ? 'kg' : f.unidades }}</q-item-label>
            <q-btn v-if="telefono(f)" round dense flat color="green-7" icon="call" type="a" :href="'tel:' + telefono(f)"
                   class="q-mt-xs" @click.stop>
              <q-tooltip>{{ f.telefono }}</q-tooltip>
            </q-btn>
          </q-item-section>
        </q-item>
      </q-list>
    </template>

    <!-- Lo facturado al cliente con los mismos filtros, el comprobante mas reciente arriba. -->
    <q-dialog v-model="detalle.abierto" :maximized="$q.screen.lt.sm">
      <q-card style="width: 640px; max-width: 100vw">
        <q-card-section class="row items-start no-wrap q-pb-sm">
          <div class="col">
            <div class="text-subtitle1 text-weight-bold">{{ detalle.cliente.cliente }}</div>
            <div class="text-caption text-grey-7">
              {{ detalle.cliente.zona }}<span v-if="detalle.cliente.zona && detalle.cliente.direccion"> · </span>{{ detalle.cliente.direccion }}
            </div>
            <div class="text-caption text-grey-7">{{ fechaCorta(desde) }} al {{ fechaCorta(hasta) }} · {{ filtroTexto }}</div>
          </div>
          <q-btn v-if="telefono(detalle.cliente)" round dense flat color="green-7" icon="call" type="a"
                 :href="'tel:' + telefono(detalle.cliente)"/>
          <q-btn round dense flat icon="close" v-close-popup/>
        </q-card-section>

        <q-card-section class="row q-col-gutter-sm q-pt-none">
          <div class="col-4">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Con el producto</div>
              <div class="tarjeta-valor">{{ detalle.cliente.comprobantes }}</div>
            </q-card>
          </div>
          <div class="col-4">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Peso kg</div>
              <div class="tarjeta-valor">{{ numero(detalle.cliente.peso) }}</div>
            </q-card>
          </div>
          <div class="col-4">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Importe Bs</div>
              <div class="tarjeta-valor text-positive">{{ bs(detalle.cliente.importe) }}</div>
            </q-card>
          </div>
        </q-card-section>

        <q-card-section class="q-pt-none q-pb-sm row items-center q-gutter-sm">
          <q-btn-toggle v-model="detalle.todo" dense no-caps unelevated rounded toggle-color="primary" color="grey-3"
                        text-color="grey-9" :options="[
                          { label: 'Con el producto', value: false },
                          { label: 'Todas sus compras', value: true }
                        ]" @update:model-value="cargarDetalle"/>
          <span v-if="!detalle.cargando" class="text-caption text-grey-7">
            {{ detalle.comprobantes.length }} compras<template v-if="detalle.todo"> · {{ conProducto }} con el producto</template>
          </span>
        </q-card-section>

        <q-card-section class="q-pt-none detalle-cuerpo">
          <q-inner-loading :showing="detalle.cargando"/>
          <div v-if="!detalle.cargando && !detalle.comprobantes.length" class="text-center text-grey-6 q-pa-md">
            Sin compras en estas fechas
          </div>
          <q-card v-for="comp in detalle.comprobantes" :key="comp.factura_id" flat bordered class="q-mb-sm"
                  :class="{ 'comp-coincide': comp.coincide }">
            <q-item dense :class="comp.coincide ? 'bg-amber-1' : 'bg-grey-2'">
              <q-item-section>
                <q-item-label class="text-weight-bold">
                  {{ fechaCorta(comp.fecha_entrega) }} · {{ comp.comprobante }}
                </q-item-label>
                <q-item-label caption>
                  Emitido {{ comp.emitido }} · {{ comp.tipo_pago }}<span v-if="comp.placa"> · {{ comp.placa }}</span>
                </q-item-label>
              </q-item-section>
              <q-item-section side class="text-right">
                <q-item-label class="text-weight-bold text-grey-9">Bs {{ bs(comp.total) }}</q-item-label>
                <q-item-label v-if="comp.coincide && comp.importe !== comp.total" caption class="text-amber-10">
                  producto: Bs {{ bs(comp.importe) }}
                </q-item-label>
              </q-item-section>
            </q-item>
            <q-markup-table flat dense separator="horizontal" class="tabla-lineas">
              <thead>
              <tr>
                <th class="text-left">Producto</th>
                <th class="text-right">Cant.</th>
                <th class="text-right">Peso kg</th>
                <th class="text-right">Precio</th>
                <th class="text-right">Importe</th>
              </tr>
              </thead>
              <tbody>
              <tr v-for="(l, i) in comp.lineas" :key="i" :class="{ 'linea-coincide': l.coincide }">
                <td class="text-left producto">{{ l.producto }}</td>
                <td class="text-right">{{ numero(l.cantidad) }} {{ l.unidad }}</td>
                <td class="text-right">{{ numero(l.peso) }}</td>
                <td class="text-right">{{ bs(l.precio) }}</td>
                <td class="text-right text-weight-medium">{{ bs(l.importe) }}</td>
              </tr>
              </tbody>
            </q-markup-table>
          </q-card>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { date } from 'quasar'

export default {
  name: 'ParetoClientes',
  data () {
    return {
      grupos: [],
      todosGrupos: [],
      opcionesGrupo: [],
      cargandoGrupos: false,
      productos: [],
      opciones: [],
      buscando: false,
      // Por defecto, el mes en curso hasta hoy.
      desde: date.formatDate(Date.now(), 'YYYY-MM') + '-01',
      hasta: date.formatDate(Date.now(), 'YYYY-MM-DD'),
      medida: 'peso',
      orden: 'mas',
      buscar: '',
      cargando: false,
      // Por defecto solo las compras con el producto, completas y con lo filtrado resaltado.
      detalle: { abierto: false, cargando: false, todo: false, cliente: {}, comprobantes: [] },
      datos: { filas: [], totales: { clientes: 0, clase_a: 0, peso: 0, cantidad: 0 } }
    }
  },
  created () {
    this.cargarGrupos()
  },
  computed: {
    // clearable deja los select en null.
    codGrupos () {
      return (this.grupos || []).map(g => g.cod_grup)
    },
    codProductos () {
      return (this.productos || []).map(p => p.cod_prod)
    },
    hayFiltro () {
      return this.codGrupos.length > 0 || this.codProductos.length > 0
    },
    conProducto () {
      return this.detalle.comprobantes.filter(c => c.coincide).length
    },
    // Lo que se esta mirando, para el encabezado del detalle.
    filtroTexto () {
      if (this.codProductos.length) return this.productos.map(p => p.producto).join(', ')
      return 'Grupo ' + (this.grupos || []).map(g => g.grupo).join(', ')
    },
    visibles () {
      const texto = (this.buscar || '').toLowerCase()
      let lista = this.datos.filas
      if (texto) {
        lista = lista.filter(f => [f.cliente, f.zona, f.direccion, f.nit].some(v => String(v || '').toLowerCase().includes(texto)))
      }
      // "Más reciente": quien lo pidio ultimo arriba; el puesto sigue siendo el del pareto.
      if (this.orden === 'reciente') {
        lista = lista.slice().sort((a, b) => b.ultima_fecha.localeCompare(a.ultima_fecha) || a.puesto - b.puesto)
      }
      return lista
    },
    maximo () {
      return this.datos.filas.length ? this.datos.filas[0][this.medida] : 0
    }
  },
  methods: {
    paramsFechas () {
      return { desde: this.desde, hasta: this.hasta }
    },
    cargarGrupos () {
      this.cargandoGrupos = true
      this.$api.get('pareto-clientes/grupos', { params: this.paramsFechas() })
        .then(res => { this.todosGrupos = res.data })
        .catch(() => { this.todosGrupos = [] })
        .finally(() => { this.cargandoGrupos = false })
    },
    filtrarGrupos (texto, actualizar) {
      actualizar(() => {
        const t = (texto || '').toLowerCase()
        this.opcionesGrupo = t ? this.todosGrupos.filter(g => g.grupo.toLowerCase().includes(t)) : this.todosGrupos
      })
    },
    // Otro grupo: los productos elegidos de fuera de el ya no van.
    cambioGrupos () {
      this.opciones = []
      if (this.codGrupos.length) {
        this.productos = (this.productos || []).filter(p => this.grupos.some(g => g.grupo === p.grupo))
      }
      this.consultar()
    },
    buscarProductos (texto, actualizar) {
      this.buscando = true
      const params = Object.assign({ buscar: texto || '', grupos: this.codGrupos }, this.paramsFechas())
      this.$api.get('pareto-clientes/productos', { params })
        .then(res => actualizar(() => { this.opciones = res.data }))
        .catch(() => actualizar(() => { this.opciones = [] }))
        .finally(() => { this.buscando = false })
    },
    cambioFechas () {
      this.opciones = []
      this.cargarGrupos()
      this.consultar()
    },
    consultar () {
      if (!this.hayFiltro) {
        this.datos = { filas: [], totales: { clientes: 0, clase_a: 0, peso: 0, cantidad: 0 } }
        return
      }
      this.cargando = true
      const params = Object.assign(
        { productos: this.codProductos, grupos: this.codGrupos, medida: this.medida }, this.paramsFechas())
      this.$api.get('pareto-clientes', { params }).then(res => {
        this.datos = res.data
      }).catch(err => {
        this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudo cargar el pareto' })
      }).finally(() => {
        this.cargando = false
      })
    },
    verDetalle (cliente) {
      this.detalle.cliente = cliente
      this.detalle.abierto = true
      this.cargarDetalle()
    },
    cargarDetalle () {
      const d = this.detalle
      d.comprobantes = []
      d.cargando = true
      const params = Object.assign(
        { productos: this.codProductos, grupos: this.codGrupos, todo: d.todo ? 1 : 0 }, this.paramsFechas())
      this.$api.get('pareto-clientes/' + d.cliente.cliente_id + '/detalle', { params }).then(res => {
        d.comprobantes = res.data.comprobantes
      }).catch(err => {
        this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudo cargar el detalle' })
      }).finally(() => {
        d.cargando = false
      })
    },
    bs (valor) {
      return Number(valor || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    colorClase (clase) {
      return clase === 'A' ? 'positive' : clase === 'B' ? 'orange-8' : 'grey-6'
    },
    // El primer numero del campo de telefono (a veces vienen dos).
    telefono (f) {
      const m = String(f.telefono || '').match(/\d{7,}/)
      return m ? m[0] : ''
    },
    fechaCorta (fecha) {
      return fecha ? date.formatDate(fecha + 'T00:00:00', 'DD/MM/YYYY') : ''
    },
    hace (dias) {
      if (dias <= 0) return 'hoy'
      if (dias === 1) return 'ayer'
      return 'hace ' + dias + ' días'
    },
    numero (valor) {
      return +Number(valor || 0).toFixed(2)
    }
  }
}
</script>

<style scoped>
.tarjeta {
  padding: 4px 8px;
  text-align: center;
  line-height: 1.2;
}
.tarjeta-titulo {
  font-size: 10px;
  text-transform: uppercase;
  color: rgba(0, 0, 0, 0.55);
}
.tarjeta-valor {
  font-size: 18px;
  font-weight: 700;
}
.tarjeta-sub {
  font-size: 10px;
  color: rgba(0, 0, 0, 0.55);
}
.detalle-cuerpo {
  max-height: 60vh;
  overflow-y: auto;
  position: relative;
  min-height: 80px;
}
.tabla-lineas th,
.tabla-lineas td {
  font-size: 11px;
  padding: 2px 6px;
}
.tabla-lineas td.producto {
  white-space: normal;
}
/* El producto filtrado dentro del comprobante completo. */
.comp-coincide {
  border-color: #ffb300;
}
.tabla-lineas tr.linea-coincide td {
  background: #fff8e1;
  font-weight: 700;
}
</style>
<style>
/* Maximizado en el celular: el cuerpo usa todo el alto. */
.q-dialog__inner--maximized .detalle-cuerpo {
  max-height: none;
}
</style>
