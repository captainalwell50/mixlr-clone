import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../config.dart';

enum NetHealth { offline, degraded, online }

class NetworkStatus extends ChangeNotifier {
  NetworkStatus({http.Client? client}) : _client = client ?? http.Client();

  final http.Client _client;
  final Connectivity _connectivity = Connectivity();

  StreamSubscription<List<ConnectivityResult>>? _sub;
  Timer? _pingTimer;
  Timer? _offlineDebounce;
  int _failStreak = 0;

  NetHealth health = NetHealth.online;
  String label = 'Checking…';
  DateTime? lastOkAt;
  bool _bootstrapped = false;
  bool _evaluating = false;

  bool get isOnline => health == NetHealth.online;
  bool get hasLink => health != NetHealth.offline;

  Future<void> start() async {
    if (_bootstrapped) return;
    _bootstrapped = true;

    _sub = _connectivity.onConnectivityChanged.listen((_) => _evaluate());
    await _evaluate();
    _pingTimer = Timer.periodic(const Duration(seconds: 25), (_) => _evaluate());
  }

  Future<void> refresh() => _evaluate(force: true);

  Future<void> _evaluate({bool force = false}) async {
    if (_evaluating && !force) return;
    _evaluating = true;
    try {
      // Reachability to our API is source of truth. connectivity_plus alone
      // often blips to "none" while Wi‑Fi is fine, which used to flash Offline
      // while listen UI kept showing Pause/LISTENING.
      final probeOk = await _probeApi();
      if (probeOk) {
        _failStreak = 0;
        _offlineDebounce?.cancel();
        _offlineDebounce = null;
        lastOkAt = DateTime.now();
        _set(NetHealth.online, 'Online');
        return;
      }

      _failStreak += 1;
      final results = await _connectivity.checkConnectivity();
      final link = results.any(_looksLikeLink);

      if (link) {
        _offlineDebounce?.cancel();
        _offlineDebounce = null;
        _set(NetHealth.degraded, 'No server');
        return;
      }

      // No OS link + failed probe: debounce before Offline so brief
      // connectivity_plus glitches don't contradict an active listen session.
      if (force || _failStreak >= 2) {
        _commitOffline();
      } else {
        _offlineDebounce?.cancel();
        _offlineDebounce = Timer(const Duration(seconds: 4), _commitOffline);
        if (health == NetHealth.online) {
          _set(NetHealth.degraded, 'Reconnecting…');
        }
      }
    } finally {
      _evaluating = false;
    }
  }

  void _commitOffline() {
    _offlineDebounce = null;
    _set(NetHealth.offline, 'Offline');
  }

  bool _looksLikeLink(ConnectivityResult r) {
    return r == ConnectivityResult.mobile ||
        r == ConnectivityResult.wifi ||
        r == ConnectivityResult.ethernet ||
        r == ConnectivityResult.vpn ||
        r == ConnectivityResult.other ||
        r == ConnectivityResult.bluetooth;
  }

  Future<bool> _probeApi() async {
    try {
      final response = await _client
          .get(Uri.parse('${AppConfig.apiBase}/up'))
          .timeout(const Duration(seconds: 5));
      return response.statusCode >= 200 && response.statusCode < 500;
    } catch (_) {
      return false;
    }
  }

  void _set(NetHealth next, String nextLabel) {
    if (health == next && label == nextLabel) return;
    health = next;
    label = nextLabel;
    notifyListeners();
  }

  @override
  void dispose() {
    _sub?.cancel();
    _pingTimer?.cancel();
    _offlineDebounce?.cancel();
    super.dispose();
  }
}
