<template>
  <q-page class="q-pa-md zonas-page">
    <q-card flat bordered>
      <q-card-section class="row items-center q-col-gutter-sm">
        <div class="col-12 col-md-4">
          <div class="text-subtitle2 text-grey-7">Catalogo de zonas</div>
          <div class="text-h6 text-weight-bold">Mapa cliente zonas</div>
        </div>
        <div class="col-12 col-md-4">
          <q-input v-model="filter" dense outlined label="Buscar zona">
            <template #append><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-12 col-md-4 text-right">
          <q-btn color="primary" icon="add" no-caps label="Nueva zona" @click="openCreate" class="q-mr-sm" />
          <q-btn color="grey-8" icon="refresh" no-caps label="Actualizar" :loading="loading" @click="loadZonas" />
        </div>
      </q-card-section>

      <q-markup-table flat bordered dense wrap-cells>
        <thead>
          <tr class="bg-grey-2">
            <th>#</th>
            <th>Nombre</th>
            <th>Color</th>
            <th>Orden</th>
            <th>Activo</th>
            <th>Opciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="z in zonasFiltradas" :key="z.id">
            <td>{{ z.id }}</td>
            <td>{{ z.nombre }}</td>
            <td>
              <q-chip :style="{ backgroundColor: z.color, color: textColor(z.color) }" dense>{{ z.color }}</q-chip>
            </td>
            <td>{{ z.orden }}</td>
            <td>
              <q-chip dense :color="z.activo ? 'green-7' : 'grey-6'" text-color="white">
                {{ z.activo ? 'SI' : 'NO' }}
              </q-chip>
            </td>
            <td>
              <q-btn-dropdown dense color="primary" label="Opciones" no-caps size="10px">
                <q-item clickable v-close-popup @click="openEdit(z)">
                  <q-item-section avatar><q-icon name="edit" /></q-item-section>
                  <q-item-section>Editar</q-item-section>
                </q-item>
                <q-item clickable v-close-popup @click="removeZona(z)">
                  <q-item-section avatar><q-icon name="delete" color="negative" /></q-item-section>
                  <q-item-section>Eliminar</q-item-section>
                </q-item>
              </q-btn-dropdown>
            </td>
          </tr>
          <tr v-if="zonasFiltradas.length === 0">
            <td colspan="6" class="text-center text-grey-7">Sin registros</td>
          </tr>
        </tbody>
      </q-markup-table>
    </q-card>

    <q-dialog v-model="dialog" persistent>
      <q-card style="width: 520px; max-width: 96vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ form.id ? 'Editar zona' : 'Nueva zona' }}</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-card-section>
          <q-form @submit.prevent="saveZona">
            <div class="row q-col-gutter-sm">
              <div class="col-12">
                <q-input v-model="form.nombre" dense outlined label="Nombre" :rules="[v => !!v || 'Requerido']" />
              </div>
              <div class="col-12 col-md-6">
                <q-input v-model="form.color" dense outlined label="Color HEX" hint="Ej: #2e7d32" />
              </div>
              <div class="col-12 col-md-6">
                <q-input v-model.number="form.orden" dense outlined type="number" label="Orden" />
              </div>
              <div class="col-12">
                <q-toggle v-model="form.activo" label="Activo" color="primary" />
              </div>
              <div class="col-12">
                <div class="text-caption text-grey-7 q-mb-xs">Vista previa</div>
                <q-chip :style="{ backgroundColor: form.color || '#607d8b', color: textColor(form.color || '#607d8b') }" dense>
                  {{ form.nombre || 'Nombre zona' }}
                </q-chip>
              </div>
            </div>
            <div class="text-right q-mt-md">
              <q-btn flat no-caps color="negative" label="Cancelar" v-close-popup />
              <q-btn color="primary" no-caps label="Guardar" type="submit" :loading="saving" class="q-ml-sm" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, ref } from 'vue'

const { proxy } = getCurrentInstance()

const zonas = ref([])
const loading = ref(false)
const saving = ref(false)
const filter = ref('')
const dialog = ref(false)
const form = ref({ id: null, nombre: '', color: '#607d8b', orden: 0, activo: true })

const zonasFiltradas = computed(() => {
  const needle = filter.value.trim().toLowerCase()
  if (!needle) return zonas.value
  return zonas.value.filter(z => String(z.nombre || '').toLowerCase().includes(needle))
})

function textColor (bg) {
  const hex = String(bg || '').replace('#', '')
  if (hex.length !== 6) return '#ffffff'
  const r = parseInt(hex.slice(0, 2), 16)
  const g = parseInt(hex.slice(2, 4), 16)
  const b = parseInt(hex.slice(4, 6), 16)
  const yiq = (r * 299 + g * 587 + b * 114) / 1000
  return yiq >= 140 ? '#1f2937' : '#ffffff'
}

function openCreate () {
  form.value = { id: null, nombre: '', color: '#607d8b', orden: 0, activo: true }
  dialog.value = true
}

function openEdit (z) {
  form.value = {
    id: z.id,
    nombre: z.nombre,
    color: z.color || '#607d8b',
    orden: Number(z.orden || 0),
    activo: !!z.activo,
  }
  dialog.value = true
}

async function saveZona () {
  saving.value = true
  try {
    const payload = {
      nombre: form.value.nombre,
      color: form.value.color || '#607d8b',
      orden: Number(form.value.orden || 0),
      activo: !!form.value.activo,
    }
    if (form.value.id) {
      await proxy.$axios.put(`/pedido-zonas/${form.value.id}`, payload)
      proxy.$alert.success('Zona actualizada')
    } else {
      await proxy.$axios.post('/pedido-zonas', payload)
      proxy.$alert.success('Zona creada')
    }
    dialog.value = false
    await loadZonas()
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo guardar la zona')
  } finally {
    saving.value = false
  }
}

async function removeZona (z) {
  proxy.$alert.dialog(`Desea eliminar la zona ${z.nombre}?`).onOk(async () => {
    loading.value = true
    try {
      await proxy.$axios.delete(`/pedido-zonas/${z.id}`)
      proxy.$alert.success('Zona eliminada')
      await loadZonas()
    } catch (e) {
      proxy.$alert.error(e?.response?.data?.message || 'No se pudo eliminar la zona')
    } finally {
      loading.value = false
    }
  })
}

async function loadZonas () {
  loading.value = true
  try {
    const res = await proxy.$axios.get('/pedido-zonas')
    zonas.value = Array.isArray(res.data) ? res.data : []
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudieron cargar zonas')
  } finally {
    loading.value = false
  }
}

onMounted(loadZonas)
</script>

<style scoped>
.zonas-page {
  background: linear-gradient(180deg, #f4f8ff 0%, #ffffff 100%);
}
</style>

