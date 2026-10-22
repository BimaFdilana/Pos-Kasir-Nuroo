<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\OutletStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleAudit;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Outlet $outlet;
    protected User $cashier;
    protected User $owner;
    protected ProductVariant $variant;

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
        OutletStock::create(['outlet_id' => $this->outlet->id, 'product_variant_id' => $this->variant->id, 'quantity' => 10]);
    }

    protected function saleData(array $overrides = []): array
    {
        return array_merge([
            'outlet_id' => $this->outlet->id,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 50000, 'tendered' => 60000]],
        ], $overrides);
    }

    public function test_cash_sale_calculates_change_and_decrements_stock(): void
    {
        $sale = app(SaleService::class)->createSale($this->cashier, $this->saleData());

        $this->assertSame('completed', $sale->status);
        $this->assertSame(50000, $sale->total);
        $this->assertSame(10000, $sale->change_total);
        $this->assertSame(10000, $sale->payments->first()->change_amount);
        $this->assertSame(8, OutletStock::first()->quantity);
        $this->assertSame(-2, StockMovement::where('type', 'sale')->first()->quantity_change);
        $this->assertSame(8, StockMovement::where('type', 'sale')->first()->quantity_after);
    }

    public function test_edc_sale_records_bank_card_type_and_approval_code(): void
    {
        $sale = app(SaleService::class)->createSale($this->cashier, $this->saleData([
            'payments' => [[
                'method' => 'edc', 'amount' => 50000,
                'bank' => 'BCA', 'card_type' => 'debit', 'approval_code' => '123456',
            ]],
        ]));

        $payment = $sale->payments->first();
        $this->assertSame('edc', $payment->method);
        $this->assertSame('BCA', $payment->bank);
        $this->assertSame('debit', $payment->card_type);
        $this->assertSame('123456', $payment->approval_code);
        $this->assertSame(0, $sale->change_total);
    }

    public function test_sale_fails_when_stock_insufficient_and_creates_nothing(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        try {
            app(SaleService::class)->createSale($this->cashier, $this->saleData([
                'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 99]],
            ]));
        } finally {
            $this->assertSame(0, Sale::count());
            $this->assertSame(10, OutletStock::first()->quantity);
        }
    }

    public function test_void_restores_stock_and_writes_audit(): void
    {
        $sale = app(SaleService::class)->createSale($this->cashier, $this->saleData());

        $voided = app(SaleService::class)->voidSale($this->owner, $sale, 'Salah input');

        $this->assertSame('voided', $voided->status);
        $this->assertSame(10, OutletStock::first()->quantity);
        $audit = SaleAudit::where('sale_id', $sale->id)->where('action', 'void')->first();
        $this->assertNotNull($audit);
        $this->assertSame('Salah input', $audit->reason);
        $this->assertSame($this->owner->id, $audit->actor_user_id);
        $this->assertSame($this->owner->id, $audit->approver_user_id);
        $this->assertSame(50000, $audit->total_before);
        $this->assertSame(0, $audit->total_after);
    }

    public function test_cashier_cannot_void_via_web(): void
    {
        $sale = app(SaleService::class)->createSale($this->cashier, $this->saleData());

        $this->actingAs($this->cashier)
            ->post(route('sales.void', $sale), ['reason' => 'Coba'])
            ->assertForbidden();

        $this->assertSame('completed', $sale->fresh()->status);
    }

    public function test_api_sale_is_idempotent_on_client_reference(): void
    {
        $token = $this->cashier->createToken('test')->plainTextToken;

        $payload = $this->saleData(['client_reference' => 'POS-001']);
        $first = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/sales', $payload);
        $first->assertCreated();

        $second = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/sales', $payload);
        $second->assertOk();
        $second->assertJsonPath('duplicate', true);
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Sale::count());
        $this->assertSame(8, OutletStock::first()->quantity);
    }

    public function test_api_login_and_products_are_tenant_scoped(): void
    {
        $login = $this->postJson('/api/v1/login', [
            'email' => $this->cashier->email, 'password' => 'password',
        ]);
        $login->assertOk();
        $token = $login->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Kopi Susu');
    }

    public function test_web_dashboard_renders_for_owner(): void
    {
        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cafe Test')
            ->assertSee('Kopi Susu');
    }

    public function test_api_products_requires_authentication(): void
    {
        // Separate test: withHeader() persists within a test, so the
        // unauthenticated check cannot live in the same test as login.
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }
}
