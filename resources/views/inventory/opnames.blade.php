@extends('layouts.app')
@section('content')
<h1>Opname Stok Bahan</h1>
<div class="card"><p>Isi jumlah yang benar-benar dihitung di gudang. Sistem membandingkan dengan stok tercatat, lalu menerapkan selisihnya sebagai mutasi opname sehingga stok menjadi jumlah hitung. Jumlah hitung tidak pernah negatif, jadi opname tidak bisa membuat stok negatif.</p>
<form method="POST" action="{{ route('inventory.opnames.store') }}">@csrf
<label>Gudang</label><select name="warehouse_id" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
<label>Bahan</label><select name="ingredient_id" required>@foreach($ingredients as $ing)<option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->unit }})</option>@endforeach</select>
<label>Jumlah hasil hitung</label><input type="number" step="0.001" min="0" name="counted_quantity" required>
<label>Catatan (opsional)</label><input name="note">
<button>Catat Opname</button>
</form></div>
<div class="card"><h2>Riwayat opname</h2>
<table><tr><th>Tanggal</th><th>Gudang</th><th>Bahan</th><th>Sistem</th><th>Hitung</th><th>Selisih</th><th>Catatan</th></tr>
@foreach($opnames as $o)<tr><td>{{ $o->created_at }}</td><td>{{ $o->warehouse?->name }}</td><td>{{ $o->ingredient?->name }}</td><td>{{ $o->system_quantity }}</td><td>{{ $o->counted_quantity }}</td><td>{{ $o->difference > 0 ? '+' : '' }}{{ $o->difference }}</td><td>{{ $o->note }}</td></tr>@endforeach
</table>{{ $opnames->links() }}</div>
@endsection
