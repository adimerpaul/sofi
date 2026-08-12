<template>
  <q-page class="q-pa-sm">
    <div class="text-h6 text-weight-bold q-mb-sm">Historial de cobranzas</div>

    <q-card flat bordered class="q-mb-sm">
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="col-12 col-md-7">
          <q-input v-model="search" dense outlined label="Buscar cliente/CI/tel/direccion" debounce="300">
            <template #append><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-6 col-md-2">
          <q-select v-model="perPage" :options="[20, 50, 100, 150, 200]" dense outlined label="Filas" />
        </div>
        <div class="col-6 col-md-3">
          <q-btn color="primary" no-caps icon="search" label="Consultar" class="full-width" :loading="loading" @click="loadRows(1)" />
        </div>
      </q-card-section>
      <q-card-section class="row q-col-gutter-sm q-pt-none">
        <div class="col-6 col-md-3"><q-chip color="blue-8" text-color="white">Clientes: {{ pagination.total || 0 }}</q-chip></div>
        <div class="col-6 col-md-3"><q-chip color="indigo-8" text-color="white">Docs pagina: {{ stats.documentos || 0 }}</q-chip></div>
        <div class="col-6 col-md-3"><q-chip color="green-8" text-color="white">Pagos pagina: {{ stats.pagos || 0 }}</q-chip></div>
        <div class="col-6 col-md-3"><q-chip color="negative" text-color="white">Saldo pagina: {{ money(stats.saldo_total) }}</q-chip></div>
      </q-card-section>
    </q-card>

    <q-card flat bordered>
      <q-table dense flat row-key="cliente_id" :rows="rows" :columns="columns" :loading="loading" :pagination="{ rowsPerPage: 0 }">
        <template #body-cell-estado="props">
          <q-td :props="props">
            <q-chip dense :color="props.row.estado === 'DEBE' ? 'negative' : 'positive'" text-color="white">{{ props.row.estado }}</q-chip>
          </q-td>
        </template>
        <template #body-cell-saldo_total="props">
          <q-td :props="props">
            <q-chip dense :color="Number(props.row.saldo_total || 0) > 0 ? 'negative' : 'positive'" text-color="white">{{ money(props.row.saldo_total) }}</q-chip>
          </q-td>
        </template>
        <template #body-cell-actions="props">
          <q-td :props="props">
            <q-btn-dropdown dense color="primary" no-caps label="Opciones">
              <q-list>
                <q-item clickable v-close-popup @click="openDetalle(props.row)">
                  <q-item-section avatar><q-icon name="visibility" /></q-item-section>
                  <q-item-section>Ver historial</q-item-section>
                </q-item>
              </q-list>
            </q-btn-dropdown>
          </q-td>
        </template>
        <template #bottom>
          <div class="row full-width items-center justify-end q-gutter-sm q-pa-sm">
            <q-btn flat dense icon="chevron_left" :disable="pagination.page <= 1 || loading" @click="loadRows(pagination.page - 1)" />
            <div class="text-caption">Pagina {{ pagination.page }} / {{ pagination.last_page }}</div>
            <q-btn flat dense icon="chevron_right" :disable="pagination.page >= pagination.last_page || loading" @click="loadRows(pagination.page + 1)" />
          </div>
        </template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialogDetalle" full-width>
      <q-card style="max-width: 1200px; margin: 0 auto;">
        <q-card-section class="row items-center">
          <div class="col">
            <div class="text-subtitle1 text-weight-bold">{{ detalleCliente?.nombre || '-' }}</div>
            <div class="text-caption text-grey-7">{{ detalleCliente?.ci || '-' }} - {{ detalleCliente?.telefono || '-' }}</div>
          </div>
          <q-btn flat icon="close" round dense v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section>
          <q-markup-table dense flat bordered wrap-cells>
            <thead>
              <tr>
                <th>Tipo</th>
                <th>Referencia</th>
                <th>Fecha</th>
                <th>Total</th>
                <th>Cobrado</th>
                <th>Saldo</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="item in detalleItems" :key="`${item.tipo}-${item.id}`">
                <tr>
                  <td>{{ item.tipo }}</td>
                  <td>{{ item.referencia }}</td>
                  <td>{{ item.fecha || '-' }}</td>
                  <td>{{ money(item.monto_total) }}</td>
                  <td>{{ money(item.cobrado) }}</td>
                  <td><q-chip dense :color="Number(item.saldo || 0) > 0 ? 'negative' : 'positive'" text-color="white">{{ money(item.saldo) }}</q-chip></td>
                  <td>
                    <q-chip dense :color="item.considerar_en_cobranza ? 'blue-7' : 'grey-7'" text-color="white">
                      {{ item.considerar_en_cobranza ? 'En cobranza' : 'No considerar' }}
                    </q-chip>
                  </td>
                  <td>
                    <q-btn-dropdown dense color="primary" no-caps label="Opciones">
                      <q-list>
                        <q-item clickable v-close-popup @click="abrirPagoItem(item, null)" :disable="Number(item.saldo || 0) <= 0 || !item.considerar_en_cobranza">
                          <q-item-section avatar><q-icon name="payments" /></q-item-section>
                          <q-item-section>Pagar</q-item-section>
                        </q-item>
                        <q-item clickable v-close-popup @click="abrirPagoItem(item, ultimoPago(item))" :disable="!ultimoPago(item) || !item.considerar_en_cobranza">
                          <q-item-section avatar><q-icon name="edit" /></q-item-section>
                          <q-item-section>Modificar ultimo pago</q-item-section>
                        </q-item>
                        <q-item clickable v-close-popup @click="cambiarConsiderar(item, !item.considerar_en_cobranza)">
                          <q-item-section avatar><q-icon name="rule" /></q-item-section>
                          <q-item-section>{{ item.considerar_en_cobranza ? 'No considerar en cobranza' : 'Volver a considerar' }}</q-item-section>
                        </q-item>
                      </q-list>
                    </q-btn-dropdown>
                  </td>
                </tr>
                <tr>
                  <td colspan="8" class="bg-grey-1">
                    <div class="text-caption text-grey-7 text-weight-bold q-mb-xs">Pagos:</div>
                    <div v-if="!(item.pagos || []).length" class="text-caption text-grey-7">Sin pagos</div>
                    <div v-for="pg in (item.pagos || [])" :key="`pg-${item.tipo}-${item.id}-${pg.id}`" class="row items-center no-wrap q-col-gutter-sm q-py-xs">
                      <div class="col-auto">#{{ pg.id }} - {{ money(pg.monto) }}</div>
                      <div class="col-auto">
                        <q-chip dense :color="String(pg.estado || 'ACTIVO').toUpperCase() === 'ANULADO' ? 'negative' : 'positive'" text-color="white">
                          {{ String(pg.estado || 'ACTIVO').toUpperCase() }}
                        </q-chip>
                      </div>
                      <div class="col-auto">{{ pg.metodo_pago }}</div>
                      <div class="col-auto">{{ pg.nro_pago || '-' }}</div>
                      <div class="col-auto">{{ pg.fecha_hora || '-' }}</div>
                      <div class="col-auto">
                        {{ pg.registrado_por || 'Sin usuario' }}
                        <div v-if="pg.anulado_por" class="text-negative">Anulado: {{ pg.anulado_por }}</div>
                      </div>
                      <div class="col-auto">
                        <q-btn
                          v-if="pg.comprobante_url"
                          dense
                          flat
                          color="primary"
                          icon="attach_file"
                          label="Ver comp."
                          no-caps
                          :href="pg.comprobante_url"
                          target="_blank"
                        />
                        <q-img v-if="isImageComprobante(pg.comprobante_url)" :src="pg.comprobante_url" style="width: 28px; height: 28px; border-radius: 4px;" fit="cover" class="q-ml-xs" />
                      </div>
                      <div class="col-auto">
                        <q-btn
                          v-if="String(pg.estado || 'ACTIVO').toUpperCase() !== 'ANULADO'"
                          dense flat color="negative" icon="cancel" label="Anular" no-caps
                          @click="anularPago(item, pg)"
                        />
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </q-markup-table>
        </q-card-section>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogPago">
      <q-card style="width: 560px; max-width: 96vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ pagoEditando ? 'Modificar pago' : 'Registrar pago' }}</div>
          <q-space />
          <q-btn flat dense round icon="close" v-close-popup />
        </q-card-section>
        <q-card-section>
          <div class="text-caption text-grey-8 q-mb-sm">{{ pagoTarget?.referencia || '-' }} - Saldo: {{ money(pagoTarget?.saldo || 0) }}</div>
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-md-4"><q-input v-model.number="pagoForm.monto" type="number" min="0.01" step="0.01" dense outlined label="Monto" /></div>
            <div class="col-12 col-md-4"><q-select v-model="pagoForm.metodo_pago" :options="metodosPago" dense outlined label="Metodo" /></div>
            <div class="col-12 col-md-4"><q-input v-model="pagoForm.nro_pago" dense outlined label="Nro pago" /></div>
            <div class="col-12"><q-input v-model="pagoForm.observacion" dense outlined label="Comentario" /></div>
            <div class="col-12"><q-file v-model="pagoForm.comprobante" dense outlined label="Foto/PDF comprobante" accept=".jpg,.jpeg,.png,.pdf" /></div>
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat color="grey-8" no-caps label="Cancelar" v-close-popup />
          <q-btn color="primary" no-caps label="Guardar" :loading="loadingPago" @click="guardarPago" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { getCurrentInstance, onMounted, ref, watch } from 'vue'

const { proxy } = getCurrentInstance()
const loading = ref(false)
const loadingPago = ref(false)
const search = ref('')
const perPage = ref(50)
const rows = ref([])
const stats = ref({})
const pagination = ref({ page: 1, per_page: 50, total: 0, last_page: 1 })

const dialogDetalle = ref(false)
const detalleCliente = ref(null)
const detalleItems = ref([])

const dialogPago = ref(false)
const pagoTarget = ref(null)
const pagoEditando = ref(null)
const metodosPago = ['EFECTIVO', 'QR', 'TRANSFERENCIA', 'OTRO']
const pagoForm = ref({
  monto: null,
  metodo_pago: 'EFECTIVO',
  nro_pago: '',
  observacion: '',
  comprobante: null,
})

const columns = [
  { name: 'actions', label: 'Acciones', align: 'left' },
  { name: 'cliente_nombre', label: 'Cliente', field: 'cliente_nombre', align: 'left' },
  { name: 'ci_nit', label: 'CI/NIT', field: 'ci_nit', align: 'left' },
  { name: 'telefono', label: 'Telefono', field: 'telefono', align: 'left' },
  { name: 'documentos', label: 'Documentos', field: 'documentos', align: 'right' },
  { name: 'pagos_total', label: 'Pagos', field: 'pagos_total', align: 'right' },
  { name: 'monto_total', label: 'Total', field: (r) => money(r.monto_total), align: 'right' },
  { name: 'cobrado_total', label: 'Cobrado', field: (r) => money(r.cobrado_total), align: 'right' },
  { name: 'saldo_total', label: 'Saldo', field: 'saldo_total', align: 'right' },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
]

function money(v) {
  return Number(v || 0).toFixed(2)
}

function isImageComprobante(url) {
  if (!url) return false
  return /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(String(url))
}

async function loadRows(page = 1) {
  loading.value = true
  try {
    const res = await proxy.$axios.get('/cobranzas/historial-clientes', {
      params: {
        search: search.value || undefined,
        per_page: perPage.value,
        page,
      },
    })
    rows.value = Array.isArray(res.data?.data) ? res.data.data : []
    stats.value = res.data?.stats || {}
    pagination.value = res.data?.pagination || { page: 1, per_page: perPage.value, total: 0, last_page: 1 }
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo cargar historial')
  } finally {
    loading.value = false
  }
}

async function openDetalle(row) {
  try {
    const res = await proxy.$axios.get(`/cobranzas/historial-clientes/${row.cliente_id}`)
    detalleCliente.value = res.data?.cliente || null
    detalleItems.value = Array.isArray(res.data?.items) ? res.data.items : []
    dialogDetalle.value = true
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo cargar detalle del cliente')
  }
}

function ultimoPago(item) {
  const pagos = Array.isArray(item?.pagos) ? item.pagos : []
  const activos = pagos.filter((p) => String(p?.estado || 'ACTIVO').toUpperCase() !== 'ANULADO')
  return activos.length ? activos[activos.length - 1] : null
}

function abrirPagoItem(item, pago) {
  pagoTarget.value = item
  pagoEditando.value = pago
  pagoForm.value = {
    monto: pago ? Number(pago.monto || 0) : Number(item.saldo || 0),
    metodo_pago: pago?.metodo_pago || 'EFECTIVO',
    nro_pago: pago?.nro_pago || '',
    observacion: pago?.observacion || '',
    comprobante: null,
  }
  dialogPago.value = true
}

async function cambiarConsiderar(item, considerar) {
  try {
    if (item.tipo === 'VENTA') {
      await proxy.$axios.put(`/cobranzas/ventas/${item.id}/considerar`, { considerar_en_cobranza: considerar })
    } else {
      await proxy.$axios.put(`/cobranzas/deudas-manuales/${item.id}/considerar`, { considerar_en_cobranza: considerar })
    }
    proxy.$alert.success('Documento actualizado')
    if (detalleCliente.value?.id) {
      await openDetalle({ cliente_id: detalleCliente.value.id })
    }
    await loadRows(pagination.value.page || 1)
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo actualizar documento')
  }
}

async function guardarPago() {
  if (!pagoTarget.value) return
  loadingPago.value = true
  try {
    const fd = new FormData()
    fd.append('monto', String(Number(pagoForm.value.monto || 0)))
    fd.append('metodo_pago', String(pagoForm.value.metodo_pago || 'EFECTIVO'))
    fd.append('nro_pago', String(pagoForm.value.nro_pago || ''))
    fd.append('observacion', String(pagoForm.value.observacion || ''))
    if (pagoForm.value.comprobante) {
      fd.append('comprobante', pagoForm.value.comprobante)
    }

    const target = pagoTarget.value
    if (target.tipo === 'VENTA') {
      if (pagoEditando.value?.id) {
        fd.append('_method', 'PUT')
        await proxy.$axios.post(`/cobranzas/pagos/ventas/${pagoEditando.value.id}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      } else {
        fd.append('venta_id', String(target.id))
        await proxy.$axios.post('/cobranzas/pagos/ventas', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      }
    } else {
      if (pagoEditando.value?.id) {
        fd.append('_method', 'PUT')
        await proxy.$axios.post(`/cobranzas/deudas-manuales/pagos/${pagoEditando.value.id}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      } else {
        await proxy.$axios.post(`/cobranzas/deudas-manuales/${target.id}/pagos`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      }
    }

    proxy.$alert.success('Pago guardado')
    dialogPago.value = false
    if (detalleCliente.value?.id) {
      await openDetalle({ cliente_id: detalleCliente.value.id })
    }
    await loadRows(pagination.value.page || 1)
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo guardar pago')
  } finally {
    loadingPago.value = false
  }
}

async function anularPago(item, pago) {
  const ok = await new Promise((resolve) => {
    proxy.$q.dialog({
      title: 'Anular pago',
      message: `Se anulara el pago #${pago?.id}. Desea continuar?`,
      cancel: true,
      persistent: true,
    }).onOk(() => resolve(true)).onCancel(() => resolve(false))
  })
  if (!ok) return

  try {
    if (item.tipo === 'VENTA') {
      await proxy.$axios.put(`/cobranzas/pagos/ventas/${pago.id}/anular`, {})
    } else {
      await proxy.$axios.put(`/cobranzas/deudas-manuales/pagos/${pago.id}/anular`, {})
    }
    proxy.$alert.success('Pago anulado')
    if (detalleCliente.value?.id) {
      await openDetalle({ cliente_id: detalleCliente.value.id })
    }
    await loadRows(pagination.value.page || 1)
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo anular pago')
  }
}

watch(perPage, () => loadRows(1))
onMounted(() => loadRows(1))
</script>
