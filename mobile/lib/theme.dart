import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class LiveMixTheme {
  static const ink = Color(0xFF0C1210);
  static const panel = Color(0xFF141C19);
  static const panelHi = Color(0xFF1C2824);
  /// Brand teal (matches web / SM mark).
  static const accent = Color(0xFF14B8A6);
  static const accentSoft = Color(0x3314B8A6);
  static const accentBright = Color(0xFF2DD4BF);
  static const mist = Color(0xFFF0F4F2);
  static const mute = Color(0xFF8A9691);
  static const live = Color(0xFFFF5C5C);
  static const liveSoft = Color(0x33FF5C5C);
  static const good = Color(0xFF3DDC97);
  static const warn = Color(0xFFF0B429);
  static const bad = Color(0xFFFF5C5C);

  /// @Deprecated — use [accent]. Kept so older call sites compile during rename.
  static const gold = accent;
  static const goldSoft = accentSoft;

  static ThemeData dark() {
    // Fall back to the platform dark theme if Outfit cannot be resolved
    // (offline first launch with empty font cache).
    TextTheme textTheme;
    try {
      textTheme = GoogleFonts.outfitTextTheme(ThemeData.dark().textTheme);
    } catch (_) {
      textTheme = ThemeData.dark().textTheme;
    }

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: const ColorScheme.dark(
        primary: accent,
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
          backgroundColor: accent,
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
          side: BorderSide(color: mist.withOpacity(0.28)),
          padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 16),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          textStyle: GoogleFonts.outfit(fontWeight: FontWeight.w600, fontSize: 16),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: panel,
        indicatorColor: accentSoft,
        labelTextStyle: WidgetStatePropertyAll(
          GoogleFonts.outfit(fontSize: 12, fontWeight: FontWeight.w600),
        ),
      ),
      navigationRailTheme: NavigationRailThemeData(
        backgroundColor: panel,
        selectedIconTheme: const IconThemeData(color: accentBright),
        selectedLabelTextStyle: GoogleFonts.outfit(
          color: mist,
          fontWeight: FontWeight.w600,
          fontSize: 12,
        ),
        unselectedIconTheme: IconThemeData(color: mute.withOpacity(0.9)),
        indicatorColor: accentSoft,
      ),
    );
  }
}
