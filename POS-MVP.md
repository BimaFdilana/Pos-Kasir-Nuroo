# Pos-Kasir-Nuroo — Web MVP (PRD v1.1, disetujui dengan revisi: Consumer App dibatalkan, QR order per meja masuk V2)

Web-first MVP only. No mobile app, no payment gateway, no QRIS/e-wallet
in this step.

## What is built

- Multi-tenant basics: `tenants`, `outlets` (per tenant), users with
  `role` = owner | manager | cashier, scoped to tenant (cashiers may be
  locked to one outlet). Sensitive actions (void/refund, product/outlet
  creation) require owner/manager.
- Catalogue: `categories`, `products`, `product_variants` (integer rupiah
  prices), `outlet_stocks` (quantity per outlet x variant).
- Sales: `sales` + `sale_items` (price/name snapshots) + `sale_payments`
  + `stock_movements` + `sale_audits`.
- Payments in MVP ONLY: `cash` (tendered + change) and `edc` manual
  (bank, debit/credit, approval code from the EDC slip). Full card
  numbers are never accepted or stored. EDC is standalone/manual —
  NOT ECR-integrated, NOT gateway settlement.
- Void/refund: full reversal only, restores stock, writes a
  `sale_audits` row (actor, approver, reason, total before/after).
- Web dashboard (plain Blade, no design system): login, dashboard with
  today's summary (total cash hari ini = jumlah nominal pembayaran cash,
  bukan jumlah transaksi berisi cash), products, outlets, categories,
  sales list/detail, multi-item cashier form with split cash+EDC
  payments (reusing the same SaleService as the API), product/outlet/
  category edit, archive-on-delete where sales history exists, and a
  stock adjustment form writing `adjustment` stock movements.
- API skeleton for the future mobile cashier, Sanctum-protected:
  `POST /api/v1/login`, `GET /api/v1/products`, `GET /api/v1/sales`,
  `POST /api/v1/sales` (with `client_reference` idempotency: a retry
  with the same reference for the same tenant returns the original
  sale with HTTP 200 and `duplicate: true`, it never double-decrements
  stock).

## Run locally

```bash
cd ~/workspace/pos-umkm
~/.config/herd-lite/bin/php artisan migrate
~/.config/herd-lite/bin/php artisan pos:create-user --tenant-name="Nama Usaha" --role=owner
# password is prompted interactively; it is never written to code
~/.config/herd-lite/bin/php artisan serve --port=8000
# open http://127.0.0.1:8000/login
```

Tests:

```bash
~/.config/herd-lite/bin/php artisan test
```

## Known limitations (not production-ready)

- Refund is full-only; partial refunds, discounts/tax UI, KDS,
  marketplace, payroll, CRM, and payment gateway are later phases.
  Consumer App is cancelled; QR ordering per table is V2, not MVP.
- No email verification, password reset UI, or rate limiting beyond
  Laravel defaults. No real EDC hardware integration (manual entry).
- `sale_audits.total_after` is 0 for void/refund (effective value of
  the cancelled sale); the sale row itself keeps its historical total.
- This is a development SQLite setup. Production needs PostgreSQL,
  backups, HTTPS, and a proper deployment pipeline.

## V2 langkah 1 — Meja, KDS, QR order per meja (belum commit/push)

- Meja: `dining_tables` (tenant + outlet, nama, status free/occupied,
  is_active, `qr_code` acak 32 karakter yang unik — BUKAN nomor meja
  sehingga tidak bisa ditebak). API owner/manager: list/buat/update
  meja per outlet, nonaktifkan, dan regenerasi kode QR. Halaman web
  Meja juga tersedia (owner/manager untuk buat/edit/regenerasi).
- Alur QR untuk pelanggan (tanpa login, tanpa install aplikasi):
  1. Owner/manager membuat meja, lalu mencetak/menempel QR untuk URL
     halaman tamu `/qr/{kode}` di meja tersebut (halaman ini memakai
     API yang sama di bawahnya).
  2. Pelanggan memindai QR dengan kamera HP, memilih menu, dan
     mengirim pesanan — nama dan nomor HP boleh kosong (pesan tamu).
  3. Pesanan masuk sebagai tagihan terbuka untuk meja itu (status
     `new`), meja menjadi `occupied`, dan order terlihat di KDS.
  4. KDS (halaman web `/kds`, untuk owner/manager/kasir) menampilkan
     order terbuka per outlet dikelompokkan per status, dengan tombol
     maju status (`new -> preparing -> ready -> served`) dan batal.
  5. Pembayaran TETAP di kasir dengan cash / EDC manual: dari KDS
     "Bayar di Kasir" membuka form konversi yang memakai SaleService
     yang sama dengan penjualan biasa (validasi stok dan pembayaran
     yang sama). Setelah lunas, order berstatus `paid`, tertaut ke
     nomor penjualan, dan meja kembali `free` bila tidak ada lagi
     order terbuka untuk meja itu.
- Endpoint: `GET /api/v1/qr/{code}` (outlet, meja, produk aktif),
  `POST /api/v1/qr/{code}/orders` (publik), `GET /api/v1/tables`,
  `POST /api/v1/tables`, `PUT /api/v1/tables/{id}`,
  `POST /api/v1/tables/{id}/regenerate`, `GET /api/v1/table-orders`
  (filter outlet/status), `POST /api/v1/table-orders/{id}/status`,
  `POST /api/v1/table-orders/{id}/convert` (Sanctum, tenant-scoped).
- Batasan yang perlu diketahui: stok TIDAK divalidasi saat order QR
  dibuat, melainkan saat konversi ke penjualan — bila stok habis di
  tengah jalan, konversi gagal 422 dan order tetap terbuka. Harga
  penjualan dihitung dari harga varian saat konversi, bukan dari
  snapshot harga saat order (bila harga berubah, total pembayaran
  harus sama dengan total penjualan yang baru). Konversi yang diulang
  mengembalikan penjualan yang sama (idempotency lewat
  `client_reference = qr-order-{id}`), tidak menggandakan stok.
  Halaman tamu QR masih sederhana (tanpa foto menu, tanpa struk
  digital, tanpa notifikasi real-time ke KDS — KDS perlu refresh
  manual). Yang TIDAK dibangun di langkah ini: inventori/resep/HPP/
  opname, CRM/loyalti, akuntansi, Consumer App, gateway/QRIS/e-wallet/
  ECR, marketplace, dan payroll.
