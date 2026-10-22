@extends('layouts.app')
@section('content')
<h1>Kasir — Buat Penjualan</h1>
<div class="card">
<p>Tambahkan beberapa item dan beberapa pembayaran. Total pembayaran harus sama dengan total belanja; kembalian cash dihitung otomatis oleh server. Pembayaran MVP: cash dan EDC manual saja (bank, jenis kartu, kode approval dari slip EDC).</p>
<form method="POST" action="{{ route('sales.store') }}" id="sale-form">@csrf
<label>Outlet</label><select name="outlet_id" required>@foreach($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select>

<h3>Item</h3>
<div id="items"></div>
<button type="button" id="add-item">+ Tambah Item</button>

<h3>Pembayaran</h3>
<div id="payments"></div>
<button type="button" id="add-payment">+ Tambah Pembayaran</button>

<p><strong>Total belanja: Rp <span id="total">0</span></strong> | <strong>Total pembayaran: Rp <span id="paid">0</span></strong> | <strong>Selisih: Rp <span id="diff">0</span></strong></p>
<label>Catatan</label><textarea name="notes"></textarea>
<button>Simpan Penjualan</button>
</form>
</div>
<script>
const variants = @json($variants);
let itemIdx = 0, payIdx = 0;
function fmt(n){ return new Intl.NumberFormat('id-ID').format(n || 0); }
function variantOptions(selected){
  return variants.map(v => `<option value="${v.id}" data-price="${v.price}" ${v.id == selected ? 'selected' : ''}>${v.label}</option>`).join('');
}
function addItem(){
  const i = itemIdx++;
  const div = document.createElement('div');
  div.className = 'card';
  div.innerHTML = `<label>Varian</label><select name="items[${i}][product_variant_id]" class="item-variant" required>${variantOptions('')}</select>
  <label>Qty</label><input type="number" name="items[${i}][quantity]" class="item-qty" value="1" min="1" required>
  <span class="item-line">Rp 0</span> <button type="button" onclick="this.parentElement.remove(); recalc();">Hapus</button>`;
  document.getElementById('items').appendChild(div);
  recalc();
}
function addPayment(){
  const i = payIdx++;
  const div = document.createElement('div');
  div.className = 'card';
  div.innerHTML = `<label>Metode</label><select name="payments[${i}][method]" class="pay-method" required><option value="cash">Cash</option><option value="edc">EDC (manual)</option></select>
  <label>Nominal pembayaran (Rp)</label><input type="number" name="payments[${i}][amount]" class="pay-amount" min="1" required>
  <div class="cash-fields"><label>Cash: uang diterima (Rp)</label><input type="number" name="payments[${i}][tendered]" min="0"></div>
  <div class="edc-fields" style="display:none"><label>EDC: bank</label><input name="payments[${i}][bank]">
  <label>EDC: jenis kartu</label><select name="payments[${i}][card_type]"><option value="">—</option><option value="debit">Debit</option><option value="credit">Kredit</option></select>
  <label>EDC: kode approval dari slip</label><input name="payments[${i}][approval_code]"></div>
  <button type="button" onclick="this.parentElement.remove(); recalc();">Hapus</button>`;
  div.querySelector('.pay-method').addEventListener('change', e => {
    const isEdc = e.target.value === 'edc';
    div.querySelector('.cash-fields').style.display = isEdc ? 'none' : 'block';
    div.querySelector('.edc-fields').style.display = isEdc ? 'block' : 'none';
  });
  document.getElementById('payments').appendChild(div);
  recalc();
}
function recalc(){
  let total = 0;
  document.querySelectorAll('#items .card').forEach(row => {
    const sel = row.querySelector('.item-variant');
    const price = parseInt(sel.selectedOptions[0]?.dataset.price || 0, 10);
    const qty = parseInt(row.querySelector('.item-qty').value || 0, 10);
    const line = price * qty;
    row.querySelector('.item-line').textContent = 'Rp ' + fmt(line);
    total += line;
  });
  let paid = 0;
  document.querySelectorAll('.pay-amount').forEach(el => { paid += parseInt(el.value || 0, 10); });
  document.getElementById('total').textContent = fmt(total);
  document.getElementById('paid').textContent = fmt(paid);
  document.getElementById('diff').textContent = fmt(total - paid);
}
document.addEventListener('input', recalc);
document.addEventListener('change', recalc);
document.getElementById('add-item').addEventListener('click', addItem);
document.getElementById('add-payment').addEventListener('click', addPayment);
addItem(); addPayment();
</script>
@endsection
