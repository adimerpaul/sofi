import 'package:appsofia/models/Almacen.dart';
import 'package:appsofia/models/User.dart';
import 'package:appsofia/pages/AlmacenPage.dart';
import 'package:appsofia/services/ImportService.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter_form_builder/flutter_form_builder.dart';
import 'package:form_builder_validators/form_builder_validators.dart';
import 'package:hive/hive.dart';
import 'package:appsofia/addons/SnackbarHelper.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  final _fechaController = TextEditingController();
  final _codigo = TextEditingController();
  final _user = TextEditingController();
  var _users = [];
  var _loading = false;
  @override
  void initState() {
    super.initState();
    getUsers();
    // verifiLogin();
  }
  void verifiLogin() async {
    var userBox = await Hive.openBox<User>('user');
    var user = userBox.get(1);
    if(user != null){
      Navigator.push(
        context,
        MaterialPageRoute(builder: (context) => AlmacenPage()),
      );
    }
  }

  void getUsers() async {
    await ImportService().users();
    var usersBox = await Hive.openBox<User>('users');
    setState(() {
      _users = usersBox.values.toList();
    });
    var almacenBox = await Hive.openBox<Almacen>('almacen');
    if(almacenBox.isNotEmpty){
      Navigator.push(
        context,
        MaterialPageRoute(builder: (context) => AlmacenPage()),
      );
    }
  }
  Future importData() async {
    if (_fechaController.text.isEmpty || _codigo.text.isEmpty || _user.text.isEmpty) {
      showErrorSnackBar(context, 'Todos los campos son requeridos');
      return;
    }
    final connectivityResult = await (Connectivity().checkConnectivity());
    if (connectivityResult == ConnectivityResult.none) {
      showErrorSnackBar(context, 'No hay conexión a internet');
      return;
    }
    setState(() {
      _loading = true;
    });
    print('fecha: ${_fechaController.text}');
    print('codigo: ${_codigo.text}');
    print('user: ${_user.text}');
    await ImportService().importData(_fechaController.text, _codigo.text, _user.text);
    setState(() {
      _loading = false;
    });
    var almacenBox = await Hive.openBox<Almacen>('almacen');
    if (almacenBox.isNotEmpty) {
      showSuccessSnackBar(context, 'Datos importados correctamente');
      if(almacenBox.length > 0){
        Navigator.push(
          context,
          MaterialPageRoute(builder: (context) => AlmacenPage()),
        );
      }
    } else {
      showErrorSnackBar(context, 'No se encontraron datos');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
            'Importar y Datos',
            style: TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.bold,
            ),
        ),
        centerTitle: true,
      ),
      body: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                'Almacen sofia',
                style: TextStyle(
                  color: Colors.red,
                  fontWeight: FontWeight.bold,
                  fontSize: 18,
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
          SizedBox(
            height: 100,
            width: 100,
            child: Image.asset('assets/logo.png'),
          ),
          Padding(
            padding: const EdgeInsets.all(10.0),
            child:  FormBuilderDateTimePicker(
              name: 'date',
              controller: _fechaController,
              inputType: InputType.date,
              decoration: InputDecoration(labelText: 'Fecha'),
              initialValue: DateTime.now(),
              locale: const Locale('es', 'ES'),
              onChanged: (value) {
                setState(() {
                  _fechaController.text = value.toString();
                });
              },
            ),
          ),
          Text('fecha: ${_fechaController.text}'),
          Padding(
            padding: const EdgeInsets.all(10.0),
            child: FormBuilderDropdown(
              name: 'codigo',
              decoration: InputDecoration(labelText: 'Código'),
              items: ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']
                  .map((codigo) => DropdownMenuItem(
                value: codigo,
                child: Text('$codigo'),
              )).toList(),
              onChanged: (value) {
                setState(() {
                  _codigo.text = value.toString();
                });
              },
            ),
          ),
          Text('codigo: ${_codigo.text}'),
          //users
          Padding(
            padding: const EdgeInsets.all(10.0),
            child: FormBuilderDropdown(
              name: 'user',
              decoration: InputDecoration(labelText: 'Usuario'),
              items: _users
                .map((user) => DropdownMenuItem(
                  value: user.id,
                  child: Text('${user.name}'),
                )).toList(),
              onChanged: (value) {
                setState(() {
                  _user.text = value.toString();
                });
              },
            ),
          ),
          Text('user: ${_user.text}'),
          Padding(
            padding: const EdgeInsets.all(10.0),
            child: SizedBox(
              width: double.infinity, // Ancho completo
              height: 60.0, // Altura deseada
              child: ElevatedButton(
                onPressed: _loading ? null : importData,
                child: _loading ? CircularProgressIndicator() : Text('Importar', style: TextStyle(color: Colors.white,fontWeight: FontWeight.bold,fontSize: 18)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.red,
                  // onSurface: Colors.grey,
                ),
              ),
            ),
          ),
          SizedBox(height: 15),
          Padding(
            padding: const EdgeInsets.all(10.0),
            child: SizedBox(
              width: double.infinity, // Ancho completo
              height: 60.0, // Altura deseada
              child: ElevatedButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (context) => AlmacenPage()),
                  );
                },
                child: Text('Ver Almacen', style: TextStyle(color: Colors.white,fontWeight: FontWeight.bold,fontSize: 18)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.green,
                  // onSurface: Colors.grey,
                ),
              ),
            ),
          ),
        ]
      ),
    );
  }
}
