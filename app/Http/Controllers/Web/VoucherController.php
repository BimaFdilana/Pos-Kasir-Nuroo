<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;

        return view('crm.vouchers', [
            'vouchers' => $tenant
                ? Voucher::where('tenant_id', $tenant->id)->orderBy('code')->get()
                : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('vouchers', 'code')->where('tenant_id', $tenant->id)],
            'discount_type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($data['discount_type'] === 'percent' && $data['value'] > 100) {
            return back()->withErrors(['value' => 'Diskon persen tidak boleh lebih dari 100.'])->withInput();
        }
        Voucher::create($data + ['tenant_id' => $tenant->id, 'is_active' => true]);

        return redirect()->route('crm.vouchers')->with('status', 'Voucher ditambahkan.');
    }
}
