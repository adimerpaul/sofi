<template>
  <q-layout view="lhr Lpr lfr" style="min-height: 0">
    <q-header class="app-header">
      <q-toolbar class="app-toolbar">
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleLeftDrawer"/>
        <q-toolbar-title>
          <template v-if="$store.getters['login/user'].Nombre1==undefined">Sofia</template>
          <template v-else>
            {{ $filters.capitalize($store.getters['login/user'].Nombre1 + ' ' + $store.getters['login/user'].App1) }}
          </template>
          <q-chip dense class="bg-red-4 text-red-10" size="10px">
            {{ version }}
          </q-chip>
        </q-toolbar-title>
        <div>
          <q-btn v-if="$store.getters['login/isLoggedIn']" @click="logout" size="xs" label="Salir" icon="logout" color="negative" dense no-caps/>
        </div>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="leftDrawerOpen" show-if-above bordered
              :width="230"
              :breakpoint="500"
              class="menu-drawer">
      <div class="menu-wrap">
        <div class="menu-brand">
          <div class="menu-brand__icon">
            <img src="logo.png" alt="Sofia" class="menu-brand__logo" @error="logoError = true" v-if="!logoError">
            <q-icon v-else name="storefront" size="20px"/>
          </div>
          <div class="menu-brand__text">
            <div class="menu-brand__title">Sofia</div>
            <div class="menu-brand__sub">Sistema de gestión</div>
          </div>
        </div>

        <div class="menu-scroll">
          <div class="menu-header">Modulos</div>

          <template v-for="(item, index) in menuItems" :key="index">
            <!-- Grupo que se agranda y se achica (ventas/facturacion, compras) -->
            <q-expansion-item
              v-if="item.children"
              dense
              dense-toggle
              class="menu-group"
              header-class="menu-group__header"
              expand-icon-class="menu-group__arrow"
              :model-value="isGroupOpen(item)"
              @update:model-value="val => setGroupOpen(item, val)"
            >
              <template #header>
                <q-item-section avatar class="menu-avatar">
                  <q-icon :name="item.icon" size="17px"/>
                </q-item-section>
                <q-item-section class="menu-label">{{ item.label }}</q-item-section>
                <q-item-section side>
                  <span class="menu-badge">{{ item.children.length }}</span>
                </q-item-section>
              </template>

              <q-item
                v-for="(child, ci) in item.children"
                :key="ci"
                clickable
                exact
                dense
                class="menu-item menu-item--child"
                active-class="menu-item--active"
                :to="child.to"
                @click="onMenuClick(child)"
              >
                <q-item-section avatar class="menu-avatar">
                  <q-icon :name="child.icon" size="16px"/>
                </q-item-section>
                <q-item-section class="menu-label">{{ child.label }}</q-item-section>
              </q-item>
            </q-expansion-item>

            <q-item
              v-else
              clickable
              exact
              dense
              class="menu-item"
              active-class="menu-item--active"
              :to="item.to"
              @click="onMenuClick(item)"
            >
              <q-item-section avatar class="menu-avatar">
                <q-icon :name="item.icon" size="17px"/>
              </q-item-section>
              <q-item-section class="menu-label">{{ item.label }}</q-item-section>
            </q-item>
          </template>
        </div>

        <div class="menu-foot">
          <div class="menu-foot__version">Sofia v{{ version }}</div>
          <q-btn
            v-if="$store.getters['login/isLoggedIn']"
            class="menu-foot__btn"
            flat
            no-caps
            icon="logout"
            label="Salir"
            @click="logout"
          />
        </div>
      </div>
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
      leftDrawerOpen: false,
      openGroups: {},
      logoError: false,
      version: '11.5.6'
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
        { label: 'Productos', caption: 'Precios y stock', icon: 'inventory_2', to: 'productos', show: can('productos') },
        {
          label: 'Ventas y Facturación',
          caption: 'Ventas, facturas e impuestos',
          icon: 'point_of_sale',
          children: [
            { label: 'Ventas', caption: 'Consulta por fechas', icon: 'point_of_sale', to: 'ventas', show: can('ventas') },
            { label: 'Facturación', caption: 'Ventas y facturas', icon: 'request_quote', to: 'facturacion', show: can('facturacion') },
            { label: 'Nueva Venta', caption: 'Armar carrito y cobrar', icon: 'add_shopping_cart', to: 'facturacion/nueva', show: can('facturacionNueva') },
            { label: 'Pedido factura', caption: 'Facturar pedidos de preventistas', icon: 'assignment_turned_in', to: 'facturacion/pedidos', show: can('facturacionNueva') },
            { label: 'Impuestos', caption: 'CUIS, CUFD y token', icon: 'gavel', to: 'impuestos', show: can('impuestos') }
          ]
        },
        {
          label: 'Compras',
          caption: 'Proveedores e ingresos',
          icon: 'local_shipping',
          children: [
            { label: 'Compras', caption: 'Ingresos a proveedor', icon: 'local_shipping', to: 'compras', show: can('compras') },
            { label: 'Nueva Compra', caption: 'Sube el stock', icon: 'add_business', to: 'compras/nueva', show: can('comprasNueva') },
            { label: 'Proveedores', caption: 'Administrar', icon: 'store', to: 'proveedores', show: can('proveedores') }
          ]
        },
        {
          label: 'Camionero',
          icon: 'local_shipping',
          children: [
            { label: 'Verificar Carga', caption: 'Revisar el camión antes de salir', icon: 'inventory', to: 'caminero/carga', show: can('cargacamion') },
            { label: 'Mis Entregas', caption: 'Cobrar en ruta', icon: 'local_shipping', to: 'caminero/entregas', show: can('misentregas') },
            { label: 'Mi Reporte de Entregas', caption: 'Recojo del día', icon: 'summarize', to: 'caminero/reporte', show: can('misentregasreporte') },
            { label: 'Ruta de Entregas', icon: 'map', to: 'ruta', show: can('ruta') },
            { label: 'Reporte Entrega', icon: 'description', to: 'despacho', show: can('despacho') },
            { label: 'Pedidos / Entregas', caption: 'Resumen', icon: 'summarize', to: 'avance', show: can('avance') },
            { label: 'Reporte Entrega', icon: 'dvr', to: 'entrega', show: can('entrega') },
            { label: 'Reporte Entrega', caption: 'Clientes entregas', icon: 'list', to: 'reporte', show: can('reporte') }
          ]
        },
        {
          label: 'Cobros',
          icon: 'payments',
          children: [
            { label: 'Cobrar', caption: 'Recojo y reportes de los camineros', icon: 'payments', to: 'cobranzas/recojo', show: can('cobranzasrecojo') },
            { label: 'Créditos y deudas', caption: 'Deudas y abonos de clientes', icon: 'account_balance_wallet', to: 'cobranzas/creditos', show: can('cobranzasrecojo') },
            { label: 'Verificar facturación', caption: 'Facturación del día contra lo que trajo el camión', icon: 'fact_check', to: 'cobranzas/verificacion', show: can('cobranzasverificar') },
            { label: 'Cobros Realizados', icon: 'monetization_on', to: 'cobrosrealizados', show: can('cobrosrealizados') },
            { label: 'Cobranzas', caption: 'Cobro a cliente', icon: 'receipt', to: 'cobranza', show: can('cobranza') },
            { label: 'Cobrar créditos y deudas', caption: 'Clientes con deuda y cobro con boleta', icon: 'payments', to: 'vendedor/creditos', show: can('cobranza') },
            { label: 'Mis Cobros', icon: 'money', to: 'miscobranzas', show: can('miscobranzas') }
          ]
        },
        { label: 'Clientes sin Pedido', icon: 'person_off', to: 'nopedido', show: can('nopedido') },
        { label: 'Horarios de Envío', caption: 'Envío automático', icon: 'schedule_send', to: 'horariosenvio', show: can('horariosenvio') },
        { label: 'Exportar Excel', icon: 'table_chart', to: 'generar', show: can('generar') },
        { label: 'Excel Pedidos', caption: 'Rango de fechas', icon: 'receipt_long', to: 'genreporte', show: can('genreporte') },
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
      const absoluto = item => (
        typeof item.to === 'string' && !item.to.startsWith('/')
          ? { ...item, to: '/' + item.to }
          : item
      )

      // Un grupo solo aparece si al menos una de sus opciones esta permitida.
      return items
        .map(item => (
          item.children
            ? { ...item, children: item.children.filter(hijo => hijo.show).map(absoluto) }
            : item
        ))
        .filter(item => (item.children ? item.children.length > 0 : item.show))
        .map(absoluto)
    }
  },
  methods: {
    // El grupo arranca abierto si estamos dentro de una de sus paginas; despues
    // manda lo que el usuario haya elegido con el toggle.
    isGroupOpen(item) {
      if (this.openGroups[item.label] !== undefined) {
        return this.openGroups[item.label]
      }
      return item.children.some(hijo => this.$route.path === hijo.to)
    },
    setGroupOpen(item, val) {
      this.openGroups = { ...this.openGroups, [item.label]: val }
    },
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
/* Menu compacto en rojo: el fondo oscuro lo pone el drawer y todo lo de adentro
   va en blanco translucido, asi lo activo resalta con el rojo fuerte. */
.app-header {
  background: linear-gradient(90deg, #7f1220, #4a0b14);
}
.menu-wrap {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 100%;
  padding: 8px;
  background: linear-gradient(180deg, #4a0b14, #2d060c);
  color: #fff;
}
.menu-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.08);
}
.menu-brand__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 9px;
  background: #fff;
  color: #b71c1c;
  overflow: hidden;
  flex: 0 0 auto;
}
.menu-brand__logo {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.menu-brand__title {
  font-size: 14px;
  font-weight: 700;
  line-height: 1.1;
}
.menu-brand__sub {
  font-size: 10.5px;
  line-height: 1.1;
  color: rgba(255, 255, 255, 0.55);
}
.menu-scroll {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  margin-top: 8px;
  padding-right: 2px;
}
.menu-scroll::-webkit-scrollbar {
  width: 5px;
}
.menu-scroll::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.18);
  border-radius: 4px;
}
.menu-header {
  font-size: 9.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.2px;
  color: rgba(255, 255, 255, 0.45);
  padding: 4px 6px 6px;
}
.menu-item {
  border-radius: 7px;
  min-height: 28px;
  padding: 0 8px;
  margin-bottom: 2px;
  color: rgba(255, 255, 255, 0.82);
  background: rgba(255, 255, 255, 0.04);
}
.menu-item--child {
  margin-left: 12px;
  background: transparent;
  min-height: 26px;
}
.menu-avatar {
  min-width: 26px;
  padding-right: 6px;
  color: rgba(255, 255, 255, 0.65);
}
.menu-label {
  font-size: 12px;
  font-weight: 500;
  line-height: 1.15;
}
.menu-item--active {
  background: linear-gradient(90deg, #e53935, #a31420);
  color: #fff;
  font-weight: 600;
}
.menu-item--active .menu-avatar {
  color: #fff;
}
.menu-badge {
  min-width: 20px;
  padding: 0 5px;
  border-radius: 8px;
  font-size: 10px;
  font-weight: 700;
  line-height: 15px;
  text-align: center;
  color: rgba(255, 255, 255, 0.85);
  background: rgba(255, 255, 255, 0.14);
}
.menu-group {
  margin-bottom: 2px;
}
.menu-group :deep(.menu-group__header) {
  border-radius: 7px;
  min-height: 28px;
  padding: 0 6px 0 8px;
  color: #fff;
  background: rgba(255, 255, 255, 0.07);
}
.menu-group :deep(.menu-group__header .menu-label) {
  font-weight: 600;
}
.menu-group :deep(.menu-group__header .q-item__section--side) {
  padding-left: 6px;
  color: rgba(255, 255, 255, 0.6);
}
.menu-group :deep(.menu-group__arrow) {
  font-size: 18px;
}
.menu-foot {
  padding-top: 8px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}
.menu-foot__version {
  font-size: 10px;
  color: rgba(255, 255, 255, 0.4);
  padding: 0 6px 6px;
}
.menu-foot__btn {
  width: 100%;
  border-radius: 8px;
  font-size: 12px;
  color: #ff8a80;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.12);
}
</style>

<style>
/* QDrawer aplica la clase del componente al div .q-drawer__content, que no
   lleva el atributo de scope de este SFC: por eso este bloque va sin scoped,
   si no Quasar deja el fondo blanco por defecto. */
.menu-drawer.q-drawer__content {
  background: linear-gradient(180deg, #4a0b14, #2d060c);
  color: #fff;
  overflow: hidden;
}
</style>
