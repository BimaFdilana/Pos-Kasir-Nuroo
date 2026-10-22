<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    protected function variantFor(Request $request, int $variantId): ProductVariant
    {
        return ProductVariant::whereHas('product', fn ($q) => $q->where('tenant_id', $request->user()->tenant_id))
            ->findOrFail($variantId);
    }

    /** Lines for one variant, plus its computed HPP per serving. */
    public function index(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        $data = $request->validate(['product_variant_id' => ['required', 'integer']]);
        $variant = $this->variantFor($request, (int) $data['product_variant_id']);
        $lines = Recipe::where('product_variant_id', $variant->id)->with('ingredient')->get();

        return response()->json(['data' => [
            'product_variant_id' => $variant->id,
            'product' => $variant->product->name,
            'variant' => $variant->name,
            'price' => $variant->price,
            'hpp_per_serving' => app(InventoryService::class)->hppForVariant($variant),
            'lines' => $lines->map(fn (Recipe $r) => [
                'id' => $r->id, 'ingredient_id' => $r->ingredient_id,
                'ingredient' => $r->ingredient->name, 'unit' => $r->ingredient->unit,
                'quantity' => (float) $r->quantity, 'cost_per_unit' => $r->ingredient->cost_per_unit,
                'line_cost' => $r->lineCost(),
            ]),
        ]]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer'],
            'ingredient_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $variant = $this->variantFor($request, (int) $data['product_variant_id']);
        $ingredient = $request->user()->tenant->id
            ? \App\Models\Ingredient::where('tenant_id', $request->user()->tenant_id)->findOrFail($data['ingredient_id'])
            : abort(404);
        if (Recipe::where('product_variant_id', $variant->id)->where('ingredient_id', $ingredient->id)->exists()) {
            return response()->json(['message' => 'This ingredient is already in the recipe. Update its quantity instead.'], 422);
        }

        $recipe = Recipe::create([
            'product_variant_id' => $variant->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => $data['quantity'],
        ]);

        return response()->json(['data' => $recipe->load('ingredient')], 201);
    }

    public function update(Request $request, Recipe $recipe)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_unless($recipe->variant->product->tenant_id === $request->user()->tenant_id, 404);
        $data = $request->validate(['quantity' => ['required', 'numeric', 'gt:0']]);
        $recipe->update($data);

        return response()->json(['data' => $recipe->fresh('ingredient')]);
    }

    public function destroy(Request $request, Recipe $recipe)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_unless($recipe->variant->product->tenant_id === $request->user()->tenant_id, 404);
        $recipe->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
