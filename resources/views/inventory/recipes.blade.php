@extends('layouts.app')
@section('content')
<h1>Resep &amp; HPP</h1>
<div class="card"><form method="GET" action="{{ route('inventory.recipes') }}">
<label>Varian produk</label><select name="product_variant_id" onchange="this.form.submit()">@foreach($variants as $v)<option value="{{ $v['id'] }}" {{ $selected && $selected->id == $v['id'] ? 'selected' : '' }}>{{ $v['label'] }}</option>@endforeach</select>
</form>
@if($selected)<p>Harga jual: Rp {{ number_format($selected->price, 0, ',', '.') }} &nbsp;|&nbsp; <strong>HPP per porsi: {{ $hpp === null ? '— (belum ada resep)' : 'Rp '.number_format($hpp, 0, ',', '.') }}</strong> (dihitung dari resep × harga bahan, bukan harga jual).</p>@endif
</div>
@if($selected)
<div class="card"><h2>Tambah / ubah bahan resep</h2>
<form method="POST" action="{{ route('inventory.recipes.store') }}">@csrf
<input type="hidden" name="product_variant_id" value="{{ $selected->id }}">
<label>Bahan</label><select name="ingredient_id" required>@foreach($ingredients as $ing)<option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->unit }})</option>@endforeach</select>
<label>Jumlah per porsi (dalam satuan bahan)</label><input type="number" step="0.001" min="0.001" name="quantity" required>
<button>Simpan Bahan</button>
</form></div>
<div class="card"><h2>Resep saat ini</h2>
<table><tr><th>Bahan</th><th>Jumlah/porsi</th><th>Biaya/porsi</th><th></th></tr>
@foreach($lines as $line)<tr><td>{{ $line->ingredient->name }} ({{ $line->ingredient->unit }})</td><td>{{ $line->quantity }}</td><td>Rp {{ number_format($line->lineCost(), 0, ',', '.') }}</td><td><form method="POST" action="{{ route('inventory.recipes.destroy', $line) }}">@csrf @method('DELETE')<button>Hapus</button></form></td></tr>@endforeach
</table></div>
@endif
@endsection
