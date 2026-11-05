<template>
  <q-page class="q-pa-sm">
    <!-- Menu Compras: /compras/transformaciones/nueva (crear) y /compras/transformaciones (ver). -->
    <div class="row items-center q-gutter-sm">
      <div class="text-h6">{{ vista === 'crear' ? 'Nueva transformación de productos' : 'Transformaciones de productos' }}</div>
      <q-space/>
      <q-btn v-if="vista === 'ver' && can('transformacionCrear')" color="primary" icon="add_circle"
             label="Crear transformación" no-caps unelevated to="/compras/transformaciones/nueva"/>
      <q-btn v-if="vista === 'crear' && puedeVer" color="primary" outline icon="list"
             label="Ver transformaciones" no-caps to="/compras/transformaciones"/>
    </div>
    <div v-if="!vista" class="q-pa-lg text-center text-grey-7">No tiene permiso para esta opción.</div>

    <!-- ============ CREAR ============ -->
    <div v-if="vista === 'crear'" class="q-mt-sm">
      <div class="text-caption text-grey-7">
        Los productos de origen salen del stock y los transformados entran, todo en kilos. Funciona en ambos
        sentidos: de varios productos a granel a uno armado, o de un producto por kilo a otro por kilo.
      </div>

      <div class="row q-col-gutter-md q-mt-xs">
        <!-- Origen -->
        <div class="col-12 col-md-6">
          <q-card flat bordered>
            <q-card-section class="q-py-sm bg-red-1 text-red-10 text-weight-bold row items-center">
              <q-icon name="logout" class="q-mr-sm"/> 1. Productos de origen (utilizados · salen del stock)
            </q-card-section>
            <q-card-section class="q-pa-sm">
              <q-select :model-value="null" dense outlined use-input fill-input hide-selected input-debounce="300"
                        :options="opcionesProducto" option-label="etiqueta" label="Agregar producto de origen"
                        @filter="buscarProducto" @update:model-value="p => agregar('origen', p)">
                <template v-slot:prepend><q-icon name="search"/></template>
                <template v-slot:no-option><q-item><q-item-section class="text-grey">Escriba código o nombre</q-item-section></q-item></template>
              </q-select>
              <q-markup-table flat dense separator="horizontal" class="q-mt-sm">
                <thead>
                <tr class="bg-grey-2">
                  <th class="text-left">Código</th>
                  <th class="text-left">Producto</th>
                  <th class="text-right">Stock</th>
                  <th class="text-right" style="width: 110px">KG</th>
                  <th></th>
                </tr>
                </thead>
                <tbody>
                <tr v-if="!form.origen.length"><td colspan="5" class="text-center text-grey-6">Sin productos</td></tr>
                <tr v-for="(l, i) in form.origen" :key="'o' + l.cod_prod">
                  <td>{{ l.cod_prod }}</td>
                  <td>{{ l.producto }}</td>
                  <td class="text-right" :class="Number(l.kg) > l.stock_actual ? 'text-negative text-weight-bold' : 'text-grey-7'">
                    {{ numero(l.stock_actual) }}
                  </td>
                  <td><q-input v-model.number="l.kg" type="number" min="0" step="0.001" dense outlined input-class="text-right"/></td>
                  <td><q-btn flat round dense size="sm" icon="close" color="negative" @click="form.origen.splice(i, 1)"/></td>
                </tr>
                </tbody>
                <tfoot>
                <tr class="text-weight-bold"><td colspan="3">Total KG origen</td><td class="text-right">{{ numero(totalOrigen) }}</td><td></td></tr>
                </tfoot>
              </q-markup-table>
            </q-card-section>
          </q-card>
        </div>

        <!-- Destino -->
        <div class="col-12 col-md-6">
          <q-card flat bordered>
            <q-card-section class="q-py-sm bg-green-1 text-green-10 text-weight-bold row items-center">
              <q-icon name="login" class="q-mr-sm"/> 2. Productos transformados (creados · entran al stock)
            </q-card-section>
            <q-card-section class="q-pa-sm">
              <q-select :model-value="null" dense outlined use-input fill-input hide-selected input-debounce="300"
                        :options="opcionesProducto" option-label="etiqueta" label="Agregar producto transformado"
                        @filter="buscarProducto" @update:model-value="p => agregar('destino', p)">
                <template v-slot:prepend><q-icon name="search"/></template>
                <template v-slot:no-option><q-item><q-item-section class="text-grey">Escriba código o nombre</q-item-section></q-item></template>
              </q-select>
              <q-markup-table flat dense separator="horizontal" class="q-mt-sm">
                <thead>
                <tr class="bg-grey-2">
                  <th class="text-left">Código</th>
                  <th class="text-left">Producto</th>
                  <th class="text-right" style="width: 110px">KG</th>
                  <th class="text-right" style="width: 90px">Cajas</th>
                  <th></th>
                </tr>
                </thead>
                <tbody>
                <tr v-if="!form.destino.length"><td colspan="5" class="text-center text-grey-6">Sin productos</td></tr>
                <tr v-for="(l, i) in form.destino" :key="'d' + l.cod_prod">
                  <td>{{ l.cod_prod }}</td>
                  <td>{{ l.producto }}</td>
                  <td><q-input v-model.number="l.kg" type="number" min="0" step="0.001" dense outlined input-class="text-right"/></td>
                  <td><q-input v-model.number="l.cajas" type="number" min="0" step="0.1" dense outlined input-class="text-right"/></td>
                  <td><q-btn flat round dense size="sm" icon="close" color="negative" @click="form.destino.splice(i, 1)"/></td>
                </tr>
                </tbody>
                <tfoot>
                <tr class="text-weight-bold">
                  <td colspan="2">Total KG transformado</td>
                  <td class="text-right">{{ numero(totalDestino) }}</td>
                  <td class="text-right">{{ numero(totalCajas) }}</td>
                  <td></td>
                </tr>
                </tfoot>
              </q-markup-table>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <div class="row items-center q-gutter-sm q-mt-sm">
        <q-input v-model="form.observacion" dense outlined label="Observación" maxlength="255" class="col"/>
        <div class="text-body2">
          Diferencia (merma): <b :class="diferencia < 0 ? 'text-negative' : ''">{{ numero(diferencia) }} kg</b>
          <span v-if="totalOrigen" class="text-grey-7"> · {{ numero(diferencia / totalOrigen * 100) }}%</span>
        </div>
        <q-btn color="primary" icon="save" label="Registrar transformación" no-caps unelevated
               :loading="guardando" :disable="!valido" @click="guardar"/>
      </div>
    </div>

    <!-- ============ VER ============ -->
    <div v-if="vista === 'ver'" class="q-mt-sm">
      <div class="row items-center q-gutter-sm">
        <q-input v-model="filtro.desde" type="date" dense outlined label="Desde" style="width: 160px" @update:model-value="consultar"/>
        <q-input v-model="filtro.hasta" type="date" dense outlined label="Hasta" style="width: 160px" @update:model-value="consultar"/>
        <q-select v-model="filtro.estado" dense outlined emit-value map-options label="Estado" style="width: 140px"
                  :options="[{ label: 'Todos', value: '' }, { label: 'Activos', value: 'ACTIVO' }, { label: 'Anulados', value: 'ANULADO' }]"
                  @update:model-value="consultar"/>
        <q-input v-model="filtro.buscar" dense outlined clearable placeholder="Buscar producto, usuario, N°" class="col">
          <template v-slot:append><q-icon name="search"/></template>
        </q-input>
        <q-btn color="primary" icon="refresh" label="Actualizar" no-caps unelevated :loading="cargando" @click="consultar"/>
      </div>

      <div class="row q-col-gutter-sm q-mt-xs">
        <div class="col-6 col-sm-3"><q-card flat bordered class="q-pa-sm">
          <div class="text-caption text-grey-7">Transformaciones activas</div>
          <div class="text-h6">{{ activas.length }}</div>
        </q-card></div>
        <div class="col-6 col-sm-3"><q-card flat bordered class="q-pa-sm">
          <div class="text-caption text-grey-7">KG origen</div>
          <div class="text-h6 text-red-9">{{ numero(sumar(activas, 'total_origen_kg')) }}</div>
        </q-card></div>
        <div class="col-6 col-sm-3"><q-card flat bordered class="q-pa-sm">
          <div class="text-caption text-grey-7">KG transformado</div>
          <div class="text-h6 text-green-9">{{ numero(sumar(activas, 'total_destino_kg')) }}</div>
        </q-card></div>
        <div class="col-6 col-sm-3"><q-card flat bordered class="q-pa-sm">
          <div class="text-caption text-grey-7">Diferencia (merma)</div>
          <div class="text-h6">{{ numero(sumar(activas, 'diferencia_kg')) }}</div>
        </q-card></div>
      </div>

      <div v-if="!visibles.length" class="text-center text-grey-7 q-pa-lg">
        {{ cargando ? 'Cargando…' : 'No hay transformaciones en el rango' }}
      </div>

      <q-card v-for="t in visibles" :key="t.id" flat bordered class="q-mt-sm"
              :class="{ 'anulada': t.estado === 'ANULADO' }">
        <q-card-section class="q-py-sm row items-center q-gutter-x-md bg-grey-2">
          <div class="text-weight-bold">N° {{ t.id }}</div>
          <q-badge :color="t.estado === 'ANULADO' ? 'negative' : 'positive'" :label="t.estado"/>
          <div><q-icon name="event"/> {{ t.fecha }} {{ t.hora }}</div>
          <div><q-icon name="person"/> {{ t.usuario || '—' }}</div>
          <div class="text-grey-8" v-if="t.observacion"><q-icon name="notes"/> {{ t.observacion }}</div>
          <q-space/>
          <div class="text-body2">
            {{ numero(t.total_origen_kg) }} kg → {{ numero(t.total_destino_kg) }} kg ·
            dif. <b>{{ numero(t.diferencia_kg) }}</b>
          </div>
          <q-btn v-if="t.estado === 'ACTIVO' && can('transformacionAnular')" dense flat no-caps color="negative"
                 icon="block" label="Anular" @click="anular(t)"/>
        </q-card-section>
        <q-card-section v-if="t.estado === 'ANULADO'" class="q-py-xs bg-red-1 text-red-10 text-caption">
          Anulado por <b>{{ t.anulado_por || '—' }}</b> el {{ t.anulado_at }} · Motivo: {{ t.motivo_anulacion }}
        </q-card-section>
        <q-card-section class="q-pa-sm">
          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-6">
              <div class="text-weight-bold text-green-9 q-mb-xs">1. Productos transformados (creados)</div>
              <q-markup-table flat dense bordered separator="horizontal">
                <thead><tr class="bg-green-1">
                  <th class="text-left">Código</th><th class="text-left">Producto</th>
                  <th class="text-right">KG</th><th class="text-right">Cajas</th>
                </tr></thead>
                <tbody>
                <tr v-for="d in t.destino" :key="'d' + d.cod_prod">
                  <td>{{ d.cod_prod }}</td><td>{{ d.producto }}</td>
                  <td class="text-right">{{ numero(d.kg) }}</td>
                  <td class="text-right">{{ d.cajas !== null ? numero(d.cajas) : '' }}</td>
                </tr>
                </tbody>
              </q-markup-table>
            </div>
            <div class="col-12 col-md-6">
              <div class="text-weight-bold text-red-9 q-mb-xs">2. Productos de origen (utilizados)</div>
              <q-markup-table flat dense bordered separator="horizontal">
                <thead><tr class="bg-red-1">
                  <th class="text-left">Código</th><th class="text-left">Producto</th><th class="text-right">KG</th>
                </tr></thead>
                <tbody>
                <tr v-for="d in t.origen" :key="'o' + d.cod_prod">
                  <td>{{ d.cod_prod }}</td><td>{{ d.producto }}</td><td class="text-right">{{ numero(d.kg) }}</td>
                </tr>
                </tbody>
              </q-markup-table>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </div>
  </q-page>
</template>

<script>
import { date } from 'quasar'

export default {
  name: 'TransformacionesProductos',
  props: {
    modo: { type: String, default: 'ver' }
  },
  data () {
    const hoy = date.formatDate(Date.now(), 'YYYY-MM-DD')
    return {
      opcionesProducto: [],
      form: { origen: [], destino: [], observacion: '' },
      guardando: false,
      filtro: { desde: hoy, hasta: hoy, estado: '', buscar: '' },
      lista: [],
      cargando: false
    }
  },
  computed: {
    can () {
      return this.$store.getters['login/can']
    },
    puedeVer () {
      return ['transformacionVer', 'transformacionCrear', 'transformacionAnular'].some(p => this.can(p))
    },
    // Cada ruta abre su vista, si el usuario tiene el permiso.
    vista () {
      if (this.modo === 'crear') return this.can('transformacionCrear') ? 'crear' : null
      return this.puedeVer ? 'ver' : null
    },
    totalOrigen () { return this.sumar(this.form.origen, 'kg') },
    totalDestino () { return this.sumar(this.form.destino, 'kg') },
    totalCajas () { return this.sumar(this.form.destino, 'cajas') },
    diferencia () { return Math.round((this.totalOrigen - this.totalDestino) * 1000) / 1000 },
    valido () {
      const ok = l => l.length && l.every(x => Number(x.kg) > 0)
      return ok(this.form.origen) && ok(this.form.destino)
    },
    activas () { return this.visibles.filter(t => t.estado === 'ACTIVO') },
    visibles () {
      const b = (this.filtro.buscar || '').toLowerCase().trim()
      if (!b) return this.lista
      return this.lista.filter(t =>
        String(t.id) === b ||
        (t.usuario || '').toLowerCase().includes(b) ||
        (t.observacion || '').toLowerCase().includes(b) ||
        [...t.origen, ...t.destino].some(d => d.cod_prod.toLowerCase().includes(b) || d.producto.toLowerCase().includes(b)))
    }
  },
  watch: {
    vista: {
      immediate: true,
      handler (v) {
        if (v === 'ver') this.consultar()
      }
    }
  },
  methods: {
    numero (n) {
      return Number(n || 0).toLocaleString('es-BO', { minimumFractionDigits: 1, maximumFractionDigits: 3 })
    },
    sumar (filas, campo) {
      return Math.round(filas.reduce((s, f) => s + (Number(f[campo]) || 0), 0) * 1000) / 1000
    },
    buscarProducto (val, update, abort) {
      if (!val || val.length < 2) { abort(); return }
      this.$api.get('transformaciones/productos', { params: { buscar: val } }).then(res => {
        update(() => {
          this.opcionesProducto = res.data.map(p => ({ ...p, etiqueta: p.cod_prod + ' · ' + p.producto }))
        })
      }).catch(() => abort())
    },
    agregar (lado, p) {
      if (!p) return
      const otro = lado === 'origen' ? 'destino' : 'origen'
      if (this.form[otro].some(l => l.cod_prod === p.cod_prod)) {
        this.$q.notify({ type: 'warning', message: 'Ese producto ya está del otro lado' })
        return
      }
      if (this.form[lado].some(l => l.cod_prod === p.cod_prod)) return
      this.form[lado].push({
        cod_prod: p.cod_prod,
        producto: p.producto,
        stock_actual: Number(p.stock_actual),
        kg: null,
        cajas: null
      })
    },
    guardar () {
      const sinStock = this.form.origen.filter(l => Number(l.kg) > l.stock_actual)
      const mensaje = `Se descontarán ${this.numero(this.totalOrigen)} kg de origen y se ingresarán ` +
        `${this.numero(this.totalDestino)} kg transformados (diferencia ${this.numero(this.diferencia)} kg).` +
        (sinStock.length ? ` Ojo: ${sinStock.map(l => l.producto).join(', ')} quedará(n) con stock negativo.` : '')
      this.$q.dialog({ title: 'Registrar transformación', message: mensaje, cancel: true, persistent: true }).onOk(() => {
        this.guardando = true
        this.$api.post('transformaciones', {
          origen: this.form.origen.map(l => ({ cod_prod: l.cod_prod, kg: l.kg })),
          destino: this.form.destino.map(l => ({ cod_prod: l.cod_prod, kg: l.kg, cajas: l.cajas })),
          observacion: this.form.observacion || null
        }).then(res => {
          this.$q.notify({ type: 'positive', message: res.data.message })
          this.form = { origen: [], destino: [], observacion: '' }
          this.lista = []
          if (this.puedeVer) this.$router.push('/compras/transformaciones')
        }).catch(err => {
          this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudo registrar' })
        }).finally(() => { this.guardando = false })
      })
    },
    consultar () {
      this.cargando = true
      this.$api.get('transformaciones', {
        params: { desde: this.filtro.desde, hasta: this.filtro.hasta || this.filtro.desde, estado: this.filtro.estado || undefined }
      }).then(res => {
        this.lista = res.data.transformaciones
      }).catch(err => {
        this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudo cargar' })
      }).finally(() => { this.cargando = false })
    },
    anular (t) {
      this.$q.dialog({
        title: 'Anular transformación N° ' + t.id,
        message: 'Se devolverá el stock: el origen vuelve a entrar y lo transformado sale. Motivo:',
        prompt: { model: '', type: 'text', isValid: v => v.trim().length > 0 },
        cancel: true,
        persistent: true
      }).onOk(motivo => {
        this.$api.put('transformaciones/' + t.id + '/anular', { motivo }).then(res => {
          this.$q.notify({ type: 'positive', message: res.data.message })
          const i = this.lista.findIndex(x => x.id === t.id)
          if (i >= 0) this.lista.splice(i, 1, res.data.transformacion)
        }).catch(err => {
          this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudo anular' })
        })
      })
    }
  }
}
</script>

<style scoped>
.anulada { opacity: .75; }
</style>
