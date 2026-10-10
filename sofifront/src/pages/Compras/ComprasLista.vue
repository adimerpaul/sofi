<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md">
        <div class="text-h6 text-weight-bold">Compras</div>
        <div class="text-caption text-grey-7">
          Ingresos de mercadería a proveedores; cada compra suma al stock
        </div>
      </div>
      <div class="col-auto">
        <q-btn
          v-if="can('comprasNueva')"
          color="positive" unelevated no-caps icon="add_shopping_cart"
          label="Nueva compra" to="/compras/nueva"
        />
      </div>
    </div>

    <q-card flat bordered class="q-pa-sm q-mb-md">
      <div class="row q-col-gutter-sm" @keyup.enter="recargar">
        <div class="col-6 col-md-2">
          <q-input v-model="filtros.desde" type="date" dense outlined label="Desde"/>
        </div>
        <div class="col-6 col-md-2">
          <q-input v-model="filtros.hasta" type="date" dense outlined label="Hasta"/>
        </div>
        <div class="col-12 col-md-3">
          <q-input v-model="filtros.buscar" dense outlined clearable label="Proveedor, NIT o factura">
            <template v-slot:append><q-icon name="search"/></template>
          </q-input>
        </div>
        <div class="col-6 col-md-2">
          <q-select v-model="filtros.estado" dense outlined clearable label="Estado" :options="['ACTIVO', 'ANULADO']"/>
        </div>
        <div class="col-12 col-md row items-center q-gutter-sm">
          <q-btn :loading="loading" color="primary" icon="search" no-caps label="Buscar" @click="recargar"/>
          <q-btn flat color="grey-7" icon="layers_clear" no-caps label="Limpiar" @click="limpiar"/>
        </div>
      </div>
    </q-card>

    <q-table
      flat bordered dense
      :rows="compras"
      :columns="columns"
      row-key="id"
      v-model:pagination="pagination"
      :loading="loading"
      :rows-per-page-options="[15, 30, 50, 100]"
      @request="onRequest"
    >
      <template v-slot:body-cell-id="props">
        <q-td :props="props">
          <q-badge color="indigo-5" text-color="white">#{{ props.value }}</q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-estado="props">
        <q-td :props="props" class="text-center">
          <q-badge :color="props.value === 'ANULADO' ? 'negative' : 'positive'" text-color="white">
            {{ props.value }}
          </q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-proveedor="props">
        <q-td :props="props">
          <template v-if="props.value">
            {{ props.value }}
            <div class="text-caption text-grey-7">NIT {{ props.row.nit || '—' }}</div>
          </template>
          <span v-else class="text-grey-6">Sin proveedor</span>
        </q-td>
      </template>

      <template v-slot:body-cell-acciones="props">
        <q-td :props="props" style="white-space: nowrap">
          <!-- Todo lo que se hace con una compra, en un solo menu. -->
          <q-btn dense flat round size="sm" icon="more_vert" color="grey-8" :loading="imprimiendo === props.row.id">
            <q-menu auto-close>
              <q-list dense style="min-width: 230px">
                <q-item clickable @click="verDetalle(props.row)">
                  <q-item-section avatar><q-icon name="visibility" color="primary"/></q-item-section>
                  <q-item-section>Ver detalle</q-item-section>
                </q-item>
                <q-item clickable :disable="props.row.estado === 'ANULADO'" @click="imprimirCompra(props.row)">
                  <q-item-section avatar><q-icon name="print" color="secondary"/></q-item-section>
                  <q-item-section>
                    Imprimir nota de compra
                    <q-item-label v-if="props.row.estado === 'ANULADO'" caption>Anulada: no se imprime</q-item-label>
                  </q-item-section>
                </q-item>
                <template v-if="can('comprasAnular') && props.row.estado !== 'ANULADO'">
                  <q-separator/>
                  <q-item clickable @click="pedirAnulacion(props.row)">
                    <q-item-section avatar><q-icon name="block" color="negative"/></q-item-section>
                    <q-item-section>
                      Anular
                      <q-item-label caption>Devuelve el stock que había ingresado</q-item-label>
                    </q-item-section>
                  </q-item>
                </template>
              </q-list>
            </q-menu>
          </q-btn>
        </q-td>
      </template>

      <template v-slot:no-data>
        <div class="full-width row flex-center q-pa-md text-grey-7">
          <q-icon name="inventory" size="20px" class="q-mr-sm"/>
          No hay compras en este rango
        </div>
      </template>
    </q-table>

    <q-dialog v-model="dialogDetalle">
      <q-card style="min-width: 340px; max-width: 720px; width: 100%">
        <q-card-section class="bg-primary text-white q-py-sm">
          <div class="text-subtitle1 text-weight-bold">Compra #{{ sel.id }}</div>
          <div class="text-caption">
            {{ sel.proveedor || 'Sin proveedor' }} · {{ String(sel.fecha || '').substr(0, 10) }} {{ sel.hora }}
            <span v-if="sel.nro_factura"> · Factura {{ sel.nro_factura }}</span>
          </div>
          <div v-if="sel.usuario" class="text-caption">
            <q-icon name="person" size="14px"/> Registrado por {{ nombreUsuario(sel.usuario) }}
          </div>
        </q-card-section>

        <q-card-section v-if="sel.estado === 'ANULADO'" class="q-py-sm">
          <q-banner dense rounded class="bg-red-1 text-red-9">
            <template v-slot:avatar><q-icon name="block"/></template>
            Anulada: {{ sel.motivo_anulacion }} — el stock fue devuelto.
          </q-banner>
        </q-card-section>

        <q-card-section class="q-pa-none">
          <q-markup-table dense flat wrap-cells>
            <thead>
            <tr class="bg-grey-2">
              <th class="text-left">Código</th>
              <th class="text-left">Producto</th>
              <th class="text-right">Cant.</th>
              <th class="text-right">Costo</th>
              <th class="text-right">Subtotal</th>
              <th class="text-left">Lote</th>
              <th class="text-left">Vence</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="d in (sel.detalles || [])" :key="d.id">
              <td class="text-left">{{ d.cod_prod }}</td>
              <td class="text-left">{{ d.nombre }}</td>
              <td class="text-right">{{ Number(d.cantidad).toFixed(d.unidad === 'KG' ? 3 : 0) }}</td>
              <td class="text-right">{{ money(d.precio) }}</td>
              <td class="text-right text-weight-bold">{{ money(d.subtotal) }}</td>
              <td class="text-left">{{ d.lote || '—' }}</td>
              <td class="text-left">{{ fechaCorta(d.fecha_vencimiento) }}</td>
            </tr>
            </tbody>
          </q-markup-table>
        </q-card-section>

        <q-separator/>
        <q-card-actions align="between" class="q-px-md">
          <div>
            <div class="text-caption text-grey-7">
              Subtotal Bs {{ money(sel.subtotal) }} · Descuento Bs {{ money(sel.descuento) }}
            </div>
            <div class="text-weight-bold">Total: Bs {{ money(sel.total) }}</div>
          </div>
          <div>
            <q-btn
              v-if="sel.estado !== 'ANULADO'" flat no-caps icon="print" label="Imprimir" color="secondary"
              :loading="imprimiendo === sel.id" @click="imprimirCompra(sel)"
            />
            <q-btn flat no-caps label="Cerrar" color="primary" v-close-popup/>
          </div>
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogAnular">
      <q-card style="min-width: 340px">
        <q-card-section class="q-py-sm">
          <div class="text-subtitle1 text-weight-bold">Anular compra #{{ sel.id }}</div>
          <div class="text-caption text-grey-7">
            Se devolverá al stock todo lo que había ingresado.
          </div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <q-input v-model.trim="motivo" outlined dense autofocus autogrow label="Motivo"/>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense no-caps label="Cancelar" v-close-popup/>
          <q-btn
            color="negative" dense unelevated no-caps label="Anular"
            :disable="!motivo" :loading="anulando" @click="anular"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { date } from 'quasar'
import { Printd } from 'printd'

let impresoraCompras
const escaparHtml = valor => String(valor ?? '').replace(/[&<>"']/g, caracter => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]))

// El logo va incrustado: la hoja se imprime en un iframe y una imagen por URL
// puede no alcanzar a cargarse antes del dialogo de impresion.
let logoCompras
function cargarLogo () {
  if (!logoCompras) {
    logoCompras = fetch(new URL('logo-area-fresca.png', window.location.href).href)
      .then(res => res.ok ? res.blob() : Promise.reject(new Error('sin logo')))
      .then(blob => new Promise(resolve => {
        const lector = new FileReader()
        lector.onload = () => resolve(lector.result)
        lector.readAsDataURL(blob)
      }))
      .catch(() => '')
  }
  return logoCompras
}

const ESTILOS_NOTA = `
  @page { size: letter portrait; margin: 12mm 11mm }
  * { box-sizing: border-box }
  body { font: 10.5px Arial, Helvetica, sans-serif; color: #222; margin: 0 }
  .cab { display: flex; align-items: flex-start; gap: 12px }
  .cab img { width: 110px }
  .emp { flex: 1 }
  .emp-nom { font-size: 16px; font-weight: bold; color: #c1272d; letter-spacing: .5px }
  .emp-dato { font-size: 9.5px; color: #555; line-height: 1.5 }
  .caja { width: 210px; border: 1.5px solid #c1272d; border-radius: 4px; overflow: hidden }
  .caja .tit { background: #c1272d; color: #fff; text-align: center; font-weight: bold;
               letter-spacing: 1px; padding: 4px; font-size: 11px }
  .caja table { width: 100%; border-collapse: collapse }
  .caja td { padding: 2px 8px; font-size: 10px }
  .caja .et { color: #666 }
  .caja .nro { font-size: 18px; font-weight: bold; color: #c1272d; text-align: right }
  .datos { display: grid; grid-template-columns: 2fr 1fr 1fr; margin-top: 10px;
           border: 1px solid #ccc; border-radius: 4px }
  .datos div { padding: 5px 8px; border-bottom: 1px solid #eee }
  .datos .ancho { grid-column: span 3 }
  .et2 { display: block; font-size: 8.5px; color: #777; text-transform: uppercase; letter-spacing: .3px }
  table.det { width: 100%; border-collapse: collapse; margin-top: 10px }
  table.det th { background: #37474f; color: #fff; font-size: 9px; text-transform: uppercase;
                 letter-spacing: .4px; padding: 6px 5px; text-align: left }
  table.det td { padding: 5px; border-bottom: 1px solid #e4e4e4 }
  table.det tr:nth-child(even) td { background: #fafafa }
  .num { text-align: right !important; white-space: nowrap }
  .cod { color: #666 }
  .pie { display: flex; gap: 12px; margin-top: 10px; align-items: flex-start }
  .obs { flex: 1; border: 1px solid #ddd; padding: 7px 9px; line-height: 1.5 }
  .tot { width: 38%; border-collapse: collapse }
  .tot td { padding: 4px 9px; border-bottom: 1px solid #eee }
  .tot .final td { background: #37474f; color: #fff; font-size: 13px; font-weight: bold; border: 0 }
  .firmas { display: flex; gap: 30px; margin-top: 55px }
  .firmas div { flex: 1; border-top: 1px solid #999; padding-top: 4px; text-align: center; font-size: 9.5px; color: #555 }
  .firmas b { display: block; color: #222; font-size: 10px }
  .legal { margin-top: 18px; text-align: center; font-size: 8.5px; color: #888 }
`

function filtrosPorDefecto () {
  const hoy = date.formatDate(new Date(), 'YYYY-MM-DD')
  return { desde: hoy, hasta: hoy, buscar: '', estado: null }
}

export default {
  name: 'ComprasLista',
  data () {
    return {
      compras: [],
      sel: {},
      dialogDetalle: false,
      dialogAnular: false,
      motivo: '',
      anulando: false,
      imprimiendo: null,
      loading: false,
      filtros: filtrosPorDefecto(),
      pagination: { page: 1, rowsPerPage: 15, rowsNumber: 0 },
      columns: [
        { name: 'acciones', label: 'Opciones', field: 'acciones', align: 'left' },
        { name: 'id', label: 'Nº', field: 'id', align: 'left' },
        { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left', format: v => String(v || '').substr(0, 10) },
        { name: 'hora', label: 'Hora', field: 'hora', align: 'left' },
        { name: 'proveedor', label: 'Proveedor', field: 'proveedor', align: 'left' },
        { name: 'nro_factura', label: 'Factura', field: 'nro_factura', align: 'left' },
        { name: 'tipo_pago', label: 'Pago', field: 'tipo_pago', align: 'center' },
        { name: 'estado', label: 'Estado', field: 'estado', align: 'center' },
        { name: 'total', label: 'Total Bs.', field: 'total', align: 'right', format: v => Number(v || 0).toFixed(2) }
      ]
    }
  },
  computed: {
    can () {
      return this.$store.getters['login/can']
    }
  },
  created () {
    this.onRequest({ pagination: this.pagination })
  },
  methods: {
    money (v) {
      return Number(v || 0).toFixed(2)
    },
    // La fecha llega como 'YYYY-MM-DD…'; se muestra al modo de acá.
    fechaCorta (v) {
      if (!v) {
        return '—'
      }
      const partes = String(v).substr(0, 10).split('-')
      return partes.length === 3 ? partes[2] + '/' + partes[1] + '/' + partes[0] : v
    },
    limpiar () {
      this.filtros = filtrosPorDefecto()
      this.recargar()
    },
    recargar () {
      this.pagination.page = 1
      this.onRequest({ pagination: this.pagination })
    },
    onRequest (props) {
      const { page, rowsPerPage } = props.pagination
      this.loading = true

      this.$api.get('compras', {
        params: {
          desde: this.filtros.desde || '',
          hasta: this.filtros.hasta || '',
          buscar: this.filtros.buscar || '',
          estado: this.filtros.estado || '',
          page,
          perPage: rowsPerPage
        }
      }).then(res => {
        this.compras = res.data.data
        this.pagination.page = res.data.current_page
        this.pagination.rowsPerPage = rowsPerPage
        this.pagination.rowsNumber = res.data.total
      }).catch(err => {
        this.avisar(err, 'No se pudieron cargar las compras')
      }).finally(() => {
        this.loading = false
      })
    },

    verDetalle (row) {
      this.sel = row
      this.dialogDetalle = true

      this.$api.get('compras/' + row.id)
        .then(res => { this.sel = res.data })
        .catch(() => {})
    },

    // personal guarda los nombres con espacios de relleno.
    nombreUsuario (u) {
      return [u.Nombre1, u.App1, u.Apm].map(p => String(p || '').trim()).filter(Boolean).join(' ') || '—'
    },

    /** La nota de compra en carta, con el mismo aire que la boleta de entrega. */
    imprimirCompra (row) {
      this.imprimiendo = row.id
      Promise.all([this.$api.get('compras/' + row.id), cargarLogo()])
        .then(([res, logo]) => {
          const c = res.data
          const e = c.empresa || {}
          const p = c.proveedor_rel || {}
          const usuario = c.usuario ? this.nombreUsuario(c.usuario) : '—'
          const n = (v, dec = 2) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec })

          const filas = (c.detalles || []).map((d, i) => `<tr>
              <td class="num">${i + 1}</td>
              <td class="cod">${escaparHtml(d.cod_prod)}</td>
              <td>${escaparHtml(d.nombre)}</td>
              <td>${escaparHtml(d.unidad || '')}</td>
              <td class="num">${n(d.cantidad, d.unidad === 'KG' ? 3 : 0)}</td>
              <td class="num">${n(d.precio)}</td>
              <td class="num"><b>${n(d.subtotal)}</b></td>
              <td>${escaparHtml(d.lote || '—')}</td>
              <td>${escaparHtml(this.fechaCorta(d.fecha_vencimiento))}</td>
            </tr>`).join('')

          const contenido = document.createElement('div')
          contenido.innerHTML = `
            <div class="cab">
              ${logo ? `<img src="${logo}" alt="">` : ''}
              <div class="emp">
                <div class="emp-nom">${escaparHtml(e.nombre || '')}</div>
                <div class="emp-dato">
                  ${escaparHtml(e.sucursal || '')} · NIT ${escaparHtml(e.nit || '')}<br>
                  ${escaparHtml(e.direccion || '')}<br>
                  Telf. ${escaparHtml(e.telefono || '')} · ${escaparHtml(e.ciudad || '')}
                </div>
              </div>
              <div class="caja">
                <div class="tit">NOTA DE COMPRA</div>
                <table>
                  <tr><td class="et">Nro</td><td class="nro">${escaparHtml(c.id)}</td></tr>
                  <tr><td class="et">Fecha</td><td class="num">${escaparHtml(this.fechaCorta(c.fecha))}</td></tr>
                  <tr><td class="et">Hora</td><td class="num">${escaparHtml(c.hora || '')}</td></tr>
                </table>
              </div>
            </div>

            <div class="datos">
              <div><span class="et2">Proveedor</span><b>${escaparHtml(c.proveedor || 'Sin proveedor')}</b></div>
              <div><span class="et2">NIT</span>${escaparHtml(c.nit || '—')}</div>
              <div><span class="et2">Teléfono</span>${escaparHtml(String(p.TELF || '').trim() || '—')}</div>
              <div><span class="et2">Dirección</span>${escaparHtml(String(p.DIRECCION || '').trim() || '—')}</div>
              <div><span class="et2">Factura del proveedor</span>${escaparHtml(c.nro_factura || '—')}</div>
              <div><span class="et2">Forma de pago</span><b>${escaparHtml(c.tipo_pago || '—')}</b></div>
              <div class="ancho"><span class="et2">Registrado por</span><b>${escaparHtml(usuario)}</b></div>
            </div>

            <table class="det">
              <thead><tr>
                <th class="num" style="width:4%">#</th><th style="width:9%">Código</th><th>Producto</th>
                <th style="width:6%">Unid</th><th class="num" style="width:9%">Cantidad</th>
                <th class="num" style="width:10%">Costo Bs</th><th class="num" style="width:11%">Subtotal Bs</th>
                <th style="width:9%">Lote</th><th style="width:9%">Vence</th>
              </tr></thead>
              <tbody>${filas}</tbody>
            </table>

            <div class="pie">
              <div class="obs">
                <b>${(c.detalles || []).length} producto(s)</b><br>
                <b>Observación:</b> ${escaparHtml(c.observacion || '—')}
              </div>
              <table class="tot">
                <tr><td>Subtotal Bs.</td><td class="num">${n(c.subtotal)}</td></tr>
                <tr><td>Descuento Bs.</td><td class="num">${n(c.descuento)}</td></tr>
                <tr class="final"><td>TOTAL Bs.</td><td class="num">${n(c.total)}</td></tr>
              </table>
            </div>

            <div class="firmas">
              <div><b>${escaparHtml(c.proveedor || '')}&nbsp;</b>Entregado por (proveedor)</div>
              <div><b>${escaparHtml(usuario)}</b>Recibido por</div>
            </div>

            <div class="legal">
              ${escaparHtml(e.nombre || '')} · nota de compra Nº ${escaparHtml(c.id)} · impresa el ${date.formatDate(new Date(), 'DD/MM/YYYY HH:mm')}
            </div>`

          if (!impresoraCompras) impresoraCompras = new Printd()
          impresoraCompras.print(contenido, [ESTILOS_NOTA])
        })
        .catch(err => { this.avisar(err, 'No se pudo cargar el detalle para imprimir') })
        .finally(() => { this.imprimiendo = null })
    },

    pedirAnulacion (row) {
      this.sel = row
      this.motivo = ''
      this.dialogAnular = true
    },
    anular () {
      this.anulando = true

      this.$api.put('compras/' + this.sel.id + '/anular', { motivo: this.motivo })
        .then(res => {
          this.$q.notify({
            message: res.data.message,
            color: 'positive',
            icon: 'check_circle',
            position: 'top',
            timeout: 6000
          })
          this.dialogAnular = false
          this.onRequest({ pagination: this.pagination })
        })
        .catch(err => { this.avisar(err, 'No se pudo anular') })
        .finally(() => { this.anulando = false })
    },

    avisar (err, porDefecto) {
      this.$q.notify({
        message: err.response?.data?.message || porDefecto,
        color: 'negative',
        icon: 'error',
        position: 'top'
      })
    }
  }
}
</script>
