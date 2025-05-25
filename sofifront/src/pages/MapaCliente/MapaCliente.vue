<template>
  <q-page class="bg-grey-3 q-pa-xs">
    <q-card flat bordered>
      <q-card-section>
        <div class="row">
          <div class="col-xs-12 col-md-6 q-pa-xs">
            <q-input v-model="fecha" label="Fecha" type="date" dense outlined @update:model-value="buscar"/>
          </div>
          <div class="col-xs-2 col-md-2 q-pa-xs">
            <q-btn color="purple" label="5" @click="loadGeoJson(5)" :loading="loading"/>
          </div>
          <div class="col-xs-2 col-md-2 q-pa-xs">
            <q-btn color="green" label="4" @click="loadGeoJson(4)" :loading="loading"/>
          </div>
          <div class="col-xs-2 col-md-2 q-pa-xs">
            <q-btn color="orange" label="3" @click="loadGeoJson(3)" :loading="loading"/>
          </div>
        </div>
        <div class="row">
          <div class="q-pa-xs col-md-2 col-xs-4 " v-for="vih in vehiculos" :key="vih"
               style="font-size: 12px;"><b>{{ vih.placa == '' ? 'SIN ASIGNAR' : vih.placa }} :
            {{ calculo(vih.placa) }}</b></div>
          <br>
        </div>
        <div class="row">
          <div class="col-xs-12 col-md-12">
            <div style="height: 500px; width: 100%;">
              <l-map
                v-model="zoom"
                :zoom="zoom"
                :center="center"
              >
                <LTileLayer
                  url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                ></LTileLayer>
                <LGeoJson :geojson="geojsonData" :options="geoJsonOptions"/>
                <l-marker
                  v-for="(pedido, i) in clientes"
                  :key="i"
                  :lat-lng="[parseFloat(pedido.Latitud), parseFloat(pedido.longitud)]"
                  @click="toggleSeleccion(pedido)"
                >
                  <l-tooltip
                    :content="pedido.Nombres+' Total:' +pedido.importe+' Bs'"
                    :permanent="false"
                    :direction="'top'"
                  />
                  <l-icon>
                    <q-badge
                      style="padding: 2px"
                      :color="pedido.selected ? 'red' : pedido.color"
                    >
                      {{ pedido.num }}
<!--                      <pre>{{pedido}}</pre>-->
                    </q-badge>
                  </l-icon>
                </l-marker>
              </l-map>
              <!--              <pre>{{ clientes }}</pre>-->
            </div>
            <br>
            <div class="col-12 col-md-12">
              <div class="row">
<!--                <div class="col-12 col-md-2">-->
<!--                  <q-select-->
<!--                    dense-->
<!--                    outlined-->
<!--                    v-model="auto"-->
<!--                    :options="vehiculos"-->
<!--                    option-label="placa"-->
<!--                    label="Camion Asignar"-->
<!--                  />-->
<!--                </div>-->
                <div class="col-12 col-md-2">
                  <q-btn color="green" icon="local_shipping" @click="cambiar" no-caps label="Asignar"
                         :loading="loading"/>
                </div>
                <div class="col-12 col-md-2">
                  <q-btn color="info" icon="print" @click="generarPdf" label="Imprimir" no-caps :loading="loading"/>
                </div>
                <div class="col-12 col-md-2"></div>
                <div class="col-12 col-md-2"></div>
                <div class="col-12 col-md-2">
                  <q-select square outlined v-model="usuario" :options="vendedores" label="Vendedor" dense
                            @update:model-value="filtrarClientes"/>
                </div>
                <div class="col-12 col-md-2">
                  <q-select square outlined v-model="tipoProducto" :options="['NORMAL','POLLO','CERDO']"
                            label="Producto" dense
                            @update:model-value="buscar"/>
                </div>
              </div>
            </div>
          </div>
          <div class="col-xs-12 col-md-12">
            <q-table dense :rows="clientes" :columns="column" row-key="name" :rows-per-page-options="['0']"
                     :filter="filtro" style="font-size:10px; height: 400px" virtual-scroll flat bordered>
              <template v-slot:top-right>
                <q-input outlined dense debounce="300" v-model="filtro" placeholder="buscar">
                  <template v-slot:append>
                    <q-icon name="search"/>
                  </template>
                </q-input>
              </template>
              <template v-slot:body="props">
                <tr
                  :class=" props.row.selected?'bg-red':'bg-'+props.row.color"
                  style=" font-size:10px "
                  @click="toggleSeleccionZoom(props.row)"
                >
                  <!--                  {{props.row.color}}-->
                  <td v-for="col in column" :key="col.name"
                      style="font-size:10px">
                    {{ props.row[col.field] }}
                  </td>
                  <td key="op">
                    <q-btn color="info" icon="visibility" dense flat @click="verDetalle(props.row)"/>
                  </td>
                </tr>
              </template>
            </q-table>
            <!--            <pre>-->
            <!--              {{ clientes }}-->
            <!--            </pre>-->
            <!--
          <q-markup-table flat bordered dense wrap-cells class="bg-primary text-white cursor-pointer" >
            <thead>
              <tr>
                <th>#</th>
                <th>Cinit</th>
                <th>Cliente</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(user, index) in clientes" :key="index" @click="toggleSeleccion(user)" :class="user.selected?'bg-red':'bg-'+user.color">
                <td>{{ user.num }}</td>
                <td>{{ user.Id }} </td>
                <td>
                  <div style="text-transform: lowercase; line-height: 0.9;">
                    {{ user.Nombres }}
                  </div>
                </td>
              </tr>
            </tbody>
          </q-markup-table>-->
          </div>
        </div>


      </q-card-section>
    </q-card>

    <q-dialog v-model="dialogDetalle">
      <q-card>
        <q-card-section>
          <div class="text-h6">{{ detalle.Nombres }}</div>
        </q-card-section>
        <q-card-section>
          <table>
            <tr>
              <th>PEDIDO</th>
              <th>CODIGO</th>
              <th>CANTIDAD</th>
              <th>PRODUCTO</th>
            </tr>
            <tr v-for="pedido in detalle.contenido" :key="pedido">
              <td>{{ pedido.NroPed }}</td>
              <td>{{ pedido.cod_prod }}</td>
              <td>{{ pedido.Cant }}</td>
              <td>{{ pedido.Producto }}</td>
            </tr>
          </table>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="OK" color="primary" v-close-popup/>
        </q-card-actions>
      </q-card>
    </q-dialog>
    <q-dialog v-model="dialgoCamion">
      <q-card style="width: 300px">
        <q-card-section class="row items-center">
          <div class="text-h6">Asignar Camion</div>
          <q-space/>
          <q-btn flat icon="close" @click="dialgoCamion = false"/>
        </q-card-section>
        <q-card-section>
          <q-form @submit.prevent="asignarCamion">
          <q-select v-model="auto" :options="vehiculos" option-label="placa" label="Camion Asignar" dense outlined
                    :rules="[val => !!val || 'Campo requerido']"
          />
          <q-select v-model="color" :options="colores" option-label="zona" label="Color Asignar" dense outlined
                    :rules="[val => !!val || 'Campo requerido']">
            <template v-slot:option="scope">
              <q-item v-bind="scope.itemProps">
                <q-item-section>
                  <div :class="'text-center bg-'+scope.opt.color">
                    {{ scope.opt.zona }}
                  </div>
                </q-item-section>
              </q-item>
            </template>
            <template v-slot:selected-item="scope">
<!--              <q-item v-bind="scope.itemProps">-->
<!--                <q-item-section>-->
                  <div :class="'text-center bg-'+scope.opt.color">
                    {{ scope.opt.zona }}
                  </div>
<!--                </q-item-section>-->
<!--              </q-item>-->
            </template>
          </q-select>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" color="primary" @click="dialgoCamion = false" :loading="loading"/>
            <q-btn label="Asignar" color="primary"  type="submit" :loading="loading"/>
          </q-card-actions>
          </q-form>
        </q-card-section>
      </q-card>

    </q-dialog>

  </q-page>
</template>

<script>
import moment from "moment";
import {LIcon, LMap, LMarker, LTileLayer, LTooltip, LGeoJson} from "@vue-leaflet/vue-leaflet";


export default {
  name: "mapaCliente",
  components: {
    LMap,
    LIcon,
    LTileLayer,
    LMarker,
    LTooltip,
    LGeoJson
  },
  data() {
    return {
      color: '',
      colores: [
          {
            "id": 1,
            "zona": "NORTE",
            "color": "deep-orange-4",
            "colorStyle": "background-color: #FF7043"
          },
          {
            "id": 2,
            "zona": "BOLIVAR",
            "color": "pink-4",
            "colorStyle": "background-color: #F06292"
          },
          {
            "id": 3,
            "zona": "SE RECOGE",
            "color": "blue-grey-4",
            "colorStyle": "background-color: #37474F"
          },
          {
            "id": 4,
            "zona": "CENTRO",
            "color": "yellow",
            "colorStyle": "background-color: #F5EE17"
          },
          {
            "id": 5,
            "zona": "APOYO",
            "color": "green-4",
            "colorStyle": "background-color: #1B5E20"
          },
          {
            "id": 6,
            "zona": "PROVINCIA",
            "color": "deep-purple-4",
            "colorStyle": "background-color: #9575CD"
          },
          {
            "id": 7,
            "zona": "SUD",
            "color": "blue-4",
            "colorStyle": "background-color: #0D47A1"
          },
          {
            "id": 8,
            "zona": "SIN ZONA",
            "color": "grey-6",
            "colorStyle": "background-color: #757575"
          }
      ],
      tipoProducto: 'NORMAL',
      styleGeoJSON: (feature) => ({
        color: feature.properties.color || 'red', // Usa el color de las propiedades o transparente
        weight: 2,
        opacity: 0.2,
        fillOpacity: 0.3, // Ajusta la opacidad del relleno
      }),
      center: [-17.969721, -67.114493],
      filtro: '',
      listado: [],
      vehiculos: [],
      auto: {},
      zoom: 13,
      fecha: moment().format("YYYY-MM-DD"),
      minimo: moment().subtract(1, 'days').format("YYYY-MM-DD"),
      loading: false,
      clientes: [],
      seleccionados: [], // Lista de clientes seleccionados
      column: [
        {label: 'OP', name: 'op', field: 'op', sortable: true},
        {label: 'N', name: 'id', field: 'num', sortable: true, align: 'center'},
        {label: 'CINIT', name: 'id', field: 'Id', sortable: true, align: 'center'},
        {label: 'CLIENTE', name: 'nombre', field: 'Nombres', sortable: true, align: 'left'},
        {label: 'HORARIO', name: 'horario', field: 'horario', sortable: true, align: 'left'},
        {label: 'VENDEDOR', name: 'vendedor', field: 'vendedor', sortable: true},
        {label: 'ZONA', name: 'zona', field: 'territorio', sortable: true},
        {label: 'PLACA', name: 'placa', field: 'placa', sortable: true},
      ],
      dialgoCamion: false,
      geojsonData: null,
      geojsonData5: {
        "type": "FeatureCollection",
        "features": [
          {
            "type": "Feature",
            "properties": {
              "name": "NORTE",
              "color": "#ff0000"
            },
            "geometry": {
              "type": "Polygon",
              "coordinates": [[
                [-67.099879, -17.95474], [-67.100311, -17.944827], [-67.085978, -17.93315],
                [-67.071816, -17.93217], [-67.075506, -17.919267], [-67.113353, -17.911143],
                [-67.118482, -17.893828], [-67.134844, -17.895579], [-67.130279, -17.92691],
                [-67.121165, -17.94142], [-67.120854, -17.948468], [-67.122543, -17.949004],
                [-67.12654, -17.94755], [-67.127607, -17.956572], [-67.125864, -17.957169],
                [-67.122549, -17.957674], [-67.117989, -17.956378], [-67.115403, -17.956363],
                [-67.114395, -17.956552], [-67.109594, -17.957144], [-67.107067, -17.957802],
                [-67.10601, -17.95376], [-67.104272, -17.954179], [-67.103274, -17.954444],
                [-67.101654, -17.954852], [-67.100496, -17.955036], [-67.099879, -17.95474]
              ]]
            }
          },
          {
            "type": "Feature",
            "properties": {
              "name": "SUD",
              "color": "green"
            },
            "geometry": {
              "type": "Polygon",
              "coordinates": [[
                [-67.092556, -17.963821], [-67.093142, -17.971621], [-67.098464, -17.970519],
                [-67.100556, -17.977601], [-67.108699, -17.975938], [-67.108957, -17.976918],
                [-67.111124, -17.976509], [-67.110909, -17.975468], [-67.115952, -17.974713],
                [-67.116317, -17.975489], [-67.116617, -17.976754], [-67.123312, -17.978958],
                [-67.127775, -17.976999], [-67.133354, -17.972346], [-67.138847, -17.965569],
                [-67.144598, -17.96663], [-67.142624, -17.973529], [-67.14477, -17.976958],
                [-67.145242, -17.981693], [-67.141036, -17.983979], [-67.137045, -17.985898],
                [-67.138847, -17.99349], [-67.136358, -18.006061], [-67.10606, -18.001],
                [-67.106575, -17.98998], [-67.096447, -17.983285], [-67.091297, -17.993082],
                [-67.070526, -18.000428], [-67.049898, -17.981904], [-67.047409, -17.97223],
                [-67.07303, -17.964433], [-67.074703, -17.973006], [-67.075905, -17.974639],
                [-67.081012, -17.966515], [-67.092556, -17.963821]
              ]]
            }
          },
          {
            "type": "Feature",
            "properties": {
              "name": "BOLIVAR",
              "color": "blue"
            },
            "geometry": {
              "type": "Polygon",
              "coordinates": [[
                [-67.098232, -17.969964], [-67.09815, -17.969718], [-67.097392, -17.967165],
                [-67.105855, -17.965936], [-67.106005, -17.965896], [-67.106906, -17.96859],
                [-67.108699, -17.975938], [-67.100598, -17.977326], [-67.098464, -17.970519],
                [-67.093142, -17.971621], [-67.095195, -17.970559], [-67.098171, -17.970048],
                [-67.098232, -17.969964]
              ]]
            }
          },
          {
            "type": "Feature",
            "properties": {
              "name": "CENTRO",
              "color": "orange"
            },
            "geometry": {
              "type": "Polygon",
              "coordinates": [[
                [-67.107257, -17.964448], [-67.112021, -17.964469], [-67.112665, -17.965122],
                [-67.112922, -17.965897], [-67.118587, -17.963938], [-67.120196, -17.964836],
                [-67.120432, -17.967877], [-67.122557, -17.967938], [-67.122771, -17.971939],
                [-67.119316, -17.972449], [-67.11687, -17.974286], [-67.111763, -17.975041],
                [-67.110948, -17.975164], [-67.109875, -17.971408], [-67.10891, -17.971551],
                [-67.107107, -17.964489], [-67.107257, -17.964448]
              ]]
            }
          },
          {
            "type": "Feature",
            "properties": {
              "name": "APOYO",
              "color": "purple"
            },
            "geometry": {
              "type": "Polygon",
              "coordinates": [[
                [-67.107067, -17.957679], [-67.10971, -17.957368], [-67.112739, -17.956852],
                [-67.113297, -17.964405], [-67.107031, -17.963751], [-67.106859, -17.965548],
                [-67.097392, -17.967042], [-67.098232, -17.969964], [-67.093142, -17.971621],
                [-67.092556, -17.963821], [-67.081012, -17.966392], [-67.089865, -17.947666],
                [-67.083771, -17.944808], [-67.089092, -17.936153], [-67.100311, -17.944704],
                [-67.099948, -17.954411], [-67.09995, -17.955219], [-67.105829, -17.953913],
                [-67.107067, -17.957679]
              ]]
            }
          }
        ]
      },
      geojsonData4: {
        "type": "FeatureCollection",
        "features": [
          {
            "type": "Feature",
            "properties": {
              "name": "ZONA SUD",
              "color": "#00FF00" // Verde
            },
            "geometry": {
              "type": "Polygon",
              "coordinates": [[
                [-67.092526, -17.9638446], [-67.0934008, -17.9716146], [-67.097349, -17.9707982], [-67.0981215, -17.9732474],
                [-67.0988939, -17.975758], [-67.100911, -17.9752884], [-67.1011041, -17.9758191], [-67.1044301, -17.9750844],
                [-67.1080349, -17.974023], [-67.1088074, -17.9759824], [-67.1107386, -17.9756967], [-67.116146, -17.9750027],
                [-67.1212528, -17.974615], [-67.122433, -17.9782582], [-67.1253298, -17.9785133], [-67.1385478, -17.9713697],
                [-67.1423243, -17.9744721], [-67.1371745, -18.0067992], [-67.118635, -18.0010853], [-67.1040438, -18.0005955],
                [-67.0892809, -17.9956976], [-67.0839594, -17.9986363], [-67.0683383, -17.9986363], [-67.0635318, -17.9942282],
                [-67.0587597, -17.9863438], [-67.0507265, -17.980418], [-67.0472504, -17.9680901], [-67.0516706, -17.9664572],
                [-67.0731712, -17.9671104], [-67.0733, -17.9725805], [-67.0749307, -17.9725805], [-67.0755316, -17.9746216],
                [-67.0781923, -17.9741317], [-67.0776773, -17.97156], [-67.0809818, -17.9665797], [-67.092526, -17.9638446]
              ]]
            }
          },
          {
            "type": "Feature",
            "properties": {
              "name": "ZONA CENTRO",
              "color": "#FFA500" // Naranja
            },
            "geometry": {
              "type": "Polygon",
              "coordinates": [[
                [-67.0999669, -17.9547953], [-67.1008037, -17.9550607], [-67.1046017, -17.9540605], [-67.1053742, -17.9538156],
                [-67.1058625, -17.9536869], [-67.1061629, -17.9549933], [-67.1102666, -17.9544279], [-67.1147726, -17.9542238],
                [-67.1192787, -17.9545095], [-67.120459, -17.9599086], [-67.1207593, -17.9608986], [-67.1202229, -17.9616946],
                [-67.1199654, -17.9627969], [-67.1198367, -17.9632051], [-67.1191071, -17.9632052], [-67.12018, -17.9655321],
                [-67.1214245, -17.9673283], [-67.123227, -17.9679406], [-67.1209525, -17.9742271], [-67.1137427, -17.9750844],
                [-67.1107386, -17.9756967], [-67.1097086, -17.9714105], [-67.1086358, -17.9718595], [-67.1055459, -17.9608374],
                [-67.1001814, -17.9618172], [-67.0995806, -17.9584288], [-67.0999669, -17.9547953]
              ]]
            }
          },
          {
            "type": "Feature",
            "properties": {
              "name": "ZONA BOLIVAR",
              "color": "#0000FF" // Azul
            },
            "geometry": {
              "type": "Polygon",
              "coordinates": [[
                [-67.0999669, -17.9547953], [-67.0998618, -17.9552061], [-67.0995346, -17.9584747],
                [-67.0996204, -17.9594902], [-67.1001569, -17.9619396], [-67.1003607, -17.9618886],
                [-67.1055106, -17.9609088], [-67.1072379, -17.9671038], [-67.107549, -17.9682876],
                [-67.1088794, -17.9726351], [-67.1085146, -17.9729413], [-67.1089009, -17.9738291],
                [-67.104148, -17.9749517], [-67.1031717, -17.9751967], [-67.1022651, -17.9754926],
                [-67.1011041, -17.9758191], [-67.100911, -17.9752884], [-67.0988939, -17.975758],
                [-67.0981215, -17.9732474], [-67.0974425, -17.971135], [-67.0973245, -17.9706145],
                [-67.0934621, -17.9713697], [-67.0926252, -17.9643686], [-67.0928398, -17.9617151],
                [-67.0933333, -17.9594902], [-67.09402, -17.9574694], [-67.0949641, -17.9555914],
                [-67.096316, -17.9531827], [-67.0999669, -17.9547953]
              ]]
            }
          },
          {
            "type": "Feature",
            "properties": {
              "name": "ZONA NORTE",
              "color": "#800080" // Morado
            },
            "geometry": {
              "type": "Polygon",
              "coordinates": [[
                [-67.1046017, -17.9540605], [-67.1008037, -17.9550607], [-67.096316, -17.9531827],
                [-67.0791921, -17.945785], [-67.0861015, -17.9354962], [-67.0785055, -17.9299432],
                [-67.0850286, -17.9190818], [-67.0964012, -17.9260642], [-67.1100053, -17.9184285],
                [-67.1126669, -17.8967851], [-67.1372145, -17.8989087], [-67.1233099, -17.9284732],
                [-67.1200483, -17.9372925], [-67.1200483, -17.951174], [-67.1195525, -17.9543809],
                [-67.1128147, -17.9540135], [-67.1089524, -17.9544626], [-67.1061629, -17.9549933],
                [-67.1058625, -17.9536869], [-67.1046017, -17.9540605]
              ]]
            }
          }
        ]
      },
      url:process.env.API,
      geojsonData3: {
        "type": "FeatureCollection",
        "features": [
          {
            "type": "Feature",
            "properties": {
              "name": "CENTRO/BOLIVAR",
              "color": "#1f77b4"  // Azul
            },
            "geometry": {
              "type": "Polygon",
              "coordinates": [[
                [-67.1001614, -17.9618895], [-67.0996035, -17.9593584], [-67.0996571, -17.9565364], [-67.0998663, -17.9556791], [-67.1000326, -17.954837],
                [-67.1006979, -17.9551126], [-67.1041525, -17.954092], [-67.1042813, -17.9565824], [-67.1055258, -17.9604097], [-67.1061481, -17.96241],
                [-67.1102143, -17.9622059], [-67.1125854, -17.9622876], [-67.1135832, -17.9622876], [-67.1133793, -17.9625427], [-67.1144522, -17.9644206],
                [-67.1174992, -17.9634], [-67.1208037, -17.9637674], [-67.1189154, -17.9650737], [-67.1193875, -17.9662984], [-67.1220482, -17.9676048],
                [-67.1232069, -17.972136], [-67.1188725, -17.9749935], [-67.1165551, -17.974422], [-67.1108044, -17.9754833], [-67.109989, -17.9715237],
                [-67.1087016, -17.9718502], [-67.1089161, -17.972585], [-67.1083582, -17.9729932], [-67.1088303, -17.9737688], [-67.1083153, -17.9740138],
                [-67.1042813, -17.9749935], [-67.1037234, -17.9733606], [-67.1016205, -17.9739729], [-67.0998824, -17.9683803], [-67.0981015, -17.9624611],
                [-67.1001614, -17.9618895]
              ]]
            }
          },
          {
            "type": "Feature",
            "properties": {
              "name": "ZONA SUD",
              "color": "#2ca02c"  // Verde
            },
            "geometry": {
              "type": "Polygon",
              "coordinates": [[
                [-67.0998663, -17.9549646], [-67.0991195, -17.9594247], [-67.1000541, -17.9619712], [-67.0979942, -17.9625428], [-67.1015132, -17.9740546],
                [-67.1036161, -17.9734423], [-67.1042478, -17.9751413], [-67.1062434, -17.9746311], [-67.1082604, -17.9741208], [-67.1089471, -17.9767945],
                [-67.1101058, -17.976815], [-67.1113569, -17.9766065], [-67.1123868, -17.9795456], [-67.1161634, -17.980362], [-67.1225149, -17.9797089],
                [-67.1355611, -17.9756269], [-67.1403676, -17.9697486], [-67.139166, -17.9852603], [-67.1373636, -18.0023214], [-67.1346599, -18.0020358],
                [-67.1331364, -17.9982197], [-67.1232015, -18.0019133], [-67.1027738, -18.0006073], [-67.0897275, -17.9945666], [-67.0698148, -17.9939135],
                [-67.0689565, -17.9862399], [-67.0500738, -17.9823213], [-67.0505887, -17.9735858], [-67.0735145, -17.9705686], [-67.0733857, -17.9728546],
                [-67.074759, -17.9726097], [-67.0754457, -17.9746915], [-67.0779777, -17.9742833], [-67.0776344, -17.9712217], [-67.0804239, -17.9679968],
                [-67.0835996, -17.9613018], [-67.0925689, -17.9613426], [-67.0939422, -17.9570969], [-67.096088, -17.9532236], [-67.0975502, -17.953854],
                [-67.099152, -17.9545406], [-67.0998663, -17.9549646]
              ]]
            }
          },
          {
            "type": "Feature",
            "properties": {
              "name": "ZONA NORTE",
              "color": "#d62728"  // Rojo
            },
            "geometry": {
              "type": "Polygon",
              "coordinates": [[
                [-67.0960451, -17.9532848], [-67.08897, -17.9481364], [-67.0878542, -17.9405424], [-67.0841635, -17.9372761],
                [-67.0864809, -17.9337647], [-67.0779837, -17.9266601], [-67.0841635, -17.9220869], [-67.08897, -17.9248635],
                [-67.0926607, -17.9211885], [-67.0946348, -17.9245368], [-67.1063936, -17.9203719], [-67.1125734, -17.9162885],
                [-67.1151483, -17.8983205], [-67.1188391, -17.8889274], [-67.1273363, -17.8939099], [-67.1308554, -17.8850884],
                [-67.1357477, -17.8876205], [-67.1327436, -17.8987289], [-67.1348036, -17.9071414], [-67.1342028, -17.9133484],
                [-67.138666, -17.9203719], [-67.1357477, -17.9262518], [-67.1292246, -17.9296], [-67.1256197, -17.9318865],
                [-67.1213935, -17.9352188], [-67.1202777, -17.9396284], [-67.1239684, -17.9428947], [-67.1225951, -17.9487738],
                [-67.1268867, -17.9458343], [-67.1289466, -17.9486922], [-67.1268867, -17.9520399], [-67.1281741, -17.9552243],
                [-67.1234534, -17.9579188], [-67.1177886, -17.956694], [-67.1163295, -17.9584087], [-67.1135832, -17.9622876],
                [-67.1098013, -17.9621855], [-67.1061481, -17.96241], [-67.1041525, -17.954092], [-67.1000326, -17.954837], [-67.0960451, -17.9532848]
              ]]
            }
          }
        ]
      }
      ,

      enableTooltip: true,
      options: {
        onEachFeature: this.onEachFeatureFunction
      },
      vendedores: [],
      filtercliente: [],
      geoJsonOptions: {
        style: (feature) => ({
          color: feature.properties.color, // Color según la propiedad del GeoJSON
          weight: 0.5,
          opacity: 0.1,
          fillOpacity: 0.2,
        }),
      },
      detalle: [],
      dialogDetalle: false,
      usuario: []
    }
  },
  mounted() {
    // ordernar zonas por zona
    this.colores.sort((a, b) => a.zona.localeCompare(b.zona));
    this.geojsonData = this.geojsonData5;
    this.buscar();
    this.getVehiculo();
  },

  methods: {
    filtrarClientes() {
      if (this.usuario == 'TODOS') {
        this.clientes = this.filtercliente;
      } else {
        this.clientes = this.filtercliente.filter(c => c.vendedor === this.usuario);
      }
    },
    loadGeoJson(index) {
      switch (index) {
        case 5:
          this.geojsonData = this.geojsonData5;
          break;
        case 4:
          this.geojsonData = this.geojsonData4;
          break;
        case 3:
          this.geojsonData = this.geojsonData3;
          break;
      }
    },
    verDetalle(dato) {
      dato.fecha = this.fecha
      console.log(dato)
      this.$api.post("detallePedMap", dato).then((res) => {
        console.log(res.data)
        this.detalle = dato
        this.detalle.contenido = res.data
        this.dialogDetalle = true
      })
    },
    calculo(veh) {
      let total = 0
      this.clientes.forEach(r => {
        if (r.placa == veh)
          total++;
      });
      return total
    },
    onEachFeatureFunction() {
      if (!this.enableTooltip) {
        return () => {
        };
      }
      return (feature, layer) => {
        layer.bindTooltip(
          "<div>" +
          feature.properties.name +
          "</div>",
          {permanent: false, sticky: true}
        );
      };

    },
    // Función que se ejecuta cuando el mouse pasa sobre un polígono
    generarPdf() {
      // :href="`${url}reportePedido/${fecha1}`" target="_blank"
      const urlapi = `${this.url}reportePedido/${this.fecha}`
      window.open(urlapi, '_blank')
    },
    getVehiculo() {
      this.$api.post("listVehiculo").then((res) => {
        res.data.forEach(r => {
          r.cantidad = 0
        });
        this.vehiculos = res.data;
        this.auto = this.vehiculos[0]
      });
    },
    buscar() {
      this.loading = true;
      this.clientes = [];
      this.vendedores = [];
      const setVendedores = new Set();
      this.usuario = []
      this.$api.post("mapClientes", {
        fecha: this.fecha,
        tipo: this.tipoProducto,
      }).then((res) => {
        console.log(res.data)
        let numero = 1
        res.data.forEach(r => {
          r.num = numero
          r.selected = false
          r.showTooltip = false
          numero++
          setVendedores.add(r.vendedor);
        });
        this.vendedores = Array.from(setVendedores);
        this.vendedores.push('TODOS')
        this.usuario = 'TODOS'
        this.clientes = res.data;
        this.filtercliente = this.clientes
        console.log(this.usuario)
      }).finally(() => {
        this.loading = false;
      });
    },
    toggleSeleccion(cliente) {
      // this.center = [cliente.Latitud, cliente.longitud];
      // this.zoom = 17;
      cliente.selected = !cliente.selected;
      if (cliente.selected) {
        this.seleccionados.push(cliente);
      } else {
        this.seleccionados = this.seleccionados.filter((item) => item.Id !== cliente.Id);
      }

      // Ocultar todos los tooltips
      // this.clientes.forEach(c => {
      //   c.showTooltip = false;
      //   if (c.marker && c.marker.leafletObject && c.marker.leafletObject.getTooltip()) {
      //     c.marker.leafletObject.closeTooltip();
      //   }
      // });

      this.$nextTick(() => {
        cliente.showTooltip = true;

        if (cliente.marker && cliente.marker.leafletObject) {
          cliente.marker.leafletObject.openTooltip();
        }
      });
    },
    toggleSeleccionZoom(cliente) {
      this.center = [cliente.Latitud, cliente.longitud];
      this.zoom = 17;
      cliente.selected = !cliente.selected;
      if (cliente.selected) {
        this.seleccionados.push(cliente);
      } else {
        this.seleccionados = this.seleccionados.filter((item) => item.Id !== cliente.Id);
      }

      // Ocultar todos los tooltips
      // this.clientes.forEach(c => {
      //   c.showTooltip = false;
      //   if (c.marker && c.marker.leafletObject && c.marker.leafletObject.getTooltip()) {
      //     c.marker.leafletObject.closeTooltip();
      //   }
      // });

      this.$nextTick(() => {
        cliente.showTooltip = true;

        if (cliente.marker && cliente.marker.leafletObject) {
          cliente.marker.leafletObject.openTooltip();
        }
      });
    },
    asignarCamion() {
      // this.dialgoCamion = false
      this.loading = true;
      // console.log('fecha ' + this.fecha)
      // console.log('vehiculo ' + this.auto.placa)
      // console.log(this.seleccionados)
      this.$api.post("updaVehiPed", {
        fecha: this.fecha,
        placa: this.auto.placa,
        listado: this.seleccionados,
        color: this.color
      }).then((res) => {
        this.seleccionados = []
        this.buscar()
        this.dialgoCamion = false
      }).finally(() => {
        this.loading = false;
      });
    },
    cambiar() {
      this.dialgoCamion = true
      // this.loading = true;
      // console.log('fecha ' + this.fecha)
      // console.log('vehiculo ' + this.auto.placa)
      // console.log(this.seleccionados)
      // this.$api.post("updaVehiPed", {
      //   fecha: this.fecha,
      //   placa: this.auto.placa,
      //   listado: this.seleccionados
      // }).then((res) => {
      //   this.seleccionados = []
      //   this.buscar()
      // })
    }
  }
};
</script>
