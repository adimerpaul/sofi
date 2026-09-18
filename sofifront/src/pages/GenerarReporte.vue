<template>
  <q-page class="q-pa-xs">
    <div class="row">
      <div class="col-12">
        <q-form @submit="consultar">
          <div class="row q-col-gutter-xs items-center">
            <div class="col-12 col-sm-3">
              <q-input type="date" dense outlined label="Desde" v-model="fecha"/>
            </div>
            <div class="col-12 col-sm-3">
              <q-input type="date" dense outlined label="Hasta" v-model="fecha2"/>
            </div>
            <div class="col-12 col-sm-3">
              <q-btn class="full-width" color="info" icon="search" label="Consultar" type="submit"/>
            </div>
          </div>
          <!-- Embutidos sale en dos: todo lo que cargaron los preventistas
               (incluido lo que todavia pueden cambiar) y solo lo enviado, que
               es lo que de verdad se va a despachar. -->
          <div class="row q-col-gutter-xs q-mt-xs q-mb-sm">
            <div class="col-6 col-sm-3">
              <q-btn class="full-width" color="green" icon="description" label="Pollo EXCEL" @click="abrirReporte('pollo')" dense/>
            </div>
            <div class="col-6 col-sm-3">
              <q-btn class="full-width" color="accent" icon="description" label="Cerdo EXCEL" @click="abrirReporte('cerdo')" dense/>
            </div>
            <div class="col-6 col-sm-3">
              <q-btn class="full-width" color="orange-10" icon="description" label="Embut. todos" @click="abrirReporte('embutido')" dense/>
            </div>
            <div class="col-6 col-sm-3">
              <q-btn class="full-width" color="deep-orange-9" icon="fact_check" label="Embut. enviados" @click="abrirReporte('embutidoEnviados')" dense/>
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
              <q-btn color="green" icon="list" size="xs" label="Excel" @click="abrirReporte('vendedor', props.row)"/>
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
    <q-dialog v-model="dialogReporte" :persistent="generandoReporte">
      <q-card style="width: 440px; max-width: 95vw">
        <q-card-section class="text-h6">Exportar reporte</q-card-section>
        <q-card-section class="q-pt-none">
          <div class="q-mb-md">{{ reporteSeleccionado.titulo }}</div>
          <div class="row q-col-gutter-sm">
            <q-input class="col-12 col-sm-6" v-model="fecha" type="date" outlined dense label="Desde" :disable="generandoReporte"/>
            <q-input class="col-12 col-sm-6" v-model="fecha2" type="date" outlined dense label="Hasta" :disable="generandoReporte"/>
          </div>
          <div class="text-caption q-mt-md">{{ reporteSeleccionado.detalle }}</div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup :disable="generandoReporte"/>
          <q-btn color="green" icon="download" label="Descargar Excel" :loading="generandoReporte" @click="descargarReporte"/>
        </q-card-actions>
      </q-card>
    </q-dialog>
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
      dialogReporte: false,
      generandoReporte: false,
      reporteSeleccionado: {},
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
    abrirReporte(tipo, vendedor = null) {
      const titulos = {
        pollo: 'Pollo',
        cerdo: 'Cerdo',
        embutido: 'Embutidos · todos los pedidos',
        embutidoEnviados: 'Embutidos · solo los enviados'
      }
      // Lo que dice el dialogo antes de bajar: que se lleva cada reporte.
      const detalles = {
        embutido: 'Incluye los pedidos que el preventista todavía no envió y puede cambiar. Trae el detalle y los resúmenes por producto y por preventista.',
        embutidoEnviados: 'Solo los pedidos ya enviados: lo que se va a despachar. Trae el detalle y los resúmenes por producto y por preventista.'
      }
      this.reporteSeleccionado = {
        tipo,
        vendedor,
        titulo: vendedor ? 'Pedidos de ' + vendedor.vendedor : titulos[tipo],
        detalle: detalles[tipo] || 'El Excel incluye la zona del cliente.'
      }
      this.dialogReporte = true
    },
    async descargarReporte() {
      if (this.generandoReporte) return
      if (!this.fecha || !this.fecha2 || this.fecha > this.fecha2) {
        this.$q.notify({ message: 'Seleccione un rango de fechas v?lido.', color: 'negative' })
        return
      }
      this.generandoReporte = true
      try {
        const { tipo, vendedor } = this.reporteSeleccionado
        if (tipo === 'vendedor') await this.generarConsulta(vendedor)
        else if (tipo === 'pollo') await this.exportPollo()
        else if (tipo === 'cerdo') await this.exportCerdo()
        else await this.exportEmbutido(tipo === 'embutidoEnviados')
        this.dialogReporte = false
      } catch (error) {
        let mensaje = error.response?.data?.message || 'No se pudo generar el reporte. Intente nuevamente.'
        // Lo que baja como archivo trae el error tambien como blob: hay que leerlo.
        try {
          if (error.response?.data instanceof Blob) {
            mensaje = JSON.parse(await error.response.data.text()).message || mensaje
          }
        } catch (e) { /* el error no vino en JSON */ }
        this.$q.notify({ message: mensaje, color: 'negative' })
      } finally {
        this.generandoReporte = false
      }
    },
    /** Baja el archivo que armo el backend con el nombre que el mismo mando. */
    descargarArchivo(res, nombre) {
      const cabecera = res.headers['content-disposition'] || ''
      const enCabecera = /filename\*?=(?:UTF-8'')?"?([^";]+)/i.exec(cabecera)
      const url = window.URL.createObjectURL(res.data)
      const enlace = document.createElement('a')
      enlace.href = url
      enlace.download = enCabecera ? decodeURIComponent(enCabecera[1]) : nombre
      enlace.click()
      window.URL.revokeObjectURL(url)
    },
    toNumber(value) {
      const parsed = parseFloat(value)
      return Number.isNaN(parsed) ? 0 : parsed
    },
    formatDecimal(value) {
      return this.toNumber(value).toFixed(2).replace('.', ',')
    },
    getEmbutidoContentWithTotal(rows) {
      const content = rows.map(row => ({
        ...row,
        importe: this.toNumber(row.Cant) * this.toNumber(row.precio)
      }))
      const totalGeneral = content.reduce((acc, row) => acc + this.toNumber(row.importe), 0)

      content.push({
        fecha: '',
        Nombre1: '',
        App1: '',
        Apm: '',
        Id: '',
        Nombres: 'TOTAL GENERAL',
        zona: '',
        NroPed: '',
        cod_prod: '',
        Cant: '',
        Producto: '',
        precio: '',
        importe: totalGeneral,
        Observaciones: '',
        estado_ruta: '',
        pago: '',
        fact: '',
        horario: '',
        comentario: '',
        estado: '',
      })

      return content
    },
    exportPollo() {


      return this.$api.post('reportePollo2', {ini: this.fecha, fin: this.fecha2}).then(res => {
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

              {label: "Zona", value: row => row.zona ?? ''},
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
              {label: "sincronizado", value: row => row.estado == 'ENVIADO' ? 'si' : 'no'},
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


    },
    exportCerdo() {


      return this.$api.post('reporteCerdoTodo', {ini: this.fecha, fin: this.fecha2}).then(res => {
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

              {label: "Zona", value: row => row.zona ?? ''},
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
              {label: "sincronizado", value: row => row.estado == 'ENVIADO' ? 'si' : 'no'},
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


    },
    /**
     * El Excel de embutidos lo arma el backend con PhpSpreadsheet.
     *
     * Viene con encabezado, importes con formato, totales y dos hojas de
     * resumen (por producto y por preventista). `soloEnviados` deja fuera lo
     * que el preventista todavia puede cambiar, que es la diferencia entre los
     * dos botones.
     */
    async exportEmbutido(soloEnviados = false) {
      const res = await this.$api.get('reporteEmbutidoExcel', {
        params: { ini: this.fecha, fin: this.fecha2, enviados: soloEnviados ? 1 : 0 },
        responseType: 'blob'
      })
      this.descargarArchivo(res, 'embutidos_' + (soloEnviados ? 'enviados' : 'todos') + '_' + this.fecha + '.xlsx')
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

    async generarConsulta(per) {
      await Promise.all([
        this.getCerdo(per),
        this.getEmbutido(per),
        this.getPollo(per)
      ])

      const embutidoContent = this.getEmbutidoContentWithTotal(this.pedido)
      let datacaja = [
        {
          sheet: "Cerdo",
          columns: [
            {label: "fecha", value: "fecha"},
            {label: "CI/NIT", value: "Id"},
            // {label: "cliente", value: "Nombres"},
            {label: "cliente", value: row => row.bonificacionId == null? row.Nombres : row.bonificacionId == 2728 ? this.cliente2728 : this.cliente3070},

            {label: "Zona", value: row => row.zona ?? ''},
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
            {label: "sincronizado", value: row => row.estado == 'ENVIADO' ? 'si' : 'no'},
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

            {label: "Zona", value: row => row.zona ?? ''},
            {label: "NroPed", value: "NroPed"},
            {label: "cod_prod", value: "cod_prod"},
            {label: "Cant", value: row => row.Cant === '' ? '' : this.formatDecimal(row.Cant)},
            {label: "Producto", value: "Producto"},
            {label: "precio", value: row => row.precio === '' ? '' : this.formatDecimal(row.precio)},
            {label: "importe", value: row => row.importe === '' ? '' : this.formatDecimal(row.importe)},
            {label: "observaciones", value: "Observaciones"},
            {label: "ruta", value: "estado_ruta"},
            {label: "pago", value: row => row.pago == 'CONTADO' ? 'si' : 'no'},
            {label: "fact", value: "fact"},
            {label: "horario", value: "horario"},
            // {label: "comentario", value: "comentario"},
            {label: "comentario", value: row => row.bonificacionId == null? row.comentario : row.Nombres+ ' '+ row.comentario},
            {label: "sincronizado", value: row => row.estado == 'ENVIADO' ? 'si' : 'no'},
          ],
          content: embutidoContent
        },
        {
          sheet: "Pollo",
          columns: [
            {label: "fecha", value: "fecha"},
            {label: "CI/NIT", value: "Id"},
            // {label: "cliente", value: "Nombres"},
            {label: "cliente", value: row => row.bonificacionId == null? row.Nombres : row.bonificacionId == 2728 ? this.cliente2728 : this.cliente3070},

            {label: "Zona", value: row => row.zona ?? ''},
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
            {label: "sincronizado", value: row => row.estado == 'ENVIADO' ? 'si' : 'no'},
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
      return this.$api.post('reporteCerdo', {ini: this.fecha, fin: this.fecha2, codaut: per.CodAut}).then(res => {
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
      return this.$api.post('reporteEmbutido', {ini: this.fecha, fin: this.fecha2, codaut: per.CodAut}).then(res => {
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
      return this.$api.post('reportePollo', {ini: this.fecha, fin: this.fecha2, codaut: per.CodAut}).then(res => {
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
