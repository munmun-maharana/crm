import 'package:flutter/material.dart';

class AppTheme {
  AppTheme._();

  static const Color primary = Color(0xFF0F6CBD);
  static const Color primaryDark = Color(0xFF0B4A82);
  static const Color primarySoft = Color(0xFFEBF3FC);

  // Soft tints for sections and status pills matching the website
  static const Color skySoft = Color(0xFFE0F2FE);
  static const Color skyBorder = Color(0xFFBAE6FD);
  static const Color skyText = Color(0xFF0369A1);

  static const Color indigoSoft = Color(0xFFEEF2FF);
  static const Color indigoBorder = Color(0xFFC7D2FE);
  static const Color indigoText = Color(0xFF4338CA);

  static const Color tealSoft = Color(0xFFE6FFFA);
  static const Color tealBorder = Color(0xFF99F6E4);
  static const Color tealText = Color(0xFF0F766E);

  static const Color violetSoft = Color(0xFFF3E8FF);
  static const Color violetBorder = Color(0xFFDDD6FE);
  static const Color violetText = Color(0xFF6D28D9);

  static const Color amberSoft = Color(0xFFFEF3C7);
  static const Color amberBorder = Color(0xFFFDE68A);
  static const Color amberText = Color(0xFFB45309);

  static const Color emeraldSoft = Color(0xFFECFDF5);
  static const Color emeraldBorder = Color(0xFFA7F3D0);
  static const Color emeraldText = Color(0xFF047857);

  static const Color roseSoft = Color(0xFFFFE4E6);
  static const Color roseBorder = Color(0xFFFECDD3);
  static const Color roseText = Color(0xFFBE123C);

  static const Color background = Color(0xFFF8FAFC);
  static const Color surface = Colors.white;
  static const Color textMain = Color(0xFF1E293B);
  static const Color textMuted = Color(0xFF64748B);
  static const Color border = Color(0xFFE2E8F0);

  static ThemeData light() {
    return ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(
        seedColor: primary,
        primary: primary,
        surface: surface,
      ),
      scaffoldBackgroundColor: background,
      fontFamily: 'Roboto',
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: textMain,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: TextStyle(
          color: textMain,
          fontSize: 18,
          fontWeight: FontWeight.w700,
        ),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: border),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: primary, width: 1.5),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: textMain,
          side: const BorderSide(color: border),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
        ),
      ),
    );
  }

  static Color statusBg(String? status) {
    switch ((status ?? '').toLowerCase().trim()) {
      case 'new':
        return skySoft;
      case 'interested':
      case 'contacted':
        return indigoSoft;
      case 'converted':
      case 'confirmed':
      case 'success':
        return emeraldSoft;
      case 'pending':
      case 'request':
      case 'follow-up':
        return amberSoft;
      case 'lost':
      case 'cancelled':
        return roseSoft;
      default:
        return background;
    }
  }

  static Color statusText(String? status) {
    switch ((status ?? '').toLowerCase().trim()) {
      case 'new':
        return skyText;
      case 'interested':
      case 'contacted':
        return indigoText;
      case 'converted':
      case 'confirmed':
      case 'success':
        return emeraldText;
      case 'pending':
      case 'request':
      case 'follow-up':
        return amberText;
      case 'lost':
      case 'cancelled':
        return roseText;
      default:
        return textMain;
    }
  }

  static Color statusBorder(String? status) {
    switch ((status ?? '').toLowerCase().trim()) {
      case 'new':
        return skyBorder;
      case 'interested':
      case 'contacted':
        return indigoBorder;
      case 'converted':
      case 'confirmed':
      case 'success':
        return emeraldBorder;
      case 'pending':
      case 'request':
      case 'follow-up':
        return amberBorder;
      case 'lost':
      case 'cancelled':
        return roseBorder;
      default:
        return border;
    }
  }
}
