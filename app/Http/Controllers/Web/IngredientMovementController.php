<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\IngredientStockMovement;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class IngredientMovementController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;

        return view('inventory.movements', [
            'warehouses' => $tenant ? Warehouse::where('tenant_id', $tenant->id)->orderBy('name')->get() : collect(),
            'ingredients' => $tenant ? Ingredient::where('tenant_id', $tenant->id)->orderBy('name')->get() : collect(),
            'movements' => $tenant
                ? IngredientStockMovement::where('tenant_id', $tenant->id)->with(['warehouse', 'ingredient', 'user'])->latest()->paginate(50)
                : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'ingredient_id' => ['required', 'integer'],
            'type' => ['required', 'in:purchase,adjustment'],
            'quantity_change' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        if ($data['type'] === 'purchase' && (float) $data['quantity_change'] <= 0) {
            return back()->withErrors(['quantity_change' => 'Pembelian harus positif.'])->withInput();
        }
        $warehouse = Warehouse::where('tenant_id', $tenant->id)->findOrFail($data['warehouse_id']);
        $ingredient = Ingredient::where('tenant_id', $tenant->id)->findOrFail($data['ingredient_id']);
        app(InventoryService::class)->applyMovement(
            $request->user(), $warehouse, $ingredient, $data['type'], (float) $data['quantity_change'], $data['reason'],
        );

        return redirect()->route('inventory.movements')->with('status', 'Mutasi stok bahan dicatat.');
    }

    public function transfer(Request $request)
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);
        $data = $request->validate([
            'source_warehouse_id' => ['required', 'integer'],
            'destination_warehouse_id' => ['required', 'integer', 'different:source_warehouse_id'],
            'ingredient_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $source = Warehouse::where('tenant_id', $tenant->id)->findOrFail($data['source_warehouse_id']);
        $destination = Warehouse::where('tenant_id', $tenant->id)->findOrFail($data['destination_warehouse_id']);
        $ingredient = Ingredient::where('tenant_id', $tenant->id)->findOrFail($data['ingredient_id']);
        app(InventoryService::class)->transfer(
            $request->user(), $source, $destination, $ingredient, (float) $data['quantity'], $data['reason'],
        );

        return redirect()->route('inventory.movements')->with('status', 'Transfer antar gudang dicatat.');
    }
}
