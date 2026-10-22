@extends('layouts.app')
@section('content')
<h1>Mutasi &amp; Transfer Bahan</h1>
<div class="card"><h2>Pembelian / penyesuaian</h2><p>Pembelian harus positif. Penyesuaian boleh negatif, tapi tidak boleh membuat stok negatif. Alasan wajib dicatat beserta pelakunya.</p>
<form method="POST" action="{{ route('inventory.movements.store') }}">@csrf
<label>Gudang</label><select name="warehouse_id" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
<label>Bahan</label><select name="ingredient_id" required>@foreach($ingredients as $ing)<option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->unit }})</option>@endforeach</select>
<label>Jenis</label><select name="type" required><option value="purchase">Pembelian (masuk)</option><option value="adjustment">Penyesuaian (boleh negatif)</option></select>
<label>Perubahan stok</label><input type="number" step="0.001" name="quantity_change" required>
<label>Alasan (wajib)</label><input name="reason" required>
<button>Catat Mutasi</button>
</form></div>
<div class="card"><h2>Transfer antar gudang</h2>
<form method="POST" action="{{ route('inventory.transfers.store') }}">@csrf
<label>Gudang asal</label><select name="source_warehouse_id" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
<label>Gudang tujuan</label><select name="destination_warehouse_id" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
<label>Bahan</label><select name="ingredient_id" required>@foreach($ingredients as $ing)<option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->unit }})</option>@endforeach</select>
<label>Jumlah</label><input type="number" step="0.001" min="0.001" name="quantity" required>
<label>Alasan (wajib)</label><input name="reason" required>
<button>Transfer</button>
</form></div>
<div class="card"><h2>Riwayat mutasi</h2>
<table><tr><th>Tanggal</th><th>Gudang</th><th>Bahan</th><th>Jenis</th><th>Perubahan</th><th>Stok akhir</th><th>Alasan</th></tr>
@foreach($movements as $m)<tr><td>{{ $m->created_at }}</td><td>{{ $m->warehouse?->name }}</td><td>{{ $m->ingredient?->name }}</td><td>{{ $m->type }}</td><td>{{ $m->quantity_change > 0 ? '+' : '' }}{{ $m->quantity_change }}</td><td>{{ $m->quantity_after }}</td><td>{{ $m->reason }}</td></tr>@endforeach
</table>{{ $movements->links() }}</div>
@endsection
