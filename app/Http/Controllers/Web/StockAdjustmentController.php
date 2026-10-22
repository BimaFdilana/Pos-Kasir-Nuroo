<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OutletStock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;

        return view('stock-adjustments.index', [
            'outlets' => $tenant ? $tenant->outlets()->orderBy('name')->get() : collect(),
            'variants' => $tenant
                ? $tenant->products()->with('variants')->where('is_active', true)->get()
                    ->flatMap(fn ($p) => $p->variants->map(fn ($v) => ['id' => $v->id, 'label' => $p->name.' - '.$v->name]))
                : collect(),
            'movements' => $tenant
                ? StockMovement::where('tenant_id', $tenant->id)->where('type', 'adjustment')
                    ->with(['outlet', 'variant'])->latest()->paginate(50)
                : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);

        $data = $request->validate([
            'outlet_id' => ['required', 'exists:outlets,id'],
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $outlet = $tenant->outlets()->findOrFail($data['outlet_id']);
        $variant = $tenant->products()->with('variants')
            ->get()->flatMap(fn ($p) => $p->variants)
            ->firstWhere('id', (int) $data['product_variant_id']);
        abort_unless($variant, 404);

        DB::transaction(function () use ($tenant, $outlet, $variant, $data, $request) {
            $stock = OutletStock::where('outlet_id', $outlet->id)
                ->where('product_variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                $stock = OutletStock::create([
                    'outlet_id' => $outlet->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => 0,
                ]);
            }

            $after = $stock->quantity + (int) $data['quantity_change'];
            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity_change' => 'Stok hasil penyesuaian tidak boleh negatif.',
                ]);
            }

            $stock->update(['quantity' => $after]);

            StockMovement::create([
                'tenant_id' => $tenant->id,
                'outlet_id' => $outlet->id,
                'product_variant_id' => $variant->id,
                'sale_id' => null,
                'user_id' => $request->user()->id,
                'type' => 'adjustment',
                'quantity_change' => (int) $data['quantity_change'],
                'quantity_after' => $after,
                'reason' => $data['reason'],
            ]);
        });

        return redirect()->route('stock-adjustments.index')->with('status', 'Penyesuaian stok dicatat.');
    }
}
