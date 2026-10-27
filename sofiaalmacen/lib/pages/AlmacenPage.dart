import 'dart:convert';

import 'package:appsofia/models/Almacen.dart';
import 'package:appsofia/models/User.dart';
import 'package:appsofia/pages/AlmacenAlert.dart';
import 'package:appsofia/services/ImportService.dart';
import 'package:flutter/material.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:hive/hive.dart';


class AlmacenPage extends StatefulWidget {
  const AlmacenPage({super.key});

  @override
  State<AlmacenPage> createState() => _AlmacenPageState();
}

class _AlmacenPageState extends State<AlmacenPage> {
  var almacen = [];
  var almacenAll = [];
  var almacenTodo = [];
  var almacenPorColor = [];
  var user = User(id: 0);
  var loading = false;
  @override
  void initState() {
    getDatos();
    super.initState();
  }
  void getDatos() async {
    var almacenBox = await Hive.openBox<Almacen>('almacen');
    var userBox = await Hive.openBox<User>('user');
    setState(() {
      almacenTodo = almacenBox.values.toList();
      almacenPorColor=ordenaPorEstado(almacenTodo);
      almacen = almacenPorColor;
      almacenAll = almacenPorColor;
      // print(jsonEncode(almacen));
      user = User(id: userBox.getAt(0)!.id, name: userBox.getAt(0)!.name);
    });
    // print(user.name);
  }
  List<Almacen> ordenaPorEstado(almacen) {
    List<Almacen> pendientes = [];
    List<Almacen> cuadraNot = [];
    List<Almacen> cuadra = [];
    for (var i = 0; i < almacen.length; i++) {
      if (almacen[i].estado == 'PENDIENTE') {
        pendientes.add(almacen[i]);
      } else {
        // ordenar por que primero irian los que no cuadran la cantidad y luego los que cuadran
        if(almacen[i].saldo!=almacen[i].cantidad){
          cuadraNot.add(almacen[i]);
        }else{
          cuadra.add(almacen[i]);
        }
      }
    }
    pendientes.sort((a, b) => a.producto!.compareTo(b.producto!));
    cuadraNot.sort((a, b) => a.producto!.compareTo(b.producto!));
    cuadra.sort((a, b) => a.producto!.compareTo(b.producto!));
    return pendientes + cuadraNot + cuadra;
  }
  Color? getColorForCard(Almacen almacen) {
    double diferencia = almacen.saldo! - almacen.cantidad!;
    if (almacen.cantidad == 0) {
      return Colors.white;
    }else if (diferencia.abs() < 0.5) {
      return Colors.green[100];
    } else if (almacen.estado == 'PENDIENTE') {
      return Colors.white;
    } else {
      return Colors.red[100];
    }
  }
  void importData() async {
    setState(() {
      loading = true;
    });
    await ImportService().exportData();
    setState(() {
      loading = false;
    });
  }
  void logout(context) {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: Text('Salir'),
          content: Text('Se perderán los datos no guardados'),
          actions: <Widget>[
            // Botón para cancelar el cierre de sesión
            TextButton(
              child: Text('Cancelar'),
              onPressed: () {
                Navigator.of(context).pop(); // Cerrar el diálogo
              },
            ),
            // Botón para confirmar el cierre de sesión
            TextButton(
              child: Text('Aceptar'),
              onPressed: () async {
                var almacenBox = await Hive.box<Almacen>('almacen');
                almacenBox.deleteAll(almacenBox.keys);
                var userBox = await Hive.openBox<User>('user');
                userBox.deleteAll(userBox.keys);
                Navigator.of(context).pop(); // Cerrar el diálogo
                Navigator.pop(context); // Cerrar la página actual
              },
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            ElevatedButton(
                onPressed: () {
                  loading ? null :
                  importData();
                },
                child: loading ? CircularProgressIndicator() :
                Row(
                  children: [
                    Icon(Icons.import_export, color: Colors.white,),
                    Text('Exportar', style: TextStyle(color: Colors.white),)
                  ],
                ),
                style: ButtonStyle(
                  backgroundColor: MaterialStateProperty.all(Colors.orange),
                ),
            ),
            Row(
              children: [
                Text(
                  'Almacen',
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                SizedBox(width: 10),
                Container(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(5),
                    color: Colors.purple,
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(2.0),
                    child: Text(dotenv.env['VERSION']!,
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 18,
                      ),
                    ),
                  ),
                )
              ],
            ),
          ],
        ),
        automaticallyImplyLeading: false, // Esto elimina el botón de retroceso
        actions: <Widget>[
          IconButton(
            icon: Icon(
              Icons.logout,
              color: Colors.white,
            ),
            onPressed: () {
              logout(context);
            },
          ),
        ],
      ),
      body: Column(
        children: [
          Container(
            // padding: EdgeInsets.all(10),
            child: Column(
              //centrear
              mainAxisAlignment: MainAxisAlignment.center, // Centra los elementos horizontalmente
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Padding(
                  padding: const EdgeInsets.all(5.0),
                  child: Row(
                    children: [
                      Text('Usuario: ',
                        style: TextStyle(
                          color: Colors.black,
                          fontWeight: FontWeight.w900, // Aquí puedes ajustar el valor de FontWeight
                          fontSize: 13,
                        ),
                      ),
                      Text('${user.name}',
                        style: TextStyle(
                          color: Colors.black,
                          fontSize: 13,
                        ),
                      ),
                      Text(' Codigo: ',
                        style: TextStyle(
                          color: Colors.black,
                          fontWeight: FontWeight.w900, // Aquí puedes ajustar el valor de FontWeight
                          fontSize: 13,
                        ),
                      ),
                      Text(
                        almacen.length > 0 ? almacen[0].codigo.toString() : '',
                        style: TextStyle(
                          color: Colors.black,
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                    width: double.infinity,
                    color: Colors.blueGrey[900],
                    child: Center(
                      child: Text(
                        'Total: ${almacen.length} | Pendientes: ${almacen.where((element) => element.estado == 'PENDIENTE').length} | Registrados: ${almacen.length - almacen.where((element) => element.estado == 'PENDIENTE').length}',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    )
                ),
                //buscador keu upp
                TextField(
                  decoration: InputDecoration(
                    hintText: 'Buscar',
                    prefixIcon: Icon(Icons.search),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  onChanged: (value) {
                    // print(value);
                    setState(() {
                      //filtrar nombre y codigo de producto
                      almacen = almacenAll.where((element) => element.producto!.toLowerCase().contains(value.toLowerCase())).toList();
                      almacen += almacenAll.where((element) => element.codigoProducto!.toLowerCase().contains(value.toLowerCase())).toList();
                      almacen = almacen.toSet().toList();

                    });
                  },
                ),
              ],
            ),
          ),
          Expanded(
            child: ListView.builder(
              itemCount: almacen.length,
              itemBuilder: (context, index) {
                return GestureDetector(
                  onTap: () {
                    // print(almacen[index].id);
                    showDialog(
                      context: context,
                      builder: (BuildContext context) {
                        return AlmacenAlert(
                          almacen: almacen[index],
                          onClick: () {
                            getDatos();
                          },
                        );
                      },
                    );
                  },
                  child: Card(
                    color: getColorForCard(almacen[index]),
                    child: Padding(
                      padding: const EdgeInsets.all(8.0),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.start,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                padding: EdgeInsets.symmetric(horizontal: 3, vertical: 2), // Ajusta el padding del Container
                                decoration: BoxDecoration(
                                  color: almacen[index].estado == 'PENDIENTE' ? Colors.red : Colors.green, // Establece el color de fondo del Container según el estado
                                  borderRadius: BorderRadius.circular(3), // Ajusta el radio de borde del Container
                                ),
                                child: Text(
                                  almacen[index].estado!.trim(),
                                  style: TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.bold,
                                    fontSize: 13, // Ajusta el tamaño del texto del Chip
                                  ),
                                ),
                              ),

                            ],
                          ),
                          Text(
                            (index+1).toString()+' '+almacen[index].producto!.trim()+ ' ' ,style: TextStyle(
                            color: Colors.black,
                            fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
                            fontSize: 13,
                          ),),
                          Row(
                            children: [
                              Text('Codigo: ',style: TextStyle(
                                color: Colors.black,
                                fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
                                fontSize: 13,
                              ),),
                              Text(
                                almacen[index].codigoProducto!.trim()+ ' ' ,style: TextStyle(
                                color: Colors.purple,
                                fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
                                fontSize: 13,
                              ),),
                            ],
                          ),
                          Text('Grupo: ' +almacen[index].grupo!.trim(),style: TextStyle(
                            color: Colors.black,
                            fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
                            fontSize: 13,
                          ),),
                          Row(
                            children: [
                              Text('Saldo: '),
                              Text(almacen[index].saldo.toString(),style: TextStyle(
                                color: Colors.black,
                                fontWeight: FontWeight.w900, // Aquí puedes ajustar el valor de FontWeight
                                fontSize: 13,
                              ),),
                            ],
                          ),
                          Row(
                            children: [
                              Text(' Vencimiento: '),
                              Text(almacen[index].vencimiento?.toString().substring(0,10) ?? '-',style: TextStyle(
                                color: Colors.black,
                                fontWeight: FontWeight.w900, // Aquí puedes ajustar el valor de FontWeight
                                fontSize: 13,
                              ),),
                              Text(' Cantidad: '),
                              Container(
                                decoration: BoxDecoration(
                                  color: double.parse(almacen[index].cantidad.toString()) == double.parse(almacen[index].saldo.toString()) ? Colors.green : Colors.red, // Establece el color de fondo del Container según la cantidad (saldo y cantidad iguales: verde, saldo y cantidad diferentes: rojo
                                  borderRadius: BorderRadius.circular(3),
                                ),
                                child: Padding(
                                  padding: const EdgeInsets.all(3.0),
                                  child: Text(almacen[index].cantidad.toString(),style: TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w900, // Aquí puedes ajustar el valor de FontWeight
                                    fontSize: 13,
                                  ),),
                                ),
                              ),
                              //diferencia
                            ],
                          ),
                          Row(
                            children: [
                              Text('Diferencia: '),
                              Text((almacen[index].saldo! - almacen[index].cantidad!).toString(),style: TextStyle(
                                color: Colors.red ,
                                fontWeight: FontWeight.w900, // Aquí puedes ajustar el valor de FontWeight
                                fontSize: 13,
                              ),),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
