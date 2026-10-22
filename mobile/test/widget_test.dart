import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_kasir_nuroo/api_client.dart';
import 'package:pos_kasir_nuroo/app_state.dart';
import 'package:pos_kasir_nuroo/models.dart';
import 'package:pos_kasir_nuroo/screens/home_screen.dart';
import 'package:provider/provider.dart';

class FakeApiClient extends ApiClient {
  FakeApiClient() : super(baseUrl: 'http://10.0.2.2:8000');

  @override
  Future<List<Product>> getProducts() async => [
        const Product(
          id: 1,
          name: 'Kopi',
          variants: [Variant(id: 1, name: 'Reguler', price: 25000)],
        ),
      ];
}

void main() {
  testWidgets('cashier flow: add product, see total, open checkout',
      (tester) async {
    final state = AppState(api: FakeApiClient());
    state.products = [
      const Product(
        id: 1,
        name: 'Kopi',
        variants: [Variant(id: 1, name: 'Reguler', price: 25000)],
      ),
    ];

    await tester.pumpWidget(
      MaterialApp(
        home: ChangeNotifierProvider<AppState>.value(
          value: state,
          child: const HomeScreen(),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Kopi'), findsWidgets);
    await tester.tap(find.text('Reguler • Rp 25.000'));
    await tester.pump();

    expect(find.textContaining('Total: Rp 25.000'), findsOneWidget);

    await tester.tap(find.text('Checkout'));
    await tester.pumpAndSettle();

    expect(find.text('Checkout'), findsWidgets);
    expect(find.textContaining('Total belanja: Rp 25.000'), findsOneWidget);
  });
}
