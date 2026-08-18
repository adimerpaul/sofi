<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md">
        <div class="text-h6 text-weight-bold">Impuestos</div>
        <div class="text-caption text-grey-7">
          Datos de producción del emisor ante el SIAT y obtención de los códigos CUIS y CUFD
        </div>
      </div>
      <div class="col-auto">
        <q-btn flat color="grey-7" icon="refresh" no-caps label="Actualizar" :loading="cargando" @click="cargarTodo"/>
      </div>
    </div>

    <q-banner v-if="!estado.soap" dense rounded class="bg-red-1 text-red-9 q-mb-md">
      <template v-slot:avatar><q-icon name="error"/></template>
      El servidor no tiene habilitada la extensión SOAP de PHP, así que no se puede hablar con el SIAT.
    </q-banner>

    <q-banner v-else-if="estado.token_vencido" dense rounded class="bg-red-1 text-red-9 q-mb-md">
      <template v-slot:avatar><q-icon name="key_off"/></template>
      El token delegado venció el {{ fecha(estado.configuracion.token_expira) }}. Hay que sacar uno nuevo en la
      oficina virtual de Impuestos y pegarlo abajo.
    </q-banner>

    <q-banner v-else-if="estado.faltantes.length" dense rounded class="bg-orange-1 text-orange-9 q-mb-md">
      <template v-slot:avatar><q-icon name="warning"/></template>
      Falta {{ estado.faltantes.join(', ') }} para poder pedir códigos al SIAT.
    </q-banner>

    <!-- Estado de un vistazo: es lo primero que se mira cada mañana. -->
    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12 col-md-3">
        <q-card flat bordered>
          <q-card-section class="q-pb-none">
            <div class="text-caption text-grey-7">Ambiente</div>
            <div class="text-subtitle1 text-weight-bold">
              <q-badge color="red-7" text-color="white">PRODUCCIÓN</q-badge>
            </div>
          </q-card-section>
          <q-card-section class="text-caption text-grey-7" style="word-break: break-all">
            {{ estado.modalidad }}<br>
            Servicios: {{ estado.url_base }}<br>
            QR: {{ estado.url_qr }}
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card flat bordered>
          <q-card-section class="q-pb-none">
            <div class="text-caption text-grey-7">Token delegado</div>
            <div class="text-subtitle1 text-weight-bold" :class="claseToken">{{ textoToken }}</div>
          </q-card-section>
          <q-card-section class="text-caption text-grey-7">
            <template v-if="estado.configuracion.token_expira">
              Vence {{ fecha(estado.configuracion.token_expira) }}
              <div :class="claseRestante(estado.configuracion.token_expira, 30 * 24)" class="text-weight-medium">
                {{ restante(estado.configuracion.token_expira) }}
              </div>
            </template>
            <template v-else>Sin fecha de vencimiento registrada</template>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card flat bordered>
          <q-card-section class="q-pb-none">
            <div class="text-caption text-grey-7">CUIS vigente</div>
            <div class="text-subtitle1 text-weight-bold">
              <template v-if="estado.cuis">{{ estado.cuis.codigo }}</template>
              <span v-else class="text-red-7">Sin CUIS</span>
            </div>
          </q-card-section>
          <q-card-section class="text-caption text-grey-7">
            <template v-if="estado.cuis">
              Hasta {{ fecha(estado.cuis.fecha_vigencia) }}
              <div :class="claseRestante(estado.cuis.fecha_vigencia, 30 * 24)" class="text-weight-medium">
                {{ restante(estado.cuis.fecha_vigencia) }}
              </div>
            </template>
            <template v-else>Genéralo abajo; sin CUIS no hay CUFD.</template>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-3">
        <q-card flat bordered>
          <q-card-section class="q-pb-none">
            <div class="text-caption text-grey-7">CUFD de hoy</div>
            <div class="text-subtitle1 text-weight-bold">
              <template v-if="estado.cufd">{{ estado.cufd.codigo_control }}</template>
              <span v-else class="text-red-7">Sin CUFD</span>
            </div>
          </q-card-section>
          <q-card-section class="text-caption text-grey-7">
            <template v-if="estado.cufd">
              Vence {{ fecha(estado.cufd.fecha_vigencia) }}
              <!-- Umbral bajo: el CUFD dura horas, no dias. -->
              <div :class="claseRestante(estado.cufd.fecha_vigencia, 2)" class="text-weight-medium">
                {{ restante(estado.cufd.fecha_vigencia) }}
              </div>
            </template>
            <template v-else>Caduca cada 24 horas: hay que pedirlo cada día.</template>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <q-card flat bordered>
      <q-tabs v-model="tab" dense align="left" active-color="primary" indicator-color="primary" no-caps>
        <q-tab name="datos" icon="settings" label="Datos de Impuestos"/>
        <q-tab name="cuis" icon="vpn_key" label="CUIS"/>
        <q-tab name="cufd" icon="event_available" label="CUFD"/>
        <q-tab name="facturas" icon="receipt_long" label="Facturas enviadas"/>
      </q-tabs>
      <q-separator/>

      <q-tab-panels v-model="tab" animated>
        <!-- ------------------------------------------------ Datos --- -->
        <q-tab-panel name="datos">
          <q-form @submit="guardar">
            <div class="row q-col-gutter-sm">
              <div class="col-12 col-md-6">
                <q-input v-model.trim="form.razon_social" dense outlined label="Razón social" :disable="!puedeConfigurar"/>
              </div>
              <div class="col-6 col-md-3">
                <q-input v-model.trim="form.nit" dense outlined label="NIT" :disable="!puedeConfigurar"
                         :rules="[v => !!v || 'Requerido']"/>
              </div>
              <div class="col-6 col-md-3">
                <q-input v-model.trim="form.codigo_sistema" dense outlined label="Código de sistema"
                         :disable="!puedeConfigurar" :rules="[v => !!v || 'Requerido']"/>
              </div>

              <div class="col-6 col-md-3">
                <q-select v-model="form.codigo_modalidad" dense outlined label="Modalidad" emit-value map-options
                          :options="modalidades" :disable="!puedeConfigurar"/>
              </div>
              <div class="col-6 col-md-3">
                <q-input v-model.number="form.codigo_sucursal" type="number" min="0" dense outlined
                         label="Sucursal" :disable="!puedeConfigurar"/>
              </div>
              <div class="col-6 col-md-3">
                <q-input v-model.number="form.codigo_punto_venta" type="number" min="0" dense outlined
                         label="Punto de venta" :disable="!puedeConfigurar"/>
              </div>
              <div class="col-12 col-md-6">
                <q-input v-model.trim="form.url_siat" dense outlined label="URL SIAT · servicios"
                         hint="Es la que atiende CUIS, CUFD y el envío de facturas"
                         :disable="!puedeConfigurar" :rules="[v => !!v || 'Requerido']"/>
              </div>
              <div class="col-12 col-md-6">
                <q-input v-model.trim="form.url_siat2" dense outlined label="URL SIAT · impresión (QR)"
                         :hint="'El QR queda como ' + estado.url_qr"
                         :disable="!puedeConfigurar" :rules="[v => !!v || 'Requerido']"/>
              </div>

              <div class="col-12">
                <q-separator class="q-my-sm"/>
                <div class="text-subtitle2 text-weight-bold">Token delegado</div>
                <div class="text-caption text-grey-7 q-mb-sm">
                  Es el JWT que entrega la oficina virtual de Impuestos. Se guarda entero en la base de datos.
                  La fecha de vencimiento se lee sola del token al guardar; se puede corregir a mano si hiciera falta.
                </div>
              </div>

              <div class="col-12 col-md-8">
                <q-input
                  v-model.trim="form.token" outlined autogrow
                  :type="verToken ? 'textarea' : 'password'"
                  :disable="!puedeConfigurar"
                  label="Token"
                  input-style="font-family: monospace; font-size: 12px"
                >
                  <template v-slot:append>
                    <q-icon :name="verToken ? 'visibility_off' : 'visibility'" class="cursor-pointer"
                            @click="verToken = !verToken">
                      <q-tooltip>{{ verToken ? 'Ocultar' : 'Mostrar' }}</q-tooltip>
                    </q-icon>
                    <q-icon name="content_copy" class="cursor-pointer q-ml-sm" @click="copiarToken">
                      <q-tooltip>Copiar</q-tooltip>
                    </q-icon>
                  </template>
                </q-input>
              </div>

              <div class="col-12 col-md-4">
                <q-input
                  v-model="form.token_expira" outlined
                  label="Vence el" :disable="!puedeConfigurar"
                  hint="Se completa sola con lo que dice el token"
                >
                  <template v-slot:append>
                    <q-icon name="event" class="cursor-pointer">
                      <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                        <q-date v-model="form.token_expira" mask="YYYY-MM-DD HH:mm:ss">
                          <div class="row items-center justify-end">
                            <q-btn v-close-popup label="Cerrar" color="primary" flat/>
                          </div>
                        </q-date>
                      </q-popup-proxy>
                    </q-icon>
                  </template>
                </q-input>
              </div>

              <div class="col-12 row q-gutter-sm q-mt-sm">
                <q-btn v-if="puedeConfigurar" type="submit" color="primary" unelevated no-caps
                       icon="save" label="Guardar" :loading="guardando"/>
                <q-btn color="secondary" outline no-caps icon="wifi_tethering" label="Probar conexión"
                       :loading="probando" @click="probar"/>
              </div>
            </div>
          </q-form>
        </q-tab-panel>

        <!-- ------------------------------------------------- CUIS --- -->
        <q-tab-panel name="cuis">
          <div class="row items-center q-mb-sm">
            <div class="col text-caption text-grey-7">
              El CUIS dura cerca de un año y es uno por sucursal y punto de venta.
            </div>
            <q-btn v-if="puedeGenerar" color="primary" unelevated no-caps icon="add"
                   label="Generar CUIS" :loading="generandoCuis" @click="generarCuis(false)"/>
          </div>

          <q-markup-table flat bordered dense wrap-cells>
            <thead>
            <tr class="bg-grey-2">
              <th class="text-left">Opciones</th>
              <th class="text-left">Nº</th>
              <th class="text-left">Código</th>
              <th class="text-left">Vigencia</th>
              <th class="text-center">Sucursal</th>
              <th class="text-center">Punto venta</th>
              <th class="text-left">Generado</th>
              <th class="text-center">Estado</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="c in listaCuis" :key="c.id">
              <td>
                <q-btn v-if="puedeGenerar" dense flat round size="sm" icon="delete" color="negative"
                       @click="pedirBaja('cuis', c)">
                  <q-tooltip>Dar de baja</q-tooltip>
                </q-btn>
              </td>
              <td>{{ c.id }}</td>
              <td class="text-weight-medium">{{ c.codigo }}</td>
              <td>{{ fecha(c.fecha_vigencia) }}</td>
              <td class="text-center">{{ c.codigo_sucursal }}</td>
              <td class="text-center">{{ c.codigo_punto_venta }}</td>
              <td>{{ fecha(c.created_at) }}</td>
              <td class="text-center">
                <q-badge :color="vigente(c) ? 'positive' : 'grey-6'" text-color="white">
                  {{ vigente(c) ? 'VIGENTE' : 'VENCIDO' }}
                </q-badge>
              </td>
            </tr>
            <tr v-if="!listaCuis.length">
              <td colspan="8" class="text-center text-grey-7 q-pa-md">Todavía no se generó ningún CUIS</td>
            </tr>
            </tbody>
          </q-markup-table>
        </q-tab-panel>

        <!-- ------------------------------------------------- CUFD --- -->
        <q-tab-panel name="cufd">
          <div class="row items-center q-mb-sm">
            <div class="col text-caption text-grey-7">
              El CUFD caduca cada 24 horas; su código de control es el que entra en el CUF de cada factura.
            </div>
            <q-btn v-if="puedeGenerar" color="primary" unelevated no-caps icon="add"
                   label="Generar CUFD" :loading="generandoCufd" @click="generarCufd(false)"/>
          </div>

          <q-markup-table flat bordered dense wrap-cells>
            <thead>
            <tr class="bg-grey-2">
              <th class="text-left">Opciones</th>
              <th class="text-left">Nº</th>
              <th class="text-left">Código control</th>
              <th class="text-left">Código</th>
              <th class="text-left">Vigencia</th>
              <th class="text-center">Sucursal</th>
              <th class="text-center">Punto venta</th>
              <th class="text-center">Estado</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="c in listaCufd" :key="c.id">
              <td>
                <q-btn v-if="puedeGenerar" dense flat round size="sm" icon="delete" color="negative"
                       @click="pedirBaja('cufd', c)">
                  <q-tooltip>Dar de baja</q-tooltip>
                </q-btn>
              </td>
              <td>{{ c.id }}</td>
              <td class="text-weight-medium">{{ c.codigo_control }}</td>
              <td style="max-width: 320px; word-break: break-all">{{ c.codigo }}</td>
              <td>{{ fecha(c.fecha_vigencia) }}</td>
              <td class="text-center">{{ c.codigo_sucursal }}</td>
              <td class="text-center">{{ c.codigo_punto_venta }}</td>
              <td class="text-center">
                <q-badge :color="vigente(c) ? 'positive' : 'grey-6'" text-color="white">
                  {{ vigente(c) ? 'VIGENTE' : 'VENCIDO' }}
                </q-badge>
              </td>
            </tr>
            <tr v-if="!listaCufd.length">
              <td colspan="8" class="text-center text-grey-7 q-pa-md">Todavía no se generó ningún CUFD</td>
            </tr>
            </tbody>
          </q-markup-table>
        </q-tab-panel>

        <!-- --------------------------------------------- Facturas --- -->
        <q-tab-panel name="facturas">
          <div class="row items-center q-col-gutter-sm q-mb-sm">
            <div class="col-6 col-md-2">
              <q-input v-model="filtroFacturas.desde" type="date" dense outlined label="Desde"/>
            </div>
            <div class="col-6 col-md-2">
              <q-input v-model="filtroFacturas.hasta" type="date" dense outlined label="Hasta"/>
            </div>
            <div class="col-6 col-md-2">
              <q-select
                v-model="filtroFacturas.estado_siat" dense outlined clearable label="Estado SIAT"
                :options="['VALIDADA', 'PENDIENTE', 'RECHAZADA', 'OBSERVADA', 'ERROR']"
              />
            </div>
            <div class="col-auto">
              <q-btn color="primary" dense unelevated no-caps icon="search" label="Buscar"
                     :loading="cargandoFacturas" @click="cargarFacturas"/>
            </div>
            <div class="col text-caption text-grey-7">
              Lo que se mandó a Impuestos desde el sistema. «Verificar» le pregunta al SIAT
              en qué quedó cada una.
            </div>
          </div>

          <q-markup-table flat bordered dense wrap-cells>
            <thead>
            <tr class="bg-grey-2">
              <th class="text-left">Opciones</th>
              <th class="text-left">Nº factura</th>
              <th class="text-left">Fecha</th>
              <th class="text-left">Cliente</th>
              <th class="text-right">Total Bs.</th>
              <th class="text-center">Estado SIAT</th>
              <th class="text-left">Cód. recepción</th>
              <th class="text-left">CUF</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="f in listaFacturas" :key="f.id">
              <td style="white-space: nowrap">
                <q-btn dense flat round size="sm" icon="fact_check" color="primary"
                       :loading="verificando === f.id" @click="verificarFactura(f)">
                  <q-tooltip>Preguntar al SIAT</q-tooltip>
                </q-btn>
                <q-btn dense flat round size="sm" icon="print" color="grey-8" @click="imprimirFactura(f)">
                  <q-tooltip>Imprimir la factura</q-tooltip>
                </q-btn>
                <q-btn
                  v-if="puedeGenerar && f.estado_siat === 'ERROR'"
                  dense flat round size="sm" icon="send" color="negative"
                  :loading="reenviando === f.id" @click="reenviarFactura(f)"
                >
                  <q-tooltip>Reintentar el envío</q-tooltip>
                </q-btn>
              </td>
              <td class="text-weight-medium">{{ f.nro_factura || '—' }}</td>
              <td>{{ String(f.fecha || '').substr(0, 10) }} {{ f.hora }}</td>
              <td>
                {{ f.nombre || 'Sin cliente' }}
                <div class="text-caption text-grey-7">NIT {{ f.nit || '—' }}</div>
              </td>
              <td class="text-right">{{ Number(f.total || 0).toFixed(2) }}</td>
              <td class="text-center">
                <q-badge :color="colorEstadoSiat(f.estado_siat)" text-color="white">
                  {{ f.estado_siat || 'SIN ENVIAR' }}
                </q-badge>
                <div v-if="f.mensaje_siat" class="text-caption text-grey-7" style="max-width: 260px">
                  {{ f.mensaje_siat }}
                </div>
              </td>
              <td>{{ f.codigo_recepcion || '—' }}</td>
              <td style="max-width: 200px; word-break: break-all; font-size: 11px">{{ f.cuf || '—' }}</td>
            </tr>
            <tr v-if="!listaFacturas.length">
              <td colspan="8" class="text-center text-grey-7 q-pa-md">
                No hay facturas enviadas en ese rango
              </td>
            </tr>
            </tbody>
          </q-markup-table>
        </q-tab-panel>
      </q-tab-panels>
    </q-card>

    <q-dialog v-model="dialogBaja">
      <q-card style="min-width: 320px">
        <q-card-section class="q-pb-none">
          <div class="text-subtitle1 text-weight-bold">Dar de baja</div>
        </q-card-section>
        <q-card-section class="text-body2">
          Se marca como dado de baja pero no se borra: las facturas emitidas con
          {{ queBaja === 'cuis' ? 'este CUIS' : 'este CUFD' }} lo siguen necesitando.
        </q-card-section>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense no-caps label="Cancelar" v-close-popup/>
          <q-btn color="negative" unelevated dense no-caps label="Dar de baja"
                 :loading="dandoBaja" @click="darBaja"/>
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- El SIAT devuelve el mismo código mientras siga vigente, así que pedir
         otro solo tiene sentido si el anterior quedó inservible. -->
    <q-dialog v-model="dialogForzar">
      <q-card style="min-width: 320px">
        <q-card-section class="q-pb-none">
          <div class="text-subtitle1 text-weight-bold">Ya hay uno vigente</div>
        </q-card-section>
        <q-card-section class="text-body2">{{ mensajeForzar }} ¿Pedir uno nuevo igual?</q-card-section>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense no-caps label="No" v-close-popup/>
          <q-btn color="primary" unelevated dense no-caps label="Sí, pedir otro" @click="confirmarForzar"/>
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { copyToClipboard, date } from 'quasar'

function estadoVacio () {
  return {
    configuracion: {
      razon_social: '',
      nit: '',
      codigo_modalidad: 2,
      codigo_sistema: '',
      codigo_sucursal: 0,
      codigo_punto_venta: 0,
      url_siat: '',
      url_siat2: '',
      token: '',
      token_expira: null
    },
    ambiente: 'PRODUCCIÓN',
    modalidad: '',
    url_base: '',
    url_qr: '',
    faltantes: [],
    token_vencido: false,
    soap: true,
    cuis: null,
    cufd: null
  }
}

export default {
  name: 'ImpuestosPage',
  data () {
    return {
      tab: 'datos',
      // Reloj propio para que las cuentas regresivas se refresquen solas.
      ahora: Date.now(),
      reloj: null,
      estado: estadoVacio(),
      form: { ...estadoVacio().configuracion },
      listaCuis: [],
      listaCufd: [],
      listaFacturas: [],
      filtroFacturas: { desde: '', hasta: '', estado_siat: null },
      cargandoFacturas: false,
      verificando: null,
      reenviando: null,
      verToken: true,
      cargando: false,
      guardando: false,
      probando: false,
      generandoCuis: false,
      generandoCufd: false,
      dialogForzar: false,
      mensajeForzar: '',
      queForzar: null,
      dialogBaja: false,
      dandoBaja: false,
      queBaja: null,
      filaBaja: null,
      modalidades: [
        { label: '1 · Electrónica en línea', value: 1 },
        { label: '2 · Computarizada en línea', value: 2 }
      ]
    }
  },
  computed: {
    can () {
      return this.$store.getters['login/can']
    },
    puedeConfigurar () {
      return this.can('impuestosConfigurar')
    },
    puedeGenerar () {
      return this.can('impuestosGenerar')
    },
    textoToken () {
      if (!this.estado.configuracion.token) return 'Sin token'
      return this.estado.token_vencido ? 'Vencido' : 'Cargado'
    },
    claseToken () {
      if (!this.estado.configuracion.token || this.estado.token_vencido) return 'text-red-7'
      return 'text-positive'
    }
  },
  created () {
    this.cargarTodo()
    this.reloj = setInterval(() => { this.ahora = Date.now() }, 60000)
  },
  beforeUnmount () {
    clearInterval(this.reloj)
  },
  methods: {
    fecha (v) {
      if (!v) return '—'
      return date.formatDate(String(v).replace(' ', 'T'), 'DD/MM/YYYY HH:mm')
    },
    vigente (fila) {
      return this.horasHasta(fila.fecha_vigencia) > 0
    },

    // Horas que faltan. Depende de `ahora` para que la cuenta se refresque
    // sola: el CUFD dura horas y si no, se queda congelada en pantalla.
    horasHasta (v) {
      if (!v) return null
      return (new Date(String(v).replace(' ', 'T')) - this.ahora) / 3600000
    },

    /** "Faltan 12 días" / "Faltan 3 h 20 min" / "Venció hace 2 días". */
    restante (v) {
      const horas = this.horasHasta(v)
      if (horas === null) return ''

      if (horas <= 0) {
        const dias = Math.floor(-horas / 24)
        if (dias >= 1) return 'Venció hace ' + dias + (dias === 1 ? ' día' : ' días')
        return 'Venció hace ' + Math.max(Math.floor(-horas), 1) + ' h'
      }

      if (horas < 24) {
        const h = Math.floor(horas)
        const min = Math.floor((horas - h) * 60)
        return h >= 1 ? 'Faltan ' + h + ' h ' + min + ' min' : 'Faltan ' + min + ' min'
      }

      const dias = Math.floor(horas / 24)
      return dias === 1 ? 'Falta 1 día' : 'Faltan ' + dias + ' días'
    },

    /** Rojo si ya venció, naranja cuando entra en el umbral de aviso. */
    claseRestante (v, umbralHoras) {
      const horas = this.horasHasta(v)
      if (horas === null) return 'text-grey-7'
      if (horas <= 0) return 'text-red-7'
      return horas <= umbralHoras ? 'text-orange-8' : 'text-positive'
    },

    cargarTodo () {
      this.cargarConfiguracion()
      this.cargarCuis()
      this.cargarCufd()
      this.cargarFacturas()
    },

    cargarFacturas () {
      this.cargandoFacturas = true

      this.$api.get('impuestos/facturas', {
        params: {
          desde: this.filtroFacturas.desde || '',
          hasta: this.filtroFacturas.hasta || '',
          estado_siat: this.filtroFacturas.estado_siat || ''
        }
      })
        .then(res => { this.listaFacturas = res.data })
        .catch(err => { this.avisar(err, 'No se pudieron cargar las facturas enviadas') })
        .finally(() => { this.cargandoFacturas = false })
    },

    colorEstadoSiat (estado) {
      if (estado === 'VALIDADA') return 'positive'
      if (estado === 'PENDIENTE' || estado === 'RECIBIDA') return 'blue-7'
      if (!estado) return 'grey-6'
      return 'negative'
    },

    // El estado real lo dice Impuestos, no nosotros: esto vuelve a preguntar.
    verificarFactura (fila) {
      this.verificando = fila.id

      this.$api.post('impuestos/facturas/' + fila.id + '/verificar')
        .then(res => {
          this.exito(res.data.message)
          this.cargarFacturas()
        })
        .catch(err => { this.avisar(err, 'No se pudo verificar en el SIAT') })
        .finally(() => { this.verificando = null })
    },

    reenviarFactura (fila) {
      this.reenviando = fila.id

      this.$api.post('impuestos/facturas/' + fila.id + '/reenviar')
        .then(res => {
          this.exito(res.data.message)
          this.cargarFacturas()
        })
        .catch(err => { this.avisar(err, 'No se pudo reenviar') })
        .finally(() => { this.reenviando = null })
    },

    /** Abre el mismo PDF que imprime la pantalla de facturación. */
    imprimirFactura (fila) {
      this.$api.get('facturacion/' + fila.id + '/factura', { responseType: 'blob' })
        .then(res => {
          const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
          window.open(url, '_blank')
        })
        .catch(err => { this.avisar(err, 'No se pudo abrir la factura') })
    },

    cargarConfiguracion () {
      this.cargando = true

      this.$api.get('impuestos/configuracion')
        .then(res => { this.aplicarEstado(res.data) })
        .catch(err => { this.avisar(err, 'No se pudo cargar la configuración de Impuestos') })
        .finally(() => { this.cargando = false })
    },

    // El formulario queda con lo que hay guardado, token incluido: es lo que
    // se vuelve a mandar al guardar, asi que tiene que verse tal cual esta.
    aplicarEstado (data) {
      this.estado = data
      this.form = { ...data.configuracion }
    },

    cargarCuis () {
      this.$api.get('impuestos/cuis')
        .then(res => { this.listaCuis = res.data })
        .catch(() => {})
    },
    cargarCufd () {
      this.$api.get('impuestos/cufd')
        .then(res => { this.listaCufd = res.data })
        .catch(() => {})
    },

    guardar () {
      this.guardando = true

      this.$api.put('impuestos/configuracion', this.form)
        .then(res => {
          this.aplicarEstado(res.data)
          this.exito(res.data.message)
        })
        .catch(err => { this.avisar(err, 'No se pudieron guardar los datos') })
        .finally(() => { this.guardando = false })
    },

    copiarToken () {
      if (!this.form.token) return

      copyToClipboard(this.form.token)
        .then(() => { this.exito('Token copiado') })
        .catch(() => {})
    },

    probar () {
      this.probando = true

      this.$api.post('impuestos/probar')
        .then(res => { this.exito(res.data.message) })
        .catch(err => { this.avisar(err, 'No se pudo conectar al SIAT') })
        .finally(() => { this.probando = false })
    },

    generarCuis (forzar) {
      this.generandoCuis = true

      this.$api.post('impuestos/cuis', { forzar: forzar ? 1 : 0 })
        .then(res => {
          this.exito(res.data.message)
          this.cargarCuis()
          this.cargarConfiguracion()
        })
        .catch(err => { this.manejarVigente(err, 'cuis', 'No se pudo generar el CUIS') })
        .finally(() => { this.generandoCuis = false })
    },

    generarCufd (forzar) {
      this.generandoCufd = true

      this.$api.post('impuestos/cufd', { forzar: forzar ? 1 : 0 })
        .then(res => {
          this.exito(res.data.message)
          this.cargarCufd()
          this.cargarConfiguracion()
        })
        .catch(err => { this.manejarVigente(err, 'cufd', 'No se pudo generar el CUFD') })
        .finally(() => { this.generandoCufd = false })
    },

    // El backend rechaza con 422 cuando ya hay uno vigente y devuelve el
    // código en el cuerpo; eso se convierte en la pregunta de forzar.
    manejarVigente (err, que, porDefecto) {
      const data = err.response?.data

      if (data && data[que]) {
        this.mensajeForzar = data.message
        this.queForzar = que
        this.dialogForzar = true
        return
      }

      this.avisar(err, porDefecto)
    },

    pedirBaja (que, fila) {
      this.queBaja = que
      this.filaBaja = fila
      this.dialogBaja = true
    },

    // Borrado lógico: el backend solo marca deleted_at.
    darBaja () {
      this.dandoBaja = true

      this.$api.delete('impuestos/' + this.queBaja + '/' + this.filaBaja.id)
        .then(res => {
          this.exito(res.data.message)
          this.dialogBaja = false
          if (this.queBaja === 'cuis') this.cargarCuis()
          else this.cargarCufd()
          this.cargarConfiguracion()
        })
        .catch(err => { this.avisar(err, 'No se pudo dar de baja') })
        .finally(() => { this.dandoBaja = false })
    },

    confirmarForzar () {
      this.dialogForzar = false

      if (this.queForzar === 'cuis') this.generarCuis(true)
      else this.generarCufd(true)
    },

    exito (message) {
      this.$q.notify({ message, color: 'positive', icon: 'check_circle', position: 'top', timeout: 5000 })
    },
    avisar (err, porDefecto) {
      this.$q.notify({
        message: err.response?.data?.message || porDefecto,
        color: 'negative',
        icon: 'error',
        position: 'top',
        timeout: 8000
      })
    }
  }
}
</script>
