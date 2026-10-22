<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;

        return view('inventory.warehouses', [
            'warehouses' => $tenant
                ? Warehouse::where('tenant_id', $tenant->id)->with(['outlet', 'stocks.ingredient'])->orderBy('name')->get()
                : collect(),
            'outlets' => $tenant ? $tenant->outlets()->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'outlet_id' => ['nullable', 'exists:outlets,id'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        if (! empty($data['outlet_id'])) {
            $tenant->outlets()->findOrFail($data['outlet_id']);
        }
        if (! empty($data['is_default'])) {
            Warehouse::where('tenant_id', $tenant->id)->update(['is_default' => false]);
        }
        Warehouse::create($data + ['tenant_id' => $tenant->id]);

        return redirect()->route('inventory.warehouses')->with('status', 'Gudang ditambahkan.');
    }
}
