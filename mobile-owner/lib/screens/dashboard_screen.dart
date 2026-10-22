import 'package:flutter/material.dart';

import '../api_client.dart';
import '../models.dart';

/// Dashboard baca-saja. Menerima data yang sudah dimuat agar bisa
/// diuji widget tanpa jaringan; pemuatan dari API dilakukan oleh
/// [DashboardPage] di bawah.
class DashboardScreen extends StatelessWidget {
  final OwnerSummary summary;
  final List<OutletSummary> allOutlets;
  final List<Sale> sales;
  final int? selectedOutletId;
  final ValueChanged<int?> onOutletChanged;
  final Future<void> Function() onRefresh;
  final VoidCallback onLogout;
  final bool loading;

  const DashboardScreen({
    super.key,
    required this.summary,
    required this.allOutlets,
    required this.sales,
    required this.selectedOutletId,
    required this.onOutletChanged,
    required this.onRefresh,
    required this.onLogout,
    this.loading = false,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Ringkasan Owner'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Keluar',
            onPressed: onLogout,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'Penjualan hari ini (${summary.date})',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _StatCard(
                    label: 'Transaksi',
                    value: '${summary.salesCount}',
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _StatCard(
                    label: 'Total',
                    value: formatRupiah(summary.total),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _StatCard(
                    label: 'Tunai (cash)',
                    value: formatRupiah(summary.cashTotal),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<int?>(
              // ignore: deprecated_member_use
              value: selectedOutletId,
              decoration: const InputDecoration(
                labelText: 'Outlet',
                border: OutlineInputBorder(),
              ),
              items: [
                const DropdownMenuItem<int?>(
                  value: null,
                  child: Text('Semua outlet'),
                ),
                for (final outlet in allOutlets)
                  DropdownMenuItem<int?>(
                    value: outlet.outletId,
                    child: Text(outlet.outletName),
                  ),
              ],
              onChanged: onOutletChanged,
            ),
            const SizedBox(height: 16),
            Text('Per outlet',
                style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            if (summary.perOutlet.isEmpty)
              const Text('Belum ada penjualan hari ini.')
            else
              for (final outlet in summary.perOutlet)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(outlet.outletName),
                  subtitle: Text(
                      '${outlet.salesCount} transaksi · tunai ${formatRupiah(outlet.cashTotal)}'),
                  trailing: Text(formatRupiah(outlet.total)),
                ),
            const Divider(height: 32),
            Text('Penjualan',
                style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            if (sales.isEmpty)
              const Text('Belum ada penjualan untuk filter ini.')
            else
              for (final sale in sales) _SaleTile(sale: sale),
            const Divider(height: 32),
            Text(
              'Stok menipis (stok ≤ ${summary.lowStockThreshold})',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            if (summary.lowStock.isEmpty)
              const Text('Tidak ada stok menipis.')
            else
              for (final item in summary.lowStock)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.warning_amber_outlined),
                  title: Text(
                      '${item.productName}${item.variantName.isEmpty ? '' : ' — ${item.variantName}'}'),
                  subtitle: Text(
                      '${item.outletName} · SKU ${item.sku.isEmpty ? '-' : item.sku} · min. stok ${item.minStock}'),
                  trailing: Text('${item.quantity}'),
                ),
            if (loading) ...[
              const SizedBox(height: 16),
              const Center(child: CircularProgressIndicator()),
            ],
            const SizedBox(height: 16),
            const Text(
              'Catatan: pembayaran MVP hanya tunai dan EDC manual. '
              'Aplikasi ini tidak pernah meminta atau menyimpan nomor kartu.',
              style: TextStyle(fontStyle: FontStyle.italic),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;

  const _StatCard({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 4),
            Text(value, style: Theme.of(context).textTheme.titleMedium),
          ],
        ),
      ),
    );
  }
}

class _SaleTile extends StatelessWidget {
  final Sale sale;

  const _SaleTile({required this.sale});

  @override
  Widget build(BuildContext context) {
    return ExpansionTile(
      title: Text(sale.saleNumber.isEmpty ? 'Penjualan #${sale.id}' : sale.saleNumber),
      subtitle: Text(
          '${sale.status} · ${formatRupiah(sale.total)} · ${sale.createdAt}'),
      children: [
        for (final item in sale.items)
          ListTile(
            title: Text(
                '${item.productName}${item.variantName.isEmpty ? '' : ' — ${item.variantName}'}'),
            subtitle: Text(
                '${item.quantity} × ${formatRupiah(item.unitPrice)}'),
            trailing: Text(formatRupiah(item.subtotal)),
          ),
        for (final payment in sale.payments)
          ListTile(
            title: Text(
                'Bayar: ${payment.method}${payment.bank != null && payment.bank!.isNotEmpty ? ' · ${payment.bank}' : ''}${payment.cardType != null && payment.cardType!.isNotEmpty ? ' · ${payment.cardType}' : ''}'),
            subtitle: payment.approvalCode != null &&
                    payment.approvalCode!.isNotEmpty
                ? Text('Kode approval: ${payment.approvalCode}')
                : null,
            trailing: Text(formatRupiah(payment.amount)),
          ),
        ListTile(
          title: const Text('Total dibayar'),
          trailing: Text(formatRupiah(sale.paidTotal)),
        ),
        if (sale.changeTotal > 0)
          ListTile(
            title: const Text('Kembalian'),
            trailing: Text(formatRupiah(sale.changeTotal)),
          ),
      ],
    );
  }
}

/// Halaman bermuatan API: memuat ringkasan + penjualan, menangani
/// filter outlet, refresh, dan pesan 403 owner/manager.
class DashboardPage extends StatefulWidget {
  final ApiClient client;
  final AppUser user;
  final VoidCallback onSessionExpired;

  const DashboardPage({
    super.key,
    required this.client,
    required this.user,
    required this.onSessionExpired,
  });

  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  OwnerSummary? _summary;
  List<OutletSummary> _allOutlets = [];
  List<Sale> _sales = [];
  int? _selectedOutletId;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      // Daftar outlet selalu dari ringkasan tanpa filter, karena
      // tidak ada endpoint daftar outlet khusus.
      final unfiltered = await widget.client.getOwnerSummary();
      final summary = _selectedOutletId == null
          ? unfiltered
          : await widget.client
              .getOwnerSummary(outletId: _selectedOutletId);
      final allSales = await widget.client.getSales();
      if (!mounted) return;
      setState(() {
        _summary = summary;
        _allOutlets = unfiltered.perOutlet;
        _sales = Sale.filterByOutlet(allSales, _selectedOutletId);
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.statusCode == 401) {
        widget.onSessionExpired();
        return;
      }
      setState(() => _error = e.message);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_error != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Ringkasan Owner')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(_error!, textAlign: TextAlign.center),
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: _load,
                  child: const Text('Coba lagi'),
                ),
                TextButton(
                  onPressed: widget.onSessionExpired,
                  child: const Text('Kembali ke login'),
                ),
              ],
            ),
          ),
        ),
      );
    }
    final summary = _summary;
    if (summary == null) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    return DashboardScreen(
      summary: summary,
      allOutlets: _allOutlets,
      sales: _sales,
      selectedOutletId: _selectedOutletId,
      onOutletChanged: (value) {
        setState(() => _selectedOutletId = value);
        _load();
      },
      onRefresh: _load,
      onLogout: widget.onSessionExpired,
      loading: _loading,
    );
  }
}
