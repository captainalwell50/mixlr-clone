import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Premium console palette — teal brand, deep stage blacks.
class StudioTheme {
  static const ink = Color(0xFF070A09);
  static const stage = Color(0xFF0C1210);
  static const panel = Color(0xFF121A17);
  static const panelHi = Color(0xFF1A2621);
  static const line = Color(0x1AFFFFFF);
  static const accent = Color(0xFF14B8A6);
  static const accentBright = Color(0xFF2DD4BF);
  static const accentDim = Color(0x3314B8A6);
  static const cream = Color(0xFFF2F6F4);
  static const mute = Color(0xFF8A9691);
  static const live = Color(0xFFFF4D4D);
  static const liveSoft = Color(0x33FF4D4D);
  static const good = Color(0xFF3DDC97);

  static ThemeData dark() {
    final text = GoogleFonts.outfitTextTheme(ThemeData.dark().textTheme);
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      scaffoldBackgroundColor: ink,
      colorScheme: const ColorScheme.dark(
        primary: accent,
        onPrimary: ink,
        surface: panel,
        onSurface: cream,
        error: live,
      ),
      textTheme: text.apply(bodyColor: cream, displayColor: cream),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: panelHi,
        contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide.none,
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: accent, width: 1.4),
        ),
        labelStyle: const TextStyle(color: mute),
        hintStyle: TextStyle(color: mute.withOpacity(0.7)),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: accent,
          foregroundColor: ink,
          disabledBackgroundColor: accent.withOpacity(0.35),
          textStyle: GoogleFonts.outfit(fontWeight: FontWeight.w700, fontSize: 15),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
    );
  }
}
