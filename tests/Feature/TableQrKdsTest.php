<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\OutletStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TableOrder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TableQrKdsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Outlet $outlet;
    protected User $owner;
    protected User $cashier;
    protected ProductVariant $variant;
    protected DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Cafe Test']);
        $this->outlet = Outlet::create(['tenant_id' => $this->tenant->id, 'name' => 'Outlet Utama']);
        $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);
        $this->cashier = User::factory()->create(['tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id, 'role' => 'cashier']);
        $product = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Kopi Susu']);
        $this->variant = ProductVariant::create(['product_id' => $product->id, 'name' => 'Reguler', 'price' => 25000]);
        OutletStock::create(['outlet_id' => $this->outlet->id, 'product_variant_id' => $this->variant->id, 'quantity' => 10]);
        $this->table = DiningTable::create([
            'tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Meja 1', 'status' => 'free', 'is_active' => true,
            'qr_code' => DiningTable::generateQrCode(),
        ]);
    }

    public function test_table_qr_codes_are_unique_and_regeneration_changes_code(): void
    {
        $second = DiningTable::create([
            'tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Meja 2', 'status' => 'free', 'is_active' => true,
            'qr_code' => DiningTable::generateQrCode(),
        ]);
        $this->assertNotSame($this->table->qr_code, $second->qr_code);
        $this->assertNotSame('Meja 1', $this->table->qr_code);

        Sanctum::actingAs($this->owner);
        $old = $this->table->qr_code;
        $this->postJson("/api/v1/tables/{$this->table->id}/regenerate")->assertOk();
        $this->assertNotSame($old, $this->table->fresh()->qr_code);
    }

    public function test_guest_order_works_without_login_and_name_phone_optional(): void
    {
        $this->getJson("/api/v1/qr/{$this->table->qr_code}")->assertOk()
            ->assertJsonPath('data.table.name', 'Meja 1');

        $res = $this->postJson("/api/v1/qr/{$this->table->qr_code}/orders", [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
        ])->assertCreated();

        $order = TableOrder::find($res->json('data.id'));
        $this->assertSame('new', $order->status);
        $this->assertNull($order->customer_name);
        $this->assertNull($order->customer_phone);
        $this->assertSame('occupied', $this->table->fresh()->status);
        // Stock is NOT decremented at order time.
        $this->assertSame(10, OutletStock::first()->quantity);
    }

    public function test_guest_order_404_for_unknown_and_disabled_codes(): void
    {
        $this->getJson('/api/v1/qr/tidak-ada')->assertNotFound();
        $this->postJson('/api/v1/qr/tidak-ada/orders', [
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
        ])->assertNotFound();

        $this->table->update(['is_active' => false]);
        $this->getJson("/api/v1/qr/{$this->table->qr_code}")->assertNotFound();
        $this->getJson("/qr/{$this->table->qr_code}")->assertNotFound();
    }

    public function test_kds_status_transitions_and_invalid_transition_rejected(): void
    {
        Sanctum::actingAs($this->cashier);
        $order = TableOrder::create([
            'tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id,
            'dining_table_id' => $this->table->id, 'status' => 'new',
        ]);
        $this->postJson("/api/v1/table-orders/{$order->id}/status", ['status' => 'ready'])
            ->assertStatus(422); // new -> ready skips preparing
        $this->postJson("/api/v1/table-orders/{$order->id}/status", ['status' => 'preparing'])->assertOk();
        $this->postJson("/api/v1/table-orders/{$order->id}/status", ['status' => 'ready'])->assertOk();
        $this->postJson("/api/v1/table-orders/{$order->id}/status", ['status' => 'served'])->assertOk();
        $this->assertSame('served', $order->fresh()->status);
    }

    public function test_order_to_sale_conversion_decrements_stock_and_frees_table(): void
    {
        Sanctum::actingAs($this->cashier);
        $order = TableOrder::create([
            'tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id,
            'dining_table_id' => $this->table->id, 'status' => 'new',
        ]);
        $order->items()->create([
            'product_variant_id' => $this->variant->id, 'product_name' => 'Kopi Susu',
            'variant_name' => 'Reguler', 'unit_price' => 25000, 'quantity' => 2, 'subtotal' => 50000,
        ]);
        $this->table->refreshStatus();

        $res = $this->postJson("/api/v1/table-orders/{$order->id}/convert", [
            'payments' => [['method' => 'cash', 'amount' => 50000, 'tendered' => 50000]],
        ])->assertCreated();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->sale_id);
        $this->assertSame(8, OutletStock::first()->quantity);
        $this->assertSame('free', $this->table->fresh()->status);
        $this->assertSame('completed', $res->json('data.status'));

        // Second conversion returns the same sale, no double decrement.
        $this->postJson("/api/v1/table-orders/{$order->id}/convert", [
            'payments' => [['method' => 'cash', 'amount' => 50000, 'tendered' => 50000]],
        ])->assertOk();
        $this->assertSame(8, OutletStock::first()->quantity);
    }

    public function test_conversion_fails_when_stock_insufficient(): void
    {
        Sanctum::actingAs($this->cashier);
        $order = TableOrder::create([
            'tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id,
            'dining_table_id' => $this->table->id, 'status' => 'new',
        ]);
        $order->items()->create([
            'product_variant_id' => $this->variant->id, 'product_name' => 'Kopi Susu',
            'variant_name' => 'Reguler', 'unit_price' => 25000, 'quantity' => 99, 'subtotal' => 2475000,
        ]);
        $this->postJson("/api/v1/table-orders/{$order->id}/convert", [
            'payments' => [['method' => 'cash', 'amount' => 2475000, 'tendered' => 2475000]],
        ])->assertStatus(422);
        $this->assertSame('new', $order->fresh()->status);
        $this->assertSame(10, OutletStock::first()->quantity);
    }

    public function test_kds_and_tables_web_pages_load_for_cashier(): void
    {
        $this->actingAs($this->cashier);
        $this->get('/kds')->assertOk();
        $this->get('/dining-tables')->assertOk();
        $this->get("/qr/{$this->table->qr_code}")->assertOk();
    }
}
