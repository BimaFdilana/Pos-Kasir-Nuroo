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
