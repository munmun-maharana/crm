import 'package:flutter/material.dart';
import 'config.dart';
import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'services/api_service.dart';
import 'theme/app_theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await AppConfig.instance.init();
  await ApiService.instance.loadSession();
  runApp(const PulseCrmApp());
}

class PulseCrmApp extends StatelessWidget {
  const PulseCrmApp({super.key});

  @override
  Widget build(BuildContext context) {
    final loggedIn = ApiService.instance.isAuthenticated;
    return MaterialApp(
      title: 'PulseCRM',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      home: loggedIn ? const HomeShell() : const LoginScreen(),
    );
  }
}
