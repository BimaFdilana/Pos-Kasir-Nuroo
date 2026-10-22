<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);

        return response()->json(
            Warehouse::where('tenant_id', $request->user()->tenant_id)
                ->with(['outlet', 'stocks.ingredient'])
                ->orderBy('name')->paginate(50)
        );
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'outlet_id' => ['nullable', 'exists:outlets,id'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        if (! empty($data['outlet_id'])) {
            $request->user()->tenant->outlets()->findOrFail($data['outlet_id']);
        }
        if (! empty($data['is_default'])) {
            Warehouse::where('tenant_id', $request->user()->tenant_id)->update(['is_default' => false]);
        }

        $warehouse = Warehouse::create($data + ['tenant_id' => $request->user()->tenant_id]);

        return response()->json(['data' => $warehouse], 201);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_if($warehouse->tenant_id !== $request->user()->tenant_id, 404);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'outlet_id' => ['nullable', 'exists:outlets,id'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('outlet_id', $data) && $data['outlet_id']) {
            $request->user()->tenant->outlets()->findOrFail($data['outlet_id']);
        }
        if (! empty($data['is_default'])) {
            Warehouse::where('tenant_id', $request->user()->tenant_id)
                ->where('id', '!=', $warehouse->id)->update(['is_default' => false]);
        }
        $warehouse->update($data);

        return response()->json(['data' => $warehouse->fresh()]);
    }

    public function destroy(Request $request, Warehouse $warehouse)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_if($warehouse->tenant_id !== $request->user()->tenant_id, 404);
        if ($warehouse->stocks()->where('quantity', '>', 0)->exists()
            || \App\Models\IngredientStockMovement::where('warehouse_id', $warehouse->id)->exists()) {
            return response()->json(['message' => 'Warehouse cannot be deleted: it has stock or movement history.'], 422);
        }
        $warehouse->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
