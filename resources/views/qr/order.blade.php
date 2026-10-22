<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pesan — {{ $table->name }}</title></head>
<body><h1>Pesan dari {{ $table->name }}</h1><p>{{ $table->outlet->name }} — Nama dan nomor HP boleh kosong (pesan sebagai tamu). Pembayaran dilakukan di kasir (cash / EDC).</p>
<div id="msg"></div>
<form id="order-form">
<label>Nama (opsional)</label><input name="customer_name"><br>
<label>No. HP (opsional)</label><input name="customer_phone"><br>
<label>Catatan (opsional)</label><textarea name="note"></textarea>
@foreach($products as $p)<h3>{{ $p->name }}</h3>@foreach($p->variants as $v)
<label><input type="checkbox" class="variant" data-id="{{ $v->id }}" data-price="{{ $v->price }}"> {{ $v->name }} — Rp {{ number_format($v->price, 0, ',', '.') }}</label>
<label>Qty <input type="number" class="qty" data-id="{{ $v->id }}" value="1" min="1"></label><br>@endforeach @endforeach
<p><strong>Total: Rp <span id="total">0</span></strong></p>
<button>Kirim Pesanan</button></form>
<script>
const code = @json($table->qr_code);
function fmt(n){ return new Intl.NumberFormat('id-ID').format(n || 0); }
function recalc(){ let t=0; document.querySelectorAll('.variant:checked').forEach(cb=>{ const q=parseInt(document.querySelector('.qty[data-id="'+cb.dataset.id+'"]').value||0,10); t += parseInt(cb.dataset.price,10)*q; }); document.getElementById('total').textContent = fmt(t); }
document.addEventListener('change', recalc);
document.getElementById('order-form').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const items=[]; document.querySelectorAll('.variant:checked').forEach(cb=>{ items.push({product_variant_id: parseInt(cb.dataset.id,10), quantity: parseInt(document.querySelector('.qty[data-id="'+cb.dataset.id+'"]').value||1,10)}); });
  if(!items.length){ document.getElementById('msg').textContent='Pilih minimal satu item.'; return; }
  const fd=new FormData(e.target);
  const res=await fetch('/api/v1/qr/'+code+'/orders',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({items, customer_name: fd.get('customer_name')||null, customer_phone: fd.get('customer_phone')||null, note: fd.get('note')||null})});
  const json=await res.json();
  document.getElementById('msg').textContent = res.ok ? 'Pesanan terkirim! Order #'+json.data.id+' — silakan tunggu, pembayaran di kasir.' : 'Gagal: '+(json.message||JSON.stringify(json.errors||json));
});
</script></body></html>
