<template>
  <q-page class="q-pa-xs">
    <div>LISTADO DE PEDIDOS CLIENTE</div>
    <div class="row">
      <div class="col-12 col-md-4">
        <q-input @change="consulta()" v-model="fecha" label="fecha" dense outlined type="date"/>
      </div>
      <div class="col-6 col-md-4">
        <q-select
          v-model="usuario"
          :options="usuarios"
          option-label="nombre"
          option-value="ci"
          label="Usuario"
          dense
          outlined
          @update:model-value="consulta"
          v-if="user  == '7329688' || user == '7205489'"
        />
<!--        <pre>{{usuarios}}</pre>-->
<!--        <pre>{{usuario}}</pre>-->
      </div>
      <div class="col-6 col-md-2">
        <q-select
          v-model="tipo"
          :options="[
            'TODOS',
            'PEDIDO',
            'NO PEDIDO',
            'PARADO',
            'SIN VISITA'
          ]"
          label="Tipo de Visita"
          dense
          outlined
          v-if="user  == '7329688' || user == '7205489'"
        />
      </div>
      <div class="col-6 col-md-2">
        <q-btn
          color="primary"
          icon="refresh"
          no-caps
          label="Actualizar"
          @click="consulta"
          :loading="loading"
          v-if="user  == '7329688' || user == '7205489'"
          dense/>
      </div>
<!--      <div class="col-3 text-center q-pa-xs">-->
<!--        <div class="text-subtitle2 text-bold">Totales</div>-->
<!--        <div class="text-h3 text-bold ">{{ pedido + retorno + nopedido }}</div>-->
<!--      </div>-->
<!--      <div class="col-3 text-center q-pa-xs">-->
<!--        <div class="text-subtitle2 text-bold">Pedidos</div>-->
<!--        <div class="text-h3 text-bold text-green ">{{ pedido }}</div>-->
<!--      </div>-->
<!--      <div class="col-3 text-center q-pa-xs">-->
<!--        <div class="text-subtitle2 text-bold">Retorno</div>-->
<!--        <div class="text-h3 text-bold text-yellow ">{{ retorno }}</div>-->
<!--      </div>-->
<!--      <div class="col-3 text-center q-pa-xs">-->
<!--        <div class="text-subtitle2 text-bold">No pedidos</div>-->
<!--        <div class="text-h3 text-bold text-red ">{{ nopedido }}</div>-->
<!--      </div>-->

      <div style="height: 350px; width: 100%;">
<!--        @locationfound="onLocationFound"-->
        <l-map
          @ready="onReady"
          v-model="zoom"
          :zoom="zoom"
          :center="center"
        >
          <!--      @click="ubicacion"-->
          <l-tile-layer
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          ></l-tile-layer>
          <l-marker v-for="c in clientesFiltrados" :key="c.Cod_Aut" :lat-lng="[c.Latitud, c.longitud]">
            <l-icon>
              <q-badge
                :class="c.tipo == 'PEDIDO' ? 'bg-green-5 text-italic' : c.tipo == 'NO PEDIDO' ? 'bg-red-5 text-italic' : c.tipo == 'PARADO' ? 'bg-orange-5 text-italic' : 'bg-blue-5 text-italic'"
                class="q-pa-none"
              >
                {{ c.Cod_Aut }}
              </q-badge>
            </l-icon>
            <l-tooltip>
              {{ c.Nombres }} <br>
              <span v-if="c.tipo !== 'SIN VISITA'">Estado: {{ c.tipo }}</span>
              <span v-else>No visitado</span>
            </l-tooltip>
          </l-marker>
<!--          <l-marker :lat-lng="center">-->
<!--          </l-marker>-->

        </l-map>
      </div>

      <div class="col-12 q-mt-md">
        <q-table
          title="Listado de Clientes Visitados"
          :rows="clientesFiltrados"
          :columns="columns"
          row-key="Cod_Aut"
          dense
          wrap-cells
          flat
          bordered
          :rows-per-page-options="[0]"
          :filter="filterCliente"
        >
          <template v-slot:top-right>
            <q-input
              v-model="filterCliente"
              label="Filtrar por cliente"
              dense
              outlined
            >
              <template v-slot:append>
                <q-icon name="search" class="cursor-pointer"/>
              </template>
            </q-input>
          </template>

          <template v-slot:body="props">
            <q-tr
              :props="props"
              :class="props.row.tipo === 'PEDIDO' ? 'bg-green-2' :
                props.row.tipo === 'NO PEDIDO' ? 'bg-red-2' :
                props.row.tipo === 'PARADO' ? 'bg-orange-2' :
                props.row.tipo === 'SIN VISITA' ? 'bg-blue-1' : ''"
            >
              <q-td key="Cod_Aut">{{ props.row.Cod_Aut }}</q-td>
              <q-td key="Nombres">{{ props.row.Nombres }}</q-td>
              <q-td key="tipo">{{ props.row.tipo }}</q-td>
              <q-td key="fecha">{{ props.row.visita ? props.row.visita.fecha : '—' }}</q-td>
              <q-td key="observacion">{{ props.row.visita ? props.row.visita.observacion : '—' }}</q-td>
            </q-tr>
          </template>
        </q-table>
      </div>
    </div>
  </q-page>
</template>

<script>
import {date} from "quasar";
import {
  LMap,
  LIcon,
  LTileLayer,
  LMarker,
  LControlLayers,
  LTooltip,
  LPopup,
  LPolyline,
  LPolygon,
  LRectangle,
} from "@vue-leaflet/vue-leaflet";
import "leaflet/dist/leaflet.css";

export default {
  name: 'avancePage',
  components: {
    LMap,
    LIcon,
    LTileLayer,
    LMarker,
    // LControlLayers,
    LTooltip,
    // LPopup,
    // LPolyline,
    // LPolygon,
    // LRectangle,
  },
  computed: {
    clientesFiltrados() {
      return this.clientes
        .filter(c => {
          if (this.tipo === 'TODOS') return true;
          return c.tipo === this.tipo;
        })
        .filter(c => {
          if (!this.filterCliente) return true;
          return (c.Nombres || '').toLowerCase().includes(this.filterCliente.toLowerCase());
        });
    }
  },
  data() {
    return {
      tipo: 'TODOS',
      center: [-17.970371, -67.112303],
      zoom: 12,
      clientes: [],
      usuarios: [],
      usuario: {},
      fecha: date.formatDate(new Date(), 'YYYY-MM-DD'),
      fechareporte: {
        ini: date.formatDate(new Date(), 'YYYY-MM-DD'),
        fin: date.formatDate(new Date(), 'YYYY-MM-DD')
      },
      user: '',
      loading: false,
      filterCliente: '',
      pedido: 0,
      retorno: 0,
      nopedido: 0,
      visitas: [],
      infoventa: [],
      preventistas: [],
      preventista: {},
      productos: [],
      pedidos: [],
      expandedId: null,
      columns: [
        { name: 'Cod_Aut', label: 'Código', field: 'Cod_Aut', align: 'left', sortable: true },
        { name: 'Nombres', label: 'Cliente', field: 'Nombres', align: 'left', sortable: true },
        { name: 'tipo', label: 'Estado Visita', field: 'tipo', align: 'left', sortable: true },
        { name: 'fecha', label: 'Fecha Visita', field: row => row.visita?.fecha || '—', align: 'left' },
        { name: 'observacion', label: 'Observación', field: row => row.visita?.observacion || '—', align: 'left' },
      ],
      colped: [
        {name: 'hora', label: 'hora', field: 'hora', sortable: true, align: 'left'},
        {name: 'placa', label: 'placa', field: 'placa', sortable: true, align: 'left'},
        {name: 'CI', label: 'ci', field: 'Id', sortable: true, align: 'left'},
        {name: 'CLIENTE', label: 'nombre', field: 'Nombres', sortable: true, align: 'left'},
        {name: 'COMANDA', label: 'comanda', field: 'comanda', sortable: true},
        {name: 'ESTADO', label: 'estado', field: 'estado'},
      ]
    };
  },
  created() {
    this.user = this.$store.getters['login/user'].ci;
    console.log(this.user);
    this.consulta()
    // this.entregas();
    // this.listhoy()
    this.listapersonalGet()
  },
  methods: {
    listapersonalGet() {
      this.$api.get('personalCliente').then(res => {
        this.usuarios = res.data;
        this.usuario = this.usuarios.find(u => u.ci === this.user);
        // console.log(this.usuario);
      }).catch(err => {
        this.$q.notify({
          message: err.response.data.message,
          color: 'red',
          icon: 'error'
        });
      });
    },
    onLocationFound(location) {
      // console.log(location)
      this.center = [location.latlng.lat, location.latlng.lng]
    },
    onReady(mapObject) {
      mapObject.locate();
    },
    listhoy() {
      // this.$q.loading.show()
      this.loading = true
      this.$api.post('filtrarlista', {filtradia: 8}).then(res => {
        console.log(res.data)
        this.clientes = []
        res.data.forEach(r => {
          let d = r
          if (parseFloat(r.Latitud) != NaN && parseFloat(r.longitud) != NaN && r.Latitud != '' && r.longitud != '') {
            d.Latitud = parseFloat(r.Latitud)
            d.longitud = parseFloat(r.longitud)
          } else {
            d.Latitud = 0
            d.longitud = 0
          }

          this.clientes.push(d)
        })
      }).catch(err => {
        this.$q.notify({
          message: err.response.data.message,
          color: 'red',
          icon: 'error'
        })
      }).finally(() => {
        this.loading = false
      })
    },
    // generar() {
    //   this.consulta();
    //   this.entregas();
    //   this.listhoy();
    // },
    consulta() {
      this.pedido = 0;
      this.retorno = 0;
      this.nopedido = 0;
      this.loading = true;
      this.$api.post('pedidoVenta', {
        fecha: this.fecha,
        usuario: this.usuario
      }).then(res => {
        this.clientes = res.data.map(item => {
          const c = item.cliente;
          c.visita = item.visita;
          c.visitado = item.visitado;

          // Colores por tipo de visita
          if (!item.visitado) {
            c.tipo = 'SIN VISITA';
          } else {
            c.tipo = item.visita.estado || 'SIN VISITA';
          }

          return c;
        });
      }).catch(err => {
        this.$q.notify({
          message: err.response?.data?.message || 'Error al obtener visitas',
          color: 'red',
          icon: 'error'
        });
      }).finally(() => {
        this.loading = false;
      });
    },
    entregas() {
      this.$api.post('reportEntregVend', {fecha: this.fecha}).then(res => {
        this.pedidos = res.data;
      });
    },
    toggleExpand(comanda) {
      this.expandedId = this.expandedId === comanda ? null : comanda;
    },
    descargarFactura(comanda) {
      // const url = `http://localhost:8000/api/factura/${comanda}`; // o /facturaV si estás usando esa

      this.$q.loading.show();

      this.$api.get('factura/' + comanda, {
        responseType: 'blob',
        headers: {
          Accept: 'application/pdf'
        }
      }).then((res) => {
        const blob = new Blob([res.data], {type: 'application/pdf'});
        const link = document.createElement('a');
        link.href = window.URL.createObjectURL(blob);
        link.download = `factura_${comanda}.pdf`;
        link.click();
        window.URL.revokeObjectURL(link.href);
        this.$q.loading.hide();
      }).catch((err) => {
        this.$q.notify({type: 'negative', message: 'Error al descargar la factura'});
        console.error(err);
        this.$q.loading.hide();
      });
    }
  }
};
</script>
