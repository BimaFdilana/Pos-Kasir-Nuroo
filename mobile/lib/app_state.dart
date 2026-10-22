import 'dart:async';
import 'dart:io';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:uuid/uuid.dart';

import 'api_client.dart';
import 'models.dart';
import 'storage.dart';

/// Status sinkronisasi yang terlihat kasir.
enum SyncStatus { idle, syncing, offline, error }

/// State aplikasi: autentikasi, katalog, keranjang, dan antrean offline.
///
/// Alur offline-first (disengaja sederhana dan jujur):
/// 1. Checkout mencoba POST langsung ke server.
/// 2. Bila jaringan gagal (SocketException/ClientException), penjualan
///    disimpan lokal sebagai 'pending' dan keranjang dikosongkan.
///    Struknya tetap bisa ditampilkan dengan label MENUNGGU SINKRONISASI.
/// 3. Bila server menolak (422, mis. stok tidak cukup), penjualan TIDAK
///    diantrekan: kasir melihat pesan error dan keranjang tetap utuh.
/// 4. Sinkronisasi dipicu saat: login, aplikasi dibuka, tombol
///    "Sinkronkan sekarang", dan saat konektivitas kembali (listener
///    connectivity_plus). Tidak ada background service terpisah di
///    fase ini; aplikasi harus dibuka agar antrean terkirim.
/// 5. Antrean diproses FIFO. Penjualan yang gagal validasi di server
///    ditandai 'failed' beserta pesannya dan tidak dicoba ulang
///    otomatis; kasir bisa menandainya pending lagi dari Riwayat.
///    Kegagalan jaringan menghentikan proses (sisa antrean tetap pending).
class AppState extends ChangeNotifier {
  ApiClient api;
  final AppStorage storage;

  AppState({ApiClient? api, AppStorage? storage})
      : api = api ??
            ApiClient(baseUrl: 'http://10.0.2.2:8000'),
        storage = storage ?? AppStorage();

  Map<String, dynamic>? user;
  List<Product> products = [];
  final Cart cart = Cart();
  List<QueuedSale> queue = [];
  SyncStatus syncStatus = SyncStatus.idle;
  String? lastSyncError;
  bool initialized = false;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySub;

  bool get isLoggedIn => user != null && api.token != null;

  int? get outletId {
    final fromUser = user?['outlet_id'];
    if (fromUser is int) return fromUser;
    if (fromUser is num) return fromUser.toInt();
    return null;
  }

  Future<void> init() async {
    if (initialized) return;
    try {
      final baseUrl = await storage.readBaseUrl();
      api = ApiClient(baseUrl: baseUrl, token: await storage.readToken());
      user = await storage.readCachedUser();
      if (api.token == null) user = null;
      products = await storage.readCachedProducts();
      queue = await storage.readQueue();
    } catch (_) {
      // Aplikasi tetap bisa dibuka offline dengan cache yang ada.
    }
    initialized = true;
    notifyListeners();
    _connectivitySub = Connectivity()
        .onConnectivityChanged
        .listen((results) {
      if (results.any((r) => r != ConnectivityResult.none)) {
        unawaited(syncPending());
      }
    });
    if (isLoggedIn) unawaited(syncPending());
  }

  @override
  void dispose() {
    _connectivitySub?.cancel();
    super.dispose();
  }

  Future<void> setBaseUrl(String value) async {
    await storage.writeBaseUrl(value);
    api = ApiClient(baseUrl: value, token: api.token);
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    final loggedInUser = await api.login(email, password);
    user = loggedInUser;
    await storage.writeToken(api.token!);
    await storage.writeCachedUser(loggedInUser);
    notifyListeners();
    await refreshProducts();
    await syncPending();
  }

  Future<void> logout() async {
    try {
      await api.logout();
    } catch (_) {
      // Logout lokal tetap jalan walau server tidak terjangkau.
    }
    user = null;
    cart.clear();
    await storage.clearToken();
    await storage.clearCachedUser();
    notifyListeners();
  }

  Future<void> refreshProducts() async {
    try {
      final fresh = await api.getProducts();
      products = fresh;
      await storage.writeCachedProducts(fresh);
    } on SocketException {
      // Tetap pakai cache lokal.
    } on http.ClientException {
      // Tetap pakai cache lokal.
    } catch (_) {
      // Error lain (mis. 401) dibiarkan: UI memakai cache.
    }
    notifyListeners();
  }

  List<Product> searchProducts(String query) {
    final q = query.trim().toLowerCase();
    if (q.isEmpty) return products;
    return products
        .where((p) =>
            p.name.toLowerCase().contains(q) ||
            (p.sku ?? '').toLowerCase().contains(q) ||
            p.variants.any((v) => v.name.toLowerCase().contains(q)))
        .toList();
  }

  void addToCart(Variant variant, String productName) {
    cart.add(variant, productName);
    notifyListeners();
  }

  void setCartQuantity(int variantId, int quantity) {
    cart.setQuantity(variantId, quantity);
    notifyListeners();
  }

  void clearCart() {
    cart.clear();
    notifyListeners();
  }

  /// Checkout. Mengembalikan penjualan yang dibuat dan apakah penjualan
  /// itu langsung tersinkron ke server (true) atau tersimpan offline
  /// (false). Melempar [ApiException] bila server menolak dan
  /// [StateError] bila ID outlet belum diketahui.
  ///
  /// Catatan API: backend TIDAK punya endpoint daftar outlet, jadi
  /// ID outlet diambil dari login (user.outlet_id). Bila login tidak
  /// membawa outlet_id, kasir wajib mengisinya di Pengaturan
  /// (disimpan lokal) sebelum checkout.
  Future<({QueuedSale sale, bool synced})> checkout(
    List<PaymentDraft> payments, {
    int? outletIdOverride,
  }) async {
    final resolvedOutletId =
        outletIdOverride ?? outletId ?? await storage.readOutletId();
    if (resolvedOutletId == null) {
      throw StateError(
          'ID outlet belum diketahui. Atur ID outlet di Pengaturan, atau pakai akun kasir yang terikat ke satu outlet.');
    }
    final error = validatePayments(cart.total, payments);
    if (error != null) throw ApiException(error);
    if (cart.isEmpty) throw ApiException('Keranjang masih kosong.');

    final sale = QueuedSale(
      clientReference: const Uuid().v4(),
      outletId: resolvedOutletId,
      items: cart.lines
          .map((line) => CartLine(
                variantId: line.variantId,
                productName: line.productName,
                variantName: line.variantName,
                unitPrice: line.unitPrice,
                quantity: line.quantity,
              ))
          .toList(),
      payments: payments,
      total: cart.total,
    );

    try {
      final result = await api.postSale(sale.toSaleRequest());
      sale.status = 'synced';
      sale.serverSaleNumber = result.saleNumber;
      sale.error = null;
      queue.insert(0, sale);
      await storage.writeQueue(queue);
      cart.clear();
      notifyListeners();
      return (sale: sale, synced: true);
    } on SocketException {
      return _queueOffline(sale);
    } on http.ClientException {
      return _queueOffline(sale);
    }
    // ApiException (422 dsb.) sengaja tidak ditangkap: kasir harus
    // melihat pesan server dan keranjang tetap utuh.
  }

  Future<({QueuedSale sale, bool synced})> _queueOffline(
      QueuedSale sale) async {
    sale.status = 'pending';
    queue.insert(0, sale);
    await storage.writeQueue(queue);
    cart.clear();
    syncStatus = SyncStatus.offline;
    notifyListeners();
    return (sale: sale, synced: false);
  }

  /// Kirim antrean pending secara FIFO. Lihat dokumentasi kelas untuk
  /// perilaku gagal-validasi vs gagal-jaringan.
  Future<void> syncPending() async {
    final pending = queue.where((q) => q.status == 'pending').toList();
    if (pending.isEmpty || api.token == null) return;
    if (syncStatus == SyncStatus.syncing) return;
    syncStatus = SyncStatus.syncing;
    lastSyncError = null;
    notifyListeners();
    // Urutkan dari yang paling lama dibuat dulu.
    pending.sort((a, b) => a.createdAt.compareTo(b.createdAt));
    for (final sale in pending) {
      try {
        final result = await api.postSale(sale.toSaleRequest());
        sale.status = 'synced';
        sale.serverSaleNumber = result.saleNumber;
        sale.error = null;
      } on SocketException {
        syncStatus = SyncStatus.offline;
        break;
      } on http.ClientException {
        syncStatus = SyncStatus.offline;
        break;
      } on ApiException catch (e) {
        sale.status = 'failed';
        sale.error = e.message;
        // Lanjut ke antrean berikutnya: kegagalan satu penjualan
        // (mis. stok) tidak boleh memblokir penjualan lain.
        continue;
      }
    }
    if (syncStatus == SyncStatus.syncing) syncStatus = SyncStatus.idle;
    await storage.writeQueue(queue);
    notifyListeners();
  }

  Future<void> retryFailed(QueuedSale sale) async {
    sale.status = 'pending';
    sale.error = null;
    await storage.writeQueue(queue);
    notifyListeners();
    await syncPending();
  }

  Future<void> setOutletIdSetting(int? value) async {
    await storage.writeOutletId(value);
    notifyListeners();
  }

  Future<int?> readOutletIdSetting() => storage.readOutletId();
  Future<String> readBaseUrlSetting() => storage.readBaseUrl();
}
