<?php

namespace App\Services;

use App\Models\OutletStock;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleAudit;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single place where a sale is created, voided or refunded.
 * All stock and audit side-effects happen inside the same DB transaction
 * as the sale itself, so a failed sale can never half-decrement stock.
 *
 * Money is integer rupiah. Payment methods in MVP: cash, edc (manual).
 * No card numbers are accepted or stored anywhere in this service.
 */
class SaleService
{
    /**
     * @param  array{outlet_id:int, items:array, payments:array, client_reference?:string|null, discount_total?:int, tax_total?:int, notes?:string|null, member_id?:int|null, redeem_points?:int, voucher_code?:string|null}  $data
     */
    public function createSale(User $user, array $data): Sale
    {
        if (! $user->tenant_id) {
            throw ValidationException::withMessages(['user' => 'User is not assigned to a tenant.']);
        }

        // Idempotency: a retried mobile sync with the same client_reference
        // returns the original sale instead of creating a duplicate.
        if (! empty($data['client_reference'])) {
            $existing = Sale::where('tenant_id', $user->tenant_id)
                ->where('client_reference', $data['client_reference'])
                ->with(['items', 'payments'])
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($user, $data) {
            $outlet = $user->tenant->outlets()->findOrFail($data['outlet_id']);

            // Cashiers locked to one outlet may only sell from that outlet.
            if ($user->outlet_id && $user->outlet_id !== (int) $data['outlet_id']) {
                throw ValidationException::withMessages(['outlet_id' => 'Cashier is not assigned to this outlet.']);
            }

            $subtotal = 0;
            $preparedItems = [];

            foreach ($data['items'] as $item) {
                $variant = ProductVariant::whereHas('product', fn ($q) => $q->where('tenant_id', $user->tenant_id))
                    ->where('is_active', true)
                    ->findOrFail($item['product_variant_id']);

                $quantity = (int) $item['quantity'];
                if ($quantity < 1) {
                    throw ValidationException::withMessages(['items' => 'Quantity must be at least 1.']);
                }

                // Lock the stock row so two concurrent sales cannot oversell.
                $stock = OutletStock::where('outlet_id', $outlet->id)
                    ->where('product_variant_id', $variant->id)
                    ->lockForUpdate()
                    ->first();

                if (! $stock || $stock->quantity < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for {$variant->product->name} ({$variant->name}).",
                    ]);
                }

                $line = $variant->price * $quantity;
                $subtotal += $line;
                $preparedItems[] = [
                    'variant' => $variant,
                    'stock' => $stock,
                    'quantity' => $quantity,
                    'line' => $line,
                ];
            }

            $manualDiscount = (int) ($data['discount_total'] ?? 0);
            $tax = (int) ($data['tax_total'] ?? 0);

            // CRM attachments, validated before any row is written, in
            // this same transaction. discount_total stored on the sale
            // is the sum of manual + voucher + points discounts.
            $crm = app(CrmService::class);
            $member = $crm->memberFor($user, $data['member_id'] ?? null);
            if (! $member && ! empty($data['redeem_points'])) {
                throw ValidationException::withMessages(['redeem_points' => 'A member is required to redeem points.']);
            }
            [$voucher, $voucherDiscount] = $crm->voucherFor($user, $data['voucher_code'] ?? null, $subtotal);
            $remaining = max(0, $subtotal - $manualDiscount - $voucherDiscount);
            $redemption = $member
                ? $crm->redemptionFor($member, (int) ($data['redeem_points'] ?? 0), $remaining)
                : ['points' => 0, 'value' => 0];
            $discount = $manualDiscount + $voucherDiscount + $redemption['value'];
            $total = max(0, $subtotal - $discount + $tax);
            $pointsEarned = $crm->pointsForTotal($total);

            // COGS from recipes, for the journal entry. Variants without
            // a recipe contribute 0; the selling price is untouched.
            $inventory = app(InventoryService::class);
            $cogs = 0;
            foreach ($preparedItems as $row) {
                $hpp = $inventory->hppForVariant($row['variant']);
                if ($hpp !== null) {
                    $cogs += $hpp * $row['quantity'];
                }
            }

            $payments = $this->preparePayments($data['payments'] ?? [], $total);

            $sale = Sale::create([
                'tenant_id' => $user->tenant_id,
                'outlet_id' => $outlet->id,
                'user_id' => $user->id,
                'member_id' => $member?->id,
                'voucher_id' => $voucher?->id,
                'client_reference' => $data['client_reference'] ?? null,
                'status' => 'completed',
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'tax_total' => $tax,
                'total' => $total,
                'paid_total' => $total,
                'change_total' => $payments['change_total'],
                'notes' => $data['notes'] ?? null,
                'points_redeemed' => $redemption['points'],
                'points_value' => $redemption['value'],
                'points_earned' => $pointsEarned,
                'voucher_discount' => $voucherDiscount,
                'cogs_total' => $cogs,
            ]);
            $sale->update(['sale_number' => 'SLS-'.$outlet->id.'-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($preparedItems as $row) {
                $sale->items()->create([
                    'product_variant_id' => $row['variant']->id,
                    'product_name' => $row['variant']->product->name,
                    'variant_name' => $row['variant']->name,
                    'unit_price' => $row['variant']->price,
                    'quantity' => $row['quantity'],
                    'subtotal' => $row['line'],
                ]);

                $row['stock']->decrement('quantity', $row['quantity']);
                StockMovement::create([
                    'tenant_id' => $user->tenant_id,
                    'outlet_id' => $outlet->id,
                    'product_variant_id' => $row['variant']->id,
                    'sale_id' => $sale->id,
                    'user_id' => $user->id,
                    'type' => 'sale',
                    'quantity_change' => -$row['quantity'],
                    'quantity_after' => $row['stock']->fresh()->quantity,
                    'reason' => 'Sale '.$sale->sale_number,
                ]);
            }

            // Recipe consumption: variants with a recipe decrement their
            // ingredients from the outlet's warehouse, inside this same
            // transaction. Variants without a recipe are untouched.
            app(InventoryService::class)->decrementForSale($user, $outlet, $sale, $preparedItems);

            foreach ($payments['payments'] as $payment) {
                $sale->payments()->create($payment);
            }

            // Loyalty ledger + voucher usage + journal entry, all in
            // this same transaction as the sale itself.
            $crm->recordForSale($user, $sale, $member, $voucher);
            app(AccountingService::class)->recordForSale($sale->fresh(['payments']));

            return $sale->fresh(['items', 'payments']);
        });
    }

    /**
     * @return array{payments:array, change_total:int}
     */
    protected function preparePayments(array $payments, int $total): array
    {
        // A sale fully covered by points/voucher discount has total 0
        // and needs no payment row at all.
        if ($total === 0 && empty($payments)) {
            return ['payments' => [], 'change_total' => 0];
        }

        if (empty($payments)) {
            throw ValidationException::withMessages(['payments' => 'At least one payment is required.']);
        }

        $paid = 0;
        $changeTotal = 0;
        $prepared = [];

        foreach ($payments as $payment) {
            $method = $payment['method'] ?? null;
            if (! in_array($method, ['cash', 'edc'], true)) {
                throw ValidationException::withMessages(['payments' => 'Only cash and EDC payments are supported in MVP.']);
            }

            $amount = (int) ($payment['amount'] ?? 0);
            if ($amount < 1) {
                throw ValidationException::withMessages(['payments' => 'Payment amount must be positive.']);
            }

            $row = ['method' => $method, 'amount' => $amount];

            if ($method === 'cash') {
                $tendered = (int) ($payment['tendered'] ?? $amount);
                if ($tendered < $amount) {
                    throw ValidationException::withMessages(['payments' => 'Cash tendered is less than the cash amount.']);
                }
                $row['tendered'] = $tendered;
                $row['change_amount'] = $tendered - $amount;
                $changeTotal += $row['change_amount'];
            } else {
                // EDC manual: bank + card type recorded, approval code from slip.
                // Full card numbers must never be sent here.
                $row['bank'] = $payment['bank'] ?? null;
                $row['card_type'] = $payment['card_type'] ?? null;
                if ($row['card_type'] && ! in_array($row['card_type'], ['debit', 'credit'], true)) {
                    throw ValidationException::withMessages(['payments' => 'Card type must be debit or credit.']);
                }
                $row['approval_code'] = $payment['approval_code'] ?? null;
            }

            $paid += $amount;
            $prepared[] = $row;
        }

        if ($paid !== $total) {
            throw ValidationException::withMessages([
                'payments' => "Payments total (Rp {$paid}) must equal sale total (Rp {$total}).",
            ]);
        }

        return ['payments' => $prepared, 'change_total' => $changeTotal];
    }

    public function voidSale(User $supervisor, Sale $sale, ?string $reason): Sale
    {
        return $this->reverseSale($supervisor, $sale, 'void', $reason);
    }

    public function refundSale(User $supervisor, Sale $sale, ?string $reason): Sale
    {
        // MVP refund is a full refund. Partial refunds are a later feature.
        return $this->reverseSale($supervisor, $sale, 'refund', $reason);
    }

    protected function reverseSale(User $supervisor, Sale $sale, string $action, ?string $reason): Sale
    {
        if (! $supervisor->isSupervisor()) {
            throw ValidationException::withMessages(['role' => 'Only owner or manager may void or refund a sale.']);
        }
        if ($supervisor->tenant_id !== $sale->tenant_id) {
            throw ValidationException::withMessages(['sale' => 'Sale belongs to a different tenant.']);
        }
        if (! $sale->isCompleted()) {
            throw ValidationException::withMessages(['sale' => 'Only a completed sale can be voided or refunded.']);
        }
        if (empty($reason)) {
            throw ValidationException::withMessages(['reason' => 'A reason is required for void/refund.']);
        }

        return DB::transaction(function () use ($supervisor, $sale, $action, $reason) {
            $totalBefore = $sale->total;

            foreach ($sale->items as $item) {
                $stock = OutletStock::where('outlet_id', $sale->outlet_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->increment('quantity', $item->quantity);
                    StockMovement::create([
                        'tenant_id' => $sale->tenant_id,
                        'outlet_id' => $sale->outlet_id,
                        'product_variant_id' => $item->product_variant_id,
                        'sale_id' => $sale->id,
                        'user_id' => $supervisor->id,
                        'type' => 'sale_reversal',
                        'quantity_change' => $item->quantity,
                        'quantity_after' => $stock->fresh()->quantity,
                        'reason' => ucfirst($action).' of '.$sale->sale_number.': '.$reason,
                    ]);
                }
            }

            // Restore recipe ingredients consumed by this sale, if any.
            app(InventoryService::class)->restoreForSale($supervisor, $sale);

            // Reverse loyalty points, release the voucher, and write the
            // reversing journal entry — same transaction as the void.
            app(CrmService::class)->reverseForSale($supervisor, $sale);
            app(AccountingService::class)->reverseForSale($supervisor, $sale);

            $sale->update([
                'status' => $action === 'void' ? 'voided' : 'refunded',
                'voided_by' => $supervisor->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            SaleAudit::create([
                'sale_id' => $sale->id,
                'action' => $action,
                'actor_user_id' => $supervisor->id,
                'approver_user_id' => $supervisor->id,
                'reason' => $reason,
                'total_before' => $totalBefore,
                'total_after' => 0,
                'payload' => ['sale_number' => $sale->sale_number],
            ]);

            return $sale->fresh(['items', 'payments', 'audits']);
        });
    }
}
