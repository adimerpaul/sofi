import 'package:hive_flutter/adapters.dart';
part 'Detalle.g.dart';

@HiveType(typeId: 2)
class Detalle {
  @HiveField(0)
  String? cantidad;
  @HiveField(1)
  String? vencimiento;
  @HiveField(2)
  String? lote;
  @HiveField(3)
  String? comentario;
  Detalle({this.cantidad, this.vencimiento, this.lote, this.comentario});
  Map<String, dynamic> toJson() {
    return {
      'cantidad': cantidad,
      'vencimiento': vencimiento,
      'lote': lote,
      'comentario': comentario,
    };
  }
}