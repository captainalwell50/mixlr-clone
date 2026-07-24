import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import 'package:google_fonts/google_fonts.dart';

import 'brand.dart';
import 'screens/home_shell.dart';
import 'screens/splash_screen.dart';
import 'screens/welcome_screen.dart';
import 'services/auth_state.dart';
import 'services/cache_store.dart';
import 'services/network_status.dart';
import 'services/selected_channel.dart';
import 'services/whep_listener.dart';
import 'theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Never crash cold-start if Google Fonts CDN is unreachable.
  GoogleFonts.config.allowRuntimeFetching = true;
  // Phone chrome only — desktop uses native window chrome.
  if (!kIsWeb &&
      (defaultTargetPlatform == TargetPlatform.android ||
          defaultTargetPlatform == TargetPlatform.iOS)) {
    SystemChrome.setSystemUIOverlayStyle(
      const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        systemNavigationBarColor: LiveMixTheme.ink,
        systemNavigationBarIconBrightness: Brightness.light,
      ),
    );
  }
  // Initialize WebRTC ADM with USAGE_MEDIA / music attributes before any
  // other plugin call — voiceCommunication ADM tracks stick to the earpiece.
  try {
    await WhepListener.ensureWebRtcInitialized();
  } catch (e) {
    debugPrint('WebRTC early init skipped: $e');
  }
  runApp(const LiveMixApp());
}

class LiveMixApp extends StatelessWidget {
  const LiveMixApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthState()..bootstrap()),
        ChangeNotifierProvider(create: (_) => NetworkStatus()..start()),
        ChangeNotifierProvider(create: (_) => SelectedChannel()),
      ],
      child: MaterialApp(
        title: Brand.name,
        debugShowCheckedModeBanner: false,
        theme: LiveMixTheme.dark(),
        home: const _BootGate(),
      ),
    );
  }
}

class _BootGate extends StatefulWidget {
  const _BootGate();

  @override
  State<_BootGate> createState() => _BootGateState();
}

class _BootGateState extends State<_BootGate> {
  bool _minSplashDone = false;
  bool? _welcomeSeen;

  @override
  void initState() {
    super.initState();
    Future<void>.delayed(const Duration(milliseconds: 1400), () {
      if (mounted) setState(() => _minSplashDone = true);
    });
    CacheStore().welcomeSeen().then((seen) {
      if (mounted) setState(() => _welcomeSeen = seen);
    });
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthState>();
    final booting =
        !auth.ready || !_minSplashDone || _welcomeSeen == null;

    if (booting) return const SplashScreen();

    if (_welcomeSeen == false) {
      return WelcomeScreen(
        onContinue: () => setState(() => _welcomeSeen = true),
      );
    }

    return const HomeShell();
  }
}
