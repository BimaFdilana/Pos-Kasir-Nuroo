@extends('layouts.app')
@section('content')
<h1>Edit Meja — {{ $table->name }}</h1>
<div class="card"><form method="POST" action="{{ route('dining-tables.update', $table) }}">@csrf @method('PUT')
<label>Nama meja</label><input name="name" value="{{ $table->name }}" required>
<label><input type="checkbox" name="is_active" value="1" {{ $table->is_active ? 'checked' : '' }}> Meja aktif (bila nonaktif, QR-nya tidak bisa dipakai pesan)</label>
<button>Simpan</button></form></div>
@endsection
