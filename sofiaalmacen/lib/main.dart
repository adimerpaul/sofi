import 'package:appsofia/models/Almacen.dart';
import 'package:appsofia/models/Detalle.dart';
import 'package:appsofia/models/User.dart';
import 'package:appsofia/pages/HomePage.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:hive_flutter/adapters.dart';


Future<void> main() async {
  // debug -> .env (API local), release -> .env.production (bsofia.tuprogam.com)
  await dotenv.load(fileName: kReleaseMode ? ".env.production" : ".env");

  await Hive.initFlutter();
  Hive.registerAdapter(UserAdapter());
  Hive.registerAdapter(AlmacenAdapter());
  Hive.registerAdapter(DetalleAdapter());

  runApp(const MyApp());
}

class MyApp extends StatefulWidget {
  const MyApp({super.key});

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {

  @override
  Widget build(BuildContext context) {
    final Color myCustomColor = Color(0xFFED1C24);

    return MaterialApp(
      title: 'Sofia App',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        fontFamily: GoogleFonts.montserrat().fontFamily,
        appBarTheme: AppBarTheme(
          color: myCustomColor,
        ),
      ),
      home: HomePage(),
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      supportedLocales: const [
        Locale('es', 'ES'),
      ],
    );
  }
}
