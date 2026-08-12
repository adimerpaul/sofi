<template>
  <q-page class="q-pa-sm despacho-page">
    <q-card flat bordered class="q-mb-sm">
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="col-12 col-md-2">
          <q-input v-model="fechaInicio" dense outlined type="date" label="Fecha inicio" />
        </div>
        <div class="col-12 col-md-2">
          <q-input v-model="fechaFin" dense outlined type="date" label="Fecha fin" />
        </div>
        <div class="col-12 col-md-3">
          <q-btn color="primary" icon="search" no-caps class="full-width" label="Consultar despacho" :loading="loading" @click="loadDespacho" />
        </div>
        <div class="col-12 col-md-5">
          <q-btn color="teal" icon="print" no-caps class="full-width text-weight-bold" label="Imprimir vouchers del rango" :loading="loadingPrint" @click="imprimirVouchersMasivo" />
        </div>
      </q-card-section>
      <q-card-section class="row q-col-gutter-sm q-pt-none">
        <div class="col-6 col-md-3"><q-chip color="blue-8" text-color="white">Comandas: {{ stats.total_entregas || 0 }}</q-chip></div>
        <div class="col-6 col-md-3"><q-chip color="green-8" text-color="white">Cobrado: {{ Number(stats.monto_cobrado || 0).toFixed(2) }} Bs</q-chip></div>
        <div class="col-6 col-md-3"><q-chip color="indigo-8" text-color="white">Contado: {{ Number(stats.contado || 0).toFixed(2) }} Bs</q-chip></div>
        <div class="col-6 col-md-3"><q-chip color="orange-8" text-color="white">Saldo: {{ Number(stats.saldo_total || 0).toFixed(2) }} Bs</q-chip></div>
      </q-card-section>
    </q-card>

    <q-card flat bordered>
      <q-markup-table flat dense wrap-cells>
        <thead>
          <tr class="bg-grey-2">
            <th>Op</th>
            <th>CINIT</th>
            <th>Cliente</th>
            <th>Comanda</th>
            <th>Importe</th>
            <th>Cobrado</th>
            <th>Placa</th>
            <th>Despachador</th>
            <th>Tipo pago</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="row in rows" :key="row.pedido_id">
            <tr>
              <td style="white-space: nowrap;">
                <q-btn dense size="sm" color="primary" no-caps icon="visibility" label="Ver" class="q-mr-xs" @click="toggleExpand(row.pedido_id)" />
                <q-btn dense size="sm" color="teal" no-caps icon="description" label="Voucher" :disable="!row.venta_id" @click="imprimirVoucherIndividual(row)" />
                <q-btn dense size="sm" color="indigo" no-caps icon="chat" label="WhatsApp" class="q-ml-xs" :disable="!row.venta_id" @click="mandarWhatsappVoucher(row)" />
              </td>
              <td>{{ row.ci || '-' }}</td>
              <td>{{ row.cliente || '-' }}</td>
              <td>{{ row.comanda || '-' }}</td>
              <td>{{ Number(row.total || 0).toFixed(2) }}</td>
              <td>{{ Number(row.pagado || 0).toFixed(2) }}</td>
              <td>{{ row.placa || '-' }}</td>
              <td>{{ row.camion || '-' }}</td>
              <td>
                <q-chip dense :color="String(row.tipo_pago || '').toUpperCase().includes('CRED') ? 'orange-8' : 'red'" text-color="white">
                  {{ row.tipo_pago || '-' }}
                </q-chip>
              </td>
              <td>{{ row.despacho_estado || '-' }}</td>
            </tr>
            <tr v-if="expanded[row.pedido_id]">
              <td colspan="9">
                <q-markup-table dense flat bordered>
                  <thead>
                    <tr>
                      <th>Codigo</th>
                      <th>Producto</th>
                      <th>Cantidad</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(p, i) in (row.productos || [])" :key="`${row.pedido_id}-d-${i}`">
                      <td>{{ p.codigo || '-' }}</td>
                      <td>{{ p.nombre || '-' }}</td>
                      <td>{{ Number(p.cantidad || 0).toFixed(2) }}</td>
                    </tr>
                  </tbody>
                </q-markup-table>
                <div class="text-subtitle2 q-mt-sm">
                  Cobrado: {{ Number(row.pagado || 0).toFixed(2) }} Bs | Saldo: {{ Number(row.saldo || 0).toFixed(2) }} Bs
                </div>
                <q-markup-table dense flat bordered class="q-mt-xs" v-if="(row.pagos || []).length">
                  <thead>
                    <tr>
                      <th>Fecha/Hora</th>
                      <th>Monto</th>
                      <th>Metodo</th>
                      <th>Tipo</th>
                      <th>Registrado por</th>
                      <th>Obs.</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="pg in row.pagos" :key="pg.id">
                      <td>{{ pg.fecha_hora }}</td>
                      <td>{{ Number(pg.monto || 0).toFixed(2) }}</td>
                      <td>{{ pg.metodo_pago }}</td>
                      <td>{{ pg.tipo_pago }}</td>
                      <td>{{ pg.registrado_por || '-' }}</td>
                      <td>{{ pg.observacion || '-' }}</td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </td>
            </tr>
          </template>
          <tr v-if="rows.length === 0">
            <td colspan="9" class="text-center text-grey-7">Sin registros</td>
          </tr>
        </tbody>
      </q-markup-table>
    </q-card>
  </q-page>
</template>

<script setup>
import { getCurrentInstance, onMounted, ref } from 'vue'

const { proxy } = getCurrentInstance()
const today = new Date().toISOString().slice(0, 10)
const fechaInicio = ref(today)
const fechaFin = ref(today)
const loading = ref(false)
const loadingPrint = ref(false)
const rows = ref([])
const stats = ref({})
const expanded = ref({})

function toggleExpand(pedidoId) {
  expanded.value[pedidoId] = !expanded.value[pedidoId]
}

async function loadDespacho() {
  loading.value = true
  try {
    const res = await proxy.$axios.get('/despachador/despacho', {
      params: { fecha_inicio: fechaInicio.value, fecha_fin: fechaFin.value },
    })
    rows.value = Array.isArray(res.data?.data) ? res.data.data : []
    stats.value = res.data?.stats || {}
    expanded.value = {}
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo cargar despacho')
  } finally {
    loading.value = false
  }
}

async function descargarPdf(url, loadingRef) {
  loadingRef.value = true
  try {
    const res = await proxy.$axios.get(url, {
      params: {
        fecha_inicio: fechaInicio.value,
        fecha_fin: fechaFin.value,
      },
      responseType: 'blob',
    })
    const blob = new Blob([res.data], { type: 'application/pdf' })
    const disposition = res?.headers?.['content-disposition'] || ''
    const match = disposition.match(/filename="?([^"]+)"?/)
    const fileName = match?.[1] || 'vouchers_despacho.pdf'
    const link = document.createElement('a')
    const fileUrl = window.URL.createObjectURL(blob)
    link.href = fileUrl
    link.download = fileName
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(fileUrl)
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo generar el PDF')
  } finally {
    loadingRef.value = false
  }
}

async function imprimirVouchersMasivo() {
  await descargarPdf('/despachador/reportes/vouchers', loadingPrint)
}

async function imprimirVoucherIndividual(row) {
  try {
    const res = await proxy.$axios.get(`/despachador/reportes/vouchers/${row.venta_id}`, { responseType: 'blob' })
    const blob = new Blob([res.data], { type: 'application/pdf' })
    const disposition = res?.headers?.['content-disposition'] || ''
    const match = disposition.match(/filename="?([^"]+)"?/)
    const fileName = match?.[1] || `voucher_venta_${row.venta_id}.pdf`
    const link = document.createElement('a')
    const fileUrl = window.URL.createObjectURL(blob)
    link.href = fileUrl
    link.download = fileName
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(fileUrl)
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo generar el voucher')
  }
}

async function mandarWhatsappVoucher(row) {
  try {
    const res = await proxy.$axios.get(`/despachador/reportes/vouchers/${row.venta_id}`, { responseType: 'blob' })
    const blob = new Blob([res.data], { type: 'application/pdf' })
    const fileName = `voucher_venta_${row.venta_id}.pdf`
    const file = new File([blob], fileName, { type: 'application/pdf' })
    if (navigator.canShare && navigator.canShare({ files: [file] })) {
      await navigator.share({ files: [file], title: fileName })
      return
    }

    const link = document.createElement('a')
    const fileUrl = window.URL.createObjectURL(blob)
    link.href = fileUrl
    link.download = fileName
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(fileUrl)
    proxy.$alert.error('Tu navegador no permite compartir PDF directo. Se descargó el voucher.')
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo preparar el voucher')
  }
}

onMounted(loadDespacho)
</script>

<style scoped>
.despacho-page { background: #f4f7fc; }
</style>
