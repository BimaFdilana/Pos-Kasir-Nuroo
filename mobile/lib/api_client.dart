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

/// Klien HTTP untuk API v1 Laravel di routes/api.php.
///
/// Endpoint yang dipakai (persis dari backend, jangan ditebak ulang):
/// - POST /api/v1/login  {email, password}
///   -> 200 {token, user: {id, name, email, tenant_id, outlet_id, role}}
///   -> 401 {message: 'Invalid credentials.'}
/// - POST /api/v1/logout (Bearer) -> 200 {message}
/// - GET  /api/v1/products (Bearer) -> {data: [{id, name, sku, category,
///   variants: [{id, name, sku, price}]}]}  (tanpa stok per outlet)
/// - GET  /api/v1/sales (Bearer) -> paginator Laravel {data: [...], ...}
/// - POST /api/v1/sales (Bearer)
///   {outlet_id, client_reference?, items: [{product_variant_id, quantity}],
///    payments: [{method: cash|edc, amount, tendered?, bank?,
///               card_type?: debit|credit, approval_code?}],
///    discount_total?, tax_total?, notes?}
///   -> 201 {data: sale, duplicate: false} untuk penjualan baru
///   -> 200 {data: sale, duplicate: true} bila client_reference sudah
///      pernah diproses (idempotensi server)
///   -> 422 {message, errors: {...}} bila validasi gagal (mis. stok
///      tidak cukup atau total pembayaran tidak sama dengan total)
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

  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await _post('/api/v1/login', {
      'email': email,
      'password': password,
    }, authenticated: false);
    final body = _decode(response);
    final user = body['user'] as Map<String, dynamic>?;
    token = body['token'] as String?;
    if (token == null || user == null) {
      throw ApiException('Respons login tidak valid dari server.');
    }
    return user;
  }

  Future<void> logout() async {
    try {
      await _post('/api/v1/logout', {});
    } finally {
      token = null;
    }
  }

  Future<List<Product>> getProducts() async {
    final response = await _http.get(
      Uri.parse('$_base/api/v1/products'),
      headers: _headers,
    );
    final body = _decode(response);
    final data = body['data'] as List<dynamic>? ?? [];
    return data
        .map((item) => Product.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  /// Mengembalikan (saleNumber, duplicate).
  Future<({String? saleNumber, bool duplicate})> postSale(
      Map<String, dynamic> request) async {
    final response = await _post('/api/v1/sales', request);
    final body = _decode(response);
    final data = body['data'] as Map<String, dynamic>?;
    return (
      saleNumber: data?['sale_number'] as String?,
      duplicate: body['duplicate'] == true,
    );
  }

  Future<List<Map<String, dynamic>>> getSales() async {
    final response = await _http.get(
      Uri.parse('$_base/api/v1/sales'),
      headers: _headers,
    );
    final body = _decode(response);
    final data = body['data'] as List<dynamic>? ?? [];
    return data.map((item) => item as Map<String, dynamic>).toList();
  }

  Future<http.Response> _post(String path, Map<String, dynamic> body,
      {bool authenticated = true}) async {
    try {
      final response = await _http.post(
        Uri.parse('$_base$path'),
        headers: authenticated ? _headers : _headersWithoutAuth,
        body: jsonEncode(body),
      );
      _throwIfError(response);
      return response;
    } on SocketException {
      rethrow;
    } on HttpException {
      rethrow;
    }
  }

  Map<String, String> get _headersWithoutAuth => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      };

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
      final errors = body['errors'];
      if (errors is Map<String, dynamic> && errors.isNotEmpty) {
        final details = errors.values
            .expand((value) => (value as List<dynamic>).map((e) => '$e'))
            .join(' ');
        message = '$message $details';
      }
    } catch (_) {
      // Pakai pesan default di atas bila body bukan JSON.
    }
    if (response.statusCode == 401) {
      message = 'Sesi berakhir atau email/kata sandi salah.';
    }
    throw ApiException(message, statusCode: response.statusCode);
  }
}
