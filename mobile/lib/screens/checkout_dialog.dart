import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../api_client.dart';
import '../app_state.dart';
import '../models.dart';
import 'receipt_dialog.dart';

Future<void> showCheckoutDialog(BuildContext context, AppState state) async {
  final payments = <PaymentDraft>[
    PaymentDraft(method: 'cash', amount: state.cart.total),
  ];
  await showDialog<void>(
    context: context,
    builder: (dialogContext) => _CheckoutDialog(
      state: state,
      payments: payments,
    ),
  );
}

class _CheckoutDialog extends StatefulWidget {
  final AppState state;
  final List<PaymentDraft> payments;
  const _CheckoutDialog({required this.state, required this.payments});

  @override
  State<_CheckoutDialog> createState() => _CheckoutDialogState();
}

class _CheckoutDialogState extends State<_CheckoutDialog> {
  String? _error;
  bool _paying = false;

  int get _paid =>
      widget.payments.fold(0, (sum, payment) => sum + payment.amount);
  int get _remaining => widget.state.cart.total - _paid;

  void _addPayment(String method) {
    setState(() {
      widget.payments.add(PaymentDraft(
        method: method,
        amount: _remaining > 0 ? _remaining : 0,
      ));
    });
  }

  Future<void> _pay() async {
    final state = widget.state;
    final error = validatePayments(state.cart.total, widget.payments);
    if (error != null) {
      setState(() => _error = error);
      return;
    }
    setState(() {
      _paying = true;
      _error = null;
    });
    try {
      final result = await state.checkout(widget.payments);
      if (!mounted) return;
      Navigator.of(context).pop();
      await showReceiptDialog(context, result.sale, result.synced);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } on StateError catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Terjadi kesalahan. Coba lagi.');
    } finally {
      if (mounted) setState(() => _paying = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final total = widget.state.cart.total;
    return AlertDialog(
      title: const Text('Checkout'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Total belanja: ${formatRupiah(total)}',
                style: const TextStyle(fontWeight: FontWeight.bold)),
            Text('Sudah dibayar: ${formatRupiah(_paid)}'),
            Text('Sisa: ${formatRupiah(_remaining)}'),
            Text('Kembalian cash: ${formatRupiah(totalChange(widget.payments))}'),
            const SizedBox(height: 12),
            for (var i = 0; i < widget.payments.length; i++)
              _PaymentEditor(
                payment: widget.payments[i],
                onRemove: widget.payments.length > 1
                    ? () => setState(() => widget.payments.removeAt(i))
                    : null,
                onChanged: () => setState(() {}),
              ),
            const SizedBox(height: 8),
            Row(
              children: [
                OutlinedButton(
                  onPressed: () => _addPayment('cash'),
                  child: const Text('+ Cash'),
                ),
                const SizedBox(width: 8),
                OutlinedButton(
                  onPressed: () => _addPayment('edc'),
                  child: const Text('+ EDC'),
                ),
              ],
            ),
            const SizedBox(height: 8),
            const Text(
              'EDC di sini manual: proses kartu di mesin EDC, lalu isi bank, jenis kartu, dan kode approval dari slip. Nomor kartu tidak pernah diminta atau disimpan.',
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
            if (_error != null) ...[
              const SizedBox(height: 8),
              Text(_error!, style: const TextStyle(color: Colors.red)),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _paying ? null : () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        FilledButton(
          onPressed: _paying ? null : _pay,
          child: _paying
              ? const CircularProgressIndicator()
              : const Text('Bayar'),
        ),
      ],
    );
  }
}

class _PaymentEditor extends StatelessWidget {
  final PaymentDraft payment;
  final VoidCallback? onRemove;
  final VoidCallback onChanged;
  const _PaymentEditor({
    required this.payment,
    required this.onRemove,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 4),
      child: Padding(
        padding: const EdgeInsets.all(8),
        child: Column(
          children: [
            Row(
              children: [
                DropdownButton<String>(
                  value: payment.method,
                  items: const [
                    DropdownMenuItem(value: 'cash', child: Text('Cash')),
                    DropdownMenuItem(value: 'edc', child: Text('EDC')),
                  ],
                  onChanged: (value) {
                    if (value != null) {
                      payment.method = value;
                      onChanged();
                    }
                  },
                ),
                const Spacer(),
                if (onRemove != null)
                  IconButton(
                    icon: const Icon(Icons.delete),
                    onPressed: onRemove,
                  ),
              ],
            ),
            TextFormField(
              initialValue: payment.amount.toString(),
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(labelText: 'Nominal (Rp)'),
              onChanged: (value) {
                payment.amount = int.tryParse(value) ?? 0;
                onChanged();
              },
            ),
            if (payment.method == 'cash')
              TextFormField(
                initialValue: (payment.tendered ?? payment.amount).toString(),
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration:
                    const InputDecoration(labelText: 'Uang diterima (Rp)'),
                onChanged: (value) {
                  payment.tendered = int.tryParse(value);
                  onChanged();
                },
              ),
            if (payment.method == 'edc') ...[
              TextFormField(
                initialValue: payment.bank ?? '',
                decoration:
                    const InputDecoration(labelText: 'Bank (mis. BCA)'),
                onChanged: (value) {
                  payment.bank = value.isEmpty ? null : value;
                  onChanged();
                },
              ),
              DropdownButton<String>(
                value: payment.cardType,
                hint: const Text('Jenis kartu'),
                isExpanded: true,
                items: const [
                  DropdownMenuItem(value: 'debit', child: Text('Debit')),
                  DropdownMenuItem(value: 'credit', child: Text('Kredit')),
                ],
                onChanged: (value) {
                  payment.cardType = value;
                  onChanged();
                },
              ),
              TextFormField(
                initialValue: payment.approvalCode ?? '',
                decoration: const InputDecoration(
                    labelText: 'Kode approval dari slip EDC'),
                onChanged: (value) {
                  payment.approvalCode = value.isEmpty ? null : value;
                  onChanged();
                },
              ),
            ],
          ],
        ),
      ),
    );
  }
}
