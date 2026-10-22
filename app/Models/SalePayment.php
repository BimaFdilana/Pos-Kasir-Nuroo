<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    // MVP payment methods only. No card numbers are ever stored here.
    public const METHODS = ['cash', 'edc'];

    protected $fillable = ['sale_id', 'method', 'amount', 'tendered', 'change_amount', 'bank', 'card_type', 'approval_code'];
    protected $casts = ['amount' => 'integer', 'tendered' => 'integer', 'change_amount' => 'integer'];

    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
}
