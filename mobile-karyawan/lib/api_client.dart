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

/// Klien HTTP untuk API v1 Laravel (routes/api.php backend Pos-Kasir-Nuroo).
///
/// Endpoint yang dipakai (persis dari backend, jangan ditebak ulang):
/// - POST /api/v1/login {email, password}
///   -> {token, user: {id, name, email, tenant_id, outlet_id, role}}
/// - POST /api/v1/logout (Bearer)
/// - GET  /api/v1/attendance/open (Bearer)
///   -> {data: attendance record atau null}
/// - POST /api/v1/attendance/check-in (Bearer)
///   {outlet_id?, latitude?, longitude?, note?}
///   -> 201 {data}; 422 bila sudah ada check-in terbuka
/// - POST /api/v1/attendance/check-out (Bearer)
///   {latitude?, longitude?, note?}
///   -> 200 {data}; 422 bila tidak ada check-in terbuka
/// - GET  /api/v1/attendance/me?date= (Bearer)
///   -> {data: paginator {data: [attendance records]}} (riwayat sendiri)
/// - GET  /api/v1/shifts/my?from=&to= (Bearer)
///   -> {data: paginator {data: [{id, date, start_time, end_time, note,
///      outlet: {id, name}}]}}
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

  /// Check-in terbuka saat ini, atau null bila belum check-in.
  Future<Attendance?> getOpenAttendance() async {
    final response = await _http.get(
      Uri.parse('$_base/api/v1/attendance/open'),
      headers: _headers,
    );
    _throwIfError(response);
    final body = _decode(response);
    final data = body['data'];
    if (data == null) return null;
    if (data is Map<String, dynamic>) return Attendance.fromJson(data);
    return null;
  }

  Future<Attendance> checkIn({
    int? outletId,
    double? latitude,
    double? longitude,
    String? note,
  }) async {
    final payload = <String, dynamic>{
      if (outletId != null) 'outlet_id': outletId,
      if (latitude != null) 'latitude': latitude,
      if (longitude != null) 'longitude': longitude,
      if (note != null && note.isNotEmpty) 'note': note,
    };
    final response = await _post('/api/v1/attendance/check-in', payload);
    final body = _decode(response);
    final data = body['data'] as Map<String, dynamic>?;
    if (data == null) throw ApiException('Respons check-in tidak valid.');
    return Attendance.fromJson(data);
  }

  Future<Attendance> checkOut({
    double? latitude,
    double? longitude,
    String? note,
  }) async {
    final payload = <String, dynamic>{
      if (latitude != null) 'latitude': latitude,
      if (longitude != null) 'longitude': longitude,
      if (note != null && note.isNotEmpty) 'note': note,
    };
    final response = await _post('/api/v1/attendance/check-out', payload);
    final body = _decode(response);
    final data = body['data'] as Map<String, dynamic>?;
    if (data == null) throw ApiException('Respons check-out tidak valid.');
    return Attendance.fromJson(data);
  }

  Future<List<Attendance>> getMyAttendance({String? date}) async {
    final query = date != null ? '?date=${Uri.encodeQueryComponent(date)}' : '';
    final response = await _http.get(
      Uri.parse('$_base/api/v1/attendance/me$query'),
      headers: _headers,
    );
    _throwIfError(response);
    final body = _decode(response);
    return paginatorItems(body).map(Attendance.fromJson).toList();
  }

  Future<List<Shift>> getMyShifts({String? from, String? to}) async {
    final params = <String, String>{};
    if (from != null) params['from'] = from;
    if (to != null) params['to'] = to;
    final uri = Uri.parse('$_base/api/v1/shifts/my')
        .replace(queryParameters: params.isEmpty ? null : params);
    final response = await _http.get(uri, headers: _headers);
    _throwIfError(response);
    final body = _decode(response);
    return paginatorItems(body).map(Shift.fromJson).toList();
  }

  Future<http.Response> _post(String path, Map<String, dynamic> body,
      {bool authenticated = true}) async {
    final headers = authenticated
        ? _headers
        : {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          };
    try {
      final response = await _http.post(
        Uri.parse('$_base$path'),
        headers: headers,
        body: jsonEncode(body),
      );
      _throwIfError(response);
      return response;
    } on SocketException {
      rethrow;
    }
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
