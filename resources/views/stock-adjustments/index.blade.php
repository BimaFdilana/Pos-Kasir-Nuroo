@extends('layouts.app')
@section('content')
<h1>Penyesuaian Stok</h1>
<div class="card"><p>Penyesuaian ini tercatat sebagai movement tipe <code>adjustment</code> dengan alasan dan pelaku, dan langsung mengubah stok outlet. Nilai negatif tidak boleh membuat stok akhir negatif.</p>
<form method="POST" action="{{ route('stock-adjustments.store') }}">@csrf
<label>Outlet</label><select name="outlet_id" required>@foreach($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select>
<label>Varian</label><select name="product_variant_id" required>@foreach($variants as $v)<option value="{{ $v['id'] }}">{{ $v['label'] }}</option>@endforeach</select>
<label>Perubahan stok (boleh negatif, tidak boleh 0)</label><input type="number" name="quantity_change" required>
<label>Alasan (wajib)</label><input name="reason" required placeholder="mis. Opname, barang rusak, koreksi input">
<button>Catat Penyesuaian</button>
</form></div>
<div class="card"><h2>Riwayat penyesuaian</h2>
<table><tr><th>Tanggal</th><th>Outlet</th><th>Varian</th><th>Perubahan</th><th>Stok akhir</th><th>Alasan</th></tr>
@foreach($movements as $m)<tr><td>{{ $m->created_at }}</td><td>{{ $m->outlet?->name }}</td><td>{{ $m->variant?->product?->name }} — {{ $m->variant?->name }}</td><td>{{ $m->quantity_change > 0 ? '+' : '' }}{{ $m->quantity_change }}</td><td>{{ $m->quantity_after }}</td><td>{{ $m->reason }}</td></tr>@endforeach
</table>{{ $movements->links() }}</div>
@endsection
