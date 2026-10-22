@extends('layouts.app')
@section('content')
<div class="card" style="max-width:420px;margin:auto">
<h1>Masuk</h1>
<p>Buat akun dulu lewat perintah <code>php artisan pos:create-user</code> (password diminta interaktif, tidak tersimpan di kode).</p>
<form method="POST" action="{{ route('login') }}">@csrf
<label>Email</label><input type="email" name="email" value="{{ old('email') }}" required>
<label>Password</label><input type="password" name="password" required>
<button>Masuk</button>
</form>
</div>
@endsection
