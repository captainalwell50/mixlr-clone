import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models.dart';
import 'api_client.dart';

/// Session token in SharedPreferences — avoids macOS Keychain entitlement -34018
/// on unsigned local builds (flutter_secure_storage needs keychain-access-groups).
class AuthState extends ChangeNotifier {
  AuthState({ApiClient? api}) : _api = api ?? ApiClient();

  static const _tokenKey = 'soundmix_studio_token';

  final ApiClient _api;

  AppUser? user;
  bool ready = false;
  String? error;

  ApiClient get api => _api;
  bool get isLoggedIn => user != null;

  Future<void> bootstrap() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString(_tokenKey);
      if (token != null && token.isNotEmpty) {
        _api.setToken(token);
        user = await _api.me();
      }
    } catch (_) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_tokenKey);
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
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_tokenKey, result.token);
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
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    user = null;
    notifyListeners();
  }
}
