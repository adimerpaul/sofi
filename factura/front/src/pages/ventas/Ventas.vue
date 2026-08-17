<template>
  <q-page class="q-pa-xs">
    <div class="row">
      <div class="col-12 col-md-4 q-pa-xs">
        <q-card flat bordered>
          <q-card-section class="q-pa-none">
            <q-item class="bg-indigo">
              <q-item-section avatar>
                <q-icon name="trending_up" size="50px" color="white" />
              </q-item-section>
              <q-item-section>
                <q-item-label caption class="text-white">Cantidad de Ventas</q-item-label>
<!--                ventas solo activo-->
                <q-item-label  class="text-white text-h4">{{(ventas.filter(venta => venta.estado === 'Activo')).length}}</q-item-label>
              </q-item-section>
            </q-item>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-4 q-pa-xs">
        <q-card flat bordered>
          <q-card-section class="q-pa-none">
            <q-item class="bg-green">
              <q-item-section avatar>
                <q-icon name="monetization_on" size="50px" color="white" />
              </q-item-section>
              <q-item-section>
                <q-item-label caption class="text-white">Ventas Total</q-item-label>
                <q-item-label  class="text-white text-h4">{{(ventas.filter(venta => venta.estado === 'Activo')).reduce((acc, venta) => acc + parseFloat(venta.total), 0)}} Bs</q-item-label>
              </q-item-section>
            </q-item>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-4 q-pa-xs">
        <q-card flat bordered>
          <q-card-section class="q-pa-none">
            <q-item class="bg-red">
              <q-item-section avatar>
                <q-icon name="monetization_on" size="50px" color="white" />
              </q-item-section>
              <q-item-section>
                <q-item-label caption class="text-white">Ventas Anuladas</q-item-label>
                <q-item-label  class="text-white text-h4">{{(ventas.filter(venta => venta.estado === 'Anulada')).length}}</q-item-label>
              </q-item-section>
            </q-item>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12">
        <q-card flat bordered>
          <q-card-section class="q-pa-none">
            <div class="row">
              <div class="col-12 col-md-2">
                <q-input v-model="fechaInicio" label="Fecha inicio" dense outlined type="date" />
              </div>
              <div class="col-12 col-md-2">
                <q-input v-model="fechaFin" label="Fecha fin" dense outlined type="date" />
              </div>
              <div class="col-12 col-md-2">
                <q-select v-model="user" :options="usersTodos" label="Usuario" dense outlined  emit-value map-options/>
              </div>
              <div class="col-12 col-md-3">
                <q-select
                  v-model="productoFiltro"
                  :options="productoOptions"
                  label="Producto"
                  option-label="nombre"
                  option-value="id"
                  dense outlined clearable
                  use-input
                  input-debounce="300"
                  :loading="productoLoading"
                  @filter="filtrarProductos"
                  @update:model-value="ventasGet()"
                  hide-selected
                  fill-input
                >
                  <template #no-option>
                    <q-item>
                      <q-item-section class="text-grey">Sin productos</q-item-section>
                    </q-item>
                  </template>
                  <template #option="scope">
                    <q-item v-bind="scope.itemProps">
                      <q-item-section avatar>
                        <q-img :src="scope.opt.imagen_url" style="width:32px;height:32px;border-radius:4px;" fit="contain">
                          <template #error>
                            <div class="absolute-full flex flex-center bg-grey-2">
                              <q-icon name="medication" size="16px" color="grey-5" />
                            </div>
                          </template>
                        </q-img>
                      </q-item-section>
                      <q-item-section>
                        <q-item-label>{{ scope.opt.nombre }}</q-item-label>
                        <q-item-label caption>Stock: {{ scope.opt.stock ?? 0 }}</q-item-label>
                      </q-item-section>
                    </q-item>
                  </template>
                </q-select>
              </div>
              <div class="col-12 col-md-1">
                <q-btn color="primary" label="Buscar"  no-caps  icon="search" :loading="loading" @click="ventasGet()" />
              </div>
              <div class="col-12 col-md-2 text-right">
                <q-btn-dropdown color="primary" label="Exportar" no-caps  >
                  <q-item clickable v-ripple @click="exportExcel" v-close-popup>
                    <q-item-section avatar>
                      <q-icon name="file_download" />
                    </q-item-section>
                    <q-item-section>Excel</q-item-section>
                  </q-item>
                </q-btn-dropdown>
              </div>
              <div class="col-12 col-md-2 text-right">
                <q-btn color="positive" label="Nueva venta"  no-caps  icon="add_circle_outline" :loading="loading" :to="'/ventaNuevo'" />
              </div>
              <div class="col-12 row items-center q-gutter-sm q-pa-xs">
                <q-select
                  v-model="envioFilter"
                  :options="envioOptions"
                  label="Envío a SIAT"
                  dense
                  outlined
                  emit-value
                  map-options
                  style="min-width: 200px"
                />
                <q-chip
                  v-if="ventasPendientes.length"
                  clickable
                  color="orange"
                  text-color="white"
                  icon="cloud_off"
                  @click="envioFilter = 'pendientes'"
                >
                  {{ ventasPendientes.length }} factura(s) sin enviar a SIAT
                  <q-tooltip>De cualquier fecha. Clic para verlas y enviarlas.</q-tooltip>
                </q-chip>
                <q-chip v-else color="green" text-color="white" icon="cloud_done">
                  Todas las facturas fueron enviadas
                </q-chip>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>
    <q-markup-table dense wrap-cells>
      <thead>
        <tr class="bg-primary text-white">
          <th>Acciones</th>
          <th>ID</th>
<!--          opciones deatlle fecha hora estado total-->
          <th>Fecha</th>
          <th>Cliente</th>
          <th>Usuario</th>
          <th>Estado</th>
          <th>Total</th>
          <th>Detalle</th>
          <th>Linea</th>
        </tr>
      </thead>
      <tbody>
      <template v-if="ventasFiltradas.length != 0">
        <tr v-for="(venta, index) in ventasFiltradas" :key="venta.id">
          <td>
            <q-btn-dropdown color="primary" label="Opciones" no-caps dense size="10px" :loading="loading">
              <q-item clickable v-ripple @click="imprimir(venta)" v-close-popup>
                <q-item-section avatar>
                  <q-icon name="print" />
                </q-item-section>
                <q-item-section>Imprimir</q-item-section>
              </q-item>
              <q-item clickable v-ripple @click="verificarImpuestos(venta)" v-close-popup v-if="venta.cuf">
                <q-item-section avatar>
                  <q-icon name="fact_check" />
                </q-item-section>
                <q-item-section>Verificar impuestos</q-item-section>
              </q-item>
<!--              imprimis de impuesto-->
              <q-item clickable v-ripple @click="imprimirImpuestos(venta)" v-close-popup v-if="venta.cuf">
                <q-item-section avatar>
                  <q-icon name="receipt_long" />
                </q-item-section>
                <q-item-section>Imprimir impuesto</q-item-section>
              </q-item>
              <q-item clickable v-ripple @click="anular(venta.id)" v-close-popup v-if="venta.estado === 'Activo'">
                <q-item-section avatar>
                  <q-icon name="delete" />
                </q-item-section>
                <q-item-section>Anular</q-item-section>
              </q-item>
              <q-item clickable v-ripple @click="revertir(venta.id)" v-close-popup v-if="venta.estado === 'Anulada'">
                <q-item-section avatar>
                  <q-icon name="restore" />
                </q-item-section>
                <q-item-section>Revertir Anulación</q-item-section>
              </q-item>
              <q-item clickable v-ripple @click="dialogEventoClick(venta)" v-close-popup v-if="!venta.online && venta.cuf && venta.estado === 'Activo'">
                <q-item-section avatar>
                  <q-icon name="cloud_upload" />
                </q-item-section>
                <q-item-section>Enviar a SIAT (paquete)</q-item-section>
              </q-item>
<!--              <q-item clickable v-ripple @click="tipoVentasChange(venta.id)" v-close-popup>-->
<!--                <q-item-section avatar>-->
<!--                  <q-icon name="swap_horiz" />-->
<!--                </q-item-section>-->
<!--                <q-item-section>Cambiar a {{ venta.tipo_venta === 'Interno' ? 'Externo' : 'Interno' }}</q-item-section>-->
<!--              </q-item>-->
            </q-btn-dropdown>
          </td>
          <td>{{ venta.id }}</td>
          <td>
            {{ venta.fecha }}
            {{ venta.hora }}
          </td>
          <td>{{ venta.cliente.nombre }}</td>
          <td>{{ venta.user.name }}</td>
          <td>
            <!--            {{ venta.estado }} q-chip activo verde -->
            <q-chip :color="venta.estado === 'Activo' ? 'positive' : 'negative'" class="text-white" dense>{{ venta.estado }}</q-chip>
          </td>
          <td class="text-bold">
            {{ venta.total }}
            <q-chip size="10px" :color="venta.tipo_pago === 'Efectivo' ? 'green' : 'blue'" class="text-white" dense>{{ venta.tipo_pago.charAt(0) }}</q-chip>
          </td>
          <td>
            <div style="max-width: 200px;wrap-option: wrap;line-height: 0.9;">
              {{ venta.detailsText }}
            </div>
          </td>
          <td>
            <q-chip size="10px" :color="venta.online ? 'green' : (venta.tipo_comprobante === 'FACTURA' ? 'orange' : 'red')" class="text-white" dense>
              {{ venta.online ? 'S' : 'N' }}
              <q-tooltip>{{ venta.online ? 'Enviada a SIAT' : (venta.tipo_comprobante === 'FACTURA' ? 'Factura pendiente de envío a SIAT' : 'No se envía a SIAT') }}</q-tooltip>
            </q-chip>
          </td>
        </tr>
      </template>
      <template v-else>
<!--        <tr>-->
<!--          <td colspan="8" class="text-center">-->
<!--&lt;!&ndash;            <q-icon name="warning" size="50px" color="red" />&ndash;&gt;-->
<!--            <div class="text-h6">No hay ventas registradas</div>-->
<!--          </td>-->
<!--        </tr>-->
      </template>
      </tbody>
    </q-markup-table>
  </q-page>
  <q-dialog v-model="dialogEvento" persistent>
    <q-card>
      <q-card-section>
        <div class="text-h6">Enviar factura fuera de línea a SIAT</div>
        <div class="text-caption text-grey-8">
          Se registrará el evento significativo, se enviará el paquete, se validará
          y se enviará el correo al cliente, todo en un solo paso.
        </div>
      </q-card-section>

      <q-card-section>
        <q-select
          v-model="codigoMotivoEvento"
          :options="eventos"
          label="Motivo del evento"
          emit-value
          map-options
          dense
          outlined
          :loading="loading"
          style="width: 100%;"
        />
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat label="Cancelar" color="primary" v-close-popup :disable="loading" />
        <q-btn
          flat
          label="Enviar"
          color="primary"
          icon="cloud_upload"
          :loading="loading"
          :disabled="!codigoMotivoEvento"
          @click="enviarPaquete()"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
  <div id="myElement" class="hidden"></div>
</template>
<script>
import moment from 'moment'
import {Imprimir} from "src/addons/Imprimir";
import {Excel} from "src/addons/Excel";
export default {
  name: 'Ventas',
  data() {
    return {
      dialogEvento : false,
      codigoMotivoEvento: null,
      eventos : [
        {label: '1 - CORTE DEL SERVICIO DE INTERNET', value: 1},
        {label: '2 - INACCESIBILIDAD AL SERVICIO WEB DE LA ADMINISTRACIÓN TRIBUTARIA', value: 2},
        {label: '3 - INGRESO A ZONAS SIN INTERNET POR DESPLIEGUE DE PUNTO DE VENTA EN VEHICULOS AUTOMOTORES', value: 3},
        {label: '4 - VENTA EN LUGARES SIN INTERNET', value: 4},
        {label: '5 - VIRUS INFORMÁTICO O FALLA DE SOFTWARE', value: 5},
        {label: '6 - CAMBIO DE INFRAESTRUCTURA DEL SISTEMA INFORMÁTICO DE FACTURACIÓN O FALLA DE HARDWARE', value: 6},
        {label: '7 - CORTE DE SUMINISTRO DE ENERGIA ELECTRICA', value: 7},
      ],
      ventas: [],
      venta: {},
      // facturas sin enviar a SIAT, de cualquier fecha
      ventasPendientes: [],
      envioFilter: 'todas',
      envioOptions: [
        {label: 'Todas', value: 'todas'},
        {label: 'Sin enviar a SIAT', value: 'pendientes'},
        {label: 'Enviadas a SIAT', value: 'enviadas'},
      ],
      ventaDialog: false,
      fechaInicio: moment().format('YYYY-MM-DD'),
      fechaFin: moment().format('YYYY-MM-DD'),
      loading: false,
      actionPeriodo: '',
      gestiones: [],
      users: [],
      user: '',
      productoFiltro: null,
      productoOptions: [],
      productoLoading: false,
      filter: '',
      roles: ['Doctor', 'Enfermera', 'Administrativo', 'Secretaria'],
      columns: [
        { name: 'actions', label: 'Acciones', align: 'center' },
        { name: 'nombre', label: 'Nombre', align: 'left', field: 'nombre' },
        { name: 'descripcion', label: 'Descripción', align: 'left', field: 'descripcion' },
        { name: 'unidad', label: 'Unidad', align: 'left', field: 'unidad' },
        { name: 'precio', label: 'Precio', align: 'left', field: 'precio' },
        { name: 'stock', label: 'Stock', align: 'left', field: 'stock' },
        { name: 'stock_minimo', label: 'Stock mínimo', align: 'left', field: 'stock_minimo' },
        { name: 'stock_maximo', label: 'Stock máximo', align: 'left', field: 'stock_maximo' },
      ]
    }
  },
  mounted() {
    this.ventasGet()
    this.usersGet()
  },
  methods: {
    exportExcel() {
      // let data = [{
      //   columns: [
      //     {label: "Nombre", value: "nombre"},
      //     {label: "Descripción", value: "descripcion"},
      //     {label: "Unidad", value: "unidad"},
      //     {label: "Precio", value: "precio"},
      //     {label: "Stock", value: "stock"},
      //     {label: "Stock mínimo", value: "stock_minimo"},
      //     {label: "Stock máximo", value: "stock_maximo"},
      //   ],
      //   content: res.data
      // }]
      // Excel.export(data,'Productos')
      let data = [{
        columns: [
          {label: "ID", value: "id"},
          {label: "Fecha", value: "fecha"},
          {label: "Cliente", value: "cliente.nombre"},
          {label: "Usuario", value: "user.name"},
          {label: "Estado", value: "estado"},
          {label: "Total", value: "total"},
          {label: "Detalle", value: "detailsText"},
          // {label: "Tipo venta", value: "tipo_venta"},
        ],
        content: this.ventas
      }]
      Excel.export(data,'Ventas')
    },
    usersGet() {
      this.$axios.get('users').then(res => {
        this.users = res.data
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      })
    },
    filtrarProductos(val, update, abort) {
      this.productoLoading = true
      this.$axios.get('productosStock', { params: { search: val, per_page: 20 } })
        .then(res => {
          update(() => {
            this.productoOptions = res.data.data
          })
        })
        .catch(() => {
          abort()
        })
        .finally(() => {
          this.productoLoading = false
        })
    },
    imprimir(venta) {
      Imprimir.printFactura(venta)
    },
    tipoVentasChange(id) {
      this.$axios.put(`tipoVentasChange/${id}`).then(res => {
        this.$alert.success('Tipo de venta cambiado')
        this.ventasGet()
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      })
    },
    imprimirImpuestos(venta) {
      window.open(this.$store.env.url2+`consulta/QR?nit=${this.$store.env.nit}&cuf=${venta.cuf}&numero=${venta.id}&t=2`, '_blank')
    },
    verificarImpuestos(venta) {
      this.loading = true
      this.$axios.post(`verificarImpuestos/${venta.cuf}`).then(res => {
        this.$q.dialog({
          title: 'Verificación de impuestos',
          fullWidth: true,
          message: '<pre>' + JSON.stringify(res.data, null, 2) + '</pre>',
          html: true,
          ok: true
        })
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },
    enviarPaquete() {
      this.loading = true
      this.$axios.post('enviarPaquete', {
        venta_id: this.venta.id,
        codigoMotivoEvento: this.codigoMotivoEvento,
        descripcion: this.eventos.find(e => e.value === this.codigoMotivoEvento).label
      }).then(res => {
        this.$alert.success(res.data.message || 'Factura enviada y validada en SIAT correctamente')
        this.dialogEvento = false
        this.ventasGet()
      }).catch(error => {
        this.$alert.error(error.response?.data?.message || 'Error al enviar el paquete')
      }).finally(() => {
        this.loading = false
      })
    },
    dialogEventoClick(venta) {
      this.dialogEvento = true
      this.venta = venta
      this.codigoMotivoEvento = 2
    },
    anular(id) {
      this.$q.dialog({
        title: 'Anular Factura',
        message: 'Seleccione el motivo de la anulación:',
        options: {
          type: 'radio',
          model: 1,
          items: [
            { label: '1 - FACTURA MAL EMITIDA', value: 1 },
            { label: '2 - DATOS DE EMISION INCORRECTOS', value: 2 },
            { label: '3 - FACTURA O NOTA DEVUELTA', value: 3 }
          ]
        },
        cancel: true,
        persistent: true
      }).onOk(codigoMotivo => {
        this.loading = true
        this.$axios.put(`ventasAnular/${id}`, { codigoMotivo: codigoMotivo }).then(res => {
          this.$alert.success(res.data?.message || 'Venta anulada')
          this.ventasGet()
        }).catch(error => {
          this.$alert.error(error.response?.data?.message || 'Error al anular la venta')
        }).finally(() => {
          this.loading = false
        })
      })
    },
    revertir(id) {
      this.$alert.dialog('¿Está seguro de revertir la anulación de la venta?').onOk(() => {
        this.loading = true
        this.$axios.put(`ventasRevertir/${id}`).then(res => {
          this.$alert.success(res.data?.message || 'Reversión completada con éxito')
          this.ventasGet()
        }).catch(error => {
          this.$alert.error(error.response.data.message || 'Error al revertir la venta')
        }).finally(() => {
          this.loading = false
        })
      })
    },
    ventasGet() {
      this.loading = true
      this.$axios.get('ventas',{
        params: {
          fechaInicio: this.fechaInicio,
          fechaFin: this.fechaFin,
          user: this.user,
          producto_id: this.productoFiltro?.id ?? ''
        }
      }).then(res => {
        this.ventas = res.data
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
      this.ventasPendientesGet()
    },
    ventasPendientesGet() {
      this.$axios.get('ventasPendientes').then(res => {
        this.ventasPendientes = res.data
      }).catch(error => {
        console.error(error)
      })
    },
  },
  computed: {
    ventasFiltradas() {
      if (this.envioFilter === 'pendientes') return this.ventasPendientes
      if (this.envioFilter === 'enviadas') return this.ventas.filter(venta => venta.online)
      return this.ventas
    },
    usersTodos() {
      // colocar a user todos
      return [{label: 'Todos', value: ''}, ...this.users.map(user => ({label: user.name, value: user.id}))]
    },
    totalInternos() {
      return this.ventas.reduce((acc, venta) => venta.tipo_venta === 'Interno' && venta.estado === 'Activo' ? acc + parseFloat(venta.total) : acc, 0)
    },
    totalExternos() {
      return this.ventas.reduce((acc, venta) => venta.tipo_venta === 'Externo' && venta.estado === 'Activo' ? acc + parseFloat(venta.total) : acc, 0)
    }
  }
}
</script>
