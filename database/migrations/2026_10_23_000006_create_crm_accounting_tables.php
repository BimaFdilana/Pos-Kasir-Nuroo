<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // CRM members. Phone is unique per tenant, not globally, so two
        // different businesses may each have the same customer phone.
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->date('birthdate')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'name']);
        });

        // Loyalty ledger. `points` is signed: earn/adjust/reversal add,
        // redeem subtracts. Balance is always the sum of the ledger, so
        // no separate balance column can drift out of sync.
        // earn | redeem | adjust | reversal
        Schema::create('point_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->integer('points');
            $table->text('reason');
            $table->timestamps();
            $table->index(['tenant_id', 'member_id']);
        });

        // Simple campaign vouchers: one discount per sale, percent or
        // fixed rupiah. Usage is counted only while the sale that used
        // it is completed; void/refund releases the usage.
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('discount_type'); // percent | fixed
            $table->integer('value');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->integer('max_uses')->nullable();
            $table->integer('uses_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('voucher_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('used'); // used | released
            $table->timestamps();
            $table->index(['tenant_id', 'voucher_id']);
        });

        // Core accounting chart: a small fixed set, created on demand
        // per tenant. No tax account in this step: sale tax, when set,
        // is credited to revenue together with the subtotal (see
        // AccountingService for the exact balancing rule).
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('type'); // asset | revenue | contra_revenue | expense
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('entry_date');
            $table->string('description');
            $table->string('reference')->nullable();
            $table->boolean('is_reversal')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'outlet_id', 'entry_date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->integer('debit')->default(0);
            $table->integer('credit')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['journal_entry_id', 'account_id']);
        });

        // Sale attachments for CRM/accounting. Additive only.
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('voucher_id')->nullable()->after('member_id')->constrained()->nullOnDelete();
            $table->integer('points_redeemed')->default(0)->after('discount_total');
            $table->integer('points_value')->default(0)->after('points_redeemed');
            $table->integer('points_earned')->default(0)->after('points_value');
            $table->integer('voucher_discount')->default(0)->after('points_earned');
            $table->integer('cogs_total')->default(0)->after('voucher_discount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['member_id', 'voucher_id', 'points_redeemed', 'points_value', 'points_earned', 'voucher_discount', 'cogs_total']);
        });
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('voucher_usages');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('point_ledgers');
        Schema::dropIfExists('members');
    }
};
