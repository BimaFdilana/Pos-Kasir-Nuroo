import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api_client.dart';
import '../app_state.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _baseUrlController = TextEditingController();
  String? _error;
  bool _loading = false;
  bool _baseUrlLoaded = false;

  @override
  void initState() {
    super.initState();
    context.read<AppState>().readBaseUrlSetting().then((value) {
      if (mounted) {
        setState(() {
          _baseUrlController.text = value;
          _baseUrlLoaded = true;
        });
      }
    });
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _baseUrlController.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    final state = context.read<AppState>();
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      if (_baseUrlController.text.trim().isNotEmpty) {
        await state.setBaseUrl(_baseUrlController.text.trim());
      }
      await state.login(
        _emailController.text.trim(),
        _passwordController.text,
      );
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = 'Tidak dapat terhubung ke server. '
          'Periksa alamat server dan koneksi internet.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Pos-Kasir-Nuroo')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Kasir',
              style: TextStyle(fontSize: 28, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            const Text(
              'Masuk dengan akun kasir yang dibuat dari dashboard web.',
            ),
            const SizedBox(height: 24),
            TextField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(
                labelText: 'Email',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _passwordController,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'Kata sandi',
                border: OutlineInputBorder(),
              ),
              onSubmitted: (_) => _login(),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _baseUrlController,
              keyboardType: TextInputType.url,
              decoration: const InputDecoration(
                labelText: 'Alamat server (base URL)',
                helperText:
                    'Emulator Android: http://10.0.2.2:8000. Perangkat fisik: alamat LAN komputer backend, mis. http://192.168.1.10:8000',
                border: OutlineInputBorder(),
              ),
            ),
            if (!_baseUrlLoaded) const SizedBox(height: 8),
            const SizedBox(height: 12),
            if (_error != null)
              Text(_error!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: _loading ? null : _login,
              child: _loading
                  ? const CircularProgressIndicator()
                  : const Text('Masuk'),
            ),
          ],
        ),
      ),
    );
  }
}
