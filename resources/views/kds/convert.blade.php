@extends('layouts.app')
@section('content')
<h1>Bayar Order #{{ $order->id }} — {{ $order->diningTable->name }} ({{ $order->outlet->name }})</h1>
@if($errors->any())<div class="card"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="card"><ul>@foreach($order->items as $item)<li>{{ $item->quantity }}x {{ $item->product_name }} ({{ $item->variant_name }}) — Rp {{ number_format($item->subtotal, 0, ',', '.') }}</li>@endforeach</ul>
<p><strong>Total: Rp {{ number_format($order->items->sum('subtotal'), 0, ',', '.') }}</strong></p>
<p>Harga dihitung ulang dari harga varian saat ini saat penjualan dibuat; bila harga berubah sejak order, total pembayaran harus sama dengan total penjualan yang baru. Pembayaran: cash dan EDC manual saja.</p>
<form method="POST" action="{{ route('kds.convert', $order) }}">@csrf
<h3>Cash</h3><label>Nominal (Rp)</label><input type="number" name="cash_amount" min="0" value="0"><label>Uang diterima (Rp)</label><input type="number" name="cash_tendered" min="0">
<h3>EDC (manual)</h3><label>Nominal (Rp)</label><input type="number" name="edc_amount" min="0" value="0"><label>Bank</label><input name="edc_bank"><label>Jenis kartu</label><select name="edc_card_type"><option value="">—</option><option value="debit">Debit</option><option value="credit">Kredit</option></select><label>Kode approval dari slip</label><input name="edc_approval_code">
<button>Buat Penjualan &amp; Tandai Lunas</button></form></div>
@endsection
