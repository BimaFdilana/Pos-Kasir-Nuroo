@extends('layouts.app')
@section('content')
<h1>Produk</h1>
<div class="card"><h2>Tambah produk (owner/manager)</h2>
<form method="POST" action="{{ route('products.store') }}">@csrf
<label>Kategori</label><select name="category_id"><option value="">—</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
<label>Nama produk</label><input name="name" required>
<label>SKU</label><input name="sku">
<label>Nama varian</label><input name="variant_name" value="Reguler" required>
<label>Harga (Rp, bilangan bulat)</label><input type="number" name="price" min="0" required>
<label>Outlet stok awal</label><select name="outlet_id"><option value="">—</option>@foreach($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select>
<label>Stok awal</label><input type="number" name="initial_stock" min="0" value="0">
<button>Simpan</button>
</form></div>

<div class="card"><table><tr><th>Produk</th><th>Varian</th><th>Stok per outlet</th><th>Status</th><th></th></tr>
@foreach($products as $p)@foreach($p->variants as $v)<tr><td>{{ $p->name }}</td><td>{{ $v->name }} — Rp {{ number_format($v->price, 0, ',', '.') }}</td><td>
@foreach($v->stocks as $s)Outlet #{{ $s->outlet_id }}: {{ $s->quantity }}<br>@endforeach
</td><td>{{ $p->is_active ? 'Aktif' : 'Nonaktif' }}</td><td>
<a href="{{ route('products.edit', $p) }}">Edit</a>
<form method="POST" action="{{ route('products.destroy', $p) }}" style="display:inline" onsubmit="return confirm('Hapus produk ini? Produk yang sudah pernah terjual akan diarsipkan, bukan dihapus.');">@csrf @method('DELETE')<button>Hapus / Arsipkan</button></form>
</td></tr>@endforeach @endforeach
</table>
{{ $products->links() }}
</div>
@endsection
