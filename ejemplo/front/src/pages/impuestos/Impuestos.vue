<template>
  <q-page class="q-pa-xs">
    <q-card flat bordered class="q-mb-sm">
      <q-card-section class="row items-center q-col-gutter-sm">
        <div class="col-12 col-md-6">
          <div class="text-h6">Impuestos - Ajustes CUFD</div>
          <div class="text-caption text-grey-7">
            Programado diariamente a las {{ estado.hora_programada || '09:00' }} ({{ estado.zona_horaria || '-' }})
          </div>
        </div>
        <div class="col-12 col-md-6 text-right">
          <q-btn color="primary" label="Generar CUI" icon="add" no-caps @click="generarCUI" :loading="loading" />
          <q-btn color="secondary" label="Generar CUFD" icon="add" no-caps class="q-ml-sm" @click="generarCUFD" :loading="loading" />
          <q-btn color="orange" label="Reintentar CUFD" icon="refresh" no-caps class="q-ml-sm" @click="reintentarCUFD" :loading="loading" />
        </div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <q-chip color="negative" text-color="white" class="q-mr-sm">
          Fallas pendientes: {{ estado.fallas_pendientes || 0 }}
        </q-chip>
        <q-chip v-if="estado.cufd_actual" color="green" text-color="white">
          CUFD vigente ID: {{ estado.cufd_actual.id }}
        </q-chip>
        <q-chip v-else color="orange" text-color="white">
          Sin CUFD vigente
        </q-chip>
      </q-card-section>
    </q-card>

    <q-card flat bordered class="q-mb-sm">
      <q-card-section class="q-pa-xs">
        <div class="text-subtitle1 text-weight-bold q-mb-sm">Historial CUFD</div>
        <q-markup-table dense wrap-cells flat bordered>
          <thead>
          <tr>
            <th>ID</th>
            <th>Codigo Control</th>
            <th>Fecha Vigencia</th>
            <th>Fecha Creacion</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="cufd in cufds" :key="cufd.id">
            <td>{{ cufd.id }}</td>
            <td>{{ cufd.codigoControl }}</td>
            <td>{{ cufd.fechaVigencia }}</td>
            <td>{{ cufd.fechaCreacion }}</td>
          </tr>
          </tbody>
        </q-markup-table>
      </q-card-section>
    </q-card>

    <q-card flat bordered>
      <q-card-section class="q-pa-xs">
        <div class="text-subtitle1 text-weight-bold q-mb-sm">Fallas de generacion CUFD</div>
        <q-markup-table dense wrap-cells flat bordered>
          <thead>
          <tr>
            <th>ID</th>
            <th>Estado</th>
            <th>Mensaje</th>
            <th>Detalle</th>
            <th>Fecha</th>
            <th>Acciones</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="falla in fallas" :key="falla.id">
            <td>{{ falla.id }}</td>
            <td>
              <q-chip
                dense
                :color="falla.estado === 'pendiente' ? 'negative' : (falla.estado === 'resuelto' ? 'green' : 'grey')"
                text-color="white"
              >
                {{ falla.estado }}
              </q-chip>
            </td>
            <td>{{ falla.mensaje }}</td>
            <td style="max-width: 300px; white-space: normal;">
              {{ falla.detalle?.error || '-' }}
            </td>
            <td>{{ falla.fecha_evento }}</td>
            <td>
              <q-btn
                size="xs"
                color="green"
                flat
                no-caps
                label="Resolver"
                class="q-mr-xs"
                @click="resolverFalla(falla)"
                v-if="falla.estado === 'pendiente'"
              />
              <q-btn
                size="xs"
                color="orange"
                flat
                no-caps
                label="Ocultar"
                class="q-mr-xs"
                @click="ocultarFalla(falla)"
                v-if="falla.estado === 'pendiente'"
              />
              <q-btn
                size="xs"
                color="negative"
                flat
                no-caps
                label="Borrar"
                @click="eliminarFalla(falla)"
              />
            </td>
          </tr>
          <tr v-if="fallas.length === 0">
            <td colspan="6" class="text-center text-grey-7">Sin fallas registradas</td>
          </tr>
          </tbody>
        </q-markup-table>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script>
export default {
  name: 'ImpuestosPage',
  data () {
    return {
      loading: false,
      cufds: [],
      fallas: [],
      estado: {
        hora_programada: '09:00',
        zona_horaria: '',
        cufd_actual: null,
        fallas_pendientes: 0
      }
    }
  },
  mounted () {
    this.cargarTodo()
  },
  methods: {
    cargarTodo () {
      this.listCUFD()
      this.listFallas()
      this.estadoAuto()
    },
    estadoAuto () {
      this.$axios.get('impuestos/auto-cufd/estado')
        .then(({ data }) => {
          this.estado = data || this.estado
        })
        .catch((error) => {
          this.$alert.error(error.response?.data?.message || 'Error al obtener estado automatico')
        })
    },
    listCUFD () {
      this.loading = true
      this.$axios.get('impuestos/list-cufd').then(response => {
        this.cufds = response.data
      }).catch(error => {
        this.$alert.error(error.response?.data?.message || 'Error al listar CUFD')
      }).finally(() => {
        this.loading = false
      })
    },
    listFallas () {
      this.$axios.get('impuestos/fallas')
        .then(({ data }) => {
          this.fallas = data?.data || []
        })
        .catch(error => {
          this.$alert.error(error.response?.data?.message || 'Error al listar fallas')
        })
    },
    generarCUI () {
      this.loading = true
      this.$axios.post('impuestos/generar-cui').then(() => {
        this.$alert.success('CUI generado correctamente')
      }).catch(error => {
        this.$alert.error(error.response?.data?.message || 'Error al generar CUI')
      }).finally(() => {
        this.loading = false
        this.cargarTodo()
      })
    },
    generarCUFD () {
      this.loading = true
      this.$axios.post('impuestos/generar-cufd').then((response) => {
        const msg = response.data?.success || response.data?.message || 'CUFD procesado correctamente'
        this.$alert.success(msg)
      }).catch(error => {
        this.$alert.error(error.response?.data?.message || 'Error al generar CUFD')
      }).finally(() => {
        this.loading = false
        this.cargarTodo()
      })
    },
    reintentarCUFD () {
      this.loading = true
      this.$axios.post('impuestos/reintentar-cufd').then((response) => {
        this.$alert.success(response.data?.message || 'Reintento CUFD ejecutado')
      }).catch(error => {
        this.$alert.error(error.response?.data?.message || 'Error al reintentar CUFD')
      }).finally(() => {
        this.loading = false
        this.cargarTodo()
      })
    },
    resolverFalla (falla) {
      this.$axios.put(`impuestos/fallas/${falla.id}/resolver`).then(() => {
        this.$alert.success('Falla resuelta')
        this.cargarTodo()
      }).catch(error => {
        this.$alert.error(error.response?.data?.message || 'No se pudo resolver la falla')
      })
    },
    ocultarFalla (falla) {
      this.$axios.put(`impuestos/fallas/${falla.id}/ocultar`).then(() => {
        this.$alert.success('Falla ocultada')
        this.cargarTodo()
      }).catch(error => {
        this.$alert.error(error.response?.data?.message || 'No se pudo ocultar la falla')
      })
    },
    eliminarFalla (falla) {
      this.$axios.delete(`impuestos/fallas/${falla.id}`).then(() => {
        this.$alert.success('Falla eliminada')
        this.cargarTodo()
      }).catch(error => {
        this.$alert.error(error.response?.data?.message || 'No se pudo eliminar la falla')
      })
    }
  }
}
</script>
