@extends('layouts.app')
@section('content')
<h1>KDS — Order Meja</h1>
@if(session('status'))<p>{{ session('status') }}</p>@endif
<div class="card"><form method="GET" action="{{ route('kds.index') }}">
<label>Outlet</label><select name="outlet_id"><option value="">Semua outlet</option>@foreach($outlets as $o)<option value="{{ $o->id }}" {{ (string)$selectedOutlet === (string)$o->id ? 'selected' : '' }}>{{ $o->name }}</option>@endforeach</select>
<button>Filter</button></form></div>
@foreach($statuses as $status)
<h2>{{ ucfirst($status) }}</h2>
@forelse($grouped->get($status, collect()) as $order)
<div class="card">
<p><strong>Order #{{ $order->id }}</strong> — {{ $order->diningTable->name ?? '-' }} ({{ $order->outlet->name ?? '-' }}) — {{ $order->created_at->format('H:i') }}
@if($order->customer_name) — {{ $order->customer_name }}@endif
@if($order->note) — Catatan: {{ $order->note }}@endif</p>
<ul>@foreach($order->items as $item)<li>{{ $item->quantity }}x {{ $item->product_name }} ({{ $item->variant_name }}) — Rp {{ number_format($item->subtotal, 0, ',', '.') }}</li>@endforeach</ul>
<p><strong>Total: Rp {{ number_format($order->items->sum('subtotal'), 0, ',', '.') }}</strong></p>
@if($status === 'new')<form method="POST" action="{{ route('kds.status', $order) }}" style="display:inline">@csrf<input type="hidden" name="status" value="preparing"><button>Mulai Masak</button></form>@endif
@if($status === 'preparing')<form method="POST" action="{{ route('kds.status', $order) }}" style="display:inline">@csrf<input type="hidden" name="status" value="ready"><button>Siap Diantar</button></form>@endif
@if($status === 'ready')<form method="POST" action="{{ route('kds.status', $order) }}" style="display:inline">@csrf<input type="hidden" name="status" value="served"><button>Sudah Diantar</button></form>@endif
<form method="POST" action="{{ route('kds.status', $order) }}" style="display:inline" onsubmit="return confirm('Batalkan order ini?');">@csrf<input type="hidden" name="status" value="cancelled"><button>Batalkan</button></form>
<a href="{{ route('kds.convert.form', $order) }}">Bayar di Kasir</a>
</div>
@empty<p>Tidak ada order {{ $status }}.</p>@endforelse
@endforeach
@endsection
