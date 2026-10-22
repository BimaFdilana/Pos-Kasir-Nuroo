@extends('layouts.app')
@section('content')
<h1>Voucher &amp; Kampanye</h1>
<div class="card"><p>Satu penjualan hanya boleh memakai satu voucher. Voucher persen maksimal 100, voucher nominal dipotong sampai total item. Pemakaian dilepas lagi bila penjualan di-void/refund.</p>
<form method="POST" action="{{ route('crm.vouchers.store') }}">@csrf
<label>Kode</label><input name="code" required>
<label>Jenis diskon</label><select name="discount_type"><option value="percent">Persen</option><option value="fixed">Nominal (Rp)</option></select>
<label>Nilai</label><input type="number" min="1" name="value" required>
<label>Berlaku dari (opsional)</label><input type="date" name="valid_from">
<label>Berlaku sampai (opsional)</label><input type="date" name="valid_to">
<label>Maksimal pemakaian (opsional, kosong = tanpa batas)</label><input type="number" min="1" name="max_uses">
<button>Tambah Voucher</button>
</form></div>
<div class="card"><h2>Daftar voucher</h2>
<table><tr><th>Kode</th><th>Jenis</th><th>Nilai</th><th>Berlaku</th><th>Terpakai</th><th>Aktif</th></tr>
@foreach($vouchers as $v)<tr><td>{{ $v->code }}</td><td>{{ $v->discount_type }}</td><td>{{ $v->discount_type === 'percent' ? $v->value.'%' : 'Rp '.number_format($v->value, 0, ',', '.') }}</td><td>{{ $v->valid_from ?? '-' }} s/d {{ $v->valid_to ?? '-' }}</td><td>{{ $v->uses_count }}{{ $v->max_uses ? ' / '.$v->max_uses : '' }}</td><td>{{ $v->is_active ? 'Ya' : 'Tidak' }}</td></tr>@endforeach
</table></div>
@endsection
