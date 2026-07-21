<template>
  <q-page class="q-pa-xs">
    <div class="row">
      <div class="col-12 col-md-4 q-pa-xs">
        <q-card flat bordered>
          <q-card-section class="q-pa-none">
            <q-item class="bg-green">
              <q-item-section avatar>
                <q-icon name="inventory_2" size="50px" color="white" />
              </q-item-section>
              <q-item-section>
                <q-item-label caption class="text-white">Compras Totales</q-item-label>
                <q-item-label class="text-white text-h4">{{ totalCompras }} Bs</q-item-label>
              </q-item-section>
            </q-item>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-4 q-pa-xs">
        <q-card flat bordered>
          <q-card-section class="q-pa-none">
            <q-item class="bg-negative">
              <q-item-section avatar>
                <q-icon name="cancel" size="50px" color="white" />
              </q-item-section>
              <q-item-section>
                <q-item-label caption class="text-white">Compras Anuladas</q-item-label>
                <q-item-label class="text-white text-h4">{{ totalAnuladas }} Bs</q-item-label>
              </q-item-section>
            </q-item>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-4 q-pa-xs">
        <q-card flat bordered>
          <q-card-section class="q-pa-none">
            <q-item class="bg-indigo">
              <q-item-section avatar>
                <q-icon name="inventory_2" size="50px" color="white" />
              </q-item-section>
              <q-item-section>
                <q-item-label caption class="text-white">Total Compras</q-item-label>
                <q-item-label class="text-white text-h4">
                  {{ (compras.length) }}
                </q-item-label>
              </q-item-section>
            </q-item>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <q-card flat bordered class="q-mt-sm">
      <q-card-section class="q-pa-none">
        <div class="row q-col-gutter-sm q-pa-sm">
          <div class="col-12 col-md-2">
            <q-input v-model="fechaInicio" label="Fecha inicio" dense outlined type="date" />
          </div>
          <div class="col-12 col-md-2">
            <q-input v-model="fechaFin" label="Fecha fin" dense outlined type="date" />
          </div>
          <div class="col-12 col-md-2">
            <q-select v-model="user" :options="usersTodos" label="Usuario" dense outlined emit-value map-options/>
          </div>
          <div class="col-12 col-md-2">
            <q-btn color="primary" label="Buscar" icon="search" @click="comprasGet" :loading="loading" no-caps />
          </div>
<!--           btn crear-->
          <div class="col-12 col-md-2">
            <q-btn color="positive" label="Compra" icon="add_circle_outline" @click="$router.push({ name: 'compras-create' })" no-caps :loading="loading" />
          </div>
          <div class="col-12 col-md-2">
            <q-btn color="primary" label="Excel" icon="file_download" @click="excel" no-caps :loading="loading" />
          </div>
        </div>
      </q-card-section>
    </q-card>

    <q-markup-table dense wrap-cells>
      <thead>
      <tr class="bg-primary text-white">
        <th>Acciones</th>
        <th>ID</th>
        <th>Fecha</th>
        <th>Factura</th>
        <th>Proveedor</th>
        <th>Usuario</th>
        <th>Estado</th>
        <th>Total</th>
        <th>Detalle</th>
        <th>Pago</th>
      </tr>
      </thead>
      <tbody>
      <tr v-for="compra in compras" :key="compra.id">
        <td>
          <q-btn-dropdown color="primary" label="Opciones" no-caps dense size="10px">
            <q-item clickable @click="imprimir(compra)" v-close-popup>
              <q-item-section avatar><q-icon name="print" /></q-item-section>
              <q-item-section>Imprimir</q-item-section>
            </q-item>
            <q-item clickable @click="verDatos(compra)" v-close-popup>
              <q-item-section avatar><q-icon name="visibility" /></q-item-section>
              <q-item-section>Ver datos</q-item-section>
            </q-item>
            <q-item clickable @click="openEditar(compra)" v-close-popup>
              <q-item-section avatar><q-icon name="edit" /></q-item-section>
              <q-item-section>Actualizar datos</q-item-section>
            </q-item>
            <q-item clickable @click="anular(compra.id)" v-close-popup>
              <q-item-section avatar><q-icon name="delete" /></q-item-section>
              <q-item-section>Anular</q-item-section>
            </q-item>
          </q-btn-dropdown>
        </td>
        <td>{{ compra.id }}</td>
        <td>{{ compra.fecha_hora || (compra.fecha + ' ' + compra.hora) }}</td>
        <td>{{ compra.nro_factura }}</td>
        <td>{{ compra.proveedor?.nombre }}</td>
        <td>{{ compra.user?.name }}</td>
        <td><q-chip :color="compra.estado === 'Activo' ? 'positive' : 'negative'" class="text-white" dense>{{ compra.estado }}</q-chip></td>
        <td class="text-bold">{{ compra.total }} Bs</td>
        <td>
          <div style="max-width: 200px; line-height: 1.1;">
            {{ compra.detailsText }}
          </div>
        </td>
        <td><q-chip :color="compra.tipo_pago === 'Efectivo' ? 'green' : 'blue'" class="text-white" dense>{{ compra.tipo_pago }}</q-chip></td>
      </tr>
      </tbody>
    </q-markup-table>

    <q-dialog v-model="verDatosDialog">
      <q-card style="width: 700px; max-width: 95vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Datos de compra #{{ compraSeleccionada?.id }}</div>
          <q-space />
          <q-btn flat round dense icon="close" @click="verDatosDialog = false" />
        </q-card-section>

        <q-card-section v-if="compraSeleccionada">
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-md-6"><b>Fecha:</b> {{ compraSeleccionada.fecha_hora || (compraSeleccionada.fecha + ' ' + compraSeleccionada.hora) }}</div>
            <div class="col-12 col-md-6"><b>Proveedor:</b> {{ compraSeleccionada.proveedor?.nombre || compraSeleccionada.nombre || '-' }}</div>
            <div class="col-12 col-md-6"><b>CI:</b> {{ compraSeleccionada.ci || '-' }}</div>
            <div class="col-12 col-md-6"><b>Pago:</b> {{ compraSeleccionada.tipo_pago || '-' }}</div>
            <div class="col-12 col-md-6"><b>Factura Nro:</b> {{ compraSeleccionada.nro_factura || '-' }}</div>
            <div class="col-12 col-md-6"><b>Facturado:</b> {{ compraSeleccionada.facturado ? 'SI' : 'NO' }}</div>
            <div class="col-12 col-md-6"><b>Agencia:</b> {{ compraSeleccionada.agencia || '-' }}</div>
            <div class="col-12 col-md-6"><b>Total:</b> {{ compraSeleccionada.total }} Bs</div>

            <div class="col-12">
              <div class="text-subtitle2 q-mb-xs">Foto / Factura</div>
              <div v-if="compraSeleccionada.foto">
                <q-img
                  :src="imageUrl(compraSeleccionada.foto)"
                  style="max-width: 360px; border-radius: 8px; cursor: pointer;"
                  fit="contain"
                  @click="openFoto(compraSeleccionada.foto)"
                />
              </div>
              <div v-else class="text-grey-7">Sin foto adjunta</div>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <q-dialog v-model="fotoDialog" maximized>
      <q-card style="width: 100vw; height: 100vh;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-subtitle1">Factura / Foto</div>
          <q-space />
          <q-btn flat round dense icon="close" @click="fotoDialog = false" />
        </q-card-section>
        <q-card-section>
          <q-img :src="fotoDialogUrl" style="width: 100%; height: calc(100vh - 90px);" fit="contain" />
        </q-card-section>
      </q-card>
    </q-dialog>

    <q-dialog v-model="editarDialog" persistent>
      <q-card style="width: 520px; max-width: 95vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Actualizar datos compra #{{ editarCompra.id || '' }}</div>
          <q-space />
          <q-btn flat round dense icon="close" @click="editarDialog = false" />
        </q-card-section>

        <q-card-section>
          <q-form @submit="guardarEdicionCompra">
            <q-input v-model="editarCompra.nro_factura" label="Nro. factura" dense outlined class="q-mb-sm" />
            <div class="q-mb-sm">
              <q-toggle v-model="editarCompra.facturado" label="Facturado">
                <q-tooltip>
                  Facturado: {{ editarCompra.facturado ? 'Si' : 'No' }}
                </q-tooltip>
              </q-toggle>
            </div>

            <div class="q-mb-sm">
              <q-btn flat color="primary" icon="photo_camera" label="Cambiar foto" no-caps @click="$refs.editarFotoInput.click()" />
              <input ref="editarFotoInput" type="file" accept="image/*" style="display:none" @change="onEditarFotoChange" />
              <div v-if="editarFotoName" class="text-caption q-mt-xs">{{ editarFotoName }}</div>
            </div>

            <div v-if="editarFotoPreview || editarCompra.foto">
              <q-img :src="editarFotoPreview || imageUrl(editarCompra.foto)" style="max-width: 240px; border-radius: 8px;" fit="contain" />
            </div>
            <div class="q-mt-sm" v-if="editarFotoPreview || editarCompra.foto">
              <q-btn flat color="primary" icon="visibility" label="Ver foto" no-caps @click="openFoto(editarFotoPreview || editarCompra.foto, !!editarFotoPreview)" />
              <q-btn flat color="negative" icon="delete" label="Quitar foto" no-caps @click="quitarFotoEdicion" class="q-ml-sm" />
            </div>

            <div class="text-right q-mt-md">
              <q-btn flat label="Cancelar" color="grey-8" @click="editarDialog = false" :loading="loading" />
              <q-btn color="primary" label="Guardar" type="submit" class="q-ml-sm" :loading="loading" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
  <div id="myElement" class="hidden"></div>
</template>

<script>
import moment from 'moment'
import {Imprimir} from "src/addons/Imprimir";
import {Excel} from "src/addons/Excel";
export default {
  data() {
    return {
      compras: [],
      fechaInicio: moment().format('YYYY-MM-DD'),
      fechaFin: moment().format('YYYY-MM-DD'),
      user: '',
      users: [],
      loading: false,
      verDatosDialog: false,
      compraSeleccionada: null,
      fotoDialog: false,
      fotoDialogUrl: '',
      editarDialog: false,
      editarCompra: {
        id: null,
        nro_factura: '',
        facturado: false,
        foto: '',
      },
      editarFotoFile: null,
      editarFotoName: '',
      editarFotoPreview: '',
      removeFoto: false,
    }
  },
  mounted() {
    this.comprasGet()
    this.usersGet()
  },
  computed: {
    usersTodos() {
      return [{ label: 'Todos', value: '' }, ...this.users.map(u => ({ label: u.name, value: u.id }))]
    },
    totalCompras() {
      return this.compras.reduce((sum, c) => c.estado === 'Activo' ? sum + parseFloat(c.total) : sum, 0).toFixed(2)
    },
    totalAnuladas() {
      return this.compras.reduce((sum, c) => c.estado === 'Anulado' ? sum + parseFloat(c.total) : sum, 0).toFixed(2)
    }
  },
  methods: {
    imageUrl(path) {
      if (!path) return ''
      return `${this.$url}../${path}`
    },
    verDatos(compra) {
      this.compraSeleccionada = compra
      this.verDatosDialog = true
    },
    openEditar(compra) {
      this.editarCompra = {
        id: compra.id,
        nro_factura: compra.nro_factura || '',
        facturado: !!compra.facturado,
        foto: compra.foto || '',
      }
      this.editarFotoFile = null
      this.editarFotoName = ''
      this.editarFotoPreview = ''
      this.removeFoto = false
      this.editarDialog = true
    },
    onEditarFotoChange(e) {
      const file = e.target.files?.[0]
      this.editarFotoFile = file || null
      this.editarFotoName = file ? file.name : ''
      this.removeFoto = false
      if (this.editarFotoPreview) URL.revokeObjectURL(this.editarFotoPreview)
      this.editarFotoPreview = file ? URL.createObjectURL(file) : ''
    },
    quitarFotoEdicion() {
      this.editarFotoFile = null
      this.editarFotoName = ''
      if (this.editarFotoPreview) URL.revokeObjectURL(this.editarFotoPreview)
      this.editarFotoPreview = ''
      this.editarCompra.foto = ''
      this.removeFoto = true
    },
    guardarEdicionCompra() {
      if (!this.editarCompra.id) return
      this.loading = true
      const fd = new FormData()
      fd.append('_method', 'PUT')
      fd.append('nro_factura', this.editarCompra.nro_factura || '')
      fd.append('facturado', this.editarCompra.facturado ? '1' : '0')
      if (this.editarFotoFile) fd.append('foto', this.editarFotoFile)
      if (this.removeFoto) fd.append('remove_foto', '1')

      this.$axios.post(`compras/${this.editarCompra.id}/datos`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
      }).then(() => {
        this.$alert.success('Datos de compra actualizados')
        this.editarDialog = false
        this.comprasGet()
      }).catch(err => {
        this.$alert.error(err.response?.data?.message || 'No se pudo actualizar la compra')
      }).finally(() => {
        this.loading = false
      })
    },
    openFoto(path, isAbsolute = false) {
      this.fotoDialogUrl = isAbsolute ? path : this.imageUrl(path)
      this.fotoDialog = true
    },
    excel() {
      const data = [{
        columns: [
          { label: "ID", value: "id" },
          { label: "Fecha", value: row => row.fecha_hora || `${row.fecha} ${row.hora}` },
          { label: "Proveedor", value: row => row.proveedor?.nombre || row.nombre || '' },
          { label: "Usuario", value: row => row.user?.name || '' },
          { label: "Estado", value: "estado" },
          { label: "Total (Bs)", value: "total" },
          { label: "Detalle", value: "detailsText" },
          { label: "Tipo de pago", value: "tipo_pago" },
          { label: "Factura", value: "nro_factura" }
        ],
        content: this.compras
      }]
      Excel.export(data, `Compras_${this.fechaInicio}_a_${this.fechaFin}`)
    },
    comprasGet() {
      this.loading = true
      this.$axios.get('compras', {
        params: {
          fechaInicio: this.fechaInicio,
          fechaFin: this.fechaFin,
          user: this.user
        }
      }).then(res => {
        this.compras = res.data
      }).catch(err => {
        this.$alert.error(err.response?.data?.message || 'Error al obtener compras')
      }).finally(() => {
        this.loading = false
      })
    },
    usersGet() {
      this.$axios.get('users').then(res => {
        console.log(res.data)
        this.users = res.data
      })
    },
    imprimir(compra) {
      Imprimir.reciboCompra(compra)
    },
    anular(id) {
      this.$alert.dialog('¿Anular esta compra?').onOk(() => {
        this.loading = true
        this.$axios.put(`comprasAnular/${id}`).then(() => {
          this.$alert.success('Compra anulada')
          this.comprasGet()
        })
      })
    }
  }
}
</script>
