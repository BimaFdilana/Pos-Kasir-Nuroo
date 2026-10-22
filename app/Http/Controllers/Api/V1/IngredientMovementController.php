<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\IngredientStockMovement;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class IngredientMovementController extends Controller
{
    protected function warehouseFor(Request $request, int $id): Warehouse
    {
        return Warehouse::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
    }

    protected function ingredientFor(Request $request, int $id): Ingredient
    {
        return Ingredient::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);

        return response()->json(
            IngredientStockMovement::where('tenant_id', $request->user()->tenant_id)
                ->with(['warehouse', 'ingredient', 'user'])
                ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
                ->when($request->filled('ingredient_id'), fn ($q) => $q->where('ingredient_id', $request->ingredient_id))
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
                ->latest()->paginate(50)
        );
    }

    /** Purchase (stock in) or manual adjustment (signed change). Reason is required. */
    public function store(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'ingredient_id' => ['required', 'integer'],
            'type' => ['required', 'in:purchase,adjustment'],
            'quantity_change' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        if ($data['type'] === 'purchase' && (float) $data['quantity_change'] <= 0) {
            return response()->json(['message' => 'Purchase quantity must be positive.'], 422);
        }

        $movement = app(InventoryService::class)->applyMovement(
            $request->user(),
            $this->warehouseFor($request, (int) $data['warehouse_id']),
            $this->ingredientFor($request, (int) $data['ingredient_id']),
            $data['type'], (float) $data['quantity_change'], $data['reason'],
        );

        return response()->json(['data' => $movement], 201);
    }

    public function transfer(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        $data = $request->validate([
            'source_warehouse_id' => ['required', 'integer'],
            'destination_warehouse_id' => ['required', 'integer', 'different:source_warehouse_id'],
            'ingredient_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $group = app(InventoryService::class)->transfer(
            $request->user(),
            $this->warehouseFor($request, (int) $data['source_warehouse_id']),
            $this->warehouseFor($request, (int) $data['destination_warehouse_id']),
            $this->ingredientFor($request, (int) $data['ingredient_id']),
            (float) $data['quantity'], $data['reason'],
        );

        return response()->json(['data' => ['transfer_group' => $group]], 201);
    }
}
