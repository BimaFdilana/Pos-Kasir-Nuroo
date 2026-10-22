<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $fillable = [
        'tenant_id', 'code', 'discount_type', 'value',
        'valid_from', 'valid_to', 'max_uses', 'uses_count', 'is_active',
    ];
    protected $casts = [
        'value' => 'integer', 'max_uses' => 'integer', 'uses_count' => 'integer',
        'is_active' => 'boolean', 'valid_from' => 'date', 'valid_to' => 'date',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function usages(): HasMany { return $this->hasMany(VoucherUsage::class); }

    /** Discount for a given subtotal, before any other discount is applied. */
    public function discountFor(int $subtotal): int
    {
        if ($this->discount_type === 'percent') {
            return (int) round($subtotal * $this->value / 100);
        }

        return min($this->value, $subtotal);
    }

    public function remainingUses(): ?int
    {
        return $this->max_uses === null ? null : max(0, $this->max_uses - $this->uses_count);
    }
}
