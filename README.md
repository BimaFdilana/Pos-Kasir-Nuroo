# Pos-Kasir-Nuroo

POS dan manajemen bisnis UMKM — web dulu, kasir mobile tahap berikutnya.

MVP web: tenant dan outlet, produk dan kategori, stok per outlet,
penjualan dengan pembayaran cash dan EDC manual (tanpa payment gateway,
tanpa QRIS/e-wallet, tanpa integrasi ECR), void/refund dengan audit,
dashboard web, dan API v1 Sanctum untuk kasir mobile di masa depan.

Consumer App dibatalkan sesuai keputusan produk; penggantinya adalah
QR order per meja di V2 (bukan di MVP ini).

## Jalankan lokal (dev, SQLite)

```bash
cd ~/workspace/pos-umkm
~/.config/herd-lite/bin/php artisan migrate
~/.config/herd-lite/bin/php artisan pos:create-user --tenant-name="Nama Usaha" --role=owner
~/.config/herd-lite/bin/php artisan serve --port=8000
# buka http://127.0.0.1:8000/login
```

## Tes

```bash
~/.config/herd-lite/bin/php artisan test
```

Lihat `POS-MVP.md` untuk cakupan, rute, dan batasan yang masih ada.
Data kartu tidak pernah disimpan; EDC di MVP adalah input manual dari
slip terminal (bank, jenis kartu, kode approval).
