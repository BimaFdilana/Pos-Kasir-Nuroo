<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        $ingredients = Ingredient::where('tenant_id', $request->user()->tenant_id)
            ->with('stocks.warehouse')
            ->orderBy('name')->paginate(50);

        $ingredients->getCollection()->transform(function (Ingredient $ing) {
            $total = $ing->stocks->sum(fn ($s) => (float) $s->quantity);

            return [
                'id' => $ing->id, 'name' => $ing->name, 'unit' => $ing->unit,
                'cost_per_unit' => $ing->cost_per_unit, 'min_stock' => $ing->min_stock,
                'is_active' => $ing->is_active, 'total_stock' => $total,
                'low_stock' => $ing->min_stock !== null && $total <= (float) $ing->min_stock,
                'stocks' => $ing->stocks->map(fn ($s) => [
                    'warehouse_id' => $s->warehouse_id,
                    'warehouse' => $s->warehouse?->name,
                    'quantity' => (float) $s->quantity,
                ]),
            ];
        });

        return response()->json($ingredients);
    }

    public function show(Request $request, Ingredient $ingredient)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_if($ingredient->tenant_id !== $request->user()->tenant_id, 404);

        return response()->json(['data' => $ingredient->load('stocks.warehouse')]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:20'],
            'cost_per_unit' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $ingredient = Ingredient::create($data + ['tenant_id' => $request->user()->tenant_id]);

        return response()->json(['data' => $ingredient], 201);
    }

    public function update(Request $request, Ingredient $ingredient)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_if($ingredient->tenant_id !== $request->user()->tenant_id, 404);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'cost_per_unit' => ['sometimes', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $ingredient->update($data);

        return response()->json(['data' => $ingredient->fresh()]);
    }

    public function destroy(Request $request, Ingredient $ingredient)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_if($ingredient->tenant_id !== $request->user()->tenant_id, 404);
        if ($ingredient->recipes()->exists() || $ingredient->stocks()->where('quantity', '>', 0)->exists()
            || \App\Models\IngredientStockMovement::where('ingredient_id', $ingredient->id)->exists()) {
            return response()->json(['message' => 'Ingredient cannot be deleted: it has recipes, stock, or movement history. Deactivate it instead.'], 422);
        }
        $ingredient->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
