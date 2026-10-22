<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'tenant_id', 'outlet_id', 'user_id', 'member_id', 'voucher_id', 'sale_number', 'client_reference',
        'status', 'subtotal', 'discount_total', 'tax_total', 'total',
        'paid_total', 'change_total', 'notes',
        'points_redeemed', 'points_value', 'points_earned', 'voucher_discount', 'cogs_total',
        'voided_by', 'voided_at', 'void_reason',
    ];

    protected $casts = [
        'subtotal' => 'integer', 'discount_total' => 'integer', 'tax_total' => 'integer',
        'total' => 'integer', 'paid_total' => 'integer', 'change_total' => 'integer',
        'points_redeemed' => 'integer', 'points_value' => 'integer', 'points_earned' => 'integer',
        'voucher_discount' => 'integer', 'cogs_total' => 'integer',
        'voided_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function voucher(): BelongsTo { return $this->belongsTo(Voucher::class); }
    public function pointLedgers(): HasMany { return $this->hasMany(PointLedger::class); }
    public function journalEntries(): HasMany { return $this->hasMany(JournalEntry::class); }
    public function items(): HasMany { return $this->hasMany(SaleItem::class); }
    public function payments(): HasMany { return $this->hasMany(SalePayment::class); }
    public function audits(): HasMany { return $this->hasMany(SaleAudit::class); }
    public function stockMovements(): HasMany { return $this->hasMany(StockMovement::class); }

    public function isCompleted(): bool { return $this->status === 'completed'; }
}
