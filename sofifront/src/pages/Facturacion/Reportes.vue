<template>
  <q-page class="q-pa-sm">
    <!-- Una pestaña por reporte. -->
    <q-tabs v-model="reporte" dense align="left" no-caps active-color="primary" indicator-color="primary"
            class="text-grey-8">
      <q-tab name="quiebre" icon="production_quantity_limits" label="Quiebre de stock embutido"/>
      <q-tab name="pollo" icon="egg" label="Reporte auxiliar de pollo"/>
      <q-tab name="detalle" icon="receipt_long" label="Detalle de ventas"/>
      <q-tab name="kardex" icon="inventory" label="Kardex por producto"/>
    </q-tabs>
    <q-separator/>

    <q-tab-panels v-model="reporte" animated>
      <q-tab-panel name="quiebre" class="q-pa-none q-pt-sm">
        <div class="row items-center q-gutter-sm">
          <q-input v-model="fecha" type="date" dense outlined label="Fecha del pedido" style="width: 170px"
                   @update:model-value="consultar"/>
          <q-btn color="primary" icon="refresh" label="Actualizar" no-caps unelevated :loading="cargando"
                 @click="consultar"/>
          <q-btn color="green-8" icon="download" label="Excel" no-caps unelevated :loading="descargando"
                 :disable="!filas.length" @click="descargarExcel"/>
          <q-space/>
          <div class="text-caption text-grey-8">
            {{ ciudad }} · Semana N° <b>{{ semana }}</b>
          </div>
        </div>

        <div class="text-caption text-grey-7 q-mt-xs">
          Lo que pidió cada cliente de embutidos contra lo que se le vendió en caja. Solo entran los pedidos que ya
          tienen comprobante.
        </div>

        <!-- Totales -->
        <div class="row q-col-gutter-sm q-mt-xs">
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Pedido</div>
              <div class="tarjeta-valor">{{ numero(totales.pedido) }}</div>
            </q-card>
          </div>
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Vendido</div>
              <div class="tarjeta-valor text-blue-9">{{ numero(totales.vendido) }}</div>
            </q-card>
          </div>
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Quiebre</div>
              <div class="tarjeta-valor text-negative">{{ numero(totales.quiebre) }}</div>
              <div class="tarjeta-sub">{{ porcentaje }}% de lo pedido</div>
            </q-card>
          </div>
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Productos con quiebre</div>
              <div class="tarjeta-valor">{{ filas.length }}</div>
              <div class="tarjeta-sub">
                {{ totales.parcial }} parcial · {{ totales.total }} total · {{ totales.clientes }} clientes
              </div>
            </q-card>
          </div>
        </div>

        <div class="row items-center q-gutter-sm q-mt-sm">
          <q-btn-toggle v-model="estado" dense no-caps unelevated toggle-color="primary" color="grey-3"
                        text-color="grey-9" :options="[
                          { label: 'Todos', value: '' },
                          { label: 'Parcial', value: 'QUIEBRE PARCIAL' },
                          { label: 'Total', value: 'QUIEBRE TOTAL' }
                        ]"/>
          <q-input v-model="buscar" dense outlined clearable placeholder="Buscar vendedor, cliente o producto"
                   class="col">
            <template v-slot:append><q-icon name="search"/></template>
          </q-input>
        </div>

        <q-markup-table flat bordered dense separator="horizontal" class="q-mt-sm tabla-quiebre">
          <thead>
          <tr class="bg-grey-3">
            <th class="text-left">Estado</th>
            <th class="text-left">Vendedor</th>
            <th class="text-left">Cliente</th>
            <th class="text-left">Producto</th>
            <th class="text-right">Pedido</th>
            <th class="text-right">Vendido</th>
            <th class="text-right">Quiebre</th>
          </tr>
          </thead>
          <tbody>
          <tr v-if="!visibles.length">
            <td colspan="7" class="text-center text-grey-7 q-pa-md">
              {{ cargando ? 'Cargando…' : 'No hay quiebres de stock en esta fecha' }}
            </td>
          </tr>
          <!-- Como la tabla dinamica: estado, vendedor y cliente solo cuando cambian. -->
          <tr v-for="(f, i) in visibles" :key="f.pedido_nro + '-' + f.cod_prod"
              :class="{ 'corte-estado': i > 0 && f._estado }">
            <td>
              <q-badge v-if="f._estado" :color="f.estado === 'QUIEBRE TOTAL' ? 'negative' : 'orange-8'"
                       :label="f.estado"/>
            </td>
            <td class="text-weight-bold">{{ f._vendedor ? f.vendedor : '' }}</td>
            <td>{{ f._cliente ? f.cliente : '' }}</td>
            <td>
              {{ f.producto }}
              <span class="text-grey-6">· {{ f.cod_prod }}</span>
            </td>
            <td class="text-right">{{ numero(f.pedido) }}</td>
            <td class="text-right text-blue-9">{{ numero(f.vendido) }}</td>
            <td class="text-right text-weight-bold text-negative">{{ numero(f.quiebre) }}</td>
          </tr>
          </tbody>
          <tfoot v-if="visibles.length">
          <tr class="bg-grey-3 text-weight-bold">
            <td colspan="4">Total general</td>
            <td class="text-right">{{ numero(suma('pedido')) }}</td>
            <td class="text-right">{{ numero(suma('vendido')) }}</td>
            <td class="text-right text-negative">{{ numero(suma('quiebre')) }}</td>
          </tr>
          </tfoot>
        </q-markup-table>
      </q-tab-panel>

      <!-- La planilla auxiliar del dia: stock con que se arranca, lo que vendio
           cada preventista por codigo y con cuanto se termina. -->
      <q-tab-panel name="pollo" class="q-pa-none q-pt-sm">
        <div class="row items-center q-gutter-sm">
          <q-input v-model="fechaPollo" type="date" dense outlined label="Fecha de salida" style="width: 170px"
                   @update:model-value="consultarPollo"/>
          <q-btn color="primary" icon="refresh" label="Actualizar" no-caps unelevated :loading="cargandoPollo"
                 @click="consultarPollo"/>
          <q-btn color="green-8" icon="download" label="Excel" no-caps unelevated :loading="descargandoPollo"
                 @click="descargarPollo"/>
        </div>

        <div class="text-caption text-grey-7 q-mt-xs">
          Lo vendido es lo que facturó caja: cada pedido cuenta en su fecha de entrega y la venta de mostrador en
          "Ventas del día". El stock sale del sistema. Tránsito, devolución, trozado y bajas van vacíos en el Excel
          para llenarlos a mano; el stock final se recalcula solo.
        </div>

        <div class="tabla-pollo-caja q-mt-sm">
          <table class="tabla-pollo">
            <thead>
            <tr>
              <th class="rotulo"></th>
              <th v-for="c in pollo.columnas" :key="c.cod_prod" :title="c.nombre">{{ c.cod_prod }}</th>
              <th class="entero">POLLO</th>
            </tr>
            <tr>
              <th class="rotulo">{{ diaPollo }}</th>
              <th v-for="c in pollo.columnas" :key="'n' + c.cod_prod" :title="c.nombre">{{ c.corto }}</th>
              <th class="entero">ENTERO</th>
            </tr>
            </thead>
            <tbody>
            <tr v-if="!pollo.columnas.length">
              <td :colspan="2" class="text-grey-7 q-pa-md">{{ cargandoPollo ? 'Cargando…' : 'Sin datos' }}</td>
            </tr>
            <template v-else>
              <tr class="fila-stock">
                <td class="rotulo">STOCK</td>
                <td v-for="c in pollo.columnas" :key="'s' + c.cod_prod">{{ kg(pollo.stock_inicial[c.cod_prod]) }}</td>
                <td class="entero">{{ kg(entero(pollo.stock_inicial)) }}</td>
              </tr>
              <tr v-for="v in pollo.vendedores" :key="v.nombre">
                <td class="rotulo vendedor">{{ v.nombre }}</td>
                <td v-for="c in pollo.columnas" :key="v.nombre + c.cod_prod">{{ kg(v.valores[c.cod_prod]) }}</td>
                <td class="entero">{{ kg(entero(v.valores)) }}</td>
              </tr>
              <tr class="fila-directas">
                <td class="rotulo">VENTAS DEL DÍA</td>
                <td v-for="c in pollo.columnas" :key="'d' + c.cod_prod">{{ kg(pollo.ventas_directas[c.cod_prod]) }}</td>
                <td class="entero">{{ kg(entero(pollo.ventas_directas)) }}</td>
              </tr>
              <tr class="fila-total">
                <td class="rotulo">TOTAL</td>
                <td v-for="c in pollo.columnas" :key="'t' + c.cod_prod">{{ kg(pollo.total_ventas[c.cod_prod]) }}</td>
                <td class="entero">{{ kg(entero(pollo.total_ventas)) }}</td>
              </tr>
              <tr class="fila-stock">
                <td class="rotulo">STOCK</td>
                <td v-for="c in pollo.columnas" :key="'f' + c.cod_prod"
                    :class="{ 'text-negative': pollo.stock_final[c.cod_prod] < 0 }">
                  {{ kg(pollo.stock_final[c.cod_prod]) }}
                </td>
                <td class="entero">{{ kg(entero(pollo.stock_final)) }}</td>
              </tr>
            </template>
            </tbody>
          </table>
        </div>
      </q-tab-panel>

      <!-- Todo lo facturado en el turno, una fila por producto: la planilla de
           ventas que se sacaba del sistema anterior. -->
      <q-tab-panel name="detalle" class="q-pa-none q-pt-sm">
        <div class="row items-center q-gutter-sm">
          <q-input v-model="turno.desde" type="date" dense outlined label="Desde" style="width: 150px"/>
          <q-input v-model="turno.horaDesde" type="time" dense outlined label="Hora" style="width: 105px"/>
          <q-input v-model="turno.hasta" type="date" dense outlined label="Hasta" style="width: 150px"/>
          <q-input v-model="turno.horaHasta" type="time" dense outlined label="Hora" style="width: 105px"/>
          <q-btn color="primary" icon="refresh" label="Actualizar" no-caps unelevated :loading="cargandoDetalle"
                 @click="consultarDetalle"/>
          <q-btn color="green-8" icon="download" label="Excel" no-caps unelevated :loading="descargandoDetalle"
                 :disable="!detalle.filas.length" @click="descargarDetalle"/>
        </div>

        <div class="text-caption text-grey-7 q-mt-xs">
          Facturas y vouchers no anulados del turno (la hora de cierre ya es del turno siguiente). La venta de
          mostrador sale como AGENCIA. P.COMPRA usa el precio de compra cargado en Productos.
        </div>

        <div class="row q-col-gutter-sm q-mt-xs">
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Comprobantes</div>
              <div class="tarjeta-valor">{{ detalle.totales.comprobantes }}</div>
              <div class="tarjeta-sub">{{ detalle.totales.lineas }} líneas</div>
            </q-card>
          </div>
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Importe Bs</div>
              <div class="tarjeta-valor text-positive">{{ bs(detalle.totales.importe) }}</div>
            </q-card>
          </div>
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Peso kg</div>
              <div class="tarjeta-valor">{{ numero(detalle.totales.peso) }}</div>
            </q-card>
          </div>
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">P. compra Bs</div>
              <div class="tarjeta-valor text-blue-9">{{ bs(detalle.totales.p_compra) }}</div>
            </q-card>
          </div>
        </div>

        <q-table
          class="q-mt-sm tabla-detalle" flat bordered dense virtual-scroll
          :rows="detalle.filas" :columns="columnasDetalle" :filter="buscarDetalle"
          :rows-per-page-options="[0]" row-key="_k" :loading="cargandoDetalle"
          no-data-label="No hay ventas en ese turno"
        >
          <template v-slot:top-right>
            <q-input v-model="buscarDetalle" dense outlined clearable debounce="300"
                     placeholder="Vendedor, producto, placa, documento…" style="width: 280px">
              <template v-slot:append><q-icon name="search"/></template>
            </q-input>
          </template>
        </q-table>
      </q-tab-panel>

      <!-- Kardex: sin producto, todos (una fila cada uno); eligiendo uno salen todas sus entradas y salidas,
           con la existencia que iba quedando. Por defecto, el año entero. -->
      <q-tab-panel name="kardex" class="q-pa-none q-pt-sm">
        <div class="row items-center q-gutter-sm">
          <q-select
            v-model="kardexProducto" :options="kardexOpciones" dense outlined clearable
            use-input input-debounce="300" label="Producto (código o nombre)" style="min-width: 320px"
            option-value="cod_prod" :option-label="o => o.cod_prod + ' · ' + o.producto"
            @filter="buscarKardexProducto" @update:model-value="consultarKardex"
          >
            <template v-slot:no-option>
              <q-item><q-item-section class="text-grey">Escribe para buscar</q-item-section></q-item>
            </template>
          </q-select>
          <q-input v-model="kardexDesde" type="date" dense outlined label="Desde" style="width: 150px"
                   @update:model-value="consultarKardex"/>
          <q-input v-model="kardexHasta" type="date" dense outlined label="Hasta" style="width: 150px"
                   @update:model-value="consultarKardex"/>
          <q-btn color="primary" icon="refresh" label="Actualizar" no-caps unelevated :loading="cargandoKardex"
                 @click="consultarKardex"/>
          <q-btn color="green-8" icon="download" no-caps unelevated :loading="descargandoKardex"
                 :label="kardex.todos ? 'Excel (todos)' : 'Excel'"
                 :disable="kardex.todos ? !(kardex.productos || []).length : !(kardex.movimientos || []).length" @click="descargarKardex"/>
        </div>

        <template v-if="kardex.cod_prod">
          <div class="row q-col-gutter-sm q-mt-xs">
            <div class="col-6 col-sm">
              <q-card flat bordered class="tarjeta">
                <div class="tarjeta-titulo">Saldo anterior</div>
                <div class="tarjeta-valor">{{ numero(kardex.saldo_anterior) }}</div>
              </q-card>
            </div>
            <div class="col-6 col-sm">
              <q-card flat bordered class="tarjeta">
                <div class="tarjeta-titulo">Entradas</div>
                <div class="tarjeta-valor text-green-9">{{ numero(kardex.entradas) }}</div>
              </q-card>
            </div>
            <div class="col-6 col-sm">
              <q-card flat bordered class="tarjeta">
                <div class="tarjeta-titulo">Salidas</div>
                <div class="tarjeta-valor text-negative">{{ numero(kardex.salidas) }}</div>
              </q-card>
            </div>
            <div class="col-6 col-sm">
              <q-card flat bordered class="tarjeta">
                <div class="tarjeta-titulo">Existencia final</div>
                <div class="tarjeta-valor text-blue-9">{{ numero(kardex.saldo_final) }}</div>
                <div class="tarjeta-sub">Stock del sistema hoy: {{ numero(kardex.stock_sistema) }}</div>
              </q-card>
            </div>
          </div>

          <q-input v-model="buscarKardex" dense outlined clearable placeholder="Buscar motivo, comanda o factura"
                   class="q-mt-sm">
            <template v-slot:append><q-icon name="search"/></template>
          </q-input>

          <q-markup-table flat bordered dense separator="horizontal" class="q-mt-sm tabla-quiebre">
            <thead>
            <tr class="bg-grey-3">
              <th class="text-left">Fecha registro</th>
              <th class="text-right">Entrada</th>
              <th class="text-right">Salida</th>
              <th class="text-left">Motivo ingre/egre</th>
              <th class="text-right">Existencia</th>
              <th class="text-right">Nro comanda</th>
              <th class="text-right">Nro factura</th>
              <th class="text-left">Motivo ingr. stock</th>
            </tr>
            </thead>
            <tbody>
            <tr v-if="!kardexVisibles.length">
              <td colspan="8" class="text-center text-grey-7 q-pa-md">
                {{ cargandoKardex ? 'Cargando…' : 'Sin movimientos en este rango' }}
              </td>
            </tr>
            <tr v-for="m in kardexVisibles" :key="m.id">
              <td>{{ fechaHora(m.fecha) }}</td>
              <td class="text-right text-green-9">{{ m.entrada ? numero(m.entrada) : '' }}</td>
              <td class="text-right text-negative">{{ m.salida ? numero(m.salida) : '' }}</td>
              <td>{{ m.motivo }}</td>
              <td class="text-right text-weight-bold">{{ numero(m.existencia) }}</td>
              <td class="text-right">{{ m.comanda || '' }}</td>
              <td class="text-right">{{ m.factura || '' }}</td>
              <td>{{ m.motivo_stock }}</td>
            </tr>
            </tbody>
          </q-markup-table>
        </template>
        <!-- Sin producto elegido: todos, una fila por producto. Tocando una se
             abre su kardex con cada entrada y salida. -->
        <template v-else>
          <div class="row items-center q-gutter-sm q-mt-xs">
            <div class="text-caption text-grey-8">
              Todos los productos · {{ (kardex.productos || []).length }} con movimientos o saldo ·
              toca uno para ver su detalle
            </div>
            <q-space/>
            <q-input v-model="buscarKardex" dense outlined clearable placeholder="Buscar código o producto" style="min-width: 260px">
              <template v-slot:append><q-icon name="search"/></template>
            </q-input>
          </div>
          <q-markup-table flat bordered dense separator="horizontal" class="q-mt-sm tabla-quiebre">
            <thead>
            <tr class="bg-grey-3">
              <th class="text-left">Código</th>
              <th class="text-left">Producto</th>
              <th class="text-right">Saldo anterior</th>
              <th class="text-right">Entradas</th>
              <th class="text-right">Salidas</th>
              <th class="text-right">Existencia final</th>
              <th class="text-right">Movim.</th>
              <th class="text-right">Stock sistema</th>
            </tr>
            </thead>
            <tbody>
            <tr v-if="!productosKardexVisibles.length">
              <td colspan="8" class="text-center text-grey-7 q-pa-md">
                {{ cargandoKardex ? 'Cargando…' : 'Sin movimientos en este rango' }}
              </td>
            </tr>
            <tr v-for="p in productosKardexVisibles" :key="p.cod_prod" class="cursor-pointer" @click="elegirProductoKardex(p)">
              <td>{{ p.cod_prod }}</td>
              <td>{{ p.producto }}</td>
              <td class="text-right">{{ numero(p.saldo_anterior) }}</td>
              <td class="text-right text-green-9">{{ numero(p.entradas) }}</td>
              <td class="text-right text-negative">{{ numero(p.salidas) }}</td>
              <td class="text-right text-weight-bold text-blue-9">{{ numero(p.saldo_final) }}</td>
              <td class="text-right">{{ p.movimientos }}</td>
              <td class="text-right">{{ numero(p.stock_sistema) }}</td>
            </tr>
            </tbody>
          </q-markup-table>
        </template>
      </q-tab-panel>
    </q-tab-panels>
  </q-page>
</template>

<script>
import { date } from 'quasar'

export default {
  name: 'ReportesVentas',
  data () {
    return {
      reporte: 'quiebre',
      fecha: date.formatDate(Date.now(), 'YYYY-MM-DD'),
      ciudad: '',
      semana: '',
      filas: [],
      totales: { pedido: 0, vendido: 0, quiebre: 0, parcial: 0, total: 0, clientes: 0 },
      estado: '',
      buscar: '',
      cargando: false,
      descargando: false,
      fechaPollo: date.formatDate(Date.now(), 'YYYY-MM-DD'),
      pollo: { columnas: [], stock_inicial: {}, vendedores: [], ventas_directas: {}, total_ventas: {}, stock_final: {} },
      cargandoPollo: false,
      descargandoPollo: false,
      // Detalle de ventas: el turno va de ayer a las 18:00 a hoy a las 18:00.
      turno: {
        desde: date.formatDate(date.subtractFromDate(Date.now(), { days: 1 }), 'YYYY-MM-DD'),
        horaDesde: '18:00',
        hasta: date.formatDate(Date.now(), 'YYYY-MM-DD'),
        horaHasta: '18:00'
      },
      detalle: { filas: [], totales: { lineas: 0, comprobantes: 0, peso: 0, importe: 0, p_compra: 0 } },
      buscarDetalle: '',
      cargandoDetalle: false,
      descargandoDetalle: false,
      columnasDetalle: [
        { name: 'vendedor', label: 'Vendedor', field: 'vendedor', align: 'left', sortable: true },
        { name: 'cod_prod', label: 'Cód. producto', field: 'cod_prod', align: 'left', sortable: true },
        { name: 'producto', label: 'Producto', field: 'producto', align: 'left', sortable: true },
        { name: 'peso', label: 'Peso', field: 'peso', align: 'right', sortable: true, format: v => this.numero(v) },
        { name: 'importe', label: 'Importe', field: 'importe', align: 'right', sortable: true, format: v => this.bs(v) },
        { name: 'p_compra', label: 'P. compra', field: 'p_compra', align: 'right', sortable: true, format: v => v === null ? '—' : this.bs(v) },
        { name: 'placa', label: 'Placa', field: 'placa', align: 'left', sortable: true },
        { name: 'tipo_pago', label: 'Tip. pago', field: 'tipo_pago', align: 'left', sortable: true },
        { name: 'cantidad', label: 'Cantidad', field: 'cantidad', align: 'right', sortable: true, format: v => this.numero(v) },
        { name: 'cajas', label: 'Cajas', field: 'cajas', align: 'right', sortable: true },
        { name: 'direccion', label: 'Dirección', field: 'direccion', align: 'left' },
        { name: 'peso_promedio', label: 'Peso prom.', field: 'peso_promedio', align: 'right', format: v => v === null ? '—' : this.numero(v) },
        { name: 'doc_cliente', label: 'Doc. cliente', field: 'doc_cliente', align: 'left', sortable: true },
        { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
        { name: 'nro_factura', label: 'Nro. fact.', field: 'nro_factura', align: 'right', sortable: true },
        // El Nro impreso en la boleta (el del comprobante).
        { name: 'nro_boleta', label: 'Nro. boleta', field: 'nro_boleta', align: 'right', sortable: true }
      ],
      // Kardex: por defecto el año en curso.
      kardexProducto: null,
      kardexOpciones: [],
      kardexDesde: date.formatDate(Date.now(), 'YYYY') + '-01-01',
      kardexHasta: date.formatDate(Date.now(), 'YYYY') + '-12-31',
      kardex: { movimientos: [] },
      buscarKardex: '',
      cargandoKardex: false,
      descargandoKardex: false
    }
  },
  watch: {
    // Los otros reportes se piden recien cuando se abre su pestaña.
    reporte (valor) {
      if (valor === 'pollo' && !this.pollo.columnas.length) this.consultarPollo()
      if (valor === 'detalle' && !this.detalle.filas.length) this.consultarDetalle()
      // Sin producto elegido arranca con todos los productos.
      if (valor === 'kardex' && !this.kardex.todos && !this.kardex.cod_prod) this.consultarKardex()
    }
  },
  computed: {
    productosKardexVisibles () {
      const texto = (this.buscarKardex || '').toLowerCase()
      const lista = this.kardex.productos || []
      if (!texto) return lista
      return lista.filter(p => p.cod_prod.toLowerCase().includes(texto) || p.producto.toLowerCase().includes(texto))
    },
    kardexVisibles () {
      const texto = (this.buscarKardex || '').toLowerCase()
      const lista = this.kardex.movimientos || []
      if (!texto) return lista
      return lista.filter(m =>
        [m.motivo, m.motivo_stock, m.comanda, m.factura].some(v => String(v || '').toLowerCase().includes(texto)))
    },
    // Filtradas, y marcando en que fila cambia estado, vendedor o cliente.
    visibles () {
      const texto = (this.buscar || '').toLowerCase()
      const lista = this.filas.filter(f =>
        (!this.estado || f.estado === this.estado) &&
        (!texto || [f.vendedor, f.cliente, f.producto, f.cod_prod].some(v => String(v).toLowerCase().includes(texto))))
      return lista.map((f, i) => {
        const prev = lista[i - 1]
        const _estado = !prev || prev.estado !== f.estado
        const _vendedor = _estado || prev.vendedor !== f.vendedor
        return { ...f, _estado, _vendedor, _cliente: _vendedor || prev.pedido_nro !== f.pedido_nro }
      })
    },
    porcentaje () {
      return this.totales.pedido > 0 ? Math.round(this.totales.quiebre / this.totales.pedido * 100) : 0
    },
    diaPollo () {
      if (!this.fechaPollo) return ''
      const dias = ['DOMINGO', 'LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES', 'SÁBADO']
      const d = new Date(this.fechaPollo + 'T00:00:00')
      return dias[d.getDay()] + ' ' + date.formatDate(d, 'DD/MM')
    }
  },
  created () {
    this.consultar()
  },
  methods: {
    buscarKardexProducto (texto, actualizar) {
      this.$api.get('reportes/kardex/productos', { params: { buscar: texto || '' } })
        .then(res => actualizar(() => { this.kardexOpciones = res.data }))
        .catch(() => actualizar(() => { this.kardexOpciones = [] }))
    },
    // Sin producto elegido se traen todos (una fila por producto).
    consultarKardex () {
      this.cargandoKardex = true
      this.$api.get('reportes/kardex', { params: this.paramsKardex() }).then(res => {
        this.kardex = res.data
      }).catch(err => {
        this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudo cargar el kardex' })
      }).finally(() => {
        this.cargandoKardex = false
      })
    },
    paramsKardex () {
      return { cod_prod: this.kardexProducto ? this.kardexProducto.cod_prod : '', desde: this.kardexDesde, hasta: this.kardexHasta }
    },
    elegirProductoKardex (p) {
      this.kardexProducto = { cod_prod: p.cod_prod, producto: p.producto }
      // El detalle arranca arriba, no donde estaba la fila tocada.
      window.scrollTo(0, 0)
      this.buscarKardex = ''
      this.consultarKardex()
    },
    async descargarKardex () {
      this.descargandoKardex = true
      try {
        const res = await this.$api.get('reportes/kardex/excel', { params: this.paramsKardex(), responseType: 'blob' })
        const url = window.URL.createObjectURL(res.data)
        const enlace = document.createElement('a')
        enlace.href = url
        enlace.download = 'kardex_' + (this.kardexProducto ? this.kardexProducto.cod_prod : 'todos') + '.xlsx'
        enlace.click()
        window.URL.revokeObjectURL(url)
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'No se pudo descargar el Excel' })
      } finally {
        this.descargandoKardex = false
      }
    },
    fechaHora (valor) {
      return valor ? date.formatDate(valor.replace(' ', 'T'), 'DD/MM/YYYY HH:mm') : ''
    },
    consultar () {
      this.cargando = true
      this.$api.get('reportes/quiebre-embutido', { params: { fecha: this.fecha } }).then(res => {
        this.filas = res.data.filas
        this.totales = res.data.totales
        this.ciudad = res.data.ciudad
        this.semana = res.data.semana
      }).catch(err => {
        this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudo cargar el reporte' })
      }).finally(() => {
        this.cargando = false
      })
    },
    async descargarExcel () {
      this.descargando = true
      try {
        const res = await this.$api.get('reportes/quiebre-embutido/excel', {
          params: { fecha: this.fecha },
          responseType: 'blob'
        })
        const url = window.URL.createObjectURL(res.data)
        const enlace = document.createElement('a')
        enlace.href = url
        enlace.download = 'quiebre_embutido_' + this.fecha + '.xlsx'
        enlace.click()
        window.URL.revokeObjectURL(url)
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'No se pudo descargar el Excel' })
      } finally {
        this.descargando = false
      }
    },
    consultarPollo () {
      if (!this.fechaPollo) return
      this.cargandoPollo = true
      this.$api.get('reportes/auxiliar-pollo', { params: { fecha: this.fechaPollo } }).then(res => {
        this.pollo = res.data
      }).catch(err => {
        this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudo cargar el reporte de pollo' })
      }).finally(() => {
        this.cargandoPollo = false
      })
    },
    async descargarPollo () {
      this.descargandoPollo = true
      try {
        const res = await this.$api.get('reportes/auxiliar-pollo/excel', {
          params: { fecha: this.fechaPollo },
          responseType: 'blob'
        })
        const url = window.URL.createObjectURL(res.data)
        const enlace = document.createElement('a')
        enlace.href = url
        enlace.download = 'reporte_auxiliar_pollo_' + this.fechaPollo + '.xlsx'
        enlace.click()
        window.URL.revokeObjectURL(url)
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'No se pudo descargar el Excel' })
      } finally {
        this.descargandoPollo = false
      }
    },
    paramsTurno () {
      return {
        desde: this.turno.desde,
        hora_desde: this.turno.horaDesde || '00:00',
        hasta: this.turno.hasta,
        hora_hasta: this.turno.horaHasta || '00:00'
      }
    },
    consultarDetalle () {
      if (!this.turno.desde || !this.turno.hasta) return
      this.cargandoDetalle = true
      this.$api.get('reportes/detalle-ventas', { params: this.paramsTurno() }).then(res => {
        // Clave propia: un mismo comprobante puede repetir producto.
        res.data.filas.forEach((f, i) => { f._k = i })
        this.detalle = res.data
      }).catch(err => {
        this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudo cargar el detalle de ventas' })
      }).finally(() => {
        this.cargandoDetalle = false
      })
    },
    async descargarDetalle () {
      this.descargandoDetalle = true
      try {
        const res = await this.$api.get('reportes/detalle-ventas/excel', {
          params: this.paramsTurno(),
          responseType: 'blob'
        })
        const url = window.URL.createObjectURL(res.data)
        const enlace = document.createElement('a')
        enlace.href = url
        enlace.download = 'ventas_' + this.turno.desde + '_a_' + this.turno.hasta + '.xlsx'
        enlace.click()
        window.URL.revokeObjectURL(url)
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'No se pudo descargar el Excel' })
      } finally {
        this.descargandoDetalle = false
      }
    },
    bs (valor) {
      return Number(valor || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    // Suma del pollo entero (frial y brasa) de una fila, como la ultima columna del Excel.
    entero (valores) {
      return this.pollo.columnas.filter(c => c.entero).reduce((s, c) => s + Number(valores[c.cod_prod] || 0), 0)
    },
    kg (valor) {
      return Number(valor || 0).toFixed(1)
    },
    suma (campo) {
      return this.visibles.reduce((s, f) => s + Number(f[campo] || 0), 0)
    },
    numero (valor) {
      return +Number(valor || 0).toFixed(3)
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
/* Cientos de lineas por turno: alto fijo y scroll virtual, encabezado fijo. */
.tabla-detalle {
  height: 62vh;
}
.tabla-detalle :deep(thead tr th) {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #eeeeee;
}
.tabla-detalle :deep(td),
.tabla-detalle :deep(th) {
  font-size: 11px;
  padding: 2px 6px;
  height: 22px;
}
.tabla-quiebre td,
.tabla-quiebre th {
  font-size: 12px;
}
.tabla-quiebre tr.corte-estado td {
  border-top: 2px solid #9e9e9e;
}
/* Planilla de pollo: muchas columnas, se desliza de costado. */
.tabla-pollo-caja {
  overflow-x: auto;
}
.tabla-pollo {
  border-collapse: collapse;
  font-size: 11px;
  min-width: 100%;
}
.tabla-pollo th,
.tabla-pollo td {
  border: 1px solid #bdbdbd;
  padding: 2px 4px;
  text-align: center;
  white-space: nowrap;
}
.tabla-pollo thead th {
  background: #b4c6d9;
  font-size: 10px;
}
.tabla-pollo .rotulo {
  text-align: left;
  font-weight: 700;
  min-width: 150px;
  position: sticky;
  left: 0;
  background: #fff;
}
.tabla-pollo thead .rotulo {
  background: #b4c6d9;
}
.tabla-pollo .vendedor {
  font-style: italic;
}
.tabla-pollo .entero {
  font-weight: 700;
  color: #c00000;
}
.tabla-pollo .fila-stock td {
  background: #d9c28f;
  font-weight: 700;
}
.tabla-pollo .fila-total td {
  background: #e6b9a6;
  font-weight: 700;
}
.tabla-pollo .fila-directas td {
  background: #f4e3a1;
  color: #c00000;
  font-weight: 700;
}
</style>
