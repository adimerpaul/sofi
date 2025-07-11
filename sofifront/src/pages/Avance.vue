<template>
  <q-page class="q-pa-xs">
    <div>LISTADO DE PEDIDOS CLIENTE</div>
    <div class="row">
      <div class="col-12">
        <q-input @change="generar()" v-model="fecha" label="fecha" dense outlined type="date"/>
      </div>
      <div class="col-3 text-center q-pa-xs">
        <div class="text-subtitle2 text-bold">Totales</div>
        <div class="text-h3 text-bold ">{{ pedido + retorno + nopedido }}</div>
      </div>
      <div class="col-3 text-center q-pa-xs">
        <div class="text-subtitle2 text-bold">Pedidos</div>
        <div class="text-h3 text-bold text-green ">{{ pedido }}</div>
      </div>
      <div class="col-3 text-center q-pa-xs">
        <div class="text-subtitle2 text-bold">Retorno</div>
        <div class="text-h3 text-bold text-yellow ">{{ retorno }}</div>
      </div>
      <div class="col-3 text-center q-pa-xs">
        <div class="text-subtitle2 text-bold">No pedidos</div>
        <div class="text-h3 text-bold text-red ">{{ nopedido }}</div>
      </div>

       <div style="height: 350px; width: 100%;">
    <l-map
      @ready="onReady"
      @locationfound="onLocationFound"
      v-model="zoom"
      :zoom="zoom"
      :center="center"

    >
<!--      @click="ubicacion"-->
      <l-tile-layer
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      ></l-tile-layer>


      <l-marker  v-for="c in clientes" :key="c.Cod_Aut" :lat-lng="[c.Latitud, c.longitud]"  >
        <l-icon><q-badge  :class="c.tipo=='PEDIDO'?'bg-green-5  text-italic':c.tipo=='PARADO'?'bg-yellow-5  text-italic':c.tipo=='NO PEDIDO'?'bg-red-5 text-italic':''"  class="q-pa-none" color="info" >{{c.Cod_Aut}}</q-badge></l-icon>
        <l-tooltip>{{c.Nombres}}</l-tooltip>
      </l-marker>
      <l-marker :lat-lng="center"  >
      </l-marker>

    </l-map>
    </div>

      <div class="col-12">
        <q-table
          title="Listado de Entregas"
          :rows="pedidos"
          :columns="colped"
          row-key="comanda"
          dense
          wrap-cells
          :filter="filterCliente"
          :rows-per-page-options="[0]"
          flat
          bordered
        >
<!--          tamplet body top-->
          <template v-slot:top-right>
<!--            btn actulizar-->
            <q-btn
              color="primary"
              icon="refresh"
              no-caps
              label="Actualizar"
              @click="generar"
              :loading="loading"
              dense/>
            <q-input
              v-model="filterCliente"
              label="Filtrar por cliente"
              dense
              outlined
            >
              <template v-slot:append>
                <q-icon name="search" class="cursor-pointer" />
              </template>
            </q-input>
          </template>
          <template v-slot:body="props">
            <q-tr
              :props="props"
              :class="props.row.estado == 'ENTREGADO' ? 'bg-green' : props.row.estado == 'NO ENTREGADO' ? 'bg-amber' : props.row.estado == 'RECHAZADO' ? 'bg-red' : ''"
            >
              <q-td
                v-for="col in props.cols"
                :key="col.name"
                :props="props"
              >
                {{ col.value }}
              </q-td>
              <q-td>
                <q-btn-dropdown
                  color="primary"
                  size="sm"
                  dense
                  no-caps
                  :label="expandedId === props.row.comanda ? 'Opciones' : 'Ver'"
                  icon="menu"
                  :menu-offset="[0, 10]"
                >
                  <q-list style="min-width: 150px">
                    <q-item clickable v-close-popup @click="toggleExpand(props.row.comanda)">
                      <q-item-section avatar><q-icon :name="expandedId === props.row.comanda ? 'visibility_off' : 'visibility'" /></q-item-section>
                      <q-item-section>{{ expandedId === props.row.comanda ? 'Ocultar Detalle' : 'Ver Detalle' }}</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="descargarFactura(props.row.comanda)">
                      <q-item-section avatar><q-icon name="picture_as_pdf" /></q-item-section>
                      <q-item-section>Descargar PDF</q-item-section>
                    </q-item>
                  </q-list>
                </q-btn-dropdown>
              </q-td>
            </q-tr>
            <q-tr v-show="expandedId === props.row.comanda" :props="props">
              <q-td colspan="100%">
                <div class="text-left" v-for="(r, index) in props.row.detalle" :key="`${r.cod_prod}-${index}`">
                  <b>Código:</b> {{ r.cod_prod }} &nbsp;
                  <b>Producto:</b> {{ r.Producto }} &nbsp;
                  <b>Cantidad:</b> {{ r.cant }}
                </div>
                <div class="text-right q-mt-sm">
                  <q-separator />
                  <div class="text-bold">TOTAL DEL PEDIDO:
                    {{ props.row.detalle.reduce((total, item) => total + parseFloat(item.cant), 0).toFixed(3) }}
                  </div>
                </div>
              </q-td>
            </q-tr>
          </template>
        </q-table>
      </div>
    </div>
  </q-page>
</template>

<script>
import { date } from "quasar";
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
  data() {
    return {
      center:[-17.970371, -67.112303],
      zoom: 16,
      clientes: [],
      usuarios: [],
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
        {
          name: 'name',
          label: 'CLIENTE',
          align: 'left',
          field: 'Nombres',
          sortable: true
        },
        {
          name: 'estado',
          align: 'center',
          label: 'ESTADO',
          field: 'tipo',
          sortable: true
        }
      ],
      colped: [
        { name: 'hora', label: 'hora', field: 'hora', sortable: true, align: 'left' },
        { name: 'placa', label: 'placa', field: 'placa', sortable: true, align: 'left' },
        { name: 'CI', label: 'ci', field: 'Id', sortable: true, align: 'left' },
        { name: 'CLIENTE', label: 'nombre', field: 'Nombres', sortable: true, align: 'left' },
        { name: 'COMANDA', label: 'comanda', field: 'comanda', sortable: true },
        { name: 'ESTADO', label: 'estado', field: 'estado' },
      ]
    };
  },
  created() {
    this.user = this.$store.getters['login/user'].ci;
    console.log(this.user);
    this.consulta();
    this.entregas();
    this.listhoy()
  },
  methods: {
        onLocationFound(location){
      // console.log(location)
      this.center=[location.latlng.lat,location.latlng.lng]
    },
        onReady (mapObject) {
      mapObject.locate();
    },
      listhoy(){
      // this.$q.loading.show()
      this.loading=true
      this.$api.post('filtrarlista',{filtradia:8}).then(res=>{
         console.log(res.data)
        this.clientes=[]
        res.data.forEach(r=>{
          let d=r
          if (parseFloat(r.Latitud)!=NaN && parseFloat(r.longitud)!=NaN && r.Latitud!='' && r.longitud!='' ){
            d.Latitud=parseFloat(r.Latitud)
            d.longitud=parseFloat(r.longitud)
          }else{
            d.Latitud=0
            d.longitud=0
          }

          this.clientes.push(d)
        })
      }).catch(err=>{
        this.$q.notify({
          message:err.response.data.message,
          color:'red',
          icon:'error'
        })
      }).finally(()=>{
        this.loading=false
      })
    },
    generar() {
      this.consulta();
      this.entregas();
      this.listhoy();
    },
    consulta() {
      this.pedido = 0;
      this.retorno = 0;
      this.nopedido = 0;
      // this.$q.loading.show();
      this.loading = true
      this.$api.post('pedidoVenta', { fecha: this.fecha }).then(res => {
        if (res.data.length > 0) {
          res.data.forEach(r => {
            if (r.estado === 'PEDIDO') this.pedido = r.cantidad;
            if (r.estado === 'PARADO') this.retorno = r.cantidad;
            if (r.estado === 'NO PEDIDO') this.nopedido = r.cantidad;
          });
        }
        // this.$q.loading.hide();
      }).finally(() => {
        this.loading = false;
      });
    },
    entregas() {
      this.$api.post('reportEntregVend', { fecha: this.fecha }).then(res => {
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
        const blob = new Blob([res.data], { type: 'application/pdf' });
        const link = document.createElement('a');
        link.href = window.URL.createObjectURL(blob);
        link.download = `factura_${comanda}.pdf`;
        link.click();
        window.URL.revokeObjectURL(link.href);
        this.$q.loading.hide();
      }).catch((err) => {
        this.$q.notify({ type: 'negative', message: 'Error al descargar la factura' });
        console.error(err);
        this.$q.loading.hide();
      });
    }
  }
};
</script>
