@extends('layouts.app')
@section('content')
<h1>{{ $sale->sale_number }} — {{ $sale->status }}</h1>
<div class="card">
<p>Outlet: {{ $sale->outlet?->name }} | Kasir: {{ $sale->cashier?->name }} | {{ $sale->created_at }}</p>
<table><tr><th>Item</th><th>Harga</th><th>Qty</th><th>Subtotal</th></tr>
@foreach($sale->items as $i)<tr><td>{{ $i->product_name }} — {{ $i->variant_name }}</td><td>Rp {{ number_format($i->unit_price, 0, ',', '.') }}</td><td>{{ $i->quantity }}</td><td>Rp {{ number_format($i->subtotal, 0, ',', '.') }}</td></tr>@endforeach
</table>
<p>Subtotal: Rp {{ number_format($sale->subtotal, 0, ',', '.') }} | Diskon: Rp {{ number_format($sale->discount_total, 0, ',', '.') }} | Pajak: Rp {{ number_format($sale->tax_total, 0, ',', '.') }}</p>
<p><strong>Total: Rp {{ number_format($sale->total, 0, ',', '.') }}</strong> | Kembalian: Rp {{ number_format($sale->change_total, 0, ',', '.') }}</p>
<h3>Pembayaran (MVP: cash / EDC manual saja)</h3>
<ul>@foreach($sale->payments as $p)<li>{{ $p->method }} — Rp {{ number_format($p->amount, 0, ',', '.') }}
@if($p->method === 'cash') (diterima Rp {{ number_format($p->tendered ?? 0, 0, ',', '.') }}, kembalian Rp {{ number_format($p->change_amount ?? 0, 0, ',', '.') }})@endif
@if($p->method === 'edc') (bank {{ $p->bank ?? '-' }}, {{ $p->card_type ?? '-' }}, approval {{ $p->approval_code ?? '-' }})@endif
</li>@endforeach</ul>
@if($sale->void_reason)<p>Alasan void/refund: {{ $sale->void_reason }}</p>@endif
</div>

@if($sale->status === 'completed' && auth()->user()?->isSupervisor())
<div class="card"><h2>Aksi supervisor (owner/manager, tercatat di audit)</h2>
<form method="POST" action="{{ route('sales.void', $sale) }}">@csrf
<label>Alasan void</label><input name="reason" required><button>Void</button></form>
<form method="POST" action="{{ route('sales.refund', $sale) }}" style="margin-top:12px">@csrf
<label>Alasan refund penuh</label><input name="reason" required><button>Refund penuh</button></form>
</div>
@endif

<div class="card"><h2>Audit</h2><ul>
@foreach($sale->audits as $a)<li>{{ $a->created_at }} — {{ $a->action }} oleh {{ $a->actor?->name }} (approver: {{ $a->approver?->name ?? '-' }}), alasan: {{ $a->reason }}, total sebelum Rp {{ number_format($a->total_before, 0, ',', '.') }} → sesudah Rp {{ number_format($a->total_after, 0, ',', '.') }}</li>@endforeach
</ul></div>
@endsection
