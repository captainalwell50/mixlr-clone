import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'screens/home_shell.dart';
import 'services/auth_state.dart';
import 'theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const LiveMixApp());
}

class LiveMixApp extends StatelessWidget {
  const LiveMixApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => AuthState()..bootstrap(),
      child: MaterialApp(
        title: 'Live Mix',
        debugShowCheckedModeBanner: false,
        theme: LiveMixTheme.dark(),
        home: const _BootGate(),
      ),
    );
  }
}

class _BootGate extends StatelessWidget {
  const _BootGate();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthState>();
    if (!auth.ready) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    return const HomeShell();
  }
}
