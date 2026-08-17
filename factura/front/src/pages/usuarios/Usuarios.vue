<template>
  <q-page class="q-pa-md">
    <q-table :rows="users" :columns="columns" dense wrap-cells flat bordered :rows-per-page-options="[0]"
             title="Usuarios" :filter="filter">
      <template v-slot:top-right>
        <q-btn color="positive" label="Nuevo" @click="userNew"  no-caps  icon="add_circle_outline" :loading="loading" />
        <q-input v-model="filter" label="Buscar" dense outlined >
          <template v-slot:append>
            <q-icon name="search" />
          </template>
        </q-input>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn-dropdown label="Opciones" no-caps size="10px" dense color="primary">
            <q-list>
              <q-item clickable @click="userEdit(props.row)" v-close-popup>
                <q-item-section avatar>
                  <q-icon name="edit" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>Editar</q-item-label>
                </q-item-section>
              </q-item>
              <q-item clickable @click="userDelete(props.row.id)" v-close-popup>
                <q-item-section avatar>
                  <q-icon name="delete" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>Eliminar</q-item-label>
                </q-item-section>
              </q-item>
              <q-item clickable @click="userEditPassword(props.row)" v-close-popup>
                <q-item-section avatar>
                  <q-icon name="edit" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>Cambiar contraseña</q-item-label>
                </q-item-section>
              </q-item>
<!--              <q-item clickable @click="permisosShow(props.row)" v-close-popup>-->
<!--                <q-item-section avatar>-->
<!--                  <q-icon name="edit" />-->
<!--                </q-item-section>-->
<!--                <q-item-section>-->
<!--                  <q-item-label>Permisos</q-item-label>-->
<!--                </q-item-section>-->
<!--              </q-item>-->
            </q-list>
          </q-btn-dropdown>
        </q-td>
      </template>
      <template v-slot:body-cell-role="props">
        <q-td :props="props">
          <q-chip :label="props.row.role"
                  :color="props.row.color"
                  text-color="white" dense  size="14px"/>
        </q-td>
      </template>
      <template v-slot:body-cell-sucursal="props">
        <q-td :props="props">
          <q-chip :label="props.row.agencia"
                  :color="props.row.agencia === 'Ayacucho' ? 'green' : 'blue'"
                  text-color="white" dense  size="14px"/>
        </q-td>
      </template>
    </q-table>
<!--    <pre>{{ users }}</pre>-->
    <q-dialog v-model="userDialog" persistent>
      <q-card>
        <q-card-section class="q-pb-none row items-center">
          <div>
            {{ actionPeriodo }} user
          </div>
          <q-space />
          <q-btn icon="close" flat round dense @click="userDialog = false" />
        </q-card-section>
        <q-card-section class="q-pt-none">
          <q-form @submit="user.id ? userPut() : userPost()">
            <q-input v-model="user.name" label="Nombre" dense outlined :rules="[val => !!val || 'Campo requerido']" />
            <q-input v-model="user.username" label="Usuario" dense outlined :rules="[val => !!val || 'Campo requerido']" />
<!--            <q-input v-model="user.email" label="Email" dense outlined hint="" />-->
            <q-input v-model="user.password" label="Contraseña" dense outlined :rules="[val => !!val || 'Campo requerido']" v-if="!user.id" />
            <q-select v-model="user.role" label="Rol" dense outlined :options="roles" :rules="[val => !!val || 'Campo requerido']" />
<!--            <q-select v-model="user.agencia" label="Sucursal" dense outlined :options="$agencias" :rules="[val => !!val || 'Campo requerido']" />-->
<!--            <q-input v-model="user.codigo" label="Codigo" dense outlined hint="" />-->
<!--            <q-input v-model="user.gestion" label="Gestion" dense outlined hint="" />-->
<!--            <q-input v-model="user.bloque" label="Bloque" dense outlined hint="" />-->
            <div class="text-right" >
              <q-btn color="negative" label="Cancelar" @click="userDialog = false" no-caps :loading="loading" />
              <q-btn color="primary" label="Guardar" type="submit" no-caps :loading="loading" class="q-ml-sm" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
<!--    dialogPermisos-->
    <q-dialog v-model="dialogPermisos" persistent>
      <q-card>
        <q-card-section class="q-pb-none row items-center text-bold">
            Permisos
          <q-space />
          <q-btn icon="close" flat round dense @click="dialogPermisos = false" />
        </q-card-section>
        <q-card-section class="q-pt-none">
          <q-list>
            <q-item v-for="permiso in permissions" :key="permiso.id">
              <q-item-section>
                <q-item-label>{{ permiso.name }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-toggle v-model="permiso.checked" />
              </q-item-section>
            </q-item>
          </q-list>
<!--          <pre>{{ user }}</pre>-->
        </q-card-section>
        <q-card-actions align="right">
          <q-btn color="negative" label="Cancelar" @click="dialogPermisos = false" no-caps :loading="loading" />
          <q-btn color="primary" label="Guardar" @click="permisosPost" no-caps :loading="loading" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
<script>
import moment from 'moment'
export default {
  name: 'UsuariosPage',
  data() {
    return {
      users: [],
      user: {},
      userDialog: false,
      loading: false,
      actionPeriodo: '',
      gestiones: [],
      filter: '',
      roles: ['Admin', 'Usuario'],
      columns: [
        { name: 'actions', label: 'Acciones', align: 'center' },
        { name: 'name', label: 'Nombre', align: 'left', field: 'name' },
        { name: 'username', label: 'Usuario', align: 'left', field: 'username' },
        { name: 'role', label: 'Rol', align: 'left', field: 'role' },
        // { name: 'sucursal', label: 'Sucursal', align: 'left', field: 'sucursal' }
      ],
      permissions: [],
      dialogPermisos: false
    }
  },
  mounted() {
    this.usersGet()
    // this.permissionsGet()
  },
  methods: {
    permisosPost() {
      this.loading = true
      const permissions = this.permissions.filter(p => p.checked).map(p => p.id)
      this.$axios.post('permissions/' + this.user.id, { permissions }).then(res => {
        this.dialogPermisos = false
        this.$alert.success('Permisos actualizados')
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },
    permissionsGet() {
      this.$axios.get('permissions').then(res => {
        this.permissions = res.data
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      })
    },
    userNew() {
      this.user = {
        name: '',
        email: '',
        password: '',
        area_id: 1,
        username: '',
        cargo: '',
        role: 'Usuario',
        agencia: 'Challgua',
      }
      this.actionPeriodo = 'Nuevo'
      this.userDialog = true
    },
    usersGet() {
      this.loading = true
      this.$axios.get('users').then(res => {
        this.users = res.data
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },
    gestionGet() {
      this.loading = true
      this.$axios.get('gestiones').then(res => {
        this.gestiones = res.data
        this.loading = false
      }).catch(error => {
        this.$alert.error(error.response.data.message)
        this.loading = false
      })
    },
    userPost() {
      this.loading = true
      this.$axios.post('users', this.user).then(res => {
        this.usersGet()
        this.userDialog = false
        this.$alert.success('Periodo creado')
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },
    userPut() {
      this.loading = true
      this.$axios.put('users/' + this.user.id, this.user).then(res => {
        this.usersGet()
        this.userDialog = false
        this.$alert.success('Periodo actualizado')
      }).catch(error => {
        this.$alert.error(error.response.data.message)
      }).finally(() => {
        this.loading = false
      })
    },
    permisosShow(user) {
      this.dialogPermisos = true
      this.user = { ...user }
      this.permissions.forEach(permiso => {
        permiso.checked = user.permissions.some(p => p.id === permiso.id)
      })
    },
    userEditPassword(user) {
      this.user = { ...user }
      this.$alert.dialogPrompt('Nueva contraseña', 'Ingrese la nueva contraseña', 'password')
        .onOk(password => {
          this.$axios.put('updatePassword/' + user.id, { password }).then(res => {
            this.usersGet()
            this.$alert.success('Contraseña actualizada')
          }).catch(error => {
            this.$alert.error(error.response.data.message)
          })
        })
    },
    userEdit(user) {
      this.user = { ...user }
      this.actionPeriodo = 'Editar'
      this.userDialog = true
    },
    userDelete(id) {
      this.$alert.dialog('¿Desea eliminar el user?')
        .onOk(() => {
          this.loading = true
          this.$axios.delete('users/' + id).then(res => {
            this.usersGet()
            this.$alert.success('Periodo eliminado')
          }).catch(error => {
            this.$alert.error(error.response.data.message)
          }).finally(() => {
            this.loading = false
          })
        })
    }
  }
}
</script>
