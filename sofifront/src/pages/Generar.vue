<template>
  <q-page class="q-pa-xs">
    <div class="row">
      <div class="col-12">
        <q-form @submit="consultar">
          <div class="row">
            <div class="col-2">
              <q-input dense outlined label="fecha" v-model="fecha" type="date"/>
            </div>
            <div class="col-3 flex flex-center">
              <q-btn color="info" icon="search" label="Consultar" type="submit" :loading="loading" :disable="loading"/>
            </div>
              <div class="col-3 flex flex-center">
                <q-btn color="green" label="Pollo preparacion" type="a" :href="url+'generarXlsPollo/'+fecha"
                      target="_blank"
                      icon="list" :loading="loading" :disable="loading"/>
              </div>
            <div class="col-3 flex flex-center">
              <q-btn color="teal" label="Cerdo preparacion" type="a" :href="url+'generarXlsCerdo/'+fecha"
                     target="_blank"
                     icon="list" :loading="loading" :disable="loading"/>  
                   </div>
          </div>
        </q-form>
      </div>
      <div class="col-12">
        <q-table dense title="Pedidos pollo res cerdo" :columns="columspedido" :rows="personales" :filter="filter"
                 :rows-per-page-options="[0]"
                 wrap-cells bordered flat
        >
          <template v-slot:body-cell-pollo="props">
            <q-td auto-width :props="props">
              <template v-if="props.row.pollo>0">
                <q-btn color="green" type="a" :href="url+'excel/p/'+this.fecha+'/1/'+props.row.CodAut" target="_blank"
                       icon="list" size="xs" label="Excel Pollo"/>
              </template>
            </q-td>
          </template>
          <template v-slot:body-cell-res="props">
            <q-td auto-width :props="props">
              <template v-if="props.row.res>0">
                <q-btn color="accent" type="a" :href="url+'excel/r/'+this.fecha+'/1/'+props.row.CodAut" target="_blank"
                       icon="list" size="xs" label="Excel res"/>
              </template>
            </q-td>
          </template>
          <template v-slot:body-cell-cerdo="props">
            <q-td auto-width :props="props">
              <template v-if="props.row.cerdo>0">
                <q-btn color="teal" type="a" :href="url+'excel/c/'+this.fecha+'/1/'+props.row.CodAut" target="_blank"
                       icon="list" size="xs" label="Excel cerdo"/>
              </template>
            </q-td>
          </template>
          <template v-slot:body-cell-vendedor="props">
            <q-td auto-width :props="props">
              {{ props.row.vendedor }}
            </q-td>
          </template>
          <template v-slot:top-right>
            <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
              <template v-slot:append>
                <q-icon name="search"/>
              </template>
            </q-input>
          </template>
        </q-table>
      </div>
    </div>
  </q-page>
</template>

<script>
import {date} from "quasar";

export default {
  name: "Generar",
  data() {
    return {
      url: process.env.API,
      filter: '',
      loading: false,
      personales: [],
      columspedido: [
        {label: 'vendedor', name: 'vendedor', field: 'vendedor', align: 'left'},
        {label: 'pollo', name: 'pollo', field: 'pollo', align: 'left'},
        {label: 'res', name: 'res', field: 'res', align: 'left'},
        {label: 'cerdo', name: 'cerdo', field: 'cerdo', align: 'left'},
      ],
      fecha:date.formatDate(new Date(),'YYYY-MM-DD'),
      // fecha: '2025-06-28',
      fecha1: date.formatDate(new Date(), 'YYYY-MM-DD'),
      fecha2: date.formatDate(new Date(), 'YYYY-MM-DD'),
    }
  },
  created() {
    this.consultar()
  },
  methods: {
    consultar() {
      this.loading = true
      // this.$q.loading.show()
      this.$api.get('excel/' + this.fecha).then(res => {
        // console.log(res.data)
        this.personales = []
        res.data.forEach(r => {
          r.vendedor = r.Nombre1 + ' ' + r.App1
          this.personales.push(r)
        })
        // this.$q.loading.hide()
      }).finally(res => {
        this.loading = false
        // this.$q.loading.hide()
      })
    },
    expedidos() {
      this.$q.loading.show()
      this.$api.post('export', {fecha1: this.fecha1, fecha2: this.fecha2}).then(res => {
        console.log(res.data)
        this.$q.loading.hide()
        this.$q.notify({
          color: 'green',
          message: 'Enviado correctamente',
          icon: 'send'
        })
      }).catch(err => {
        this.$q.loading.hide()
        this.$q.notify({
          color: 'red',
          message: err.response.data.message,
          icon: 'error'
        })
      })
    },
  }
}
</script>

<style scoped>

</style>
