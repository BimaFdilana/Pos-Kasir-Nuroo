<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        $variants = $tenant
            ? $tenant->products()->with('variants')->where('is_active', true)->get()
                ->flatMap(fn ($p) => $p->variants->map(fn ($v) => ['id' => $v->id, 'label' => $p->name.' - '.$v->name]))
            : collect();
        $selectedId = (int) $request->query('product_variant_id', $variants->first()['id'] ?? 0);
        $selected = $selectedId
            ? ProductVariant::whereHas('product', fn ($q) => $q->where('tenant_id', $tenant?->id))->find($selectedId)
            : null;
        $lines = $selected ? Recipe::where('product_variant_id', $selected->id)->with('ingredient')->get() : collect();

        return view('inventory.recipes', [
            'variants' => $variants,
            'ingredients' => $tenant ? Ingredient::where('tenant_id', $tenant->id)->orderBy('name')->get() : collect(),
            'selected' => $selected,
            'lines' => $lines,
            'hpp' => $selected ? app(InventoryService::class)->hppForVariant($selected) : null,
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer'],
            'ingredient_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $variant = ProductVariant::whereHas('product', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->findOrFail($data['product_variant_id']);
        $ingredient = Ingredient::where('tenant_id', $tenant->id)->findOrFail($data['ingredient_id']);
        Recipe::updateOrCreate(
            ['product_variant_id' => $variant->id, 'ingredient_id' => $ingredient->id],
            ['quantity' => $data['quantity']],
        );

        return redirect()->route('inventory.recipes', ['product_variant_id' => $variant->id])
            ->with('status', 'Resep diperbarui.');
    }

    public function destroy(Request $request, Recipe $recipe)
    {
        abort_unless($recipe->variant->product->tenant_id === $request->user()->tenant_id, 404);
        $variantId = $recipe->product_variant_id;
        $recipe->delete();

        return redirect()->route('inventory.recipes', ['product_variant_id' => $variantId])
            ->with('status', 'Bahan dihapus dari resep.');
    }
}
