@extends('layouts.app')
@section('content')
<h1>Laba Rugi Sederhana</h1>
<div class="card"><p>Laba kotor = Pendapatan − Diskon − HPP. Penjualan yang di-void/refund ikut mengurangi total lewat jurnal pembaliknya, jadi angkanya sudah bersih.</p>
<form method="GET" action="{{ route('accounting.profit-loss') }}">
<label>Outlet</label><select name="outlet_id"><option value="">Semua outlet</option>@foreach($outlets as $o)<option value="{{ $o->id }}" @selected(request('outlet_id') == $o->id)>{{ $o->name }}</option>@endforeach</select>
<label>Dari</label><input type="date" name="from" value="{{ request('from') }}">
<label>Sampai</label><input type="date" name="to" value="{{ request('to') }}">
<button>Filter</button>
</form></div>
<div class="card"><table>
<tr><td>Pendapatan penjualan</td><td>Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</td></tr>
<tr><td>Diskon</td><td>(Rp {{ number_format($summary['discounts'], 0, ',', '.') }})</td></tr>
<tr><td>Harga pokok penjualan (HPP)</td><td>(Rp {{ number_format($summary['cogs'], 0, ',', '.') }})</td></tr>
<tr><td><strong>Laba kotor</strong></td><td><strong>Rp {{ number_format($summary['gross_profit'], 0, ',', '.') }}</strong></td></tr>
</table></div>
@endsection
