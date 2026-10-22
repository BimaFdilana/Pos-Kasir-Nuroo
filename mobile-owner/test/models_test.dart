import 'package:flutter_test/flutter_test.dart';
import 'package:pos_kasir_nuroo_owner/models.dart';

Map<String, dynamic> sampleSummaryJson() => {
      'data': {
        'date': '2026-10-23',
        'sales_count': 3,
        'total': 350000,
        'cash_total': 200000,
        'per_outlet': [
          {
            'outlet_id': 1,
            'outlet_name': 'Cafe Pusat',
            'sales_count': 2,
            'total': 250000,
            'cash_total': 150000,
          },
          {
            'outlet_id': 2,
            'outlet_name': 'Cafe Cabang',
            'sales_count': 1,
            'total': 100000,
            'cash_total': 50000,
          },
        ],
        'low_stock': [
          {
            'outlet_id': 1,
            'outlet_name': 'Cafe Pusat',
            'product_variant_id': 10,
            'product_name': 'Kopi Susu',
            'variant_name': 'Reguler',
            'sku': 'KS-REG',
            'quantity': 3,
            'min_stock': 10,
          },
          {
            'outlet_id': 2,
            'outlet_name': 'Cafe Cabang',
            'product_variant_id': 11,
            'product_name': 'Roti',
            'variant_name': '',
            'sku': 'RT-01',
            'quantity': 5,
            'min_stock': 5,
          },
          {
            'outlet_id': 1,
            'outlet_name': 'Cafe Pusat',
            'product_variant_id': 12,
            'product_name': 'Teh',
            'variant_name': '',
            'sku': 'TH-01',
            'quantity': 6,
            'min_stock': 2,
          },
        ],
        'low_stock_threshold': 5,
      },
    };

Sale sampleSale({required int id, int? outletId}) => Sale(
      id: id,
      saleNumber: 'SALE-$id',
      outletId: outletId,
      status: 'completed',
      total: 100000,
      paidTotal: 100000,
      changeTotal: 0,
      createdAt: '2026-10-23T10:00:00',
      items: const [],
      payments: const [],
    );

void main() {
  test('owner summary parsing: fields, per-outlet, low-stock', () {
    final summary = OwnerSummary.fromJson(sampleSummaryJson());

    expect(summary.date, '2026-10-23');
    expect(summary.salesCount, 3);
    expect(summary.total, 350000);
    expect(summary.cashTotal, 200000);
    expect(summary.lowStockThreshold, 5);
    expect(summary.perOutlet.length, 2);
    expect(summary.perOutlet[0].outletName, 'Cafe Pusat');
    expect(summary.lowStock.length, 3);
    expect(summary.lowStock[0].productName, 'Kopi Susu');
    expect(summary.lowStock[0].sku, 'KS-REG');
    expect(summary.lowStock[0].minStock, 10);
  });

  test('totals: sum of per-outlet totals/cash/count matches summary', () {
    final summary = OwnerSummary.fromJson(sampleSummaryJson());

    expect(summary.totalFromPerOutlet, summary.total);
    expect(summary.cashTotalFromPerOutlet, summary.cashTotal);
    expect(summary.salesCountFromPerOutlet, summary.salesCount);
    // Nilai eksplisit, bukan hanya kesamaan antar-field.
    expect(summary.totalFromPerOutlet, 350000);
    expect(summary.cashTotalFromPerOutlet, 200000);
  });

  test('low-stock identification uses threshold (quantity <= threshold)',
      () {
    final summary = OwnerSummary.fromJson(sampleSummaryJson());

    // Server memakai quantity <= threshold (default 5): stok 3 dan 5
    // menipis, stok 6 tidak — meski min_stock teh hanya 2.
    final low = summary.lowStockAtOrBelowThreshold();
    expect(low.map((e) => e.productName), ['Kopi Susu', 'Roti']);
    expect(summary.lowStock[0].isLowStock(5), isTrue);
    expect(summary.lowStock[1].isLowStock(5), isTrue);
    expect(summary.lowStock[2].isLowStock(5), isFalse);
    // Ambang lebih ketat mengubah hasil dengan cara yang sama.
    expect(summary.lowStockAtOrBelowThreshold(3).length, 1);
  });

  test('sales client-side outlet filter', () {
    final sales = [
      sampleSale(id: 1, outletId: 1),
      sampleSale(id: 2, outletId: 2),
      sampleSale(id: 3, outletId: 1),
    ];

    expect(Sale.filterByOutlet(sales, null).length, 3);
    expect(
      Sale.filterByOutlet(sales, 1).map((s) => s.id),
      [1, 3],
    );
    expect(Sale.filterByOutlet(sales, 2).map((s) => s.id), [2]);
    expect(Sale.filterByOutlet(sales, 99), isEmpty);
  });

  test('rupiah formatting', () {
    expect(formatRupiah(350000), 'Rp350.000');
    expect(formatRupiah(0), 'Rp0');
    expect(formatRupiah(1500), 'Rp1.500');
  });

  test('only owner/manager count as supervisor', () {
    AppUser user(String role) => AppUser(
          id: 1,
          name: 'Tes',
          email: 'tes@example.com',
          tenantId: 1,
          outletId: null,
          role: role,
        );
    expect(user('owner').isSupervisor, isTrue);
    expect(user('manager').isSupervisor, isTrue);
    expect(user('cashier').isSupervisor, isFalse);
    expect(user('').isSupervisor, isFalse);
  });
}
