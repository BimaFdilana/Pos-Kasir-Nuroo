<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);

        return response()->json(
            Voucher::where('tenant_id', $request->user()->tenant_id)
                ->orderBy('code')->paginate(50)
        );
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('vouchers', 'code')->where('tenant_id', $request->user()->tenant_id)],
            'discount_type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if ($data['discount_type'] === 'percent' && $data['value'] > 100) {
            return response()->json(['message' => 'Percent discount cannot exceed 100.'], 422);
        }

        $voucher = Voucher::create($data + ['tenant_id' => $request->user()->tenant_id]);

        return response()->json(['data' => $voucher], 201);
    }

    public function update(Request $request, Voucher $voucher)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_if($voucher->tenant_id !== $request->user()->tenant_id, 404);
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('vouchers', 'code')->where('tenant_id', $request->user()->tenant_id)->ignore($voucher->id)],
            'discount_type' => ['sometimes', 'in:percent,fixed'],
            'value' => ['sometimes', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $voucher->update($data);

        return response()->json(['data' => $voucher->fresh()]);
    }

    public function destroy(Request $request, Voucher $voucher)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_if($voucher->tenant_id !== $request->user()->tenant_id, 404);
        if ($voucher->usages()->exists()) {
            return response()->json(['message' => 'Voucher cannot be deleted: it has usage history. Deactivate it instead.'], 422);
        }
        $voucher->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
