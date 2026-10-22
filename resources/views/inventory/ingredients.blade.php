@extends('layouts.app')
@section('content')
<h1>Bahan Baku</h1>
<div class="card"><p>Harga per satuan dipakai untuk menghitung HPP resep. Harga jual produk tidak berubah karena biaya bahan.</p>
<form method="POST" action="{{ route('inventory.ingredients.store') }}">@csrf
<label>Nama</label><input name="name" required placeholder="mis. Susu UHT">
<label>Satuan</label><input name="unit" required placeholder="gram / ml / pcs">
<label>Harga per satuan (Rp)</label><input type="number" min="0" name="cost_per_unit" required>
<label>Stok minimum (opsional)</label><input type="number" step="0.001" min="0" name="min_stock">
<button>Tambah Bahan</button>
</form></div>
<div class="card"><h2>Daftar bahan</h2>
<table><tr><th>Nama</th><th>Satuan</th><th>Harga/satuan</th><th>Stok minimum</th><th>Stok per gudang</th></tr>
@foreach($ingredients as $ing)<tr><td>{{ $ing->name }}</td><td>{{ $ing->unit }}</td><td>Rp {{ number_format($ing->cost_per_unit, 0, ',', '.') }}</td><td>{{ $ing->min_stock ?? '-' }}</td><td>@foreach($ing->stocks as $s){{ $s->warehouse?->name }}: {{ $s->quantity }}; @endforeach</td></tr>@endforeach
</table></div>
@endsection
