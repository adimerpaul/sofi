import 'package:appsofia/models/Almacen.dart';
import 'package:appsofia/models/Detalle.dart';
import 'package:appsofia/models/User.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:hive/hive.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;

class ImportService {
  static apiURL() {
    return dotenv.env['API_BACK'];
  }

  Future users() async {
    var usersBox = await Hive.openBox<User>('users');
    // await usersBox.put(1,User(id: 1, name: 'Juan'));
    if (usersBox.isEmpty) {
      // print('AAA');
      // print(apiURL()+ 'users');
      // print(Uri.parse(apiURL() + 'users'));
      var response = await http.get(Uri.parse(apiURL() + 'users'));
      if (response.statusCode == 200) {
        var data = json.decode(response.body);
        data.forEach((user) async {
          // print(user['id']);
          await usersBox.put(user['id'], User(id: user['id'], name: user['nombre']));
        });
      }
    }
  }
  Future importData(fecha,codigo,user) async {
    var usersBox = await Hive.openBox<User>('users');
    var userSearch = usersBox.get(int.parse(user));
    var userBox = await Hive.openBox<User>('user');
    userBox.put(1, userSearch!);
    // print(userSearch);
    var almacenBox = await Hive.openBox<Almacen>('almacen');
    almacenBox.deleteAll(almacenBox.keys);
    var uri = Uri.parse(apiURL() + 'importData');
    var response = await http.post(uri, body: {
      'fecha': fecha,
      'codigo': codigo,
    });
    print(response.body);
    if (response.statusCode == 200) {
      var data = json.decode(response.body);
      data.forEach((almacen) async {
        print(almacen['saldo']);
        await almacenBox.put(almacen['id'], Almacen(
          id: almacen['id'],
          codigo: almacen['codigo']?.toString() ?? '',
          codigoProducto: almacen['codigo_producto']?.toString() ?? '',
          producto: almacen['producto']?.toString() ?? '',
          unidad: almacen['unidad']?.toString() ?? '',
          saldo: double.tryParse(almacen['saldo'].toString()) ?? 0,
          registro: almacen['registro'] == null ? null : DateTime.tryParse(almacen['registro'].toString()),
          vencimiento: almacen['vencimiento'] == null ? null : DateTime.tryParse(almacen['vencimiento'].toString()),
          grupo: almacen['grupo']?.toString() ?? '',
          estado: 'PENDIENTE',
          detalle: [
            Detalle(cantidad: '', vencimiento: ''),
            Detalle(cantidad: '', vencimiento: ''),
            Detalle(cantidad: '', vencimiento: ''),
            Detalle(cantidad: '', vencimiento: ''),
            Detalle(cantidad: '', vencimiento: ''),
            Detalle(cantidad: '', vencimiento: ''),
            Detalle(cantidad: '', vencimiento: ''),
            Detalle(cantidad: '', vencimiento: ''),
            Detalle(cantidad: '', vencimiento: ''),
            Detalle(cantidad: '', vencimiento: ''),
          ],
          cantidad: 0
        ));
      });
    }
  }
  Future exportData() async {
    var almacenBox = await Hive.openBox<Almacen>('almacen');
    var userBox = await Hive.openBox<User>('user');
    var uri = Uri.parse(apiURL() + 'exportData');
    print(json.encode(almacenBox.values.toList()));
    var response = await http.post(uri, body: {
      'user': userBox.get(1)!.id.toString(),
      'almacen': json.encode(almacenBox.values.toList())
    });
    if (response.statusCode == 200) {
      var data = json.decode(response.body);
      // print(data);
    }
  }
}