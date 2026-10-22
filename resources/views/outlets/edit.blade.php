@extends('layouts.app')
@section('content')
<h1>Edit Outlet</h1>
<div class="card">
<form method="POST" action="{{ route('outlets.update', $outlet) }}">@csrf @method('PUT')
<label>Nama</label><input name="name" value="{{ $outlet->name }}" required>
<label>Kode</label><input name="code" value="{{ $outlet->code }}">
<label>Alamat</label><textarea name="address">{{ $outlet->address }}</textarea>
<label>Status</label><select name="is_active" required><option value="1" @selected($outlet->is_active)>Aktif</option><option value="0" @selected(!$outlet->is_active)>Nonaktif</option></select>
<button>Simpan Perubahan</button>
</form>
</div>
@endsection
