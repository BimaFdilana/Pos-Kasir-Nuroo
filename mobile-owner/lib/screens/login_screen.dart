import 'package:flutter/material.dart';

import '../api_client.dart';
import '../models.dart';

/// Layar login owner. Baca-saja setelah login; tidak ada pendaftaran
/// atau reset kata sandi di aplikasi ini.
///
/// [initialBaseUrl] default emulator http://10.0.2.2:8000; untuk
/// perangkat fisik isi alamat LAN server, mis. http://192.168.1.10:8000.
class LoginScreen extends StatefulWidget {
  final String initialBaseUrl;
  final void Function(ApiClient client, AppUser user, String baseUrl)
      onLoggedIn;

  const LoginScreen({
    super.key,
    required this.initialBaseUrl,
    required this.onLoggedIn,
  });

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  late final TextEditingController _baseUrlController;
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _baseUrlController = TextEditingController(text: widget.initialBaseUrl);
  }

  @override
  void dispose() {
    _baseUrlController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final baseUrl = _baseUrlController.text.trim();
    final email = _emailController.text.trim();
    final password = _passwordController.text;
    if (baseUrl.isEmpty || email.isEmpty || password.isEmpty) {
      setState(() => _error = 'Alamat server, email, dan kata sandi wajib diisi.');
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    final client = ApiClient(baseUrl: baseUrl);
    try {
      final user = await client.login(email, password);
      if (!user.isSupervisor) {
        await client.logout();
        setState(() {
          _error =
              'Aplikasi ini hanya untuk owner/manager. Akun ini tidak memiliki akses ringkasan owner.';
        });
        return;
      }
      if (!mounted) return;
      widget.onLoggedIn(client, user, baseUrl);
    } catch (e) {
      setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Pos-Kasir-Nuroo Owner')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Masuk sebagai owner/manager untuk melihat ringkasan penjualan dan stok menipis. Aplikasi ini hanya membaca data.',
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _baseUrlController,
              decoration: const InputDecoration(
                labelText: 'Alamat server (base URL)',
                hintText: 'http://10.0.2.2:8000',
                border: OutlineInputBorder(),
              ),
              keyboardType: TextInputType.url,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _emailController,
              decoration: const InputDecoration(
                labelText: 'Email',
                border: OutlineInputBorder(),
              ),
              keyboardType: TextInputType.emailAddress,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _passwordController,
              decoration: const InputDecoration(
                labelText: 'Kata sandi',
                border: OutlineInputBorder(),
              ),
              obscureText: true,
            ),
            const SizedBox(height: 16),
            if (_error != null) ...[
              Text(_error!, style: const TextStyle(color: Colors.red)),
              const SizedBox(height: 12),
            ],
            ElevatedButton(
              onPressed: _loading ? null : _submit,
              child: Text(_loading ? 'Memeriksa…' : 'Masuk'),
            ),
          ],
        ),
      ),
    );
  }
}
