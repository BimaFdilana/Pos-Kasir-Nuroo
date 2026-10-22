@extends('layouts.app')
@section('content')
<h1>Jurnal Akuntansi</h1>
<div class="card"><p>Satu penjualan menghasilkan satu jurnal yang seimbang: debit Kas + Piutang EDC + Diskon + HPP sama dengan kredit Pendapatan + Persediaan Bahan. Void/refund menulis jurnal pembalik, bukan menghapus jurnal lama.</p>
<form method="GET" action="{{ route('accounting.journals') }}">
<label>Outlet</label><select name="outlet_id"><option value="">Semua outlet</option>@foreach($outlets as $o)<option value="{{ $o->id }}" @selected(request('outlet_id') == $o->id)>{{ $o->name }}</option>@endforeach</select>
<label>Dari</label><input type="date" name="from" value="{{ request('from') }}">
<label>Sampai</label><input type="date" name="to" value="{{ request('to') }}">
<button>Filter</button>
</form></div>
@foreach($entries as $e)<div class="card"><h2>{{ $e->entry_date->format('d M Y') }} — {{ $e->description }} @if($e->is_reversal)(pembalik)@endif</h2>
<p>Outlet: {{ $e->outlet?->name ?? '-' }} | Referensi: {{ $e->reference ?? '-' }}</p>
<table><tr><th>Akun</th><th>Debit</th><th>Kredit</th></tr>
@foreach($e->lines as $l)<tr><td>{{ $l->account?->code }} {{ $l->account?->name }}</td><td>Rp {{ number_format($l->debit, 0, ',', '.') }}</td><td>Rp {{ number_format($l->credit, 0, ',', '.') }}</td></tr>@endforeach
<tr><td><strong>Total</strong></td><td><strong>Rp {{ number_format($e->lines->sum('debit'), 0, ',', '.') }}</strong></td><td><strong>Rp {{ number_format($e->lines->sum('credit'), 0, ',', '.') }}</strong></td></tr>
</table></div>@endforeach
@endsection
