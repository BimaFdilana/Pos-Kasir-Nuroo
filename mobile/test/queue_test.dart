import 'package:flutter_test/flutter_test.dart';
import 'package:pos_kasir_nuroo/models.dart';
import 'package:uuid/uuid.dart';

void main() {
  group('Offline queue serialisation', () {
    QueuedSale buildSale() => QueuedSale(
          clientReference: '550e8400-e29b-41d4-a716-446655440000',
          outletId: 3,
          items: [
            CartLine(
              variantId: 9,
              productName: 'Kopi',
              variantName: 'Reguler',
              unitPrice: 25000,
              quantity: 2,
            ),
          ],
          payments: [
            PaymentDraft(method: 'cash', amount: 50000, tendered: 100000),
            PaymentDraft(
              method: 'edc',
              amount: 0,
              bank: 'BCA',
              cardType: 'credit',
              approvalCode: '654321',
            ),
          ],
          total: 50000,
          status: 'pending',
        );

    test('toJson/fromJson round-trips without losing data', () {
      final original = buildSale();
      final restored = QueuedSale.fromJson(original.toJson());
      expect(restored.clientReference, original.clientReference);
      expect(restored.outletId, 3);
      expect(restored.items.length, 1);
      expect(restored.items.first.quantity, 2);
      expect(restored.payments.length, 2);
      expect(restored.payments.first.change, 50000);
      expect(restored.payments[1].approvalCode, '654321');
      expect(restored.status, 'pending');
    });

    test('sale request shape matches the Laravel API contract', () {
      final request = buildSale().toSaleRequest();
      expect(request['outlet_id'], 3);
      expect(request['client_reference'],
          '550e8400-e29b-41d4-a716-446655440000');
      expect(request['items'], [
        {'product_variant_id': 9, 'quantity': 2},
      ]);
      final payments = request['payments'] as List<dynamic>;
      expect(payments.first['method'], 'cash');
      expect(payments.first['tendered'], 100000);
      expect(payments[1]['method'], 'edc');
      expect(payments[1]['approval_code'], '654321');
      // Nomor kartu tidak pernah ada di body request.
      expect(request.toString(), isNot(contains('card_number')));
    });

    test('generated client_reference is a UUID v4', () {
      final reference = const Uuid().v4();
      final uuidV4 = RegExp(
        r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
      );
      expect(uuidV4.hasMatch(reference), isTrue);
      expect(const Uuid().v4() == reference, isFalse);
    });
  });
}
