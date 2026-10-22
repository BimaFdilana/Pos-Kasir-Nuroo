import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../app_state.dart';
import '../models.dart';

class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  List<Map<String, dynamic>> _serverSales = [];
  bool _loadingServer = false;
  String? _serverError;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadServerSales();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadServerSales() async {
    final state = context.read<AppState>();
    setState(() {
      _loadingServer = true;
      _serverError = null;
    });
    try {
      _serverSales = await state.api.getSales();
    } catch (e) {
      _serverError = 'Gagal memuat riwayat server. Periksa koneksi.';
    } finally {
      if (mounted) setState(() => _loadingServer = false);
    }
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'synced':
        return Colors.green;
      case 'failed':
        return Colors.red;
      default:
        return Colors.orange;
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    return Column(
      children: [
        TabBar(
          controller: _tabController,
          tabs: const [
            Tab(text: 'Antrian lokal'),
            Tab(text: 'Server'),
          ],
        ),
        Expanded(
          child: TabBarView(
            controller: _tabController,
            children: [
              Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        Text(
                            'Status sinkronisasi: ${state.syncStatus.name}'),
                        const Spacer(),
                        FilledButton(
                          onPressed: () => state.syncPending(),
                          child: const Text('Sinkronkan sekarang'),
                        ),
                      ],
                    ),
                  ),
                  if (state.lastSyncError != null)
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      child: Text(state.lastSyncError!,
                          style: const TextStyle(color: Colors.red)),
                    ),
                  Expanded(
                    child: state.queue.isEmpty
                        ? const Center(
                            child: Text('Belum ada penjualan lokal.'))
                        : ListView.builder(
                            itemCount: state.queue.length,
                            itemBuilder: (context, index) {
                              final sale = state.queue[index];
                              return Card(
                                margin: const EdgeInsets.symmetric(
                                    horizontal: 12, vertical: 4),
                                child: ListTile(
                                  title: Text(sale.serverSaleNumber ??
                                      'Menunggu sinkronisasi'),
                                  subtitle: Text(
                                      '${sale.items.length} jenis item • ${formatRupiah(sale.total)}${sale.error != null ? '\n${sale.error}' : ''}'),
                                  trailing: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Chip(
                                        label: Text(sale.status),
                                        backgroundColor: _statusColor(
                                                sale.status)
                                            .withValues(alpha: 0.2),
                                      ),
                                      if (sale.status == 'failed')
                                        TextButton(
                                          onPressed: () =>
                                              state.retryFailed(sale),
                                          child: const Text('Coba lagi'),
                                        ),
                                    ],
                                  ),
                                ),
                              );
                            },
                          ),
                  ),
                ],
              ),
              _loadingServer
                  ? const Center(child: CircularProgressIndicator())
                  : _serverError != null
                      ? Center(child: Text(_serverError!))
                      : RefreshIndicator(
                          onRefresh: _loadServerSales,
                          child: ListView.builder(
                            itemCount: _serverSales.length,
                            itemBuilder: (context, index) {
                              final sale = _serverSales[index];
                              return ListTile(
                                title: Text(
                                    '${sale['sale_number'] ?? '-'} • ${sale['status'] ?? ''}'),
                                subtitle: Text(
                                    'Total ${formatRupiah((sale['total'] as num?)?.toInt() ?? 0)}'),
                              );
                            },
                          ),
                        ),
            ],
          ),
        ),
      ],
    );
  }
}
