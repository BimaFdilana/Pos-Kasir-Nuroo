<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\OutletStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerKaryawanTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Outlet $outlet;
    protected User $owner;
    protected User $employee;
    protected User $otherTenantUser;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Cafe Test']);
        $this->outlet = Outlet::create(['tenant_id' => $this->tenant->id, 'name' => 'Outlet Utama']);
        $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);
        $this->employee = User::factory()->create([
            'tenant_id' => $this->tenant->id, 'outlet_id' => $this->outlet->id, 'role' => 'cashier',
        ]);
        $otherTenant = Tenant::create(['name' => 'Lain']);
        $this->otherTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'owner']);

        $product = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Kopi Susu']);
        $this->variant = ProductVariant::create(['product_id' => $product->id, 'name' => 'Reguler', 'price' => 25000]);
        OutletStock::create(['outlet_id' => $this->outlet->id, 'product_variant_id' => $this->variant->id, 'quantity' => 2, 'min_stock' => 5]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_check_in_then_check_out_closes_the_open_record(): void
    {
        $token = $this->token($this->employee);

        Sanctum::actingAs($this->employee);
            $in = $this->postJson('/api/v1/attendance/check-in', ['outlet_id' => $this->outlet->id, 'note' => 'Masuk']);
        $in->assertCreated();

        Sanctum::actingAs($this->employee);
            $this->getJson('/api/v1/attendance/open')
            ->assertOk()
            ->assertJsonPath('data.id', $in->json('data.id'));

        Sanctum::actingAs($this->employee);
            $out = $this->postJson('/api/v1/attendance/check-out', ['note' => 'Pulang']);
        $out->assertOk();
        $this->assertNotNull($out->json('data.checked_out_at'));

        Sanctum::actingAs($this->employee);
            $this->getJson('/api/v1/attendance/open')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_second_check_in_is_rejected_while_open(): void
    {
        $token = $this->token($this->employee);
        $headers = ['Authorization' => "Bearer {$token}"];

        Sanctum::actingAs($this->employee); $this->postJson('/api/v1/attendance/check-in', [])->assertCreated();
        Sanctum::actingAs($this->employee); $this->postJson('/api/v1/attendance/check-in', [])->assertUnprocessable();
        $this->assertSame(1, \App\Models\Attendance::count());
    }

    public function test_check_out_without_open_is_rejected(): void
    {
        Sanctum::actingAs($this->employee);
            $this->postJson('/api/v1/attendance/check-out', [])
            ->assertUnprocessable();
    }

    public function test_attendance_is_tenant_scoped(): void
    {
        Sanctum::actingAs($this->employee);
            $this->postJson('/api/v1/attendance/check-in', [])->assertCreated();

        // Other tenant's owner cannot list this tenant's attendance.
        Sanctum::actingAs($this->otherTenantUser);
            $this->getJson('/api/v1/attendance')
            ->assertOk()
            ->assertJsonPath('data.data', []);

        // Employee cannot list tenant attendance (supervisor only).
        Sanctum::actingAs($this->employee);
            $this->getJson('/api/v1/attendance')
            ->assertForbidden();
    }

    public function test_owner_summary_totals_and_low_stock(): void
    {
        $sale = app(SaleService::class)->createSale($this->employee, [
            'outlet_id' => $this->outlet->id,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
            'payments' => [
                ['method' => 'cash', 'amount' => 15000, 'tendered' => 20000],
                ['method' => 'edc', 'amount' => 10000, 'bank' => 'BCA', 'card_type' => 'debit', 'approval_code' => '999'],
            ],
        ]);
        $this->assertSame(25000, $sale->total);

        Sanctum::actingAs($this->owner);
            $response = $this->getJson('/api/v1/owner/summary');
        $response->assertOk()
            ->assertJsonPath('data.sales_count', 1)
            ->assertJsonPath('data.total', 25000)
            ->assertJsonPath('data.cash_total', 15000)
            ->assertJsonPath('data.per_outlet.0.outlet_id', $this->outlet->id)
            ->assertJsonPath('data.per_outlet.0.total', 25000);

        // Stock went 2 -> 1, below default threshold 5, so it must appear.
        $low = $response->json('data.low_stock');
        $this->assertCount(1, $low);
        $this->assertSame(1, $low[0]['quantity']);
        $this->assertSame('Kopi Susu', $low[0]['product_name']);

        // Employee cannot view owner summary.
        Sanctum::actingAs($this->employee);
            $this->getJson('/api/v1/owner/summary')
            ->assertForbidden();
    }

    public function test_my_shifts_and_supervisor_shift_creation(): void
    {
        Shift::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id,
            'outlet_id' => $this->outlet->id, 'date' => today()->toDateString(),
            'start_time' => '08:00', 'end_time' => '16:00', 'note' => 'Pagi',
        ]);

        Sanctum::actingAs($this->employee);
            $this->getJson('/api/v1/shifts/my')
            ->assertOk()
            ->assertJsonPath('data.data.0.note', 'Pagi');

        Sanctum::actingAs($this->owner);
            $this->postJson('/api/v1/shifts', [
                'user_id' => $this->employee->id, 'outlet_id' => $this->outlet->id,
                'date' => today()->addDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
            ])
            ->assertCreated();

        Sanctum::actingAs($this->employee);
            $this->postJson('/api/v1/shifts', [
                'user_id' => $this->employee->id,
                'date' => today()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
            ])
            ->assertForbidden();
    }
}
