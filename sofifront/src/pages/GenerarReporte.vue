<template>
  <q-page class="q-pa-xs">
    <div class="row">
      <div class="col-12">
        <q-form @submit="consultar">
          <div class="row q-col-gutter-sm items-center">
            <div class="col-6 col-sm-3">
              <q-input type="date" dense outlined label="Desde" v-model="fecha"/>
            </div>
            <div class="col-6 col-sm-3">
              <q-input type="date" dense outlined label="Hasta" v-model="fecha2"/>
            </div>
            <div class="col-12 col-sm-3">
              <q-btn class="full-width" color="primary" icon="search" label="Consultar" type="submit" no-caps unelevated/>
            </div>
          </div>
        </q-form>
        <!-- Cada tipo sale en dos: todo lo que cargaron los preventistas
             (incluido lo que todavia pueden cambiar) y solo lo enviado, que
             es lo que de verdad se va a despachar. -->
        <div class="row q-col-gutter-sm q-mt-xs q-mb-sm">
          <div v-for="t in tiposReporte" :key="t.tipo" class="col-12 col-sm-4">
            <q-card flat bordered class="tarjeta-reporte" :style="{ borderTopColor: t.borde }">
              <q-card-section class="row items-center no-wrap q-pb-xs">
                <q-avatar :color="t.color" text-color="white" :icon="t.icono" size="38px"/>
                <div class="q-ml-sm">
                  <div class="text-subtitle1 text-weight-bold">{{ t.nombre }}</div>
                  <div class="text-caption text-grey-7">Excel con detalle y resúmenes</div>
                </div>
              </q-card-section>
              <q-card-actions class="q-pt-none">
                <q-btn class="col" outline :color="t.color" icon="list_alt" label="Todos" no-caps
                       @click="abrirReporte(t.tipo)">
                  <q-tooltip>Enviados y sin enviar</q-tooltip>
                </q-btn>
                <q-btn class="col" unelevated :color="t.color" icon="fact_check" label="Enviados" no-caps
                       @click="abrirReporte(t.tipo + 'Enviados')">
                  <q-tooltip>Solo lo que se va a despachar</q-tooltip>
                </q-btn>
              </q-card-actions>
            </q-card>
          </div>
        </div>
      </div>
      <div class="col-12">
        <q-table dense flat bordered title="Pedidos por preventista" :columns="columspedido" :rows="personales"
                 :filter="filter" :rows-per-page-options="[20, 50, 0]">
          <template v-slot:top-right>
            <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
              <template v-slot:append>
                <q-icon name="search"/>
              </template>
            </q-input>
          </template>
          <template v-slot:body-cell-excel="props">
            <q-td :props="props">
              <q-btn color="green-7" icon="download" size="sm" label="Excel" no-caps unelevated dense class="q-px-sm"
                     @click="abrirReporte('vendedor', props.row)"/>
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
          <q-btn color="green-7" icon="download" label="Descargar Excel" no-caps unelevated :loading="generandoReporte" @click="descargarReporte"/>
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
      // Endpoint del backend de cada tipo; los dos botones solo cambian enviados=0/1.
      tiposReporte: [
        { tipo: 'embutido', nombre: 'Embutidos', icono: 'kebab_dining', color: 'deep-orange-8', borde: '#e64a19', url: 'reporteEmbutidoExcel' },
        { tipo: 'pollo', nombre: 'Pollo', icono: 'egg', color: 'amber-9', borde: '#ff8f00', url: 'reportePolloExcel' },
        { tipo: 'cerdo', nombre: 'Cerdo', icono: 'savings', color: 'pink-6', borde: '#d81b60', url: 'reporteCerdoExcel' }
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
    // tipo: 'vendedor', o el tipo de tiposReporte con 'Enviados' al final para solo lo enviado.
    abrirReporte(tipo, vendedor = null) {
      if (vendedor) {
        this.reporteSeleccionado = {
          tipo,
          vendedor,
          titulo: 'Pedidos de ' + vendedor.vendedor,
          detalle: 'El Excel incluye la zona del cliente.'
        }
      } else {
        const soloEnviados = tipo.endsWith('Enviados')
        const base = this.tiposReporte.find(t => t.tipo === tipo.replace('Enviados', ''))
        // Lo que dice el dialogo antes de bajar: que se lleva cada reporte.
        this.reporteSeleccionado = {
          tipo,
          base,
          soloEnviados,
          titulo: base.nombre + (soloEnviados ? ' · solo los enviados' : ' · todos los pedidos'),
          detalle: (soloEnviados
            ? 'Solo los pedidos ya enviados: lo que se va a despachar.'
            : 'Incluye los pedidos que el preventista todavía no envió y puede cambiar.') +
            ' Trae el detalle y los resúmenes por producto y por preventista.'
        }
      }
      this.dialogReporte = true
    },
    async descargarReporte() {
      if (this.generandoReporte) return
      if (!this.fecha || !this.fecha2 || this.fecha > this.fecha2) {
        this.$q.notify({ message: 'Seleccione un rango de fechas válido.', color: 'negative' })
        return
      }
      this.generandoReporte = true
      try {
        const { tipo, vendedor, base, soloEnviados } = this.reporteSeleccionado
        if (tipo === 'vendedor') await this.generarConsulta(vendedor)
        else await this.exportReporte(base, soloEnviados)
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
    /**
     * El Excel de embutidos, pollo o cerdo lo arma el backend con PhpSpreadsheet.
     *
     * Viene con encabezado, importes con formato, totales y dos hojas de
     * resumen (por producto y por preventista). `soloEnviados` deja fuera lo
     * que el preventista todavia puede cambiar, que es la diferencia entre los
     * dos botones.
     */
    async exportReporte(base, soloEnviados = false) {
      const res = await this.$api.get(base.url, {
        params: { ini: this.fecha, fin: this.fecha2, enviados: soloEnviados ? 1 : 0 },
        responseType: 'blob'
      })
      this.descargarArchivo(res, base.tipo + '_' + (soloEnviados ? 'enviados' : 'todos') + '_' + this.fecha + '.xlsx')
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
            // Pedido nuevo (/visita): una fila por producto con su rango (kg).
            {label: "producto", value: row => row.producto_nombre ?? ''},
            {label: "cantidad", value: row => row.Cant ?? ''},
            {label: "precio", value: row => row.cod_prod ? row.precio : ''},
            {label: "rango kg", value: row => row.rango_kg == null ? '' : parseFloat(row.rango_kg)},
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
/* Cada tipo con su color arriba, para ubicarlo de un vistazo. */
.tarjeta-reporte {
  border-top-width: 4px;
  border-top-style: solid;
  border-radius: 8px;
}
</style>
