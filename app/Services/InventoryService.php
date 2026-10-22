<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\IngredientStockMovement;
use App\Models\Opname;
use App\Models\Outlet;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Complete inventory: ingredients, warehouses, recipes, HPP, movements,
 * transfers, opname, and the recipe consumption side of a sale.
 *
 * Quantities are decimal (grams/ml/pcs need fractions); money stays
 * integer rupiah. HPP is always computed from current recipe quantities
 * and ingredient costs — it is never stored as a price override, so the
 * selling price of a variant is untouched by inventory.
 *
 * Every stock change is a movement row with actor + reason, and a change
 * that would make stock negative is rejected (422) instead of applied.
 * Sale consumption runs inside the sale's own DB transaction, so a
 * failed sale decrements nothing.
 */
class InventoryService
{
    /** Warehouse serving an outlet: its own warehouse, else the tenant default, else the first. */
    public function warehouseForOutlet(Tenant $tenant, int $outletId): ?Warehouse
    {
        return Warehouse::where('tenant_id', $tenant->id)
                ->where('outlet_id', $outletId)->orderBy('id')->first()
            ?? Warehouse::where('tenant_id', $tenant->id)
                ->where('is_default', true)->orderBy('id')->first()
            ?? Warehouse::where('tenant_id', $tenant->id)->orderBy('id')->first();
    }

    /** HPP (cost of goods) for one serving of a variant, or null when it has no recipe. */
    public function hppForVariant(ProductVariant $variant): ?int
    {
        $recipes = Recipe::where('product_variant_id', $variant->id)->with('ingredient')->get();
        if ($recipes->isEmpty()) {
            return null;
        }

        return (int) round($recipes->sum(fn (Recipe $r) => $r->quantity * $r->ingredient->cost_per_unit));
    }

    /**
     * Apply one stock change. Caller must already be inside a transaction
     * when atomicity with something else (sale, transfer, opname) matters.
     */
    public function applyMovement(
        User $user,
        Warehouse $warehouse,
        Ingredient $ingredient,
        string $type,
        float $change,
        string $reason,
        ?int $saleId = null,
        ?int $opnameId = null,
        ?string $transferGroup = null,
    ): IngredientStockMovement {
        if ($warehouse->tenant_id !== $ingredient->tenant_id || $warehouse->tenant_id !== $user->tenant_id) {
            throw ValidationException::withMessages(['warehouse_id' => 'Warehouse and ingredient belong to different tenants.']);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for every stock movement.']);
        }
        $change = round($change, 3);
        if (abs($change) < 0.0005) {
            throw ValidationException::withMessages(['quantity' => 'Movement quantity must not be zero.']);
        }

        $stock = IngredientStock::where('warehouse_id', $warehouse->id)
            ->where('ingredient_id', $ingredient->id)
            ->lockForUpdate()
            ->first();

        $current = $stock ? (float) $stock->quantity : 0.0;
        $after = round($current + $change, 3);
        if ($after < -0.0005) {
            throw ValidationException::withMessages([
                'quantity' => "Stock of {$ingredient->name} would go negative ({$current} + {$change}).",
            ]);
        }
        $after = max(0.0, $after);

        if (! $stock) {
            $stock = IngredientStock::create([
                'warehouse_id' => $warehouse->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => $after,
            ]);
        } else {
            $stock->update(['quantity' => $after]);
        }

        return IngredientStockMovement::create([
            'tenant_id' => $user->tenant_id,
            'warehouse_id' => $warehouse->id,
            'ingredient_id' => $ingredient->id,
            'user_id' => $user->id,
            'sale_id' => $saleId,
            'opname_id' => $opnameId,
            'type' => $type,
            'quantity_change' => $change,
            'quantity_after' => $after,
            'reason' => $reason,
            'transfer_group' => $transferGroup,
        ]);
    }

    /** Move stock between two warehouses of the same tenant, as a paired out/in. */
    public function transfer(User $user, Warehouse $source, Warehouse $destination, Ingredient $ingredient, float $quantity, string $reason): string
    {
        if ($source->id === $destination->id) {
            throw ValidationException::withMessages(['destination_warehouse_id' => 'Source and destination warehouses must differ.']);
        }
        $quantity = round($quantity, 3);
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Transfer quantity must be positive.']);
        }

        $group = (string) Str::uuid();

        DB::transaction(function () use ($user, $source, $destination, $ingredient, $quantity, $reason, $group) {
            $this->applyMovement($user, $source, $ingredient, 'transfer_out', -$quantity, $reason, null, null, $group);
            $this->applyMovement($user, $destination, $ingredient, 'transfer_in', $quantity, $reason, null, null, $group);
        });

        return $group;
    }

    /**
     * Record a stock count. The opname row is the history; when the counted
     * quantity differs from system stock, the difference is applied as an
     * 'opname' movement so system stock becomes the counted quantity.
     * Counted quantity is >= 0, so applying a difference can never go negative.
     */
    public function recordOpname(User $user, Warehouse $warehouse, Ingredient $ingredient, float $counted, ?string $note): Opname
    {
        $counted = round($counted, 3);

        return DB::transaction(function () use ($user, $warehouse, $ingredient, $counted, $note) {
            $stock = IngredientStock::where('warehouse_id', $warehouse->id)
                ->where('ingredient_id', $ingredient->id)
                ->lockForUpdate()
                ->first();
            $system = $stock ? (float) $stock->quantity : 0.0;
            $difference = round($counted - $system, 3);

            $opname = Opname::create([
                'tenant_id' => $user->tenant_id,
                'warehouse_id' => $warehouse->id,
                'ingredient_id' => $ingredient->id,
                'user_id' => $user->id,
                'system_quantity' => $system,
                'counted_quantity' => $counted,
                'difference' => $difference,
                'note' => $note,
            ]);

            if (abs($difference) >= 0.0005) {
                $this->applyMovement(
                    $user, $warehouse, $ingredient, 'opname', $difference,
                    $note ? "Opname #{$opname->id}: {$note}" : "Opname #{$opname->id}",
                    null, $opname->id,
                );
            }

            return $opname;
        });
    }

    /**
     * Decrement recipe ingredients for a sale. Must be called inside the
     * sale transaction (SaleService does this). Needs are aggregated per
     * ingredient and checked before anything is decremented, so a sale
     * with one insufficient ingredient fails wholly. Variants without a
     * recipe are untouched.
     *
     * @param  array<int, array{variant: ProductVariant, quantity: int}>  $preparedItems
     */
    public function decrementForSale(User $user, Outlet $outlet, Sale $sale, array $preparedItems): void
    {
        $needs = []; // ingredient_id => ['ingredient' => Ingredient, 'quantity' => float]
        foreach ($preparedItems as $row) {
            $recipes = Recipe::where('product_variant_id', $row['variant']->id)->with('ingredient')->get();
            foreach ($recipes as $recipe) {
                $id = $recipe->ingredient_id;
                $needs[$id] ??= ['ingredient' => $recipe->ingredient, 'quantity' => 0.0];
                $needs[$id]['quantity'] = round($needs[$id]['quantity'] + $recipe->quantity * $row['quantity'], 3);
            }
        }
        if (empty($needs)) {
            return;
        }

        $warehouse = $this->warehouseForOutlet($user->tenant, $outlet->id);
        if (! $warehouse) {
            throw ValidationException::withMessages([
                'items' => 'A recipe product was sold but this tenant has no warehouse to consume ingredients from.',
            ]);
        }

        // Lock in a fixed order, then validate everything before decrementing.
        ksort($needs);
        $stocks = [];
        foreach ($needs as $id => $need) {
            $stock = IngredientStock::where('warehouse_id', $warehouse->id)
                ->where('ingredient_id', $id)->lockForUpdate()->first();
            $available = $stock ? (float) $stock->quantity : 0.0;
            if ($available + 0.0005 < $need['quantity']) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient ingredient stock for {$need['ingredient']->name} ({$available} available, {$need['quantity']} needed).",
                ]);
            }
            $stocks[$id] = $stock;
        }

        foreach ($needs as $id => $need) {
            $this->applyMovement(
                $user, $warehouse, $need['ingredient'], 'sale', -$need['quantity'],
                'Sale '.$sale->sale_number, $sale->id,
            );
        }
    }

    /** Restore recipe ingredients when a sale is voided/refunded. Best-effort per warehouse: skips when no warehouse exists. */
    public function restoreForSale(User $supervisor, Sale $sale): void
    {
        $prepared = [];
        foreach ($sale->items as $item) {
            $variant = ProductVariant::find($item->product_variant_id);
            if ($variant) {
                $prepared[] = ['variant' => $variant, 'quantity' => (int) $item->quantity];
            }
        }
        $needs = [];
        foreach ($prepared as $row) {
            foreach (Recipe::where('product_variant_id', $row['variant']->id)->with('ingredient')->get() as $recipe) {
                $needs[$recipe->ingredient_id] ??= ['ingredient' => $recipe->ingredient, 'quantity' => 0.0];
                $needs[$recipe->ingredient_id]['quantity'] = round($needs[$recipe->ingredient_id]['quantity'] + $recipe->quantity * $row['quantity'], 3);
            }
        }
        if (empty($needs)) {
            return;
        }

        $warehouse = $this->warehouseForOutlet($sale->tenant, $sale->outlet_id);
        if (! $warehouse) {
            return;
        }

        foreach ($needs as $need) {
            $this->applyMovement(
                $supervisor, $warehouse, $need['ingredient'], 'sale_reversal', $need['quantity'],
                'Reversal of sale '.$sale->sale_number, $sale->id,
            );
        }
    }
}
