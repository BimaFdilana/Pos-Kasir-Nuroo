<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (! $tenant) {
            return response()->json(['message' => 'User is not assigned to a tenant.'], 403);
        }

        $products = $tenant->products()
            ->with(['category', 'variants'])
            ->where('is_active', true)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'category' => $p->category?->name,
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'sku' => $v->sku,
                    'price' => $v->price,
                    // Computed HPP per serving; null when the variant has no recipe.
                    'hpp_per_serving' => app(\App\Services\InventoryService::class)->hppForVariant($v),
                ]),
            ]);

        return response()->json(['data' => $products]);
    }
}
