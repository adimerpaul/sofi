<template>
<q-page class="q-pa-xs">
  <div class="row q-col-gutter-xs">
    <div class="col-12 col-md-3">
      <q-card flat bordered>
        <q-card-section class="q-pa-xs">
          <div class="row no-wrap q-gutter-x-xs">
            <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar por nombre o CI" clearable class="col">
              <template v-slot:append>
                <q-icon name="search" size="18px"/>
              </template>
            </q-input>
            <q-btn color="positive" icon="person_add" dense no-caps label="Nuevo" @click="nuevoUsuario"/>
          </div>
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
          <q-btn flat round dense color="primary" icon="edit" size="sm" @click="editarUsuario">
            <q-tooltip>Modificar datos del usuario</q-tooltip>
          </q-btn>
          <span class="text-caption text-grey" v-if="usuario.placa">Camión: {{ usuario.placa }}</span>
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
          <q-input v-model="filtroPermisos" outlined dense clearable placeholder="Buscar permiso: Cobrar, créditos, camiones..." class="q-my-sm"/>
          <div class="row">
            <div class="col-6 col-sm-4 col-md-3" v-for="p in permisosFiltrados" :key="p">
              <q-checkbox
                size="xs"
                dense
                :model-value="porRol(p) || permisosUsuario.includes(p)"
                :disable="porRol(p)"
                @update:model-value="togglePermiso(p, $event)"
                :label="nombrePermiso(p)"
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

  <q-dialog v-model="dialogUsuario" persistent>
    <q-card style="width: 480px; max-width: 95vw">
      <q-card-section class="row items-center q-pb-none">
        <div class="text-subtitle1 text-weight-bold">{{ form.CodAut ? 'Modificar usuario' : 'Nuevo usuario' }}</div>
        <q-space/>
        <q-btn icon="close" flat round dense v-close-popup/>
      </q-card-section>
      <q-form @submit="guardarUsuario">
        <q-card-section class="q-gutter-y-sm">
          <div class="row q-col-gutter-sm">
            <div class="col-6">
              <q-input outlined dense v-model="form.ci" label="Carnet (CI) *" maxlength="15" :rules="[v => !!(v && v.trim()) || 'Requerido']" hide-bottom-space/>
            </div>
            <div class="col-6">
              <q-input outlined dense v-model="form.pasw" :type="verPasw ? 'text' : 'password'" maxlength="15"
                       :label="form.CodAut ? 'Nueva contraseña' : 'Contraseña *'"
                       :hint="form.CodAut ? 'Vacío = no cambia' : ''"
                       :rules="[v => !!form.CodAut || !!(v && v.trim()) || 'Requerido']" hide-bottom-space>
                <template v-slot:append>
                  <q-icon :name="verPasw ? 'visibility_off' : 'visibility'" class="cursor-pointer" @click="verPasw = !verPasw"/>
                </template>
              </q-input>
            </div>
            <div class="col-6">
              <q-input outlined dense v-model="form.Nombre1" label="Primer nombre *" maxlength="15" :rules="[v => !!(v && v.trim()) || 'Requerido']" hide-bottom-space/>
            </div>
            <div class="col-6">
              <q-input outlined dense v-model="form.Nombre2" label="Segundo nombre" maxlength="15"/>
            </div>
            <div class="col-6">
              <q-input outlined dense v-model="form.App1" label="Apellido paterno *" maxlength="20" :rules="[v => !!(v && v.trim()) || 'Requerido']" hide-bottom-space/>
            </div>
            <div class="col-6">
              <q-input outlined dense v-model="form.Apm" label="Apellido materno" maxlength="20"/>
            </div>
            <div class="col-6">
              <q-input outlined dense v-model="form.Fech_naci" type="date" label="Fecha de nacimiento" stack-label/>
            </div>
            <div class="col-6">
              <q-select outlined dense v-model="form.placa" :options="placasFiltradas" label="Camión (caminero)"
                        use-input fill-input hide-selected input-debounce="0" clearable
                        new-value-mode="add-unique" @filter="filtrarPlacas" @input-value="v => form.placa = v"/>
            </div>
            <div class="col-12">
              <q-input outlined dense v-model="form.direccion" label="Dirección" maxlength="250"/>
            </div>
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat no-caps label="Cancelar" v-close-popup/>
          <q-btn color="primary" no-caps icon="save" label="Guardar" type="submit" :loading="guardandoUsuario"/>
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</q-page>
</template>

<script>
export default {
  name: 'Usuario',
  data() {
    return {
      filter: '',
      filtroPermisos: '',
      usuarios: [],
      usuario: null,
      roles: [],
      permisos: [],
      rolesUsuario: [],
      permisosUsuario: [],
      guardando: false,
      placas: [],
      placasFiltradas: [],
      dialogUsuario: false,
      guardandoUsuario: false,
      verPasw: false,
      form: {}
    }
  },
  computed: {
    permisosFiltrados() {
      const normalizar = texto => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
      const filtro = normalizar((this.filtroPermisos || '').trim())
      return this.permisos.filter(p => normalizar(p + ' ' + this.nombrePermiso(p)).includes(filtro))
    },
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
    nombrePermiso(p) {
      const nombres = {
        cobranzasrecojo: 'Cobrar — Todos los camiones, créditos y deudas',
        cobranzasverificar: 'Cobrar — Verificar la facturación del día',
        misentregasreporte: 'Mi reporte de entregas (caminero)',
        misentregas: 'Mis entregas (caminero)',
        cargacamion: 'Verificar carga (caminero)',
        entregaFactura: 'Entrega factura — entregas del día con facturación',
        facturacionPrecio: 'Cambiar precio al facturar pedidos',
        facturacionAprobarCarga: 'Aprobar la carga de un camión (facturación)'
      }
      return nombres[p] || p
    },
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
        this.placas = resPerms.data.placas || []
      }).catch(() => {
        this.$q.notify({ type: 'negative', message: 'Error al cargar usuarios y permisos' })
      }).finally(() => {
        this.$q.loading.hide()
      })
    },
    filtrarPlacas(val, update) {
      update(() => {
        const f = (val || '').toLowerCase()
        this.placasFiltradas = this.placas.filter(p => p.toLowerCase().includes(f))
      })
    },
    nuevoUsuario() {
      this.form = { CodAut: null, ci: '', pasw: '', Nombre1: '', Nombre2: '', App1: '', Apm: '', Fech_naci: '', direccion: '', placa: null }
      this.verPasw = false
      this.dialogUsuario = true
    },
    editarUsuario() {
      this.$q.loading.show()
      this.$api.get('user/' + this.usuario.CodAut).then(res => {
        this.form = { ...res.data, pasw: '' }
        this.verPasw = false
        this.dialogUsuario = true
      }).catch(() => {
        this.$q.notify({ type: 'negative', message: 'Error al cargar los datos del usuario' })
      }).finally(() => {
        this.$q.loading.hide()
      })
    },
    guardarUsuario() {
      this.guardandoUsuario = true
      const datos = { ...this.form, placa: (this.form.placa || '').trim() }
      const peticion = datos.CodAut
        ? this.$api.put('user/' + datos.CodAut, datos)
        : this.$api.post('user', datos)
      peticion.then(res => {
        const u = res.data
        const i = this.usuarios.findIndex(x => String(x.CodAut) === String(u.CodAut))
        if (i >= 0) this.usuarios.splice(i, 1, { ...this.usuarios[i], ...u })
        else this.usuarios.push(u)
        if (u.placa && !this.placas.includes(u.placa)) this.placas = [...this.placas, u.placa].sort()
        this.dialogUsuario = false
        this.$q.notify({ type: 'positive', message: datos.CodAut ? 'Usuario modificado' : 'Usuario creado' })
        if (!datos.CodAut || (this.usuario && String(this.usuario.CodAut) === String(u.CodAut))) {
          this.seleccionar(this.usuarios.find(x => String(x.CodAut) === String(u.CodAut)))
        }
      }).catch(err => {
        const data = (err.response && err.response.data) || {}
        const errores = data.errors ? Object.values(data.errors).flat().join(' ') : ''
        this.$q.notify({ type: 'negative', message: errores || data.message || 'Error al guardar el usuario' })
      }).finally(() => {
        this.guardandoUsuario = false
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
        if (String(this.usuario.CodAut) === String(this.$store.getters['login/user'].CodAut)) {
          this.$store.commit('login/actualizarPermisos', res.data.efectivos)
        }
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
