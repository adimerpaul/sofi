<template>
<q-page class="q-pa-xs">
  <div class="row q-col-gutter-xs">
    <div class="col-12 col-md-3">
      <q-card flat bordered>
        <q-card-section class="q-pa-xs">
          <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar por nombre o CI" clearable>
            <template v-slot:append>
              <q-icon name="search" size="18px"/>
            </template>
          </q-input>
        </q-card-section>
        <q-list separator dense class="lista-usuarios">
          <q-item
            v-for="u in usuariosFiltrados"
            :key="u.CodAut"
            clickable
            dense
            :active="usuario!=null && usuario.CodAut===u.CodAut"
            active-class="bg-primary text-white"
            @click="seleccionar(u)"
          >
            <q-item-section>
              <q-item-label lines="1" class="texto-nombre">{{ nombreCompleto(u) }}</q-item-label>
              <q-item-label caption lines="1" class="texto-ci" :class="usuario!=null && usuario.CodAut===u.CodAut ? 'text-white' : ''">CI: {{ (u.ci || '').trim() }}</q-item-label>
            </q-item-section>
          </q-item>
        </q-list>
      </q-card>
    </div>

    <div class="col-12 col-md-9">
      <q-card flat bordered v-if="usuario!=null">
        <q-card-section class="row items-center q-pa-xs q-gutter-x-xs">
          <div class="q-ml-sm">
            <span class="text-subtitle2">{{ nombreCompleto(usuario) }}</span>
            <span class="text-caption text-grey q-ml-sm">CI: {{ (usuario.ci || '').trim() }}</span>
          </div>
          <q-space/>
          <q-btn outline color="positive" icon="done_all" label="Todo" size="sm" dense no-caps @click="marcarTodo"/>
          <q-btn outline color="negative" icon="remove_done" label="Ninguno" size="sm" dense no-caps @click="quitarTodo"/>
          <q-btn color="primary" icon="save" label="Guardar" size="sm" dense no-caps :loading="guardando" @click="guardar"/>
        </q-card-section>
        <q-separator/>
        <q-card-section class="q-pa-xs">
          <div class="text-caption text-weight-bold text-grey-8 q-pl-xs">ROLES</div>
          <div class="row">
            <div class="col-6 col-sm-4 col-md-2" v-for="r in roles" :key="r.name">
              <q-checkbox size="xs" dense v-model="rolesUsuario" :val="r.name" :label="r.name" class="texto-check"/>
            </div>
          </div>
        </q-card-section>
        <q-separator/>
        <q-card-section class="q-pa-xs">
          <div class="text-caption text-weight-bold text-grey-8 q-pl-xs">
            PERMISOS
            <span class="text-weight-regular text-grey">— los "por rol" se quitan desmarcando el rol</span>
          </div>
          <div class="row">
            <div class="col-6 col-sm-4 col-md-3" v-for="p in permisos" :key="p">
              <q-checkbox
                size="xs"
                dense
                :model-value="porRol(p) || permisosUsuario.includes(p)"
                :disable="porRol(p)"
                @update:model-value="togglePermiso(p, $event)"
                :label="p"
                class="texto-check"
              >
                <q-badge v-if="porRol(p)" outline color="grey" class="q-ml-xs badge-rol">rol</q-badge>
              </q-checkbox>
            </div>
          </div>
        </q-card-section>
      </q-card>
      <q-card flat bordered v-else>
        <q-card-section class="text-grey text-center q-pa-xl">
          <q-icon name="manage_accounts" size="48px"/>
          <div>Seleccione un usuario para administrar sus permisos</div>
        </q-card-section>
      </q-card>
    </div>
  </div>
</q-page>
</template>

<script>
export default {
  name: 'Usuario',
  data() {
    return {
      filter: '',
      usuarios: [],
      usuario: null,
      roles: [],
      permisos: [],
      rolesUsuario: [],
      permisosUsuario: [],
      guardando: false
    }
  },
  computed: {
    usuariosFiltrados() {
      const f = (this.filter || '').toLowerCase().trim()
      if (f === '') return this.usuarios
      return this.usuarios.filter(u =>
        this.nombreCompleto(u).toLowerCase().includes(f) ||
        (u.ci || '').trim().toLowerCase().includes(f)
      )
    }
  },
  mounted() {
    this.cargar()
  },
  methods: {
    nombreCompleto(u) {
      return [u.Nombre1, u.Nombre2, u.App1, u.Apm]
        .map(x => (x || '').trim())
        .filter(x => x !== '')
        .join(' ')
    },
    cargar() {
      this.$q.loading.show()
      Promise.all([
        this.$api.get('user'),
        this.$api.get('permisosList')
      ]).then(([resUsers, resPerms]) => {
        this.usuarios = resUsers.data
        this.roles = resPerms.data.roles
        this.permisos = resPerms.data.permisos
      }).catch(() => {
        this.$q.notify({ type: 'negative', message: 'Error al cargar usuarios y permisos' })
      }).finally(() => {
        this.$q.loading.hide()
      })
    },
    seleccionar(u) {
      this.$q.loading.show()
      this.$api.get('usuarioPermisos/' + u.CodAut).then(res => {
        this.usuario = u
        this.rolesUsuario = res.data.roles
        this.permisosUsuario = res.data.permisos
      }).catch(() => {
        this.$q.notify({ type: 'negative', message: 'Error al cargar permisos del usuario' })
      }).finally(() => {
        this.$q.loading.hide()
      })
    },
    porRol(permiso) {
      return this.roles.some(r => this.rolesUsuario.includes(r.name) && r.permisos.includes(permiso))
    },
    togglePermiso(permiso, valor) {
      if (valor) {
        if (!this.permisosUsuario.includes(permiso)) this.permisosUsuario.push(permiso)
      } else {
        this.permisosUsuario = this.permisosUsuario.filter(p => p !== permiso)
      }
    },
    marcarTodo() {
      this.rolesUsuario = this.roles.map(r => r.name)
      this.permisosUsuario = [...this.permisos]
    },
    quitarTodo() {
      this.rolesUsuario = []
      this.permisosUsuario = []
    },
    guardar() {
      this.guardando = true
      this.$api.post('usuarioPermisos/' + this.usuario.CodAut, {
        roles: this.rolesUsuario,
        permisos: this.permisosUsuario
      }).then(res => {
        this.rolesUsuario = res.data.roles
        this.permisosUsuario = res.data.permisos
        this.$q.notify({ type: 'positive', message: 'Permisos actualizados' })
      }).catch(() => {
        this.$q.notify({ type: 'negative', message: 'Error al guardar los permisos' })
      }).finally(() => {
        this.guardando = false
      })
    }
  }
}
</script>

<style scoped>
.lista-usuarios {
  max-height: 75vh;
  overflow: auto;
}
.texto-nombre {
  font-size: 12px;
  font-weight: 500;
}
.texto-ci {
  font-size: 10.5px;
}
.texto-check :deep(.q-checkbox__label) {
  font-size: 11.5px;
}
.badge-rol {
  font-size: 9px;
}
</style>
