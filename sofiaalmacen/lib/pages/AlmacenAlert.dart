import 'dart:convert';

import 'package:appsofia/models/Almacen.dart';
import 'package:appsofia/models/Detalle.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_form_builder/flutter_form_builder.dart';
import 'package:hive/hive.dart';
import 'package:url_launcher/url_launcher.dart';

class AlmacenAlert extends StatefulWidget {
  final almacen;
  final VoidCallback? onClick;

  const AlmacenAlert({
    super.key,
    this.almacen,
    this.onClick,
  });

  @override
  State<AlmacenAlert> createState() => _AlmacenAlertState();
}

class _AlmacenAlertState extends State<AlmacenAlert> {
  var cantidad1 = TextEditingController();
  var vencimiento1 = TextEditingController();
  var cantidad2 = TextEditingController();
  var vencimiento2 = TextEditingController();
  var cantidad3 = TextEditingController();
  var vencimiento3 = TextEditingController();
  var cantidad4 = TextEditingController();
  var vencimiento4 = TextEditingController();
  var cantidad5 = TextEditingController();
  var vencimiento5 = TextEditingController();
  var cantidad6 = TextEditingController();
  var vencimiento6 = TextEditingController();
  var cantidad7 = TextEditingController();
  var vencimiento7 = TextEditingController();
  var cantidad8 = TextEditingController();
  var vencimiento8 = TextEditingController();
  var cantidad9 = TextEditingController();
  var vencimiento9 = TextEditingController();
  var cantidad10 = TextEditingController();
  var vencimiento10 = TextEditingController();
  var lote1 = TextEditingController();
  var lote2 = TextEditingController();
  var lote3 = TextEditingController();
  var lote4 = TextEditingController();
  var lote5 = TextEditingController();
  var lote6 = TextEditingController();
  var lote7 = TextEditingController();
  var lote8 = TextEditingController();
  var lote9 = TextEditingController();
  var lote10 = TextEditingController();
  var comentario1 = TextEditingController();
  var comentario2 = TextEditingController();
  var comentario3 = TextEditingController();
  var comentario4 = TextEditingController();
  var comentario5 = TextEditingController();
  var comentario6 = TextEditingController();
  var comentario7 = TextEditingController();
  var comentario8 = TextEditingController();
  var comentario9 = TextEditingController();
  var comentario10 = TextEditingController();
  double total = 0;
  @override
  void initState() {
    llenado();
    calcularTotal();
    super.initState();
  }
  void llenado() {
    cantidad1.text = widget.almacen.detalle.length > 0 ? widget.almacen.detalle[0].cantidad.toString() : '';
    vencimiento1.text = widget.almacen.detalle.length > 0 ? (widget.almacen.detalle[0].vencimiento ?? '').replaceAll('null', '') : '';
    lote1.text = widget.almacen.detalle.length > 0 ? widget.almacen.detalle[0].lote.toString() : '';
    comentario1.text = widget.almacen.detalle.length > 0 ? widget.almacen.detalle[0].comentario.toString()=='null' ? '' : widget.almacen.detalle[0].comentario.toString() : '';
    cantidad2.text = widget.almacen.detalle.length > 1 ? widget.almacen.detalle[1].cantidad.toString() : '';
    vencimiento2.text = widget.almacen.detalle.length > 1 ? (widget.almacen.detalle[1].vencimiento ?? '').replaceAll('null', '') : '';
    lote2.text = widget.almacen.detalle.length > 1 ? widget.almacen.detalle[1].lote.toString() : '';
    comentario2.text = widget.almacen.detalle.length > 1 ? widget.almacen.detalle[1].comentario.toString()== 'null' ? '' : widget.almacen.detalle[1].comentario.toString() : '';
    cantidad3.text = widget.almacen.detalle.length > 2 ? widget.almacen.detalle[2].cantidad.toString() : '';
    vencimiento3.text = widget.almacen.detalle.length > 2 ? (widget.almacen.detalle[2].vencimiento ?? '').replaceAll('null', '') : '';
    lote3.text = widget.almacen.detalle.length > 2 ? widget.almacen.detalle[2].lote.toString() : '';
    comentario3.text = widget.almacen.detalle.length > 2 ? widget.almacen.detalle[2].comentario.toString()== 'null' ? '' : widget.almacen.detalle[2].comentario.toString() : '';
    cantidad4.text = widget.almacen.detalle.length > 3 ? widget.almacen.detalle[3].cantidad.toString() : '';
    vencimiento4.text = widget.almacen.detalle.length > 3 ? (widget.almacen.detalle[3].vencimiento ?? '').replaceAll('null', '') : '';
    lote4.text = widget.almacen.detalle.length > 3 ? widget.almacen.detalle[3].lote.toString() : '';
    comentario4.text = widget.almacen.detalle.length > 3 ? widget.almacen.detalle[3].comentario.toString()== 'null' ? '' : widget.almacen.detalle[3].comentario.toString() : '';
    cantidad5.text = widget.almacen.detalle.length > 4 ? widget.almacen.detalle[4].cantidad.toString() : '';
    vencimiento5.text = widget.almacen.detalle.length > 4 ? (widget.almacen.detalle[4].vencimiento ?? '').replaceAll('null', '') : '';
    lote5.text = widget.almacen.detalle.length > 4 ? widget.almacen.detalle[4].lote.toString() : '';
    comentario5.text = widget.almacen.detalle.length > 4 ? widget.almacen.detalle[4].comentario.toString()== 'null' ? '' : widget.almacen.detalle[4].comentario.toString() : '';
    cantidad6.text = widget.almacen.detalle.length > 5 ? widget.almacen.detalle[5].cantidad.toString() : '';
    vencimiento6.text = widget.almacen.detalle.length > 5 ? (widget.almacen.detalle[5].vencimiento ?? '').replaceAll('null', '') : '';
    lote6.text = widget.almacen.detalle.length > 5 ? widget.almacen.detalle[5].lote.toString() : '';
    comentario6.text = widget.almacen.detalle.length > 5 ? widget.almacen.detalle[5].comentario.toString()== 'null' ? '' : widget.almacen.detalle[5].comentario.toString() : '';
    cantidad7.text = widget.almacen.detalle.length > 6 ? widget.almacen.detalle[6].cantidad.toString() : '';
    vencimiento7.text = widget.almacen.detalle.length > 6 ? (widget.almacen.detalle[6].vencimiento ?? '').replaceAll('null', '') : '';
    lote7.text = widget.almacen.detalle.length > 6 ? widget.almacen.detalle[6].lote.toString() : '';
    comentario7.text = widget.almacen.detalle.length > 6 ? widget.almacen.detalle[6].comentario.toString()== 'null' ? '' : widget.almacen.detalle[6].comentario.toString() : '';
    cantidad8.text = widget.almacen.detalle.length > 7 ? widget.almacen.detalle[7].cantidad.toString() : '';
    vencimiento8.text = widget.almacen.detalle.length > 7 ? (widget.almacen.detalle[7].vencimiento ?? '').replaceAll('null', '') : '';
    lote8.text = widget.almacen.detalle.length > 7 ? widget.almacen.detalle[7].lote.toString() : '';
    comentario8.text = widget.almacen.detalle.length > 7 ? widget.almacen.detalle[7].comentario.toString()== 'null' ? '' : widget.almacen.detalle[7].comentario.toString() : '';
    cantidad9.text = widget.almacen.detalle.length > 8 ? widget.almacen.detalle[8].cantidad.toString() : '';
    vencimiento9.text = widget.almacen.detalle.length > 8 ? (widget.almacen.detalle[8].vencimiento ?? '').replaceAll('null', '') : '';
    lote9.text = widget.almacen.detalle.length > 8 ? widget.almacen.detalle[8].lote.toString() : '';
    comentario9.text = widget.almacen.detalle.length > 8 ? widget.almacen.detalle[8].comentario.toString()== 'null' ? '' : widget.almacen.detalle[8].comentario.toString() : '';
    cantidad10.text = widget.almacen.detalle.length > 9 ? widget.almacen.detalle[9].cantidad.toString() : '';
    vencimiento10.text = widget.almacen.detalle.length > 9 ? (widget.almacen.detalle[9].vencimiento ?? '').replaceAll('null', '') : '';
    lote10.text = widget.almacen.detalle.length > 9 ? widget.almacen.detalle[9].lote.toString() : '';
    comentario10.text = widget.almacen.detalle.length > 9 ? widget.almacen.detalle[9].comentario.toString()== 'null' ? '' : widget.almacen.detalle[9].comentario.toString() : '';
  }

  void calcularTotal() {
    double sum = 0;
    if (cantidad1.text.isNotEmpty) {
      sum += double.parse(cantidad1.text);
    }
    if (cantidad2.text.isNotEmpty) {
      sum += double.parse(cantidad2.text);
    }
    if (cantidad3.text.isNotEmpty) {
      sum += double.parse(cantidad3.text);
    }
    if (cantidad4.text.isNotEmpty) {
      sum += double.parse(cantidad4.text);
    }
    if (cantidad5.text.isNotEmpty) {
      sum += double.parse(cantidad5.text);
    }
    if (cantidad6.text.isNotEmpty) {
      sum += double.parse(cantidad6.text);
    }
    if (cantidad7.text.isNotEmpty) {
      sum += double.parse(cantidad7.text);
    }
    if (cantidad8.text.isNotEmpty) {
      sum += double.parse(cantidad8.text);
    }
    if (cantidad9.text.isNotEmpty) {
      sum += double.parse(cantidad9.text);
    }
    if (cantidad10.text.isNotEmpty) {
      sum += double.parse(cantidad10.text);
    }
    setState(() {
      total = sum;
    });
  }
  // _launchCalculator() async {
  //   final Uri _url = Uri.parse('https://flutter.dev');
  //   if (await canLaunch(_url.toString())) {
  //     await launch(_url.toString());
  //   } else {
  //     throw 'Could not launch $_url';
  //   }
  // }
  Future<void> _launchCalculator() async {
    final Uri _url = Uri.parse('https://calculadorasonline.com/');
    if (!await launchUrl(_url)) {
      throw Exception('Could not launch $_url');
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Row(
        children: [
          Text('Detalle'),
          SizedBox(width: 5),
          GestureDetector(
            onTap: () {
              _launchCalculator();
            },
            child: Icon(Icons.calculate_outlined),
          ),
        ],
      ),
      content: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  Text(
                    'Producto: ',
                    style: TextStyle(
                      color: Colors.black,
                      fontWeight: FontWeight.bold,
                      fontSize: 12,
                    ),
                  ),
                  // SizedBox(width: 5), // Añade un espacio entre el texto 'Producto:' y el contenido del producto
                ],
              ),
            ),
            Text(
              widget.almacen.producto.toString(),
              style: TextStyle(
                color: Colors.black,
                fontSize: 12,
              ),
            ),
            Row(
              children: [
                Text('Codigo : ',
                  style: TextStyle(
                    color: Colors.black,
                    fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
                    fontSize: 12,
                  ),
                ),
                Text(
                  widget.almacen.codigoProducto.toString(),
                  style: TextStyle(
                    color: Colors.purple,
                    fontSize: 12,
                    fontWeight: FontWeight.bold, // bold
                    // bold
                  ),
                ),
              ],
            ),
            Row(children: [Text('Saldo: ',style: TextStyle(
              color: Colors.black,
              fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
              fontSize: 12,
            ),), Text(widget.almacen.saldo.toString(),style: TextStyle(
              color: Colors.black,
              fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
              fontSize: 20,
            ),),]),
            Row(children: [Text('Unidad x kilo: ',style: TextStyle(
              color: Colors.black,
              fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
              fontSize: 12,
            ),), Text(widget.almacen.unidad.toString(),style: TextStyle(
              color: Colors.black,
              fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
              fontSize: 10,
            ),),]),
            Row(children: [Text('Registro: ',style: TextStyle(
              color: Colors.black,
              fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
              fontSize: 12,
            ),), Text(widget.almacen.registro?.toString().substring(0,10) ?? '-',style: TextStyle(
              color: Colors.black,
              fontSize: 12,
            ),),]),
            Row(children: [Text('Vencimiento: ',style: TextStyle(
              color: Colors.black,
              fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
              fontSize: 12,
            ),), Text(widget.almacen.vencimiento?.toString().substring(0,10) ?? '-',style: TextStyle(
              color: Colors.black,
              fontSize: 12,
            ),),]),
            Row(children: [Text('Grupo: ',style: TextStyle(
              color: Colors.black,
              fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
              fontSize: 12,
            ),), Text(widget.almacen.grupo!.trim(),style: TextStyle(
              color: Colors.black,
              fontSize: 12,
            ),),]),
            Row(children: [Text('Estado: ',style: TextStyle(
              color: Colors.black,
              fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
              fontSize: 12,
            ),), Text(widget.almacen.estado!.trim(),style: TextStyle(
              color: Colors.black,
              fontSize: 12,
            ),),]),
            Row(children: [Text('Cantidad: ',style: TextStyle(
              color: Colors.black,
              fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
              fontSize: 12,
            ),),
              Text(total.toString(),
                style: TextStyle(
                  color: double.parse(widget.almacen.saldo.toString()) == double.parse(total.toString()) ? Colors.green : Colors.red,
                  fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
                  fontSize: 20,
                ),
              ),
            ]
            ),
            Text('Detalle: ',style: TextStyle(
              color: Colors.black,
              fontWeight: FontWeight.bold, // Aquí puedes ajustar el valor de FontWeight
              fontSize: 12,
            ),),
            // Text(widget.almacen.detalle.length > 0 ? widget.almacen.detalle.length.toString() : 'Sin detalle',style: TextStyle(
            //   color: Colors.black,
            //   fontSize: 12,
            // ),),
            Row(
              children: [
                Expanded(
                  child: FormBuilderTextField(
                    name: 'cantidad1',
                    // controller: cantidad1,
                    decoration: InputDecoration(labelText: 'Cantidad1'),
                    initialValue: cantidad1.text,
                    onChanged: (value) {
                      setState(() {
                        cantidad1.text = value!;
                        calcularTotal();
                      });
                    },
                    keyboardType: TextInputType.numberWithOptions(decimal: true), // Asegura que el teclado que se muestra sea numérico
                    inputFormatters: <TextInputFormatter>[
                      FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')) // Acepta solo números con un máximo de dos decimales
                    ],
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderDateTimePicker(
                    name: 'date',
                    // controller: vencimiento1,
                    inputType: InputType.date,
                    decoration: InputDecoration(labelText: 'Fecha1'),
                    initialValue: DateTime.tryParse(vencimiento1.text),
                    locale: const Locale('es', 'ES'),
                    onChanged: (value) {
                      setState(() {
                        vencimiento1.text = value?.toString() ?? '';
                        print(vencimiento1.text);
                        calcularTotal();
                      });
                    },
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderTextField(
                    name: 'comentario1',
                    // controller: comentario1,
                    decoration: InputDecoration(labelText: 'Comentario1'),
                    initialValue: comentario1.text,
                    onChanged: (value) {
                      setState(() {
                        comentario1.text = value!;
                      });
                    },
                  ),
                ),
              ],
            ),
            Row(
              children: [
                Expanded(
                  child: FormBuilderTextField(
                    name: 'cantidad2',
                    // controller: cantidad2,
                    decoration: InputDecoration(labelText: 'Cantidad2'),
                    initialValue: cantidad2.text,
                    onChanged: (value) {
                      setState(() {
                        cantidad2.text = value!;
                        calcularTotal();
                      });
                    },
                    keyboardType: TextInputType.numberWithOptions(decimal: true), // Asegura que el teclado que se muestra sea numérico
                    inputFormatters: <TextInputFormatter>[
                      FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')) // Acepta solo números con un máximo de dos decimales
                    ],
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderDateTimePicker(
                    name: 'date',
                    // controller: vencimiento2,
                    inputType: InputType.date,
                    decoration: InputDecoration(labelText: 'Fecha2'),
                    initialValue: DateTime.tryParse(vencimiento2.text),
                    locale: const Locale('es', 'ES'),
                    onChanged: (value) {
                      setState(() {
                        vencimiento2.text = value?.toString() ?? '';
                        calcularTotal();
                      });
                    },
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderTextField(
                    name: 'comentario2',
                    // controller: comentario2,
                    decoration: InputDecoration(labelText: 'Comentario2'),
                    initialValue: comentario2.text,
                    onChanged: (value) {
                      setState(() {
                        comentario2.text = value!;
                      });
                    },
                  ),
                ),
              ],
            ),
            Row(
              children: [
                Expanded(
                  child: FormBuilderTextField(
                    name: 'cantidad3',
                    // controller: cantidad3,
                    decoration: InputDecoration(labelText: 'Cantidad3'),
                    initialValue: cantidad3.text,
                    onChanged: (value) {
                      setState(() {
                        cantidad3.text = value!;
                        calcularTotal();
                      });
                    },
                    keyboardType: TextInputType.numberWithOptions(decimal: true), // Asegura que el teclado que se muestra sea numérico
                    inputFormatters: <TextInputFormatter>[
                      FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')) // Acepta solo números con un máximo de dos decimales
                    ],
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderDateTimePicker(
                    name: 'date',
                    // controller: vencimiento3,
                    inputType: InputType.date,
                    decoration: InputDecoration(labelText: 'Fecha3'),
                    initialValue: DateTime.tryParse(vencimiento3.text),
                    locale: const Locale('es', 'ES'),
                    onChanged: (value) {
                      setState(() {
                        vencimiento3.text = value?.toString() ?? '';
                        calcularTotal();
                      });
                    },
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderTextField(
                    name: 'comentario3',
                    // controller: comentario3,
                    decoration: InputDecoration(labelText: 'Comentario3'),
                    initialValue: comentario3.text,
                    onChanged: (value) {
                      setState(() {
                        comentario3.text = value!;
                      });
                    },
                  ),
                ),
              ],
            ),
            Row(
              children: [
                Expanded(
                  child: FormBuilderTextField(
                    name: 'cantidad4',
                    // controller: cantidad4,
                    decoration: InputDecoration(labelText: 'Cantidad4'),
                    initialValue: cantidad4.text,
                    onChanged: (value) {
                      setState(() {
                        cantidad4.text = value!;
                        calcularTotal();
                      });
                    },
                    keyboardType: TextInputType.numberWithOptions(decimal: true), // Asegura que el teclado que se muestra sea numérico
                    inputFormatters: <TextInputFormatter>[
                      FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')) // Acepta solo números con un máximo de dos decimales
                    ],
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderDateTimePicker(
                    name: 'date',
                    // controller: vencimiento4,
                    inputType: InputType.date,
                    decoration: InputDecoration(labelText: 'Fecha4'),
                    initialValue: DateTime.tryParse(vencimiento4.text),
                    locale: const Locale('es', 'ES'),
                    onChanged: (value) {
                      setState(() {
                        vencimiento4.text = value?.toString() ?? '';
                        calcularTotal();
                      });
                    },
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderTextField(
                    name: 'comentario4',
                    // controller: comentario4,
                    decoration: InputDecoration(labelText: 'Comentario4'),
                    initialValue: comentario4.text,
                    onChanged: (value) {
                      setState(() {
                        comentario4.text = value!;
                      });
                    },
                  ),
                ),
              ],
            ),
            Row(
              children: [
                Expanded(
                  child: FormBuilderTextField(
                    name: 'cantidad5',
                    // controller: cantidad5,
                    decoration: InputDecoration(labelText: 'Cantidad5'),
                    initialValue: cantidad5.text,
                    onChanged: (value) {
                      setState(() {
                        cantidad5.text = value!;
                        calcularTotal();
                      });
                    },
                    keyboardType: TextInputType.numberWithOptions(decimal: true), // Asegura que el teclado que se muestra sea numérico
                    inputFormatters: <TextInputFormatter>[
                      FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')) // Acepta solo números con un máximo de dos decimales
                    ],
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderDateTimePicker(
                    name: 'date',
                    // controller: vencimiento5,
                    inputType: InputType.date,
                    decoration: InputDecoration(labelText: 'Fecha5'),
                    initialValue: DateTime.tryParse(vencimiento5.text),
                    locale: const Locale('es', 'ES'),
                    onChanged: (value) {
                      setState(() {
                        vencimiento5.text = value?.toString() ?? '';
                        calcularTotal();
                      });
                    },
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderTextField(
                    name: 'comentario5',
                    // controller: comentario5,
                    decoration: InputDecoration(labelText: 'Comentario5'),
                    initialValue: comentario5.text,
                    onChanged: (value) {
                      setState(() {
                        comentario5.text = value!;
                      });
                    },
                  ),
                ),
              ],
            ),
            Row(
              children: [
                Expanded(
                  child: FormBuilderTextField(
                    name: 'cantidad6',
                    // controller: cantidad6,
                    decoration: InputDecoration(labelText: 'Cantidad6'),
                    initialValue: cantidad6.text,
                    onChanged: (value) {
                      setState(() {
                        cantidad6.text = value!;
                        calcularTotal();
                      });
                    },
                    keyboardType: TextInputType.numberWithOptions(decimal: true), // Asegura que el teclado que se muestra sea numérico
                    inputFormatters: <TextInputFormatter>[
                      FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')) // Acepta solo números con un máximo de dos decimales
                    ],
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderDateTimePicker(
                    name: 'date',
                    // controller: vencimiento6,
                    inputType: InputType.date,
                    decoration: InputDecoration(labelText: 'Fecha6'),
                    initialValue: DateTime.tryParse(vencimiento6.text),
                    locale: const Locale('es', 'ES'),
                    onChanged: (value) {
                      setState(() {
                        vencimiento6.text = value?.toString() ?? '';
                        calcularTotal();
                      });
                    },
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderTextField(
                    name: 'comentario6',
                    // controller: comentario6,
                    decoration: InputDecoration(labelText: 'Comentario6'),
                    initialValue: comentario6.text,
                    onChanged: (value) {
                      setState(() {
                        comentario6.text = value!;
                      });
                    },
                  ),
                ),
              ],
            ),
            Row(
              children: [
                Expanded(
                  child: FormBuilderTextField(
                    name: 'cantidad7',
                    // controller: cantidad7,
                    decoration: InputDecoration(labelText: 'Cantidad7'),
                    initialValue: cantidad7.text,
                    onChanged: (value) {
                      setState(() {
                        cantidad7.text = value!;
                        calcularTotal();
                      });
                    },
                    keyboardType: TextInputType.numberWithOptions(decimal: true), // Asegura que el teclado que se muestra sea numérico
                    inputFormatters: <TextInputFormatter>[
                      FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')) // Acepta solo números con un máximo de dos decimales
                    ],
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderDateTimePicker(
                    name: 'date',
                    // controller: vencimiento7,
                    inputType: InputType.date,
                    decoration: InputDecoration(labelText: 'Fecha7'),
                    initialValue: DateTime.tryParse(vencimiento7.text),
                    locale: const Locale('es', 'ES'),
                    onChanged: (value) {
                      setState(() {
                        vencimiento7.text = value?.toString() ?? '';
                        calcularTotal();
                      });
                    },
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderTextField(
                    name: 'comentario7',
                    // controller: comentario7,
                    decoration: InputDecoration(labelText: 'Comentario7'),
                    initialValue: comentario7.text,
                    onChanged: (value) {
                      setState(() {
                        comentario7.text = value!;
                      });
                    },
                  ),
                ),
              ],
            ),
            Row(
              children: [
                Expanded(
                  child: FormBuilderTextField(
                    name: 'cantidad8',
                    // controller: cantidad8,
                    decoration: InputDecoration(labelText: 'Cantidad8'),
                    initialValue: cantidad8.text,
                    onChanged: (value) {
                      setState(() {
                        cantidad8.text = value!;
                        calcularTotal();
                      });
                    },
                    keyboardType: TextInputType.numberWithOptions(decimal: true), // Asegura que el teclado que se muestra sea numérico
                    inputFormatters: <TextInputFormatter>[
                      FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')) // Acepta solo números con un máximo de dos decimales
                    ],
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderDateTimePicker(
                    name: 'date',
                    // controller: vencimiento8,
                    inputType: InputType.date,
                    decoration: InputDecoration(labelText: 'Fecha8'),
                    initialValue: DateTime.tryParse(vencimiento8.text),
                    locale: const Locale('es', 'ES'),
                    onChanged: (value) {
                      setState(() {
                        vencimiento8.text = value?.toString() ?? '';
                        calcularTotal();
                      });
                    },
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderTextField(
                    name: 'comentario8',
                    // controller: comentario8,
                    decoration: InputDecoration(labelText: 'Comentario8'),
                    initialValue: comentario8.text,
                    onChanged: (value) {
                      setState(() {
                        comentario8.text = value!;
                      });
                    },
                  ),
                ),
              ],
            ),
            Row(
              children: [
                Expanded(
                  child: FormBuilderTextField(
                    name: 'cantidad9',
                    // controller: cantidad9,
                    decoration: InputDecoration(labelText: 'Cantidad9'),
                    initialValue: cantidad9.text,
                    onChanged: (value) {
                      setState(() {
                        cantidad9.text = value!;
                        calcularTotal();
                      });
                    },
                    keyboardType: TextInputType.numberWithOptions(decimal: true), // Asegura que el teclado que se muestra sea numérico
                    inputFormatters: <TextInputFormatter>[
                      FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')) // Acepta solo números con un máximo de dos decimales
                    ],
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderDateTimePicker(
                    name: 'date',
                    // controller: vencimiento9,
                    inputType: InputType.date,
                    decoration: InputDecoration(labelText: 'Fecha9'),
                    initialValue: DateTime.tryParse(vencimiento9.text),
                    locale: const Locale('es', 'ES'),
                    onChanged: (value) {
                      setState(() {
                        vencimiento9.text = value?.toString() ?? '';
                        calcularTotal();
                      });
                    },
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderTextField(
                    name: 'comentario9',
                    // controller: comentario9,
                    decoration: InputDecoration(labelText: 'Comentario9'),
                    initialValue: comentario9.text,
                    onChanged: (value) {
                      setState(() {
                        comentario9.text = value!;
                      });
                    },
                  ),
                ),
              ],
            ),
            Row(
              children: [
                Expanded(
                  child: FormBuilderTextField(
                    name: 'cantidad10',
                    // controller: cantidad10,
                    decoration: InputDecoration(labelText: 'Cantidad10'),
                    initialValue: cantidad10.text,
                    onChanged: (value) {
                      setState(() {
                        cantidad10.text = value!;
                        calcularTotal();
                      });
                    },
                    keyboardType: TextInputType.numberWithOptions(decimal: true), // Asegura que el teclado que se muestra sea numérico
                    inputFormatters: <TextInputFormatter>[
                      FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')) // Acepta solo números con un máximo de dos decimales
                    ],
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderDateTimePicker(
                    name: 'date',
                    // controller: vencimiento10,
                    inputType: InputType.date,
                    decoration: InputDecoration(labelText: 'Fecha10'),
                    initialValue: DateTime.tryParse(vencimiento10.text),
                    locale: const Locale('es', 'ES'),
                    onChanged: (value) {
                      setState(() {
                        vencimiento10.text = value?.toString() ?? '';
                        calcularTotal();
                      });
                    },
                  ),
                ),
                SizedBox(width: 5),
                Expanded(
                  child: FormBuilderTextField(
                    name: 'comentario10',
                    // controller: comentario10,
                    decoration: InputDecoration(labelText: 'Comentario10'),
                    initialValue: comentario10.text,
                    onChanged: (value) {
                      setState(() {
                        comentario10.text = value!;
                      });
                    },
                  ),
                ),
              ],
            ),
            // Row(
            //   children: [
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'lote1',
            //         controller: lote1,
            //         decoration: InputDecoration(labelText: 'Lote1'),
            //         // initialValue: lote1.text,
            //         onChanged: (value) {
            //           setState(() {
            //             lote1.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //     SizedBox(width: 5),
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'comentario1',
            //         // controller: comentario1,
            //         decoration: InputDecoration(labelText: 'Comentario1'),
            //         initialValue: comentario1.text,
            //         onChanged: (value) {
            //           setState(() {
            //             comentario1.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //   ],
            // ),
            // Row(
            //   children: [
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'lote2',
            //         controller: lote2,
            //         decoration: InputDecoration(labelText: 'Lote2'),
            //         // initialValue: lote2.text,
            //         onChanged: (value) {
            //           setState(() {
            //             lote2.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //     SizedBox(width: 5),
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'comentario2',
            //         // controller: comentario2,
            //         decoration: InputDecoration(labelText: 'Comentario2'),
            //         initialValue: comentario2.text,
            //         onChanged: (value) {
            //           setState(() {
            //             comentario2.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //   ],
            // ),
            // Row(
            //   children: [
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'lote3',
            //         controller: lote3,
            //         decoration: InputDecoration(labelText: 'Lote3'),
            //         // initialValue: lote3.text,
            //         onChanged: (value) {
            //           setState(() {
            //             lote3.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //     SizedBox(width: 5),
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'comentario3',
            //         // controller: comentario3,
            //         decoration: InputDecoration(labelText: 'Comentario3'),
            //         initialValue: comentario3.text,
            //         onChanged: (value) {
            //           setState(() {
            //             comentario3.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //   ],
            // ),
            // Row(
            //   children: [
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'lote4',
            //         controller: lote4,
            //         decoration: InputDecoration(labelText: 'Lote4'),
            //         // initialValue: lote4.text,
            //         onChanged: (value) {
            //           setState(() {
            //             lote4.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //     SizedBox(width: 5),
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'comentario4',
            //         // controller: comentario4,
            //         decoration: InputDecoration(labelText: 'Comentario4'),
            //         initialValue: comentario4.text,
            //         onChanged: (value) {
            //           setState(() {
            //             comentario4.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //   ],
            // ),
            // Row(
            //   children: [
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'lote5',
            //         controller: lote5,
            //         decoration: InputDecoration(labelText: 'Lote5'),
            //         // initialValue: lote5.text,
            //         onChanged: (value) {
            //           setState(() {
            //             lote5.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //     SizedBox(width: 5),
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'comentario5',
            //         // controller: comentario5,
            //         decoration: InputDecoration(labelText: 'Comentario5'),
            //         initialValue: comentario5.text,
            //         onChanged: (value) {
            //           setState(() {
            //             comentario5.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //   ],
            // ),
            // Row(
            //   children: [
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'lote6',
            //         controller: lote6,
            //         decoration: InputDecoration(labelText: 'Lote6'),
            //         // initialValue: lote6.text,
            //         onChanged: (value) {
            //           setState(() {
            //             lote6.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //     SizedBox(width: 5),
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'comentario6',
            //         // controller: comentario6,
            //         decoration: InputDecoration(labelText: 'Comentario6'),
            //         initialValue: comentario6.text,
            //         onChanged: (value) {
            //           setState(() {
            //             comentario6.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //   ],
            // ),
            // Row(
            //   children: [
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'lote7',
            //         controller: lote7,
            //         decoration: InputDecoration(labelText: 'Lote7'),
            //         // initialValue: lote7.text,
            //         onChanged: (value) {
            //           setState(() {
            //             lote7.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //     SizedBox(width: 5),
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'comentario7',
            //         // controller: comentario7,
            //         decoration: InputDecoration(labelText: 'Comentario7'),
            //         initialValue: comentario7.text,
            //         onChanged: (value) {
            //           setState(() {
            //             comentario7.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //   ],
            // ),
            // Row(
            //   children: [
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'lote8',
            //         controller: lote8,
            //         decoration: InputDecoration(labelText: 'Lote8'),
            //         // initialValue: lote8.text,
            //         onChanged: (value) {
            //           setState(() {
            //             lote8.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //     SizedBox(width: 5),
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'comentario8',
            //         // controller: comentario8,
            //         decoration: InputDecoration(labelText: 'Comentario8'),
            //         initialValue: comentario8.text,
            //         onChanged: (value) {
            //           setState(() {
            //             comentario8.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //   ],
            // ),
            // Row(
            //   children: [
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'lote9',
            //         controller: lote9,
            //         decoration: InputDecoration(labelText: 'Lote9'),
            //         // initialValue: lote9.text,
            //         onChanged: (value) {
            //           setState(() {
            //             lote9.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //     SizedBox(width: 5),
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'comentario9',
            //         // controller: comentario9,
            //         decoration: InputDecoration(labelText: 'Comentario9'),
            //         initialValue: comentario9.text,
            //         onChanged: (value) {
            //           setState(() {
            //             comentario9.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //   ],
            // ),
            // Row(
            //   children: [
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'lote10',
            //         controller: lote10,
            //         decoration: InputDecoration(labelText: 'Lote10'),
            //         // initialValue: lote10.text,
            //         onChanged: (value) {
            //           setState(() {
            //             lote10.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //     SizedBox(width: 5),
            //     Expanded(
            //       child: FormBuilderTextField(
            //         name: 'comentario10',
            //         // controller: comentario10,
            //         decoration: InputDecoration(labelText: 'Comentario10'),
            //         initialValue: comentario10.text,
            //         onChanged: (value) {
            //           setState(() {
            //             comentario10.text = value!;
            //           });
            //         },
            //       ),
            //     ),
            //   ],
            // ),
          ],
        ),
      ),
      actions: <Widget>[
        Container(
          child: Padding(
            padding: const EdgeInsets.all(10.0),
            child: TextButton(
              onPressed: () async {
                var almacenBox = await Hive.openBox<Almacen>('almacen');
                // this.id, this.codigo, this.codigoProducto, this.producto, this.unidad, this.saldo, this.registro, this.vencimiento, this.grupo, this.estado, this.cantidad, this.detalle
                var almacenInsert=Almacen(
                  id: widget.almacen.id,
                  codigo: widget.almacen.codigo,
                  codigoProducto: widget.almacen.codigoProducto,
                  producto: widget.almacen.producto,
                  unidad: widget.almacen.unidad,
                  saldo: widget.almacen.saldo,
                  registro: widget.almacen.registro,
                  vencimiento: widget.almacen.vencimiento,
                  grupo: widget.almacen.grupo,
                  estado: 'REALIZADO',
                  cantidad: total,
                  detalle: [
                    Detalle(cantidad: cantidad1.text, vencimiento: vencimiento1.text, lote: lote1.text, comentario: comentario1.text),
                    Detalle(cantidad: cantidad2.text, vencimiento: vencimiento2.text, lote: lote2.text, comentario: comentario2.text),
                    Detalle(cantidad: cantidad3.text, vencimiento: vencimiento3.text, lote: lote3.text, comentario: comentario3.text),
                    Detalle(cantidad: cantidad4.text, vencimiento: vencimiento4.text, lote: lote4.text, comentario: comentario4.text),
                    Detalle(cantidad: cantidad5.text, vencimiento: vencimiento5.text, lote: lote5.text, comentario: comentario5.text),
                    Detalle(cantidad: cantidad6.text, vencimiento: vencimiento6.text, lote: lote6.text, comentario: comentario6.text),
                    Detalle(cantidad: cantidad7.text, vencimiento: vencimiento7.text, lote: lote7.text, comentario: comentario7.text),
                    Detalle(cantidad: cantidad8.text, vencimiento: vencimiento8.text, lote: lote8.text, comentario: comentario8.text),
                    Detalle(cantidad: cantidad9.text, vencimiento: vencimiento9.text, lote: lote9.text, comentario: comentario9.text),
                    Detalle(cantidad: cantidad10.text, vencimiento: vencimiento10.text, lote: lote10.text, comentario: comentario10.text),
                  ],
                );
                almacenBox.put( widget.almacen.id, almacenInsert);
                // print(jsonEncode(almacenInsert.toJson()));
                widget.onClick!();
                Navigator.of(context).pop(); // Cerrar el diálogo
              },
              style: TextButton.styleFrom(
                backgroundColor: Colors.red, // Color de fondo del botón
                // primary: Colors.white, // Color del texto del botón
                minimumSize: Size(double.infinity, 60.0), // Ancho completo y altura de 60.0
              ),
              child: Text(
                'Aceptar',
                style: TextStyle(fontSize: 20.0,color: Colors.white,fontWeight: FontWeight.bold),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
