import 'package:flutter/material.dart';

import 'api_client.dart';
import 'attendance_state.dart';
import 'location_service.dart';
import 'models.dart';
import 'session.dart';

void main() {
  runApp(const KaryawanApp());
}

class KaryawanApp extends StatelessWidget {
  const KaryawanApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Pos-Kasir-Nuroo Karyawan',
      theme: ThemeData(colorSchemeSeed: Colors.teal, useMaterial3: true),
      home: const _Gate(),
    );
  }
}

/// Memuat sesi tersimpan, lalu mengarahkan ke login atau beranda.
class _Gate extends StatefulWidget {
  const _Gate();

  @override
  State<_Gate> createState() => _GateState();
}

class _GateState extends State<_Gate> {
  Session? _session;

  @override
  void initState() {
    super.initState();
    Session.load().then((s) {
      if (mounted) setState(() => _session = s);
    });
  }

  @override
  Widget build(BuildContext context) {
    final session = _session;
    if (session == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (!session.isLoggedIn) {
      return LoginScreen(
        session: session,
        onLoggedIn: () => setState(() {}),
      );
    }
    return HomeScreen(
      session: session,
      onLoggedOut: () => setState(() {}),
    );
  }
}

class LoginScreen extends StatefulWidget {
  final Session session;
  final VoidCallback onLoggedIn;

  const LoginScreen(
      {super.key, required this.session, required this.onLoggedIn});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  late final TextEditingController _email;
  late final TextEditingController _password;
  late final TextEditingController _baseUrl;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _email = TextEditingController();
    _password = TextEditingController();
    _baseUrl = TextEditingController(text: widget.session.baseUrl);
  }

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _baseUrl.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    final baseUrl = _baseUrl.text.trim();
    if (baseUrl.isEmpty) {
      setState(() => _error = 'Isi alamat server (base URL) dulu.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.session.saveBaseUrl(baseUrl);
      final api = ApiClient(baseUrl: baseUrl);
      final userJson = await api.login(_email.text.trim(), _password.text);
      await widget.session.saveLogin(api.token!, userJson);
      if (mounted) widget.onLoggedIn();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = 'Tidak dapat terhubung ke server: $e');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Pos-Kasir-Nuroo Karyawan')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'Masuk sebagai karyawan untuk absensi dan jadwal shift.',
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _baseUrl,
            decoration: const InputDecoration(
              labelText: 'Alamat server (base URL)',
              helperText:
                  'Emulator: http://10.0.2.2:8000. Perangkat fisik: alamat LAN server, mis. http://192.168.1.10:8000',
              border: OutlineInputBorder(),
            ),
            keyboardType: TextInputType.url,
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _email,
            decoration: const InputDecoration(
              labelText: 'Email',
              border: OutlineInputBorder(),
            ),
            keyboardType: TextInputType.emailAddress,
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _password,
            decoration: const InputDecoration(
              labelText: 'Kata sandi',
              border: OutlineInputBorder(),
            ),
            obscureText: true,
          ),
          const SizedBox(height: 16),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(_error!, style: const TextStyle(color: Colors.red)),
            ),
          FilledButton(
            onPressed: _busy ? null : _login,
            child: Text(_busy ? 'Memeriksa…' : 'Masuk'),
          ),
        ],
      ),
    );
  }
}

class HomeScreen extends StatefulWidget {
  final Session session;
  final VoidCallback onLoggedOut;

  const HomeScreen(
      {super.key, required this.session, required this.onLoggedOut});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _tab = 0;
  late ApiClient _api;
  final AttendanceState _state = AttendanceState();
  bool _loadingStatus = true;
  bool _busy = false;
  String? _notice;
  String? _error;

  @override
  void initState() {
    super.initState();
    _api =
        ApiClient(baseUrl: widget.session.baseUrl, token: widget.session.token);
    _refreshStatus();
  }

  Future<void> _refreshStatus() async {
    setState(() {
      _loadingStatus = true;
      _error = null;
    });
    try {
      final open = await _api.getOpenAttendance();
      setState(() => _state.syncFromOpen(open));
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = 'Tidak dapat memuat status absensi: $e');
    } finally {
      if (mounted) setState(() => _loadingStatus = false);
    }
  }

  Future<void> _checkIn() async {
    if (!_state.canCheckIn) return; // Guard klien.
    setState(() {
      _busy = true;
      _notice = null;
      _error = null;
    });
    try {
      final loc = await GeolocatorLocationProvider().currentLocation();
      final record = await _api.checkIn(
        outletId: widget.session.user?.outletId,
        latitude: loc.latitude,
        longitude: loc.longitude,
      );
      if (!_state.applyCheckIn(record)) {
        setState(() => _error = 'Anda sudah check-in.');
      } else {
        setState(() {
          _notice =
              'Check-in berhasil.${loc.note != null ? ' ${loc.note}' : ''}';
        });
      }
    } on ApiException catch (e) {
      // Pesan 422 server (mis. sudah check-in) ditampilkan apa adanya.
      setState(() => _error = e.message);
      await _refreshStatus();
    } catch (e) {
      setState(() => _error = 'Check-in gagal: $e');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _checkOut() async {
    if (!_state.canCheckOut) return; // Guard klien.
    setState(() {
      _busy = true;
      _notice = null;
      _error = null;
    });
    try {
      final loc = await GeolocatorLocationProvider().currentLocation();
      final record = await _api.checkOut(
        latitude: loc.latitude,
        longitude: loc.longitude,
      );
      if (!_state.applyCheckOut(record)) {
        setState(() => _error = 'Anda belum check-in.');
      } else {
        setState(() {
          _notice =
              'Check-out berhasil.${loc.note != null ? ' ${loc.note}' : ''}';
        });
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
      await _refreshStatus();
    } catch (e) {
      setState(() => _error = 'Check-out gagal: $e');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _logout() async {
    try {
      await _api.logout();
    } finally {
      await widget.session.clear();
      if (mounted) widget.onLoggedOut();
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.session.user;
    final pages = <Widget>[
      _StatusPage(
        state: _state,
        loading: _loadingStatus,
        busy: _busy,
        notice: _notice,
        error: _error,
        onCheckIn: _checkIn,
        onCheckOut: _checkOut,
        onRefresh: _refreshStatus,
      ),
      _HistoryPage(api: _api),
      _ShiftPage(api: _api),
    ];
    return Scaffold(
      appBar: AppBar(
        title: Text(user != null ? 'Halo, ${user.name}' : 'Karyawan'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Keluar',
            onPressed: _logout,
          ),
        ],
      ),
      body: pages[_tab],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home), label: 'Beranda'),
          NavigationDestination(icon: Icon(Icons.history), label: 'Riwayat'),
          NavigationDestination(icon: Icon(Icons.schedule), label: 'Shift'),
        ],
      ),
    );
  }
}

class _StatusPage extends StatelessWidget {
  final AttendanceState state;
  final bool loading;
  final bool busy;
  final String? notice;
  final String? error;
  final VoidCallback onCheckIn;
  final VoidCallback onCheckOut;
  final VoidCallback onRefresh;

  const _StatusPage({
    required this.state,
    required this.loading,
    required this.busy,
    this.notice,
    this.error,
    required this.onCheckIn,
    required this.onCheckOut,
    required this.onRefresh,
  });

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Center(child: CircularProgressIndicator());
    }
    final open = state.openAttendance;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  state.status == AttendanceStatus.checkedIn
                      ? 'Sedang bekerja'
                      : 'Belum check-in',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 8),
                if (open != null && open.checkedInAt != null)
                  Text('Check-in: ${open.checkedInAt}'),
                if (open != null && open.latitude != null)
                  Text('Lokasi check-in: ${open.latitude}, ${open.longitude}'),
                if (state.status == AttendanceStatus.checkedOut &&
                    state.lastClosedAttendance?.checkedOutAt != null)
                  Text(
                      'Check-out: ${state.lastClosedAttendance!.checkedOutAt}'),
                if (state.status != AttendanceStatus.checkedIn)
                  const Text(
                      'Tekan Check-in saat mulai bekerja, Check-out saat selesai.'),
              ],
            ),
          ),
        ),
        if (notice != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Text(notice!, style: const TextStyle(color: Colors.green)),
          ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Text(error!, style: const TextStyle(color: Colors.red)),
          ),
        const SizedBox(height: 16),
        FilledButton.icon(
          onPressed: (!busy && state.canCheckIn) ? onCheckIn : null,
          icon: const Icon(Icons.login),
          label: const Text('Check-in'),
        ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: (!busy && state.canCheckOut) ? onCheckOut : null,
          icon: const Icon(Icons.logout),
          label: const Text('Check-out'),
        ),
        const SizedBox(height: 8),
        TextButton.icon(
          onPressed: busy ? null : onRefresh,
          icon: const Icon(Icons.refresh),
          label: const Text('Perbarui status'),
        ),
        const Text(
          'Lokasi bersifat opsional: bila izin ditolak atau lokasi tidak '
          'tersedia, absensi tetap diproses tanpa koordinat.',
          style: TextStyle(color: Colors.grey),
        ),
      ],
    );
  }
}

class _HistoryPage extends StatelessWidget {
  final ApiClient api;
  const _HistoryPage({required this.api});

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<Attendance>>(
      future: api.getMyAttendance(),
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(child: Text('Gagal memuat riwayat: ${snapshot.error}'));
        }
        final items = snapshot.data ?? [];
        if (items.isEmpty) {
          return const Center(child: Text('Belum ada riwayat absensi.'));
        }
        return ListView.builder(
          itemCount: items.length,
          itemBuilder: (context, i) {
            final a = items[i];
            return ListTile(
              title: Text(a.checkedInAt ?? '-'),
              subtitle: Text(a.checkedOutAt == null
                  ? 'Masih check-in'
                  : 'Check-out: ${a.checkedOutAt}'),
            );
          },
        );
      },
    );
  }
}

class _ShiftPage extends StatelessWidget {
  final ApiClient api;
  const _ShiftPage({required this.api});

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<Shift>>(
      future: api.getMyShifts(),
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(child: Text('Gagal memuat shift: ${snapshot.error}'));
        }
        final items = snapshot.data ?? [];
        if (items.isEmpty) {
          return const Center(child: Text('Belum ada jadwal shift.'));
        }
        return ListView.builder(
          itemCount: items.length,
          itemBuilder: (context, i) {
            final s = items[i];
            return ListTile(
              title: Text('${s.date}  ${s.startTime}–${s.endTime}'),
              subtitle: Text([
                if (s.outletName != null) s.outletName!,
                if (s.note != null) s.note!,
              ].join(' • ')),
            );
          },
        );
      },
    );
  }
}
