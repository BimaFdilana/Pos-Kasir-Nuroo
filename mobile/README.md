# Pos-Kasir-Nuroo — Aplikasi Kasir Android

Aplikasi kasir mobile (Android dulu; iOS fase berikutnya) untuk backend
Laravel di `~/workspace/pos-umkm`. Satu codebase Flutter, satu backend,
satu API yang sama dengan dashboard web.

## Yang dibuat di fase ini

- Login email/kata sandi via `POST /api/v1/login`, token Sanctum
  disimpan aman (FlutterSecureStorage), logout menghapus token.
- Daftar produk dari `GET /api/v1/products`, di-cache lokal sehingga
  aplikasi tetap bisa dibuka tanpa internet. Catatan: endpoint produk
  tidak mengembalikan stok per outlet, jadi stok hanya divalidasi
  server saat penjualan dibuat (gagal stok = 422, bukan antrean).
- Keranjang multi-item, ubah jumlah, pencarian produk, total live.
- Checkout split cash+EDC: cash dengan uang diterima/kembalian,
  EDC manual dengan bank, jenis kartu debit/kredit, dan kode approval
  dari slip. **Nomor kartu tidak pernah diminta atau disimpan.**
- Offline-first: bila jaringan gagal saat checkout, penjualan
  disimpan lokal dengan UUID `client_reference` dan status pending.
  Saat online kembali, antrean disinkronkan FIFO; server memakai
  `client_reference` sebagai kunci idempotensi sehingga kirim ulang
  tidak menggandakan penjualan atau mengurangi stok dua kali.
  Status tiap antrean terlihat jelas: pending / synced / failed.
- Riwayat: antrean lokal (dengan tombol sinkronkan dan coba lagi
  untuk yang gagal) dan riwayat server dari `GET /api/v1/sales`.
- Struk hanya pratinjau teks di aplikasi. **Cetak printer ESC/POS
  belum tersedia di fase ini** dan tidak dipalsukan di sini.

## Yang TIDAK dibuat di fase ini

Consumer App (dibatalkan sesuai keputusan), QR order per meja (V2),
payment gateway / QRIS / e-wallet / ECR, marketplace, payroll,
akuntansi, dan aplikasi iOS. Pembayaran di fase ini hanya cash dan
EDC manual.

## Flutter SDK di VM ini

SDK terpasang di:

```
~/flutter-sdk/flutter
```

Tambahkan ke PATH di setiap shell baru:

```bash
export PATH="$HOME/flutter-sdk/flutter/bin:$PATH"
flutter doctor
flutter --version
```

## Menjalankan backend + aplikasi

Backend (dari repo yang sama):

```bash
cd ~/workspace/pos-umkm
~/.config/herd-lite/bin/php artisan migrate
~/.config/herd-lite/bin/php artisan pos:create-user --tenant-name="Nama Usaha" --role=owner
~/.config/herd-lite/bin/php artisan serve --port=8000
```

Aplikasi (emulator Android):

```bash
cd ~/workspace/pos-umkm/mobile
export PATH="$HOME/flutter-sdk/flutter/bin:$PATH"
flutter pub get
flutter run
```

Alamat server default di aplikasi adalah `http://10.0.2.2:8000`
(backend di host, dilihat dari emulator Android). Untuk perangkat
fisik, ubah di layar Login atau Pengaturan ke alamat LAN komputer
backend, mis. `http://192.168.1.10:8000`, dan pastikan HP dan komputer
satu jaringan.

ID outlet: backend v1 belum punya endpoint daftar outlet, jadi ID
outlet diambil dari login (`user.outlet_id`). Bila akun tidak terikat
ke satu outlet, isi ID outlet di Pengaturan sebelum checkout;
bila kasir terikat ke satu outlet, server menolak outlet lain.

## Tes dan analisis

```bash
cd ~/workspace/pos-umkm/mobile
export PATH="$HOME/flutter-sdk/flutter/bin:$PATH"
flutter test
flutter analyze
```

## Batasan yang perlu diketahui

- Emulator/perangkat Android TIDAK tersedia di VM ini, jadi aplikasi
  belum pernah dijalankan di perangkat; yang terverifikasi di sini
  adalah `flutter analyze` dan `flutter test` saja.
- Sinkronisasi dipicu saat login, saat aplikasi dibuka, saat tombol
  "Sinkronkan sekarang" ditekan, dan saat konektivitas kembali
  (listener connectivity_plus). Tidak ada background service
  terpisah: aplikasi harus dibuka agar antrean terkirim.
- Antrean offline disimpan sebagai dokumen JSON di SharedPreferences.
  Cukup untuk antrean harian kasir; bila antrean tumbuh sangat besar,
  pindah ke database lokal (Drift/sqflite) pada fase berikutnya.
- Penjualan yang ditolak server (mis. stok tidak cukup) ditandai
  failed dan tidak dicoba ulang otomatis; kasir bisa menandainya
  pending lagi dari Riwayat setelah masalah diperbaiki.
- Membangun APK butuh Android SDK + Java, yang belum terpasang di
  VM ini; `flutter doctor` akan melaporkannya sebagai belum
  terpasang. Itu tidak menghalangi analisis dan tes unit/widget.
