@extends('layouts.app')
@section('content')
<h1>Outlet</h1>
<div class="card"><h2>Tambah outlet (owner/manager)</h2>
<form method="POST" action="{{ route('outlets.store') }}">@csrf
<label>Nama</label><input name="name" required>
<label>Kode</label><input name="code">
<label>Alamat</label><textarea name="address"></textarea>
<button>Simpan</button>
</form></div>
<div class="card"><table><tr><th>Nama</th><th>Kode</th><th>Alamat</th><th>Status</th><th></th></tr>
@foreach($outlets as $o)<tr><td>{{ $o->name }}</td><td>{{ $o->code }}</td><td>{{ $o->address }}</td><td>{{ $o->is_active ? 'Aktif' : 'Nonaktif' }}</td><td>
<a href="{{ route('outlets.edit', $o) }}">Edit</a>
<form method="POST" action="{{ route('outlets.destroy', $o) }}" style="display:inline" onsubmit="return confirm('Hapus outlet ini? Outlet yang sudah ada penjualannya akan dinonaktifkan, bukan dihapus.');">@csrf @method('DELETE')<button>Hapus / Arsipkan</button></form>
</td></tr>@endforeach
</table>{{ $outlets->links() }}</div>
@endsection
