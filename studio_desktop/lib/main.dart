import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'screens/console_screen.dart';
import 'screens/login_screen.dart';
import 'services/auth_state.dart';
import 'theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const SoundMixStudioApp());
}

class SoundMixStudioApp extends StatelessWidget {
  const SoundMixStudioApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => AuthState()..bootstrap(),
      child: MaterialApp(
        title: 'Sound Mix Live Studio',
        debugShowCheckedModeBanner: false,
        theme: StudioTheme.dark(),
        home: const _Gate(),
      ),
    );
  }
}

class _Gate extends StatelessWidget {
  const _Gate();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthState>();
    if (!auth.ready) {
      return const Scaffold(
        body: Center(
          child: CircularProgressIndicator(color: StudioTheme.accent),
        ),
      );
    }
    // After sign-in: straight to live console (no Listen tab).
    if (auth.isLoggedIn) return const ConsoleScreen();
    return const LoginScreen();
  }
}
