<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;

        return view('categories.index', [
            'categories' => $tenant ? $tenant->categories()->withCount('products')->latest()->paginate(50) : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);

        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $tenant->categories()->create($data);

        return redirect()->route('categories.index')->with('status', 'Kategori ditambahkan.');
    }

    public function edit(Request $request, int $id)
    {
        $tenant = $request->user()->tenant;
        $category = $tenant->categories()->findOrFail($id);

        return view('categories.edit', ['category' => $category]);
    }

    public function update(Request $request, int $id)
    {
        $tenant = $request->user()->tenant;
        $category = $tenant->categories()->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);
        $category->update($data);

        return redirect()->route('categories.index')->with('status', 'Kategori diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $tenant = $request->user()->tenant;
        $category = $tenant->categories()->findOrFail($id);

        // A category with products is archived, not deleted, so products
        // (and their sales history) are never silently orphaned by a
        // delete that looks harmless from the category list.
        if ($category->products()->exists()) {
            $category->update(['is_active' => false]);

            return redirect()->route('categories.index')->with('status', 'Kategori dinonaktifkan karena masih memiliki produk; produk tidak dihapus.');
        }

        $category->delete();

        return redirect()->route('categories.index')->with('status', 'Kategori dihapus.');
    }
}
