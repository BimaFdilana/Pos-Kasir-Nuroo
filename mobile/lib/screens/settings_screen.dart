import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../app_state.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  final _baseUrlController = TextEditingController();
  final _outletController = TextEditingController();
  String? _saved;

  @override
  void initState() {
    super.initState();
    final state = context.read<AppState>();
    state.readBaseUrlSetting().then((value) {
      if (mounted) _baseUrlController.text = value;
    });
    state.readOutletIdSetting().then((value) {
      if (mounted && value != null) _outletController.text = '$value';
    });
  }

  @override
  void dispose() {
    _baseUrlController.dispose();
    _outletController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final user = state.user;
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (user != null) ...[
            Text('Pengguna: ${user['name'] ?? ''}',
                style: const TextStyle(fontWeight: FontWeight.bold)),
            Text('Email: ${user['email'] ?? ''}'),
            Text('Peran: ${user['role'] ?? ''}'),
            Text('ID outlet dari login: ${user['outlet_id'] ?? '-'}'),
            const SizedBox(height: 16),
          ],
          TextField(
            controller: _baseUrlController,
            keyboardType: TextInputType.url,
            decoration: const InputDecoration(
              labelText: 'Alamat server (base URL)',
              helperText:
                  'Emulator: http://10.0.2.2:8000. Perangkat fisik: alamat LAN backend.',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _outletController,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
              labelText: 'ID outlet (bila login tidak membawa outlet)',
              helperText:
                  'Backend v1 belum punya endpoint daftar outlet, jadi ID ini dipakai bila akun tidak terikat ke satu outlet.',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: () async {
              await state.setBaseUrl(_baseUrlController.text.trim());
              final outletText = _outletController.text.trim();
              await state.setOutletIdSetting(
                  outletText.isEmpty ? null : int.tryParse(outletText));
              if (mounted) {
                setState(() => _saved = 'Pengaturan tersimpan.');
              }
            },
            child: const Text('Simpan pengaturan'),
          ),
          if (_saved != null) ...[
            const SizedBox(height: 8),
            Text(_saved!, style: const TextStyle(color: Colors.green)),
          ],
          const SizedBox(height: 24),
          const Text(
            'Catatan versi ini: cetak printer ESC/POS belum tersedia; struk hanya pratinjau teks di aplikasi. Pembayaran hanya cash dan EDC manual; QRIS, e-wallet, dan gateway tidak ada di fase ini.',
            style: TextStyle(color: Colors.grey),
          ),
          const SizedBox(height: 24),
          OutlinedButton(
            onPressed: () => state.logout(),
            child: const Text('Keluar'),
          ),
        ],
      ),
    );
  }
}
