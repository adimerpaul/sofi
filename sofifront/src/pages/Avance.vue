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

      <div class="col-12">
        <q-table
          title="Listado de Entregas"
          :rows="pedidos"
          :columns="colped"
          row-key="comanda"
          dense
          wrap-cells
          :rows-per-page-options="[0]"
          flat
          bordered
        >
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

export default {
  name: 'avancePage',
  data() {
    return {
      usuarios: [],
      fecha: date.formatDate(new Date(), 'YYYY-MM-DD'),
      fechareporte: {
        ini: date.formatDate(new Date(), 'YYYY-MM-DD'),
        fin: date.formatDate(new Date(), 'YYYY-MM-DD')
      },
      user: '',
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
  },
  methods: {
    generar() {
      this.consulta();
      this.entregas();
    },
    consulta() {
      this.pedido = 0;
      this.retorno = 0;
      this.nopedido = 0;
      this.$q.loading.show();
      this.$api.post('pedidoVenta', { fecha: this.fecha }).then(res => {
        if (res.data.length > 0) {
          res.data.forEach(r => {
            if (r.estado === 'PEDIDO') this.pedido = r.cantidad;
            if (r.estado === 'PARADO') this.retorno = r.cantidad;
            if (r.estado === 'NO PEDIDO') this.nopedido = r.cantidad;
          });
        }
        this.$q.loading.hide();
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
