@extends('layouts.app')
@section('content')
<h1>Kategori</h1>
<div class="card"><h2>Tambah kategori (owner/manager)</h2>
<form method="POST" action="{{ route('categories.store') }}">@csrf
<label>Nama kategori</label><input name="name" required>
<button>Simpan</button>
</form></div>
<div class="card"><table><tr><th>Nama</th><th>Jumlah produk</th><th>Status</th><th></th></tr>
@foreach($categories as $c)<tr><td>{{ $c->name }}</td><td>{{ $c->products_count }}</td><td>{{ $c->is_active ? 'Aktif' : 'Nonaktif' }}</td><td>
<a href="{{ route('categories.edit', $c) }}">Edit</a>
<form method="POST" action="{{ route('categories.destroy', $c) }}" style="display:inline" onsubmit="return confirm('Hapus kategori ini? Kategori yang masih punya produk akan dinonaktifkan, bukan dihapus.');">@csrf @method('DELETE')<button>Hapus / Arsipkan</button></form>
</td></tr>@endforeach
</table>{{ $categories->links() }}</div>
@endsection
