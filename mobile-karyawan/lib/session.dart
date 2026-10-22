import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import 'models.dart';

/// Sesi tersimpan lokal: token Sanctum + base URL + profil pengguna.
/// Tidak ada kata sandi, nomor kartu, atau rahasia lain yang disimpan.
class Session {
  static const defaultBaseUrl = 'http://10.0.2.2:8000';
  static const _keyToken = 'token';
  static const _keyBaseUrl = 'base_url';
  static const _keyUser = 'user_json';

  String baseUrl;
  String? token;
  AppUser? user;

  Session({this.baseUrl = defaultBaseUrl, this.token, this.user});

  bool get isLoggedIn => token != null && token!.isNotEmpty;

  static Future<Session> load() async {
    final prefs = await SharedPreferences.getInstance();
    AppUser? user;
    final raw = prefs.getString(_keyUser);
    if (raw != null) {
      try {
        user = AppUser.fromJson(jsonDecode(raw) as Map<String, dynamic>);
      } catch (_) {
        user = null;
      }
    }
    return Session(
      baseUrl: prefs.getString(_keyBaseUrl) ?? defaultBaseUrl,
      token: prefs.getString(_keyToken),
      user: user,
    );
  }

  Future<void> saveLogin(String newToken, Map<String, dynamic> userJson) async {
    final prefs = await SharedPreferences.getInstance();
    token = newToken;
    user = AppUser.fromJson(userJson);
    await prefs.setString(_keyToken, newToken);
    await prefs.setString(_keyUser, jsonEncode(userJson));
    await prefs.setString(_keyBaseUrl, baseUrl);
  }

  Future<void> saveBaseUrl(String url) async {
    baseUrl = url;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyBaseUrl, url);
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    token = null;
    user = null;
    await prefs.remove(_keyToken);
    await prefs.remove(_keyUser);
    // baseUrl sengaja dipertahankan agar tidak perlu diisi ulang.
  }
}
