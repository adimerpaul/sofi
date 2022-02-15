<template>
  <q-layout view="lHh Lpr lFf">
    <q-header elevated>
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleLeftDrawer"/>
        <q-toolbar-title>
          Sofia
        </q-toolbar-title>
        <div>Quasar v{{ $q.version }}</div>
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

        <q-item clickable exact to="visita">
          <q-item-section avatar>
            <q-icon name="map" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Visita </q-item-label>
            <q-item-label caption>
              Visita
            </q-item-label>
          </q-item-section>
        </q-item>

        <q-item clickable exact to="mispedidos">
          <q-item-section avatar>
            <q-icon name="list" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Pedidos </q-item-label>
            <q-item-label caption>
              Listado
            </q-item-label>
          </q-item-section>
        </q-item>

        <q-item clickable exact to="asignar">
          <q-item-section avatar>
            <q-icon name="people" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Repartidor </q-item-label>
            <q-item-label caption>
              Repartidor
            </q-item-label>
          </q-item-section>
        </q-item>

        <q-item clickable exact to="misasignaciones">
          <q-item-section avatar>
            <q-icon name="shop" />
          </q-item-section>
          <q-item-section>
            <q-item-label>Mis asignaciones </q-item-label>
            <q-item-label caption>
              Mis asignaciones
            </q-item-label>
          </q-item-section>
        </q-item>

        <q-item clickable exact to="cobranza">
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
      leftDrawerOpen:false
    }
  },
  methods:{
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
  //   const leftDrawerOpen = ref(false)
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
