<template>
  <!-- Media pantalla de mapa y media de pedidos. El alto se fija a mano
       porque el layout de la app arranca con min-height 0: sin esto los dos
       bloques nacen aplastados. -->
  <q-page class="pantalla">
    <div class="mitad-mapa">
      <l-map v-model="zoom" :zoom="zoom" :center="centro" @ready="mapaListo">
        <!-- Tiles de Google: en Oruro tienen las calles y los nombres que el
             caminero conoce. El key fuerza a rehacer la capa al cambiar de
             tipo, que si no se queda con la anterior. -->
        <l-tile-layer
          :key="tipoMapa" :url="urlMapa" :subdomains="['mt0', 'mt1', 'mt2', 'mt3']"
          :max-zoom="20" attribution="Google"
        />
        <!-- Una marca por puerta y no por pedido: el mismo cliente pide dos o
             tres veces el mismo dia y las marcas se tapaban entre ellas. El
             globito dice cuantos pedidos hay en ese punto, y al tocarlo se
             abre la lista de todos. -->
        <l-marker
          v-for="punto in marcadores" :key="punto.clave"
          :lat-lng="punto.latLng"
          @click="abrirPunto(punto)"
        >
          <l-icon>
            <div class="marca" :class="clasePunto(punto.entregas)">
              {{ punto.indice + 1 }}
              <span v-if="punto.entregas.length > 1" class="marca-cuantos">
                {{ punto.entregas.length }}
              </span>
            </div>
          </l-icon>
        </l-marker>
      </l-map>

      <!-- Va a la derecha para no taparse con el zoom de Leaflet. -->
      <div class="panel-alto row items-center no-wrap q-gutter-xs">
        <q-input
          v-model="fecha" type="date" dense outlined bg-color="white"
          class="campo-fecha" @update:model-value="cargar"
        />
        <q-btn round dense unelevated color="white" text-color="primary" icon="refresh"
               :loading="cargando" @click="cargar">
          <q-tooltip>Actualizar</q-tooltip>
        </q-btn>
        <q-btn round dense unelevated color="white" text-color="primary" icon="my_location"
               @click="encuadrar">
          <q-tooltip>Centrar mis clientes</q-tooltip>
        </q-btn>
        <q-btn round dense unelevated color="white" text-color="primary" icon="layers">
          <q-tooltip>Tipo de mapa</q-tooltip>
          <q-menu auto-close>
            <q-list dense style="min-width: 150px">
              <q-item
                v-for="tipo in tiposMapa" :key="tipo.valor"
                clickable :active="tipoMapa === tipo.valor" active-class="bg-blue-1"
                @click="cambiarMapa(tipo.valor)"
              >
                <q-item-section avatar style="min-width: 32px">
                  <q-icon :name="tipo.icono" size="18px"/>
                </q-item-section>
                <q-item-section>{{ tipo.label }}</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
        <q-btn round dense unelevated color="white" text-color="primary" icon="summarize"
               to="/caminero/reporte">
          <q-tooltip>Mi reporte del día</q-tooltip>
        </q-btn>
      </div>

      <!-- Lo que tiene que rendir al volver: es el dato por el que le
           preguntan en caja, asi que va fijo sobre el mapa. -->
      <div class="panel-bajo">
        <q-linear-progress
          size="16px" :value="resumen.porcentaje / 100"
          :color="resumen.pendientes ? 'orange-7' : 'positive'" track-color="blue-grey-2"
        >
          <div class="absolute-full flex flex-center">
            <span class="texto-barra">
              {{ resumen.cobradas }} de {{ resumen.comprobantes }} cobrados · {{ resumen.porcentaje }}%
            </span>
          </div>
        </q-linear-progress>
        <div class="row no-wrap caja">
          <div class="col caja-dato">
            <q-icon name="payments" color="green-8" size="15px"/>
            <span class="caja-monto text-green-9">{{ money(resumen.efectivo) }}</span>
          </div>
          <div class="col caja-dato">
            <q-icon name="qr_code_2" color="indigo-8" size="15px"/>
            <span class="caja-monto text-indigo-9">{{ money(resumen.qr) }}</span>
          </div>
          <div class="col caja-dato">
            <q-icon name="schedule" color="orange-9" size="15px"/>
            <span class="caja-monto text-orange-9">{{ money(resumen.por_cobrar) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="row items-center no-wrap q-px-xs barra-buscar">
      <q-input
        v-model.trim="buscar" dense outlined clearable class="col campo-buscar"
        bg-color="white" placeholder="Cliente o pedido"
      >
        <template v-slot:prepend><q-icon name="search" size="16px"/></template>
      </q-input>
      <q-chip dense square size="sm" class="q-ml-xs q-my-none" color="blue-grey-8" text-color="white">
        {{ filtradas.length }}
      </q-chip>
      <q-chip
        v-if="sinComprobante" dense square size="sm" class="q-ml-xs q-my-none"
        color="amber-3" text-color="amber-10" icon="warning"
      >
        {{ sinComprobante }}
        <q-tooltip>Pedidos de tu camión todavía sin comprobante en caja</q-tooltip>
      </q-chip>
    </div>

    <!-- Tabla y no tarjetas: entran el triple de pedidos por pantalla y el
         numero de cada fila es el mismo que el del mapa. -->
    <div class="mitad-lista">
      <div v-if="cargando" class="flex flex-center q-pa-lg">
        <q-spinner color="primary" size="42px"/>
      </div>

      <div v-else-if="!filtradas.length" class="text-center text-grey-6 q-pa-lg">
        <q-icon name="local_shipping" size="36px"/>
        <div class="q-mt-sm">No hay comprobantes de tu camión para esta fecha</div>
      </div>

      <table v-else class="tabla">
        <thead>
          <tr>
            <th class="col-num">#</th>
            <th>CLIENTE</th>
            <th class="col-monto">MONTO</th>
            <th class="col-acciones"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(entrega, indice) in filtradas" :key="entrega.factura_id"
            :class="claseFila(entrega)" @click="abrirPuntoDe(entrega)"
          >
            <td class="col-num">
              <div class="marca" :class="claseEstado(entrega)">{{ indice + 1 }}</div>
            </td>
            <td>
              <div class="linea-cliente ellipsis">{{ entrega.cliente || entrega.nombre || 'Sin cliente' }}</div>
              <div class="linea-pie ellipsis">
                <q-icon name="place" size="11px"/>
                {{ entrega.direccion || 'Sin dirección' }}
              </div>
            </td>
            <td class="col-monto">
              <div class="monto">{{ money(entrega.total) }}</div>
              <div class="linea-pie">
                <q-icon :name="entrega.cobrada ? 'check_circle' : 'schedule'" size="11px"/>
                {{ entrega.cobrada ? entrega.tipago : '#' + entrega.nro_pedido }}
              </div>
            </td>
            <td class="col-acciones">
              <q-btn dense flat round size="sm" icon="receipt_long" color="blue-grey-7"
                     @click.stop="verComprobante(entrega)">
                <q-tooltip>Ver comprobante</q-tooltip>
              </q-btn>
              <q-btn
                v-if="entrega.telefono" dense flat round size="sm" icon="call" color="green-8"
                :href="'tel:' + entrega.telefono" @click.stop
              />
              <q-btn
                v-if="Number(entrega.latitud)" dense flat round size="sm" icon="navigation" color="blue-8"
                :href="rutaMaps(entrega)" target="_blank" @click.stop
              />
              <q-btn
                v-if="!entrega.cobrada" dense unelevated round size="sm" icon="payments"
                color="positive" class="q-ml-xs" @click.stop="abrirCobro(entrega)"
              >
                <q-tooltip>Cobrar</q-tooltip>
              </q-btn>
              <q-icon v-else name="task_alt" color="positive" size="20px" class="q-ml-xs"/>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Lo que sale al tocar una marca: todos los pedidos de esa puerta en una
         tabla, con el mismo formato que la pantalla de ruta para que el
         caminero no tenga que aprender otra pantalla. -->
    <q-dialog
      v-model="dialogoPunto" full-width
      transition-show="slide-up" transition-hide="slide-down"
    >
      <q-card>
        <q-card-section class="row items-center no-wrap q-gutter-sm q-pb-sm">
          <q-icon name="place" size="md" color="blue-grey-8"/>
          <div class="col">
            <div class="text-subtitle1 text-weight-medium ellipsis">
              {{ punto.cliente || punto.entregas.length + ' pedidos en este punto' }}
            </div>
            <div class="text-caption text-grey-7 ellipsis-2-lines">
              <span v-if="punto.cliente && punto.entregas.length > 1">
                {{ punto.entregas.length }} pedidos ·
              </span>
              {{ punto.direccion || 'Sin dirección' }}
            </div>
          </div>
          <q-btn
            v-if="punto.lat" type="a" target="_blank" no-caps dense
            :href="'https://www.google.com/maps/dir/?api=1&destination=' + punto.lat + ',' + punto.lng"
            icon="navigation" color="blue-8" label="Ir"
          />
        </q-card-section>

        <q-separator/>

        <q-card-section class="q-pa-none">
          <q-table
            dense flat :rows="punto.entregas" :columns="columnasPunto"
            row-key="factura_id" :rows-per-page-options="[0]" hide-pagination
          >
            <template #body="props">
              <q-tr :props="props" :class="claseFila(props.row)">
                <q-td key="nro" :props="props">
                  <div class="marca" :class="claseEstado(props.row)">{{ numero(props.row) }}</div>
                </q-td>

                <q-td key="cliente" :props="props">
                  <div v-if="nombreFila(props.rowIndex)" class="text-weight-medium">
                    {{ nombreFila(props.rowIndex) }}
                  </div>
                  <div class="text-caption text-grey-7">
                    Pedido #{{ props.row.nro_pedido }} ·
                    {{ props.row.tipo_comprobante }} #{{ props.row.factura_id }}
                  </div>
                </q-td>

                <q-td key="total" :props="props">
                  <div class="text-weight-bolder">Bs {{ money(props.row.total) }}</div>
                  <div class="text-caption" :class="props.row.cobrada ? 'text-green-9' : 'text-grey-7'">
                    {{ props.row.cobrada ? props.row.tipago : (props.row.entrega_estado || 'Pendiente') }}
                  </div>
                </q-td>

                <q-td key="opcion" :props="props" class="text-no-wrap">
                  <q-btn
                    dense flat round size="sm" icon="receipt_long" color="blue-grey-7"
                    @click="verComprobante(props.row)"
                  >
                    <q-tooltip>Ver comprobante</q-tooltip>
                  </q-btn>
                  <q-btn
                    dense flat round size="sm" icon="feedback" color="primary"
                    @click="abrirEncuesta(props.row)"
                  >
                    <q-tooltip>Encuesta</q-tooltip>
                  </q-btn>
                  <q-btn
                    v-if="props.row.telefono" dense flat round size="sm" icon="call"
                    color="green-8" :href="'tel:' + props.row.telefono"
                  />
                  <q-btn
                    v-if="!props.row.cobrada" dense unelevated round size="sm"
                    icon="payments" color="positive" class="q-ml-xs"
                    @click="abrirCobro(props.row)"
                  >
                    <q-tooltip>Cobrar</q-tooltip>
                  </q-btn>
                  <q-icon v-else name="task_alt" color="positive" size="20px" class="q-ml-xs"/>
                </q-td>
              </q-tr>
            </template>
          </q-table>
        </q-card-section>

        <q-separator/>

        <q-card-actions class="q-px-md">
          <div class="text-caption text-grey-7">
            Total del punto: <b>Bs {{ money(punto.total) }}</b>
            <span v-if="punto.porCobrar"> · faltan cobrar {{ punto.porCobrar }}</span>
          </div>
          <q-space/>
          <q-btn flat no-caps color="grey-8" label="Cerrar" v-close-popup/>
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogo" @hide="limpiarCobro">
      <q-card style="min-width: 300px">
        <q-card-section class="q-pb-none">
          <div class="text-subtitle1 text-weight-bold">{{ cobro.cliente }}</div>
          <div class="text-caption text-grey-7">
            Pedido #{{ cobro.nro_pedido }} · {{ cobro.tipo_comprobante }} #{{ cobro.factura_id }}
          </div>
          <div class="text-h5 text-weight-bolder text-blue-grey-10 q-mt-xs">
            Bs {{ money(cobro.total) }}
          </div>
        </q-card-section>

        <q-card-section class="q-pt-sm">
          <q-btn-toggle
            v-model="forma" spread no-caps unelevated dense
            toggle-color="primary" color="grey-3" text-color="grey-9"
            :options="[
              { label: 'Efectivo', value: 'CONTADO', icon: 'payments' },
              { label: 'QR', value: 'PAGO QR', icon: 'qr_code_2' },
              { label: 'Mixto', value: 'MIXTO', icon: 'call_split' },
              { label: 'Crédito', value: 'CRÉDITO', icon: 'schedule' }
            ]"
          />

          <!-- En el mixto solo se escribe el efectivo: el QR es el resto, que
               es como lo cuenta el caminero en la puerta. -->
          <div v-if="forma === 'MIXTO'" class="row q-col-gutter-xs q-mt-sm">
            <div class="col-6">
              <q-input
                v-model.number="efectivo" type="number" dense outlined label="Efectivo"
                inputmode="decimal" @update:model-value="ajustarQr"
              />
            </div>
            <div class="col-6">
              <q-input v-model.number="qr" type="number" dense outlined label="QR" inputmode="decimal"/>
            </div>
            <div class="col-12">
              <div class="text-caption" :class="cuadra ? 'text-green-9' : 'text-red-9'">
                Suma Bs {{ money(efectivo + qr) }} de Bs {{ money(cobro.total) }}
              </div>
            </div>
          </div>

          <q-input
            v-model.trim="motivo" dense outlined class="q-mt-sm" maxlength="90"
            label="Motivo (solo si no se entrega)"
          />
        </q-card-section>

        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat no-caps color="grey-8" label="Cerrar" v-close-popup/>
          <q-btn
            flat no-caps color="negative" label="No entregado"
            :loading="guardando" @click="registrar('NO ENTREGADO')"
          />
          <q-btn
            unelevated no-caps color="positive" label="Cobrar" icon="check"
            :loading="guardando" :disable="forma === 'MIXTO' && !cuadra"
            @click="registrar('ENTREGADO')"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogoDetalle">
      <q-card style="min-width: 320px">
        <q-card-section class="q-pb-xs">
          <div class="text-subtitle1 text-weight-bold">{{ verEntrega.cliente }}</div>
          <div class="text-caption text-grey-7">
            {{ verEntrega.tipo_comprobante }} #{{ verEntrega.factura_id }} ·
            Pedido #{{ verEntrega.nro_pedido }} · NIT {{ verEntrega.nit || '—' }}
          </div>
        </q-card-section>
        <q-separator/>
        <div v-if="detalles[verEntrega.factura_id] === 'cargando'" class="q-pa-md text-center">
          <q-spinner color="primary" size="28px"/>
        </div>
        <q-list v-else dense separator style="max-height: 50vh; overflow-y: auto">
          <q-item v-for="item in (detalles[verEntrega.factura_id] || [])" :key="item.id" class="q-px-sm">
            <q-item-section>
              <q-item-label lines="2">{{ item.nombre }}</q-item-label>
              <q-item-label caption>{{ item.cod_prod }}</q-item-label>
            </q-item-section>
            <q-item-section side class="text-right">
              <q-item-label>{{ cantidad(item.cantidad) }} × Bs {{ money(item.precio) }}</q-item-label>
              <q-item-label caption class="text-weight-bold">Bs {{ money(item.subtotal) }}</q-item-label>
            </q-item-section>
          </q-item>
        </q-list>
        <q-separator/>
        <q-card-actions class="q-pa-sm">
          <div class="text-h6 text-weight-bolder text-blue-grey-10">Bs {{ money(verEntrega.total) }}</div>
          <q-space/>
          <q-btn flat no-caps color="grey-8" label="Cerrar" v-close-popup/>
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- El cliente escanea y responde desde su celular; el QR lo arma un
         servicio, asi no hay que sumar una libreria al bundle. -->
    <q-dialog v-model="dialogQR" transition-show="scale" transition-hide="scale">
      <q-card style="min-width: 300px; max-width: 360px">
        <q-card-section class="row items-center no-wrap q-pb-none">
          <q-icon name="reviews" size="26px" class="q-mr-sm" color="primary"/>
          <div class="col">
            <div class="text-subtitle1 text-weight-bold">Encuesta de satisfacción</div>
            <div class="text-caption text-grey-7 ellipsis">{{ qrCliente || 'Cliente' }}</div>
          </div>
          <q-btn round flat dense icon="close" v-close-popup/>
        </q-card-section>

        <q-card-section class="text-center q-pb-sm">
          <q-img :src="qrSrc" ratio="1" spinner-color="primary" style="width: 220px"/>
          <q-input
            v-model="qrLink" dense readonly outlined class="q-mt-sm"
            @focus="$event.target.select()"
          >
            <template v-slot:append>
              <q-btn
                round dense flat icon="content_copy" color="primary"
                :disable="!qrLink" @click="copiarEncuesta"
              >
                <q-tooltip>Copiar enlace</q-tooltip>
              </q-btn>
            </template>
          </q-input>
        </q-card-section>

        <q-card-actions class="column q-px-md q-pb-md q-gutter-y-sm">
          <q-btn
            v-if="qrWhatsapp" :href="qrWhatsapp" target="_blank" class="full-width"
            color="green-7" icon="chat" label="Enviar por WhatsApp" no-caps unelevated
          />
          <q-btn
            :href="qrLink" target="_blank" class="full-width" color="primary"
            icon="open_in_new" label="Abrir encuesta" no-caps outline
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { markRaw } from 'vue'
import { date } from 'quasar'
import { LMap, LIcon, LTileLayer, LMarker } from '@vue-leaflet/vue-leaflet'
import 'leaflet/dist/leaflet.css'

export default {
  name: 'MisEntregas',
  components: { LMap, LIcon, LTileLayer, LMarker },
  data () {
    return {
      fecha: this.$route.query.fecha || date.formatDate(new Date(), 'YYYY-MM-DD'),
      buscar: '',
      cargando: false,
      guardando: false,
      placa: '',
      sinComprobante: 0,
      entregas: [],
      resumen: { comprobantes: 0, cobradas: 0, pendientes: 0, porcentaje: 0, efectivo: 0, qr: 0, por_cobrar: 0 },
      detalles: {},
      dialogo: false,
      dialogoDetalle: false,
      // El punto abierto se guarda por id y no por copia: asi al recargar
      // despues de cobrar la lista del dialogo se actualiza sola.
      dialogoPunto: false,
      puntoIds: [],
      columnasPunto: [
        { name: 'nro', label: '#', field: 'factura_id', align: 'center' },
        { name: 'cliente', label: 'PEDIDO', field: 'cliente', align: 'left' },
        { name: 'total', label: 'MONTO', field: 'total', align: 'right' },
        { name: 'opcion', label: '', field: 'factura_id', align: 'right' }
      ],
      // Encuesta de satisfaccion, igual que en la pantalla de ruta.
      dialogQR: false,
      qrLink: '',
      qrSrc: '',
      qrCliente: '',
      qrWhatsapp: '',
      verEntrega: {},
      cobro: {},
      forma: 'CONTADO',
      efectivo: 0,
      qr: 0,
      motivo: '',
      zoom: 13,
      // lyrs de Google: r calles, s satelite, y hibrido, p relieve.
      tipoMapa: 'r',
      tiposMapa: [
        { label: 'Mapa', valor: 'r', icono: 'map' },
        { label: 'Satélite', valor: 's', icono: 'satellite' },
        { label: 'Híbrido', valor: 'y', icono: 'layers' },
        { label: 'Relieve', valor: 'p', icono: 'terrain' }
      ],
      centro: [-17.9833, -67.15],
      posicion: null,
      mapa: null
    }
  },
  created () {
    // El tipo de mapa es del caminero, no del dia: se recuerda entre visitas.
    try {
      const guardado = localStorage.getItem('caminero-tipo-mapa')
      if (guardado && this.tiposMapa.some(tipo => tipo.valor === guardado)) {
        this.tipoMapa = guardado
      }
    } catch (e) {}
    this.cargar()
    this.ubicar()
  },
  computed: {
    urlMapa () {
      return 'https://{s}.google.com/vt/lyrs=' + this.tipoMapa + '&x={x}&y={y}&z={z}'
    },
    filtradas () {
      const texto = this.buscar.toLowerCase()
      if (!texto) return this.entregas
      return this.entregas.filter(entrega =>
        String(entrega.cliente || '').toLowerCase().includes(texto) ||
        String(entrega.nro_pedido).includes(texto)
      )
    },
    cuadra () {
      return Math.abs((Number(this.efectivo) + Number(this.qr)) - Number(this.cobro.total || 0)) < 0.01
    },
    /**
     * Las marcas del mapa: una por puerta, no una por pedido.
     *
     * El mismo cliente pide dos y tres veces en el dia, y la tienda de al lado
     * esta cargada en la misma esquina: todo eso llegaba con la misma
     * coordenada y la ultima marca tapaba a las demas. Ahora esas entregas
     * comparten una sola marca y se abren juntas al tocarla.
     *
     * Se agrupa por metros en el terreno y no por pixeles en pantalla para que
     * la marca sea siempre la misma aunque el caminero acerque o aleje: si la
     * agrupacion cambiara con el zoom, Leaflet tendria que rehacer las marcas
     * a cada rato.
     */
    marcadores () {
      const grupos = []

      this.filtradas.forEach((entrega, indice) => {
        const lat = Number(entrega.latitud)
        const lng = Number(entrega.longitud)
        // Sin coordenada no hay nada que poner en el mapa.
        if (!lat || !lng) return

        // Quince metros: la misma puerta aunque el GPS del vendedor la haya
        // marcado dos veces desde la vereda de enfrente.
        const junto = grupos.find(grupo => this.metros(grupo, { lat, lng }) < 15)

        if (junto) junto.entregas.push(entrega)
        else grupos.push({ lat, lng, indice, entregas: [entrega] })
      })

      return grupos.map(grupo => ({
        // La clave no depende del zoom, asi que la marca no se rehace sola.
        clave: grupo.entregas.map(entrega => entrega.factura_id).join('-'),
        latLng: [grupo.lat, grupo.lng],
        indice: grupo.indice,
        entregas: grupo.entregas
      }))
    },
    /** Las entregas del punto abierto, sacadas siempre de la lista viva. */
    punto () {
      const entregas = this.filtradas.filter(
        entrega => this.puntoIds.includes(entrega.factura_id)
      )
      const primera = entregas[0] || {}
      // Casi siempre la puerta es de un solo cliente que pidio varias veces:
      // ahi el nombre va una vez en el encabezado y no en cada fila.
      const nombres = [...new Set(entregas.map(this.clienteDe))]

      return {
        entregas,
        cliente: nombres.length === 1 ? nombres[0] : '',
        direccion: primera.direccion || '',
        lat: Number(primera.latitud) || 0,
        lng: Number(primera.longitud) || 0,
        total: entregas.reduce((suma, entrega) => suma + Number(entrega.total || 0), 0),
        porCobrar: entregas.filter(entrega => !entrega.cobrada).length
      }
    },
    encuestaBase () {
      // La encuesta la renderiza el backend, no el SPA: se deriva del API base
      // (http://localhost:8000/api/ -> http://localhost:8000), igual que en Ruta.
      return String(process.env.API || '').replace(/\/+$/, '').replace(/\/api$/, '')
    }
  },
  methods: {
    money (valor) {
      return Number(valor || 0).toFixed(2)
    },
    cantidad (valor) {
      return Number(valor || 0).toLocaleString('es-BO', { maximumFractionDigits: 3 })
    },
    claseFila (entrega) {
      if (entrega.cobrada) return 'fila-cobrada'
      return entrega.entrega_estado ? 'fila-rechazada' : ''
    },
    claseEstado (entrega) {
      if (entrega.cobrada) return 'marca-verde'
      return entrega.entrega_estado ? 'marca-roja' : 'marca-naranja'
    },
    // La marca de un punto con varios pedidos: verde solo cuando ya no queda
    // nada por cobrar ahi, para que el caminero no se vaya de la puerta antes.
    clasePunto (entregas) {
      if (entregas.every(entrega => entrega.cobrada)) return 'marca-verde'
      if (entregas.some(entrega => !entrega.cobrada && !entrega.entrega_estado)) {
        return 'marca-naranja'
      }
      return 'marca-roja'
    },
    clienteDe (entrega) {
      return entrega.cliente || entrega.nombre || 'Sin cliente'
    },
    /**
     * El nombre que va en una fila del punto, o vacio si no hace falta.
     *
     * Si la puerta es de un solo cliente el nombre ya esta arriba; si hay
     * varios, se escribe una vez y las filas que siguen del mismo quedan
     * limpias, que era lo que se leia repetido.
     */
    nombreFila (indice) {
      if (this.punto.cliente) return ''

      const nombre = this.clienteDe(this.punto.entregas[indice])
      if (indice === 0) return nombre

      return this.clienteDe(this.punto.entregas[indice - 1]) === nombre ? '' : nombre
    },
    /** El numero que le toca en la lista de abajo, que es el que se ve. */
    numero (entrega) {
      return this.filtradas.findIndex(
        fila => fila.factura_id === entrega.factura_id
      ) + 1
    },
    abrirPunto (punto) {
      this.puntoIds = punto.entregas.map(entrega => entrega.factura_id)
      this.dialogoPunto = true
    },
    // Desde la lista se abre el mismo punto que desde el mapa: si el cliente
    // tiene otro pedido en la misma puerta aparecen los dos juntos. Las
    // entregas sin coordenada no estan en el mapa y se abren solas.
    abrirPuntoDe (entrega) {
      const punto = this.marcadores.find(marca => marca.entregas.some(
        fila => fila.factura_id === entrega.factura_id
      ))

      this.abrirPunto(punto || { entregas: [entrega] })
    },
    cambiarMapa (valor) {
      this.tipoMapa = valor
      try { localStorage.setItem('caminero-tipo-mapa', valor) } catch (e) {}
      // La capa se rehace y el mapa pierde el encuadre: se vuelve a poner.
      this.$nextTick(this.encuadrar)
    },
    rutaMaps (entrega) {
      return 'https://www.google.com/maps/dir/?api=1&destination=' +
        entrega.latitud + ',' + entrega.longitud
    },
    /**
     * La encuesta de satisfaccion del cliente de esa entrega.
     *
     * Es la misma que usa la pantalla de ruta: la pagina la arma el backend,
     * asi que el cliente puede recargarla sin perder lo que ya respondio.
     */
    abrirEncuesta (entrega) {
      if (!entrega.cliente_id) {
        this.$q.notify({
          type: 'warning', position: 'top',
          message: 'Esta venta no tiene un cliente registrado para encuestar'
        })
        return
      }

      const userId = this.$store.getters['login/user']?.CodAut
      if (!userId) {
        this.$q.notify({
          type: 'negative', position: 'top',
          message: 'No se pudo identificar al caminero'
        })
        return
      }

      this.qrLink = this.encuestaBase + '/encuesta/' +
        encodeURIComponent(entrega.cliente_id) + '/' + encodeURIComponent(userId)
      this.qrCliente = entrega.cliente || entrega.nombre || ''
      this.qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=8&data=' +
        encodeURIComponent(this.qrLink)
      this.qrWhatsapp = this.linkWhatsapp(entrega.telefono, this.qrLink)
      this.dialogQR = true
    },
    // Bolivia: los numeros de ocho digitos van con el 591 adelante.
    linkWhatsapp (telefono, link) {
      const numero = String(telefono || '').replace(/\D/g, '')
      if (numero.length < 7) return ''

      const completo = numero.length === 8 ? '591' + numero : numero
      const texto = 'Hola, gracias por tu compra en Distribuidora Sofía 🐔\n' +
        '¿Nos ayudas con una encuesta rápida? ' + link

      return 'https://wa.me/' + completo + '?text=' + encodeURIComponent(texto)
    },
    copiarEncuesta () {
      if (!this.qrLink) return

      navigator.clipboard.writeText(this.qrLink)
        .then(() => this.$q.notify({ type: 'positive', position: 'top', message: 'Link copiado' }))
        .catch(() => this.$q.notify({ type: 'negative', position: 'top', message: 'No se pudo copiar' }))
    },
    /** Distancia aproximada en metros entre dos coordenadas cercanas. */
    metros (a, b) {
      const grado = 111320
      const x = (a.lng - b.lng) * grado * Math.cos((a.lat * Math.PI) / 180)
      const y = (a.lat - b.lat) * grado

      return Math.hypot(x, y)
    },
    mapaListo (mapa) {
      this.mapa = markRaw(mapa)
      // El mapa se dibuja antes de que el navegador reparta las dos mitades,
      // asi que sin esto queda calculado a un alto que ya no es el suyo.
      setTimeout(() => {
        mapa.invalidateSize()
        this.encuadrar()
      }, 200)
    },
    // Todos los clientes del dia entran en pantalla: el caminero no tiene que
    // buscar sus puntos moviendo el mapa.
    encuadrar () {
      const puntos = this.filtradas
        .filter(entrega => Number(entrega.latitud) && Number(entrega.longitud))
        .map(entrega => [Number(entrega.latitud), Number(entrega.longitud)])
      if (!this.mapa || !puntos.length) return
      this.mapa.fitBounds(puntos, { padding: [40, 40], maxZoom: 16 })
    },
    ubicar () {
      if (!navigator.geolocation) return
      navigator.geolocation.getCurrentPosition(
        posicion => { this.posicion = posicion.coords },
        () => { this.posicion = null },
        { enableHighAccuracy: true, timeout: 8000 }
      )
    },
    cargar () {
      this.cargando = true
      this.$api.get('caminero/entregas', { params: { fecha: this.fecha } })
        .then(res => {
          this.placa = res.data.placa
          this.entregas = res.data.entregas
          this.resumen = res.data.resumen
          this.sinComprobante = res.data.sin_comprobante
          this.$nextTick(this.encuadrar)
        })
        .catch(err => {
          this.$q.notify({
            type: 'negative',
            position: 'top',
            message: err.response?.data?.message || 'No se pudieron cargar tus entregas'
          })
        })
        .finally(() => { this.cargando = false })
    },
    // El detalle se pide una sola vez por comprobante y queda cacheado: en la
    // calle la señal es mala y no conviene repetir la consulta.
    verComprobante (entrega) {
      this.verEntrega = entrega
      this.dialogoDetalle = true
      if (this.detalles[entrega.factura_id]) return
      this.detalles = { ...this.detalles, [entrega.factura_id]: 'cargando' }
      this.$api.get('facturacion/' + entrega.factura_id)
        .then(res => {
          this.detalles = { ...this.detalles, [entrega.factura_id]: res.data.detalles || [] }
        })
        .catch(() => {
          this.detalles = { ...this.detalles, [entrega.factura_id]: [] }
        })
    },
    abrirCobro (entrega) {
      if (entrega.cobrada) return
      this.cobro = entrega
      this.forma = 'CONTADO'
      this.efectivo = Number(entrega.total)
      this.qr = 0
      this.motivo = ''
      this.dialogo = true
    },
    limpiarCobro () {
      this.cobro = {}
    },
    ajustarQr () {
      const resto = Number(this.cobro.total || 0) - Number(this.efectivo || 0)
      this.qr = Math.round(Math.max(resto, 0) * 100) / 100
    },
    registrar (estado) {
      if (estado !== 'ENTREGADO' && !this.motivo) {
        this.$q.notify({ type: 'warning', position: 'top', message: 'Escribe el motivo de la no entrega' })
        return
      }

      this.guardando = true
      this.$api.post('caminero/cobrar', {
        factura_id: this.cobro.factura_id,
        estado,
        tipago: estado === 'ENTREGADO' ? this.forma : null,
        monto_efectivo: this.forma === 'MIXTO' ? this.efectivo : null,
        monto_qr: this.forma === 'MIXTO' ? this.qr : null,
        observacion: this.motivo || null,
        lat: this.posicion?.latitude || null,
        lng: this.posicion?.longitude || null
      }).then(() => {
        this.$q.notify({ type: 'positive', position: 'top', message: 'Entrega registrada' })
        this.dialogo = false
        this.cargar()
      }).catch(err => {
        this.$q.notify({
          type: 'negative',
          position: 'top',
          message: err.response?.data?.message || 'No se pudo registrar la entrega'
        })
      }).finally(() => { this.guardando = false })
    }
  }
}
</script>

<style scoped>
/* 50px es el alto del toolbar de la app. */
.pantalla {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 50px);
  overflow: hidden;
}
.mitad-mapa {
  position: relative;
  flex: 0 0 50%;
}
.mitad-lista {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  background: #fff;
}

/* Los paneles flotan sobre el mapa; 500 los deja encima de las capas de
   Leaflet y debajo de sus controles. */
.panel-alto,
.panel-bajo {
  position: absolute;
  z-index: 500;
}
.panel-alto {
  top: 6px;
  right: 6px;
}
.panel-bajo {
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 0 -1px 4px rgba(0, 0, 0, 0.25);
}
.campo-fecha {
  width: 140px;
}
.texto-barra {
  font-size: 10px;
  font-weight: 700;
  color: #263238;
}
.caja {
  padding: 2px 0;
}
.caja-dato {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 3px;
}
.caja-monto {
  font-size: 13px;
  font-weight: 700;
}

.barra-buscar {
  background: #eceff1;
  border-top: 1px solid #cfd8dc;
  border-bottom: 1px solid #cfd8dc;
  padding: 4px 0;
}
.campo-buscar :deep(.q-field--dense .q-field__control),
.campo-buscar :deep(.q-field--dense .q-field__marginal) {
  height: 30px;
  min-height: 30px;
}
.campo-buscar :deep(.q-field__native) {
  font-size: 12px;
  padding: 0;
}

/* El numero de la fila es el mismo que el del marcador en el mapa. */
.marca {
  position: relative;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  line-height: 22px;
  text-align: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
}
/* Cuantos pedidos hay en esa puerta. Va en la esquina y no adentro para que
   no se confunda con el numero de la fila. */
.marca-cuantos {
  position: absolute;
  top: -6px;
  right: -8px;
  min-width: 15px;
  height: 15px;
  padding: 0 3px;
  border-radius: 8px;
  background: #263238;
  color: #fff;
  font-size: 9px;
  line-height: 15px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
}
.marca-verde {
  background: #2e7d32;
}
.marca-naranja {
  background: #ef6c00;
}
.marca-roja {
  background: #c62828;
}

.tabla {
  width: 100%;
  border-collapse: collapse;
}
.tabla th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #cfd8dc;
  color: #37474f;
  font-size: 9px;
  letter-spacing: 0.5px;
  text-align: left;
  padding: 2px 6px;
}
.tabla td {
  padding: 3px 6px;
  border-bottom: 1px solid #eceff1;
  max-width: 0;
}
.tabla tbody tr {
  cursor: pointer;
}
.tabla tbody tr:active {
  background: #e3f2fd;
}
.fila-cobrada {
  background: #e8f5e9;
}
.fila-rechazada {
  background: #ffebee;
}
.linea-cliente {
  font-size: 13px;
  font-weight: 600;
  color: #263238;
}
.linea-pie {
  font-size: 10px;
  color: #78909c;
}
.monto {
  font-size: 14px;
  font-weight: 800;
  color: #263238;
  text-align: right;
}
.col-num {
  width: 30px;
}
.col-monto {
  width: 86px;
  text-align: right;
}
.col-acciones {
  width: 150px;
  white-space: nowrap;
  text-align: right;
}
</style>
