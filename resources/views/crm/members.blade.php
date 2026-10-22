@extends('layouts.app')
@section('content')
<h1>Member CRM</h1>
<div class="card"><p>Saldo poin selalu dihitung dari buku poin (ledger), bukan kolom tersendiri. Poin didapat 1 per Rp 10.000 total penjualan, nilainya Rp 100 per poin saat ditukar.</p>
<form method="POST" action="{{ route('crm.members.store') }}">@csrf
<label>Nama</label><input name="name" required>
<label>Nomor HP (unik per usaha)</label><input name="phone" required>
<label>Email (opsional)</label><input type="email" name="email">
<label>Tanggal lahir (opsional)</label><input type="date" name="birthdate">
<label>Catatan</label><input name="notes">
<button>Tambah Member</button>
</form></div>
<div class="card"><h2>Daftar member</h2>
<table><tr><th>Nama</th><th>HP</th><th>Email</th><th>Saldo poin</th></tr>
@foreach($members as $m)<tr><td>{{ $m->name }}</td><td>{{ $m->phone }}</td><td>{{ $m->email ?? '-' }}</td><td>{{ $m->points_balance }}</td></tr>@endforeach
</table></div>
@endsection
