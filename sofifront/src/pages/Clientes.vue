<template>
  <q-page class="q-pa-xs">
    <!-- Totales segun los filtros aplicados -->
    <div class="row q-col-gutter-xs q-mb-xs">
      <div class="col-6 col-sm-3 col-md" v-for="t in totales" :key="t.label">
        <q-card flat bordered>
          <q-card-section class="row items-center no-wrap q-pa-xs">
            <q-avatar size="30px" :color="t.color" text-color="white" :icon="t.icon"/>
            <div class="q-ml-sm ellipsis">
              <div class="stat-valor" :class="'text-' + t.color">{{ t.valor }}</div>
              <div class="stat-label ellipsis">{{ t.label }}</div>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-xs items-center q-pa-xs">
        <div class="col-12 col-sm-6 col-md-3">
          <q-input v-model="filter" dense outlined placeholder="Buscar nombre, CI, teléfono…" debounce="300" clearable>
            <template #prepend><q-icon name="search" size="xs"/></template>
          </q-input>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <q-select v-model="filterVendedor" :options="vendedoresFiltrados" use-input fill-input hide-selected input-debounce="0" @filter="filtrarVendedores" option-label="label" option-value="ci"
                    emit-value map-options clearable dense outlined options-dense label="Vendedor"/>
        </div>
        <div class="col-6 col-md-2">
          <q-select v-model="filterZona" :options="zonasOptions" emit-value map-options clearable dense outlined
                    options-dense label="Zona"/>
        </div>
        <div class="col-6 col-md-1">
          <q-select v-model="filterEstado" :options="['ACTIVO', 'INACTIVO']" clearable dense outlined options-dense
                    label="Estado"/>
        </div>
        <div class="col-12 col-md-3 row no-wrap justify-end items-center q-gutter-xs">
          <q-btn dense unelevated color="green" icon="add" no-caps label="Nuevo" @click="nuevoCliente"/>
          <q-btn dense flat round color="primary" icon="refresh" :loading="loading" @click="misclientes">
            <q-tooltip>Recargar</q-tooltip>
          </q-btn>
          <q-btn-dropdown dense flat color="grey-8" icon="lock" no-caps label="Bloqueo">
            <q-list dense>
              <q-item clickable v-close-popup @click="quitar">
                <q-item-section avatar><q-icon name="lock" color="negative"/></q-item-section>
                <q-item-section>
                  <q-item-label>Todos pendiente</q-item-label>
                  <q-item-label caption>Recalcula el bloqueo por deuda</q-item-label>
                </q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="agregar">
                <q-item-section avatar><q-icon name="lock_open" color="positive"/></q-item-section>
                <q-item-section>
                  <q-item-label>Actualizar hab</q-item-label>
                  <q-item-label caption>Habilita a quien debe menos de 12 000 Bs</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-btn-dropdown>
        </div>
      </q-card-section>

      <q-table :rows="clientesFiltrados" :columns="columns" row-key="Cod_Aut" dense flat
               class="tabla-clientes" virtual-scroll :rows-per-page-options="[0]" hide-bottom :loading="loading">
        <template #body-cell-opciones="props">
          <q-td :props="props">
            <q-btn dense flat round size="sm" color="primary" icon="edit" @click="editarCliente(props.row)">
              <q-tooltip>Editar</q-tooltip>
            </q-btn>
            <q-btn dense flat round size="sm" color="info" icon="photo_camera" @click="editarCliente(props.row, 'fotos')">
              <q-badge v-if="Number(props.row.fotos_count)" floating rounded color="info">{{ props.row.fotos_count }}</q-badge>
              <q-tooltip>Fotos</q-tooltip>
            </q-btn>
          </q-td>
        </template>
        <template #body-cell-Nombres="props">
          <q-td :props="props">
            <div class="text-weight-medium ellipsis celda-nombre">{{ props.row.Nombres }}</div>
            <div class="text-caption text-grey-7 ellipsis celda-nombre">{{ props.row.Direccion }}</div>
          </q-td>
        </template>
        <template #body-cell-dias="props">
          <q-td :props="props">
            <span v-for="d in dias" :key="d.campo" class="dia"
                  :class="Number(props.row[d.campo]) ? 'dia-on' : 'dia-off'">{{ d.letra }}<q-tooltip>{{ d.largo }}</q-tooltip></span>
          </q-td>
        </template>
        <template #body-cell-totdeuda="props">
          <q-td :props="props" :class="Number(props.row.totdeuda) > 0 ? 'text-negative text-weight-medium' : 'text-grey-5'">
            {{ Number(props.row.totdeuda) > 0 ? Number(props.row.totdeuda).toFixed(2) : '-' }}
          </q-td>
        </template>
        <template #body-cell-excepcion_deuda="props">
          <q-td :props="props">
            <q-toggle dense size="xs" color="purple" :model-value="Number(props.row.excepcion_deuda) === 1"
                      :disable="cambiandoExcepcion === props.row.Cod_Aut" @update:model-value="cambiarExcepcion(props.row, $event)">
              <q-tooltip>Excepción: puede pedir aunque deba</q-tooltip>
            </q-toggle>
          </q-td>
        </template>
        <template #body-cell-venta="props">
          <q-td :props="props">
            <q-badge class="cursor-pointer" @click="hablitar(props.row)"
                     :color="props.row.venta=='ACTIVO'?'positive':'negative'">{{ props.row.venta }}</q-badge>
          </q-td>
        </template>
        <template #no-data>
          <div class="full-width text-center text-grey-7 q-pa-md">Sin clientes</div>
        </template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog" maximized>
      <q-card>
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ cliente.Cod_Aut ? 'Editar cliente #' + cliente.Cod_Aut : 'Nuevo cliente' }}</div>
          <q-space/>
          <q-btn flat round dense icon="close" @click="dialog = false"/>
        </q-card-section>

        <q-card-section>
          <q-form @submit="guardarCliente">
            <q-tabs v-model="tab" dense active-color="primary" align="left" class="text-grey-8" no-caps>
              <q-tab name="basico" label="Básico"/>
              <q-tab name="comercial" label="Comercial"/>
              <q-tab name="visita" label="Visita"/>
              <q-tab name="ubicacion" label="Ubicación"/>
              <q-tab name="fotos" label="Fotos"/>
            </q-tabs>
            <q-separator class="q-my-sm"/>

            <q-tab-panels v-model="tab" animated keep-alive>
              <q-tab-panel name="basico">
                <div class="row q-col-gutter-sm">
                  <div class="col-12 col-md-5"><q-input v-model="cliente.Nombres" label="Nombre *" dense outlined maxlength="70" :rules="[v => !!v || 'Requerido']"/></div>
                  <div class="col-8 col-md-3"><q-input v-model="cliente.Id" label="CI / NIT *" dense outlined maxlength="15" :rules="[v => !!v || 'Requerido']"/></div>
                  <div class="col-4 col-md-2"><q-input v-model="cliente.complto" label="Complemento" dense outlined maxlength="5"/></div>
                  <div class="col-12 col-md-2"><q-input v-model.number="cliente.Tipodocu" label="Tipo doc" dense outlined type="number"/></div>
                  <div class="col-12 col-md-3"><q-input v-model="cliente.Telf" label="Teléfono" dense outlined maxlength="100"/></div>
                  <div class="col-12 col-md-3"><q-input v-model="cliente.Correcli" label="Email" dense outlined maxlength="50"/></div>
                  <div class="col-12 col-md-6"><q-input v-model="cliente.Direccion" label="Dirección" dense outlined maxlength="100"/></div>
                  <div class="col-12 col-md-3"><q-input v-model="cliente.Empresa" label="Empresa" dense outlined maxlength="150"/></div>
                  <div class="col-12 col-md-3"><q-input v-model="cliente.profecion" label="Profesión" dense outlined maxlength="60"/></div>
                  <div class="col-6 col-md-2"><q-input v-model="cliente.sexo" label="Sexo" dense outlined maxlength="20"/></div>
                  <div class="col-6 col-md-2"><q-input v-model="cliente.edad" label="Edad" dense outlined maxlength="3"/></div>
                  <div class="col-6 col-md-2"><q-input v-model="cliente.EstCiv" label="Estado civil" dense outlined maxlength="50"/></div>
                  <div class="col-6 col-md-2"><q-input v-model="cliente.Cod_ciudad" label="Cod ciudad" dense outlined maxlength="4"/></div>
                  <div class="col-6 col-md-2"><q-input v-model="cliente.Cod_Nacio" label="Cod nacio" dense outlined maxlength="4"/></div>
                </div>
              </q-tab-panel>

              <q-tab-panel name="comercial">
                <div class="row q-col-gutter-sm">
                  <div class="col-12 col-md-6">
                    <q-select v-model="cliente.CiVend" :options="vendedoresFiltrados" use-input fill-input hide-selected input-debounce="0" @filter="filtrarVendedores" option-label="label" option-value="ci"
                              emit-value map-options clearable dense outlined label="Vendedor (preventista)"/>
                  </div>
                  <div class="col-6 col-md-2"><q-input v-model.number="cliente.cod_car" label="Cod car" dense outlined type="number"/></div>
                  <div class="col-6 col-md-2"><q-input v-model.number="cliente.Categoria" label="Categoría" dense outlined type="number"/></div>
                  <div class="col-6 col-md-2"><q-input v-model.number="cliente.codcli" label="Cod cliente" dense outlined type="number"/></div>
                  <div class="col-6 col-md-2"><q-input v-model="cliente.clinew" label="Cli new" dense outlined maxlength="3"/></div>
                  <div class="col-6 col-md-2"><q-input v-model.number="cliente.Imp_pieza" label="Imp pieza" dense outlined type="number" step="0.01"/></div>
                  <div class="col-6 col-md-2"><q-input v-model="cliente.SupraCanal" label="Supra canal" dense outlined maxlength="5"/></div>
                  <div class="col-6 col-md-3"><q-input v-model="cliente.Canal" label="Canal" dense outlined maxlength="80"/></div>
                  <div class="col-6 col-md-3"><q-input v-model="cliente.subcanal" label="Subcanal" dense outlined maxlength="20"/></div>
                  <div class="col-6 col-md-3"><q-input v-model="cliente.zona" label="Zona" dense outlined maxlength="20"/></div>
                  <div class="col-6 col-md-3"><q-input v-model="cliente.territorio" label="Territorio" dense outlined maxlength="10"/></div>
                  <div class="col-6 col-md-3"><q-input v-model="cliente.transporte" label="Transporte" dense outlined maxlength="60"/></div>
                  <div class="col-6 col-md-3">
                    <q-select v-model="cliente.venta" :options="['ACTIVO', 'INACTIVO']" label="Estado venta" dense outlined/>
                  </div>
                  <div class="col-6 col-md-3"><q-input v-model="cliente.tarjeta" label="Tarjeta" dense outlined maxlength="20"/></div>
                  <div class="col-12 col-md-3"><q-input v-model="cliente.TipoPaciente" label="Tipo cliente" dense outlined maxlength="90"/></div>
                </div>
              </q-tab-panel>

              <q-tab-panel name="visita">
                <div class="row q-col-gutter-sm">
                  <div class="col-6 col-md-3"><q-input v-model.number="cliente.ctasMont" label="Monto ctas" dense outlined type="number" step="0.01"/></div>
                  <div class="col-6 col-md-3"><q-input v-model.number="cliente.ctasdias" label="Días ctas" dense outlined type="number"/></div>
                  <div class="col-12 col-md-6"><q-input v-model="cliente.MotivoListBlack" label="Motivo lista negra" dense outlined maxlength="90"/></div>

                  <div class="col-12">
                    <q-card flat bordered class="q-pa-sm bg-purple-1">
                      <q-toggle v-model="cliente.excepcion_deuda" :true-value="1" :false-value="0" color="purple"
                                label="Excepción de deuda: puede hacer pedidos aunque deba (no se bloquea)"/>
                    </q-card>
                  </div>

                  <div class="col-12 q-mt-sm text-subtitle2">Días de visita</div>
                  <div class="col-12 row q-col-gutter-sm">
                    <div class="col-auto" v-for="d in dias" :key="d.campo">
                      <q-toggle v-model="cliente[d.campo]" :true-value="1" :false-value="0" :label="d.largo"/>
                    </div>
                  </div>

                  <div class="col-12 q-mt-sm text-subtitle2">Estados</div>
                  <div class="col-12 row q-col-gutter-sm">
                    <div class="col-auto" v-for="f in flags" :key="f.campo">
                      <q-toggle v-model="cliente[f.campo]" :true-value="1" :false-value="0" :label="f.label"/>
                    </div>
                  </div>
                </div>
              </q-tab-panel>

              <q-tab-panel name="ubicacion">
                <div class="row q-col-gutter-sm">
                  <div class="col-6 col-md-3"><q-input v-model="cliente.Latitud" label="Latitud" dense outlined maxlength="15"/></div>
                  <div class="col-6 col-md-3"><q-input v-model="cliente.longitud" label="Longitud" dense outlined maxlength="15"/></div>
                  <div class="col-4 col-md-2"><q-btn color="primary" no-caps label="Centrar" class="full-width" @click="centrarMapa"/></div>
                  <div class="col-4 col-md-2"><q-btn color="secondary" no-caps label="Mi ubicación" class="full-width" @click="miUbicacion"/></div>
                  <div class="col-4 col-md-2">
                    <q-btn color="info" no-caps icon="open_in_new" label="Maps" class="full-width"
                           :disable="!coordenadasValidas()" @click="abrirGoogleMaps"/>
                  </div>
                  <div class="col-12">
                    <div ref="mapRef" class="map-canvas"/>
                    <div class="text-caption q-mt-xs">Click en el mapa o arrastra el marcador para cambiar la ubicación.</div>
                  </div>
                </div>
              </q-tab-panel>

              <q-tab-panel name="fotos">
                <div v-if="!cliente.Cod_Aut" class="text-grey-7">Guarda primero el cliente para poder subir fotos.</div>
                <div v-else class="row q-col-gutter-sm">
                  <div class="col-12">
                    <q-btn color="primary" no-caps icon="photo_camera" label="Agregar fotos" :loading="subiendo"
                           @click="$refs.fotosInput.click()"/>
                    <input ref="fotosInput" type="file" accept="image/*" multiple style="display:none" @change="subirFotos"/>
                  </div>
                  <div v-if="!fotos.length && !cargandoFotos" class="col-12 text-grey-7">Este cliente no tiene fotos.</div>
                  <div class="col-12" v-if="cargandoFotos"><q-spinner size="md"/></div>
                  <div class="col-6 col-md-3" v-for="f in fotos" :key="f.id">
                    <q-card flat bordered>
                      <a :href="f.url" target="_blank"><q-img :src="f.url" style="height: 150px" fit="cover"/></a>
                      <q-card-actions align="right">
                        <q-btn flat dense color="negative" icon="delete" :loading="borrandoFoto === f.id" @click="borrarFoto(f)"/>
                      </q-card-actions>
                    </q-card>
                  </div>
                </div>
              </q-tab-panel>
            </q-tab-panels>

            <div class="text-right q-mt-md">
              <q-btn flat no-caps label="Cancelar" color="grey-8" @click="dialog = false"/>
              <q-btn color="primary" no-caps label="Guardar" type="submit" class="q-ml-sm" :loading="guardando"/>
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const ORURO_CENTRO = [-17.967, -67.106]

// Marcador dibujado con CSS: evita depender de las imagenes de leaflet en el build de Vite.
const iconoMarcador = L.divIcon({
  className: 'cliente-marker-wrap',
  html: '<div class="cliente-marker-pin"></div>',
  iconSize: [28, 28],
  iconAnchor: [14, 14]
})

const camposNumero = [
  'Tipodocu', 'cod_car', 'Categoria', 'codcli', 'Imp_pieza', 'ctasMont', 'ctasdias',
  'lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'do',
  'ListBlack', 'ListBlanck', 'canmayni', 'baja', 'waths', 'ctasActivo', 'noesempre', 'excepcion_deuda'
]

const clienteVacio = () => ({
  Cod_Aut: null, Id: '', Nombres: '', Telf: '', Direccion: '', complto: '', Tipodocu: 0, Correcli: '',
  Empresa: '', profecion: '', sexo: '', edad: '', EstCiv: '', Cod_ciudad: '', Cod_Nacio: '',
  cod_car: 0, Categoria: 0, codcli: 0, clinew: '', CiVend: '', Imp_pieza: 0, SupraCanal: '', Canal: '',
  subcanal: '', zona: '', territorio: '', transporte: '', venta: 'ACTIVO', tarjeta: '', TipoPaciente: '',
  ctasMont: 0, ctasdias: 0, MotivoListBlack: '', Latitud: '', longitud: '',
  lu: 0, Ma: 0, Mi: 0, Ju: 0, Vi: 0, Sa: 0, do: 0,
  ListBlack: 0, ListBlanck: 0, canmayni: 0, baja: 0, waths: 0, ctasActivo: 0, noesempre: 0, excepcion_deuda: 0
})

export default {
  data () {
    return {
      loading: false,
      guardando: false,
      filter: '',
      filterVendedor: null,
      filterZona: null,
      filterEstado: null,
      clientes: [],
      vendedores: [],
      dialog: false,
      tab: 'basico',
      cliente: clienteVacio(),
      fotos: [],
      cargandoFotos: false,
      subiendo: false,
      borrandoFoto: null,
      cambiandoExcepcion: null,
      map: null,
      marker: null,
      dias: [
        {campo: 'lu', letra: 'L', largo: 'Lun'}, {campo: 'Ma', letra: 'M', largo: 'Mar'},
        {campo: 'Mi', letra: 'X', largo: 'Mié'}, {campo: 'Ju', letra: 'J', largo: 'Jue'},
        {campo: 'Vi', letra: 'V', largo: 'Vie'}, {campo: 'Sa', letra: 'S', largo: 'Sáb'},
        {campo: 'do', letra: 'D', largo: 'Dom'}
      ],
      flags: [
        {campo: 'ListBlack', label: 'Lista negra'}, {campo: 'ListBlanck', label: 'Lista blanca'},
        {campo: 'canmayni', label: 'Can mayni'}, {campo: 'baja', label: 'Baja'},
        {campo: 'waths', label: 'WhatsApp'}, {campo: 'ctasActivo', label: 'Ctas activo'},
        {campo: 'noesempre', label: 'No es empresa'}
      ],
      columns: [
        {label: '', name: 'opciones', field: 'opciones', align: 'left'},
        {label: 'Cod', name: 'Cod_Aut', field: 'Cod_Aut', align: 'left', sortable: true},
        {label: 'Cliente', name: 'Nombres', field: 'Nombres', align: 'left', sortable: true},
        {label: 'CI/NIT', name: 'Id', field: 'Id', align: 'left', sortable: true},
        {label: 'Teléfono', name: 'Telf', field: 'Telf', align: 'left'},
        {label: 'Vendedor', name: 'vendedor', field: r => r.vendedor || r.CiVend, align: 'left', sortable: true},
        {label: 'Zona', name: 'zona', field: 'zona', align: 'left', sortable: true},
        {label: 'Deuda', name: 'totdeuda', field: 'totdeuda', align: 'right', sortable: true,
          sort: (a, b) => Number(a || 0) - Number(b || 0)},
        {label: 'Días', name: 'dias', field: 'dias', align: 'left'},
        {label: 'Excep.', name: 'excepcion_deuda', field: 'excepcion_deuda', align: 'center', sortable: true},
        {label: 'Estado', name: 'venta', field: 'venta', align: 'center', sortable: true}
      ],
      vendedoresFiltrados: []
    }
  },
  computed: {
    totales () {
      const lista = this.clientesFiltrados
      let inactivos = 0
      let conDeuda = 0
      let deuda = 0
      let conFotos = 0
      let sinUbicacion = 0
      let conExcepcion = 0
      lista.forEach(c => {
        if (c.venta === 'INACTIVO') inactivos++
        const d = Number(c.totdeuda) || 0
        if (d > 0) {
          conDeuda++
          deuda += d
        }
        if (Number(c.fotos_count)) conFotos++
        const lat = parseFloat(c.Latitud)
        if (!Number.isFinite(lat) || lat === 0) sinUbicacion++
        if (Number(c.excepcion_deuda) === 1) conExcepcion++
      })
      const bs = n => n.toLocaleString('es-BO', {minimumFractionDigits: 2, maximumFractionDigits: 2})
      return [
        {label: 'Clientes', valor: lista.length, icon: 'groups', color: 'primary'},
        {label: 'Activos', valor: lista.length - inactivos, icon: 'check_circle', color: 'positive'},
        {label: 'Inactivos', valor: inactivos, icon: 'block', color: 'negative'},
        {label: `Deuda (${conDeuda} clientes)`, valor: bs(deuda), icon: 'payments', color: 'orange-9'},
        {label: 'Con excepción', valor: conExcepcion, icon: 'verified_user', color: 'purple'},
        {label: 'Con fotos', valor: conFotos, icon: 'photo_camera', color: 'info'},
        {label: 'Sin ubicación', valor: sinUbicacion, icon: 'wrong_location', color: 'grey-7'}
      ]
    },
    zonasOptions () {
      const total = {}
      this.clientes.forEach(c => {
        const z = (c.zona || '').trim()
        if (z) total[z] = (total[z] || 0) + 1
      })
      return Object.keys(total).sort().map(z => ({label: `${z} (${total[z]})`, value: z}))
    },
    clientesFiltrados () {
      const texto = this.filter.trim().toLowerCase()
      return this.clientes.filter(c => {
        if (this.filterVendedor && (c.CiVend || '').trim() !== this.filterVendedor) return false
        if (this.filterZona && (c.zona || '').trim() !== this.filterZona) return false
        if (this.filterEstado && c.venta !== this.filterEstado) return false
        if (!texto) return true
        return [c.Nombres, c.Id, c.Telf, c.Cod_Aut, c.Direccion]
          .some(v => String(v == null ? '' : v).toLowerCase().includes(texto))
      })
    }
  },
  watch: {
    dialog (abierto) {
      if (!abierto && this.map) {
        this.map.remove()
        this.map = null
        this.marker = null
      }
    },
    tab (val) {
      if (val === 'ubicacion' && this.dialog) this.$nextTick(() => this.iniciarMapa())
    },
    'cliente.Latitud' () { this.moverMarcador() },
    'cliente.longitud' () { this.moverMarcador() }
  },
  created () {
    if (!this.$store.getters['login/can']('clientes')) {
      this.$router.push('/')
      return
    }
    this.misclientes()
    this.vendedoresGet()
  },
  methods: {
    quitar () {
      this.$api.post('bloquear').then(() => this.misclientes())
    },
    agregar () {
      this.$api.post('desbloq2').then(() => this.misclientes())
    },
    hablitar (cliente) {
      this.$api.post('desbloquear', cliente)
      cliente.venta = cliente.venta == 'ACTIVO' ? 'INACTIVO' : 'ACTIVO'
    },
    // Marca o quita la excepcion desde la tabla: se guarda el cliente con el mismo endpoint de editar.
    cambiarExcepcion (row, valor) {
      const datos = {...row, excepcion_deuda: valor ? 1 : 0}
      this.cambiandoExcepcion = row.Cod_Aut
      this.$api.put('cliente/' + row.Cod_Aut, datos).then(res => {
        row.excepcion_deuda = datos.excepcion_deuda
        if (res.data && res.data.venta) row.venta = res.data.venta
        this.$q.notify({
          type: 'positive',
          message: valor ? 'Excepción activada: puede pedir aunque deba' : 'Excepción quitada'
        })
      }).catch(e => {
        this.$q.notify({type: 'negative', message: e.response?.data?.message || 'No se pudo guardar'})
      }).finally(() => {
        this.cambiandoExcepcion = null
      })
    },
    misclientes () {
      this.loading = true
      this.$api.post('todosclientes').then(res => {
        this.clientes = res.data
      }).catch(e => {
        this.$q.notify({type: 'negative', message: e.response?.data?.message || 'No se pudo cargar clientes'})
      }).finally(() => {
        this.loading = false
      })
    },
    vendedoresGet () {
      this.$api.get('personalCliente', {params: {todos: 1}}).then(res => {
        this.vendedores = (res.data || []).map(v => ({
          ci: String(v.ci || '').trim(),
          label: `${String(v.nombre || '').replace(/\s+/g, ' ').trim()} (${String(v.ci || '').trim()})`
        }))
        this.vendedoresFiltrados = this.vendedores
      }).catch(() => {
        this.vendedores = []
      })
    },
    // Busca el vendedor escribiendo parte del nombre o del CI.
    filtrarVendedores (val, update) {
      update(() => {
        const texto = (val || '').toLowerCase()
        this.vendedoresFiltrados = texto
          ? this.vendedores.filter(v => v.label.toLowerCase().includes(texto))
          : this.vendedores
      })
    },
    nuevoCliente () {
      this.cliente = clienteVacio()
      this.fotos = []
      this.tab = 'basico'
      this.dialog = true
    },
    editarCliente (row, tab = 'basico') {
      const c = {...clienteVacio()}
      Object.keys(c).forEach(k => {
        if (row[k] === undefined || row[k] === null) return
        c[k] = camposNumero.includes(k) ? Number(row[k]) || 0 : String(row[k]).trim()
      })
      c.Cod_Aut = row.Cod_Aut
      this.cliente = c
      this.fotos = []
      this.tab = tab
      this.dialog = true
      this.cargarFotos()
      if (tab === 'ubicacion') this.$nextTick(() => this.iniciarMapa())
    },
    guardarCliente () {
      this.guardando = true
      const c = this.cliente
      const peticion = c.Cod_Aut ? this.$api.put('cliente/' + c.Cod_Aut, c) : this.$api.post('cliente', c)
      peticion.then(res => {
        this.$q.notify({type: 'positive', message: c.Cod_Aut ? 'Cliente actualizado' : 'Cliente creado'})
        if (!c.Cod_Aut && res.data && res.data.Cod_Aut) {
          // Queda abierto para poder cargarle fotos.
          this.cliente.Cod_Aut = res.data.Cod_Aut
          this.tab = 'fotos'
        } else {
          this.dialog = false
        }
        this.misclientes()
      }).catch(e => {
        this.$q.notify({type: 'negative', message: e.response?.data?.message || 'No se pudo guardar el cliente'})
      }).finally(() => {
        this.guardando = false
      })
    },
    cargarFotos () {
      if (!this.cliente.Cod_Aut) return
      this.cargandoFotos = true
      this.$api.get('cliente-photos', {params: {cliente_id: this.cliente.Cod_Aut}}).then(res => {
        this.fotos = res.data || []
      }).finally(() => {
        this.cargandoFotos = false
      })
    },
    subirFotos (e) {
      const archivos = Array.from(e.target.files || [])
      e.target.value = ''
      if (!archivos.length) return
      const fd = new FormData()
      fd.append('cliente_id', this.cliente.Cod_Aut)
      archivos.forEach(f => fd.append('photos[]', f))
      this.subiendo = true
      this.$api.post('cliente-photos', fd, {headers: {'Content-Type': 'multipart/form-data'}}).then(() => {
        this.$q.notify({type: 'positive', message: 'Fotos subidas'})
        this.cargarFotos()
        this.misclientes()
      }).catch(err => {
        this.$q.notify({type: 'negative', message: err.response?.data?.message || 'No se pudieron subir las fotos'})
      }).finally(() => {
        this.subiendo = false
      })
    },
    borrarFoto (foto) {
      this.$q.dialog({title: 'Eliminar foto', message: '¿Eliminar esta foto?', cancel: true}).onOk(() => {
        this.borrandoFoto = foto.id
        this.$api.delete('cliente-photos/' + foto.id).then(() => {
          this.fotos = this.fotos.filter(f => f.id !== foto.id)
          this.misclientes()
        }).catch(() => {
          this.$q.notify({type: 'negative', message: 'No se pudo eliminar la foto'})
        }).finally(() => {
          this.borrandoFoto = null
        })
      })
    },
    coordenadasValidas () {
      const lat = parseFloat(this.cliente.Latitud)
      const lng = parseFloat(this.cliente.longitud)
      return Number.isFinite(lat) && Number.isFinite(lng) && !(lat === 0 && lng === 0)
    },
    iniciarMapa () {
      if (!this.$refs.mapRef) return
      const hay = this.coordenadasValidas()
      const centro = hay ? [parseFloat(this.cliente.Latitud), parseFloat(this.cliente.longitud)] : ORURO_CENTRO
      if (!this.map) {
        this.map = L.map(this.$refs.mapRef, {center: centro, zoom: hay ? 16 : 13})
        const calle = L.tileLayer('https://mt1.google.com/vt/lyrs=r&x={x}&y={y}&z={z}', {maxZoom: 21, attribution: 'Map data © Google'})
        const satelite = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {maxZoom: 21, attribution: 'Map data © Google'})
        calle.addTo(this.map)
        L.control.layers({'Calle': calle, 'Satélite': satelite}).addTo(this.map)
        this.map.on('click', e => this.ponerCoordenadas(e.latlng.lat, e.latlng.lng))
      }
      this.map.invalidateSize()
      this.map.setView(centro, hay ? 16 : 13)
      this.moverMarcador()
    },
    ponerCoordenadas (lat, lng) {
      // Latitud/longitud son varchar(15) en tbclientes.
      this.cliente.Latitud = lat.toFixed(7)
      this.cliente.longitud = lng.toFixed(7)
    },
    moverMarcador () {
      if (!this.map) return
      if (!this.coordenadasValidas()) {
        if (this.marker) {
          this.map.removeLayer(this.marker)
          this.marker = null
        }
        return
      }
      const punto = [parseFloat(this.cliente.Latitud), parseFloat(this.cliente.longitud)]
      if (!this.marker) {
        this.marker = L.marker(punto, {draggable: true, icon: iconoMarcador}).addTo(this.map)
        this.marker.on('dragend', e => {
          const p = e.target.getLatLng()
          this.ponerCoordenadas(p.lat, p.lng)
        })
      } else {
        this.marker.setLatLng(punto)
      }
    },
    centrarMapa () {
      if (this.map && this.coordenadasValidas()) {
        this.map.flyTo([parseFloat(this.cliente.Latitud), parseFloat(this.cliente.longitud)], 17)
      }
    },
    miUbicacion () {
      if (!navigator.geolocation) {
        this.$q.notify({type: 'negative', message: 'Geolocalización no disponible'})
        return
      }
      navigator.geolocation.getCurrentPosition(pos => {
        this.ponerCoordenadas(pos.coords.latitude, pos.coords.longitude)
        this.centrarMapa()
      }, () => this.$q.notify({type: 'negative', message: 'No se pudo obtener la ubicación'}))
    },
    abrirGoogleMaps () {
      window.open(`https://www.google.com/maps/search/?api=1&query=${this.cliente.Latitud},${this.cliente.longitud}`, '_blank')
    }
  }
}
</script>

<style scoped>
.stat-valor {
  font-size: 15px;
  font-weight: 600;
  line-height: 1.1;
}
.stat-label {
  font-size: 11px;
  color: #6b7280;
}
.tabla-clientes {
  height: calc(100vh - 210px);
}
.tabla-clientes :deep(td),
.tabla-clientes :deep(th) {
  font-size: 12px;
  padding: 2px 6px;
}
.tabla-clientes :deep(thead tr th) {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #f5f7fa;
}
.celda-nombre {
  max-width: 260px;
}
.dia {
  display: inline-block;
  width: 15px;
  height: 15px;
  line-height: 15px;
  margin-right: 1px;
  border-radius: 3px;
  font-size: 10px;
  text-align: center;
}
.dia-on {
  background: #1976d2;
  color: #fff;
}
.dia-off {
  background: #e5e7eb;
  color: #9ca3af;
}
.map-canvas {
  height: 400px;
  border-radius: 12px;
  border: 1px solid #d7e3f8;
}
:deep(.cliente-marker-wrap) {
  background: transparent;
  border: 0;
}
:deep(.cliente-marker-pin) {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #e53935;
  border: 3px solid #fff;
  box-shadow: 0 0 0 2px rgba(229, 57, 53, 0.35), 0 4px 12px rgba(0, 0, 0, 0.35);
}
</style>
