<template>
  <q-layout view="lhr Lpr lfr" style="min-height: 0">
    <q-header>
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleLeftDrawer"/>
        <q-toolbar-title>
          <template v-if="$store.getters['login/user'].Nombre1==undefined">Sofia</template>
          <template v-else>
            {{ $filters.capitalize($store.getters['login/user'].Nombre1 + ' ' + $store.getters['login/user'].App1) }}
          </template>
          <q-chip dense class="bg-orange" size="10px">
            {{ '11.5.6' }}
          </q-chip>
        </q-toolbar-title>
        <div>
          <q-btn v-if="$store.getters['login/isLoggedIn']" @click="logout" size="xs" label="Salir" icon="logout" color="negative" dense no-caps/>
        </div>
      </q-toolbar>
    </q-header>
    <q-drawer v-model="leftDrawerOpen" show-if-above bordered
              :width="210"
              :breakpoint="500">
      <q-list dense class="menu-list">
        <q-item-label header class="menu-header">Opciones</q-item-label>

        <q-item
          v-for="(item, index) in menuItems"
          :key="index"
          clickable
          exact
          dense
          class="menu-item"
          active-class="menu-item--active"
          :to="item.to"
          @click="onMenuClick(item)"
        >
          <q-item-section avatar class="menu-avatar">
            <q-icon :name="item.icon" size="18px"/>
          </q-item-section>
          <q-item-section>
            <q-item-label lines="1" class="menu-label">{{ item.label }}</q-item-label>
            <q-item-label v-if="item.caption" lines="1" caption class="menu-caption">
              {{ item.caption }}
            </q-item-label>
          </q-item-section>
        </q-item>

        <template v-if="$store.getters['login/isLoggedIn']">
          <q-separator spaced class="q-mx-sm"/>
          <q-item clickable dense class="menu-item menu-item--logout" @click="logout">
            <q-item-section avatar class="menu-avatar">
              <q-icon name="logout" size="18px"/>
            </q-item-section>
            <q-item-section>
              <q-item-label lines="1" class="menu-label">Salir</q-item-label>
              <q-item-label lines="1" caption class="menu-caption">Salir del sistema</q-item-label>
            </q-item-section>
          </q-item>
        </template>
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view style="min-height: 0"/>
    </q-page-container>
  </q-layout>
</template>

<script>

export default {
  data() {
    return {
      leftDrawerOpen: false
    }
  },
  computed: {
    can() {
      return this.$store.getters['login/can']
    },
    menuItems() {
      const can = this.can

      const items = [
        { label: 'Principal', icon: 'home', to: '/', show: true },
        { label: 'Ingresar', caption: 'Ingresar al sistema', icon: 'login', to: 'login', show: !this.$store.getters['login/isLoggedIn'] },
        { label: 'Realizar Visita', icon: 'map', to: 'visita', show: can('visita') },
        { label: 'Clientes del Día', caption: 'Ver la semana', icon: 'today', to: 'clientevisita', show: can('clientevisita') },
        { label: 'Mis Pedidos', icon: 'list', to: 'mispedidos', show: can('mispedidos') },
        { label: 'Pedidos Totales', icon: 'list_alt', to: 'mispedidostotales', show: can('mispedidostotales') },
        { label: 'Lista Clientes', caption: 'Habilitar cliente', icon: 'people', to: 'clientes', show: can('clientes') },
        { label: 'Pedidos Pendientes', caption: 'Faltantes', icon: 'local_grocery_store', to: 'pendientes', show: can('pendientes') },
        { label: 'Pedidos', caption: 'Registrados', icon: 'local_mall', to: 'clientepedido', show: can('clientepedido') },
        { label: 'Listado Pedidos', caption: 'Registrados', icon: 'shopping_cart', to: 'listpedido', show: can('listpedido') },
        { label: 'Cobros Realizados', icon: 'monetization_on', to: 'cobrosrealizados', show: can('cobrosrealizados') },
        { label: 'Cobranzas', caption: 'Cobro a cliente', icon: 'receipt', to: 'cobranza', show: can('cobranza') },
        { label: 'Mis Cobros', icon: 'money', to: 'miscobranzas', show: can('miscobranzas') },
        { label: 'Productos', caption: 'Precios y stock', icon: 'inventory_2', to: 'productos', show: can('productos') },
        { label: 'Ventas', caption: 'Consulta por fechas', icon: 'point_of_sale', to: 'ventas', show: can('ventas') },
        { label: 'Facturación', caption: 'Ventas y facturas', icon: 'request_quote', to: 'facturacion', show: can('facturacion') },
        { label: 'Nueva Venta', caption: 'Armar carrito y cobrar', icon: 'add_shopping_cart', to: 'facturacion/nueva', show: can('facturacionNueva') },
        { label: 'Pedido factura', caption: 'Facturar pedidos de preventistas', icon: 'assignment_turned_in', to: 'facturacion/pedidos', show: can('facturacionNueva') },
        { label: 'Impuestos', caption: 'CUIS, CUFD y token', icon: 'gavel', to: 'impuestos', show: can('impuestos') },
        { label: 'Compras', caption: 'Ingresos a proveedor', icon: 'local_shipping', to: 'compras', show: can('compras') },
        { label: 'Nueva Compra', caption: 'Sube el stock', icon: 'add_business', to: 'compras/nueva', show: can('comprasNueva') },
        { label: 'Proveedores', caption: 'Administrar', icon: 'store', to: 'proveedores', show: can('proveedores') },
        { label: 'Clientes sin Pedido', icon: 'person_off', to: 'nopedido', show: can('nopedido') },
        { label: 'Horarios de Envío', caption: 'Envío automático', icon: 'schedule_send', to: 'horariosenvio', show: can('horariosenvio') },
        { label: 'Exportar Excel', icon: 'table_chart', to: 'generar', show: can('generar') },
        { label: 'Excel Pedidos', caption: 'Rango de fechas', icon: 'receipt_long', to: 'genreporte', show: can('genreporte') },
        { label: 'Ruta de Entregas', icon: 'map', to: 'ruta', show: can('ruta') },
        { label: 'Reporte Entrega', icon: 'description', to: 'despacho', show: can('despacho') },
        { label: 'Pedidos / Entregas', caption: 'Resumen', icon: 'summarize', to: 'avance', show: can('avance') },
        { label: 'Reporte Entrega', icon: 'dvr', to: 'entrega', show: can('entrega') },
        { label: 'Reporte Entrega', caption: 'Clientes entregas', icon: 'list', to: 'reporte', show: can('reporte') },
        { label: 'Almacén', icon: 'o_store', to: 'almacen', show: can('almacen') },
        { label: 'Verificar Almacén', icon: 'fact_check', to: 'almacenVerificar', show: can('almacenVerificar') },
        { label: 'Almacén Verificado', icon: 'task_alt', to: 'almacenVerificado', show: can('almacenVerificado') },
        { label: 'Asignar Preventista', icon: 'people', to: 'modifica', show: can('modifica') },
        { label: 'Monitoreo', icon: 'computer', to: 'monitoreo', show: can('monitoreo') },
        { label: 'Resumen de Preventa', caption: 'Monitoreo', icon: 'query_stats', to: 'mapavendedor', show: can('mapavendedor') },
        { label: 'Mapa Visitas', icon: 'map', to: 'mapavendedorvisita', show: can('mapavendedorvisita') },
        { label: 'Asignación', caption: 'Monitoreo', icon: 'computer', to: 'mapacliente', show: can('mapacliente') },
        { label: 'Alta Cliente', caption: 'Formulario', icon: 'person_add', handler: 'irformulario', show: can('altacliente') },
        { label: 'Cambios', icon: 'no_food', to: 'bonificaciones', show: can('bonificaciones') },
        { label: 'Clientes Fotografías', icon: 'photo_camera', to: 'clientefotografias', show: can('clientefotografias') },
        { label: 'Pedidos', icon: 'shopping_cart', to: 'pedidos', show: can('pedidos') },
        { label: 'Encuestas', icon: 'assignment', to: 'encuestasIndex', show: can('encuestasIndex') },
        { label: 'Cambios por Calidad', caption: 'Formulario', icon: 'published_with_changes', handler: 'irformulario2', show: can('cambioscalidad') },
        { label: 'Usuarios', caption: 'Roles y permisos', icon: 'manage_accounts', to: 'usuario', show: can('usuario') }
      ]

      // Los destinos se escriben sin barra inicial. Mientras todas las rutas
      // fueron de un solo nivel eso daba igual, pero desde una ruta anidada
      // (/facturacion/nueva) un destino relativo resuelve a
      // /facturacion/productos en vez de /productos. Se normalizan a absolutos
      // para que el menu lleve siempre al mismo sitio, se este donde se este.
      return items
        .filter(item => item.show)
        .map(item => (
          typeof item.to === 'string' && !item.to.startsWith('/')
            ? { ...item, to: '/' + item.to }
            : item
        ))
    }
  },
  methods: {
    onMenuClick(item) {
      if (item.handler) {
        this[item.handler]()
      }
    },
    irformulario() {
      var win = window.open('https://form.jotform.com/261335471332653', '_blank');
      win.focus();
    },
    irformulario2() {
      var win = window.open('https://docs.google.com/forms/d/e/1FAIpQLSfkfb6iu-mdPgVXBlemyrwLi1RRblI15J_paQQV-siiIbPQgA/viewform', '_blank');
      win.focus();
    },
    toggleLeftDrawer() {
      this.leftDrawerOpen = !this.leftDrawerOpen
    },
    logout() {
      this.$q.loading.show()
      this.$store.dispatch('login/logout')
        .then(() => {
          this.$q.loading.hide()
          this.$router.push('/login')
        })
    }
  }
}
</script>

<style scoped>
.menu-list {
  padding: 4px 6px 12px;
}
.menu-header {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: #90a4ae;
  padding: 10px 10px 6px;
}
.menu-item {
  border-radius: 8px;
  min-height: 34px;
  padding: 2px 8px;
  margin-bottom: 2px;
  color: #455a64;
}
.menu-avatar {
  min-width: 30px;
  padding-right: 6px;
  color: #78909c;
}
.menu-label {
  font-size: 12.5px;
  font-weight: 500;
  line-height: 1.2;
}
.menu-caption {
  font-size: 10.5px;
  line-height: 1.1;
  color: #90a4ae;
}
.menu-item--active {
  background: var(--q-primary);
  color: #fff;
}
.menu-item--active .menu-avatar,
.menu-item--active .menu-caption {
  color: rgba(255, 255, 255, 0.85);
}
.menu-item--logout {
  color: #c62828;
}
.menu-item--logout .menu-avatar {
  color: #c62828;
}
</style>
