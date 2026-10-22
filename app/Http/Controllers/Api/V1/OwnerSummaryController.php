<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\OutletStock;
use App\Models\Sale;
use Illuminate\Http\Request;

class OwnerSummaryController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if (! $user->isSupervisor()) {
            abort(403, 'Only owner/manager can view the owner summary.');
        }

        $threshold = (int) $request->query('threshold', 5);
        $outletId = $request->query('outlet_id');
        $today = now()->toDateString();

        $salesQuery = Sale::where('tenant_id', $user->tenant_id)
            ->where('status', 'completed')
            ->whereDate('created_at', $today);

        if ($outletId) {
            $salesQuery->where('outlet_id', $outletId);
        }

        $sales = $salesQuery->with('payments')->get();
        $cashTotal = $sales->flatMap->payments->where('method', 'cash')->sum('amount');

        $outlets = Outlet::where('tenant_id', $user->tenant_id)
            ->when($outletId, fn ($q) => $q->where('id', $outletId))
            ->orderBy('name')
            ->get();

        $perOutlet = $outlets->map(function ($outlet) use ($sales) {
            $outletSales = $sales->where('outlet_id', $outlet->id);

            return [
                'outlet_id' => $outlet->id,
                'outlet_name' => $outlet->name,
                'sales_count' => $outletSales->count(),
                'total' => (int) $outletSales->sum('total'),
                'cash_total' => (int) $outletSales->flatMap->payments->where('method', 'cash')->sum('amount'),
            ];
        })->values();

        $lowStock = OutletStock::with(['outlet:id,name', 'variant:id,product_id,name,sku', 'variant.product:id,name'])
            ->whereHas('outlet', fn ($q) => $q->where('tenant_id', $user->tenant_id))
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('quantity', '<=', $threshold)
            ->orderBy('quantity')
            ->get()
            ->map(fn ($stock) => [
                'outlet_id' => $stock->outlet_id,
                'outlet_name' => $stock->outlet?->name,
                'product_variant_id' => $stock->product_variant_id,
                'product_name' => $stock->variant?->product?->name,
                'variant_name' => $stock->variant?->name,
                'sku' => $stock->variant?->sku,
                'quantity' => (int) $stock->quantity,
                'min_stock' => (int) $stock->min_stock,
            ]);

        return response()->json([
            'data' => [
                'date' => $today,
                'sales_count' => $sales->count(),
                'total' => (int) $sales->sum('total'),
                'cash_total' => (int) $cashTotal,
                'per_outlet' => $perOutlet,
                'low_stock' => $lowStock,
                'low_stock_threshold' => $threshold,
            ],
        ]);
    }
}
