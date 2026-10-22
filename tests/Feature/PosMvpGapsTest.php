<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\OutletStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosMvpGapsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Outlet $outlet;
    protected User $cashier;
    protected User $owner;
    protected ProductVariant $variant;
    protected ProductVariant $variant2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Cafe Test']);
        $this->outlet = Outlet::create(['tenant_id' => $this->tenant->id, 'name' => 'Outlet Utama']);
        $this->cashier = User::factory()->create([
            'tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id, 'role' => 'cashier',
        ]);
        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id, 'role' => 'owner',
        ]);
        $product = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Kopi Susu']);
        $this->variant = ProductVariant::create(['product_id' => $product->id, 'name' => 'Reguler', 'price' => 25000]);
        $product2 = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Roti']);
        $this->variant2 = ProductVariant::create(['product_id' => $product2->id, 'name' => 'Reguler', 'price' => 15000]);
        OutletStock::create(['outlet_id' => $this->outlet->id, 'product_variant_id' => $this->variant->id, 'quantity' => 10]);
        OutletStock::create(['outlet_id' => $this->outlet->id, 'product_variant_id' => $this->variant2->id, 'quantity' => 10]);
    }

    protected function makeSale(): Sale
    {
        return app(SaleService::class)->createSale($this->cashier, [
            'outlet_id' => $this->outlet->id,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 50000, 'tendered' => 50000]],
        ]);
    }

    public function test_web_multi_item_split_payment_sale(): void
    {
        $response = $this->actingAs($this->cashier)->post(route('sales.store'), [
            'outlet_id' => $this->outlet->id,
            'items' => [
                ['product_variant_id' => $this->variant->id, 'quantity' => 2],
                ['product_variant_id' => $this->variant2->id, 'quantity' => 1],
            ],
            // Total: 2*25000 + 15000 = 65000, split cash 40000 + EDC 25000.
            'payments' => [
                ['method' => 'cash', 'amount' => 40000, 'tendered' => 40000],
                ['method' => 'edc', 'amount' => 25000, 'bank' => 'BCA', 'card_type' => 'debit', 'approval_code' => '999'],
            ],
        ]);

        $response->assertRedirect();
        $sale = Sale::first();
        $this->assertSame(65000, $sale->total);
        $this->assertCount(2, $sale->items);
        $this->assertCount(2, $sale->payments);
        $this->assertSame(8, OutletStock::where('product_variant_id', $this->variant->id)->first()->quantity);
        $this->assertSame(9, OutletStock::where('product_variant_id', $this->variant2->id)->first()->quantity);
    }

    public function test_web_cashier_form_renders_with_variant_prices(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('Tambah Item')
            ->assertSee('Tambah Pembayaran');
    }

    public function test_product_edit_and_archive_when_sold(): void
    {
        $sale = $this->makeSale();
        $product = Product::where('name', 'Kopi Susu')->first();

        $this->actingAs($this->owner)
            ->put(route('products.update', $product), [
                'name' => 'Kopi Susu Gula Aren', 'is_active' => true,
            ])
            ->assertRedirect();
        $this->assertSame('Kopi Susu Gula Aren', $product->fresh()->name);

        // Destroy archives instead of deleting: sale history survives.
        $this->actingAs($this->owner)
            ->delete(route('products.destroy', $product))
            ->assertRedirect();
        $this->assertNotNull(Product::find($product->id));
        $this->assertFalse(Product::find($product->id)->is_active);
        $this->assertSame(1, Sale::count());
    }

    public function test_product_without_sales_is_hard_deleted(): void
    {
        $product = Product::where('name', 'Roti')->first();

        $this->actingAs($this->owner)
            ->delete(route('products.destroy', $product))
            ->assertRedirect();
        $this->assertNull(Product::find($product->id));
    }

    public function test_outlet_edit_and_archive_when_sold(): void
    {
        $this->makeSale();

        $this->actingAs($this->owner)
            ->put(route('outlets.update', $this->outlet), [
                'name' => 'Outlet Pusat', 'is_active' => true,
            ])
            ->assertRedirect();
        $this->assertSame('Outlet Pusat', $this->outlet->fresh()->name);

        $this->actingAs($this->owner)
            ->delete(route('outlets.destroy', $this->outlet))
            ->assertRedirect();
        $this->assertNotNull(Outlet::find($this->outlet->id));
        $this->assertFalse(Outlet::find($this->outlet->id)->is_active);
        $this->assertSame(1, Sale::count());
    }

    public function test_category_create_edit_archive_and_delete(): void
    {
        $category = Category::create(['tenant_id' => $this->tenant->id, 'name' => 'Minuman']);

        $this->actingAs($this->owner)
            ->put(route('categories.update', $category), ['name' => 'Minuman Dingin', 'is_active' => true])
            ->assertRedirect();
        $this->assertSame('Minuman Dingin', $category->fresh()->name);

        // Category with products is archived, not deleted.
        Product::where('name', 'Kopi Susu')->first()->update(['category_id' => $category->id]);
        $this->actingAs($this->owner)
            ->delete(route('categories.destroy', $category))
            ->assertRedirect();
        $this->assertNotNull(Category::find($category->id));
        $this->assertFalse(Category::find($category->id)->is_active);

        // Category without products is hard-deleted.
        $empty = Category::create(['tenant_id' => $this->tenant->id, 'name' => 'Kosong']);
        $this->actingAs($this->owner)
            ->delete(route('categories.destroy', $empty))
            ->assertRedirect();
        $this->assertNull(Category::find($empty->id));
    }

    public function test_stock_adjustment_writes_movement_and_updates_stock(): void
    {
        $this->actingAs($this->owner)
            ->post(route('stock-adjustments.store'), [
                'outlet_id' => $this->outlet->id,
                'product_variant_id' => $this->variant->id,
                'quantity_change' => 5,
                'reason' => 'Opname: stok lebih',
            ])
            ->assertRedirect();
        $this->assertSame(15, OutletStock::where('product_variant_id', $this->variant->id)->first()->quantity);

        $movement = StockMovement::where('type', 'adjustment')->first();
        $this->assertSame(5, $movement->quantity_change);
        $this->assertSame(15, $movement->quantity_after);
        $this->assertSame('Opname: stok lebih', $movement->reason);
        $this->assertSame($this->owner->id, $movement->user_id);

        // Negative adjustment that would go below zero is rejected.
        $response = $this->actingAs($this->owner)
            ->post(route('stock-adjustments.store'), [
                'outlet_id' => $this->outlet->id,
                'product_variant_id' => $this->variant->id,
                'quantity_change' => -99,
                'reason' => 'Salah',
            ]);
        $response->assertSessionHasErrors('quantity_change');
        $this->assertSame(15, OutletStock::where('product_variant_id', $this->variant->id)->first()->quantity);
    }

    public function test_dashboard_cash_total_sums_cash_amounts(): void
    {
        // Two sales: cash 50000 + cash 40000 (split with EDC 25000 on a
        // 65000 sale) = 90000 cash total, but only 2 sales contain cash.
        // Counting sales would give 2; summing must give 90000.
        $this->makeSale();
        app(SaleService::class)->createSale($this->cashier, [
            'outlet_id' => $this->outlet->id,
            'items' => [
                ['product_variant_id' => $this->variant->id, 'quantity' => 2],
                ['product_variant_id' => $this->variant2->id, 'quantity' => 1],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 40000, 'tendered' => 40000],
                ['method' => 'edc', 'amount' => 25000, 'bank' => 'BCA', 'card_type' => 'debit', 'approval_code' => '1'],
            ],
        ]);

        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Rp 90.000');
    }

    public function test_cashier_cannot_edit_or_delete_products(): void
    {
        $product = Product::where('name', 'Kopi Susu')->first();

        $this->actingAs($this->cashier)
            ->get(route('products.edit', $product))
            ->assertForbidden();
        $this->actingAs($this->cashier)
            ->delete(route('products.destroy', $product))
            ->assertForbidden();
    }
}
