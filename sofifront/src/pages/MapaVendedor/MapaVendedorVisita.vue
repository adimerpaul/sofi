<template>
  <q-page class="bg-grey-3 q-pa-xs mapa-visita">
    <q-card flat bordered>
      <q-card-section class="q-pa-xs">
        <!-- Filtros -->
        <div class="row q-col-gutter-xs items-center">
          <div class="col-6 col-sm-3 col-md-2">
            <q-input v-model="fecha" label="Fecha" type="date" dense outlined @update:model-value="buscar"/>
          </div>
          <div class="col-6 col-sm-3 col-md-2">
            <!-- Filtra los clientes del mapa y de las tablas por el tipo de producto
                 que pidieron; no hace falta volver a buscar. -->
            <q-select v-model="tipo" label="Tipo de producto" dense outlined :options="opcionesTipo"
                      emit-value map-options/>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <q-select v-model="vendedor" label="Vendedor" dense outlined :options="opcionesVendedor"
                      emit-value map-options/>
          </div>
          <div class="col-12 col-sm-auto">
            <!-- Ruta normal: clientes que tienen marcado el dia. Fuera de ruta: no les
                 tocaba ese dia pero se los visito o se les hizo pedido. -->
            <q-btn-toggle v-model="ruta" dense no-caps unelevated toggle-color="primary" color="white"
                          text-color="grey-9" class="filtro-ruta"
                          :options="[
                            {label: 'Completo', value: 'TODOS'},
                            {label: 'Ruta normal', value: 'RUTA'},
                            {label: 'Fuera de ruta', value: 'FUERA'}
                          ]"/>
          </div>
          <div class="col-auto">
            <q-btn label="Buscar" color="primary" dense no-caps icon="search" :loading="loading" @click="buscar"/>
          </div>
          <div class="col-auto">
            <q-btn-dropdown label="Excel" color="green-8" dense no-caps icon="file_download" :loading="exportando">
              <q-list dense>
                <q-item clickable v-close-popup :disable="!clientesFiltrados.length" @click="exportarExcel">
                  <q-item-section avatar><q-icon name="table_view" color="green-8"/></q-item-section>
                  <q-item-section>
                    <q-item-label>Resumen y clientes</q-item-label>
                    <q-item-label caption>Lo que se ve en pantalla: por vendedor y por cliente</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item clickable v-close-popup @click="exportarVisitas">
                  <q-item-section avatar><q-icon name="schedule" color="green-8"/></q-item-section>
                  <q-item-section>
                    <q-item-label>Registro de visitas</q-item-label>
                    <q-item-label caption>Cada visita por hora, no visitado (&gt;100 m) y en ruta / fuera de ruta</q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </q-btn-dropdown>
          </div>
        </div>

        <div class="row q-col-gutter-xs q-mt-xs">
          <!-- Resumen y detalle -->
          <div class="col-12 col-md-5">
            <q-markup-table flat bordered dense separator="cell" class="tabla-compacta">
              <thead>
              <tr>
                <th class="text-left">Vendedor</th>
                <th>Cli.</th>
                <th title="En ruta / fuera de ruta">Ruta/Fuera</th>
                <th class="text-green-8">Ped.</th>
                <th class="text-red-8">No P.</th>
                <th class="text-orange-8">Ret.</th>
                <th class="text-grey-7">Falt.</th>
                <th>Efect.</th>
                <th class="text-right">Bs</th>
              </tr>
              </thead>
              <tbody>
              <tr v-for="r in resumen" :key="r.CodAut" class="cursor-pointer"
                  :class="vendedor === r.CodAut ? 'bg-blue-1' : ''" @click="vendedor = vendedor === r.CodAut ? null : r.CodAut">
                <td class="text-left ellipsis" style="max-width: 120px">{{ $filters.capitalize(r.nombre) }}</td>
                <td class="text-center">{{ r.total }}</td>
                <td class="text-center">{{ r.ruta }}/<span class="text-purple-8">{{ r.fuera }}</span></td>
                <td class="text-center text-green-8 text-weight-bold">{{ r.pedido }}</td>
                <td class="text-center text-red-8">{{ r.nopedido }}</td>
                <td class="text-center text-orange-8">{{ r.parado }}</td>
                <td class="text-center text-grey-7">{{ r.faltaron }}</td>
                <td class="text-center">{{ r.efectividad }}%</td>
                <td class="text-right">{{ r.bs.toFixed(2) }}</td>
              </tr>
              <tr v-if="!resumen.length">
                <td colspan="9" class="text-center text-grey-7">{{ loading ? 'Cargando…' : 'Sin datos' }}</td>
              </tr>
              </tbody>
              <tfoot v-if="resumen.length > 1">
              <tr class="text-weight-bold">
                <td class="text-left">Total</td>
                <td class="text-center">{{ totales.total }}</td>
                <td class="text-center">{{ totales.ruta }}/<span class="text-purple-8">{{ totales.fuera }}</span></td>
                <td class="text-center text-green-8">{{ totales.pedido }}</td>
                <td class="text-center text-red-8">{{ totales.nopedido }}</td>
                <td class="text-center text-orange-8">{{ totales.parado }}</td>
                <td class="text-center text-grey-7">{{ totales.faltaron }}</td>
                <td class="text-center">{{ totales.efectividad }}%</td>
                <td class="text-right">{{ totales.bs.toFixed(2) }}</td>
              </tr>
              </tfoot>
            </q-markup-table>

            <q-table flat bordered dense class="tabla-compacta tabla-clientes q-mt-xs"
                     :rows="clientesFiltrados" :columns="columnas" row-key="clave"
                     :filter="buscarCliente" :rows-per-page-options="[0]" hide-bottom
                     virtual-scroll :virtual-scroll-item-size="22">
              <template v-slot:top>
                <div class="row items-center full-width no-wrap">
                  <div class="text-caption text-weight-bold">Clientes ({{ clientesFiltrados.length }})</div>
                  <q-space/>
                  <q-input v-model="buscarCliente" dense borderless placeholder="Buscar…" class="buscar-cliente">
                    <template v-slot:prepend><q-icon name="search" size="14px"/></template>
                  </q-input>
                </div>
              </template>
              <template v-slot:body="props">
                <q-tr :props="props" class="cursor-pointer" @click="enfocar(props.row)">
                  <q-td key="Cod_Aut" :props="props">{{ props.row.Cod_Aut }}</q-td>
                  <q-td key="Nombres" :props="props" class="ellipsis" style="max-width: 150px">
                    {{ props.row.Nombres }}
                    <q-tooltip>{{ props.row.Nombres }} · {{ props.row.Direccion }}</q-tooltip>
                  </q-td>
                  <q-td key="en_ruta" :props="props">
                    <span :class="props.row.en_ruta ? 'text-grey-8' : 'text-purple-8 text-weight-bold'">
                      {{ props.row.en_ruta ? 'Ruta' : 'Fuera' }}
                    </span>
                  </q-td>
                  <q-td key="estado" :props="props">
                    <span class="punto" :class="'bg-' + colorEstado(props.row.estado)"></span>
                    {{ props.row.estado || 'FALTA' }}
                  </q-td>
                  <q-td key="hora" :props="props">{{ hora(props.row.visita_hora || props.row.pedido_hora) }}</q-td>
                  <q-td key="pedido_total" :props="props">{{ props.row.pedido_total ? props.row.pedido_total.toFixed(2) : '' }}</q-td>
                </q-tr>
              </template>
            </q-table>
          </div>

          <!-- Mapa -->
          <div class="col-12 col-md-7">
            <!-- Cantidades de lo que se ve en el mapa (tipo, ruta y vendedor elegidos). -->
            <div class="tarjetas q-mb-xs">
              <div v-for="t in tarjetas" :key="t.label" class="tarjeta" :class="'borde-' + t.color">
                <div class="tarjeta-valor" :class="'text-' + t.color">{{ t.valor }}</div>
                <div class="tarjeta-label">{{ t.label }}</div>
              </div>
            </div>
            <div class="contenedor-mapa">
              <l-map ref="mapa" v-model:zoom="zoom" v-model:center="center" :use-global-leaflet="true">
                <l-tile-layer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" layer-type="base"
                              name="OpenStreetMap"/>
                <l-marker v-for="c in clientesMapa" :key="c.clave"
                          :lat-lng="[parseFloat(c.Latitud), parseFloat(c.longitud)]">
                  <l-icon :icon-size="[0, 0]" :icon-anchor="[0, 0]" class-name="icono-vacio">
                    <div class="marcador" :class="['bg-' + colorEstado(c.estado), c.en_ruta ? '' : 'marcador-fuera']">
                      {{ c.Cod_Aut }}
                    </div>
                  </l-icon>
                  <l-tooltip :options="{direction: 'top', offset: [8, -4]}">
                    <div class="tip">
                      <div class="text-weight-bold">{{ c.Cod_Aut }} · {{ c.Nombres }}</div>
                      <div>
                        <b :class="'text-' + colorEstado(c.estado)">{{ c.estado || 'SIN VISITA' }}</b>
                        · <span :class="c.en_ruta ? '' : 'text-purple-8 text-weight-bold'">{{ c.en_ruta ? 'En ruta' : 'Fuera de ruta' }}</span>
                        <template v-if="c.se_recoge"> · <b>Se recoge</b></template>
                      </div>
                      <div v-if="c.Direccion">Dir.: {{ c.Direccion }}</div>
                      <div v-if="c.zona || c.Telf">
                        <span v-if="c.zona">Zona: {{ c.zona }}</span>
                        <span v-if="c.zona && c.Telf"> · </span>
                        <span v-if="c.Telf">Telf.: {{ c.Telf }}</span>
                      </div>
                      <div>Días de ruta: {{ c.dias || '—' }}</div>
                      <div>Vendedor: {{ $filters.capitalize(c.vendedor) }}</div>
                      <div v-if="c.visita_hora">
                        Visita: {{ hora(c.visita_hora) }}
                        <template v-if="c.visita_distancia"> · a {{ metros(c.visita_distancia) }}</template>
                      </div>
                      <div v-if="c.visita_obs">Obs.: {{ c.visita_obs }}</div>
                      <div v-if="c.pedido_nros">
                        Pedido {{ c.pedido_nros }} · {{ c.pedido_lineas }} prod. ·
                        <b>{{ c.pedido_total.toFixed(2) }} Bs</b>
                      </div>
                      <div v-if="!tipo && c.pedido_nros" class="tip-tipos">
                        <span v-for="(p, nombre) in c.pedido_por_tipo" :key="nombre">
                          {{ nombre }}: {{ p.total.toFixed(2) }}
                        </span>
                      </div>
                    </div>
                  </l-tooltip>
                </l-marker>
              </l-map>
              <div class="leyenda">
                <span><span class="punto bg-green"></span>Pedido</span>
                <span><span class="punto bg-red"></span>No pedido</span>
                <span><span class="punto bg-orange"></span>Retorno</span>
                <span><span class="punto bg-grey"></span>Falta</span>
                <span><span class="punto punto-fuera"></span>Fuera de ruta</span>
              </div>
            </div>
          </div>
        </div>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script>
// Leaflet global (como en Avance): con el que carga vue-leaflet por su cuenta
// el l-icon con contenido propio no se aplica. Sin el CSS las teselas del mapa
// salen desfasadas.
import 'leaflet'
import 'leaflet/dist/leaflet.css'
import { LIcon, LMap, LMarker, LTileLayer, LTooltip } from '@vue-leaflet/vue-leaflet'
import xlsx from 'json-as-xlsx'
import moment from 'moment'

const COLORES = { PEDIDO: 'green', 'NO PEDIDO': 'red', PARADO: 'orange' }

export default {
  name: 'MapaVendedorVisita',
  components: { LMap, LIcon, LTileLayer, LMarker, LTooltip },
  data () {
    return {
      center: [-17.969721, -67.114493],
      zoom: 13,
      fecha: moment().format('YYYY-MM-DD'),
      loading: false,
      exportando: false,
      users: [],
      vendedor: null,
      ruta: 'TODOS',
      buscarCliente: '',
      tipo: '',
      // Tipos de producto (Productos > Tipo): EMBUTIDO, HUEVO, PET, POLLO...
      tipos: [],
      columnas: [
        { name: 'Cod_Aut', label: 'Cód.', field: 'Cod_Aut', align: 'left', sortable: true },
        { name: 'Nombres', label: 'Cliente', field: 'Nombres', align: 'left', sortable: true },
        { name: 'en_ruta', label: 'Ruta', field: r => (r.en_ruta ? 'Ruta' : 'Fuera'), align: 'left', sortable: true },
        { name: 'estado', label: 'Estado', field: r => r.estado || 'FALTA', align: 'left', sortable: true },
        { name: 'hora', label: 'Hora', field: r => r.visita_hora || r.pedido_hora || '', align: 'left', sortable: true },
        { name: 'pedido_total', label: 'Bs', field: 'pedido_total', align: 'right', sortable: true }
      ]
    }
  },
  mounted () {
    this.buscar()
  },
  computed: {
    opcionesTipo () {
      return [{ label: 'TODOS', value: '' }].concat(this.tipos.map(t => ({ label: t.nombre, value: t.nombre })))
    },
    opcionesVendedor () {
      return [{ label: 'Todos', value: null }].concat(
        this.users.map(u => ({ label: this.$filters.capitalize(u.nombreCompleto), value: u.CodAut })))
    },
    // Clientes con los filtros de ruta y de tipo de producto aplicados. Con un
    // tipo elegido quedan solo los clientes que pidieron ese tipo, con el
    // pedido (Bs, productos, numeros) de ese tipo.
    clientesPorRuta () {
      const filas = []
      this.users.forEach(u => {
        u.clientes.forEach(c => {
          if (this.ruta === 'RUTA' && !c.en_ruta) return
          if (this.ruta === 'FUERA' && c.en_ruta) return
          const fila = { ...c, clave: u.CodAut + '-' + c.Cod_Aut, vendedorId: u.CodAut, vendedor: u.nombreCompleto }
          if (this.tipo) {
            const p = (c.pedido_por_tipo || {})[this.tipo]
            if (!p) return
            Object.assign(fila, {
              estado: c.estado || 'PEDIDO',
              pedido_nros: p.nros,
              pedido_tipos: this.tipo,
              pedido_lineas: p.lineas,
              pedido_total: p.total,
              pedido_hora: p.hora
            })
          }
          filas.push(fila)
        })
      })
      return filas
    },
    clientesFiltrados () {
      if (!this.vendedor) return this.clientesPorRuta
      return this.clientesPorRuta.filter(c => c.vendedorId === this.vendedor)
    },
    clientesMapa () {
      return this.clientesFiltrados.filter(c => this.coordenadaValida(c.Latitud, c.longitud))
    },
    resumen () {
      return this.users.map(u => {
        const filas = this.clientesPorRuta.filter(c => c.vendedorId === u.CodAut)
        return { CodAut: u.CodAut, nombre: u.nombreCompleto, ...this.contar(filas) }
      })
    },
    totales () {
      return this.contar(this.clientesPorRuta)
    },
    tarjetas () {
      const t = this.contar(this.clientesFiltrados)
      return [
        { label: 'Clientes', valor: t.total, color: 'blue-8' },
        { label: 'En ruta', valor: t.ruta, color: 'grey-8' },
        { label: 'Fuera de ruta', valor: t.fuera, color: 'purple-8' },
        { label: 'Pedido', valor: t.pedido, color: 'green-8' },
        { label: 'No pedido', valor: t.nopedido, color: 'red-8' },
        { label: 'Retorno', valor: t.parado, color: 'orange-8' },
        { label: 'Faltan', valor: t.faltaron, color: 'grey-7' },
        { label: 'Efectividad', valor: t.efectividad + '%', color: 'teal-8' },
        { label: 'Total Bs', valor: t.bs.toFixed(2), color: 'indigo-8' }
      ]
    }
  },
  methods: {
    contar (filas) {
      const r = { total: filas.length, ruta: 0, fuera: 0, pedido: 0, nopedido: 0, parado: 0, faltaron: 0, bs: 0 }
      filas.forEach(c => {
        if (c.en_ruta) r.ruta++
        else r.fuera++
        if (c.estado === 'PEDIDO') r.pedido++
        else if (c.estado === 'NO PEDIDO') r.nopedido++
        else if (c.estado === 'PARADO') r.parado++
        else r.faltaron++
        r.bs += parseFloat(c.pedido_total) || 0
      })
      r.efectividad = r.total ? ((r.pedido / r.total) * 100).toFixed(1) : '0.0'
      return r
    },
    colorEstado (estado) {
      return COLORES[estado] || 'grey'
    },
    hora (h) {
      return h ? String(h).substring(0, 5) : ''
    },
    metros (d) {
      const m = parseFloat(d) || 0
      return m >= 1000 ? (m / 1000).toFixed(1) + ' km' : Math.round(m) + ' m'
    },
    coordenadaValida (lat, lng) {
      const la = parseFloat(lat)
      const ln = parseFloat(lng)
      return !isNaN(la) && !isNaN(ln) && la !== 0 && ln !== 0
    },
    enfocar (c) {
      if (!this.coordenadaValida(c.Latitud, c.longitud)) {
        this.$q.notify({ type: 'warning', message: 'El cliente no tiene ubicación' })
        return
      }
      this.center = [parseFloat(c.Latitud), parseFloat(c.longitud)]
      this.zoom = 17
    },
    buscar () {
      this.loading = true
      this.$api.post('mapaVendedorVisita', { fecha: this.fecha }).then(res => {
        this.users = res.data.users || []
        this.tipos = res.data.tipos || []
        if (this.vendedor && !this.users.some(u => u.CodAut === this.vendedor)) this.vendedor = null
      }).catch(() => {
        this.$q.notify({ type: 'negative', message: 'No se pudo cargar el mapa de visitas' })
      }).finally(() => {
        this.loading = false
      })
    },
    // Lo arma el backend (MapaVendedorController::excelVisitas) con el vendedor
    // y el filtro de ruta de la pantalla.
    async exportarVisitas () {
      this.exportando = true
      try {
        const { data } = await this.$api.get('mapaVendedorVisita/excel-visitas', {
          params: { fecha: this.fecha, vendedor: this.vendedor || '', ruta: this.ruta },
          responseType: 'blob'
        })
        const url = URL.createObjectURL(data)
        const a = document.createElement('a')
        a.href = url
        a.download = 'visitas_' + this.fecha + '.xlsx'
        a.click()
        URL.revokeObjectURL(url)
      } catch (err) {
        let mensaje = 'No se pudo generar el registro de visitas'
        try {
          mensaje = JSON.parse(await err.response.data.text()).message || mensaje
        } catch (e) { /* el error no vino en JSON */ }
        this.$q.notify({ type: 'negative', message: mensaje })
      } finally {
        this.exportando = false
      }
    },
    exportarExcel () {
      const filtro = { TODOS: 'Completo', RUTA: 'Ruta normal', FUERA: 'Fuera de ruta' }[this.ruta]
      const tipo = this.tipo || 'TODOS'
      const vendedores = this.vendedor ? this.resumen.filter(r => r.CodAut === this.vendedor) : this.resumen
      const resumen = vendedores.map(r => ({ ...r, bs: Number(r.bs.toFixed(2)), efectividad: r.efectividad + '%' }))
      if (resumen.length > 1) {
        const t = this.contar(this.clientesFiltrados)
        resumen.push({ ...t, nombre: 'TOTAL', bs: Number(t.bs.toFixed(2)), efectividad: t.efectividad + '%' })
      }
      xlsx([
        {
          sheet: 'Resumen',
          columns: [
            { label: 'Vendedor', value: 'nombre' },
            { label: 'Clientes', value: 'total' },
            { label: 'En ruta', value: 'ruta' },
            { label: 'Fuera de ruta', value: 'fuera' },
            { label: 'Pedido', value: 'pedido' },
            { label: 'No pedido', value: 'nopedido' },
            { label: 'Retorno', value: 'parado' },
            { label: 'Faltaron', value: 'faltaron' },
            { label: 'Efectividad', value: 'efectividad' },
            { label: 'Total pedido Bs', value: 'bs' }
          ],
          content: resumen
        },
        {
          sheet: 'Clientes',
          columns: [
            { label: 'Vendedor', value: 'vendedor' },
            { label: 'Código', value: 'Cod_Aut' },
            { label: 'CI/NIT', value: 'Id' },
            { label: 'Cliente', value: 'Nombres' },
            { label: 'Dirección', value: 'Direccion' },
            { label: 'Zona', value: 'zona' },
            { label: 'Teléfono', value: 'Telf' },
            { label: 'Ruta', value: r => (r.en_ruta ? 'En ruta' : 'Fuera de ruta') },
            { label: 'Días de ruta', value: 'dias' },
            { label: 'Estado', value: r => r.estado || 'FALTA' },
            { label: 'Hora visita', value: r => this.hora(r.visita_hora) },
            { label: 'Distancia visita (m)', value: r => (r.visita_distancia ? Math.round(r.visita_distancia) : '') },
            { label: 'Observación', value: r => r.visita_obs || '' },
            { label: 'Nro pedido', value: r => r.pedido_nros || '' },
            { label: 'Tipo pedido', value: r => r.pedido_tipos || '' },
            { label: 'Productos', value: 'pedido_lineas' },
            { label: 'Total Bs', value: 'pedido_total' },
            { label: 'Latitud', value: 'Latitud' },
            { label: 'Longitud', value: 'longitud' }
          ],
          content: this.clientesFiltrados
        }
      ], {
        fileName: ['Visitas', this.fecha, tipo, filtro].join('_').replace(/[^a-zA-Z0-9_-]+/g, '_'),
        extraLength: 2
      })
    }
  }
}
</script>

<style scoped>
.tabla-compacta :deep(th),
.tabla-compacta :deep(td) {
  font-size: 11px;
  line-height: 1.1;
  padding: 2px 4px !important;
  height: auto !important;
}
.tabla-compacta :deep(thead tr),
.tabla-compacta :deep(tbody tr) {
  height: auto !important;
}
.tabla-clientes {
  height: calc(100vh - 330px);
  min-height: 220px;
}
.tabla-clientes :deep(thead tr th) {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #fff;
}
.buscar-cliente {
  max-width: 160px;
  font-size: 11px;
}
.filtro-ruta {
  border: 1px solid rgba(0, 0, 0, 0.24);
}
.tarjetas {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(78px, 1fr));
  gap: 4px;
}
.tarjeta {
  background: #fff;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-left-width: 4px;
  border-radius: 4px;
  padding: 3px 6px;
  line-height: 1.1;
}
.tarjeta-valor {
  font-size: 16px;
  font-weight: 700;
  white-space: nowrap;
}
.tarjeta-label {
  font-size: 10px;
  color: #616161;
  white-space: nowrap;
}
.borde-blue-8 { border-left-color: #1565c0; }
.borde-grey-8 { border-left-color: #424242; }
.borde-purple-8 { border-left-color: #6a1b9a; }
.borde-green-8 { border-left-color: #2e7d32; }
.borde-red-8 { border-left-color: #c62828; }
.borde-orange-8 { border-left-color: #ef6c00; }
.borde-grey-7 { border-left-color: #616161; }
.borde-teal-8 { border-left-color: #00695c; }
.borde-indigo-8 { border-left-color: #283593; }
.contenedor-mapa {
  position: relative;
  height: calc(100vh - 200px);
  min-height: 380px;
  width: 100%;
}
.punto {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 3px;
  vertical-align: middle;
}
.punto-fuera {
  background: #fff;
  border: 2px dashed #7b1fa2;
}
.leyenda {
  position: absolute;
  bottom: 6px;
  left: 6px;
  z-index: 500;
  background: rgba(255, 255, 255, 0.92);
  border-radius: 4px;
  padding: 2px 6px;
  font-size: 11px;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.marcador {
  display: inline-block;
  transform: translate(-50%, -50%);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  line-height: 1;
  padding: 2px 3px;
  border-radius: 3px;
  white-space: nowrap;
  border: 1px solid rgba(0, 0, 0, 0.25);
}
.marcador-fuera {
  border: 2px dashed #7b1fa2;
}
.tip {
  font-size: 11px;
  line-height: 1.25;
  width: 320px;
  white-space: normal;
}
.tip-tipos {
  display: flex;
  flex-wrap: wrap;
  column-gap: 8px;
}
:deep(.icono-vacio) {
  background: transparent;
  border: none;
}
</style>
