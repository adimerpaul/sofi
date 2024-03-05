<template>
  <q-page class="q-pa-sm">
    <q-card>
      <q-card-section class="q-pa-md row">
        <div class="col-2">
          <q-input dense outlined v-model="fecha" label="Fecha" type="date" />
        </div>
        <div class="col-2 flex flex-center">
          <q-btn label="Consultar" no-caps color="primary" icon="search" :loading="loading" @click="getAlmacenes" />
        </div>
        <div class="col-1">
          <q-chip :color="porcentaje < 100 ? 'red' : 'green'" dense text-color="white">
            {{ porcentaje }}%
          </q-chip>
        </div>
        <div class="col-7  text-right">
          <q-btn label="Exportar Excel" no-caps color="orange" icon="cloud_download" :loading="loading" @click="exportarExcel" />
          <q-btn label="Cargar Excel" no-caps color="green" icon="cloud_upload" :loading="loading" @click="dialogFile = true" />
        </div>
      </q-card-section>
      <q-card-section class="q-pa-md">
        <div class="text-red">Porcentaje de carga</div>
        <q-linear-progress size="25px" :value="parseFloat(porcentaje/100)" color="primary" track-color="grey-3" rounded>
          <div class="absolute-full flex flex-center">
            <q-badge color="white" text-color="accent" :label="porcentaje + '%'" />
          </div>
        </q-linear-progress>
        <q-table :rows-per-page-options="[0]" :loading="loading" :filter="filter" :columns="columns" :rows="almacenes" dense wrap-cells no-data-label="No hay datos" no-results-label="No hay resultados">
          <template v-slot:top-right>
            <q-input placeholder="Buscar" dense outlined v-model="filter" />
          </template>
          <template v-slot:body-cell-opciones="props">
            <q-td :props="props">
              {{ props.pageIndex  + 1 }}
              <q-btn-dropdown label="Opciones" no-caps dense color="primary">
                <q-item clickable v-close-popup @click="clickEdit(props.row)">
                  <q-item-section avatar>
                    <q-icon name="visibility" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>Ver</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item clickable v-close-popup @click="clickDatos(props.row)">
                  <q-item-section avatar>
                    <q-icon name="o_visibility" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>Ver Datos</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item clickable v-close-popup @click="eliminar(props.row.id)">
                  <q-item-section avatar>
                    <q-icon name="delete" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>Eliminar</q-item-label>
                  </q-item-section>
                </q-item>
              </q-btn-dropdown>
            </q-td>
          </template>
          <template v-slot:body-cell-codigo="props">
            <q-td :props="props">
              <q-chip :color="getColor(props.row.codigo)" dense text-color="white">
                {{ props.row.codigo }}
              </q-chip>
            </q-td>
          </template>
          <template v-slot:body-cell-se_descargo="props">
            <q-td :props="props">
              <q-chip :color="props.row.se_descargo=='IMPORTADO' ? 'orange' : 'green'" dense text-color="white">
                {{ props.row.se_descargo }}
              </q-chip>
            </q-td>
          </template>
          <template v-slot:body-cell-cantidad="props">
            <q-td :props="props">
              <q-chip :color="props.row.cantidad==props.row.saldo ? 'green' : 'red'" dense text-color="white">
                {{ props.row.cantidad }}
              </q-chip>
            </q-td>
          </template>
        </q-table>
      </q-card-section>
    </q-card>
    <q-dialog v-model="dialogFile" >
      <q-card>
        <q-card-section class="q-pb-none row">
          <div class="text-h6">Cargar Excel</div>
          <q-space />
          <q-btn icon="close" flat no-caps round @click="dialogFile = false" />
        </q-card-section>
        <q-card-section class="q-pa-md">
          <div class="row">
            <div class="col-12 q-pa-sm">
              <q-file dense outlined v-model="file" label="Seleccionar archivo" @input="fileChange" accept=".xls,.xlsx">
                <template v-slot:prepend>
                  <q-icon name="attach_file" />
                </template>
              </q-file>
            </div>
            <div class="col-12 q-pa-sm">
              <q-input dense outlined v-model="fecha" label="Fecha" type="date" />
            </div>
<!--            <div class="col-12 q-pa-sm">-->
<!--              <q-select v-model="codigo" label="Código" outlined :options="codigos" dense :emit-value="true" :map-options="true" />-->
<!--            </div>-->
            <div class="col-12 q-pa-sm">
              <q-btn no-caps color="primary" class="full-width" label="Subir" :loading="loading" @click="cargarExcel" icon="cloud_upload" />
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
    <q-dialog v-model="dialogAlmacen">
      <q-card>
        <q-card-section class="q-pb-none row">
          <div class="text-h6">Editar Almacen</div>
          <q-space />
          <q-btn icon="close" flat no-caps round @click="dialogAlmacen = false" />
        </q-card-section>
        <q-card-section class="q-pt-none">
          <div class="row">
            <div class="col-12 q-pa-sm">
              <q-input dense outlined v-model="almacen.codigo" label="Código" />
            </div>
            <div class="col-12 q-pa-sm">
              <q-input dense outlined v-model="almacen.codigo_producto" label="Código Producto" />
            </div>
            <div class="col-12 q-pa-sm">
              <q-input dense outlined v-model="almacen.producto" label="Producto" />
            </div>
            <div class="col-12 q-pa-sm">
              <q-input dense outlined v-model="almacen.unidad" label="Unidad" />
            </div>
            <div class="col-12 q-pa-sm">
              <q-input dense outlined v-model="almacen.saldo" label="Saldo" />
            </div>
            <div class="col-12 q-pa-sm">
              <q-input dense outlined v-model="almacen.registro" label="Registro" />
            </div>
            <div class="col-12 q-pa-sm">
              <q-input dense outlined v-model="almacen.vencimiento" label="Vencimiento" />
            </div>
            <div class="col-12 q-pa-sm">
              <q-input dense outlined v-model="almacen.grupo" label="Grupo" />
            </div>
            <div class="col-12 q-pa-sm">
              <q-input dense outlined v-model="almacen.fecha_registro" label="Fecha Registro" />
            </div>
            <div class="col-12 q-pa-sm">
              <q-btn no-caps color="orange" class="full-width" label="Guardar" :loading="loading" @click="modficarAlmacen" icon="save" />
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
    <q-dialog v-model="dialogDatos">
      <q-card>
        <q-card-section class="q-pb-none row">
          <div class="text-h6">Datos Almacen</div>
          <q-space />
          <q-btn icon="close" flat no-caps round v-close-popup />
        </q-card-section>
        <q-card-section class="q-pt-none">
          <div class="row">
            <div class="col-3 text-bold">Cantidad</div>
            <div class="col-3 text-bold">Vencimiento</div>
            <div class="col-3 text-bold">Registro</div>
            <div class="col-3 text-bold">Usuario</div>
            <template v-for="registro in registros" :key="registro.id">
              <div class="col-3">
<!--                <pre>{{ registro}}</pre>-->
                {{ registro.cantidad }}
              </div>
              <div class="col-3">
                {{ registro.fecha_vencimiento }}
              </div>
              <div class="col-3">
                {{ registro.fecha_registro }}
              </div>
              <div class="col-3 text-center">
                {{ registro.user.Nombre1 }}
              </div>
            </template>
<!--            <pre>{{ registros }}</pre>-->
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>
<script>
import {date} from "quasar";
import xlsx from "json-as-xlsx"

export default {
  name: "AlmacenPage",
  data() {
    return {
      codigo: '4',
      codigos: [
        {label: 'A', value: '1', color: 'red'},
        {label: 'B', value: '2', color: 'green'},
        {label: 'C', value: '3', color: 'blue'},
        {label: 'D', value: '4', color: 'yellow'},
        {label: 'E', value: '5', color: 'purple'},
        {label: 'F', value: '6', color: 'orange'},
        {label: 'G', value: '7', color: 'pink'},
        {label: 'H', value: '8', color: 'brown'},
        {label: 'I', value: '9', color: 'grey'},
        {label: 'J', value: '10', color: 'black'},
      ],
      fecha: date.formatDate(new Date(), 'YYYY-MM-DD'),
      file: null,
      almacenes: [],
      porcentaje: 0,
      almacen: {},
      loading: false,
      filter: '',
      dialogFile: false,
      dialogAlmacen: false,
      dialogDatos: false,
      registros: [],
      columns: [
        {name: 'opciones', label: 'Opciones', align: 'left', field: 'opciones', sortable: true},
        {name: 'codigo', label: 'Código', align: 'left', field: 'codigo', sortable: true},
        // {name: 'codigo_producto', label: 'Código Producto', align: 'left', field: 'codigo_producto', sortable: true},
        {name: 'producto', label: 'Producto', align: 'left', field: 'producto', sortable: true},
        // {name: 'unidad', label: 'Unidad', align: 'left', field: 'unidad', sortable: true},
        {name: 'saldo', label: 'Saldo', align: 'left', field: 'saldo', sortable: true},
        // {name: 'registro', label: 'Registro', align: 'left', field: 'registro', sortable: true},
        // {name: 'vencimiento', label: 'Vencimiento', align: 'left', field: 'vencimiento', sortable: true},
        {name: 'grupo', label: 'Grupo', align: 'left', field: 'grupo', sortable: true},
        {name: 'cantidad', label: 'Cantidad', align: 'left', field: 'cantidad', sortable: true},
        // {name: 'fecha_registro', label: 'Fecha Registro', align: 'left', field: 'fecha_registro', sortable: true},
        // se_descargo
        {name: 'se_descargo', label: 'Se Descargo', align: 'left', field: 'se_descargo', sortable: true},
      ]
    }
  },
  mounted() {
    this.getAlmacenes();
  },
  methods: {
    exportarExcel() {
      let data = [
        {
          sheet: "Adults",
          columns: [
            { label: "Codigo", value: "codigo" },
            { label: "Producto", value: "producto" },
            { label: "Saldo", value: "saldo" },
            { label: "Grupo", value: "grupo" },
            { label: "Cantidad", value: "cantidad" },
            { label: "Se Descargo", value: "se_descargo" },
            { label: "Cantidad", value: "cantidad" },
          ],
          content: this.almacenes
        }
      ]

      let settings = {
        fileName: "Almacenes", // The name of the file
        // extraLength: 3, // A bigger number means that columns will be wider
        // writeMode: "writeFile", // The available parameters are 'WriteFile' and 'write'. This setting is optional. Useful in such cases https://docs.sheetjs.com/docs/solutions/output#example-remote-file
        // writeOptions: {}, // Style options from https://docs.sheetjs.com/docs/api/write-options
        // RTL: true, // Display the columns from right-to-left (the default value is false)
      }

      xlsx(data, settings) // Will download the excel file
    },
    getColor(codigo) {
      let color = this.codigos.find(c => c.label === codigo);
      return color ? color.color : 'grey';
    },
    fileChange(event) {
      this.file = event.target.files[0];
    },
    clickEdit(almacen) {
      this.dialogAlmacen = true;
      this.almacen = almacen;
    },
    clickDatos(almacen) {
      this.dialogDatos = true;
      this.almacen = almacen;
      this.$api.get('registros', {
        params: {
          almacen_id: almacen.id,
        }
      }).then(res => {
        this.registros = res.data;
      }).catch(err => {
        console.error(err);
      });
    },
    modficarAlmacen() {
      this.loading = true;
      this.$api.put('almacenes/' + this.almacen.id, this.almacen).then(res => {
        this.dialogAlmacen = false;
        this.getAlmacenes();
      }).catch(err => {
        console.error(err);
      }).finally(() => {
        this.loading = false;
      });
    },
    eliminar(id) {
      this.$q.dialog({
        title: 'Eliminar',
        message: '¿Está seguro de eliminar el registro?',
        cancel: true,
        persistent: true,
      }).onOk(() => {
        this.loading = true;
        this.$api.delete('almacenes/' + id).then(res => {
          this.getAlmacenes();
        }).catch(err => {
          console.error(err);
        }).finally(() => {
          // this.loading = false;
        });
      });
    },
    cargarExcel() {
      if (!this.file) return this.$q.notify({
        color: 'negative',
        position: 'top',
        message: 'Seleccione un archivo',
        icon: 'report_problem',
      });
      this.$q.dialog({
        title: 'Cargar Excel',
        message: '¿Está seguro de cargar el archivo? remplazará los datos existentes',
        cancel: true,
        persistent: true,
      }).onOk(() => {
        this.loading = true;
        let formData = new FormData();
        formData.append('file', this.file);
        formData.append('fecha', this.fecha);
        formData.append('codigo', this.codigo);
        this.$api.post('cargarExcel', formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        }).then(res => {
          this.dialogFile = false;
          this.file = null;
          this.getAlmacenes();
        }).catch(err => {
          this.$q.notify({
            color: 'negative',
            position: 'top',
            message: 'El archivo no es válido',
            icon: 'report_problem',
          });
        }).finally(() => {
          this.loading = false;
        });
      });
    },
    getAlmacenes() {
      this.loading = true;
      this.$api.get('almacenes', {
        params: {
          fecha: this.fecha,
        }
      }).then(res => {
        this.almacenes = res.data.almacenes;
        this.porcentaje = res.data.porcentaje;
      }).catch(err => {
        console.error(err);
      }).finally(() => {
        this.loading = false;
      });
    },
  },
}
</script>
