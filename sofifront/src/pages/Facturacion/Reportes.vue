<template>
  <q-page class="q-pa-sm">
    <!-- Una pestaña por reporte. -->
    <q-tabs v-model="reporte" dense align="left" no-caps active-color="primary" indicator-color="primary"
            class="text-grey-8">
      <q-tab name="quiebre" icon="production_quantity_limits" label="Quiebre de stock"/>
      <q-tab name="pollo" icon="egg" label="Reporte auxiliar de pollo"/>
      <q-tab name="cerdo" icon="savings" label="Reporte de cerdo"/>
      <q-tab name="detalle" icon="receipt_long" label="Detalle de ventas"/>
      <q-tab name="kardex" icon="inventory" label="Kardex por producto"/>
      <q-tab name="ranking" icon="leaderboard" label="Productos facturados"/>
      <q-tab name="anulados" icon="block" label="Anulados de facturación"/>
      <q-tab name="general" icon="table_view" label="Reporte general"/>
    </q-tabs>
    <q-separator/>

    <q-tab-panels v-model="reporte" animated>
      <q-tab-panel name="quiebre" class="q-pa-none q-pt-sm">
        <div class="row items-center q-gutter-sm">
          <q-input v-model="fecha" type="date" dense outlined label="Fecha del pedido" style="width: 170px"
                   @update:model-value="consultar"/>
          <q-select v-model="tipo" :options="tipos" emit-value map-options dense outlined label="Tipo"
                    style="width: 170px" @update:model-value="consultar"/>
          <q-btn color="primary" icon="refresh" label="Actualizar" no-caps unelevated :loading="cargando"
                 @click="consultar"/>
          <q-btn color="green-8" icon="download" label="Excel" no-caps unelevated :loading="descargando"
                 :disable="!visibles.length" @click="descargarExcel"/>
          <q-space/>
          <div class="text-caption text-grey-8">
            {{ ciudad }} · Semana N° <b>{{ semana }}</b>
          </div>
        </div>

        <div class="text-caption text-grey-7 q-mt-xs">
          Lo que pidió cada cliente contra lo que se le vendió en caja. Un pedido enviado que no se facturó, o
          cuya venta se anuló sin reemplazo, entra entero como quiebre total; la columna Situación dice por qué.
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
            <th class="text-left">Tipo</th>
            <th class="text-left">Producto</th>
            <th class="text-right">Pedido</th>
            <th class="text-right">Vendido</th>
            <th class="text-right">Quiebre</th>
            <th class="text-left">Situación</th>
          </tr>
          </thead>
          <tbody>
          <tr v-if="!visibles.length">
            <td colspan="9" class="text-center text-grey-7 q-pa-md">
              {{ cargando ? 'Cargando…' : 'No hay quiebres de stock en esta fecha' }}
            </td>
          </tr>
          <!-- Como la tabla dinamica: estado, vendedor y cliente solo cuando cambian. -->
          <tr v-for="(f, i) in visibles" :key="f.tipo + '-' + f.pedido_nro + '-' + f.cod_prod"
              :class="{ 'corte-estado': i > 0 && f._estado }">
            <td>
              <q-badge v-if="f._estado" :color="f.estado === 'QUIEBRE TOTAL' ? 'negative' : 'orange-8'"
                       :label="f.estado"/>
            </td>
            <td class="text-weight-bold">{{ f._vendedor ? f.vendedor : '' }}</td>
            <td>{{ f._cliente ? f.cliente : '' }}</td>
            <td class="text-grey-8">{{ f.tipo }}</td>
            <td>
              {{ f.producto }}
              <span class="text-grey-6">· {{ f.cod_prod }}</span>
            </td>
            <td class="text-right">{{ numero(f.pedido) }}</td>
            <td class="text-right text-blue-9">{{ numero(f.vendido) }}</td>
            <td class="text-right text-weight-bold text-negative">{{ numero(f.quiebre) }}</td>
            <!-- Vacia si hubo venta; si no, por que el pedido no le llego al cliente. -->
            <td>
              <q-badge v-if="f.situacion" :color="f.situacion.startsWith('ANULADO') ? 'negative' : 'grey-7'"
                       :label="f.situacion" class="situacion"/>
            </td>
          </tr>
          </tbody>
          <tfoot v-if="visibles.length">
          <tr class="bg-grey-3 text-weight-bold">
            <td colspan="5">Total general</td>
            <td class="text-right">{{ numero(suma('pedido')) }}</td>
            <td class="text-right">{{ numero(suma('vendido')) }}</td>
            <td class="text-right text-negative">{{ numero(suma('quiebre')) }}</td>
            <td></td>
          </tr>
          </tfoot>
        </q-markup-table>
      </q-tab-panel>

      <!-- La planilla auxiliar del dia: stock con que se arranca, lo que vendio
           cada preventista por codigo y con cuanto se termina. -->
      <!-- Las dos planillas de cerdo con lo vendido en facturacion: por
           vendedor (entero, desmembrado, cortado) y por cortes. En cada codigo
           la cantidad; NETO son los kilos cobrados. -->
      <q-tab-panel name="cerdo" class="q-pa-none q-pt-sm">
        <div class="row items-center q-gutter-sm">
          <q-btn-toggle v-model="cerdo.reporte" dense no-caps unelevated toggle-color="primary" color="grey-3"
                        text-color="grey-9" :options="[
                          { label: 'Por vendedor (ENT/DES/COR)', value: 'enteros' },
                          { label: 'Por cortes', value: 'cortes' }
                        ]" @update:model-value="consultarCerdo"/>
          <q-input v-model="cerdo.desde" type="date" dense outlined label="Entrega desde" style="width: 160px"
                   @update:model-value="consultarCerdo"/>
          <q-input v-model="cerdo.hasta" type="date" dense outlined label="Entrega hasta" style="width: 160px"
                   @update:model-value="consultarCerdo"/>
          <q-btn color="primary" icon="refresh" label="Actualizar" no-caps unelevated :loading="cerdo.cargando"
                 @click="consultarCerdo"/>
          <q-btn color="green-8" icon="download" label="Excel" no-caps unelevated :loading="cerdo.descargando"
                 @click="descargarCerdo"/>
        </div>

        <div class="text-caption text-grey-7 q-mt-xs">
          Ventas vigentes de Facturación por fecha de entrega. En cada código va la cantidad vendida y en NETO los
          kilos cobrados. El stock va en kilos, que es como lo lleva el sistema; en el Excel, devolución y tránsito
          quedan para llenar a mano y el stock final se recalcula solo.
        </div>

        <template v-if="cerdo.datos">
          <div class="text-subtitle1 text-weight-bold text-red-9 q-mt-sm">{{ cerdo.datos.titulo }}</div>

          <!-- Stock en kilos al inicio y al final del rango. -->
          <q-markup-table flat bordered dense separator="cell" class="q-mt-xs tabla-cerdo">
            <thead>
            <tr class="cerdo-rosa">
              <th class="text-left">Kilos</th>
              <th v-for="c in cerdo.datos.columnas" :key="'h' + c.cod_prod">
                {{ c.etiqueta }}<div class="cerdo-cod">{{ c.cod_prod }}</div>
              </th>
              <th>Peso kg</th>
            </tr>
            </thead>
            <tbody>
            <tr class="cerdo-verde text-weight-bold">
              <td>Stock al inicio</td>
              <td v-for="c in cerdo.datos.columnas" :key="'i' + c.cod_prod" class="text-center">{{ numero(c.stock_inicial) }}</td>
              <td class="text-center">{{ numero(sumaColumnasCerdo('stock_inicial')) }}</td>
            </tr>
            <tr>
              <td>Kilos vendidos</td>
              <td v-for="c in cerdo.datos.columnas" :key="'k' + c.cod_prod" class="text-center text-negative">
                {{ numero(cerdo.datos.totales.kilos[c.cod_prod]) }}
              </td>
              <td class="text-center text-negative">{{ numero(cerdo.datos.totales.neto) }}</td>
            </tr>
            <tr class="cerdo-amarillo text-weight-bold">
              <td>Stock al final (sistema)</td>
              <td v-for="c in cerdo.datos.columnas" :key="'f' + c.cod_prod" class="text-center"
                  :class="{ 'text-negative': c.stock_final < 0 }">{{ numero(c.stock_final) }}</td>
              <td class="text-center">{{ numero(sumaColumnasCerdo('stock_final')) }}</td>
            </tr>
            </tbody>
          </q-markup-table>

          <q-markup-table flat bordered dense separator="cell" class="q-mt-sm tabla-cerdo">
            <thead>
            <tr class="cerdo-azul">
              <th class="text-left">Cliente</th>
              <th v-if="cerdo.datos.con_total">Total</th>
              <th v-for="c in cerdo.datos.columnas" :key="'s' + c.cod_prod">{{ c.etiqueta }}</th>
              <th>Neto kg</th>
            </tr>
            </thead>
            <tbody>
            <tr v-if="!cerdo.datos.dias.length">
              <td :colspan="cerdo.datos.columnas.length + (cerdo.datos.con_total ? 3 : 2)" class="text-center text-grey-7 q-pa-md">
                {{ cerdo.cargando ? 'Cargando…' : 'Sin ventas de estos códigos en el rango' }}
              </td>
            </tr>
            <template v-for="dia in cerdo.datos.dias" :key="dia.fecha">
              <tr v-if="cerdo.datos.dias.length > 1" class="cerdo-azul">
                <td :colspan="cerdo.datos.columnas.length + (cerdo.datos.con_total ? 3 : 2)" class="text-weight-bold">
                  {{ fechaCorta(dia.fecha) }}
                </td>
              </tr>
              <template v-for="v in dia.vendedores" :key="dia.fecha + v.vendedor">
                <tr>
                  <td :colspan="cerdo.datos.columnas.length + (cerdo.datos.con_total ? 3 : 2)" class="cerdo-vendedor">
                    {{ v.vendedor }}
                  </td>
                </tr>
                <tr v-for="f in v.filas" :key="f.id">
                  <td>
                    {{ f.cliente }}
                    <span class="text-grey-6 text-caption">· #{{ f.id }}<template v-if="f.pedido"> · ped {{ f.pedido }}</template></span>
                  </td>
                  <td v-if="cerdo.datos.con_total" class="text-center text-weight-bold">{{ numero(f.total) }}</td>
                  <td v-for="c in cerdo.datos.columnas" :key="f.id + c.cod_prod" class="text-center">
                    {{ f.cantidades[c.cod_prod] ? numero(f.cantidades[c.cod_prod]) : '' }}
                  </td>
                  <td class="text-center text-weight-bold">{{ numero(f.neto) }}</td>
                </tr>
              </template>
            </template>
            </tbody>
            <tfoot v-if="cerdo.datos.dias.length">
            <tr class="cerdo-azul text-weight-bold text-red-9">
              <td>TOTAL VENTAS ({{ cerdo.datos.totales.comprobantes }})</td>
              <td v-if="cerdo.datos.con_total" class="text-center">{{ numero(cerdo.datos.totales.total) }}</td>
              <td v-for="c in cerdo.datos.columnas" :key="'t' + c.cod_prod" class="text-center">
                {{ numero(cerdo.datos.totales.cantidades[c.cod_prod]) }}
              </td>
              <td class="text-center">{{ numero(cerdo.datos.totales.neto) }}</td>
            </tr>
            </tfoot>
          </q-markup-table>
        </template>
      </q-tab-panel>

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
          <q-input v-model="turno.desde" type="date" dense outlined label="Entrega desde" style="width: 160px"
                   @update:model-value="consultarDetalle"/>
          <q-input v-model="turno.hasta" type="date" dense outlined label="Entrega hasta" style="width: 160px"
                   @update:model-value="consultarDetalle"/>
          <q-btn color="primary" icon="refresh" label="Actualizar" no-caps unelevated :loading="cargandoDetalle"
                 @click="consultarDetalle"/>
          <q-btn color="green-8" icon="download" label="Excel" no-caps unelevated :loading="descargandoDetalle"
                 :disable="!detalle.filas.length" @click="descargarDetalle"/>
        </div>

        <div class="text-caption text-grey-7 q-mt-xs">
          Facturas y vouchers no anulados por fecha de entrega (el día del reparto, como en Facturación). La venta de
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
          no-data-label="No hay ventas con esa fecha de entrega"
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

      <!-- Lo que salio en facturacion en el rango, un producto por fila, de mayor a menor. -->
      <q-tab-panel name="ranking" class="q-pa-none q-pt-sm">
        <div class="row items-center q-gutter-sm">
          <q-input v-model="ranking.desde" type="date" dense outlined label="Desde" style="width: 150px"
                   @update:model-value="consultarRanking"/>
          <q-input v-model="ranking.hasta" type="date" dense outlined label="Hasta" style="width: 150px"
                   @update:model-value="consultarRanking"/>
          <q-btn-toggle v-model="ranking.orden" dense no-caps unelevated toggle-color="primary" color="grey-3"
                        text-color="grey-9" :options="[
                          { label: 'Por importe', value: 'importe' },
                          { label: 'Por peso', value: 'peso' },
                          { label: 'Por cantidad', value: 'cantidad' }
                        ]" @update:model-value="consultarRanking"/>
          <q-btn color="primary" icon="refresh" label="Actualizar" no-caps unelevated :loading="cargandoRanking"
                 @click="consultarRanking"/>
          <q-btn color="green-8" icon="download" label="Excel" no-caps unelevated :loading="descargandoRanking === 'excel'"
                 :disable="!ranking.datos.filas.length" @click="descargarRanking('excel')"/>
          <q-btn color="red-8" icon="picture_as_pdf" label="PDF" no-caps unelevated :loading="descargandoRanking === 'pdf'"
                 :disable="!ranking.datos.filas.length" @click="descargarRanking('pdf')"/>
        </div>

        <div class="text-caption text-grey-7 q-mt-xs">
          Facturas y vouchers no anulados entre las dos fechas (ambas incluidas), de pedidos y de mostrador.
        </div>

        <div class="row q-col-gutter-sm q-mt-xs">
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Productos</div>
              <div class="tarjeta-valor">{{ ranking.datos.totales.productos }}</div>
              <div class="tarjeta-sub">{{ ranking.datos.totales.comprobantes }} comprobantes</div>
            </q-card>
          </div>
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Importe Bs</div>
              <div class="tarjeta-valor text-positive">{{ bs(ranking.datos.totales.importe) }}</div>
            </q-card>
          </div>
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Peso kg</div>
              <div class="tarjeta-valor">{{ numero(ranking.datos.totales.peso) }}</div>
            </q-card>
          </div>
          <div class="col-6 col-sm-3">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">Cantidad</div>
              <div class="tarjeta-valor">{{ numero(ranking.datos.totales.cantidad) }}</div>
            </q-card>
          </div>
        </div>

        <q-input v-model="buscarRanking" dense outlined clearable placeholder="Buscar código o producto" class="q-mt-sm">
          <template v-slot:append><q-icon name="search"/></template>
        </q-input>

        <q-markup-table flat bordered dense separator="horizontal" class="q-mt-sm tabla-quiebre">
          <thead>
          <tr class="bg-grey-3">
            <th class="text-right">N°</th>
            <th class="text-left">Código</th>
            <th class="text-left">Producto</th>
            <th class="text-left">Unidad</th>
            <th class="text-right">Cantidad</th>
            <th class="text-right">Peso kg</th>
            <th class="text-right">Importe Bs</th>
            <th class="text-right">%</th>
            <th class="text-right">Comprob.</th>
            <th class="text-right">Clientes</th>
          </tr>
          </thead>
          <tbody>
          <tr v-if="!rankingVisibles.length">
            <td colspan="10" class="text-center text-grey-7 q-pa-md">
              {{ cargandoRanking ? 'Cargando…' : 'No hay productos facturados en este rango' }}
            </td>
          </tr>
          <tr v-for="p in rankingVisibles" :key="p.cod_prod">
            <td class="text-right text-grey-7">{{ p.puesto }}</td>
            <td>{{ p.cod_prod }}</td>
            <td>{{ p.producto }}</td>
            <td>{{ p.unidad }}</td>
            <td class="text-right" :class="{ 'text-weight-bold': ranking.datos.orden === 'cantidad' }">{{ numero(p.cantidad) }}</td>
            <td class="text-right" :class="{ 'text-weight-bold': ranking.datos.orden === 'peso' }">{{ numero(p.peso) }}</td>
            <td class="text-right" :class="{ 'text-weight-bold': ranking.datos.orden === 'importe' }">{{ bs(p.importe) }}</td>
            <td class="text-right text-grey-7">{{ p.porcentaje.toFixed(1) }}</td>
            <td class="text-right">{{ p.comprobantes }}</td>
            <td class="text-right">{{ p.clientes }}</td>
          </tr>
          </tbody>
        </q-markup-table>
      </q-tab-panel>

      <!-- Anulados y reporte general comparten armado: comprobantes de
           facturacion por fecha de entrega, con su cadena padre/hijo. -->
      <q-tab-panel v-for="panel in ['anulados', 'general']" :key="panel" :name="panel" class="q-pa-none q-pt-sm">
        <div class="row items-center q-gutter-sm">
          <q-input v-model="comp[panel].desde" type="date" dense outlined label="Entrega desde" style="width: 160px"
                   @update:model-value="consultarComp(panel)"/>
          <q-input v-model="comp[panel].hasta" type="date" dense outlined label="Entrega hasta" style="width: 160px"
                   @update:model-value="consultarComp(panel)"/>
          <q-select v-model="comp[panel].pedidoTipo" :options="tiposComp" emit-value map-options dense outlined
                    label="Tipo de pedido" style="width: 170px" @update:model-value="consultarComp(panel)"/>
          <q-btn color="primary" icon="refresh" label="Actualizar" no-caps unelevated :loading="comp[panel].cargando"
                 @click="consultarComp(panel)"/>
          <q-btn color="green-8" icon="download" label="Excel completo" no-caps unelevated
                 :loading="comp[panel].descargando === 'general'" :disable="!comp[panel].filas.length"
                 @click="descargarComp(panel, 'general')">
            <q-tooltip>Resumen, comprobantes, productos en plano y padres/hijos</q-tooltip>
          </q-btn>
          <q-btn v-if="panel === 'anulados'" color="blue-grey-7" icon="download" label="Excel por producto" no-caps
                 unelevated :loading="comp[panel].descargando === 'caja'" :disable="!comp[panel].filas.length"
                 @click="descargarComp(panel, 'caja')">
            <q-tooltip>Una fila por producto anulado, como el reporte de anulados de caja</q-tooltip>
          </q-btn>
        </div>

        <div class="text-caption text-grey-7 q-mt-xs">
          <template v-if="panel === 'anulados'">
            Comprobantes anulados en Facturación por fecha de entrega: quién y cuándo anuló, el motivo y con qué
            comprobante se reemplazó (hijo).
          </template>
          <template v-else>
            Todos los comprobantes de Facturación por fecha de entrega, incluidos los anulados (en rojo): pedido,
            camión, cliente, quién cobró y la cadena padre/hijo de los refacturados y retornos parciales.
          </template>
        </div>

        <div class="row q-col-gutter-sm q-mt-xs">
          <div v-for="t in tarjetasComp(panel)" :key="t.titulo" class="col-6 col-sm">
            <q-card flat bordered class="tarjeta">
              <div class="tarjeta-titulo">{{ t.titulo }}</div>
              <div class="tarjeta-valor" :class="t.clase">{{ t.valor }}</div>
              <div v-if="t.sub" class="tarjeta-sub">{{ t.sub }}</div>
            </q-card>
          </div>
        </div>

        <div v-if="panel === 'general'" class="row items-center q-gutter-sm q-mt-xs">
          <q-btn-toggle v-model="comp.general.relacion" dense no-caps unelevated toggle-color="primary" color="grey-3"
                        text-color="grey-9" :options="[
                          { label: 'Todos', value: '' },
                          { label: 'Vigentes', value: 'VIGENTE' },
                          { label: 'Anulados', value: 'ANULADO' },
                          { label: 'Padres e hijos', value: 'CADENA' }
                        ]"/>
        </div>

        <q-table
          class="q-mt-sm tabla-detalle" flat bordered dense virtual-scroll
          :rows="visiblesComp(panel)" :columns="panel === 'anulados' ? columnasAnulados : columnasGeneral"
          :filter="comp[panel].buscar" :rows-per-page-options="[0]" row-key="id" :loading="comp[panel].cargando"
          :no-data-label="panel === 'anulados' ? 'No hay anulados con esa fecha de entrega' : 'No hay comprobantes con esa fecha de entrega'"
        >
          <template v-slot:top-right>
            <q-input v-model="comp[panel].buscar" dense outlined clearable debounce="300"
                     placeholder="Cliente, pedido, camión, ID…" style="width: 280px">
              <template v-slot:append><q-icon name="search"/></template>
            </q-input>
          </template>
          <!-- Lo anulado del reporte general va en rojo, toda la fila. -->
          <template v-slot:body-cell="props">
            <q-td :props="props" :class="{ 'celda-anulada': panel === 'general' && props.row.anulado }">
              {{ props.value }}
            </q-td>
          </template>
          <template v-slot:body-cell-relacion="props">
            <q-td :props="props" :class="{ 'celda-anulada': panel === 'general' && props.row.anulado }">
              <q-badge v-if="props.value !== 'ÚNICO'" :color="colorRelacion(props.value)" :label="props.value"/>
              <span v-else class="text-grey-6">ÚNICO</span>
            </q-td>
          </template>
          <template v-slot:body-cell-estado="props">
            <q-td :props="props" :class="{ 'celda-anulada': panel === 'general' && props.row.anulado }">
              <q-badge :color="props.value === 'ANULADO' ? 'negative' : 'positive'" :label="props.value"/>
            </q-td>
          </template>
        </q-table>
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
      tipo: 'NORMAL',
      tipos: [
        { label: 'Todos', value: 'TODOS' },
        { label: 'Embutido', value: 'NORMAL' },
        { label: 'Pollo', value: 'POLLO' },
        { label: 'Cerdo', value: 'CERDO' },
        { label: 'Res', value: 'RES' },
        { label: 'Podium', value: 'PODIUM' },
        { label: 'Huevo', value: 'HUEVO' }
      ],
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
      // Detalle de ventas: por fecha de entrega, por defecto la de hoy.
      turno: {
        desde: date.formatDate(Date.now(), 'YYYY-MM-DD'),
        hasta: date.formatDate(Date.now(), 'YYYY-MM-DD')
      },
      detalle: { filas: [], totales: { lineas: 0, comprobantes: 0, peso: 0, importe: 0, p_compra: 0 } },
      buscarDetalle: '',
      cargandoDetalle: false,
      descargandoDetalle: false,
      columnasDetalle: [
        { name: 'fecha_entrega', label: 'F. entrega', field: 'fecha_entrega', align: 'left', sortable: true },
        { name: 'fecha_hora', label: 'Emitido', field: 'fecha_hora', align: 'left', sortable: true },
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
        // En una baja: a quien se le dio (Cliente es la cuenta de baja).
        { name: 'recibio', label: 'Recibió', field: 'recibio', align: 'left', sortable: true },
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
      descargandoKardex: false,
      // Productos facturados: por defecto, el mes en curso hasta hoy.
      ranking: {
        desde: date.formatDate(Date.now(), 'YYYY-MM') + '-01',
        hasta: date.formatDate(Date.now(), 'YYYY-MM-DD'),
        orden: 'importe',
        datos: { filas: [], totales: { productos: 0, comprobantes: 0, cantidad: 0, peso: 0, importe: 0 } }
      },
      buscarRanking: '',
      cargandoRanking: false,
      descargandoRanking: '',
      // Reporte de cerdo: por fecha de entrega, por defecto hoy.
      cerdo: {
        reporte: 'enteros',
        desde: date.formatDate(Date.now(), 'YYYY-MM-DD'),
        hasta: date.formatDate(Date.now(), 'YYYY-MM-DD'),
        datos: null,
        cargando: false,
        descargando: false
      },
      // Anulados y reporte general: por fecha de entrega, por defecto hoy.
      comp: {
        anulados: this.nuevoComp(),
        general: Object.assign(this.nuevoComp(), { relacion: '' })
      },
      tiposComp: [
        { label: 'Todos', value: '' },
        { label: 'Embutidos', value: 'NORMAL' },
        { label: 'Pollo', value: 'POLLO' },
        { label: 'Cerdo', value: 'CERDO' },
        { label: 'Res', value: 'RES' },
        { label: 'Podium', value: 'PODIUM' },
        { label: 'Huevo', value: 'HUEVO' }
      ],
      columnasAnulados: [
        { name: 'id', label: 'ID', field: 'id', align: 'right', sortable: true },
        { name: 'comprobante', label: 'Comprob.', field: 'comprobante', align: 'left', sortable: true },
        { name: 'nro_factura', label: 'Nro fact.', field: 'nro_factura', align: 'right', sortable: true },
        { name: 'fecha', label: 'Emitido', field: f => f.fecha + ' ' + f.hora, align: 'left' },
        { name: 'fecha_entrega', label: 'F. entrega', field: 'fecha_entrega', align: 'left', sortable: true },
        { name: 'pedido', label: 'Pedido', field: 'pedido', align: 'left', sortable: true },
        { name: 'pedido_tipo', label: 'Tipo', field: 'pedido_tipo', align: 'left', sortable: true },
        { name: 'camion', label: 'Camión', field: 'camion', align: 'left', sortable: true },
        { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
        { name: 'preventista', label: 'Preventista', field: 'preventista', align: 'left', sortable: true },
        { name: 'total', label: 'Total Bs', field: 'total', align: 'right', sortable: true, format: v => this.bs(v) },
        { name: 'anulado_at', label: 'Anulado el', field: 'anulado_at', align: 'left' },
        { name: 'anulado_por', label: 'Anulado por', field: 'anulado_por', align: 'left', sortable: true },
        { name: 'motivo', label: 'Motivo', field: 'motivo', align: 'left', sortable: true },
        { name: 'relacion', label: 'Relación', field: 'relacion', align: 'left', sortable: true },
        { name: 'hijo', label: 'Reemplazado por', field: 'hijo', align: 'left' }
      ],
      columnasGeneral: [
        { name: 'id', label: 'ID', field: 'id', align: 'right', sortable: true },
        { name: 'estado', label: 'Estado', field: 'estado', align: 'left', sortable: true },
        { name: 'comprobante', label: 'Comprob.', field: 'comprobante', align: 'left', sortable: true },
        { name: 'nro_factura', label: 'Nro fact.', field: 'nro_factura', align: 'right', sortable: true },
        { name: 'fecha', label: 'Emitido', field: f => f.fecha + ' ' + f.hora, align: 'left' },
        { name: 'pedido', label: 'Pedido', field: 'pedido', align: 'left', sortable: true },
        { name: 'pedido_tipo', label: 'Tipo', field: 'pedido_tipo', align: 'left', sortable: true },
        { name: 'camion', label: 'Camión', field: 'camion', align: 'left', sortable: true },
        // Lo que marco el camionero al entregar.
        { name: 'camionero', label: 'Camionero', field: 'camionero', align: 'left', sortable: true },
        { name: 'entrega_estado', label: 'Entrega', field: 'entrega_estado', align: 'left', sortable: true },
        { name: 'entrega_fecha', label: 'F./hora entrega', field: 'entrega_fecha', align: 'left', sortable: true },
        { name: 'entrega_tipago', label: 'Cobro camionero', field: 'entrega_tipago', align: 'left', sortable: true },
        { name: 'entrega_cobrado', label: 'Cobrado Bs', field: 'entrega_cobrado', align: 'right', sortable: true, format: v => v === null ? '—' : this.bs(v) },
        { name: 'entrega_efectivo', label: 'Efectivo cam. Bs', field: 'entrega_efectivo', align: 'right', sortable: true, format: v => v === null ? '—' : this.bs(v) },
        { name: 'entrega_qr', label: 'QR cam. Bs', field: 'entrega_qr', align: 'right', sortable: true, format: v => v === null ? '—' : this.bs(v) },
        { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
        { name: 'nit', label: 'NIT / CI', field: 'nit', align: 'left' },
        { name: 'pago', label: 'Pago', field: 'pago', align: 'left', sortable: true },
        { name: 'efectivo', label: 'Efectivo Bs', field: 'efectivo', align: 'right', sortable: true, format: v => this.bs(v) },
        { name: 'qr', label: 'QR Bs', field: 'qr', align: 'right', sortable: true, format: v => this.bs(v) },
        { name: 'total', label: 'Total Bs', field: 'total', align: 'right', sortable: true, format: v => this.bs(v) },
        { name: 'lineas', label: 'Prod.', field: 'lineas', align: 'right' },
        { name: 'cadena', label: 'Cadena', field: 'cadena', align: 'right', sortable: true },
        { name: 'relacion', label: 'Relación', field: 'relacion', align: 'left', sortable: true },
        { name: 'padre', label: 'Padre', field: 'padre', align: 'right' },
        { name: 'hijo', label: 'Hijo', field: 'hijo', align: 'left' },
        { name: 'cambio_padre', label: 'Qué cambió', field: 'cambio_padre', align: 'left',
          style: 'max-width: 260px; white-space: normal' },
        { name: 'usuario', label: 'Cobró', field: 'usuario', align: 'left', sortable: true },
        { name: 'motivo', label: 'Motivo anulación', field: 'motivo', align: 'left' }
      ]
    }
  },
  watch: {
    // Los otros reportes se piden recien cuando se abre su pestaña.
    reporte (valor) {
      if (valor === 'pollo' && !this.pollo.columnas.length) this.consultarPollo()
      if (valor === 'detalle' && !this.detalle.filas.length) this.consultarDetalle()
      // Sin producto elegido arranca con todos los productos.
      if (valor === 'kardex' && !this.kardex.todos && !this.kardex.cod_prod) this.consultarKardex()
      if (valor === 'ranking' && !this.ranking.datos.filas.length) this.consultarRanking()
      if (valor === 'cerdo' && !this.cerdo.datos) this.consultarCerdo()
      if ((valor === 'anulados' || valor === 'general') && !this.comp[valor].consultado) this.consultarComp(valor)
    }
  },
  computed: {
    rankingVisibles () {
      const texto = (this.buscarRanking || '').toLowerCase()
      const lista = this.ranking.datos.filas
      if (!texto) return lista
      return lista.filter(p => p.cod_prod.toLowerCase().includes(texto) || p.producto.toLowerCase().includes(texto))
    },
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
    paramsCerdo () {
      return { reporte: this.cerdo.reporte, desde: this.cerdo.desde, hasta: this.cerdo.hasta || this.cerdo.desde }
    },
    consultarCerdo () {
      if (!this.cerdo.desde) return
      this.cerdo.cargando = true
      this.$api.get('reportes/cerdo', { params: this.paramsCerdo() })
        .then(res => { this.cerdo.datos = res.data })
        .catch(err => {
          this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudo cargar el reporte de cerdo' })
        })
        .finally(() => { this.cerdo.cargando = false })
    },
    async descargarCerdo () {
      this.cerdo.descargando = true
      try {
        const params = Object.assign({ formato: 'excel' }, this.paramsCerdo())
        const res = await this.$api.get('reportes/cerdo', { params, responseType: 'blob' })
        const enlace = document.createElement('a')
        enlace.href = window.URL.createObjectURL(res.data)
        const rango = params.desde === params.hasta ? params.desde : params.desde + '_a_' + params.hasta
        enlace.download = 'cerdo_' + params.reporte + '_' + rango + '.xlsx'
        enlace.click()
        window.URL.revokeObjectURL(enlace.href)
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'No se pudo descargar el Excel' })
      } finally {
        this.cerdo.descargando = false
      }
    },
    sumaColumnasCerdo (campo) {
      return (this.cerdo.datos?.columnas || []).reduce((s, c) => s + Number(c[campo] || 0), 0)
    },
    fechaCorta (fecha) {
      return fecha ? date.formatDate(fecha + 'T00:00:00', 'DD/MM/YYYY') : ''
    },
    nuevoComp () {
      const hoy = date.formatDate(Date.now(), 'YYYY-MM-DD')
      return { desde: hoy, hasta: hoy, pedidoTipo: '', filas: [], buscar: '', cargando: false, descargando: '', consultado: false }
    },
    // Los mismos filtros que la pantalla de Facturacion (por fecha de entrega).
    paramsComp (panel) {
      const c = this.comp[panel]
      return {
        fecha_entrega: c.desde,
        fecha_entrega_hasta: c.hasta || c.desde,
        pedido_tipo: c.pedidoTipo || '',
        solo_anulados: panel === 'anulados' ? 1 : 0
      }
    },
    consultarComp (panel) {
      const c = this.comp[panel]
      if (!c.desde) return
      c.cargando = true
      c.consultado = true
      this.$api.get('facturacion/reporte-general', { params: Object.assign({ formato: 'json' }, this.paramsComp(panel)) })
        .then(res => { c.filas = res.data.filas })
        .catch(err => {
          this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudo cargar el reporte' })
        })
        .finally(() => { c.cargando = false })
    },
    // tipo: 'general' (el libro completo) o 'caja' (anulados por producto).
    async descargarComp (panel, tipo) {
      const c = this.comp[panel]
      c.descargando = tipo
      const params = this.paramsComp(panel)
      const url = tipo === 'caja' ? 'facturacion/reporte' : 'facturacion/reporte-general'
      if (tipo === 'caja') Object.assign(params, { contenido: 'anulados', formato: 'excel' })
      try {
        const res = await this.$api.get(url, { params, responseType: 'blob' })
        const enlace = document.createElement('a')
        enlace.href = window.URL.createObjectURL(res.data)
        const rango = c.hasta && c.hasta !== c.desde ? c.desde + '_a_' + c.hasta : c.desde
        enlace.download = (panel === 'anulados' ? (tipo === 'caja' ? 'anulados_por_producto_' : 'anulados_') : 'reporte_general_') +
          rango + '.xlsx'
        enlace.click()
        window.URL.revokeObjectURL(enlace.href)
      } catch (err) {
        let mensaje = 'No se pudo descargar el Excel'
        try {
          mensaje = JSON.parse(await err.response.data.text()).message || mensaje
        } catch (e) { /* el error no vino en JSON */ }
        this.$q.notify({ type: 'negative', message: mensaje })
      } finally {
        c.descargando = ''
      }
    },
    visiblesComp (panel) {
      const filas = this.comp[panel].filas
      if (panel !== 'general' || !this.comp.general.relacion) return filas
      const relacion = this.comp.general.relacion
      if (relacion === 'VIGENTE') return filas.filter(f => !f.anulado)
      if (relacion === 'ANULADO') return filas.filter(f => f.anulado)
      // Las cadenas completas, ordenadas para que cada hijo quede junto a su padre.
      return filas.filter(f => f.relacion !== 'ÚNICO')
        .slice().sort((a, b) => a.cadena - b.cadena || a.version - b.version)
    },
    tarjetasComp (panel) {
      const filas = this.comp[panel].filas
      const suma = lista => lista.reduce((s, f) => s + Number(f.total || 0), 0)
      if (panel === 'anulados') {
        const reemplazados = filas.filter(f => f.hijo).length
        return [
          { titulo: 'Anulados', valor: filas.length },
          { titulo: 'Importe anulado Bs', valor: this.bs(suma(filas)), clase: 'text-negative' },
          { titulo: 'Reemplazados', valor: reemplazados, sub: 'refacturados con otro comprobante' },
          { titulo: 'Sin reemplazo', valor: filas.length - reemplazados, clase: 'text-orange-9' }
        ]
      }
      const vigentes = filas.filter(f => !f.anulado)
      const anulados = filas.filter(f => f.anulado)
      return [
        { titulo: 'Comprobantes', valor: filas.length, sub: vigentes.length + ' vigentes · ' + anulados.length + ' anulados' },
        { titulo: 'Total vigente Bs', valor: this.bs(suma(vigentes)), clase: 'text-positive' },
        { titulo: 'Total anulado Bs', valor: this.bs(suma(anulados)), clase: 'text-negative' },
        { titulo: 'Refacturados', valor: filas.filter(f => f.relacion.includes('REFACTURADO')).length, sub: 'hijos por edición' },
        { titulo: 'Retornos parciales', valor: filas.filter(f => f.relacion.includes('RETORNO')).length }
      ]
    },
    colorRelacion (relacion) {
      if (relacion.startsWith('PADRE')) return 'deep-purple-6'
      if (relacion.includes('RETORNO')) return 'teal-7'
      return 'indigo-5'
    },
    paramsRanking () {
      return { desde: this.ranking.desde, hasta: this.ranking.hasta, orden: this.ranking.orden }
    },
    consultarRanking () {
      if (!this.ranking.desde || !this.ranking.hasta) return
      this.cargandoRanking = true
      this.$api.get('reportes/productos-vendidos', { params: this.paramsRanking() }).then(res => {
        this.ranking.datos = res.data
      }).catch(err => {
        this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudo cargar el reporte' })
      }).finally(() => {
        this.cargandoRanking = false
      })
    },
    async descargarRanking (formato) {
      this.descargandoRanking = formato
      try {
        const res = await this.$api.get('reportes/productos-vendidos/' + formato, {
          params: this.paramsRanking(),
          responseType: 'blob'
        })
        const url = window.URL.createObjectURL(res.data)
        const enlace = document.createElement('a')
        enlace.href = url
        enlace.download = 'productos_facturados_' + this.ranking.desde + '_a_' + this.ranking.hasta +
          (formato === 'pdf' ? '.pdf' : '.xlsx')
        enlace.click()
        window.URL.revokeObjectURL(url)
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'No se pudo descargar el ' + (formato === 'pdf' ? 'PDF' : 'Excel') })
      } finally {
        this.descargandoRanking = ''
      }
    },
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
      this.$api.get('reportes/quiebre-embutido', { params: { fecha: this.fecha, tipo: this.tipo } }).then(res => {
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
          // El Excel sale con lo mismo que se ve: tipo, estado y búsqueda.
          params: { fecha: this.fecha, tipo: this.tipo, estado: this.estado, buscar: this.buscar || '' },
          responseType: 'blob'
        })
        const url = window.URL.createObjectURL(res.data)
        const enlace = document.createElement('a')
        enlace.href = url
        const nombre = (this.tipos.find(t => t.value === this.tipo)?.label || 'todos').toLowerCase().replace(/ /g, '_')
        enlace.download = 'quiebre_' + nombre + '_' + this.fecha + '.xlsx'
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
      return { desde: this.turno.desde, hasta: this.turno.hasta }
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
.tabla-detalle :deep(td.celda-anulada) {
  background: #ffebee;
  color: #b71c1c;
}
.tabla-cerdo th,
.tabla-cerdo td {
  font-size: 12px;
}
.tabla-cerdo .cerdo-cod {
  font-size: 9px;
  font-weight: 400;
  color: #1155cc;
}
.cerdo-rosa th { background: #f4cccc; color: #c00000; }
.cerdo-azul th, .cerdo-azul td { background: #c9daf8; }
.cerdo-verde td { background: #d9ead3; }
.cerdo-amarillo td { background: #fff2cc; }
.cerdo-vendedor {
  text-align: center;
  font-weight: 700;
  font-style: italic;
  color: #e06666;
}
.situacion {
  white-space: normal;
  text-align: left;
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
