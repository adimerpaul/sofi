<template>
  <q-page class="q-pa-none">
    <!-- Filtro / fecha -->
    <div class="row q-col-gutter-sm q-pa-sm items-end">
      <div class="col-12 col-sm-6">
        <q-form @submit.prevent="misclientes">
          <div class="row q-col-gutter-sm">
            <div class="col-7 col-sm-8">
              <q-input
                v-model="fecha"
                type="date"
                dense outlined
                label="Fecha"
                :max="hoy"
                hide-bottom-space
              />
            </div>
            <div class="col-5 col-sm-4">
              <q-btn
                class="full-width"
                type="submit"
                icon="search"
                label="Buscar"
                color="primary"
                unelevated
              />
            </div>
          </div>
        </q-form>
      </div>

      <div class="col-12 col-sm-6">
        <div class="row q-col-gutter-sm">
          <div class="col-4 col-sm-3">
            <q-btn
              class="full-width"
              icon="my_location"
              size="sm"
              color="primary"
              flat
              label="Yo"
              @click="getUserPosition"
            />
          </div>
          <div class="col-4 col-sm-3">
            <q-btn
              class="full-width"
              icon="location_on"
              size="sm"
              color="secondary"
              flat
              label="Centro"
              @click="getCentro"
            />
          </div>
          <div class="col-12 col-sm-6">
            <q-input
              v-model="filter"
              filled dense
              placeholder="Buscar cliente…"
              debounce="300"
              clearable
            >
              <template #append>
                <q-icon name="search" />
              </template>
            </q-input>
          </div>
        </div>
      </div>
    </div>

    <!-- Mapa -->
    <div class="q-px-sm q-mb-sm">
      <div class="map-wrapper shadow-1">
        <l-map
          @ready="onReady"
          @locationfound="onLocationFound"
          v-model="zoom"
          :zoom="zoom"
          :center="center"
        >
          <l-tile-layer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
          <l-marker
            v-for="(c,i) in clientesConCoord"
            :key="c.Cod_Aut"
            :lat-lng="[c.Latitud, c.longitud]"
            @click="clickopciones(c)"
          >
            <l-icon >
              <q-badge
                :class="c.estado=='ENTREGADO'?'bg-green text-italic':c.estado=='NO ENTREGADO'?'bg-yellow text-italic':c.estado=='RECHAZADO'?'bg-red text-italic':'bg-blue'"
                style="padding: 2px"

              >{{i+1}}
              </q-badge>
            </l-icon>
          </l-marker>
        </l-map>
      </div>
    </div>

    <!-- Tabla -->
    <div class="q-px-sm q-pb-md">
      <q-card flat bordered class="section-card">
        <q-table
          :rows="clientes"
          :columns="columns"
          :filter="filter"
          row-key="Cod_Aut"
          hide-header
          :rows-per-page-options="[0]"
          class="my-sticky-header-table"
          :virtual-scroll="clientes.length > 40"
          @row-click="(_, row) => clickopciones(row)"
        >
          <template #body-cell-Nombres="props">
            <q-td
              :props="props"
              :class="rowClass(props.row.estado)"
              class="cursor-pointer"
            >
              <div class="text-body2 text-weight-medium">
                {{ props.pageIndex + 1 }} · {{ props.row.Nombres }}
              </div>
              <div class="text-caption ellipsis-2-lines">
                {{ props.row.Direccion }}
              </div>
              <q-chip
                size="xs"
                class="q-mt-xs"
                :color="chipColor(props.row.estado)"
                text-color="white"
                square
              >
                {{ props.row.estado || 'PENDIENTE' }}
              </q-chip>
            </q-td>
          </template>

          <template #body-cell-opcion="props">
            <q-td :props="props" :class="rowClass(props.row.estado)">
              <q-btn
                size="sm"
                round
                :color="btnColor(props.row.estado)"
                icon="my_location"
                @click.stop="clickclientes(props.row)"
              />
            </q-td>
          </template>

          <template #no-data>
            <div class="full-width q-pa-lg flex flex-center text-grey-7">
              <q-icon name="inbox" class="q-mr-sm" />
              No hay resultados para la fecha seleccionada.
            </div>
          </template>
          <template #bottom>
            <q-tr>
              <q-td colspan="100%">
                <div class="text-right text-caption text-grey-7">
                  Total clientes: {{ clientes.length }}
                </div>
              </q-td>
            </q-tr>
          </template>
        </q-table>
      </q-card>
    </div>

    <!-- Diálogo -->
    <q-dialog v-model="dialogentrega" full-width transition-show="slide-up" transition-hide="slide-down">
      <q-card class="dialog-card">
        <q-card-section class="row items-center q-gutter-sm">
          <q-icon name="local_shipping" size="md" />
          <div class="col grow">
            <div class="text-subtitle1 text-weight-medium">{{ cliente.Nombres || 'Cliente' }}</div>
            <div class="text-caption text-grey-7">
              {{ cliente.Direccion }} · {{ cliente.Telf }}
            </div>
          </div>
          <div>
            <q-btn
              type="a"
              target="_blank"
              :href="linkGoogleMaps(cliente)"
              icon="map"
              color="accent"
              dense
              label="Abrir mapa"
              no-caps
            />
            <br>
            <br>
            <q-btn
              icon="feedback"
              color="primary"
              dense
              @click="openEncuesta()"
              label="Encuesta"
              no-caps
            />
          </div>
        </q-card-section>

        <q-separator />

        <q-card-section class="q-pt-sm">
          <!-- Prestamo / devolucion -->
          <div class="text-subtitle2 q-mb-sm">Préstamo / devolución de canastillos</div>
          <div class="row q-col-gutter-sm">
            <div class="col-6 col-sm-3">
              <q-input
                v-model.number="prestamo.ingreso"
                type="number"
                dense outlined
                label="Préstamo"
                min="0"
                step="1"
                hide-bottom-space
              />
            </div>
            <div class="col-6 col-sm-3">
              <q-input
                v-model.number="prestamo.salida"
                type="number"
                dense outlined
                label="Devolución"
                min="0"
                step="1"
                hide-bottom-space
              />
            </div>
            <div class="col-12 col-sm-6 flex items-end">
              <q-btn
                class="q-ml-none q-mt-sm q-mt-sm-sm"
                color="positive"
                icon="unarchive"
                label="Registrar"
                no-caps
                :disable="!puedeRegistrarPrestamo"
                @click="regPrestamo"
              />
            </div>
          </div>

          <q-separator spaced />

          <!-- Entrega -->
          <div class="row items-center q-mb-sm">
            <div class="col">
              <div class="text-subtitle1">Entrega de comandas</div>
            </div>
            <div class="col-auto">
              <q-badge outline color="primary">
                {{ listado.length }} seleccionados
              </q-badge>
            </div>
          </div>

          <q-input
            v-model="observacion"
            type="textarea"
            dense outlined autogrow
            label="Observación"
            placeholder="Notas de entrega, referencias, etc."
            class="q-mb-sm"
          />

          <q-table
            dense
            :rows="pedidos"
            :columns="columspedido"
            row-key="comanda"
            selection="multiple"
            v-model:selected="listado"
            :rows-per-page-options="[0]"
            flat bordered
            :wrap-cells="false"
            class="table-pedidos"
          >
            <template #body="props">
              <q-tr
                :props="props"
                :class="props.row.estado==='ENTREGADO' ? 'bg-green-1' : props.row.estado==='NO ENTREGADO' ? 'bg-amber-1' : ''"
              >
                <q-td auto-width>
                  <q-checkbox v-model="props.selected" dense />
                  <q-btn
                    size="sm"
                    :color="props.expand ? 'primary' : 'grey-7'"
                    :label="props.expand ? 'Ocultar' : 'Ver'"
                    no-caps dense flat
                    :icon="props.expand ? 'visibility_off' : 'visibility'"
                    class="q-ml-xs"
                    @click="props.expand = !props.expand"
                  />
                </q-td>

                <q-td v-for="col in props.cols" :key="col.name" :props="props" class="text-no-wrap">
                  <span v-if="col.name === 'Importe'">{{ money(col.value) }}</span>
                  <span v-else>{{ col.value }}</span>
                </q-td>

                <q-td auto-width>
                  <q-input
                    v-model.number="props.row.pago"
                    square outlined dense
                    type="number" step="0.01" min="0"
                    style="width:110px"
                  />
                </q-td>
              </q-tr>

              <q-tr v-show="props.expand" :props="props">
                <q-td colspan="100%">
                  <div class="column">
                    <div
                      class="text-left q-py-xs"
                      v-for="(r, idx) in props.row.detalle"
                      :key="idx"
                    >
                      <b>Código:</b> {{ r.cod_prod }}
                      &nbsp; <b>Producto:</b> {{ r.Producto }}
                      &nbsp; <b>Cant.:</b> {{ r.cant }}
                    </div>
                  </div>
                </q-td>
              </q-tr>
            </template>
<!--            bottom total sum -->
            <template #bottom>
              <q-tr>
                <q-td colspan="100%">
                  <div class="text-right text-caption text-grey-7">
                    Total comandas: {{ pedidos.length }} ·
                    Total importe: {{ money(pedidos.reduce((a,b) => a + (Number(b.Importe) || 0), 0)) }}
                  </div>
                </q-td>
              </q-tr>
            </template>
          </q-table>
        </q-card-section>

        <q-separator />

        <q-card-actions align="right" class="dialog-actions">
          <q-btn
            color="positive"
            dense no-caps
            label="Entregado"
            @click="createEntrega('ENTREGADO')"
            :disable="!puedeConfirmar"
          />
          <q-btn
            color="amber-8"
            dense no-caps
            label="Volver más tarde"
            @click="createEntrega('NO ENTREGADO')"
            :disable="!puedeConfirmar"
          />
          <q-btn
            color="negative"
            dense no-caps
            label="Rechazado"
            @click="createEntrega('RECHAZADO')"
            :disable="!puedeConfirmar"
          />
          <q-btn flat dense no-caps color="grey-8" label="Cerrar" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
    <q-dialog v-model="dialogQR" transition-show="scale" transition-hide="scale">
      <q-card class="q-pa-md" style="width: 360px; max-width: 90vw">
        <q-card-section class="text-center">
          <div class="text-subtitle1 text-weight-medium q-mb-xs">Encuesta de Satisfacción</div>
          <div class="text-caption text-grey-7 q-mb-md">
            Escanee el código o abra el enlace para responder.
          </div>
          <q-img
            :src="qrSrc"
            ratio="1"
            style="width: 220px; height: 220px; margin: 0 auto; border-radius: 12px;"
            spinner-color="primary"
          />
          <div class="q-mt-md">
            <q-input v-model="qrLink" dense readonly filled>
              <template #append>
                <q-btn round dense flat icon="content_copy" @click="copyEncuestaLink" :disable="!qrLink"/>
              </template>
            </q-input>
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cerrar" color="grey-8" v-close-popup />
          <q-btn :href="qrLink" target="_blank" label="Abrir" color="primary" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
// vue-leaflet
import {
  LMap,
  LIcon,
  LTileLayer,
  LMarker
} from "@vue-leaflet/vue-leaflet";
import "leaflet/dist/leaflet.css";

// quasar date
import { date } from "quasar";
const { formatDate } = date;

export default {
  name: "RutaMejorada",
  components: { LMap, LIcon, LTileLayer, LMarker },

  data () {
    const hoy = formatDate(new Date(), "YYYY-MM-DD");
    return {
      dialogQR: false,
      qrLink: "",
      qrSrc: "",
      prestamo: { ingreso: 0, salida: 0 },
      estado: "",
      observacion: "",
      listado: [],
      dialogentrega: false,
      center: [-17.970371, -67.112303],
      zoom: 16,
      filter: "",
      clientes: [],
      pedidos: [],
      cliente: {},
      columspedido: [
        { label: "Comanda",   name: "comanda",     field: "comanda", align: "left" },
        { label: "Importe",   name: "Importe",     field: "Importe", align: "right" },
        { label: "Tipo Pago", name: "Tipago",      field: "Tipago",  align: "left",
          style: "font-size:14px; font-weight:600;" },
        { label: "Estado",    name: "estado",      field: "estado",  align: "left" },
        { label: "Obs.",      name: "observacion", field: "observacion", align: "left" },
        { label: "Pago",      name: "pago" }
      ],
      columns: [
        { label: "Opción",  name: "opcion",  field: "opcion",  align: "left" },
        { label: "Nombres", name: "Nombres", field: "Nombres", align: "left" }
      ],
      fecha: hoy,
      hoy
    };
  },

  computed: {
    encuestaBase () {
      // Usa tu dominio si quieres forzar otro host:
      // return 'https://tudominio.com' // por ejemplo
      return window.location.origin
    },
    clientesConCoord () {
      // Filtra clientes con coordenadas válidas y normaliza
      return (this.clientes || []).map(c => {
        const lat = Number(c.Latitud);
        const lng = Number(c.longitud);
        return {
          ...c,
          Latitud: Number.isFinite(lat) ? lat : 0,
          longitud: Number.isFinite(lng) ? lng : 0
        };
      }).filter(c => c.Latitud !== 0 && c.longitud !== 0);
    },
    puedeRegistrarPrestamo () {
      return Number.isFinite(this.prestamo.ingreso)
        && Number.isFinite(this.prestamo.salida)
        && this.prestamo.ingreso >= 0
        && this.prestamo.salida >= 0
        && this.cliente?.Cod_Aut;
    },
    puedeConfirmar () {
      return this.listado.length > 0 && this.cliente?.Cod_Aut;
    }
  },

  async created () {
    // Si quieres fijar fecha por defecto:
    // this.fecha = '2025-09-01'
    await this.misclientes();
  },

  methods: {
    badgeClass (estado) {
      return {
        'marker-green': estado === 'ENTREGADO',
        'marker-amber': estado === 'NO ENTREGADO',
        'marker-red':   estado === 'RECHAZADO',
        'marker-blue':  !estado || (['ENTREGADO','NO ENTREGADO','RECHAZADO'].indexOf(estado) === -1)
      };
    },
    rowClass (estado) {
      if (estado === 'ENTREGADO') return 'bg-green-1';
      if (estado === 'NO ENTREGADO') return 'bg-amber-1';
      if (estado === 'RECHAZADO') return 'bg-red-1';
      return '';
      // Neutral por defecto
    },
    chipColor (estado) {
      if (estado === 'ENTREGADO') return 'green-7';
      if (estado === 'NO ENTREGADO') return 'amber-8';
      if (estado === 'RECHAZADO') return 'red-8';
      return 'blue-6';
    },
    btnColor (estado) {
      if (estado === 'ENTREGADO') return 'green';
      if (estado === 'NO ENTREGADO') return 'amber-10';
      return 'red-10';
    },
    money (n) {
      const v = Number(n);
      if (!Number.isFinite(v)) return n;
      return new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB', maximumFractionDigits: 2 }).format(v);
    },
    openEncuesta () {
      if (!this.cliente?.Cod_Aut) {
        this.$q.notify({ message: 'Seleccione un cliente válido', color: 'warning' });
        return;
      }
      // store user
      const userId = this.$store.getters['login/user'].CodAut
      console.log('User ID for survey link:', userId);
      // Construye el link encuestaIndex/<Cod_Aut>
      this.qrLink = `${this.encuestaBase}#/encuesta/${encodeURIComponent(this.cliente.Cod_Aut)}/${userId}`;

      // Genera QR mediante servicio liviano (sin libs)
      // Puedes cambiar size=220x220 o agregar margin & color si quieres
      const encoded = encodeURIComponent(this.qrLink);
      this.qrSrc = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encoded}`;

      this.dialogQR = true;
    },

    copyEncuestaLink () {
      if (!this.qrLink) return;
      navigator.clipboard.writeText(this.qrLink)
        .then(() => this.$q.notify({ message: 'Link copiado', color: 'positive', icon: 'content_copy' }))
        .catch(() => this.$q.notify({ message: 'No se pudo copiar', color: 'negative', icon: 'error' }));
    },
    linkGoogleMaps (c) {
      const lat = Number(c?.Latitud) || 0;
      const lng = Number(c?.longitud) || 0;
      return `https://www.google.com.bo/maps/place/${lat},${lng}/@${lat},${lng},17z`;
    },

    regPrestamo () {
      if (!this.puedeRegistrarPrestamo) {
        this.$q.notify({ message: 'Ingrese valores válidos', color: 'red', icon: 'error' });
        return;
      }

      this.$q.dialog({
        title: '¿Confirmar registro?',
        message: 'Se registrará el préstamo/devolución.',
        ok: { label: 'Registrar', color: 'green-7' },
        cancel: { label: 'Cancelar' },
        focus: 'cancel'
      }).onOk(() => {
        this.$q.loading.show();
        this.$api.post('prestamo', {
          cliente_id: this.cliente.Cod_Aut,
          cinit: this.cliente.Id,
          ingreso: this.prestamo.ingreso,
          salida: this.prestamo.salida
        }).then(() => {
          this.$q.notify({ message: 'Préstamo registrado', color: 'green', icon: 'check_circle' });
          this.prestamo = { ingreso: 0, salida: 0 };
        }).catch(() => {
          this.$q.notify({ message: 'No se pudo registrar', color: 'negative', icon: 'error' });
        }).finally(() => this.$q.loading.hide());
      });
    },

    createEntrega (estado) {
      if (!this.puedeConfirmar) {
        this.$q.notify({ message: 'Seleccione al menos una comanda', color: 'orange', icon: 'warning' });
        return;
      }

      this.$q.dialog({
        title: '¿Confirmar envío?',
        message: `Se guardará como "${estado}".`,
        ok: { label: 'Confirmar', color: 'primary' },
        cancel: { label: 'Cancelar' },
        focus: 'cancel'
      }).onOk(() => {
        this.$q.loading.show();

        const finish = (lat, lng) => this.insertarpedido(lat, lng, estado);

        if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(
            pos => finish(pos.coords.latitude, pos.coords.longitude),
            () => {
              this.$q.notify({ message: 'No se pudo obtener la ubicación. Se guardará sin coordenadas.', color: 'warning' });
              finish(0, 0);
            },
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
          );
        } else {
          finish(0, 0);
        }
      });
    },

    insertarpedido (lat, lng, esta) {
      this.$api.post('regTodo', {
        cliente_id: this.cliente.Cod_Aut,
        cinit: this.cliente.Id,
        listado: this.listado,
        lat,
        lng,
        estado: esta,
        fecha: this.fecha,
        observacion: this.observacion
      }).then(async () => {
        this.cliente = {};
        this.listado = [];
        this.observacion = '';
        this.dialogentrega = false;
        await this.misclientes();
        this.$q.notify({ message: 'Entrega registrada', color: 'positive', icon: 'check_circle' });
      }).catch(() => {
        this.$q.notify({ message: 'Error al registrar entrega', color: 'negative', icon: 'error' });
      }).finally(() => this.$q.loading.hide());
    },

    clickopciones (c) {
      this.estado = '';
      this.observacion = '';
      this.cliente = c;
      this.listado = [];

      this.$q.loading.show();
      this.$api.post('ruta', { id: c.Id, fecha: this.fecha })
        .then(res => {
          this.pedidos = (res.data || []).map(r => ({ ...r, pago: 0 }));
          this.dialogentrega = true;
        })
        .catch(() => {
          this.$q.notify({ message: 'No se pudieron cargar las comandas', color: 'negative' });
        })
        .finally(() => this.$q.loading.hide());
    },

    async getCentro () {
      this.center = [-17.970371, -67.112303];
    },

    async getUserPosition () {
      if (!navigator.geolocation) {
        this.$q.notify({ message: 'Geolocalización no disponible', color: 'warning' });
        return;
      }
      this.center = [0, 0];
      navigator.geolocation.getCurrentPosition(
        pos => { this.center = [pos.coords.latitude, pos.coords.longitude]; },
        () => { this.$q.notify({ message: 'No se pudo obtener tu ubicación', color: 'warning' }); }
      );
    },

    clickclientes (c) {
      const lat = Number(c.Latitud);
      const lng = Number(c.longitud);
      if (Number.isFinite(lat) && Number.isFinite(lng)) this.center = [lat, lng];
    },

    async misclientes () {
      this.$q.loading.show();
      this.$api.get(`ruta/${this.fecha}`)
        .then(res => {
          // Normaliza coordenadas
          this.clientes = (res.data || []).map(r => ({
            ...r,
            Latitud: Number(r.Latitud) || 0,
            longitud: Number(r.longitud) || 0
          }));
        })
        .catch(() => {
          this.$q.notify({ message: 'Error al conectarse al servidor', color: 'negative', icon: 'error' });
        })
        .finally(() => this.$q.loading.hide());
    },

    onReady (map) {
      try { map.locate(); } catch (e) {}
    },

    onLocationFound (location) {
      this.center = [location.latlng.lat, location.latlng.lng];
    }
  }
};
</script>

<style scoped>
.map-wrapper {
  height: 360px;
  width: 100%;
  border-radius: 14px;
  overflow: hidden;
  background: #f7f7f7;
}

.section-card {
  border-radius: 14px;
}

.dialog-card {
  max-width: 1200px;
  margin: 0 auto;
}

.dialog-actions {
  position: sticky;
  bottom: 0;
  background: white;
  z-index: 2;
  /* sutil sombra al pie para separar */
  box-shadow: 0 -6px 8px -8px rgba(0,0,0,.2);
}

.marker-wrapper {
  border-radius: 999px;
  box-shadow: 0 6px 14px rgba(0,0,0,.25);
  transform: translateY(-2px);
}
.marker-green  { background: linear-gradient(180deg, #A5D6A7, #66BB6A); }
.marker-amber  { background: linear-gradient(180deg, #FFE082, #FFB300); }
.marker-red    { background: linear-gradient(180deg, #EF9A9A, #E53935); }
.marker-blue   { background: linear-gradient(180deg, #90CAF9, #1E88E5); }

.ellipsis-2-lines {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
}

.table-pedidos :deep(.q-table__middle) {
  max-height: 48vh; /* hace el diálogo más usable en pantallas pequeñas */
  overflow: auto;
}
</style>
