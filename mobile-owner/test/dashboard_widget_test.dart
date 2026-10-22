import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_kasir_nuroo_owner/models.dart';
import 'package:pos_kasir_nuroo_owner/screens/dashboard_screen.dart';

OwnerSummary sampleSummary() => const OwnerSummary(
      date: '2026-10-23',
      salesCount: 3,
      total: 350000,
      cashTotal: 200000,
      perOutlet: [
        OutletSummary(
          outletId: 1,
          outletName: 'Cafe Pusat',
          salesCount: 2,
          total: 250000,
          cashTotal: 150000,
        ),
        OutletSummary(
          outletId: 2,
          outletName: 'Cafe Cabang',
          salesCount: 1,
          total: 100000,
          cashTotal: 50000,
        ),
      ],
      lowStock: [
        LowStockItem(
          outletId: 1,
          outletName: 'Cafe Pusat',
          productVariantId: 10,
          productName: 'Kopi Susu',
          variantName: 'Reguler',
          sku: 'KS-REG',
          quantity: 3,
          minStock: 10,
        ),
      ],
      lowStockThreshold: 5,
    );

Sale sampleSale() => const Sale(
      id: 1,
      saleNumber: 'SALE-1',
      outletId: 1,
      status: 'completed',
      total: 100000,
      paidTotal: 100000,
      changeTotal: 0,
      createdAt: '2026-10-23T10:00:00',
      items: [
        SaleItem(
          productName: 'Kopi Susu',
          variantName: 'Reguler',
          unitPrice: 50000,
          quantity: 2,
          subtotal: 100000,
        ),
      ],
      payments: [
        SalePayment(
          method: 'cash',
          amount: 100000,
          bank: null,
          cardType: null,
          approvalCode: null,
        ),
      ],
    );

void main() {
  testWidgets('dashboard renders summary, outlets, sale and low stock',
      (tester) async {
    int? selectedOutletId = 1;
    await tester.pumpWidget(
      MaterialApp(
        home: DashboardScreen(
          summary: sampleSummary(),
          allOutlets: sampleSummary().perOutlet,
          sales: [sampleSale()],
          selectedOutletId: selectedOutletId,
          onOutletChanged: (value) => selectedOutletId = value,
          onRefresh: () async {},
          onLogout: () {},
        ),
      ),
    );

    expect(find.text('Ringkasan Owner'), findsOneWidget);
    expect(find.text('Penjualan hari ini (2026-10-23)'), findsOneWidget);
    expect(find.text('Rp350.000'), findsWidgets);
    expect(find.text('Rp200.000'), findsWidgets);
    expect(find.text('Cafe Pusat'), findsWidgets);
    expect(find.text('Cafe Cabang'), findsWidgets);
    // Dropdown tertutup menampilkan outlet terpilih; opsi "Semua outlet"
    // baru terlihat setelah dropdown dibuka.
    await tester.tap(find.byType(DropdownButtonFormField<int?>));
    await tester.pumpAndSettle();
    expect(find.text('Semua outlet'), findsOneWidget);
    await tester.tap(find.text('Semua outlet'));
    await tester.pumpAndSettle();
    expect(find.text('SALE-1'), findsOneWidget);
    // Detail penjualan ada di dalam ExpansionTile yang tertutup.
    await tester.tap(find.text('SALE-1'));
    await tester.pumpAndSettle();
    expect(find.text('Kopi Susu — Reguler'), findsWidgets);
    // Stok menipis ada di bawah lipatan: gulir sampai terlihat
    // (ListView membangun item secara malas).
    await tester.scrollUntilVisible(
      find.textContaining('min. stok 10'),
      200,
    );
    await tester.pumpAndSettle();
    // Stok menipis: nama produk + kuantitas + min. stok tampil.
    expect(find.textContaining('min. stok 10'), findsOneWidget);
    // Layar baca-saja ini tidak punya input apa pun untuk pembayaran
    // (tidak ada TextField; satu-satunya kontrol adalah dropdown outlet).
    expect(find.byType(TextField), findsNothing);
  });
}
