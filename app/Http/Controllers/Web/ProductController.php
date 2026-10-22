<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OutletStock;
use App\Models\SaleItem;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        $products = $tenant
            ? $tenant->products()->with(['category', 'variants.stocks'])->latest()->paginate(50)
            : collect();

        return view('products.index', [
            'products' => $products,
            'categories' => $tenant ? $tenant->categories()->where('is_active', true)->orderBy('name')->get() : collect(),
            'outlets' => $tenant ? $tenant->outlets()->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);

        $data = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'variant_name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'integer', 'min:0'],
            'outlet_id' => ['nullable', 'exists:outlets,id'],
            'initial_stock' => ['nullable', 'integer', 'min:0'],
        ]);

        // Category must belong to this tenant.
        if (($data['category_id'] ?? null) && ! $tenant->categories()->whereKey($data['category_id'])->exists()) {
            return back()->withErrors(['category_id' => 'Category belongs to another tenant.']);
        }

        $product = $tenant->products()->create([
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
        ]);

        $variant = $product->variants()->create([
            'name' => $data['variant_name'],
            'price' => $data['price'],
        ]);

        if (! empty($data['outlet_id']) && ($data['initial_stock'] ?? 0) > 0) {
            $outlet = $tenant->outlets()->findOrFail($data['outlet_id']);
            OutletStock::create([
                'outlet_id' => $outlet->id,
                'product_variant_id' => $variant->id,
                'quantity' => $data['initial_stock'],
            ]);
        }

        return redirect()->route('products.index')->with('status', 'Produk ditambahkan.');
    }

    public function edit(Request $request, int $id)
    {
        $tenant = $request->user()->tenant;
        $product = $tenant->products()->with('variants')->findOrFail($id);

        return view('products.edit', [
            'product' => $product,
            'categories' => $tenant->categories()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $tenant = $request->user()->tenant;
        $product = $tenant->products()->findOrFail($id);

        $data = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'variant_name' => ['nullable', 'string', 'max:100'],
            'variant_price' => ['nullable', 'integer', 'min:0'],
        ]);

        if (($data['category_id'] ?? null) && ! $tenant->categories()->whereKey($data['category_id'])->exists()) {
            return back()->withErrors(['category_id' => 'Category belongs to another tenant.']);
        }

        $product->update([
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'],
        ]);

        // Keep the primary variant editable from the same form. Sale
        // snapshots are untouched, so past sales keep their old price.
        if (($data['variant_name'] ?? null) !== null || ($data['variant_price'] ?? null) !== null) {
            $variant = $product->variants()->first();
            if ($variant) {
                $variant->update([
                    'name' => $data['variant_name'] ?? $variant->name,
                    'price' => $data['variant_price'] ?? $variant->price,
                ]);
            }
        }

        return redirect()->route('products.index')->with('status', 'Produk diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $tenant = $request->user()->tenant;
        $product = $tenant->products()->findOrFail($id);
        $variantIds = $product->variants()->pluck('id');

        // Sales history must survive: archive instead of hard-deleting
        // a product that has ever been sold.
        if ($variantIds->isNotEmpty() && SaleItem::whereIn('product_variant_id', $variantIds)->exists()) {
            $product->update(['is_active' => false]);
            $product->variants()->update(['is_active' => false]);

            return redirect()->route('products.index')->with('status', 'Produk diarsipkan (nonaktif) karena sudah ada riwayat penjualan; data penjualan tidak dihapus.');
        }

        $product->delete();

        return redirect()->route('products.index')->with('status', 'Produk dihapus.');
    }
}
