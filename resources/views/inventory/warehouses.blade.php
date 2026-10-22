@extends('layouts.app')
@section('content')
<h1>Gudang</h1>
<div class="card"><p>Gudang yang terikat ke outlet dipakai untuk konsumsi resep saat penjualan di outlet itu. Bila outlet tidak punya gudang sendiri, dipakai gudang default tenant, lalu gudang pertama.</p>
<form method="POST" action="{{ route('inventory.warehouses.store') }}">@csrf
<label>Nama</label><input name="name" required placeholder="mis. Gudang Utama">
<label>Outlet (opsional)</label><select name="outlet_id"><option value="">— Tidak terikat outlet —</option>@foreach($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select>
<label><input type="checkbox" name="is_default" value="1" style="width:auto"> Jadikan gudang default tenant</label>
<button>Tambah Gudang</button>
</form></div>
<div class="card"><h2>Daftar gudang</h2>
<table><tr><th>Nama</th><th>Outlet</th><th>Default</th><th>Stok bahan</th></tr>
@foreach($warehouses as $w)<tr><td>{{ $w->name }}</td><td>{{ $w->outlet?->name ?? '-' }}</td><td>{{ $w->is_default ? 'Ya' : 'Tidak' }}</td><td>@foreach($w->stocks as $s){{ $s->ingredient?->name }}: {{ $s->quantity }}; @endforeach</td></tr>@endforeach
</table></div>
@endsection
