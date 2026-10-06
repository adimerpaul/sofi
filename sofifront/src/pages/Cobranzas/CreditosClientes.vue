<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-gutter-sm q-mb-sm">
      <div class="text-h6">Créditos de clientes</div>
      <q-space/>
      <!-- Lo que cobranzas usa: deudores y los cobros del dia para el deposito. -->
      <q-btn-dropdown unelevated dense no-caps color="teal-8" icon="payments" label="Excel cobros">
        <div class="q-pa-sm column q-gutter-sm" style="min-width: 240px">
          <div class="text-caption text-grey-8">Cobros registrados entre:</div>
          <q-input v-model="rangoCobros.desde" type="date" dense outlined label="Desde"/>
          <q-input v-model="rangoCobros.hasta" type="date" dense outlined label="Hasta"/>
          <q-btn unelevated dense no-caps color="teal-8" icon="download" label="Descargar"
                 :loading="descargandoCobros" @click="excelCobros"/>
        </div>
      </q-btn-dropdown>
      <!-- Cierre de caja: lo que cobro cada usuario entre fecha y hora. -->
      <q-btn-dropdown unelevated dense no-caps color="indigo-7" icon="point_of_sale" label="Cierre de caja"
                      @show="cargarCobradores">
        <div class="q-pa-sm column q-gutter-sm" style="min-width: 280px">
          <div class="text-caption text-grey-8">Cobros registrados entre:</div>
          <div class="row q-col-gutter-xs">
            <div class="col-7"><q-input v-model="cierre.desde" type="date" dense outlined label="Desde" @update:model-value="cargarCobradores"/></div>
            <div class="col-5"><q-input v-model="cierre.horaDesde" type="time" dense outlined label="Hora" @update:model-value="cargarCobradores"/></div>
            <div class="col-7"><q-input v-model="cierre.hasta" type="date" dense outlined label="Hasta" @update:model-value="cargarCobradores"/></div>
            <div class="col-5"><q-input v-model="cierre.horaHasta" type="time" dense outlined label="Hora" @update:model-value="cargarCobradores"/></div>
          </div>
          <q-select
            v-model="cierre.usuario" :options="cobradores" dense outlined clearable emit-value map-options
            option-value="user_id" :option-label="c => c.nombre + ' · ' + c.cobros + ' cobros · Bs ' + money(c.total)"
            label="Cajero (vacío = todos)" :loading="cargandoCobradores"
          >
            <template v-slot:no-option>
              <q-item><q-item-section class="text-grey">Nadie cobró en ese rango</q-item-section></q-item>
            </template>
          </q-select>
          <q-btn unelevated dense no-caps color="indigo-7" icon="download" label="Descargar cierre"
                 :loading="descargandoCierre" @click="excelCierre"/>
        </div>
      </q-btn-dropdown>
      <q-btn unelevated dense no-caps color="green-8" icon="grid_on" label="Excel deudores"
             :loading="descargandoExcel" @click="excelDeudores">
        <q-tooltip>Cuentas por cobrar (formato debito sumado): una fila por deuda, con filtros y totales</q-tooltip>
      </q-btn>
      <q-btn outline dense no-caps color="primary" icon="local_shipping" label="Reportes de camiones" to="/cobranzas/recojo"/>
      <q-btn unelevated dense no-caps color="red-7" icon="add" label="Agregar deuda" @click="nuevaDeuda()"/>
      <q-btn flat dense round icon="refresh" color="primary" :loading="cargando" @click="cargar">
        <q-tooltip>Actualizar</q-tooltip>
      </q-btn>
    </div>

    <!-- Lo que se mira primero: cuanto hay en la calle y quienes lo deben. -->
    <div class="row q-col-gutter-sm q-mb-sm">
      <div class="col-6 col-md-3">
        <q-card flat bordered class="tarjeta bg-red-1">
          <div class="tarjeta-rotulo text-red-9">DEUDA TOTAL</div>
          <div class="tarjeta-valor text-red-10">Bs {{ money(totales.saldo) }}</div>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card flat bordered class="tarjeta bg-orange-1 cursor-pointer" @click="filtro = 'deuda'">
          <div class="tarjeta-rotulo text-orange-9">CLIENTES QUE DEBEN</div>
          <div class="tarjeta-valor text-orange-10">{{ totales.con_deuda }}</div>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card flat bordered class="tarjeta bg-blue-grey-1">
          <div class="tarjeta-rotulo text-blue-grey-8">DEUDAS PENDIENTES</div>
          <div class="tarjeta-valor text-blue-grey-10">{{ totales.deudas }}</div>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card flat bordered class="tarjeta bg-grey-2 cursor-pointer" @click="filtro = 'todos'">
          <div class="tarjeta-rotulo text-grey-8">CLIENTES</div>
          <div class="tarjeta-valor text-grey-9">{{ totales.clientes }}</div>
        </q-card>
      </div>
    </div>

    <div class="row q-col-gutter-xs items-center q-mb-sm">
      <div class="col-12 col-md-4">
        <q-input v-model="buscar" dense outlined clearable placeholder="Buscar por nombre, NIT o teléfono">
          <template #prepend><q-icon name="search"/></template>
        </q-input>
      </div>
      <div class="col-12 col-md-auto">
        <q-btn-toggle
          v-model="filtro" no-caps unelevated dense toggle-color="primary" color="grey-3" text-color="grey-9"
          :options="[
            { label: 'Con deuda (' + totales.con_deuda + ')', value: 'deuda' },
            { label: 'Sin deuda', value: 'sin' },
            { label: 'Todos', value: 'todos' }
          ]"
        />
      </div>
      <div class="col-6 col-md-2">
        <q-select v-model="zona" dense outlined clearable :options="zonas" label="Zona"/>
      </div>
      <div class="col-6 col-md-2">
        <q-select v-model="vendedor" dense outlined clearable :options="vendedores" label="Preventista"/>
      </div>
    </div>

    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-sm">{{ error }}</q-banner>

    <div class="row items-center justify-end q-gutter-sm q-mb-sm">
      <span class="text-caption">Filtrados: {{ filtrados.length }} clientes · Saldo Bs {{ money(filtrados.reduce((total, c) => total + Number(c.saldo), 0)) }}</span>
      <acciones-reporte-credito alcance="clientes filtrados" :disable="cargando || !!error || !filtrados.length || reportando" @reporte="reporteTotal($event, true)"/>
    </div>
    <q-table
      flat bordered dense :rows="filtrados" :columns="columnas" row-key="id" :loading="cargando"
      :pagination="paginacion" :rows-per-page-options="[20, 50, 100, 0]" :grid="$q.screen.lt.md"
      no-data-label="No hay clientes con ese filtro" class="tabla-clientes"
      @row-click="(evento, fila) => abrirCliente(fila)"
    >
      <template #body-cell-nombre="props">
        <q-td :props="props">
          <div class="text-weight-medium">{{ props.row.nombre }}</div>
          <div class="text-caption text-grey-7">NIT {{ props.row.nit || '—' }}<span v-if="!props.row.activo"> · inactivo</span></div>
        </q-td>
      </template>
      <template #body-cell-saldo="props">
        <q-td :props="props">
          <span v-if="props.row.saldo > 0" class="text-weight-bolder text-red-9">Bs {{ money(props.row.saldo) }}</span>
          <span v-else class="text-grey-5">—</span>
        </q-td>
      </template>
      <template #body-cell-dias="props">
        <q-td :props="props">
          <q-badge v-if="props.row.saldo > 0" :color="colorDias(props.row.dias)">{{ props.row.dias }} d</q-badge>
        </q-td>
      </template>

      <template #body-cell-reportes="props">
        <q-td :props="props"><acciones-reporte-credito compacto :alcance="props.row.nombre" :disable="reportando" @reporte="exportarCliente($event, props.row)"/></q-td>
      </template>
      <!-- En el celular cada cliente es una tarjeta: nombre, deuda y datos. -->
      <template #item="props">
        <div class="col-12 q-pa-xs">
          <q-card flat bordered class="cursor-pointer" :class="props.row.saldo > 0 ? 'borde-deuda' : ''" @click="abrirCliente(props.row)">
            <q-card-section class="q-pa-sm">
              <div class="row items-start no-wrap">
                <div class="col" style="min-width: 0">
                  <div class="text-weight-bold ellipsis">{{ props.row.nombre }}</div>
                  <div class="text-caption text-grey-7 ellipsis">
                    NIT {{ props.row.nit || '—' }} · {{ props.row.zona || 'Sin zona' }}
                  </div>
                  <div class="text-caption text-grey-7 ellipsis">
                    <q-icon name="place" size="12px"/> {{ props.row.direccion || 'Sin dirección' }}
                  </div>
                  <div class="text-caption text-grey-7 ellipsis">
                    <q-icon name="call" size="12px"/> {{ props.row.telefono || '—' }} ·
                    <q-icon name="badge" size="12px"/> {{ props.row.vendedor || 'Sin preventista' }}
                  </div>
                </div>
                <div class="text-right q-ml-sm">
                  <template v-if="props.row.saldo > 0">
                    <div class="text-caption text-red-8">Debe</div>
                    <div class="text-subtitle1 text-weight-bolder text-red-9">Bs {{ money(props.row.saldo) }}</div>
                    <q-badge :color="colorDias(props.row.dias)">{{ props.row.deudas }} · {{ props.row.dias }} días</q-badge>
                  </template>
                  <div v-else class="text-caption text-green-8">Sin deuda</div>
                </div>
              </div>
              <div class="row justify-end q-mt-sm"><acciones-reporte-credito :alcance="props.row.nombre" :disable="reportando" @reporte="exportarCliente($event, props.row)"/></div>
            </q-card-section>
          </q-card>
        </div>
      </template>
    </q-table>

    <!-- Detalle del cliente: sus datos, lo que debe y las ventas a crédito. -->
    <q-dialog v-model="dialogCliente" :maximized="$q.screen.lt.md">
      <q-card :class="$q.screen.lt.md ? 'column no-wrap full-height' : ''" :style="$q.screen.lt.md ? '' : 'width: 900px; max-width: 96vw'">
        <q-card-section class="row items-center no-wrap q-py-sm bg-primary text-white">
          <q-icon name="person" size="26px" class="q-mr-sm"/>
          <div class="col" style="min-width: 0">
            <div class="text-subtitle1 text-weight-bold ellipsis">{{ detalle.cliente.nombre || 'Cliente' }}</div>
            <div class="text-caption">NIT {{ detalle.cliente.nit || '—' }}</div>
          </div>
          <q-btn flat dense no-caps icon="add" label="Agregar deuda" class="q-mr-xs" @click="nuevaDeuda(detalle.cliente)"/>
          <q-btn round flat dense icon="close" v-close-popup/>
        </q-card-section>

        <div v-if="cargandoDetalle" class="flex flex-center q-pa-xl"><q-spinner color="primary" size="40px"/></div>

        <q-card-section v-else class="q-pa-sm" :class="$q.screen.lt.md ? 'col scroll' : ''">
          <div class="row justify-end q-mb-sm"><acciones-reporte-credito alcance="estado de cuenta completo" :disable="cargandoDetalle || reportando || !detalle.cliente.id" @reporte="exportarCliente($event)"/></div>
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-md-6">
              <q-list dense bordered class="rounded-borders">
                <q-item><q-item-section avatar><q-icon name="call" color="green-8"/></q-item-section>
                  <q-item-section>
                    <q-item-label caption>Teléfono</q-item-label>
                    <q-item-label>
                      <a v-if="detalle.cliente.telefono" :href="'tel:' + detalle.cliente.telefono">{{ detalle.cliente.telefono }}</a>
                      <span v-else>—</span>
                    </q-item-label>
                  </q-item-section>
                </q-item>
                <q-item><q-item-section avatar><q-icon name="place" color="blue-8"/></q-item-section>
                  <q-item-section>
                    <q-item-label caption>Dirección · {{ detalle.cliente.zona || 'Sin zona' }}</q-item-label>
                    <q-item-label>{{ detalle.cliente.direccion || '—' }}</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item><q-item-section avatar><q-icon name="badge" color="blue-grey-7"/></q-item-section>
                  <q-item-section>
                    <q-item-label caption>Preventista · Canal</q-item-label>
                    <q-item-label>{{ detalle.cliente.vendedor || '—' }} · {{ detalle.cliente.canal || '—' }}</q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </div>
            <div class="col-12 col-md-6">
              <div class="row q-col-gutter-xs">
                <div class="col-6">
                  <q-card flat bordered class="tarjeta bg-red-1">
                    <div class="tarjeta-rotulo text-red-9">DEBE</div>
                    <div class="tarjeta-valor text-red-10">Bs {{ money(detalle.totales.saldo) }}</div>
                  </q-card>
                </div>
                <div class="col-6">
                  <q-card flat bordered class="tarjeta bg-green-1">
                    <div class="tarjeta-rotulo text-green-9">ABONADO</div>
                    <div class="tarjeta-valor text-green-10">Bs {{ money(detalle.totales.abonado) }}</div>
                  </q-card>
                </div>
                <div class="col-6">
                  <q-card flat bordered class="tarjeta bg-orange-1">
                    <div class="tarjeta-rotulo text-orange-9">DEUDAS PENDIENTES</div>
                    <div class="tarjeta-valor text-orange-10">{{ detalle.totales.deudas }}</div>
                  </q-card>
                </div>
                <div class="col-6">
                  <q-card flat bordered class="tarjeta bg-blue-grey-1">
                    <div class="tarjeta-rotulo text-blue-grey-8">VENDIDO A CRÉDITO</div>
                    <div class="tarjeta-valor text-blue-grey-10">Bs {{ money(detalle.totales.vendido) }}</div>
                  </q-card>
                </div>
              </div>
            </div>
          </div>

          <q-tabs v-model="pestana" dense no-caps align="left" active-color="primary" indicator-color="primary" class="q-mt-sm text-grey-8">
            <q-tab name="deudas" icon="request_quote" :label="'Deudas (' + detalle.totales.deudas + ')'"/>
            <q-tab name="ventas" icon="receipt_long" :label="'Ventas a crédito (' + detalle.totales.ventas + ')'"/>
          </q-tabs>
          <q-separator/>

          <q-tab-panels v-model="pestana" animated>
            <q-tab-panel name="deudas" class="q-pa-none q-pt-sm">
              <div class="row items-center q-mb-xs">
                <q-space/>
                <q-toggle v-model="soloPendientes" dense label="Solo pendientes"/>
              </div>
              <div v-if="!deudasVisibles.length" class="text-center text-grey-6 q-pa-md">
                {{ soloPendientes ? 'No debe nada' : 'Sin deudas registradas' }}
              </div>
              <div v-for="deuda in deudasVisibles" :key="deuda.clave" class="deuda-compacta q-mb-xs">
                <div class="row items-center q-col-gutter-xs cabecera-deuda">
                  <div class="col-12 col-sm">
                    <strong>{{ deuda.concepto }}</strong>
                    <span class="text-grey-7 q-ml-xs">{{ String(deuda.fecha).slice(0, 10) }}</span>
                    <q-badge class="q-ml-xs" :color="deuda.saldo > 0 ? 'orange-8' : (deuda.estado.includes('REVISAR') ? 'red-7' : 'green-7')">{{ deuda.estado }}</q-badge>
                    <div class="resumen-deuda">Total: Bs {{ money(deuda.monto) }} ? Abonado: Bs {{ money(deuda.pagado) }} ? <strong class="text-red-9">Saldo: Bs {{ money(deuda.saldo) }}</strong></div>
                  </div>
                  <div class="col-auto row items-center q-gutter-xs acciones-deuda">
                    <q-btn dense flat no-caps size="sm" color="positive" icon="payments" label="Abonar" :disable="deuda.saldo <= 0" @click="abrirAbono(deuda)"/>
                    <acciones-reporte-credito :alcance="deuda.concepto" :disable="reportando" @reporte="exportarDeuda($event, deuda)"/>
                  </div>
                </div>
                <q-table v-if="(abonosPorDeuda[deuda.clave] || []).length" flat dense class="tabla-abonos"
                         :rows="abonosPorDeuda[deuda.clave]" :columns="columnasHistorial" row-key="id"
                         :pagination="{ rowsPerPage: 0 }" hide-bottom separator="cell">
                  <template #body-cell-monto="props">
                    <q-td :props="props" :class="props.row.anulado_at ? 'text-strike text-grey-6' : ''">{{ props.value }}</q-td>
                  </template>
                  <template #body-cell-referencia="props"><q-td :props="props">{{ props.row.referencia || '-' }}</q-td></template>
                  <template #body-cell-cobrador="props"><q-td :props="props">{{ props.row.cobrador || '-' }}</q-td></template>
                  <!-- Un abono cobrado por equivocacion no se borra: se anula con
                       su motivo y esa plata vuelve a quedar como deuda. -->
                  <template #body-cell-anular="props">
                    <q-td :props="props">
                      <q-badge v-if="props.row.anulado_at" color="red-7" class="cursor-pointer">
                        ANULADO
                        <q-tooltip>
                          {{ props.row.motivo_anulacion || 'Sin motivo' }}
                          <span v-if="props.row.anulado_por"> · {{ props.row.anulado_por }}</span>
                          · {{ String(props.row.anulado_at).slice(0, 16).replace('T', ' ') }}
                        </q-tooltip>
                      </q-badge>
                      <q-btn
                        v-else dense flat no-caps size="sm" color="negative" icon="undo" label="Anular"
                        @click="abrirAnulacion(props.row, deuda)"
                      />
                    </q-td>
                  </template>
                </q-table>
                <div v-else class="sin-abonos text-grey-6">Sin abonos registrados</div>
              </div>
            </q-tab-panel>

            <q-tab-panel name="ventas" class="q-pa-none q-pt-sm">
              <div v-if="!detalle.ventas.length" class="text-center text-grey-6 q-pa-md">Sin ventas a crédito</div>
              <q-list v-else bordered separator class="rounded-borders">
                <q-expansion-item v-for="venta in detalle.ventas" :key="venta.id" dense expand-separator>
                  <template #header>
                    <q-item-section>
                      <q-item-label class="text-weight-medium">
                        {{ venta.comprobante === 'FACTURA' ? 'Factura' : 'Venta' }} #{{ venta.id }}
                        <span v-if="venta.pedido" class="text-grey-7"> · Pedido #{{ venta.pedido }}</span>
                      </q-item-label>
                      <q-item-label caption>
                        {{ venta.fecha }} {{ venta.hora }} · {{ venta.productos.length }} productos
                      </q-item-label>
                    </q-item-section>
                    <q-item-section side class="text-right">
                      <q-item-label class="text-weight-bold">Bs {{ money(venta.total) }}</q-item-label>
                      <q-badge :color="venta.estado === 'PENDIENTE' ? 'orange-8' : (venta.estado === 'ANULADA' ? 'grey-6' : 'green-7')">
                        {{ venta.estado }}<span v-if="venta.estado === 'PENDIENTE'">&nbsp;· Bs {{ money(venta.saldo) }}</span>
                      </q-badge>
                    </q-item-section>
                  </template>
                  <div class="contenedor-productos">
                    <div class="row justify-end q-pa-sm"><acciones-reporte-credito :alcance="'venta #' + venta.id" :disable="reportando" @reporte="exportarVenta($event, venta)"/></div>
                    <table class="tabla-productos">
                      <thead>
                        <tr><th class="text-left">Producto</th><th class="text-right">Cant.</th><th class="text-right">Precio</th><th class="text-right">Subtotal</th></tr>
                      </thead>
                      <tbody>
                        <tr v-for="(producto, i) in venta.productos" :key="i">
                          <td>{{ producto.nombre }}<div class="text-caption text-grey-6">{{ producto.cod_prod }}</div></td>
                          <td class="text-right text-no-wrap">
                            {{ producto.peso > 0 ? cantidad(producto.peso) + ' kg' : cantidad(producto.cantidad) + ' ' + producto.unidad }}
                          </td>
                          <td class="text-right">{{ money(producto.precio) }}</td>
                          <td class="text-right text-weight-bold">{{ money(producto.subtotal) }}</td>
                        </tr>
                      </tbody>
                      <tfoot>
                        <tr><td colspan="3" class="text-right">Total</td><td class="text-right">{{ money(venta.total) }}</td></tr>
                        <tr v-if="venta.pagado > 0"><td colspan="3" class="text-right">Abonado</td><td class="text-right text-green-8">{{ money(venta.pagado) }}</td></tr>
                      </tfoot>
                    </table>
                  </div>
                </q-expansion-item>
              </q-list>
            </q-tab-panel>
          </q-tab-panels>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Deuda agregada a mano: algo que el cliente debe y no salio de una venta a credito. -->
    <q-dialog v-model="dialogDeuda" persistent>
      <q-card style="width: 480px; max-width: 95vw">
        <q-form @submit="guardarDeuda">
          <q-card-section class="row items-center no-wrap q-py-sm bg-red-7 text-white">
            <q-icon name="add_card" size="24px" class="q-mr-sm"/>
            <div class="col text-subtitle1 text-weight-bold">Agregar deuda</div>
            <q-btn round flat dense icon="close" :disable="guardando" v-close-popup/>
          </q-card-section>
          <q-card-section class="q-gutter-sm">
            <q-select
              v-model="formDeuda.cliente" :options="opcionesCliente" option-label="nombre" use-input input-debounce="200"
              outlined label="Cliente" :rules="[v => !!v || 'Elegí el cliente']" @filter="filtrarClientes"
            >
              <template #option="scope">
                <q-item v-bind="scope.itemProps">
                  <q-item-section>
                    <q-item-label>{{ scope.opt.nombre }}</q-item-label>
                    <q-item-label caption>NIT {{ scope.opt.nit || '—' }} · {{ scope.opt.zona || 'Sin zona' }}</q-item-label>
                  </q-item-section>
                  <q-item-section v-if="scope.opt.saldo > 0" side class="text-red-8">Debe Bs {{ money(scope.opt.saldo) }}</q-item-section>
                </q-item>
              </template>
              <template #no-option><q-item><q-item-section class="text-grey">Escribí al menos 2 letras del nombre o NIT</q-item-section></q-item></template>
            </q-select>
            <q-input v-model="formDeuda.fecha" type="date" outlined label="Fecha" :rules="[v => !!v || 'Indicá la fecha']"/>
            <q-input v-model="formDeuda.concepto" outlined label="Concepto (por qué debe)" maxlength="255" :rules="[v => !!(v || '').trim() || 'Indicá el concepto']"/>
            <q-input v-model="formDeuda.monto" type="number" step="0.01" min="0.01" outlined label="Monto Bs" :rules="[montoValido]"/>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat no-caps label="Cancelar" :disable="guardando" v-close-popup/>
            <q-btn type="submit" unelevated no-caps color="red-7" icon="save" label="Guardar deuda" :loading="guardando"/>
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogAbono" persistent>
      <q-card style="width: 480px; max-width: 95vw">
        <q-form @submit="guardarAbono">
          <q-card-section><div class="text-h6">Cobrar abono</div>{{ seleccion.cliente }} · {{ seleccion.concepto }}<div>Saldo: <b>Bs {{ money(seleccion.saldo) }}</b></div></q-card-section>
          <q-card-section class="q-gutter-md">
            <q-select v-model="abono.forma_pago" outlined label="Forma de pago" :options="['EFECTIVO', 'QR', 'MIXTO']"/>
            <!-- En el mixto se anota cuanto entro por cada lado y el abono es la suma. -->
            <div v-if="abono.forma_pago === 'MIXTO'" class="row q-col-gutter-sm">
              <div class="col-6">
                <q-input v-model="abono.monto_efectivo" outlined type="number" step="0.01" min="0.01" label="Monto efectivo Bs"
                         :rules="[montoValido]"/>
              </div>
              <div class="col-6">
                <q-input v-model="abono.monto_qr" outlined type="number" step="0.01" min="0.01" label="Monto QR Bs"
                         :rules="[montoValido]"/>
              </div>
              <div class="col-12 text-body2" :class="sumaMixto > seleccion.saldo + 0.001 ? 'text-negative' : 'text-grey-8'">
                Total del abono: <b>Bs {{ money(sumaMixto) }}</b>
                <span v-if="sumaMixto > seleccion.saldo + 0.001"> · supera el saldo</span>
              </div>
            </div>
            <q-input v-else v-model="abono.monto" outlined type="number" step="0.01" min="0.01" :max="seleccion.saldo"
                     :label="abono.forma_pago === 'QR' ? 'Monto QR Bs' : 'Monto efectivo Bs'"
                     :rules="[montoValido, v => Number(v) <= seleccion.saldo || 'Supera el saldo']"/>
            <q-input v-model="abono.referencia" outlined label="Boleta / referencia" maxlength="100"/>
          </q-card-section>
          <q-card-actions align="right"><q-btn flat label="Cancelar" :disable="guardando" v-close-popup/><q-btn color="positive" type="submit" label="Registrar cobro" :loading="guardando"/></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <!-- El cobro mal anotado se da de baja con su motivo: el abono queda en el
         historial tachado y la deuda vuelve a deber esa plata. -->
    <q-dialog v-model="dialogAnular" persistent>
      <q-card style="width: 440px; max-width: 95vw">
        <q-form @submit="anularAbono">
          <q-card-section class="row items-center no-wrap q-py-sm bg-red-7 text-white">
            <q-icon name="undo" size="24px" class="q-mr-sm"/>
            <div class="col text-subtitle1 text-weight-bold">Anular abono</div>
            <q-btn round flat dense icon="close" :disable="guardando" v-close-popup/>
          </q-card-section>
          <q-card-section class="q-pb-none">
            <div class="text-body2">
              Abono #{{ anulacion.id }} · <b>Bs {{ money(anulacion.monto) }}</b> · {{ anulacion.forma_pago }}
            </div>
            <div class="text-caption text-grey-7">
              {{ anulacion.concepto }} · cobrado por {{ anulacion.cobrador || 'sin registrar' }}
            </div>
            <div class="text-caption text-red-9">Esos Bs {{ money(anulacion.monto) }} vuelven a quedar como deuda.</div>
          </q-card-section>
          <q-card-section>
            <q-input
              v-model="anulacion.motivo" outlined autofocus maxlength="150" label="¿Por qué se anula?"
              :rules="[v => !!(v || '').trim() || 'Indicá el motivo']"
            />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat no-caps label="Cancelar" :disable="guardando" v-close-popup/>
            <q-btn type="submit" unelevated no-caps color="negative" icon="undo" label="Anular abono" :loading="guardando"/>
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { date, uid } from 'quasar'
import AccionesReporteCredito from 'components/AccionesReporteCredito.vue'
import { reporteGeneral, reporteCliente, emitirReporte } from 'src/utils/reportesCreditos'

function detalleVacio () {
  return { cliente: {}, deudas: [], abonos: [], ventas: [], totales: { saldo: 0, deudas: 0, abonado: 0, ventas: 0, vendido: 0 } }
}

export default {
  name: 'CreditosClientes',
  components: { AccionesReporteCredito },
  data () {
    return {
      clientes: [],
      reportando: false,
      descargandoExcel: false,
      descargandoCobros: false,
      rangoCobros: { desde: date.formatDate(new Date(), 'YYYY-MM-DD'), hasta: date.formatDate(new Date(), 'YYYY-MM-DD') },
      // Cierre de caja: por defecto hoy de 00:00 a 23:59, todos los cajeros.
      cierre: {
        desde: date.formatDate(new Date(), 'YYYY-MM-DD'),
        horaDesde: '00:00',
        hasta: date.formatDate(new Date(), 'YYYY-MM-DD'),
        horaHasta: '23:59',
        usuario: null
      },
      cobradores: [],
      cargandoCobradores: false,
      descargandoCierre: false,
      totales: { clientes: 0, con_deuda: 0, saldo: 0, deudas: 0 },
      buscar: '',
      // Arranca en los que deben: es lo que cobranzas viene a mirar.
      filtro: 'deuda',
      zona: null,
      vendedor: null,
      cargando: false,
      error: '',
      paginacion: { rowsPerPage: 20, sortBy: 'saldo', descending: true },
      dialogCliente: false,
      cargandoDetalle: false,
      clienteId: null,
      detalle: detalleVacio(),
      pestana: 'deudas',
      soloPendientes: true,
      guardando: false,
      dialogDeuda: false,
      formDeuda: { cliente: null, fecha: '', concepto: '', monto: '', solicitud_id: '' },
      opcionesCliente: [],
      dialogAbono: false,
      seleccion: {},
      abono: {},
      dialogAnular: false,
      anulacion: {},
      columnas: [
        { name: 'nombre', label: 'Cliente', field: 'nombre', align: 'left', sortable: true },
        { name: 'telefono', label: 'Teléfono', field: 'telefono', align: 'left' },
        { name: 'direccion', label: 'Dirección', field: 'direccion', align: 'left', classes: 'celda-direccion' },
        { name: 'zona', label: 'Zona', field: 'zona', align: 'left', sortable: true },
        { name: 'vendedor', label: 'Preventista', field: 'vendedor', align: 'left', sortable: true },
        { name: 'deudas', label: 'Ventas', field: 'deudas', align: 'center', sortable: true, format: v => v || '' },
        { name: 'dias', label: 'Desde', field: 'dias', align: 'center', sortable: true },
        { name: 'saldo', label: 'Deuda Bs', field: 'saldo', align: 'right', sortable: true },
        { name: 'reportes', label: 'Reportes', field: 'id', align: 'right' }
      ],
      columnasHistorial: [
        { name: 'id', label: '#', field: 'id', align: 'left', sortable: true },
        { name: 'created_at', label: 'Fecha', field: 'created_at', align: 'left' },
        { name: 'monto', label: 'Monto Bs', field: 'monto', align: 'right', sortable: true, format: v => this.money(v) },
        { name: 'forma_pago', label: 'Pago', field: 'forma_pago' },
        { name: 'monto_efectivo', label: 'Efectivo Bs', field: 'monto_efectivo', align: 'right', format: v => Number(v) ? this.money(v) : '' },
        { name: 'monto_qr', label: 'QR Bs', field: 'monto_qr', align: 'right', format: v => Number(v) ? this.money(v) : '' },
        { name: 'referencia', label: 'Boleta / referencia', field: 'referencia' },
        { name: 'cobrador', label: 'Cobrador', field: 'cobrador' },
        { name: 'anular', label: '', field: 'id', align: 'right' }
      ]
    }
  },
  computed: {
    // Total de un abono mixto: efectivo + QR.
    sumaMixto () {
      return Math.round((Number(this.abono.monto_efectivo || 0) + Number(this.abono.monto_qr || 0)) * 100) / 100
    },
    abonosPorDeuda () {
      return (this.detalle.abonos || []).reduce((grupos, abono) => {
        const clave = abono.origen + ':' + abono.deuda_id
        if (!grupos[clave]) grupos[clave] = []
        grupos[clave].push(abono)
        return grupos
      }, {})
    },
    zonas () {
      return [...new Set(this.clientes.map(c => c.zona).filter(Boolean))].sort()
    },
    vendedores () {
      return [...new Set(this.clientes.map(c => c.vendedor).filter(Boolean))].sort()
    },
    filtrados () {
      const texto = (this.buscar || '').trim().toLowerCase()
      return this.clientes.filter(c => {
        if (this.filtro === 'deuda' && !(c.saldo > 0)) return false
        if (this.filtro === 'sin' && c.saldo > 0) return false
        if (this.zona && c.zona !== this.zona) return false
        if (this.vendedor && c.vendedor !== this.vendedor) return false
        if (!texto) return true
        return c.nombre.toLowerCase().includes(texto) || c.nit.includes(texto) || c.telefono.includes(texto)
      })
    },
    deudasVisibles () {
      return this.detalle.deudas.filter(d => !this.soloPendientes || d.saldo > 0 || d.estado.includes('REVISAR'))
    }
  },
  mounted () { this.cargar() },
  methods: {
    async generarReporte (formato, preparar) {
      if (this.reportando) return
      this.reportando = true
      try { emitirReporte(await preparar(), formato) }
      catch (e) { this.$q.notify({ type: 'negative', message: this.mensaje(e) }) }
      finally { this.reportando = false }
    },
    reporteTotal (formato, filtrado = false) {
      const alcance = filtrado
        ? `Clientes filtrados · Estado: ${{ deuda: 'Con deuda', sin: 'Sin deuda', todos: 'Todos' }[this.filtro]} · Buscar: ${this.buscar || 'Todos'} · Zona: ${this.zona || 'Todas'} · Preventista: ${this.vendedor || 'Todos'}`
        : 'Total general · Todos los clientes'
      return this.generarReporte(formato, () => reporteGeneral(filtrado ? this.filtrados : this.clientes, alcance))
    },
    exportarCliente (formato, cliente) {
      return this.generarReporte(formato, async () => {
        const detalle = cliente ? (await this.$api.get('creditos/clientes/' + cliente.id)).data : this.detalle
        return reporteCliente(detalle)
      })
    },
    exportarDeuda (formato, deuda) {
      const detalle = this.detalle
      return this.generarReporte(formato, async () => {
        const { data } = await this.$api.get(`creditos/${deuda.origen}/${deuda.id}/abonos`)
        return reporteCliente(detalle, deuda, data)
      })
    },
    exportarVenta (formato, venta) {
      const deuda = this.detalle.deudas.find(d => d.origen === 'factura' && String(d.id) === String(venta.id))
      return this.exportarDeuda(formato, deuda || { origen: 'factura', id: venta.id, fecha: venta.fecha, concepto: 'Venta #' + venta.id, estado: venta.estado, monto: venta.total, pagado: venta.pagado, saldo: venta.saldo })
    },
    // El Excel lo arma el backend con el formato del sistema anterior.
    async excelDeudores () {
      this.descargandoExcel = true
      try {
        const { data } = await this.$api.get('creditos/excel-deudores', { responseType: 'blob' })
        const url = URL.createObjectURL(data)
        const a = document.createElement('a')
        a.href = url
        a.download = 'CUENTAS POR COBRAR ' + date.formatDate(new Date(), 'YYYY-MM-DD') + '.xlsx'
        a.click()
        URL.revokeObjectURL(url)
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'No se pudo generar el Excel de deudores' })
      } finally {
        this.descargandoExcel = false
      }
    },
    // Hoja de deposito: cobros del rango por dia y por boleta, con totales.
    async excelCobros () {
      const { desde, hasta } = this.rangoCobros
      if (!desde || !hasta || desde > hasta) {
        this.$q.notify({ type: 'warning', message: 'Revisa el rango de fechas' })
        return
      }
      this.descargandoCobros = true
      try {
        const { data } = await this.$api.get('creditos/excel-cobros', { params: { desde, hasta }, responseType: 'blob' })
        const url = URL.createObjectURL(data)
        const a = document.createElement('a')
        a.href = url
        a.download = 'COBROS ' + desde + (hasta !== desde ? ' AL ' + hasta : '') + '.xlsx'
        a.click()
        URL.revokeObjectURL(url)
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'No se pudo generar el Excel de cobros' })
      } finally {
        this.descargandoCobros = false
      }
    },
    paramsCierre () {
      const { desde, hasta, horaDesde, horaHasta, usuario } = this.cierre
      const params = { desde, hasta, hora_desde: horaDesde || '00:00', hora_hasta: horaHasta || '23:59' }
      if (usuario) params.user_id = usuario
      return params
    },
    // Quienes cobraron en el rango, para elegir el cajero del cierre.
    async cargarCobradores () {
      if (!this.cierre.desde || !this.cierre.hasta) return
      this.cargandoCobradores = true
      try {
        const { usuario, ...sinUsuario } = this.cierre
        const { data } = await this.$api.get('creditos/cobradores', {
          params: { desde: sinUsuario.desde, hasta: sinUsuario.hasta, hora_desde: sinUsuario.horaDesde || '00:00', hora_hasta: sinUsuario.horaHasta || '23:59' }
        })
        this.cobradores = data
        // Si el cajero elegido no cobro en el rango nuevo, se suelta.
        if (usuario && !data.some(c => c.user_id === usuario)) this.cierre.usuario = null
      } catch (e) {
        this.cobradores = []
      } finally {
        this.cargandoCobradores = false
      }
    },
    async excelCierre () {
      const { desde, hasta, horaDesde, horaHasta } = this.cierre
      if (!desde || !hasta || (desde + ' ' + (horaDesde || '00:00')) > (hasta + ' ' + (horaHasta || '23:59'))) {
        this.$q.notify({ type: 'warning', message: 'Revisa el rango de fechas y horas' })
        return
      }
      this.descargandoCierre = true
      try {
        const { data } = await this.$api.get('creditos/excel-cierre', { params: this.paramsCierre(), responseType: 'blob' })
        const url = URL.createObjectURL(data)
        const a = document.createElement('a')
        a.href = url
        a.download = 'CIERRE COBROS ' + desde + '.xlsx'
        a.click()
        URL.revokeObjectURL(url)
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'No se pudo generar el cierre de caja' })
      } finally {
        this.descargandoCierre = false
      }
    },
    money (v) { return Number(v || 0).toFixed(2) },
    cantidad (v) { return Number(v || 0).toLocaleString('es-BO', { maximumFractionDigits: 3 }) },
    colorDias (dias) {
      if (dias > 30) return 'red-8'
      if (dias > 15) return 'orange-8'
      return 'blue-grey-6'
    },
    montoValido (v) { return (/^\d+(\.\d{1,2})?$/.test(String(v)) && Number(v) > 0) || 'Indique un monto positivo con hasta dos decimales' },
    mensaje (e) { return Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'No se pudo completar la operación. Intente nuevamente.' },
    async cargar () {
      this.cargando = true
      this.error = ''
      try {
        const { data } = await this.$api.get('creditos/resumen')
        this.clientes = data.clientes
        this.totales = data.totales
      } catch (e) {
        this.error = this.mensaje(e)
      } finally {
        this.cargando = false
      }
    },
    abrirCliente (fila) {
      this.clienteId = fila.id
      this.detalle = detalleVacio()
      this.detalle.cliente = { nombre: fila.nombre, nit: fila.nit }
      this.pestana = 'deudas'
      this.soloPendientes = true
      this.dialogCliente = true
      this.cargarDetalle()
    },
    async cargarDetalle () {
      this.cargandoDetalle = true
      try {
        this.detalle = (await this.$api.get('creditos/clientes/' + this.clienteId)).data
      } catch (e) {
        this.dialogCliente = false
        this.$q.notify({ type: 'negative', message: this.mensaje(e) })
      } finally {
        this.cargandoDetalle = false
      }
    },
    /** Abre el formulario; desde el detalle llega con el cliente ya elegido. */
    nuevaDeuda (cliente) {
      const elegido = cliente && cliente.id ? (this.clientes.find(c => c.id === cliente.id) || cliente) : null
      this.formDeuda = {
        cliente: elegido,
        fecha: date.formatDate(Date.now(), 'YYYY-MM-DD'),
        concepto: '',
        monto: '',
        // Evita que un doble toque registre la deuda dos veces.
        solicitud_id: uid()
      }
      this.opcionesCliente = elegido ? [elegido] : []
      this.dialogDeuda = true
    },
    // Se busca sobre la lista ya cargada: son pocos miles y responde al instante.
    filtrarClientes (texto, update) {
      const buscado = (texto || '').trim().toLowerCase()
      update(() => {
        this.opcionesCliente = buscado.length < 2
          ? []
          : this.clientes.filter(c => c.nombre.toLowerCase().includes(buscado) || c.nit.includes(buscado)).slice(0, 40)
      })
    },
    async guardarDeuda () {
      if (this.guardando) return
      this.guardando = true
      try {
        const { cliente, ...resto } = this.formDeuda
        await this.$api.post('creditos', { ...resto, concepto: resto.concepto.trim(), cliente_id: cliente.id })
        this.dialogDeuda = false
        this.$q.notify({ type: 'positive', message: 'Deuda agregada a ' + cliente.nombre })
        const tareas = [this.cargar()]
        if (this.dialogCliente && this.clienteId === cliente.id) tareas.push(this.cargarDetalle())
        await Promise.all(tareas)
      } catch (e) { this.$q.notify({ type: 'negative', message: this.mensaje(e) }) }
      finally { this.guardando = false }
    },
    abrirAbono (fila) {
      this.seleccion = fila
      this.abono = { monto: '', monto_efectivo: '', monto_qr: '', forma_pago: 'EFECTIVO', referencia: '', solicitud_id: uid() }
      this.dialogAbono = true
    },
    async guardarAbono () {
      if (this.guardando) return
      const mixto = this.abono.forma_pago === 'MIXTO'
      if (mixto && this.sumaMixto > this.seleccion.saldo + 0.001) {
        this.$q.notify({ type: 'negative', message: 'Efectivo + QR supera el saldo' })
        return
      }
      // El backend reparte efectivo y QR; en el mixto el abono es la suma.
      const datos = {
        ...this.abono,
        monto: mixto ? this.sumaMixto.toFixed(2) : this.abono.monto,
        monto_efectivo: mixto ? this.abono.monto_efectivo : null,
        monto_qr: mixto ? this.abono.monto_qr : null
      }
      this.guardando = true
      try {
        await this.$api.post(`creditos/${this.seleccion.origen}/${this.seleccion.id}/abonos`, datos)
        this.dialogAbono = false
        this.$q.notify({ type: 'positive', message: 'Abono registrado y saldo actualizado' })
        // Se refrescan el detalle abierto y la lista, que muestra la deuda.
        await Promise.all([this.cargarDetalle(), this.cargar()])
      } catch (e) { this.$q.notify({ type: 'negative', message: this.mensaje(e) }) }
      finally { this.guardando = false }
    },
    /** Un abono que se cobro por equivocacion: se anula con motivo, no se borra. */
    abrirAnulacion (abono, deuda) {
      this.anulacion = { ...abono, concepto: deuda.concepto, motivo: '' }
      this.dialogAnular = true
    },
    async anularAbono () {
      if (this.guardando) return
      this.guardando = true
      try {
        const { data } = await this.$api.put(`creditos/abonos/${this.anulacion.id}/anular`, {
          motivo: this.anulacion.motivo.trim()
        })
        this.dialogAnular = false
        this.$q.notify({ type: 'positive', message: data.message })
        await Promise.all([this.cargarDetalle(), this.cargar()])
      } catch (e) { this.$q.notify({ type: 'negative', message: this.mensaje(e) }) }
      finally { this.guardando = false }
    }
  }
}
</script>

<style scoped>
.deuda-compacta { border: 1px solid #dce2e5; border-radius: 4px; overflow: hidden; }
.cabecera-deuda { padding: 4px 6px; background: #f1f4f6; font-size: 12px; }
.resumen-deuda, .sin-abonos { font-size: 11px; line-height: 1.4; }
.sin-abonos { padding: 3px 6px; }
.acciones-deuda :deep(.q-btn) { min-height: 24px; font-size: 11px; padding: 2px 5px; }

.tabla-abonos :deep(th), .tabla-abonos :deep(td) {
  padding: 2px 5px;
  height: 22px;
  font-size: 11px;
  line-height: 1.25;
}
.tabla-abonos :deep(th) { background: #eceff1; font-weight: 700; }
.tabla-abonos :deep(tbody tr:nth-child(even)) { background: #f7faf8; }
.tabla-abonos :deep(td) { white-space: normal; min-width: 75px; }

.tarjeta {
  padding: 6px 10px;
}
.tarjeta-rotulo {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.4px;
}
.tarjeta-valor {
  font-size: 18px;
  font-weight: 800;
  line-height: 1.2;
}
.tabla-clientes :deep(tbody tr) {
  cursor: pointer;
}
.tabla-clientes :deep(.celda-direccion) {
  max-width: 260px;
  white-space: normal;
}
.borde-deuda {
  border-left: 4px solid #e53935;
}
.contenedor-productos {
  overflow-x: auto;
  padding: 0 8px 8px;
}
.tabla-productos {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}
.tabla-productos th {
  background: #eceff1;
  font-size: 10px;
  padding: 3px 6px;
}
.tabla-productos td {
  padding: 3px 6px;
  border-bottom: 1px solid #eee;
}
.tabla-productos tfoot td {
  font-weight: 700;
  border-bottom: none;
}
</style>
