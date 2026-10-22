<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherUsage extends Model
{
    protected $fillable = ['tenant_id', 'voucher_id', 'sale_id', 'user_id', 'status'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function voucher(): BelongsTo { return $this->belongsTo(Voucher::class); }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
}
