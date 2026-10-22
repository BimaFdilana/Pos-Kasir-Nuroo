import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../models.dart';

Future<void> showReceiptDialog(
    BuildContext context, QueuedSale sale, bool synced) async {
  await showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text(synced ? 'Penjualan tersinkron' : 'Tersimpan offline'),
      content: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (!synced)
              const Text(
                'Tidak ada koneksi ke server. Penjualan ini tersimpan di perangkat dan akan disinkronkan otomatis saat online.',
                style: TextStyle(color: Colors.orange),
              ),
            const SizedBox(height: 8),
            SelectableText(
              receiptText(sale),
              style: const TextStyle(fontFamily: 'monospace', fontSize: 13),
            ),
            const SizedBox(height: 8),
            const Text(
              'Pratinjau struk teks di aplikasi. Cetak printer ESC/POS adalah tahap berikutnya dan belum tersedia di versi ini.',
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () async {
            await Clipboard.setData(ClipboardData(text: receiptText(sale)));
            if (dialogContext.mounted) Navigator.of(dialogContext).pop();
          },
          child: const Text('Salin & Tutup'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(dialogContext).pop(),
          child: const Text('Tutup'),
        ),
      ],
    ),
  );
}
