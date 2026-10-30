<template>
  <q-page class="q-pa-sm">
    <div class="text-h6 text-weight-bold">Pareto de clientes</div>
    <div class="text-caption text-grey-7 q-mb-sm">
      Busca un producto y mira qué clientes tuyos lo pidieron más y cuándo fue la última vez.
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
        <q-item v-for="f in visibles" :key="f.cliente_id" class="q-py-sm">
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
            <q-item-label caption>{{ f.pedidos }} pedidos · {{ f.porcentaje }}% · acumulado {{ f.acumulado }}%</q-item-label>
          </q-item-section>
          <q-item-section side top class="text-right">
            <q-item-label class="text-subtitle1 text-weight-bold text-grey-9">{{ numero(f[medida]) }}</q-item-label>
            <q-item-label caption>{{ medida === 'peso' ? 'kg' : f.unidades }}</q-item-label>
            <q-btn v-if="telefono(f)" round dense flat color="green-7" icon="call" type="a" :href="'tel:' + telefono(f)"
                   class="q-mt-xs">
              <q-tooltip>{{ f.telefono }}</q-tooltip>
            </q-btn>
          </q-item-section>
        </q-item>
      </q-list>
    </template>
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
      // Por defecto, los ultimos 90 dias.
      desde: date.formatDate(date.subtractFromDate(Date.now(), { days: 90 }), 'YYYY-MM-DD'),
      hasta: date.formatDate(Date.now(), 'YYYY-MM-DD'),
      medida: 'peso',
      orden: 'mas',
      buscar: '',
      cargando: false,
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
</style>
