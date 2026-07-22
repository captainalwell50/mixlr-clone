import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class LiveMixTheme {
  static const ink = Color(0xFF0E1014);
  static const panel = Color(0xFF1A1E27);
  static const panelHi = Color(0xFF242933);
  static const gold = Color(0xFFD4A24C);
  static const goldSoft = Color(0x33D4A24C);
  static const mist = Color(0xFFF0EBE3);
  static const mute = Color(0xFF8B909A);
  static const live = Color(0xFFFF5C5C);
  static const liveSoft = Color(0x33FF5C5C);
  static const good = Color(0xFF3DDC97);
  static const warn = Color(0xFFF0B429);
  static const bad = Color(0xFFFF5C5C);

  static ThemeData dark() {
    final textTheme = GoogleFonts.outfitTextTheme(ThemeData.dark().textTheme);

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: const ColorScheme.dark(
        primary: gold,
        onPrimary: ink,
        surface: panel,
        onSurface: mist,
        error: bad,
      ),
      scaffoldBackgroundColor: ink,
      textTheme: textTheme.apply(
        bodyColor: mist,
        displayColor: mist,
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: Colors.transparent,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: GoogleFonts.outfit(
          color: mist,
          fontSize: 20,
          fontWeight: FontWeight.w700,
          letterSpacing: -0.3,
        ),
        iconTheme: const IconThemeData(color: mist),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: panel,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide.none,
        ),
        labelStyle: const TextStyle(color: mute),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: gold,
          foregroundColor: ink,
          textStyle: GoogleFonts.outfit(
            fontWeight: FontWeight.w700,
            fontSize: 16,
          ),
          padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 16),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: mist,
          side: const BorderSide(color: Color(0x44E8E4DC)),
          padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 16),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          textStyle: GoogleFonts.outfit(fontWeight: FontWeight.w600, fontSize: 16),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: panel,
        indicatorColor: goldSoft,
        labelTextStyle: WidgetStatePropertyAll(
          GoogleFonts.outfit(fontSize: 12, fontWeight: FontWeight.w600),
        ),
      ),
    );
  }
}
