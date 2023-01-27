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
          <q-btn color="info" icon="search" label="consulta" @click="consultar" size="xs" />
        </div>

        <div class="col-12">
          <q-table dense title="Clientes " :columns="columns" :rows="clientes" :filter="filter">
            <template v-slot:top-right>
              <q-input outlined dense debounce="300" v-model="filter" placeholder="Buscar">
                <template v-slot:append>
                  <q-icon name="search" />
                </template>
              </q-input>
            </template>

          </q-table>
        </div>
      </div>
    </q-page>
    </template>
    <script>
    import {date} from 'quasar'
    import {jsPDF} from "jspdf";
    const conversor = require('conversor-numero-a-letras-es-ar');

    export default {
      data(){
        return{
          filter:'',
          fecha1:date.formatDate(Date.now(),'YYYY-MM-DD'),
          fecha2:date.formatDate(Date.now(),'YYYY-MM-DD'),
          cobros:[],
          clientes:[],
          columns:[
            {label:'ci',name:'ci',field:'Id'},
            {label:'NOMBRE',name:'Nombres',field:'Nombres'},
          ],
        }
        },
        created() {

          this.consultar();

        },
        methods: {

          consultar(){
          this.$q.loading.show()
          let userlog=this.$store.state.login.user
          console.log(user)
          this.$api.post('listsinpedido',{ini:this.fecha1,fin:this.fecha2,user:userlog}).then(res=>{
            //console.log('s')
            console.log(res.data)
           // return false
            this.clientes=res.data;
            this.$q.loading.hide()

          }).catch(err=>{
            // console.log(err.response)
            this.$q.loading.hide()
            this.$q.notify({
              message:err.response.data.message,
              color:'red',
              icon:'error'
            })
          })

          },



        },

    }
    </script>

    <style scoped>

    </style>
