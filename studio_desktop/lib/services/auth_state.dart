import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models.dart';
import 'api_client.dart';

/// Session token in SharedPreferences — avoids macOS Keychain entitlement -34018
/// on unsigned local builds (flutter_secure_storage needs keychain-access-groups).
class AuthState extends ChangeNotifier {
  AuthState({ApiClient? api}) : _api = api ?? ApiClient() {
    _api.onUnauthorized = _handleUnauthorized;
  }

  static const _tokenKey = 'soundmix_studio_token';
  static const _streamKey = 'soundmix_studio_stream_uuid';

  final ApiClient _api;

  AppUser? user;
  bool ready = false;
  String? error;
  String? preferredStreamUuid;

  ApiClient get api => _api;
  bool get isLoggedIn => user != null;

  Future<void> _handleUnauthorized() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    _api.setToken(null);
    if (user != null) {
      user = null;
      error = 'Session expired. Sign in again.';
      notifyListeners();
    }
  }

  Future<void> bootstrap() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      preferredStreamUuid = prefs.getString(_streamKey);
      final token = prefs.getString(_tokenKey);
      if (token != null && token.isNotEmpty) {
        _api.setToken(token);
        user = await _api.me();
      }
    } on ApiException catch (e) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_tokenKey);
      _api.setToken(null);
      user = null;
      error = e.isUnauthorized
          ? 'Session expired. Sign in again.'
          : e.message;
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

  Future<void> rememberStreamUuid(String? uuid) async {
    preferredStreamUuid = uuid;
    final prefs = await SharedPreferences.getInstance();
    if (uuid == null || uuid.isEmpty) {
      await prefs.remove(_streamKey);
    } else {
      await prefs.setString(_streamKey, uuid);
    }
  }

  Future<void> logout() async {
    await _api.logout();
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    user = null;
    error = null;
    notifyListeners();
  }
}
