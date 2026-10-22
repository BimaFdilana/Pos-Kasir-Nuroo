@extends('layouts.app')
@section('content')
<h1>Meja</h1>
@if(session('status'))<p>{{ session('status') }}</p>@endif
<div class="card"><h2>Tambah meja (owner/manager)</h2>
<form method="POST" action="{{ route('dining-tables.store') }}">@csrf
<label>Outlet</label><select name="outlet_id" required>@foreach($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select>
<label>Nama meja</label><input name="name" required placeholder="cth: Meja 1">
<button>Simpan</button></form></div>
<div class="card"><p>Tempel QR di tiap meja dengan membuka halaman tamu <code>/qr/{kode}</code> atau memindai kode di bawah lewat generator QR eksternal. Pelanggan tidak perlu login atau install aplikasi.</p>
<table><tr><th>Meja</th><th>Outlet</th><th>Status</th><th>Aktif</th><th>Kode QR</th><th></th></tr>
@foreach($tables as $t)<tr><td>{{ $t->name }}</td><td>{{ $t->outlet->name ?? '-' }}</td><td>{{ $t->status }}</td><td>{{ $t->is_active ? 'Ya' : 'Tidak' }}</td><td><a href="{{ route('qr.guest', $t->qr_code) }}">{{ $t->qr_code }}</a></td><td>
<a href="{{ route('dining-tables.edit', $t) }}">Edit</a>
<form method="POST" action="{{ route('dining-tables.regenerate', $t) }}" style="display:inline" onsubmit="return confirm('Regenerasi kode QR? Kode lama tidak berlaku lagi dan QR lama harus dicetak ulang.');">@csrf<button>Regenerasi QR</button></form>
</td></tr>@endforeach
</table>{{ $tables->links() }}</div>
@endsection
