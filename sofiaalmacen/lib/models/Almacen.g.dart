// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'Almacen.dart';

// **************************************************************************
// TypeAdapterGenerator
// **************************************************************************

class AlmacenAdapter extends TypeAdapter<Almacen> {
  @override
  final int typeId = 1;

  @override
  Almacen read(BinaryReader reader) {
    final numOfFields = reader.readByte();
    final fields = <int, dynamic>{
      for (int i = 0; i < numOfFields; i++) reader.readByte(): reader.read(),
    };
    return Almacen(
      id: fields[0] as int,
      codigo: fields[1] as String?,
      codigoProducto: fields[2] as String?,
      producto: fields[3] as String?,
      unidad: fields[4] as String?,
      saldo: fields[5] as double?,
      registro: fields[6] as DateTime?,
      vencimiento: fields[7] as DateTime?,
      grupo: fields[8] as String?,
      estado: fields[9] as String?,
      cantidad: fields[10] as double?,
      detalle: (fields[11] as List?)?.cast<Detalle>(),
    );
  }

  @override
  void write(BinaryWriter writer, Almacen obj) {
    writer
      ..writeByte(12)
      ..writeByte(0)
      ..write(obj.id)
      ..writeByte(1)
      ..write(obj.codigo)
      ..writeByte(2)
      ..write(obj.codigoProducto)
      ..writeByte(3)
      ..write(obj.producto)
      ..writeByte(4)
      ..write(obj.unidad)
      ..writeByte(5)
      ..write(obj.saldo)
      ..writeByte(6)
      ..write(obj.registro)
      ..writeByte(7)
      ..write(obj.vencimiento)
      ..writeByte(8)
      ..write(obj.grupo)
      ..writeByte(9)
      ..write(obj.estado)
      ..writeByte(10)
      ..write(obj.cantidad)
      ..writeByte(11)
      ..write(obj.detalle);
  }

  @override
  int get hashCode => typeId.hashCode;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is AlmacenAdapter &&
          runtimeType == other.runtimeType &&
          typeId == other.typeId;
}
