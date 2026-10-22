<?php

namespace App\Services;

use App\Models\Member;
use App\Models\PointLedger;
use App\Models\Sale;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Validation\ValidationException;

/**
 * CRM rules, kept deliberately simple and stated exactly here:
 *
 * - Points earned: 1 point per Rp 10.000 of the FINAL sale total
 *   (after all discounts, plus tax when set), integer division, no
 *   fractional points. A Rp 25.000 sale earns 2 points.
 * - Points value: Rp 100 per point when redeemed.
 * - Redemption: requesting more points than the member's ledger
 *   balance is rejected (422). The redeemed value is capped at the
 *   amount left after manual discount and voucher discount, so a
 *   redemption can never make the sale total negative; when the cap
 *   binds, fewer points than requested are actually redeemed.
 * - Voucher: at most one voucher per sale, percent or fixed rupiah,
 *   validated for active flag, date window, and remaining uses.
 * - Void/refund reverses everything: earned points are taken back,
 *   redeemed points are restored, and the voucher usage is released
 *   so the voucher can be used again.
 *
 * All ledger/usage writes happen inside the sale's own DB transaction
 * (SaleService calls these), so a failed sale records nothing.
 */
class CrmService
{
    public const RUPIAH_PER_POINT = 10000;
    public const POINT_VALUE = 100;

    public function pointsForTotal(int $total): int
    {
        return intdiv(max(0, $total), self::RUPIAH_PER_POINT);
    }

    public function balanceFor(Member $member): int
    {
        return (int) PointLedger::where('member_id', $member->id)->sum('points');
    }

    /** Resolve a member of this tenant, locking the row for the balance check. */
    public function memberFor(User $user, ?int $memberId): ?Member
    {
        if (! $memberId) {
            return null;
        }

        return Member::where('tenant_id', $user->tenant_id)->lockForUpdate()->findOrFail($memberId);
    }

    /**
     * Validate a voucher code and return [voucher, discount] for a subtotal.
     * Caller must already be inside the sale transaction; the voucher row
     * is locked so two concurrent sales cannot both take the last use.
     */
    public function voucherFor(User $user, ?string $code, int $subtotal): array
    {
        if (! $code) {
            return [null, 0];
        }

        $voucher = Voucher::where('tenant_id', $user->tenant_id)
            ->where('code', $code)->lockForUpdate()->first();
        if (! $voucher) {
            throw ValidationException::withMessages(['voucher_code' => 'Voucher code is not recognised.']);
        }

        if (! $voucher->is_active) {
            throw ValidationException::withMessages(['voucher_code' => 'Voucher is not active.']);
        }
        $today = today();
        if ($voucher->valid_from && $today->lt($voucher->valid_from)) {
            throw ValidationException::withMessages(['voucher_code' => 'Voucher is not valid yet.']);
        }
        if ($voucher->valid_to && $today->gt($voucher->valid_to)) {
            throw ValidationException::withMessages(['voucher_code' => 'Voucher has expired.']);
        }
        if ($voucher->max_uses !== null && $voucher->uses_count >= $voucher->max_uses) {
            throw ValidationException::withMessages(['voucher_code' => 'Voucher uses are exhausted.']);
        }

        return [$voucher, $voucher->discountFor($subtotal)];
    }

    /**
     * Work out the actual redemption. Requesting more than the balance
     * is an error; exceeding the remaining sale amount only caps the
     * redemption (fewer points are spent, the rest stay on the member).
     *
     * @return array{points:int, value:int}
     */
    public function redemptionFor(Member $member, int $requested, int $remaining): array
    {
        if ($requested <= 0) {
            return ['points' => 0, 'value' => 0];
        }

        $balance = $this->balanceFor($member);
        if ($requested > $balance) {
            throw ValidationException::withMessages([
                'redeem_points' => "Member has {$balance} points, cannot redeem {$requested}.",
            ]);
        }

        $capped = min($requested, intdiv(max(0, $remaining), self::POINT_VALUE));

        return ['points' => $capped, 'value' => $capped * self::POINT_VALUE];
    }

    /** Ledger entries + voucher usage for a completed sale. */
    public function recordForSale(User $user, Sale $sale, ?Member $member, ?Voucher $voucher): void
    {
        if ($member && $sale->points_redeemed > 0) {
            PointLedger::create([
                'tenant_id' => $sale->tenant_id, 'member_id' => $member->id,
                'sale_id' => $sale->id, 'user_id' => $user->id,
                'type' => 'redeem', 'points' => -$sale->points_redeemed,
                'reason' => 'Redeemed on sale '.$sale->sale_number,
            ]);
        }
        if ($member && $sale->points_earned > 0) {
            PointLedger::create([
                'tenant_id' => $sale->tenant_id, 'member_id' => $member->id,
                'sale_id' => $sale->id, 'user_id' => $user->id,
                'type' => 'earn', 'points' => $sale->points_earned,
                'reason' => 'Earned on sale '.$sale->sale_number,
            ]);
        }
        if ($voucher) {
            VoucherUsage::create([
                'tenant_id' => $sale->tenant_id, 'voucher_id' => $voucher->id,
                'sale_id' => $sale->id, 'user_id' => $user->id, 'status' => 'used',
            ]);
            $voucher->increment('uses_count');
        }
    }

    /** Reverse points and release the voucher when a sale is voided/refunded. */
    public function reverseForSale(User $supervisor, Sale $sale): void
    {
        if ($sale->member_id) {
            if ($sale->points_earned > 0) {
                PointLedger::create([
                    'tenant_id' => $sale->tenant_id, 'member_id' => $sale->member_id,
                    'sale_id' => $sale->id, 'user_id' => $supervisor->id,
                    'type' => 'reversal', 'points' => -$sale->points_earned,
                    'reason' => 'Reversed earned points of sale '.$sale->sale_number,
                ]);
            }
            if ($sale->points_redeemed > 0) {
                PointLedger::create([
                    'tenant_id' => $sale->tenant_id, 'member_id' => $sale->member_id,
                    'sale_id' => $sale->id, 'user_id' => $supervisor->id,
                    'type' => 'reversal', 'points' => $sale->points_redeemed,
                    'reason' => 'Restored redeemed points of sale '.$sale->sale_number,
                ]);
            }
        }

        if ($sale->voucher_id) {
            $usage = VoucherUsage::where('sale_id', $sale->id)
                ->where('voucher_id', $sale->voucher_id)->where('status', 'used')->first();
            if ($usage) {
                $usage->update(['status' => 'released']);
                Voucher::where('id', $sale->voucher_id)->decrement('uses_count');
                // Guard against a count that somehow went negative.
                Voucher::where('id', $sale->voucher_id)->where('uses_count', '<', 0)->update(['uses_count' => 0]);
            }
        }
    }
}
