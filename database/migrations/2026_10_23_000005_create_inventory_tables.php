<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Raw materials. cost_per_unit is integer rupiah per 1 unit
        // (e.g. per gram, per ml, per pcs). Selling price is never
        // derived from cost: HPP is computed, not stored as a price.
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit', 20); // gram | ml | pcs | ...
            $table->integer('cost_per_unit')->default(0);
            $table->decimal('min_stock', 12, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'outlet_id']);
        });

        Schema::create('ingredient_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'ingredient_id']);
        });

        // One recipe line: how much of one ingredient a single serving
        // of a product variant consumes.
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->timestamps();
            $table->unique(['product_variant_id', 'ingredient_id']);
        });

        Schema::create('ingredient_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opname_id')->nullable()->constrained()->nullOnDelete();
            // purchase | adjustment | transfer_out | transfer_in | opname | sale | sale_reversal
            $table->string('type');
            $table->decimal('quantity_change', 12, 3);
            $table->decimal('quantity_after', 12, 3);
            $table->text('reason');
            $table->uuid('transfer_group')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'warehouse_id', 'ingredient_id']);
        });

        Schema::create('opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->nullOnDelete();
            $table->decimal('system_quantity', 12, 3);
            $table->decimal('counted_quantity', 12, 3);
            $table->decimal('difference', 12, 3);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'warehouse_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opnames');
        Schema::dropIfExists('ingredient_stock_movements');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('ingredient_stocks');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('ingredients');
    }
};
