<template>
  <q-layout view="lHh Lpr lFf">
    <q-header elevated>
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleLeftDrawer"/>
        <q-toolbar-title>
          <template v-if="$store.getters['login/user'].Nombre1==undefined">Sofia</template>
          <template v-else>{{ $store.getters['login/user'].Nombre1 }} {{ $store.getters['login/user'].App1 }}</template>
        </q-toolbar-title>
<!--        <div>Quasar v{{ $q.version }}</div>-->
        <div>
<!--          <q-input dense bg-color="white" v-model="$store.state.login.url" outlined label="url" />-->
<!--          <q-btn v-if="$store.getters['login/isLoggedIn']" @click="logout" size="xs" label="salir" icon="logout" color="negative"/>-->
        </div>
      </q-toolbar>
    </q-header>
    <q-drawer v-model="leftDrawerOpen" show-if-above bordered>
      <q-list>
        <q-item-label header>Opciones</q-item-label>
        <q-item clickable exact to="/">
          <q-item-section avatar>
            <q-icon name="home" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Principal </q-item-label>
            <q-item-label caption>
              Principal
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item v-if="!$store.getters['login/isLoggedIn']" exact to="login">
          <q-item-section avatar>
            <q-icon name="login" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Ingresar</q-item-label>
            <q-item-label caption>
              Ingresar al sistema
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable exact to="visita" v-if="vendores.includes($store.getters['login/user'].ci)">
          <q-item-section avatar>
            <q-icon name="map" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Realizar Visita </q-item-label>
            <q-item-label caption>
              Realizar Visita
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable exact to="mispedidos"  v-if="vendores.includes($store.getters['login/user'].ci)">
          <q-item-section avatar>
            <q-icon name="list" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Mis pedidos </q-item-label>
            <q-item-label caption>
              Mis pedidos
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable exact to="clientes" v-if="$store.getters['login/user'].ci=='7329536'">
          <q-item-section avatar>
            <q-icon name="people" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Lista Clientes </q-item-label>
            <q-item-label caption>
              Habilitar Cliente
            </q-item-label>
          </q-item-section>
        </q-item>
                <q-item clickable exact to="pendientes" v-if="$store.getters['login/user'].ci=='7329536'">
          <q-item-section avatar>
            <q-icon name="local_grocery_store" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Pedidos Pendientes </q-item-label>
            <q-item-label caption>
              Faltantes
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable exact to="cobrosrealizados" v-if="vendores.includes($store.getters['login/user'].ci) || cobrador.includes($store.getters['login/user'].ci) || $store.getters['login/user'].ci=='7329536'">
          <q-item-section avatar>
            <q-icon name="monetization_on" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Cobros realizados </q-item-label>
            <q-item-label caption>
              Cobros realizados
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable exact to="cobranza"  v-if="vendores.includes($store.getters['login/user'].ci) || cobrador.includes($store.getters['login/user'].ci)">
          <q-item-section avatar>
            <q-icon name="receipt" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Cobranzas</q-item-label>
            <q-item-label caption>
              Cobro a cliente
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable exact to="miscobranzas"  v-if="vendores.includes($store.getters['login/user'].ci) || cobrador.includes($store.getters['login/user'].ci)">
          <q-item-section avatar>
            <q-icon name="money" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Mis Cobros</q-item-label>
            <q-item-label caption>
              Mis Cobros
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable exact to="productos"  v-if="vendores.includes($store.getters['login/user'].ci)">
          <q-item-section avatar>
            <q-icon name="list" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Productos</q-item-label>
            <q-item-label caption>
              Mis Productos
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable exact to="nopedido"  v-if="vendores.includes($store.getters['login/user'].ci)">
          <q-item-section avatar>
            <q-icon name="list" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Cliente No Pedido</q-item-label>
            <q-item-label caption>
              No pedido
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable exact to="generar" v-if="encargados.includes($store.getters['login/user'].ci)">
          <q-item-section avatar>
            <q-icon name="money" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Exportar excel</q-item-label>
            <q-item-label caption>
              Exportar excel
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable exact to="genreporte" v-if="supervisor.includes($store.getters['login/user'].ci)">
          <q-item-section avatar>
            <q-icon name="receipt_long" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Exportar excel Pedidos</q-item-label>
            <q-item-label caption>
              Pedidos Rango Fecha
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable exact to="ruta" v-if="despachador.includes($store.getters['login/user'].ci)">
          <q-item-section avatar>
            <q-icon name="map" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Ruta de pedidos</q-item-label>
            <q-item-label caption>
              Ruta de pedidos
            </q-item-label>
          </q-item-section>
        </q-item>
                <q-item clickable exact to="reporte" v-if="encargados.includes($store.getters['login/user'].ci)">
          <q-item-section avatar>
            <q-icon name="list" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Reporte entrega</q-item-label>
            <q-item-label caption>
              Clientes entregas
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable exact to="modifica" v-if="supervisor.includes($store.getters['login/user'].ci)">
          <q-item-section avatar>
            <q-icon name="people" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Asignar preventista</q-item-label>
            <q-item-label caption>
              Modifica al preventista
            </q-item-label>
          </q-item-section>
        </q-item>

        <q-item clickable exact to="monitoreo" v-if="supervisor.includes($store.getters['login/user'].ci)">
          <q-item-section avatar>
            <q-icon name="computer" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Monitoreo</q-item-label>
            <q-item-label caption>
              Monitoreo
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable exact  @click="irformulario" v-if="vendores.includes($store.getters['login/user'].ci) || cobrador.includes($store.getters['login/user'].ci)">
          <q-item-section avatar>
            <q-icon name="people" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Alta Cliente</q-item-label>
            <q-item-label caption>
              Formulario
            </q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable exact  @click="irformulario2" v-if="vendores.includes($store.getters['login/user'].ci) || cobrador.includes($store.getters['login/user'].ci)">
          <q-item-section avatar>
            <q-icon name="people" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Cambios por Calidad</q-item-label>
            <q-item-label caption>
              Formulario
            </q-item-label>
          </q-item-section>
        </q-item>  
        <!--        <q-item clickable exact to="asignar">-->
<!--          <q-item-section avatar>-->
<!--            <q-icon name="people" />-->
<!--          </q-item-section>-->
<!--          <q-item-section>-->
<!--            <q-item-label>Repartidor </q-item-label>-->
<!--            <q-item-label caption>-->
<!--              Repartidor-->
<!--            </q-item-label>-->
<!--          </q-item-section>-->
<!--        </q-item>-->

<!--        <q-item clickable exact to="misasignaciones">-->
<!--          <q-item-section avatar>-->
<!--            <q-icon name="shop" />-->
<!--          </q-item-section>-->
<!--          <q-item-section>-->
<!--            <q-item-label>Mis asignaciones </q-item-label>-->
<!--            <q-item-label caption>-->
<!--              Mis asignaciones-->
<!--            </q-item-label>-->
<!--          </q-item-section>-->
<!--        </q-item>-->



        <q-item v-if="$store.getters['login/isLoggedIn']" clickable @click="logout">
          <q-item-section avatar>
            <q-icon name="logout" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Salir</q-item-label>
            <q-item-label caption>
              Salir del sistema
            </q-item-label>
          </q-item-section>
        </q-item>
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script>

export default {
  data(){
    return {
      leftDrawerOpen:false,
      vendores:['12612870','1593578','33555433','3520335','5676554','7422201','9876785','7360035','5067737','7331330','7308976','7377278','5938578','7351953'],
      encargados:['0' ,'123321' ,'22222222','7205489'],
      cobrador:['7424479'],
      despachador:['7386961','9688418','7205489','7417239','12810781','7395208'],
      supervisor:['7308976','7329688','7288817']
    }
  },
  methods:{
    irformulario(){
      var win = window.open('https://form.jotform.com/232714403173650', '_blank');
        // Cambiar el foco al nuevo tab (punto opcional)
        win.focus();
    },
    irformulario2(){
      var win = window.open('https://docs.google.com/forms/d/e/1FAIpQLScp_D8ihzdtAN5ZwuBYqIEAbHu8L6T7dFaZ6-m2YdagKUZIaA/viewform', '_blank');
        // Cambiar el foco al nuevo tab (punto opcional)
        win.focus();
    },
      validar(){
        return this.vendores.includes();
      },
      toggleLeftDrawer () {
        this.leftDrawerOpen = !this.leftDrawerOpen
      },
      logout(){
        this.$q.loading.show()
        this.$store.dispatch('login/logout')
          .then(() => {
            this.$q.loading.hide()
            this.$router.push('/login')
          })
      }
  }
  // setup () {
  //   cons t leftDrawerOpen = ref(false)
  //
  //   return {
  //     essentialLinks: linksList,
  //     leftDrawerOpen,
  //     toggleLeftDrawer () {
  //       leftDrawerOpen.value = !leftDrawerOpen.value
  //     }
  //   }
  // }
}
</script>
