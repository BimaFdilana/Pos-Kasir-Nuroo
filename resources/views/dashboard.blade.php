@extends('layouts.app')
@section('content')
<h1>Dashboard — {{ $tenant?->name ?? 'Belum ada tenant' }}</h1>
@if(!$tenant)
<div class="card"><p>Akun ini belum terhubung ke tenant. Jalankan <code>php artisan pos:create-user --tenant-name="Nama Usaha"</code> atau minta owner menetapkan tenant_id dan role.</p></div>
@else
<div class="grid">
<div class="card"><strong>Penjualan hari ini</strong><h2>{{ $todayCount }}</h2></div>
<div class="card"><strong>Total hari ini</strong><h2>Rp {{ number_format($todayTotal, 0, ',', '.') }}</h2></div>
<div class="card"><strong>Total cash hari ini</strong><h2>Rp {{ number_format($todayCash, 0, ',', '.') }}</h2></div>
</div>

<div class="card"><h2>Outlet</h2>
<table><tr><th>Nama</th><th>Kode</th><th>Status</th></tr>
@foreach($outlets as $o)<tr><td>{{ $o->name }}</td><td>{{ $o->code }}</td><td>{{ $o->is_active ? 'Aktif' : 'Nonaktif' }}</td></tr>@endforeach
</table></div>

<div class="card"><h2>Produk</h2>
<table><tr><th>Produk</th><th>Kategori</th><th>Varian / Harga</th></tr>
@foreach($products as $p)<tr><td>{{ $p->name }}</td><td>{{ $p->category?->name ?? '-' }}</td><td>
@foreach($p->variants as $v){{ $v->name }}: Rp {{ number_format($v->price, 0, ',', '.') }}<br>@endforeach
</td></tr>@endforeach
</table></div>

<div class="card"><h2>Transaksi terbaru</h2>
<table><tr><th>Nomor</th><th>Outlet</th><th>Kasir</th><th>Status</th><th>Total</th><th></th></tr>
@foreach($sales as $s)<tr><td>{{ $s->sale_number }}</td><td>{{ $s->outlet?->name }}</td><td>{{ $s->cashier?->name }}</td><td>{{ $s->status }}</td><td>Rp {{ number_format($s->total, 0, ',', '.') }}</td><td><a href="{{ route('sales.show', $s) }}">Detail</a></td></tr>@endforeach
</table></div>
@endif
@endsection
