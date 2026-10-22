@extends('layouts.app')
@section('content')
<h1>Edit Produk</h1>
<div class="card">
<form method="POST" action="{{ route('products.update', $product) }}">@csrf @method('PUT')
<label>Kategori</label><select name="category_id"><option value="">—</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected($product->category_id == $c->id)>{{ $c->name }}</option>@endforeach</select>
<label>Nama produk</label><input name="name" value="{{ $product->name }}" required>
<label>SKU</label><input name="sku" value="{{ $product->sku }}">
<label>Deskripsi</label><textarea name="description">{{ $product->description }}</textarea>
<label>Status</label><select name="is_active" required><option value="1" @selected($product->is_active)>Aktif</option><option value="0" @selected(!$product->is_active)>Nonaktif</option></select>
@php $v = $product->variants->first(); @endphp
@if($v)
<label>Nama varian utama</label><input name="variant_name" value="{{ $v->name }}">
<label>Harga varian utama (Rp)</label><input type="number" name="variant_price" value="{{ $v->price }}" min="0">
<p>Harga lama pada penjualan yang sudah terjadi tidak berubah (snapshot di item penjualan).</p>
@endif
<button>Simpan Perubahan</button>
</form>
</div>
@endsection
