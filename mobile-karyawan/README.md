# Pos-Kasir-Nuroo Karyawan (Android)

Aplikasi Karyawan Pos-Kasir-Nuroo — terpisah dari aplikasi Kasir
(`../mobile/`). Fungsinya hanya: absensi (check-in / check-out) dan
jadwal shift milik sendiri. Aplikasi ini TIDAK menangani pembayaran:
tidak ada nomor kartu, gateway, atau data pembayaran apa pun.

- Application ID: `com.poskasirnuroo.karyawan`
- Label aplikasi: "Pos-Kasir-Nuroo Karyawan"
- Platform: Android saja (tahap ini)

## Cara menjalankan

```sh
# Backend Laravel harus jalan dulu (dari ~/workspace/pos-umkm):
php artisan serve   # http://127.0.0.1:8000

# Dari folder ini:
~/flutter-sdk/flutter/bin/flutter pub get
~/flutter-sdk/flutter/bin/flutter run
```

Saat login, isi **Alamat server (base URL)**:

- Emulator Android: `http://10.0.2.2:8000` (default, sudah terisi)
- Perangkat fisik: alamat LAN komputer yang menjalankan backend,
  mis. `http://192.168.1.10:8000` (pastikan perangkat satu jaringan
  dan backend bisa diakses dari LAN, mis. `php artisan serve --host 0.0.0.0`)

Akun yang dipakai adalah akun karyawan yang sama dengan backend
(email + kata sandi). Token Sanctum disimpan lokal; kata sandi tidak
disimpan.

## Fitur

- Login / logout (`POST /api/v1/login`, `POST /api/v1/logout`)
- Beranda: status absensi saat ini dari `GET /api/v1/attendance/open`
  (sedang check-in dengan waktunya, atau belum check-in), tombol
  Check-in dan Check-out
- Riwayat absensi sendiri: `GET /api/v1/attendance/me`
- Jadwal shift sendiri: `GET /api/v1/shifts/my`

## Endpoint yang dipakai

| Kebutuhan | Endpoint |
| --- | --- |
| Login | `POST /api/v1/login` `{email, password}` → `{token, user:{id,name,email,tenant_id,outlet_id,role}}` |
| Logout | `POST /api/v1/logout` (Bearer) |
| Status absensi | `GET /api/v1/attendance/open` (Bearer) → `{data: record \| null}` |
| Check-in | `POST /api/v1/attendance/check-in` (Bearer) `{outlet_id?, latitude?, longitude?, note?}` → 201 `{data}`; 422 bila sudah ada check-in terbuka |
| Check-out | `POST /api/v1/attendance/check-out` (Bearer) `{latitude?, longitude?, note?}` → 200 `{data}`; 422 bila tidak ada check-in terbuka |
| Riwayat | `GET /api/v1/attendance/me?date=` (Bearer) → paginator `{data:{data:[...]}}` |
| Shift | `GET /api/v1/shifts/my?from=&to=` (Bearer) → paginator `{data:{data:[{id,date,start_time,end_time,note,outlet:{id,name}}]}}` |

Field record absensi: `id, user_id, outlet_id, checked_in_at,
checked_out_at` (null selama terbuka), `latitude, longitude, note,
checkout_latitude, checkout_longitude, checkout_note`.

## Lokasi: opsional, best-effort

Lokasi memakai paket `geolocator`. Bila izin diberikan, koordinat
dilampirkan ke check-in/out. Bila izin ditolak, layanan lokasi mati,
atau lokasi tidak tersedia, absensi TETAP diproses tanpa koordinat
dan aplikasi menampilkan catatan bahwa lokasi tidak tersedia.
Absensi tidak pernah diblokir oleh lokasi.

## Guard klien & error server

- Tombol Check-in dinonaktifkan saat sudah check-in; tombol Check-out
  dinonaktifkan saat belum check-in (`lib/attendance_state.dart`).
- Bila server tetap menolak (mis. 422 "sudah check-in"), pesan server
  ditampilkan apa adanya, bukan crash, dan status disegarkan ulang
  dari `GET /api/v1/attendance/open`.

## Keterbatasan (jujur)

- **APK belum bisa dibangun di VM ini**: tidak ada Android SDK/Java,
  jadi `flutter build apk` tidak dapat dijalankan di sini.
- **Aplikasi belum pernah dijalankan di perangkat/emulator**: belum
  ada uji visual maupun uji end-to-end melawan backend live; yang
  terverifikasi di sini hanyalah `flutter analyze` dan `flutter test`
  (unit + widget, dengan HTTP mock).
- Lokasi hanya best-effort (lihat di atas); tidak ada validasi
  geofence terhadap outlet.
- Tidak ada mode offline: tanpa koneksi, absensi/riwayat/shift tidak
  bisa dimuat (tidak seperti aplikasi Kasir yang punya antrean offline).
- Riwayat dan shift saat ini tanpa filter tanggal di UI (endpoint
  mendukung `date` / `from` / `to`, tapi UI memanggil tanpa filter).
- Bukan Consumer App, bukan QR ordering, bukan payroll, bukan
  akuntansi — sengaja tidak dibangun di sini.
