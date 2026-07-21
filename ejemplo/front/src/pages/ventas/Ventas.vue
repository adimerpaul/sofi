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
              <div class="col-12 col-md-2">
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
      <template v-if="ventas.length != 0">
        <tr v-for="(venta, index) in ventas" :key="venta.id">
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
              <q-item clickable v-ripple @click="anular(venta.id)" v-close-popup>
                <q-item-section avatar>
                  <q-icon name="delete" />
                </q-item-section>
                <q-item-section>Anular</q-item-section>
              </q-item>
              <q-item clickable v-ripple @click="dialogEventoClick(venta)" v-close-popup v-if="!venta.online">
                <q-item-section avatar>
                  <q-icon name="event" />
                </q-item-section>
                <q-item-section>Evento significativo</q-item-section>
              </q-item>
<!--              validarPaquete-->
              <q-item clickable v-ripple @click="validarPaquete(venta)" v-close-popup v-if="!venta.online">
                <q-item-section avatar>
                  <q-icon name="verified" />
                </q-item-section>
                <q-item-section>Validar paquete</q-item-section>
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
            <q-chip size="10px" :color="venta.online ? 'green' : 'red'" class="text-white" dense>{{ venta.online ? 'S' : 'N' }}</q-chip>
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
        <div class="text-h6">Seleccionar motivo del evento significativo</div>
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
          style="width: 100%;"
        />
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat label="Cancelar" color="primary" v-close-popup />
        <q-btn
          flat
          label="Guardar"
          color="primary"
          :disabled="!codigoMotivoEvento"
          @click="() => {
            $axios.post('eventoSignificativo', {
              venta_id: venta.id,
              codigoMotivoEvento: codigoMotivoEvento,
              descripcion: eventos.find(e => e.value === codigoMotivoEvento).label
            }).then(res => {
              $alert.success('Evento significativo registrado')
              dialogEvento = false
              ventasGet()
            }).catch(error => {
              $alert.error(error.response.data.message)
            })
          }"
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
      ventaDialog: false,
      fechaInicio: moment().format('YYYY-MM-DD'),
      fechaFin: moment().format('YYYY-MM-DD'),
      loading: false,
      actionPeriodo: '',
      gestiones: [],
      users: [],
      user: '',
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
    validarPaquete(venta) {
      this.loading = true
      this.$axios.post(`validarPaquete`,{
        venta_id: venta.id
      }).then(res => {
        this.$q.dialog({
          title: 'Validación de paquete',
          fullWidth: true,
          message: '<pre>' + JSON.stringify(res.data, null, 2) + '</pre>',
          html: true,
          ok: true
        })
        this.ventasGet()
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },
    dialogEventoClick(venta) {
      this.dialogEvento = true
      this.venta = venta
      this.codigoMotivoEvento = null
    },
    anular(id) {
      this.$alert.dialog('¿Está seguro de anular la venta?').onOk(() => {
        this.loading = true
        this.$axios.put(`ventasAnular/${id}`).then(res => {
          this.$alert.success('Venta anulada')
          this.ventasGet()
        }).catch(error => {
          this.$alert.error(error.response.data.message)
        })
      })
    },
    ventasGet() {
      this.loading = true
      this.$axios.get('ventas',{
        params: {
          fechaInicio: this.fechaInicio,
          fechaFin: this.fechaFin,
          user: this.user
        }
      }).then(res => {
        this.ventas = res.data
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },
  },
  computed: {
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
