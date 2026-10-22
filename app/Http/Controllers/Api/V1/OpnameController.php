<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Opname;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class OpnameController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);

        return response()->json(
            Opname::where('tenant_id', $request->user()->tenant_id)
                ->with(['warehouse', 'ingredient', 'user'])
                ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
                ->when($request->filled('ingredient_id'), fn ($q) => $q->where('ingredient_id', $request->ingredient_id))
                ->latest()->paginate(50)
        );
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'ingredient_id' => ['required', 'integer'],
            'counted_quantity' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $warehouse = Warehouse::where('tenant_id', $request->user()->tenant_id)->findOrFail($data['warehouse_id']);
        $ingredient = Ingredient::where('tenant_id', $request->user()->tenant_id)->findOrFail($data['ingredient_id']);

        $opname = app(InventoryService::class)->recordOpname(
            $request->user(), $warehouse, $ingredient, (float) $data['counted_quantity'], $data['note'] ?? null,
        );

        return response()->json(['data' => $opname->load(['warehouse', 'ingredient'])], 201);
    }
}
