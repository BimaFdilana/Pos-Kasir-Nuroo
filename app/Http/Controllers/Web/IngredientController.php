<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;

        return view('inventory.ingredients', [
            'ingredients' => $tenant
                ? Ingredient::where('tenant_id', $tenant->id)->with('stocks.warehouse')->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:20'],
            'cost_per_unit' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
        ]);
        Ingredient::create($data + ['tenant_id' => $tenant->id, 'is_active' => true]);

        return redirect()->route('inventory.ingredients')->with('status', 'Bahan baku ditambahkan.');
    }
}
