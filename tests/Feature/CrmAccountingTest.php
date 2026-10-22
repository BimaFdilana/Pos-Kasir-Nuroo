<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Member;
use App\Models\Outlet;
use App\Models\OutletStock;
use App\Models\PointLedger;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Models\Warehouse;
use App\Services\AccountingService;
use App\Services\CrmService;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CrmAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Outlet $outlet;
    protected User $owner;
    protected User $cashier;
    protected Warehouse $warehouse;
    protected ProductVariant $variant;
    protected Member $member;

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
        $susu = Ingredient::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Susu', 'unit' => 'ml', 'cost_per_unit' => 50,
        ]);
        $gula = Ingredient::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Gula', 'unit' => 'gram', 'cost_per_unit' => 10,
        ]);
        $product = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Kopi Susu']);
        $this->variant = ProductVariant::create(['product_id' => $product->id, 'name' => 'Reguler', 'price' => 25000]);
        OutletStock::create(['outlet_id' => $this->outlet->id, 'product_variant_id' => $this->variant->id, 'quantity' => 50]);
        Recipe::create(['product_variant_id' => $this->variant->id, 'ingredient_id' => $susu->id, 'quantity' => 200]);
        Recipe::create(['product_variant_id' => $this->variant->id, 'ingredient_id' => $gula->id, 'quantity' => 10]);

        $inv = app(InventoryService::class);
        $inv->applyMovement($this->owner, $this->warehouse, $susu, 'purchase', 10000, 'Pembelian awal');
        $inv->applyMovement($this->owner, $this->warehouse, $gula, 'purchase', 10000, 'Pembelian awal');

        $this->member = Member::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Andi', 'phone' => '081234567890',
        ]);
    }

    protected function saleData(int $qty, array $extra = []): array
    {
        return array_merge([
            'outlet_id' => $this->outlet->id,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => $qty]],
            'payments' => [['method' => 'cash', 'amount' => 25000 * $qty, 'tendered' => 25000 * $qty]],
        ], $extra);
    }

    protected function balance(): int
    {
        return app(CrmService::class)->balanceFor($this->member->fresh());
    }

    public function test_member_api_crud_and_phone_unique_per_tenant(): void
    {
        $token = $this->cashier->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/members', ['name' => 'Budi', 'phone' => '089999999999'])
            ->assertCreated()
            ->assertJsonPath('data.points_balance', 0);

        // Same phone in the same tenant is rejected.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/members', ['name' => 'Budi Lagi', 'phone' => '089999999999'])
            ->assertUnprocessable();
    }

    public function test_member_api_is_tenant_scoped(): void
    {
        $otherTenant = Tenant::create(['name' => 'Tenant Lain']);
        $otherOwner = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'owner']);
        $token = $otherOwner->createToken('test')->plainTextToken;

        // Same phone in a different tenant is fine, but this tenant's
        // member is invisible to the other tenant.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/members', ['name' => 'Andi Lain', 'phone' => '081234567890'])
            ->assertCreated();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/members/'.$this->member->id)
            ->assertNotFound();
    }

    public function test_points_earned_on_sale_and_reversed_on_void(): void
    {
        // 2 x Rp 25.000 = Rp 50.000 -> 5 points (1 per Rp 10.000).
        $sale = app(SaleService::class)->createSale(
            $this->cashier, $this->saleData(2, ['member_id' => $this->member->id]),
        );

        $this->assertSame(5, $sale->points_earned);
        $this->assertSame(5, $this->balance());
        $this->assertSame('earn', PointLedger::first()->type);

        app(SaleService::class)->voidSale($this->owner, $sale, 'Salah input');
        $this->assertSame(0, $this->balance());
        $this->assertSame(-5, PointLedger::where('type', 'reversal')->sum('points'));
    }

    public function test_redemption_uses_points_and_earns_on_reduced_total(): void
    {
        app(SaleService::class)->createSale(
            $this->cashier, $this->saleData(2, ['member_id' => $this->member->id]),
        );
        $this->assertSame(5, $this->balance());

        // Redeem 3 points = Rp 300 off Rp 25.000 -> total Rp 24.700,
        // which itself earns floor(24700 / 10000) = 2 points.
        $sale = app(SaleService::class)->createSale($this->cashier, $this->saleData(1, [
            'member_id' => $this->member->id,
            'redeem_points' => 3,
            'payments' => [['method' => 'cash', 'amount' => 24700, 'tendered' => 24700]],
        ]));

        $this->assertSame(3, $sale->points_redeemed);
        $this->assertSame(300, $sale->points_value);
        $this->assertSame(300, $sale->discount_total);
        $this->assertSame(24700, $sale->total);
        $this->assertSame(2, $sale->points_earned);
        $this->assertSame(4, $this->balance()); // 5 - 3 + 2
    }

    public function test_redemption_beyond_balance_is_rejected_and_creates_nothing(): void
    {
        app(SaleService::class)->createSale(
            $this->cashier, $this->saleData(2, ['member_id' => $this->member->id]),
        );

        try {
            app(SaleService::class)->createSale($this->cashier, $this->saleData(1, [
                'member_id' => $this->member->id, 'redeem_points' => 99,
            ]));
            $this->fail('Redeeming beyond balance should have thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(1, Sale::count());
            $this->assertSame(5, $this->balance());
        }
    }

    public function test_redemption_is_capped_at_sale_total_with_zero_total_sale(): void
    {
        PointLedger::create([
            'tenant_id' => $this->tenant->id, 'member_id' => $this->member->id,
            'user_id' => $this->owner->id, 'type' => 'adjust', 'points' => 300,
            'reason' => 'Seed for cap test',
        ]);

        // 300 points = Rp 30.000 > Rp 25.000 sale: only 250 points are
        // spent, the sale total becomes 0 and needs no payment row.
        $sale = app(SaleService::class)->createSale($this->cashier, $this->saleData(1, [
            'member_id' => $this->member->id, 'redeem_points' => 300, 'payments' => [],
        ]));

        $this->assertSame(250, $sale->points_redeemed);
        $this->assertSame(25000, $sale->points_value);
        $this->assertSame(0, $sale->total);
        $this->assertSame(0, $sale->payments()->count());
        $this->assertSame(50, $this->balance());
    }

    public function test_voucher_percent_and_fixed_discounts(): void
    {
        Voucher::create([
            'tenant_id' => $this->tenant->id, 'code' => 'DISKON10',
            'discount_type' => 'percent', 'value' => 10,
        ]);
        $sale = app(SaleService::class)->createSale($this->cashier, $this->saleData(2, [
            'voucher_code' => 'DISKON10',
            'payments' => [['method' => 'cash', 'amount' => 45000, 'tendered' => 45000]],
        ]));
        $this->assertSame(5000, $sale->voucher_discount);
        $this->assertSame(45000, $sale->total);

        Voucher::create([
            'tenant_id' => $this->tenant->id, 'code' => 'POTONG7RB',
            'discount_type' => 'fixed', 'value' => 7000,
        ]);
        $sale2 = app(SaleService::class)->createSale($this->cashier, $this->saleData(1, [
            'voucher_code' => 'POTONG7RB',
            'payments' => [['method' => 'cash', 'amount' => 18000, 'tendered' => 18000]],
        ]));
        $this->assertSame(7000, $sale2->voucher_discount);
        $this->assertSame(18000, $sale2->total);
    }

    public function test_voucher_expired_exhausted_and_released_on_void(): void
    {
        Voucher::create([
            'tenant_id' => $this->tenant->id, 'code' => 'KADALUARSA',
            'discount_type' => 'fixed', 'value' => 1000, 'valid_to' => today()->subDay(),
        ]);
        try {
            app(SaleService::class)->createSale($this->cashier, $this->saleData(1, [
                'voucher_code' => 'KADALUARSA',
            ]));
            $this->fail('Expired voucher should have thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(0, Sale::count());
        }

        $voucher = Voucher::create([
            'tenant_id' => $this->tenant->id, 'code' => 'SEKALI',
            'discount_type' => 'fixed', 'value' => 1000, 'max_uses' => 1,
        ]);
        $sale = app(SaleService::class)->createSale($this->cashier, $this->saleData(1, [
            'voucher_code' => 'SEKALI',
            'payments' => [['method' => 'cash', 'amount' => 24000, 'tendered' => 24000]],
        ]));
        $this->assertSame(1, $voucher->fresh()->uses_count);

        try {
            app(SaleService::class)->createSale($this->cashier, $this->saleData(1, [
                'voucher_code' => 'SEKALI',
            ]));
            $this->fail('Exhausted voucher should have thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(1, Sale::count());
        }

        // Void releases the usage: the voucher can be used again.
        app(SaleService::class)->voidSale($this->owner, $sale, 'Salah input');
        $this->assertSame(0, $voucher->fresh()->uses_count);
        $this->assertSame('released', VoucherUsage::first()->status);
        $sale2 = app(SaleService::class)->createSale($this->cashier, $this->saleData(1, [
            'voucher_code' => 'SEKALI',
            'payments' => [['method' => 'cash', 'amount' => 24000, 'tendered' => 24000]],
        ]));
        $this->assertSame(1000, $sale2->voucher_discount);
    }

    public function test_journal_is_balanced_for_split_payment_with_discount_and_cogs(): void
    {
        Voucher::create([
            'tenant_id' => $this->tenant->id, 'code' => 'DISKON10',
            'discount_type' => 'percent', 'value' => 10,
        ]);
        // Rp 25.000 sale, Rp 2.500 voucher discount, paid cash 12.500 + EDC 10.000,
        // HPP Rp 10.100 (200 ml x Rp 50 + 10 gram x Rp 10).
        $sale = app(SaleService::class)->createSale($this->cashier, $this->saleData(1, [
            'voucher_code' => 'DISKON10',
            'payments' => [
                ['method' => 'cash', 'amount' => 12500, 'tendered' => 12500],
                ['method' => 'edc', 'amount' => 10000, 'bank' => 'BCA', 'card_type' => 'debit', 'approval_code' => '123456'],
            ],
        ]));

        $entry = JournalEntry::where('sale_id', $sale->id)->where('is_reversal', false)->first();
        $this->assertNotNull($entry);
        $lines = JournalLine::where('journal_entry_id', $entry->id)->with('account')->get();
        $this->assertSame($lines->sum('debit'), $lines->sum('credit'));
        $this->assertSame(35100, $lines->sum('debit')); // 12500 + 10000 + 2500 + 10100
        $byCode = $lines->keyBy(fn ($l) => $l->account->code);
        $this->assertSame(12500, $byCode['1101']->debit);
        $this->assertSame(10000, $byCode['1102']->debit);
        $this->assertSame(2500, $byCode['4201']->debit);
        $this->assertSame(10100, $byCode['5101']->debit);
        $this->assertSame(25000, $byCode['4101']->credit);
        $this->assertSame(10100, $byCode['1301']->credit);
        $this->assertSame(6, Account::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_reversing_entry_and_profit_loss_net_to_zero_on_void(): void
    {
        Voucher::create([
            'tenant_id' => $this->tenant->id, 'code' => 'DISKON10',
            'discount_type' => 'percent', 'value' => 10,
        ]);
        $sale = app(SaleService::class)->createSale($this->cashier, $this->saleData(1, [
            'voucher_code' => 'DISKON10',
            'payments' => [['method' => 'cash', 'amount' => 22500, 'tendered' => 22500]],
        ]));

        $pl = app(AccountingService::class)->profitLoss($this->tenant, $this->outlet->id, null, null);
        $this->assertSame(25000, $pl['revenue']);
        $this->assertSame(2500, $pl['discounts']);
        $this->assertSame(10100, $pl['cogs']);
        $this->assertSame(12400, $pl['gross_profit']);

        app(SaleService::class)->voidSale($this->owner, $sale, 'Salah input');
        $this->assertSame(1, JournalEntry::where('sale_id', $sale->id)->where('is_reversal', true)->count());

        $plAfter = app(AccountingService::class)->profitLoss($this->tenant, $this->outlet->id, null, null);
        $this->assertSame(0, $plAfter['revenue']);
        $this->assertSame(0, $plAfter['discounts']);
        $this->assertSame(0, $plAfter['cogs']);
        $this->assertSame(0, $plAfter['gross_profit']);
    }

    public function test_accounting_api_is_supervisor_only(): void
    {
        // Separate test per token: the Authorization header sticks between
        // requests inside one test, so a second token cannot be trusted here.
        $cashierToken = $this->cashier->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$cashierToken}")
            ->getJson('/api/v1/accounting/journals')->assertForbidden();
    }

    public function test_crm_accounting_web_pages_render_for_owner(): void
    {
        $this->actingAs($this->owner)
            ->get(route('crm.members'))->assertOk()->assertSee('Member CRM');
        $this->actingAs($this->owner)
            ->get(route('crm.vouchers'))->assertOk()->assertSee('Voucher');
        $this->actingAs($this->owner)
            ->get(route('accounting.journals'))->assertOk()->assertSee('Jurnal Akuntansi');
        $this->actingAs($this->owner)
            ->get(route('accounting.profit-loss'))->assertOk()->assertSee('Laba kotor');
    }
}
