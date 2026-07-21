<template>
  <q-page class="q-pa-sm misped-page">
    <q-card flat bordered class="hero-card q-mb-sm">
      <q-card-section class="row items-center q-col-gutter-sm">
        <div class="col-12 col-md-4">
          <div class="text-subtitle2 text-grey-7">Panel administrativo</div>
          <div class="text-h6 text-weight-bold">Mis pedidos totales</div>
        </div>
        <div class="col-12 col-md-2">
          <q-input v-model="fecha" type="date" dense outlined label="Fecha" />
        </div>
        <div class="col-12 col-md-2">
          <q-select
            v-model="vendedorId"
            :options="vendedoresOptions"
            emit-value
            map-options
            dense
            outlined
            label="Vendedor"
          />
        </div>
        <div class="col-12 col-md-2">
          <q-select
            v-model="estadoFiltro"
            :options="estadoFiltroOptions"
            emit-value
            map-options
            dense
            outlined
            label="Estado"
          />
        </div>
        <div class="col-12 col-md-2">
          <q-select
            v-model="clienteId"
            :options="clienteOptions"
            emit-value
            map-options
            dense
            outlined
            label="Cliente"
          />
        </div>
        <div class="col-12 col-md-1">
          <q-btn color="primary" icon="search" no-caps class="full-width" label="Consulta" :loading="loading" @click="cargarPedidos" />
        </div>
        <div class="col-12 col-md-1">
          <q-btn
            color="warning"
            icon="bolt"
            no-caps
            class="full-width text-black"
            label="Emerg."
            :disable="enviables.length === 0"
            :loading="sendingAll"
            @click="enviarTodos"
          />
        </div>
      </q-card-section>

<!--      <q-separator />-->

<!--      <q-card-section class="row q-col-gutter-sm items-center">-->
<!--        <div class="col-6 col-md-2">-->
<!--          <q-chip square color="blue-8" text-color="white" class="full-width justify-center" icon="receipt_long">Total: {{ stats.total }}</q-chip>-->
<!--        </div>-->
<!--        <div class="col-6 col-md-2">-->
<!--          <q-chip square color="orange-8" text-color="white" class="full-width justify-center" icon="edit_note">Creado: {{ stats.creado }}</q-chip>-->
<!--        </div>-->
<!--        <div class="col-6 col-md-2">-->
<!--          <q-chip square color="green-8" text-color="white" class="full-width justify-center" icon="send">Enviado: {{ stats.enviado }}</q-chip>-->
<!--        </div>-->
<!--        <div class="col-6 col-md-2">-->
<!--          <q-chip square color="grey-8" text-color="white" class="full-width justify-center" icon="outbox">No enviado: {{ stats.no_enviado }}</q-chip>-->
<!--        </div>-->
<!--        <div class="col-6 col-md-2">-->
<!--          <q-chip square color="indigo-8" text-color="white" class="full-width justify-center" icon="payments">Total Bs: {{ Number(stats.monto_total || 0).toFixed(2) }}</q-chip>-->
<!--        </div>-->
<!--        <div class="col-6 col-md-3">-->
<!--          <q-chip square color="blue-grey-7" text-color="white" class="full-width justify-center" icon="inventory_2">Normal: {{ stats.tipo_normal }}</q-chip>-->
<!--        </div>-->
<!--        <div class="col-6 col-md-3">-->
<!--          <q-chip square color="orange-7" text-color="white" class="full-width justify-center" icon="egg">Pollo: {{ stats.tipo_pollo }}</q-chip>-->
<!--        </div>-->
<!--        <div class="col-6 col-md-3">-->
<!--          <q-chip square color="red-7" text-color="white" class="full-width justify-center" icon="set_meal">Res: {{ stats.tipo_res }}</q-chip>-->
<!--        </div>-->
<!--        <div class="col-6 col-md-3">-->
<!--          <q-chip square color="brown-7" text-color="white" class="full-width justify-center" icon="restaurant">Cerdo: {{ stats.tipo_cerdo }}</q-chip>-->
<!--        </div>-->
<!--      </q-card-section>-->
    </q-card>

    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="col-12 col-md-4">
          <q-input v-model="search" dense outlined label="Buscar cliente/comanda/producto" debounce="250">
            <template #append><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-12 col-md-3">
          <q-select
            v-model="tipoFiltro"
            dense
            outlined
            emit-value
            map-options
            :options="tipoFiltroOptions"
            label="Filtrar por tipo"
          />
        </div>
        <div class="col-12 col-md-auto">
          <q-chip color="indigo-7" text-color="white">Pedidos: {{ pedidosFiltrados.length }}</q-chip>
        </div>
      </q-card-section>

      <q-markup-table flat dense wrap-cells>
        <thead>
        <tr class="bg-grey-2">
          <th>Opciones</th>
          <th>Comanda</th>
          <th>Vendedor</th>
          <th>Cliente</th>
          <th>Tipo</th>
          <th>Producto</th>
          <th>Fec/Hora</th>
          <th>Pago</th>
          <th>Factura</th>
          <th>Estado</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="pedido in pedidosFiltrados" :key="pedido.id">
          <td>
            <q-btn-dropdown v-if="isEditable(pedido)" color="primary" label="Opciones" dense no-caps size="10px">
              <q-item clickable v-close-popup @click="verPedido(pedido)">
                <q-item-section avatar><q-icon name="visibility" /></q-item-section>
                <q-item-section>Ver</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="editarPedido(pedido)">
                <q-item-section avatar><q-icon name="edit" /></q-item-section>
                <q-item-section>Editar</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="enviarUno(pedido)">
                <q-item-section avatar><q-icon name="send" /></q-item-section>
                <q-item-section>Mandar</q-item-section>
              </q-item>
            </q-btn-dropdown>
            <div v-else class="row items-center q-gutter-xs">
              <q-chip dense color="green-7" text-color="white">Ya mandado</q-chip>
              <q-btn dense flat color="primary" icon="visibility" label="Ver" no-caps @click="verPedido(pedido)" />
            </div>
          </td>
          <td>{{ pedido.id }}</td>
          <td>{{ pedido.user?.name || '-' }}</td>
          <td>{{ pedido.cliente?.nombre || '-' }}</td>
          <td>
            <q-chip
              v-for="tipo in pedidoTipos(pedido)"
              :key="`${pedido.id}-${tipo}`"
              dense
              text-color="white"
              :color="tipoColor(tipo)"
              class="q-mr-xs q-mb-xs"
            >
              {{ tipo }}
            </q-chip>
          </td>
          <td>
<!--            <q-list dense separator>-->
<!--              <q-item v-for="d in (pedido.detalles || [])" :key="d.id" class="q-px-none">-->
<!--                <q-item-section>-->
<!--                  <q-item-label>{{ d.producto?.nombre || ('Producto ' + d.producto_id) }} x {{ d.cantidad }}</q-item-label>-->
<!--                </q-item-section>-->
<!--              </q-item>-->
<!--            </q-list>-->
            <ul style="padding: 0; margin: 0; list-style: none;">
              <li v-for="d in (pedido.detalles || [])" :key="d.id" style="font-size: 0.9em; border-bottom: 1px solid #eee; padding: 0;">
                {{ d.producto?.nombre || ('Producto ' + d.producto_id) }} x {{ d.cantidad }}
              </li>
            </ul>
          </td>
          <td>{{ pedido.fecha }} {{ pedido.hora || '' }}</td>
          <td>{{ pedido.tipo_pago || '-' }}</td>
          <td>{{ pedido.facturado ? 'SI' : 'NO' }}</td>
          <td>
            <q-chip dense :color="estadoColor(pedido.estado)" text-color="white">{{ pedido.estado }}</q-chip>
          </td>
        </tr>
        <tr v-if="pedidosFiltrados.length === 0">
          <td colspan="10" class="text-center text-grey-7">Sin datos disponibles</td>
        </tr>
        </tbody>
      </q-markup-table>
    </q-card>

    <q-dialog v-model="dialogEdit" maximized>
      <q-card>
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Editar pedido #{{ editForm.id }}</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-card-section class="q-pt-sm">
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-md-4">
              <q-option-group v-model="editForm.tipo_pago" :options="tiposPagoOptions" type="radio" color="primary" inline />
            </div>
            <div class="col-12 col-md-2"><q-toggle v-model="editForm.facturado" label="Facturado" /></div>
            <div class="col-12 col-md-3"><q-input v-model="editForm.fecha" type="date" dense outlined label="Fecha" /></div>
            <div class="col-12 col-md-3">
              <q-select v-model="editForm.hora" :options="horariosPedido" dense outlined emit-value map-options label="Horario" />
            </div>
            <div class="col-12 col-md-12"><q-input v-model="editForm.observaciones" dense outlined label="Comentario" /></div>
            <div class="col-12 col-md-10">
              <q-select
                v-model="newProductoId"
                :options="productosOptions"
                option-value="id"
                option-label="label"
                emit-value
                map-options
                dense
                outlined
                label="Productos"
                use-input
                input-debounce="350"
                @filter="filtrarProductos"
              >
                <template #selected-item="scope">
                  <div class="row items-center no-wrap q-gutter-xs">
                    <q-avatar rounded size="24px"><q-img :src="productImageUrl(scope?.opt?.imagen)" /></q-avatar>
                    <span class="ellipsis">{{ scope?.opt?.label || '' }}</span>
                  </div>
                </template>
                <template #option="scope">
                  <q-item v-bind="scope.itemProps">
                    <q-item-section avatar>
                      <q-avatar rounded size="28px"><q-img :src="productImageUrl(scope.opt.imagen)" /></q-avatar>
                    </q-item-section>
                    <q-item-section><q-item-label>{{ scope.opt.label }}</q-item-label></q-item-section>
                  </q-item>
                </template>
              </q-select>
            </div>
            <div class="col-12 col-md-2">
              <q-btn color="negative" class="full-width" icon="add" @click="agregarProducto" />
            </div>
          </div>

          <q-markup-table dense flat bordered class="q-mt-sm">
            <thead>
            <tr>
              <th>Detalle</th>
              <th>Subtotal</th>
              <th>Cantidad</th>
              <th>Precio</th>
              <th>Cod</th>
              <th>Nombre</th>
              <th>Obs</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(d, i) in editForm.productos" :key="`${d.producto_id}-${i}`">
              <td>
                <q-btn-dropdown dense :label="'Op(' + d.tipo + ')'" :color="tipoColor(d.tipo)" no-caps size="10px">
                  <q-list>
                    <q-item clickable v-ripple v-close-popup @click="openDetalleDialog(d, i)">
                      <q-item-section avatar><q-icon name="tune" color="purple" /></q-item-section>
                      <q-item-section>Editar detalle</q-item-section>
                    </q-item>
                    <q-item clickable v-ripple v-close-popup @click="editForm.productos.splice(i, 1)">
                      <q-item-section avatar><q-icon name="delete" color="negative" /></q-item-section>
                      <q-item-section>Eliminar producto</q-item-section>
                    </q-item>
                  </q-list>
                </q-btn-dropdown>
              </td>
              <td>{{ (Number(d.cantidad) * Number(d.precio)).toFixed(2) }}</td>
              <td><input v-model.number="d.cantidad" type="text" style="width: 40px" @input="d.cantidad = d.cantidad < 1 ? 1 : d.cantidad" /></td>
              <td><input v-model.number="d.precio" type="text" style="width: 50px" @input="d.precio = d.precio < 0 ? 0 : d.precio" /></td>
              <td>{{ d.codigo || d.producto_id }}</td>
              <td>
                <div class="row items-center no-wrap q-gutter-sm">
                  <q-avatar rounded size="30px"><q-img :src="productImageUrl(d.imagen)" /></q-avatar>
                  <div>{{ d.nombre }}</div>
                </div>
              </td>
              <td>{{ d.observacion || '-' }}</td>
            </tr>
            <tr v-if="editForm.productos.length === 0">
              <td colspan="7" class="text-grey-7">Sin datos disponibles</td>
            </tr>
            </tbody>
          </q-markup-table>
          <div class="text-h6 q-mt-sm">Total: {{ totalEdit.toFixed(2) }} Bs.</div>
        </q-card-section>
        <q-card-actions align="between" class="q-pa-md">
          <q-btn flat color="negative" label="Cerrar" v-close-popup />
          <q-btn color="green" no-caps icon="save" label="Guardar cambios" :loading="saving" @click="guardarEdicion" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogDetalle">
      <q-card style="width: 450px; max-width: 96vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Pedido {{ detalleTipoLabel }}</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-card-section>
          <div class="row q-col-gutter-sm" v-if="detalleTipo === 'NORMAL'">
            <div class="col-12"><q-input v-model="detalleEdit.observacion" dense outlined label="Observacion detalle" /></div>
          </div>
          <div class="row q-col-gutter-sm" v-else-if="detalleTipo === 'RES'">
            <div class="col-12 col-md-4"><q-input v-model="detalleEdit.precio_res" dense outlined label="Precio RES" /></div>
            <div class="col-12 col-md-4"><q-input v-model="detalleEdit.res_trozado" dense outlined label="Res trozado" /></div>
            <div class="col-12 col-md-4"><q-input v-model="detalleEdit.res_entero" dense outlined label="Res entero" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.res_pierna" dense outlined label="Res pierna" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.res_brazo" dense outlined label="Res brazo" /></div>
            <div class="col-12"><q-input v-model="detalleEdit.observacion" dense outlined label="Observacion detalle" /></div>
          </div>
          <div class="row q-col-gutter-sm" v-else-if="detalleTipo === 'CERDO'">
            <div class="col-12 col-md-4"><q-input v-model="detalleEdit.cerdo_precio_total" dense outlined label="Precio total" /></div>
            <div class="col-12 col-md-4"><q-input v-model="detalleEdit.cerdo_entero" dense outlined label="Cerdo entero" /></div>
            <div class="col-12 col-md-4"><q-input v-model="detalleEdit.cerdo_kilo" dense outlined label="Cerdo kilo" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.cerdo_desmembrado" dense outlined label="Cerdo desmembrado" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.cerdo_corte" dense outlined label="Cerdo corte" /></div>
            <div class="col-12"><q-input v-model="detalleEdit.observacion" dense outlined label="Observacion detalle" /></div>
          </div>
          <div class="row q-col-gutter-sm" v-else>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_b5" dense outlined label="Cja b5" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_b5" dense outlined label="Uni b5" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_b6" dense outlined label="Cja b6" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_b6" dense outlined label="Uni b6" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_104" dense outlined label="Cja-104" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_104" dense outlined label="Unid-104" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_105" dense outlined label="Cja-105" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_105" dense outlined label="Unid-105" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_106" dense outlined label="Cja-106" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_106" dense outlined label="Unid-106" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_107" dense outlined label="Cja-107" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_107" dense outlined label="Unid-107" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_108" dense outlined label="Cja-108" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_108" dense outlined label="Unid-108" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cja_109" dense outlined label="Cja-109" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_uni_109" dense outlined label="Unid-109" /></div>
            <div class="col-12"><q-input v-model="detalleEdit.pollo_rango_unidades" dense outlined label="Rango pollo (unidades)" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_ala" dense outlined label="Ala" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_ala_unidad" :options="unidadesPollo" dense outlined label="Unidad ala" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cadera" dense outlined label="Cadera" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_cadera_unidad" :options="unidadesPollo" dense outlined label="Unidad cadera" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_pecho" dense outlined label="Pecho" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_pecho_unidad" :options="unidadesPollo" dense outlined label="Unidad pecho" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_pi_mu" dense outlined label="Pi/Mu" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_pi_mu_unidad" :options="unidadesPollo" dense outlined label="Unidad pi/mu" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_filete" dense outlined label="Filete" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_filete_unidad" :options="unidadesPollo" dense outlined label="Unidad filete" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_cuello" dense outlined label="Cuello" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_cuello_unidad" :options="unidadesPollo" dense outlined label="Unidad cuello" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_hueso" dense outlined label="Hueso" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_hueso_unidad" :options="unidadesPollo" dense outlined label="Unidad hueso" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_menudencia" dense outlined label="Menudencia" /></div>
            <div class="col-12 col-md-6"><q-select v-model="detalleEdit.pollo_menudencia_unidad" :options="unidadesPollo" dense outlined label="Unidad menudencia" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_bs" dense outlined label="BS" /></div>
            <div class="col-12 col-md-6"><q-input v-model="detalleEdit.pollo_bs2" dense outlined label="BS2" /></div>
            <div class="col-12"><q-input v-model="detalleEdit.observacion" dense outlined label="Observacion detalle" /></div>
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat color="negative" label="Cerrar" v-close-popup />
          <q-btn color="primary" label="Guardar detalle" @click="saveDetalleDialog" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogView">
      <q-card style="width: 900px; max-width: 98vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Pedido #{{ viewPedido?.id }}</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-card-section class="q-pt-sm">
          <div class="row q-col-gutter-sm q-mb-sm">
            <div class="col-12 col-md-3"><b>Vendedor:</b> {{ viewPedido?.user?.name || '-' }}</div>
            <div class="col-12 col-md-3"><b>Cliente:</b> {{ viewPedido?.cliente?.nombre || '-' }}</div>
            <div class="col-12 col-md-2"><b>Fecha/Hora:</b> {{ viewPedido?.fecha || '-' }} {{ viewPedido?.hora || '-' }}</div>
            <div class="col-12 col-md-2"><b>Pago:</b> {{ viewPedido?.tipo_pago || '-' }}</div>
            <div class="col-12 col-md-2"><b>Estado:</b> {{ viewPedido?.estado || '-' }}</div>
          </div>
          <q-markup-table dense flat bordered>
            <thead>
            <tr>
              <th>Codigo</th>
              <th>Producto</th>
              <th>Cantidad</th>
              <th>Precio</th>
              <th>Subtotal</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="d in (viewPedido?.detalles || [])" :key="d.id">
              <td>{{ d.producto?.codigo || d.producto_id }}</td>
              <td>{{ d.producto?.nombre || ('Producto ' + d.producto_id) }}</td>
              <td>{{ Number(d.cantidad || 0).toFixed(2) }}</td>
              <td>{{ Number(d.precio || 0).toFixed(2) }}</td>
              <td>{{ (Number(d.cantidad || 0) * Number(d.precio || 0)).toFixed(2) }}</td>
            </tr>
            </tbody>
          </q-markup-table>
          <div class="text-h6 q-mt-sm text-right">Total: {{ totalView.toFixed(2) }} Bs.</div>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, ref } from 'vue'

const { proxy } = getCurrentInstance()

const fecha = ref(new Date().toISOString().slice(0, 10))
const loading = ref(false)
const sendingAll = ref(false)
const saving = ref(false)
const search = ref('')
const tipoFiltro = ref('TODOS')
const vendedorId = ref(null)
const estadoFiltro = ref('TODOS')
const clienteId = ref(null)
const vendedoresOptions = ref([{ label: 'Todos', value: null }])
const clienteOptions = ref([{ label: 'Todos', value: null }])
const estadoFiltroOptions = [
  { label: 'Todos', value: 'TODOS' },
  { label: 'Creado', value: 'Creado' },
  { label: 'Enviado', value: 'Enviado' },
]

const pedidos = ref([])
const stats = ref({
  total: 0,
  creado: 0,
  pendiente: 0,
  enviado: 0,
  no_enviado: 0,
  monto_total: 0,
  tipo_normal: 0,
  tipo_pollo: 0,
  tipo_res: 0,
  tipo_cerdo: 0,
})

const dialogEdit = ref(false)
const dialogDetalle = ref(false)
const dialogView = ref(false)
const viewPedido = ref(null)
const detalleEditIndex = ref(-1)
const detalleEdit = ref({})
const detalleTipo = ref('NORMAL')
const unidadesPollo = ['KG', 'CAJA', 'UNIDAD']
const editForm = ref({ id: null, productos: [] })
const tiposPagoOptions = [
  { label: 'Contado', value: 'Contado' },
  { label: 'Pago QR', value: 'QR' },
  { label: 'Credito', value: 'Credito' },
  { label: 'Boleta anterior', value: 'Boleta anterior' }
]
const horariosPedido = [
  { label: '06:00-07:30', value: '06:00-07:30' },
  { label: '07:30-09:00', value: '07:30-09:00' },
  { label: '09:00-10:30', value: '09:00-10:30' },
  { label: '10:30-12:00', value: '10:30-12:00' },
  { label: 'SEGUNDA VUELTA', value: 'SEGUNDA VUELTA' },
  { label: 'SE RECOGE', value: 'SE RECOGE' },
]

const productosSource = ref([])
const productosOptions = ref([])
const newProductoId = ref(null)
const tipoFiltroOptions = [
  { label: 'Todos', value: 'TODOS' },
  { label: 'Normal', value: 'NORMAL' },
  { label: 'Pollo', value: 'POLLO' },
  { label: 'Res', value: 'RES' },
  { label: 'Cerdo', value: 'CERDO' },
]

const enviables = computed(() => pedidosFiltrados.value.filter(p => isEditable(p)))
const totalEdit = computed(() => editForm.value.productos.reduce((acc, p) => acc + (Number(p.cantidad || 0) * Number(p.precio || 0)), 0))
const totalView = computed(() => (viewPedido.value?.detalles || []).reduce((acc, d) => acc + (Number(d.cantidad || 0) * Number(d.precio || 0)), 0))
const detalleTipoLabel = computed(() => detalleTipo.value === 'RES' ? 'Res' : detalleTipo.value === 'CERDO' ? 'Cerdo' : detalleTipo.value === 'POLLO' ? 'Pollo' : 'Normal')

const pedidosFiltrados = computed(() => {
  const term = search.value.trim().toLowerCase()
  return pedidos.value.filter((p) => {
    const cumpleTipo = tipoFiltro.value === 'TODOS' || pedidoTipos(p).includes(tipoFiltro.value)
    if (!cumpleTipo) return false
    if (!term) return true
    const vendedor = (p.user?.name || '').toLowerCase()
    const cliente = (p.cliente?.nombre || '').toLowerCase()
    const comanda = String(p.id)
    const productos = (p.detalles || []).map(d => d.producto?.nombre || '').join(' ').toLowerCase()
    return cliente.includes(term) || comanda.includes(term) || productos.includes(term) || vendedor.includes(term)
  })
})

function normalizeTipo (tipo) {
  const t = String(tipo || 'NORMAL').trim().toUpperCase()
  if (t === 'POLLO' || t === 'RES' || t === 'CERDO') return t
  return 'NORMAL'
}

function estadoColor (estado) {
  if (estado === 'Enviado') return 'green'
  if (estado === 'Pendiente') return 'deep-orange'
  if (estado === 'Creado') return 'orange'
  if (estado === 'Anulado') return 'negative'
  return 'primary'
}

function tipoColor (tipo) {
  if (tipo === 'POLLO') return 'orange'
  if (tipo === 'RES') return 'red'
  if (tipo === 'CERDO') return 'brown'
  return 'primary'
}

function pedidoTipos (pedido) {
  const tipos = []
  if (pedido?.contiene_normal) tipos.push('NORMAL')
  if (pedido?.contiene_pollo) tipos.push('POLLO')
  if (pedido?.contiene_res) tipos.push('RES')
  if (pedido?.contiene_cerdo) tipos.push('CERDO')
  if (tipos.length > 0) return tipos

  const tiposDetalle = Array.from(new Set(
    (pedido?.detalles || [])
      .map((d) => normalizeTipo(d?.producto?.tipo))
      .filter(Boolean)
  ))
  return tiposDetalle.length > 0 ? tiposDetalle : ['NORMAL']
}

function isEditable (pedido) {
  return pedido && (pedido.estado === 'Creado' || pedido.estado === 'Pendiente')
}

function productImageUrl (path) {
  const safe = path || 'uploads/default.png'
  return `${proxy.$url}../${safe}`
}

function detalleDefaultsByTipo (tipo) {
  if (tipo === 'RES') {
    return { precio_res: '', res_trozado: '', res_entero: '', res_pierna: '', res_brazo: '', observacion: '' }
  }
  if (tipo === 'CERDO') {
    return { cerdo_precio_total: '', cerdo_entero: '', cerdo_desmembrado: '', cerdo_corte: '', cerdo_kilo: '', observacion: '' }
  }
  if (tipo === 'POLLO') {
    return {
      pollo_cja_b5: '', pollo_uni_b5: '', pollo_cja_b6: '', pollo_uni_b6: '',
      pollo_cja_104: '', pollo_uni_104: '', pollo_cja_105: '', pollo_uni_105: '',
      pollo_cja_106: '', pollo_uni_106: '', pollo_cja_107: '', pollo_uni_107: '',
      pollo_cja_108: '', pollo_uni_108: '', pollo_cja_109: '', pollo_uni_109: '',
      pollo_rango_unidades: '', pollo_ala: '', pollo_ala_unidad: 'KG', pollo_cadera: '',
      pollo_cadera_unidad: 'KG', pollo_pecho: '', pollo_pecho_unidad: 'KG', pollo_pi_mu: '',
      pollo_pi_mu_unidad: 'KG', pollo_filete: '', pollo_filete_unidad: 'KG', pollo_cuello: '',
      pollo_cuello_unidad: 'KG', pollo_hueso: '', pollo_hueso_unidad: 'KG',
      pollo_menudencia: '', pollo_menudencia_unidad: 'KG', pollo_bs: '', pollo_bs2: '',
      observacion: ''
    }
  }
  return { observacion: '' }
}

function openDetalleDialog (item, index) {
  detalleEditIndex.value = index
  detalleTipo.value = normalizeTipo(item?.tipo)
  detalleEdit.value = { ...detalleDefaultsByTipo(detalleTipo.value), ...(item?.detalle_extra || {}) }
  dialogDetalle.value = true
}

function saveDetalleDialog () {
  if (detalleEditIndex.value < 0 || !editForm.value.productos[detalleEditIndex.value]) return
  const current = editForm.value.productos[detalleEditIndex.value]
  current.detalle_extra = { ...detalleEdit.value }
  current.observacion = detalleEdit.value.observacion || current.observacion || ''
  dialogDetalle.value = false
  detalleEditIndex.value = -1
  detalleEdit.value = {}
}

function filtrarProductos (val, update) {
  update(() => {
    const needle = (val || '').toLowerCase()
    if (!needle) {
      productosOptions.value = [...productosSource.value]
      return
    }
    productosOptions.value = productosSource.value.filter(p => p.label.toLowerCase().includes(needle))
  })
}

function agregarProducto () {
  if (!newProductoId.value) return
  const p = productosSource.value.find(x => x.id === newProductoId.value)
  if (!p) return
  const ex = editForm.value.productos.find(x => x.producto_id === p.id)
  if (ex) {
    ex.cantidad += 1
  } else {
    editForm.value.productos.push({
      producto_id: p.id,
      codigo: p.codigo,
      nombre: p.nombre,
      imagen: p.imagen || 'uploads/default.png',
      tipo: p.tipo,
      cantidad: 1,
      precio: p.precio,
      observacion: '',
      detalle_extra: detalleDefaultsByTipo(p.tipo),
    })
  }
  newProductoId.value = null
}

function editarPedido (pedido) {
  if (!isEditable(pedido)) {
    proxy.$alert.error('El pedido enviado ya no se puede modificar')
    return
  }
  editForm.value = {
    id: pedido.id,
    fecha: pedido.fecha,
    hora: pedido.hora || horariosPedido[0].value,
    facturado: !!pedido.facturado,
    tipo_pago: pedido.tipo_pago || 'Contado',
    observaciones: pedido.observaciones || '',
    productos: (pedido.detalles || []).map(d => ({
      producto_id: d.producto_id,
      codigo: d.producto?.codigo,
      nombre: d.producto?.nombre || `Producto ${d.producto_id}`,
      imagen: d.producto?.imagen || 'uploads/default.png',
      tipo: normalizeTipo(d.producto?.tipo),
      cantidad: Number(d.cantidad || 0),
      precio: Number(d.precio || 0),
      observacion: d.observacion_detalle || '',
      detalle_extra: { ...detalleDefaultsByTipo(normalizeTipo(d.producto?.tipo)), ...(d.detalle_extra || {}) },
    }))
  }
  dialogEdit.value = true
}

function verPedido (pedido) {
  viewPedido.value = pedido
  dialogView.value = true
}

async function guardarEdicion () {
  if (!editForm.value.id) return
  try {
    const productos = editForm.value.productos
      .map((p) => ({
        producto_id: p.producto_id,
        cantidad: Number(p.cantidad || 0),
        precio: Number(p.precio || 0),
        observacion: p.observacion || '',
        detalle_extra: p.detalle_extra || {}
      }))
      .filter((p) => p.cantidad > 0)

    if (productos.length === 0) {
      proxy.$alert.error('Debe agregar al menos un producto')
      return
    }

    saving.value = true
    await proxy.$axios.put(`/pedidos/${editForm.value.id}`, {
      fecha: editForm.value.fecha,
      hora: editForm.value.hora,
      facturado: editForm.value.facturado,
      tipo_pago: editForm.value.tipo_pago,
      observaciones: editForm.value.observaciones,
      comentario_visita: editForm.value.observaciones,
      productos,
    })
    proxy.$alert.success('Pedido actualizado')
    dialogEdit.value = false
    await cargarPedidos()
  } catch (e) {
    proxy.$alert.error(e?.message || e?.response?.data?.message || 'No se pudo guardar')
  } finally {
    saving.value = false
  }
}

async function enviarUno (pedido) {
  if (!isEditable(pedido)) return
  try {
    await proxy.$axios.put(`/pedidos/${pedido.id}/enviar`)
    proxy.$alert.success('Pedido enviado')
    await cargarPedidos()
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo enviar')
  }
}

async function enviarTodos () {
  if (enviables.value.length === 0) return
  const confirmado = await new Promise((resolve) => {
    proxy.$q.dialog({
      title: 'Envio emergencia',
      message: `Se enviaran ${enviables.value.length} pedido(s). Desea continuar?`,
      cancel: true,
      persistent: true,
      ok: { label: 'Si, enviar', color: 'warning', textColor: 'black' },
    }).onOk(() => resolve(true)).onCancel(() => resolve(false))
  })
  if (!confirmado) return

  sendingAll.value = true
  try {
    await proxy.$axios.post('/pedidos-totales/enviar-emergencia', {
      fecha: fecha.value,
      user_id: vendedorId.value,
      cliente_id: clienteId.value,
      estado: estadoFiltro.value,
      ids: enviables.value.map(p => p.id)
    })
    proxy.$alert.success('Envio emergencia completado')
    await cargarPedidos()
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo completar el envio emergencia')
  } finally {
    sendingAll.value = false
  }
}

async function cargarPedidos () {
  loading.value = true
  try {
    const res = await proxy.$axios.get('/pedidos-totales', {
      params: {
        fecha: fecha.value,
        user_id: vendedorId.value,
        cliente_id: clienteId.value,
        estado: estadoFiltro.value,
      },
    })
    pedidos.value = res.data?.data || []
    const vendedores = Array.isArray(res.data?.vendedores) ? res.data.vendedores : []
    vendedoresOptions.value = [
      { label: 'Todos', value: null },
      ...vendedores.map(v => ({ label: v.name, value: v.id })),
    ]
    const clientesMap = new Map()
    ;(pedidos.value || []).forEach((p) => {
      if (p?.cliente?.id) clientesMap.set(p.cliente.id, p.cliente.nombre || `Cliente ${p.cliente.id}`)
    })
    clienteOptions.value = [
      { label: 'Todos', value: null },
      ...Array.from(clientesMap.entries()).map(([id, label]) => ({ label, value: id })),
    ]
    stats.value = res.data?.stats || {
      total: 0,
      creado: 0,
      pendiente: 0,
      enviado: 0,
      no_enviado: 0,
      monto_total: 0,
      tipo_normal: 0,
      tipo_pollo: 0,
      tipo_res: 0,
      tipo_cerdo: 0,
    }
  } catch (e) {
    proxy.$alert.error(e?.response?.data?.message || 'No se pudo cargar pedidos')
  } finally {
    loading.value = false
  }
}

async function cargarProductos () {
  try {
    const res = await proxy.$axios.get('/productosAll')
    const rows = Array.isArray(res.data) ? res.data : []
    productosSource.value = rows.map((p) => ({
      id: p.id,
      codigo: p.codigo,
      nombre: p.nombre,
      imagen: p.imagen || 'uploads/default.png',
      tipo: normalizeTipo(p.tipo),
      precio: Number(p.precio1 || 0),
      label: `${p.codigo || p.id}-${p.nombre} ${Number(p.precio1 || 0).toFixed(2)} Bs`,
    }))
    productosOptions.value = [...productosSource.value]
  } catch (_) {
    productosSource.value = []
    productosOptions.value = []
  }
}

onMounted(async () => {
  await Promise.all([cargarPedidos(), cargarProductos()])
})
</script>

<style scoped>
.misped-page {
  background: linear-gradient(180deg, #eef4ff 0%, #f8fbff 30%, #ffffff 100%);
}
.hero-card {
  border: 1px solid #dbe7ff;
  background:
    radial-gradient(1200px 280px at 10% 0%, rgba(30, 136, 229, 0.12), transparent 70%),
    radial-gradient(1000px 240px at 95% 20%, rgba(102, 187, 106, 0.12), transparent 70%),
    #fff;
}
</style>
