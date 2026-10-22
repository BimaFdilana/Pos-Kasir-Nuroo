<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $sales = Sale::where('tenant_id', $request->user()->tenant_id)
            ->with(['items', 'payments'])
            ->latest()
            ->paginate(50);

        return response()->json($sales);
    }

    public function store(Request $request, SaleService $service)
    {
        $data = $request->validate([
            'outlet_id' => ['required', 'exists:outlets,id'],
            'client_reference' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            // Payments may be empty only for a sale fully covered by
            // points/voucher discount (total 0); the service still
            // rejects a non-zero total with no payments.
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required', 'in:cash,edc'],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'payments.*.tendered' => ['nullable', 'integer', 'min:0'],
            'payments.*.bank' => ['nullable', 'string', 'max:100'],
            'payments.*.card_type' => ['nullable', 'in:debit,credit'],
            'payments.*.approval_code' => ['nullable', 'string', 'max:100'],
            'discount_total' => ['nullable', 'integer', 'min:0'],
            'tax_total' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'member_id' => ['nullable', 'exists:members,id'],
            'redeem_points' => ['nullable', 'integer', 'min:0'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
        ]);

        // Idempotency check first, so the status code is reliable:
        // 200 = this client_reference was already processed, 201 = new sale.
        $duplicate = null;
        if (! empty($data['client_reference'])) {
            $duplicate = Sale::where('tenant_id', $request->user()->tenant_id)
                ->where('client_reference', $data['client_reference'])
                ->with(['items', 'payments'])
                ->first();
        }

        try {
            $sale = $service->createSale($request->user(), $data);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Sale could not be created.', 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'data' => $sale->load(['items', 'payments']),
            'duplicate' => $duplicate !== null,
        ], $duplicate !== null ? 200 : 201);
    }
}
