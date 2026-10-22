import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api_client.dart';
import 'models.dart';
import 'screens/dashboard_screen.dart';
import 'screens/login_screen.dart';

const String _kBaseUrl = 'owner_base_url';
const String _kToken = 'owner_token';
const String _kUser = 'owner_user';
const String _defaultBaseUrl = 'http://10.0.2.2:8000';

void main() {
  runApp(const OwnerApp());
}

class OwnerApp extends StatefulWidget {
  const OwnerApp({super.key});

  @override
  State<OwnerApp> createState() => _OwnerAppState();
}

class _OwnerAppState extends State<OwnerApp> {
  ApiClient? _client;
  AppUser? _user;
  String _baseUrl = _defaultBaseUrl;
  bool _restoring = true;

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_kToken);
    final userJson = prefs.getString(_kUser);
    final baseUrl = prefs.getString(_kBaseUrl) ?? _defaultBaseUrl;
    if (token != null && userJson != null) {
      try {
        final user =
            AppUser.fromJson(jsonDecode(userJson) as Map<String, dynamic>);
        if (user.isSupervisor) {
          setState(() {
            _client = ApiClient(baseUrl: baseUrl, token: token);
            _user = user;
            _baseUrl = baseUrl;
          });
        }
      } catch (_) {
        // Sesi tersimpan rusak: kembali ke login.
      }
    } else {
      setState(() => _baseUrl = baseUrl);
    }
    setState(() => _restoring = false);
  }

  Future<void> _onLoggedIn(
      ApiClient client, AppUser user, String baseUrl) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kBaseUrl, baseUrl);
    await prefs.setString(_kToken, client.token ?? '');
    await prefs.setString(
      _kUser,
      jsonEncode({
        'id': user.id,
        'name': user.name,
        'email': user.email,
        'tenant_id': user.tenantId,
        'outlet_id': user.outletId,
        'role': user.role,
      }),
    );
    setState(() {
      _client = client;
      _user = user;
      _baseUrl = baseUrl;
    });
  }

  Future<void> _logout() async {
    final client = _client;
    if (client != null) await client.logout();
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kToken);
    await prefs.remove(_kUser);
    setState(() {
      _client = null;
      _user = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Pos-Kasir-Nuroo Owner',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.teal),
        useMaterial3: true,
      ),
      home: _restoring
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : (_client != null && _user != null
              ? DashboardPage(
                  client: _client!,
                  user: _user!,
                  onSessionExpired: _logout,
                )
              : LoginScreen(
                  initialBaseUrl: _baseUrl,
                  onLoggedIn: _onLoggedIn,
                )),
    );
  }
}
