import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../app_state.dart';
import '../models.dart';
import 'checkout_dialog.dart';
import 'history_screen.dart';
import 'settings_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final pages = [
      const CashierTab(),
      const HistoryScreen(),
      const SettingsScreen(),
    ];
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pos-Kasir-Nuroo'),
        actions: [
          if (state.queue.any((q) => q.status == 'pending'))
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: Chip(
                label: Text(
                    '${state.queue.where((q) => q.status == 'pending').length} menunggu'),
                backgroundColor: Colors.amber.shade200,
              ),
            ),
          IconButton(
            icon: const Icon(Icons.sync),
            tooltip: 'Sinkronkan sekarang',
            onPressed: () => state.syncPending(),
          ),
        ],
      ),
      body: pages[_tab],
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _tab,
        onTap: (index) => setState(() => _tab = index),
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.point_of_sale), label: 'Kasir'),
          BottomNavigationBarItem(icon: Icon(Icons.history), label: 'Riwayat'),
          BottomNavigationBarItem(icon: Icon(Icons.settings), label: 'Pengaturan'),
        ],
      ),
    );
  }
}

class CashierTab extends StatefulWidget {
  const CashierTab({super.key});

  @override
  State<CashierTab> createState() => _CashierTabState();
}

class _CashierTabState extends State<CashierTab> {
  final _searchController = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final products = state.searchProducts(_query);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: TextField(
            controller: _searchController,
            decoration: InputDecoration(
              labelText: 'Cari produk atau SKU',
              prefixIcon: const Icon(Icons.search),
              border: const OutlineInputBorder(),
              suffixIcon: IconButton(
                icon: const Icon(Icons.refresh),
                tooltip: 'Muat ulang produk',
                onPressed: () => state.refreshProducts(),
              ),
            ),
            onChanged: (value) => setState(() => _query = value),
          ),
        ),
        if (state.syncStatus == SyncStatus.offline)
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 12),
            child: Text(
              'Offline: penjualan akan disimpan lokal dan disinkronkan saat online.',
              style: TextStyle(color: Colors.orange),
            ),
          ),
        Expanded(
          child: products.isEmpty
              ? const Center(
                  child: Text(
                      'Belum ada produk. Tarik untuk memuat ulang atau periksa koneksi.'),
                )
              : RefreshIndicator(
                  onRefresh: () => state.refreshProducts(),
                  child: ListView.builder(
                    itemCount: products.length,
                    itemBuilder: (context, index) {
                      final product = products[index];
                      return Card(
                        margin: const EdgeInsets.symmetric(
                            horizontal: 12, vertical: 4),
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(product.name,
                                  style: const TextStyle(
                                      fontWeight: FontWeight.bold)),
                              if (product.category != null)
                                Text(product.category!,
                                    style: const TextStyle(
                                        color: Colors.grey)),
                              const SizedBox(height: 8),
                              Wrap(
                                spacing: 8,
                                children: product.variants.map((variant) {
                                  return ElevatedButton(
                                    onPressed: () => state.addToCart(
                                        variant, product.name),
                                    child: Text(
                                        '${variant.name} • ${formatRupiah(variant.price)}'),
                                  );
                                }).toList(),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
        ),
        _CartBar(state: state),
      ],
    );
  }
}

class _CartBar extends StatelessWidget {
  final AppState state;
  const _CartBar({required this.state});

  @override
  Widget build(BuildContext context) {
    final cart = state.cart;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        border: const Border(top: BorderSide(color: Colors.grey)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (!cart.isEmpty)
            SizedBox(
              height: 120,
              child: ListView.builder(
                itemCount: cart.lines.length,
                itemBuilder: (context, index) {
                  final line = cart.lines[index];
                  return Row(
                    children: [
                      Expanded(
                        child: Text(
                            '${line.productName} (${line.variantName})'),
                      ),
                      IconButton(
                        icon: const Icon(Icons.remove),
                        onPressed: () => state.setCartQuantity(
                            line.variantId, line.quantity - 1),
                      ),
                      Text('${line.quantity}'),
                      IconButton(
                        icon: const Icon(Icons.add),
                        onPressed: () => state.setCartQuantity(
                            line.variantId, line.quantity + 1),
                      ),
                      SizedBox(
                        width: 100,
                        child: Text(
                          formatRupiah(line.lineTotal),
                          textAlign: TextAlign.right,
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Total: ${formatRupiah(cart.total)} (${cart.itemCount} item)',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
              ),
              FilledButton(
                onPressed: cart.isEmpty
                    ? null
                    : () => showCheckoutDialog(context, state),
                child: const Text('Checkout'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
