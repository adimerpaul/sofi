<template>
  <q-page class="q-pa-xs">
    <div class="row">
      <div class="col-12 col-md-2">
        <q-input dense outlined v-model="fecha1" label="Fecha Ini" type="date"/>
      </div>
      <div class="col-12 col-md-2">
        <q-input dense outlined v-model="fecha2" label="Fecha Fin" type="date"/>
      </div>
      <div class="col-12 col-md-4 text-center">
        <q-btn color="info" icon="search" label="Consulta" @click="consultar" no-caps :loading="loading"/>
      </div>
      <div class="col-12 col-md-4 text-right">
        <q-btn
          color="primary"
          label="Exportar PDF"
          icon="picture_as_pdf"
          @click="exportarPDF"
          :loading="loading"
           no-caps
        />
      </div>

      <div class="col-12">
        <q-table dense title="Clientes " :columns="columns" :rows="clientes" :filter="filter" flat bordered :rows-per-page-options="[0]"
                 wrap-cells>
          <template v-slot:top-right>
            <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
              <template v-slot:append>
                <q-icon name="search"/>
              </template>
            </q-input>
          </template>

        </q-table>
<!--        <pre>{{ clientes }}</pre>-->
      </div>
    </div>
  </q-page>
</template>
<script>
import {date} from 'quasar'
// import {jsPDF} from "jspdf";
//
// const conversor = require('conversor-numero-a-letras-es-ar');

export default {
  data() {
    return {
      filter: '',
      fecha1: date.formatDate(Date.now(), 'YYYY-MM-DD'),
      fecha2: date.formatDate(Date.now(), 'YYYY-MM-DD'),
      cobros: [],
      clientes: [],
      loading: false,
      columns: [
        { label: 'CI', name: 'ci', field: 'Id', align: 'left' },
        { label: 'Nombre', name: 'Nombres', field: 'Nombres', align: 'left' },
        { label: 'Dirección', name: 'Direccion', field: 'Direccion', align: 'left' },
        { label: 'Teléfono', name: 'Telf', field: 'Telf', align: 'left' },
        { label: 'Última Compra', name: 'ultima_compra', field: 'ultima_compra', align: 'left', format: val => val ? val.split(' ')[0] : 'Nunca' },
      ],
    }
  },
  mounted() {
    this.consultar()
  },
  methods: {
    consultar() {
      this.loading = true
      this.$api.post('listsinpedido', {ini: this.fecha1, fin: this.fecha2}).then(res => {
        console.log(res.data)
        this.clientes = res.data;

      }).catch(err => {
        this.$q.notify({
          message: err.response.data.message,
          color: 'red',
          icon: 'error'
        })
      }).finally(()=>{
        this.loading = false
      })
    },
    exportarPDF() {
      this.loading = true
      this.$api.post('listsinpedido/exportar', {
        ini: this.fecha1,
        fin: this.fecha2
      }, { responseType: 'blob' }).then(res => {
        const blob = new Blob([res.data], { type: 'application/pdf' })
        const link = document.createElement('a')
        link.href = URL.createObjectURL(blob)
        link.download = 'clientes_sin_pedido.pdf'
        link.click()
      }).catch(err => {
        this.$q.notify({
          message: 'Error al generar PDF',
          color: 'red',
          icon: 'error'
        })
      }).finally(() => {
        this.loading = false
      })
    }
  },

}
</script>

<style scoped>

</style>
