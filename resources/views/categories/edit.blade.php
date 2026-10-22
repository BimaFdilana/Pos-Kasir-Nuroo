@extends('layouts.app')
@section('content')
<h1>Edit Kategori</h1>
<div class="card">
<form method="POST" action="{{ route('categories.update', $category) }}">@csrf @method('PUT')
<label>Nama kategori</label><input name="name" value="{{ $category->name }}" required>
<label>Status</label><select name="is_active" required><option value="1" @selected($category->is_active)>Aktif</option><option value="0" @selected(!$category->is_active)>Nonaktif</option></select>
<button>Simpan Perubahan</button>
</form>
</div>
@endsection
