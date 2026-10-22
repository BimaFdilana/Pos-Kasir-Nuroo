# Pos-Kasir-Nuroo Owner (Android)

Aplikasi Owner Android untuk Pos-Kasir-Nuroo. **Baca-saja** kecuali
login/logout: ringkasan penjualan hari ini, penjualan, dan stok menipis.
Aplikasi ini terpisah dari aplikasi kasir di `../mobile/` — kasir tidak
diubah sama sekali, begitu juga backend Laravel di `../`.

- applicationId: `com.poskasirnuroo.owner`
- Label aplikasi: "Pos-Kasir-Nuroo Owner"
- Flutter 3.35.1, Android saja (`flutter create --platforms android`).
  Catatan: namespace Android yang dihasilkan `flutter create` tetap
  `com.poskasirnuroo.pos_kasir_nuroo_owner`; yang diubah ke
  `com.poskasirnuroo.owner` adalah `applicationId` di
  `android/app/build.gradle.kts` (itulah ID yang dipakai Play Store /
  perangkat).

## Cara menjalankan

```sh
cd ~/workspace/pos-umkm/mobile-owner
~/flutter-sdk/flutter/bin/flutter pub get
~/flutter-sdk/flutter/bin/flutter run
```

Prasyarat: backend Laravel berjalan (mis. `php artisan serve` di
`~/workspace/pos-umkm` pada port 8000). Masuk dengan akun owner/manager.

- Emulator Android: alamat server default `http://10.0.2.2:8000`.
- Perangkat fisik: ganti kolom "Alamat server (base URL)" di layar login
  dengan alamat LAN server, mis. `http://192.168.1.10:8000`. Alamat yang
  terakhir dipakai disimpan di perangkat; kata sandi tidak disimpan.

## Endpoint yang dipakai

| Metode & path | Keterangan |
|---|---|
| `POST /api/v1/login` | `{email, password}` → `{token, user}`. Peran selain owner/manager ditolak di aplikasi dengan pesan jelas, tanpa masuk dashboard. |
| `POST /api/v1/logout` | Bearer. Kegagalan jaringan tidak menghalangi logout lokal. |
| `GET /api/v1/owner/summary?outlet_id=&threshold=5` | Bearer, owner/manager saja. Bila server mengembalikan 403, aplikasi menampilkan pesan bahwa aplikasi ini hanya untuk owner/manager. |
| `GET /api/v1/sales` | Bearer, paginator Laravel. **Tidak** difilter outlet di server, jadi aplikasi memfilter di sisi klien (`Sale.filterByOutlet`) saat outlet dipilih. |

Tidak ada endpoint daftar outlet khusus, jadi pemilih outlet dibangun
dari `summary.per_outlet` pada ringkasan tanpa filter, ditambah opsi
"Semua outlet". Saat outlet dipilih, aplikasi memuat ulang ringkasan
dengan `outlet_id` tersebut (angka untuk outlet itu) sambil tetap
memakai daftar outlet dari ringkasan tanpa filter.

## Fitur

- Login (base URL bisa diubah), logout, sesi tersimpan (token + data
  pengguna; kata sandi tidak disimpan).
- Dashboard: jumlah transaksi hari ini, total penjualan, total tunai,
  total per outlet, pemilih outlet (Semua outlet + per outlet).
- Daftar penjualan: nomor, status, total, tanggal; detail per penjualan
  (item, pembayaran cash/EDC manual, total dibayar, kembalian).
- Daftar stok menipis: nama produk/varian, outlet, SKU, kuantitas vs
  min. stok, dan ambang threshold.
- Tarik untuk refresh (pull-to-refresh) di dashboard.

## Aturan stok menipis

Server memasukkan stok ke `low_stock` bila `quantity <= threshold`
(parameter `threshold`, default 5 di aplikasi ini). Identifikasi stok
menipis di aplikasi memakai ambang yang sama — bukan sekadar
membandingkan kuantitas dengan `min_stock`. Nilai `min_stock` tetap
ditampilkan sebagai informasi tambahan.

## Batasan / belum terverifikasi

- **Tidak ada klaim APK**: VM ini tidak punya Android SDK/Java, jadi
  APK tidak bisa dibangun di sini. Aplikasi **belum pernah dijalankan
  di perangkat/emulator** — hanya `flutter analyze` dan `flutter test`
  yang terverifikasi.
- Hanya halaman pertama penjualan (paginasi server 50 item) yang
  ditampilkan; belum ada muat halaman berikutnya.
- Threshold stok menipis tetap 5 (nilai default server); belum ada
  pengaturan threshold di UI.
- Token disimpan di SharedPreferences (bukan secure storage) — cukup
  untuk MVP internal, bukan untuk distribusi publik tanpa peninjauan.
- Tidak ada di aplikasi ini, sesuai spesifikasi: Consumer App, QR
  ordering, payment gateway, marketplace, payroll, akuntansi,
  void/refund, dan edit produk. Pembayaran MVP hanya cash dan EDC
  manual; aplikasi tidak pernah meminta atau menyimpan nomor kartu
  (hanya menampilkan bank/tipe kartu/kode approval yang dikembalikan
  server untuk pembayaran EDC).
- Tidak ada kredensial, kata sandi, API key, atau nomor kartu di kode
  maupun konfigurasi aplikasi ini.
