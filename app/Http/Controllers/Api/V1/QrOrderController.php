<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TableOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QrOrderController extends Controller
{
    /** Public: no login, no app install. Disabled or unknown codes are 404. */
    protected function findTable(string $code): DiningTable
    {
        return DiningTable::where('qr_code', $code)->where('is_active', true)->firstOrFail();
    }

    public function show(string $code)
    {
        $table = $this->findTable($code);
        $products = Product::where('tenant_id', $table->tenant_id)
            ->where('is_active', true)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name,
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $v->id, 'name' => $v->name, 'price' => $v->price,
                ])->values(),
            ]);

        return response()->json([
            'data' => [
                'outlet' => ['id' => $table->outlet->id, 'name' => $table->outlet->name],
                'table' => ['id' => $table->id, 'name' => $table->name, 'status' => $table->status],
                'products' => $products,
            ],
        ]);
    }

    public function store(Request $request, string $code)
    {
        $table = $this->findTable($code);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            // Guest ordering: name and phone are optional, never required.
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // Stock is deliberately NOT validated here: a QR order is an open
        // bill, not a sale. Stock is validated when the cashier converts
        // the order to a paid sale (see TableOrderController::convert).
        $prepared = [];
        foreach ($data['items'] as $item) {
            $variant = ProductVariant::where('is_active', true)
                ->whereHas('product', fn ($q) => $q->where('tenant_id', $table->tenant_id))
                ->find($item['product_variant_id']);
            if (! $variant) {
                throw ValidationException::withMessages(['items' => 'One of the ordered variants is not available at this outlet.']);
            }
            $prepared[] = [
                'variant' => $variant,
                'quantity' => (int) $item['quantity'],
                'line' => $variant->price * (int) $item['quantity'],
            ];
        }

        $order = DB::transaction(function () use ($table, $data, $prepared) {
            $order = TableOrder::create([
                'tenant_id' => $table->tenant_id,
                'outlet_id' => $table->outlet_id,
                'dining_table_id' => $table->id,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => 'new',
            ]);
            foreach ($prepared as $row) {
                $order->items()->create([
                    'product_variant_id' => $row['variant']->id,
                    'product_name' => $row['variant']->product->name,
                    'variant_name' => $row['variant']->name,
                    'unit_price' => $row['variant']->price,
                    'quantity' => $row['quantity'],
                    'subtotal' => $row['line'],
                ]);
            }
            $table->refreshStatus();

            return $order->fresh(['items', 'diningTable']);
        });

        return response()->json(['data' => $order], 201);
    }
}
