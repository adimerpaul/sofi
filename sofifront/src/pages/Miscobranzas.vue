<template>
<q-page class="q-pa-xs">
  <div class="row">
    <div class="col-4">
      <q-input dense outlined v-model="fecha1" label="Fecha Ini" type="date"/>
    </div>
    <div class="col-4">
      <q-input dense outlined v-model="fecha2" label="Fecha Fin" type="date"/>
    </div>
    <div class="col-4 flex flex-center">
      <q-btn color="info" icon="search" label="consulta" @click="misclientes" size="xs" />
    </div>
    <div class="col-12">
      <q-table dense title="Clientes " :columns="columns" :rows="clientes" :filter="filter">
        <template v-slot:body-cell-opciones="props">
          <q-td :props="props">
            <q-btn  @click="listpedidos(props.row)" :color="props.row.estado=='CREADO'?'primary':'warning'" :label="props.row.estado=='CREADO'?'MODIFICAR':'ENVIADO'" icon="shop" size="xs"  />        </q-td>
        </template>
        <template v-slot:top-right>
          <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
            <template v-slot:append>
              <q-icon name="search" />
            </template>
          </q-input>
        </template>
      </q-table>
      <q-btn style="width: 100%" @click="enviarpedidos" color="accent" icon="check" label="Enviar todos los Cobros"> </q-btn>
      <q-btn style="width: 100%" @click="enviarpedidos" color="info" icon="print" label="Enviar todos los Cobros"> </q-btn>
    </div>
  </div>
</q-page>
</template>
<script>
import {date} from "quasar";

export default {
  data(){
    return{
      fecha1:date.formatDate(Date.now(),'YYYY-MM-DD'),
      fecha2:date.formatDate(Date.now(),'YYYY-MM-DD'),
    }
  }
}
</script>

<style scoped>

</style>
