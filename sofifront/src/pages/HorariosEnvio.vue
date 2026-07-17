<template>
  <q-page class="q-pa-xs">
    <div class="row">
      <div class="col-12">
        <q-table dense title="HORARIOS DE ENVIO AUTOMATICO" :columns="columns" :rows="horarios" row-key="id" :rows-per-page-options="[0]">
          <template v-slot:top-right>
            <q-btn color="primary" icon="add" label="Nuevo horario" dense @click="nuevo"/>
          </template>
          <template v-slot:body-cell-dias="props">
            <q-td :props="props">
              {{ diasTexto(props.row.dias) }}
            </q-td>
          </template>
          <template v-slot:body-cell-activo="props">
            <q-td :props="props">
              <q-toggle :model-value="props.row.activo==1" @update:model-value="toggleActivo(props.row)" color="positive"/>
            </q-td>
          </template>
          <template v-slot:body-cell-acciones="props">
            <q-td :props="props">
              <q-btn dense flat color="primary" icon="edit" @click="editar(props.row)"/>
              <q-btn dense flat color="negative" icon="delete" @click="eliminar(props.row)"/>
            </q-td>
          </template>
        </q-table>
        <div class="q-pa-sm text-caption text-grey-8">
          El sistema revisa cada minuto los horarios activos. Cuando llega la hora configurada, envia automaticamente
          todos los pedidos del dia en estado CREADO (sin bonificaciones y solo de clientes ACTIVOS) y los marca como
          enviados por sistema.
        </div>
      </div>
    </div>

    <q-dialog v-model="dialog">
      <q-card style="min-width: 320px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ horario.id ? 'Editar horario' : 'Nuevo horario' }}</div>
        </q-card-section>
        <q-card-section>
          <q-input outlined dense v-model="horario.hora" type="time" label="Hora de envio" class="q-mb-md"/>
          <div class="text-caption q-mb-xs">Dias que se ejecuta</div>
          <q-option-group v-model="horario.diasArray" :options="diasOptions" type="checkbox" inline dense/>
          <q-toggle v-model="horario.activo" label="Activo" color="positive" class="q-mt-md"/>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" color="negative" v-close-popup/>
          <q-btn label="Guardar" color="primary" :disable="!horario.hora || horario.diasArray.length==0" @click="guardar"/>
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
export default {
  data() {
    return {
      horarios: [],
      dialog: false,
      horario: {id: null, hora: '', diasArray: [], activo: true},
      diasOptions: [
        {label: 'Lun', value: '1'},
        {label: 'Mar', value: '2'},
        {label: 'Mie', value: '3'},
        {label: 'Jue', value: '4'},
        {label: 'Vie', value: '5'},
        {label: 'Sab', value: '6'},
        {label: 'Dom', value: '7'},
      ],
      columns: [
        {label: 'Hora', name: 'hora', field: 'hora', align: 'left', sortable: true},
        {label: 'Dias', name: 'dias', field: 'dias', align: 'left'},
        {label: 'Activo', name: 'activo', field: 'activo', align: 'center'},
        {label: 'Ultima ejecucion', name: 'ultima_ejecucion', field: 'ultima_ejecucion', align: 'left'},
        {label: 'Filas enviadas', name: 'pedidos_enviados', field: 'pedidos_enviados', align: 'center'},
        {label: 'Acciones', name: 'acciones', align: 'center'},
      ],
    }
  },
  created() {
    this.listar()
  },
  methods: {
    listar() {
      this.$q.loading.show()
      this.$api.get('horarioenvio').then(res => {
        this.horarios = res.data
      }).finally(() => this.$q.loading.hide())
    },
    nuevo() {
      this.horario = {id: null, hora: '', diasArray: ['1', '2', '3', '4', '5', '6', '7'], activo: true}
      this.dialog = true
    },
    editar(row) {
      this.horario = {
        id: row.id,
        hora: row.hora.substring(0, 5),
        diasArray: row.dias.split(','),
        activo: row.activo == 1,
      }
      this.dialog = true
    },
    guardar() {
      const data = {
        hora: this.horario.hora,
        dias: this.horario.diasArray.join(','),
        activo: this.horario.activo ? 1 : 0,
      }
      const req = this.horario.id
        ? this.$api.put('horarioenvio/' + this.horario.id, data)
        : this.$api.post('horarioenvio', data)
      this.$q.loading.show()
      req.then(() => {
        this.dialog = false
        this.$q.notify({type: 'positive', message: 'Horario guardado'})
        this.listar()
      }).catch(() => {
        this.$q.loading.hide()
        this.$q.notify({type: 'negative', message: 'Error al guardar el horario'})
      })
    },
    toggleActivo(row) {
      this.$api.put('horarioenvio/' + row.id, {activo: row.activo == 1 ? 0 : 1}).then(() => {
        this.listar()
      })
    },
    eliminar(row) {
      this.$q.dialog({
        title: 'Eliminar',
        message: 'Eliminar el horario de las ' + row.hora + '?',
        cancel: true,
      }).onOk(() => {
        this.$api.delete('horarioenvio/' + row.id).then(() => {
          this.$q.notify({type: 'positive', message: 'Horario eliminado'})
          this.listar()
        })
      })
    },
    diasTexto(dias) {
      const nombres = {1: 'Lun', 2: 'Mar', 3: 'Mie', 4: 'Jue', 5: 'Vie', 6: 'Sab', 7: 'Dom'}
      const arr = String(dias).split(',').map(d => nombres[d.trim()])
      return arr.length === 7 ? 'Todos los dias' : arr.join(', ')
    },
  },
}
</script>

<style scoped>
</style>
