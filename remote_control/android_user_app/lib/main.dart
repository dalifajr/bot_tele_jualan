import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'config/constants.dart';
import 'screens/device_list_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Kunci orientasi ke Portrait & set visual system navigation
  SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.light,
    systemNavigationBarColor: Color(0xFF111622),
    systemNavigationBarIconBrightness: Brightness.light,
  ));

  runApp(const CyberControlMobileApp());
}

class CyberControlMobileApp extends StatelessWidget {
  const CyberControlMobileApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: AppConstants.appTitle,
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        brightness: Brightness.dark,
        scaffoldBackgroundColor: const Color(0xFF0A0D14),
        primaryColor: const Color(0xFF06B6D4),
        colorScheme: const ColorScheme.dark(
          primary: Color(0xFF06B6D4),
          secondary: Color(0xFF10B981),
          surface: Color(0xFF111622),
          error: Color(0xFFF43F5E),
        ),
        fontFamily: 'sans-serif',
      ),
      home: const DeviceListScreen(),
    );
  }
}
