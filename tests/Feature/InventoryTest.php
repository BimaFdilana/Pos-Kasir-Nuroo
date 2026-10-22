<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\IngredientStockMovement;
use App\Models\Opname;
use App\Models\Outlet;
use App\Models\OutletStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Outlet $outlet;
    protected User $owner;
    protected User $cashier;
    protected Warehouse $warehouse;
    protected Ingredient $susu;
    protected Ingredient $gula;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Cafe Test']);
        $this->outlet = Outlet::create(['tenant_id' => $this->tenant->id, 'name' => 'Outlet Utama']);
        $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);
        $this->cashier = User::factory()->create([
            'tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id, 'role' => 'cashier',
        ]);
        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Gudang Utama', 'is_default' => true,
        ]);
        $this->susu = Ingredient::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Susu', 'unit' => 'ml', 'cost_per_unit' => 50,
        ]);
        $this->gula = Ingredient::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Gula', 'unit' => 'gram', 'cost_per_unit' => 10,
        ]);
        $product = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Kopi Susu']);
        $this->variant = ProductVariant::create(['product_id' => $product->id, 'name' => 'Reguler', 'price' => 25000]);
        OutletStock::create(['outlet_id' => $this->outlet->id, 'product_variant_id' => $this->variant->id, 'quantity' => 10]);
        Recipe::create(['product_variant_id' => $this->variant->id, 'ingredient_id' => $this->susu->id, 'quantity' => 200]);
        Recipe::create(['product_variant_id' => $this->variant->id, 'ingredient_id' => $this->gula->id, 'quantity' => 10]);
    }

    protected function stockOf(Ingredient $ingredient, ?Warehouse $warehouse = null): float
    {
        $row = IngredientStock::where('warehouse_id', ($warehouse ?? $this->warehouse)->id)
            ->where('ingredient_id', $ingredient->id)->first();

        return $row ? (float) $row->quantity : 0.0;
    }

    protected function purchase(Ingredient $ingredient, float $qty, ?Warehouse $warehouse = null): void
    {
        app(InventoryService::class)->applyMovement(
            $this->owner, $warehouse ?? $this->warehouse, $ingredient, 'purchase', $qty, 'Pembelian awal',
        );
    }

    protected function saleData(int $qty): array
    {
        return [
            'outlet_id' => $this->outlet->id,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => $qty]],
            'payments' => [['method' => 'cash', 'amount' => 25000 * $qty, 'tendered' => 25000 * $qty]],
        ];
    }

    public function test_hpp_is_computed_from_recipe_and_costs(): void
    {
        // 200 ml x Rp 50 + 10 gram x Rp 10 = Rp 10.100 per serving.
        $this->assertSame(10100, app(InventoryService::class)->hppForVariant($this->variant));

        $token = $this->owner->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/recipes?product_variant_id='.$this->variant->id)
            ->assertOk()
            ->assertJsonPath('data.hpp_per_serving', 10100)
            ->assertJsonPath('data.price', 25000);
    }

    public function test_movement_rejects_negative_result_and_changes_nothing(): void
    {
        $this->purchase($this->susu, 100);

        try {
            app(InventoryService::class)->applyMovement(
                $this->owner, $this->warehouse, $this->susu, 'adjustment', -150, 'Koreksi salah',
            );
            $this->fail('Negative movement should have thrown.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame(100.0, $this->stockOf($this->susu));
            $this->assertSame(1, IngredientStockMovement::count());
        }
    }

    public function test_transfer_moves_stock_between_warehouses_as_a_pair(): void
    {
        $this->purchase($this->susu, 100);
        $second = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Gudang Cabang']);

        $group = app(InventoryService::class)->transfer(
            $this->owner, $this->warehouse, $second, $this->susu, 30, 'Pindah stok',
        );

        $this->assertSame(70.0, $this->stockOf($this->susu, $this->warehouse));
        $this->assertSame(30.0, $this->stockOf($this->susu, $second));
        $this->assertSame(2, IngredientStockMovement::where('transfer_group', $group)->count());
        $this->assertSame(
            ['transfer_in', 'transfer_out'],
            IngredientStockMovement::where('transfer_group', $group)->orderBy('type')->pluck('type')->all(),
        );
    }

    public function test_opname_applies_difference_and_keeps_history(): void
    {
        $this->purchase($this->susu, 70);

        $opname = app(InventoryService::class)->recordOpname(
            $this->owner, $this->warehouse, $this->susu, 65, 'Hitung harian',
        );

        $this->assertSame(70.0, $opname->system_quantity);
        $this->assertSame(65.0, $opname->counted_quantity);
        $this->assertSame(-5.0, $opname->difference);
        $this->assertSame(65.0, $this->stockOf($this->susu));
        $movement = IngredientStockMovement::where('type', 'opname')->first();
        $this->assertNotNull($movement);
        $this->assertSame($opname->id, $movement->opname_id);
        $this->assertSame(1, Opname::count());
    }

    public function test_sale_with_recipe_decrements_ingredients_and_void_restores_them(): void
    {
        $this->purchase($this->susu, 500);
        $this->purchase($this->gula, 100);

        $sale = app(SaleService::class)->createSale($this->cashier, $this->saleData(2));

        $this->assertSame('completed', $sale->status);
        $this->assertSame(8, OutletStock::first()->quantity);
        $this->assertSame(100.0, $this->stockOf($this->susu)); // 500 - 2x200
        $this->assertSame(80.0, $this->stockOf($this->gula));  // 100 - 2x10
        $this->assertSame(25000, $sale->total / 2); // selling price unchanged by HPP

        app(SaleService::class)->voidSale($this->owner, $sale, 'Salah input');
        $this->assertSame(10, OutletStock::first()->quantity);
        $this->assertSame(500.0, $this->stockOf($this->susu));
        $this->assertSame(100.0, $this->stockOf($this->gula));
    }

    public function test_sale_with_recipe_fails_atomically_when_ingredient_insufficient(): void
    {
        $this->purchase($this->susu, 500);
        $this->purchase($this->gula, 100);

        try {
            app(SaleService::class)->createSale($this->cashier, $this->saleData(3)); // needs 600 ml susu
            $this->fail('Sale should have failed on ingredient stock.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame(0, Sale::count());
            $this->assertSame(10, OutletStock::first()->quantity);
            $this->assertSame(500.0, $this->stockOf($this->susu));
            $this->assertSame(100.0, $this->stockOf($this->gula));
            $this->assertSame(0, IngredientStockMovement::where('type', 'sale')->count());
        }
    }

    public function test_sale_without_recipe_is_unchanged(): void
    {
        $plain = ProductVariant::create([
            'product_id' => Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Air Mineral'])->id,
            'name' => 'Botol', 'price' => 5000,
        ]);
        OutletStock::create(['outlet_id' => $this->outlet->id, 'product_variant_id' => $plain->id, 'quantity' => 5]);

        $sale = app(SaleService::class)->createSale($this->cashier, [
            'outlet_id' => $this->outlet->id,
            'items' => [['product_variant_id' => $plain->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 10000, 'tendered' => 10000]],
        ]);

        $this->assertSame('completed', $sale->status);
        $this->assertSame(0, IngredientStockMovement::where('type', 'sale')->count());
        $this->assertNull(app(InventoryService::class)->hppForVariant($plain));
    }

    public function test_inventory_api_is_supervisor_only(): void
    {
        // Separate test per token: the Authorization header sticks between
        // requests inside one test, so a second token cannot be trusted here.
        $cashierToken = $this->cashier->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$cashierToken}")
            ->getJson('/api/v1/ingredients')->assertForbidden();
    }

    public function test_inventory_api_is_tenant_scoped(): void
    {
        $otherTenant = Tenant::create(['name' => 'Tenant Lain']);
        $otherOwner = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'owner']);
        $otherToken = $otherOwner->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$otherToken}")
            ->getJson('/api/v1/recipes?product_variant_id='.$this->variant->id)->assertNotFound();
    }

    public function test_inventory_web_pages_render_for_owner(): void
    {
        $this->actingAs($this->owner)
            ->get(route('inventory.ingredients'))->assertOk()->assertSee('Bahan Baku');
        $this->actingAs($this->owner)
            ->get(route('inventory.warehouses'))->assertOk()->assertSee('Gudang');
        $this->actingAs($this->owner)
            ->get(route('inventory.recipes', ['product_variant_id' => $this->variant->id]))
            ->assertOk()->assertSee('HPP');
        $this->actingAs($this->owner)
            ->get(route('inventory.movements'))->assertOk()->assertSee('Transfer antar gudang');
        $this->actingAs($this->owner)
            ->get(route('inventory.opnames'))->assertOk()->assertSee('Opname');
    }
}
