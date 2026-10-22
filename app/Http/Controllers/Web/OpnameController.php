<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\Opname;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class OpnameController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;

        return view('inventory.opnames', [
            'warehouses' => $tenant ? Warehouse::where('tenant_id', $tenant->id)->orderBy('name')->get() : collect(),
            'ingredients' => $tenant ? Ingredient::where('tenant_id', $tenant->id)->orderBy('name')->get() : collect(),
            'opnames' => $tenant
                ? Opname::where('tenant_id', $tenant->id)->with(['warehouse', 'ingredient', 'user'])->latest()->paginate(50)
                : collect(),
            'current' => function (int $warehouseId, int $ingredientId) {
                $stock = IngredientStock::where('warehouse_id', $warehouseId)->where('ingredient_id', $ingredientId)->first();

                return $stock ? (float) $stock->quantity : 0.0;
            },
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'ingredient_id' => ['required', 'integer'],
            'counted_quantity' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $warehouse = Warehouse::where('tenant_id', $tenant->id)->findOrFail($data['warehouse_id']);
        $ingredient = Ingredient::where('tenant_id', $tenant->id)->findOrFail($data['ingredient_id']);
        $opname = app(InventoryService::class)->recordOpname(
            $request->user(), $warehouse, $ingredient, (float) $data['counted_quantity'], $data['note'] ?? null,
        );

        return redirect()->route('inventory.opnames')
            ->with('status', "Opname dicatat. Selisih: {$opname->difference} (sistem {$opname->system_quantity} → hitung {$opname->counted_quantity}).");
    }
}
