<template>
  <q-layout view="lHh Lpr lFf" class="main-shell">
    <q-header class="app-header" bordered>
      <q-toolbar class="header-toolbar">
        <q-btn
          dense
          round
          flat
          color="primary"
          :icon="leftDrawerOpen ? 'menu_open' : 'menu'"
          aria-label="Menu"
          @click="toggleLeftDrawer"
        />

        <div class="header-brand">
          <div class="header-brand__title">Sofia Factu</div>
          <div class="header-brand__meta">v{{ version }}</div>
        </div>

        <q-space />

        <div class="q-mr-sm">
          <q-btn flat round dense icon="notifications" color="grey-8" @click="loadFallas">
            <q-badge v-if="fallasPendientes > 0" color="negative" floating>{{ fallasPendientes }}</q-badge>
            <q-tooltip>Fallas CUFD</q-tooltip>
            <q-menu>
              <q-list style="min-width: 360px; max-width: 420px;">
                <q-item-label header>
                  Alertas de impuestos
                </q-item-label>
                <q-item v-if="fallas.length === 0">
                  <q-item-section>Sin fallas pendientes</q-item-section>
                </q-item>
                <q-item v-for="falla in fallas.slice(0, 5)" :key="falla.id">
                  <q-item-section>
                    <q-item-label class="text-negative text-weight-bold">Fallo generacion CUFD</q-item-label>
                    <q-item-label caption>{{ falla.mensaje }}</q-item-label>
                    <q-item-label caption>{{ falla.detalle?.error || '' }}</q-item-label>
                  </q-item-section>
                </q-item>
                <q-separator />
                <q-item clickable v-close-popup @click="$router.push('/impuestos')">
                  <q-item-section avatar><q-icon name="settings" /></q-item-section>
                  <q-item-section>Ir a Impuestos / Ajustes</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>
        </div>

        <q-btn
          flat
          no-caps
          dense
          class="user-summary"
          @click="toggleLeftDrawer"
        >

          <q-avatar rounded size="34px" class="user-trigger__avatar">
            <q-img :src="$url + '../images/' + $store.user.avatar" v-if="$store.user.avatar" />
            <q-icon v-else name="person" color="grey-7" />
          </q-avatar>
          <div class="user-summary__copy">
            <div class="user-summary__name">{{ formatCompactText(userDisplayName) }}</div>
            <div class="user-summary__name">
<!--              {{ userDisplayRole }}-->
              <q-chip size="xs" color="primary" text-color="white" :label="userDisplayRole" />
            </div>
          </div>

          <q-tooltip anchor="bottom middle" self="top middle">
            {{ userDisplayName }} - {{ userDisplayRole }}
          </q-tooltip>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-drawer
      v-model="leftDrawerOpen"
      bordered
      show-if-above
      :width="180"
      :breakpoint="900"
      class="app-drawer"
    >
      <div class="drawer-inner">
        <div class="drawer-brand">
          <q-avatar size="46px" rounded class="drawer-brand__logo">
            <q-img src="/logo.png" fit="contain" />
          </q-avatar>
          <div class="drawer-brand__copy">
            <div class="drawer-brand__title">Sofia Factu</div>
            <div class="drawer-brand__subtitle">Ventas, pedidos y facturacion</div>
          </div>
        </div>

        <q-scroll-area class="drawer-scroll">
          <q-list class="menu-list">
            <template v-for="group in groupedLinks" :key="group.title">
              <div v-if="group.links.length" class="menu-group">
                <div class="menu-group__label">{{ group.title }}</div>

                <q-item
                  v-for="link in group.links"
                  :key="link.title"
                  clickable
                  :to="link.link"
                  exact
                  class="menu-item"
                  active-class="menu-item--active"
                  dense
                  v-close-popup
                >
                  <q-item-section avatar class="menu-item__avatar">
                    <q-icon :name="$route.path === link.link ? 'o_' + link.icon : link.icon" size="18px" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="menu-item__label">{{ link.title }}</q-item-label>
                  </q-item-section>
                </q-item>
              </div>
            </template>
          </q-list>
        </q-scroll-area>

        <div class="drawer-footer">
          <q-item clickable class="menu-item menu-item--logout" @click="logout" v-close-popup dense>
            <q-item-section avatar class="menu-item__avatar">
              <q-icon name="logout" size="18px" />
            </q-item-section>
            <q-item-section>
              <q-item-label class="menu-item__label">Salir</q-item-label>
            </q-item-section>
          </q-item>
        </div>
      </div>
    </q-drawer>

    <q-page-container class="page-shell">
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, onMounted, ref } from 'vue'

const { proxy } = getCurrentInstance()
const linksList = ref([])

const leftDrawerOpen = ref(false)
const fallasPendientes = ref(0)
const fallas = ref([])
let pollTimer = null

const version = import.meta.env.VITE_API_VERSION

const menuGroups = [
  { title: 'General', keys: ['Principal'] },
  { title: 'Comercial', keys: ['Clientes', 'Nueva Cliente', 'Ventas', 'Nueva Venta', 'Pedidos', 'Realizar pedido', 'Mis pedidos', 'Mis pedidos totales', 'Visitas', 'Cobranzas deudas', 'Historial cobranzas'] },
  { title: 'Inventario', keys: ['Productos', 'Productos por vencer', 'Productos vencidos', 'Proveedores', 'Compras', 'Compras Nueva', 'Auxiliar de camara'] },
  { title: 'Operacion', keys: ['Rutas', 'Despacho', 'Mapa cliente', 'Mapa zona', 'Mapa zonas', 'Verificacion'] },
  { title: 'Administracion', keys: ['Usuarios', 'Impuestos', 'Digitador factura'] },
]

const groupedLinks = computed(() => {
  const visibleLinks = linksList.value.filter((link) => canSee(link))
  const groups = menuGroups.map((group) => ({
    title: group.title,
    links: group.keys
      .map((key) => visibleLinks.find((link) => link.title === key))
      .filter(Boolean),
  }))

  const groupedTitles = new Set(groups.flatMap((group) => group.links.map((link) => link.title)))
  const remainder = visibleLinks.filter((link) => !groupedTitles.has(link.title))
  if (remainder.length) {
    groups.push({ title: 'Otros', links: remainder })
  }

  return groups
})

const currentUser = computed(() => proxy.$store?.user || {})
const userDisplayName = computed(() => {
  const user = currentUser.value
  return user.name || user.username || 'Usuario'
})
const userDisplayRole = computed(() => {
  const user = currentUser.value
  return user.role || 'Sin rol'
})
onMounted(() => {
  const baseLinks = [
    { title: 'Principal', icon: 'home', link: '/', always: true },
    { title: 'Usuarios', icon: 'people', link: '/usuarios', perm: 'Usuarios' },
    { title: 'Impuestos', icon: 'percent', link: '/impuestos', perm: 'Impuestos' },
    { title: 'Productos', icon: 'inventory_2', link: '/productos', perm: 'Productos' },
    { title: 'Clientes', icon: 'groups', link: '/clientes', perm: 'Clientes' },
    { title: 'Nueva Cliente', icon: 'person_add', link: '/alta-cliente', perm: 'Clientes' },
    { title: 'Ventas', icon: 'point_of_sale', link: '/venta', perm: 'Ventas' },
    { title: 'Nueva Venta', icon: 'add_shopping_cart', link: '/ventaNuevo', perm: 'Nueva Venta' },
    { title: 'Proveedores', icon: 'store', link: '/proveedores', perm: 'Proveedores' },
    { title: 'Compras', icon: 'shopping_basket', link: '/compras', perm: 'Compras' },
    { title: 'Compras Nueva', icon: 'post_add', link: '/compras-create', perm: 'Nueva Compra' },
    { title: 'Productos por vencer', icon: 'warning_amber', link: '/productos-vencer', perm: 'Productos por vencer' },
    { title: 'Productos vencidos', icon: 'dangerous', link: '/productos-vencidos', perm: 'Productos vencidos' },
    { title: 'Pedidos', icon: 'receipt_long', link: '/pedidos', perm: 'Pedidos' },
    { title: 'Visitas', icon: 'map', link: '/visitas', perm: 'Pedidos' },
    { title: 'Mis pedidos', icon: 'task_alt', link: '/mis-pedidos', perm: 'Pedidos' },
    { title: 'Mis pedidos totales', icon: 'summarize', link: '/mis-pedidos-totales', perm: 'Mis pedidos totales' },
    { title: 'Mapa cliente', icon: 'location_on', link: '/mapa-cliente', perm: 'Mapa cliente' },
    { title: 'Mapa zona', icon: 'polyline', link: '/mapa-zona', perm: 'Mapa zona' },
    { title: 'Mapa zonas', icon: 'grid_view', link: '/mapa-zonas', perm: 'Mapa cliente zonas' },
    { title: 'Auxiliar de camara', icon: 'warehouse', link: '/auxiliar-camara', perm: 'Auxiliar de camara' },
    { title: 'Verificacion', icon: 'fact_check', link: '/verificacion', perm: 'Verificacion' },
    { title: 'Digitador factura', icon: 'receipt', link: '/digitador-factura', perm: 'Digitador factura' },
    { title: 'Cobranzas deudas', icon: 'payments', link: '/cobranzas', perm: 'Cobranzas' },
    { title: 'Historial cobranzas', icon: 'history', link: '/historial-cobranzas', perm: 'Cobranzas' },
    { title: 'Rutas', icon: 'route', link: '/rutas-camion', perm: 'Rutas', esCamion: true },
    { title: 'Despacho', icon: 'local_shipping', link: '/despacho-camion', perm: 'Despacho', esCamion: true },
    { title: 'Realizar pedido', icon: 'shopping_cart_checkout', link: '/pedidosCompra', perm: 'Nuevo Pedido' },
  ]

  linksList.value = baseLinks
  loadFallas()
})

onBeforeUnmount(() => {
  if (pollTimer) clearInterval(pollTimer)
})

function toggleLeftDrawer() {
  leftDrawerOpen.value = !leftDrawerOpen.value
}

function logout() {
  proxy.$alert.dialog('Desea salir del sistema?')
    .onOk(() => {
      proxy.$axios.post('/logout')
        .then(() => {
          proxy.$store.isLogged = false
          proxy.$store.user = {}
          localStorage.removeItem('tokenSofiaFactu')
          localStorage.removeItem('user')
          proxy.$alert.success('Sesion cerrada', 'Exito')
          proxy.$router.push('/login')
        })
        .catch(() => {
          proxy.$store.isLogged = false
          proxy.$store.user = {}
          localStorage.removeItem('tokenSofiaFactu')
          localStorage.removeItem('user')
          proxy.$alert.success('Sesion cerrada', 'Exito')
          proxy.$router.push('/login')
        })
    })
}

function canSee(link) {
  if (!link || !proxy.$store?.user) return false
  if (link.always) return true

  const isCamion = !!proxy.$store.user.es_camion
  const isCobrador = String(proxy.$store.user.role || '').toUpperCase() === 'COBRADOR'
  const perms = (proxy.$store.permissions || [])
    .map((permission) => typeof permission === 'string' ? permission : permission?.name)
    .filter(Boolean)
  const requiredPerm = link.perm || link.title

  if (perms.includes(requiredPerm)) return true
  if (requiredPerm === 'Cobranzas' && isCobrador) return true
  return !!link.esCamion && isCamion
}

function loadFallas(showError = false) {
  proxy.$axios.get('/impuestos/fallas')
    .then((res) => {
      fallasPendientes.value = res.data?.pending || 0
      fallas.value = (res.data?.data || []).filter((item) => item.estado === 'pendiente')
    })
    .catch((err) => {
      if (showError) {
        proxy.$alert.error(err?.response?.data?.message || 'No se pudieron cargar fallas de impuestos')
      }
    })
}

function formatCompactText(value) {
  const text = String(value || '').trim().toLowerCase()
  if (!text) return ''
  return text.charAt(0).toUpperCase() + text.slice(1)
}
</script>

<style scoped>
.main-shell {
  background: #eef2f6;
}

.app-header {
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(10px);
}

.header-toolbar {
  min-height: 60px;
  padding: 0 14px;
}

.header-brand {
  margin-left: 10px;
  line-height: 1.05;
}

.header-brand__title {
  font-size: 15px;
  font-weight: 700;
  color: #182433;
  letter-spacing: 0.02em;
}

.header-brand__meta {
  font-size: 11px;
  color: #6c7a89;
}

.user-summary {
  min-height: 48px;
  padding: 4px 10px 4px 6px;
  border-radius: 16px;
  background: linear-gradient(135deg, #ffffff 0%, #eef4fb 100%);
  box-shadow: inset 0 0 0 1px rgba(33, 67, 108, 0.08), 0 10px 24px rgba(24, 48, 79, 0.08);
  gap: 10px;
}

.user-summary:hover {
  background: linear-gradient(135deg, #ffffff 0%, #e8f1fb 100%);
}

.user-summary__avatar {
  background: linear-gradient(135deg, #163a63 0%, #2f6cb0 100%);
  color: #ffffff;
  font-size: 15px;
  font-weight: 800;
  letter-spacing: 0.03em;
  box-shadow: 0 8px 18px rgba(35, 82, 136, 0.26);
}

.user-summary__copy {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  min-width: 0;
  text-align: left;
  line-height: 1.08;
}

.user-summary__name {
  max-width: 168px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 13px;
  font-weight: 800;
  color: #142436;
}

.user-summary__role {
  max-width: 168px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-top: 3px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #6d7e91;
}

.app-drawer {
  background: linear-gradient(180deg, #f7fafc 0%, #f0f4f8 100%);
  color: #223142;
}

.drawer-inner {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.drawer-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 14px 10px;
  border-bottom: 1px solid rgba(27, 43, 65, 0.08);
}

.drawer-brand__logo {
  background: #ffffff;
  box-shadow: 0 8px 20px rgba(32, 54, 86, 0.08);
}

.drawer-brand__copy {
  min-width: 0;
}

.drawer-brand__title {
  font-size: 14px;
  font-weight: 700;
  color: #162334;
}

.drawer-brand__subtitle {
  font-size: 11px;
  color: #708095;
}

.drawer-scroll {
  flex: 1;
}

.menu-list {
  padding: 8px 8px 12px;
}

.menu-group {
  margin-bottom: 10px;
}

.menu-group__label {
  padding: 6px 10px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #7a8795;
}

.menu-item {
  min-height: 34px;
  margin-bottom: 2px;
  padding: 0 10px;
  border-radius: 10px;
  color: #334155;
  transition: background-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
}

.menu-item:hover {
  background: rgba(32, 72, 121, 0.08);
  color: #10243d;
}

.menu-item--active {
  background: linear-gradient(90deg, #dceeff 0%, #eef6ff 100%);
  color: #12314f;
  box-shadow: inset 0 0 0 1px rgba(47, 108, 176, 0.12);
}

.menu-item__avatar {
  min-width: 28px;
  color: inherit;
}

.menu-item__label {
  font-size: 12.5px;
  font-weight: 600;
  line-height: 1.2;
}

.drawer-footer {
  padding: 8px;
  border-top: 1px solid rgba(27, 43, 65, 0.08);
}

.menu-item--logout {
  color: #7a1d1d;
}

.page-shell {
  background: #eef2f6;
}

@media (max-width: 700px) {
  .header-toolbar {
    padding: 0 10px;
  }

  .user-summary {
    padding-right: 6px;
  }

  .user-summary__copy {
    display: none;
  }

  .drawer-brand__subtitle {
    display: none;
  }
}
</style>

