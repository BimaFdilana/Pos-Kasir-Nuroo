import 'package:flutter_test/flutter_test.dart';
import 'package:pos_kasir_nuroo/models.dart';

void main() {
  group('Cart totals', () {
    test('multi-item total sums price times quantity', () {
      final cart = Cart();
      cart.add(const Variant(id: 1, name: 'Reguler', price: 25000), 'Kopi');
      cart.add(const Variant(id: 1, name: 'Reguler', price: 25000), 'Kopi');
      cart.add(const Variant(id: 2, name: 'Besar', price: 35000), 'Kopi');
      expect(cart.total, 85000);
      expect(cart.itemCount, 3);
    });

    test('setting quantity to zero removes the line', () {
      final cart = Cart();
      cart.add(const Variant(id: 1, name: 'Reguler', price: 10000), 'Teh');
      cart.setQuantity(1, 0);
      expect(cart.isEmpty, isTrue);
      expect(cart.total, 0);
    });

    test('adding the same variant twice increments quantity, not lines', () {
      final cart = Cart();
      cart.add(const Variant(id: 7, name: 'Porsi', price: 15000), 'Nasi');
      cart.add(const Variant(id: 7, name: 'Porsi', price: 15000), 'Nasi');
      expect(cart.lines.length, 1);
      expect(cart.lines.first.quantity, 2);
    });
  });

  group('Cash change and split payments', () {
    test('cash change is tendered minus amount', () {
      final payment = PaymentDraft(method: 'cash', amount: 40000, tendered: 50000);
      expect(payment.change, 10000);
      expect(totalChange([payment]), 10000);
    });

    test('EDC payment has no change', () {
      final payment = PaymentDraft(
        method: 'edc',
        amount: 25000,
        bank: 'BCA',
        cardType: 'debit',
        approvalCode: '123456',
      );
      expect(payment.change, 0);
    });

    test('split cash+EDC must equal the total', () {
      expect(
        validatePayments(65000, [
          PaymentDraft(method: 'cash', amount: 40000, tendered: 40000),
          PaymentDraft(method: 'edc', amount: 25000),
        ]),
        isNull,
      );
      expect(
        validatePayments(65000, [
          PaymentDraft(method: 'cash', amount: 40000, tendered: 40000),
        ]),
        isNotNull,
      );
    });

    test('cash tendered below amount is invalid', () {
      expect(
        validatePayments(40000, [
          PaymentDraft(method: 'cash', amount: 40000, tendered: 30000),
        ]),
        isNotNull,
      );
    });
  });

  group('Receipt text', () {
    test('receipt contains items, total, and payment lines', () {
      final sale = QueuedSale(
        clientReference: 'test-ref',
        outletId: 1,
        items: [
          CartLine(
            variantId: 1,
            productName: 'Kopi',
            variantName: 'Reguler',
            unitPrice: 25000,
            quantity: 2,
          ),
        ],
        payments: [PaymentDraft(method: 'cash', amount: 50000, tendered: 50000)],
        total: 50000,
        status: 'synced',
        serverSaleNumber: 'SLS-1-000001',
      );
      final text = receiptText(sale);
      expect(text, contains('Kopi'));
      expect(text, contains('SLS-1-000001'));
      expect(text, contains('50000'));
    });
  });
}
