<template>
  <q-page class="q-pa-xs bg-grey-3">
    <q-card>
      <q-card-section class="q-pa-xs">
        <div class="row">
          <div class="col-2">
            <q-input v-model="fecha" label="Fecha" type="date" outlined dense />
          </div>
          <div class="col-2 flex flex-center">
            <q-btn
              @click="almacenVerificar"
              label="Verificar"
              color="primary"
              :loading="loading"
              icon="search"
              no-caps
            />
          </div>
          <div class="col-12">
            <q-markup-table dense wrap-cells>
              <thead>
                <tr>
<!--                  <th>Opciones</th>-->
                  <th>Código</th>
                  <th>Código Producto</th>
                  <th>Producto</th>
                  <th>Grupo</th>
                  <th>Registros</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="almacen in almacenes" :key="almacen.id">
<!--                  <td>-->
<!--                    <q-btn-->
<!--                      @click="almacen = almacen"-->
<!--                      label="Ver"-->
<!--                      color="primary"-->
<!--                      icon="search"-->
<!--                      no-caps-->
<!--                    />-->
<!--                  </td>-->
                  <td>{{ almacen.codigo }}</td>
                  <td>{{ almacen.codigo_producto }}</td>
                  <td>{{ $filters.capitalize(almacen.producto) }}</td>
                  <td>{{ almacen.grupo }}</td>
                  <td>
                    <ul style="list-style: none;margin: 0;padding: 0;">
                      <template v-for="registro in almacen.registros" :key="registro.id">
                        <li v-if="registro.cantidad" style="margin: 0;padding: 0;font-size:9px;">
                          {{ $filters.dateYmd(registro.fecha_vencimiento) }}
                          - <q-chip dense size="10px" :color="calculateColor(registro.dias_vencimiento)">{{ registro.dias_vencimiento }}</q-chip>
                          - {{ registro.cantidad }}
                          <input type="checkbox" v-model="registro.verificadoBool" @change="cambioCheck(registro)">
                          <q-chip dense size="10px" :color="registro.verificado === 'Verificado' ? 'positive' : 'warning'">{{ registro.verificado }}</q-chip>
                        </li>
                      </template>
                    </ul>
                  </td>
                </tr>
              </tbody>
            </q-markup-table>
<!--            [-->
<!--            {-->
<!--            "id": 22634,-->
<!--            "codigo": "B",-->
<!--            "codigo_producto": "533001                   ",-->
<!--            "producto": "CHORIZO TIPO ARGENTINO A GRANEL                             ",-->
<!--            "unidad": "0000000.00",-->
<!--            "saldo": 30.25,-->
<!--            "registro": "2024-08-21",-->
<!--            "vencimiento": "2024-08-21",-->
<!--            "grupo": "CHORIZO                                           ",-->
<!--            "se_descargo": "EXPORTADO",-->
<!--            "fecha_registro": "2024-08-24",-->
<!--            "cantidad": 31,-->
<!--            "created_at": null,-->
<!--            "updated_at": "2024-08-24T11:30:57.000000Z",-->
<!--            "registros": [-->
<!--            {-->
<!--            "id": 410891,-->
<!--            "cantidad": 31,-->
<!--            "fecha_vencimiento": "2024-08-30",-->
<!--            "almacen_id": 22634,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410892,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22634,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410893,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22634,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410894,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22634,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410895,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22634,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410896,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22634,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410897,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22634,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410898,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22634,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410899,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22634,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410900,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22634,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            }-->
<!--            ]-->
<!--            },-->
<!--            {-->
<!--            "id": 22646,-->
<!--            "codigo": "B",-->
<!--            "codigo_producto": "533349                   ",-->
<!--            "producto": "CHORIZO DE RES AL VACIO DE 5 UNIDADES                       ",-->
<!--            "unidad": "0000000.00",-->
<!--            "saldo": 22,-->
<!--            "registro": "2024-08-21",-->
<!--            "vencimiento": "2024-08-21",-->
<!--            "grupo": "CHORIZO                                           ",-->
<!--            "se_descargo": "EXPORTADO",-->
<!--            "fecha_registro": "2024-08-24",-->
<!--            "cantidad": 22,-->
<!--            "created_at": null,-->
<!--            "updated_at": "2024-08-24T11:30:57.000000Z",-->
<!--            "registros": [-->
<!--            {-->
<!--            "id": 410981,-->
<!--            "cantidad": 17,-->
<!--            "fecha_vencimiento": "2024-09-14",-->
<!--            "almacen_id": 22646,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410982,-->
<!--            "cantidad": 5,-->
<!--            "fecha_vencimiento": "2024-08-31",-->
<!--            "almacen_id": 22646,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410983,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22646,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410984,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22646,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410985,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22646,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410986,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22646,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410987,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22646,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410988,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22646,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410989,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22646,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            },-->
<!--            {-->
<!--            "id": 410990,-->
<!--            "cantidad": 0,-->
<!--            "fecha_vencimiento": null,-->
<!--            "almacen_id": 22646,-->
<!--            "user_id": 27,-->
<!--            "fecha_registro": "2024-08-24 11:30:57",-->
<!--            "verificado": "Pendiente",-->
<!--            "created_at": null,-->
<!--            "updated_at": null,-->
<!--            "lote": "",-->
<!--            "comentario": ""-->
<!--            }-->
<!--            ]-->
<!--            }-->
<!--            ]-->
          </div>
        </div>
      </q-card-section>
    </q-card>
<!--    <pre>{{almacenes}}</pre>-->
  </q-page>
</template>
<script>
import moment from "moment";

export default {
  name: "AlmacenVerificarPage",
  data() {
    return {
      // fecha: moment().format("YYYY-MM-DD"),
      fecha: '2024-08-24',
      almacenes: [],
      almacen: {},
      loading: false,
    };
  },
  mounted() {
    this.almacenVerificar()
  },
  methods: {
    calculateColor(dias) {
      if (dias < 0) {
        return "negative";
      } else if (dias < 30) {
        return "warning";
      } else {
        return "positive"
      }
    },
    cambioCheck(registro) {
      if (registro.verificadoBool) {
        registro.verificado = "Verificado";
      } else {
        registro.verificado = "Pendiente";
      }
      this.$api.put("almacenRegistroVerificar/" + registro.id, {
        verificado: registro.verificado,
      }).then((response) => {
        console.log(response.data);
      });
    },
    almacenVerificar() {
      this.loading = true;
      this.$api.get("almacenPendientes",
        {
          params: {
            fecha: this.fecha,
          },
        }
      ).then((response) => {
          this.almacenes = response.data;
      }).finally(() => {
        this.loading = false;
      });
    },
  },
};
</script>