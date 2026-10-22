/// Model murni (tanpa dependensi Flutter) untuk Pos-Kasir-Nuroo.
///
/// Uang selalu integer rupiah, sesuai backend Laravel (SaleService).
/// Nomor kartu TIDAK PERNAH ada di model mana pun di aplikasi ini:
/// pembayaran EDC hanya menyimpan bank, jenis kartu (debit/kredit),
/// dan kode approval dari slip EDC.
class Variant {
  final int id;
  final String name;
  final String? sku;
  final int price;

  const Variant({
    required this.id,
    required this.name,
    this.sku,
    required this.price,
  });

  factory Variant.fromJson(Map<String, dynamic> json) => Variant(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String,
        sku: json['sku'] as String?,
        price: (json['price'] as num).toInt(),
      );

  Map<String, dynamic> toJson() =>
      {'id': id, 'name': name, 'sku': sku, 'price': price};
}

class Product {
  final int id;
  final String name;
  final String? sku;
  final String? category;
  final List<Variant> variants;

  const Product({
    required this.id,
    required this.name,
    this.sku,
    this.category,
    required this.variants,
  });

  factory Product.fromJson(Map<String, dynamic> json) => Product(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String,
        sku: json['sku'] as String?,
        category: json['category'] as String?,
        variants: (json['variants'] as List<dynamic>? ?? [])
            .map((v) => Variant.fromJson(v as Map<String, dynamic>))
            .toList(),
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'sku': sku,
        'category': category,
        'variants': variants.map((v) => v.toJson()).toList(),
      };
}

class CartLine {
  final int variantId;
  final String productName;
  final String variantName;
  final int unitPrice;
  int quantity;

  CartLine({
    required this.variantId,
    required this.productName,
    required this.variantName,
    required this.unitPrice,
    this.quantity = 1,
  });

  int get lineTotal => unitPrice * quantity;

  Map<String, dynamic> toJson() => {
        'product_variant_id': variantId,
        'product_name': productName,
        'variant_name': variantName,
        'unit_price': unitPrice,
        'quantity': quantity,
      };

  factory CartLine.fromJson(Map<String, dynamic> json) => CartLine(
        variantId: (json['product_variant_id'] as num).toInt(),
        productName: json['product_name'] as String? ?? '',
        variantName: json['variant_name'] as String? ?? '',
        unitPrice: (json['unit_price'] as num).toInt(),
        quantity: (json['quantity'] as num).toInt(),
      );
}

class Cart {
  final List<CartLine> lines = [];

  int get total => lines.fold(0, (sum, line) => sum + line.lineTotal);
  int get itemCount => lines.fold(0, (sum, line) => sum + line.quantity);
  bool get isEmpty => lines.isEmpty;

  void add(Variant variant, String productName) {
    for (final line in lines) {
      if (line.variantId == variant.id) {
        line.quantity += 1;
        return;
      }
    }
    lines.add(CartLine(
      variantId: variant.id,
      productName: productName,
      variantName: variant.name,
      unitPrice: variant.price,
    ));
  }

  void setQuantity(int variantId, int quantity) {
    if (quantity <= 0) {
      lines.removeWhere((line) => line.variantId == variantId);
      return;
    }
    for (final line in lines) {
      if (line.variantId == variantId) line.quantity = quantity;
    }
  }

  void clear() => lines.clear();
}

/// Satu baris pembayaran pada checkout. Metode MVP saja: cash, edc.
class PaymentDraft {
  String method; // 'cash' | 'edc'
  int amount;
  int? tendered; // cash saja
  String? bank; // edc saja
  String? cardType; // edc saja: 'debit' | 'credit'
  String? approvalCode; // edc saja, dari slip EDC

  PaymentDraft({
    required this.method,
    required this.amount,
    this.tendered,
    this.bank,
    this.cardType,
    this.approvalCode,
  });

  /// Kembalian untuk pembayaran cash ini (0 untuk EDC).
  int get change => method == 'cash' ? (tendered ?? amount) - amount : 0;

  Map<String, dynamic> toJson() {
    final map = <String, dynamic>{'method': method, 'amount': amount};
    if (method == 'cash') map['tendered'] = tendered ?? amount;
    if (method == 'edc') {
      if (bank != null) map['bank'] = bank;
      if (cardType != null) map['card_type'] = cardType;
      if (approvalCode != null) map['approval_code'] = approvalCode;
    }
    return map;
  }

  factory PaymentDraft.fromJson(Map<String, dynamic> json) => PaymentDraft(
        method: json['method'] as String,
        amount: (json['amount'] as num).toInt(),
        tendered: (json['tendered'] as num?)?.toInt(),
        bank: json['bank'] as String?,
        cardType: json['card_type'] as String?,
        approvalCode: json['approval_code'] as String?,
      );
}

/// Validasi pembayaran checkout. Mengembalikan pesan error, atau null
/// bila valid. Aturan ini mencerminkan SaleService backend: total
/// pembayaran harus sama persis dengan total belanja, dan uang cash
/// yang diserahkan tidak boleh kurang dari nominal cash-nya.
String? validatePayments(int total, List<PaymentDraft> payments) {
  if (payments.isEmpty) return 'Tambahkan minimal satu pembayaran.';
  var paid = 0;
  for (final payment in payments) {
    if (payment.amount < 1) return 'Nominal pembayaran harus lebih dari 0.';
    if (payment.method == 'cash' &&
        (payment.tendered ?? payment.amount) < payment.amount) {
      return 'Uang cash yang diserahkan kurang dari nominalnya.';
    }
    if (payment.method == 'edc' &&
        payment.cardType != null &&
        payment.cardType != 'debit' &&
        payment.cardType != 'credit') {
      return 'Jenis kartu EDC harus debit atau kredit.';
    }
    paid += payment.amount;
  }
  if (paid != total) return 'Total pembayaran harus sama dengan total belanja.';
  return null;
}

int totalChange(List<PaymentDraft> payments) =>
    payments.fold(0, (sum, payment) => sum + payment.change);

/// Penjualan yang menunggu sinkronisasi (atau sudah tersinkron).
/// [status]: 'pending' | 'synced' | 'failed'.
/// [clientReference] adalah UUID v4 yang dibuat di perangkat; server
/// memakai field ini sebagai kunci idempotensi sehingga percobaan
/// kirim ulang tidak pernah menggandakan penjualan atau stok.
class QueuedSale {
  final String clientReference;
  final int outletId;
  final List<CartLine> items;
  final List<PaymentDraft> payments;
  final int total;
  String status;
  String? serverSaleNumber;
  String? error;
  final DateTime createdAt;

  QueuedSale({
    required this.clientReference,
    required this.outletId,
    required this.items,
    required this.payments,
    required this.total,
    this.status = 'pending',
    this.serverSaleNumber,
    this.error,
    DateTime? createdAt,
  }) : createdAt = createdAt ?? DateTime.now();

  Map<String, dynamic> toJson() => {
        'client_reference': clientReference,
        'outlet_id': outletId,
        'items': items.map((i) => i.toJson()).toList(),
        'payments': payments.map((p) => p.toJson()).toList(),
        'total': total,
        'status': status,
        'server_sale_number': serverSaleNumber,
        'error': error,
        'created_at': createdAt.toIso8601String(),
      };

  factory QueuedSale.fromJson(Map<String, dynamic> json) => QueuedSale(
        clientReference: json['client_reference'] as String,
        outletId: (json['outlet_id'] as num).toInt(),
        items: (json['items'] as List<dynamic>)
            .map((i) => CartLine.fromJson(i as Map<String, dynamic>))
            .toList(),
        payments: (json['payments'] as List<dynamic>)
            .map((p) => PaymentDraft.fromJson(p as Map<String, dynamic>))
            .toList(),
        total: (json['total'] as num).toInt(),
        status: json['status'] as String? ?? 'pending',
        serverSaleNumber: json['server_sale_number'] as String?,
        error: json['error'] as String?,
        createdAt: DateTime.parse(json['created_at'] as String),
      );

  /// Body persis untuk POST /api/v1/sales (lihat SaleController backend).
  Map<String, dynamic> toSaleRequest() => {
        'outlet_id': outletId,
        'client_reference': clientReference,
        'items': items
            .map((i) => {
                  'product_variant_id': i.variantId,
                  'quantity': i.quantity,
                })
            .toList(),
        'payments': payments.map((p) => p.toJson()).toList(),
      };
}

/// Pratinjau struk teks di dalam aplikasi. Ini BUKAN cetak printer:
/// integrasi printer ESC/POS adalah tahap berikutnya.
String receiptText(QueuedSale sale) {
  final buffer = StringBuffer();
  buffer.writeln('Pos-Kasir-Nuroo');
  buffer.writeln('No: ${sale.serverSaleNumber ?? 'MENUNGGU SINKRONISASI'}');
  buffer.writeln('Status: ${sale.status}');
  buffer.writeln('--------------------------------');
  for (final item in sale.items) {
    buffer.writeln(
        '${item.productName} (${item.variantName}) x${item.quantity}');
    buffer.writeln('  Rp ${item.lineTotal}');
  }
  buffer.writeln('--------------------------------');
  buffer.writeln('TOTAL: Rp ${sale.total}');
  for (final payment in sale.payments) {
    if (payment.method == 'cash') {
      buffer.writeln(
          'Cash: Rp ${payment.amount} (diterima Rp ${payment.tendered ?? payment.amount}, kembali Rp ${payment.change})');
    } else {
      buffer.writeln(
          'EDC: Rp ${payment.amount} ${payment.bank ?? ''} ${payment.cardType ?? ''} approval: ${payment.approvalCode ?? '-'}');
    }
  }
  buffer.writeln('Terima kasih.');
  return buffer.toString();
}

String formatRupiah(int amount) {
  final text = amount.toString();
  final buffer = StringBuffer();
  for (var i = 0; i < text.length; i++) {
    if (i > 0 && (text.length - i) % 3 == 0) buffer.write('.');
    buffer.write(text[i]);
  }
  return 'Rp ${buffer.toString()}';
}
