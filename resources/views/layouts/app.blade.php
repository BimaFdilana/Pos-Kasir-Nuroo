<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pos-Kasir-Nuroo</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f6f6f6;color:#222}
header{background:#111827;color:#fff;padding:12px 20px;display:flex;gap:16px;align-items:center}
header a{color:#fff;text-decoration:none} header nav a{margin-right:12px}
main{max-width:1000px;margin:20px auto;padding:0 16px}
.card{background:#fff;padding:16px;border-radius:8px;margin-bottom:16px}
table{width:100%;border-collapse:collapse} th,td{padding:8px;border-bottom:1px solid #eee;text-align:left}
input,select,textarea{padding:8px;margin:4px 0;width:100%;box-sizing:border-box}
button{padding:8px 14px;cursor:pointer}
.alert{background:#ecfdf5;padding:10px;border-radius:6px;margin-bottom:12px}
.errors{background:#fef2f2;padding:10px;border-radius:6px;margin-bottom:12px}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
</style>
</head>
<body>
<header>
<strong>Pos-Kasir-Nuroo</strong>
<nav>
@if(auth()->check())
<a href="{{ route('dashboard') }}">Dashboard</a>
<a href="{{ route('products.index') }}">Produk</a>
<a href="{{ route('categories.index') }}">Kategori</a>
<a href="{{ route('outlets.index') }}">Outlet</a>
<a href="{{ route('sales.index') }}">Penjualan</a>
<a href="{{ route('sales.create') }}">Kasir</a>
@if(in_array(auth()->user()->role, ['owner', 'manager'], true))
<a href="{{ route('stock-adjustments.index') }}">Penyesuaian Stok</a>
@endif
@endif
</nav>
<span style="margin-left:auto">
@if(auth()->check())
{{ auth()->user()->name }} ({{ auth()->user()->role ?? '-' }})
<form method="POST" action="{{ route('logout') }}" style="display:inline">@csrf<button>Keluar</button></form>
@endif
</span>
</header>
<main>
@if(session('status'))<div class="alert">{{ session('status') }}</div>@endif
@if($errors->any())<div class="errors"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@yield('content')
</main>
</body>
</html>
