<template>
  <q-page class="q-pa-xs">
    <div class="row">
      <div class="col-12">
        <q-form @submit="consultar">
          <div class="row">
            <div class="col-3">
              <q-input type="date" dense outlined label="fecha" v-model="fecha"/>
            </div>
            <div class="col-3">
              <q-input type="date" dense outlined label="fecha" v-model="fecha2"/>
            </div>
            <div class="col-3 flex flex-center">
              <q-btn color="info" icon="search" label="Consultar" type="submit"/>
            </div>
            <div class="col-2 flex flex-center">
              <q-btn color="green" icon="description" label="Pollo EXCEL" @click="exportPollo" dense/>
            </div>
            <div class="col-2 flex flex-center">
              <q-btn color="accent" icon="description" label="Cerdo EXCEL" @click="exportCerdo" dense/>
            </div>
            <div class="col-2 flex flex-center">
              <q-btn color="orange-10" icon="description" label="Embut EXCEL" @click="exportEmbutido" dense/>
            </div>
          </div>
        </q-form>
      </div>
      <div class="col-12">
        <q-table dense title="Pedidos" :columns="columspedido" :rows="personales" :filter="filter">
          <template v-slot:top-right>
            <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
              <template v-slot:append>
                <q-icon name="search"/>
              </template>
            </q-input>
          </template>
          <template v-slot:body-cell-excel="props">
            <q-td :props="props">
              <q-btn color="green" icon="list" size="xs" label="Excel" @click="generarConsulta(props.row)"/>
            </q-td>
          </template>
          <template v-slot:body-cell-vendedor="props">
            <q-td :props="props">
              {{ props.row.vendedor }}
            </q-td>
          </template>

        </q-table>
      </div>
      <!--  <div class="col-4">-->
      <!--    <q-input dense outlined v-model="fecha1" label="Fecha Ini" type="date"/>-->
      <!--  </div>-->
      <!--    <div class="col-4">-->
      <!--    <q-input dense outlined v-model="fecha2" label="Fecha Fin" type="date"/>-->
      <!--  </div>-->
      <!--  <div class="col-4">-->
      <!--        <q-btn style="width: 100%" @click="expedidos" color="red" icon="download" label="importar pedidos"> </q-btn>-->
      <!--  </div>-->
    </div>
  </q-page>
</template>

<script>
import {date} from "quasar";
import xlsx from "json-as-xlsx"

export default {
  name: "Generar",
  data() {
    return {
      cliente2728: 'BAJAS POR BONIFICACIONES',
      cliente3070: 'BAJAS POR CALIDAD',
      url: process.env.API,
      filter: '',
      personales: [],
      pedPollo: [],
      pedCerdo: [],
      pedido: [],
      columspedido: [
        {label: 'VENDEDOR', name: 'vendedor', field: 'vendedor', align: 'left'},
        {label: 'EXCEL', name: 'excel', field: 'excel', align: 'left'},
      ],
      fecha: date.formatDate(new Date(), 'YYYY-MM-DD'),
      fecha1: date.formatDate(new Date(), 'YYYY-MM-DD'),
      fecha2: date.formatDate(new Date(), 'YYYY-MM-DD'),
    }
  },
  created() {
    this.consultar()
  },
  methods: {
    exportPollo() {
      this.$q.loading.show()

      this.$api.post('reportePollo2', {ini: this.fecha, fin: this.fecha2}).then(res => {
        if (res.data.length == 0) {
          this.$q.notify({
            message: 'No Ay pedido Pollo',
            color: 'red',
            icon: 'info'
          })
          return false
        }
        let datacaja = [
          {
            sheet: "Pollo",
            columns: [
              {label: "preventista", value: "preventista"},
              // {label: "Cliente", value: "Nombres"},
              {label: "cliente", value: row => row.bonificacionId == null? row.Nombres : row.bonificacionId == 2728 ? this.cliente2728 : this.cliente3070},
              {label: "fecha", value: "fecha"},
              {label: "Observaciones", value: "Observaciones"},
              {label: "producto", value: "producto"},
              {label: "cantidad", value: "cantidad"},
              {label: "precio", value: "precio"},
              {label: "pago", value: row => row.pago == 'CONTADO' ? 'si' : 'no'},
              {label: "fact", value: "fact"},
              {label: "horario", value: "horario"},
              // {label: "comentario", value: "comentario"},
              {label: "comentario", value: row => row.bonificacionId == null? row.comentario : row.Nombres+ ' '+ row.comentario},
            ],
            content: res.data
          },
        ]

        let settings = {
          fileName: "Pollo Frial", // Name of the resulting spreadsheet
          extraLength: 5, // A bigger number means that columns will be wider
          writeOptions: {}, // Style options from https://github.com/SheetJS/sheetjs#writing-options
        }

        xlsx(datacaja, settings) // Will download the excel file

      })
      this.$q.loading.hide()

    },
    exportCerdo() {
      this.$q.loading.show()

      this.$api.post('reporteCerdoTodo', {ini: this.fecha, fin: this.fecha2}).then(res => {
        if (res.data.length == 0) {
          this.$q.notify({
            message: 'No Ay pedido Cerdo',
            color: 'red',
            icon: 'info'
          })
          return false
        }
        let datacaja = [
          {

            sheet: "Cerdo",
            columns: [
              {label: "fecha", value: "fecha"},
              {label: "preventista", value: row => row.Nombre1 + ' ' + row.App1 + ' ' + row.Apm},
              {label: "CI/NIT", value: "Id"},
              // {label: "cliente", value: "Nombres"},
              {label: "cliente", value: row => row.bonificacionId == null? row.Nombres : row.bonificacionId == 2728 ? this.cliente2728 : this.cliente3070},
              {label: "pfrial", value: "pfrial"},
              {label: "entero", value: "entero"},
              {label: "desmembre", value: "desmembre"},
              {label: "corte", value: "corte"},
              {label: "kilo", value: "kilo"},
              {label: "observaciones", value: "Observaciones"},
              {label: "pago", value: row => row.pago == 'CONTADO' ? 'si' : 'no'},
              {label: "fact", value: "fact"},
              {label: "horario", value: "horario"},
              // {label: "comentario", value: "comentario"},
              {label: "comentario", value: row => row.bonificacionId == null? row.comentario : row.Nombres+ ' '+ row.comentario},
            ],
            content: res.data
          },
        ]

        let settings = {
          fileName: "Cerdo Frial", // Name of the resulting spreadsheet
          extraLength: 5, // A bigger number means that columns will be wider
          writeOptions: {}, // Style options from https://github.com/SheetJS/sheetjs#writing-options
        }

        xlsx(datacaja, settings) // Will download the excel file

      })
      this.$q.loading.hide()

    },
    exportEmbutido() {
      this.$q.loading.show()

      this.$api.post('reporteEmbutidoTodo', {ini: this.fecha, fin: this.fecha2}).then(res => {
        if (res.data.length == 0) {
          this.$q.notify({
            message: 'No Ay pedido Embutido',
            color: 'red',
            icon: 'info'
          })
          return false
        }
        let datacaja = [
          {
            sheet: "Embutido",
            columns: [
              {label: "fecha", value: "fecha"},
              {label: "preventista", value: row => row.Nombre1 + ' ' + row.App1 + ' ' + row.Apm},
              {label: "CI/NIT", value: "Id"},
              {label: "cliente", value: "Nombres"},
              {label: "NroPed", value: "NroPed"},
              {label: "cod_prod", value: "cod_prod"},
              {label: "Cant", value: "Cant"},
              {label: "Producto", value: "Producto"},
              {label: "precio", value: "precio"},
              {label: "observaciones", value: "Observaciones"},
              {label: "pago", value: row => row.pago == 'CONTADO' ? 'si' : 'no'},
              {label: "fact", value: "fact"},
              {label: "horario", value: "horario"},
              {label: "comentario", value: "comentario"},
            ],
            content: res.data
          },
        ]

        let settings = {
          fileName: "Embutidos", // Name of the resulting spreadsheet
          extraLength: 5, // A bigger number means that columns will be wider
          writeOptions: {}, // Style options from https://github.com/SheetJS/sheetjs#writing-options
        }

        xlsx(datacaja, settings) // Will download the excel file

      })
      this.$q.loading.hide()

    },
    consultar() {
      this.$q.loading.show()
      this.$api.post('listregistro', {ini: this.fecha, fin: this.fecha2}).then(res => {
        // console.log(res.data)
        this.personales = []
        res.data.forEach(r => {
          r.vendedor = r.Nombre1 + ' ' + r.App1
          this.personales.push(r)
        })
        this.$q.loading.hide()
      })
    },

    generarConsulta(per) {
      this.getCerdo(per)
      this.getEmbutido(per)
      this.getPollo(per)
      let datacaja = [
        {
          sheet: "Cerdo",
          columns: [
            {label: "fecha", value: "fecha"},
            {label: "CI/NIT", value: "Id"},
            // {label: "cliente", value: "Nombres"},
            {label: "cliente", value: row => row.bonificacionId == null? row.Nombres : row.bonificacionId == 2728 ? this.cliente2728 : this.cliente3070},
            {label: "pfrial", value: "pfrial"},
            {label: "entero", value: "entero"},
            {label: "desmembre", value: "desmembre"},
            {label: "corte", value: "corte"},
            {label: "kilo", value: "kilo"},
            {label: "observaciones", value: "Observaciones"},
            {label: "pago", value: row => row.pago == 'CONTADO' ? 'si' : 'no'},
            {label: "fact", value: "fact"},
            {label: "horario", value: "horario"},
            // {label: "comentario", value: "comentario"},
            {label: "comentario", value: row => row.bonificacionId == null? row.comentario : row.Nombres+ ' '+ row.comentario},
          ],
          content: this.pedCerdo
        },
        {
          sheet: "Embutido",
          columns: [
            {label: "fecha", value: "fecha"},
            {label: "CI/NIT", value: "Id"},
            // {label: "cliente", value: "Nombres"},
            {label: "cliente", value: row => row.bonificacionId == null? row.Nombres : row.bonificacionId == 2728 ? this.cliente2728 : this.cliente3070},
            {label: "NroPed", value: "NroPed"},
            {label: "cod_prod", value: "cod_prod"},
            {label: "Cant", value: "Cant"},
            {label: "Producto", value: "Producto"},
            {label: "precio", value: "precio"},
            {label: "observaciones", value: "Observaciones"},
            {label: "pago", value: row => row.pago == 'CONTADO' ? 'si' : 'no'},
            {label: "fact", value: "fact"},
            {label: "horario", value: "horario"},
            // {label: "comentario", value: "comentario"},
            {label: "comentario", value: row => row.bonificacionId == null? row.comentario : row.Nombres+ ' '+ row.comentario},

          ],
          content: this.pedido
        },
        {
          sheet: "Pollo",
          columns: [
            {label: "fecha", value: "fecha"},
            {label: "CI/NIT", value: "Id"},
            // {label: "cliente", value: "Nombres"},
            {label: "cliente", value: row => row.bonificacionId == null? row.Nombres : row.bonificacionId == 2728 ? this.cliente2728 : this.cliente3070},
            {label: "cbrasa5", value: "cbrasa5"},
            {label: "ubrasa5", value: "ubrasa5"},
            {label: "cbrasa6", value: "cbrasa6"},
            {label: "ubrasa6", value: "ubrasa6"},
            {label: "c104", value: "c104"},
            {label: "u104", value: "u104"},
            {label: "c105", value: "c105"},
            {label: "u105", value: "u105"},
            {label: "c106", value: "c106"},
            {label: "u106", value: "u106"},
            {label: "c107", value: "c107"},
            {label: "u107", value: "u107"},
            {label: "c108", value: "c108"},
            {label: "u108", value: "u108"},
            {label: "c109", value: "c109"},
            {label: "u109", value: "u109"},
            {label: "rango p", value: "rango"},
            {label: "ala", value: row => row.ala == null ? '' : row.ala + ' ' + row.unidala},
            {label: "cadera", value: row => row.cadera == null ? '' : row.cadera + ' ' + row.unidcadera},
            {label: "pecho", value: row => row.pecho == null ? '' : row.pecho + ' ' + row.unidpecho},
            {label: "pie", value: row => row.pie == null ? '' : row.pie + ' ' + row.unidpie},
            {label: "filete", value: row => row.filete == null ? '' : row.filete + ' ' + row.unidfilete},
            {label: "cuello", value: row => row.cuello == null ? '' : row.cuello + ' ' + row.unidcuello},
            {label: "hueso", value: row => row.hueso == null ? '' : row.hueso + ' ' + row.unidhueso},
            {label: "menu", value: row => row.menu == null ? '' : row.menu + ' ' + row.unidmenu},
            {label: "bs", value: "bs"},
            {label: "bs2", value: "bs2"},
            {label: "pago", value: row => row.pago == 'CONTADO' ? 'si' : 'no'},
            {label: "observaciones", value: "Observaciones"},
            {label: "fact", value: "fact"},
            {label: "horario", value: "horario"},
            // {label: "comentario", value: "comentario"},
            {label: "comentario", value: row => row.bonificacionId == null? row.comentario : row.Nombres+ ' '+ row.comentario},
          ],
          content: this.pedPollo
        },
      ]

      let settings = {
        fileName: "Embutido - Vendedor " + per.vendedor, // Name of the resulting spreadsheet
        extraLength: 5, // A bigger number means that columns will be wider
        writeOptions: {}, // Style options from https://github.com/SheetJS/sheetjs#writing-options
      }

      xlsx(datacaja, settings) // Will download the excel file

    },

    getCerdo(per) {
      this.$api.post('reporteCerdo', {ini: this.fecha, fin: this.fecha2, codaut: per.CodAut}).then(res => {
        this.pedCerdo = res.data
        if (res.data.length == 0) {
          this.$q.notify({
            message: 'No Ay pedido Cerdo',
            color: 'red',
            icon: 'info'
          })
          return false
        }
      })

    },

    getEmbutido(per) {
      this.$api.post('reporteEmbutido', {ini: this.fecha, fin: this.fecha2, codaut: per.CodAut}).then(res => {
        this.pedido = res.data
        if (res.data.length == 0) {
          this.$q.notify({
            message: 'No Ay pedido ',
            color: 'red',
            icon: 'info'
          })
          return false
        }
      })
    },

    getPollo(per) {
      this.$api.post('reportePollo', {ini: this.fecha, fin: this.fecha2, codaut: per.CodAut}).then(res => {
        console.log(res.data)
        this.pedPollo = res.data
        if (res.data.length == 0) {
          this.$q.notify({
            message: 'No Ay pedido Pollo ',
            color: 'red',
            icon: 'info'
          })
          return false
        }
      })
    },

  }
}
</script>

<style scoped>

</style>
