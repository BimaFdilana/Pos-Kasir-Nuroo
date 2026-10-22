import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import 'models.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  ApiException(this.message, {this.statusCode});

  @override
  String toString() => message;
}

/// Klien HTTP baca-saja untuk API v1 Laravel (pola yang sama dengan
/// aplikasi kasir, tapi tanpa endpoint tulis apa pun).
///
/// Endpoint yang dipakai (persis dari backend, jangan ditebak ulang):
/// - POST /api/v1/login  {email, password}
///   -> 200 {token, user: {id, name, email, tenant_id, outlet_id, role}}
///   -> 401 {message: 'Invalid credentials.'}
/// - POST /api/v1/logout (Bearer) -> 200 {message}
/// - GET  /api/v1/owner/summary?outlet_id=&threshold=5 (Bearer,
///   owner/manager saja) -> 200 {data: {date, sales_count, total,
///   cash_total, per_outlet: [...], low_stock: [...],
///   low_stock_threshold}}; 403 bila peran bukan owner/manager.
/// - GET  /api/v1/sales (Bearer) -> paginator Laravel {data: [...]};
///   TIDAK difilter outlet di server, jadi pemfilteran outlet
///   dilakukan di sisi klien (Sale.filterByOutlet).
///
/// Tidak ada endpoint daftar outlet khusus: daftar outlet dibangun
/// dari summary.per_outlet saat dipanggil tanpa filter outlet.
class ApiClient {
  final String baseUrl;
  final http.Client _http;
  String? token;

  ApiClient({required this.baseUrl, this.token, http.Client? httpClient})
      : _http = httpClient ?? http.Client();

  String get _base => baseUrl.replaceAll(RegExp(r'/+$'), '');

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Future<AppUser> login(String email, String password) async {
    final response = await _http.post(
      Uri.parse('$_base/api/v1/login'),
      headers: const {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'email': email, 'password': password}),
    );
    _throwIfError(response);
    final body = _decode(response);
    final userJson = body['user'] as Map<String, dynamic>?;
    token = body['token'] as String?;
    if (token == null || userJson == null) {
      throw ApiException('Respons login tidak valid dari server.');
    }
    return AppUser.fromJson(userJson);
  }

  Future<void> logout() async {
    try {
      await _http.post(
        Uri.parse('$_base/api/v1/logout'),
        headers: _headers,
        body: jsonEncode({}),
      );
    } catch (_) {
      // Logout lokal tetap jalan meski server tidak terjangkau.
    } finally {
      token = null;
    }
  }

  /// [outletId] null = semua outlet. [threshold] default 5, sama
  /// dengan default server.
  Future<OwnerSummary> getOwnerSummary({int? outletId, int threshold = 5}) async {
    final query = <String, String>{'threshold': '$threshold'};
    if (outletId != null) query['outlet_id'] = '$outletId';
    final uri = Uri.parse('$_base/api/v1/owner/summary')
        .replace(queryParameters: query);
    final response = await _http.get(uri, headers: _headers);
    _throwIfError(response);
    return OwnerSummary.fromJson(_decode(response));
  }

  Future<List<Sale>> getSales() async {
    final response =
        await _http.get(Uri.parse('$_base/api/v1/sales'), headers: _headers);
    _throwIfError(response);
    final body = _decode(response);
    final data = body['data'] as List<dynamic>? ?? [];
    return data.map((e) => Sale.fromJson(e as Map<String, dynamic>)).toList();
  }

  Map<String, dynamic> _decode(http.Response response) {
    if (response.body.isEmpty) return {};
    final decoded = jsonDecode(response.body);
    if (decoded is Map<String, dynamic>) return decoded;
    return {'data': decoded};
  }

  void _throwIfError(http.Response response) {
    if (response.statusCode >= 200 && response.statusCode < 300) return;
    String message = 'Server mengembalikan ${response.statusCode}.';
    try {
      final body = jsonDecode(response.body) as Map<String, dynamic>;
      if (body['message'] is String) message = body['message'] as String;
    } catch (_) {
      // Pakai pesan default bila body bukan JSON.
    }
    if (response.statusCode == 401) {
      message = 'Sesi berakhir atau email/kata sandi salah.';
    } else if (response.statusCode == 403) {
      message =
          'Aplikasi ini hanya untuk owner/manager. Akun ini tidak memiliki akses ringkasan owner.';
    }
    throw ApiException(message, statusCode: response.statusCode);
  }
}

/// Pesan ramah untuk error jaringan (bukan dari server).
String friendlyError(Object error) {
  if (error is ApiException) return error.message;
  if (error is SocketException) {
    return 'Tidak dapat terhubung ke server. Periksa alamat server dan jaringan.';
  }
  return 'Terjadi kesalahan: $error';
}
