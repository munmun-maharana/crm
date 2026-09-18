import 'package:flutter/material.dart';

import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'services/api.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await ApiClient.instance.loadSession();
  runApp(const PulseCrmApp());
}

class PulseCrmApp extends StatelessWidget {
  const PulseCrmApp({super.key});

  @override
  Widget build(BuildContext context) {
    final loggedIn = ApiClient.instance.token != null;
    return MaterialApp(
      title: 'PulseCRM',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF0F6CBD)),
        useMaterial3: true,
        fontFamily: 'Roboto',
      ),
      home: loggedIn ? const HomeShell() : const LoginScreen(),
    );
  }
}
