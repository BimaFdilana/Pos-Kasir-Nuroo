<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenants = merchants. One tenant owns many outlets, products and sales.
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('tenant_id');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Reguler');
            $table->string('sku')->nullable();
            // Prices are integer rupiah (no cents) to avoid float rounding.
            $table->unsignedBigInteger('price');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('product_id');
        });

        // Stock is tracked per outlet x variant.
        Schema::create('outlet_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('min_stock')->default(0);
            $table->timestamps();
            $table->unique(['outlet_id', 'product_variant_id']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sale_number')->nullable();
            // Idempotency / client reference for retried mobile syncs.
            // Nullable so web sales without a reference are unaffected;
            // unique per tenant when present.
            $table->string('client_reference')->nullable();
            $table->string('status')->default('completed'); // completed|voided|refunded
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('paid_total')->default(0);
            $table->unsignedBigInteger('change_total')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'client_reference']);
            $table->index(['tenant_id', 'outlet_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            // Snapshots: history must survive later price/name edits.
            $table->string('product_name');
            $table->string('variant_name');
            $table->unsignedBigInteger('unit_price');
            $table->integer('quantity');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            // MVP ONLY: cash | edc. No QRIS / e-wallet / gateway fields here.
            $table->string('method'); // cash|edc
            $table->unsignedBigInteger('amount');
            // Cash only.
            $table->unsignedBigInteger('tendered')->nullable();
            $table->unsignedBigInteger('change_amount')->nullable();
            // EDC manual only (standalone terminal, NOT ECR-integrated).
            $table->string('bank')->nullable();
            $table->string('card_type')->nullable(); // debit|credit
            // Approval code from the printed EDC slip. Nullable by design
            // (some terminals/void flows may not have one at entry time).
            // NEVER store full card numbers, expiry, or CVV anywhere.
            $table->string('approval_code')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // sale|sale_reversal|adjustment
            $table->integer('quantity_change'); // signed
            $table->integer('quantity_after');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'outlet_id', 'product_variant_id']);
        });

        // Audit trail for sensitive actions: void and refund.
        Schema::create('sale_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('action'); // void|refund
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            // Supervisor who approved. For MVP the supervisor is the actor
            // when the actor already holds owner/manager role; the column
            // exists so a future request/approve flow needs no schema change.
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('total_before');
            $table->unsignedBigInteger('total_after');
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_audits');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('outlet_stocks');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('outlets');
        Schema::dropIfExists('tenants');
    }
};
