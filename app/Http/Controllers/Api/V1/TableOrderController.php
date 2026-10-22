<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TableOrder;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TableOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = TableOrder::where('tenant_id', $request->user()->tenant_id)
            ->with(['items', 'diningTable', 'outlet'])
            ->when($request->filled('outlet_id'), fn ($q) => $q->where('outlet_id', $request->outlet_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(50);

        return response()->json($orders);
    }

    public function updateStatus(Request $request, TableOrder $order)
    {
        abort_if($order->tenant_id !== $request->user()->tenant_id, 404);
        $data = $request->validate(['status' => ['required', 'in:preparing,ready,served,cancelled']]);
        $allowed = TableOrder::TRANSITIONS[$order->status] ?? [];
        if (! in_array($data['status'], $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move order from {$order->status} to {$data['status']}.",
            ]);
        }
        $order->update(['status' => $data['status']]);
        $order->diningTable->refreshStatus();

        return response()->json(['data' => $order->fresh(['items', 'diningTable'])]);
    }

    public function convert(Request $request, TableOrder $order, SaleService $service)
    {
        abort_if($order->tenant_id !== $request->user()->tenant_id, 404);
        if ($order->status === 'paid' && $order->sale_id) {
            return response()->json(['data' => $order->sale->load(['items', 'payments']), 'duplicate' => true], 200);
        }
        if (! $order->isOpen()) {
            throw ValidationException::withMessages(['order' => 'Only an open order can be converted to a sale.']);
        }

        $data = $request->validate([
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:cash,edc'],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'payments.*.tendered' => ['nullable', 'integer', 'min:0'],
            'payments.*.bank' => ['nullable', 'string', 'max:100'],
            'payments.*.card_type' => ['nullable', 'in:debit,credit'],
            'payments.*.approval_code' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $sale = $service->createSale($request->user(), [
                'outlet_id' => $order->outlet_id,
                'items' => $order->items->map(fn ($i) => [
                    'product_variant_id' => $i->product_variant_id, 'quantity' => $i->quantity,
                ])->all(),
                'payments' => $data['payments'],
                'notes' => 'QR order #'.$order->id.' meja '.$order->diningTable->name,
                // Idempotency: a retried conversion returns the same sale.
                'client_reference' => 'qr-order-'.$order->id,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Order could not be converted to a sale.', 'errors' => $e->errors()], 422);
        }

        DB::transaction(function () use ($order, $sale) {
            $order->update(['status' => 'paid', 'sale_id' => $sale->id]);
            $order->diningTable->refreshStatus();
        });

        return response()->json(['data' => $sale->load(['items', 'payments'])], 201);
    }
}
