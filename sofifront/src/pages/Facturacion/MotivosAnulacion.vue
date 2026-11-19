<template>
  <q-page class="q-pa-sm">
    <!-- Motivos que se eligen al anular una factura (Facturacion). En la factura
         se guarda solo el texto: editar o borrar no cambia lo ya anulado. -->
    <q-card flat bordered style="max-width: 720px">
      <q-card-section class="row items-center q-py-sm">
        <div>
          <div class="text-subtitle1 text-weight-bold">Motivos de anulación</div>
          <div class="text-caption text-grey-7">
            Al anular una factura es obligatorio elegir uno de los motivos activos.
          </div>
        </div>
        <q-space/>
        <q-btn dense no-caps unelevated color="primary" icon="add" label="Nuevo motivo" @click="abrir(null)"/>
      </q-card-section>

      <q-table flat dense :rows="filtrados" :columns="columnas" row-key="id" :loading="cargando"
               :rows-per-page-options="[0]" hide-bottom>
        <template v-slot:top>
          <q-input v-model="buscar" dense outlined clearable placeholder="Buscar…" style="width: 240px">
            <template v-slot:prepend><q-icon name="search"/></template>
          </q-input>
          <q-space/>
          <q-toggle v-model="soloActivos" dense label="Solo activos"/>
        </template>
        <template v-slot:body="props">
          <q-tr :props="props" :class="props.row.activo ? '' : 'text-grey-6'">
            <q-td key="nombre" :props="props">{{ props.row.nombre }}</q-td>
            <q-td key="activo" :props="props">
              <q-toggle :model-value="!!props.row.activo" dense color="positive"
                        :disable="guardandoId === props.row.id"
                        @update:model-value="v => cambiarActivo(props.row, v)"/>
              <span class="q-ml-xs">{{ props.row.activo ? 'Activo' : 'Inactivo' }}</span>
            </q-td>
            <q-td key="acciones" :props="props">
              <q-btn flat round dense size="sm" color="primary" icon="edit" @click="abrir(props.row)">
                <q-tooltip>Modificar</q-tooltip>
              </q-btn>
              <q-btn flat round dense size="sm" color="negative" icon="delete" @click="borrar(props.row)">
                <q-tooltip>Eliminar</q-tooltip>
              </q-btn>
            </q-td>
          </q-tr>
        </template>
        <template v-slot:no-data>
          <div class="full-width text-center text-grey-7">Sin motivos</div>
        </template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialogo">
      <q-card style="min-width: 340px">
        <q-card-section class="text-subtitle1 text-weight-bold q-pb-none">
          {{ form.id ? 'Modificar motivo' : 'Nuevo motivo' }}
        </q-card-section>
        <q-card-section>
          <q-input v-model="form.nombre" label="Motivo" dense outlined autofocus maxlength="150"
                   hint="Se guarda en mayúsculas" @keyup.enter="guardar"/>
          <q-toggle v-model="form.activo" class="q-mt-sm" label="Activo" color="positive"/>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat dense no-caps label="Cancelar" v-close-popup/>
          <q-btn dense unelevated no-caps color="primary" label="Guardar" :loading="guardando"
                 :disable="!String(form.nombre || '').trim()" @click="guardar"/>
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
export default {
  name: 'MotivosAnulacion',
  data () {
    return {
      motivos: [],
      cargando: false,
      buscar: '',
      soloActivos: false,
      dialogo: false,
      guardando: false,
      guardandoId: null,
      form: { id: null, nombre: '', activo: true },
      columnas: [
        { name: 'nombre', label: 'Motivo', field: 'nombre', align: 'left', sortable: true },
        { name: 'activo', label: 'Estado', field: 'activo', align: 'left', sortable: true },
        { name: 'acciones', label: '', field: 'id', align: 'right' }
      ]
    }
  },
  computed: {
    filtrados () {
      const texto = String(this.buscar || '').toUpperCase()
      return this.motivos.filter(m => (!this.soloActivos || m.activo) && (!texto || m.nombre.includes(texto)))
    }
  },
  mounted () {
    this.cargar()
  },
  methods: {
    cargar () {
      this.cargando = true
      this.$api.get('motivos-anulacion')
        .then(res => { this.motivos = res.data })
        .catch(err => this.avisar(err, 'No se pudieron cargar los motivos'))
        .finally(() => { this.cargando = false })
    },
    abrir (m) {
      this.form = m ? { id: m.id, nombre: m.nombre, activo: !!m.activo } : { id: null, nombre: '', activo: true }
      this.dialogo = true
    },
    guardar () {
      if (!String(this.form.nombre || '').trim()) return
      this.guardando = true
      const datos = { nombre: this.form.nombre, activo: this.form.activo }
      const peticion = this.form.id
        ? this.$api.put('motivos-anulacion/' + this.form.id, datos)
        : this.$api.post('motivos-anulacion', datos)
      peticion
        .then(() => {
          this.dialogo = false
          this.$q.notify({ type: 'positive', message: 'Motivo guardado' })
          this.cargar()
        })
        .catch(err => this.avisar(err, 'No se pudo guardar el motivo'))
        .finally(() => { this.guardando = false })
    },
    cambiarActivo (m, activo) {
      this.guardandoId = m.id
      this.$api.put('motivos-anulacion/' + m.id, { nombre: m.nombre, activo })
        .then(() => { m.activo = activo })
        .catch(err => this.avisar(err, 'No se pudo cambiar el estado'))
        .finally(() => { this.guardandoId = null })
    },
    borrar (m) {
      this.$q.dialog({
        title: 'Eliminar motivo',
        message: '¿Eliminar "' + m.nombre + '"? Las facturas ya anuladas con este motivo lo conservan.',
        cancel: { label: 'Cancelar', flat: true },
        ok: { label: 'Eliminar', color: 'negative' },
        persistent: true
      }).onOk(() => {
        this.$api.delete('motivos-anulacion/' + m.id)
          .then(() => {
            this.$q.notify({ type: 'positive', message: 'Motivo eliminado' })
            this.cargar()
          })
          .catch(err => this.avisar(err, 'No se pudo eliminar el motivo'))
      })
    },
    avisar (err, mensaje) {
      const datos = err.response && err.response.data
      const errores = datos && datos.errors ? Object.values(datos.errors).flat()[0] : null
      this.$q.notify({ type: 'negative', message: errores || (datos && datos.message) || mensaje })
    }
  }
}
</script>
