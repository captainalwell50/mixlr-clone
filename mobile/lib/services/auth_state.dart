import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/models.dart';
import 'api_client.dart';

class AuthState extends ChangeNotifier {
  AuthState({ApiClient? api, FlutterSecureStorage? storage})
      : _api = api ?? ApiClient(),
        _storage = storage ?? const FlutterSecureStorage();

  static const _tokenKey = 'live_mix_token';

  final ApiClient _api;
  final FlutterSecureStorage _storage;

  AppUser? user;
  bool ready = false;
  String? error;

  ApiClient get api => _api;
  bool get isLoggedIn => user != null;

  Future<void> bootstrap() async {
    try {
      final token = await _storage.read(key: _tokenKey);
      if (token != null && token.isNotEmpty) {
        _api.setToken(token);
        user = await _api.me();
      }
    } catch (_) {
      await _storage.delete(key: _tokenKey);
      _api.setToken(null);
      user = null;
    } finally {
      ready = true;
      notifyListeners();
    }
  }

  Future<void> login(String email, String password) async {
    error = null;
    notifyListeners();
    try {
      final result = await _api.login(email: email, password: password);
      await _storage.write(key: _tokenKey, value: result.token);
      user = result.user;
      notifyListeners();
    } on ApiException catch (e) {
      error = e.message;
      notifyListeners();
      rethrow;
    }
  }

  Future<void> logout() async {
    await _api.logout();
    await _storage.delete(key: _tokenKey);
    user = null;
    notifyListeners();
  }
}
