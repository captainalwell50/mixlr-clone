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

  NetHealth health = NetHealth.online;
  String label = 'Checking…';
  DateTime? lastOkAt;
  bool _bootstrapped = false;

  bool get isOnline => health == NetHealth.online;
  bool get hasLink => health != NetHealth.offline;

  Future<void> start() async {
    if (_bootstrapped) return;
    _bootstrapped = true;

    _sub = _connectivity.onConnectivityChanged.listen((_) => _evaluate());
    await _evaluate();
    _pingTimer = Timer.periodic(const Duration(seconds: 12), (_) => _evaluate());
  }

  Future<void> refresh() => _evaluate();

  Future<void> _evaluate() async {
    final results = await _connectivity.checkConnectivity();
    final link = results.any(
      (r) =>
          r == ConnectivityResult.mobile ||
          r == ConnectivityResult.wifi ||
          r == ConnectivityResult.ethernet ||
          r == ConnectivityResult.vpn,
    );

    if (!link) {
      _set(NetHealth.offline, 'Offline');
      return;
    }

    try {
      final response = await _client
          .get(Uri.parse('${AppConfig.apiBase}/up'))
          .timeout(const Duration(seconds: 5));
      if (response.statusCode >= 200 && response.statusCode < 500) {
        lastOkAt = DateTime.now();
        _set(NetHealth.online, 'Online');
        return;
      }
      _set(NetHealth.degraded, 'Server unreachable');
    } catch (_) {
      _set(NetHealth.degraded, 'No server');
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
    super.dispose();
  }
}
