<template>
  <q-page class="q-pa-md">
    <div class="row q-col-gutter-md">
      <!-- Izquierda: búsqueda / clientes -->
      <div class="col-12 col-md-5">
        <q-card flat bordered>
          <q-card-section class="row items-center">
            <div class="text-h6">Clientes</div>
            <q-space />
            <q-input
              dense outlined debounce="350"
              v-model="search"
              placeholder="Buscar por nombre, Cod_Aut o CI"
              @update:model-value="page = 1; fetchClientes()"
              class="q-ml-sm"
            >
              <template #append><q-icon name="search"/></template>
            </q-input>
          </q-card-section>

          <q-separator />

          <q-card-section>
            <q-table
              :rows="clientes"
              :columns="colsClientes"
              row-key="Cod_Aut"
              flat bordered dense hide-bottom
              :loading="loadingClientes"
              :rows-per-page-options="[0]"
            >
              <template #body-cell-opciones="props">
                <q-td :props="props">
                  <q-btn size="sm" color="primary" icon="photo" label="Fotos"
                         @click="selectCliente(props.row)"/>
                </q-td>
              </template>
            </q-table>

            <div class="q-mt-sm row justify-between items-center">
              <q-btn dense flat icon="chevron_left" :disable="!pagination.prev" @click="goPage('prev')"/>
              <div class="text-caption">Página {{ page }}</div>
              <q-btn dense flat icon="chevron_right" :disable="!pagination.next" @click="goPage('next')"/>
            </div>
          </q-card-section>
        </q-card>
      </div>

      <!-- Derecha: fotos del cliente -->
      <div class="col-12 col-md-7" v-if="clienteSel">
        <q-card flat bordered>
          <q-card-section class="row items-center">
            <div class="text-h6">
              Fotos — #{{ clienteSel.Cod_Aut }} · {{ clienteSel.Nombres }}
            </div>
            <q-space />
            <q-btn flat dense icon="refresh" @click="fetchFotos" :loading="loadingFotos"/>
          </q-card-section>

          <q-separator />

          <q-card-section class="q-gutter-md">
            <!-- Cargador con QFile -->
            <q-banner class="bg-grey-2 text-dark">
              Sube una o varias imágenes. Se comprimen en el servidor (JPG 85%, máx 1600px).
            </q-banner>

            <q-file
              ref="qfile"
              v-model="files"
              multiple
              use-chips
              outlined
              clearable
              accept="image/*"
              counter
              :max-file-size="5*1024*1024"
              label="Seleccionar imágenes (máx 5MB c/u)"
            >
              <template #prepend>
                <q-icon name="photo_camera" />
              </template>
              <template #append>
                <q-btn flat dense icon="folder_open" @click="$refs.qfile.pickFiles()" />
              </template>
            </q-file>

            <!-- Grid de previews locales (antes de subir) -->
            <div v-if="files.length" class="row q-col-gutter-sm">
              <div v-for="(f, i) in files" :key="i" class="col-6 col-sm-4 col-md-3">
                <q-card flat bordered class="overflow-hidden">
                  <q-img :src="fileUrl(f)" :ratio="1" />
                  <q-item dense class="q-pa-xs">
                    <q-item-section>
                      <div class="text-caption ellipsis">{{ f.name }}</div>
                    </q-item-section>
                    <q-item-section side>
                      <q-btn flat dense round icon="close" @click="removeTemp(i)"/>
                    </q-item-section>
                  </q-item>
                </q-card>
              </div>
            </div>

            <div class="row justify-end">
              <q-btn
                color="primary"
                icon="cloud_upload"
                label="Subir"
                :disable="files.length===0"
                :loading="loadingUpload"
                @click="upload"
              />
            </div>

            <q-separator />

            <!-- Galería del cliente (fotos ya guardadas) -->
            <div class="text-subtitle2">Galería</div>
            <div class="row q-col-gutter-sm q-mt-xs">
              <div v-for="p in fotos" :key="p.id" class="col-6 col-sm-4 col-md-3">
                <q-card flat bordered class="overflow-hidden">
                  <q-img :src="p.url" :ratio="1" spinner-color="primary" />
                  <q-card-actions align="between">
                    <q-btn dense flat icon="open_in_new" @click="openFull(p.url)" />
                    <q-btn dense flat color="negative" icon="delete"
                           :loading="removingId===p.id" @click="removeFoto(p.id)"/>
                  </q-card-actions>
                </q-card>
              </div>

              <div v-if="!loadingFotos && fotos.length===0" class="col-12">
                <q-banner class="bg-grey-2 text-grey-8">
                  Aún no hay fotos para este cliente.
                </q-banner>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script>
export default {
  name: 'ClienteFotografias',
  data () {
    return {
      // clientes
      search: '',
      clientes: [],
      loadingClientes: false,
      page: 1,
      pagination: { next: null, prev: null },

      // selección
      clienteSel: null,

      // fotos
      fotos: [],
      loadingFotos: false,
      loadingUpload: false,
      removingId: null,

      // q-file model
      files: [],

      colsClientes: [
        { label: 'Opciones', name: 'opciones', field: 'opciones', align: 'left' },
        { label: 'Cod', name: 'Cod_Aut', field: 'Cod_Aut', align: 'left' },
        { label: 'Nombre', name: 'Nombres', field: 'Nombres', align: 'left' },
        { label: 'CI', name: 'Id', field: 'Id', align: 'left' },
        { label: 'Tel', name: 'Telf', field: 'Telf', align: 'left' },
      ],
    }
  },
  created () {
    this.fetchClientes()
  },
  methods: {
    async fetchClientes () {
      this.loadingClientes = true
      try {
        const { data } = await this.$api.get('cliente-photos', {
          params: { search: this.search, page: this.page }
        })
        this.clientes = data.data || []
        this.pagination.next = data.next_page_url
        this.pagination.prev = data.prev_page_url
      } finally {
        this.loadingClientes = false
      }
    },
    goPage (dir) {
      if (dir === 'next' && this.pagination.next) {
        this.page += 1
        this.fetchClientes()
      } else if (dir === 'prev' && this.pagination.prev && this.page > 1) {
        this.page -= 1
        this.fetchClientes()
      }
    },
    selectCliente (row) {
      this.clienteSel = row
      this.fetchFotos()
    },
    async fetchFotos () {
      if (!this.clienteSel) return
      this.loadingFotos = true
      try {
        const { data } = await this.$api.get('cliente-photos', {
          params: { cliente_id: this.clienteSel.Cod_Aut }
        })
        this.fotos = data
      } finally {
        this.loadingFotos = false
      }
    },
    fileUrl (f) {
      return URL.createObjectURL(f)
    },
    removeTemp (idx) {
      this.files.splice(idx, 1)
    },
    async upload () {
      if (!this.clienteSel || this.files.length === 0) return
      this.loadingUpload = true
      try {
        const fd = new FormData()
        fd.append('cliente_id', this.clienteSel.Cod_Aut)
        // QFile entrega File[] nativos
        this.files.forEach(f => fd.append('photos[]', f))

        await this.$api.post('cliente-photos', fd, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })

        this.$q.notify({ type: 'positive', message: 'Fotos subidas' })
        this.files = []
        // this.$refs.qfile.reset()
        await this.fetchFotos()
      } catch (e) {
        this.$q.notify({ type: 'negative', message: e?.response?.data?.message || 'Error al subir' })
      } finally {
        this.loadingUpload = false
      }
    },
    async removeFoto (id) {
      this.removingId = id
      try {
        await this.$api.delete(`cliente-photos/${id}`)
        this.fotos = this.fotos.filter(x => x.id !== id)
        this.$q.notify({ type: 'positive', message: 'Foto eliminada' })
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'No se pudo eliminar' })
      } finally {
        this.removingId = null
      }
    },
    openFull (url) {
      window.open(url, '_blank')
    }
  }
}
</script>
