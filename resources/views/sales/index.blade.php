@extends('layouts.app')
@section('content')
<h1>Penjualan</h1>
<div class="card"><table><tr><th>Nomor</th><th>Tanggal</th><th>Outlet</th><th>Kasir</th><th>Status</th><th>Total</th><th></th></tr>
@foreach($sales as $s)<tr><td>{{ $s->sale_number }}</td><td>{{ $s->created_at }}</td><td>{{ $s->outlet?->name }}</td><td>{{ $s->cashier?->name }}</td><td>{{ $s->status }}</td><td>Rp {{ number_format($s->total, 0, ',', '.') }}</td><td><a href="{{ route('sales.show', $s) }}">Detail</a></td></tr>@endforeach
</table>{{ $sales->links() }}</div>
@endsection
