import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'models.dart';

/// Penyimpanan lokal aplikasi.
///
/// - Token Sanctum: FlutterSecureStorage (bukan SharedPreferences).
/// - Pengaturan (base URL, ID outlet), cache produk, dan antrean
///   penjualan offline: SharedPreferences sebagai JSON.
/// - Batasan yang perlu diketahui: antrean disimpan sebagai satu
///   dokumen JSON di SharedPreferences. Ini cukup untuk antrean
///   harian kasir, tetapi bila antrean tumbuh sangat besar, pindah
///   ke database lokal (mis. Drift/sqflite) pada fase berikutnya.
/// - TIDAK ADA nomor kartu, kata sandi, atau API key yang disimpan
///   di sini. Kata sandi hanya dipakai sekali saat login.
class AppStorage {
  static const _tokenKey = 'sanctum_token';
  static const _baseUrlKey = 'base_url';
  static const _outletIdKey = 'outlet_id';
  static const _productsKey = 'cached_products';
  static const _queueKey = 'offline_queue';
  static const _userKey = 'cached_user';

  final FlutterSecureStorage _secure = const FlutterSecureStorage();
  SharedPreferences? _prefs;

  Future<SharedPreferences> get prefs async =>
      _prefs ??= await SharedPreferences.getInstance();

  Future<String?> readToken() => _secure.read(key: _tokenKey);
  Future<void> writeToken(String token) =>
      _secure.write(key: _tokenKey, value: token);
  Future<void> clearToken() => _secure.delete(key: _tokenKey);

  Future<String> readBaseUrl() async {
    final p = await prefs;
    return p.getString(_baseUrlKey) ?? 'http://10.0.2.2:8000';
  }

  Future<void> writeBaseUrl(String value) async {
    (await prefs).setString(_baseUrlKey, value.trim());
  }

  Future<int?> readOutletId() async => (await prefs).getInt(_outletIdKey);
  Future<void> writeOutletId(int? value) async {
    final p = await prefs;
    if (value == null) {
      await p.remove(_outletIdKey);
    } else {
      await p.setInt(_outletIdKey, value);
    }
  }

  Future<List<Product>> readCachedProducts() async {
    final raw = (await prefs).getString(_productsKey);
    if (raw == null || raw.isEmpty) return [];
    final decoded = jsonDecode(raw) as List<dynamic>;
    return decoded
        .map((item) => Product.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<void> writeCachedProducts(List<Product> products) async {
    (await prefs).setString(
      _productsKey,
      jsonEncode(products.map((p) => p.toJson()).toList()),
    );
  }

  Future<List<QueuedSale>> readQueue() async {
    final raw = (await prefs).getString(_queueKey);
    if (raw == null || raw.isEmpty) return [];
    final decoded = jsonDecode(raw) as List<dynamic>;
    return decoded
        .map((item) => QueuedSale.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<void> writeQueue(List<QueuedSale> queue) async {
    (await prefs).setString(
      _queueKey,
      jsonEncode(queue.map((q) => q.toJson()).toList()),
    );
  }

  Future<Map<String, dynamic>?> readCachedUser() async {
    final raw = (await prefs).getString(_userKey);
    if (raw == null) return null;
    return jsonDecode(raw) as Map<String, dynamic>;
  }

  Future<void> writeCachedUser(Map<String, dynamic> user) async {
    (await prefs).setString(_userKey, jsonEncode(user));
  }

  Future<void> clearCachedUser() async {
    (await prefs).remove(_userKey);
  }
}
