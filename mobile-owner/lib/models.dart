/// Model baca-saja untuk API v1 Owner Pos-Kasir-Nuroo.
///
/// Bentuk JSON diambil dari backend Laravel (routes/api.php dan
/// OwnerSummaryController), bukan ditebak:
/// - GET /api/v1/owner/summary -> {data: {date, sales_count, total,
///   cash_total, per_outlet: [...], low_stock: [...],
///   low_stock_threshold}}
/// - GET /api/v1/sales -> paginator Laravel {data: [{id, sale_number,
///   outlet_id, status, total, paid_total, change_total, created_at,
///   items: [...], payments: [...]}]}
/// - Login -> {token, user: {id, name, email, tenant_id, outlet_id, role}}
///
/// Aturan stok menipis di server: stok masuk daftar low_stock bila
/// quantity <= threshold (parameter `threshold`, default 5). Jadi
/// identifikasi stok menipis di sini memakai ambang yang sama, BUKAN
/// sekadar membandingkan quantity dengan min_stock — min_stock hanya
/// ditampilkan sebagai informasi tambahan.
library;

int _asInt(dynamic value, [int fallback = 0]) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value) ?? fallback;
  return fallback;
}

String _asString(dynamic value, [String fallback = '']) {
  if (value is String) return value;
  if (value != null) return '$value';
  return fallback;
}

class AppUser {
  final int id;
  final String name;
  final String email;
  final int? tenantId;
  final int? outletId;
  final String role;

  const AppUser({
    required this.id,
    required this.name,
    required this.email,
    required this.tenantId,
    required this.outletId,
    required this.role,
  });

  bool get isSupervisor => role == 'owner' || role == 'manager';

  factory AppUser.fromJson(Map<String, dynamic> json) => AppUser(
        id: _asInt(json['id']),
        name: _asString(json['name']),
        email: _asString(json['email']),
        tenantId: json['tenant_id'] == null ? null : _asInt(json['tenant_id']),
        outletId: json['outlet_id'] == null ? null : _asInt(json['outlet_id']),
        role: _asString(json['role']),
      );
}

class OutletSummary {
  final int outletId;
  final String outletName;
  final int salesCount;
  final int total;
  final int cashTotal;

  const OutletSummary({
    required this.outletId,
    required this.outletName,
    required this.salesCount,
    required this.total,
    required this.cashTotal,
  });

  factory OutletSummary.fromJson(Map<String, dynamic> json) => OutletSummary(
        outletId: _asInt(json['outlet_id']),
        outletName: _asString(json['outlet_name']),
        salesCount: _asInt(json['sales_count']),
        total: _asInt(json['total']),
        cashTotal: _asInt(json['cash_total']),
      );
}

class LowStockItem {
  final int outletId;
  final String outletName;
  final int productVariantId;
  final String productName;
  final String variantName;
  final String sku;
  final int quantity;
  final int minStock;

  const LowStockItem({
    required this.outletId,
    required this.outletName,
    required this.productVariantId,
    required this.productName,
    required this.variantName,
    required this.sku,
    required this.quantity,
    required this.minStock,
  });

  /// Stok menipis menurut aturan server: quantity <= threshold.
  bool isLowStock(int threshold) => quantity <= threshold;

  factory LowStockItem.fromJson(Map<String, dynamic> json) => LowStockItem(
        outletId: _asInt(json['outlet_id']),
        outletName: _asString(json['outlet_name'], '-'),
        productVariantId: _asInt(json['product_variant_id']),
        productName: _asString(json['product_name'], '-'),
        variantName: _asString(json['variant_name']),
        sku: _asString(json['sku']),
        quantity: _asInt(json['quantity']),
        minStock: _asInt(json['min_stock']),
      );
}

class OwnerSummary {
  final String date;
  final int salesCount;
  final int total;
  final int cashTotal;
  final List<OutletSummary> perOutlet;
  final List<LowStockItem> lowStock;
  final int lowStockThreshold;

  const OwnerSummary({
    required this.date,
    required this.salesCount,
    required this.total,
    required this.cashTotal,
    required this.perOutlet,
    required this.lowStock,
    required this.lowStockThreshold,
  });

  factory OwnerSummary.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map<String, dynamic>
        ? json['data'] as Map<String, dynamic>
        : json;
    final perOutletRaw = data['per_outlet'] as List<dynamic>? ?? [];
    final lowStockRaw = data['low_stock'] as List<dynamic>? ?? [];
    return OwnerSummary(
      date: _asString(data['date']),
      salesCount: _asInt(data['sales_count']),
      total: _asInt(data['total']),
      cashTotal: _asInt(data['cash_total']),
      perOutlet: perOutletRaw
          .map((e) => OutletSummary.fromJson(e as Map<String, dynamic>))
          .toList(),
      lowStock: lowStockRaw
          .map((e) => LowStockItem.fromJson(e as Map<String, dynamic>))
          .toList(),
      lowStockThreshold: _asInt(data['low_stock_threshold'], 5),
    );
  }

  /// Total dari per-outlet harus sama dengan total ringkasan.
  /// Dipakai di tes dan sebagai cek konsistensi tampilan.
  int get totalFromPerOutlet =>
      perOutlet.fold<int>(0, (sum, outlet) => sum + outlet.total);

  int get cashTotalFromPerOutlet =>
      perOutlet.fold<int>(0, (sum, outlet) => sum + outlet.cashTotal);

  int get salesCountFromPerOutlet =>
      perOutlet.fold<int>(0, (sum, outlet) => sum + outlet.salesCount);

  /// Daftar stok menipis yang lolos ambang (server sudah memfilter,
  /// fungsi ini menjaga invarian yang sama bila daftar difilter ulang
  /// di sisi klien, mis. setelah threshold diubah).
  List<LowStockItem> lowStockAtOrBelowThreshold([int? threshold]) {
    final t = threshold ?? lowStockThreshold;
    return lowStock.where((item) => item.isLowStock(t)).toList();
  }
}

class SaleItem {
  final String productName;
  final String variantName;
  final int unitPrice;
  final int quantity;
  final int subtotal;

  const SaleItem({
    required this.productName,
    required this.variantName,
    required this.unitPrice,
    required this.quantity,
    required this.subtotal,
  });

  factory SaleItem.fromJson(Map<String, dynamic> json) => SaleItem(
        productName: _asString(json['product_name'], '-'),
        variantName: _asString(json['variant_name']),
        unitPrice: _asInt(json['unit_price']),
        quantity: _asInt(json['quantity']),
        subtotal: _asInt(json['subtotal']),
      );
}

class SalePayment {
  final String method; // cash | edc (MVP; tidak ada nomor kartu di sini)
  final int amount;
  final String? bank;
  final String? cardType; // debit | credit
  final String? approvalCode;

  const SalePayment({
    required this.method,
    required this.amount,
    required this.bank,
    required this.cardType,
    required this.approvalCode,
  });

  factory SalePayment.fromJson(Map<String, dynamic> json) => SalePayment(
        method: _asString(json['method']),
        amount: _asInt(json['amount']),
        bank: json['bank'] as String?,
        cardType: json['card_type'] as String?,
        approvalCode: json['approval_code'] as String?,
      );
}

class Sale {
  final int id;
  final String saleNumber;
  final int? outletId;
  final String status;
  final int total;
  final int paidTotal;
  final int changeTotal;
  final String createdAt;
  final List<SaleItem> items;
  final List<SalePayment> payments;

  const Sale({
    required this.id,
    required this.saleNumber,
    required this.outletId,
    required this.status,
    required this.total,
    required this.paidTotal,
    required this.changeTotal,
    required this.createdAt,
    required this.items,
    required this.payments,
  });

  factory Sale.fromJson(Map<String, dynamic> json) {
    final itemsRaw = json['items'] as List<dynamic>? ?? [];
    final paymentsRaw = json['payments'] as List<dynamic>? ?? [];
    return Sale(
      id: _asInt(json['id']),
      saleNumber: _asString(json['sale_number']),
      outletId: json['outlet_id'] == null ? null : _asInt(json['outlet_id']),
      status: _asString(json['status']),
      total: _asInt(json['total']),
      paidTotal: _asInt(json['paid_total']),
      changeTotal: _asInt(json['change_total']),
      createdAt: _asString(json['created_at']),
      items: itemsRaw
          .map((e) => SaleItem.fromJson(e as Map<String, dynamic>))
          .toList(),
      payments: paymentsRaw
          .map((e) => SalePayment.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  /// Daftar penjualan TIDAK difilter outlet di server, jadi filter
  /// dilakukan di sisi klien. Bila [outletId] null, semua penjualan
  /// dikembalikan (mode "Semua outlet").
  static List<Sale> filterByOutlet(List<Sale> sales, int? outletId) {
    if (outletId == null) return List<Sale>.of(sales);
    return sales.where((sale) => sale.outletId == outletId).toList();
  }
}

/// Format rupiah sederhana: 150000 -> "Rp150.000".
String formatRupiah(int amount) {
  final negative = amount < 0;
  final digits = amount.abs().toString();
  final buffer = StringBuffer();
  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) buffer.write('.');
    buffer.write(digits[i]);
  }
  return '${negative ? '-' : ''}Rp${buffer.toString()}';
}
