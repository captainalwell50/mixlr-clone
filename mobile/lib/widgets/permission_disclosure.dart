import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../theme.dart';

/// In-app disclosures shown before Android runtime permission prompts
/// (Play policy: Permissions that access sensitive information).
class PermissionDisclosure {
  static const _micKey = 'disclosure_mic_v1';
  static const _notifKey = 'disclosure_notifications_v1';

  /// Explain mic use, then request [Permission.microphone] if the user continues.
  static Future<PermissionStatus> ensureMicrophone(BuildContext context) async {
    final status = await Permission.microphone.status;
    if (status.isGranted || status.isLimited) return status;

    final prefs = await SharedPreferences.getInstance();
    final seen = prefs.getBool(_micKey) ?? false;
    if (!seen) {
      if (!context.mounted) return PermissionStatus.denied;
      final ok = await _show(
        context,
        title: 'Microphone access',
        body:
            'Sound Mix Live uses the microphone only when you open Studio to '
            'broadcast live audio. Mic audio is streamed to listeners — not used '
            'for ads or profiling.',
        confirmLabel: 'Allow microphone',
      );
      await prefs.setBool(_micKey, true);
      if (!ok) return PermissionStatus.denied;
    }

    return Permission.microphone.request();
  }

  /// Explain notification use for background listen, then request if needed.
  static Future<PermissionStatus> ensureNotifications(BuildContext context) async {
    final status = await Permission.notification.status;
    if (status.isGranted) return status;
    if (status.isPermanentlyDenied) return status;

    final prefs = await SharedPreferences.getInstance();
    final seen = prefs.getBool(_notifKey) ?? false;
    if (!seen) {
      if (!context.mounted) return PermissionStatus.denied;
      final ok = await _show(
        context,
        title: 'Listening notification',
        body:
            'To keep live audio playing when the app is in the background, '
            'Android shows an ongoing media notification. You can stop playback '
            'from that notification. We do not send marketing push notifications.',
        confirmLabel: 'Continue',
      );
      await prefs.setBool(_notifKey, true);
      if (!ok) {
        // Playback may still work in-foreground without the notification.
        return PermissionStatus.denied;
      }
    }

    return Permission.notification.request();
  }

  static Future<bool> _show(
    BuildContext context, {
    required String title,
    required String body,
    required String confirmLabel,
  }) async {
    final result = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        backgroundColor: LiveMixTheme.panel,
        title: Text(
          title,
          style: GoogleFonts.outfit(
            color: LiveMixTheme.mist,
            fontWeight: FontWeight.w700,
          ),
        ),
        content: Text(
          body,
          style: GoogleFonts.outfit(
            color: LiveMixTheme.mute,
            height: 1.45,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Not now'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(confirmLabel),
          ),
        ],
      ),
    );
    return result == true;
  }
}
