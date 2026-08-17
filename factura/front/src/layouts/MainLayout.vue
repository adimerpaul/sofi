<template>
  <q-layout view="lHh Lpr lFf">
    <q-header class="bg-white text-black" bordered>
      <q-toolbar>
        <q-btn
          flat
          dense
          round
          icon="menu"
          aria-label="Menu"
          @click="toggleLeftDrawer"
        />
        <span class="q-pa-xs text-bold">{{ version }}</span>
        <q-toolbar-title />
        <q-btn-dropdown flat unelevated no-caps dropdownIcon="expand_more">
          <template v-slot:label>
            <q-avatar rounded>
              <q-img :src="$url + '../images/' + $store.user.avatar" v-if="$store.user.avatar" />
            </q-avatar>
            <div class="text-center" style="line-height: 1">
              <div style="width: 100px; white-space: normal; overflow-wrap: break-word;">
                {{ $store.user.name }}<br>
                <q-chip :color="$store.user.role === 'Admin' ? 'red' : 'blue'" dense size="xs" class="text-white">
                  {{ $store.user.role }}
                </q-chip>
              </div>
            </div>
          </template>
          <q-item clickable v-ripple @click="logout" v-close-popup>
            <q-item-section avatar>
              <q-icon name="logout" />
            </q-item-section>
            <q-item-section>
              <q-item-label>Salir</q-item-label>
            </q-item-section>
          </q-item>
        </q-btn-dropdown>
      </q-toolbar>
    </q-header>

    <q-drawer
      v-model="leftDrawerOpen"
      show-if-above
      :width="220"
      :breakpoint="500"
      class="drawer-shell text-white"
    >
      <div class="drawer-content">
        <div class="drawer-logo">
          <q-img src="/logo.png" width="80px" />
        </div>

        <div class="drawer-profile">
          <div class="drawer-profile__avatar">
            <q-icon name="account_circle" size="32px" />
          </div>
          <div class="drawer-profile__info">
            <div class="drawer-profile__name">{{ $store.user.name }}</div>
            <q-chip
              :color="$store.user.role === 'Admin' ? 'red' : 'blue'"
              text-color="white"
              dense
              class="drawer-profile__role"
            >
              {{ $store.user.role }}
            </q-chip>
          </div>
        </div>

        <div class="drawer-section-label">Navegación</div>

        <q-list dense class="drawer-list">
          <template v-for="section in visibleSections" :key="section.title">
            <q-expansion-item
              dense
              dense-toggle
              expand-separator
              default-opened
              :icon="section.icon"
              :label="section.title"
              :header-class="sectionIsActive(section) ? 'drawer-group drawer-group--active' : 'drawer-group'"
            >
              <q-list dense class="q-px-xs q-pb-xs">
                <q-item
                  v-for="link in section.links.filter(canAccess)"
                  :key="link.title"
                  clickable
                  :to="link.link"
                  exact
                  class="drawer-link"
                  active-class="menu"
                >
                  <q-item-section avatar class="drawer-link__avatar">
                    <q-icon
                      :name="linkIsActive(link) ? 'o_' + link.icon : link.icon"
                      :class="linkIsActive(link) ? 'text-white' : 'text-blue-grey-2'"
                    />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label :class="linkIsActive(link) ? 'text-white text-weight-bold' : 'text-blue-grey-1'">
                      {{ link.title }}
                    </q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </q-expansion-item>
          </template>
        </q-list>

        <q-item clickable class="drawer-logout" @click="logout">
          <q-item-section avatar>
            <q-icon name="exit_to_app" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Salir</q-item-label>
          </q-item-section>
        </q-item>
      </div>
    </q-drawer>

    <q-page-container class="bg-grey-3">
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { computed, getCurrentInstance, ref } from 'vue'

const { proxy } = getCurrentInstance()

const leftDrawerOpen = ref(false)
const version = import.meta.env.VITE_API_VERSION

const menuSections = [
  {
    title: 'General',
    icon: 'space_dashboard',
    links: [
      { title: 'Principal', icon: 'home', link: '/', can: ['Todos'] },
    ]
  },
  {
    title: 'Farmacia',
    icon: 'local_pharmacy',
    links: [
      { title: 'Productos', icon: 'shopping_cart', link: '/productos', can: ['Todos'] },
      { title: 'Ventas', icon: 'shopping_bag', link: '/venta', can: ['Todos'] },
      { title: 'Nueva Venta', icon: 'add_shopping_cart', link: '/ventaNuevo', can: ['Todos'] },
      { title: 'Proveedores', icon: 'manage_accounts', link: '/proveedores', can: ['Todos'] },
      { title: 'Compras', icon: 'storefront', link: '/compras', can: ['Todos'] },
      { title: 'Compras Nueva', icon: 'shopping_basket', link: '/compras-create', can: ['Todos'] },
    ]
  },
  {
    title: 'Control',
    icon: 'health_and_safety',
    links: [
      { title: 'Por vencer', icon: 'warning', link: '/productos-vencer', can: ['Todos'] },
      { title: 'Vencidos', icon: 'do_not_touch', link: '/productos-vencidos', can: ['Todos'] },
      { title: 'Pedidos', icon: 'real_estate_agent', link: '/pedidos', can: ['Todos'] },
      { title: 'Realizar pedido', icon: 'shopping_cart_checkout', link: '/pedidosCompra', can: ['Todos'] },
    ]
  },
  {
    title: 'Administración',
    icon: 'admin_panel_settings',
    links: [
      { title: 'Usuarios', icon: 'people', link: '/usuarios', can: ['Admin'] },
      { title: 'Impuestos', icon: 'percent', link: '/impuestos', can: ['Admin'] },
    ]
  },
]

function canAccess(link) {
  if (!link?.can) return false
  const required = Array.isArray(link.can) ? link.can : [link.can]
  return required.includes('Todos') || required.includes(proxy.$store.user.role)
}

const visibleSections = computed(() =>
  menuSections.filter(section => section.links.some(link => canAccess(link)))
)

function linkIsActive(link) {
  return proxy.$route.path === link.link
}

function sectionIsActive(section) {
  return section.links.some(link => linkIsActive(link))
}

function toggleLeftDrawer() {
  leftDrawerOpen.value = !leftDrawerOpen.value
}

function logout() {
  proxy.$alert.dialog('¿Desea salir del sistema?')
    .onOk(() => {
      proxy.$axios.post('/logout')
        .then(() => {
          proxy.$store.isLogged = false
          proxy.$store.user = {}
          localStorage.removeItem('tokenProvidencia')
          localStorage.removeItem('user')
          proxy.$alert.success('Sesión cerrada', 'Éxito')
          proxy.$router.push('/login')
        })
        .catch(() => {
          proxy.$store.isLogged = false
          proxy.$store.user = {}
          localStorage.removeItem('tokenProvidencia')
          localStorage.removeItem('user')
          proxy.$alert.success('Sesión cerrada', 'Éxito')
          proxy.$router.push('/login')
        })
    })
}
</script>

<style>
.drawer-shell {
  background: linear-gradient(180deg, #0f4c81 0%, #0a3558 100%);
}

.drawer-content {
  min-height: 100%;
  padding: 6px 6px 10px;
}

.drawer-logo {
  display: flex;
  justify-content: center;
  padding: 4px 0 6px;
}

.drawer-profile {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 8px;
  margin-bottom: 6px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.14);
  backdrop-filter: blur(6px);
}

.drawer-profile__avatar {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.18);
  flex-shrink: 0;
}

.drawer-profile__info {
  min-width: 0;
}

.drawer-profile__name {
  font-size: 12px;
  font-weight: 700;
  line-height: 1.1;
  margin-bottom: 2px;
}

.drawer-profile__role {
  margin-left: 0;
}

.drawer-section-label {
  padding: 2px 6px 4px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.72);
}

.drawer-list {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.drawer-group {
  margin: 0;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.08);
  color: white;
  font-weight: 600;
  min-height: 32px !important;
}

.drawer-group .q-item__section--avatar {
  min-width: 28px;
  padding-right: 4px;
}

.drawer-group--active {
  background: rgba(255, 255, 255, 0.16);
}

.drawer-link {
  min-height: 28px !important;
  border-radius: 7px;
  margin: 1px 0;
  padding: 2px 6px;
}

.drawer-link__avatar {
  min-width: 28px;
}

.menu {
  background-color: #1976D2;
  border-radius: 7px;
}

.drawer-logout {
  margin-top: 6px;
  min-height: 32px !important;
  border-radius: 8px;
  background: rgba(244, 67, 54, 0.14);
  color: #ffd5d2;
  padding: 2px 8px;
}
</style>
