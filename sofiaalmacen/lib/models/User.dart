import 'package:hive/hive.dart';

part 'User.g.dart';

@HiveType(typeId: 0)
class User {
  @HiveField(0)
  int id;
  @HiveField(1)
  String? name;
  User({required this.id, this.name});
}